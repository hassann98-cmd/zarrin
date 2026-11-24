import assert from "node:assert/strict";
import fs from "node:fs";
import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import test from "node:test";

const root = new URL("../assets/compiled/", import.meta.url);
const manifest = JSON.parse(fs.readFileSync(new URL("manifest.json", root), "utf8"));
const headerEntry = manifest["src/islands/Header.js"];
const headerBundle = fs.readFileSync(new URL(headerEntry.file, root), "utf8");
const headerSource = fs.readFileSync(new URL("../src/islands/Header.js", import.meta.url), "utf8");
const headerModule = await import(new URL(headerEntry.file, root));

test("compiled Header binds the tag icon and renders a non-empty live-search brand group", () => {
  assert.match(headerSource, /import \{ T \} from "\.\.\/icons\/tag\.js"/);
  assert.ok(/\bT\b/.test(headerBundle), "the compiled brand branch references the tag icon binding");
  const html = renderToStaticMarkup(React.createElement(headerModule.LiveSearchResults, {
    query: "acme",
    results: {
      products: [],
      categories: [],
      brands: [{ name: "Acme Tools", url: "/brand/acme/" }],
      viewAllUrl: "",
      query: "acme",
      correctedQuery: "",
    },
    loading: false,
  }));
  assert.match(html, /برندها/);
  assert.match(html, /Acme Tools/);
  assert.match(html, /<svg/);
});
