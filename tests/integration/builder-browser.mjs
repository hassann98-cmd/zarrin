// Opt-in real Chromium acceptance. Generate the fixture with BUILDER_CAPTURE=1 builder-v2-run.mjs.
// Install playwright-core and @sparticuz/chromium in an external ignored cache directory.
import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const repo = path.resolve(import.meta.dirname,'../..');
const require = createRequire(path.join(process.env.BUILDER_BROWSER_MODULES || path.join(repo,'.cache/browser/node_modules'), 'entry.cjs'));
const { chromium } = require('playwright-core');
const chromiumPackage = require('@sparticuz/chromium');
const serverless = chromiumPackage.default || chromiumPackage;
const fixture = JSON.parse(await fs.readFile(path.join(repo,'.cache/builder-capture/fixture.json'),'utf8'));
const css = await fs.readFile(path.join(repo,'assets/css/visual-builder-v2.css'),'utf8');
const model = await fs.readFile(path.join(repo,'assets/js/builder-tree.js'),'utf8');
const manifest = JSON.parse(await fs.readFile(path.join(repo,'assets/compiled/manifest.json'),'utf8'));
const storefrontCss = (await Promise.all(manifest['src/main.js'].css.map(file=>fs.readFile(path.join(repo,'assets/compiled',file),'utf8')))).join('\n').replaceAll('url(./','url(https://shop.test/theme/assets/');
const app = await fs.readFile(path.join(repo,'assets/js/visual-builder-v2.js'),'utf8');
const bridge = await fs.readFile(path.join(repo,'assets/js/builder-preview.js'),'utf8');
const browser=await chromium.launch({args:serverless.args,executablePath:await serverless.executablePath(),headless:true});
let checks=0;
try {
 const page=await browser.newPage({viewport:{width:1440,height:1000}});
 const errors=[]; page.on('pageerror',error=>errors.push(error.message));
 const preview=fixture.preview.replace(/<script\b[\s\S]*?<\/script>/gi,'').replace(/<link\b[^>]*>/gi,'').replace('</head>','<style>'+storefrontCss+'</style></head>').replace('</body>','<script>window.jluxeBuilderPreview={parentOrigin:"https://shop.test"};</script><script>'+bridge+'</script></body>');
 const root=fixture.admin.slice(fixture.admin.indexOf('<div id="jluxe-builder2"'),fixture.admin.indexOf('<div id="wpfooter"'));
 await page.route('**/*',async route=>{
  if(route.request().url()==='https://shop.test/editor')return route.fulfill({contentType:'text/html',body:'<!doctype html><html><head><meta charset="utf-8"><style>'+css+'</style></head><body class="jluxe-visual-builder-screen">'+root+'</body></html>'});
  if(route.request().isNavigationRequest()&&route.request().frame()!==page.mainFrame())return route.fulfill({contentType:'text/html',body:preview});
  if (route.request().url().includes('/theme/assets/')) { const name=path.basename(new URL(route.request().url()).pathname);try{return route.fulfill({contentType:'font/woff2',body:await fs.readFile(path.join(repo,'assets/compiled/assets',name))});}catch(_){}}
  return route.abort();
 });
 await page.goto('https://shop.test/editor');
 const initial={sections:[{id:'integration-container',type:'container',enabled:true,children:[{id:'integration-heading',type:'heading',title:'Initial',enabled:true}]}]};
 await page.evaluate(({config,inspectors,initial})=>{
  window.jluxeBuilder2={...config,homepage:initial,previewOrigin:'https://shop.test'};
  window.__saved=[];
  window.fetch=async (_url,request)=>{
   const operation=request.body.get('operation'),document=JSON.parse(request.body.get('document'));
   let data={}; if(operation==='inspect')data.html=inspectors[document.sections[0].type];
   if(operation==='preview')data.url='https://shop.test/?preview='+Date.now();
   if(operation==='save'||operation==='publish'){window.__saved.push(document);data={homepage:document,revision:String(Date.now())};}
   return {ok:true,status:200,json:async()=>({success:true,data})};
  };
 },{config:fixture.config,inspectors:fixture.inspectors,initial});
 await page.addScriptTag({content:model}); await page.addScriptTag({content:app});
 assert.equal(await page.locator('[data-jb-add]:not([data-jb-preset])').count(),24); checks++;
 const sidebar=await page.locator('.jb-sidebar').boundingBox(); assert.ok(sidebar.width>=400&&sidebar.width<=480); checks++;
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true); checks++;
 await page.locator('.jb-tabs [data-jb-tab="layers"]').click();
 await page.locator('[data-jb-select="integration-heading"]').click();
 const title=page.locator('[name="homepage[sections][0][title]"]'); await title.waitFor();
 const box=await title.boundingBox();assert.ok(box.width>=300&&box.x>=sidebar.x);checks++;
 await title.fill('Chromium edited heading'); await page.locator('[data-jb-action="save"]').click();
 await page.waitForFunction(()=>window.__saved.length===1);
 assert.equal(await page.evaluate(()=>window.__saved[0].sections[0].children[0].title),'Chromium edited heading');checks++;
 // Exercise all server-generated inspector types in an actual browser.
 for(const type of Object.keys(fixture.config.types)){
  await page.locator('.jb-tabs [data-jb-tab="library"]').click();
  await page.locator('[data-jb-action="root"]').click();
  await page.locator('[data-jb-add="'+type+'"]:not([data-jb-preset])').click();
  await page.locator('[data-jb-inspector][data-loaded] .jluxe-hb-section').waitFor();
  assert.equal(await page.locator('[data-jb-status][data-error="true"]').count(),0);checks++;
 }
 await page.locator('.jb-tabs [data-jb-tab="page"]').click();
 await page.locator('[data-jb-action="preview"]').click();
 await page.frameLocator('[data-jb-frame]').locator('[data-jvb-node="integration-heading"]').waitFor();
 await page.frameLocator('[data-jb-frame]').locator('[data-jvb-node="integration-heading"]').click();
 await page.waitForFunction(()=>document.querySelector('[data-jb-inspector]').dataset.loaded==='integration-heading');checks++;
 await page.locator('[data-jb-inspector-tab="advanced"]').click();
 assert.equal(await page.locator('.jvb-design-controls').isVisible(),true);checks++;
 await page.locator('[data-jb-inspector-tab="content"]').click();
 assert.equal(await page.locator('.jvb-design-controls').isVisible(),false);checks++;
 await page.locator('.jb-tabs [data-jb-tab="history"]').click();
 assert.ok(await page.locator('[data-jb-history-index]').count()>1);checks++;
 await page.locator('.jb-tabs [data-jb-tab="library"]').click();
 await page.screenshot({path:path.join(repo,'.cache/builder-capture/desktop.png'),fullPage:true});
 await page.setViewportSize({width:390,height:844});
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);checks++;
 assert.ok((await page.locator('.jb-sidebar').boundingBox()).height>180);checks++;
 await page.screenshot({path:path.join(repo,'.cache/builder-capture/mobile.png'),fullPage:true});
 assert.deepEqual(errors,[]);checks++;
 console.log('BUILDER_CHROMIUM_ACCEPTANCE_PASSED: '+checks);
}finally{await browser.close();}
