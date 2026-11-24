import { readStorefrontCss } from "./helpers/storefront-css.mjs";
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { JSDOM } from 'jsdom';
import { MOBILE_DRAWER_Z_INDEX, openMobileDrawer, closeMobileDrawer } from '../src/lib/mobile-drawers.js';
const css = readStorefrontCss();
// Include the late body overrides as well as the base rules: checking only
// the original 166 declaration missed the regression caused by a later 180.
const layerRules = [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
  .filter(([, selector, body]) => /(?:^|\s)\.(?:jluxe-mobile-bar-toggle|jluxe-sticky-cta-mode-toggle)\s*$/.test(selector.trim()) && /z-index\s*:/.test(body))
  .map(([, selector, body]) => `${selector}{${body}}`).join('\n');
for (const switcher of ['jluxe-mobile-bar-toggle', 'jluxe-sticky-cta-mode-toggle']) {
  for (const drawer of ['cart', 'categories']) {
    test(`${switcher} stays above purchase bar but below ${drawer} overlay`, () => {
      const dom = new JSDOM(`<style>${layerRules}</style><body><button class="${switcher}">Switch mode</button><div id="drawer" style="position:fixed;z-index:${MOBILE_DRAWER_Z_INDEX}"></div></body>`, { pretendToBeVisual: true });
      try {
        const w = dom.window;
        const button = w.document.querySelector('button');
        const assertLayers = () => {
          const z = Number(w.getComputedStyle(button).zIndex);
          assert.ok(z > 165, 'switch remains above the purchase bar');
          assert.ok(z < MOBILE_DRAWER_Z_INDEX, 'drawer and its backdrop cover the switch');
        };
        assertLayers();
        openMobileDrawer(drawer, w);
        assert.equal(w.document.documentElement.getAttribute('data-jluxe-mobile-drawer'), drawer);
        assertLayers();
        closeMobileDrawer(drawer, w);
        assertLayers();
      } finally { dom.window.close(); }
    });
  }
}
