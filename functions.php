<?php
/**
 * Vuzix theme bootstrap and shared helpers.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function vuzix_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
	register_nav_menus( array(
		'primary' => __( 'Menu chính', 'vuzix-practice' ),
		'footer'  => __( 'Menu chân trang', 'vuzix-practice' ),
	) );
}
add_action( 'after_setup_theme', 'vuzix_theme_setup' );

/**
 * Danh sách các thư mục chứa file HTML mẫu để tìm kiếm (dùng chung cho page.php
 * và cơ chế fallback 404 bên dưới).
 */
function vuzix_get_search_dirs() {
	return array(
		'original-pages/',
		'original-products/',
		'original-policies/',
		'original-blogs/',
		'original-blogs/release-notes/',
		'original-blogs/vuzix-white-papers-and-case-studies-across-industries/',
		'original-blogs/white-papers/',
		'original-tools/',
		'original-tools/perfect-product-finder/',
	);
}

/**
 * Tìm file HTML mẫu khớp với 1 slug trong các thư mục original-*.
 * Thử khớp trực tiếp trước ("$slug.html"); nếu không thấy, thử khớp lại bằng
 * sanitize_title() để chịu được các file có tên bị lỗi encode (vd chứa %XX
 * do quá trình export/scan trước đó để lại) mà slug thật của WP không có.
 */
function vuzix_find_original_file( $slug ) {
	if ( empty( $slug ) ) {
		return '';
	}

	foreach ( vuzix_get_search_dirs() as $dir ) {
		$candidate = $dir . $slug . '.html';
		if ( file_exists( get_theme_file_path( $candidate ) ) ) {
			return $candidate;
		}
	}

	foreach ( vuzix_get_search_dirs() as $dir ) {
		$dir_path = get_theme_file_path( $dir );
		if ( ! is_dir( $dir_path ) ) {
			continue;
		}
		foreach ( glob( $dir_path . '*.html' ) as $file ) {
			if ( sanitize_title( pathinfo( $file, PATHINFO_FILENAME ) ) === $slug ) {
				return $dir . basename( $file );
			}
		}
	}

	return '';
}

/**
 * Nhiều trang tĩnh (sản phẩm, blog, tool, policy...) chưa được tạo thành
 * Page/Post thật trong WordPress nên WordPress trả về 404 trước khi
 * page.php kịp chạy. Hook này bắt các 404 đó, kiểm tra xem slug được yêu
 * cầu có khớp 1 file HTML mẫu không, nếu có thì render nội dung trực tiếp
 * thay vì hiển thị "Không tìm thấy nội dung".
 */
