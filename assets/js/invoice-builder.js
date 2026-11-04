(function () {
  'use strict';

  function initInvoiceLogoPicker() {
    var root = document.getElementById('jluxe-invoice-builder');
    if (!root) return;

    var selectButton = root.querySelector('[data-jib-select-logo]');
    var removeButton = root.querySelector('[data-jib-remove-logo]');
    var logoId = root.querySelector('[data-jib-logo-id]');
    var logoUrl = root.querySelector('[data-jib-logo-url]');
    var preview = root.querySelector('[data-jib-logo-preview]');
    var frame = null;

    if (!selectButton || !logoId || !logoUrl || !preview) return;

    function showPreview(url) {
      while (preview.firstChild) preview.removeChild(preview.firstChild);
      if (!url) {
        var placeholder = document.createElement('span');
        placeholder.className = 'dashicons dashicons-format-image';
        placeholder.setAttribute('aria-hidden', 'true');
        preview.appendChild(placeholder);
        return;
      }
      var image = document.createElement('img');
      image.src = url;
      image.alt = 'پیش‌نمایش لوگو';
      preview.appendChild(image);
    }

    selectButton.addEventListener('click', function (event) {
      event.preventDefault();
      if (!window.wp || typeof window.wp.media !== 'function') return;
      if (!frame) {
        frame = window.wp.media({
          title: 'انتخاب لوگوی فاکتور',
          button: { text: 'استفاده از این تصویر' },
          library: { type: 'image' },
          multiple: false
        });
        frame.on('select', function () {
          var selection = frame.state().get('selection');
          var attachment = selection && typeof selection.first === 'function' ? selection.first() : null;
          var data = attachment && typeof attachment.toJSON === 'function' ? attachment.toJSON() : null;
          if (!data || !data.url) return;
          logoId.value = data.id ? String(data.id) : '';
          logoUrl.value = data.url;
          showPreview(data.url);
        });
      }
      frame.open();
    });

    if (removeButton) {
      removeButton.addEventListener('click', function (event) {
        event.preventDefault();
        logoId.value = '';
        logoUrl.value = '';
        showPreview('');
      });
    }

    logoUrl.addEventListener('change', function () {
      if (logoUrl.value) logoId.value = '';
      showPreview(logoUrl.value.trim());
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initInvoiceLogoPicker);
  } else {
    initInvoiceLogoPicker();
  }
})();
