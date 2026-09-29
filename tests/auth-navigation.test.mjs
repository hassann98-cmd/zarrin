import test from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { setupAuthNavigation } from "../src/lib/auth-navigation.js";

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));
const nextPopState = (window) =>
  new Promise((resolve) => {
    const timeout = setTimeout(resolve, 100);
    window.addEventListener(
      "popstate",
      () => {
        clearTimeout(timeout);
        resolve();
      },
      { once: true },
    );
  });

function makeNavigationDom() {
  const dom = new JSDOM(
    `<!doctype html><html><body style="overflow:auto">
      <main id="storefront"><p>Homepage React state</p>
        <a id="desktop-login" href="/store/customer-zone/">ورود</a>
        <a id="mobile-login" href="/store/customer-zone/"><span>ورود موبایل</span></a>
        <a id="categories" href="/store/categories/">دسته‌بندی‌ها</a>
      </main>
    </body></html>`,
    { url: "https://shop.test/store/", pretendToBeVisual: true },
  );
  const { window } = dom;
  const { document } = window;
  const store = document.getElementById("storefront");
  let mounts = 0;
  let unmounts = 0;
  let moduleLoads = 0;
  const dispose = setupAuthNavigation({
    win: window,
    doc: document,
    loginUrl: "https://shop.test/store/customer-zone/",
    homeUrl: "https://shop.test/store/",
    loadAuth: async () => {
      moduleLoads += 1;
      return { default: function AuthPage() {} };
    },
    mountIsland(element, load) {
      mounts += 1;
      return load().then(() => {
        const dialog = document.createElement("main");
        dialog.setAttribute("role", "dialog");
        dialog.setAttribute("aria-modal", "true");
        const back = document.createElement("a");
        back.href = "/store/";
        back.className = "jluxe-auth-back";
        back.textContent = "بازگشت به فروشگاه";
        dialog.append(back);
        element.replaceChildren(dialog);
        return () => {
          unmounts += 1;
        };
      });
    },
  });
  return {
    dom,
    window,
    document,
    store,
    dispose,
    get mounts() {
      return mounts;
    },
    get unmounts() {
      return unmounts;
    },
    get moduleLoads() {
      return moduleLoads;
    },
  };
}

function click(window, element, options = {}) {
  const event = new window.MouseEvent("click", {
    bubbles: true,
    cancelable: true,
    button: 0,
    ...options,
  });
  element.dispatchEvent(event);
  return event;
}

function clickAndCancelNativeNavigation(window, element, options = {}) {
  let reachedBubblePhase = false;
  const cancelAtBubble = (event) => {
    reachedBubblePhase = true;
    event.preventDefault();
  };
  window.document.addEventListener("click", cancelAtBubble, { once: true });
  const event = click(window, element, options);
  return { event, reachedBubblePhase };
}

test("desktop and mobile login links open a React overlay without replacing the homepage", async () => {
  const app = makeNavigationDom();
  const { window, document, store } = app;
  let mobileReactClick = 0;
  document
    .getElementById("mobile-login")
    .addEventListener("click", () => mobileReactClick++);
  document.getElementById("desktop-login").dispatchEvent(
    new window.FocusEvent("focusin", { bubbles: true }),
  );
  await tick();
  assert.equal(app.moduleLoads, 1, "keyboard focus preloads the login React chunk");

  const desktopEvent = click(window, document.getElementById("desktop-login"));
  assert.equal(desktopEvent.defaultPrevented, true);
  assert.equal(window.location.pathname, "/store/customer-zone/");
  assert.ok(document.getElementById("jluxe-auth-overlay-root"));
  assert.equal(document.body.style.overflow, "hidden");
  assert.equal(store.isConnected, true, "the existing homepage DOM is retained");
  assert.equal(store.getAttribute("aria-hidden"), "true");
  assert.equal(store.hasAttribute("inert"), true);
  await tick();
  assert.equal(app.mounts, 1);
  assert.equal(app.moduleLoads, 1);

  const back = document.querySelector(".jluxe-auth-back");
  const poppedFromBackLink = nextPopState(window);
  const backEvent = click(window, back);
  assert.equal(backEvent.defaultPrevented, true);
  await poppedFromBackLink;
  assert.equal(window.location.pathname, "/store/");
  assert.equal(document.getElementById("jluxe-auth-overlay-root"), null);
  assert.equal(document.body.style.overflow, "auto");
  assert.equal(store.getAttribute("aria-hidden"), null);
  assert.equal(store.hasAttribute("inert"), false);
  assert.equal(app.unmounts, 1);
  assert.equal(store.textContent.includes("Homepage React state"), true);

  const mobileEvent = click(window, document.querySelector("#mobile-login span"));
  assert.equal(mobileEvent.defaultPrevented, true);
  assert.equal(mobileReactClick, 0, "the native navigation handler is stopped before it changes state");
  await tick();
  assert.equal(window.location.pathname, "/store/customer-zone/");
  assert.ok(document.getElementById("jluxe-auth-overlay-root"));
  assert.equal(app.mounts, 2);
  assert.equal(app.moduleLoads, 1, "the login React chunk is reused after its first import");

  const poppedFromBrowserBack = nextPopState(window);
  window.history.back();
  await poppedFromBrowserBack;
  assert.equal(window.location.pathname, "/store/");
  assert.equal(document.getElementById("jluxe-auth-overlay-root"), null);
  assert.equal(store.isConnected, true);
  assert.equal(app.unmounts, 2);

  app.dispose();
  window.close();
});

test("browser forward restores the login overlay; unrelated and modified links stay native", async () => {
  const app = makeNavigationDom();
  const { window, document } = app;
  const category = document.getElementById("categories");
  const categoryClick = clickAndCancelNativeNavigation(window, category);
  assert.equal(categoryClick.reachedBubblePhase, true, "ordinary storefront links are not intercepted");

  const modifiedClick = clickAndCancelNativeNavigation(
    window,
    document.getElementById("desktop-login"),
    { ctrlKey: true },
  );
  assert.equal(modifiedClick.reachedBubblePhase, true, "modified clicks keep their browser behavior");
  assert.equal(document.getElementById("jluxe-auth-overlay-root"), null);

  click(window, document.getElementById("desktop-login"));
  await tick();
  assert.ok(document.getElementById("jluxe-auth-overlay-root"));
  const popped = nextPopState(window);
  window.history.back();
  await popped;
  assert.equal(document.getElementById("jluxe-auth-overlay-root"), null);
  const forwarded = nextPopState(window);
  window.history.forward();
  await forwarded;
  assert.ok(document.getElementById("jluxe-auth-overlay-root"));
  assert.equal(window.location.pathname, "/store/customer-zone/");

  app.dispose();
  window.close();
});
