import test from 'node:test';
import assert from 'node:assert/strict';
import { scheduleResponsiveIsland } from '../src/lib/responsive-island.js';

test('hidden device-specific island loads once on breakpoint entry, preserving SSR until then', () => {
  let listener, count = 0;
  const query = { matches: false, addEventListener: (_, fn) => { listener = fn; }, removeEventListener: () => { listener = null; } };
  const element = { dataset: { jluxeIsland: 'mega-menu' }, isConnected: true };
  scheduleResponsiveIsland(element, () => count++, { matchMedia: media => { assert.equal(media, '(min-width: 768px)'); return query; } });
  assert.equal(count, 0);
  const change = listener;
  query.matches = true; change(); change();
  assert.equal(count, 1); assert.equal(listener, null);
});

test('mobile search uses mobile breakpoint; generic islands and unsupported browsers remain immediate', () => {
  let count = 0;
  scheduleResponsiveIsland({ dataset: { jluxeIsland: 'header-mobile-search' }, isConnected: true }, () => count++, {
    matchMedia: media => { assert.equal(media, '(max-width: 767.98px)'); return { matches: true }; },
  });
  scheduleResponsiveIsland({ dataset: { jluxeIsland: 'mini-cart' } }, () => count++, {});
  scheduleResponsiveIsland({ dataset: { jluxeIsland: 'mega-menu' } }, () => count++, {});
  assert.equal(count, 3);
});
