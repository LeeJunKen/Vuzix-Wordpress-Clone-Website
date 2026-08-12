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
		'menu_order'       => __( 'Nổi bật', 'vuzix-practice' ),
		'most-relevant'    => __( 'Liên quan nhất', 'vuzix-practice' ),
		'popularity'       => __( 'Bán chạy nhất', 'vuzix-practice' ),
		'title-asc'        => __( 'Tên, A-Z', 'vuzix-practice' ),
		'title-desc'       => __( 'Tên, Z-A', 'vuzix-practice' ),
		'price'            => __( 'Giá, thấp đến cao', 'vuzix-practice' ),
		'price-desc'       => __( 'Giá, cao đến thấp', 'vuzix-practice' ),
		'date-asc'         => __( 'Ngày, cũ đến mới', 'vuzix-practice' ),
		'date'             => __( 'Ngày, mới đến cũ', 'vuzix-practice' ),
	)
);

$orderby = isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : 'menu_order';
?>
<facet-filters-form class="vuzix-collection__sort">
	<form class="woocommerce-ordering" method="get" id="FacetSortForm">
		<label for="SortBy"><?php esc_html_e( 'Sắp xếp theo:', 'vuzix-practice' ); ?></label>
		<select name="orderby" id="SortBy" class="vuzix-collection__sort-select" onchange="this.form.submit()">
			<?php foreach ( $catalog_orderby_options as $id => $name ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $orderby, $id ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="hidden" name="paged" value="1" />
		<?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?>
	</form>
</facet-filters-form>
