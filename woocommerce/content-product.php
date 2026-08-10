<?php
/**
 * Thẻ sản phẩm trong lưới danh sách - phục dựng theo cấu trúc
 * <article class="vuzix-product-card"> trong original-collections/all.html,
 * dữ liệu (ảnh, tên, giá, link) lấy thật/động từ WooCommerce.
 *
 * Override của woocommerce/templates/content-product.php.
 */
defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

$image_id   = $product->get_image_id();
$image_html = $image_id
	? wp_get_attachment_image( $image_id, 'woocommerce_single', false, array( 'class' => 'vuzix-product-card__image', 'loading' => 'lazy' ) )
	: wc_placeholder_img( 'woocommerce_single', array( 'class' => 'vuzix-product-card__image' ) );
?>
<li <?php wc_product_class( 'vuzix-product-card', $product ); ?>>
	<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="vuzix-product-card__link">
		<div class="vuzix-product-card__image-wrap">
			<?php echo wp_kses_post( $image_html ); ?>
		</div>
		<div class="vuzix-product-card__content">
			<h2><?php echo esc_html( $product->get_name() ); ?></h2>
			<div class="vuzix-product-card__price">
				<span class="dualPrice"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
			</div>
		</div>
	</a>
</li>
