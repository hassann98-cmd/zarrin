import { CategoryImage } from './category-image.js';
import { j as jsx } from '../lib/jsx.js';
import { G as Grid2x2 } from '../icons/grid-2x2.js';

/** Shared data contract: PHP resolves theme overrides before WooCommerce images. */
export function CategoryVisual({category}) {
  if (category.iconImage) return jsx.jsx(CategoryImage, {src:category.iconImage, className:'jluxe-mobile-category-image'});
  if (category.iconSvg) return jsx.jsx('span', {
    className: 'size-5 shrink-0 [&>svg]:size-full jluxe-mobile-category-svg',
    'aria-hidden': true, dangerouslySetInnerHTML: {__html: category.iconSvg},
  });
  return jsx.jsx(Grid2x2, {className:'size-5 shrink-0', 'aria-hidden':true});
}
