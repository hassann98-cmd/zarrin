import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const header=fs.readFileSync(new URL('../src/islands/Header.js',import.meta.url),'utf8');
const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8');
test('desktop, inline mobile and modal searches share the scoped contrast treatment',()=>{
 assert.equal((header.match(/jluxe-header-search-field /g)||[]).length,3);
 assert.equal((header.match(/type: "search"/g)||[]).length,3);
 assert.match(css,/body input\.jluxe-header-search-field\s*\{[^}]*background-color:hsl\(var\(--muted\)\)[^}]*94%[^}]*border-color:/s);
});
test('search focus remains visible with theme tokens, hover is pointer-scoped and motion is optional',()=>{
 assert.match(css,/body input\.jluxe-header-search-field:focus\s*\{[^}]*border-color:hsl\(var\(--primary\)\)[^}]*box-shadow:/s);
 assert.match(css,/@media\(hover:hover\)\{body input\.jluxe-header-search-field:hover/);
 assert.match(css,/@media\(prefers-reduced-motion:reduce\)\{body input\.jluxe-header-search-field\{transition:none;/);
});
