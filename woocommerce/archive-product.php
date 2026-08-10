<?php
/**
 * Trang danh sách sản phẩm (Shop / Danh mục sản phẩm / /collections/all)
 * Phục dựng theo giao diện gốc trong original-collections/all.html
 * (Hero banner vzx-hero + lưới vuzix-collection__grid + thẻ vuzix-product-card),
 * hiển thị danh sách sản phẩm động từ WooCommerce.
 *
 * Override của woocommerce/templates/archive-product.php.
 */
defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

// Tùy chỉnh các action mặc định của WC để khớp với giao diện gốc
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
?>
<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">

	<section class="vzx-hero vzx-hero--transparent-header" style="--hero-height: 420px;">
		<div class="vzx-hero__overlay"></div>
		<div class="page-width vzx-hero__inner">
			<div class="vzx-hero__content">
				<p class="vzx-hero__eyebrow"><?php esc_html_e( 'Products', 'vuzix-practice' ); ?></p>
				<h1 style="font-size: 3em; color: #fff; margin: 0;"><?php esc_html_e( 'Products', 'vuzix-practice' ); ?></h1>
			</div>
		</div>
	</section>
	<script>document.documentElement.classList.add('vzx-transparent-header-active');</script>

	<div class="vuzix-collection">
		<div class="page-width">

			<?php do_action( 'woocommerce_before_main_content' ); ?>

			<div class="vuzix-collection__header">
				<div class="vuzix-collection__actions">
					<?php do_action( 'woocommerce_before_shop_loop' ); // Form sắp xếp sản phẩm ?>
				</div>
			</div>

			<?php if ( woocommerce_product_loop() ) : ?>

				<?php woocommerce_product_loop_start(); // Trả về <ul class="vuzix-collection__grid"> ?>

				<?php
				if ( wc_get_loop_prop( 'total' ) ) {
					while ( have_posts() ) {
						the_post();
						do_action( 'woocommerce_shop_loop' );
						wc_get_template_part( 'content', 'product' ); // Render <li class="vuzix-product-card">
					}
				}
				?>

				<?php woocommerce_product_loop_end(); ?>

				<div class="vuzix-collection__pagination">
					<?php do_action( 'woocommerce_after_shop_loop' ); ?>
				</div>

			<?php else : ?>
				<?php do_action( 'woocommerce_no_products_found' ); ?>
			<?php endif; ?>

			<?php do_action( 'woocommerce_after_main_content' ); ?>

		</div>
	</div>

</main>

<?php
get_footer( 'shop' );
