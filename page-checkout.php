<?php
/** Native, minimal WooCommerce checkout page. */
defined( 'ABSPATH' ) || exit;

get_header();

if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
	$order_id  = absint( get_query_var( 'order-received' ) );
	$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
	$order     = wc_get_order( $order_id );

	if ( $order && hash_equals( $order->get_order_key(), $order_key ) ) :
		?>
		<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
			<section class="vuzix-order-success">
				<p class="vuzix-order-success__eyebrow"><?php esc_html_e( 'Cảm ơn bạn', 'vuzix-practice' ); ?></p>
				<h1><?php esc_html_e( 'Đơn hàng đã được gửi thành công', 'vuzix-practice' ); ?></h1>
				<p><?php printf( esc_html__( 'Mã đơn hàng của bạn là #%s. Chúng tôi sẽ liên hệ xác nhận qua số điện thoại bạn đã cung cấp.', 'vuzix-practice' ), esc_html( $order->get_order_number() ) ); ?></p>
				<a class="vuzix-order-success__button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Quay về trang chủ', 'vuzix-practice' ); ?></a>
			</section>
		</main>
		<style>
			.vuzix-order-success { max-width: 760px; margin: 90px auto; padding: 0 28px; font-family: Inter, sans-serif; color: #111; }
			.vuzix-order-success__eyebrow { margin: 0 0 14px; color: #16a34a; font-size: 14px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
			.vuzix-order-success h1 { margin: 0 0 22px; font-size: clamp(38px, 5vw, 62px); font-weight: 500; line-height: 1; letter-spacing: -.045em; }
			.vuzix-order-success p { max-width: 600px; color: #555; font-size: 18px; line-height: 1.55; }
			.vuzix-order-success__button { display: inline-flex; align-items: center; justify-content: center; min-height: 52px; margin-top: 16px; padding: 0 28px; background: #000; color: #fff; font-weight: 700; letter-spacing: .06em; text-decoration: none; text-transform: uppercase; }
		</style>
		<?php
	else :
		?>
		<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1"><div class="vuzix-order-success"><h1><?php esc_html_e( 'Không tìm thấy đơn hàng', 'vuzix-practice' ); ?></h1></div></main>
		<?php
	endif;
} elseif ( function_exists( 'WC' ) && WC()->cart && WC()->checkout() ) {
	wc_get_template( 'checkout/form-checkout.php', array( 'checkout' => WC()->checkout() ) );
}

get_footer();
