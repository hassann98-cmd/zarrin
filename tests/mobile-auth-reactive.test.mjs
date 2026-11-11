import test from 'node:test';
import assert from 'node:assert/strict';
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { JSDOM } from 'jsdom';
import { createServer } from 'vite';

for (const name of ['MobileNav', 'FloatingMobileNav']) {
  test(`${name} reacts to login/logout without reload and retains the account destination on rerender`, async () => {
    const dom = new JSDOM('<div id="root"></div>', { url: 'https://shop.test/store/' });
    const keys = ['window', 'document', 'navigator', 'IS_REACT_ACT_ENVIRONMENT'];
    const saved = keys.map(k => [k, Object.getOwnPropertyDescriptor(globalThis, k)]);
    for (const [key, value] of Object.entries({ window: dom.window, document: dom.window.document, navigator: dom.window.navigator, IS_REACT_ACT_ENVIRONMENT: true })) {
      Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
    }
    dom.window.JLuxeThemeSettings = {
      auth: { isLoggedIn: false },
      urls: { home: 'https://shop.test/store/', login: 'https://shop.test/store/login/', dashboard: 'https://shop.test/store/members/' },
      mobile: {
        nav_items: [{ id: 'account', icon: 'user', label: 'ورود', enabled: true }],
        floating_nav_items: [{ id: 'account', action: 'account', icon: 'user', label: 'ورود', enabled: true }],
      },
    };
    const server = await createServer({ configFile: false, server: { middlewareMode: true, hmr: false, watch: null } });
    const root = createRoot(dom.window.document.getElementById('root'));
    try {
      const { default: Nav } = await server.ssrLoadModule(`/src/islands/${name}.js`);
      await act(async () => root.render(React.createElement(Nav)));
      const link = () => dom.window.document.querySelector('[data-jluxe-account-link]');
      assert.equal(link().textContent.trim(), 'ورود');
      for (const authenticated of [true, false, true]) {
        await act(async () => {
          dom.window.jluxeCurrentAuthStatus = authenticated;
          dom.window.dispatchEvent(new dom.window.CustomEvent('jluxe:auth-status', { detail: { isLoggedIn: authenticated } }));
        });
        await act(async () => root.render(React.createElement(Nav)));
        assert.equal(link().textContent.trim(), authenticated ? 'حساب کاربری' : 'ورود');
        assert.equal(link().href, authenticated ? 'https://shop.test/store/members/' : 'https://shop.test/store/login/');
        assert.ok(link().querySelector('svg'));
      }
    } finally {
      await act(async () => root.unmount());
      await server.close();
      dom.window.close();
      for (const [key, descriptor] of saved) {
        if (descriptor) Object.defineProperty(globalThis, key, descriptor);
        else delete globalThis[key];
      }
    }
  });
}
