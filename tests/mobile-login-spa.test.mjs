import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";
import { setupAuthNavigation } from "../src/lib/auth-navigation.js";

for (const pending of [false, true]) {
  test(`mobile session capture preserves the React login route (pending=${pending})`, async () => {
    const dom = new JSDOM('<a id="account" href="/login/" data-jluxe-account-link data-jluxe-account-login-href="/login/" data-jluxe-account-dashboard-href="/members/">ورود</a>', {
      url: "https://shop.test/", runScripts: "outside-only",
    });
    const win = dom.window;
    win.JLuxeThemeSettings = { auth: { isLoggedIn: false }, urls: { login: "https://shop.test/login/", home: "https://shop.test/" } };
    win.jluxeMobileAuthSettings = { sessionUrl: "/session" };
    let finish;
    win.fetch = () => new Promise(resolve => { finish = () => resolve({ ok: true, json: async () => ({ success: true, data: { auth: { isLoggedIn: false } } }) }); });
    const renders = [];
    const dispose = setupAuthNavigation(win, { renderAuth: (_node, open) => renders.push(open) });
    win.eval(fs.readFileSync(new URL('../assets/js/mobile-auth-status.js', import.meta.url), 'utf8'));
    try {
      if (!pending) { finish(); await win.jluxeMobileAuthStatusPromise; }
      win.document.getElementById("account").click();
      if (pending) { finish(); await win.jluxeMobileAuthStatusPromise; }
      await new Promise(resolve => setTimeout(resolve, 0));
      assert.equal(win.location.pathname, "/login/");
      assert.equal(win.history.state.__jluxeAuthRoute, true);
      assert.equal(renders.at(-1), true);
      win.history.replaceState(null, "", "/");
      win.dispatchEvent(new win.PopStateEvent("popstate"));
      assert.equal(renders.at(-1), false);
      assert.equal(win.document.querySelector('[data-jluxe-auth-route]').hidden, true);
    } finally { dispose(); win.close(); }
  });
}
