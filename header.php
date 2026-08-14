<?php
/**
 * Header: Trích xuất từ original-index.html tới trước thẻ <main>.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$theme_uri  = get_template_directory_uri();
$index_path = get_theme_file_path( 'original-index.html' );

if ( file_exists( $index_path ) ) {
	$header_html = vuzix_cached_html_pipeline(
		'header',
		array( $index_path, get_theme_file_path( 'functions.php' ) ),
		function () use ( $index_path, $theme_uri ) {
			$html = file_get_contents( $index_path );
			$parts = explode( '<main id="MainContent"', $html );
			$header_html = vuzix_strip_shopify_runtime_assets( $parts[0] );

			$header_html = str_replace( '\\u0026', '&', $header_html );
			$header_html = vuzix_convert_internal_links( $header_html );
			$header_html = vuzix_replace_asset_paths( $header_html, $theme_uri );
			$header_html = vuzix_translate_static_ui( $header_html );

			/*
			 * Store UI is currently disabled. Keep the original account/cart markup in
			 * the rendered source as HTML comments so it can be restored by removing
			 * this block later.
			 */
			$header_html = preg_replace_callback(
				'#<a\b[^>]*class=["\'][^"\']*header__icon--account[^"\']*["\'][^>]*>.*?</a>#si',
				function ( $matches ) {
					return "<!-- VUZIX: account/login icon disabled\n" . $matches[0] . "\n-->";
				},
				$header_html
			);
			$header_html = preg_replace_callback(
				'#<a\b[^>]*class=["\'][^"\']*header__icon--cart[^"\']*["\'][^>]*>.*?</a>#si',
				function ( $matches ) {
					return "<!-- VUZIX: cart icon disabled\n" . $matches[0] . "\n-->";
				},
				$header_html
			);

			return $header_html;
		}
	);

	$head_parts = explode( '</head>', $header_html );
	if ( count( $head_parts ) > 1 ) {
		echo $head_parts[0];
		wp_head();
		echo '</head>';

		$body_parts = explode( '<body class="gradient">', $head_parts[1] );
		if ( count( $body_parts ) > 1 ) {
			echo $body_parts[0];
			?>
			<body <?php body_class( 'gradient' ); ?>>
			<?php
			wp_body_open();
			echo $body_parts[1];
		} else {
			echo $head_parts[1];
		}
	} else {
		echo $header_html;
	}
} else {
	?>
	<!doctype html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<?php
}
