<?php
/**
 * Single Product Template override.
 * Phục dựng giao diện chi tiết sản phẩm từ các file original-products/*.html tĩnh
 * và tích hợp các logic động (AddToCart, Giá, Tên...) của WooCommerce.
 */
defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

while ( have_posts() ) :
	the_post();
	global $product;
	$product = wc_get_product( get_the_ID() );
	$slug = $product->get_slug();
	$original_file = vuzix_find_original_file( $slug );

	if ( ! empty( $original_file ) ) {
		// 1. Lấy nội dung phần <main> từ file HTML mẫu
		$main_content = vuzix_get_main_content( $original_file );

		if ( ! empty( $main_content ) ) {
			// 2. Thay thế tiêu đề tĩnh bằng tiêu đề động từ WooCommerce
			$main_content = preg_replace(
				'/<div class="product__title"\s*[^>]*>.*?<\/div>/s',
				'<div class="product__title"><h1 class="h2" style="font-family: \'Inter\', sans-serif !important; letter-spacing: -0.035em !important; font-weight: 500 !important; font-size: 2.5rem; margin: 0 0 10px;">' . esc_html( $product->get_name() ) . '</h1></div>',
				$main_content
			);

			// 3. Thay thế giá tĩnh bằng giá động từ WooCommerce
			$main_content = preg_replace(
				'/<div id="price-template--28892985000102__main"[^>]*>.*?(?=<div\s*>\s*<form[^>]*action="[^"]*\/cart\/add")/s',
				'<div id="price-template--28892985000102__main" role="status"><div class="price price--large"><span class="price-item price-item--regular"><span class="dualPrice">' . $product->get_price_html() . '</span></span></div></div>',
				$main_content
			);

			// 4. Thay thế form Add to Cart và quantity tĩnh bằng WooCommerce Add to Cart Form động
			ob_start();
			woocommerce_template_single_add_to_cart();
			$wc_form = ob_get_clean();

			$main_content = preg_replace(
				'/<div\s*>\s*<form[^>]*action="[^"]*\/cart\/add"[^>]*id="product-form-installment-template-.*?(?=<div[^>]*class="[^"]*product__description)/s',
				'<div class="vuzix-wc-add-to-cart-wrapper">' . $wc_form . '</div>',
				$main_content
			);

			// 5. Thêm class để kích hoạt header trong suốt đè lên banner (nếu có section vzx-hero tương ứng)
			if ( strpos( $main_content, 'vzx-hero' ) !== false ) {
				echo "<script>document.documentElement.classList.add('vzx-transparent-header-active');</script>";
			} else {
				echo "<script>document.documentElement.classList.remove('vzx-transparent-header-active');</script>";
			}

			echo $main_content;
		} else {
			vuzix_render_fallback_product_page( $product );
		}
	} else {
		vuzix_render_fallback_product_page( $product );
	}
endwhile;

get_footer( 'shop' );

if ( ! function_exists( 'vuzix_render_fallback_product_page' ) ) {
	/**
	 * Giao diện dự phòng đẹp mắt khi không tìm thấy tệp HTML mẫu tương ứng cho sản phẩm.
	 */
	function vuzix_render_fallback_product_page( $product ) {
		?>
		<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
			<div class="page-width" style="margin-top: 40px; margin-bottom: 40px;">
				<div class="product product--large grid grid--1-col grid--2-col-tablet">
					<div class="grid__item product__media-wrapper">
						<?php
						/**
						 * Hiển thị thư viện ảnh của WooCommerce
						 */
						woocommerce_show_product_images();
						?>
					</div>
					<div class="product__info-wrapper grid__item">
						<div class="product__info-container" style="padding-left: 20px;">
							<div class="product__title">
								<h1 class="h2" style="font-family: 'Inter', sans-serif !important; letter-spacing: -0.035em !important; font-weight: 500 !important; font-size: 2.5rem; margin: 0 0 10px;">
									<?php echo esc_html( $product->get_name() ); ?>
								</h1>
							</div>
							<div class="price price--large" style="margin: 15px 0;">
								<span class="price-item price-item--regular">
									<span class="dualPrice"><?php echo $product->get_price_html(); ?></span>
								</span>
							</div>
							<div class="vuzix-wc-add-to-cart-wrapper">
								<?php woocommerce_template_single_add_to_cart(); ?>
							</div>
							<div class="product__description rte" style="margin-top: 30px; line-height: 1.6; font-size: 15px;">
								<?php echo wp_kses_post( apply_filters( 'the_content', $product->get_description() ) ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</main>
		<?php
	}
}
