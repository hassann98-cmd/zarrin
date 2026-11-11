import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import vm from "node:vm";
import { JSDOM } from "jsdom";

const [mobileNavSource, floatingNavSource, phpSource, authScript] = await Promise.all([
  readFile(new URL("../src/islands/MobileNav.js", import.meta.url), "utf8"),
  readFile(new URL("../src/islands/FloatingMobileNav.js", import.meta.url), "utf8"),
  readFile(new URL("../functions.php", import.meta.url), "utf8"),
  readFile(new URL("../assets/js/mobile-auth-status.js", import.meta.url), "utf8"),
]);

function accountLink(document, id) {
  const link = document.createElement("a");
  link.id = id;
  link.href = "/store/login/";
  link.setAttribute("data-jluxe-account-link", "");
  link.setAttribute("data-jluxe-account-login-href", "/store/login/");
  link.setAttribute("data-jluxe-account-dashboard-href", "/store/members/");
  link.setAttribute("data-jluxe-account-logged-label", "حساب کاربری");
  const label = document.createElement("span");
  label.setAttribute("data-jluxe-account-label", "");
  label.textContent = "ورود";
  link.append(label);
  document.body.append(link);
  return link;
}

function runAuthScript(dom, fetch, extraWindow = {}) {
  const navigations = [];
  const window = {
    jluxeMobileAuthSettings: { sessionUrl: "https://shop.test/wp-admin/admin-ajax.php" },
    JLuxeThemeSettings: { auth: { isLoggedIn: false } },
    fetch,
    location: { assign: (href) => navigations.push(href) },
    ...extraWindow,
  };
  vm.runInNewContext(authScript, { window, document: dom.window.document, Promise });
  return { window, navigations };
}

test("R352 fresh private session replaces cached guest auth in both mobile account links", async () => {
  const dom = new JSDOM("<!doctype html><body></body>", { url: "https://shop.test/store/" });
  const links = [accountLink(dom.window.document, "classic"), accountLink(dom.window.document, "floating")];
  const requests = [];
  const { window } = runAuthScript(dom, async (url, options) => {
    requests.push({ url, options });
    return {
      ok: true,
      json: async () => ({ success: true, data: { auth: { isLoggedIn: true, userId: 42 } } }),
    };
  });

  assert.equal(await window.jluxeMobileAuthStatusPromise, true);
  assert.equal(window.jluxeCurrentAuthStatus, true);
  assert.equal(window.JLuxeThemeSettings.auth.isLoggedIn, true);
  for (const link of links) {
    assert.equal(link.getAttribute("href"), "/store/members/");
    assert.equal(link.querySelector("[data-jluxe-account-label]").textContent, "حساب کاربری");
  }
  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, "https://shop.test/wp-admin/admin-ajax.php");
  assert.equal(requests[0].options.method, "POST");
  assert.equal(requests[0].options.credentials, "same-origin");
  assert.equal(requests[0].options.cache, "no-store");
  assert.equal(requests[0].options.body, "action=jluxe_session");
  dom.window.close();
});

test("R352 older WebViews without fetch retain the same-origin XMLHttpRequest fallback", async () => {
  const dom = new JSDOM("<!doctype html><body></body>", { url: "https://shop.test/store/" });
  let request;
  class LegacyRequest {
    open(method, url, async) { request = { method, url, async, headers: {}, instance: this }; }
    setRequestHeader(name, value) { request.headers[name] = value; }
    send(body) {
      request.body = body;
      this.status = 200;
      this.readyState = 4;
      this.responseText = JSON.stringify({ success: true, data: { auth: { isLoggedIn: true } } });
      this.onreadystatechange();
    }
  }
  const { window } = runAuthScript(dom, undefined, { XMLHttpRequest: LegacyRequest });
  assert.equal(await window.jluxeMobileAuthStatusPromise, true);
  assert.equal(request.method, "POST");
  assert.equal(request.url, "https://shop.test/wp-admin/admin-ajax.php");
  assert.equal(request.async, true);
  assert.equal(request.instance.withCredentials, true);
  assert.equal(request.body, "action=jluxe_session");
  assert.equal(request.headers["Cache-Control"], "no-store");
  dom.window.close();
});

test("R352 an account tap during session verification waits and navigates to the dashboard", async () => {
  const dom = new JSDOM("<!doctype html><body></body>", { url: "https://shop.test/store/" });
  const link = accountLink(dom.window.document, "classic");
  let finishRequest;
  const { window, navigations } = runAuthScript(
    dom,
    () => new Promise((resolve) => { finishRequest = resolve; }),
  );
  const click = new dom.window.MouseEvent("click", {
    bubbles: true,
    cancelable: true,
    button: 0,
  });
  link.dispatchEvent(click);
  assert.equal(click.defaultPrevented, true, "do not follow cached guest href while checking the cookie session");

  finishRequest({
    ok: true,
    json: async () => ({ success: true, data: { auth: { isLoggedIn: true, userId: 42 } } }),
  });
  assert.equal(await window.jluxeMobileAuthStatusPromise, true);
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.deepEqual(navigations, ["/store/members/"]);
  assert.equal(link.getAttribute("href"), "/store/members/");
  dom.window.close();
});

test("R352 both mobile docks publish account routes and the theme enqueues the private-session synchronizer early", () => {
  for (const source of [mobileNavSource, floatingNavSource]) {
    assert.match(source, /jluxeCurrentAuthStatus/);
    assert.match(source, /data-jluxe-account-login-href/);
    assert.match(source, /data-jluxe-account-dashboard-href/);
    assert.match(source, /data-jluxe-account-label/);
  }
  assert.match(phpSource, /jluxe_enqueue_mobile_auth_status/);
  assert.match(phpSource, /mobile-auth-status\.js/);
  assert.match(phpSource, /wp_localize_script\([\s\S]*?jluxeMobileAuthSettings[\s\S]*?admin_url\( 'admin-ajax\.php' \)/);
  assert.match(authScript, /document\.addEventListener\("click", handleAccountClick, true\)/);
});