function vuzix_fallback_static_page() {
	if ( ! is_404() ) {
		return;
	}

	global $wp;
	$request = isset( $wp->request ) ? trim( $wp->request, '/' ) : '';
	$slug    = $request !== '' ? basename( $request ) : '';

	// Giả lập Cart API (Shopify AJAX Cart) để phần giỏ hàng tĩnh hoạt động trên WordPress
	if ( $request === 'cart' || $request === 'cart.js' || strpos( $request, 'cart/' ) === 0 ) {
		header( 'Content-Type: application/json' );
		if ( ! session_id() ) {
			session_start();
		}

		// Đảm bảo session có cấu trúc giỏ hàng cơ bản
		if ( ! isset( $_SESSION['vuzix_cart'] ) ) {
			$_SESSION['vuzix_cart'] = array(
				'token'                 => 'mock_cart_token',
				'note'                  => '',
				'attributes'            => (object) array(),
				'original_total_price'  => 0,
				'total_price'           => 0,
				'total_discount'        => 0,
				'total_weight'          => 0,
				'item_count'            => 0,
				'items'                 => array(),
				'requires_shipping'     => false,
				'currency'              => 'USD',
			);
		}

		$action = basename( $request );
		$action = str_replace( '.js', '', $action );

		if ( $action === 'add' ) {
			// Thêm sản phẩm
			$raw_body = file_get_contents( 'php://input' );
			$data     = json_decode( $raw_body, true );

			$id  = isset( $data['id'] ) ? $data['id'] : ( isset( $_REQUEST['id'] ) ? $_REQUEST['id'] : '' );
			$qty = isset( $data['quantity'] ) ? intval( $data['quantity'] ) : ( isset( $_REQUEST['quantity'] ) ? intval( $_REQUEST['quantity'] ) : 1 );

			// Tìm thông tin sản phẩm từ tiêu đề file
			$title = 'Vuzix Product (Demo)';
			$price = 29900; // $299.00 mặc định
			$sku = '';
			$filename = '';

			// Thử tìm file sản phẩm để lấy tên cho đẹp
			foreach ( glob( get_theme_file_path( 'original-products/*.html' ) ) as $file ) {
				$html_content = file_get_contents( $file );
				if ( strpos( $html_content, 'value="' . $id . '"' ) !== false ) {
					$filename = pathinfo( $file, PATHINFO_FILENAME );
					$title    = ucwords( str_replace( '-', ' ', $filename ) );

					// Trích xuất SKU từ file
					if ( preg_match( '/"sku"\s*:\s*"(.*?)"/i', $html_content, $sku_matches ) ) {
						$sku = $sku_matches[1];
					}
					break;
				}
			}

			// NẾU WOOCOMMERCE ĐANG HOẠT ĐỘNG, THÊM SẢN PHẨM VÀO GIỎ HÀNG WOOCOMMERCE THẬT
			if ( function_exists( 'WC' ) && WC()->cart ) {
				$product_id = 0;
				if ( ! empty( $sku ) ) {
					$product_id = wc_get_product_id_by_sku( $sku );
				}
				if ( ! $product_id && ! empty( $filename ) ) {
					$post = get_page_by_path( $filename, OBJECT, 'product' );
					if ( $post ) {
						$product_id = $post->ID;
					}
				}

				if ( $product_id ) {
					WC()->cart->add_to_cart( $product_id, $qty );
					$product = wc_get_product( $product_id );
					
					$title = $product->get_name();
					$price = floatval( $product->get_price() ) * 100;
				}
			}

			$item = array(
				'id'           => $id,
				'title'        => $title,
				'price'        => $price,
				'line_price'   => $price * $qty,
				'quantity'     => $qty,
				'image'        => get_template_directory_uri() . '/cdn/shop/files/favicon.png',
				'url'          => '#',
				'variant_id'   => $id,
				'handle'       => 'mock-product',
			);

			$found = false;
			foreach ( $_SESSION['vuzix_cart']['items'] as &$cart_item ) {
				if ( $cart_item['id'] == $id ) {
					$cart_item['quantity'] += $qty;
					$cart_item['line_price'] = $cart_item['price'] * $cart_item['quantity'];
					$found                   = true;
					$item                    = $cart_item;
					break;
				}
			}
			if ( ! $found ) {
				$_SESSION['vuzix_cart']['items'][] = $item;
			}
		} elseif ( $action === 'clear' ) {
			// Xóa giỏ hàng WooCommerce nếu có
			if ( function_exists( 'WC' ) && WC()->cart ) {
				WC()->cart->empty_cart();
			}
			$_SESSION['vuzix_cart']['items']                = array();
			$_SESSION['vuzix_cart']['total_price']          = 0;
			$_SESSION['vuzix_cart']['original_total_price'] = 0;
			$_SESSION['vuzix_cart']['item_count']           = 0;
		}

		// NẾU WOOCOMMERCE ĐANG HOẠT ĐỘNG, TỰ ĐỘNG ĐỒNG BỘ GIỎ HÀNG SANG FRONTEND JSON
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$items = array();
			$count = 0;
			$total = 0;

			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$product_id = $cart_item['product_id'];
				$product    = wc_get_product( $product_id );
				$qty        = $cart_item['quantity'];
				$item_price = floatval( $product->get_price() ) * 100;
				$line_price = $item_price * $qty;

				$items[] = array(
					'id'         => $product_id,
					'title'      => $product->get_name(),
					'price'      => $item_price,
					'line_price' => $line_price,
					'quantity'   => $qty,
					'image'      => wp_get_attachment_url( $product->get_image_id() ) ?: get_template_directory_uri() . '/cdn/shop/files/favicon.png',
					'url'        => get_permalink( $product_id ),
					'variant_id' => $product_id,
					'handle'     => $product->get_slug(),
				);

				$count += $qty;
				$total += $line_price;
			}

			$_SESSION['vuzix_cart'] = array(
				'token'                 => 'mock_cart_token',
				'note'                  => '',
				'attributes'            => (object) array(),
				'original_total_price'  => $total,
				'total_price'           => $total,
				'total_discount'        => 0,
				'total_weight'          => 0,
				'item_count'            => $count,
				'items'                 => $items,
				'requires_shipping'     => false,
				'currency'              => 'USD',
			);
		}

		if ( $action === 'add' ) {
			echo json_encode( isset( $item ) ? $item : $_SESSION['vuzix_cart'] );
		} else {
			echo json_encode( $_SESSION['vuzix_cart'] );
		}
		exit;
	}

	$original_file = vuzix_find_original_file( $slug );
	if ( empty( $original_file ) ) {
		return;
	}

	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );

	get_header();
	echo "\n<!-- === VUZIX STATIC FALLBACK (chưa có Page thật trong WP) === -->\n";
	echo "<!-- Slug: " . esc_html( $slug ) . " -->\n";
	echo "<!-- Matched Original File: " . esc_html( $original_file ) . " -->\n";
	echo vuzix_get_main_content( $original_file );
	get_footer();
	exit;
}
add_action( 'template_redirect', 'vuzix_fallback_static_page' );

/**
 * Thay thế đường dẫn tương đối (cdn/, apps/) thành đường dẫn tuyệt đối của theme.
 */
