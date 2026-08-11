<?php
/**
 * Minimal WooCommerce checkout.
 * The form uses WooCommerce's native hooks, validation and order creation.
 */
defined( 'ABSPATH' ) || exit;

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'Bạn cần đăng nhập để đặt hàng.', 'woocommerce' ) ) );
	return;
}

do_action( 'woocommerce_before_checkout_form', $checkout );
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout vuzix-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
	<div class="vuzix-checkout__layout">
		<section class="vuzix-checkout__details">
			<h1><?php esc_html_e( 'Đặt hàng', 'vuzix-practice' ); ?></h1>
			<h2><?php esc_html_e( 'Thông tin nhận hàng', 'vuzix-practice' ); ?></h2>
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
		</section>

		<aside class="vuzix-checkout__summary">
			<h2><?php esc_html_e( 'Thông tin đơn hàng', 'vuzix-practice' ); ?></h2>
			<?php do_action( 'woocommerce_checkout_order_review' ); ?>
		</aside>
	</div>
</form>

<style>
	.vuzix-checkout { max-width: 1280px; margin: 54px auto 80px; padding: 0 28px; font-family: Inter, sans-serif; color: #111; }
	.vuzix-checkout__layout { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(330px, .75fr); gap: 72px; align-items: start; }
	.vuzix-checkout h1 { margin: 0 0 42px; font-size: clamp(38px, 5vw, 62px); font-weight: 500; letter-spacing: -.045em; }
	.vuzix-checkout h2 { margin: 0 0 24px; font-size: 24px; font-weight: 600; }
	.vuzix-checkout .woocommerce-billing-fields > h3 { display: none; }
	.vuzix-checkout .woocommerce-billing-fields__field-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
	.vuzix-checkout .form-row { float: none !important; width: auto !important; margin: 0 !important; }
	.vuzix-checkout .form-row-wide { grid-column: 1 / -1; }
	.vuzix-checkout label { display: block; margin: 0 0 8px; font-size: 14px; font-weight: 600; }
	.vuzix-checkout .required { color: #b42318; text-decoration: none; }
	.vuzix-checkout input { box-sizing: border-box; width: 100%; min-height: 52px; padding: 12px 14px; border: 1px solid #777; border-radius: 0; font: inherit; }
	.vuzix-checkout__summary { padding: 28px; border: 1px solid #d3d3d3; }
	.vuzix-checkout .shop_table { width: 100%; border-collapse: collapse; margin: 0 0 24px; }
	.vuzix-checkout .shop_table th, .vuzix-checkout .shop_table td { padding: 14px 0; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
	.vuzix-checkout .shop_table td:last-child, .vuzix-checkout .shop_table th:last-child { text-align: right; }
	.vuzix-checkout .woocommerce-checkout-payment { margin-top: 24px; }
	.vuzix-checkout .payment_box { margin: 12px 0; color: #555; font-size: 14px; line-height: 1.5; }
	.vuzix-checkout #place_order { width: 100%; min-height: 54px; border: 1px solid #000; background: #000; color: #fff; font: 700 15px Inter, sans-serif; letter-spacing: .08em; text-transform: uppercase; cursor: pointer; }
	.vuzix-checkout #place_order:hover { background: #fff; color: #000; }
	.vuzix-checkout .woocommerce-terms-and-conditions-wrapper, .vuzix-checkout .woocommerce-form-coupon-toggle, .vuzix-checkout .woocommerce-checkout-payment ul { display: none; }
	@media (max-width: 800px) { .vuzix-checkout { margin-top: 36px; padding: 0 20px; } .vuzix-checkout__layout { grid-template-columns: 1fr; gap: 38px; } .vuzix-checkout__summary { order: -1; } }
	@media (max-width: 520px) { .vuzix-checkout .woocommerce-billing-fields__field-wrapper { grid-template-columns: 1fr; } .vuzix-checkout .form-row-wide { grid-column: auto; } }
</style>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
