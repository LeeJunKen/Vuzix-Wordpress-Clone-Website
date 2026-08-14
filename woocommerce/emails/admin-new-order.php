<?php
/**
 * Mẫu email "Đơn hàng mới" gửi cho sales/admin khi có đơn được tạo (on-hold).
 * Override của woocommerce/templates/emails/admin-new-order.php — dùng chung
 * thiết kế với mail cảm ơn khách hàng, chỉ đổi nội dung sang hướng nội bộ.
 */
defined( 'ABSPATH' ) || exit;

$customer_name  = trim( $order->get_formatted_billing_full_name() );
$order_date     = vuzix_format_order_date( $order->get_date_created() );
$payment_method = $order->get_payment_method_title();
$address_1      = $order->get_billing_address_1();
$phone          = $order->get_billing_phone();
$email_address  = $order->get_billing_email();
$edit_order_url = $order->get_edit_order_url();
$none           = __( '(Không cung cấp)', 'vuzix-practice' );
?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $email_heading ); ?></title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			background-color: #f1f5f9;
			margin: 0;
			padding: 0;
			-webkit-font-smoothing: antialiased;
		}
		.wrapper {
			width: 100%;
			table-layout: fixed;
			background-color: #f1f5f9;
			padding: 40px 0;
		}
		.main-container {
			background-color: #ffffff;
			max-width: 600px;
			margin: 0 auto;
			border-radius: 12px;
			overflow: hidden;
			box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
		}
		.header {
			background-color: #0f172a;
			padding: 28px 20px;
			text-align: center;
			border-bottom: 3px solid #38bdf8;
		}
		.header h1 {
			color: #ffffff;
			margin: 0;
			font-size: 28px;
			letter-spacing: 3px;
			font-weight: 800;
		}
		.banner {
			background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
			color: #ffffff;
			padding: 24px 30px;
			text-align: center;
		}
		.banner h2 {
			margin: 0 0 8px 0;
			font-size: 22px;
			color: #38bdf8;
			font-weight: 700;
		}
		.banner p {
			margin: 0;
			font-size: 14px;
			color: #94a3b8;
			line-height: 1.5;
		}
		.content {
			padding: 32px 28px;
		}
		.order-badge {
			background-color: #f0f9ff;
			border: 1px solid #bae6fd;
			border-radius: 8px;
			padding: 16px;
			margin-bottom: 28px;
		}
		.order-badge p {
			margin: 4px 0;
			font-size: 14px;
			color: #0369a1;
		}
		.order-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 28px;
		}
		.order-table th {
			background-color: #f8fafc;
			color: #475569;
			text-align: left;
			padding: 12px 14px;
			font-size: 13px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			border-bottom: 2px solid #e2e8f0;
		}
		.order-table td {
			padding: 16px 14px;
			border-bottom: 1px solid #e2e8f0;
			color: #334155;
			font-size: 14px;
		}
		.order-table td.price {
			text-align: right;
			font-weight: 600;
		}
		.summary-row td {
			border-bottom: none;
			padding: 8px 14px;
		}
		.summary-row.total td {
			font-size: 16px;
			font-weight: bold;
			color: #0f172a;
			border-top: 2px solid #e2e8f0;
			padding-top: 14px;
		}
		.address-box {
			background-color: #f8fafc;
			border: 1px solid #e2e8f0;
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 28px;
		}
		.address-box h3 {
			margin: 0 0 12px 0;
			font-size: 14px;
			color: #0f172a;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		.address-box p {
			margin: 5px 0;
			font-size: 14px;
			color: #475569;
			line-height: 1.5;
		}
		.action-button {
			text-align: center;
			margin-bottom: 8px;
		}
		.action-button a {
			display: inline-block;
			background-color: #0284c7;
			color: #ffffff;
			text-decoration: none;
			font-size: 14px;
			font-weight: 600;
			padding: 12px 28px;
			border-radius: 8px;
		}
		.footer {
			background-color: #0f172a;
			color: #64748b;
			padding: 24px 20px;
			text-align: center;
			font-size: 13px;
			line-height: 1.6;
		}
		.footer a {
			color: #38bdf8;
			text-decoration: none;
		}
	</style>
