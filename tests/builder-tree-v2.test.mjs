import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { JSDOM } from 'jsdom';
const model = fs.readFileSync(new URL('../assets/js/builder-tree.js', import.meta.url), 'utf8');
const app = fs.readFileSync(new URL('../assets/js/visual-builder-v2.js', import.meta.url), 'utf8');
const php = fs.readFileSync(new URL('../inc/visual-builder-v2.php', import.meta.url), 'utf8');
const markup = php.slice(php.indexOf('<div id="jluxe-builder2"'), php.indexOf('\n\t<?php\n}', php.indexOf('<div id="jluxe-builder2"'))).replace(/<\?php[\s\S]*?\?>/g, '');
const types = { container: 'کانتینر', heading: 'تیتر', text: 'متن', image: 'تصویر', button: 'دکمه' };
function tree(t) { const dom = new JSDOM('<body></body>', { runScripts:'outside-only', url:'https://shop.test/wp-admin/' }); t.after(() => dom.window.close()); dom.window.eval(model); return dom.window.JLuxeBuilderTree; }
const layout = () => ({ sections: [{id:'row', type:'container', enabled:true, children:[{id:'one',type:'heading',title:'Original', enabled:true}]}, {id:'two',type:'text',title:'Second',content:'Text',enabled:true}] });

test('tree can nest, reorder across parents, duplicate with new ids and reject cycles', t => {
 const T = tree(t), doc = layout();
 T.move(doc, 'two', 'row'); assert.equal(doc.sections.length,1); assert.equal(doc.sections[0].children[1].id,'two');
 T.move(doc,'two','row','one'); assert.equal(doc.sections[0].children[0].id,'two');
 const duplicate = T.duplicate(doc,'row'); assert.notEqual(duplicate,'row'); assert.equal(T.validate(doc,types),true);
 assert.notEqual(doc.sections[1].children[0].id, doc.sections[0].children[0].id);
 assert.throws(() => T.move(doc,'row','row'), /خود/);
 T.insert(doc,{id:'inner',type:'container',children:[]},'row');
 assert.throws(() => T.move(doc,'row','inner'), /زیرمجموعه/);
});

test('limits reject unknown types, duplicates, depth and node budgets', t => {
 const T=tree(t); const value=layout(); value.sections[1].id='row'; assert.throws(()=>T.validate(value,types));
 assert.throws(()=>T.validate({sections:[{id:'x',type:'evil'}]},types));
 const large={sections:Array.from({length:151},(_,i)=>({id:'x'+i,type:'heading'}))}; assert.throws(()=>T.validate(large,types));
 let node={id:'leaf',type:'heading'}; for(let i=0;i<8;i++) node={id:'d'+i,type:'container',children:[node]}; assert.throws(()=>T.validate({sections:[node]},types));
});

test('inspector parser preserves children, resets empty repeaters, handles unchecked inputs and blocks prototype keys', t => {
 const T=tree(t); const dom=new JSDOM('<form><input name="homepage[sections][0][title]" value="New"><input type="checkbox" name="homepage[sections][0][enabled]"><div class="jluxe-repeater-list" data-group="items"></div></form>'); t.after(()=>dom.window.close());
 const node=T.fields(dom.window.document.querySelector('form'),{id:'row',type:'container',children:[{id:'child'}],items:[{title:'stale'}]});
 assert.equal(node.title,'New'); assert.equal(node.enabled,'0'); assert.equal(node.children[0].id,'child'); assert.equal(node.items.length,0);
 const input=dom.window.document.createElement('input'); input.name='homepage[sections][0][__proto__][polluted]'; dom.window.document.querySelector('form').append(input);
 assert.throws(()=>T.fields(dom.window.document.querySelector('form'),{}), /ناامن/); assert.equal({}.polluted,undefined);
});
const wait = (ms=25)=>new Promise(resolve=>setTimeout(resolve,ms));
function boot(t, options={}) {
 const dom=new JSDOM(markup,{url:'https://shop.test/wp-admin/admin.php?page=jluxe-visual-builder',runScripts:'outside-only',pretendToBeVisual:true}); t.after(()=>dom.window.close()); const win=dom.window;
 win.eval(model); win.confirm=()=>true;
 win.jluxeBuilder2={ajaxUrl:'https://shop.test/wp-admin/admin-ajax.php',nonce:'nonce',homepage:layout(),types,revision:'r1',previewOrigin:'https://shop.test'};
 const requests=[]; let revision=1;
 const esc = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;');
 win.fetch=async (_url,init)=>{
  const operation=init.body.get('operation'), document=JSON.parse(init.body.get('document')); requests.push({operation,document,revision:init.body.get('revision')});
  if(options.reply){const override=await options.reply(operation,document,init);if(override)return override;}
  let data={};
  if(operation==='inspect') {const n=document.sections[0]; data.html=`<div class="jluxe-hb-section"><details><summary><label class="jluxe-hb-enabled"><input type="checkbox" name="homepage[sections][0][enabled]" value="1" checked></label></summary><input name="homepage[sections][0][title]" value="${esc(n.title)}"><input type="hidden" name="homepage[sections][0][type]" value="${n.type}"><textarea name="homepage[sections][0][content]">${esc(n.content)}</textarea></details></div>`;}
  if(operation==='preview') data.url='https://shop.test/?jluxe_builder_preview=live&jvb_revision='+requests.length;
  if(operation==='save'||operation==='publish') data={homepage:document,revision:'r'+(++revision)};
  return {ok:true,status:200,json:async()=>({success:true,data})};
 };
 win.eval(app); return {dom,win,document:win.document,requests,T:win.JLuxeBuilderTree};
}
function click(document, selector) {const el=document.querySelector(selector);assert.ok(el,selector);el.click();}

