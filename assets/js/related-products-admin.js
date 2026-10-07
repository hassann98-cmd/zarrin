/* global jQuery */
(function ($) {
  'use strict';

  $(function () {
    var select = document.getElementById('_jluxe_related_product_ids');
    var orderField = document.querySelector('input[name="_jluxe_related_product_order"]');
    if (!select || !orderField) return;

    function cleanId(value) {
      var id = String(value || '').trim();
      return /^\d+$/.test(id) && id !== '0' ? id : '';
    }

    var selected = Array.prototype.map.call(select.selectedOptions || [], function (option) {
      return cleanId(option.value);
    }).filter(Boolean);
    var order = (orderField.value || '').split(',').map(cleanId).filter(function (id, index, ids) {
      return id && ids.indexOf(id) === index && selected.indexOf(id) !== -1;
    });
    selected.forEach(function (id) {
      if (order.indexOf(id) === -1) order.push(id);
    });

    function saveOrder() {
      orderField.value = order.join(',');
    }

    var $select = $(select);
    $select.on('select2:select.jluxeRelatedProducts', function (event) {
      var id = cleanId(event.params && event.params.data && event.params.data.id);
      if (!id) return;
      order = order.filter(function (existing) { return existing !== id; });
      order.push(id);
      saveOrder();
    });
    $select.on('select2:unselect.jluxeRelatedProducts', function (event) {
      var id = cleanId(event.params && event.params.data && event.params.data.id);
      order = order.filter(function (existing) { return existing !== id; });
      saveOrder();
    });
    $select.on('select2:clear.jluxeRelatedProducts', function () {
      order = [];
      saveOrder();
    });

    saveOrder();
  });
})(jQuery);
