import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { JSDOM } from 'jsdom';
import jquery from 'jquery';

const source = fs.readFileSync(new URL('../assets/js/theme-settings-admin.js', import.meta.url), 'utf8');
const start = source.indexOf('\t// Active mobile-nav editor:');
const end = source.indexOf('\t// لینکِ دستی', start);

test('only the active mobile layout editor is visible, while both layouts keep their saved icons in the submitted form', () => {
  const dom = new JSDOM(`<form><select id="jluxe-mobile-nav-variant" name="mobile[nav_variant]"><option value="classic">Classic</option><option value="floating" selected>Floating</option></select>
    <p data-mobile-nav-editor-status></p>
    <div data-mobile-nav-editor="classic"><select name="mobile[nav_items][0][icon]"><option value="bed" selected>bed</option></select></div>
    <div data-mobile-nav-editor="floating"><select name="mobile[floating_nav_items][0][icon]"><option value="gift" selected>gift</option></select></div></form>`, { runScripts: 'outside-only' });
  try {
    dom.window.$ = jquery(dom.window);
    dom.window.eval(source.slice(start, end));
    const doc = dom.window.document;
    const variant = doc.getElementById('jluxe-mobile-nav-variant');
    for (const mode of ['floating', 'classic', 'floating']) {
      variant.value = mode;
      dom.window.$(variant).trigger('change');
      assert.equal(doc.querySelector('[data-mobile-nav-editor="classic"]').hidden, mode !== 'classic');
      assert.equal(doc.querySelector('[data-mobile-nav-editor="floating"]').hidden, mode !== 'floating');
      const submitted = new dom.window.FormData(doc.querySelector('form'));
      assert.equal(submitted.get('mobile[nav_items][0][icon]'), 'bed');
      assert.equal(submitted.get('mobile[floating_nav_items][0][icon]'), 'gift');
      assert.match(doc.querySelector('[data-mobile-nav-editor-status]').textContent, mode === 'floating' ? /شناور/ : /تمام‌عرض/);
    }
  } finally { dom.window.close(); }
});
