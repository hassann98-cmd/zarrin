export function keepOtpInputVisible(input, browserWindow = window) {
  if (!input || typeof input.getBoundingClientRect !== "function") return false;
  const viewport = browserWindow.visualViewport;
  const top = Number.isFinite(viewport?.offsetTop) ? viewport.offsetTop : 0;
  const height = Number.isFinite(viewport?.height)
    ? viewport.height
    : browserWindow.innerHeight;
  if (!Number.isFinite(height) || height <= 0) return false;

  const rect = input.getBoundingClientRect();
  const margin = 20;
  const visibleBottom = top + height;
  if (rect.top >= top + margin && rect.bottom <= visibleBottom - margin) {
    return false;
  }
  if (typeof input.scrollIntoView !== "function") return false;

  try {
    input.scrollIntoView({ block: "center", inline: "nearest", behavior: "auto" });
  } catch {
    // Older iOS/Samsung WebViews support only the boolean form.
    try {
      input.scrollIntoView(false);
    } catch {
      return false;
    }
  }
  return true;
}

export function watchOtpInputViewport(input, browserWindow = window) {
  if (!input || typeof input.addEventListener !== "function") return () => {};

  let frameId = null;
  let timerId = null;
  let active = true;
  const viewport = browserWindow.visualViewport;
  const reveal = () => {
    frameId = null;
    timerId = null;
    if (active) keepOtpInputVisible(input, browserWindow);
  };
  const scheduleReveal = () => {
    if (!active) return;
    if (frameId !== null && typeof browserWindow.cancelAnimationFrame === "function") {
      browserWindow.cancelAnimationFrame(frameId);
      frameId = null;
    }
    if (timerId !== null) {
      browserWindow.clearTimeout(timerId);
      timerId = null;
    }
    if (typeof browserWindow.requestAnimationFrame === "function") {
      frameId = browserWindow.requestAnimationFrame(reveal);
    } else {
      timerId = browserWindow.setTimeout(reveal, 50);
    }
  };

  input.addEventListener("focus", scheduleReveal);
  browserWindow.addEventListener("resize", scheduleReveal);
  browserWindow.addEventListener("orientationchange", scheduleReveal);
  viewport?.addEventListener("resize", scheduleReveal);
  viewport?.addEventListener("scroll", scheduleReveal);
  scheduleReveal();

  return () => {
    active = false;
    input.removeEventListener("focus", scheduleReveal);
    browserWindow.removeEventListener("resize", scheduleReveal);
    browserWindow.removeEventListener("orientationchange", scheduleReveal);
    viewport?.removeEventListener("resize", scheduleReveal);
    viewport?.removeEventListener("scroll", scheduleReveal);
    if (frameId !== null && typeof browserWindow.cancelAnimationFrame === "function") {
      browserWindow.cancelAnimationFrame(frameId);
    }
    if (timerId !== null) browserWindow.clearTimeout(timerId);
  };
}
