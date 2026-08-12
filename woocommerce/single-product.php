<?php
/**
 * Single Product Template override.
 * Phục dựng giao diện chi tiết sản phẩm từ các file original-products/*.html tĩnh
 * và tích hợp các logic động (AddToCart, Giá, Tên...) của WooCommerce.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'vuzix_render_fallback_product_page' ) ) {
	/**
	 * Giao diện dự phòng đẹp mắt khi không tìm thấy tệp HTML mẫu tương ứng cho sản phẩm.
	 */
	function vuzix_render_fallback_product_page( $product ) {
		$hero_image = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'full' ) : '';
		?>
		<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
			<script>document.documentElement.classList.add('vzx-transparent-header-active');</script>
			<style>
				.vzx-transparent-header-active .header a, .vzx-transparent-header-active .header summary, .vzx-transparent-header-active .header__menu-item, .vzx-transparent-header-active .header__icon, .vzx-transparent-header-active .header svg { color: #fff !important; stroke: currentColor !important; }
				.vuzix-dynamic-product-hero { position: relative; min-height: 700px; overflow: hidden; color: #fff; background: #111; }
				.vuzix-dynamic-product-hero::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(0,0,0,.78), rgba(0,0,0,.34) 56%, rgba(0,0,0,.08)); z-index: 1; }
				.vuzix-dynamic-product-hero__image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
				.vuzix-dynamic-product-hero__content { position: relative; z-index: 2; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; min-height: 700px; width: min(100%, 1280px); margin: 0 auto; padding: 170px 40px 100px; }
				.vuzix-dynamic-product-hero__eyebrow { margin: 0 0 16px; color: #45c3e8; font-size: 18px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; }
				.vuzix-dynamic-product-hero h1 { max-width: 960px; margin: 0; color: #fff; font-family: Inter, sans-serif; font-size: 3em; font-weight: 500; line-height: .96; letter-spacing: -.035em; }
				@media screen and (max-width: 749px) { .vuzix-dynamic-product-hero, .vuzix-dynamic-product-hero__content { min-height: 500px; } .vuzix-dynamic-product-hero__content { padding: 150px 20px 54px; } .vuzix-dynamic-product-hero h1 { font-size: 42px; } }
			</style>
			<section class="vuzix-dynamic-product-hero">
				<?php if ( $hero_image ) : ?><img class="vuzix-dynamic-product-hero__image" src="<?php echo esc_url( $hero_image ); ?>" alt=""><?php endif; ?>
				<div class="vuzix-dynamic-product-hero__content">
					<p class="vuzix-dynamic-product-hero__eyebrow"><?php esc_html_e( 'Sản phẩm', 'vuzix-practice' ); ?></p>
					<h1><?php echo esc_html( $product->get_name() ); ?></h1>
				</div>
			</section>
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

get_header( 'shop' );

while ( have_posts() ) :
	the_post();
	global $product;
	$product = wc_get_product( get_the_ID() );
	// Use one stable product layout for every WooCommerce product.
	$original_file = 'original-products/4800mah-extended-use-power-bank.html';

	if ( ! empty( $original_file ) ) {
		// 1. Lấy nội dung phần <main> từ file HTML mẫu
		$main_content = vuzix_get_main_content( $original_file );

		if ( ! empty( $main_content ) ) {
			// Preserve the original product hero; only make the EasyLockdown wrapper visible.
			$main_content = preg_replace(
				'/(<div class=[\'\"]easylockdown-content[\'\"][^>]*style=[\'\"])display\s*:\s*none;?([\'\"][^>]*>)/i',
				'$1display:block;$2',
				$main_content,
				1
			);
			// Product detail dùng header nền sáng. Bỏ script Shopify bật header
			// trong suốt, vì nó khiến menu màu trắng biến mất trên nền này.
			$main_content = preg_replace(
				'#<script\b[^>]*>\s*document\.documentElement\.classList\.add\(\s*[\'\"]vzx-transparent-header-active[\'\"]\s*\)\s*;?\s*</script>#i',
				'',
				$main_content
			);

			// Loại bỏ phần banner đăng nhập ẩn (Easylockdown) nếu có
			$main_content = preg_replace(
				'/<div class=[\'"]easylockdown-content[\'"] style=[\'"]display:none;[\'"]>.*?(?=<section id="shopify-section-template--[a-zA-Z0-9_-]+__main")/s',
				'',
				$main_content
			);
			// 2. Thay thế tiêu đề tĩnh bằng tiêu đề động từ WooCommerce
			$main_content = preg_replace_callback(
				'#(<section\b[^>]*class=[\'\"][^\'\"]*vzx-hero[^\'\"]*[\'\"][^>]*>.*?<h1[^>]*>).*?(</h1>)#si',
				function ( $matches ) use ( $product ) {
					return $matches[1] . esc_html( $product->get_name() ) . $matches[2];
				},
				$main_content,
				1
			);

			// Use WordPress/WooCommerce media for the product gallery and lightbox.
			$main_content = preg_replace(
				'#<media-gallery\b[^>]*>.*?</media-gallery>#s',
				vuzix_get_wc_product_gallery_markup( $product ),
				$main_content,
				1
			);
			$main_content = preg_replace( '#<product-modal\b[^>]*>.*?</product-modal>#s', '', $main_content, 1 );

			$main_content = preg_replace(
				'/<div class="product__title"\s*[^>]*>.*?<\/div>/s',
				'<div class="product__title"><h1 class="h2" style="font-family: \'Inter\', sans-serif !important; letter-spacing: -0.035em !important; font-weight: 500 !important; font-size: 2.5rem; margin: 0 0 10px;">' . esc_html( $product->get_name() ) . '</h1></div>',
				$main_content
			);

			// 3. Thay thế giá tĩnh bằng giá động từ WooCommerce
			$main_content = preg_replace(
				'#<div class="product__description[^\"]*"[^>]*>.*?</div>#s',
				'<div class="product__description rte quick-add-hidden">' . wp_kses_post( apply_filters( 'the_content', $product->get_description() ) ) . '</div>',
				$main_content,
				1
			);
			$main_content = preg_replace( '#<a\b[^>]*class="[^"]*product__view-details[^"]*"[^>]*>.*?</a>#s', '', $main_content );

			$main_content = preg_replace(
				'/<div id="price-template--28892985000102__main"[^>]*>.*?(?=<div\s*>\s*<form[^>]*action="[^"]*\/cart\/add")/s',
				'<div id="price-template--28892985000102__main" role="status"><div class="price price--large"><span class="price-item price-item--regular"><span class="dualPrice">' . $product->get_price_html() . '</span></span></div></div>',
				$main_content
			);

			// Render WooCommerce purchasing controls. Quantity is suppressed globally
			// in functions.php so every purchase uses one item by default.
			ob_start();
			woocommerce_template_single_add_to_cart();
			$wc_form = ob_get_clean();
			$main_content = preg_replace(
				'/<div\s*>\s*<form[^>]*action="[^"]*\/cart\/add"[^>]*id="product-form-installment-template-.*?(?=<div[^>]*class="[^"]*product__description)/s',
				'<div class="vuzix-wc-add-to-cart-wrapper">' . $wc_form . '</div>',
				$main_content
			);

			if ( strpos( $main_content, 'vzx-hero' ) !== false ) {
				echo "<script>document.documentElement.classList.add('vzx-transparent-header-active');</script>";
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
