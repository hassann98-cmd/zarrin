/* Document model: the DOM is a view, never the saved source of truth. */
(function (win) {
 'use strict';
 function copy(value) { return JSON.parse(JSON.stringify(value)); }
 function uid() { return 'node-' + (win.crypto?.randomUUID ? win.crypto.randomUUID() : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2)); }
 function walk(nodes, visit, parent) { (nodes || []).forEach(function (node, index) { visit(node, nodes, index, parent || null); if (node.type === 'container') walk(node.children || [], visit, node); }); }
 function find(doc, id) { var found; walk(doc.sections, function (node, list, index, parent) { if (node.id === id) found = { node: node, list: list, index: index, parent: parent }; }); return found; }
 function validate(doc, types) {
  if (!doc || !Array.isArray(doc.sections)) throw new Error('ساختار صفحه معتبر نیست.');
  var seen = new Set(), count = 0;
  function check(nodes, depth) {
   if (depth > 6) throw new Error('حداکثر عمق کانتینر ۶ است.');
   nodes.forEach(function (node) {
    if (!node || typeof node !== 'object' || !Object.hasOwn(types, node.type) || typeof node.id !== 'string' || !/^[\w-]{1,80}$/.test(node.id) || seen.has(node.id)) throw new Error('نوع یا شناسهٔ المان معتبر نیست.');
    seen.add(node.id); if (++count > 150) throw new Error('حداکثر ۱۵۰ المان مجاز است.');
    if (node.type === 'container') { if (!Array.isArray(node.children)) throw new Error('فرزندان کانتینر معتبر نیست.'); check(node.children, depth + 1); }
    else if (node.children && node.children.length) throw new Error('فقط کانتینر می‌تواند فرزند داشته باشد.');
   });
  }
  check(doc.sections, 0); return true;
 }
 function normalize(doc) {
  doc = copy(doc); var ids = new Set();
  walk(doc.sections, function (node) { if (!node.id || ids.has(node.id)) node.id = uid(); ids.add(node.id); if (node.type === 'container' && !Array.isArray(node.children)) node.children = []; });
  return doc;
 }
 function contains(node, id) { var yes = node.id === id; walk(node.children, function (child) { if (child.id === id) yes = true; }); return yes; }
 function destination(doc, parentId) {
  if (!parentId) return doc.sections;
  var target = find(doc, parentId);
  if (!target || target.node.type !== 'container') throw new Error('مقصد باید کانتینر باشد.');
  return target.node.children;
 }
 function move(doc, id, parentId, beforeId) {
  var source = find(doc, id); if (!source) throw new Error('المان پیدا نشد.');
  if (id === beforeId) return;
  if (parentId && contains(source.node, parentId)) throw new Error('انتقال به خود یا زیرمجموعه مجاز نیست.');
  var list = destination(doc, parentId);
  if (beforeId && !list.some(function (node) { return node.id === beforeId; })) throw new Error('محل درج معتبر نیست.');
  source.list.splice(source.index, 1);
  var index = beforeId ? list.findIndex(function (node) { return node.id === beforeId; }) : list.length;
  list.splice(index, 0, source.node);
 }
 function duplicate(doc, id) {
  var source = find(doc, id); if (!source) throw new Error('المان پیدا نشد.');
  var node = copy(source.node); node.id = uid(); walk(node.children, function (child) { child.id = uid(); });
  source.list.splice(source.index + 1, 0, node); return node.id;
 }
 function insert(doc, node, selected) {
  var target = find(doc, selected);
  if (!target) doc.sections.push(node);
  else if (target.node.type === 'container') target.node.children.push(node);
  else target.list.splice(target.index + 1, 0, node);
 }
 function fields(form, base) {
  var out = copy(base), paths = [];
  function put(path, value) {
   var obj = out;
   path.forEach(function (key, index) {
    if (['__proto__', 'constructor', 'prototype'].indexOf(key) >= 0) throw new Error('نام فیلد ناامن است.');
    if (index === path.length - 1) obj[key] = value;
    else { if (!obj[key] || typeof obj[key] !== 'object') obj[key] = /^\d+$/.test(path[index + 1]) ? [] : {}; obj = obj[key]; }
   });
  }
  // Reset repeaters represented in the inspector, including lists whose last row was deleted.
  form.querySelectorAll('.jluxe-repeater-list[data-group]').forEach(function (list) {
   var group = list.getAttribute('data-group');
   if (group && Object.hasOwn(out, group) && Array.isArray(out[group])) out[group] = [];
  });
  Array.from(form.elements).forEach(function (field) {
   if (!field.name || field.disabled || /^(button|submit|file)$/.test(field.type)) return;
   if (field.type === 'radio' && !field.checked) return;
   var suffix = field.name.replace(/^homepage\[sections\]\[0\]/, '');
   if (suffix === field.name) return;
   var path = Array.from(suffix.matchAll(/\[([^\]]+)\]/g), function (match) { return match[1]; });
   if (!path.length) return;
   paths.push(path);
   put(path, field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value);
  });
  out.id = base.id; out.type = base.type;
  return out;
 }
 win.JLuxeBuilderTree = { copy: copy, uid: uid, walk: walk, find: find, validate: validate, normalize: normalize, move: move, duplicate: duplicate, insert: insert, fields: fields, contains: contains };
})(window);
