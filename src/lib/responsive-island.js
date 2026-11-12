/** Defer device-specific islands without losing native SSR links or resize support. */
export function scheduleResponsiveIsland(element, mount, win = window) {
  const media = {
    'mega-menu': '(min-width: 768px)',
    'header-mobile-search': '(max-width: 767.98px)',
  }[element.dataset.jluxeIsland];
  if (!media || typeof win.matchMedia !== 'function') { mount(); return () => {}; }
  const query = win.matchMedia(media);
  let mounted = false;
  const run = () => {
    if (mounted || !query.matches || !element.isConnected) return;
    mounted = true;
    cleanup();
    mount();
  };
  const cleanup = () => {
    if (query.removeEventListener) query.removeEventListener('change', run);
    else if (query.removeListener) query.removeListener(run);
  };
  if (query.addEventListener) query.addEventListener('change', run);
  else if (query.addListener) query.addListener(run);
  run();
  return cleanup;
}
