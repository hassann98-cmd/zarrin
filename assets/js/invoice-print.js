(function () {
  'use strict';

  function attachPrintControl() {
    var printButton = document.querySelector('[data-invoice-print]');
    if (printButton) {
      printButton.addEventListener('click', function () {
        if (typeof window.print === 'function') window.print();
      });
    }

    if (!document.body || document.body.getAttribute('data-auto-print') !== '1') return;

    var waitForFontsAndPrint = function () {
      var printInvoice = function () {
        window.setTimeout(function () {
          if (typeof window.print === 'function') window.print();
        }, 250);
      };
      var fontsReady = document.fonts && document.fonts.ready;
      if (fontsReady && typeof fontsReady.then === 'function') {
        fontsReady.then(printInvoice, printInvoice);
      } else {
        printInvoice();
      }
    };
    if (document.readyState === 'complete') {
      waitForFontsAndPrint();
    } else {
      window.addEventListener('load', waitForFontsAndPrint);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachPrintControl);
  } else {
    attachPrintControl();
  }
})();
