<?php
/**
 * File template hiển thị các trang của WooCommerce.
 * Định nghĩa cấu trúc bao quanh để tích hợp WooCommerce vào theme Vuzix.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>
<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
	<div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
		<?php 
		// Hàm này tự động gọi nội dung của WooCommerce (Trang Cửa hàng, Chi tiết sản phẩm...)
		woocommerce_content(); 
		?>
	</div>
</main>
<?php
get_footer();
