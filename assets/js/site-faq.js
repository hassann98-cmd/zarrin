/* Accessible FAQ disclosures use native <details>/<summary>; this only adds a smooth close transition. */
(function () {
  "use strict";

  var rootSelector = ".jluxe-site-faq";
  var summarySelector = ".jluxe-site-faq__trigger";
  var reducedMotion = Boolean(
    window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches,
  );

  document.addEventListener("click", function (event) {
    var target = event.target;
    if (!target || typeof target.closest !== "function") return;

    var summary = target.closest(summarySelector);
    if (!summary) return;

    var root = summary.closest(rootSelector);
    var details = summary.closest("details");
    var panel = details && details.querySelector(".jluxe-site-faq__panel");
    if (!root || !details || !panel) return;

    event.preventDefault();
    if (details.dataset.jluxeFaqAnimating === "closing") return;

    if (!details.open) {
      details.classList.remove("is-closing");
      panel.removeAttribute("aria-hidden");
      panel.inert = false;
      details.open = true;
      if (!reducedMotion) {
        details.classList.add("is-opening");
        // Ensure the closed row is painted before switching it to 1fr.
        panel.getBoundingClientRect();
        window.requestAnimationFrame(function () {
          if (details.open && !details.classList.contains("is-closing")) {
            details.classList.remove("is-opening");
          }
        });
      }
      return;
    }

    details.classList.remove("is-opening");
    details.dataset.jluxeFaqAnimating = "closing";
    details.classList.add("is-closing");
    panel.setAttribute("aria-hidden", "true");
    panel.inert = true;

    var timer = 0;
    var finished = false;
    function finishClose() {
      if (finished) return;
      finished = true;
      if (timer) window.clearTimeout(timer);
      panel.removeEventListener("transitionend", onTransitionEnd);
      details.open = false;
      details.classList.remove("is-closing");
      delete details.dataset.jluxeFaqAnimating;
    }
    function onTransitionEnd(transitionEvent) {
      if (
        transitionEvent.target === panel &&
        transitionEvent.propertyName === "grid-template-rows"
      ) {
        finishClose();
      }
    }

    if (reducedMotion) {
      finishClose();
      return;
    }

    panel.addEventListener("transitionend", onTransitionEnd);
    timer = window.setTimeout(finishClose, 450);
  });
})();
