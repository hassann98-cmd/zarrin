import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { N as navIcons } from "../src/components/nav-icons.js";

const catalog = JSON.parse(
  fs.readFileSync(new URL("../assets/icons/flaticon-uicons.json", import.meta.url), "utf8"),
);

test("R360 shared Flaticon catalog has normalized, colorable paths and attribution metadata", () => {
  assert.equal(catalog._meta.source, "@flaticon/flaticon-uicons");
  assert.equal(catalog._meta.version, "3.3.1");
  assert.equal(catalog._meta.viewBox, "0 0 24 24");
  assert.equal(Object.keys(catalog.icons).length, 67);
  assert.equal(catalog._meta.attribution, "Uicons by Flaticon");
  for (const [name, icon] of Object.entries(catalog.icons)) {
    assert.match(name, /^[a-z0-9-]+$/);
    assert.match(icon.source, /^fi-(?:rr|brands)-[a-z0-9-]+$/);
    assert.match(icon.d, /^[MmZzLlHhVvCcSsQqTtAaEe0-9,.+\-\s]+$/);
  }
  for (const name of ["bed", "mattress", "sofa", "brand-instagram", "brand-whatsapp"]) {
    assert.ok(catalog.icons[name], `${name} exists in the shared source`);
  }
});

test("R360 keeps required public attribution on the About page, outside the global footer", () => {
  const footer = fs.readFileSync(new URL("../footer.php", import.meta.url), "utf8");
  const about = fs.readFileSync(new URL("../page-about-us.php", import.meta.url), "utf8");
  assert.doesNotMatch(footer, /jluxe_uicons_attribution/);
  assert.match(about, /jluxe_uicons_attribution\(\s*true\s*\)/);
  const navSource = fs.readFileSync(new URL("../src/components/nav-icons.js", import.meta.url), "utf8");
  assert.doesNotMatch(navSource, /flaticon-uicons\.json/);
  const css = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
  assert.match(css, /\.jluxe-uicons-attribution--standalone[\s\S]*?safe-area-inset-bottom/);
});

test("R360 React icons use active SVG glyphs via currentColor and keep Lucide fallbacks", () => {
  const previousWindow = globalThis.window;
  globalThis.window = {
    JLuxeThemeSettings: {
      iconGlyphs: {
        bed: catalog.icons.bed.d,
        home: catalog.icons.home.d,
        "brand-whatsapp": catalog.icons["brand-whatsapp"].d,
      },
    },
  };

  try {
    const bedMarkup = renderToStaticMarkup(
      React.createElement(navIcons.bed, {
        className: "size-5 text-emerald-700",
        "aria-hidden": true,
      }),
    );
    assert.match(bedMarkup, /<svg[^>]*viewBox="0 0 24 24"/);
    assert.match(bedMarkup, /fill="currentColor"/);
    assert.match(bedMarkup, /stroke="none"/);
    assert.match(bedMarkup, /class="size-5 text-emerald-700"/);
    assert.match(bedMarkup, /<path[^>]*d="M/);

    const brandMarkup = renderToStaticMarkup(
      React.createElement(navIcons["brand-whatsapp"], { className: "size-4" }),
    );
    assert.match(brandMarkup, /fill="currentColor"/);
    assert.match(brandMarkup, /viewBox="0 0 24 24"/);

    delete globalThis.window.JLuxeThemeSettings.iconGlyphs.home;
    const fallbackMarkup = renderToStaticMarkup(
      React.createElement(navIcons.home, { className: "size-5" }),
    );
    assert.match(fallbackMarkup, /stroke="currentColor"/);
    assert.match(fallbackMarkup, /fill="none"/);
  } finally {
    if (previousWindow === undefined) delete globalThis.window;
    else globalThis.window = previousWindow;
  }
});
