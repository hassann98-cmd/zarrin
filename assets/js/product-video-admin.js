(function ($) {
  'use strict';
  function bind(buttonSelector, inputSelector, nameSelector, removeSelector, type, title) {
    $(document).on('click', buttonSelector, function (event) {
      event.preventDefault();
      var frame = wp.media({ title: title, button: { text: 'استفاده از فایل' }, library: { type: type }, multiple: false });
      frame.on('select', function () {
        var item = frame.state().get('selection').first().toJSON();
        if (!item || (type === 'video' && !/^video\/(mp4|webm)$/i.test(item.mime || '')) || (type === 'image' && !/^image\//i.test(item.mime || ''))) return;
        $(inputSelector).val(item.id).trigger('change');
        $(nameSelector).text(item.filename || item.title || 'فایل انتخاب شد');
        $(removeSelector).prop('hidden', false);
      });
      frame.open();
    });
    $(document).on('click', removeSelector, function (event) {
      event.preventDefault();
      $(inputSelector).val('').trigger('change');
      $(nameSelector).text(type === 'video' ? 'ویدیویی انتخاب نشده است' : 'پوستر انتخاب نشده است؛ تصویر شاخص محصول استفاده می‌شود.');
      $(this).prop('hidden', true);
    });
  }
  $(function () {
    bind('.jluxe-product-video-select', '#_jluxe_product_video_id', '.jluxe-product-media-name', '.jluxe-product-video-remove', 'video', 'انتخاب ویدیوی محصول');
    bind('.jluxe-product-poster-select', '#_jluxe_product_video_poster_id', '.jluxe-product-poster-name', '.jluxe-product-poster-remove', 'image', 'انتخاب پوستر ویدیو');
  });
})(jQuery);