test('new PHP screen boots with every library entry and a real full-width inspector', async t=>{
 const {document}=boot(t); assert.equal(document.querySelectorAll('[data-jb-add]:not([data-jb-preset])').length,5);
 click(document,'[data-jb-select="one"]'); await wait();
 assert.equal(document.querySelector('[data-jb-panel="inspector"]').hidden,false);
 assert.equal(document.querySelector('[data-jb-inspector] details').open,true);
 assert.equal(document.querySelector('[name$="[title]"]').value,'Original');
});

test('add into container, edit fields, save full JSON and undo/redo without dropping siblings',async t=>{
 const {document,win,requests}=boot(t);
 click(document,'[data-jb-select="row"]'); await wait(); click(document,'[data-jb-add="heading"]'); await wait();
 const input=document.querySelector('[name$="[title]"]'); input.value='Edited nested'; input.dispatchEvent(new win.Event('input',{bubbles:true}));
 click(document,'[data-jb-action="save"]'); await wait();
 const saved=requests.findLast(r=>r.operation==='save').document;
 assert.equal(saved.sections[0].children.length,2); assert.equal(saved.sections[0].children[1].title,'Edited nested'); assert.equal(saved.sections[1].id,'two');
 click(document,'[data-jb-action="undo"]'); await wait(); click(document,'[data-jb-action="redo"]'); await wait();
 click(document,'[data-jb-action="save"]'); await wait();
 assert.equal(requests.findLast(r=>r.operation==='save').document.sections[0].children[1].title,'Edited nested');
});

test('duplicate subtree, move with keyboard destination and remove are recoverable through history',async t=>{
 const {document,requests}=boot(t);
 click(document,'[data-jb-select="row"]'); await wait(); click(document,'[data-jb-action="duplicate"]'); await wait();
 click(document,'[data-jb-action="save"]'); await wait();
 let value=requests.findLast(r=>r.operation==='save').document;
 assert.equal(value.sections.length,3); assert.notEqual(value.sections[1].children[0].id,'one');
 click(document,'[data-jb-select="two"]'); await wait(); document.querySelector('[data-jb-destination]').value='row';
 click(document,'[data-jb-action="move"]'); await wait(); click(document,'[data-jb-action="save"]'); await wait();
 value=requests.findLast(r=>r.operation==='save').document; assert.equal(value.sections[0].children.at(-1).id,'two');
 click(document,'[data-jb-action="remove"]'); await wait(); click(document,'[data-jb-action="undo"]'); await wait();
 click(document,'[data-jb-action="save"]'); await wait(); assert.equal(requests.findLast(r=>r.operation==='save').document.sections[0].children.at(-1).id,'two');
});

test('stale inspector responses cannot replace the selected element settings',async t=>{
 let resolveOld;
 const {document}=boot(t,{reply:async(op,value)=>{
  if(op==='inspect'&&value.sections[0].id==='one')return new Promise(resolve=>{resolveOld=resolve;});
 }});
 click(document,'[data-jb-select="one"]'); await wait(); click(document,'[data-jb-select="two"]'); await wait();
 resolveOld({ok:true,status:200,json:async()=>({success:true,data:{html:'<input value="WRONG">'}})}); await wait();
 assert.equal(document.querySelector('[name$="[title]"]').value,'Second');
});

