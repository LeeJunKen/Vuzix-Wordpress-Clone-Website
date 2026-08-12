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
		'original-collections/',
		'original-policies/',
		'original-blogs/',
		'original-blogs/case-studies/',
		'original-blogs/release-notes/',
		'original-blogs/vuzix-blog/',
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

	// Các trang phân trang blog export từ Shopify được lưu dưới dạng
	// "blog-name%3Fpage=N.html". Ưu tiên đúng file tương ứng query page.
	$page_number = isset( $_GET['page'] ) ? absint( wp_unslash( $_GET['page'] ) ) : 0;
	if ( $page_number > 0 ) {
		foreach ( vuzix_get_search_dirs() as $dir ) {
			$paginated_candidate = $dir . $slug . '%3Fpage=' . $page_number . '.html';
			if ( file_exists( get_theme_file_path( $paginated_candidate ) ) ) {
				return $paginated_candidate;
			}
		}
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
 * Một số tên file Shopify không trùng slug WooCommerce được sinh từ tên sản
 * phẩm lúc import. Dùng alias để các sản phẩm đó vẫn mở đúng trang chi tiết
 * HTML gốc thay vì rơi vào layout fallback.
 */
function vuzix_find_original_product_file( $product ) {
	if ( ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}

	$slug = $product->get_slug();
	$file = vuzix_find_original_file( $slug );
	if ( $file !== '' ) {
		return $file;
	}

	$aliases = array(
		'4800-mah-extended-use-power-bank'                  => '4800mah-extended-use-power-bank',
		'ball-cap-human-2-0'                                => 'baseball-hat-human-2-0',
		'lx1-bump-cap'                                       => 'bump-cap',
		'm-series-hat-mount'                                 => 'hat-mount',
		'lx1-charger-kit'                                    => 'lx1-4-bank-charger-cradle',
		'lx1-long-shift-power-bank'                          => 'lx1-7ah-battery-pack',
		'm-series-ear-hooks-for-lens-less-frames'            => 'm-series-ear-hooks',
		'm-series-comfort-headband'                          => 'm-series-elastic-headband-accessory-standard',
		'm-series-lensless-frames'                           => 'm-series-lens-less-frames',
		'm-series-safety-frame-mounting-clips-l-r'           => 'm-series-safety-frame-mounting-clips',
		'm-series-safety-helmet-mounts-l-r'                  => 'm-series-safety-helmet-mounts',
		'vuzix-m400-smart-glasses'                           => 'm400-smart-glasses',
		'vuzix-m400-usb-a-to-usb-c-charging-cable-1-meter'   => 'm400-usb-a-to-usb-ccharging-cable-1-meter',
		'vuzix-m400-usb-c-to-usb-c-viewer-cable-16'          => 'm400-usb-c-to-usb-c-viewer-cable-16-inch',
		'men-s-polo'                                         => 'mens-premium-polo-1',
		'remote-assist-kit-m400'                             => 'remote-assist-kit',
		'snapback-hat-augmented-human'                       => 'snapback-hat-3',
		'snapback-hat-human-2-0'                             => 'snapback-hat-4',
		'unisex-tee'                                         => 'unisex-t-shirt-1',
		'warranty-product'                                   => 'warranty-product-1',
		'vuzix-z100-smart-glasses-prescription-inserts'      => 'z100-smart-glasses-prescription-inserts',
		'vuzix-z100-usb-a-to-magnetic-charging-cable-3'      => 'z100-usb-a-to-magnetic-charging-cable-3',
	);

	if ( ! isset( $aliases[ $slug ] ) ) {
		return '';
	}

	$candidate = 'original-products/' . $aliases[ $slug ] . '.html';
	return file_exists( get_theme_file_path( $candidate ) ) ? $candidate : '';
}

/**
 * Gallery/product lightbox dùng ảnh media của WooCommerce, không dùng URL ảnh
 * Shopify còn nằm trong file HTML export.
 */
function vuzix_get_wc_product_gallery_markup( $product ) {
	if ( ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}

	$image_ids = array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) );
	$image_ids = array_values( array_unique( array_map( 'absint', $image_ids ) ) );
	$gallery_id = 'vuzix-wc-gallery-' . absint( $product->get_id() );

	if ( empty( $image_ids ) ) {
		return '<div class="vuzix-wc-product-gallery vuzix-wc-product-gallery--empty"><img src="' . esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ) . '" alt="' . esc_attr( $product->get_name() ) . '"></div>';
	}

	$first_id  = $image_ids[0];
	$first_src = wp_get_attachment_image_url( $first_id, 'woocommerce_single' );
	$first_alt = get_post_meta( $first_id, '_wp_attachment_image_alt', true );
	$first_alt = $first_alt ? $first_alt : $product->get_name();

	ob_start();
	?>
	<div id="<?php echo esc_attr( $gallery_id ); ?>" class="vuzix-wc-product-gallery">
		<button class="vuzix-wc-product-gallery__main" type="button" aria-label="<?php esc_attr_e( 'Open product image', 'vuzix-practice' ); ?>">
			<img src="<?php echo esc_url( $first_src ); ?>" alt="<?php echo esc_attr( $first_alt ); ?>">
		</button>
		<?php if ( count( $image_ids ) > 1 ) : ?>
			<div class="vuzix-wc-product-gallery__thumbs" aria-label="<?php esc_attr_e( 'Product images', 'vuzix-practice' ); ?>">
				<?php foreach ( $image_ids as $index => $image_id ) :
					$thumb = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
					$full  = wp_get_attachment_image_url( $image_id, 'full' );
					$large = wp_get_attachment_image_url( $image_id, 'woocommerce_single' );
					$alt   = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
					$alt   = $alt ? $alt : $product->get_name();
					?>
					<button class="vuzix-wc-product-gallery__thumb<?php echo $index === 0 ? ' is-active' : ''; ?>" type="button" data-large="<?php echo esc_url( $large ); ?>" data-full="<?php echo esc_url( $full ); ?>" data-alt="<?php echo esc_attr( $alt ); ?>">
						<img src="<?php echo esc_url( $thumb ); ?>" alt="">
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="vuzix-wc-product-gallery__modal" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Product image', 'vuzix-practice' ); ?>">
			<button class="vuzix-wc-product-gallery__close" type="button" aria-label="<?php esc_attr_e( 'Close', 'vuzix-practice' ); ?>">&times;</button>
			<img src="<?php echo esc_url( wp_get_attachment_image_url( $first_id, 'full' ) ); ?>" alt="<?php echo esc_attr( $first_alt ); ?>">
		</div>
	</div>
	<style>
		.vuzix-wc-product-gallery__main { display:block; width:100%; padding:0; border:0; background:#f7f7f7; cursor:zoom-in; }
		.vuzix-wc-product-gallery__main img { display:block; width:100%; height:auto; max-height:760px; object-fit:contain; }
		.vuzix-wc-product-gallery__thumbs { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
		.vuzix-wc-product-gallery__thumb { width:74px; height:74px; padding:0; border:1px solid transparent; background:#f7f7f7; cursor:pointer; }
		.vuzix-wc-product-gallery__thumb.is-active { border-color:#111; }
		.vuzix-wc-product-gallery__thumb img { display:block; width:100%; height:100%; object-fit:cover; }
		.vuzix-wc-product-gallery__modal { position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center; padding:42px; background:rgba(255,255,255,.96); }
		.vuzix-wc-product-gallery__modal[hidden] { display:none; }
		.vuzix-wc-product-gallery__modal img { max-width:100%; max-height:100%; object-fit:contain; }
		.vuzix-wc-product-gallery__close { position:absolute; top:20px; right:24px; width:46px; height:46px; border:1px solid #ddd; border-radius:50%; background:#fff; color:#111; font-size:34px; line-height:1; cursor:pointer; }
	</style>
	<script>
	(function () {
		var gallery = document.getElementById(<?php echo wp_json_encode( $gallery_id ); ?>);
		if (!gallery) return;
		var main = gallery.querySelector('.vuzix-wc-product-gallery__main img');
		var modal = gallery.querySelector('.vuzix-wc-product-gallery__modal');
		var modalImage = modal.querySelector('img');
		gallery.querySelectorAll('.vuzix-wc-product-gallery__thumb').forEach(function (button) {
			button.addEventListener('click', function () {
				main.src = button.dataset.large; main.alt = button.dataset.alt;
				modalImage.src = button.dataset.full; modalImage.alt = button.dataset.alt;
				gallery.querySelectorAll('.vuzix-wc-product-gallery__thumb').forEach(function (item) { item.classList.remove('is-active'); });
				button.classList.add('is-active');
			});
		});
		gallery.querySelector('.vuzix-wc-product-gallery__main').addEventListener('click', function () { modal.hidden = false; });
		gallery.querySelector('.vuzix-wc-product-gallery__close').addEventListener('click', function () { modal.hidden = true; });
		modal.addEventListener('click', function (event) { if (event.target === modal) modal.hidden = true; });
	}());
	</script>
	<?php
	return ob_get_clean();
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

	// Shopify export stores query-like parts in literal asset filenames.
	$content = preg_replace_callback(
		'#(?:' . preg_quote( $theme_uri, '#' ) . '/)?(?:cdn|apps)/[^\s"\'<>]*%253F[^\s"\'<>]*#',
		function ( $matches ) {
			return str_replace( array( '=', '&' ), array( '%3D', '%26' ), $matches[0] );
		},
		$content
	);

	// Khắc phục lỗi unicode escape (\u0026 -> &)
	$content = str_replace( '\\u0026', '&', $content );

	return $content;
}

/**
 * Translate recurring text labels inherited from the exported Shopify HTML.
 * Only text nodes are replaced; URLs, handles, classes and script data remain intact.
 */
function vuzix_translate_static_ui( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}

	$labels = array(
		// Uppercase headers/links
		'FIND YOUR MATCH' => 'TÌM SẢN PHẨM PHÙ HỢP',
		'WAVEGUIDES'      => 'ỐNG DẪN SÓNG',
		'OEM SERVICES'    => 'DỊCH VỤ OEM',
		'SMART GLASSES'   => 'KÍNH THÔNG MINH',
		'RESOURCES'       => 'TÀI NGUYÊN',
		'COMPANY'         => 'CÔNG TY',
		'CONTACT'         => 'LIÊN HỆ',
		'SUPPORT'         => 'HỖ TRỢ',
		'PRODUCTS'        => 'SẢN PHẨM',
		'STAY IN THE LOOP' => 'ĐĂNG KÝ NHẬN TIN',
		'LEGAL'           => 'PHÁP LÝ',

		// Title case / Mixed case headers/links
		'Smart Glasses'   => 'Kính thông minh',
		'Waveguides'      => 'Ống dẫn sóng',
		'OEM Services'    => 'Dịch vụ OEM',
		'Resources'       => 'Tài nguyên',
		'Company'         => 'Công ty',
		'Contact'         => 'Liên hệ',
		'Support'         => 'Hỗ trợ',
		'Products'        => 'Sản phẩm',
		'Search'          => 'Tìm kiếm',
		'About Us'        => 'Về chúng tôi',
		'News & Events'   => 'Tin tức & Sự kiện',
		'Investors'       => 'Nhà đầu tư',
		'Careers'         => 'Tuyển dụng',
		'Developer Center' => 'Trung tâm Phát triển',
		'FAQ'             => 'Câu hỏi thường gặp',
		'Subscribe'       => 'Đăng ký',
		'Cart'            => 'Giỏ hàng',
		'Close'           => 'Đóng',
		'Overview'        => 'Tổng quan',
		'Waveguide Configurations' => 'Cấu hình ống dẫn sóng',
		'US-Based Manufacturing' => 'Sản xuất tại Mỹ',
		'About Vuzix'     => 'Giới thiệu Vuzix',
		'Partners'        => 'Đối tác',
		'Events'          => 'Sự kiện',
		'Developers'      => 'Nhà phát triển',
		'Contact Vuzix OEM' => 'Liên hệ Vuzix OEM',
		'Contact Sales'   => 'Liên hệ bộ phận bán hàng',
		'Contact Technical Support' => 'Liên hệ hỗ trợ kỹ thuật',
		'Contact Investor Relations' => 'Liên hệ quan hệ nhà đầu tư',
		'Contact Business Development' => 'Liên hệ hợp tác phát triển kinh doanh',
		'Contact Media Relations' => 'Liên hệ quan hệ truyền thông',
		'Contact Vuzix Enterprise Solutions' => 'Liên hệ giải pháp doanh nghiệp Vuzix',
		'Privacy Policy'  => 'Chính sách bảo mật',
		'Privacy policy'  => 'Chính sách bảo mật',
		'Terms & Conditions' => 'Điều khoản & Điều kiện',
		'Terms and Conditions' => 'Điều khoản & Điều kiện',
		'EULA'            => 'Thỏa thuận cấp phép người dùng cuối (EULA)',
		'Open Source License' => 'Giấy phép nguồn mở',
		'Content Policy'  => 'Chính sách nội dung',
		'OEM Solutions'   => 'Giải pháp OEM',
		'OEM Programs'    => 'Chương trình OEM',
		'Custom Solutions' => 'Giải pháp tùy chỉnh',
		'Advanced Manufacturing' => 'Sản xuất tiên tiến',
		'Learn More'      => 'Tìm hiểu thêm',
		'Learn more'      => 'Tìm hiểu thêm',
		'Find Your Match' => 'Tìm sản phẩm phù hợp',
		'Find your match' => 'Tìm sản phẩm phù hợp',
		'Compare Smart Glasses' => 'So sánh kính thông minh',
		'Solution Bundles' => 'Gói giải pháp',
		'Solutions Bundles' => 'Gói giải pháp',
		'Sales'           => 'Bán hàng',
		'Partnerships'    => 'Quan hệ đối tác',
		'Investor Relations' => 'Quan hệ nhà đầu tư',
		'Business Development' => 'Phát triển kinh doanh',
		'Media Relations' => 'Quan hệ truyền thông',
		'Enterprise Solutions' => 'Giải pháp doanh nghiệp',
		'Technical Support' => 'Hỗ trợ kỹ thuật',
		'Media Inquiries' => 'Yêu cầu truyền thông',
		'History'         => 'Lịch sử',
		'Press Releases'  => 'Thông cáo báo chí',
		'Use Cases'       => 'Tình huống sử dụng',
		'Remote Mentor'   => 'Cố vấn từ xa',
		'Mobile Device Management' => 'Quản lý thiết bị di động',
		'Use Case Overview' => 'Tổng quan tình huống sử dụng',
		'Prescription Lenses' => 'Thấu kính theo toa',
		'Remote Assist Kit' => 'Bộ hỗ trợ từ xa',
		'Pick and Pack Kit' => 'Bộ Pick and Pack',
		'Pick & Pack Kit' => 'Bộ Pick & Pack',
		'Engineering Services' => 'Dịch vụ kỹ thuật',
		'Vuzix Ultralite OEM Platform' => 'Nền tảng OEM Vuzix Ultralite',
		'OEM Readiness'   => 'Độ sẵn sàng OEM',
		'Press Release'   => 'Thông cáo báo chí',
		'Recent News'     => 'Tin tức gần đây',
		'Patents & patents pending' => 'Bằng sáng chế & Bằng sáng chế đang chờ xử lý',
		'Years of industry expertise' => 'Năm kinh nghiệm trong ngành',
		'To commercialize waveguides' => 'Thương mại hóa ống dẫn sóng',
		'U.S. based facilities' => 'Cơ sở sản xuất tại Mỹ',
		'What Vuzix Does' => 'Vuzix Làm Gì',
		'With 30 years in wearable technology, Vuzix develops waveguide optics for device makers and builds smart glasses for enterprise use.' => 'Với 30 năm kinh nghiệm trong công nghệ thiết bị đeo, Vuzix phát triển thấu kính ống dẫn sóng cho các nhà sản xuất thiết bị và xây dựng kính thông minh cho mục đích doanh nghiệp.',
		'OEM & Waveguide Technology' => 'Công nghệ OEM & Ống dẫn sóng',
		'Optical platforms for device makers' => 'Nền tảng quang học cho các nhà sản xuất thiết bị',
		'Vuzix designs and manufactures waveguide optics, display systems, and OEM platforms for companies building or white-labeling smart glasses.' => 'Vuzix thiết kế và sản xuất thấu kính ống dẫn sóng, hệ thống hiển thị và nền tảng OEM cho các công ty tự xây dựng hoặc gắn nhãn thương hiệu kính thông minh.',
		'Waveguide optics' => 'Thấu kính ống dẫn sóng',
		'OEM platforms & reference designs' => 'Nền tảng OEM & Thiết kế tham chiếu',
		'Engineering services' => 'Dịch vụ kỹ thuật',
		'Defense programs' => 'Chương trình quốc phòng',
		'Enterprise Smart Glasses' => 'Kính thông minh doanh nghiệp',
		'Smart glasses for frontline operations' => 'Kính thông minh cho hoạt động thực địa',
		'Vuzix designs and manufactures enterprise smart glasses, delivering hardware, software, and support for fast, scalable deployment.' => 'Vuzix thiết kế và sản xuất kính thông minh doanh nghiệp, cung cấp phần cứng, phần mềm và hỗ trợ triển khai nhanh chóng, dễ dàng mở rộng.',
		'Smart glasses'   => 'Kính thông minh',
		'Pick & Pack Validation Program' => 'Chương trình xác thực Pick & Pack',
		'Use cases by industry' => 'Tình huống sử dụng theo ngành nghề',
		'Vuzix Shield®'   => 'Vuzix Shield®',
		'Revolutionary binocular smart glasses built for the enterprise. Optical waveguides and miniature microLED projectors.' => 'Kính thông minh hai mắt mang tính cách mạng được chế tạo cho doanh nghiệp. Ống dẫn sóng quang học và máy chiếu microLED siêu nhỏ.',
		'Vuzix M400™'     => 'Vuzix M400™',
		'The enterprise workhorse. High-performance, lightweight smart glasses built for all-day frontline operations.' => 'Trụ cột hiệu năng doanh nghiệp. Kính thông minh hiệu năng cao, trọng lượng nhẹ được chế tạo cho các hoạt động thực địa cả ngày.',
		'Vuzix M400 Solutions Kit' => 'Bộ giải pháp Vuzix M400',
		'Everything you need to deploy. Solution kits include M400 smart glasses, custom accessories, and software validation.' => 'Mọi thứ bạn cần để triển khai. Các bộ giải pháp bao gồm kính thông minh M400, phụ kiện tùy chỉnh và chương trình xác thực phần mềm.',
		'Vuzix M400C™'    => 'Vuzix M400C™',
		'Type-C connected enterprise smart glasses. High-resolution display and camera, lightweight all-day comfort.' => 'Kính thông minh doanh nghiệp kết nối Type-C. Màn hình và camera độ phân giải cao, thoải mái nhẹ nhàng suốt cả ngày.',
		'Shop now'        => 'Mua ngay',
		'Vuzix Announces Preliminary Second Quarter 2026 Revenues' => 'Vuzix công bố doanh thu sơ bộ quý II năm 2026',
		'Vuzix Receives Additional Optics Development Milestone Payment from a Major Defense Contractor' => 'Vuzix nhận thêm khoản thanh toán cột mốc phát triển quang học từ một nhà thầu quốc phòng lớn',
		'Vuzix Smart Glasses Integration with TeamViewer Frontline Boosts Operations for Major UK Logistics Provider' => 'Tích hợp kính thông minh Vuzix với TeamViewer Frontline thúc đẩy hoạt động cho nhà cung cấp dịch vụ logistics lớn của Anh',
		'August 06, 2026' => 'Ngày 06 tháng 8 năm 2026',
		'July 22, 2026'   => 'Ngày 22 tháng 7 năm 2026',
		'July 15, 2026'   => 'Ngày 15 tháng 7 năm 2026',
		'Waveguide optics and display engines' => 'Ống dẫn sóng quang học và động cơ hiển thị',
		'Standard & custom optical waveguides. Projectors and display systems. High-volume manufacturing capability.' => 'Ống dẫn sóng quang học tiêu chuẩn & tùy chỉnh. Máy chiếu và hệ thống hiển thị. Khả năng sản xuất quy mô lớn.',
		'Custom waveguides and smart glasses solutions. High-volume manufacturing capability. Engineering design services.' => 'Giải pháp kính thông minh và ống dẫn sóng tùy chỉnh. Khả năng sản xuất quy mô lớn. Dịch vụ thiết kế kỹ thuật.',
		'Custom optical waveguide and smart glasses design. Integration services for display engine, electronics, and software.' => 'Thiết kế kính thông minh và ống dẫn sóng quang học tùy chỉnh. Dịch vụ tích hợp cho động cơ hiển thị, điện tử và phần mềm.',
		'A turnkey reference design for smart glasses. Accelerate your product development and time-to-market.' => 'Thiết kế tham chiếu trọn gói cho kính thông minh. Đẩy nhanh quá trình phát triển sản phẩm và thời gian đưa ra thị trường.',
		'A structured program to assess your product requirements. Determine feasibility, timeline, and budget.' => 'Một chương trình có cấu trúc để đánh giá các yêu cầu sản phẩm của bạn. Xác định tính khả thi, tiến độ và ngân sách.',

		// Mega menu descriptions & items
		'Revolutionary Binocular Smart Glasses' => 'Kính thông minh hai mắt mang tính cách mạng',
		'The Workhorse of Smart Glasses' => 'Trụ cột hiệu năng kính thông minh',
		'Type-C Connected Enterprise Smart Glasses' => 'Kính thông minh doanh nghiệp kết nối Type-C',
		'M400 Power Bank' => 'Sạc dự phòng M400',
		'Keep Your Smart Glasses Charged' => 'Giữ kính thông minh của bạn luôn đầy pin',
		'All Smart Glasses' => 'Tất cả kính thông minh',
		'Accessories'     => 'Phụ kiện',
		'Merchandise'     => 'Hàng lưu niệm',
		'About OEM Services' => 'Giới thiệu dịch vụ OEM',
		'Custom Waveguide Solutions' => 'Giải pháp ống dẫn sóng tùy chỉnh',
		'Waveguide Technology' => 'Công nghệ ống dẫn sóng',
		'Optics of the Future' => 'Quang học của tương lai',
		'Vuzix Custom Solutions' => 'Giải pháp tùy chỉnh Vuzix',
		'Design & Integration Services' => 'Dịch vụ thiết kế & tích hợp',
		'High-Volume Production' => 'Sản xuất quy mô lớn',
		'Vuzix Blog'      => 'Blog Vuzix',
		'Latest News & Insights' => 'Tin tức & thông tin chi tiết mới nhất',
		'White Papers'    => 'Sách trắng',
		'In-Depth Technical Insights' => 'Thông tin kỹ thuật chuyên sâu',
		'Case Studies'    => 'Nghiên cứu điển hình',
		'Real-World Applications' => 'Ứng dụng trong thực tế',
		'App Store'       => 'Cửa hàng ứng dụng',
		'Browse Apps for Vuzix Glasses' => 'Duyệt ứng dụng cho kính Vuzix',
		'Build for Vuzix Smart Glasses' => 'Xây dựng ứng dụng cho kính thông minh Vuzix',
		'Frequently Asked Questions' => 'Các câu hỏi thường gặp',
		'Our Mission & Leadership' => 'Sứ mệnh & Đội ngũ lãnh đạo',
		'Press Releases & Media' => 'Thông cáo báo chí & Truyền thông',
		'Financials & Stock Info' => 'Thông tin tài chính & Cổ phiếu',
		'Join the Vuzix Team' => 'Gia nhập đội ngũ Vuzix',
		'Get in Touch with Us' => 'Liên hệ với chúng tôi',
		'Product Support & Returns' => 'Hỗ trợ sản phẩm & Đổi trả',
		'Sign up for the latest news, product releases, and more...' => 'Đăng ký để nhận tin tức mới nhất, sản phẩm mới và nhiều hơn nữa...',

		// Homepage body content details
		'Waveguide optics and enterprise smart glasses' => 'Thấu kính ống dẫn sóng và kính thông minh doanh nghiệp',
		'Vuzix is a leading manufacturer of Smart Glasses and Waveguide optics for the consumer and enterprise markets. Learn more about how we are shaping the future of optics.' => 'Vuzix là nhà sản xuất hàng đầu về Kính thông minh và công nghệ ống dẫn sóng cho thị trường tiêu dùng và doanh nghiệp. Tìm hiểu thêm về cách chúng tôi định hình tương lai của quang học.',
		'Vuzix technology in real-world applications' => 'Công nghệ Vuzix trong các ứng dụng thực tế',
		'Enterprise-ready devices and solutions. Custom waveguides. High-volume manufacturing. Learn more about OEM Services.' => 'Các thiết bị và giải pháp sẵn sàng cho doanh nghiệp. Ống dẫn sóng tùy chỉnh. Sản xuất quy mô lớn. Tìm hiểu thêm về Dịch vụ OEM.',
		'A developer-ready Android OS. A dedicated developer community. Flexible, feature-rich tools and documentation. Learn more about building for Vuzix Smart Glasses.' => 'Hệ điều hành Android sẵn sàng cho nhà phát triển. Cộng đồng nhà phát triển chuyên dụng. Các công cụ và tài liệu linh hoạt, phong phú tính năng. Tìm hiểu thêm về phát triển ứng dụng cho Kính thông minh Vuzix.',
		'Industry insights' => 'Thông tin chi tiết về ngành',
		'Where do you want to start?' => 'Bạn muốn bắt đầu từ đâu?',
		'Subscribe to our emails' => 'Đăng ký nhận email của chúng tôi',
		'Enterprise-ready devices and solutions' => 'Các thiết bị và giải pháp sẵn sàng cho doanh nghiệp',
		'A portfolio of enterprise AI smart glasses and solutions for warehouse, field service, healthcare, and industrial use cases.' => 'Danh mục các kính thông minh AI và giải pháp cho doanh nghiệp trong nhà kho, dịch vụ thực địa, y tế và các tình huống sử dụng công nghiệp.',
	);

	foreach ( $labels as $english => $vietnamese ) {
		$pattern = '#>(\s*)' . preg_quote( $english, '#' ) . '(\s*)<#';
		$html = preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $vietnamese ) {
				return '>' . $matches[1] . $vietnamese . $matches[2] . '<';
			},
			$html
		);
	}

	return $html;
}

/**
 * Remove Shopify storefront runtime left in exported HTML.
 *
 * The theme only reuses the exported visual markup. Checkout preload, Shop Pay,
 * analytics and Shopify app embeds are not used by WordPress/WooCommerce and
 * otherwise create dozens of failed/external requests on every page.
 */
function vuzix_strip_shopify_runtime_assets( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}

	$blocked_script_sources = array(
		'checkouts/internal/preloads.js',
		'cdn.shopify.com/extensions/',
		'cdn.shopify.com/shopifycloud/',
		'cdn.shopify.com/storefront/',
		'cdn.shopify.com/s/files/',
		'cdn.gtranslate.net/',
		'enormapps.com/',
		'plausible.io/',
		'monorail-edge.shopifysvc.com/',
		'consentmo.com/',
		'gtranslate.io/',
		'na.shgcdn3.com/',
		'ma.zoho.com/',
		'cloudfront.net/apps/',
		'apps-sp.webkul.com/',
		's3.eu-west-1.amazonaws.com/',
		'cdn/shopifycloud/shop-js/',
		'cdn/shopifycloud/storefront/',
		'cdn/shopifycloud/perf-kit/',
	);

	$html = preg_replace_callback(
		'#<script\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>.*?</script\s*>#is',
		function ( $matches ) use ( $blocked_script_sources ) {
			$source = strtolower( html_entity_decode( $matches[2], ENT_QUOTES ) );
			foreach ( $blocked_script_sources as $blocked_source ) {
				if ( strpos( $source, strtolower( $blocked_source ) ) !== false ) {
					return '<!-- VUZIX: removed unused Shopify/external script -->';
				}
			}
			return $matches[0];
		},
		$html
	);

	$blocked_inline_markers = array(
		'Shopify.SignInWithShop',
		'Shopify.featureAssets',
		'ShopifyAnalytics',
		'ShopifyPaypalV4VisibilityTracking',
		'Shopify.PaymentButton',
		'webPixelsManager',
		'__TREKKIE_SHIM_QUEUE',
		'Trekkie.load',
		'monorail-edge.shopifysvc.com',
		'window.Globo.FormBuilder',
		'consentmo_cookie_consent',
		'window.custloIsCustomer',
		'cdSelector',
		'window.ooTaxExemptionConfig',
		'window.shopUrl',
		'updateAndOpenDawnCart',
		'window.ga = function ga',
		'geopro_inline_location_response',
		'vuzix-shop.myshopify.com',
		'shopify-digital-wallet',
		'shop-js-analytics',
		'shopify-features',
		'shopify-origin-trials',
	);

	$html = preg_replace_callback(
		'#<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?</script\s*>#is',
		function ( $matches ) use ( $blocked_inline_markers ) {
			foreach ( $blocked_inline_markers as $marker ) {
				if ( stripos( $matches[0], $marker ) !== false ) {
					return '<!-- VUZIX: removed unused Shopify inline runtime -->';
				}
			}
			return $matches[0];
		},
		$html
	);

	$blocked_link_markers = array(
		'cdn.shopify.com/extensions/',
		'monorail-edge.shopifysvc.com',
		'consentmo.com',
		'workers.consentmo.com',
		'storage.consentmo.com',
	);
	$html = preg_replace_callback(
		'#<link\b[^>]*>#is',
		function ( $matches ) use ( $blocked_link_markers ) {
			foreach ( $blocked_link_markers as $marker ) {
				if ( stripos( $matches[0], $marker ) !== false ) {
					return '<!-- VUZIX: removed unused Shopify/external link -->';
				}
			}
			return $matches[0];
		},
		$html
	);

	// Shopify wallet metadata has no purpose in WooCommerce and points to a
	// Shopify-only endpoint.
	$html = preg_replace( '#<meta\b[^>]*(?:id|name)=["\']shopify-digital-wallet["\'][^>]*>#i', '', $html );

	// Remove Recommenda Quiz Builder app block and its styles
	$html = preg_replace( '#<div[^>]*shopify-block-AY2E2bmk5c2dKUnB5a__5733569959556273306[^>]*>.*?</div>\s*</div>#si', '', $html );
	$html = preg_replace( '#\.recommenda-quiz-link\s*\{\s*display:\s*block\s*!important;\s*\}#i', '', $html );

	return $html;
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

		// Shopify export encode dấu ? trong tên file pagination thành %253F.
		// Chuyển lại thành query string trước khi xác định slug WordPress.
		$url = str_ireplace( array( '%253f', '%3f' ), '?', $url );
		$url = preg_replace( '/([?&]page=\d+)\.html(?=(&|#|$))/i', '$1', $url );

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
			preg_match( '/\.(jpg|jpeg|png|gif|svg|webp|css|js|mp4|webm|ogv|pdf|zip|json|atom|woff|woff2|ttf|otf|eot)(\?.*)?$/i', $url ) ) {
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

		$collection_category_slugs = array(
			'accessories' => 'accessories',
			'merchandise' => 'merchandise',
		);
		$is_collection_category = isset( $collection_category_slugs[ $slug ] ) &&
			( strpos( $clean_url, 'collections/' ) !== false || in_array( $clean_url, array( 'accessories.html', 'merchandise.html' ), true ) );

		if ( strpos( $clean_url, 'collections/all' ) !== false ) {
			$new_url = home_url( '/collections/all/' );
		} elseif ( $is_collection_category && taxonomy_exists( 'product_cat' ) ) {
			$term = get_term_by( 'slug', $collection_category_slugs[ $slug ], 'product_cat' );
			$term_link = $term && ! is_wp_error( $term ) ? get_term_link( $term ) : '';
			$new_url = $term_link && ! is_wp_error( $term_link ) ? $term_link : home_url( '/product-category/' . $collection_category_slugs[ $slug ] . '/' );
		} elseif ( empty( $slug ) || $slug === 'index' || $slug === 'original-index' ) {
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
 * Lấy URL WordPress tương ứng với 1 href gốc trong các file original-*.html
 * (ví dụ "collections/all.html"), dùng đúng logic của vuzix_convert_internal_links()
 * thay vì tự suy ra URL khác trong các template PHP viết tay.
 */
function vuzix_resolve_original_href( $original_href ) {
	$converted = vuzix_convert_internal_links( 'href="' . esc_attr( $original_href ) . '"' );
	if ( preg_match( '/href="([^"]*)"/', $converted, $matches ) ) {
		return html_entity_decode( $matches[1] );
	}
	return home_url( '/' );
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

	// Các file Shopify export thường gắn class header trong suốt ngay cả khi
	// nội dung không có hero. Khi đó menu bị đổi sang màu trắng trên nền sáng.
	// Chỉ giữ hành vi này cho trang thực sự có hero.
	if ( strpos( $main_content, 'vzx-hero' ) === false ) {
		$main_content = preg_replace(
			'#<script\b[^>]*>\s*document\.documentElement\.classList\.add\(\s*[\'\"]vzx-transparent-header-active[\'\"]\s*\)\s*;?\s*</script>#i',
			'',
			$main_content
		);
	}

	$main_content = vuzix_translate_static_ui( $main_content );

	return $main_content;
}

/**
 * Giữ announcement bar và menu header ở hai lớp riêng biệt khi trang dùng
 * hero trong suốt. Markup export dùng class .section-header (không phải
 * .shopify-section-header), nên các CSS cũ đã đặt cả hai khối absolute và
 * khiến một trong hai khối bị che.
 */
function vuzix_header_overlay_safety_styles() {
	?>
	<style id="vuzix-header-overlay-safety">
		/* Đồng bộ nguyên tắc xếp lớp từ original-index.html. */
		.vzx-transparent-header-active .shopify-section-group-header-group {
			position: absolute !important;
			top: 0 !important;
			left: 0 !important;
			right: 0 !important;
			width: 100% !important;
			z-index: 100 !important;
			background: transparent !important;
		}
		.vzx-transparent-header-active .announcement-bar-section {
			position: relative !important;
			top: auto !important;
			z-index: 102 !important;
			width: 100% !important;
			pointer-events: auto !important;
		}
		.vzx-transparent-header-active sticky-header {
			position: relative !important;
			top: auto !important;
			left: auto !important;
			right: auto !important;
			width: 100% !important;
			z-index: 101 !important;
			background: transparent !important;
		}
		.vzx-transparent-header-active .header-wrapper,
		.vzx-transparent-header-active sticky-header,
		.vzx-transparent-header-active header.header,
		.vzx-transparent-header-active .header {
			background: transparent !important;
			border: 0 !important;
			box-shadow: none !important;
		}
		.vzx-transparent-header-active .header {
			margin-top: 18px !important;
			padding-top: 22px !important;
			padding-bottom: 22px !important;
		}
		.vzx-transparent-header-active .announcement-bar-section,
		.vzx-transparent-header-active .announcement-bar-section a,
		.vzx-transparent-header-active .section-header,
		.vzx-transparent-header-active .section-header a,
		.vzx-transparent-header-active .section-header button,
		.vzx-transparent-header-active .section-header summary,
		.vzx-transparent-header-active .section-header details {
			pointer-events: auto !important;
		}
		@media screen and (min-width: 990px) {
			.vzx-mega-menu__content {
				position: fixed !important;
				top: 120px !important;
				left: 0 !important;
				right: 0 !important;
				width: 100vw !important;
				z-index: 9999 !important;
			}
		}
	</style>
	<?php
}
add_action( 'wp_footer', 'vuzix_header_overlay_safety_styles', 1 );

/**
 * The universal product template relies on the original Vuzix hero. Some
 * exported section styles override it later in the document, so restore its
 * base dimensions and contrast after all product markup/styles have loaded.
 */
function vuzix_universal_product_hero_styles() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	?>
	<style id="vuzix-universal-product-hero">
		.single-product .vzx-hero.vzx-hero--transparent-header {
			position: relative !important;
			display: block !important;
			min-height: 420px !important;
			overflow: hidden !important;
			background: linear-gradient(90deg, #050505 0%, #111827 38%, #5f636c 100%) !important;
		}
		.single-product .vzx-hero.vzx-hero--transparent-header::before {
			content: '' !important;
			position: absolute !important;
			inset: 0 !important;
			z-index: 1 !important;
			background: linear-gradient(90deg, rgba(0,0,0,.82), rgba(0,0,0,.42) 52%, rgba(0,0,0,.04)) !important;
		}
		.single-product .vzx-hero.vzx-hero--transparent-header .vzx-hero__inner {
			position: relative !important;
			z-index: 2 !important;
			display: flex !important;
			align-items: center !important;
			box-sizing: border-box !important;
			min-height: 420px !important;
			max-width: 1280px !important;
			margin: 0 auto !important;
			padding: 150px 40px 72px !important;
		}
		.single-product .vzx-hero.vzx-hero--transparent-header .vzx-hero__content { max-width: 960px !important; }
		.single-product .vzx-hero.vzx-hero--transparent-header h1 { margin: 0 !important; color: #fff !important; font-family: Inter, sans-serif !important; font-size: 3em !important; font-weight: 500 !important; line-height: .96 !important; letter-spacing: -.035em !important; }
		@media screen and (max-width: 749px) {
			.single-product .vzx-hero.vzx-hero--transparent-header, .single-product .vzx-hero.vzx-hero--transparent-header .vzx-hero__inner { min-height: 360px !important; }
			.single-product .vzx-hero.vzx-hero--transparent-header .vzx-hero__inner { padding: 120px 20px 48px !important; }
			.single-product .vzx-hero.vzx-hero--transparent-header h1 { font-size: 42px !important; }
		}
	</style>
	<?php
}
add_action( 'wp_footer', 'vuzix_universal_product_hero_styles', 20 );

/**
 * HTML product export dùng logo/menu trắng. Trang chi tiết không overlay header
 * lên hero, nên cần nền header tối cố định để các control này luôn nhìn thấy.
 */
/**
 * In icon thùng rác (remove) dùng cho nút xoá sản phẩm trong giỏ hàng, đặt trong
 * <cart-remove-button> để ăn đúng CSS .cart-item cart-remove-button/.icon-remove
 * đã có sẵn trong component-cart-items.css.
 */
function vuzix_icon_remove() {
	?>
	<svg aria-hidden="true" focusable="false" class="icon icon-remove" viewBox="0 0 20 20" fill="none">
		<path d="M6 6.5h8M8.25 9v4M11.75 9v4M4.75 6.5l.6 8.4a1.5 1.5 0 0 0 1.5 1.4h6.2a1.5 1.5 0 0 0 1.5-1.4l.6-8.4M7.5 6.5V4.75A1.25 1.25 0 0 1 8.75 3.5h2.5a1.25 1.25 0 0 1 1.25 1.25V6.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
	</svg>
	<?php
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

/**
 * Nạp đúng bộ CSS gốc (Shopify component-cart*, component-totals, component-price,
 * component-discounts) chỉ trên trang Giỏ hàng, để giao diện cart.php override
 * (wp-content/themes/vuzix-practice/woocommerce/cart/cart.php) hiển thị giống hệt
 * bản HTML gốc thay vì style mặc định của WooCommerce.
 */
function vuzix_enqueue_cart_assets() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}

	$theme_uri = get_template_directory_uri();
	$base      = $theme_uri . '/cdn/shop/t/85/assets/';

	wp_enqueue_style( 'vuzix-component-cart', $base . 'component-cart.css%253Fv=164708765130180853531783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-cart-items', $base . 'component-cart-items.css%253Fv=13033300910818915211783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-totals', $base . 'component-totals.css%253Fv=15906652033866631521783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-price', $base . 'component-price.css%253Fv=47596247576480123001783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-discounts', $base . 'component-discounts.css%253Fv=152760482443307489271783695641.css', array(), null );
}
add_action( 'wp_enqueue_scripts', 'vuzix_enqueue_cart_assets' );

/**
 * Whether the current request is the legacy Shopify-compatible collection URL.
 * The route is supplied by a custom rewrite, so checking its URL is more
 * reliable than relying solely on WordPress conditional tags/query vars.
 */
function vuzix_is_collections_all_request() {
	if ( get_query_var( 'vuzix_collections_all' ) ) {
		return true;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$request_path = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
	return $request_path === 'collections/all' || strpos( $request_path, 'collections/all/page/' ) === 0;
}

/**
 * In CSS cho trang danh sách sản phẩm (Shop / danh mục sản phẩm) theo đúng
 * giao diện gốc trong original-collections/all.html: banner "vzx-hero" +
 * khối "vuzix-collection__header/actions/sort" + lưới "vuzix-collection__grid"
 * / "vuzix-product-card". Áp dụng cho markup do vuzix_render_shop_archive(),
 * woocommerce/loop/loop-start.php, woocommerce/loop/orderby.php và
 * woocommerce/content-product.php tạo ra.
 */
function vuzix_shop_grid_styles() {
	$is_collections_all = vuzix_is_collections_all_request();

	if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() || $is_collections_all ) ) {
		return;
	}
	?>
	<style>
		.vzx-hero, .vzx-hero * { font-family: "Inter", sans-serif !important; }
		.vzx-hero { position: relative; min-height: var(--hero-height); color: #fff; overflow: hidden; background: linear-gradient(90deg, #050505 0%, #111827 38%, #5f636c 100%); }
		.vzx-hero::before { content: ''; position: absolute; inset: 0; z-index: 1; pointer-events: none; background: linear-gradient(90deg, rgba(0,0,0,.82) 0%, rgba(0,0,0,.68) 26%, rgba(0,0,0,.42) 52%, rgba(0,0,0,.16) 76%, rgba(0,0,0,.04) 100%); }
		.vzx-hero__overlay { display: none; }
		.vzx-hero__inner { position: relative; z-index: 2; width: 100% !important; max-width: 100% !important; min-height: var(--hero-height); display: flex; align-items: center; padding-top: 170px; padding-bottom: 100px; }
		.vzx-hero__content { max-width: 960px; }
		.vzx-hero__eyebrow { margin: 0 0 18px; color: #45c3e8; text-transform: uppercase; letter-spacing: .14em; font-size: 18px; line-height: 1; font-weight: 500 !important; }
		.vzx-hero h1 { margin: 0 0 22px; color: #fff; font-family: 'Inter'; font-size: 3em; line-height: .96; letter-spacing: -.035em !important; font-weight: 500 !important; }
		@media screen and (max-width: 989px) {
			.vzx-hero__inner { padding-top: 150px; padding-bottom: 80px; }
			.vzx-hero h1 { font-size: 58px; }
		}

		/* Header trong suốt đè lên banner — lấy nguyên bộ quy tắc đầy đủ từ
		   original-index.html (bản trong original-collections/all.html bị thiếu
		   phần giữ announcement-bar hiển thị, khiến "FIND YOUR MATCH" bị header đè mất). */
		.vzx-transparent-header-active .shopify-section-group-header-group {
			position: absolute !important; top: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; z-index: 100 !important; background: transparent !important;
		}
		.vzx-transparent-header-active .announcement-bar-section {
			position: relative !important; top: auto !important; z-index: 102 !important; width: 100% !important; pointer-events: auto !important;
		}
		.vzx-transparent-header-active .shopify-section-header,
		.vzx-transparent-header-active #shopify-section-header,
		.vzx-transparent-header-active sticky-header {
			position: relative !important; top: auto !important; left: auto !important; right: auto !important; width: 100% !important; z-index: 101 !important; background: transparent !important;
		}
		.vzx-transparent-header-active .header-wrapper,
		.vzx-transparent-header-active sticky-header,
		.vzx-transparent-header-active header.header,
		.vzx-transparent-header-active .header {
			background: transparent !important; border: 0 !important; box-shadow: none !important;
		}
		.vzx-transparent-header-active .header {
			position: relative !important; margin-top: 18px !important; padding-top: 22px !important; padding-bottom: 22px !important;
		}
		.vzx-transparent-header-active .announcement-bar-section,
		.vzx-transparent-header-active .announcement-bar-section a,
		.vzx-transparent-header-active .shopify-section-header,
		.vzx-transparent-header-active .shopify-section-header a,
		.vzx-transparent-header-active .shopify-section-header button,
		.vzx-transparent-header-active .shopify-section-header summary,
		.vzx-transparent-header-active .shopify-section-header details {
			pointer-events: auto !important;
		}
		.vzx-transparent-header-active .header,
		.vzx-transparent-header-active .header * {
			font-family: "Inter", sans-serif !important; letter-spacing: 0.02em; font-size: 16px;
		}
		.vzx-transparent-header-active .header a,
		.vzx-transparent-header-active .header summary,
		.vzx-transparent-header-active .header__menu-item,
		.vzx-transparent-header-active .header__icon { color: #fff !important; }
		.vzx-transparent-header-active .header svg { color: #fff !important; stroke: currentColor !important; }

		@media screen and (min-width: 990px) and (max-width: 1280px) {
			.vzx-transparent-header-active .header { padding-left: 28px !important; padding-right: 28px !important; column-gap: 18px !important; }
			.vzx-transparent-header-active .header__heading-logo { max-width: 165px !important; height: auto !important; }
			.vzx-transparent-header-active .header__inline-menu { margin-left: 0 !important; }
			.vzx-transparent-header-active .header__menu-item { padding-left: 9px !important; padding-right: 9px !important; font-size: 14px !important; white-space: nowrap !important; }
			.vzx-transparent-header-active .header__icons { display: flex !important; width: auto !important; min-width: max-content !important; flex-shrink: 0 !important; }
		}
		@media screen and (max-width: 989px) {
			.vzx-transparent-header-active .header { position: relative !important; display: grid !important; grid-template-columns: 64px minmax(0, 1fr) auto !important; align-items: center !important; width: 100% !important; min-height: 82px !important; margin: 30px 0 0 !important; padding: 16px 20px !important; }
			.vzx-transparent-header-active header-drawer { grid-column: 1 !important; justify-self: start !important; margin: 0 !important; z-index: 3 !important; }
			.vzx-transparent-header-active .header__heading,
			.vzx-transparent-header-active .header > .header__heading-link { position: absolute !important; top: 50% !important; left: 60% !important; transform: translate(-50%, -50%) !important; width: auto !important; max-width: calc(100% - 250px) !important; margin: 0 !important; padding: 0 !important; z-index: 2 !important; }
			.vzx-transparent-header-active .header__heading .header__heading-link { position: static !important; transform: none !important; margin: 0 !important; padding: 0 !important; }
			.vzx-transparent-header-active .header__heading-logo { display: block !important; width: auto !important; max-width: 150px !important; height: auto !important; margin: 0 auto !important; }
			.vzx-transparent-header-active .header__icons { grid-column: 3 !important; justify-self: end !important; display: flex !important; align-items: center !important; justify-content: flex-end !important; width: auto !important; min-width: max-content !important; margin: 0 !important; padding: 0 !important; gap: 0 !important; z-index: 3 !important; }
			.vzx-transparent-header-active .header__icon { flex: 0 0 44px !important; width: 44px !important; height: 44px !important; }
			.vzx-transparent-header-active .header__icon--search,
			.vzx-transparent-header-active .header__icon--account,
			.vzx-transparent-header-active .header__icon--cart { display: inline-flex !important; visibility: visible !important; opacity: 1 !important; }
		}
		@media screen and (max-width: 520px) {
			.vzx-transparent-header-active .header { grid-template-columns: 54px minmax(0, 1fr) auto !important; min-height: 76px !important; padding-left: 14px !important; padding-right: 14px !important; }
			.vzx-transparent-header-active .header__heading,
			.vzx-transparent-header-active .header > .header__heading-link { max-width: calc(100% - 205px) !important; }
			.vzx-transparent-header-active .header__heading-logo { max-width: 130px !important; }
			.vzx-transparent-header-active .header__icon { flex-basis: 40px !important; width: 40px !important; height: 40px !important; }
		}

		.vuzix-collection { background: #fff; padding-top: 36px; padding-bottom: 36px; }
		.vuzix-collection__header { display: flex; justify-content: space-between; align-items: center; gap: 24px; margin-bottom: 34px; }
		.vuzix-collection__actions { display: flex; align-items: center; gap: 18px; margin-left: auto !important; }
		.vuzix-collection__actions > facet-filters-form { display: block; width: fit-content; margin-left: auto !important; }
		.vuzix-collection__actions .woocommerce-ordering { margin: 0 !important; }
		.vuzix-collection__sort { display: flex; align-items: center; gap: 10px; margin-left: auto !important; }
		.vuzix-collection__sort label { color: #666; font-size: 14px; }
		.vuzix-collection__sort-select { min-width: 160px; height: 48px; padding: 0 42px 0 16px; border: 1.5px solid #333; border-radius: 6px; background: #fff; color: #111; font-size: 14px; font-weight: 700; }

		.vuzix-collection__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; list-style: none; margin: 0; padding: 0; }
		.vuzix-product-card { border: 2px solid #a0a0a0; border-radius: 8px; overflow: hidden; background: #fff; }
		.vuzix-product-card__link { display: block; color: inherit; text-decoration: none; padding: 8px; }
		.vuzix-product-card__image-wrap { aspect-ratio: 1 / 1; border-radius: 6px; overflow: hidden; background: #fff; }
		.vuzix-product-card__image { width: 100%; height: 100%; object-fit: contain; display: block; }
		.vuzix-product-card__content { padding: 16px 4px 8px; }
		.vuzix-product-card__content h2 { margin: 0 0 10px; color: #111; font-size: 20px; min-height: 30px; line-height: 1.1; font-weight: 600; letter-spacing: -0.035em !important; }
		.vuzix-product-card__price { color: #9b9b9b; font-size: 18px; line-height: 1.2; }
		.vuzix-collection__pagination { margin-top: 60px; }
		.vuzix-collection__pagination .pagination { justify-content: center; }

		@media screen and (max-width: 989px) {
			.vuzix-collection__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		}
		@media screen and (max-width: 749px) {
			.vuzix-collection { padding-top: 25px; padding-bottom: 25px; }
			.vuzix-collection__header { flex-direction: column; align-items: flex-end; gap: 18px; }
			.vuzix-collection__actions { width: auto; flex-direction: column; align-items: flex-end; margin-left: auto !important; }
			.vuzix-collection__sort { width: auto; justify-content: flex-end; margin-left: auto !important; }
			.vuzix-collection__sort-select { width: 180px; }
			.vuzix-collection__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
			.vuzix-product-card__content h2 { font-size: 20px; }
			.vuzix-product-card__price { font-size: 16px; }
		}
	</style>
	<?php
}
add_action( 'wp_head', 'vuzix_shop_grid_styles' );

/**
 * Render toàn bộ trang Shop / danh mục sản phẩm: banner "vzx-hero" (giống
 * original-collections/all.html) + lưới sản phẩm thật của WooCommerce.
 * Được gọi từ woocommerce.php khi is_shop() || is_product_taxonomy().
 */
function vuzix_render_shop_archive() {
	// Bỏ khung div/breadcrumb mặc định của WooCommerce và dòng "Showing X of Y
	// results" — bản gốc (original-collections/all.html) không có 2 thứ này.
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );

	// Đổi nhãn cho giống văn phong dropdown sort gốc (Featured/Best selling...),
	// giá trị orderby vẫn là giá trị thật của WooCommerce nên sắp xếp vẫn hoạt động đúng.
	add_filter(
		'woocommerce_catalog_orderby',
		function ( $options ) {
			$labels = array(
				'menu_order' => __( 'Featured', 'vuzix-practice' ),
				'popularity' => __( 'Best selling', 'vuzix-practice' ),
				'date'       => __( 'Date, new to old', 'vuzix-practice' ),
				'price'      => __( 'Price, low to high', 'vuzix-practice' ),
				'price-desc' => __( 'Price, high to low', 'vuzix-practice' ),
			);
			foreach ( $labels as $key => $label ) {
				if ( isset( $options[ $key ] ) ) {
					$options[ $key ] = $label;
				}
			}
			return $options;
		}
	);
	?>
	<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">

		<section class="vzx-hero vzx-hero--transparent-header" style="--hero-height: 420px;">
			<div class="page-width vzx-hero__inner">
				<div class="vzx-hero__content">
					<p class="vzx-hero__eyebrow"><?php esc_html_e( 'Products', 'vuzix-practice' ); ?></p>
					<h1><?php woocommerce_page_title(); ?></h1>
				</div>
			</div>
		</section>
		<script>document.documentElement.classList.add('vzx-transparent-header-active');</script>

		<div class="vuzix-collection">
			<div class="page-width">

				<?php do_action( 'woocommerce_before_main_content' ); ?>

				<div class="vuzix-collection__header">
					<div></div>
					<div class="vuzix-collection__actions">
						<?php do_action( 'woocommerce_before_shop_loop' ); // Thông báo + form sắp xếp thật (woocommerce/loop/orderby.php). ?>
					</div>
				</div>

				<?php if ( woocommerce_product_loop() ) : ?>

					<?php woocommerce_product_loop_start(); ?>
					<?php
					if ( wc_get_loop_prop( 'total' ) ) {
						while ( have_posts() ) {
							the_post();
							do_action( 'woocommerce_shop_loop' );
							wc_get_template_part( 'content', 'product' );
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
}

/**
 * Giao diện Shopify gốc không có banner "has been added to your cart / View
 * cart" giữa hero và lưới. Chỉ ẩn thông báo thêm giỏ hàng thành công ở archive
 * để lỗi tồn kho/validation của WooCommerce vẫn hiển thị bình thường.
 */
function vuzix_hide_shop_add_to_cart_message( $message, $products ) {
	// This site uses direct checkout, so an "added to cart" banner is never useful.
	return '';
}
add_filter( 'wc_add_to_cart_message_html', 'vuzix_hide_shop_add_to_cart_message', 10, 2 );

/**
 * Bỏ thông báo "Your cart is currently empty." mặc định của WooCommerce
 * trên trang giỏ hàng vì woocommerce/cart/cart-empty.php của theme đã tự
 * hiển thị thông báo trống giỏ theo đúng giao diện gốc.
 */
function vuzix_remove_default_empty_cart_message() {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message' );

		/*
		 * woocommerce/cart/cart.php của theme đã tự vẽ khối "Estimated total" +
		 * nút "Check out" theo đúng giao diện gốc, nên bỏ bảng cart-totals cổ điển
		 * (Subtotal/Total + "Proceed to checkout") mà WooCommerce tự gắn vào
		 * woocommerce_cart_collaterals — nếu không nó in ra 2 lần, một bảng xấu
		 * chen giữa danh sách sản phẩm và khối tổng tiền theo đúng giao diện.
		 */
		remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cart_totals', 10 );
	}
}
add_action( 'wp', 'vuzix_remove_default_empty_cart_message' );

/**
 * Thêm Rewrite Rule cho đường dẫn chuẩn gốc: /collections/all
 */
function vuzix_register_collections_all_rewrite_rule() {
	add_rewrite_rule( '^collections/all/page/([0-9]+)/?$', 'index.php?vuzix_collections_all=1&paged=$matches[1]', 'top' );
	add_rewrite_rule( '^collections/all/?$', 'index.php?vuzix_collections_all=1', 'top' );
	add_rewrite_rule( '^all/page/([0-9]+)/?$', 'index.php?vuzix_collections_all=1&paged=$matches[1]', 'top' );
	add_rewrite_rule( '^all/?$', 'index.php?vuzix_collections_all=1', 'top' );
}
add_action( 'init', 'vuzix_register_collections_all_rewrite_rule' );

function vuzix_collections_all_query_vars( $vars ) {
	$vars[] = 'vuzix_collections_all';
	return $vars;
}
add_filter( 'query_vars', 'vuzix_collections_all_query_vars' );

/**
 * Redirect legacy Shopify collection URLs to the live WooCommerce categories.
 * This prevents accessories/merchandise from falling back to exported HTML.
 */
function vuzix_redirect_legacy_product_collections() {
	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$request_path = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
	$legacy_map   = array(
		'accessories'             => 'accessories',
		'collections/accessories' => 'accessories',
		'merchandise'             => 'merchandise',
		'collections/merchandise' => 'merchandise',
		'collections/smart-glasses' => 'smart-glasses',
	);

	if ( ! isset( $legacy_map[ $request_path ] ) || ! taxonomy_exists( 'product_cat' ) ) {
		return;
	}

	$term = get_term_by( 'slug', $legacy_map[ $request_path ], 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	$target = get_term_link( $term );
	if ( is_wp_error( $target ) ) {
		return;
	}

	$query_string = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
	if ( $query_string !== '' ) {
		$target .= '?' . $query_string;
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'vuzix_redirect_legacy_product_collections', 1 );

function vuzix_collections_all_template_include( $template ) {
	if ( get_query_var( 'vuzix_collections_all' ) ) {
		global $wp_query;
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'menu_order';
		$current_page = max(
			1,
			(int) get_query_var( 'paged' ),
			isset( $_GET['collection_page'] ) ? (int) $_GET['collection_page'] : 1,
			isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1
		);

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			// Hiển thị tất cả sản phẩm không giới hạn trên 1 trang
			'posts_per_page' => -1,
			'paged'          => $current_page,
		);

		switch ( $orderby ) {
			case 'popularity':
			case 'best-selling':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'total_sales';
				$args['order']    = 'DESC';
				break;
			case 'title-asc':
			case 'title-ascending':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'title-desc':
			case 'title-descending':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;
			case 'price':
			case 'price-ascending':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order']    = 'ASC';
				break;
			case 'price-desc':
			case 'price-descending':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order']    = 'DESC';
				break;
			case 'date-asc':
			case 'created-ascending':
				$args['orderby'] = 'date';
				$args['order']   = 'ASC';
				break;
			case 'date':
			case 'created-descending':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			default:
				$args['orderby'] = 'menu_order';
				$args['order']   = 'ASC';
				break;
		}

		$wp_query = new WP_Query( $args );
		if ( function_exists( 'wc_set_loop_prop' ) ) {
			wc_set_loop_prop( 'total', $wp_query->found_posts );
			wc_set_loop_prop( 'per_page', -1 );
			wc_set_loop_prop( 'current_page', $current_page );
			wc_set_loop_prop( 'total_pages', $wp_query->max_num_pages );
		}

		return get_theme_file_path( 'woocommerce/archive-product.php' );
	}
	return $template;
}
add_action( 'template_include', 'vuzix_collections_all_template_include' );

/**
 * The custom collection is not a native WordPress archive, so use an explicit
 * query parameter for pagination instead of WordPress's /page/N/ permalink.
 */
function vuzix_collections_all_pagination_args( $args ) {
	if ( ! vuzix_is_collections_all_request() ) {
		return $args;
	}

	$args['base']   = add_query_arg( 'collection_page', '%#%', home_url( '/collections/all/' ) );
	$args['format'] = '';
	return $args;
}
add_filter( 'woocommerce_pagination_args', 'vuzix_collections_all_pagination_args' );

/**
 * Pagination for /collections/all/. This route is a custom query rather than
 * a native archive, so its links must retain the custom page parameter.
 */
function vuzix_render_collections_all_pagination() {
	if ( ! vuzix_is_collections_all_request() ) {
		return;
	}

	global $wp_query;
	$total_pages = (int) $wp_query->max_num_pages;
	if ( $total_pages < 2 ) {
		return;
	}

	$current_page = max(
		1,
		(int) get_query_var( 'paged' ),
		isset( $_GET['collection_page'] ) ? (int) $_GET['collection_page'] : 1
	);
	$query_args = array( 'collection_page' => '%#%' );
	if ( isset( $_GET['orderby'] ) ) {
		$query_args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
	}

	$links = paginate_links(
		array(
			'base'      => add_query_arg( $query_args, home_url( '/collections/all/' ) ),
			'format'    => '',
			'current'   => $current_page,
			'total'     => $total_pages,
			'type'      => 'list',
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
		)
	);

	if ( $links ) {
		echo '<nav class="woocommerce-pagination" aria-label="' . esc_attr__( 'Product Pagination', 'vuzix-practice' ) . '">' . wp_kses_post( $links ) . '</nav>';
	}
}

function vuzix_flush_collections_all_rules() {
	$rules = get_option( 'rewrite_rules' );
	if ( ! isset( $rules['^collections/all/?$'] ) || ! isset( $rules['^collections/all/page/([0-9]+)/?$'] ) ) {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
}
add_action( 'wp_loaded', 'vuzix_flush_collections_all_rules' );

/**
 * Nạp CSS đặc thù cho trang chi tiết sản phẩm.
 */
function vuzix_product_single_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$theme_uri = get_template_directory_uri();
	$base      = $theme_uri . '/cdn/shop/t/85/assets/';

	wp_enqueue_style( 'vuzix-section-main-product', $base . 'section-main-product.css%253Fv=133628908596903377571783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-accordion', $base . 'component-accordion.css%253Fv=7971072480289620591783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-price', $base . 'component-price.css%253Fv=47596247576480123001783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-slider', $base . 'component-slider.css%253Fv=14039311878856620671783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-rating', $base . 'component-rating.css%253Fv=179577762467860590411783695641.css', array(), null );
	wp_enqueue_style( 'vuzix-component-deferred-media', $base . 'component-deferred-media.css%253Fv=14096082462203297471783695641.css', array(), null );
}
add_action( 'wp_enqueue_scripts', 'vuzix_product_single_assets' );

/**
 * Thêm mã CSS tùy chỉnh để làm đẹp các form và input của WooCommerce trong trang chi tiết.
 */
function vuzix_product_single_styles() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	?>
	<style>
		.vuzix-wc-add-to-cart-wrapper {
			margin: 24px 0;
			font-family: "Inter", sans-serif !important;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart {
			display: flex;
			flex-direction: column;
			gap: 20px;
		}
		/* Số lượng Shopify-style */
		.vuzix-wc-add-to-cart-wrapper form.cart quantity-input.quantity {
			display: inline-flex;
			align-items: center;
			border: 1.5px solid #333;
			border-radius: 6px;
			height: 48px;
			background-color: #fff;
			width: 142px;
			overflow: hidden;
			justify-content: space-between;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart quantity-input.quantity .quantity__button {
			width: 45px;
			height: 100%;
			border: 0;
			background: transparent;
			cursor: pointer;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #333;
			padding: 0;
			outline: none;
			transition: background-color 0.1s ease;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart quantity-input.quantity .quantity__button:hover {
			background-color: #f5f5f5;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart quantity-input.quantity .quantity__button svg {
			width: 10px;
			height: 10px;
			display: block;
			color: #333;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart quantity-input.quantity input.qty {
			width: 50px;
			height: 100%;
			border: 0;
			text-align: center;
			font-size: 16px;
			font-weight: 700;
			color: #111;
			padding: 0;
			background: transparent;
			outline: none;
			-moz-appearance: textfield;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart quantity-input.quantity input.qty::-webkit-outer-spin-button,
		.vuzix-wc-add-to-cart-wrapper form.cart quantity-input.quantity input.qty::-webkit-inner-spin-button {
			-webkit-appearance: none;
			margin: 0;
		}
		/* Nút Add to Cart (White background, black border) */
		.vuzix-wc-add-to-cart-wrapper form.cart .single_add_to_cart_button {
			display: inline-flex;
			justify-content: center;
			align-items: center;
			border: 1.5px solid #000 !important;
			padding: 0 30px;
			cursor: pointer;
			font-family: "Inter", sans-serif !important;
			font-size: 15px;
			text-decoration: none;
			color: #000 !important;
			background-color: #fff !important;
			transition: all .2s ease;
			height: 48px;
			width: 100%;
			text-transform: uppercase;
			font-weight: 700;
			border-radius: 6px;
			letter-spacing: .1em;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart .single_add_to_cart_button:hover {
			background-color: #000 !important;
			color: #fff !important;
		}
		/*
		 * Only direct purchase is shown on product pages. Remove this rule later
		 * to bring the standard "Add to cart" button back.
		 */
		.vuzix-wc-add-to-cart-wrapper form.cart .single_add_to_cart_button:not(.vuzix_buy_it_now_button) {
			display: none !important;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart:has(.vuzix_buy_it_now_button) {
			gap: 0;
		}
		/* Nút Buy it now (Solid black background) */
		.vuzix-wc-add-to-cart-wrapper form.cart .vuzix_buy_it_now_button {
			display: inline-flex;
			justify-content: center;
			align-items: center;
			border: 1.5px solid #000 !important;
			padding: 0 30px;
			cursor: pointer;
			font-family: "Inter", sans-serif !important;
			font-size: 15px;
			text-decoration: none;
			color: #fff !important;
			background-color: #000 !important;
			transition: all .2s ease;
			height: 48px;
			width: 100%;
			text-transform: uppercase;
			font-weight: 700;
			border-radius: 6px;
			letter-spacing: .1em;
			margin-top: -8px; /* Close spacing beneath Add to cart */
		}
		.vuzix-wc-add-to-cart-wrapper form.cart .vuzix_buy_it_now_button:hover {
			background-color: #fff !important;
			color: #000 !important;
			border-color: #000 !important;
		}
		/* Dropdown Biến thể (Variants) */
		.vuzix-wc-add-to-cart-wrapper form.cart .variations {
			width: 100%;
			border-collapse: collapse;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart .variations td {
			padding: 6px 0;
			display: block;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart .variations td.label {
			padding-bottom: 6px;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart .variations label {
			font-weight: 700;
			font-size: 13px;
			color: #111;
			text-transform: uppercase;
			letter-spacing: .08em;
		}
		.vuzix-wc-add-to-cart-wrapper form.cart .variations select {
			width: 100%;
			height: 48px;
			padding: 0 16px;
			border: 1.5px solid #333;
			border-radius: 6px;
			background-color: #fff;
			font-size: 14px;
			color: #111;
			font-weight: 700;
			outline: none;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'vuzix_product_single_styles' );

/**
 * Thêm nút "Buy it now" trong form chi tiết sản phẩm.
 */
function vuzix_add_buy_it_now_button() {
	global $product;
	if ( ! $product || ! $product->is_purchasable() ) {
		return;
	}
	?>
	<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="vuzix_buy_it_now_button button">
		Buy it now
	</button>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var form = document.querySelector('form.cart');
			if (form) {
				var buyItNowBtn = form.querySelector('.vuzix_buy_it_now_button');
				if (buyItNowBtn) {
					buyItNowBtn.addEventListener('click', function(e) {
						var input = document.createElement('input');
						input.type = 'hidden';
						input.name = 'vuzix_buy_it_now_redirect';
						input.value = '1';
						form.appendChild(input);
					});
				}
			}
		});
	</script>
	<?php
}
add_action( 'woocommerce_after_add_to_cart_button', 'vuzix_add_buy_it_now_button' );

/**
 * Chuyển hướng thẳng tới trang Checkout (Thanh toán) sau khi nhấn Buy it now.
 */
function vuzix_buy_it_now_redirect( $url ) {
	if ( isset( $_REQUEST['vuzix_buy_it_now_redirect'] ) && function_exists( 'wc_get_checkout_url' ) ) {
		/*
		 * Direct purchase must contain only the item just selected. This prevents
		 * earlier clicks from accumulating in the cart and changing the order total.
		 */
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$product_id = isset( $_REQUEST['add-to-cart'] ) ? absint( wp_unslash( $_REQUEST['add-to-cart'] ) ) : 0;
			$kept_item  = false;
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( ! $kept_item && $product_id && absint( $cart_item['product_id'] ) === $product_id ) {
					WC()->cart->set_quantity( $cart_item_key, 1, false );
					$kept_item = true;
				} else {
					WC()->cart->remove_cart_item( $cart_item_key );
				}
			}
			WC()->cart->calculate_totals();
		}

		return wc_get_checkout_url();
	}
	return $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'vuzix_buy_it_now_redirect' );

/**
 * Website does not offer quantity selection. WooCommerce still receives a
 * valid quantity of one for every purchase. Remove this filter to restore the
 * quantity field in the future.
 */
function vuzix_single_item_purchase_only( $sold_individually, $product ) {
	return ( function_exists( 'is_product' ) && is_product() ) ? true : $sold_individually;
}
add_filter( 'woocommerce_is_sold_individually', 'vuzix_single_item_purchase_only', 10, 2 );

/**
 * Checkout tối giản: chỉ lấy họ tên, địa chỉ và số điện thoại. WooCommerce vẫn
 * dùng các field này để lưu dữ liệu billing/shipping vào order như bình thường.
 */
function vuzix_minimal_checkout_fields( $fields ) {
	$fields['billing'] = array(
		'billing_first_name' => array(
			'label'       => __( 'Họ', 'vuzix-practice' ),
			'required'    => true,
			'class'       => array( 'form-row-first' ),
			'priority'    => 10,
			'autocomplete'=> 'given-name',
		),
		'billing_last_name'  => array(
			'label'       => __( 'Tên', 'vuzix-practice' ),
			'required'    => true,
			'class'       => array( 'form-row-last' ),
			'priority'    => 20,
			'autocomplete'=> 'family-name',
		),
		'billing_address_1'  => array(
			'label'       => __( 'Địa chỉ', 'vuzix-practice' ),
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 30,
			'autocomplete'=> 'street-address',
		),
		'billing_phone'      => array(
			'label'       => __( 'Số điện thoại', 'vuzix-practice' ),
			'required'    => true,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 40,
			'type'        => 'tel',
			'autocomplete'=> 'tel',
		),
	);

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'vuzix_minimal_checkout_fields', 20 );

add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
add_filter( 'woocommerce_enable_checkout_login_reminder', '__return_false' );
add_filter( 'woocommerce_enable_coupon_form', '__return_false' );
add_filter( 'woocommerce_coupons_enabled', '__return_false' );
add_filter( 'woocommerce_order_button_text', function () {
	return __( 'Gửi đơn hàng', 'vuzix-practice' );
} );

/** Phone is required server-side too, so it cannot be bypassed in the browser. */
function vuzix_validate_checkout_phone( $data, $errors ) {
	if ( empty( $data['billing_phone'] ) ) {
		$errors->add( 'billing_phone_required', __( 'Vui lòng nhập số điện thoại.', 'vuzix-practice' ) );
	}
}
add_action( 'woocommerce_after_checkout_validation', 'vuzix_validate_checkout_phone', 10, 2 );

/**
 * Phương thức đặt hàng nội bộ. Nó không thu tiền online; khi submit, WooCommerce
 * tạo một order thật ở trạng thái on-hold để quản trị viên tiếp nhận xử lý.
 */
function vuzix_register_manual_order_gateway() {
	if ( class_exists( 'Vuzix_Manual_Order_Gateway' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class Vuzix_Manual_Order_Gateway extends WC_Payment_Gateway {
		public function __construct() {
			$this->id                 = 'vuzix_manual_order';
			$this->method_title       = __( 'Đặt hàng thủ công', 'vuzix-practice' );
			$this->method_description = __( 'Tạo đơn hàng để cửa hàng liên hệ xác nhận.', 'vuzix-practice' );
			$this->title              = __( 'Đặt hàng', 'vuzix-practice' );
			$this->description        = __( 'Cửa hàng sẽ liên hệ để xác nhận đơn hàng của bạn.', 'vuzix-practice' );
			$this->has_fields         = false;
			$this->enabled            = 'yes';
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			$order->update_status( 'on-hold', __( 'Đơn hàng được tạo từ checkout.', 'vuzix-practice' ) );
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => add_query_arg(
					'key',
					$order->get_order_key(),
					wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() )
				),
			);
		}
	}
}
// Theme functions load after plugins_loaded, so register once WooCommerce is initialized.
add_action( 'woocommerce_init', 'vuzix_register_manual_order_gateway', 20 );

function vuzix_add_manual_order_gateway( $gateways ) {
	$gateways[] = 'Vuzix_Manual_Order_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'vuzix_add_manual_order_gateway' );

/** Only show the internal ordering option on this streamlined checkout. */
function vuzix_only_manual_checkout_gateway( $gateways ) {
	return isset( $gateways['vuzix_manual_order'] )
		? array( 'vuzix_manual_order' => $gateways['vuzix_manual_order'] )
		: $gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'vuzix_only_manual_checkout_gateway' );

/**
 * The existing Checkout page uses the WooCommerce Blocks checkout, which does
 * not load PHP template overrides. Route only the active checkout screen to
 * our native WooCommerce template; keep the normal order-received endpoint.
 */
function vuzix_checkout_template_include( $template ) {
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		$checkout_template = get_theme_file_path( 'page-checkout.php' );
		if ( file_exists( $checkout_template ) ) {
			return $checkout_template;
		}
	}

	return $template;
}
add_filter( 'template_include', 'vuzix_checkout_template_include', 99 );

/**
 * Dynamic WordPress blog route. All posts use one archive template and one
 * single-post template; no exported HTML file is required for new articles.
 */
function vuzix_register_blog_routes() {
	add_rewrite_rule( '^vuzix-blog/page/([0-9]+)/?$', 'index.php?vuzix_blog_archive=1&paged=$matches[1]', 'top' );
	add_rewrite_rule( '^vuzix-blog/?$', 'index.php?vuzix_blog_archive=1', 'top' );
}
add_action( 'init', 'vuzix_register_blog_routes' );

function vuzix_blog_query_vars( $vars ) {
	$vars[] = 'vuzix_blog_archive';
	return $vars;
}
add_filter( 'query_vars', 'vuzix_blog_query_vars' );

function vuzix_blog_template_include( $template ) {
	if ( get_query_var( 'vuzix_blog_archive' ) ) {
		return get_theme_file_path( 'blog.php' );
	}
	return $template;
}
add_filter( 'template_include', 'vuzix_blog_template_include', 98 );

/** Keep every native WordPress blog archive at 18 posts per page. */
function vuzix_blog_posts_per_page( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_home() || $query->is_category() || $query->is_tag() || $query->is_date() || $query->is_author() ) {
		$query->set( 'posts_per_page', 18 );
	}
}
add_action( 'pre_get_posts', 'vuzix_blog_posts_per_page' );

/** Flush the new blog rewrite once, rather than on every request. */
function vuzix_maybe_flush_blog_routes() {
	if ( get_option( 'vuzix_blog_routes_version' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'vuzix_blog_routes_version', '1' );
	}
}
add_action( 'init', 'vuzix_maybe_flush_blog_routes', 30 );

function vuzix_render_blog_hero( $title, $eyebrow = 'Resources', $height = 420 ) {
	?>
	<section class="vzx-hero vzx-hero--transparent-header vuzix-blog-hero" style="--hero-height: <?php echo absint( $height ); ?>px;">
		<div class="page-width vzx-hero__inner">
			<div class="vzx-hero__content">
				<p class="vzx-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<h1><?php echo esc_html( $title ); ?></h1>
			</div>
		</div>
	</section>
	<script>document.documentElement.classList.add('vzx-transparent-header-active');</script>
	<?php
}

function vuzix_render_blog_styles() {
	?>
	<style id="vuzix-wordpress-blog-styles">
		.vuzix-blog-hero { position:relative; min-height:var(--hero-height); overflow:hidden; color:#fff; background:linear-gradient(90deg,#050505 0%,#111827 38%,#5f636c 100%); }
		.vuzix-blog-hero::before { content:''; position:absolute; inset:0; z-index:1; background:linear-gradient(90deg,rgba(0,0,0,.82),rgba(0,0,0,.42) 52%,rgba(0,0,0,.04)); }
		.vuzix-blog-hero .vzx-hero__inner { position:relative; z-index:2; box-sizing:border-box; display:flex; align-items:center; width:100%; max-width:1280px; min-height:var(--hero-height); margin:0 auto; padding:150px 40px 72px; }
		.vuzix-blog-hero .vzx-hero__eyebrow { margin:0 0 18px; color:#45c3e8; font-size:18px; font-weight:500; line-height:1; letter-spacing:.14em; text-transform:uppercase; }
		.vuzix-blog-hero h1 { max-width:1000px; margin:0; color:#fff; font-family:Inter,sans-serif; font-size:3em; font-weight:500; line-height:.96; letter-spacing:-.035em; }
		.vuzix-blog-main { max-width:1280px; margin:0 auto; padding:52px 40px 80px; font-family:Inter,sans-serif; }
		.vuzix-blog-toolbar { display:flex; justify-content:flex-end; align-items:center; gap:12px; margin-bottom:32px; }
		.vuzix-blog-toolbar label { color:#666; font-size:14px; }
		.vuzix-blog-toolbar select { min-width:190px; height:48px; padding:0 42px 0 16px; border:1px solid #555; border-radius:6px; background:#fff; color:#111; font:600 14px Inter,sans-serif; }
		.vuzix-blog-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:24px; margin:0; padding:0; list-style:none; }
		.vuzix-blog-card { height:100%; border:1px solid #ddd; border-radius:8px; overflow:hidden; background:#fff; }
		.vuzix-blog-card__link { display:flex; flex-direction:column; height:100%; color:inherit; text-decoration:none; }
		.vuzix-blog-card__image-wrap { position:relative; aspect-ratio:3/2; overflow:hidden; background:#eee; }
		.vuzix-blog-card__image { display:block; width:100%; height:100%; object-fit:cover; transition:transform .35s ease; }
		.vuzix-blog-card__link:hover .vuzix-blog-card__image { transform:scale(1.035); }
		.vuzix-blog-card__placeholder { display:flex; align-items:center; justify-content:center; width:100%; height:100%; color:#777; background:linear-gradient(135deg,#eee,#d8d8d8); font-weight:600; }
		.vuzix-blog-card__tag { position:absolute; top:14px; left:14px; z-index:1; padding:7px 11px; border-radius:4px; background:#e5724b; color:#fff; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
		.vuzix-blog-card__content { padding:22px; }
		.vuzix-blog-card__title { margin:0 0 20px; color:#111; font-size:22px; font-weight:650; line-height:1.15; letter-spacing:-.025em; }
		.vuzix-blog-card__date { color:#929292; font-size:14px; }
		.vuzix-blog-pagination { margin-top:48px; text-align:center; }
		.vuzix-blog-pagination .page-numbers { display:inline-flex; align-items:center; justify-content:center; min-width:42px; height:42px; margin:3px; border:1px solid #bbb; color:#111; text-decoration:none; }
		.vuzix-blog-pagination .current { border-color:#111; background:#111; color:#fff; }
		.vuzix-article-header { max-width:1200px; margin:0 auto; padding:50px 50px 0; }
		.vuzix-article-title { margin:0 0 20px; max-width:1100px; color:#111; font-size:2.5em; font-weight:700; line-height:1.05; letter-spacing:-.035em; }
		.vuzix-article-meta { display:flex; align-items:center; gap:24px; }
		.vuzix-article-tag { padding:7px 12px; border-radius:4px; background:#e5724b; color:#fff; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
		.vuzix-article-date { color:#929292; font-size:16px; }
		.vuzix-article-featured { display:block; width:min(1100px,calc(100% - 80px)); max-height:680px; margin:42px auto 10px; object-fit:cover; border-radius:8px; }
		.vuzix-article-content { max-width:1080px; margin:0 auto; padding:38px 60px 60px; color:#1b1b1b; font-size:18px; line-height:1.55; }
		.vuzix-article-content p { margin:0 0 20px; }
		.vuzix-article-content h2 { margin:60px 0 22px; color:#222; font-size:42px; line-height:1.15; }
		.vuzix-article-content h3 { margin:44px 0 18px; color:#222; font-size:32px; line-height:1.2; }
		.vuzix-article-content img { max-width:100%; height:auto; margin:34px 0; border-radius:8px; }
		.vuzix-blog-back { display:block; max-width:300px; margin:8px auto 50px; padding:18px 28px; border:2px solid #111; border-radius:8px; color:#111; text-align:center; text-decoration:none; }
		@media(max-width:989px){ .vuzix-blog-grid{grid-template-columns:repeat(2,minmax(0,1fr));} }
		@media(max-width:749px){ .vuzix-blog-hero .vzx-hero__inner{padding:120px 20px 50px;} .vuzix-blog-hero h1{font-size:42px;} .vuzix-blog-main{padding:36px 20px 60px;} .vuzix-blog-grid{grid-template-columns:1fr;} .vuzix-article-header{padding:38px 20px 0;} .vuzix-article-title{font-size:34px;} .vuzix-article-featured{width:calc(100% - 40px);margin-top:30px;} .vuzix-article-content{padding:30px 20px 50px;} }
	</style>
	<?php
}

/**
 * Thêm hậu tố " USD" vào sau hiển thị giá nếu cửa hàng đang dùng đơn vị USD (nhằm khớp $49.99 USD của Shopify).
 */
function vuzix_custom_price_suffix( $price, $product ) {
	if ( function_exists( 'get_woocommerce_currency' ) && get_woocommerce_currency() === 'USD' ) {
		return $price . ' USD';
	}
	return $price;
}
add_filter( 'woocommerce_get_price_html', 'vuzix_custom_price_suffix', 10, 2 );

/**
 * Đặt số lượng sản phẩm hiển thị trên một trang lưu trữ (Shop / collections) là -1 (hiển thị tất cả, không phân trang).
 */
function vuzix_loop_shop_per_page( $cols ) {
	return -1;
}
add_filter( 'loop_shop_per_page', 'vuzix_loop_shop_per_page', 9999 );
