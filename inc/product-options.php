<?php
/** R167 — product guarantees and post-add recommendations, for all Woo product types. */
defined( 'ABSPATH' ) || exit;

function jluxe_product_options_tab( array $tabs ): array {
	$tabs['jluxe_options'] = array(
		'label' => 'گزینه‌های زرین',
		'target' => 'jluxe_product_options',
		'class' => array(), // Not inside General's show_if_simple/show_if_external scope.
		'priority' => 75,
	);
	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'jluxe_product_options_tab' );

function jluxe_render_product_options_panel(): void {
	global $post;
	$id = (int) ( $post->ID ?? 0 );
	if ( $id < 1 ) {
		return;
	}
	echo '<div id="jluxe_product_options" class="panel woocommerce_options_panel hidden">';
	echo '<input type="hidden" name="_jluxe_product_options_present" value="1">';
	wp_nonce_field( 'jluxe_product_options_' . $id, '_jluxe_product_options_nonce' );
	jluxe_render_product_badge_fields();
	jluxe_render_suggested_modal_toggle_field();
	echo '</div>';
}
add_action( 'woocommerce_product_data_panels', 'jluxe_render_product_options_panel' );

/** Only a submitted editor panel may clear unchecked fields; REST/bulk/stock saves must not. */
function jluxe_save_product_options( $product ): void {
	if ( ! $product instanceof WC_Product || ! current_user_can( 'edit_post', $product->get_id() ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || '1' !== ( $_POST['_jluxe_product_options_present'] ?? null ) ) {
		return;
	}
	$nonce = $_POST['_jluxe_product_options_nonce'] ?? '';
	if ( ! is_string( $nonce ) || ! wp_verify_nonce( wp_unslash( $nonce ), 'jluxe_product_options_' . $product->get_id() ) ) {
		return;
	}
	foreach ( array( '_jluxe_badge_authenticity', '_jluxe_badge_warranty', '_jluxe_suggested_modal_enabled' ) as $key ) {
		$value = $_POST[ $key ] ?? '';
		$product->update_meta_data( $key, is_string( $value ) && 'yes' === wp_unslash( $value ) ? 'yes' : 'no' );
	}
	// WooCommerce saves the object once after this hook, including these metadata changes.
}
add_action( 'woocommerce_admin_process_product_object', 'jluxe_save_product_options' );

/** The same server-rendered badges in both product layouts; no JavaScript required. */
function jluxe_render_product_trust_badges( int $product_id ): void {
	$badges = jluxe_get_product_trust_badges( $product_id );
	if ( ! $badges ) {
		return;
	}
	?>
	<div class="jluxe-product-trust-badges mt-4 flex flex-wrap items-center gap-2" data-jluxe-product-trust-badges>
		<?php foreach ( $badges as $badge ) : ?>
			<span class="inline-flex w-fit items-center gap-1 rounded-full <?php echo esc_attr( $badge['bg_class'] ); ?> px-2 py-1 text-[11px] font-bold <?php echo esc_attr( $badge['text_class'] ); ?>">
				<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"></path></svg>
				<?php echo esc_html( $badge['label'] ); ?>
			</span>
		<?php endforeach; ?>
	</div>
	<?php
}
