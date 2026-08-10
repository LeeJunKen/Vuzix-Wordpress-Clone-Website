<?php
/**
 * Form sắp xếp sản phẩm phục dựng theo <facet-filters-form class="vuzix-collection__sort">
 * trong original-collections/all.html.
 *
 * Override của woocommerce/templates/loop/orderby.php.
 */
defined( 'ABSPATH' ) || exit;

$catalog_orderby_options = apply_filters(
	'woocommerce_catalog_orderby',
	array(
		'menu_order'       => __( 'Featured', 'vuzix-practice' ),
		'most-relevant'    => __( 'Most relevant', 'vuzix-practice' ),
		'popularity'       => __( 'Best selling', 'vuzix-practice' ),
		'title-asc'        => __( 'Alphabetically, A-Z', 'vuzix-practice' ),
		'title-desc'       => __( 'Alphabetically, Z-A', 'vuzix-practice' ),
		'price'            => __( 'Price, low to high', 'vuzix-practice' ),
		'price-desc'       => __( 'Price, high to low', 'vuzix-practice' ),
		'date-asc'         => __( 'Date, old to new', 'vuzix-practice' ),
		'date'             => __( 'Date, new to old', 'vuzix-practice' ),
	)
);

$orderby = isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : 'menu_order';
?>
<facet-filters-form class="vuzix-collection__sort">
	<form class="woocommerce-ordering" method="get" id="FacetSortForm">
		<label for="SortBy"><?php esc_html_e( 'Sort by:', 'vuzix-practice' ); ?></label>
		<select name="orderby" id="SortBy" class="vuzix-collection__sort-select" onchange="this.form.submit()">
			<?php foreach ( $catalog_orderby_options as $id => $name ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $orderby, $id ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="hidden" name="paged" value="1" />
		<?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?>
	</form>
</facet-filters-form>
