# WooCommerce browser contract fixture

`add-to-cart-variation-11.1.2.js` is the unmodified official WooCommerce 11.1.2 variation-form controller, used only by regression tests. It is not enqueued by the theme; installed WooCommerce remains the runtime owner.

Source: https://github.com/woocommerce/woocommerce/blob/11.1.2/plugins/woocommerce/client/legacy/js/frontend/add-to-cart-variation.js

SHA-256: `e901d7dd32a89f3bdbaefcd7589949c35176cc58b5b307be07bdbe579db7cdc9`

Copyright WooCommerce contributors. WooCommerce is licensed under GNU GPL v3 or later: https://github.com/woocommerce/woocommerce/blob/11.1.2/license.txt

The fixtures supply synthetic products and mock network/HTML-template I/O. They exercise real WooCommerce selection, initialization and events, but are not a payment or live-shop test.
