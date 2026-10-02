import test, { afterEach } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { setupAuthNavigation } from "../src/lib/auth-navigation.js";

const homeUrl = "https://shop.test/store/";
const loginUrl = "https://shop.test/store/customer-zone/";
const disposers = [];

afterEach(() => {
  for (const dispose of disposers.splice(0).reverse()) dispose();
});

function page(url = homeUrl, options = {}) {
  const dom = new JSDOM(
    `<!doctype html><html><body class="${options.bodyClass || ""}">${options.markup || ""}</body></html>`, 
    { url, pretendToBeVisual: true },
  );
  const win = dom.window;
  const renders = [];
  const dispose = setupAuthNavigation(win, {
    loginUrl: options.loginUrl || loginUrl,
    homeUrl: options.homeUrl || homeUrl,
    isLoggedIn: options.isLoggedIn || false,
    renderAuth(container, open) {
      renders.push(open);
      if (open) {
        container.innerHTML = `<main><h1>React login</h1><a href="${options.homeUrl || homeUrl}" data-auth-home>بازگشت به فروشگاه</a></main>`;
      } else {
        container.replaceChildren();
      }
    },
  });
  disposers.push(() => {
    dispose();
    dom.window.close();
  });
  return { win, renders };
}

function click(win, node, init = {}) {
  const event = new win.MouseEvent("click", {
    bubbles: true,
    cancelable: true,
    button: 0,
    ...init,
  });
  node.dispatchEvent(event);
  return event.defaultPrevented;
}

test("desktop and mobile guest-login links open the React screen without document navigation; home returns by history", () => {
  const { win, renders } = page(homeUrl, {
    markup: `<a id="desktop-login" href="${loginUrl}">ورود دسکتاپ</a><a id="mobile-login" href="${loginUrl}">ورود موبایل</a>`,
  });
  const startingUrl = win.location.href;
  const desktop = win.document.getElementById("desktop-login");
  assert.equal(click(win, desktop), true);
  assert.equal(win.location.href, loginUrl);
  assert.equal(win.history.state.__jluxeAuthRoute, true);
  const loginState = win.history.state;
  assert.equal(renders.at(-1), true);
  assert.equal(win.document.querySelector("[data-jluxe-auth-route]").hidden, false);
  assert.equal(win.document.getElementById("desktop-login").inert, true);
  assert.equal(win.document.body.style.overflow, "hidden");

  // Model the browser Back action so this regression test never triggers a
  // JSDOM navigation. The route should restore the existing home document.
  win.history.back = () => {
    win.history.replaceState(null, "", startingUrl);
    win.dispatchEvent(new win.PopStateEvent("popstate", { state: null }));
  };
  assert.equal(click(win, win.document.querySelector("[data-auth-home]")), true);
  assert.equal(win.location.href, startingUrl);
  assert.equal(win.document.querySelector("[data-jluxe-auth-route]").hidden, true);
  assert.equal(win.document.getElementById("desktop-login").inert, false);
  assert.equal(win.document.body.style.overflow, "");
  assert.equal(renders.at(-1), false);
  win.history.forward = () => {
    win.history.replaceState(loginState, "", loginUrl);
    win.dispatchEvent(new win.PopStateEvent("popstate", { state: loginState }));
  };
  win.history.forward();
  assert.equal(win.document.querySelector("[data-jluxe-auth-route]").hidden, false);
  win.history.back();
  assert.equal(win.document.querySelector("[data-jluxe-auth-route]").hidden, true);

  const mobile = win.document.getElementById("mobile-login");
  assert.equal(click(win, mobile), true);
  assert.equal(win.location.href, loginUrl);
  assert.equal(renders.at(-1), true);
  const escape = new win.KeyboardEvent("keydown", {
    bubbles: true,
    cancelable: true,
    key: "Escape",
  });
  win.document.dispatchEvent(escape);
  assert.equal(escape.defaultPrevented, true);
  assert.equal(win.location.href, startingUrl);
  assert.equal(win.document.querySelector("[data-jluxe-auth-route]").hidden, true);
});

test("query-string login routes work, while modified, external, and logged-in clicks keep native behavior", () => {
  const queryLogin = "https://shop.test/store/?page_id=42";
  const { win } = page(homeUrl, {
    loginUrl: queryLogin,
    markup: [
      `<a id="query-login" href="${queryLogin}&redirect_to=%2Fstore%2Fcheckout%2F">login</a>`,
      `<a id="external" href="https://elsewhere.test/store/customer-zone/">external</a>`,
      `<a id="modified" href="${queryLogin}" target="_blank">new tab</a>`,
      `<a id="home" href="${homeUrl}">home</a>`,
    ].join(""),
  });
  assert.equal(click(win, win.document.getElementById("query-login")), true);
  assert.equal(win.location.search, "?page_id=42&redirect_to=%2Fstore%2Fcheckout%2F");
  assert.equal(click(win, win.document.getElementById("external")), false);
  assert.equal(click(win, win.document.getElementById("modified")), false);
  assert.equal(click(win, win.document.getElementById("home"), { ctrlKey: true }), false);
});

test("authenticated users and the standalone React login document are not intercepted", () => {
  const authenticated = page(homeUrl, {
    isLoggedIn: true,
    markup: `<a id="login" href="${loginUrl}">account</a>`,
  });
  assert.equal(click(authenticated.win, authenticated.win.document.getElementById("login")), false);

  const directAuth = page(loginUrl, {
    bodyClass: "jluxe-auth-page",
    markup: `<a id="home" href="${homeUrl}">بازگشت</a>`,
  });
  // A separately initialized auth document retains the server route instead
  // of creating a second soft-overlay auth island.
  const direct = setupAuthNavigation(directAuth.win, {
    loginUrl,
    homeUrl,
    renderAuth() {
      throw new Error("standalone login must not mount a second auth route");
    },
  });
  assert.equal(click(directAuth.win, directAuth.win.document.getElementById("home")), false);
  direct();
});
