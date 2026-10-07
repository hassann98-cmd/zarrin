/** Keep native scrolling inside search results; Lenis sees data-lenis-prevent on the panel. */
export function containSearchScroll(element, win = element.ownerDocument.defaultView, fraction = 0.7, { positioned = false } = {}) {
  let frame = 0;
  const update = () => {
    frame = 0;
    if (element.classList.contains('jluxe-header-search-results--mobile')) {
      // A stable root scrollbar gutter can make the header narrower than 100vw.
      const header = element.closest('.jluxe-header-bar');
      const width = header?.getBoundingClientRect().width || element.ownerDocument.documentElement.clientWidth || win.innerWidth;
      const value = `${Math.max(0, Math.min(420, width - 32))}px`;
      if (element.style.width !== value) element.style.width = value;
    }
    const viewport = win.visualViewport;
    const height = viewport?.height || win.innerHeight;
    const bottom = (viewport?.offsetTop || 0) + height;
    const available = Math.max(0, Math.min(height * fraction, bottom - element.getBoundingClientRect().top - 12));
    const value = `${Math.floor(available)}px`;
    if (element.style.maxHeight !== value) element.style.maxHeight = value;
  };
  const schedule = () => { if (!frame) frame = win.requestAnimationFrame(update); };
  const wheel = event => {
    // Do not turn trackpad pinch-to-zoom or horizontal gestures into vertical scroll.
    if (event.ctrlKey || !event.deltaY || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
    event.stopPropagation(); // Preserve native inner scrolling without feeding Lenis/window listeners.
    const maximum = Math.max(0, element.scrollHeight - element.clientHeight);
    if (maximum <= 1 || (event.deltaY < 0 && element.scrollTop <= 0) || (event.deltaY > 0 && element.scrollTop >= maximum - 1)) {
      if (event.cancelable) event.preventDefault();
    }
  };
  element.addEventListener('wheel', wheel, { passive: false });
  if (!positioned) {
  win.addEventListener('resize', schedule, { passive: true });
  win.addEventListener('scroll', schedule, { passive: true });
  win.visualViewport?.addEventListener('resize', schedule);
  win.visualViewport?.addEventListener('scroll', schedule);
  update();
  }
  return () => {
    if (frame) win.cancelAnimationFrame(frame);
    element.removeEventListener('wheel', wheel);
    win.removeEventListener('resize', schedule);
    win.removeEventListener('scroll', schedule);
    win.visualViewport?.removeEventListener('resize', schedule);
    win.visualViewport?.removeEventListener('scroll', schedule);
  };
}