</head>
<body>
	<div class="wrapper">
		<div class="main-container">
			<!-- Header Logo -->
			<div class="header">
				<h1>VUZIX</h1>
			</div>

			<!-- Banner -->
			<div class="banner">
				<h2><?php esc_html_e( 'Khách hàng vừa chốt đơn hàng mới', 'vuzix-practice' ); ?></h2>
				<p><?php esc_html_e( 'Vui lòng liên hệ khách hàng để xác nhận và xử lý đơn hàng sớm nhất.', 'vuzix-practice' ); ?></p>
			</div>

			<!-- Content -->
			<div class="content">
				<!-- Order Info Summary -->
				<div class="order-badge">
					<p><strong><?php esc_html_e( 'Mã đơn hàng:', 'vuzix-practice' ); ?></strong> #<?php echo esc_html( $order->get_order_number() ); ?></p>
					<p><strong><?php esc_html_e( 'Ngày đặt hàng:', 'vuzix-practice' ); ?></strong> <?php echo esc_html( $order_date ); ?></p>
					<p><strong><?php esc_html_e( 'Phương thức thanh toán:', 'vuzix-practice' ); ?></strong> <?php echo esc_html( $payment_method ); ?></p>
				</div>

				<!-- Customer Details -->
				<div class="address-box">
					<h3><?php esc_html_e( 'Thông tin khách hàng', 'vuzix-practice' ); ?></h3>
					<p><strong><?php esc_html_e( 'Họ và tên:', 'vuzix-practice' ); ?></strong> <?php echo esc_html( $customer_name !== '' ? $customer_name : $none ); ?></p>
					<p><strong><?php esc_html_e( 'Địa chỉ:', 'vuzix-practice' ); ?></strong> <?php echo esc_html( $address_1 !== '' ? $address_1 : $none ); ?></p>
					<p><strong><?php esc_html_e( 'Số điện thoại:', 'vuzix-practice' ); ?></strong> <?php echo esc_html( $phone !== '' ? $phone : $none ); ?></p>
					<p><strong><?php esc_html_e( 'Email:', 'vuzix-practice' ); ?></strong> <?php echo esc_html( $email_address !== '' ? $email_address : $none ); ?></p>
				</div>

				<!-- Product Table -->
				<table class="order-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sản phẩm', 'vuzix-practice' ); ?></th>
							<th style="text-align: center;"><?php esc_html_e( 'SL', 'vuzix-practice' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Giá', 'vuzix-practice' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php echo vuzix_email_order_item_rows( $order ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<tr class="summary-row" style="border-top: 1px solid #e2e8f0;">
							<td colspan="2" style="text-align: right; color: #64748b;"><?php esc_html_e( 'Tạm tính:', 'vuzix-practice' ); ?></td>
							<td class="price"><?php echo wp_kses_post( wc_price( $order->get_subtotal(), array( 'currency' => $order->get_currency() ) ) ); ?></td>
						</tr>
						<tr class="summary-row total">
							<td colspan="2" style="text-align: right;"><?php esc_html_e( 'Tổng cộng:', 'vuzix-practice' ); ?></td>
							<td class="price" style="color: #0284c7;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="action-button">
					<a href="<?php echo esc_url( $edit_order_url ); ?>"><?php esc_html_e( 'Xem đơn hàng trong quản trị', 'vuzix-practice' ); ?></a>
				</div>
			</div>

			<!-- Footer -->
			<div class="footer">
				<p><?php esc_html_e( 'Đây là email tự động từ hệ thống website Vuzix.', 'vuzix-practice' ); ?></p>
				<p style="margin-top: 8px;">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Vuzix Vietnam. <?php esc_html_e( 'All rights reserved.', 'vuzix-practice' ); ?></p>
			</div>
		</div>
	</div>
</body>
</html>
