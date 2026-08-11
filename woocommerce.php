<?php
/**
 * File template hiển thị các trang của WooCommerce.
 * Định nghĩa cấu trúc bao quanh để tích hợp WooCommerce vào theme Vuzix.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

if ( is_shop() || is_product_taxonomy() ) :
	wc_get_template( 'archive-product.php' );
elseif ( is_cart() ) :
	wc_get_template( 'cart/cart.php' );
elseif ( is_product() ) :
	wc_get_template( 'single-product.php' );
else :
	?>
	<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
		<div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
			<?php 
			woocommerce_content(); 
			?>
		</div>
	</main>
	<?php
endif;

get_footer();
