import { useEffect } from 'react';

// Bounded, low-priority warmup; do not download a whole catalogue on mobile.
const warmed = new Set();
export function warmCategoryImages(categories, limit = 6, win = window) {
  if (win.navigator?.connection?.saveData || /(^|-)2g$/.test(win.navigator?.connection?.effectiveType || '')) return;
  categories.slice(0, limit).forEach(category => {
    const src = category.iconImage;
    if (!src || warmed.has(src) || warmed.size >= 32) return;
    warmed.add(src);
    const image = new win.Image();
    image.fetchPriority = 'low';
    image.decoding = 'async';
    image.src = src;
  });
}
export function useCategoryImageWarmup(categories) {
  useEffect(() => {
    const run = () => warmCategoryImages(categories);
    if (window.requestIdleCallback) {
      const id = window.requestIdleCallback(run, {timeout: 3000});
      return () => window.cancelIdleCallback(id);
    }
    const id = window.setTimeout(run, 1800);
    return () => window.clearTimeout(id);
  }, [categories]);
}