function vuzix_replace_asset_paths( $content, $theme_uri ) {
	// Chuẩn hóa các đường dẫn tương đối dạng ../cdn/ hoặc ../../cdn/ thành cdn/
	$content = preg_replace( '/(\.\.\/)+cdn\//', 'cdn/', $content );
	$content = preg_replace( '/(\.\.\/)+apps\//', 'apps/', $content );

	$content = str_replace( '="cdn/', '="' . $theme_uri . '/cdn/', $content );
	$content = str_replace( "='cdn/", "='" . $theme_uri . '/cdn/', $content );
	$content = str_replace( 'srcset="cdn/', 'srcset="' . $theme_uri . '/cdn/', $content );
	$content = str_replace( ', cdn/', ', ' . $theme_uri . '/cdn/', $content );
	$content = str_replace( 'url(cdn/', 'url(' . $theme_uri . '/cdn/', $content );
	$content = str_replace( 'url(\'cdn/', 'url(\'' . $theme_uri . '/cdn/', $content );
	$content = str_replace( 'url("cdn/', 'url("' . $theme_uri . '/cdn/', $content );

	$content = str_replace( '="apps/', '="' . $theme_uri . '/apps/', $content );
	$content = str_replace( "='apps/", "='" . $theme_uri . '/apps/', $content );
	$content = str_replace( 'srcset="apps/', 'srcset="' . $theme_uri . '/apps/', $content );
	$content = str_replace( ', apps/', ', ' . $theme_uri . '/apps/', $content );

	// Khắc phục lỗi unicode escape (\u0026 -> &)
	$content = str_replace( '\\u0026', '&', $content );

	return $content;
}

/**
 * Chuyển các liên kết tĩnh sang liên kết động WordPress.
 */
function vuzix_convert_internal_links( $html ) {
	$pattern = '/(href|data-url|data-href)=(["\'])([^"\']+)\2/i';

	return preg_replace_callback( $pattern, function( $matches ) {
		$attr = $matches[1];
		$quote = $matches[2];
		$url  = $matches[3];

		$is_external = false;
		if ( preg_match( '/^(https?:)?\/\//i', $url ) ) {
			if ( ! preg_match( '/vuzix\.com/i', $url ) ) {
				$is_external = true;
			}
		}

		if ( $is_external ||
			strpos( $url, '#' ) === 0 ||
			strpos( $url, 'mailto:' ) === 0 ||
			strpos( $url, 'tel:' ) === 0 ||
			strpos( $url, 'javascript:' ) === 0 ||
			preg_match( '/\.(jpg|jpeg|png|gif|svg|webp|css|js|mp4|webm|ogv|pdf|zip|json|atom)(\?.*)?$/i', $url ) ) {
			return $matches[0];
		}

		$url_parts = explode( '?', $url );
		$clean_url = $url_parts[0];
		$url_parts_hash = explode( '#', $clean_url );
		$clean_url = $url_parts_hash[0];

		$clean_url = str_replace( array( 'https://www.vuzix.com', 'http://www.vuzix.com', 'https://vuzix.com', 'http://vuzix.com' ), '', $clean_url );
		$clean_url = ltrim( $clean_url, '/' );
		$clean_url = preg_replace( '/^\.\.+\//', '', $clean_url );

		$path_info = pathinfo( $clean_url );
		$slug = isset( $path_info['filename'] ) ? $path_info['filename'] : '';

		if ( empty( $slug ) || $slug === 'index' || $slug === 'original-index' ) {
			$new_url = home_url( '/' );
		} else {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				$new_url = get_permalink( $page );
			} else {
				$new_url = home_url( '/' . $slug . '/' );
			}
		}

		if ( isset( $url_parts[1] ) ) {
			$new_url .= '?' . $url_parts[1];
		}
		if ( isset( $url_parts_hash[1] ) ) {
			$new_url .= '#' . $url_parts_hash[1];
		}

		return $attr . '=' . $quote . esc_url( $new_url ) . $quote;
	}, $html );
}

/**
 * Trích xuất phần thân <main> của tệp HTML tĩnh.
 */
function vuzix_get_main_content( $original_filename ) {
	$theme_uri  = get_template_directory_uri();
	$index_path = get_theme_file_path( $original_filename );

	if ( ! file_exists( $index_path ) ) {
		return '';
	}

	$html = file_get_contents( $index_path );
	$parts = explode( '<main id="MainContent"', $html );
	if ( count( $parts ) < 2 ) {
		return '';
	}
	$sub_parts = explode( '</main>', $parts[1] );
	$main_content = $sub_parts[0];
	$main_content = substr( $main_content, strpos( $main_content, '>' ) + 1 );

	$main_content = vuzix_convert_internal_links( $main_content );
	$main_content = vuzix_replace_asset_paths( $main_content, $theme_uri );

	return $main_content;
}

/**
 * Khai báo hỗ trợ WooCommerce cho theme
 */
function vuzix_woocommerce_support() {
	add_theme_support( 'woocommerce' );
	
	// Hỗ trợ zoom ảnh sản phẩm, lightbox và slideshow trong trang chi tiết
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'vuzix_woocommerce_support' );

