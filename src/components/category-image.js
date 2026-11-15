import { j as jsx } from '../lib/jsx.js';
import { G as Grid2x2 } from '../icons/grid-2x2.js';

export function CategoryImage({src, size = 24, className}) {
  return jsx.jsxs('span', {
    className: 'jluxe-category-image-slot',
    style: {width:size, height:size},
    'aria-hidden': true,
    children: [
      jsx.jsx(Grid2x2, {className:'jluxe-category-image-placeholder'}),
      jsx.jsx('img', {
        key:src, src, alt:'', width:size, height:size, loading:'eager', decoding:'async',
        className,
        onLoad:event => { event.currentTarget.parentElement.dataset.loaded='true'; },
        onError:event => { event.currentTarget.style.visibility='hidden'; event.currentTarget.parentElement.dataset.loaded='false'; },
      }),
    ],
  });
}