test('save conflicts surface error and preserve editable document',async t=>{
 const {document}=boot(t,{reply:async op=>op==='save'?{ok:false,status:409,json:async()=>({success:false,data:{message:'revision conflict'}})}:null});
 click(document,'[data-jb-add="heading"]'); await wait(); click(document,'[data-jb-action="save"]'); await wait();
 assert.match(document.querySelector('[data-jb-status]').textContent,/revision conflict/);
 assert.equal(document.querySelectorAll('[data-jb-select]').length,4);
 assert.equal(document.querySelector('[data-jb-action="save"]').disabled,false);
});

test('preview messages require both the expected iframe and exact origin',async t=>{
 const {document,win}=boot(t);const frame=document.querySelector('[data-jb-frame]');
 win.dispatchEvent(new win.MessageEvent('message',{source:frame.contentWindow,origin:'https://evil.test',data:{source:'jluxe-builder-preview',type:'select',id:'one'}})); await wait();
 assert.equal(document.querySelector('[data-jb-panel="inspector"]').hidden,true);
 win.dispatchEvent(new win.MessageEvent('message',{source:frame.contentWindow,origin:'https://shop.test',data:{source:'jluxe-builder-preview',type:'select',id:'one'}})); await wait();
 assert.equal(document.querySelector('[name$="[title]"]').value,'Original');
});

test('reference workspace groups searchable elements and exposes independent page/history tabs', async t=>{
 const {document,win}=boot(t);
 assert.ok(document.querySelector('.jb-library details summary'));
 click(document,'[data-jb-tab="page"]');
 assert.equal(document.querySelector('[data-jb-panel="page"]').hidden,false);
 const search=document.querySelector('[data-jb-search]'); search.value='NOT_FOUND';search.dispatchEvent(new win.Event('input',{bubbles:true}));
 assert.equal(document.querySelectorAll('[data-jb-add]').length,0);
 search.value='';search.dispatchEvent(new win.Event('input',{bubbles:true}));assert.equal(document.querySelectorAll('[data-jb-add]:not([data-jb-preset])').length,5);
});

test('history jump restores document; branching discards future states and visibility is undoable', async t=>{
 const {document,requests}=boot(t);
 click(document,'[data-jb-toggle="two"]');
 click(document,'[data-jb-action="save"]');await wait();
 assert.equal(requests.findLast(r=>r.operation==='save').document.sections[1].enabled,false);
 click(document,'[data-jb-tab="history"]');
 assert.ok(document.querySelector('[data-jb-history]').textContent.includes('تغییر نمایش'));
 click(document,'[data-jb-history-index="0"]');await wait();
 click(document,'[data-jb-action="save"]');await wait();
 assert.equal(requests.findLast(r=>r.operation==='save').document.sections[1].enabled,true);
 click(document,'[data-jb-add="heading"]');await wait();
 assert.equal(document.querySelector('[data-jb-action="redo"]').disabled,true);
 assert.equal(document.querySelectorAll('[data-jb-history-index]').length,2);
});

test('inspector content/advanced switch never removes fields or writes a draft', async t=>{
 const {document,requests}=boot(t);
 click(document,'[data-jb-select="one"]');await wait();
 const count=document.querySelector('[data-jb-inspector]').querySelectorAll('[name]').length;
 click(document,'[data-jb-inspector-tab="advanced"]');
 assert.equal(document.querySelector('[data-jb-inspector]').dataset.view,'advanced');
 click(document,'[data-jb-inspector-tab="content"]');
 assert.equal(document.querySelector('[data-jb-inspector]').querySelectorAll('[name]').length,count);
 assert.equal(requests.filter(r=>r.operation==='save'||r.operation==='publish').length,0);
});


test('layout presets create safe unequal six-column and two-column containers', async t=>{
 const {document,requests}=boot(t);
 click(document,'[data-jb-preset="66.7,33.3"]');await wait();
 click(document,'[data-jb-action="save"]');await wait();
 let saved=requests.findLast(r=>r.operation==='save').document;
 assert.equal(saved.sections.at(-1).columns,2); assert.equal(saved.sections.at(-1).column_ratios,'66.7,33.3');
 click(document,'[data-jb-action="root"]');click(document,'[data-jb-preset="1,1,1,1,1,1"]');await wait();
 click(document,'[data-jb-action="save"]');await wait();
 saved=requests.findLast(r=>r.operation==='save').document;assert.equal(saved.sections.at(-1).columns,6);
});
