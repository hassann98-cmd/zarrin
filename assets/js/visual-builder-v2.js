/* global jluxeBuilder2, JLuxeBuilderTree, jQuery */
(function (win, doc) {
 'use strict';
 var root = doc.getElementById('jluxe-builder2');
 if (!root) return;
 var config = win.jluxeBuilder2 || {}, T = win.JLuxeBuilderTree;
 var types = config.types || {}, state, selected = null, inspectorId = null, inspectorRequest = 0;
 var inspectorDirty = false;
 var revision = config.revision, generation = 0, saved, history = [], historyMeta = [], pendingLabel = 'بازکردن صفحه', cursor = -1;
 var inputTimer, previewTimer, previewTimeout, previewRequest = 0, previewAbort, busy = false, dragId = null;
 var form = root.querySelector('[data-jb-inspector]'), frame = root.querySelector('[data-jb-frame]');
 var status = root.querySelector('[data-jb-status]'), previewStatus = root.querySelector('[data-jb-preview-status]');
 function message(text, error) { status.textContent = text; status.dataset.error = error ? 'true' : 'false'; }
 function label(node) { return node.editor_label || node.title || types[node.type] || node.type; }
 function button(text, attrs) { var el = doc.createElement('button'); el.type = 'button'; el.textContent = text; Object.keys(attrs || {}).forEach(function (key) { el.setAttribute(key, attrs[key]); }); return el; }
 function serialize() { return JSON.stringify(state); }
 function updateStatus() { message(serialize() === saved ? 'پیش‌نویس بدون تغییر ذخیره‌نشده' : 'تغییرهای ذخیره‌نشده — سایت هنوز تغییر نکرده است'); }
 function record() {
  var value = serialize();
  if (history[cursor] !== value) { history = history.slice(0, cursor + 1); historyMeta = historyMeta.slice(0, cursor + 1); history.push(value); historyMeta.push({label: pendingLabel, time: new Date().toLocaleTimeString('fa-IR')}); if (history.length > 40) { history.shift(); historyMeta.shift(); } cursor = history.length - 1; }
  root.querySelector('[data-jb-action=undo]').disabled = cursor <= 0;
  root.querySelector('[data-jb-action=redo]').disabled = cursor >= history.length - 1;
  renderHistory();
 }
 function renderHistory() {
  var host = root.querySelector('[data-jb-history]'); if (!host) return;
  host.replaceChildren();
  for (var i = history.length - 1; i >= 0; i--) {
   var entry = historyMeta[i], item = button(entry.label, {'data-jb-history-index': String(i), 'aria-current': String(i === cursor)});
   var time = doc.createElement('small'); time.textContent = entry.time; item.appendChild(time); host.appendChild(item);
  }
 }
 function inspectorTab(name) {
  form.dataset.view = name;
  var advanced = form.querySelector('.jvb-design-controls'); if (advanced) advanced.open = name === 'advanced';
  root.querySelectorAll('[data-jb-inspector-tab]').forEach(function (item) { item.setAttribute('aria-pressed', String(item.dataset.jbInspectorTab === name)); });
 }
 async function api(operation, documentValue, extra, signal) {
  var body = new win.URLSearchParams({ action: 'jluxe_builder2', nonce: config.nonce, operation: operation, document: JSON.stringify(documentValue) });
  Object.keys(extra || {}).forEach(function (key) { body.set(key, extra[key]); });
  var controller = signal ? null : new win.AbortController();
  var timeout = controller ? win.setTimeout(function () { controller.abort(); }, 25000) : null;
  var response;
  try { response = await win.fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body, signal: signal || controller.signal }); }
  catch (error) { if (error.name === 'AbortError' && !signal) throw new Error('سرور در مهلت مقرر پاسخ نداد؛ تغییرها حفظ شدند. پیش از تکرار انتشار، وضعیت سایت را بررسی کنید.'); throw error; }
  finally { if (timeout) win.clearTimeout(timeout); }
  var result;
  try { result = await response.json(); } catch (_) { throw new Error('پاسخ سرور JSON معتبر نیست؛ اتصال یا افزونهٔ امنیتی را بررسی کنید.'); }
  if (!response.ok || !result.success) throw new Error(result.data?.message || 'خطای سرور (' + response.status + ')؛ تغییرها حفظ شدند.');
  return result.data;
 }
 function tab(name) {
  root.querySelectorAll('[data-jb-panel]').forEach(function (el) { el.hidden = el.dataset.jbPanel !== name; });
  root.querySelectorAll('.jb-tabs [data-jb-tab]').forEach(function (el) { el.setAttribute('aria-pressed', String(el.dataset.jbTab === name)); });
 }
 function readInspector() {
  var found = T.find(state, inspectorId);
  if (!inspectorDirty || !found || form.dataset.loaded !== inspectorId) return false;
  inspectorDirty = false;
  var node = T.fields(form, found.node);
  if (JSON.stringify(node) === JSON.stringify(found.node)) return false;
  found.list[found.index] = node; generation++; return true;
 }
 function flush() { win.clearTimeout(inputTimer); if (readInspector()) { renderLayers(); schedulePreview(); } record(); }
 function changed() { generation++; record(); renderLayers(); updateStatus(); schedulePreview(); }
 function transaction(action) {
  flush(); var before = T.copy(state);
  try { action(); T.validate(state, types); changed(); return true; }
  catch (error) { state = before; message(error.message, true); return false; }
 }
 function sendSelection() { frame.contentWindow?.postMessage({ source: 'jluxe-builder-editor', type: 'select', id: selected }, config.previewOrigin); }
 async function inspect(id) {
  var sequence = ++inspectorRequest;
  inspectorId = null; inspectorDirty = false; delete form.dataset.loaded;
  form.replaceChildren();
  var found = T.find(state, id);
  root.querySelector('[data-jb-selected]').textContent = found ? label(found.node) : 'المانی انتخاب نشده';
  root.querySelector('[data-jb-node-actions]').hidden = !found;
  if (!found) return;
  form.textContent = 'در حال دریافت تنظیمات…';
  try {
   var result = await api('inspect', { sections: [found.node] });
   if (sequence !== inspectorRequest || id !== selected) return;
   // HTML is generated by authenticated PHP field renderers, not arbitrary imported markup.
   form.innerHTML = result.html;
   var details = form.querySelector('details'); if (details) details.open = true;
   var enabled = form.querySelector('.jluxe-hb-enabled');
   if (enabled) { enabled.style.display = 'block'; form.prepend(enabled); }
   inspectorId = id; form.dataset.loaded = id; inspectorTab('content');
   if (win.jQuery?.fn.sortable) win.jQuery(form).find('.jluxe-repeater-list').sortable({ handle: '.jluxe-repeater-handle', axis: 'y', update: function () {
    // Native manager renumbers on add/remove; sorting needs the same group-aware renumbering.
    var list = this, group = list.dataset.group;
    if (group) Array.from(list.children).forEach(function (item, index) { item.querySelectorAll('[name]').forEach(function (field) { field.name = field.name.replace(new RegExp('\\[' + group + '\\]\\[\\d+\\]'), '[' + group + '][' + index + ']'); }); });
    onInput();
   } });
  } catch (error) {
   if (sequence !== inspectorRequest) return;
   form.textContent = '';
   var p = doc.createElement('p'); p.textContent = error.message; form.appendChild(p);
   var retry = button('تلاش مجدد', { 'data-jb-retry-inspect': id }); form.appendChild(retry);
   message(error.message, true);
  }
 }
 function select(id, openInspector) {
  flush(); selected = T.find(state, id) ? id : null;
  renderLayers(); inspect(selected); sendSelection();
  if (openInspector) tab('inspector');
 }
 function renderLayers() {
  var box = root.querySelector('[data-jb-layers]'), destination = root.querySelector('[data-jb-destination]');
  box.replaceChildren(); var oldDestination = destination.value;
  destination.replaceChildren(new win.Option('صفحهٔ اصلی', ''));
  function render(nodes, host) {
   nodes.forEach(function (node) {
    var row = doc.createElement('div'); row.className = 'jb-layer'; row.draggable = true; row.dataset.nodeId = node.id;
    var pick = button((node.type === 'container' ? '▦ ' : '◦ ') + label(node) + (node.enabled === false || node.enabled === '0' ? ' — خاموش' : ''), { 'data-jb-select': node.id, 'aria-current': String(node.id === selected) });
    row.appendChild(pick); row.appendChild(button(node.enabled === false || node.enabled === '0' ? '◌' : '◉', {'data-jb-toggle':node.id, 'aria-label':'تغییر نمایش ' + label(node), 'aria-pressed':String(!(node.enabled === false || node.enabled === '0'))})); host.appendChild(row);
    if (node.type === 'container') {
     var current = T.find(state, selected);
     if (!current || !T.contains(current.node, node.id)) destination.appendChild(new win.Option(label(node), node.id));
     var inner = doc.createElement('div'); inner.className = 'jb-layer-children'; row.appendChild(inner); render(node.children, inner);
    }
   });
  }
  render(state.sections, box); destination.value = oldDestination;
  root.querySelector('[data-jb-empty]').hidden = state.sections.length > 0;
 }
 function renderLibrary(query) {
  var host = root.querySelector('[data-jb-library]'); host.replaceChildren();
  var groups = {'چیدمان': ['container','spacer'], 'پایه': ['heading','text','button','cta','html'], 'رسانه': ['hero','stories','banner','banner_two','banner_three','banner_slider','banner_collage','brand_marquee'], 'فروشگاه': ['product_grid','category_grid','category_showcase','brick_products','special_products','recommended_panels']};
  var used = new Set();
  function group(title, keys) {
   keys = keys.filter(function (type) { return Object.hasOwn(types,type) && (!query || (types[type]+' '+type).includes(query)); });
   if (!keys.length) return;
   var section = doc.createElement('details'); section.open = true;
   var summary = doc.createElement('summary'); summary.textContent = title + ' (' + keys.length.toLocaleString('fa-IR') + ')'; section.appendChild(summary);
   var grid = doc.createElement('div'); grid.className = 'jb-element-grid';
   keys.forEach(function (type) {
    used.add(type); var item = button(types[type], {'data-jb-add':type, draggable:'true'});
    var icon = doc.createElement('span'); icon.className = 'jb-element-icon'; icon.setAttribute('aria-hidden','true'); icon.textContent = type === 'container' ? '▥' : type === 'heading' ? 'H' : type === 'button' ? '▰' : type.includes('product') ? '▦' : '◇'; item.prepend(icon); grid.appendChild(item);
   });
   section.appendChild(grid); host.appendChild(section);
  }
  Object.keys(groups).forEach(function (name) { group(name,groups[name]); });
  group('محتوا',Object.keys(types).filter(function (type) { return !used.has(type); }));
  if (!query && Object.hasOwn(types, 'container')) {
   var presets = doc.createElement('details'); presets.open = true;
   var title = doc.createElement('summary'); title.textContent = 'چیدمان‌های آماده'; presets.appendChild(title);
   var grid = doc.createElement('div'); grid.className = 'jb-element-grid';
   [['1','یک ستون'],['1,1','دو ستون مساوی'],['33.3,66.7','باریک + پهن'],['66.7,33.3','پهن + باریک'],['1,1,1','سه ستون مساوی'],['25,50,25','وسط پهن'],['1,1,1,1','چهار ستون'],['1,1,1,1,1,1','شش ستون']].forEach(function (preset) {
    grid.appendChild(button(preset[1], {'data-jb-add':'container','data-jb-preset':preset[0]}));
   });
   grid.querySelectorAll('button').forEach(function(item){item.draggable=true;});
   presets.appendChild(grid); host.prepend(presets);
  }
  if (!host.children.length) host.textContent = 'المانی با این عبارت پیدا نشد.';
 }
 function add(type, preset) {
  pendingLabel = 'افزودن «' + (types[type] || type) + '»';
  if (!Object.hasOwn(types, type)) return;
  var node = { id: T.uid(), type: type, enabled: true };
  if (type === 'container') Object.assign(node, { children: [], columns: 2, mobile_columns: 1, gap: 20, max_width: 1320 });
  if (type === 'container' && preset) { var ratios = preset.split(','); node.columns = ratios.length; node.column_ratios = preset; }
  if (type === 'heading') Object.assign(node, { title: 'تیتر تازه', tag: 'h2' });
  if (type === 'text') Object.assign(node, { title: 'متن تازه', content: 'متن خود را بنویسید.' });
  if (type === 'button') Object.assign(node, { title: 'مشاهده', url: '' });
  if (transaction(function () { T.insert(state, node, selected); })) select(node.id, true);
 }
 function schedulePreview() { win.clearTimeout(previewTimer); previewTimer = win.setTimeout(refreshPreview, 650); }
 async function refreshPreview() {
  win.clearTimeout(previewTimer); readInspector();
  var request = ++previewRequest;
  if (previewAbort) previewAbort.abort(); previewAbort = new win.AbortController();
  previewStatus.textContent = 'در حال آماده‌سازی پیش‌نمایش…';
  try {
   var result = await api('preview', T.copy(state), {}, previewAbort.signal);
   if (request !== previewRequest) return;
   var url = new win.URL(result.url);
   if (url.origin !== config.previewOrigin || !/^https?:$/.test(url.protocol)) throw new Error('مبدأ پیش‌نمایش با سایت مطابقت ندارد.');
   frame.src = url.href;
   var open = root.querySelector('[data-jb-open]'); open.href = url.href; open.hidden = false;
   win.clearTimeout(previewTimeout);
   previewTimeout = win.setTimeout(function () { previewStatus.textContent = 'پیش‌نمایش آماده نشد. CSP، X-Frame-Options یا نشست ورود را بررسی کنید؛ تنظیمات و خروجی پشتیبان همچنان در دسترس‌اند.'; }, 15000);
  } catch (error) { if (error.name !== 'AbortError' && request === previewRequest) previewStatus.textContent = error.message; }
 }
 async function save(publish) {
  if (busy) return; flush();
  if (publish && !win.confirm('این چیدمان جایگزین صفحهٔ اصلی فعال سایت شود؟')) return;
  busy = true; var ticket = generation, submitted = T.copy(state);
  root.querySelectorAll('[data-jb-action=save],[data-jb-action=publish]').forEach(function (el) { el.disabled = true; });
  message('در حال ' + (publish ? 'انتشار' : 'ذخیرهٔ پیش‌نویس') + '…');
  try {
   T.validate(submitted, types);
   var result = await api(publish ? 'publish' : 'save', submitted, { revision: revision });
   revision = result.revision;
   if (ticket === generation) { state = result.homepage; saved = serialize(); record(); renderLayers(); inspect(selected); }
   else saved = JSON.stringify(result.homepage);
   message((publish ? 'صفحهٔ اصلی منتشر شد.' : 'پیش‌نویس ذخیره شد؛ سایت تغییر نکرد.') + (ticket !== generation ? ' تغییرهای جدیدتر هنوز ذخیره نشده‌اند.' : ''));
  } catch (error) { message(error.message, true); }
  finally { busy = false; root.querySelectorAll('[data-jb-action=save],[data-jb-action=publish]').forEach(function (el) { el.disabled = false; }); }
 }
 function undo(direction) {
  flush(); var next = cursor + direction;
  if (next < 0 || next >= history.length) return;
  cursor = next; state = JSON.parse(history[cursor]); generation++;
  if (!T.find(state, selected)) selected = null;
  record(); renderLayers(); inspect(selected); sendSelection(); updateStatus(); schedulePreview();
 }
 function exportFile() {
  flush(); var blob = new win.Blob([JSON.stringify(state, null, 2)], { type: 'application/json' });
  var url = win.URL.createObjectURL(blob), a = doc.createElement('a'); a.href = url; a.download = 'homepage-layout.json'; a.click(); win.setTimeout(function () { win.URL.revokeObjectURL(url); }, 1000);
 }
 function onInput() {
  pendingLabel = 'ویرایش تنظیمات المان';
  inspectorDirty = true;
  if (readInspector()) { updateStatus(); schedulePreview(); }
  win.clearTimeout(inputTimer);
  inputTimer = win.setTimeout(function () { record(); renderLayers(); }, 300);
 }
 root.addEventListener('click', function (event) {
  var el = event.target.closest('button'); if (!el) return;
  if (el.dataset.jbHistoryIndex !== undefined) {
   flush(); var target = Number(el.dataset.jbHistoryIndex);
   if (Number.isInteger(target) && target >= 0 && target < history.length) undo(target - cursor);
   return;
  }
  if (el.dataset.jbInspectorTab) { inspectorTab(el.dataset.jbInspectorTab); return; }
  if (el.dataset.jbToggle) {
   pendingLabel = 'تغییر نمایش المان';
   transaction(function () { var node = T.find(state, el.dataset.jbToggle).node; node.enabled = node.enabled === false || node.enabled === '0'; });
   if (selected === el.dataset.jbToggle) inspect(selected); return;
  }
  if (el.dataset.jbTab) { flush(); tab(el.dataset.jbTab); return; }
  if (el.dataset.jbSelect) { select(el.dataset.jbSelect, true); return; }
  if (el.dataset.jbAdd) { add(el.dataset.jbAdd, el.dataset.jbPreset); return; }
  if (el.dataset.jbRetryInspect) { inspect(el.dataset.jbRetryInspect); return; }
  if (el.dataset.jbDevice) {
   root.querySelector('[data-jb-viewport]').dataset.jbViewport = el.dataset.jbDevice;
   root.querySelectorAll('[data-jb-device]').forEach(function (item) { item.setAttribute('aria-pressed', String(item === el)); }); return;
  }
  var action = el.dataset.jbAction;
  pendingLabel = ({remove:'حذف المان',duplicate:'تکثیر المان',move:'انتقال المان',up:'جابه‌جایی به بالا',down:'جابه‌جایی به پایین'})[action] || 'ویرایش چیدمان';
  if (!action) return;
  if (action === 'save' || action === 'publish') { save(action === 'publish'); return; }
  if (action === 'preview') { flush(); refreshPreview(); return; }
  if (action === 'undo' || action === 'redo') { undo(action === 'undo' ? -1 : 1); return; }
  if (action === 'export') { exportFile(); return; }
  if (action === 'root') { select(null, false); tab('library'); return; }
  flush(); var found = T.find(state, selected); if (!found) { message('ابتدا یک المان انتخاب کنید.', true); return; }
  if (action === 'parent') { select(found.parent?.id || null, true); return; }
  if (action === 'remove' && !win.confirm('این المان و فرزندانش از پیش‌نویس حذف شوند؟ با واگرد قابل بازیابی است.')) return;
  var nextSelected = selected;
  if (transaction(function () {
   if (action === 'remove') { found.list.splice(found.index, 1); nextSelected = found.parent?.id || null; }
   if (action === 'duplicate') nextSelected = T.duplicate(state, selected);
   if (action === 'move') T.move(state, selected, root.querySelector('[data-jb-destination]').value || null);
   if (action === 'up' && found.index > 0) T.move(state, selected, found.parent?.id, found.list[found.index - 1].id);
   if (action === 'down' && found.index < found.list.length - 1) T.move(state, found.list[found.index + 1].id, found.parent?.id, selected);
  })) select(nextSelected, true);
 });
 root.querySelector('[data-jb-search]').addEventListener('input', function (event) { renderLibrary(event.target.value.trim()); });
 form.addEventListener('submit', function (event) { event.preventDefault(); });
 form.addEventListener('input', onInput); form.addEventListener('change', onInput);
 if (win.jQuery) win.jQuery(form).on('input change', onInput);
 // Repeater add/remove and media selection are delegated by WordPress's existing field controls.
 form.addEventListener('click', function (event) { if (event.target.closest('.jluxe-repeater-add,.jluxe-repeater-remove,.jluxe-media-remove')) win.setTimeout(onInput, 0); });
 root.addEventListener('dragstart', function (event) {
  var layer = event.target.closest('[data-node-id]'), addButton = event.target.closest('[data-jb-add]');
  dragId = layer?.dataset.nodeId || null;
  if (event.dataTransfer) { if (dragId) event.dataTransfer.setData('text/x-jb-node', dragId); else if (addButton) { event.dataTransfer.setData('text/x-jb-type', addButton.dataset.jbAdd); event.dataTransfer.setData('text/x-jb-preset',addButton.dataset.jbPreset || ''); } }
 });
 root.addEventListener('dragend', function () { dragId = null; });
 root.querySelector('[data-jb-layers]').addEventListener('dragover', function (event) { event.preventDefault(); });
 root.querySelector('[data-jb-layers]').addEventListener('drop', function (event) {
  event.preventDefault(); var target = event.target.closest('[data-node-id]'); if (!target) return;
  var found = T.find(state, target.dataset.nodeId); if (!found) return;
  var type = event.dataTransfer?.getData('text/x-jb-type');
  if (type) { select(found.node.id, false); add(type); return; }
  var id = dragId || event.dataTransfer?.getData('text/x-jb-node'); if (!id) return;
  if (transaction(function () { T.move(state, id, found.node.type === 'container' ? found.node.id : found.parent?.id, found.node.type === 'container' ? null : found.node.id); })) select(id, false);
  dragId = null;
 });
 root.querySelector('[data-jb-import]').addEventListener('change', async function (event) {
  var file = event.target.files[0]; if (!file) return;
  try {
   if (file.size > 1048576) throw new Error('حداکثر حجم چیدمان یک مگابایت است.');
   var value = JSON.parse(await file.text()); value = T.normalize(value); T.validate(value, types);
   var result = await api('validate', value);
   if (!win.confirm('چیدمان واردشده جایگزین پیش‌نویس داخل ویرایشگر شود؟ سایت تا انتشار تغییر نمی‌کند.')) return;
   transaction(function () { state = result.homepage; }); select(null, false); tab('layers');
  } catch (error) { message(error.message, true); }
  finally { event.target.value = ''; }
 });
 win.addEventListener('message', function (event) {
  if (event.source !== frame.contentWindow || event.origin !== config.previewOrigin || event.data?.source !== 'jluxe-builder-preview') return;
  if (event.data.type === 'resize') {
   flush(); var target = T.find(state,event.data.id), ratios=event.data.ratios;
   if (!target || target.node.type!=='container' || !Array.isArray(ratios) || ratios.length!==Number(target.node.columns || 2) || ratios.some(function(value){return typeof value!=='number' || !Number.isFinite(value) || value<1 || value>100;})) return;
   pendingLabel='تغییر عرض ستون‌ها روی بوم';
   if(transaction(function(){target.node.column_ratios=ratios.join(',');})) { if(selected===target.node.id)inspect(selected); }
   return;
  }
  if (event.data.type === 'drop') {
   if (!Object.hasOwn(types,event.data.widget) || (event.data.id!==null && !T.find(state,event.data.id))) return;
   var preset=typeof event.data.preset==='string' && /^\d+(?:\.\d+)?(?:,\d+(?:\.\d+)?){0,5}$/.test(event.data.preset)?event.data.preset:null;
   select(event.data.id,false);add(event.data.widget,preset);return;
  }
  if (event.data.type === 'select' && T.find(state, event.data.id)) select(event.data.id, true);
  if (event.data.type === 'ready' && event.data.url === frame.src) { win.clearTimeout(previewTimeout); previewStatus.textContent = 'پیش‌نمایش آماده است؛ برای انتخاب روی المان کلیک کنید.';
   if (selected && Array.isArray(event.data.nodes) && !event.data.nodes.includes(selected)) previewStatus.textContent = 'المان در سند اضافه شده ولی در خروجی پیش‌نمایش پیدا نشد؛ چیدمان را ذخیره و پاسخ پیش‌نمایش/کش را بررسی کنید.';
   sendSelection(); }
 });
 doc.addEventListener('keydown', function (event) {
  if (!(event.ctrlKey || event.metaKey)) return;
  if (event.key.toLowerCase() === 's') { event.preventDefault(); save(false); return; }
  if (event.target.closest('input,textarea,select,[contenteditable]')) return;
  if (event.key.toLowerCase() === 'z') { event.preventDefault(); undo(event.shiftKey ? 1 : -1); }
 });
 win.addEventListener('beforeunload', function (event) { if (!state) return; readInspector(); if (serialize() !== saved) { event.preventDefault(); event.returnValue = ''; } });
 try { state = T.normalize(config.homepage); T.validate(state, types); saved = serialize(); record(); renderLibrary(''); renderLayers(); inspect(null); updateStatus(); schedulePreview(); }
 catch (error) { message('بارگذاری چیدمان متوقف شد: ' + error.message + ' داده‌های ذخیره‌شده تغییر نکردند.', true); }
})(window, document);
