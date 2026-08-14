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

		// Site-wide translation batch (auto-merged)
		'PRESCRIPTIONS' => 'TRÒNG KÍNH THEO ĐƠN',
		'Prescription Lens Install Guides' => 'Hướng dẫn Lắp đặt Tròng kính theo Đơn',
		'Select your device:' => 'Chọn thiết bị của bạn:',
		'DOWNLOAD PDF' => 'TẢI FILE PDF',
		'How To Install' => 'Cách Lắp đặt',
		'BEFORE YOU GET STARTED' => 'TRƯỚC KHI BẮT ĐẦU',
		'Make sure your workspace is clean and dust-free' => 'Đảm bảo không gian làm việc của bạn sạch sẽ và không có bụi',
		'Thoroughly clean your Blade Smart Glasses with a microfiber cloth.' => 'Lau sạch kỹ Blade Smart Glasses của bạn bằng vải sợi nhỏ (microfiber).',
		'STEP 1' => 'BƯỚC 1',
		'INSTALLING YOUR LENSES' => 'LẮP ĐẶT TRÒNG KÍNH CỦA BẠN',
		'Vuzix Blade Smart Glasses come with a removable nose bridge.' => 'Vuzix Blade Smart Glasses được trang bị cầu mũi có thể tháo rời.',
		'Carefully remove the small Philips head screw and remove the nose bridge, for details see below.' => 'Nhẹ nhàng tháo vít đầu Phillips nhỏ và tháo cầu mũi ra, xem chi tiết bên dưới.',
		'The new frame assembly with the prescription lens is installed where the nose bridge was removed. The prescription lens assembly is screwed in place.' => 'Lắp khung mới có tròng kính theo đơn vào vị trí vừa tháo cầu mũi. Cụm tròng kính theo đơn được cố định bằng vít.',
		'STEP 2' => 'BƯỚC 2',
		'ADJUSTING NOSE BRIDGE (OPTIONAL)' => 'ĐIỀU CHỈNH CẦU MŨI (TÙY CHỌN)',
		'You will need the alternate nose bridge and a phillips-head screwdriver.' => 'Bạn sẽ cần cầu mũi thay thế và một tuốc-nơ-vít đầu Phillips.',
		'Blade Smart Glasses are shipped with an adjustable nose bridge. This adjustable nose bridge can resolve issues if the standard nose bridge does not provide adequate support on your nose.' => 'Blade Smart Glasses được giao kèm với cầu mũi có thể điều chỉnh. Cầu mũi có thể điều chỉnh này có thể giải quyết vấn đề nếu cầu mũi chuẩn không tạo đủ độ nâng đỡ trên mũi của bạn.',
		'Using a small Phillips-head screwdriver, carefully remove the standard nose bridge from the frames. Insert the adjustable nose bridge on to the frames and replace the screw, making sure not to over tighten.' => 'Sử dụng tuốc-nơ-vít đầu Phillips nhỏ, nhẹ nhàng tháo cầu mũi chuẩn ra khỏi khung kính. Lắp cầu mũi có thể điều chỉnh vào khung kính và lắp lại vít, chú ý không siết quá chặt.',
		'Publishers should not upload or otherwise make available applications or any other materials that display (via text, images, video or other media) or link to:' => 'Nhà phát hành không được tải lên hoặc cung cấp bằng bất kỳ hình thức nào các ứng dụng hoặc tài liệu khác hiển thị (thông qua văn bản, hình ảnh, video hoặc phương tiện khác) hoặc liên kết đến:',
		'Illegal content.' => 'Nội dung bất hợp pháp.',
		'Invasions of personal privacy or violations of the right of publicity.' => 'Xâm phạm quyền riêng tư cá nhân hoặc vi phạm quyền công khai hình ảnh.',
		'Content that interferes with the functioning of any services of other parties.' => 'Nội dung gây cản trở hoạt động của các dịch vụ của bên thứ ba.',
		'Promotions of hate or incitement of violence.' => 'Nội dung khuyến khích hận thù hoặc kích động bạo lực.',
		'Violations of intellectual property rights, including patent, copyright, trademark, trade secret, or other proprietary right of any party.' => 'Vi phạm quyền sở hữu trí tuệ, bao gồm sáng chế, bản quyền, nhãn hiệu, bí mật kinh doanh hoặc các quyền sở hữu khác của bất kỳ bên nào.',
		'Publishers should not upload or otherwise make available applications or any other materials that:' => 'Nhà phát hành không được tải lên hoặc cung cấp bằng bất kỳ hình thức nào các ứng dụng hoặc tài liệu khác mà:',
		'Harm user devices or personal data.' => 'Gây hại cho thiết bị của người dùng hoặc dữ liệu cá nhân.',
		'Create unpredictable network usage that has an adverse impact on a user\'s service charges or an Authorized Carrier\'s network.' => 'Tạo ra việc sử dụng mạng không thể dự đoán được, gây ảnh hưởng xấu đến cước phí dịch vụ của người dùng hoặc mạng của Nhà mạng Được Ủy quyền.',
		'Knowingly violate an Authorized Carrier\'s terms of service for allowed usage.' => 'Cố ý vi phạm điều khoản dịch vụ của Nhà mạng Được Ủy quyền về việc sử dụng được phép.',
		'Create a "spammy" user experience, whether by posting repetitive content or misleading information about an application\'s purpose.' => 'Tạo ra trải nghiệm người dùng mang tính "spam", cho dù bằng cách đăng nội dung lặp lại hay thông tin gây hiểu lầm về mục đích của ứng dụng.',
		'In the event that your application is removed from the Vuzix App Store, you will receive an email notification to that effect. If you have any questions or concerns regarding a removal or a rating/comment from a user, you may contact us at support@Vuzix.com. Serious or repeated violations of the Publisher Distribution Agreement or this Content Policy will result in account termination. Repeated infringement of intellectual property rights, including copyright, will also result in account termination.' => 'Trong trường hợp ứng dụng của bạn bị gỡ khỏi Vuzix App Store, bạn sẽ nhận được thông báo qua email về việc này. Nếu bạn có bất kỳ câu hỏi hoặc thắc mắc nào liên quan đến việc gỡ ứng dụng hoặc đánh giá/bình luận từ người dùng, bạn có thể liên hệ với chúng tôi tại support@Vuzix.com. Các vi phạm nghiêm trọng hoặc lặp lại đối với Thỏa thuận Phân phối dành cho Nhà Phát hành hoặc Chính sách Nội dung này sẽ dẫn đến việc chấm dứt tài khoản. Việc vi phạm lặp lại quyền sở hữu trí tuệ, bao gồm bản quyền, cũng sẽ dẫn đến việc chấm dứt tài khoản.',
		'REACH OUT' => 'LIÊN HỆ NGAY',
		'Learn More About M400 Smart Glasses' => 'Tìm hiểu thêm về M400 Smart Glasses',
		'AVAILABILITY' => 'TÌNH TRẠNG SẴN CÓ',
		'Country Availability' => 'Tình trạng sẵn có theo quốc gia',
		'Select your device' => 'Chọn thiết bị của bạn',
		'Vuzix M400 All Weather Kit' => 'Bộ Vuzix M400 dùng trong mọi thời tiết',
		'European Union' => 'Liên minh châu Âu',
		'Switzerland' => 'Thụy Sĩ',
		'Thailand' => 'Thái Lan',
		'Turkey' => 'Thổ Nhĩ Kỳ',
		'United Kingdom' => 'Vương quốc Anh',
		'United States' => 'Hoa Kỳ',
		'United States Territories' => 'Lãnh thổ Hoa Kỳ',
		'Australia' => 'Úc',
		'Japan' => 'Nhật Bản',
		'South Korea' => 'Hàn Quốc',
		'Vuzix M400 Extended Wear Kit' => 'Bộ Vuzix M400 đeo dài ngày',
		'Vuzix M4000 All Weather Kit' => 'Bộ Vuzix M4000 dùng trong mọi thời tiết',
		'U.S. Virgin Islands' => 'Quần đảo Virgin thuộc Hoa Kỳ',
		'United Arab Emirates' => 'Các Tiểu vương quốc Ả Rập Thống nhất',
		'Vuzix M4000 Extended Wear Kit' => 'Bộ Vuzix M4000 đeo dài ngày',
		'South Africa' => 'Nam Phi',
		'US Territories' => 'Lãnh thổ Hoa Kỳ',
		'Vuzix Prescriptions' => 'Kính đơn thuốc Vuzix',
		'Vuzix Accessories' => 'Phụ kiện Vuzix',
		'Antigua and Barbuda' => 'Antigua và Barbuda',
		'Bosnia and Herzegovina' => 'Bosnia và Herzegovina',
		'Bouvet Island' => 'Đảo Bouvet',
		'British Indian Ocean Territory' => 'Lãnh thổ Ấn Độ Dương thuộc Anh',
		'Cambodia' => 'Campuchia',
		'Canary Islands' => 'Quần đảo Canary',
		'Cape Verde' => 'Cabo Verde',
		'Cayman islands' => 'Quần đảo Cayman',
		'China' => 'Trung Quốc',
		'Christmas island' => 'Đảo Christmas',
		'Cocos (Keeling) islands' => 'Quần đảo Cocos (Keeling)',
		'Cook islands' => 'Quần đảo Cook',
		'Dominican Republic' => 'Cộng hòa Dominica',
		'East Timor' => 'Đông Timor',
		'Egypt' => 'Ai Cập',
		'Equatorial Guinea' => 'Guinea Xích Đạo',
		'Faroe Islands' => 'Quần đảo Faroe',
		'French Guiana' => 'Guyane thuộc Pháp',
		'French Polynesia' => 'Polynésie thuộc Pháp',
		'French Southern Territories' => 'Lãnh thổ phía Nam thuộc Pháp',
		'Heard Island and Mcdonald Islands' => 'Đảo Heard và Quần đảo McDonald',
		'Hong Kong' => 'Hồng Kông',
		'India' => 'Ấn Độ',
		'Isle of Man' => 'Đảo Man',
		'Ivory Coast' => 'Bờ Biển Ngà',
		'Laos' => 'Lào',
		'Macao' => 'Ma Cao',
		'Macau' => 'Ma Cao',
		'Marshall Islands' => 'Quần đảo Marshall',
		'Mongolia' => 'Mông Cổ',
		'Morocco' => 'Maroc',
		'Netherlands' => 'Hà Lan',
		'Netherlands antilles' => 'Antille thuộc Hà Lan',
		'Norfolk island' => 'Đảo Norfolk',
		'Pitcairn' => 'Quần đảo Pitcairn',
		'Republic of the Congo' => 'Cộng hòa Congo',
		'Reunion' => 'Réunion',
		'Saint barthélemy' => 'Saint Barthélemy',
		'Saint Kitts and Nevis' => 'Saint Kitts và Nevis',
		'Saint Pierre and Miquelon' => 'Saint Pierre và Miquelon',
		'Saint Vincent and the Grenadines' => 'Saint Vincent và Grenadines',
		'Sao Tome and Principe' => 'São Tomé và Príncipe',
		'Saudi Arabia' => 'Ả Rập Xê Út',
		'Solomon Islands' => 'Quần đảo Solomon',
		'South Georgia and the South Sandwich Islands' => 'Nam Georgia và Quần đảo Nam Sandwich',
		'Svalbard and Jan Mayen' => 'Svalbard và Jan Mayen',
		'Trinidad and Tobago' => 'Trinidad và Tobago',
		'Turks and Caicos islands' => 'Quần đảo Turks và Caicos',
		'United States Minor Outlying Islands' => 'Các đảo nhỏ xa của Hoa Kỳ',
		'Vietnam' => 'Việt Nam',
		'Virgin islands, british' => 'Quần đảo Virgin thuộc Anh',
		'Wallis and Futuna' => 'Wallis và Futuna',
		'Western Sahara' => 'Tây Sahara',
		'LX1 7Ah Battery Pack' => 'Bộ pin LX1 7Ah',
		'LX1 4-Slot Battery Charger' => 'Bộ sạc pin 4 khe LX1',
		'Japan, Mexico' => 'Nhật Bản, Mexico',
		'750mAh All Weather Power Bank' => 'Pin sạc dự phòng 750mAh dùng mọi thời tiết',
		'United States Virgin Islands' => 'Quần đảo Virgin thuộc Hoa Kỳ',
		'3200mah Xtreme Weather Power Bank' => 'Pin sạc dự phòng 3200mAh chịu thời tiết khắc nghiệt',
		'4800mAh Extended Use Power Bank' => 'Pin sạc dự phòng 4800mAh sử dụng kéo dài',
		'10,050mAh External Battery' => 'Pin ngoài 10,050mAh',
		'Collar Bank' => 'Pin đeo cổ',
		'Waveguide Optics for AI Smart Glasses and Advanced Display Systems' => 'Quang học Waveguide cho Kính Thông minh AI và Hệ thống Hiển thị Cao cấp',
		'Custom waveguides engineered to specification for OEM partners' => 'Waveguide tùy chỉnh được thiết kế theo yêu cầu kỹ thuật cho các đối tác OEM',
		'Talk to Our Optics Team →' => 'Trò chuyện với Đội ngũ Quang học của chúng tôi →',
		'Explore Configurations &amp; Specs →' => 'Khám phá Cấu hình &amp; Thông số kỹ thuật →',
		'Patents in optical technology' => 'Bằng sáng chế trong công nghệ quang học',
		'Certified facility' => 'Cơ sở được chứng nhận',
		'US-based' => 'Tại Hoa Kỳ',
		'Waveguide manufacturing' => 'Sản xuất waveguide',
		'Waveguides per day/per plant' => 'Waveguide mỗi ngày/mỗi nhà máy',
		'Government contractor' => 'Nhà thầu chính phủ',
		'Technology' => 'Công nghệ',
		'Precision-engineered waveguide systems' => 'Hệ thống waveguide được thiết kế chính xác',
		'Vuzix waveguides are ultra-thin, transparent, and full-color, delivering high-brightness image quality across a wide range of AI smart glasses.' => 'Waveguide của Vuzix siêu mỏng, trong suốt và hiển thị đầy đủ màu sắc, mang lại chất lượng hình ảnh có độ sáng cao trên nhiều loại kính thông minh AI.',
		'Backed by one of the industry’s most extensive optics IP portfolios—with over 500 patents and patents pending—Vuzix has developed core innovations across waveguide geometry, display coupling, optical manufacturing, and system integration. Nearly 30 years of continuous R&amp;D in wearable optics underpins every waveguide we design.' => 'Được hậu thuẫn bởi một trong những danh mục sở hữu trí tuệ (IP) quang học rộng lớn nhất trong ngành—với hơn 500 bằng sáng chế đã được cấp và đang chờ cấp—Vuzix đã phát triển các đổi mới cốt lõi trên nhiều lĩnh vực như hình học waveguide, ghép nối hiển thị, sản xuất quang học và tích hợp hệ thống. Gần 30 năm nghiên cứu và phát triển (R&amp;D) liên tục trong lĩnh vực quang học đeo được là nền tảng cho mọi waveguide mà chúng tôi thiết kế.',
		'We work with partners to define waveguide designs across key parameters including form factor, display engine, and field of view.' => 'Chúng tôi hợp tác với các đối tác để xác định thiết kế waveguide dựa trên các thông số quan trọng bao gồm kiểu dáng (form factor), động cơ hiển thị (display engine) và trường nhìn (field of view).',
		'Thinnest full-color configuration' => 'Cấu hình đầy đủ màu sắc mỏng nhất',
		'Customizable field of view range' => 'Phạm vi trường nhìn có thể tùy chỉnh',
		'Certified manufacturing' => 'Sản xuất được chứng nhận',
		'Design &amp; manufacturing' => 'Thiết kế &amp; sản xuất',
		'Manufacturing' => 'Sản xuất',
		'High-volume waveguide production' => 'Sản xuất waveguide với số lượng lớn',
		'Vuzix operates established high-volume manufacturing for custom waveguides, enabling rapid transition from development to production without additional manufacturing scale-up.' => 'Vuzix vận hành hệ thống sản xuất số lượng lớn đã được thiết lập cho các waveguide tùy chỉnh, giúp chuyển đổi nhanh chóng từ giai đoạn phát triển sang sản xuất mà không cần mở rộng quy mô sản xuất bổ sung.',
		'Location' => 'Vị trí',
		'Rochester, New York, USA' => 'Rochester, New York, Hoa Kỳ',
		'Annual capacity' => 'Công suất hàng năm',
		'Several million waveguides — and expanding' => 'Hàng triệu waveguide — và đang tiếp tục mở rộng',
		'Clean rooms' => 'Phòng sạch',
		'Class 1K and Class 10K environments' => 'Môi trường Class 1K và Class 10K',
		'Certification' => 'Chứng nhận',
		'Capabilities' => 'Năng lực',
		'Design, replication, fabrication, test, system integration' => 'Thiết kế, sao chép, chế tạo, kiểm thử, tích hợp hệ thống',
		'Standard waveguide architectures' => 'Kiến trúc waveguide tiêu chuẩn',
		'Vuzix Core™ configurations are standardized waveguide templates available for rapid sampling. Custom waveguide designs are developed for OEM-specific programs and application requirements.' => 'Cấu hình Vuzix Core™ là các mẫu waveguide được tiêu chuẩn hóa, sẵn có để lấy mẫu nhanh. Các thiết kế waveguide tùy chỉnh được phát triển riêng cho các chương trình OEM cụ thể và yêu cầu ứng dụng.',
		'Vuzix Core™ Configurations →' => 'Cấu hình Vuzix Core™ →',
		'Manufacturing in action' => 'Sản xuất trong thực tế',
		'Vuzix operates high-volume manufacturing for precision waveguide production, supporting the transition from development to full-scale production.' => 'Vuzix vận hành hệ thống sản xuất số lượng lớn cho việc sản xuất waveguide chính xác, hỗ trợ quá trình chuyển đổi từ phát triển sang sản xuất quy mô toàn diện.',
		'Play the video ▶' => 'Xem video ▶',
		'Common questions' => 'Câu hỏi thường gặp',
		'What is a waveguide?' => 'Waveguide là gì?',
		'A waveguide is a transparent optical component that directs and projects digital images into a user\'s field of view while allowing them to maintain a clear view of the surrounding environment. Using precision optical structures, waveguides efficiently guide light through transparent lenses to create lightweight, see-through displays. As a core enabling technology for AI smart glasses, heads-up displays, and other advanced optical systems, waveguides combine high image quality with a natural viewing experience.' => 'Waveguide là một thành phần quang học trong suốt có nhiệm vụ dẫn hướng và chiếu hình ảnh số vào trường nhìn của người dùng, đồng thời vẫn cho phép họ quan sát rõ ràng môi trường xung quanh. Bằng cách sử dụng các cấu trúc quang học chính xác, waveguide dẫn ánh sáng hiệu quả qua các thấu kính trong suốt để tạo ra màn hình hiển thị nhẹ và có thể nhìn xuyên qua. Là công nghệ nền tảng cốt lõi cho kính thông minh AI, màn hình hiển thị heads-up (HUD) và các hệ thống quang học tiên tiến khác, waveguide kết hợp chất lượng hình ảnh cao với trải nghiệm xem tự nhiên.',
		'What if none of the Core configurations fits my product?' => 'Nếu không có cấu hình Core nào phù hợp với sản phẩm của tôi thì sao?',
		'Vuzix develops custom waveguide geometries for OEM partners with application-specific requirements outside the standard portfolio. Custom programs can specify field of view beyond 40°+, a range of display engines (DLP, LCoS, laser LCoS, microLED, OLED), monocular or binocular layouts, helmet-mounted or consumer form factors, prescription compatibility via Litebank, non-headworn (Jumbo format) applications, and material choices including glass, polymer, or ultra-thin glass via Micro-Touch. Initial custom samples typically ship 8–16 weeks from design confirmation.' => 'Vuzix phát triển các hình học waveguide tùy chỉnh cho các đối tác OEM có yêu cầu ứng dụng đặc thù nằm ngoài danh mục tiêu chuẩn. Các chương trình tùy chỉnh có thể chỉ định trường nhìn vượt trên 40°+, nhiều loại động cơ hiển thị (DLP, LCoS, laser LCoS, microLED, OLED), bố cục một mắt hoặc hai mắt, kiểu dáng gắn trên mũ bảo hộ hoặc dùng cho người tiêu dùng, khả năng tương thích kính đơn tròng (prescription) qua Litebank, các ứng dụng không đeo trên đầu (định dạng Jumbo), và các lựa chọn vật liệu bao gồm kính, polymer, hoặc kính siêu mỏng qua công nghệ Micro-Touch. Các mẫu tùy chỉnh ban đầu thường được giao trong vòng 8–16 tuần kể từ khi xác nhận thiết kế.',
		'How does Vuzix Incognito technology actually work?' => 'Công nghệ Vuzix Incognito thực sự hoạt động như thế nào?',
		'Vuzix Incognito reduces forward-facing light leakage in waveguide displays without impacting power consumption. The result is improved image privacy for the wearer and reduced visible signature from the outside — particularly important for defense applications requiring low-signature operation and consumer products where users prefer that nearby people not see the displayed image. Incognito is available on core and most custom waveguide configurations.' => 'Vuzix Incognito giảm hiện tượng rò sáng hướng về phía trước ở màn hình waveguide mà không ảnh hưởng đến mức tiêu thụ điện năng. Kết quả là cải thiện tính riêng tư hình ảnh cho người đeo và giảm dấu hiệu nhận biết từ bên ngoài — điều đặc biệt quan trọng đối với các ứng dụng quốc phòng yêu cầu hoạt động ít bị phát hiện, cũng như các sản phẩm tiêu dùng mà người dùng không muốn người xung quanh nhìn thấy hình ảnh hiển thị. Incognito có sẵn trên các cấu hình Core và hầu hết các cấu hình waveguide tùy chỉnh.',
		'Does Vuzix work with display engines my team is already using?' => 'Vuzix có tương thích với các động cơ hiển thị mà nhóm của tôi đang sử dụng không?',
		'Most likely yes. Vuzix waveguides are compatible with multiple display engine technologies including DLP, LCoS, laser LCoS, microLED, and OLED. The four Vuzix Core configurations are paired with proven display engine choices — DLP at 1280 × 720 for the C-40 and CV-40, LCoS at 640 × 480 for the CI-30, and microLED for the MI-30 — but custom programs can specify alternative display engines to match existing OEM hardware and software stacks.' => 'Rất có thể là có. Waveguide của Vuzix tương thích với nhiều công nghệ động cơ hiển thị khác nhau, bao gồm DLP, LCoS, laser LCoS, microLED và OLED. Bốn cấu hình Vuzix Core được kết hợp với các lựa chọn động cơ hiển thị đã được kiểm chứng — DLP ở độ phân giải 1280 × 720 cho C-40 và CV-40, LCoS ở độ phân giải 640 × 480 cho CI-30, và microLED cho MI-30 — nhưng các chương trình tùy chỉnh có thể chỉ định động cơ hiển thị khác để phù hợp với phần cứng và phần mềm OEM hiện có.',
		'Let’s talk about your waveguide requirements' => 'Hãy cùng trao đổi về yêu cầu waveguide của bạn',
		'Vuzix waveguides are available in standardized configurations and custom architectures designed for integration into AI smart glasses. Our optics team works directly with partners to define performance requirements, system constraints, and production pathways.' => 'Waveguide của Vuzix có sẵn ở các cấu hình tiêu chuẩn hóa và kiến trúc tùy chỉnh được thiết kế để tích hợp vào kính thông minh AI. Đội ngũ quang học của chúng tôi làm việc trực tiếp với các đối tác để xác định yêu cầu về hiệu suất, các ràng buộc hệ thống và lộ trình sản xuất.',
		'Explore OEM Services →' => 'Khám phá Dịch vụ OEM →',
		'Warehousing' => 'Kho vận',
		'Use AR-enabled Vision + Voice to drive productivity and reduce training times' => 'Sử dụng Vision + Voice tích hợp AR để nâng cao năng suất và giảm thời gian đào tạo',
		'Smarter Fulfillment Starts on the Floor' => 'Xử lý đơn hàng thông minh hơn bắt đầu từ hiện trường',
		'Vuzix smart glasses help warehouses and distribution centers increase productivity, improve accuracy, and support a more connected workforce.' => 'Kính thông minh Vuzix giúp các kho hàng và trung tâm phân phối tăng năng suất, cải thiện độ chính xác và hỗ trợ lực lượng lao động kết nối tốt hơn.',
		'Designed for demanding logistics environments, Vuzix provides workers with hands-free access to inventory information, picking instructions, and operational updates.' => 'Được thiết kế cho các môi trường logistics khắc nghiệt, Vuzix cung cấp cho người lao động khả năng truy cập rảnh tay vào thông tin hàng tồn kho, hướng dẫn lấy hàng và các cập nhật vận hành.',
		'This reduces reliance on manual processes while enabling employees to move efficiently throughout the facility.' => 'Điều này giúp giảm sự phụ thuộc vào các quy trình thủ công, đồng thời cho phép nhân viên di chuyển hiệu quả trong toàn bộ cơ sở.',
		'From receiving and replenishment to picking and quality control, Vuzix smart glasses support warehouse workflows with real-time visibility and guided task execution.' => 'Từ tiếp nhận và bổ sung hàng đến lấy hàng và kiểm soát chất lượng, kính thông minh Vuzix hỗ trợ các quy trình vận hành kho hàng với khả năng theo dõi theo thời gian thực và thực hiện công việc có hướng dẫn.',
		'Integrated voice, vision, and AI-powered tools help organizations improve throughput while maintaining high levels of accuracy.' => 'Các công cụ tích hợp giọng nói, hình ảnh và AI giúp các tổ chức cải thiện lưu lượng xử lý trong khi vẫn duy trì độ chính xác cao.',
		'Built to integrate with existing warehouse systems, we help logistics providers streamline operations, reduce training time, and scale performance across their supply chain.' => 'Được xây dựng để tích hợp với các hệ thống kho hàng hiện có, chúng tôi giúp các nhà cung cấp dịch vụ logistics tối ưu hóa vận hành, giảm thời gian đào tạo và mở rộng hiệu suất trên toàn bộ chuỗi cung ứng.',
		'PICK &amp; PACK' => 'LẤY HÀNG &amp; ĐÓNG GÓI',
		'Enhance warehouse efficiencies with Vuzix' => 'Nâng cao hiệu quả kho hàng với Vuzix',
		'Faster order fullfillment' => 'Xử lý đơn hàng nhanh hơn',
		'Despite the onset of autonomous technology (AT), warehousing faces rising costs due to errors in stocking, order picking, and general turnover. Vuzix augmented reality (AR) smart glasses optimize efficiencies in the warehouse and distribution centers by providing an improved onboarding experience, real-time decision-making support, instantly connecting workers to supervisors and enterprise management systems.' => 'Dù công nghệ tự động hóa (AT) ngày càng phổ biến, ngành kho vận vẫn đối mặt với chi phí gia tăng do lỗi trong việc lưu trữ hàng, lấy hàng theo đơn và tỷ lệ nhân sự nghỉ việc cao. Kính thông minh thực tế tăng cường (AR) của Vuzix tối ưu hóa hiệu quả trong kho hàng và trung tâm phân phối bằng cách cải thiện trải nghiệm đào tạo nhân viên mới, hỗ trợ ra quyết định theo thời gian thực, và kết nối ngay lập tức người lao động với quản lý và hệ thống quản lý doanh nghiệp.',
		'EFFICIENCY DRIVERS' => 'YẾU TỐ THÚC ĐẨY HIỆU QUẢ',
		'Benefits of AR in warehousing' => 'Lợi ích của AR trong kho vận',
		'Maintain a High Level of Accuracy' => 'Duy trì độ chính xác cao',
		'Avoid order fulfillment errors with our warehouse picking solutions.' => 'Tránh sai sót trong xử lý đơn hàng với các giải pháp lấy hàng kho vận của chúng tôi.',
		'Boost Worker Performance' => 'Nâng cao hiệu suất người lao động',
		'Eliminate manual processes and enable hands-free mobility with our AR technology.' => 'Loại bỏ các quy trình thủ công và cho phép di chuyển rảnh tay với công nghệ AR của chúng tôi.',
		'Provide Real-Time Feedback' => 'Cung cấp phản hồi theo thời gian thực',
		'Support robust interaction and communication among workers, managers, and software.' => 'Hỗ trợ tương tác và giao tiếp mạnh mẽ giữa người lao động, quản lý và phần mềm.',
		'Create a Safe and Productive Workplace' => 'Tạo môi trường làm việc an toàn và năng suất',
		'Record training videos and give on-the-job remote guidance to new employees.' => 'Ghi lại video đào tạo và cung cấp hướng dẫn từ xa ngay trong công việc cho nhân viên mới.',
		'COMFORT' => 'THOẢI MÁI',
		'Designed for all-day wear' => 'Được thiết kế để đeo suốt cả ngày',
		'Vuzix smart glasses allow logistics professionals to pick orders with unprecedented comfort, speed, and efficiency. The voice-controlled displays feature integrated barcode readers and let workers view inventory data in the corner of their eye.' => 'Kính thông minh Vuzix cho phép các chuyên viên logistics lấy hàng theo đơn với sự thoải mái, tốc độ và hiệu quả chưa từng có. Màn hình điều khiển bằng giọng nói được tích hợp đầu đọc mã vạch, cho phép người lao động xem dữ liệu hàng tồn kho ngay ở góc mắt.',
		'Built for all-day comfort, Vuzix smart glasses improve workplace safety and worker satisfaction. Plus, our extended life battery means your team will be supported the long haul, day in and day out.' => 'Được thiết kế để mang lại sự thoải mái suốt cả ngày, kính thông minh Vuzix cải thiện an toàn nơi làm việc và sự hài lòng của người lao động. Hơn nữa, pin có tuổi thọ kéo dài của chúng tôi đảm bảo đội ngũ của bạn luôn được hỗ trợ trong suốt thời gian dài, ngày qua ngày.',
		'HANDS-FREE, HEADS-UP' => 'RẢNH TAY, KHÔNG CẦN NHÌN XUỐNG',
		'Vuzix LX1 smart glasses for warehousing' => 'Kính thông minh Vuzix LX1 cho kho vận',
		'Vuzix LX1 smart glasses boost logistics organizations productivity by:' => 'Kính thông minh Vuzix LX1 giúp nâng cao năng suất của các tổ chức logistics bằng cách:',
		'Upgrading outdated voice-picking with highly accurate vision + voice systems' => 'Nâng cấp hệ thống lấy hàng bằng giọng nói lạc hậu bằng hệ thống vision + voice có độ chính xác cao',
		'Reducing turnover with faster and more effective training' => 'Giảm tỷ lệ nghỉ việc nhờ đào tạo nhanh hơn và hiệu quả hơn',
		'Improving pick times and accuracy with visual confirmations especially in high-complexity or loud environments' => 'Cải thiện thời gian lấy hàng và độ chính xác nhờ xác nhận trực quan, đặc biệt trong môi trường phức tạp hoặc nhiều tiếng ồn',
		'Integrate every level of 3PL processes with back end tracking and inventory management' => 'Tích hợp mọi cấp độ của quy trình 3PL với hệ thống theo dõi hậu cần và quản lý hàng tồn kho',
		'Keep comfortable and powered with a lightweight all-shift battery' => 'Luôn thoải mái và đủ năng lượng với pin nhẹ dùng trọn ca làm việc',
		'Explore Vuzix LX1' => 'Khám phá Vuzix LX1',
		'How do smart glasses improve warehouse operations?' => 'Kính thông minh cải thiện hoạt động kho vận như thế nào?',
		'Vuzix smart glasses support warehouse workflows including hands-free pick-pack-sort operations, inventory management, putaway and replenishment, cycle counting, and quality inspection. Workers see digital instructions, item details, bin locations, and quantity confirmations directly in their field of view while both hands remain free for product handling. This has been proven to improve picking accuracy, increase throughput compared to handheld scanner or paper-based workflows, and reduce training time for new hires. Vuzix offers a structured Pick &amp; Pack Validation Program for organizations evaluating smart glasses for warehouse use.' => 'Kính thông minh Vuzix hỗ trợ các quy trình vận hành kho hàng bao gồm lấy hàng-đóng gói-phân loại rảnh tay, quản lý hàng tồn kho, xếp hàng và bổ sung hàng, kiểm kê theo chu kỳ, và kiểm tra chất lượng. Người lao động có thể xem hướng dẫn số, thông tin chi tiết sản phẩm, vị trí ngăn hàng, và xác nhận số lượng trực tiếp trong trường nhìn của họ, trong khi cả hai tay vẫn được tự do để xử lý sản phẩm. Điều này đã được chứng minh giúp cải thiện độ chính xác khi lấy hàng, tăng lưu lượng xử lý so với các quy trình dùng máy quét cầm tay hoặc dựa trên giấy tờ, và giảm thời gian đào tạo cho nhân viên mới. Vuzix cung cấp Chương trình Đánh giá Pick &amp; Pack (Pick &amp; Pack Validation Program) có cấu trúc cho các tổ chức đang đánh giá việc sử dụng kính thông minh trong kho vận.',
		'Which Vuzix smart glasses are best for warehouse work?' => 'Kính thông minh Vuzix nào phù hợp nhất cho công việc kho vận?',
		'The Vuzix LX1 is purpose-built for warehouse environments, with a 10-hour battery designed for full-shift use, freezer-rated construction for cold-storage operations, and ruggedization for high-impact warehouse conditions.' => 'Vuzix LX1 được thiết kế chuyên biệt cho môi trường kho vận, với pin 10 giờ được thiết kế để sử dụng trọn ca làm việc, cấu trúc đạt chuẩn hoạt động trong kho lạnh (freezer-rated) cho các hoạt động bảo quản lạnh, và độ bền cao cho các điều kiện kho hàng khắc nghiệt.',
		'What is the Vuzix Pick &amp; Pack Validation Program?' => 'Chương trình Đánh giá Pick &amp; Pack của Vuzix là gì?',
		'The Vuzix Pick &amp; Pack Validation Program is a structured evaluation specifically designed for warehouse operations considering smart glasses for picking, packing, and sorting workflows. It provides guided device evaluation, configuration support, and workflow validation before broader deployment. This helps operations teams confirm that smart glasses will deliver expected accuracy and throughput improvements for their specific environment. The program launched at MODEX 2026 and is offered to qualified warehouse operations.' => 'Chương trình Đánh giá Pick &amp; Pack của Vuzix là một quy trình đánh giá có cấu trúc, được thiết kế riêng cho các hoạt động kho vận đang xem xét sử dụng kính thông minh cho các quy trình lấy hàng, đóng gói và phân loại. Chương trình cung cấp đánh giá thiết bị có hướng dẫn, hỗ trợ cấu hình, và xác thực quy trình vận hành trước khi triển khai rộng rãi. Điều này giúp các nhóm vận hành xác nhận rằng kính thông minh sẽ mang lại độ chính xác và cải thiện lưu lượng xử lý như mong đợi cho môi trường cụ thể của họ. Chương trình được ra mắt tại MODEX 2026 và được cung cấp cho các đơn vị kho vận đủ điều kiện.',
		'Safety Certifications for Vuzix Batteries' => 'Chứng nhận An toàn cho Pin Vuzix',
		'All Vuzix batteries are tested, certified, and in compliance with these international safety standards:' => 'Tất cả pin của Vuzix đều được kiểm tra, chứng nhận và tuân thủ các tiêu chuẩn an toàn quốc tế sau:',
		'International Standard IEC 62133: Covers secondary cells and batteries containing alkaline or other nonacid electrolytes.' => 'Tiêu chuẩn Quốc tế IEC 62133: Áp dụng cho các pin và cell pin thứ cấp chứa chất điện phân kiềm hoặc các chất điện phân không có tính axit khác.',
		'United Nations (UN) Transport Regulations UN38.3: Covers battery safety during handling and transport.' => 'Quy định Vận chuyển của Liên Hợp Quốc (UN) UN38.3: Áp dụng cho an toàn pin trong quá trình xử lý và vận chuyển.',
		'UN38.3.5 Test Summaries' => 'Tóm tắt Kết quả Thử nghiệm UN38.3.5',
		'Blade Model 447 and 494' => 'Blade Mẫu 447 và 494',
		'447RF0004 (0002) - Alium ABI-H401640-L - UN38.3.5 Test Summary' => '447RF0004 (0002) - Alium ABI-H401640-L - Tóm tắt Thử nghiệm UN38.3.5',
		'447RF0005 (0002) - Alium ABI-H401640-R - UN38.3.5 Test Summary' => '447RF0005 (0002) - Alium ABI-H401640-R - Tóm tắt Thử nghiệm UN38.3.5',
		'M400 and M4000 and external power bank' => 'M400 và M4000 và pin sạc dự phòng ngoài',
		'472RF0004 (0001) Alium ABI-H651721 - UN38.3.5 Test Summary' => '472RF0004 (0001) Alium ABI-H651721 - Tóm tắt Thử nghiệm UN38.3.5',
		'478RF0001 (0001) - Alium ABI-H102039 - UN38.3.5 Test Summary' => '478RF0001 (0001) - Alium ABI-H102039 - Tóm tắt Thử nghiệm UN38.3.5',
		'4800 Power Bank UN38.3-5 Test Summary' => 'Pin sạc dự phòng 4800 - Tóm tắt Thử nghiệm UN38.3-5',
		'LX1 Battery - Model A9V - UN38.3 Test Summary' => 'Pin LX1 - Mẫu A9V - Tóm tắt Thử nghiệm UN38.3',
		'M300 and M300XL and external power bank' => 'M300 và M300XL và pin sạc dự phòng ngoài',
		'446RF0001 (0001) - Alium ABI751671 UN38.3.5 Test Summary' => '446RF0001 (0001) - Alium ABI751671 - Tóm tắt Thử nghiệm UN38.3.5',
		'446RF0002 (0001) - EVE 651723 UN38.3.5 Test Summary' => '446RF0002 (0001) - EVE 651723 - Tóm tắt Thử nghiệm UN38.3.5',
		'Blade Collar Battery' => 'Pin đeo cổ Blade',
		'447RF0006 (0001) - EVE18650-1S1P - UN38.3.5 Test Summary' => '447RF0006 (0001) - EVE18650-1S1P - Tóm tắt Thử nghiệm UN38.3.5',
		'467RF0001 (0001) AE573342P - UN38.3.5 Test Summary' => '467RF0001 (0001) AE573342P - Tóm tắt Thử nghiệm UN38.3.5',
		'ACCOUNT' => 'TÀI KHOẢN',
		'Select a Department:' => 'Chọn một Bộ phận:',
		'Vuzix Headquarters' => 'Trụ sở chính Vuzix',
		'Phone: 585-359-5900' => 'Điện thoại: 585-359-5900',
		'Toll Free: 800-436-7838' => 'Miễn phí: 800-436-7838',
		'Call Now →' => 'Gọi Ngay →',
		'Learn how you can access Vuzix resources to build your own applications for our products. Access our Developer’s Center for information on our family of devices and developers’ kits.' => 'Tìm hiểu cách bạn có thể truy cập các tài nguyên của Vuzix để xây dựng ứng dụng riêng cho sản phẩm của chúng tôi. Truy cập Trung tâm Nhà phát triển của chúng tôi để biết thông tin về dòng thiết bị và các bộ công cụ phát triển (developer\'s kits).',
		'Developer Center →' => 'Trung tâm Nhà phát triển →',
		'Contact Us' => 'Liên hệ với Chúng tôi',
		'Learn More About AI Smart Glasses' => 'Tìm hiểu thêm về Kính Thông minh AI',
		'Operations' => 'Vận hành',
		'Vuzix Publisher Distribution Agreement' => 'Thỏa thuận Phân phối cho Nhà phát hành Vuzix',
		'By uploading or otherwise making available applications or any other materials via the Vuzix App Store, you (on behalf of yourself or the business you represent) agree to be bound by the terms of this agreement. As used in this Agreement, "we," "us," and "Vuzix" means Vuzix Corporation (“Vuzix”), a company with principal place of business at 25 Hendrix Rd, West Henrietta, NY 14586 or any of its affiliates, and “you” and "Publisher" means the applicant (if registering as an individual), or the business employing the applicant (if registering as a business). Capitalized terms have the meanings listed in the Definitions below.' => 'Bằng việc tải lên hoặc cung cấp ứng dụng hay bất kỳ tài liệu nào khác qua Vuzix App Store, bạn (đại diện cho chính bạn hoặc doanh nghiệp mà bạn đại diện) đồng ý bị ràng buộc bởi các điều khoản của thỏa thuận này. Trong Thỏa thuận này, "chúng tôi" và "Vuzix" có nghĩa là Vuzix Corporation (“Vuzix”), một công ty có trụ sở chính tại 25 Hendrix Rd, West Henrietta, NY 14586, hoặc bất kỳ công ty liên kết nào của công ty này, và "bạn" và "Nhà phát hành" có nghĩa là người đăng ký (nếu đăng ký với tư cách cá nhân), hoặc doanh nghiệp sử dụng người đăng ký đó (nếu đăng ký với tư cách doanh nghiệp). Các thuật ngữ viết hoa có ý nghĩa như được liệt kê trong phần Định nghĩa dưới đây.',
		'Definitions' => 'Định nghĩa',
		'Device:' => 'Thiết bị:',
		'Any device that can access the Vuzix App Store, as defined herein.' => 'Bất kỳ thiết bị nào có thể truy cập Vuzix App Store, theo định nghĩa trong tài liệu này.',
		'Products:' => 'Sản phẩm:',
		'Software, content and digital materials distributed via the Vuzix App Store.' => 'Phần mềm, nội dung và tài liệu số được phân phối qua Vuzix App Store.',
		'Vuzix Developer Account:' => 'Tài khoản Nhà phát triển Vuzix:',
		'An account issued to users of Vuzix App Store that enables the distribution of Products via the Vuzix App Store.' => 'Tài khoản được cấp cho người dùng Vuzix App Store, cho phép phân phối Sản phẩm qua Vuzix App Store.',
		'The Store managed by Vuzix.' => 'Cửa hàng do Vuzix quản lý.',
		'Services:' => 'Dịch vụ:',
		'The services provided by Vuzix in relation to the distribution of Products via the Vuzix App Store.' => 'Các dịch vụ do Vuzix cung cấp liên quan đến việc phân phối Sản phẩm qua Vuzix App Store.',
		'1. Introduction' => '1. Giới thiệu',
		'1.1 The Vuzix App Store is a publicly available site on which Publishers can distribute their own Products for Vuzix Devices. In order to distribute your Products through the Vuzix App Store, you must maintain a valid Vuzix Developer Account.' => '1.1 Vuzix App Store là một trang web công khai, nơi các Nhà phát hành có thể phân phối Sản phẩm của riêng mình cho các Thiết bị Vuzix. Để phân phối Sản phẩm của bạn qua Vuzix App Store, bạn phải duy trì một Tài khoản Nhà phát triển Vuzix hợp lệ.',
		'2. Accepting this Agreement' => '2. Chấp nhận Thỏa thuận này',
		'2.1 This agreement ("Agreement") forms a legally binding contract between you and Vuzix in relation to distribution of Products via the Vuzix App Store. In order to distribute Products via the Vuzix App Store, you must first agree to this Agreement by accepting it online (through the button “I accept”). You may not distribute Products on the Vuzix App Store if you do not accept this Agreement.' => '2.1 Thỏa thuận này ("Thỏa thuận") tạo thành một hợp đồng ràng buộc pháp lý giữa bạn và Vuzix liên quan đến việc phân phối Sản phẩm qua Vuzix App Store. Để phân phối Sản phẩm qua Vuzix App Store, trước tiên bạn phải đồng ý với Thỏa thuận này bằng cách chấp nhận trực tuyến (thông qua nút "Tôi đồng ý"). Bạn không được phân phối Sản phẩm trên Vuzix App Store nếu bạn không chấp nhận Thỏa thuận này.',
		'2.2 Use of the Vuzix App Store and Services is limited to parties that lawfully can enter into and form contracts under applicable law.' => '2.2 Việc sử dụng Vuzix App Store và các Dịch vụ chỉ giới hạn cho các bên có đủ năng lực pháp lý để tham gia và giao kết hợp đồng theo luật áp dụng.',
		'2.3 you represent and warrant that: (a) if you are a business, you are duly organized, validly existing and in good standing under the laws of the country in which your business is registered; and (b) you have all requisite right, power and authority to enter into this Agreement and perform your obligations hereunder.' => '2.3 bạn tuyên bố và bảo đảm rằng: (a) nếu bạn là một doanh nghiệp, bạn được tổ chức hợp lệ, đang tồn tại hợp pháp và có tình trạng pháp lý tốt theo luật pháp của quốc gia nơi doanh nghiệp của bạn được đăng ký; và (b) bạn có đầy đủ quyền, năng lực và thẩm quyền cần thiết để tham gia Thỏa thuận này và thực hiện các nghĩa vụ của mình theo Thỏa thuận.',
		'3. Distribution of Products' => '3. Phân phối Sản phẩm',
		'3.1 You can distribute your Products through the Vuzix App Store.' => '3.1 Bạn có thể phân phối Sản phẩm của mình qua Vuzix App Store.',
		'3.2 You agree to use the Vuzix App Store for distribution of your Products only for purposes that are permitted by (a) this Agreement, (b) the Vuzix App Store Terms of Service and Privacy Policy and (b) any applicable law, regulation or generally accepted practices or guidelines in the relevant jurisdictions.' => '3.2 Bạn đồng ý chỉ sử dụng Vuzix App Store để phân phối Sản phẩm của mình cho các mục đích được cho phép theo (a) Thỏa thuận này, (b) Điều khoản Dịch vụ và Chính sách Quyền riêng tư của Vuzix App Store, và (b) mọi luật, quy định hoặc thông lệ, hướng dẫn được chấp nhận chung tại các khu vực pháp lý liên quan.',
		'Privacy of Device Users.' => 'Quyền riêng tư của Người dùng Thiết bị.',
		'You agree that if you use the Vuzix App Store to distribute your own Products, you will protect the privacy and legal rights of users. If the users provide you with, or your Product accesses or uses, usernames, passwords, or other login information or personal information, you must make the users aware that the information will be available to your Product, and you must provide legally adequate privacy notice and protection for those users. Further, your Product may only use that information for the limited purposes for which the user has given you permission to do so. If your Product stores personal or sensitive information provided by users, it must do so securely and only for as long as it is needed. But if the user has opted into a separate agreement with you that allows you or your Product to store or use personal or sensitive information directly related to your Product (not including other products or applications) then the terms of that separate agreement will govern your use of such information. If the user provides your Product with Vuzix Account information, your Product may only use that information to access the user\'s Vuzix Account when, and for the limited purposes for which, the user has given you permission to do so.' => 'Bạn đồng ý rằng nếu bạn sử dụng Vuzix App Store để phân phối Sản phẩm của riêng mình, bạn sẽ bảo vệ quyền riêng tư và các quyền hợp pháp của người dùng. Nếu người dùng cung cấp cho bạn, hoặc Sản phẩm của bạn truy cập hay sử dụng tên đăng nhập, mật khẩu hoặc thông tin đăng nhập khác hay thông tin cá nhân, bạn phải cho người dùng biết rằng thông tin đó sẽ được cung cấp cho Sản phẩm của bạn, và bạn phải đưa ra thông báo về quyền riêng tư và biện pháp bảo vệ phù hợp về mặt pháp lý cho những người dùng đó. Hơn nữa, Sản phẩm của bạn chỉ được sử dụng thông tin đó cho các mục đích hạn chế mà người dùng đã cho phép bạn thực hiện. Nếu Sản phẩm của bạn lưu trữ thông tin cá nhân hoặc thông tin nhạy cảm do người dùng cung cấp, việc lưu trữ đó phải được thực hiện an toàn và chỉ trong thời gian cần thiết. Tuy nhiên, nếu người dùng đã tham gia một thỏa thuận riêng với bạn cho phép bạn hoặc Sản phẩm của bạn lưu trữ hay sử dụng thông tin cá nhân hoặc thông tin nhạy cảm liên quan trực tiếp đến Sản phẩm của bạn (không bao gồm các sản phẩm hoặc ứng dụng khác), thì các điều khoản của thỏa thuận riêng đó sẽ chi phối việc bạn sử dụng thông tin này. Nếu người dùng cung cấp cho Sản phẩm của bạn thông tin Tài khoản Vuzix, Sản phẩm của bạn chỉ được sử dụng thông tin đó để truy cập Tài khoản Vuzix của người dùng khi, và cho các mục đích hạn chế mà, người dùng đã cho phép bạn thực hiện.',
		'Vuzix owns all the information related to customers of the Vuzix App Store and to the transactions entered with such customers; Vuzix may share this information with you, following specific agreement, if that is allowed by the applicable laws' => 'Vuzix sở hữu toàn bộ thông tin liên quan đến khách hàng của Vuzix App Store và các giao dịch được thực hiện với những khách hàng đó; Vuzix có thể chia sẻ thông tin này với bạn, theo thỏa thuận cụ thể, nếu điều đó được luật áp dụng cho phép',
		'Prohibited Actions.' => 'Các Hành vi Bị Cấm.',
		'you agree that you will not engage in any activity with the Vuzix App Store, including the publishing or distribution of Products, that interferes with, disrupts, damages, or accesses in an unauthorized manner the devices, servers, networks, or other properties or services of any third party including, but not limited to, Device users and Vuzix. You may not use customer information obtained from the Vuzix App Store to sell or distribute Products outside of the Vuzix App Store.' => 'bạn đồng ý rằng bạn sẽ không tham gia vào bất kỳ hoạt động nào với Vuzix App Store, bao gồm việc phát hành hoặc phân phối Sản phẩm, mà gây cản trở, gián đoạn, gây hại hoặc truy cập một cách không được phép vào thiết bị, máy chủ, mạng hoặc các tài sản hay dịch vụ khác của bất kỳ bên thứ ba nào, bao gồm nhưng không giới hạn ở người dùng Thiết bị và Vuzix. Bạn không được sử dụng thông tin khách hàng thu được từ Vuzix App Store để bán hoặc phân phối Sản phẩm bên ngoài Vuzix App Store.',
		'Non-Compete.' => 'Không Cạnh tranh.',
		'You may not use the Vuzix App Store to distribute or make available any Product whose primary purpose is to facilitate the distribution of software applications and games for use on devices outside of the Vuzix App Store.' => 'Bạn không được sử dụng Vuzix App Store để phân phối hoặc cung cấp bất kỳ Sản phẩm nào có mục đích chính là hỗ trợ việc phân phối các ứng dụng phần mềm và trò chơi để sử dụng trên các thiết bị bên ngoài Vuzix App Store.',
		'3.8 you agree that you are solely responsible for (and that Vuzix has no responsibility to you or to any third party for) any breach of your obligations under this Agreement, any applicable third-party contract or terms of service, or any applicable law or regulation, and for the consequences (including any loss or damage which Vuzix or any third party may suffer) of any such breach.' => '3.8 bạn đồng ý rằng bạn hoàn toàn chịu trách nhiệm (và Vuzix không có trách nhiệm nào đối với bạn hoặc bất kỳ bên thứ ba nào) đối với bất kỳ vi phạm nghĩa vụ nào của bạn theo Thỏa thuận này, bất kỳ hợp đồng hoặc điều khoản dịch vụ áp dụng nào của bên thứ ba, hoặc bất kỳ luật hay quy định áp dụng nào, và đối với các hậu quả (bao gồm bất kỳ tổn thất hoặc thiệt hại nào mà Vuzix hoặc bất kỳ bên thứ ba nào có thể phải gánh chịu) từ bất kỳ vi phạm nào như vậy.',
		'Product Ratings.' => 'Đánh giá Sản phẩm.',
		'The Vuzix App Store in the future will allow users to rate Products. Product ratings may be used to determine the placement of Products on the Store with higher rated Products generally given better placement. Your Products may be subject to user ratings to which you may not agree. You may contact Vuzix if you have any questions or concerns regarding such ratings.' => 'Trong tương lai, Vuzix App Store sẽ cho phép người dùng đánh giá Sản phẩm. Đánh giá Sản phẩm có thể được sử dụng để xác định vị trí hiển thị của Sản phẩm trên Cửa hàng, trong đó các Sản phẩm được đánh giá cao hơn thường được ưu tiên vị trí hiển thị tốt hơn. Sản phẩm của bạn có thể phải chịu các đánh giá của người dùng mà bạn không đồng ý. Bạn có thể liên hệ với Vuzix nếu có bất kỳ câu hỏi hoặc thắc mắc nào liên quan đến các đánh giá đó.',
		'Restricted Content.' => 'Nội dung Bị Hạn chế.',
		'Any Product you distribute on the Vuzix App Store must adhere to Vuzix Content Policy for Publishers located at' => 'Mọi Sản phẩm bạn phân phối trên Vuzix App Store phải tuân thủ Chính sách Nội dung dành cho Nhà phát hành của Vuzix, được đăng tại',
		'Support.' => 'Hỗ trợ.',
		'You will be solely responsible for support and maintenance of your Products and any complaints about your Products. Your contact information will be displayed in each application detail page and made available to users for customer support purposes.' => 'Bạn sẽ hoàn toàn chịu trách nhiệm về việc hỗ trợ và bảo trì Sản phẩm của mình cũng như mọi khiếu nại liên quan đến Sản phẩm của bạn. Thông tin liên hệ của bạn sẽ được hiển thị trên mỗi trang chi tiết ứng dụng và cung cấp cho người dùng nhằm mục đích hỗ trợ khách hàng.',
		'4. Products Revenue Share' => '4. Chia sẻ Doanh thu Sản phẩm',
		'4.1 If you enroll as a Vuzix Developer, this Agreement covers the share of the revenue generated by the sale of your Products.' => '4.1 Nếu bạn đăng ký làm Nhà phát triển Vuzix, Thỏa thuận này bao gồm phần chia sẻ doanh thu phát sinh từ việc bán Sản phẩm của bạn.',
		'4.2 You may set the price for your Products in the currencies permitted by the Vuzix App Store. The Vuzix App Store may display to users the price of Products in their native currency, but it is not responsible for the accuracy of currency rates or conversion.' => '4.2 Bạn có thể đặt giá cho Sản phẩm của mình bằng các loại tiền tệ được Vuzix App Store cho phép. Vuzix App Store có thể hiển thị cho người dùng giá Sản phẩm bằng đồng tiền bản địa của họ, nhưng không chịu trách nhiệm về độ chính xác của tỷ giá hoặc việc chuyển đổi tiền tệ.',
		'4.3 The sales prices for your Products are determined by you. Third party Products prices are determined by their authors or distributors. Prices set by the Publisher shall be inclusive of all indirect taxes.' => '4.3 Giá bán cho Sản phẩm của bạn do bạn quyết định. Giá của Sản phẩm bên thứ ba do tác giả hoặc nhà phân phối của họ quyết định. Giá do Nhà phát hành đặt ra phải đã bao gồm tất cả các loại thuế gián thu.',
		'4.4 As an exception to 4.3, Vuzix may agree with some partners that the price set by you for your Products will be adjusted to a round figure that such partner is able to charge its customers. This may lead to changes in the sales price defined by you, always to the closest higher price set by the partner at issue.' => '4.4 Là một ngoại lệ đối với mục 4.3, Vuzix có thể thỏa thuận với một số đối tác rằng giá do bạn đặt cho Sản phẩm của mình sẽ được điều chỉnh thành một con số tròn mà đối tác đó có thể áp dụng khi tính phí khách hàng của họ. Điều này có thể dẫn đến thay đổi về giá bán do bạn xác định, luôn theo hướng làm tròn lên mức giá gần nhất do đối tác liên quan đặt ra.',
		'4.5 The Publisher has the primary responsibility to collect and remit all indirect taxes for sale of Products to the appropriate taxation authorities; Vuzix will provide sufficient data to the Publisher for this purpose.' => '4.5 Nhà phát hành có trách nhiệm chính trong việc thu và nộp tất cả các loại thuế gián thu đối với việc bán Sản phẩm cho các cơ quan thuế có thẩm quyền; Vuzix sẽ cung cấp đầy đủ dữ liệu cho Nhà phát hành nhằm mục đích này.',
		'4.6 The price you set for Products will determine the amount of payment you will receive. For Products provided by third parties that you sell through your Store, the amount you will receive will be calculated over the price less the revenue share due to such third parties. The amount to be paid to you is equal to the sales price less the share due to: (i) third party Publishers, (ii) Vuzix, (iii) Vuzix’ Partners and (iii) Payment Processor. Such amounts will be remitted to you according to these Agreement rules.' => '4.6 Giá bạn đặt cho Sản phẩm sẽ quyết định số tiền thanh toán bạn nhận được. Đối với Sản phẩm do bên thứ ba cung cấp mà bạn bán qua Cửa hàng của mình, số tiền bạn nhận được sẽ được tính trên giá bán trừ đi phần chia sẻ doanh thu phải trả cho các bên thứ ba đó. Số tiền được trả cho bạn bằng giá bán trừ đi phần chia sẻ phải trả cho: (i) các Nhà phát hành bên thứ ba, (ii) Vuzix, (iii) các Đối tác của Vuzix và (iii) Đơn vị Xử lý Thanh toán. Các khoản tiền này sẽ được chuyển cho bạn theo các quy tắc của Thỏa thuận này.',
		'4.7 The default (Tier 1) revenue share for certified publishers is 70% for you and 30% for Vuzix, after deduction of all transaction expenses. Vuzix will assess a minimum fee of $3.00 per distribution via the app store.' => '4.7 Mức chia sẻ doanh thu mặc định (Cấp 1) đối với các nhà phát hành đã được chứng nhận là 70% cho bạn và 30% cho Vuzix, sau khi trừ đi tất cả các chi phí giao dịch. Vuzix sẽ áp dụng mức phí tối thiểu là 3,00 USD cho mỗi lượt phân phối qua app store.',
		'4.8 You may also choose to distribute free Products. If the Product is free, you will not be entitled to receive any amounts.' => '4.8 Bạn cũng có thể chọn phân phối Sản phẩm miễn phí. Nếu Sản phẩm là miễn phí, bạn sẽ không được nhận bất kỳ khoản tiền nào.',
		'Special Refund Requirements.' => 'Yêu cầu Hoàn tiền Đặc biệt.',
		'You authorize Vuzix to give the buyer a full refund of the Product price if the buyer requests the refund within 24 hours after purchase.' => 'Bạn cho phép Vuzix hoàn lại toàn bộ giá Sản phẩm cho người mua nếu người mua yêu cầu hoàn tiền trong vòng 24 giờ sau khi mua.',
		'Reinstalls.' => 'Cài đặt lại.',
		'Users are allowed unlimited reinstalls of each application distributed via the Vuzix App Store, provided however that if you remove paid Products from the Vuzix App Store, such Products shall be removed from all portions of the Vuzix App Store and users shall no longer have a right or ability to reinstall the affected Products.' => 'Người dùng được phép cài đặt lại không giới hạn số lần đối với mỗi ứng dụng được phân phối qua Vuzix App Store; tuy nhiên, nếu bạn gỡ bỏ các Sản phẩm có phí khỏi Vuzix App Store, các Sản phẩm đó sẽ bị gỡ bỏ khỏi mọi phần của Vuzix App Store và người dùng sẽ không còn quyền hoặc khả năng cài đặt lại các Sản phẩm bị ảnh hưởng.',
		'6. Payments' => '6. Thanh toán',
		'6.1 You shall receive payments related to the sale of your Products, as determined by Vuzix for the participants in this Vuzix Publishers Distribution Agreement.' => '6.1 Bạn sẽ nhận được các khoản thanh toán liên quan đến việc bán Sản phẩm của mình, theo quy định của Vuzix áp dụng cho các bên tham gia Thỏa thuận Phân phối cho Nhà phát hành Vuzix này.',
		'6.2 Unless otherwise agreed to by the parties in writing (including by electronic mail), payments to you shall be sent by Vuzix within approximately thirty (30) days after the end of each calendar quarter that Products are sold on your Store if your total earned balance is 200 USD or more.' => '6.2 Trừ khi các bên có thỏa thuận khác bằng văn bản (bao gồm qua email), các khoản thanh toán cho bạn sẽ được Vuzix gửi trong vòng khoảng ba mươi (30) ngày sau khi kết thúc mỗi quý dương lịch mà Sản phẩm được bán trên Cửa hàng của bạn, nếu tổng số dư đã tích lũy của bạn đạt 200 USD hoặc cao hơn.',
		'6.3 In the event the Agreement is terminated, Vuzix shall pay your earned balance to you within approximately ninety (90) days after the end of the calendar month in which the Agreement is terminated by you (following Vuzix’ receipt of your written request, including by email, to terminate the Agreement) or by Vuzix. In no event, however, shall Vuzix make payments for any earned balance less than 20 USD.' => '6.3 Trong trường hợp Thỏa thuận bị chấm dứt, Vuzix sẽ thanh toán số dư đã tích lũy của bạn trong vòng khoảng chín mươi (90) ngày sau khi kết thúc tháng dương lịch mà trong đó Thỏa thuận bị chấm dứt bởi bạn (sau khi Vuzix nhận được yêu cầu chấm dứt Thỏa thuận bằng văn bản của bạn, bao gồm qua email) hoặc bởi Vuzix. Tuy nhiên, trong mọi trường hợp, Vuzix sẽ không thanh toán cho bất kỳ số dư đã tích lũy nào dưới 20 USD.',
		'6.4 If you are past due on any payment to Vuzix, Vuzix reserves the right to withhold payment until all outstanding payments have been made or to offset amounts owed to you in connection with the Program by amounts owed by you to Vuzix.' => '6.4 Nếu bạn chậm thanh toán bất kỳ khoản nào cho Vuzix, Vuzix có quyền giữ lại khoản thanh toán cho đến khi tất cả các khoản còn nợ được thanh toán đầy đủ, hoặc bù trừ các khoản Vuzix nợ bạn liên quan đến Chương trình bằng các khoản bạn nợ Vuzix.',
		'6.5 To ensure proper payment, you are solely responsible for providing and maintaining accurate address and other contact information as well as payment information associated with your account. This information includes without limitation a valid address and tax identification number.' => '6.5 Để đảm bảo việc thanh toán được thực hiện chính xác, bạn hoàn toàn chịu trách nhiệm cung cấp và duy trì địa chỉ cùng các thông tin liên hệ khác chính xác, cũng như thông tin thanh toán gắn với tài khoản của bạn. Thông tin này bao gồm, nhưng không giới hạn ở, địa chỉ hợp lệ và mã số thuế.',
		'6.6 You are responsible for all of your expenses in connection with this Agreement, unless this Agreement expressly provides otherwise.' => '6.6 Bạn chịu trách nhiệm về tất cả các chi phí của mình liên quan đến Thỏa thuận này, trừ khi Thỏa thuận này quy định rõ khác.',
		'6.7 You agree to pay all applicable taxes or charges imposed by any government entity in connection with your participation in this Vuzix Publishers Distribution Agreement.' => '6.7 Bạn đồng ý thanh toán tất cả các loại thuế hoặc phí áp dụng do bất kỳ cơ quan chính phủ nào áp đặt liên quan đến việc bạn tham gia Thỏa thuận Phân phối cho Nhà phát hành Vuzix này.',
		'6.8 As a security measure, we may, but are not required to, impose transaction limits relating to the value of any transaction or disbursement, the cumulative value of all transactions or disbursements during a period of time, or the number of transactions per day or other period of time. We will not be liable to you: (i) if we do not proceed with a transaction or disbursement that would exceed any limit established by us for a security reason, or (ii) if we permit a customer to withdraw from a transaction because the Vuzix App or the Vuzix App Store are unavailable following the commencement of a transaction.' => '6.8 Là một biện pháp bảo mật, chúng tôi có thể, nhưng không bắt buộc, áp đặt các hạn mức giao dịch liên quan đến giá trị của bất kỳ giao dịch hoặc khoản chi trả nào, tổng giá trị của tất cả các giao dịch hoặc khoản chi trả trong một khoảng thời gian, hoặc số lượng giao dịch mỗi ngày hay trong một khoảng thời gian khác. Chúng tôi sẽ không chịu trách nhiệm đối với bạn: (i) nếu chúng tôi không thực hiện một giao dịch hoặc khoản chi trả vượt quá bất kỳ hạn mức nào chúng tôi đặt ra vì lý do bảo mật, hoặc (ii) nếu chúng tôi cho phép một khách hàng rút khỏi một giao dịch vì Vuzix App hoặc Vuzix App Store không khả dụng sau khi giao dịch đã bắt đầu.',
		'7. License Grants' => '7. Cấp phép',
		'7.1 Vuzix reserves the right to determine and control all aspects (including all functionality) of the Vuzix App.' => '7.1 Vuzix có quyền xác định và kiểm soát mọi khía cạnh (bao gồm toàn bộ chức năng) của Vuzix App.',
		'you may not and may not authorize any other party to do the following to or with the Vuzix App Store or other materials provided by Vuzix: (a) reverse engineer, decompile, or disassemble them; (b) modify or create derivative works based upon them in whole or in part; (c) distribute copies of them; (d) remove any proprietary notices or labels on them; or (e) resell, lease, rent, transfer, sublicense, or otherwise transfer rights to them. In addition to any other rights or remedies that we may have, any use in violation of this section will immediately terminate your right to use the Vuzix App Store.' => 'bạn không được và không được cho phép bất kỳ bên nào khác thực hiện các hành vi sau đối với hoặc liên quan đến Vuzix App Store hay các tài liệu khác do Vuzix cung cấp: (a) dịch ngược, giải mã hoặc phân tách chúng; (b) sửa đổi hoặc tạo ra các sản phẩm phái sinh dựa trên chúng, toàn bộ hoặc một phần; (c) phân phối các bản sao của chúng; (d) gỡ bỏ bất kỳ thông báo hoặc nhãn hiệu độc quyền nào trên chúng; hoặc (e) bán lại, cho thuê, chuyển nhượng, cấp phép lại hoặc chuyển giao quyền đối với chúng theo cách khác. Bên cạnh bất kỳ quyền hoặc biện pháp khắc phục khác mà chúng tôi có thể có, bất kỳ hành vi sử dụng vi phạm mục này sẽ ngay lập tức chấm dứt quyền sử dụng Vuzix App Store của bạn.',
		'You acknowledge and agree that Vuzix owns all right, title and interest in and to the Vuzix App Store, materials provided by Vuzix or Vuzix trademarks, and, except as explicitly included in this Agreement, you do not, by virtue of this Agreement or otherwise, acquire any ownership interest or rights in or to them. All licenses not expressly granted in this Agreement are reserved and no other licenses, immunity or rights, express or implied are granted by us, by implication, estoppels or otherwise.' => 'Bạn xác nhận và đồng ý rằng Vuzix sở hữu toàn bộ quyền, quyền sở hữu và lợi ích đối với Vuzix App Store, các tài liệu do Vuzix cung cấp hoặc các nhãn hiệu của Vuzix, và, trừ khi được quy định rõ ràng trong Thỏa thuận này, bạn không có được bất kỳ quyền lợi hoặc quyền sở hữu nào đối với chúng, dù thông qua Thỏa thuận này hay theo cách khác. Tất cả các quyền cấp phép không được cấp một cách rõ ràng trong Thỏa thuận này đều được bảo lưu, và không có bất kỳ quyền cấp phép, quyền miễn trừ hay quyền nào khác, dù rõ ràng hay ngụ ý, được chúng tôi cấp thông qua sự suy diễn, nguyên tắc estoppel hay cách khác.',
		'7.2 you grant to Vuzix a nonexclusive, and royalty-free license to distribute the Products on the Vuzix Site.' => '7.2 bạn cấp cho Vuzix một quyền cấp phép không độc quyền và miễn phí bản quyền để phân phối Sản phẩm trên Trang web Vuzix.',
		'7.3 You grant to Vuzix a nonexclusive, worldwide, and royalty-free license to: copy, perform, display, and use the Products for administrative and demonstration purposes.' => '7.3 Bạn cấp cho Vuzix một quyền cấp phép không độc quyền, trên toàn thế giới và miễn phí bản quyền để: sao chép, trình diễn, hiển thị và sử dụng Sản phẩm cho các mục đích quản trị và trình diễn minh họa.',
		'You may provide a EULA (“Publisher’s EULA”) with any Product if it complies with the requirements of, and is not inconsistent with, this Agreement. For any Product you submit to the Vuzix App Store, you agree that the provisions of the Vuzix App Store Terms of Use in regard of what we designate as Standard end user license terms (“Standard EULA Terms”) will apply to end users’ use of your Products. The Standard EULA Terms will specify, among other things, that you are the licensor of the Products and that we are not parties to your EULA. If there are any conflicts between the Standard EULA Terms and the Publisher’s EULA, then to the extent of such conflict the Standard EULA Terms will control. We do not have any responsibility or liability related to compliance or non-compliance by you or any end user under a Publisher’s EULA or the Standard EULA Terms.' => 'Bạn có thể cung cấp một EULA ("EULA của Nhà phát hành") kèm theo bất kỳ Sản phẩm nào nếu EULA đó tuân thủ các yêu cầu của, và không mâu thuẫn với, Thỏa thuận này. Đối với mọi Sản phẩm bạn gửi lên Vuzix App Store, bạn đồng ý rằng các quy định trong Điều khoản Sử dụng của Vuzix App Store liên quan đến những gì chúng tôi gọi là điều khoản cấp phép người dùng cuối chuẩn ("Điều khoản EULA Chuẩn") sẽ áp dụng cho việc người dùng cuối sử dụng Sản phẩm của bạn. Điều khoản EULA Chuẩn sẽ quy định, trong số những nội dung khác, rằng bạn là bên cấp phép của Sản phẩm và chúng tôi không phải là một bên trong EULA của bạn. Nếu có bất kỳ xung đột nào giữa Điều khoản EULA Chuẩn và EULA của Nhà phát hành, thì trong phạm vi xung đột đó, Điều khoản EULA Chuẩn sẽ được áp dụng. Chúng tôi không có bất kỳ trách nhiệm hay nghĩa vụ pháp lý nào liên quan đến việc bạn hoặc bất kỳ người dùng cuối nào tuân thủ hoặc không tuân thủ EULA của Nhà phát hành hay Điều khoản EULA Chuẩn.',
		'The Standard EULA includes the following end user license terms, and if the Product does not include a Publisher’s EULA, these terms will constitute the entire EULA between the Publisher and the end users:' => 'EULA Chuẩn bao gồm các điều khoản cấp phép người dùng cuối sau đây, và nếu Sản phẩm không kèm theo EULA của Nhà phát hành, các điều khoản này sẽ cấu thành toàn bộ EULA giữa Nhà phát hành và người dùng cuối:',
		'The Publisher is the licensor of the Product.' => 'Nhà phát hành là bên cấp phép của Sản phẩm.',
		'If the Product does not include a Publisher’s EULA that specifies license rights, Publisher grants the end user a limited, nontransferable license to download and use the Product only for personal and noncommercial purposes.' => 'Nếu Sản phẩm không kèm theo EULA của Nhà phát hành quy định các quyền cấp phép, Nhà phát hành cấp cho người dùng cuối một quyền cấp phép hạn chế, không thể chuyển nhượng, để tải xuống và sử dụng Sản phẩm chỉ cho mục đích cá nhân và không nhằm mục đích thương mại.',
		'You may not modify, reverse engineer, decompile or disassemble the Product in whole or in part, or create any derivative works from or sublicense any rights in the Product, unless otherwise expressly authorized in writing by Publisher.' => 'Bạn không được sửa đổi, dịch ngược, giải mã hoặc phân tách Sản phẩm toàn bộ hoặc một phần, hoặc tạo ra bất kỳ sản phẩm phái sinh nào từ Sản phẩm hay cấp phép lại bất kỳ quyền nào đối với Sản phẩm, trừ khi được Nhà phát hành cho phép rõ ràng bằng văn bản.',
		'The Product is protected by copyright and other intellectual property laws and treaties. Unless otherwise expressly stated in the Publisher’s EULA, Publisher or its licensors own all title, copyright and other intellectual property rights in the Product, and the Product is licensed, not sold.' => 'Sản phẩm được bảo vệ bởi luật bản quyền và các luật, hiệp ước về sở hữu trí tuệ khác. Trừ khi được quy định rõ ràng khác trong EULA của Nhà phát hành, Nhà phát hành hoặc bên cấp phép của họ sở hữu toàn bộ quyền sở hữu, bản quyền và các quyền sở hữu trí tuệ khác đối với Sản phẩm, và Sản phẩm được cấp phép sử dụng, không phải được bán.',
		'The end user acknowledges and agrees that Vuzix has no responsibility or liability with respect to your use of the Product or any content or functionality in the Product.' => 'Người dùng cuối xác nhận và đồng ý rằng Vuzix không có trách nhiệm hay nghĩa vụ pháp lý nào liên quan đến việc bạn sử dụng Sản phẩm hoặc bất kỳ nội dung hay chức năng nào trong Sản phẩm.',
		'7.5 You represent and warrant that you have all intellectual property rights, including all necessary patent, trademark, trade secret, copyright or other proprietary rights, in and to the Product. If you use third-party materials, you represent and warrant that you have the right to distribute the third-party material in the Product. You agree that you will not submit material to Vuzix App Store that is copyrighted, protected by trade secret or otherwise subject to third party proprietary rights, including patent, privacy and publicity rights, unless you are the owner of such rights or have permission from their rightful owner to submit the material.' => '7.5 Bạn tuyên bố và bảo đảm rằng bạn có đầy đủ các quyền sở hữu trí tuệ, bao gồm tất cả các quyền cần thiết về sáng chế, nhãn hiệu, bí mật kinh doanh, bản quyền hoặc các quyền sở hữu khác, đối với Sản phẩm. Nếu bạn sử dụng tài liệu của bên thứ ba, bạn tuyên bố và bảo đảm rằng bạn có quyền phân phối tài liệu của bên thứ ba đó trong Sản phẩm. Bạn đồng ý sẽ không gửi lên Vuzix App Store bất kỳ tài liệu nào có bản quyền, được bảo vệ bởi bí mật kinh doanh hoặc thuộc quyền sở hữu của bên thứ ba, bao gồm quyền sáng chế, quyền riêng tư và quyền hình ảnh cá nhân, trừ khi bạn là chủ sở hữu các quyền đó hoặc có sự cho phép từ chủ sở hữu hợp pháp để gửi tài liệu đó.',
		'7.6 Vuzix grants you a non-exclusive, non-transferable, non-assignable, revocable right and license during the term of this Agreement to use the Vuzix marks solely in connection with your use of the Services for which the Vuzix marks were provided to you. You must use the Vuzix marks solely in the manner in which they were provided to you, meaning you may not change, alter, amend, vary, or modify the Vuzix marks in any way, at any time. You may not use any Vuzix mark except as expressly provided herein and may not sublicense these rights or otherwise permit any party to use the Vuzix marks. You acknowledge that Vuzix is the sole owner of the Vuzix marks, and you agree to do nothing inconsistent with that ownership. All goodwill arising out of your use of the Vuzix marks will inure to the sole benefit of Vuzix. Vuzix may revoke your license to any or all of the Vuzix marks at any time in its sole discretion. Upon the termination this Agreement, or termination or suspension of the Services for which any Vuzix mark was provided to you, you shall immediately cease and discontinue all further use of the Vuzix mark.' => '7.6 Vuzix cấp cho bạn một quyền và giấy phép không độc quyền, không thể chuyển nhượng, không thể ủy quyền, có thể thu hồi trong thời hạn của Thỏa thuận này để sử dụng các nhãn hiệu của Vuzix chỉ liên quan đến việc bạn sử dụng các Dịch vụ mà các nhãn hiệu Vuzix đó được cung cấp cho bạn. Bạn phải sử dụng các nhãn hiệu Vuzix đúng theo cách mà chúng được cung cấp cho bạn, nghĩa là bạn không được thay đổi, chỉnh sửa, bổ sung hay điều chỉnh các nhãn hiệu Vuzix theo bất kỳ cách nào, vào bất kỳ thời điểm nào. Bạn không được sử dụng bất kỳ nhãn hiệu Vuzix nào ngoài phạm vi được quy định rõ ràng trong tài liệu này, và không được cấp phép lại các quyền này hay cho phép bất kỳ bên nào khác sử dụng các nhãn hiệu Vuzix. Bạn xác nhận rằng Vuzix là chủ sở hữu duy nhất của các nhãn hiệu Vuzix, và bạn đồng ý không thực hiện bất kỳ hành vi nào trái với quyền sở hữu đó. Toàn bộ giá trị uy tín (goodwill) phát sinh từ việc bạn sử dụng các nhãn hiệu Vuzix sẽ thuộc về lợi ích duy nhất của Vuzix. Vuzix có thể thu hồi giấy phép sử dụng bất kỳ hoặc toàn bộ nhãn hiệu Vuzix của bạn vào bất kỳ thời điểm nào theo quyết định riêng của mình. Khi Thỏa thuận này chấm dứt, hoặc khi các Dịch vụ mà nhãn hiệu Vuzix được cung cấp cho bạn bị chấm dứt hoặc tạm ngừng, bạn phải ngay lập tức chấm dứt và ngừng mọi việc sử dụng thêm nhãn hiệu Vuzix đó.',
		'8. Publicity' => '8. Truyền thông',
		'8.1 you agree that Vuzix may use your name and logo in presentations, marketing materials, customer lists, financial reports, Web site listings of customers and Referral Pages.' => '8.1 bạn đồng ý rằng Vuzix có thể sử dụng tên và logo của bạn trong các bài thuyết trình, tài liệu marketing, danh sách khách hàng, báo cáo tài chính, danh mục khách hàng trên trang web và các Trang Giới thiệu.',
		'9. Product Takedowns.' => '9. Gỡ bỏ Sản phẩm.',
		'Your Takedowns.' => 'Việc Gỡ bỏ của Bạn.',
		'You may remove your Products from future distribution via the Vuzix App Store at any time, but you must comply with this Agreement for any Products distributed through the Vuzix App Store, including but not limited to refund requirements. Removing your Products from future distribution via the Vuzix App Store does not (a) affect the license rights of users who have previously purchased or downloaded your Products, (b) remove your Products from Devices or from any part of the Vuzix App Store where previously purchased or downloaded applications are stored on behalf of users, or (c) change your obligation to deliver or support Products or services that have been previously purchased or downloaded by users. Notwithstanding the foregoing, in no event will Vuzix maintain on any portion of the Vuzix App Store (including, without limitation, the part of the Vuzix App Store where previously purchased or downloaded applications are stored on behalf of users) any paid Product that you have removed from the Vuzix App Store and provided written notice to Vuzix that such removal was due to (i) an allegation of infringement, or actual infringement, of any copyright, trademark, trade secret, trade dress, patent or other intellectual property right of any person, (ii) an allegation of defamation or actual defamation, (iii) an allegation of violation, or actual violation, of any third party\'s right of publicity or privacy, or (iv) an allegation or determination that such Product does not comply with applicable law.' => 'Bạn có thể gỡ bỏ Sản phẩm của mình khỏi việc phân phối trong tương lai qua Vuzix App Store vào bất kỳ thời điểm nào, nhưng bạn phải tuân thủ Thỏa thuận này đối với mọi Sản phẩm đã được phân phối qua Vuzix App Store, bao gồm nhưng không giới hạn ở các yêu cầu về hoàn tiền. Việc gỡ bỏ Sản phẩm của bạn khỏi việc phân phối trong tương lai qua Vuzix App Store không (a) ảnh hưởng đến quyền cấp phép của những người dùng đã mua hoặc tải xuống Sản phẩm của bạn trước đó, (b) gỡ bỏ Sản phẩm của bạn khỏi các Thiết bị hoặc khỏi bất kỳ phần nào của Vuzix App Store nơi các ứng dụng đã mua hoặc tải xuống trước đó được lưu trữ cho người dùng, hoặc (c) thay đổi nghĩa vụ của bạn trong việc cung cấp hoặc hỗ trợ các Sản phẩm hay dịch vụ mà người dùng đã mua hoặc tải xuống trước đó. Mặc dù có quy định trên, trong mọi trường hợp, Vuzix sẽ không duy trì trên bất kỳ phần nào của Vuzix App Store (bao gồm, nhưng không giới hạn ở, phần của Vuzix App Store nơi các ứng dụng đã mua hoặc tải xuống trước đó được lưu trữ cho người dùng) bất kỳ Sản phẩm có phí nào mà bạn đã gỡ bỏ khỏi Vuzix App Store và đã gửi thông báo bằng văn bản cho Vuzix rằng việc gỡ bỏ đó là do (i) cáo buộc vi phạm, hoặc vi phạm thực tế, đối với bất kỳ quyền bản quyền, nhãn hiệu, bí mật kinh doanh, kiểu dáng thương mại, sáng chế hoặc quyền sở hữu trí tuệ khác của bất kỳ cá nhân nào, (ii) cáo buộc bôi nhọ hoặc hành vi bôi nhọ thực tế, (iii) cáo buộc vi phạm, hoặc vi phạm thực tế, quyền hình ảnh cá nhân hoặc quyền riêng tư của bất kỳ bên thứ ba nào, hoặc (iv) cáo buộc hoặc xác định rằng Sản phẩm đó không tuân thủ luật áp dụng.',
		'Vuzix Takedowns.' => 'Việc Gỡ bỏ của Vuzix.',
		'If Vuzix is notified by you or otherwise becomes aware that a Product or any portion thereof; (a) violates the intellectual property rights or any other rights of any third party; (b) violates any applicable law or is subject to an injunction; (c) violates Vuzix’ policies or other terms of service as may be updated by Vuzix from time to time in its sole discretion; (d) is being distributed by you improperly; (e) may create liability for Vuzix ; (f) is deemed by Vuzix to have a virus or is deemed to be malware, spyware or have an adverse impact on Vuzix’ infrastructure; (g) violates the terms of this Agreement; (h) violates the terms of one or more Partner Program Policies of any Vuzix Partner and after being notified of the violation fails to remedy the violation within thirty (30) days of the notice; or (i) the display of the Product is impacting the integrity of Vuzix servers (i.e., users are unable to access such content or otherwise experience difficulty), Vuzix may remove the Product from the Vuzix App Store.' => 'Nếu Vuzix được bạn thông báo hoặc bằng cách khác biết được rằng một Sản phẩm hoặc bất kỳ phần nào của Sản phẩm đó: (a) vi phạm quyền sở hữu trí tuệ hoặc bất kỳ quyền nào khác của bên thứ ba; (b) vi phạm bất kỳ luật áp dụng nào hoặc đang chịu một lệnh cấm của tòa án; (c) vi phạm các chính sách của Vuzix hoặc các điều khoản dịch vụ khác có thể được Vuzix cập nhật theo từng thời điểm theo quyết định riêng của Vuzix; (d) đang được bạn phân phối không đúng cách; (e) có thể tạo ra trách nhiệm pháp lý cho Vuzix; (f) bị Vuzix cho là có virus hoặc bị xem là mã độc, phần mềm gián điệp hoặc có tác động tiêu cực đến hạ tầng của Vuzix; (g) vi phạm các điều khoản của Thỏa thuận này; (h) vi phạm các điều khoản của một hoặc nhiều Chính sách Chương trình Đối tác của bất kỳ Đối tác Vuzix nào và sau khi được thông báo về vi phạm đó vẫn không khắc phục vi phạm trong vòng ba mươi (30) ngày kể từ ngày thông báo; hoặc (i) việc hiển thị Sản phẩm đang ảnh hưởng đến tính toàn vẹn của các máy chủ Vuzix (nghĩa là người dùng không thể truy cập nội dung đó hoặc gặp khó khăn khi truy cập), Vuzix có thể gỡ bỏ Sản phẩm khỏi Vuzix App Store.',
		'In the event that your Product is involuntarily removed because it is defective, malicious, infringes intellectual property rights of another person, defames, violates a third party\'s right of publicity or privacy, or does not comply with applicable law, and an end user purchased such Product within a year before the date of takedown,: (i) you must refund to Vuzix, all amounts received, plus any associated fees (i.e. chargebacks and payment transaction fees), and (ii) Vuzix may, at its sole discretion, withhold from your future sales the amount in subsection (i) above.' => 'Trong trường hợp Sản phẩm của bạn bị gỡ bỏ ngoài ý muốn của bạn vì Sản phẩm đó bị lỗi, có tính chất độc hại, vi phạm quyền sở hữu trí tuệ của người khác, mang tính bôi nhọ, vi phạm quyền hình ảnh cá nhân hoặc quyền riêng tư của bên thứ ba, hoặc không tuân thủ luật áp dụng, và một người dùng cuối đã mua Sản phẩm đó trong vòng một năm trước ngày gỡ bỏ: (i) bạn phải hoàn trả cho Vuzix toàn bộ số tiền đã nhận, cộng với bất kỳ khoản phí liên quan (ví dụ: phí hoàn trả (chargeback) và phí giao dịch thanh toán), và (ii) Vuzix có thể, theo quyết định riêng của mình, giữ lại từ các khoản doanh thu bán hàng trong tương lai của bạn số tiền được nêu tại mục (i) nói trên.',
		'DMCA Notices.' => 'Thông báo DMCA.',
		'If Vuzix receives a Notice according to the US Digital Millennium Copyright Act (DMCA), Vuzix will immediately remove the Product in question. Such notices shall be directed to Vuzix’ DMCA Agent at abusereport@Vuzix.com.' => 'Nếu Vuzix nhận được một Thông báo theo Đạo luật Bản quyền Kỹ thuật số Thiên niên kỷ Hoa Kỳ (DMCA), Vuzix sẽ ngay lập tức gỡ bỏ Sản phẩm liên quan. Các thông báo này phải được gửi đến Đại diện DMCA của Vuzix theo địa chỉ abusereport@Vuzix.com.',
		'Repeat Infringers Policy.' => 'Chính sách Xử lý Vi phạm Lặp lại.',
		'If Vuzix verifies or is warned of repeat infringement by a Publisher of (a) the intellectual property rights or any other rights of any third party; (b) any applicable law or this Agreement; (c) Vuzix’ policies or other terms of service as may be updated by Vuzix from time to time in its sole discretion; or repeat upload of Products (d) containing virus or malware, spyware or which have an adverse impact on Vuzix’ infrastructure; (e) impacting the integrity of Vuzix servers (i.e., users are unable to access such content or otherwise experience difficulty), Vuzix will terminate the Publisher’s User Account and remove all of such Publisher’s Products from the Vuzix App Store.' => 'Nếu Vuzix xác minh hoặc được cảnh báo về việc một Nhà phát hành vi phạm lặp lại (a) quyền sở hữu trí tuệ hoặc bất kỳ quyền nào khác của bên thứ ba; (b) bất kỳ luật áp dụng nào hoặc Thỏa thuận này; (c) các chính sách của Vuzix hoặc các điều khoản dịch vụ khác có thể được Vuzix cập nhật theo từng thời điểm theo quyết định riêng của Vuzix; hoặc việc tải lên lặp lại các Sản phẩm (d) có chứa virus hoặc mã độc, phần mềm gián điệp hoặc có tác động tiêu cực đến hạ tầng của Vuzix; (e) ảnh hưởng đến tính toàn vẹn của các máy chủ Vuzix (nghĩa là người dùng không thể truy cập nội dung đó hoặc gặp khó khăn khi truy cập), Vuzix sẽ chấm dứt Tài khoản Người dùng của Nhà phát hành đó và gỡ bỏ tất cả Sản phẩm của Nhà phát hành đó khỏi Vuzix App Store.',
		'10. Your Partner Credentials' => '10. Thông tin Đăng nhập Đối tác của Bạn',
		'10.1 You agree that you are responsible for maintaining the confidentiality of any credentials that may be issued to you by Vuzix or which you may choose yourself and that you will be solely responsible for all applications that are published under your credentials.' => '10.1 Bạn đồng ý rằng bạn có trách nhiệm duy trì tính bảo mật của bất kỳ thông tin đăng nhập nào được Vuzix cấp cho bạn hoặc do bạn tự chọn, và bạn sẽ hoàn toàn chịu trách nhiệm đối với tất cả các ứng dụng được phát hành dưới thông tin đăng nhập của bạn.',
		'11. Privacy and Information' => '11. Quyền riêng tư và Thông tin',
		'11.1 In order to continually innovate and improve the Vuzix App Store, Vuzix may collect certain usage statistics from the Vuzix App Store and Devices, including but not limited to, information on how the Vuzix App Store and Devices are being used.' => '11.1 Để liên tục đổi mới và cải thiện Vuzix App Store, Vuzix có thể thu thập một số thống kê sử dụng từ Vuzix App Store và các Thiết bị, bao gồm nhưng không giới hạn ở, thông tin về cách Vuzix App Store và các Thiết bị được sử dụng.',
		'11.2 The data collected is examined in the aggregate to improve the Vuzix App Store for users and Partners and is maintained in accordance with Vuzix’ Privacy Policy. To ensure the improvement of Products, limited aggregate data may be available to you.' => '11.2 Dữ liệu được thu thập sẽ được xem xét dưới dạng tổng hợp nhằm cải thiện Vuzix App Store cho người dùng và Đối tác, và được lưu giữ theo Chính sách Quyền riêng tư của Vuzix. Để đảm bảo việc cải thiện Sản phẩm, một số dữ liệu tổng hợp có giới hạn có thể được cung cấp cho bạn.',
		'12. Terminating this Agreement' => '12. Chấm dứt Thỏa thuận này',
		'12.1 This Agreement will continue to apply until terminated by either you or Vuzix as set out below.' => '12.1 Thỏa thuận này sẽ tiếp tục có hiệu lực cho đến khi bị chấm dứt bởi bạn hoặc Vuzix theo quy định dưới đây.',
		'12.2 If you want to terminate this Agreement, you must provide Vuzix with thirty (30) days prior written notice and cease your use of any relevant credentials.' => '12.2 Nếu bạn muốn chấm dứt Thỏa thuận này, bạn phải gửi cho Vuzix thông báo bằng văn bản trước ba mươi (30) ngày và ngừng sử dụng bất kỳ thông tin đăng nhập liên quan nào.',
		'12.3 Vuzix may at any time, terminate this Agreement with you if:' => '12.3 Vuzix có thể chấm dứt Thỏa thuận này với bạn vào bất kỳ thời điểm nào nếu:',
		'You have breached any provision of this Agreement; or' => 'Bạn đã vi phạm bất kỳ quy định nào của Thỏa thuận này; hoặc',
		'Vuzix is required to do so by law; or' => 'Vuzix bị yêu cầu phải làm như vậy theo luật pháp; hoặc',
		'Vuzix decides to no longer provide the Store or Vuzix App Store.' => 'Vuzix quyết định không còn cung cấp Cửa hàng hoặc Vuzix App Store nữa.',
		'12.4 Upon termination, all rights and obligations of the parties under this Agreement will terminate, except that Sections 8 to 18 will survive termination.' => '12.4 Khi chấm dứt, tất cả các quyền và nghĩa vụ của các bên theo Thỏa thuận này sẽ chấm dứt, ngoại trừ các Mục 8 đến 18 vẫn tiếp tục có hiệu lực sau khi chấm dứt.',
		'13. DISCLAIMER OF WARRANTIES' => '13. TUYÊN BỐ MIỄN TRỪ BẢO ĐẢM',
		'13.1 YOU EXPRESSLY UNDERSTAND AND AGREE THAT THE USE OF THE VUZIX APP STORE ARE AT YOUR SOLE RISK AND THAT THEY ARE PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTY OF ANY KIND.' => '13.1 BẠN HIỂU RÕ VÀ ĐỒNG Ý RẰNG VIỆC SỬ DỤNG VUZIX APP STORE HOÀN TOÀN DO BẠN CHỊU RỦI RO VÀ VUZIX APP STORE ĐƯỢC CUNG CẤP "NGUYÊN TRẠNG" ("AS IS") VÀ "TRONG PHẠM VI SẴN CÓ" ("AS AVAILABLE") MÀ KHÔNG CÓ BẤT KỲ BẢO ĐẢM NÀO.',
		'13.2 YOUR USE OF THE VUZIX APP STORE IS AT YOUR OWN DISCRETION AND RISK AND YOU ARE SOLELY RESPONSIBLE FOR ANY DAMAGE TO ANY COMPUTER SYSTEM OR OTHER DEVICE OR LOSS OF DATA THAT RESULTS FROM SUCH USE.' => '13.2 VIỆC BẠN SỬ DỤNG VUZIX APP STORE LÀ DO BẠN TỰ QUYẾT ĐỊNH VÀ TỰ CHỊU RỦI RO, VÀ BẠN HOÀN TOÀN CHỊU TRÁCH NHIỆM ĐỐI VỚI BẤT KỲ THIỆT HẠI NÀO ĐỐI VỚI HỆ THỐNG MÁY TÍNH HOẶC THIẾT BỊ KHÁC HAY MẤT DỮ LIỆU PHÁT SINH TỪ VIỆC SỬ DỤNG ĐÓ.',
		'13.3 VUZIX FURTHER EXPRESSLY DISCLAIMS ALL WARRANTIES AND CONDITIONS OF ANY KIND, WHETHER EXPRESS OR IMPLIED, INCLUDING, BUT NOT LIMITED TO THE IMPLIED WARRANTIES AND CONDITIONS OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON-INFRINGEMENT.' => '13.3 VUZIX TIẾP TỤC TỪ CHỐI RÕ RÀNG TẤT CẢ CÁC BẢO ĐẢM VÀ ĐIỀU KIỆN THUỘC BẤT KỲ LOẠI NÀO, DÙ RÕ RÀNG HAY NGỤ Ý, BAO GỒM NHƯNG KHÔNG GIỚI HẠN Ở CÁC BẢO ĐẢM VÀ ĐIỀU KIỆN NGỤ Ý VỀ TÍNH THƯƠNG MẠI, SỰ PHÙ HỢP CHO MỘT MỤC ĐÍCH CỤ THỂ VÀ KHÔNG VI PHẠM QUYỀN CỦA BÊN THỨ BA.',
		'14. LIMITATION OF LIABILITY' => '14. GIỚI HẠN TRÁCH NHIỆM',
		'14.1 CONSIDERING THE ABSENCE OF WARRANTIES DESCRIBED ABOVE, YOU EXPRESSLY UNDERSTAND AND AGREE THAT VUZIX, ITS SUBSIDIARIES AND AFFILIATES, AND ITS LICENSORS SHALL NOT BE LIABLE TO YOU UNDER ANY THEORY OF LIABILITY FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL CONSEQUENTIAL OR EXEMPLARY DAMAGES THAT MAY BE INCURRED BY YOU, INCLUDING ANY LOSS OF DATA, WHETHER OR NOT VUZIX OR ITS REPRESENTATIVES HAVE BEEN ADVISED OF OR SHOULD HAVE BEEN AWARE OF THE POSSIBILITY OF ANY SUCH LOSSES ARISING. FURTHER, OUR AGGREGATE LIABILITY ARISING OUT OR IN CONNECTION WITH THIS AGREEMENT OR THE TRANSACTIONS CONTEMPLATED WILL NOT EXCEED AT ANY TIME THE TOTAL AMOUNTS DURING THE PRIOR SIX MONTH PERIOD PAID BY YOU TO VUZIX IN CONNECTION WITH THE PARTICULAR SERVICE GIVING RISE TO THE CLAIM.' => '14.1 XÉT ĐẾN VIỆC KHÔNG CÓ CÁC BẢO ĐẢM ĐƯỢC MÔ TẢ Ở TRÊN, BẠN HIỂU RÕ VÀ ĐỒNG Ý RẰNG VUZIX, CÁC CÔNG TY CON VÀ CÔNG TY LIÊN KẾT CỦA VUZIX, CÙNG CÁC BÊN CẤP PHÉP CỦA VUZIX SẼ KHÔNG CHỊU TRÁCH NHIỆM ĐỐI VỚI BẠN THEO BẤT KỲ CƠ SỞ TRÁCH NHIỆM PHÁP LÝ NÀO ĐỐI VỚI BẤT KỲ THIỆT HẠI TRỰC TIẾP, GIÁN TIẾP, NGẪU NHIÊN, ĐẶC BIỆT, HỆ QUẢ HOẶC MANG TÍNH TRỪNG PHẠT MÀ BẠN CÓ THỂ PHẢI GÁNH CHỊU, BAO GỒM BẤT KỲ TỔN THẤT DỮ LIỆU NÀO, CHO DÙ VUZIX HAY ĐẠI DIỆN CỦA VUZIX CÓ ĐƯỢC THÔNG BÁO HOẶC LẼ RA PHẢI BIẾT VỀ KHẢ NĂNG XẢY RA CÁC TỔN THẤT ĐÓ HAY KHÔNG. HƠN NỮA, TỔNG TRÁCH NHIỆM CỦA CHÚNG TÔI PHÁT SINH TỪ HOẶC LIÊN QUAN ĐẾN THỎA THUẬN NÀY HAY CÁC GIAO DỊCH ĐƯỢC DỰ KIẾN SẼ KHÔNG VƯỢT QUÁ, VÀO BẤT KỲ THỜI ĐIỂM NÀO, TỔNG SỐ TIỀN BẠN ĐÃ THANH TOÁN CHO VUZIX TRONG SÁU THÁNG TRƯỚC ĐÓ LIÊN QUAN ĐẾN DỊCH VỤ CỤ THỂ LÀM PHÁT SINH KHIẾU NẠI.',
		'15. Indemnification' => '15. Bồi thường',
		'15.1 To the maximum extent permitted by law, you agree to defend, indemnify and hold harmless Vuzix, its affiliates and their respective directors, officers, employees and agents from and against any and all third party claims, actions, suits or proceedings, as well as any and all losses, liabilities, damages, costs and expenses (including reasonable attorney’s fees) arising out of or accruing from (a) your use of the Vuzix App Store in violation of this Agreement, (b) your Product that infringes any copyright, trademark, trade secret, trade dress, patent or other intellectual property right of any person or defames any person or violates their rights of publicity or privacy and (c) any and all taxes due by you as a result of any sales, advertising or any other activity conducted through the Vuzix App Store.' => '15.1 Trong phạm vi tối đa được pháp luật cho phép, bạn đồng ý bảo vệ, bồi thường và giữ cho Vuzix, các công ty liên kết của Vuzix và các giám đốc, cán bộ, nhân viên, đại diện tương ứng của các bên đó không bị tổn hại từ và trước bất kỳ và toàn bộ khiếu nại, hành động, kiện tụng hoặc tố tụng của bên thứ ba, cũng như bất kỳ và toàn bộ tổn thất, trách nhiệm pháp lý, thiệt hại, chi phí và phí tổn (bao gồm phí luật sư hợp lý) phát sinh từ hoặc liên quan đến (a) việc bạn sử dụng Vuzix App Store vi phạm Thỏa thuận này, (b) Sản phẩm của bạn vi phạm bất kỳ quyền bản quyền, nhãn hiệu, bí mật kinh doanh, kiểu dáng thương mại, sáng chế hoặc quyền sở hữu trí tuệ khác của bất kỳ cá nhân nào, hoặc bôi nhọ bất kỳ cá nhân nào, hoặc vi phạm quyền hình ảnh cá nhân hay quyền riêng tư của họ, và (c) bất kỳ và toàn bộ các loại thuế mà bạn phải nộp do kết quả của việc bán hàng, quảng cáo hoặc bất kỳ hoạt động khác được thực hiện qua Vuzix App Store.',
		'16. Changes to the Agreement' => '16. Thay đổi Thỏa thuận',
		'16.1 Vuzix may make changes to this Agreement at any time by sending you notice by email describing the modifications made. Vuzix will also post a notification on the Vuzix App Store site describing the modifications made. The changes will become effective, and will be deemed accepted by you, (a) immediately for those who submit Products for distribution after the notification is posted, or (b) for pre-existing users, the modified Agreement will become effective upon your acceptance of the modified Agreement (except changes required by law which will be effective immediately). You will show your acceptance of the modified Agreement by going to the Vuzix App Store site and accepting the modified Agreement. Your continued use of the Services after Vuzix’ posting of any changes will constitute your acceptance of such changes or modifications. In the event you do not agree with the modifications to the Agreement within thirty (30) days after the date the email is sent, then Vuzix may suspend the Store and distribution of your Products you agree to the modified Agreement. In the event that you do not agree with the modifications within ninety (90) days after the date the email is sent, then you must terminate your use of the Vuzix App Store, which will be your sole and exclusive remedy.' => '16.1 Vuzix có thể thực hiện các thay đổi đối với Thỏa thuận này vào bất kỳ thời điểm nào bằng cách gửi cho bạn thông báo qua email mô tả các sửa đổi đã thực hiện. Vuzix cũng sẽ đăng một thông báo trên trang Vuzix App Store mô tả các sửa đổi đã thực hiện. Các thay đổi sẽ có hiệu lực, và được coi là đã được bạn chấp nhận, (a) ngay lập tức đối với những người gửi Sản phẩm để phân phối sau khi thông báo được đăng, hoặc (b) đối với người dùng đã có từ trước, Thỏa thuận đã sửa đổi sẽ có hiệu lực khi bạn chấp nhận Thỏa thuận đã sửa đổi đó (trừ các thay đổi theo yêu cầu của pháp luật sẽ có hiệu lực ngay lập tức). Bạn sẽ thể hiện sự chấp nhận đối với Thỏa thuận đã sửa đổi bằng cách truy cập trang Vuzix App Store và chấp nhận Thỏa thuận đã sửa đổi. Việc bạn tiếp tục sử dụng Dịch vụ sau khi Vuzix đăng bất kỳ thay đổi nào sẽ cấu thành sự chấp nhận của bạn đối với các thay đổi hoặc sửa đổi đó. Trong trường hợp bạn không đồng ý với các sửa đổi của Thỏa thuận trong vòng ba mươi (30) ngày sau ngày email được gửi, Vuzix có thể tạm ngừng Cửa hàng và việc phân phối Sản phẩm của bạn cho đến khi bạn đồng ý với Thỏa thuận đã sửa đổi. Trong trường hợp bạn không đồng ý với các sửa đổi trong vòng chín mươi (90) ngày sau ngày email được gửi, bạn phải chấm dứt việc sử dụng Vuzix App Store, và đây sẽ là biện pháp khắc phục duy nhất và độc quyền của bạn.',
		'17. General Legal Terms' => '17. Các Điều khoản Pháp lý Chung',
		'17.1 This Agreement constitutes the whole legal agreement between you and Vuzix and governs your use of the Vuzix App Store, and completely replaces any prior agreements between you and Vuzix in relation to the Vuzix App Store.' => '17.1 Thỏa thuận này cấu thành toàn bộ thỏa thuận pháp lý giữa bạn và Vuzix và chi phối việc bạn sử dụng Vuzix App Store, và thay thế hoàn toàn mọi thỏa thuận trước đó giữa bạn và Vuzix liên quan đến Vuzix App Store.',
		'17.2 you agree that if Vuzix does not exercise or enforce any legal right or remedy which is contained in this Agreement (or which Vuzix has the benefit of under any applicable law), this will not be taken to be a formal waiver of Vuzix’ rights and that those rights or remedies will still be available to Vuzix.' => '17.2 bạn đồng ý rằng nếu Vuzix không thực hiện hoặc áp dụng bất kỳ quyền hay biện pháp khắc phục pháp lý nào được quy định trong Thỏa thuận này (hoặc mà Vuzix được hưởng theo luật áp dụng), điều này sẽ không được coi là sự từ bỏ chính thức các quyền của Vuzix, và các quyền hay biện pháp khắc phục đó vẫn sẽ có sẵn cho Vuzix.',
		'17.3 If any court of law, having the jurisdiction to decide on this matter, rules that any provision of this Agreement is invalid, then that provision will be removed from this Agreement without affecting the rest of this Agreement. The remaining provisions of this Agreement will continue to be valid and enforceable.' => '17.3 Nếu bất kỳ tòa án nào có thẩm quyền quyết định về vấn đề này ra phán quyết rằng một quy định nào đó của Thỏa thuận này không có hiệu lực, thì quy định đó sẽ bị loại bỏ khỏi Thỏa thuận này mà không ảnh hưởng đến phần còn lại của Thỏa thuận. Các quy định còn lại của Thỏa thuận này sẽ tiếp tục có hiệu lực và có thể thi hành.',
		'17.4 You and Vuzix are independent contractors, and nothing in this Agreement will create any partnership, joint venture, agency, franchise, sales representative, or employment relationship between us. You will have no authority to make or accept any offers or representations on our behalf. This Agreement will not create an exclusive relationship between you and us. Nothing expressed or mentioned in or implied from this Agreement is intended or shall be construed to give to any person other than the parties hereto any legal or equitable right, remedy, or claim under or in respect to this Agreement.' => '17.4 Bạn và Vuzix là các bên hợp đồng độc lập, và không có nội dung nào trong Thỏa thuận này tạo ra bất kỳ quan hệ hợp danh, liên doanh, đại lý, nhượng quyền thương mại, đại diện bán hàng hay quan hệ lao động giữa hai bên. Bạn sẽ không có thẩm quyền đưa ra hoặc chấp nhận bất kỳ đề nghị hay tuyên bố nào thay mặt cho chúng tôi. Thỏa thuận này sẽ không tạo ra một quan hệ độc quyền giữa bạn và chúng tôi. Không có nội dung nào được nêu, đề cập hoặc ngụ ý trong Thỏa thuận này nhằm mục đích hoặc được hiểu là trao cho bất kỳ cá nhân nào khác ngoài các bên của Thỏa thuận này bất kỳ quyền, biện pháp khắc phục hay khiếu nại pháp lý hoặc theo lẽ công bằng nào theo hoặc liên quan đến Thỏa thuận này.',
		'17.5 The rights granted in this Agreement may not be assigned or transferred by either you or Vuzix without the prior written approval of the other party. Neither you nor Vuzix shall be permitted to delegate their responsibilities or obligations under this Agreement without the prior written approval of the other party.' => '17.5 Các quyền được cấp trong Thỏa thuận này không được chuyển nhượng hoặc chuyển giao bởi bạn hoặc Vuzix mà không có sự chấp thuận trước bằng văn bản của bên còn lại. Cả bạn và Vuzix đều không được phép ủy quyền các trách nhiệm hoặc nghĩa vụ của mình theo Thỏa thuận này mà không có sự chấp thuận trước bằng văn bản của bên còn lại.',
		'17.6 This Agreement will be governed by the laws of New York State, USA. You consent to the exclusive jurisdiction and venue of the courts in New York State, USA.' => '17.6 Thỏa thuận này sẽ được điều chỉnh bởi pháp luật của Tiểu bang New York, Hoa Kỳ. Bạn đồng ý với thẩm quyền xét xử và địa điểm xét xử độc quyền của các tòa án tại Tiểu bang New York, Hoa Kỳ.',
		'Field Service' => 'Dịch vụ Hiện trường',
		'Make Every Field Tech An Expert' => 'Biến Mọi Kỹ thuật viên Hiện trường Thành Chuyên gia',
		'Expertise on Every Service Call' => 'Chuyên môn Cho Mọi Cuộc gọi Dịch vụ',
		'Vuzix smart glasses help field service organizations improve first-time fix rates, reduce downtime, and deliver faster customer support.' => 'Kính thông minh Vuzix giúp các tổ chức dịch vụ hiện trường cải thiện tỷ lệ sửa chữa thành công ngay lần đầu, giảm thời gian ngừng hoạt động và mang lại hỗ trợ khách hàng nhanh hơn.',
		'Designed for technicians working in complex and distributed environments, Vuzix devices provide hands-free access to technical documentation, equipment data, and guided workflows.' => 'Được thiết kế cho các kỹ thuật viên làm việc trong môi trường phức tạp và phân tán, các thiết bị Vuzix cung cấp khả năng truy cập rảnh tay vào tài liệu kỹ thuật, dữ liệu thiết bị và quy trình làm việc có hướng dẫn.',
		'This enables workers to diagnose and resolve issues more efficiently while remaining focused on the job.' => 'Điều này giúp người lao động chẩn đoán và xử lý sự cố hiệu quả hơn trong khi vẫn tập trung vào công việc.',
		'Remote collaboration tools allow technicians to connect instantly with subject matter experts, eliminating unnecessary travel and accelerating problem resolution. Real-time access to information and support helps improve service quality while reducing operational costs.' => 'Các công cụ hợp tác từ xa cho phép kỹ thuật viên kết nối ngay lập tức với các chuyên gia chuyên môn, loại bỏ nhu cầu di chuyển không cần thiết và đẩy nhanh việc xử lý sự cố. Khả năng truy cập thông tin và hỗ trợ theo thời gian thực giúp cải thiện chất lượng dịch vụ trong khi giảm chi phí vận hành.',
		'Real-time access to information and support helps improve service quality while reducing operational costs.' => 'Khả năng truy cập thông tin và hỗ trợ theo thời gian thực giúp cải thiện chất lượng dịch vụ trong khi giảm chi phí vận hành.',
		'Whether servicing industrial equipment, utilities, transportation systems, or critical infrastructure, we help field teams work more safely, efficiently, and confidently.' => 'Cho dù là bảo trì thiết bị công nghiệp, hệ thống tiện ích, hệ thống giao thông hay hạ tầng trọng yếu, chúng tôi giúp các nhóm hiện trường làm việc an toàn, hiệu quả và tự tin hơn.',
		'MEETING CHALLENGES' => 'ĐỐI MẶT VỚI THÁCH THỨC',
		'Transform field service with Vuzix' => 'Chuyển đổi dịch vụ hiện trường với Vuzix',
		'Average service cost reduction using AR' => 'Mức giảm chi phí dịch vụ trung bình khi sử dụng AR',
		'The field service industry is experiencing major upheavals as machines that need servicing are becoming more complex. Plus, an estimated 60% of the workforce is set to retire soon.' => 'Ngành dịch vụ hiện trường đang trải qua những biến động lớn khi các máy móc cần bảo trì ngày càng trở nên phức tạp hơn. Bên cạnh đó, ước tính khoảng 60% lực lượng lao động sắp nghỉ hưu.',
		'Vuzix augmented reality (AR) smart glasses help connect technicians in the field to AI databases and remote specialists, providing them with heads-up, hands-free support and training that improves overall productivity and safety.' => 'Kính thông minh thực tế tăng cường (AR) của Vuzix giúp kết nối kỹ thuật viên tại hiện trường với các cơ sở dữ liệu AI và các chuyên gia từ xa, cung cấp cho họ khả năng hỗ trợ và đào tạo rảnh tay, hiển thị trực diện, giúp cải thiện năng suất và độ an toàn tổng thể.',
		'Vuzix Solutions' => 'Giải pháp Vuzix',
		'Hands-free remote support for field technicians. Each kit comes pre-configured to work with Microsoft Teams™ and Zoom™' => 'Hỗ trợ từ xa rảnh tay cho kỹ thuật viên hiện trường. Mỗi bộ sản phẩm được cấu hình sẵn để hoạt động với Microsoft Teams™ và Zoom™',
		'Learn More →' => 'Tìm hiểu thêm →',
		'FIX IT FASTER' => 'SỬA CHỮA NHANH HƠN',
		'Benefits of AR in field service' => 'Lợi ích của AR trong dịch vụ hiện trường',
		'Reduce Costs with Faster Fix Rates' => 'Giảm Chi phí với Tỷ lệ Sửa chữa Nhanh hơn',
		'Improve resolution rates by putting key information in a worker’s field of view.' => 'Cải thiện tỷ lệ xử lý thành công bằng cách đưa thông tin quan trọng vào tầm nhìn của người lao động.',
		'Improve Compliance with Protocols' => 'Cải thiện Tuân thủ Quy trình',
		'Provide step-by-step instructions and training in the field to reduce error rates.' => 'Cung cấp hướng dẫn từng bước và đào tạo ngay tại hiện trường để giảm tỷ lệ sai sót.',
		'Accelerate Customer Response Times' => 'Tăng tốc Thời gian Phản hồi Khách hàng',
		'Reduce downtimes required for maintenance, repairs, and upgrades.' => 'Giảm thời gian ngừng hoạt động cần thiết cho việc bảo trì, sửa chữa và nâng cấp.',
		'Get the Right Experts on the Job' => 'Kết nối Đúng Chuyên gia cho Công việc',
		'Access experts remotely to gain technical support while minimizing travel costs.' => 'Truy cập chuyên gia từ xa để nhận hỗ trợ kỹ thuật trong khi giảm thiểu chi phí di chuyển.',
		'SITUATIONAL AWARENESS' => 'NHẬN THỨC TÌNH HUỐNG',
		'Vuzix M400 smart glasses for field service' => 'Kính thông minh Vuzix M400 cho dịch vụ hiện trường',
		'Vuzix M400 smart glasses help field service workers improve productivity by letting them access critical content with AR displays; document steps and resolve issues; and livestream remote support — all hands-free. Lightweight and durable, our field services smart glasses are designed to keep your frontline focused on the job at hand. Give them access to work instructions and support at a glance while they maintain perfect awareness of their surroundings.' => 'Kính thông minh Vuzix M400 giúp người lao động dịch vụ hiện trường cải thiện năng suất bằng cách cho phép họ truy cập nội dung quan trọng qua màn hình AR; ghi lại các bước thực hiện và xử lý sự cố; và livestream hỗ trợ từ xa — tất cả đều rảnh tay. Nhẹ và bền, kính thông minh dành cho dịch vụ hiện trường của chúng tôi được thiết kế để giúp đội ngũ tuyến đầu của bạn luôn tập trung vào công việc trước mắt. Hãy cho họ khả năng truy cập hướng dẫn công việc và hỗ trợ chỉ trong một cái nhìn, trong khi vẫn duy trì nhận thức đầy đủ về môi trường xung quanh.',
		'Explore Vuzix M400 →' => 'Khám phá Vuzix M400 →',
		'USES' => 'ỨNG DỤNG',
		'Field Service use cases' => 'Các trường hợp sử dụng Dịch vụ Hiện trường',
		'remote support' => 'hỗ trợ từ xa',
		'Real-time support for complex tasks' => 'Hỗ trợ theo thời gian thực cho các tác vụ phức tạp',
		'As technologies keep advancing, technicians are responsible for servicing a large range of products of increasing complexity. With Vuzix field services smart glasses, workers in the field can access task-based information directly in their line of vision— heads up and hands-free.' => 'Khi công nghệ không ngừng phát triển, các kỹ thuật viên phải chịu trách nhiệm bảo trì một phạm vi rộng các sản phẩm có độ phức tạp ngày càng tăng. Với kính thông minh dịch vụ hiện trường của Vuzix, người lao động tại hiện trường có thể truy cập thông tin theo từng tác vụ ngay trong tầm nhìn của họ — hiển thị trực diện và rảnh tay.',
		'And they can quickly connect to experts remotely, eliminating the need for costly travel and providing speedy resolution to tough issues.' => 'Và họ có thể nhanh chóng kết nối với các chuyên gia từ xa, loại bỏ nhu cầu di chuyển tốn kém và mang lại giải pháp nhanh chóng cho các sự cố khó khăn.',
		'Explore Remote Mentor →' => 'Khám phá Remote Mentor →',
		'Increased automation, fewer errors' => 'Tăng tự động hóa, giảm lỗi',
		'Many field technicians must still consult paper manuals and rely on manual documentation. This can increase workflow inefficiencies and error rates.' => 'Nhiều kỹ thuật viên hiện trường vẫn phải tham khảo tài liệu hướng dẫn bằng giấy và phụ thuộc vào việc ghi chép thủ công. Điều này có thể làm tăng sự kém hiệu quả trong quy trình làm việc và tỷ lệ sai sót.',
		'Vuzix AR glasses for field service enable easy audio and visual documentation. Best of all, they provide a hands-free, intuitive interface between your company’s digital content — detailed instructions, diagrams, and videos — and technicians in the field.' => 'Kính AR của Vuzix dành cho dịch vụ hiện trường giúp việc ghi lại tài liệu bằng âm thanh và hình ảnh trở nên dễ dàng. Đáng chú ý nhất, chúng cung cấp một giao diện rảnh tay, trực quan giữa nội dung số của công ty bạn — hướng dẫn chi tiết, sơ đồ và video — với các kỹ thuật viên tại hiện trường.',
		'Our Vuzix Mobilium Suite even provides a workbench to help you manage work distribution, monitor progress, and track exception handling.' => 'Bộ giải pháp Vuzix Mobilium Suite của chúng tôi còn cung cấp một không gian làm việc (workbench) giúp bạn quản lý việc phân công công việc, theo dõi tiến độ và giám sát việc xử lý các trường hợp ngoại lệ.',
		'Explore Software →' => 'Khám phá Phần mềm →',
		'Increased real-time training and guidance' => 'Tăng cường đào tạo và hướng dẫn theo thời gian thực',
		'It’s estimated that 25% of service calls require follow-up visits. This is often due to a lack of training, experience, and access to information. With Vuzix AR glasses for field service, training and courses can take place remotely, delivered directly to a single location or to multiple sites.' => 'Ước tính có khoảng 25% các cuộc gọi dịch vụ cần đến các lần ghé thăm tiếp theo. Điều này thường là do thiếu đào tạo, kinh nghiệm và khả năng truy cập thông tin. Với kính AR của Vuzix dành cho dịch vụ hiện trường, việc đào tạo và các khóa học có thể được thực hiện từ xa, chuyển giao trực tiếp đến một địa điểm duy nhất hoặc nhiều địa điểm cùng lúc.',
		'Technicians can receive on-the-job, interactive training and supervision simultaneously from instructors or senior personnel.' => 'Các kỹ thuật viên có thể nhận được đào tạo tương tác ngay tại nơi làm việc và sự giám sát đồng thời từ người hướng dẫn hoặc nhân viên cấp cao.',
		'Explore Manufacturing →' => 'Khám phá Sản xuất →',
		'DEPLOYMENT ASSISTANCE' => 'HỖ TRỢ TRIỂN KHAI',
		'Hands-free, hassle-free solutions for the smart workforce' => 'Giải pháp rảnh tay, không phiền phức cho lực lượng lao động thông minh',
		'Give your frontline AI-powered intelligence, real-time visibility, and rugged performance — without the IT overhead, complex integrations, or change-management headaches. We\'re with you at every step with hardware, software and support to ensure the success of your program.' => 'Trang bị cho đội ngũ tuyến đầu của bạn trí tuệ được hỗ trợ bởi AI, khả năng theo dõi theo thời gian thực và hiệu suất bền chắc — mà không cần gánh nặng về IT, tích hợp phức tạp hay những khó khăn trong quản lý thay đổi. Chúng tôi đồng hành cùng bạn trong từng bước với phần cứng, phần mềm và dịch vụ hỗ trợ để đảm bảo sự thành công cho chương trình của bạn.',
		'Explore Enterprise Solutions →' => 'Khám phá Giải pháp Doanh nghiệp →',
		'How do smart glasses actually help a field service technician?' => 'Kính thông minh thực sự giúp gì cho một kỹ thuật viên dịch vụ hiện trường?',
		'Vuzix smart glasses give field service technicians hands-free access to service instructions, equipment diagnostics, work orders, and live remote expert support — all delivered directly into the field of view while their hands remain on the equipment. Technicians can pull up wiring diagrams, follow step-by-step repair procedures, scan asset tags, capture photo or video documentation, and connect to remote experts through Microsoft Teams or Zoom without setting down tools or pulling out a phone or tablet. This typically improves first-time fix rates and reduces job duration.' => 'Kính thông minh Vuzix mang lại cho kỹ thuật viên dịch vụ hiện trường khả năng truy cập rảnh tay vào hướng dẫn dịch vụ, chẩn đoán thiết bị, lệnh công việc và hỗ trợ trực tiếp từ chuyên gia từ xa — tất cả được hiển thị ngay trong tầm nhìn khi tay họ vẫn đang làm việc với thiết bị. Kỹ thuật viên có thể mở sơ đồ đấu nối, làm theo quy trình sửa chữa từng bước, quét mã tài sản, chụp ảnh hoặc ghi hình tài liệu, và kết nối với các chuyên gia từ xa qua Microsoft Teams hoặc Zoom mà không cần đặt dụng cụ xuống hay lấy điện thoại, máy tính bảng ra. Điều này thường giúp cải thiện tỷ lệ sửa chữa thành công ngay lần đầu và giảm thời gian hoàn thành công việc.',
		'Which Vuzix smart glasses are best for field service work?' => 'Kính thông minh Vuzix nào phù hợp nhất cho công việc dịch vụ hiện trường?',
		'The Vuzix M400 is the most common choice for field service teams, with hot-swappable batteries supporting multi-call days, all-purpose operator assist capabilities across varied job sites, and durability for outdoor and industrial environments. The Vuzix Remote Assist Kit extends the M400 with Microsoft Teams and Zoom integration specifically for live remote expert support — useful when junior technicians need senior backup, when warranty inspections require manufacturer involvement, or when sites are too remote or secure for on-site escalation.' => 'Vuzix M400 là lựa chọn phổ biến nhất cho các đội dịch vụ hiện trường, với pin có thể thay nóng (hot-swappable) hỗ trợ những ngày làm việc với nhiều cuộc gọi, khả năng hỗ trợ vận hành đa năng trên nhiều địa điểm làm việc khác nhau, và độ bền cho môi trường ngoài trời và công nghiệp. Vuzix Remote Assist Kit mở rộng khả năng của M400 với tích hợp Microsoft Teams và Zoom dành riêng cho hỗ trợ trực tiếp từ chuyên gia từ xa — hữu ích khi kỹ thuật viên mới cần sự hỗ trợ từ người có kinh nghiệm hơn, khi kiểm tra bảo hành cần sự tham gia của nhà sản xuất, hoặc khi các địa điểm quá xa xôi hoặc yêu cầu bảo mật cao để có thể leo thang xử lý tại chỗ.',
		'What does it take to roll out smart glasses to a field service team?' => 'Cần những gì để triển khai kính thông minh cho một đội dịch vụ hiện trường?',
		'A typical field service deployment involves device provisioning through an MDM platform, integration with existing field service management or work order software, training technicians on hands-free interaction patterns, and defining remote expert workflows if remote assistance is part of the program. Vuzix smart glasses run Android, support common enterprise MDM platforms, and offer an open SDK for custom integration. For larger deployments, Vuzix works with system integrators to coordinate rollout, training, and ongoing support.' => 'Một quy trình triển khai dịch vụ hiện trường điển hình bao gồm việc cấp phát thiết bị qua nền tảng MDM, tích hợp với phần mềm quản lý dịch vụ hiện trường hoặc lệnh công việc hiện có, đào tạo kỹ thuật viên về các mẫu tương tác rảnh tay, và xác định quy trình làm việc với chuyên gia từ xa nếu hỗ trợ từ xa là một phần của chương trình. Kính thông minh Vuzix chạy trên Android, hỗ trợ các nền tảng MDM doanh nghiệp phổ biến, và cung cấp SDK mở để tích hợp tùy chỉnh. Đối với các triển khai quy mô lớn hơn, Vuzix hợp tác với các đơn vị tích hợp hệ thống để điều phối việc triển khai, đào tạo và hỗ trợ liên tục.',
		'Explore more' => 'Khám phá thêm',
		'Secure, rugged AR systems for mission-critical environments Waveguide-based AR solutions for situational awareness, training, and field operations.' => 'Hệ thống AR an toàn, bền chắc cho các môi trường trọng yếu. Các giải pháp AR dựa trên waveguide dành cho nhận thức tình huống, đào tạo và hoạt động hiện trường.',
		'See all Case Studies →' => 'Xem tất cả Case Study →',
		'Scalable AR for frontline workforces Smart glasses solutions for logistics, manufacturing, field service, and remote assistance.' => 'AR có khả năng mở rộng cho lực lượng lao động tuyến đầu. Các giải pháp kính thông minh dành cho logistics, sản xuất, dịch vụ hiện trường và hỗ trợ từ xa.',
		'See all White Papers →' => 'Xem tất cả White Paper →',
		'Vuzix History' => 'Lịch sử Vuzix',
		'30+ years in the making' => 'Hơn 30 năm hình thành và phát triển',
		'OUR HISTORY' => 'LỊCH SỬ CỦA CHÚNG TÔI',
		'A timeline of Vuzix innovation' => 'Dòng thời gian đổi mới của Vuzix',
		'From pioneering defense optics to setting the standard for enterprise smart glasses, Vuzix has spent nearly three decades advancing the future of wearable display technology. Each product throughout our history reflects a commitment to innovation, performance, and real-world usability across industries and applications.' => 'Từ việc đi đầu trong lĩnh vực quang học quốc phòng đến việc thiết lập chuẩn mực cho kính thông minh doanh nghiệp, Vuzix đã trải qua gần ba thập kỷ để thúc đẩy tương lai của công nghệ màn hình đeo được (wearable display). Mỗi sản phẩm trong suốt lịch sử của chúng tôi đều phản ánh sự cam kết đối với sự đổi mới, hiệu suất và tính khả dụng trong thực tế trên nhiều ngành và ứng dụng khác nhau.',
		'As augmented reality continues to transform how people work, communicate, and interact with information, Vuzix remains focused on building the optical foundation for the next generation of lightweight, powerful, and connected wearable technology.' => 'Khi thực tế tăng cường tiếp tục thay đổi cách con người làm việc, giao tiếp và tương tác với thông tin, Vuzix vẫn tập trung vào việc xây dựng nền tảng quang học cho thế hệ tiếp theo của công nghệ đeo được nhẹ, mạnh mẽ và kết nối.',
		'VR / Gaming / Personal Computing' => 'VR / Trò chơi / Máy tính cá nhân',
		'Military' => 'Quân sự',
		'Gaming / Personal Computing' => 'Trò chơi / Máy tính cá nhân',
		'iPod accessory / Personal Computing' => 'Phụ kiện iPod / Máy tính cá nhân',
		'Wrap series' => 'Dòng sản phẩm Wrap',
		'Vuzix M100 Smart Glasses' => 'Kính thông minh Vuzix M100',
		'Manufacturing / Operations' => 'Sản xuất / Vận hành',
		'Vuzix iWear Video Headphones' => 'Tai nghe Video Vuzix iWear',
		'Mobile wearable video display for gaming / personal computing' => 'Màn hình video đeo được di động cho trò chơi / máy tính cá nhân',
		'M300 Smart Glasses' => 'Kính thông minh M300',
		'Manufacturing / Operations / Logistics' => 'Sản xuất / Vận hành / Logistics',
		'Vuzix VidWear Smart Sunglasses' => 'Kính râm thông minh Vuzix VidWear',
		'Entertainment' => 'Giải trí',
		'Vuzix M300 Smart Glasses' => 'Kính thông minh Vuzix M300',
		'Manufacturing / Operations / Field Service' => 'Sản xuất / Vận hành / Dịch vụ Hiện trường',
		'Gaming / Entertainment / Field Service' => 'Trò chơi / Giải trí / Dịch vụ Hiện trường',
		'Swimming / Sports' => 'Bơi lội / Thể thao',
		'Manufacturing / Logistics / Operations / Field Service / Healthcare' => 'Sản xuất / Logistics / Vận hành / Dịch vụ Hiện trường / Y tế',
		'Vuzix Blade Upgraded' => 'Vuzix Blade Nâng cấp',
		'Manufacturing / Operations / Healthcare' => 'Sản xuất / Vận hành / Y tế',
		'Manufacturing / Operations / Field Service / Healthcare' => 'Sản xuất / Vận hành / Dịch vụ Hiện trường / Y tế',
		'Healthcare / Manufacturing / Operations' => 'Y tế / Sản xuất / Vận hành',
		'Manufacturing / Healthcare / Deaf &amp; hearing impaired' => 'Sản xuất / Y tế / Người khiếm thính',
		'Vuzix M400 Android 13 Upgrade' => 'Vuzix M400 Nâng cấp Android 13',
		'Consumer / Logistics' => 'Người tiêu dùng / Logistics',
		'Logistics / Field Service' => 'Logistics / Dịch vụ Hiện trường',
		'Vuzix Solutions Kits (Pick &amp; Pack, Remote Assist)' => 'Bộ Giải pháp Vuzix (Pick &amp; Pack, Remote Assist)',
		'Explore our latest smart glasses' => 'Khám phá kính thông minh mới nhất của chúng tôi',
		'Discover how Vuzix continues to shape the future of AI smart glasses.' => 'Tìm hiểu cách Vuzix tiếp tục định hình tương lai của kính thông minh AI.',
		'Explore Smart Glasses →' => 'Khám phá Kính thông minh →',
		'United States Manufacturing' => 'Sản xuất tại Hoa Kỳ',
		'U.S.-based waveguide manufacturing for' => 'Sản xuất waveguide tại Hoa Kỳ cho',
		'secure, compliant production' => 'sản xuất an toàn, tuân thủ quy định',
		'Talk to Our Waveguides Team →' => 'Liên hệ Đội ngũ Waveguide của chúng tôi →',
		'Enterprise clientbase' => 'Cơ sở khách hàng doanh nghiệp',
		'Annual waveguide capacity' => 'Công suất waveguide hàng năm',
		'Clean room specification' => 'Thông số phòng sạch',
		'Secure, high-volume production' => 'Sản xuất số lượng lớn, an toàn',
		'Vuzix operates a U.S.-based waveguide manufacturing facility in Rochester, New York, supporting high-volume production for OEM and defense programs.' => 'Vuzix vận hành một cơ sở sản xuất waveguide tại Hoa Kỳ ở Rochester, New York, hỗ trợ sản xuất số lượng lớn cho các chương trình OEM và quốc phòng.',
		'Manufacturing processes are tightly controlled to ensure consistent optical performance across production runs and program phases. Integrated on-site engineering and quality systems help maintain stability during scale-up and reduce variation between development and production units.' => 'Các quy trình sản xuất được kiểm soát nghiêm ngặt để đảm bảo hiệu suất quang học ổn định qua các lô sản xuất và các giai đoạn của chương trình. Hệ thống kỹ thuật và chất lượng tích hợp ngay tại cơ sở giúp duy trì sự ổn định trong quá trình mở rộng quy mô và giảm sự sai lệch giữa các đơn vị phát triển và sản xuất.',
		'Rather than a separate downstream step, manufacturing operates as a direct extension of the optical development process, supporting smooth transition from validation to sustained production.' => 'Thay vì là một bước riêng biệt ở giai đoạn sau, sản xuất hoạt động như một phần nối tiếp trực tiếp của quy trình phát triển quang học, hỗ trợ sự chuyển đổi liền mạch từ giai đoạn xác nhận (validation) sang sản xuất bền vững.',
		'Advantages' => 'Ưu điểm',
		'The US manufacturing advantage' => 'Lợi thế sản xuất tại Hoa Kỳ',
		'Defense &amp; government' => 'Quốc phòng &amp; chính phủ',
		'US defense and government programs often require domestically sourced components. Vuzix waveguides are manufactured in Rochester, New York, supporting American-made sourcing requirements for optical systems.' => 'Các chương trình quốc phòng và chính phủ Hoa Kỳ thường yêu cầu linh kiện có nguồn gốc sản xuất trong nước. Waveguide của Vuzix được sản xuất tại Rochester, New York, đáp ứng các yêu cầu về nguồn gốc sản xuất tại Mỹ đối với hệ thống quang học.',
		'Supply chain security' => 'An ninh chuỗi cung ứng',
		'US-based design and manufacturing reduce geopolitical supply chain risk for OEM partners. Integrated development at our Rochester headquarters enables predictable lead times, secure IP handling, and direct engineering collaboration.' => 'Việc thiết kế và sản xuất tại Hoa Kỳ giúp giảm rủi ro chuỗi cung ứng liên quan đến địa chính trị cho các đối tác OEM. Việc phát triển tích hợp tại trụ sở Rochester của chúng tôi giúp đảm bảo thời gian giao hàng có thể dự đoán được, xử lý IP an toàn và hợp tác kỹ thuật trực tiếp.',
		'Competitive production economics' => 'Hiệu quả kinh tế sản xuất cạnh tranh',
		'Proprietary manufacturing processes, in-house tooling, and high-yield production enable cost-competitive waveguide manufacturing without compromising quality or supply continuity.' => 'Các quy trình sản xuất độc quyền, công cụ chế tạo nội bộ và sản xuất với tỷ lệ thành phẩm cao giúp việc sản xuất waveguide có mức chi phí cạnh tranh mà không ảnh hưởng đến chất lượng hay tính liên tục của nguồn cung.',
		'Continuity' => 'Tính liên tục',
		'Connecting development with volume manufacturing' => 'Kết nối phát triển với sản xuất số lượng lớn',
		'Vuzix supports the transition from waveguide design to high-volume manufacturing all under one roof.' => 'Vuzix hỗ trợ quá trình chuyển đổi từ thiết kế waveguide đến sản xuất số lượng lớn, tất cả đều được thực hiện trong cùng một cơ sở.',
		'A connected development pipeline links optical design, prototyping, and production within a single framework. This helps preserve validated performance characteristics as programs move from early builds into scaled manufacturing.' => 'Một quy trình phát triển liên kết kết nối thiết kế quang học, tạo mẫu thử và sản xuất trong một khung làm việc duy nhất. Điều này giúp duy trì các đặc tính hiệu suất đã được xác nhận khi các chương trình chuyển từ giai đoạn xây dựng ban đầu sang sản xuất quy mô lớn.',
		'This continuity reduces fragmentation between engineering and production phases, supporting consistent system behavior across iterative development and long-term deployment.' => 'Tính liên tục này giúp giảm sự phân tán giữa các giai đoạn kỹ thuật và sản xuất, hỗ trợ hành vi hệ thống ổn định trong suốt quá trình phát triển lặp lại và triển khai dài hạn.',
		'How quickly can Vuzix scale waveguide production for my program?' => 'Vuzix có thể mở rộng quy mô sản xuất waveguide cho chương trình của tôi nhanh đến mức nào?',
		'Vuzix\' Rochester, New York facility can currently produce approximately 1 million waveguides annually with capacity expanding to support new OEM programs. Because manufacturing operates as a direct extension of optical development rather than a separate downstream step, programs can transition from validation to sustained production without an additional scale-up phase. Production planning is handled directly with the Vuzix manufacturing team as part of program engagement.' => 'Cơ sở của Vuzix tại Rochester, New York hiện có thể sản xuất khoảng 1 triệu waveguide mỗi năm, với công suất đang được mở rộng để hỗ trợ các chương trình OEM mới. Vì sản xuất hoạt động như một phần nối tiếp trực tiếp của quá trình phát triển quang học thay vì là một bước riêng biệt ở giai đoạn sau, các chương trình có thể chuyển từ giai đoạn xác nhận sang sản xuất bền vững mà không cần thêm một giai đoạn mở rộng quy mô riêng. Việc lập kế hoạch sản xuất được thực hiện trực tiếp với đội ngũ sản xuất của Vuzix như một phần của quá trình hợp tác chương trình.',
		'What certifications does Vuzix manufacturing hold?' => 'Bộ phận sản xuất của Vuzix có những chứng nhận nào?',
		'The Rochester waveguide facility is ISO 9001:2015 certified and operates Class 1K and Class 10K clean room environments. Vuzix is also a Tier-1 government contractor, supporting US defense and government programs that require domestically sourced AR optical components. Manufacturing processes are tightly controlled to ensure consistent optical performance across production runs and program phases.' => 'Cơ sở sản xuất waveguide tại Rochester được chứng nhận ISO 9001:2015 và vận hành các môi trường phòng sạch Class 1K và Class 10K. Vuzix cũng là nhà thầu chính phủ cấp 1 (Tier-1), hỗ trợ các chương trình quốc phòng và chính phủ Hoa Kỳ yêu cầu linh kiện quang học AR có nguồn gốc sản xuất trong nước. Các quy trình sản xuất được kiểm soát nghiêm ngặt để đảm bảo hiệu suất quang học ổn định qua các lô sản xuất và các giai đoạn của chương trình.',
		'How does Vuzix protect OEM partner IP during waveguide manufacturing?' => 'Vuzix bảo vệ tài sản trí tuệ (IP) của đối tác OEM như thế nào trong quá trình sản xuất waveguide?',
		'Because Vuzix designs and manufactures waveguides in-house in Rochester, New York, partner IP is handled within a single secure development environment rather than across multiple suppliers. US-based design and manufacturing reduces geopolitical supply chain risk and enables direct engineering collaboration, predictable lead times, and secure IP handling for OEM partners. Defense and security programs are supported with full confidentiality and NDA available upon request.' => 'Vì Vuzix thiết kế và sản xuất waveguide ngay tại cơ sở của mình ở Rochester, New York, tài sản trí tuệ của đối tác được xử lý trong một môi trường phát triển an toàn duy nhất, thay vì trải rộng qua nhiều nhà cung cấp khác nhau. Việc thiết kế và sản xuất tại Hoa Kỳ giúp giảm rủi ro chuỗi cung ứng liên quan đến địa chính trị và cho phép hợp tác kỹ thuật trực tiếp, thời gian giao hàng có thể dự đoán được, và xử lý IP an toàn cho các đối tác OEM. Các chương trình quốc phòng và an ninh được hỗ trợ với sự bảo mật đầy đủ, và NDA có thể được cung cấp theo yêu cầu.',
		'US-based volume waveguide production' => 'Sản xuất waveguide số lượng lớn tại Hoa Kỳ',
		'Vuzix combines waveguide design, manufacturing, and production support within a US-based development environment designed for OEM and defense programs.' => 'Vuzix kết hợp thiết kế waveguide, sản xuất và hỗ trợ sản xuất trong một môi trường phát triển tại Hoa Kỳ được thiết kế dành riêng cho các chương trình OEM và quốc phòng.',
		'Talk to Our Manufacturing Team →' => 'Liên hệ Đội ngũ Sản xuất của chúng tôi →',
		'Explore Waveguide Configurations →' => 'Khám phá Cấu hình Waveguide →',
		'Resources Overview' => 'Tổng quan Tài nguyên',
		'EXPERIENCES' => 'TRẢI NGHIỆM',
		'Insights, events, &amp; research' => 'Thông tin chuyên sâu, sự kiện &amp; nghiên cứu',
		'The future of work is being shaped by smart glasses, artificial intelligence, and the advanced waveguide optics beneath it all. Explore expert perspectives, industry events, customer success stories, and in-depth technical resources designed to support informed decision-making and innovation in your organization.' => 'Tương lai của công việc đang được định hình bởi kính thông minh, trí tuệ nhân tạo và công nghệ quang học waveguide tiên tiến ẩn sau tất cả những điều đó. Hãy khám phá các góc nhìn chuyên gia, sự kiện ngành, câu chuyện thành công của khách hàng và các tài nguyên kỹ thuật chuyên sâu được thiết kế để hỗ trợ việc ra quyết định sáng suốt và đổi mới trong tổ chức của bạn.',
		'CATCH UP WITH VUZIX' => 'CẬP NHẬT CÙNG VUZIX',
		'Recent news' => 'Tin tức mới nhất',
		'Why Visual Displays Are Becoming the Most Effective Tool for Picking in Smart Warehouses' => 'Tại sao Màn hình Hiển thị Trực quan Đang Trở thành Công cụ Hiệu quả nhất cho Việc Lấy hàng trong Kho Thông minh',
		'In the race to make affordable display smartglasses, a $100 part may win it' => 'Trong cuộc đua tạo ra kính thông minh có màn hình với giá phải chăng, một linh kiện 100 USD có thể là chìa khóa chiến thắng',
		'News' => 'Tin tức',
		'Q&amp;A: Vuzix CEO Paul Travers On Smart Glasses, Frontline Work And Enterprise Rollouts' => 'Hỏi &amp; Đáp: CEO Vuzix Paul Travers Nói về Kính Thông minh, Công việc Tuyến đầu và Triển khai Doanh nghiệp',
		'Explore More' => 'Khám phá thêm',
		'See Vuzix in action' => 'Xem Vuzix hoạt động thực tế',
		'Events &amp; Live Experiences' => 'Sự kiện &amp; Trải nghiệm Trực tiếp',
		'Connect with Vuzix at industry events, trade shows, and live demonstrations around the world.' => 'Kết nối với Vuzix tại các sự kiện ngành, hội chợ thương mại và các buổi trình diễn trực tiếp trên toàn thế giới.',
		'See schedule →' => 'Xem lịch trình →',
		'Explore real-world examples of how organizations across industries are deploying Vuzix smart glasses to improve productivity, accuracy, and collaboration.' => 'Khám phá các ví dụ thực tế về cách các tổ chức trong nhiều ngành khác nhau đang triển khai kính thông minh Vuzix để cải thiện năng suất, độ chính xác và khả năng hợp tác.',
		'See all →' => 'Xem tất cả →',
		'Dive deeper into the technology behind Vuzix solutions with white papers and technical documentation providing in-depth analysis.' => 'Tìm hiểu sâu hơn về công nghệ đằng sau các giải pháp của Vuzix qua các White Paper và tài liệu kỹ thuật cung cấp phân tích chuyên sâu.',
		'Where should I start if I\'m new to smart glasses?' => 'Tôi nên bắt đầu từ đâu nếu tôi mới tìm hiểu về kính thông minh?',
		'For visitors new to enterprise smart glasses, the Resources Hub\'s Learning Center is the recommended starting point. The five explainers — "What are smart glasses?", "What is a waveguide?", "What does OEM mean at Vuzix?", "What is AR vs. VR?", and "What does Vuzix actually do?" — establish foundational vocabulary and concepts before moving into product-specific or workflow-specific content. From there, the buyer\'s guides help compare smart glasses to alternatives like tablets or voice picking, and use case pages cover specific workflows like warehousing, field service, and healthcare.' => 'Đối với những người mới tìm hiểu về kính thông minh doanh nghiệp, Trung tâm Học tập (Learning Center) trong Trung tâm Tài nguyên (Resources Hub) là điểm khởi đầu được khuyến nghị. Năm bài giải thích cơ bản — "Kính thông minh là gì?", "Waveguide là gì?", "OEM có nghĩa là gì tại Vuzix?", "AR khác VR như thế nào?", và "Vuzix thực sự làm gì?" — giúp xây dựng vốn từ vựng và khái niệm nền tảng trước khi đi sâu vào nội dung theo từng sản phẩm hoặc quy trình cụ thể. Từ đó, các hướng dẫn dành cho người mua giúp so sánh kính thông minh với các giải pháp thay thế như máy tính bảng hay công nghệ lấy hàng bằng giọng nói (voice picking), và các trang trường hợp sử dụng đề cập đến các quy trình cụ thể như quản lý kho vận, dịch vụ hiện trường và y tế.',
		'Where do I go for technical documentation or developer resources?' => 'Tôi có thể tìm tài liệu kỹ thuật hoặc tài nguyên dành cho nhà phát triển ở đâu?',
		'Technical documentation and developer resources are accessed through dedicated sections of the Resources Hub. The Developer portal supports application development on Vuzix smart glasses, including SDK access and camera APIs for building enterprise applications on Android-based devices. The App Store provides access to existing Vuzix-compatible applications. Technical Support documentation covers device configuration, MDM compatibility, and deployment guidance. ISV partners and platform developers building on Vuzix hardware can request additional technical support directly.' => 'Tài liệu kỹ thuật và tài nguyên dành cho nhà phát triển có thể được truy cập qua các mục chuyên biệt trong Trung tâm Tài nguyên. Cổng Nhà phát triển (Developer portal) hỗ trợ việc phát triển ứng dụng trên kính thông minh Vuzix, bao gồm quyền truy cập SDK và camera API để xây dựng các ứng dụng doanh nghiệp trên các thiết bị chạy Android. App Store cung cấp quyền truy cập vào các ứng dụng hiện có tương thích với Vuzix. Tài liệu Hỗ trợ Kỹ thuật bao gồm cấu hình thiết bị, khả năng tương thích với MDM và hướng dẫn triển khai. Các đối tác ISV và nhà phát triển nền tảng xây dựng trên phần cứng Vuzix có thể yêu cầu hỗ trợ kỹ thuật bổ sung trực tiếp.',
		'Product Support' => 'Hỗ trợ Sản phẩm',
		'Have a question about your Vuzix product? Our team is dedicated to helping customers get the deployment and usage support needed to maximize ROI.' => 'Bạn có câu hỏi về sản phẩm Vuzix của mình? Đội ngũ của chúng tôi luôn sẵn sàng giúp khách hàng nhận được sự hỗ trợ triển khai và sử dụng cần thiết để tối đa hóa ROI.',
		'Get Support →' => 'Nhận Hỗ trợ →',
		'Prescriptions' => 'Đơn kính',
		'Prescription Measurement Guide' => 'Hướng dẫn Đo Thông số Kính',
		'Single PD' => 'PD Đơn',
		'Stand 8” away from a mirror.' => 'Đứng cách gương 8 inch.',
		'Hold a ruler against your eyebrows.' => 'Đặt một cây thước áp sát lông mày của bạn.',
		'STEP 3' => 'BƯỚC 3',
		'Close your right eye, and align the ruler’s 0mm mark with the middle of your left pupil.' => 'Nhắm mắt phải lại, và căn chỉnh vạch 0mm của thước với trung tâm đồng tử mắt trái của bạn.',
		'STEP 4' => 'BƯỚC 4',
		'Open your right eye and close your left eye.' => 'Mở mắt phải và nhắm mắt trái lại.',
		'STEP 5' => 'BƯỚC 5',
		'Find the millimeter line that aligns with the center of your right pupil.' => 'Tìm vạch milimet trùng với trung tâm đồng tử mắt phải của bạn.',
		'Dual PD' => 'PD Kép',
		'Align the ruler’s 0mm mark with the middle of your nose.' => 'Căn chỉnh vạch 0mm của thước với chính giữa mũi của bạn.',
		'Close your left eye and find the millimeter line that aligns with the center of your right pupil. This is your right PD.' => 'Nhắm mắt trái lại và tìm vạch milimet trùng với trung tâm đồng tử mắt phải của bạn. Đây là PD mắt phải của bạn.',
		'Close your right eye and align the 0 mark with the center of your left eye. Find the millimeter line that aligns with the center of your nose. This is your left PD.' => 'Nhắm mắt phải lại và căn chỉnh vạch 0 với trung tâm mắt trái của bạn. Tìm vạch milimet trùng với chính giữa mũi của bạn. Đây là PD mắt trái của bạn.',
		'SEE WHAT\'S HAPPENING' => 'XEM NHỮNG GÌ ĐANG DIỄN RA',
		'MicroLED Connect is a specialized industry event focused on the latest advancements in MicroLED display technology, bringing together manufacturers, engineers, suppliers, and innovators across the display ecosystem. The show highlights emerging applications in AR/VR, automotive, consumer electronics, wearables, and next-generation displays through exhibitions, technical presentations, and industry networking.' => 'MicroLED Connect là một sự kiện chuyên ngành tập trung vào những tiến bộ mới nhất trong công nghệ màn hình MicroLED, tập hợp các nhà sản xuất, kỹ sư, nhà cung cấp và những người tiên phong đổi mới trong toàn bộ hệ sinh thái màn hình. Sự kiện này giới thiệu các ứng dụng mới nổi trong AR/VR, ô tô, điện tử tiêu dùng, thiết bị đeo được, và màn hình thế hệ tiếp theo, thông qua các buổi triển lãm, thuyết trình kỹ thuật và giao lưu kết nối trong ngành.',
		'Eindhoven, The Netherlands' => 'Eindhoven, Hà Lan',
		'Learn More About LX1 Smart Glasses' => 'Tìm hiểu thêm về Kính thông minh LX1',
		'Ultralite OEM Platform' => 'Nền tảng OEM Ultralite',
		'Complete AI smart glasses reference designs built for rapid customization and OEM development' => 'Các thiết kế mẫu kính thông minh AI hoàn chỉnh, được xây dựng để tùy chỉnh nhanh và phát triển OEM',
		'Talk to Our OEM Team →' => 'Trò chuyện với Đội ngũ OEM của chúng tôi →',
		'Take the OEM Assessment →' => 'Thực hiện Đánh giá OEM →',
		'A faster path to smart glasses development' => 'Con đường nhanh hơn để phát triển kính thông minh',
		'Named for their core characteristics as a featherlight, all-day wearable, Vuzix Ultralite platforms can be adapted across different display technologies and software environments based on program goals.' => 'Được đặt tên theo đặc điểm cốt lõi là siêu nhẹ và có thể đeo cả ngày, các nền tảng Vuzix Ultralite có thể được điều chỉnh trên nhiều công nghệ hiển thị và môi trường phần mềm khác nhau tùy theo mục tiêu của từng chương trình.',
		'Built to reduce development complexity and accelerate evaluation, Ultralite platforms provide a proven starting point' => 'Được xây dựng để giảm độ phức tạp trong phát triển và đẩy nhanh quá trình đánh giá, các nền tảng Ultralite mang đến một điểm khởi đầu đã được kiểm chứng',
		'for companies developing branded AI smart glasses across enterprise, consumer, and specialized applications.' => 'cho các công ty phát triển kính thông minh AI mang thương hiệu riêng trong lĩnh vực doanh nghiệp, tiêu dùng và các ứng dụng chuyên biệt.',
		'Unlike standalone component suppliers, Vuzix integrates optical systems, hardware engineering, and manufacturing coordination within a single OEM engagement model. This allows partners to move more' => 'Không giống như các nhà cung cấp linh kiện đơn lẻ, Vuzix tích hợp hệ thống quang học, kỹ thuật phần cứng và điều phối sản xuất trong một mô hình hợp tác OEM duy nhất. Điều này cho phép các đối tác triển khai',
		'efficiently through prototyping, validation, and product refinement while maintaining flexibility for application-specific requirements.' => 'hiệu quả hơn qua các giai đoạn tạo mẫu, kiểm định và hoàn thiện sản phẩm, đồng thời vẫn giữ được sự linh hoạt cho các yêu cầu riêng của từng ứng dụng.',
		'Ultralite platforms can be adapted across different display technologies, industrial designs, and software environments depending on program goals and device requirements.' => 'Các nền tảng Ultralite có thể được điều chỉnh trên nhiều công nghệ hiển thị, thiết kế công nghiệp và môi trường phần mềm khác nhau, tùy theo mục tiêu chương trình và yêu cầu thiết bị.',
		'Platforms' => 'Nền tảng',
		'Choose your starting point' => 'Chọn điểm khởi đầu của bạn',
		'Pre-configured smart glasses reference designs combining Vuzix waveguide optics, display systems, processing, connectivity, and firmware into bundled OEM development platforms. Partners can customize industrial design, branding, software environments, and application-specific functionality while building on validated hardware and optical architectures.' => 'Các thiết kế mẫu kính thông minh được cấu hình sẵn, kết hợp quang học ống dẫn sóng (waveguide) của Vuzix, hệ thống hiển thị, xử lý, kết nối và firmware thành các nền tảng phát triển OEM trọn gói. Đối tác có thể tùy chỉnh thiết kế công nghiệp, thương hiệu, môi trường phần mềm và các tính năng riêng theo ứng dụng, trong khi vẫn dựa trên các kiến trúc phần cứng và quang học đã được kiểm định.',
		'Full-color binocular AI smart Glasses' => 'Kính thông minh AI hai mắt, hiển thị màu đầy đủ',
		'Ultralite Pro OEM Platform' => 'Nền tảng OEM Ultralite Pro',
		'Prescription-ready full-color smart glasses with onboard processing designed for enterprise AI experiences.' => 'Kính thông minh hiển thị màu đầy đủ, hỗ trợ lắp tròng kính theo đơn, có xử lý tích hợp trên thiết bị, được thiết kế cho các trải nghiệm AI doanh nghiệp.',
		'Built for lightweight everyday wear, this configuration supports immersive visual interfaces, contextual AI interaction, and integrated sensing capabilities.' => 'Được thiết kế để đeo hàng ngày với trọng lượng nhẹ, cấu hình này hỗ trợ giao diện hình ảnh sống động, tương tác AI theo ngữ cảnh và khả năng cảm biến tích hợp.',
		'Waveguide' => 'Ống dẫn sóng',
		'CI-30 dual full-color waveguide configuration' => 'Cấu hình ống dẫn sóng CI-30, hai kênh, hiển thị màu đầy đủ',
		'Display' => 'Màn hình hiển thị',
		'Dual full-color LCoS' => 'LCoS hai kênh, hiển thị màu đầy đủ',
		'Processor' => 'Bộ xử lý',
		'Single onboard camera with AI vision support' => 'Một camera tích hợp hỗ trợ nhận diện hình ảnh AI',
		'Audio' => 'Âm thanh',
		'Integrated stereo audio' => 'Âm thanh stereo tích hợp',
		'Form factor' => 'Kiểu dáng',
		'Prescription-ready lightweight eyewear' => 'Kính đeo nhẹ, hỗ trợ lắp tròng kính theo đơn',
		'Best for' => 'Phù hợp với',
		'Enterprise AR, AI assistants, prosumers' => 'AR doanh nghiệp, trợ lý AI, người dùng chuyên sâu (prosumer)',
		'Audio-First AI Wearable' => 'Thiết bị đeo AI ưu tiên âm thanh',
		'Ultralite Audio OEM Platform' => 'Nền tảng OEM Ultralite Audio',
		'Lightweight smart glasses platform combining an integrated audio experience with a discreet monochrome display for notifications, task guidance, and AI-assisted interactions.' => 'Nền tảng kính thông minh nhẹ, kết hợp trải nghiệm âm thanh tích hợp với màn hình đơn sắc kín đáo dùng cho thông báo, hướng dẫn thao tác và các tương tác có hỗ trợ AI.',
		'Designed as a low-profile wearable accessory for consumer and enterprise applications, this configuration prioritizes comfort, all-day wearability, and streamlined connectivity through a paired mobile device.' => 'Được thiết kế như một phụ kiện đeo gọn nhẹ cho các ứng dụng tiêu dùng và doanh nghiệp, cấu hình này chú trọng sự thoải mái, khả năng đeo cả ngày và kết nối liền mạch thông qua thiết bị di động ghép nối.',
		'MI-30 single monochrome waveguide' => 'Ống dẫn sóng đơn sắc MI-30, một kênh',
		'Single microLED projector' => 'Một bộ chiếu microLED',
		'Bluetooth-connected mobile companion architecture' => 'Kiến trúc kết nối Bluetooth với thiết bị di động đồng hành',
		'None' => 'Không có',
		'Prescription-ready fashion eyewear' => 'Kính thời trang, hỗ trợ lắp tròng kính theo đơn',
		'Light industrial workflows, task confirmation, broad market' => 'Quy trình công nghiệp nhẹ, xác nhận thao tác, thị trường phổ thông',
		'Convenient Heads-Up Display' => 'Màn hình hiển thị Heads-Up tiện lợi',
		'Ultralite HUD OEM Platform' => 'Nền tảng OEM Ultralite HUD',
		'Minimalist smart glasses configuration designed to deliver glanceable visual information within a lightweight, everyday wearable form factor.' => 'Cấu hình kính thông minh tối giản, được thiết kế để hiển thị thông tin trực quan có thể xem nhanh trong một kiểu dáng nhẹ, phù hợp đeo hàng ngày.',
		'This 38g platform supports heads-up notifications, navigation prompts, workflow guidance, and contextual data visibility without introducing audio or camera complexity. Its streamlined architecture enables discreet integration into consumer or enterprise eyewear designs, and low-power operation for up to 48 hours.' => 'Nền tảng nặng 38g này hỗ trợ thông báo heads-up, chỉ dẫn định hướng, hướng dẫn quy trình làm việc và hiển thị dữ liệu theo ngữ cảnh mà không cần thêm độ phức tạp của âm thanh hay camera. Kiến trúc tinh gọn của nó cho phép tích hợp kín đáo vào các thiết kế kính tiêu dùng hoặc doanh nghiệp, cùng khả năng vận hành tiết kiệm điện lên đến 48 giờ.',
		'Notifications, navigation, light industrial workflows and broad market eyewear' => 'Thông báo, định hướng, quy trình công nghiệp nhẹ và kính đeo cho thị trường phổ thông',
		'COMPETITIVE ADVANTAGES' => 'LỢI THẾ CẠNH TRANH',
		'Built for rapid OEM deployment' => 'Được xây dựng để triển khai OEM nhanh chóng',
		'Ultralite OEM Platforms are designed to reduce engineering complexity and shorten early-stage development timelines by providing validated optical, electronic, and mechanical system foundations. Rather than sourcing and integrating individual subsystems independently, partners can begin with a pre-engineered architecture already optimized for intelligent eyewear performance and manufacturability based in the US.' => 'Các nền tảng OEM Ultralite được thiết kế để giảm độ phức tạp kỹ thuật và rút ngắn thời gian phát triển ở giai đoạn đầu bằng cách cung cấp sẵn nền tảng quang học, điện tử và cơ khí đã được kiểm định. Thay vì phải tự tìm nguồn và tích hợp từng phân hệ riêng lẻ, đối tác có thể bắt đầu ngay với một kiến trúc đã được thiết kế kỹ thuật sẵn, tối ưu cho hiệu năng và khả năng sản xuất của kính thông minh, được thực hiện tại Hoa Kỳ.',
		'PHASE 1' => 'GIAI ĐOẠN 1',
		'Validated optical integration' => 'Tích hợp quang học đã được kiểm định',
		'Waveguides, display engines, and core system components are pre-aligned and performance-tested as part of a unified architecture.' => 'Ống dẫn sóng, bộ hiển thị và các linh kiện hệ thống cốt lõi được căn chỉnh sẵn và kiểm tra hiệu năng như một phần của kiến trúc thống nhất.',
		'PHASE 2' => 'GIAI ĐOẠN 2',
		'Faster prototype cycles' => 'Chu kỳ tạo mẫu nhanh hơn',
		'Integrated hardware and firmware foundations allow teams to move quickly into evaluation, software development, and industrial design refinement.' => 'Nền tảng phần cứng và firmware được tích hợp sẵn giúp các nhóm phát triển nhanh chóng chuyển sang giai đoạn đánh giá, phát triển phần mềm và hoàn thiện thiết kế công nghiệp.',
		'PHASE 3' => 'GIAI ĐOẠN 3',
		'Flexible customization' => 'Tùy chỉnh linh hoạt',
		'Platforms can be adapted across different industrial designs, software environments, input methods, and application requirements.' => 'Các nền tảng có thể được điều chỉnh theo nhiều thiết kế công nghiệp, môi trường phần mềm, phương thức nhập liệu và yêu cầu ứng dụng khác nhau.',
		'PHASE 4' => 'GIAI ĐOẠN 4',
		'Production-aligned engineering' => 'Kỹ thuật thiết kế gắn liền với sản xuất',
		'Development decisions are informed by manufacturing realities early in the process, reducing redesign risk during scale-up.' => 'Các quyết định phát triển được đưa ra dựa trên thực tế sản xuất ngay từ giai đoạn đầu, giúp giảm rủi ro phải thiết kế lại khi mở rộng quy mô.',
		'Partnership Model' => 'Mô hình hợp tác',
		'You define the product. Vuzix provides the platform.' => 'Bạn định hình sản phẩm. Vuzix cung cấp nền tảng.',
		'Ultralite OEM Platforms are designed to give partners control over product identity and user experience while reducing the complexity of underlying hardware and optical development. Vuzix provides the integrated technology foundation, allowing partners to focus on differentiation, software, and go-to-market strategy.' => 'Các nền tảng OEM Ultralite được thiết kế để đối tác có toàn quyền kiểm soát bản sắc sản phẩm và trải nghiệm người dùng, trong khi giảm bớt độ phức tạp trong phát triển phần cứng và quang học cơ bản. Vuzix cung cấp nền tảng công nghệ tích hợp, giúp đối tác tập trung vào yếu tố khác biệt, phần mềm và chiến lược đưa sản phẩm ra thị trường.',
		'What Vuzix provides' => 'Những gì Vuzix cung cấp',
		'• Waveguide optics designed and manufactured in the United States' => '• Quang học ống dẫn sóng được thiết kế và sản xuất tại Hoa Kỳ',
		'• Validated display engine and component ecosystem' => '• Bộ hiển thị và hệ sinh thái linh kiện đã được kiểm định',
		'• Processing integration, including Snapdragon AR1-class architectures' => '• Tích hợp xử lý, bao gồm các kiến trúc thuộc dòng Snapdragon AR1',
		'• Camera, audio, connectivity, and core hardware systems' => '• Camera, âm thanh, kết nối và các hệ thống phần cứng cốt lõi',
		'• Firmware and foundational software support' => '• Firmware và hỗ trợ phần mềm nền tảng',
		'• Engineering, prototyping, and manufacturing coordination' => '• Điều phối kỹ thuật, tạo mẫu và sản xuất',
		'• Regulatory and certification guidance' => '• Hướng dẫn về quy định pháp lý và chứng nhận',
		'What partners control' => 'Những gì đối tác kiểm soát',
		'• Brand identity and product positioning' => '• Bản sắc thương hiệu và định vị sản phẩm',
		'• Industrial design direction and visual styling' => '• Định hướng thiết kế công nghiệp và phong cách hình ảnh',
		'• Software stack and user experience' => '• Ngăn xếp phần mềm (software stack) và trải nghiệm người dùng',
		'• AI applications layer' => '• Lớp ứng dụng AI',
		'• Materials, finishes, and wearable design choices' => '• Vật liệu, hoàn thiện và các lựa chọn thiết kế cho thiết bị đeo',
		'• Commercial strategy, pricing, and distribution' => '• Chiến lược kinh doanh, giá bán và phân phối',
		'• Customer relationships and market rolllout' => '• Quan hệ khách hàng và triển khai ra thị trường',
		'Which Ultralite Platform fits my product?' => 'Nền tảng Ultralite nào phù hợp với sản phẩm của tôi?',
		'Choose Ultralite Pro when full-color binocular optics are core to the user experience — for example, enterprise AR with rich visual overlays, AI assistants with visual responses, or prosumer smart glasses. Choose Ultralite Audio when AI interaction, premium audio, and notifications matter more than full-color visuals — for example, AI assistant glasses or audio-first consumer wearables. Choose Ultralite HUD when minimal heads-up information delivery in the lightest possible form factor is the priority, without audio or camera complexity.' => 'Hãy chọn Ultralite Pro khi quang học hai mắt, hiển thị màu đầy đủ là yếu tố cốt lõi của trải nghiệm người dùng — ví dụ như AR doanh nghiệp với lớp phủ hình ảnh phong phú, trợ lý AI có phản hồi hình ảnh, hoặc kính thông minh dành cho người dùng chuyên sâu. Hãy chọn Ultralite Audio khi tương tác AI, âm thanh cao cấp và thông báo quan trọng hơn hình ảnh màu đầy đủ — ví dụ như kính trợ lý AI hoặc thiết bị đeo tiêu dùng ưu tiên âm thanh. Hãy chọn Ultralite HUD khi ưu tiên hàng đầu là cung cấp thông tin heads-up tối giản trong kiểu dáng nhẹ nhất có thể, không cần âm thanh hay camera.',
		'Can I change components within an Ultralite Platform configuration?' => 'Tôi có thể thay đổi linh kiện trong một cấu hình Nền tảng Ultralite không?',
		'Yes, within limits. The Ultralite OEM Platform provides validated optical, electronic, and mechanical foundations as a starting architecture, but partners can adapt display technologies, software environments, input methods, materials, and industrial design depending on program goals. Major component substitutions (different waveguide, different processor) move the program from platform-based development toward fully custom ODM engineering, which Vuzix also supports.' => 'Có, nhưng trong một giới hạn nhất định. Nền tảng OEM Ultralite cung cấp sẵn nền tảng quang học, điện tử và cơ khí đã được kiểm định làm kiến trúc khởi đầu, nhưng đối tác có thể điều chỉnh công nghệ hiển thị, môi trường phần mềm, phương thức nhập liệu, vật liệu và thiết kế công nghiệp tùy theo mục tiêu chương trình. Việc thay đổi các linh kiện chính (ống dẫn sóng khác, bộ xử lý khác) sẽ đưa chương trình từ phát triển dựa trên nền tảng sang kỹ thuật ODM tùy chỉnh hoàn toàn, một dịch vụ mà Vuzix cũng hỗ trợ.',
		'How does the Ultralite Platform compare to starting from scratch?' => 'Nền tảng Ultralite so với việc bắt đầu từ đầu thì khác nhau như thế nào?',
		'Starting from scratch means sourcing waveguides, display engines, processors, firmware, and reference hardware independently and integrating them — a process that typically requires multiple supplier relationships and iterative validation before a working prototype exists. The Ultralite OEM Platform begins with a pre-engineered architecture already optimized for wearable performance and manufacturability. This approach allows teams to start at prototype stage and focus engineering effort on industrial design, software, and product differentiation.' => 'Bắt đầu từ đầu có nghĩa là phải tự tìm nguồn ống dẫn sóng, bộ hiển thị, bộ xử lý, firmware và phần cứng tham chiếu một cách độc lập rồi tích hợp chúng lại — một quá trình thường đòi hỏi nhiều quan hệ nhà cung cấp và nhiều vòng kiểm định lặp lại trước khi có được một mẫu thử hoạt động. Nền tảng OEM Ultralite bắt đầu với một kiến trúc đã được thiết kế kỹ thuật sẵn, tối ưu cho hiệu năng và khả năng sản xuất của thiết bị đeo. Cách tiếp cận này giúp các nhóm phát triển bắt đầu ngay từ giai đoạn mẫu thử và tập trung công sức kỹ thuật vào thiết kế công nghiệp, phần mềm và sự khác biệt của sản phẩm.',
		'Explore an OEM starting point' => 'Khám phá điểm khởi đầu OEM',
		'Whether you are exploring a new concept or scaling an existing program, Vuzix provides the technology and experience to help bring it to market.' => 'Cho dù bạn đang tìm hiểu một ý tưởng mới hay mở rộng một chương trình sẵn có, Vuzix cung cấp công nghệ và kinh nghiệm để giúp đưa sản phẩm ra thị trường.',
		'Explore Engineering Services →' => 'Khám phá Dịch vụ Kỹ thuật →',
		'Healthcare' => 'Y tế',
		'Delivering better patient outcomes' => 'Mang lại kết quả điều trị tốt hơn cho bệnh nhân',
		'with connected care through smart glasses' => 'với dịch vụ chăm sóc kết nối qua kính thông minh',
		'Critical Information at the Point of Care' => 'Thông tin quan trọng ngay tại điểm chăm sóc',
		'Vuzix smart glasses help healthcare organizations deliver information, expertise, and collaboration directly at the point of care.' => 'Kính thông minh Vuzix giúp các tổ chức y tế truyền tải thông tin, chuyên môn và sự phối hợp trực tiếp ngay tại điểm chăm sóc.',
		'Designed for clinical environments, Vuzix devices provide hands-free access to patient information, remote specialists, and digital workflows while allowing caregivers to remain focused on patients and procedures.' => 'Được thiết kế cho môi trường lâm sàng, các thiết bị Vuzix cho phép truy cập thông tin bệnh nhân, kết nối với chuyên gia từ xa và các quy trình số một cách rảnh tay, đồng thời giúp nhân viên y tế luôn tập trung vào bệnh nhân và quy trình điều trị.',
		'This enables healthcare professionals to access critical information without interrupting treatment or compromising mobility.' => 'Điều này giúp các chuyên gia y tế truy cập thông tin quan trọng mà không làm gián đoạn việc điều trị hay ảnh hưởng đến khả năng di chuyển.',
		'From telemedicine and surgical support to medical training and remote consultation, Vuzix smart glasses help connect care teams across locations and disciplines.' => 'Từ y tế từ xa và hỗ trợ phẫu thuật đến đào tạo y khoa và tư vấn từ xa, kính thông minh Vuzix giúp kết nối các nhóm chăm sóc trên nhiều địa điểm và chuyên khoa khác nhau.',
		'Real-time video sharing, remote collaboration, and voice-controlled operation enable faster decision-making and more efficient knowledge transfer throughout healthcare organizations.' => 'Chia sẻ video thời gian thực, phối hợp từ xa và vận hành bằng điều khiển giọng nói giúp đưa ra quyết định nhanh hơn và truyền đạt kiến thức hiệu quả hơn trong toàn bộ tổ chức y tế.',
		'Vuzix devices support hospitals, clinics, medical educators, and healthcare networks seeking to improve operational efficiency while enhancing patient care and clinical outcomes.' => 'Các thiết bị Vuzix hỗ trợ bệnh viện, phòng khám, giảng viên y khoa và các mạng lưới y tế mong muốn nâng cao hiệu quả vận hành, đồng thời cải thiện chất lượng chăm sóc và kết quả điều trị cho bệnh nhân.',
		'POINT OF CARE' => 'ĐIỂM CHĂM SÓC',
		'Medical smart glasses for healthcare leaders' => 'Kính thông minh y tế cho các nhà lãnh đạo ngành y tế',
		'Reduction in operating time' => 'Giảm thời gian phẫu thuật',
		'In healthcare, information and speed of delivery are crucial to successful outcomes. Delaying treatment for a serious condition can make the difference between life and death.' => 'Trong y tế, thông tin và tốc độ truyền tải thông tin là yếu tố quan trọng quyết định kết quả điều trị. Việc chậm trễ điều trị một tình trạng nghiêm trọng có thể là sự khác biệt giữa sự sống và cái chết.',
		'IMPROVING OUTCOMES' => 'CẢI THIỆN KẾT QUẢ ĐIỀU TRỊ',
		'Benefits of AR in healthcare' => 'Lợi ích của AR trong y tế',
		'Stream your practice' => 'Truyền trực tiếp quy trình khám chữa bệnh của bạn',
		'Get hands-free and private access to' => 'Truy cập rảnh tay và riêng tư vào',
		'sensitive patient information directly in your' => 'thông tin nhạy cảm của bệnh nhân ngay trong',
		'field of vision.' => 'tầm nhìn của bạn.',
		'Share your knowledge' => 'Chia sẻ kiến thức của bạn',
		'Train junior doctors with recorded and' => 'Đào tạo bác sĩ trẻ bằng video ghi lại và',
		'real-time HD streaming and Zoom capability' => 'truyền trực tiếp HD theo thời gian thực cùng khả năng Zoom',
		'Access remote expertise' => 'Kết nối với chuyên gia từ xa',
		'Share live ICU videos and test results with' => 'Chia sẻ video trực tiếp từ phòng ICU và kết quả xét nghiệm với',
		'remote medical experts for instant' => 'các chuyên gia y tế ở xa để nhận',
		'evaluation.' => 'đánh giá ngay lập tức.',
		'Offer a higher level oftreatment' => 'Cung cấp mức độ điều trị cao hơn',
		'Monitor vital signs without needing to take' => 'Theo dõi các chỉ số sinh tồn mà không cần rời',
		'your eyes, or hands, off the patient.' => 'mắt hoặc tay khỏi bệnh nhân.',
		'Stay connected' => 'Luôn kết nối',
		'Align your team so that everyone can monitor patients and discuss next steps in real time' => 'Đồng bộ nhóm của bạn để mọi người có thể theo dõi bệnh nhân và thảo luận các bước tiếp theo trong thời gian thực',
		'Certified for Use' => 'Được chứng nhận sử dụng',
		'Vuzix M400 smart glasses for healthcare professionals' => 'Kính thông minh Vuzix M400 cho các chuyên gia y tế',
		'The M400 smart glasses are rapidly replacing hand-held devices and can apply to nearly all healthcare services functions.' => 'Kính thông minh M400 đang nhanh chóng thay thế các thiết bị cầm tay và có thể áp dụng cho hầu hết mọi chức năng trong dịch vụ y tế.',
		'Vuzix M400 smart glasses are:' => 'Kính thông minh Vuzix M400 có các đặc điểm:',
		'The first to have a dedicated 8 Core 8.52Ghz XR1 Platform' => 'Sản phẩm đầu tiên sở hữu nền tảng XR1 8 nhân 8.52GHz chuyên dụng',
		'HIPAA-compliant and IP67 rated' => 'Tuân thủ HIPAA và đạt chuẩn chống nước bụi IP67',
		'Designed to work with medical personal protective equipment' => 'Được thiết kế để sử dụng cùng thiết bị bảo hộ cá nhân y tế',
		'IEC60601-1-2:2014 certified medical electrical equipment' => 'Thiết bị điện y tế đạt chứng nhận IEC60601-1-2:2014',
		'Explore M400 →' => 'Khám phá M400 →',
		'EVOLVING MEDICAL LANDSCAPE' => 'BỐI CẢNH Y TẾ ĐANG THAY ĐỔI',
		'Healthcare use cases' => 'Trường hợp ứng dụng trong y tế',
		'SURGERY' => 'PHẪU THUẬT',
		'Vuzix smart glasses for surgery bring you a truly hands-free, voice-controlled AR experience. This helps preserve a sterile operating room and eliminates risk in hazardous environments, letting you provide the utmost in surgical care.' => 'Kính thông minh Vuzix dùng trong phẫu thuật mang đến trải nghiệm AR hoàn toàn rảnh tay, điều khiển bằng giọng nói. Điều này giúp giữ vô trùng phòng mổ và loại bỏ rủi ro trong môi trường nguy hiểm, cho phép bạn cung cấp mức độ chăm sóc phẫu thuật tốt nhất.',
		'Best of all, the battery ensures that they\'ll last when you need them most: our smart glasses are worn by medical professionals during surgeries for 16+ hours straight.' => 'Điều tuyệt vời nhất là pin đảm bảo thiết bị luôn hoạt động khi bạn cần nhất: kính thông minh của chúng tôi được các chuyên gia y tế đeo liên tục hơn 16 giờ trong các ca phẫu thuật.',
		'TELEMEDICINE' => 'Y TẾ TỪ XA',
		'Vuzix augmented reality (AR) glasses for medical diagnosis allow you to safely administer to patients without compromising the quality of care.' => 'Kính thực tế tăng cường (AR) Vuzix dùng cho chẩn đoán y tế cho phép bạn chăm sóc bệnh nhân một cách an toàn mà không ảnh hưởng đến chất lượng điều trị.',
		'With Vuzix smart glasses for telemedicine, you can instantly share your medical expertise with practitioners around the globe. Send and receive live expert medical feedback without ever having to pause care to the patient.' => 'Với kính thông minh Vuzix dùng cho y tế từ xa, bạn có thể chia sẻ ngay lập tức chuyên môn y tế của mình với các bác sĩ trên toàn thế giới. Gửi và nhận phản hồi chuyên môn trực tiếp mà không cần tạm dừng việc chăm sóc bệnh nhân.',
		'TRAINING' => 'ĐÀO TẠO',
		'Vuzix smart glasses let doctors experience complex surgeries with ease and unparalleled display. Vuzix software provides full HD live-streaming and advanced options such as brightness adjustment and Zoom capability.' => 'Kính thông minh Vuzix giúp các bác sĩ dễ dàng theo dõi những ca phẫu thuật phức tạp với chất lượng hiển thị vượt trội. Phần mềm Vuzix cung cấp khả năng truyền trực tiếp Full HD cùng các tùy chọn nâng cao như điều chỉnh độ sáng và khả năng Zoom.',
		'Doctors can walk through via real-life scenarios, complex procedures can be more collaborative, and feedback can be given instantly without overwhelming the user\'s vision and sense of reality.' => 'Các bác sĩ có thể theo dõi từng bước qua các tình huống thực tế, những ca phẫu thuật phức tạp có thể được thực hiện phối hợp tốt hơn, và phản hồi có thể được đưa ra ngay lập tức mà không làm rối tầm nhìn hay cảm nhận thực tế của người dùng.',
		'How are smart glasses used in healthcare environments?' => 'Kính thông minh được sử dụng như thế nào trong môi trường y tế?',
		'Vuzix smart glasses support healthcare workflows including telemedicine and remote consultation, surgical visualization and reference, clinical documentation and EHR access, medical training and simulation, and remote expert support for procedures. Clinicians can access patient information, imaging, or procedural references hands-free while maintaining focus on the patient, and remote specialists can see what the on-site clinician sees through integrated video and annotation. Vuzix waveguide technology also powers third-party surgical platforms used for 16+ hour continuous procedures.' => 'Kính thông minh Vuzix hỗ trợ các quy trình y tế bao gồm y tế từ xa và tư vấn từ xa, hiển thị và tham chiếu hình ảnh phẫu thuật, ghi chép hồ sơ bệnh án và truy cập EHR, đào tạo và mô phỏng y khoa, cùng hỗ trợ chuyên gia từ xa cho các thủ thuật. Nhân viên y tế có thể truy cập thông tin bệnh nhân, hình ảnh chụp chiếu hoặc tài liệu tham chiếu quy trình một cách rảnh tay mà vẫn tập trung vào bệnh nhân, còn các chuyên gia ở xa có thể thấy chính xác những gì nhân viên y tế tại chỗ đang thấy thông qua video và chú thích tích hợp. Công nghệ ống dẫn sóng của Vuzix cũng là nền tảng cho các thiết bị phẫu thuật của bên thứ ba, được sử dụng trong các thủ thuật kéo dài liên tục hơn 16 giờ.',
		'Which Vuzix smart glasses work for healthcare?' => 'Kính thông minh Vuzix nào phù hợp cho lĩnh vực y tế?',
		'The Vuzix M400 is the typical choice for healthcare workflows, with hot-swappable batteries for shift-long use, all-purpose optical capability, and an open SDK that supports clinical application development. The Vuzix Remote Assist Kit extends the M400 with Microsoft Teams and Zoom integration for live remote consultation, telemedicine, and remote expert support in surgical and clinical environments. For specialized surgical applications requiring custom optical platforms, Vuzix also supports OEM development of dedicated medical assist devices.' => 'Vuzix M400 là lựa chọn phổ biến cho các quy trình y tế, với pin có thể thay nóng để sử dụng suốt ca làm việc, khả năng quang học đa dụng và SDK mở hỗ trợ phát triển ứng dụng lâm sàng. Vuzix Remote Assist Kit mở rộng khả năng của M400 với tích hợp Microsoft Teams và Zoom, phục vụ tư vấn từ xa trực tiếp, y tế từ xa và hỗ trợ chuyên gia từ xa trong môi trường phẫu thuật và lâm sàng. Đối với các ứng dụng phẫu thuật chuyên biệt cần nền tảng quang học tùy chỉnh, Vuzix cũng hỗ trợ phát triển OEM cho các thiết bị hỗ trợ y tế chuyên dụng.',
		'Are Vuzix smart glasses suitable for clinical and surgical environments?' => 'Kính thông minh Vuzix có phù hợp với môi trường lâm sàng và phẫu thuật không?',
		'Vuzix smart glasses support clinical use through hands-free operation, voice and gesture control, and integration with healthcare software platforms through the open SDK. For surgical applications specifically, Vuzix waveguide optics power third-party surgical navigation platforms used in extended orthopedic procedures. Healthcare deployments typically involve coordination with clinical IT, EHR integration, and infection control protocols. Vuzix works with system integrators experienced in clinical environments for these requirements.' => 'Kính thông minh Vuzix hỗ trợ sử dụng trong môi trường lâm sàng thông qua khả năng vận hành rảnh tay, điều khiển bằng giọng nói và cử chỉ, cùng khả năng tích hợp với các nền tảng phần mềm y tế qua SDK mở. Riêng đối với các ứng dụng phẫu thuật, quang học ống dẫn sóng của Vuzix là nền tảng cho các hệ thống định vị phẫu thuật của bên thứ ba, được sử dụng trong các thủ thuật chỉnh hình kéo dài. Việc triển khai trong y tế thường đòi hỏi sự phối hợp với bộ phận CNTT lâm sàng, tích hợp EHR và các quy trình kiểm soát nhiễm khuẩn. Vuzix hợp tác với các đơn vị tích hợp hệ thống có kinh nghiệm trong môi trường lâm sàng để đáp ứng những yêu cầu này.',
		'Secure, rugged AR systems for mission-critical environments. Waveguide-based AR solutions for situational awareness, training, and field operations.' => 'Hệ thống AR bảo mật, bền chắc cho các môi trường trọng yếu. Giải pháp AR dựa trên ống dẫn sóng cho nhận thức tình huống, đào tạo và hoạt động thực địa.',
		'Scalable AR for frontline workforces. Smart glasses solutions for logistics, manufacturing, field service, and remote assistance.' => 'Giải pháp AR có khả năng mở rộng cho lực lượng lao động tuyến đầu. Giải pháp kính thông minh cho logistics, sản xuất, dịch vụ hiện trường và hỗ trợ từ xa.',
		'APPLICATIONS' => 'ỨNG DỤNG',
		'Enterprise' => 'Doanh nghiệp',
		'Waveguide-based smart glasses and' => 'Kính thông minh dựa trên ống dẫn sóng và',
		'OEM systems for the front line' => 'hệ thống OEM cho tuyến đầu',
		'~30 yrs' => '~30 năm',
		'Wearable optics experience' => 'Kinh nghiệm quang học cho thiết bị đeo',
		'Off-the-shelf designs' => 'Thiết kế có sẵn',
		'Visually connected workforce' => 'Lực lượng lao động được kết nối trực quan',
		'Vuzix supports enterprise organizations deploying AI smart glasses to improve efficiency, accuracy, and real-time access to information across logistics, manufacturing, field service, and remote assistance workflows.' => 'Vuzix hỗ trợ các tổ chức doanh nghiệp triển khai kính thông minh AI để nâng cao hiệu quả, độ chính xác và khả năng truy cập thông tin theo thời gian thực trong các quy trình logistics, sản xuất, dịch vụ hiện trường và hỗ trợ từ xa.',
		'Built on waveguide optics and configurable OEM platforms, Vuzix technologies enable hands-free access to instructions, digital systems, and remote expertise in operational environments where mobility and productivity are critical.' => 'Được xây dựng trên nền quang học ống dẫn sóng và các nền tảng OEM có thể cấu hình, công nghệ Vuzix cho phép truy cập rảnh tay vào hướng dẫn, hệ thống số và chuyên gia từ xa trong các môi trường vận hành nơi tính di động và năng suất là yếu tố then chốt.',
		'Rather than standalone devices, these systems are designed as part of an integrated AI ecosystem that connects workers, information systems, and operational processes.' => 'Không chỉ là các thiết bị đơn lẻ, những hệ thống này được thiết kế như một phần của hệ sinh thái AI tích hợp, kết nối người lao động, hệ thống thông tin và các quy trình vận hành.',
		'ENTERPRISE USE CASES' => 'TRƯỜNG HỢP ỨNG DỤNG DOANH NGHIỆP',
		'Connected workflows across industrial operations' => 'Quy trình làm việc được kết nối trong các hoạt động công nghiệp',
		'Warehouse &amp; Logistics' => 'Kho vận &amp; Logistics',
		'Faster, hands-free fulfillment operations' => 'Hoạt động xử lý đơn hàng nhanh hơn, rảnh tay',
		'AI smart glasses support picking, packing, sorting, and inventory workflows by delivering real-time visual instructions directly in the user\'s field of view.' => 'Kính thông minh AI hỗ trợ các quy trình lấy hàng, đóng gói, phân loại và quản lý kho bằng cách hiển thị hướng dẫn hình ảnh theo thời gian thực ngay trong tầm nhìn của người dùng.',
		'This helps reduce errors, improve throughput, and streamline warehouse operations.' => 'Điều này giúp giảm sai sót, tăng năng suất và tinh gọn hoạt động kho vận.',
		'Manufacturing &amp; Assembly' => 'Sản xuất &amp; Lắp ráp',
		'Guided production and quality support' => 'Hỗ trợ sản xuất và kiểm soát chất lượng có hướng dẫn',
		'Wearable display systems provide step-by-step assembly instructions, quality checks, and digital overlays to support precision manufacturing and reduce rework.' => 'Hệ thống hiển thị đeo được cung cấp hướng dẫn lắp ráp từng bước, kiểm tra chất lượng và lớp phủ hình ảnh số để hỗ trợ sản xuất chính xác và giảm việc phải làm lại.',
		'Operators can access critical data without interrupting workflow or shifting focus away from equipment.' => 'Người vận hành có thể truy cập dữ liệu quan trọng mà không làm gián đoạn quy trình làm việc hay phải rời mắt khỏi thiết bị.',
		'Field Service &amp; Maintenance' => 'Dịch vụ hiện trường &amp; Bảo trì',
		'On-site guidance and digital workflows' => 'Hướng dẫn tại hiện trường và quy trình số',
		'Technicians use AI systems to access service instructions, diagnostics, and equipment data in real time during installations, repairs, and maintenance tasks.' => 'Kỹ thuật viên sử dụng hệ thống AI để truy cập hướng dẫn dịch vụ, chẩn đoán và dữ liệu thiết bị theo thời gian thực trong quá trình lắp đặt, sửa chữa và bảo trì.',
		'This improves first-time fix rates and reduces downtime in complex environments.' => 'Điều này giúp tăng tỷ lệ sửa chữa thành công ngay lần đầu và giảm thời gian ngừng hoạt động trong các môi trường phức tạp.',
		'Remote Assistance' => 'Hỗ trợ từ xa',
		'Expert support in real time' => 'Hỗ trợ chuyên gia theo thời gian thực',
		'Intelligent platforms enable remote experts to see what field workers see and provide live guidance through annotations, instructions, and visual overlays.' => 'Các nền tảng thông minh cho phép chuyên gia từ xa thấy chính xác những gì người lao động tại hiện trường đang thấy và đưa ra hướng dẫn trực tiếp qua chú thích, chỉ dẫn và lớp phủ hình ảnh.',
		'This reduces travel requirements and improves response times for technical support.' => 'Điều này giúp giảm nhu cầu di chuyển và cải thiện thời gian phản hồi cho hỗ trợ kỹ thuật.',
		'OEM PLATFORM APPROACH' => 'PHƯƠNG PHÁP NỀN TẢNG OEM',
		'Built on a scalable foundation' => 'Được xây dựng trên một nền tảng có khả năng mở rộng',
		'Enterprise smart glasses solutions from Vuzix are built on a shared waveguide and OEM platform architecture, enabling consistent performance across different workflows while supporting customization for specific operational needs. This approach allows organizations to deploy and integrate AI across multiple functions without redesigning core system components.' => 'Các giải pháp kính thông minh doanh nghiệp của Vuzix được xây dựng trên một kiến trúc ống dẫn sóng và nền tảng OEM chung, giúp đảm bảo hiệu năng ổn định trên nhiều quy trình làm việc khác nhau, đồng thời hỗ trợ tùy chỉnh theo nhu cầu vận hành cụ thể. Cách tiếp cận này cho phép các tổ chức triển khai và tích hợp AI trên nhiều chức năng khác nhau mà không cần thiết kế lại các linh kiện hệ thống cốt lõi.',
		'VALUE FOR ENTERPRISE' => 'GIÁ TRỊ CHO DOANH NGHIỆP',
		'Operational efficiency through wearable computing' => 'Hiệu quả vận hành thông qua công nghệ điện toán đeo được',
		'Vuzix enables the connected frontline with lightweight AI smart glasses and an ecosystem of apps that drive modern workforce efficiencies.' => 'Vuzix giúp kết nối lực lượng lao động tuyến đầu bằng kính thông minh AI nhẹ cùng hệ sinh thái ứng dụng thúc đẩy hiệu suất của lực lượng lao động hiện đại.',
		'Improved workforce productivity' => 'Nâng cao năng suất lao động',
		'Reduced operational errors' => 'Giảm sai sót vận hành',
		'Faster training and onboarding' => 'Đào tạo và hội nhập nhân viên nhanh hơn',
		'Real-time access to digital systems' => 'Truy cập hệ thống số theo thời gian thực',
		'Hands-free workflow execution' => 'Thực hiện quy trình làm việc rảnh tay',
		'Which Vuzix smart glasses fit my workflow?' => 'Kính thông minh Vuzix nào phù hợp với quy trình làm việc của tôi?',
		'Match the device to environment: the LX1 fits warehouse and industrial workflows with a 10-hour battery, freezer-rated design, and rugged construction; the M400 fits varied workflows (field service, manufacturing, inspections, healthcare) with hot-swappable batteries and all-purpose capabilities; the Remote Assist Kit best fits any workflow needing live remote expert guidance through Microsoft Teams or Zoom. The Pick &amp; Pack Validation Program offers a structured evaluation specifically for warehouse pick-pack-sort operations before broader deployment.' => 'Hãy chọn thiết bị phù hợp với môi trường: LX1 phù hợp với quy trình kho vận và công nghiệp nhờ pin dùng được 10 giờ, thiết kế chịu được nhiệt độ đông lạnh và cấu tạo bền chắc; M400 phù hợp với nhiều quy trình khác nhau (dịch vụ hiện trường, sản xuất, kiểm tra, y tế) nhờ pin có thể thay nóng và khả năng đa dụng; Remote Assist Kit phù hợp nhất với bất kỳ quy trình nào cần hướng dẫn từ chuyên gia trực tiếp qua Microsoft Teams hoặc Zoom. Chương trình Đánh giá Pick &amp; Pack cung cấp một quy trình đánh giá có cấu trúc dành riêng cho hoạt động lấy-đóng gói-phân loại tại kho trước khi triển khai rộng hơn.',
		'Can my IT team manage Vuzix smart glasses like other enterprise devices?' => 'Đội ngũ CNTT của tôi có thể quản lý kính thông minh Vuzix như các thiết bị doanh nghiệp khác không?',
		'Yes. Vuzix smart glasses run Android and support common enterprise mobile device management platforms, allowing IT teams to provision, update, and secure devices through familiar tools. The open SDK and camera APIs also let internal development teams or ISV partners build custom enterprise applications, and Vuzix works with system integrators for large multi-site deployments.' => 'Có. Kính thông minh Vuzix chạy trên hệ điều hành Android và hỗ trợ các nền tảng quản lý thiết bị di động doanh nghiệp phổ biến, cho phép đội ngũ CNTT cấp phát, cập nhật và bảo mật thiết bị bằng các công cụ đã quen thuộc. SDK mở và API camera cũng cho phép các nhóm phát triển nội bộ hoặc đối tác ISV xây dựng ứng dụng doanh nghiệp tùy chỉnh, và Vuzix hợp tác với các đơn vị tích hợp hệ thống cho các triển khai quy mô lớn, nhiều địa điểm.',
		'What\'s the difference between Vuzix\'s enterprise smart glasses and the Ultralite OEM Platform?' => 'Sự khác biệt giữa kính thông minh doanh nghiệp của Vuzix và Nền tảng OEM Ultralite là gì?',
		'Vuzix enterprise smart glasses (M400, LX1, Remote Assist Kit) are finished products that organizations buy and deploy directly to their workforce. The Ultralite OEM Platform is a series of reference designs that companies license to build from and brand their own smart glasses products. For most enterprises adopting smart glasses for warehouse, field service, or manufacturing workflows, the finished M400 or LX1 is the right path; Ultralite is for companies whose business is building branded AI smart glasses devices.' => 'Kính thông minh doanh nghiệp của Vuzix (M400, LX1, Remote Assist Kit) là các sản phẩm hoàn chỉnh mà các tổ chức mua và triển khai trực tiếp cho lực lượng lao động của mình. Nền tảng OEM Ultralite là một dòng thiết kế mẫu mà các công ty được cấp phép để phát triển và xây dựng sản phẩm kính thông minh mang thương hiệu riêng của họ. Đối với hầu hết doanh nghiệp áp dụng kính thông minh cho quy trình kho vận, dịch vụ hiện trường hoặc sản xuất, M400 hoặc LX1 hoàn chỉnh là lựa chọn phù hợp; Ultralite dành cho các công ty có hoạt động kinh doanh là xây dựng thiết bị kính thông minh AI mang thương hiệu riêng.',
		'Scale AI across your operations' => 'Mở rộng AI trên toàn bộ hoạt động của bạn',
		'Vuzix works with enterprise organizations to define workflow requirements, evaluate display system configurations, and support deployment at scale.' => 'Vuzix hợp tác với các tổ chức doanh nghiệp để xác định yêu cầu quy trình làm việc, đánh giá các cấu hình hệ thống hiển thị và hỗ trợ triển khai ở quy mô lớn.',
		'Explore Ultralite OEM Platform →' => 'Khám phá Nền tảng OEM Ultralite →',
		'Ensure a successful and speedy MDM deployment that meets your organization\'s standards' => 'Đảm bảo triển khai MDM thành công và nhanh chóng, đáp ứng tiêu chuẩn của tổ chức bạn',
		'OVERVIEW' => 'TỔNG QUAN',
		'Accelerate deployment with Vuzix' => 'Đẩy nhanh triển khai với Vuzix',
		'Protecting physical assets and corporate information in mobile device management (MDM) has grown increasingly complex, putting strain on already overloaded IT teams.' => 'Việc bảo vệ tài sản vật lý và thông tin doanh nghiệp trong quản lý thiết bị di động (MDM) ngày càng trở nên phức tạp, gây thêm áp lực cho các đội ngũ CNTT đã quá tải.',
		'At Vuzix, we help streamline the deployment and management of Vuzix AR solutions. Flexible configuration options ensure security and systems efficiency, letting you make the most of Vuzix smart glasses technology quickly and easily.' => 'Tại Vuzix, chúng tôi giúp tinh gọn việc triển khai và quản lý các giải pháp AR của Vuzix. Các tùy chọn cấu hình linh hoạt đảm bảo an ninh và hiệu quả hệ thống, giúp bạn khai thác tối đa công nghệ kính thông minh Vuzix một cách nhanh chóng và dễ dàng.',
		'TECHNICAL FLEXIBILITY' => 'TÍNH LINH HOẠT KỸ THUẬT',
		'Device configuration and compatibility' => 'Cấu hình và khả năng tương thích thiết bị',
		'Flexible Configuration' => 'Cấu hình linh hoạt',
		'Customize the configuration of your devices prior to shipment or via updates. Options include OS versions, system settings, Android-based applications, security, enrollment modes, and proprietary enhancements.' => 'Tùy chỉnh cấu hình thiết bị trước khi xuất hàng hoặc thông qua các bản cập nhật. Các tùy chọn bao gồm phiên bản hệ điều hành, cài đặt hệ thống, ứng dụng nền Android, bảo mật, chế độ đăng ký thiết bị và các cải tiến độc quyền.',
		'Training &amp; Readiness' => 'Đào tạo &amp; Sẵn sàng triển khai',
		'Engage Vuzix to provide training and procedures for your users to get them up and running quickly with our smart glasses. Vuzix can make sure your organizational systems are fully prepped for success.' => 'Hợp tác với Vuzix để nhận đào tạo và quy trình hướng dẫn cho người dùng của bạn, giúp họ nhanh chóng làm quen và sử dụng thành thục kính thông minh của chúng tôi. Vuzix có thể đảm bảo hệ thống của tổ chức bạn đã được chuẩn bị đầy đủ để đạt thành công.',
		'MDM Compatibility' => 'Khả năng tương thích MDM',
		'Rest assured that Vuzix is certified to support major MDM apps:' => 'Bạn có thể hoàn toàn an tâm vì Vuzix được chứng nhận hỗ trợ các ứng dụng MDM lớn:',
		', and' => ', và',
		'. And that\'s just a few of the many solutions we support.' => '. Và đó chỉ là một số trong rất nhiều giải pháp mà chúng tôi hỗ trợ.',
		'Trusted Partners' => 'Đối tác tin cậy',
		'Integrated with leading platforms' => 'Tích hợp với các nền tảng hàng đầu',
		'Vuzix solutions integrate with the software, mobility, and communications platforms your teams already use.' => 'Các giải pháp Vuzix tích hợp với phần mềm, nền tảng di động và truyền thông mà nhóm của bạn đang sử dụng.',
		'FRONTLINE ASSIST' => 'HỖ TRỢ TUYẾN ĐẦU',
		'Vuzix M400 for the smart workforce' => 'Vuzix M400 cho lực lượng lao động thông minh',
		'Vuzix M400 smart glasses boost productivity by allowing workers to:' => 'Kính thông minh Vuzix M400 giúp tăng năng suất bằng cách cho phép người lao động:',
		'Access supporting information via augmented reality (AR) with audio and visual overlays' => 'Truy cập thông tin hỗ trợ qua thực tế tăng cường (AR) với lớp phủ âm thanh và hình ảnh',
		'Record and store all of their actions Livestream remote support, heads-up and hands-free' => 'Ghi lại và lưu trữ toàn bộ hoạt động của họ, phát trực tiếp để nhận hỗ trợ từ xa, hiển thị heads-up và hoàn toàn rảnh tay',
		'Lightweight and durable, Vuzix M400 smart glasses are the most wearable, powerful, and versatile headworn computer on the market.' => 'Nhẹ và bền, kính thông minh Vuzix M400 là máy tính đội đầu dễ đeo, mạnh mẽ và linh hoạt nhất trên thị trường.',
		'Explore M400' => 'Khám phá M400',
		'Application Domains' => 'Lĩnh vực ứng dụng',
		'Provisioning services for industries' => 'Dịch vụ cấp phát cho các ngành',
		'Integrating Vuzix smart glasses into healthcare can drive better communication and clinical decision-making, improving the quality of care across your organization.' => 'Tích hợp kính thông minh Vuzix vào lĩnh vực y tế có thể thúc đẩy giao tiếp và ra quyết định lâm sàng tốt hơn, nâng cao chất lượng chăm sóc trên toàn tổ chức của bạn.',
		'Vuzix Mobile Device Management lets you maintain HIPAA compliance while quickly getting your medical staff online using Vuzix technology, allowing them to stay 100% focused on patient care.' => 'Vuzix Mobile Device Management giúp bạn duy trì tuân thủ HIPAA trong khi nhanh chóng đưa nhân viên y tế của bạn hoạt động trực tuyến bằng công nghệ Vuzix, cho phép họ tập trung 100% vào việc chăm sóc bệnh nhân.',
		'Explore Healthcare →' => 'Khám phá Y tế →',
		'With Vuzix AR smart glasses, you can provide heads-up, hands-free support and training directly in your workers\' field of view. This helps speed up production, reduce errors, and improve safety.' => 'Với kính thông minh AR của Vuzix, bạn có thể cung cấp hỗ trợ và đào tạo dạng heads-up, rảnh tay ngay trong tầm nhìn của người lao động. Điều này giúp đẩy nhanh sản xuất, giảm sai sót và nâng cao an toàn.',
		'Leverage our provisioning services to configure your smart glasses remotely. Or have them pre-loaded with mobile device management software installed, and scan a secure code to bring your smart glasses online.' => 'Sử dụng dịch vụ cấp phát của chúng tôi để cấu hình kính thông minh từ xa. Hoặc để kính được cài đặt sẵn phần mềm quản lý thiết bị di động và quét mã bảo mật để đưa kính thông minh của bạn vào hoạt động trực tuyến.',
		'Vuzix AR smart glasses connect field workers to AI databases and remote experts in real time, making it possible to reduce downtime and resolve issues in minutes instead of days.' => 'Kính thông minh AR của Vuzix kết nối người lao động tại hiện trường với cơ sở dữ liệu AI và chuyên gia từ xa theo thời gian thực, giúp giảm thời gian ngừng hoạt động và giải quyết vấn đề trong vài phút thay vì vài ngày.',
		'Reduce downtime even further by using Vuzix Mobile Device Management to accelerate the deployment of our smart glasses across your business, while optimizing their functionality and security.' => 'Giảm thời gian ngừng hoạt động hơn nữa bằng cách sử dụng Vuzix Mobile Device Management để đẩy nhanh việc triển khai kính thông minh của chúng tôi trên toàn doanh nghiệp của bạn, đồng thời tối ưu hóa tính năng và bảo mật của thiết bị.',
		'Explore Field Service →' => 'Khám phá Dịch vụ hiện trường →',
		'WHITE PAPER' => 'SÁCH TRẮNG',
		'Vuzix Mobility Solutions' => 'Giải pháp Di động Vuzix',
		'Explore how Vuzix can help you manage full-solution integrations between your headworn hardware and an ecosystem of ancillary devices, software applications, and security policies.' => 'Khám phá cách Vuzix có thể giúp bạn quản lý các tích hợp giải pháp toàn diện giữa phần cứng đội đầu và một hệ sinh thái các thiết bị phụ trợ, ứng dụng phần mềm và chính sách bảo mật.',
		'Read White Paper →' => 'Đọc Sách trắng →',
		'Deploy faster and scale with confidence using all-in-one solutions kits' => 'Triển khai nhanh hơn và mở rộng quy mô một cách tự tin với các bộ giải pháp trọn gói',
		'Talk to our solutions team →' => 'Trò chuyện với đội ngũ giải pháp của chúng tôi →',
		'Explore Remote Assist Kit →' => 'Khám phá Remote Assist Kit →',
		'~30yrs' => '~30 năm',
		'Wearable computing expertise' => 'Chuyên môn về điện toán đeo được',
		'Enterprise Grade' => 'Chuẩn doanh nghiệp',
		'Security and device management support' => 'Hỗ trợ bảo mật và quản lý thiết bị',
		'Headworn systems shipped' => 'Hệ thống đội đầu đã xuất hàng',
		'Hours of operational use' => 'Số giờ sử dụng vận hành',
		'Designed for Real-World Workflows' => 'Được thiết kế cho quy trình làm việc thực tế',
		'Our enterprise solutions kits are designed to simplify smart glasses adoption. Each bundle is built around a proven frontline use case and includes everything required to deploy, optimize, and scale—all in one rugged kit.' => 'Các bộ giải pháp doanh nghiệp của chúng tôi được thiết kế để đơn giản hóa việc áp dụng kính thông minh. Mỗi bộ được xây dựng xung quanh một trường hợp ứng dụng tuyến đầu đã được kiểm chứng và bao gồm mọi thứ cần thiết để triển khai, tối ưu hóa và mở rộng quy mô—tất cả trong một bộ sản phẩm bền chắc.',
		'Peek Inside (What\'s in a kit)' => 'Xem bên trong (Bộ sản phẩm gồm những gì)',
		'What\'s in a Vuzix Solutions Kit' => 'Bộ Giải pháp Vuzix gồm những gì',
		'Each highly secure solution kit includes:' => 'Mỗi bộ giải pháp bảo mật cao bao gồm:',
		'• Vuzix M400 or LX1 smart glasses' => '• Kính thông minh Vuzix M400 hoặc LX1',
		'• Rugged case, cables, batteries, and mounting accessories' => '• Hộp đựng bền chắc, dây cáp, pin và phụ kiện gắn kèm',
		'• 12 months of software and deployment support included' => '• Bao gồm 12 tháng hỗ trợ phần mềm và triển khai',
		'• All kits are priced lower than their unlocked, hardware-only alternatives, delivering more value with less risk.' => '• Tất cả các bộ sản phẩm đều có giá thấp hơn so với các lựa chọn phần cứng đơn lẻ, không kèm gói dịch vụ, mang lại nhiều giá trị hơn với rủi ro thấp hơn.',
		'Accelerated Adoption' => 'Áp dụng nhanh chóng',
		'From unboxing to impact' => 'Từ lúc mở hộp đến khi tạo ra hiệu quả',
		'Traditional smart glasses deployments can sometimes require a heavy lift from client organizations: hardware setup and security hurdles, software integrations, dedicated training, and SMEs to support. Vuzix Solutions removes the rollout burden with purpose-built, all-in-one kits designed to deliver value on day one.' => 'Việc triển khai kính thông minh theo cách truyền thống đôi khi đòi hỏi rất nhiều công sức từ các tổ chức khách hàng: thiết lập phần cứng và các rào cản bảo mật, tích hợp phần mềm, đào tạo chuyên biệt và cần chuyên gia hỗ trợ. Vuzix Solutions loại bỏ gánh nặng triển khai bằng các bộ sản phẩm trọn gói, được thiết kế chuyên biệt để mang lại giá trị ngay từ ngày đầu tiên.',
		'With Vuzix Solutions, you get:' => 'Với Vuzix Solutions, bạn sẽ nhận được:',
		'Faster time-to-value' => 'Thời gian đạt được giá trị nhanh hơn',
		'A highly secure enterprise device with minimal IT overhead' => 'Thiết bị doanh nghiệp bảo mật cao với chi phí quản lý CNTT tối thiểu',
		'Simpler onboarding for frontline teams' => 'Quy trình hội nhập đơn giản hơn cho các nhóm tuyến đầu',
		'A clear path from pilot to scaled, optimized rollout' => 'Một con đường rõ ràng từ thử nghiệm đến triển khai mở rộng, được tối ưu hóa',
		'Purpose-built around critical use cases' => 'Được thiết kế chuyên biệt xung quanh các trường hợp ứng dụng quan trọng',
		'No software sourcing. No guesswork. Just real outcomes.' => 'Không cần tìm nguồn phần mềm. Không cần đoán mò. Chỉ có kết quả thực tế.',
		'For field workers needing to share their view for handsfree, remote mentoring.' => 'Dành cho người lao động tại hiện trường cần chia sẻ tầm nhìn của mình để được cố vấn từ xa, rảnh tay.',
		'For logistics professionals needing accurate, efficient Vision + Voice handsfree picking and parcel storing.' => 'Dành cho các chuyên viên logistics cần lấy hàng và lưu trữ hàng hóa rảnh tay bằng Vision + Voice một cách chính xác, hiệu quả.',
		'Explore Pick &amp; Pack Kit →' => 'Khám phá Pick &amp; Pack Kit →',
		'Take the next step' => 'Thực hiện bước tiếp theo',
		'See how easy it is to integrate Vuzix smart glasses into workflows to improve quality, streamline production, and reduce costs.' => 'Xem cách dễ dàng tích hợp kính thông minh Vuzix vào quy trình làm việc để nâng cao chất lượng, tinh gọn sản xuất và giảm chi phí.',
		'Talk to our team →' => 'Trò chuyện với đội ngũ của chúng tôi →',
		'Get support' => 'Nhận hỗ trợ',
		'Need help? Our experienced support team is ready to answer questions, resolve technical issues, and help you get the most from your Vuzix smart glasses.' => 'Cần trợ giúp? Đội ngũ hỗ trợ giàu kinh nghiệm của chúng tôi luôn sẵn sàng giải đáp thắc mắc, xử lý các vấn đề kỹ thuật và giúp bạn khai thác tối đa kính thông minh Vuzix.',
		'Contact Support' => 'Liên hệ Hỗ trợ',
		'Legacy Products' => 'Sản phẩm cũ',
		'Get help with discontinued Vuzix products such as M300, M400C, Blade, and Blade Upgraded.' => 'Nhận hỗ trợ cho các sản phẩm Vuzix đã ngừng sản xuất như M300, M400C, Blade và Blade Upgraded.',
		'Release notes' => 'Ghi chú phát hành',
		'See All' => 'Xem tất cả',
		'M400 Version 3.1.2 Available' => 'Đã có M400 Phiên bản 3.1.2',
		'January 02, 2025' => '02 tháng 01, 2025',
		'M400 Version 4.0.3 Available' => 'Đã có M400 Phiên bản 4.0.3',
		'M400 Version 4.0.1 Available' => 'Đã có M400 Phiên bản 4.0.1',
		'October 17, 2024' => '17 tháng 10, 2024',
		'Blade 2 Version 1.2.1 Available' => 'Đã có Blade 2 Phiên bản 1.2.1',
		'M400 Version 3.1.1 Available' => 'Đã có M400 Phiên bản 3.1.1',
		'September 05, 2024' => '05 tháng 09, 2024',
		'M400 Version 3.1.0 Available' => 'Đã có M400 Phiên bản 3.1.0',
		'June 24, 2024' => '24 tháng 06, 2024',
		'Learn how you can access Vuzix resources to build your own applications for our products. Access our Developer\'s Center for information on our family of devices and developers\' kits.' => 'Tìm hiểu cách bạn có thể truy cập các tài nguyên của Vuzix để xây dựng ứng dụng riêng cho sản phẩm của chúng tôi. Truy cập Trung tâm Nhà phát triển của chúng tôi để biết thông tin về dòng thiết bị và các bộ công cụ phát triển.',
		'Vuzix strives to ensure you have the best customer experience possible. For purchases made through Vuzix\' website (' => 'Vuzix luôn nỗ lực để mang lại cho bạn trải nghiệm khách hàng tốt nhất có thể. Đối với các đơn hàng mua qua website của Vuzix (',
		'REACH RA' => 'LIÊN HỆ',
		'Full-stack engineering for AI smart glasses,' => 'Kỹ thuật toàn diện cho kính thông minh AI,',
		'covering optical systems, hardware design,' => 'bao gồm hệ thống quang học, thiết kế phần cứng,',
		'and production-ready development' => 'và phát triển sẵn sàng cho sản xuất',
		'Engineering for optical systems that scale' => 'Kỹ thuật cho các hệ thống quang học có khả năng mở rộng',
		'Vuzix provides full-stack engineering support for companies developing AI smart glasses, integrating waveguide optics, display systems, electronics, and mechanical design into cohesive, production-ready devices.' => 'Vuzix cung cấp hỗ trợ kỹ thuật toàn diện cho các công ty phát triển kính thông minh AI, tích hợp quang học ống dẫn sóng, hệ thống hiển thị, điện tử và thiết kế cơ khí thành các thiết bị gắn kết, sẵn sàng cho sản xuất.',
		'Our engineering teams work across optical design, system architecture, firmware, and hardware' => 'Các nhóm kỹ thuật của chúng tôi làm việc trên nhiều lĩnh vực: thiết kế quang học, kiến trúc hệ thống, firmware và phần cứng',
		'development, ensuring that each component functions as part of a complete system rather than in isolation.' => 'phát triển, đảm bảo mỗi thành phần hoạt động như một phần của hệ thống hoàn chỉnh thay vì riêng lẻ.',
		'Because Vuzix develops its core waveguide technology in-house, optical performance, industrial design, and system integration are aligned early in the development process.' => 'Vì Vuzix phát triển công nghệ ống dẫn sóng cốt lõi trong nội bộ, hiệu suất quang học, thiết kế công nghiệp và tích hợp hệ thống được đồng bộ ngay từ giai đoạn đầu của quá trình phát triển.',
		'This reduces design friction, accelerates iteration, and helps avoid costly redesigns later in the program.' => 'Điều này giảm thiểu xung đột trong thiết kế, đẩy nhanh quá trình lặp lại và giúp tránh việc phải thiết kế lại tốn kém ở giai đoạn sau của chương trình.',
		'Programs are structured to move efficiently through key development stages, with a focus on performance validation, manufacturability, and readiness for scaled production.' => 'Các chương trình được cấu trúc để tiến triển hiệu quả qua các giai đoạn phát triển quan trọng, tập trung vào việc xác nhận hiệu suất, khả năng sản xuất và sự sẵn sàng cho sản xuất quy mô lớn.',
		'Engagement Models' => 'Mô hình hợp tác',
		'Two ways to work with Vuzix engineering' => 'Hai cách để hợp tác với bộ phận kỹ thuật của Vuzix',
		'Waveguide Integration' => 'Tích hợp ống dẫn sóng',
		'Engineering support for optical systems' => 'Hỗ trợ kỹ thuật cho hệ thống quang học',
		'Vuzix works with OEM partners to integrate waveguide optics into wearable devices, either using Vuzix Core™ configurations or fully custom-designed waveguides tailored to specific performance and form factor requirements.' => 'Vuzix hợp tác với các đối tác OEM để tích hợp quang học ống dẫn sóng vào các thiết bị đeo được, sử dụng các cấu hình Vuzix Core™ hoặc ống dẫn sóng được thiết kế hoàn toàn tùy chỉnh theo yêu cầu cụ thể về hiệu suất và kiểu dáng.',
		'Core waveguides provide a fast path for evaluation and system integration, while custom waveguide programs allow optimization across field of view, brightness, display engine compatibility, and device architecture.' => 'Ống dẫn sóng Core cung cấp con đường nhanh để đánh giá và tích hợp hệ thống, trong khi các chương trình ống dẫn sóng tùy chỉnh cho phép tối ưu hóa trên các yếu tố như trường nhìn, độ sáng, khả năng tương thích với engine hiển thị và kiến trúc thiết bị.',
		'Waveguides can be integrated into a range of platforms, including Vuzix Ultralite OEM systems or partner-designed hardware architectures.' => 'Ống dẫn sóng có thể được tích hợp vào nhiều nền tảng, bao gồm các hệ thống Vuzix Ultralite OEM hoặc các kiến trúc phần cứng do đối tác thiết kế.',
		'Our engineering teams align optical design, display selection, and mechanical constraints to ensure consistent performance through prototyping and into production.' => 'Các nhóm kỹ thuật của chúng tôi đồng bộ thiết kế quang học, lựa chọn màn hình hiển thị và các hạn chế cơ khí để đảm bảo hiệu suất nhất quán từ giai đoạn tạo mẫu đến sản xuất.',
		'Scope' => 'Phạm vi',
		'Core and custom waveguide design and integration into AR systems' => 'Thiết kế và tích hợp ống dẫn sóng Core và tùy chỉnh vào các hệ thống AR',
		'Optical design, display integration, system architecture alignment' => 'Thiết kế quang học, tích hợp màn hình hiển thị, đồng bộ kiến trúc hệ thống',
		'Vuzix Core Configurations' => 'Các cấu hình Vuzix Core',
		'Prescription-ready Vuzix Ultralite OEM Platform or partner hardware systems' => 'Nền tảng Vuzix Ultralite OEM tương thích kính đơn thuốc hoặc các hệ thống phần cứng của đối tác',
		'U.S.-based production, ISO 9001:2015 certified' => 'Sản xuất tại Hoa Kỳ, đạt chứng nhận ISO 9001:2015',
		'Platform-based reference design' => 'Thiết kế tham chiếu dựa trên nền tảng',
		'Rapid smart glasses customization options' => 'Các tùy chọn tùy chỉnh kính thông minh nhanh chóng',
		'For partners building branded AI smart glasses, Vuzix provides full-device ODM development covering industrial design, optical system integration, electrical and mechanical engineering, firmware, and display integration.' => 'Đối với các đối tác xây dựng kính thông minh AI mang thương hiệu riêng, Vuzix cung cấp dịch vụ phát triển ODM toàn thiết bị, bao gồm thiết kế công nghiệp, tích hợp hệ thống quang học, kỹ thuật điện và cơ khí, firmware và tích hợp màn hình hiển thị.',
		'Programs can be built on the Vuzix Ultralite OEM Platform for faster time to prototype, or developed as fully custom smart glasses architectures designed around specific product and market requirements.' => 'Các chương trình có thể được xây dựng trên Nền tảng Vuzix Ultralite OEM để rút ngắn thời gian tạo mẫu, hoặc được phát triển dưới dạng kiến trúc kính thông minh hoàn toàn tùy chỉnh, được thiết kế theo yêu cầu cụ thể của sản phẩm và thị trường.',
		'Vuzix also coordinates a global ecosystem of manufacturing and technology partners, managing suppliers and production workflows within a unified program structure.' => 'Vuzix cũng điều phối một hệ sinh thái toàn cầu gồm các đối tác sản xuất và công nghệ, quản lý nhà cung cấp và quy trình sản xuất trong một cấu trúc chương trình thống nhất.',
		'Full-device ODM development or platform-based smart glasses programs' => 'Phát triển ODM toàn thiết bị hoặc các chương trình kính thông minh dựa trên nền tảng',
		'Engineering' => 'Kỹ thuật',
		'Industrial, mechanical, electrical, firmware, and system integration' => 'Thiết kế công nghiệp, cơ khí, điện, firmware và tích hợp hệ thống',
		'Vuzix Ultralite OEM Platform or fully custom device architectures' => 'Nền tảng Vuzix Ultralite OEM hoặc các kiến trúc thiết bị hoàn toàn tùy chỉnh',
		'Prototyping' => 'Tạo mẫu',
		'Rapid iterative builds with integrated engineering and validation' => 'Xây dựng lặp lại nhanh với kỹ thuật và xác nhận tích hợp',
		'Ecosystem' => 'Hệ sinh thái',
		'Managed network of global manufacturing and technology partners' => 'Mạng lưới được quản lý gồm các đối tác sản xuất và công nghệ toàn cầu',
		'Process' => 'Quy trình',
		'How we work with you' => 'Cách chúng tôi hợp tác với bạn',
		'Phase 1' => 'Giai đoạn 1',
		'Heads-up information for operational visibility' => 'Thông tin hiển thị trực tiếp cho khả năng quan sát vận hành',
		'Define product requirements, use cases, and technical constraints, including optical performance, form factor, and display system selection.' => 'Xác định yêu cầu sản phẩm, trường hợp sử dụng và các hạn chế kỹ thuật, bao gồm hiệu suất quang học, kiểu dáng và lựa chọn hệ thống hiển thị.',
		'Early tradeoff analysis aligns feasibility, performance targets, and development priorities.' => 'Phân tích đánh đổi sớm giúp đồng bộ tính khả thi, mục tiêu hiệu suất và các ưu tiên phát triển.',
		'Phase 2' => 'Giai đoạn 2',
		'Design &amp; engineering' => 'Thiết kế &amp; kỹ thuật',
		'Develop the full system architecture across optical, electrical, and mechanical design, along with firmware and display integration.' => 'Phát triển kiến trúc hệ thống toàn diện trên các mảng thiết kế quang học, điện và cơ khí, cùng với firmware và tích hợp màn hình hiển thị.',
		'Engineering teams iterate quickly through design validation and prototype builds.' => 'Các nhóm kỹ thuật lặp lại nhanh chóng qua các bước xác nhận thiết kế và xây dựng mẫu thử.',
		'Phase 3' => 'Giai đoạn 3',
		'Rapid prototyping &amp; validation' => 'Tạo mẫu nhanh &amp; xác nhận',
		'Build and iterate on physical prototypes using integrated in-house optical, mechanical, and electronic development capabilities.' => 'Xây dựng và lặp lại trên các mẫu thử vật lý bằng khả năng phát triển quang học, cơ khí và điện tử tích hợp trong nội bộ.',
		'Co-located engineering and manufacturing enable fast turnaround between design, build, and test cycles, allowing rapid refinement of designs.' => 'Kỹ thuật và sản xuất được đặt cùng vị trí giúp rút ngắn thời gian giữa các chu kỳ thiết kế, xây dựng và thử nghiệm, cho phép hoàn thiện thiết kế nhanh chóng.',
		'Phase 4' => 'Giai đoạn 4',
		'Production &amp; lifecycle support' => 'Sản xuất &amp; hỗ trợ vòng đời sản phẩm',
		'Transition validated designs into scaled manufacturing through Vuzix facilities and global partners.' => 'Chuyển đổi các thiết kế đã được xác nhận sang sản xuất quy mô lớn thông qua các cơ sở của Vuzix và đối tác toàn cầu.',
		'Ongoing support includes production optimization, quality control, and system updates as programs evolve.' => 'Hỗ trợ liên tục bao gồm tối ưu hóa sản xuất, kiểm soát chất lượng và cập nhật hệ thống khi các chương trình phát triển.',
		'WHY VUZIX ENGINEERING' => 'TẠI SAO CHỌN KỸ THUẬT VUZIX',
		'A unified approach to optical system development' => 'Một phương pháp thống nhất cho phát triển hệ thống quang học',
		'Vuzix brings optical design, system engineering, and manufacturing coordination together within a single organization, enabling faster iteration and tighter integration across every stage of development.' => 'Vuzix kết hợp thiết kế quang học, kỹ thuật hệ thống và điều phối sản xuất trong một tổ chức duy nhất, giúp lặp lại nhanh hơn và tích hợp chặt chẽ hơn trong mọi giai đoạn phát triển.',
		'Because waveguide optics are designed and manufactured in-house, engineering decisions are grounded in real optical' => 'Vì quang học ống dẫn sóng được thiết kế và sản xuất trong nội bộ, các quyết định kỹ thuật được đặt trên nền tảng quang học thực tế',
		'performance and production constraints from the start. This reduces handoffs, shortens validation cycles, and improves overall system coherence.' => 'và các hạn chế sản xuất ngay từ đầu. Điều này giảm thiểu việc chuyển giao giữa các bộ phận, rút ngắn chu kỳ xác nhận và cải thiện tính nhất quán tổng thể của hệ thống.',
		'For full device programs, Vuzix also coordinates a global network of manufacturing and technology partners, managing complexity across suppliers and production' => 'Đối với các chương trình toàn thiết bị, Vuzix cũng điều phối một mạng lưới toàn cầu gồm các đối tác sản xuất và công nghệ, quản lý sự phức tạp giữa các nhà cung cấp và',
		'workflows so partners can focus on product definition and market strategy.' => 'quy trình sản xuất để đối tác có thể tập trung vào việc xác định sản phẩm và chiến lược thị trường.',
		'The result is a development model that connects design, prototyping, and production into a continuous engineering loop, rather than a sequence of disconnected stages.' => 'Kết quả là một mô hình phát triển kết nối thiết kế, tạo mẫu và sản xuất thành một chu trình kỹ thuật liên tục, thay vì một chuỗi các giai đoạn rời rạc.',
		'Does Vuzix work with my existing engineering and manufacturing partners?' => 'Vuzix có hợp tác với các đối tác kỹ thuật và sản xuất hiện tại của tôi không?',
		'Yes. Vuzix coordinates a global ecosystem of manufacturing and technology partners as part of a client\'s program structure, and can integrate with an OEM partner\'s existing engineering teams, suppliers, and contract manufacturers. The engagement model is flexible — Vuzix can lead full ODM development, support waveguide integration into a partner\'s existing hardware architecture, or provide targeted engineering services around optical design and system integration.' => 'Có. Vuzix điều phối một hệ sinh thái toàn cầu gồm các đối tác sản xuất và công nghệ như một phần trong cấu trúc chương trình của khách hàng, và có thể tích hợp với các nhóm kỹ thuật, nhà cung cấp và nhà sản xuất theo hợp đồng hiện có của đối tác OEM. Mô hình hợp tác rất linh hoạt — Vuzix có thể dẫn dắt toàn bộ quá trình phát triển ODM, hỗ trợ tích hợp ống dẫn sóng vào kiến trúc phần cứng hiện có của đối tác, hoặc cung cấp các dịch vụ kỹ thuật có mục tiêu liên quan đến thiết kế quang học và tích hợp hệ thống.',
		'How does Vuzix\' in-house optics change the engineering timeline?' => 'Quang học nội bộ của Vuzix thay đổi tiến trình kỹ thuật như thế nào?',
		'Because Vuzix designs and manufactures waveguide optics in-house, optical performance, industrial design, and system integration are aligned from the start of engineering rather than after waveguide samples arrive from a third party. This reduces design handoffs, shortens validation cycles, and helps avoid late-stage redesigns that occur when optical constraints surface after mechanical and electrical decisions are locked. The four-phase Vuzix engineering process keeps optical, mechanical, electrical, and firmware development running in parallel rather than sequentially.' => 'Vì Vuzix thiết kế và sản xuất quang học ống dẫn sóng trong nội bộ, hiệu suất quang học, thiết kế công nghiệp và tích hợp hệ thống được đồng bộ ngay từ khi bắt đầu quá trình kỹ thuật, thay vì sau khi nhận được mẫu ống dẫn sóng từ bên thứ ba. Điều này giảm thiểu việc chuyển giao thiết kế, rút ngắn chu kỳ xác nhận và giúp tránh việc phải thiết kế lại ở giai đoạn cuối khi các hạn chế quang học xuất hiện sau khi các quyết định về cơ khí và điện đã được chốt. Quy trình kỹ thuật bốn giai đoạn của Vuzix giúp việc phát triển quang học, cơ khí, điện và firmware diễn ra song song thay vì tuần tự.',
		'What kinds of products has Vuzix engineering helped develop?' => 'Bộ phận kỹ thuật của Vuzix đã hỗ trợ phát triển những loại sản phẩm nào?',
		'Vuzix engineering has supported AR product development across defense (including the Collins Aerospace military helmet shown at CES 2026), enterprise smart glasses (Vuzix\' own M400 and LX1), Tier-1 OEM volume programs, AI reference designs with Himax, and surgical AR platforms like Ohana One. The same engineering foundation supports custom defense programs, consumer eyewear, and enterprise workflow devices depending on program requirements.' => 'Bộ phận kỹ thuật của Vuzix đã hỗ trợ phát triển sản phẩm AR trong lĩnh vực quốc phòng (bao gồm mũ bảo hiểm quân sự của Collins Aerospace được trình diễn tại CES 2026), kính thông minh doanh nghiệp (M400 và LX1 của chính Vuzix), các chương trình sản lượng OEM Tier-1, các thiết kế tham chiếu AI với Himax và các nền tảng AR phẫu thuật như Ohana One. Cùng nền tảng kỹ thuật này hỗ trợ các chương trình quốc phòng tùy chỉnh, kính mắt tiêu dùng và các thiết bị quy trình làm việc doanh nghiệp tùy theo yêu cầu của từng chương trình.',
		'Begin an engineering conversation' => 'Bắt đầu trao đổi về kỹ thuật',
		'Talk to Our Engineering Team →' => 'Trao đổi với nhóm Kỹ thuật của chúng tôi →',
		'Partners by Region' => 'Đối tác theo khu vực',
		'Australia, New Zealand' => 'Úc, New Zealand',
		'Austria' => 'Áo',
		'The Netherlands' => 'Hà Lan',
		'Germany' => 'Đức',
		'Finland' => 'Phần Lan',
		'Italy' => 'Ý',
		'Spain' => 'Tây Ban Nha',
		'Bolivia, Chile, Colombia, Costa Rica,Guatemala, Mexico, Paraguay, Peru, Uruguay' => 'Bolivia, Chile, Colombia, Costa Rica, Guatemala, Mexico, Paraguay, Peru, Uruguay',
		'NORTH AMERICA' => 'BẮC MỸ',
		'JAPAN' => 'NHẬT BẢN',
		'Visit' => 'Truy cập',
		'to see a complete list of Japanese partners.' => 'để xem danh sách đầy đủ các đối tác tại Nhật Bản.',
		'Software Partners' => 'Đối tác phần mềm',
		'Vuzix collaborates with leading AR innovators. Visit the' => 'Vuzix hợp tác với các nhà đổi mới AR hàng đầu. Truy cập',
		'to learn more.' => 'để tìm hiểu thêm.',
		'Partner with us' => 'Hợp tác với chúng tôi',
		'Vuzix is ready to partner with companies who drive innovation.' => 'Vuzix sẵn sàng hợp tác với các công ty thúc đẩy đổi mới.',
		'Contact Us →' => 'Liên hệ với chúng tôi →',
		'Boost Your Team\'s Productivity with AR' => 'Tăng năng suất của nhóm bạn với AR',
		'Connect Experts to the Frontline' => 'Kết nối chuyên gia với tuyến đầu',
		'Vuzix remote support solutions enable frontline workers to collaborate with experts in real time, regardless of location.' => 'Các giải pháp hỗ trợ từ xa của Vuzix cho phép nhân viên tuyến đầu hợp tác với chuyên gia theo thời gian thực, bất kể vị trí địa lý.',
		'Using smart glasses equipped with live audio and video capabilities, workers can share exactly what they see while receiving guidance from remote specialists. This helps organizations resolve issues faster, reduce travel requirements, and improve consistency across distributed operations.' => 'Bằng cách sử dụng kính thông minh được trang bị khả năng âm thanh và video trực tiếp, nhân viên có thể chia sẻ chính xác những gì họ nhìn thấy trong khi nhận hướng dẫn từ các chuyên gia ở xa. Điều này giúp các tổ chức giải quyết vấn đề nhanh hơn, giảm nhu cầu di chuyển và cải thiện tính nhất quán trên các hoạt động phân tán.',
		'From equipment maintenance and technical troubleshooting to training and quality inspections, Vuzix remote support solutions provide immediate access to expertise when and where it\'s needed. Hands-free communication allows workers to stay focused on the task while receiving step-by-step guidance.' => 'Từ bảo trì thiết bị và khắc phục sự cố kỹ thuật đến đào tạo và kiểm tra chất lượng, các giải pháp hỗ trợ từ xa của Vuzix cung cấp quyền truy cập ngay lập tức vào chuyên môn khi và ở nơi cần thiết. Giao tiếp rảnh tay giúp nhân viên tập trung vào công việc trong khi nhận hướng dẫn từng bước.',
		'Designed for enterprise environments, Vuzix helps organizations improve service levels, accelerate knowledge transfer, and support a more connected workforce.' => 'Được thiết kế cho môi trường doanh nghiệp, Vuzix giúp các tổ chức cải thiện mức độ dịch vụ, đẩy nhanh quá trình chuyển giao kiến thức và hỗ trợ lực lượng lao động kết nối chặt chẽ hơn.',
		'SEE WHAT I SEE' => 'XEM ĐIỀU TÔI THẤY',
		'Tap into remote support expertise with Vuzix' => 'Khai thác chuyên môn hỗ trợ từ xa với Vuzix',
		'Improvement in customer satisfaction' => 'Cải thiện sự hài lòng của khách hàng',
		'Our remote support glasses can enhance collaborations, increase productivity, streamline workflows, and improve customer satisfaction. Connect workers on the floor or in the field with moment of need support from experts across your network.' => 'Kính hỗ trợ từ xa của chúng tôi có thể tăng cường sự hợp tác, nâng cao năng suất, tối ưu hóa quy trình làm việc và cải thiện sự hài lòng của khách hàng. Kết nối nhân viên tại nhà máy hoặc ngoài hiện trường với sự hỗ trợ đúng lúc cần thiết từ các chuyên gia trong toàn mạng lưới của bạn.',
		'Live video streaming means others can see exactly what you\'re seeing in real time, capture photos and augment your view with overlays and assitive tools.' => 'Truyền video trực tiếp có nghĩa là người khác có thể thấy chính xác những gì bạn đang thấy theo thời gian thực, chụp ảnh và tăng cường tầm nhìn của bạn với các lớp phủ và công cụ hỗ trợ.',
		'FRONTLINE EFFICIENCIES' => 'HIỆU QUẢ TUYẾN ĐẦU',
		'Benefits of AR in remote support' => 'Lợi ích của AR trong hỗ trợ từ xa',
		'Improve Global Access to Support' => 'Cải thiện khả năng tiếp cận hỗ trợ toàn cầu',
		'Connect your distributed workforce with the right expert, wherever they are, whenever they need it.' => 'Kết nối lực lượng lao động phân tán của bạn với đúng chuyên gia, bất kể họ ở đâu, bất kể khi nào họ cần.',
		'Significantly Reduce Costs' => 'Giảm đáng kể chi phí',
		'Eliminate travel and commuting costs with Vuzix smart glasses for remote support.' => 'Loại bỏ chi phí đi lại và di chuyển với kính thông minh Vuzix cho hỗ trợ từ xa.',
		'Increase Uptime and Service Levels' => 'Tăng thời gian hoạt động và mức độ dịch vụ',
		'Get equipment up and running faster with just-in-time guidance and information.' => 'Đưa thiết bị vào hoạt động nhanh hơn với hướng dẫn và thông tin kịp thời.',
		'Make Training Teams Easy' => 'Giúp việc đào tạo nhóm trở nên dễ dàng',
		'Enable on-the-job training that can be delivered to multiple sites with ease.' => 'Cho phép đào tạo tại chỗ có thể được triển khai dễ dàng đến nhiều địa điểm.',
		'VUZIX SOLUTIONS' => 'GIẢI PHÁP VUZIX',
		'Learn more →' => 'Tìm hiểu thêm →',
		'Stay connected with field technicians' => 'Luôn kết nối với kỹ thuật viên hiện trường',
		'Learn how you can access Vuzix remote support for Field Service organizations striving to grow their competitive advantage while continually increasing their ROI.' => 'Tìm hiểu cách bạn có thể truy cập hỗ trợ từ xa của Vuzix cho các tổ chức dịch vụ hiện trường đang nỗ lực phát triển lợi thế cạnh tranh trong khi liên tục gia tăng ROI.',
		'Download white paper →' => 'Tải sách trắng →',
		'What is the Vuzix Remote Mentor use case?' => 'Trường hợp sử dụng Vuzix Remote Mentor là gì?',
		'Remote Mentor refers to using Vuzix smart glasses to connect frontline workers with remote experts in real time. The on-site worker wears the smart glasses, and a remote expert sees exactly what the worker sees, with the ability to provide live guidance through voice, annotations drawn into the worker\'s field of view, shared documents, and step-by-step instructions. The Vuzix Remote Assist Kit packages this capability with Microsoft Teams and Zoom integration for organizations that already use those platforms.' => 'Remote Mentor đề cập đến việc sử dụng kính thông minh Vuzix để kết nối nhân viên tuyến đầu với các chuyên gia từ xa theo thời gian thực. Nhân viên tại hiện trường đeo kính thông minh, và chuyên gia từ xa nhìn thấy chính xác những gì nhân viên đang thấy, với khả năng cung cấp hướng dẫn trực tiếp qua giọng nói, các chú thích được vẽ vào tầm nhìn của nhân viên, tài liệu được chia sẻ và hướng dẫn từng bước. Vuzix Remote Assist Kit đóng gói khả năng này cùng với tích hợp Microsoft Teams và Zoom cho các tổ chức đã sử dụng các nền tảng đó.',
		'What kinds of work use Remote Mentor capabilities?' => 'Những loại công việc nào sử dụng khả năng của Remote Mentor?',
		'Remote Mentor workflows are common in field service (junior technicians supported by senior experts), manufacturing (line-down events requiring remote engineering or manufacturer help), healthcare (telemedicine consults, surgical mentoring), inspections (remote quality or safety verification), and training (experts guiding multiple junior workers across distributed sites). The pattern is the same across industries: the expert is somewhere else, the work is here, and the smart glasses let the expert see and guide as if they were on-site.' => 'Các quy trình làm việc của Remote Mentor phổ biến trong dịch vụ hiện trường (kỹ thuật viên mới được hỗ trợ bởi chuyên gia cấp cao), sản xuất (các sự cố dừng dây chuyền cần hỗ trợ kỹ thuật hoặc hỗ trợ từ nhà sản xuất từ xa), chăm sóc sức khỏe (tư vấn y tế từ xa, hướng dẫn phẫu thuật), kiểm tra (xác minh chất lượng hoặc an toàn từ xa) và đào tạo (chuyên gia hướng dẫn nhiều nhân viên mới tại các địa điểm phân tán). Mô hình này giống nhau trên mọi ngành: chuyên gia ở một nơi khác, công việc diễn ra ở đây, và kính thông minh cho phép chuyên gia nhìn thấy và hướng dẫn như thể họ đang có mặt tại hiện trường.',
		'What\'s included in the Vuzix Remote Assist Kit?' => 'Vuzix Remote Assist Kit bao gồm những gì?',
		'The Vuzix Remote Assist Kit combines Vuzix smart glasses with integrated software for real-time remote expert support through Microsoft Teams and Zoom: the two platforms most organizations already use for video collaboration. The kit is designed to make remote expert workflows quick to deploy without requiring custom application development or new collaboration tools. For organizations needing custom remote expert applications, Vuzix\' open SDK supports development on top of the base smart glasses platform.' => 'Vuzix Remote Assist Kit kết hợp kính thông minh Vuzix với phần mềm tích hợp để hỗ trợ chuyên gia từ xa theo thời gian thực qua Microsoft Teams và Zoom: hai nền tảng mà hầu hết các tổ chức đã sử dụng để hợp tác video. Bộ kit được thiết kế để giúp triển khai nhanh các quy trình chuyên gia từ xa mà không cần phát triển ứng dụng tùy chỉnh hoặc công cụ hợp tác mới. Đối với các tổ chức cần ứng dụng chuyên gia từ xa tùy chỉnh, SDK mở của Vuzix hỗ trợ phát triển trên nền tảng kính thông minh cơ bản.',
		'Applications' => 'Ứng dụng',
		'Broad Market Eyewear' => 'Kính mắt thị trường phổ thông',
		'Lightweight waveguide platforms for next-generation AI experiences' => 'Nền tảng ống dẫn sóng nhẹ cho các trải nghiệm AI thế hệ tiếp theo',
		'Talk to Our OEM team →' => 'Trao đổi với nhóm OEM của chúng tôi →',
		'20 years' => '20 năm',
		'CES innovation awards' => 'Giải thưởng đổi mới CES',
		'Field of view range' => 'Phạm vi trường nhìn',
		'Rx-Ready' => 'Hỗ trợ đơn thuốc',
		'Waveguide portfolio' => 'Danh mục ống dẫn sóng',
		'AI smart glasses for the broad market' => 'Kính thông minh AI cho thị trường phổ thông',
		'Vuzix supports the development of next-generation smart eyewear through lightweight waveguide optics, compact display systems, and configurable OEM platforms designed for all-day wearablity and everyday AI applications.' => 'Vuzix hỗ trợ phát triển kính thông minh thế hệ tiếp theo thông qua quang học ống dẫn sóng nhẹ, hệ thống hiển thị nhỏ gọn và các nền tảng OEM có thể cấu hình được thiết kế để đeo cả ngày và ứng dụng AI hàng ngày.',
		'These systems are built to enable contextual information, AI-assisted interactions, and discreet heads-up interfaces that integrate naturally into everyday environments.' => 'Các hệ thống này được xây dựng để cung cấp thông tin theo ngữ cảnh, tương tác có hỗ trợ AI và giao diện hiển thị kín đáo, tích hợp một cách tự nhiên vào môi trường hàng ngày.',
		'Rather than standalone devices, Vuzix eyewear technologies provide a foundation for OEM partners building consumer AI smart glasses, fashion-forward wearable computing devices, and intelligent optics.' => 'Thay vì là các thiết bị độc lập, công nghệ kính mắt của Vuzix cung cấp nền tảng cho các đối tác OEM xây dựng kính thông minh AI cho người tiêu dùng, thiết bị điện toán đeo được thời trang và quang học thông minh.',
		'Platform Capabilities' => 'Khả năng của nền tảng',
		'AI eyewear development for the broad market' => 'Phát triển kính mắt AI cho thị trường phổ thông',
		'Vuzix provides the optical and hardware building blocks for lightweight smart eyewear, enabling OEM partners to design products that balance performance, comfort, and industrial design requirements.' => 'Vuzix cung cấp các thành phần quang học và phần cứng nền tảng cho kính thông minh nhẹ, giúp các đối tác OEM thiết kế sản phẩm cân bằng giữa hiệu suất, sự thoải mái và yêu cầu thiết kế công nghiệp.',
		'Key capabilities include:' => 'Các khả năng chính bao gồm:',
		'Waveguide optics for see-through displays' => 'Quang học ống dẫn sóng cho màn hình xuyên suốt',
		'Compact display integration architectures' => 'Kiến trúc tích hợp màn hình nhỏ gọn',
		'Low-power wearable system design considerations' => 'Các yếu tố thiết kế hệ thống đeo được tiêu thụ điện năng thấp',
		'Scalable Vuzix Ultralite reference platform configurations' => 'Các cấu hình nền tảng tham chiếu Vuzix Ultralite có thể mở rộng',
		'Support for AI and mobile-connected experiences' => 'Hỗ trợ cho các trải nghiệm kết nối AI và di động',
		'Ultralite OEM Platforms' => 'Các nền tảng OEM Ultralite',
		'Best for full-color smart glasses' => 'Tốt nhất cho kính thông minh màu đầy đủ',
		'Full-color binocular AR + Snapdragon AR1 + camera + voice. For consumer products where rich visual overlay — navigation, entertainment, information — is the core experience.' => 'AR hai mắt màu đầy đủ + Snapdragon AR1 + camera + giọng nói. Dành cho các sản phẩm tiêu dùng nơi lớp phủ hình ảnh phong phú — điều hướng, giải trí, thông tin — là trải nghiệm cốt lõi.',
		'Full Ultralite Pro Specs →' => 'Xem đầy đủ thông số Ultralite Pro →',
		'Best for AI Wearables' => 'Tốt nhất cho thiết bị đeo AI',
		'Monochrome microLED + premium audio + 2-day battery + Incognito. For AI assistant glasses, audio wearables, and notification-focused broad market products. Prescription-compatible.' => 'microLED đơn sắc + âm thanh cao cấp + pin 2 ngày + Incognito. Dành cho kính trợ lý AI, thiết bị đeo âm thanh và các sản phẩm thị trường phổ thông tập trung vào thông báo. Tương thích kính đơn thuốc.',
		'Full Ultralite Audio Specs →' => 'Xem đầy đủ thông số Ultralite Audio →',
		'WEARABLE USE CASES' => 'TRƯỜNG HỢP SỬ DỤNG THIẾT BỊ ĐEO',
		'Everyday smart glasses experiences' => 'Trải nghiệm kính thông minh hàng ngày',
		'Across industries, headworn systems must perform under widely varying conditions, from structured enterprise workflows to unpredictable field operations. Each application area reflects a tailored configuration of the same underlying optical and engineering foundation.' => 'Trên nhiều ngành công nghiệp, các hệ thống đeo trên đầu phải hoạt động tốt trong nhiều điều kiện khác nhau, từ quy trình làm việc doanh nghiệp có cấu trúc đến các hoạt động hiện trường khó dự đoán. Mỗi lĩnh vực ứng dụng phản ánh một cấu hình được tùy chỉnh riêng dựa trên cùng một nền tảng quang học và kỹ thuật cốt lõi.',
		'AI Assistant Interfaces' => 'Giao diện trợ lý AI',
		'Context-aware digital assistance in real time' => 'Hỗ trợ số nhận biết ngữ cảnh theo thời gian thực',
		'Smart eyewear can deliver AI-generated responses, notifications, and contextual information directly into the user\'s field of view, enabling hands-free interaction with digital systems.' => 'Kính thông minh có thể cung cấp các phản hồi do AI tạo ra, thông báo và thông tin theo ngữ cảnh trực tiếp vào tầm nhìn của người dùng, cho phép tương tác rảnh tay với các hệ thống số.',
		'Navigation &amp; Contextual Awareness' => 'Điều hướng &amp; Nhận biết ngữ cảnh',
		'Guidance layered onto the physical world' => 'Hướng dẫn được xếp lớp lên thế giới thực',
		'Wearable optics systems support navigation cues, location-based information, and contextual overlays for travel, urban environments, and daily mobility.' => 'Các hệ thống quang học đeo được hỗ trợ gợi ý điều hướng, thông tin dựa trên vị trí và lớp phủ theo ngữ cảnh cho du lịch, môi trường đô thị và di chuyển hàng ngày.',
		'Media &amp; Content Interaction' => 'Tương tác Media &amp; Nội dung',
		'Hands-free access to visual content' => 'Truy cập rảnh tay vào nội dung hình ảnh',
		'Smart eyewear can support photo, video, and immersive media experiences designed for lightweight, mobile-first consumption.' => 'Kính thông minh có thể hỗ trợ ảnh, video và trải nghiệm media sống động được thiết kế cho việc sử dụng nhẹ nhàng, ưu tiên di động.',
		'Communication &amp; Notifications' => 'Giao tiếp &amp; Thông báo',
		'Discreet, always-available connectivity' => 'Kết nối kín đáo, luôn sẵn sàng',
		'Vuzix Incognito technology reduces forward light emission for subtle delivery of messages, alerts, and notifications without visibly signaling device use.' => 'Công nghệ Vuzix Incognito giảm phát xạ ánh sáng phía trước để truyền tải tin nhắn, cảnh báo và thông báo một cách tinh tế mà không để lộ việc đang sử dụng thiết bị.',
		'FORM FACTOR &amp; DESIGN CONSIDERATIONS' => 'KIỂU DÁNG &amp; CÁC YẾU TỐ THIẾT KẾ',
		'Optimized for comfort, discretion, and all-day wearability' => 'Được tối ưu hóa cho sự thoải mái, kín đáo và khả năng đeo cả ngày',
		'Vuzix smart eyewear platforms are designed for OEM integration, enabling partners to build differentiated intelligent devices with control over industrial design, software experience, and user interaction models. This approach supports rapid development cycles while maintaining a consistent optical and hardware foundation.' => 'Các nền tảng kính thông minh của Vuzix được thiết kế để tích hợp OEM, giúp các đối tác xây dựng các thiết bị thông minh khác biệt với toàn quyền kiểm soát về thiết kế công nghiệp, trải nghiệm phần mềm và mô hình tương tác người dùng. Phương pháp này hỗ trợ các chu kỳ phát triển nhanh trong khi vẫn duy trì nền tảng quang học và phần cứng nhất quán.',
		'AI smart glasses design requires balancing:' => 'Thiết kế kính thông minh AI yêu cầu cân bằng giữa:',
		'Optical performance' => 'Hiệu suất quang học',
		'Weight and ergonomics' => 'Trọng lượng và tính tiện dụng',
		'Aesthetic design' => 'Thiết kế thẩm mỹ',
		'Battery efficiency' => 'Hiệu suất pin',
		'Thermal constraints' => 'Hạn chế về nhiệt',
		'Social acceptability' => 'Khả năng được xã hội chấp nhận',
		'Does Vuzix make a consumer smart glasses product I can buy?' => 'Vuzix có sản xuất sản phẩm kính thông minh tiêu dùng mà tôi có thể mua không?',
		'Vuzix does not sell finished consumer smart glasses directly. Vuzix\'s consumer-facing role is providing the underlying optical and hardware technology — waveguide optics, display integration architectures, and the Ultralite OEM Platform — to companies building branded consumer intelligent eyewear and AI-enabled wearables. Consumers typically experience Vuzix technology through OEM partner products rather than through a Vuzix-branded consumer device.' => 'Vuzix không bán trực tiếp kính thông minh tiêu dùng hoàn chỉnh. Vai trò của Vuzix đối với người tiêu dùng là cung cấp công nghệ quang học và phần cứng nền tảng — quang học ống dẫn sóng, kiến trúc tích hợp màn hình hiển thị và Nền tảng Ultralite OEM — cho các công ty xây dựng kính mắt thông minh mang thương hiệu riêng và thiết bị đeo hỗ trợ AI. Người tiêu dùng thường trải nghiệm công nghệ Vuzix qua các sản phẩm của đối tác OEM hơn là qua một thiết bị tiêu dùng mang thương hiệu Vuzix.',
		'What broad market use cases does Vuzix Ultralite enable?' => 'Nền tảng Vuzix Ultralite hỗ trợ những trường hợp sử dụng nào cho thị trường phổ thông?',
		'Vuzix Ultralite platforms support four consumer AI wearable use cases: AI assistant interfaces delivering context-aware responses directly in the field of view, navigation and contextual awareness with guidance overlaid on the physical world, communication and notifications via Incognito-supported discreet delivery, and hands-free media and content interaction. Different Ultralite configurations (Pro, Audio) optimize for different combinations of these use cases.' => 'Các nền tảng Vuzix Ultralite hỗ trợ bốn trường hợp sử dụng thiết bị đeo AI cho người tiêu dùng: giao diện trợ lý AI cung cấp phản hồi nhận biết ngữ cảnh trực tiếp trong tầm nhìn, điều hướng và nhận biết ngữ cảnh với hướng dẫn được phủ lên thế giới thực, giao tiếp và thông báo qua tính năng truyền tải kín đáo được hỗ trợ bởi Incognito, và tương tác media, nội dung rảnh tay. Các cấu hình Ultralite khác nhau (Pro, Audio) được tối ưu hóa cho các tổ hợp khác nhau của những trường hợp sử dụng này.',
		'How do Vuzix waveguides handle the design constraints of AI eyewear?' => 'Ống dẫn sóng của Vuzix xử lý các hạn chế thiết kế của kính mắt AI như thế nào?',
		'Consumer smart glasses must balance optical performance, weight and ergonomics, aesthetic design, battery efficiency, thermal constraints, camera and social acceptability — usually all at once. Vuzix waveguide systems are engineered for these constraints: thin (down to 0.35mm) and lightweight optics, Litebank technology for prescription compatibility, Incognito to reduce visible device signature, low-power display integration, and form factor flexibility through the Ultralite Platform configurations. The Vuzix optics team works with OEM partners to optimize tradeoffs for each program.' => 'Kính thông minh tiêu dùng phải cân bằng giữa hiệu suất quang học, trọng lượng và tính tiện dụng, thiết kế thẩm mỹ, hiệu suất pin, hạn chế về nhiệt, camera và khả năng được xã hội chấp nhận — thường là tất cả cùng một lúc. Các hệ thống ống dẫn sóng của Vuzix được thiết kế cho những hạn chế này: quang học mỏng (chỉ dày tới 0,35mm) và nhẹ, công nghệ Litebank để tương thích với kính đơn thuốc, Incognito để giảm dấu hiệu nhận biết thiết bị, tích hợp màn hình hiển thị tiêu thụ điện năng thấp và tính linh hoạt về kiểu dáng thông qua các cấu hình Nền tảng Ultralite. Nhóm quang học của Vuzix làm việc với các đối tác OEM để tối ưu hóa sự đánh đổi cho từng chương trình.',
		'Build the next generation of smart eyewear' => 'Xây dựng thế hệ kính thông minh tiếp theo',
		'Vuzix works with OEM brands developing broad market AI smart glasses.' => 'Vuzix hợp tác với các thương hiệu OEM phát triển kính thông minh AI cho thị trường phổ thông.',
		'M4000 Compliance' => 'Tuân thủ M4000',
		'1 Introduction' => '1. Giới thiệu',
		'This guide lists product information for M4000 users:' => 'Tài liệu này cung cấp thông tin sản phẩm dành cho người dùng M4000:',
		'See Safety and warranty information here.' => 'Xem thông tin an toàn và bảo hành tại đây.',
		'Electronic Regulatory Labels: Settings &amp;gt; System &amp;gt; About Glasses &amp;gt; Regulatory labels' => 'Nhãn quy định điện tử: Settings &amp;gt; System &amp;gt; About Glasses &amp;gt; Regulatory labels',
		'2 FCC regulatory compliance' => '2. Tuân thủ quy định FCC',
		'Note: This equipment has been tested and found to comply with the limits for a Class B digital device, pursuant to part 15 of the FCC Rules. These limits are designed to provide reasonable protection against harmful interference in a residential installation. This equipment generates, uses and can radiate radio frequency energy and, if not installed and used in accordance with the instructions, may cause harmful interference to radio communications. However, there is no guarantee that interference will not occur in a particular installation.' => 'Lưu ý: Thiết bị này đã được kiểm tra và xác nhận tuân thủ các giới hạn đối với thiết bị số Hạng B, theo Phần 15 của Quy định FCC. Các giới hạn này được thiết kế để cung cấp sự bảo vệ hợp lý chống lại nhiễu có hại khi lắp đặt trong khu dân cư. Thiết bị này tạo ra, sử dụng và có thể phát ra năng lượng tần số vô tuyến, và nếu không được lắp đặt và sử dụng theo đúng hướng dẫn, có thể gây nhiễu có hại cho thông tin liên lạc vô tuyến. Tuy nhiên, không có gì bảo đảm rằng nhiễu sẽ không xảy ra trong một lần lắp đặt cụ thể.',
		'If this equipment does cause harmful interference to radio or television reception, which can be determined by turning the equipment off and on, the user is encouraged to try to correct the interference by one or more of the following measures:' => 'Nếu thiết bị này gây nhiễu có hại cho việc thu sóng radio hoặc truyền hình, điều này có thể được xác định bằng cách tắt và mở lại thiết bị, người dùng nên thử khắc phục nhiễu bằng một hoặc nhiều biện pháp sau:',
		'Changes or modifications not expressly approved by Vuzix could void your authority to operate the equipment.' => 'Các thay đổi hoặc sửa đổi không được Vuzix chấp thuận rõ ràng có thể làm mất hiệu lực quyền vận hành thiết bị của bạn.',
		'M4000 complies with Part 15 of the FCC Rules. Operation is subject to the following 2 conditions:' => 'M4000 tuân thủ Phần 15 của Quy định FCC. Việc vận hành phải tuân theo 2 điều kiện sau:',
		'These devices may not cause harmful interference.' => 'Các thiết bị này không được gây nhiễu có hại.',
		'These devices must accept any interference received, including interference that may cause undesired operation.' => 'Các thiết bị này phải chấp nhận mọi nhiễu nhận được, bao gồm cả nhiễu có thể gây ra hoạt động không mong muốn.',
		'FCC Statement' => 'Tuyên bố của FCC',
		'Operation of this device in the band 5150-5250 MHz is restricted to indoor use only.' => 'Việc vận hành thiết bị này trong băng tần 5150-5250 MHz chỉ được giới hạn sử dụng trong nhà.',
		'3 Industry Canada Notices' => '3. Thông báo của Industry Canada',
		'Industry Canada, Class B' => 'Industry Canada, Hạng B',
		'This Class B digital apparatus complies with Canadian ICES-003.' => 'Thiết bị số Hạng B này tuân thủ tiêu chuẩn ICES-003 của Canada.',
		'Notice: The Industry Canada regulations provide that changes or modifications not expressly approved by Vuzix could void your authority to operate this equipment.' => 'Lưu ý: Theo quy định của Industry Canada, các thay đổi hoặc sửa đổi không được Vuzix chấp thuận rõ ràng có thể làm mất hiệu lực quyền vận hành thiết bị này của bạn.',
		'Industry Canada (IC) Notices' => 'Thông báo của Industry Canada (IC)',
		'These devices comply with Industry Canada license-exempt RSS standard(s). Operation is subject to the following two conditions:' => 'Các thiết bị này tuân thủ (các) tiêu chuẩn RSS miễn cấp phép của Industry Canada. Việc vận hành phải tuân theo hai điều kiện sau:',
		'These devices may not cause interference.' => 'Các thiết bị này không được gây nhiễu.',
		'These devices must accept any interference, including interference that may cause undesired operation of the device.' => 'Các thiết bị này phải chấp nhận mọi nhiễu, bao gồm cả nhiễu có thể gây ra hoạt động không mong muốn của thiết bị.',
		'Warning:' => 'Cảnh báo:',
		'(i) The device for operation in the band 5150–5250 MHz is only for indoor use to reduce the potential for harmful interference to co-channel mobile satellite systems;' => '(i) Thiết bị vận hành trong băng tần 5150–5250 MHz chỉ được sử dụng trong nhà nhằm giảm khả năng gây nhiễu có hại cho các hệ thống vệ tinh di động cùng kênh;',
		'(ii) The maximum antenna gain permitted for devices in the bands 5250–5350 MHz and 5470–5725 MHz shall comply with the e.i.r.p. limit; and' => '(ii) Độ lợi ăng-ten tối đa cho phép đối với thiết bị trong các băng tần 5250–5350 MHz và 5470–5725 MHz phải tuân theo giới hạn e.i.r.p.; và',
		'(iii) The maximum antenna gain permitted for devices in the band 5725–5825 MHz shall comply with the e.i.r.p. limits specified for point-to-point and non point-to-point operation as appropriate.' => '(iii) Độ lợi ăng-ten tối đa cho phép đối với thiết bị trong băng tần 5725–5825 MHz phải tuân theo các giới hạn e.i.r.p. được quy định cho hoạt động điểm-điểm và không điểm-điểm tương ứng.',
		'(iv) Users should also be advised that high-power radars are allocated as primary users (i.e. priority users) of the bands 5250–5350 MHz and 5650–5850 MHz and that these radars could cause interference and/or damage to LE-LAN devices.' => '(iv) Người dùng cũng cần được thông báo rằng các radar công suất cao được phân bổ làm người dùng chính (tức người dùng ưu tiên) của các băng tần 5250–5350 MHz và 5650–5850 MHz, và các radar này có thể gây nhiễu và/hoặc hư hại cho các thiết bị LE-LAN.',
		'4 Frequency Band and Power (CE)' => '4. Băng tần và công suất (CE)',
		'Frequency Range:' => 'Dải tần số:',
		'Transmit Power:' => 'Công suất truyền:',
		'5 Declaration of Conformity' => '5. Tuyên bố hợp chuẩn',
		'6 Human exposure to radio frequency' => '6. Mức phơi nhiễm tần số vô tuyến đối với con người',
		'M4000 was tested and certified to not exceed limits in US, Canada, EU, and Japan.' => 'M4000 đã được kiểm tra và chứng nhận không vượt quá các giới hạn cho phép tại Hoa Kỳ, Canada, EU và Nhật Bản.',
		'US/Canada' => 'Hoa Kỳ/Canada',
		'WLAN 2.4G Head:' => 'WLAN 2.4G – Đầu:',
		'Limit 1.6W/g SAR' => 'Giới hạn 1.6W/g SAR',
		'WLAN 5.2G Head:' => 'WLAN 5.2G – Đầu:',
		'WLAN 5.8G Head:' => 'WLAN 5.8G – Đầu:',
		'Limit 2.0W/g SAR' => 'Giới hạn 2.0W/g SAR',
		'Whether you\'re in the warehouse' => 'Cho dù bạn ở trong nhà kho',
		'or the operating room, our industry-defining' => 'hay trong phòng mổ, các giải pháp dẫn đầu ngành của chúng tôi',
		'solutions make you more effective' => 'giúp bạn làm việc hiệu quả hơn',
		'Compare Smart Glasses →' => 'So sánh kính thông minh →',
		'Take the Quiz →' => 'Làm bài đánh giá →',
		'Enterprise-grade' => 'Chuẩn doanh nghiệp',
		'Security &amp; device management' => 'Bảo mật &amp; quản lý thiết bị',
		'Prescription ready' => 'Hỗ trợ tròng kính theo đơn',
		'Product portfolio' => 'Danh mục sản phẩm',
		'Enabling the connected workforce' => 'Kết nối lực lượng lao động',
		'Vuzix AI smart glasses deliver real-time information exactly where it\'s needed, helping organizations improve productivity, communication, and decision-making across the frontline workforce.' => 'Kính thông minh AI của Vuzix mang lại thông tin theo thời gian thực đúng nơi cần thiết, giúp các tổ chức cải thiện năng suất, giao tiếp và khả năng ra quyết định trên toàn lực lượng lao động tuyến đầu.',
		'Combining advanced optics with enterprise-ready hardware, Vuzix devices provide a comfortable, hands-free interface for accessing digital information while allowing workers to retain their focus.' => 'Kết hợp quang học tiên tiến với phần cứng đạt chuẩn doanh nghiệp, các thiết bị Vuzix cung cấp giao diện rảnh tay, thoải mái để truy cập thông tin số trong khi vẫn giúp người lao động duy trì sự tập trung.',
		'From AI-powered assistance to remote collaboration and guided workflows, Vuzix offers smart glasses for manufacturing, logistics, healthcare, field service, defense, and other enterprise applications.' => 'Từ hỗ trợ được thúc đẩy bởi AI đến hợp tác từ xa và quy trình làm việc có hướng dẫn, Vuzix cung cấp kính thông minh cho sản xuất, hậu cần, y tế, dịch vụ hiện trường, quốc phòng và nhiều ứng dụng doanh nghiệp khác.',
		'Rugged All-Day Wear' => 'Bền bỉ, đeo cả ngày',
		'Purpose-built for the modern warehouse, Vuzix LX1 smart glasses combine rugged durability with powerful, hands-free functionality to enhance the productivity of your picking teams.' => 'Được thiết kế chuyên biệt cho nhà kho hiện đại, kính thông minh Vuzix LX1 kết hợp độ bền chắc với các tính năng rảnh tay mạnh mẽ để nâng cao năng suất cho các nhóm nhặt hàng của bạn.',
		'• 7000 mAh long-shift clip in battery' => '• Pin cài ngoài 7000 mAh cho ca làm việc dài',
		'• Rugged IP 4 Certification &amp; Freezer rated operating temperatures' => '• Đạt chứng nhận chống chịu IP 4 &amp; hoạt động được ở nhiệt độ phòng đông lạnh',
		'• Headband with easy release mounting system' => '• Băng đô với hệ thống gắn tháo nhanh, dễ dàng',
		'• Future-proof Android 15 OS' => '• Hệ điều hành Android 15 sẵn sàng cho tương lai',
		'• NFC tap to pair for easy sharing' => '• Chạm NFC để ghép nối, dễ dàng chia sẻ',
		'Explore LX1' => 'Khám phá LX1',
		'Compare smart glasses' => 'So sánh kính thông minh',
		'Lightweight Grab-and-Go' => 'Nhẹ, tiện lợi mang theo',
		'Lightweight and durable, Vuzix M400 smart glasses are the most wearable, powerful, and ergonomically versatile headworn computer on the market. Ideal for remote assist, training, and frontline tasks.' => 'Nhẹ và bền, kính thông minh Vuzix M400 là máy tính đeo đầu dễ đeo, mạnh mẽ và linh hoạt về công thái học nhất trên thị trường. Lý tưởng cho hỗ trợ từ xa, đào tạo và các công việc tuyến đầu.',
		'• First smart glasses with a dedicated XR1 platform' => '• Kính thông minh đầu tiên có nền tảng XR1 chuyên dụng',
		'• Increased processing power with an industry-leading 8-core AR processor' => '• Tăng cường sức mạnh xử lý với bộ xử lý AR 8 lõi hàng đầu ngành',
		'• Ruggedized, waterproofed design' => '• Thiết kế chống chịu, chống nước',
		'In Development' => 'Đang phát triển',
		'Vuzix AI Smart Glasses' => 'Kính thông minh AI của Vuzix',
		'Designed for the AI Era' => 'Được thiết kế cho thời đại AI',
		'Vuzix AI smart glasses preview the next generation of enterprise wearables, combining binocular full-color waveguide optics with a lightweight design intended for all-day wear. Currently in development, this future platform showcases how AI, advanced optics, and wearable computing can come together to deliver hands-free access to digital information, remote collaboration, and natural voice-driven interactions in eyewear that looks and feels like everyday glasses.' => 'Kính thông minh AI của Vuzix hé lộ thế hệ tiếp theo của thiết bị đeo doanh nghiệp, kết hợp quang học ống dẫn sóng full-color hai mắt với thiết kế nhẹ, phù hợp để đeo cả ngày. Hiện đang trong quá trình phát triển, nền tảng tương lai này cho thấy cách AI, quang học tiên tiến và máy tính đeo được có thể kết hợp với nhau để mang lại khả năng truy cập thông tin số rảnh tay, hợp tác từ xa và tương tác bằng giọng nói tự nhiên trong một chiếc kính có hình dáng và cảm giác như kính đeo thông thường.',
		'• Designed for AI-enabled enterprise use cases' => '• Được thiết kế cho các trường hợp sử dụng doanh nghiệp có ứng dụng AI',
		'• Features Incognito™ waveguide technology' => '• Trang bị công nghệ ống dẫn sóng Incognito™',
		'• Integrated camera, premium audio, and full-color binocular waveguide display' => '• Camera tích hợp, âm thanh cao cấp và màn hình ống dẫn sóng hai mắt full-color',
		'Talk to Our OEM Team' => 'Liên hệ với nhóm OEM của chúng tôi',
		'USE CASES' => 'TRƯỜNG HỢP SỬ DỤNG',
		'Smart glasses for every solution' => 'Kính thông minh cho mọi giải pháp',
		'Medical' => 'Y tế',
		'Delivering better patient outcomes with connected care.' => 'Mang lại kết quả điều trị tốt hơn cho bệnh nhân với chăm sóc kết nối.',
		'Train employees faster and reduce maintenance downtimes' => 'Đào tạo nhân viên nhanh hơn và giảm thời gian ngừng bảo trì',
		'Vision + Voice to drive productivity and reduce training times.' => 'Kết hợp Hình ảnh + Giọng nói để tăng năng suất và giảm thời gian đào tạo.',
		'Explore Warehousing →' => 'Khám phá Kho vận →',
		'Focus on our users' => 'Tập trung vào người dùng',
		'The perfect balance of performance and comfort' => 'Sự cân bằng hoàn hảo giữa hiệu suất và sự thoải mái',
		'We know that user comfort is major barrier to widespread adoption of smart glasses. That\'s why we\'ve designed all Vuzix products for minimal weight and maximum performance.' => 'Chúng tôi hiểu rằng sự thoải mái của người dùng là rào cản lớn đối với việc áp dụng rộng rãi kính thông minh. Đó là lý do vì sao chúng tôi thiết kế tất cả sản phẩm Vuzix với trọng lượng tối thiểu và hiệu suất tối đa.',
		'Vuzix smart glasses give you:' => 'Kính thông minh Vuzix mang lại cho bạn:',
		'A bright, unobtrusive display with expansive field of view' => 'Màn hình sáng, không gây khó chịu với góc nhìn rộng',
		'User-centered ergonomic design' => 'Thiết kế công thái học lấy người dùng làm trung tâm',
		'Diverse mounting options for any preference' => 'Đa dạng tùy chọn lắp đặt theo mọi sở thích',
		'Collaborative working via video streaming' => 'Làm việc cộng tác qua truyền video trực tiếp',
		'Integration with top conferencing platforms such as Zoom and WebEx' => 'Tích hợp với các nền tảng hội nghị hàng đầu như Zoom và WebEx',
		'Shop Now →' => 'Mua ngay →',
		'Vuzix Companion App' => 'Ứng dụng Vuzix Companion',
		'Our companion app for Android and iOS enables easy setup and communication between your smartphone and Vuzix device. Manage your Vuzix smart glasses right from your smartphone: Easily configure settings, manage notifications, remotely control apps, and more.' => 'Ứng dụng đồng hành của chúng tôi cho Android và iOS giúp dễ dàng thiết lập và giao tiếp giữa điện thoại thông minh và thiết bị Vuzix của bạn. Quản lý kính thông minh Vuzix ngay từ điện thoại: dễ dàng cấu hình cài đặt, quản lý thông báo, điều khiển ứng dụng từ xa và nhiều hơn nữa.',
		'Which Vuzix smart glasses should I deploy?' => 'Tôi nên triển khai kính thông minh Vuzix nào?',
		'Match the device to the operating environment. The Vuzix LX1 is purpose-built for warehouse and industrial environments — 10-hour battery, freezer-rated, ruggedized for cold-storage and high-impact use. The Vuzix M400 is an all-purpose device with hot-swappable batteries, suited to varied workflows including field service, manufacturing, inspections, and healthcare. The Remote Assist Kit pairs Vuzix smart glasses with Microsoft Teams and Zoom integration for live remote expert workflows. For warehouse pick-pack-sort specifically, the Pick &amp; Pack Validation Program provides a structured evaluation before broader deployment.' => 'Hãy chọn thiết bị phù hợp với môi trường vận hành. Vuzix LX1 được thiết kế chuyên biệt cho môi trường nhà kho và công nghiệp — pin 10 giờ, đạt chuẩn hoạt động trong phòng đông lạnh, chống chịu cho môi trường bảo quản lạnh và va đập mạnh. Vuzix M400 là thiết bị đa dụng với pin có thể tháo đổi nhanh, phù hợp với nhiều quy trình làm việc khác nhau bao gồm dịch vụ hiện trường, sản xuất, kiểm tra và y tế. Remote Assist Kit kết hợp kính thông minh Vuzix với tích hợp Microsoft Teams và Zoom cho các quy trình chuyên gia từ xa trực tiếp. Đối với quy trình nhặt-đóng gói-phân loại trong nhà kho, Pick &amp; Pack Validation Program cung cấp một đánh giá có cấu trúc trước khi triển khai rộng hơn.',
		'What\'s the difference between the Vuzix M400 and LX1?' => 'Sự khác biệt giữa Vuzix M400 và LX1 là gì?',
		'The M400 is a general-purpose enterprise device with hot-swappable batteries, designed for workflows where users move between tasks, environments, or shifts — including field service, healthcare, manufacturing, and inspections. The LX1 is purpose-built for warehouse and industrial environments, with a 10-hour battery, freezer-rated construction, and ruggedization for cold-storage and high-impact use. Choose the M400 for flexibility across multiple workflows, the LX1 for sustained single-shift warehouse and industrial operations.' => 'M400 là thiết bị doanh nghiệp đa dụng với pin có thể tháo đổi nhanh, được thiết kế cho các quy trình làm việc mà người dùng di chuyển giữa nhiều công việc, môi trường hoặc ca làm việc khác nhau — bao gồm dịch vụ hiện trường, y tế, sản xuất và kiểm tra. LX1 được thiết kế chuyên biệt cho môi trường nhà kho và công nghiệp, với pin 10 giờ, cấu trúc đạt chuẩn hoạt động trong phòng đông lạnh và khả năng chống chịu cho môi trường bảo quản lạnh và va đập mạnh. Chọn M400 để có sự linh hoạt trên nhiều quy trình làm việc, chọn LX1 cho hoạt động nhà kho và công nghiệp liên tục trong một ca làm việc.',
		'Can Vuzix smart glasses connect to the software my team already uses?' => 'Kính thông minh Vuzix có thể kết nối với phần mềm mà nhóm của tôi đang sử dụng không?',
		'Yes. Vuzix smart glasses run Android and support common enterprise mobile device management platforms, allowing IT teams to provision, secure, and update devices through familiar tools. The open SDK and camera APIs let internal development teams or ISV partners build custom applications, and the Remote Assist Kit integrates directly with Microsoft Teams and Zoom for live remote expert workflows. Vuzix also works with system integrators for large multi-site deployments, including WMS, MES, and field service management platforms.' => 'Có. Kính thông minh Vuzix chạy trên Android và hỗ trợ các nền tảng quản lý thiết bị di động doanh nghiệp phổ biến, cho phép các nhóm IT cấp phát, bảo mật và cập nhật thiết bị bằng các công cụ quen thuộc. SDK mở và API camera cho phép các nhóm phát triển nội bộ hoặc đối tác ISV xây dựng ứng dụng tùy chỉnh, và Remote Assist Kit tích hợp trực tiếp với Microsoft Teams và Zoom cho các quy trình chuyên gia từ xa trực tiếp. Vuzix cũng hợp tác với các nhà tích hợp hệ thống cho các triển khai đa địa điểm quy mô lớn, bao gồm các nền tảng WMS, MES và quản lý dịch vụ hiện trường.',
		'The fastest path from concept to volume production for AI smart glasses' => 'Con đường nhanh nhất từ ý tưởng đến sản xuất số lượng lớn cho kính thông minh AI',
		'OEM volume programs active' => 'Chương trình OEM số lượng lớn đang triển khai',
		'Build with Vuzix' => 'Xây dựng cùng Vuzix',
		'Vuzix works with companies building AI smart glasses, combining waveguide optics, display systems, and full device engineering within a single OEM program.' => 'Vuzix hợp tác với các công ty xây dựng kính thông minh AI, kết hợp quang học ống dẫn sóng, hệ thống hiển thị và kỹ thuật thiết bị hoàn chỉnh trong một chương trình OEM duy nhất.',
		'Unlike many other OEMs, Vuzix designs and manufactures its core optical technology in-house, enabling tighter system integration, faster development cycles, and a direct path to scaled production.' => 'Khác với nhiều nhà cung cấp OEM khác, Vuzix tự thiết kế và sản xuất công nghệ quang học cốt lõi ngay tại nội bộ, giúp tích hợp hệ thống chặt chẽ hơn, chu kỳ phát triển nhanh hơn và con đường trực tiếp đến sản xuất quy mô lớn.',
		'With nearly 30 years in headworn technology, Vuzix supports programs from concept through manufacturing, aligning optical performance, hardware design, and production from the start.' => 'Với gần 30 năm kinh nghiệm trong công nghệ thiết bị đeo đầu, Vuzix hỗ trợ các chương trình từ ý tưởng đến sản xuất, đồng bộ hiệu suất quang học, thiết kế phần cứng và sản xuất ngay từ đầu.',
		'What We Provide' => 'Những gì chúng tôi cung cấp',
		'A complete OEM pathway, from concept to production' => 'Lộ trình OEM toàn diện, từ ý tưởng đến sản xuất',
		'Vuzix supports OEM programs through three core capabilities: waveguide optics, engineering services, and integrated hardware platforms. These are supported by a focused ecosystem of display and technology partners, enabling faster development, reduced risk, and scalable production.' => 'Vuzix hỗ trợ các chương trình OEM thông qua ba năng lực cốt lõi: quang học ống dẫn sóng, dịch vụ kỹ thuật và nền tảng phần cứng tích hợp. Các năng lực này được hỗ trợ bởi một hệ sinh thái tập trung gồm các đối tác hiển thị và công nghệ, giúp phát triển nhanh hơn, giảm rủi ro và sản xuất có khả năng mở rộng.',
		'Waveguide Optics' => 'Quang học ống dẫn sóng',
		'Precision waveguides for AI smart glasses' => 'Ống dẫn sóng chính xác cho kính thông minh AI',
		'Vuzix designs and manufactures waveguide components for high-performance, full-color display systems. These waveguides support a wide range of device form factors and are compatible with multiple display engines, including DLP, LCoS, laser LCoS, and microLED.' => 'Vuzix thiết kế và sản xuất các linh kiện ống dẫn sóng cho hệ thống hiển thị full-color, hiệu suất cao. Các ống dẫn sóng này hỗ trợ nhiều dạng thiết bị khác nhau và tương thích với nhiều công nghệ hiển thị, bao gồm DLP, LCoS, laser LCoS và microLED.',
		'Together, they form the optical engine at the core of every OEM program, defining brightness, field of view, and overall system performance.' => 'Cùng nhau, chúng tạo thành bộ máy quang học ở trung tâm của mọi chương trình OEM, quyết định độ sáng, góc nhìn và hiệu suất tổng thể của hệ thống.',
		'Explore wavguides →' => 'Khám phá ống dẫn sóng →',
		'From concept to production-ready systems' => 'Từ ý tưởng đến hệ thống sẵn sàng sản xuất',
		'Vuzix provides full-stack engineering support for headworn device development, including optical design, system integration, prototyping, and waveguide manufacturing.' => 'Vuzix cung cấp hỗ trợ kỹ thuật toàn diện cho việc phát triển thiết bị đeo đầu, bao gồm thiết kế quang học, tích hợp hệ thống, tạo mẫu và sản xuất ống dẫn sóng.',
		'Based in New York State, we work with established global manufacturing partners for scaled production. This enables a faster, lower-risk path from early concept through to market-ready AI smart glasses, while maintaining a strong balance of performance, efficiency, and scalability.' => 'Có trụ sở tại tiểu bang New York, chúng tôi hợp tác với các đối tác sản xuất toàn cầu đã có uy tín để sản xuất quy mô lớn. Điều này tạo ra một con đường nhanh hơn, ít rủi ro hơn từ ý tưởng ban đầu đến kính thông minh AI sẵn sàng ra thị trường, đồng thời duy trì sự cân bằng vững chắc giữa hiệu suất, hiệu quả và khả năng mở rộng.',
		'Explore engineering services →' => 'Khám phá dịch vụ kỹ thuật →',
		'Build it together' => 'Cùng nhau xây dựng',
		'Looking for a pre-integrated smart glasses platform for faster deployment?' => 'Bạn đang tìm một nền tảng kính thông minh được tích hợp sẵn để triển khai nhanh hơn?',
		'Vuzix Ultralite OEM Platform combines waveguide optics, display systems, and reference hardware into several configurable smart glasses designs.' => 'Vuzix Ultralite OEM Platform kết hợp quang học ống dẫn sóng, hệ thống hiển thị và phần cứng tham chiếu thành nhiều thiết kế kính thông minh có thể cấu hình.',
		'This combination enables OEM partners to move quickly from concept to working prototypes, while maintaining flexibility for application-specific customization. It\'s a proven foundation for both rapid evaluation and scalable product development.' => 'Sự kết hợp này giúp các đối tác OEM nhanh chóng chuyển từ ý tưởng sang mẫu thử hoạt động, đồng thời vẫn giữ được sự linh hoạt để tùy biến theo từng ứng dụng cụ thể. Đây là nền tảng đã được kiểm chứng cho cả đánh giá nhanh và phát triển sản phẩm có khả năng mở rộng.',
		'OEM Applications' => 'Ứng dụng OEM',
		'How Vuzix technology is deployed in the real world' => 'Cách công nghệ Vuzix được triển khai trong thực tế',
		'Secure, rugged AR systems for mission-critical environments' => 'Hệ thống AR an toàn, bền bỉ cho các môi trường quan trọng',
		'Waveguide-based headworn solutions for situational awareness, training, and field operations.' => 'Giải pháp đeo đầu dựa trên ống dẫn sóng cho nhận thức tình huống, đào tạo và hoạt động hiện trường.',
		'Explore Defense →' => 'Khám phá Quốc phòng →',
		'Hands-free AI smart glasses for frontline workforces' => 'Kính thông minh AI rảnh tay cho lực lượng lao động tuyến đầu',
		'Smart glasses solutions for logistics, manufacturing, field service, and remote assistance.' => 'Giải pháp kính thông minh cho hậu cần, sản xuất, dịch vụ hiện trường và hỗ trợ từ xa.',
		'Explore Enterprise →' => 'Khám phá Doanh nghiệp →',
		'Lightweight, intelligent eyewear for everyday use' => 'Kính mắt thông minh, nhẹ nhàng cho sử dụng hàng ngày',
		'Next-generation wearable concepts designed for all-day, on-the-go experiences.' => 'Các ý tưởng thiết bị đeo thế hệ tiếp theo được thiết kế cho trải nghiệm sử dụng cả ngày, di chuyển linh hoạt.',
		'Explore Broad Market Eyewear →' => 'Khám phá Kính mắt thị trường rộng →',
		'PARTNERS' => 'ĐỐI TÁC',
		'Our display ecosystem' => 'Hệ sinh thái màn hình của chúng tôi',
		'Vuzix OEM solutions integrate with many of the leading display engines on the market.' => 'Các giải pháp OEM của Vuzix tích hợp với nhiều công nghệ hiển thị hàng đầu trên thị trường.',
		'Which Vuzix OEM path should I start with?' => 'Tôi nên bắt đầu với lộ trình OEM nào của Vuzix?',
		'Three engagement paths fit different starting points: choose Waveguide Optics if you\'ve already designed your AR device and need to source the optical components; choose Engineering Services if you want accelerated co-development of your device across Vuzix\' optical, electrical, and mechanical systems; choose Ultralite OEM Platform for the fastest path if you want to start from a pre-integrated reference design and focus on industrial design, software, and brand. The Vuzix OEM team helps confirm the right path based on program goals.' => 'Ba lộ trình hợp tác phù hợp với các điểm khởi đầu khác nhau: chọn Waveguide Optics nếu bạn đã thiết kế thiết bị AR của mình và cần nguồn cung linh kiện quang học; chọn Engineering Services nếu bạn muốn đẩy nhanh việc đồng phát triển thiết bị trên các hệ thống quang học, điện và cơ khí của Vuzix; chọn Ultralite OEM Platform để có con đường nhanh nhất nếu bạn muốn bắt đầu từ một thiết kế tham chiếu được tích hợp sẵn và tập trung vào thiết kế công nghiệp, phần mềm và thương hiệu. Nhóm OEM của Vuzix sẽ giúp xác nhận lộ trình phù hợp dựa trên mục tiêu chương trình.',
		'What does a Vuzix OEM engagement actually look like in practice?' => 'Một hợp tác OEM với Vuzix trong thực tế trông như thế nào?',
		'A Vuzix OEM engagement combines technology supply with engineering coordination within a single program structure. Vuzix provides the underlying technology — waveguides, display integration, processing architecture, firmware support, regulatory guidance — and coordinates a network of manufacturing and technology partners. OEM partners control product identity, industrial design, software experience, AI applications, materials, pricing, distribution, and customer relationships. Vuzix and the partner define the boundary explicitly at the start of the program.' => 'Một hợp tác OEM với Vuzix kết hợp việc cung cấp công nghệ với sự điều phối kỹ thuật trong một cấu trúc chương trình duy nhất. Vuzix cung cấp công nghệ nền tảng — ống dẫn sóng, tích hợp hiển thị, kiến trúc xử lý, hỗ trợ firmware, hướng dẫn về quy định — và điều phối một mạng lưới các đối tác sản xuất và công nghệ. Các đối tác OEM kiểm soát bản sắc sản phẩm, thiết kế công nghiệp, trải nghiệm phần mềm, ứng dụng AI, vật liệu, giá cả, phân phối và quan hệ khách hàng. Vuzix và đối tác sẽ xác định rõ ranh giới này ngay từ khi bắt đầu chương trình.',
		'Can I work with Vuzix without a fully defined product yet?' => 'Tôi có thể hợp tác với Vuzix khi chưa xác định đầy đủ sản phẩm không?',
		'Yes. Vuzix supports OEM partners across every development stage, including early feasibility evaluation and product category definition. The OEM readiness assessment quiz and engineering conversation can help define requirements, identify which Vuzix capabilities apply, and surface tradeoffs across optical performance, form factor, and production economics before committing to a specific platform or custom path.' => 'Có. Vuzix hỗ trợ các đối tác OEM ở mọi giai đoạn phát triển, bao gồm đánh giá tính khả thi ban đầu và xác định danh mục sản phẩm. Bài đánh giá mức độ sẵn sàng OEM và các buổi trao đổi kỹ thuật có thể giúp xác định yêu cầu, nhận diện những năng lực của Vuzix phù hợp, và làm rõ các đánh đổi giữa hiệu suất quang học, hình dạng thiết bị và hiệu quả kinh tế sản xuất trước khi quyết định một nền tảng cụ thể hay lộ trình tùy chỉnh.',
		'Ready to build with Vuzix?' => 'Sẵn sàng xây dựng cùng Vuzix?',
		'Hands-free remote support for field technicians' => 'Hỗ trợ từ xa rảnh tay cho kỹ thuật viên hiện trường',
		'Annual Waveguide Capcity' => 'Công suất ống dẫn sóng hàng năm',
		'Certified Facility' => 'Cơ sở được chứng nhận',
		'Waveguide Manufacturing' => 'Sản xuất ống dẫn sóng',
		'Off-the-shelf Designs' => 'Thiết kế có sẵn',
		'Thinnest Full-Color Configuration' => 'Cấu hình Full-Color mỏng nhất',
		'BUILD YOUR KIT' => 'XÂY DỰNG BỘ KIT CỦA BẠN',
		'Your Choice of Hardware' => 'Lựa chọn phần cứng của bạn',
		'High performance in harsh conditions' => 'Hiệu suất cao trong điều kiện khắc nghiệt',
		'Long-lasting power bank for full shift coverage' => 'Pin sạc dự phòng bền lâu, đủ dùng cho toàn bộ ca làm việc',
		'Buy Now →' => 'Mua ngay →',
		'Lightweight, grab-and-go wearable' => 'Thiết bị đeo nhẹ, tiện lợi mang theo',
		'Ideal for as-needed frontline and light industrial use' => 'Lý tưởng cho nhu cầu sử dụng tuyến đầu và công nghiệp nhẹ khi cần',
		'INTEGRATIONS' => 'TÍCH HỢP',
		'Each kit comes pre-configured to work with Zoom &amp; Microsoft Teams' => 'Mỗi bộ kit đều được cấu hình sẵn để hoạt động với Zoom &amp; Microsoft Teams',
		'Vuzix Remote Assist for Microsoft Teams is a high security version adhering to Microsoft security guidelines. As such, to run this application in your instance you will need your IT admin to grant permissions to enable. This is a MS verified application.' => 'Vuzix Remote Assist for Microsoft Teams là phiên bản bảo mật cao, tuân thủ các hướng dẫn bảo mật của Microsoft. Do đó, để chạy ứng dụng này trong hệ thống của bạn, bạn sẽ cần quản trị viên IT cấp quyền để bật ứng dụng. Đây là ứng dụng đã được Microsoft xác minh.',
		'Read the Advisement.' => 'Đọc thông báo khuyến nghị.',
		'DESIGNED FOR RESULTS' => 'ĐƯỢC THIẾT KẾ ĐỂ MANG LẠI KẾT QUẢ',
		'Key Benefits' => 'Lợi ích chính',
		'Ready Out-of-the-box' => 'Sẵn sàng sử dụng ngay',
		'Your choice of smart glasses, available to connect with MS Teams and Zoom for rapid deployment.' => 'Lựa chọn kính thông minh theo ý bạn, có thể kết nối với MS Teams và Zoom để triển khai nhanh chóng.',
		'See What They See — In Real Time' => 'Xem những gì họ thấy — Theo thời gian thực',
		'Share a live, first-person view with remote experts for faster guidance and decision-making.' => 'Chia sẻ góc nhìn trực tiếp theo góc nhìn thứ nhất với các chuyên gia từ xa để được hướng dẫn và ra quyết định nhanh hơn.',
		'Hands-Free by Design' => 'Rảnh tay theo thiết kế',
		'Keep workers focused on the task while maintaining full mobility and situational awareness.' => 'Giúp người lao động tập trung vào công việc trong khi vẫn duy trì khả năng di chuyển hoàn toàn và nhận thức tình huống.',
		'Field-Tested' => 'Đã được kiểm nghiệm thực tế',
		'Durable hardware and accessories designed for demanding work environments.' => 'Phần cứng và phụ kiện bền chắc, được thiết kế cho các môi trường làm việc khắc nghiệt.',
		'Uses' => 'Ứng dụng',
		'Expert Support' => 'Hỗ trợ từ chuyên gia',
		'Built for Remote Assist' => 'Được xây dựng cho Hỗ trợ từ xa',
		'The Vuzix Remote Assist Kit is purpose-built for hands-free mentoring, equipment troubleshooting, and training, where remote experts can guide workers as if they were on site.' => 'Vuzix Remote Assist Kit được thiết kế chuyên biệt cho việc cố vấn rảnh tay, khắc phục sự cố thiết bị và đào tạo, giúp các chuyên gia từ xa hướng dẫn người lao động như thể họ đang có mặt tại hiện trường.',
		'Field service and maintenance' => 'Dịch vụ hiện trường và bảo trì',
		'Equipment installation and repair' => 'Lắp đặt và sửa chữa thiết bị',
		'Remote inspections and audits' => 'Kiểm tra và đánh giá từ xa',
		'Workforce training and onboarding' => 'Đào tạo và định hướng nhân viên mới',
		'Collarboration Software' => 'Phần mềm cộng tác',
		'Each Remote Assist Kit comes with software to connect with your corporate instance of Microsoft Teams® and Zoom®, enabling seamless integration with existing enterprise collaboration workflows.' => 'Mỗi Remote Assist Kit đi kèm phần mềm để kết nối với hệ thống Microsoft Teams® và Zoom® của doanh nghiệp bạn, giúp tích hợp liền mạch với các quy trình hợp tác doanh nghiệp hiện có.',
		'Secure, familiar platforms' => 'Nền tảng an toàn, quen thuộc',
		'Real-time video, audio, and screen sharing' => 'Chia sẻ video, âm thanh và màn hình theo thời gian thực',
		'Easy setup and integration' => 'Dễ dàng cài đặt và tích hợp',
		'Looking for a Volume Order?' => 'Bạn cần đặt hàng số lượng lớn?',
		'Connect with our sales team for large quantity orders and deployment support.' => 'Liên hệ với nhóm bán hàng của chúng tôi để được hỗ trợ đặt hàng số lượng lớn và triển khai.',
		'Contact Sales →' => 'Liên hệ Bán hàng →',
		'A kit-based smart glasses offering designed to accelerate warehouse deployment' => 'Giải pháp kính thông minh dạng bộ kit được thiết kế để đẩy nhanh quá trình triển khai tại kho hàng',
		'A risk-free path to deployment' => 'Con đường triển khai không rủi ro',
		'See how hands-free smart glasses can improve picking accuracy, packing speed, receiving efficiency, and inventory confidence, before you commit to deployment.' => 'Xem cách kính thông minh rảnh tay có thể cải thiện độ chính xác khi lấy hàng, tốc độ đóng gói, hiệu quả nhận hàng và độ tin cậy trong kiểm kê, trước khi bạn quyết định triển khai.',
		'is designed specifically for warehouse and logistics stakeholders to understand, hands-on, how a smart glasses solution could work in their environment, without any up-front system integration.' => 'được thiết kế riêng để các bên liên quan trong lĩnh vực kho hàng và logistics có thể trực tiếp trải nghiệm cách một giải pháp kính thông minh có thể vận hành trong môi trường của họ, mà không cần tích hợp hệ thống ngay từ đầu.',
		'Because we know every client setup is unique, each starter kit comes with a pair of Vuzix M400 or LX1 smart glasses preloaded with Mobilium, a highly flexible software suite that integrates with most Warehouse Management Systems (WMS) and ERP platforms.' => 'Vì chúng tôi hiểu rằng mỗi hệ thống của khách hàng đều khác nhau, mỗi bộ kit khởi đầu đi kèm một cặp kính thông minh Vuzix M400 hoặc LX1 được cài đặt sẵn Mobilium, một bộ phần mềm linh hoạt cao có khả năng tích hợp với hầu hết các Hệ thống Quản lý Kho hàng (WMS) và nền tảng ERP.',
		'THE BENEFITS' => 'LỢI ÍCH',
		'Why a validation program' => 'Vì sao cần một chương trình thử nghiệm',
		'Enable hands-free, eyes-up execution on the floor' => 'Cho phép thao tác rảnh tay, mắt luôn hướng lên trong khu vực làm việc',
		'Prevent mis-sorts during pallet build' => 'Ngăn ngừa phân loại sai khi xếp pallet',
		'Reduce mis-picks and shipping errors' => 'Giảm sai sót khi lấy hàng và lỗi giao hàng',
		'Simplify onboarding and training' => 'Đơn giản hóa quá trình đào tạo và làm quen công việc',
		'Improve dock-to-stock speed' => 'Cải thiện tốc độ từ khi nhận hàng đến khi nhập kho',
		'Increase pack station accuracy' => 'Tăng độ chính xác tại trạm đóng gói',
		'THE SOLUTION' => 'GIẢI PHÁP',
		'Hands-free, hassle-free solutions' => 'Giải pháp rảnh tay, không phiền phức',
		'After an initial consultation with one of our Logistics Solutions Team members, we ship you a pre-configured smart glasses starter kit loaded with five ready-to-run warehouse workflows to evaluate.' => 'Sau buổi tư vấn ban đầu với một thành viên trong Đội ngũ Giải pháp Logistics của chúng tôi, chúng tôi sẽ gửi cho bạn một bộ kit kính thông minh khởi đầu đã được cấu hình sẵn, tích hợp năm quy trình làm việc trong kho hàng sẵn sàng để đánh giá.',
		'IT teams can rest easy: There’s no integration required for the validation program.' => 'Đội ngũ IT có thể hoàn toàn an tâm: chương trình thử nghiệm không yêu cầu bất kỳ tích hợp hệ thống nào.',
		'The Platform' => 'Nền tảng',
		'Built on Mobilium' => 'Được xây dựng trên nền tảng Mobilium',
		'Each starter kit comes preloaded with' => 'Mỗi bộ kit khởi đầu được cài đặt sẵn',
		', our enterprise software suite created specifically for connecting smart glasses to ERP and Warehouse Management Systems.' => ', bộ phần mềm doanh nghiệp của chúng tôi được tạo ra riêng để kết nối kính thông minh với các hệ thống ERP và Quản lý Kho hàng.',
		'Bridge mobile devices and ERP systems' => 'Kết nối thiết bị di động với hệ thống ERP',
		'Enable rapid development of warehouse workflows' => 'Cho phép phát triển nhanh các quy trình làm việc trong kho hàng',
		'Eliminate inefficiencies in manual processes' => 'Loại bỏ sự kém hiệu quả trong các quy trình thủ công',
		'Capture operational data across the organization' => 'Thu thập dữ liệu vận hành trên toàn tổ chức',
		'Accelerate digital transformation initiatives' => 'Đẩy nhanh các sáng kiến chuyển đổi số',
		'What\'s Included' => 'Bao gồm những gì',
		'5 hands-free warehouse workflows' => '5 quy trình làm việc rảnh tay trong kho hàng',
		'Pick Assist: Faster Picking. Fewer Errors. Faster Onboarding.' => 'Pick Assist: Lấy hàng nhanh hơn. Ít lỗi hơn. Đào tạo nhanh hơn.',
		'Pick Assist guides an operator to:' => 'Pick Assist hướng dẫn người vận hành đến:',
		'The correct location' => 'Vị trí chính xác',
		'The correct item' => 'Sản phẩm chính xác',
		'Real-time confirmation via camera scanning' => 'Xác nhận theo thời gian thực qua quét camera',
		'This demo shows how hands-free visual guidance:' => 'Bản demo này cho thấy hướng dẫn trực quan rảnh tay có thể:',
		'Reduces mis-picks' => 'Giảm sai sót khi lấy hàng',
		'Minimizes re-checking time' => 'Giảm thiểu thời gian kiểm tra lại',
		'Helps new workers become productive quickly' => 'Giúp nhân viên mới nhanh chóng đạt năng suất',
		'Keeps both hands available for physical tasks' => 'Giữ cho cả hai tay luôn sẵn sàng cho các thao tác thực tế',
		'Goods Receipt: Frictionless Dock-to-Stock Execution' => 'Nhận hàng: Quy trình từ nhận hàng đến nhập kho suôn sẻ',
		'This workflow simulates a receiving flow with:' => 'Quy trình này mô phỏng luồng nhận hàng với:',
		'Camera-first barcode scanning' => 'Quét mã vạch ưu tiên bằng camera',
		'Multi-case capture for speed' => 'Chụp nhiều thùng hàng cùng lúc để tăng tốc độ',
		'Exception handling when discrepancies occur' => 'Xử lý ngoại lệ khi có sai lệch xảy ra',
		'See how smart glasses can:' => 'Xem cách kính thông minh có thể:',
		'Reduce scanning friction' => 'Giảm trở ngại khi quét',
		'Keep inbound processing moving' => 'Duy trì luồng xử lý hàng nhập không bị gián đoạn',
		'Improve receiving accuracy' => 'Cải thiện độ chính xác khi nhận hàng',
		'Handle exceptions without slowing throughput' => 'Xử lý ngoại lệ mà không làm chậm năng suất',
		'Pack Assist: Ship the Right Order. The First Time' => 'Pack Assist: Giao đúng đơn hàng. Ngay từ lần đầu',
		'Pack Assist guides the operator through:' => 'Pack Assist hướng dẫn người vận hành qua các bước:',
		'Packing a single order' => 'Đóng gói một đơn hàng',
		'Validating each item before it goes into the carton' => 'Xác nhận từng sản phẩm trước khi đưa vào thùng carton',
		'This workflow highlights how real-time confirmation:' => 'Quy trình này cho thấy việc xác nhận theo thời gian thực có thể:',
		'Reduces shipping errors' => 'Giảm lỗi giao hàng',
		'Prevents costly returns' => 'Ngăn ngừa hàng trả lại gây tốn kém',
		'Cuts rework' => 'Giảm thiểu công việc làm lại',
		'Keeps pack stations moving efficiently' => 'Giữ cho các trạm đóng gói hoạt động hiệu quả',
		'Pallet Build: Prevent Mis-Sorts at High Throughput' => 'Xếp Pallet: Ngăn ngừa phân loại sai ở năng suất cao',
		'This outbound scenario demonstrates:' => 'Tình huống xuất hàng này minh họa:',
		'Scanning cases at end-of-line' => 'Quét thùng hàng ở cuối dây chuyền',
		'Directing each case to the correct pallet' => 'Điều hướng từng thùng hàng đến đúng pallet',
		'Building multiple pallets in parallel' => 'Xếp nhiều pallet đồng thời',
		'The focus: hands-free execution that prevents mis-sorts while allowing operators to maintain speed and safety.' => 'Trọng tâm: thao tác rảnh tay giúp ngăn ngừa phân loại sai trong khi vẫn cho phép người vận hành duy trì tốc độ và an toàn.',
		'Bin Audit: Fast Location Verification Without Full Cycle Counts' => 'Kiểm tra Ngăn hàng: Xác minh vị trí nhanh mà không cần kiểm kê toàn bộ',
		'This workflow shows:' => 'Quy trình này cho thấy:',
		'Quick bin validation' => 'Xác thực ngăn hàng nhanh',
		'Exception handling' => 'Xử lý ngoại lệ',
		'Location accuracy confirmation' => 'Xác nhận độ chính xác vị trí',
		'It highlights how eyes-up workflows help maintain inventory confidence while keeping operations moving.' => 'Điều này cho thấy các quy trình mắt luôn hướng lên giúp duy trì độ tin cậy trong kiểm kê trong khi hoạt động vẫn diễn ra liên tục.',
		'THE PROCESS' => 'QUY TRÌNH',
		'How the validation program works' => 'Chương trình thử nghiệm hoạt động như thế nào',
		'1. Discover Call' => '1. Cuộc gọi Khám phá',
		'Meet with a Vuzix Logistics Solutions specialist to discuss your operation, WMS environment, and evaluation goals to see if this program is right for you.' => 'Gặp gỡ một chuyên gia Giải pháp Logistics của Vuzix để thảo luận về hoạt động, môi trường WMS và mục tiêu đánh giá của bạn, nhằm xác định liệu chương trình này có phù hợp với bạn không.',
		'3. Guided Evaluation' => '3. Đánh giá có hướng dẫn',
		'Your team runs hands-free workflows and evaluates speed, accuracy, and usability.' => 'Nhóm của bạn thực hiện các quy trình rảnh tay và đánh giá tốc độ, độ chính xác và khả năng sử dụng.',
		'2. Kit Shipment' => '2. Giao bộ kit',
		'Receive a pre-configured Pick &amp; Pack Starter Kit loaded with five warehouse workflows.' => 'Nhận bộ kit khởi đầu Pick &amp; Pack đã được cấu hình sẵn với năm quy trình làm việc trong kho hàng.',
		'4. Review &amp; Next Steps' => '4. Đánh giá &amp; Các bước tiếp theo',
		'Discuss integration possibilities, ROI opportunities, and pilot deployment options.' => 'Thảo luận về các khả năng tích hợp, cơ hội hoàn vốn (ROI) và các phương án triển khai thử nghiệm.',
		'Ready to evaluate smart glasses in your warehouse?' => 'Bạn đã sẵn sàng đánh giá kính thông minh trong kho hàng của mình?',
		'The Pick &amp; Pack Validation Program is available through consultation with our logistics team.' => 'Chương trình Thử nghiệm Pick &amp; Pack được cung cấp thông qua tư vấn với đội ngũ logistics của chúng tôi.',
		'Exceed your customers\' expectations by accelerating production timelines, improving quality, and reducing costs with Vuzix smart glasses.' => 'Vượt qua kỳ vọng của khách hàng bằng cách đẩy nhanh tiến độ sản xuất, nâng cao chất lượng và giảm chi phí với kính thông minh Vuzix.',
		'Real-Time Guidance for the Modern Factory' => 'Hướng dẫn theo thời gian thực cho nhà máy hiện đại',
		'Vuzix smart glasses help manufacturers improve productivity, quality, and workforce readiness by delivering critical information directly within the worker’s field of view.' => 'Kính thông minh Vuzix giúp các nhà sản xuất nâng cao năng suất, chất lượng và mức độ sẵn sàng của lực lượng lao động bằng cách cung cấp thông tin quan trọng trực tiếp trong tầm nhìn của người lao động.',
		'Designed for fast-paced production environments, smart glasses provide hands-free access to work instructions, process documentation, and operational data while allowing employees to remain focused on the task at hand.' => 'Được thiết kế cho môi trường sản xuất có tốc độ cao, kính thông minh cung cấp quyền truy cập rảnh tay vào hướng dẫn công việc, tài liệu quy trình và dữ liệu vận hành, đồng thời giúp nhân viên tập trung vào công việc đang thực hiện.',
		'This helps reduce errors, improve compliance, and accelerate onboarding for new team members.' => 'Điều này giúp giảm lỗi, cải thiện tuân thủ quy trình và đẩy nhanh quá trình làm quen công việc cho các thành viên mới.',
		'From assembly and quality assurance to maintenance and troubleshooting, Vuzix devices support a wide range of manufacturing workflows. Integrated remote assistance and real-time visibility enable organizations to resolve issues faster,' => 'Từ lắp ráp, đảm bảo chất lượng đến bảo trì và xử lý sự cố, các thiết bị Vuzix hỗ trợ đa dạng các quy trình sản xuất. Hỗ trợ từ xa được tích hợp cùng khả năng quan sát theo thời gian thực giúp các tổ chức giải quyết sự cố nhanh hơn,',
		'reduce downtime, and maintain consistent production standards across facilities.' => 'giảm thời gian ngừng hoạt động và duy trì các tiêu chuẩn sản xuất đồng nhất trên toàn bộ cơ sở.',
		'Built for enterprise deployment, we help manufacturers streamline operations, improve workforce performance, and adapt to the growing demands of modern industrial environments.' => 'Được xây dựng cho triển khai ở quy mô doanh nghiệp, chúng tôi giúp các nhà sản xuất hợp lý hóa hoạt động, cải thiện hiệu suất lực lượng lao động và thích ứng với nhu cầu ngày càng tăng của môi trường công nghiệp hiện đại.',
		'WORK INSTRUCTIONS' => 'HƯỚNG DẪN CÔNG VIỆC',
		'Vuzix LX1 smart glasses for manufacturing' => 'Kính thông minh Vuzix LX1 cho ngành sản xuất',
		'Reduction in onboarding time' => 'Giảm thời gian đào tạo làm quen',
		'Manufacturing’s reliance on manual processes can result in operational errors and inefficiencies. In addition, the industry faces a growing skills gap and high turnover rates.' => 'Sự phụ thuộc của ngành sản xuất vào các quy trình thủ công có thể dẫn đến lỗi vận hành và kém hiệu quả. Ngoài ra, ngành này còn đang đối mặt với khoảng cách kỹ năng ngày càng lớn và tỷ lệ nghỉ việc cao.',
		'Vuzix enterprise solutions integrate AR technology into your workflows, helping you speed up production, increase compliance with protocols, and reduce training times. The best of vision + voice putting instructions right in your workers’ field of view, and giving you real time analytics across your workforce with practical AR for the frontline.' => 'Các giải pháp doanh nghiệp của Vuzix tích hợp công nghệ AR vào quy trình làm việc của bạn, giúp đẩy nhanh sản xuất, tăng mức độ tuân thủ quy trình và giảm thời gian đào tạo. Sự kết hợp tối ưu giữa hình ảnh và giọng nói đưa hướng dẫn trực tiếp vào tầm nhìn của người lao động, đồng thời cung cấp phân tích theo thời gian thực trên toàn bộ lực lượng lao động của bạn với công nghệ AR thực tiễn cho tuyến đầu.',
		'Benefits of smart glasses in manufacturing' => 'Lợi ích của kính thông minh trong sản xuất',
		'Boosts overall productivity' => 'Tăng năng suất tổng thể',
		'Decrease workflow interruptions by providing guidance to workers anytime, anywhere.' => 'Giảm gián đoạn quy trình làm việc bằng cách cung cấp hướng dẫn cho người lao động mọi lúc, mọi nơi.',
		'Simplify the Training Process' => 'Đơn giản hóa quy trình đào tạo',
		'Provide dynamic, on-the-job training to quickly get new hires learning by doing.' => 'Cung cấp đào tạo linh động ngay tại nơi làm việc để nhân viên mới nhanh chóng học hỏi qua thực hành.',
		'Manufacture High Quality Product' => 'Sản xuất sản phẩm chất lượng cao',
		'Make it easy to follow approved processes, helping to reduce errors and omissions.' => 'Giúp dễ dàng tuân theo các quy trình đã được phê duyệt, góp phần giảm lỗi và thiếu sót.',
		'Detect &amp; Resolve Issues in Real Time' => 'Phát hiện &amp; Giải quyết sự cố theo thời gian thực',
		'Deliver exceptional products on time and within budget.' => 'Cung cấp sản phẩm chất lượng vượt trội, đúng thời hạn và trong phạm vi ngân sách.',
		'Improve Customer Satisfaction' => 'Nâng cao sự hài lòng của khách hàng',
		'Facilitate Increases in Automation' => 'Thúc đẩy gia tăng tự động hóa',
		'Enable your employees to monitor Internet of Things (IoT) devices with ease.' => 'Giúp nhân viên của bạn dễ dàng theo dõi các thiết bị Internet vạn vật (IoT).',
		'CONNECTED WORKFORCE' => 'LỰC LƯỢNG LAO ĐỘNG KẾT NỐI',
		'Vuzix M400 smart glasses for manufacturing' => 'Kính thông minh Vuzix M400 cho ngành sản xuất',
		'The Vuzix M400 smart glasses help manufacturers improve productivity by allowing workers to:' => 'Kính thông minh Vuzix M400 giúp các nhà sản xuất nâng cao năng suất bằng cách cho phép người lao động:',
		'Access instructions, diagrams, and videos via AR with audio and visual overlays' => 'Truy cập hướng dẫn, sơ đồ và video qua AR với lớp phủ âm thanh và hình ảnh',
		'Document steps and detect issues' => 'Ghi lại các bước thực hiện và phát hiện sự cố',
		'Livestream remote support, hands-free' => 'Truyền trực tiếp hỗ trợ từ xa, hoàn toàn rảnh tay',
		'Manufacturing use cases' => 'Các trường hợp ứng dụng trong sản xuất',
		'REMOTE SUPPORT' => 'HỖ TRỢ TỪ XA',
		'Reduced Downtime Due to Repairs and Maintenance' => 'Giảm thời gian ngừng hoạt động do sửa chữa và bảo trì',
		'The downtime required to maintain and repair equipment can be costly for businesses.' => 'Thời gian ngừng hoạt động cần thiết để bảo trì và sửa chữa thiết bị có thể gây tốn kém cho doanh nghiệp.',
		'With our industrial smart glasses, engineers can resolve issues quickly by viewing real-time machine data and 3D models, detailed instructions from back-end systems or video tutorials overlaid onto machines that need attention. Workers can also have remote technicians see what they see and provide live support.' => 'Với kính thông minh công nghiệp của chúng tôi, kỹ sư có thể giải quyết sự cố nhanh chóng bằng cách xem dữ liệu máy theo thời gian thực và mô hình 3D, hướng dẫn chi tiết từ hệ thống back-end hoặc video hướng dẫn được phủ trực tiếp lên các máy cần xử lý. Người lao động cũng có thể để kỹ thuật viên từ xa nhìn thấy những gì họ đang thấy và cung cấp hỗ trợ trực tiếp.',
		'Big improvements for quality assurance' => 'Cải tiến lớn cho việc đảm bảo chất lượng',
		'Using Vuzix Mobilium Suite, workers can scan and track hundreds of datapoints, providing traceable validation and intelligent processing.' => 'Sử dụng bộ phần mềm Vuzix Mobilium, người lao động có thể quét và theo dõi hàng trăm điểm dữ liệu, cung cấp khả năng xác thực có thể truy vết và xử lý thông minh.',
		'With our Pick &amp; Pack Validation program, see how hands-free smart glasses can improve picking accuracy, packing speed, receiving efficiency, and inventory confidence, before you commit to deployment.' => 'Với chương trình Thử nghiệm Pick &amp; Pack của chúng tôi, hãy xem cách kính thông minh rảnh tay có thể cải thiện độ chính xác khi lấy hàng, tốc độ đóng gói, hiệu quả nhận hàng và độ tin cậy trong kiểm kê, trước khi bạn quyết định triển khai.',
		'Training' => 'Đào tạo',
		'More effective on-the-job training' => 'Đào tạo tại nơi làm việc hiệu quả hơn',
		'Manual-based training isn’t effective or appealing to new manufacturing hires. AR training with Vuzix smart glasses offers a more intuitive method of instruction.' => 'Đào tạo dựa trên tài liệu thủ công không hiệu quả và không hấp dẫn đối với nhân viên mới trong ngành sản xuất. Đào tạo bằng AR với kính thông minh Vuzix mang lại phương pháp hướng dẫn trực quan hơn.',
		'With Vuzix, new employees can view step-by-step instructions and visual aids overlaid on the equipment they need to assemble and operate. Plus, experienced staff can use Vuzix smart glasses to create valuable training material.' => 'Với Vuzix, nhân viên mới có thể xem hướng dẫn từng bước và các công cụ hỗ trợ trực quan được phủ trực tiếp lên thiết bị mà họ cần lắp ráp và vận hành. Ngoài ra, nhân viên có kinh nghiệm có thể sử dụng kính thông minh Vuzix để tạo ra tài liệu đào tạo giá trị.',
		'Explore Remote Support →' => 'Khám phá Hỗ trợ từ xa →',
		'See how Vuzix smart glasses provide critical support with remote assist and work instructions.' => 'Xem cách kính thông minh Vuzix cung cấp hỗ trợ quan trọng với hỗ trợ từ xa và hướng dẫn công việc.',
		'Explore Vuzix M400 Smart Glasses →' => 'Khám phá Kính thông minh Vuzix M400 →',
		'How are smart glasses used on a manufacturing floor?' => 'Kính thông minh được sử dụng như thế nào trên sàn sản xuất?',
		'Vuzix smart glasses support manufacturing workflows including guided assembly with step-by-step visual instructions, quality inspection and digital checklists, machine operator support with real-time data overlays, training and onboarding for new operators, and remote expert assistance for line issues. Operators can access work instructions, schematics, torque values, or part identification without leaving their workstation or shifting focus from the equipment, which reduces errors, supports consistency across shifts, and shortens training time for new hires.' => 'Kính thông minh Vuzix hỗ trợ các quy trình sản xuất bao gồm lắp ráp có hướng dẫn với chỉ dẫn trực quan từng bước, kiểm tra chất lượng và danh sách kiểm tra số, hỗ trợ người vận hành máy với lớp phủ dữ liệu theo thời gian thực, đào tạo và làm quen công việc cho người vận hành mới, và hỗ trợ chuyên gia từ xa cho các sự cố trên dây chuyền. Người vận hành có thể truy cập hướng dẫn công việc, sơ đồ kỹ thuật, giá trị lực siết hoặc nhận diện linh kiện mà không cần rời khỏi vị trí làm việc hay chuyển hướng tập trung khỏi thiết bị, giúp giảm lỗi, duy trì tính đồng nhất giữa các ca làm việc và rút ngắn thời gian đào tạo cho nhân viên mới.',
		'Which Vuzix smart glasses fit a manufacturing environment?' => 'Kính thông minh Vuzix nào phù hợp với môi trường sản xuất?',
		'The Vuzix M400 is the most common choice for manufacturing workflows, with hot-swappable batteries supporting multi-shift operation, all-purpose optical capability, and durability for industrial environments. For environments combining warehouse and assembly operations, the Vuzix LX1 may fit better with its 10-hour fixed battery and rugged construction. The Remote Assist Kit adds Microsoft Teams and Zoom integration for line-down events where remote engineering or manufacturer support is needed quickly.' => 'Vuzix M400 là lựa chọn phổ biến nhất cho các quy trình sản xuất, với pin có thể thay nóng hỗ trợ vận hành nhiều ca, khả năng quang học đa dụng và độ bền phù hợp với môi trường công nghiệp. Đối với môi trường kết hợp cả hoạt động kho hàng và lắp ráp, Vuzix LX1 có thể phù hợp hơn nhờ pin cố định 10 giờ và cấu trúc chắc chắn. Remote Assist Kit bổ sung tích hợp Microsoft Teams và Zoom cho các sự cố dừng dây chuyền cần hỗ trợ kỹ thuật hoặc hỗ trợ từ nhà sản xuất từ xa một cách nhanh chóng.',
		'Can Vuzix smart glasses integrate with my MES or quality system?' => 'Kính thông minh Vuzix có thể tích hợp với hệ thống MES hoặc hệ thống chất lượng của tôi không?',
		'Yes. Vuzix smart glasses run Android and provide an open SDK with camera APIs for building custom integrations with manufacturing execution systems, quality management software, ERP platforms, and digital work instruction tools. Standard enterprise mobile device management platforms support provisioning, security, and updates. For larger manufacturing deployments, Vuzix works with system integrators experienced in MES, quality, and shop floor software integration to coordinate technical setup and ongoing support.' => 'Có. Kính thông minh Vuzix chạy trên Android và cung cấp SDK mở với API camera để xây dựng các tích hợp tùy chỉnh với hệ thống điều hành sản xuất (MES), phần mềm quản lý chất lượng, nền tảng ERP và công cụ hướng dẫn công việc số. Các nền tảng quản lý thiết bị di động doanh nghiệp tiêu chuẩn hỗ trợ cấp phát, bảo mật và cập nhật. Đối với các triển khai sản xuất quy mô lớn hơn, Vuzix hợp tác với các đơn vị tích hợp hệ thống có kinh nghiệm về MES, chất lượng và tích hợp phần mềm tại sàn sản xuất để phối hợp thiết lập kỹ thuật và hỗ trợ liên tục.',
		'Vuzix Core™ Waveguides Configuration &amp; Specifications' => 'Cấu hình &amp; Thông số kỹ thuật Ống dẫn sóng Vuzix Core™',
		'Standard waveguide configurations for rapid sampling and OEM integration' => 'Các cấu hình ống dẫn sóng tiêu chuẩn cho lấy mẫu nhanh và tích hợp OEM',
		'Core Waveguide Portfolio' => 'Danh mục Ống dẫn sóng Core',
		'Standard configurations for OEM integration' => 'Các cấu hình tiêu chuẩn cho tích hợp OEM',
		'Vuzix Core™ waveguides are standardized and configurable optical architectures designed for rapid sampling into AI smart glasses.' => 'Ống dẫn sóng Vuzix Core™ là các kiến trúc quang học được tiêu chuẩn hóa và có thể cấu hình, được thiết kế để lấy mẫu nhanh vào kính thông minh AI.',
		'Full color' => 'Màu đầy đủ',
		'Color waveguide configuration with a 40° field of view. Optimized for high-brightness, full-color display performance.' => 'Cấu hình ống dẫn sóng màu với góc nhìn 40°. Được tối ưu hóa cho hiệu suất hiển thị màu đầy đủ, độ sáng cao.',
		'Field of view' => 'Góc nhìn',
		'40°+ diagonal' => '40°+ theo đường chéo',
		'Color' => 'Màu',
		'Configuration' => 'Cấu hình',
		'Binocular' => 'Hai mắt',
		'Compatible' => 'Tương thích',
		'Enterprise AR' => 'AR doanh nghiệp',
		'Defense/Helmet' => 'Quốc phòng/Mũ bảo hộ',
		'Color + Vertical waveguide configuration with a 40° field of view. Designed for helmet-mounted and vertical display.' => 'Cấu hình ống dẫn sóng Màu + Dọc với góc nhìn 40°. Được thiết kế cho hiển thị gắn trên mũ bảo hộ và hiển thị theo chiều dọc.',
		'Monocular-Helmet optimized' => 'Một mắt - tối ưu cho mũ bảo hộ',
		'Defense HUDs, industrial helmets' => 'Màn hình hiển thị quốc phòng (HUD), mũ bảo hộ công nghiệp',
		'Color waveguide configuration with a 30° field of view with Vuzix Incognito™ technology. Reduces forward light leakage while maintaining high visual fidelity in compact smart glasses systems.' => 'Cấu hình ống dẫn sóng màu với góc nhìn 30° kết hợp công nghệ Vuzix Incognito™. Giảm rò sáng phía trước trong khi vẫn duy trì độ trung thực hình ảnh cao trong các hệ thống kính thông minh nhỏ gọn.',
		'30° diagonal' => '30° theo đường chéo',
		'Enterprise AR, consumer OEM' => 'AR doanh nghiệp, OEM tiêu dùng',
		'Monochrome waveguide configuration with a 30° field of view incorporating Vuzix Incognito™ technology. Designed for low-signature applications requiring minimized external visual output.' => 'Cấu hình ống dẫn sóng đơn sắc với góc nhìn 30°, tích hợp công nghệ Vuzix Incognito™. Được thiết kế cho các ứng dụng có yêu cầu độ nhận diện thấp, cần giảm thiểu tối đa tín hiệu hình ảnh phát ra bên ngoài.',
		'Monochrome green' => 'Đơn sắc xanh lá',
		'Monocular' => 'Một mắt',
		'Proprietary Technologies' => 'Công nghệ độc quyền',
		'Integrated technologies for advanced performance' => 'Các công nghệ tích hợp cho hiệu suất vượt trội',
		'Reduces forward-facing light leakage in waveguide displays, improving image privacy and on-axis clarity without impacting power consumption. Available on core and most custom configurations.' => 'Giảm rò sáng phía trước trong màn hình ống dẫn sóng, cải thiện tính riêng tư của hình ảnh và độ rõ trên trục mà không ảnh hưởng đến mức tiêu thụ điện năng. Có sẵn trên các cấu hình Core và hầu hết các cấu hình tùy chỉnh.',
		'Minimizes substrate stress during imprinting, enabling the use of fragile materials such as ultra-thin glass and polymers. Supports higher-yield manufacturing and thinner waveguide architectures.' => 'Giảm thiểu áp lực lên vật liệu nền trong quá trình khắc in, cho phép sử dụng các vật liệu dễ vỡ như kính siêu mỏng và polymer. Hỗ trợ sản xuất với năng suất cao hơn và kiến trúc ống dẫn sóng mỏng hơn.',
		'High-index substrate materials enable integrated vision correction and wider fields of view in lightweight designs. Supports prescription-compatible consumer and clinical applications.' => 'Vật liệu nền có chỉ số khúc xạ cao cho phép tích hợp khả năng điều chỉnh thị lực và góc nhìn rộng hơn trong các thiết kế nhẹ. Hỗ trợ các ứng dụng tiêu dùng và y tế tương thích với tròng kính theo đơn thuốc.',
		'Jumbo Format' => 'Định dạng Jumbo',
		'Expanded waveguide formats designed for large-area display applications, including automotive HUDs, aviation systems, and enterprise environments beyond traditional wearables.' => 'Các định dạng ống dẫn sóng mở rộng được thiết kế cho các ứng dụng hiển thị diện tích lớn, bao gồm màn hình HUD ô tô, hệ thống hàng không và các môi trường doanh nghiệp vượt ra ngoài phạm vi thiết bị đeo truyền thống.',
		'Custom Waveguide Development' => 'Phát triển Ống dẫn sóng tùy chỉnh',
		'Need a configuration beyond the standard options?' => 'Bạn cần một cấu hình vượt ngoài các tùy chọn tiêu chuẩn?',
		'Vuzix develops custom waveguide geometries for OEM partners with application-specific requirements.' => 'Vuzix phát triển các hình dạng ống dẫn sóng tùy chỉnh cho các đối tác OEM có yêu cầu riêng theo từng ứng dụng.',
		'Custom geometries greater than 40°' => 'Hình dạng tùy chỉnh lớn hơn 40°',
		'Display Engines' => 'Bộ hiển thị (Display Engine)',
		'Configurations' => 'Cấu hình',
		'Monocular, binocular, helmet-mounted, automotive, jumbo formats' => 'Một mắt, hai mắt, gắn trên mũ bảo hộ, ô tô, định dạng jumbo',
		'Prescription' => 'Đơn thuốc',
		'Enabled via Litebank™ high-index substrate technology' => 'Được hỗ trợ qua công nghệ vật liệu nền chỉ số khúc xạ cao Litebank™',
		'Materials' => 'Vật liệu',
		'Glass, polymer, ultra-thin glass via Micro-Touch™' => 'Kính, polymer, kính siêu mỏng qua công nghệ Micro-Touch™',
		'Timeline' => 'Thời gian thực hiện',
		'Initial samples typically 8–16 weeks from design confirmation' => 'Mẫu ban đầu thường mất 8–16 tuần kể từ khi xác nhận thiết kế',
		'How do Vuzix Incognito, Litebank, Micro-Touch, and Jumbo Format work together?' => 'Vuzix Incognito, Litebank, Micro-Touch và Jumbo Format hoạt động cùng nhau như thế nào?',
		'These four proprietary technologies can be combined within a single waveguide design depending on program requirements. Incognito reduces forward light leakage, Litebank enables prescription compatibility and wider fields of view through high-index substrates, Micro-Touch supports manufacturing on fragile materials like ultra-thin glass and polymers, and Jumbo Format extends waveguide architecture into larger formats for automotive HUDs and aviation. The Vuzix optics team helps determine which combination matches application requirements.' => 'Bốn công nghệ độc quyền này có thể được kết hợp trong một thiết kế ống dẫn sóng duy nhất, tùy theo yêu cầu của từng chương trình. Incognito giảm rò sáng phía trước, Litebank hỗ trợ khả năng tương thích với tròng kính theo đơn và góc nhìn rộng hơn thông qua vật liệu nền có chỉ số khúc xạ cao, Micro-Touch hỗ trợ sản xuất trên các vật liệu dễ vỡ như kính siêu mỏng và polymer, còn Jumbo Format mở rộng kiến trúc ống dẫn sóng sang các định dạng lớn hơn cho màn hình HUD ô tô và hàng không. Đội ngũ quang học của Vuzix sẽ giúp xác định sự kết hợp nào phù hợp nhất với yêu cầu ứng dụng.',
		'Ready to evaluate waveguides?' => 'Bạn đã sẵn sàng đánh giá ống dẫn sóng?',
		'Explore Vuzix Core™ configurations or define a custom waveguide design with our optics and engineering teams.' => 'Khám phá các cấu hình Vuzix Core™ hoặc xác định một thiết kế ống dẫn sóng tùy chỉnh cùng đội ngũ quang học và kỹ thuật của chúng tôi.',
		'All rights reserved.' => 'Bảo lưu mọi quyền.',
		'Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:' => 'Việc phân phối lại và sử dụng dưới dạng mã nguồn và dạng nhị phân, có hoặc không có sửa đổi, được cho phép với điều kiện các quy định sau đây được đáp ứng:',
		'* Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.' => '* Việc phân phối lại mã nguồn phải giữ nguyên thông báo bản quyền trên, danh sách các điều kiện này và tuyên bố miễn trừ trách nhiệm sau đây.',
		'* Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.' => '* Việc phân phối lại dưới dạng nhị phân phải sao chép lại thông báo bản quyền trên, danh sách các điều kiện này và tuyên bố miễn trừ trách nhiệm sau đây trong tài liệu và/hoặc các tài liệu khác được cung cấp cùng với bản phân phối.',
		'* Neither the name of Vuzix Corporation nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.' => '* Không được sử dụng tên của Vuzix Corporation cũng như tên của những người đóng góp để xác nhận hoặc quảng bá các sản phẩm bắt nguồn từ phần mềm này mà không có sự cho phép trước bằng văn bản.',
		'THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.' => 'PHẦN MỀM NÀY ĐƯỢC CUNG CẤP BỞI CÁC CHỦ SỞ HỮU BẢN QUYỀN VÀ NHỮNG NGƯỜI ĐÓNG GÓP "NGUYÊN TRẠNG" VÀ MỌI BẢO ĐẢM RÕ RÀNG HOẶC NGỤ Ý, BAO GỒM NHƯNG KHÔNG GIỚI HẠN Ở CÁC BẢO ĐẢM NGỤ Ý VỀ KHẢ NĂNG BÁN ĐƯỢC VÀ SỰ PHÙ HỢP CHO MỘT MỤC ĐÍCH CỤ THỂ ĐỀU BỊ TỪ CHỐI. TRONG MỌI TRƯỜNG HỢP, CHỦ SỞ HỮU BẢN QUYỀN HOẶC NHỮNG NGƯỜI ĐÓNG GÓP SẼ KHÔNG CHỊU TRÁCH NHIỆM CHO BẤT KỲ THIỆT HẠI TRỰC TIẾP, GIÁN TIẾP, NGẪU NHIÊN, ĐẶC BIỆT, ĐIỂN HÌNH HOẶC HỆ QUẢ NÀO (BAO GỒM NHƯNG KHÔNG GIỚI HẠN Ở VIỆC MUA SẮM HÀNG HÓA HOẶC DỊCH VỤ THAY THẾ; MẤT KHẢ NĂNG SỬ DỤNG, MẤT DỮ LIỆU HOẶC LỢI NHUẬN; HOẶC GIÁN ĐOẠN HOẠT ĐỘNG KINH DOANH) DÙ PHÁT SINH THEO CÁCH NÀO VÀ DỰA TRÊN BẤT KỲ LÝ THUYẾT TRÁCH NHIỆM NÀO, CHO DÙ LÀ THEO HỢP ĐỒNG, TRÁCH NHIỆM NGHIÊM NGẶT HAY TRÁCH NHIỆM DÂN SỰ (BAO GỒM SỰ BẤT CẨN HOẶC KHÁC) PHÁT SINH THEO BẤT KỲ HÌNH THỨC NÀO TỪ VIỆC SỬ DỤNG PHẦN MỀM NÀY, NGAY CẢ KHI ĐÃ ĐƯỢC CẢNH BÁO VỀ KHẢ NĂNG XẢY RA THIỆT HẠI ĐÓ.',
		'Take the Quiz' => 'Làm bài trắc nghiệm',
		'Determine Your OEM Readiness' => 'Xác định mức độ sẵn sàng OEM của bạn',
		'M400 Compliance' => 'Tuân thủ M400',
		'This guide lists product information for M400 users:' => 'Hướng dẫn này cung cấp thông tin sản phẩm cho người dùng M400:',
		'M400 complies with Part 15 of the FCC Rules. Operation is subject to the following 2 conditions:' => 'M400 tuân thủ Phần 15 của Quy định FCC. Việc vận hành phải tuân theo 2 điều kiện sau:',
		'FCC Statement Operation of this device in the band 5150-5250 MHz is restricted to indoor use only.' => 'Tuyên bố của FCC: Việc vận hành thiết bị này trong băng tần 5150-5250 MHz chỉ được giới hạn sử dụng trong nhà.',
		'5G WiFi:' => 'Wi-Fi 5G:',
		'M400 was tested and certified to not exceed limits in US, Canada, EU, and Japan.' => 'M400 đã được kiểm tra và chứng nhận không vượt quá giới hạn tại Hoa Kỳ, Canada, EU và Nhật Bản.',
		'Defense, Security, &amp; First Responders' => 'Quốc phòng, An ninh &amp; Lực lượng Ứng cứu Đầu tiên',
		'Mission-ready optics for the world\'s most demanding environments' => 'Giải pháp quang học sẵn sàng cho nhiệm vụ, dành cho những môi trường khắc nghiệt nhất trên thế giới',
		'Explore Waveguide Platforms →' => 'Khám phá các Nền tảng Waveguide →',
		'US-Based' => 'Có trụ sở tại Hoa Kỳ',
		'Built for mission-critical teams' => 'Được thiết kế cho các đội thực hiện nhiệm vụ trọng yếu',
		'Vuzix supports defense, security, and first responder programs with waveguide-based AR systems designed for situational awareness, heads-up information delivery, and operational mobility in demanding environments.' => 'Vuzix hỗ trợ các chương trình quốc phòng, an ninh và ứng cứu khẩn cấp bằng các hệ thống AR dựa trên waveguide, được thiết kế để nâng cao nhận thức tình huống, truyền tải thông tin heads-up và đảm bảo khả năng cơ động khi tác nghiệp trong những môi trường khắc nghiệt.',
		'Built on a foundation of advanced waveguide optics, integrated display systems, and configurable hardware platforms, these solutions support a wide range of defense applications, including training, maintenance, logistics, and mission support.' => 'Được xây dựng trên nền tảng quang học waveguide tiên tiến, hệ thống hiển thị tích hợp và các nền tảng phần cứng có thể cấu hình, các giải pháp này hỗ trợ nhiều ứng dụng quốc phòng, bao gồm đào tạo, bảo trì, hậu cần và hỗ trợ nhiệm vụ.',
		'Unlike standalone display components, Vuzix technologies are developed as part of a unified OEM ecosystem that combines optics, engineering, and manufacturing support within a single development pathway.' => 'Khác với các linh kiện hiển thị độc lập, công nghệ của Vuzix được phát triển như một phần của hệ sinh thái OEM thống nhất, kết hợp quang học, kỹ thuật và hỗ trợ sản xuất trong một quy trình phát triển duy nhất.',
		'OEM CAPABILITIES' => 'NĂNG LỰC OEM',
		'Integration into defense &amp; first responder systems' => 'Tích hợp vào hệ thống quốc phòng &amp; ứng cứu khẩn cấp',
		'Vuzix technologies are designed to support defense OEMs, system integrators, and government-focused programs requiring lightweight headworn systems, advanced optics, and adaptable wearable platforms. Combining waveguide optics, display integration, hardware engineering, and manufacturing support, Vuzix provides a flexible technology foundation for mission-critical AR platform deployment.' => 'Công nghệ của Vuzix được thiết kế để hỗ trợ các OEM quốc phòng, đơn vị tích hợp hệ thống và các chương trình hướng đến chính phủ, đòi hỏi hệ thống đội đầu nhẹ, quang học tiên tiến và các nền tảng đeo được có khả năng thích ứng. Kết hợp quang học waveguide, tích hợp màn hình hiển thị, kỹ thuật phần cứng và hỗ trợ sản xuất, Vuzix cung cấp nền tảng công nghệ linh hoạt cho việc triển khai các nền tảng AR trọng yếu cho nhiệm vụ.',
		'Lightweight optics forheads-up operational systems' => 'Quang học siêu nhẹ cho các hệ thống tác nghiệp heads-up',
		'Vuzix waveguides are engineered for heads-up AR displays that support situational awareness, heads-up information delivery, and operational visibility in dynamic environments.' => 'Waveguide của Vuzix được thiết kế cho màn hình AR heads-up, hỗ trợ nâng cao nhận thức tình huống, truyền tải thông tin heads-up và khả năng quan sát khi tác nghiệp trong các môi trường biến động.',
		'Designed for compatibility across multiple display architectures, these optical systems support compact form factors while balancing brightness, image quality, and field of view.' => 'Được thiết kế để tương thích với nhiều kiến trúc hiển thị khác nhau, các hệ thống quang học này hỗ trợ kiểu dáng nhỏ gọn trong khi vẫn cân bằng giữa độ sáng, chất lượng hình ảnh và góc nhìn.',
		'Explore Waveguides →' => 'Khám phá Waveguide →',
		'Integrated engineering support for defense programs' => 'Hỗ trợ kỹ thuật tích hợp cho các chương trình quốc phòng',
		'Vuzix provides optical, mechanical, electrical, and system-level engineering support for defense-focused initiatives.' => 'Vuzix cung cấp hỗ trợ kỹ thuật về quang học, cơ khí, điện và cấp hệ thống cho các sáng kiến hướng đến quốc phòng.',
		'Development capabilities include display integration, prototype development, hardware optimization, and support for application-specific wearable configurations.' => 'Năng lực phát triển bao gồm tích hợp màn hình hiển thị, phát triển bản mẫu, tối ưu hóa phần cứng và hỗ trợ cấu hình thiết bị đeo được theo từng ứng dụng cụ thể.',
		'U.S. Manufacturing' => 'Sản xuất tại Hoa Kỳ',
		'Domestic waveguide production and scalable manufacturing support' => 'Sản xuất waveguide trong nước và hỗ trợ sản xuất có khả năng mở rộng',
		'Waveguide optics are designed and manufactured in New York State, supporting greater control over quality, production consistency, and long-term supply considerations.' => 'Quang học waveguide được thiết kế và sản xuất tại Tiểu bang New York, giúp kiểm soát tốt hơn về chất lượng, tính đồng nhất trong sản xuất và các yếu tố cung ứng dài hạn.',
		'Vuzix manufacturing capabilities support both early-stage program development and larger-scale deployment requirements.' => 'Năng lực sản xuất của Vuzix hỗ trợ cả giai đoạn phát triển ban đầu của chương trình và các yêu cầu triển khai quy mô lớn hơn.',
		'Explore US Manufacturing →' => 'Khám phá Sản xuất tại Hoa Kỳ →',
		'Mission-critical HUD systems' => 'Hệ thống HUD trọng yếu cho nhiệm vụ',
		'Engineered for the Extreme' => 'Được thiết kế cho những điều kiện khắc nghiệt nhất',
		'Head-worn display technology is redefining how military personnel, security professionals, and emergency responders operate in demanding environments.' => 'Công nghệ màn hình đội đầu đang định hình lại cách các quân nhân, chuyên gia an ninh và lực lượng ứng cứu khẩn cấp tác nghiệp trong những môi trường khắc nghiệt.',
		'Intelligent optics deployed across demanding environments' => 'Quang học thông minh được triển khai trong các môi trường khắc nghiệt',
		'Vuzix wearable technologies are used to support a range of defense and security workflows where headworn access to information improves awareness, coordination, and operational efficiency in the field. Built on a shared waveguide and OEM platform foundation, these applications reflect how integrated AR and AI systems are adapted for different operational roles and environments.' => 'Công nghệ đeo được của Vuzix được sử dụng để hỗ trợ nhiều quy trình quốc phòng và an ninh, trong đó khả năng truy cập thông tin qua thiết bị đội đầu giúp nâng cao nhận thức, khả năng phối hợp và hiệu quả tác nghiệp tại hiện trường. Được xây dựng trên nền tảng waveguide và OEM chung, các ứng dụng này phản ánh cách các hệ thống AR và AI tích hợp được điều chỉnh phù hợp với từng vai trò và môi trường tác nghiệp khác nhau.',
		'Real-Time Situational Awareness' => 'Nhận thức Tình huống Theo Thời gian Thực',
		'Situational awareness for disaster management' => 'Nhận thức tình huống cho công tác quản lý thảm họa',
		'Deliver mission-critical information within the user\'s field of view while maintaining full awareness of the surrounding environment.' => 'Truyền tải thông tin trọng yếu cho nhiệm vụ ngay trong tầm nhìn của người dùng, đồng thời vẫn duy trì đầy đủ nhận thức về môi trường xung quanh.',
		'Training &amp; Simulation' => 'Đào tạo &amp; Mô phỏng',
		'Accelerated mission readiness, on-demand' => 'Tăng tốc sẵn sàng nhiệm vụ, theo yêu cầu',
		'Provide compelling instruction, procedural guidance, and mission rehearsal across distributed teams.' => 'Cung cấp hướng dẫn hấp dẫn, chỉ dẫn quy trình và diễn tập nhiệm vụ cho các đội phân tán.',
		'Maintenance &amp; Logistics' => 'Bảo trì &amp; Hậu cần',
		'Hands-free support for field operations' => 'Hỗ trợ rảnh tay cho hoạt động tại hiện trường',
		'Support maintenance, inspections, and logistics with hands-free instructions and remote expertise.' => 'Hỗ trợ bảo trì, kiểm tra và hậu cần bằng hướng dẫn rảnh tay và chuyên môn từ xa.',
		'Human-Machine Teaming' => 'Phối hợp Người-Máy',
		'Interfaces for connected operational systems' => 'Giao diện cho các hệ thống tác nghiệp được kết nối',
		'Connect personnel with sensors, autonomous systems, and command networks for faster operational decisions.' => 'Kết nối nhân sự với cảm biến, hệ thống tự hành và mạng lưới chỉ huy để đưa ra quyết định tác nghiệp nhanh hơn.',
		'Navigation &amp; Field Operations' => 'Điều hướng &amp; Hoạt động Hiện trường',
		'Context-aware guidance in dynamic environments' => 'Chỉ dẫn theo ngữ cảnh trong các môi trường biến động',
		'Provide heads-up navigation and contextual guidance where traditional displays are impractical.' => 'Cung cấp điều hướng heads-up và chỉ dẫn theo ngữ cảnh ở những nơi màn hình truyền thống không khả thi.',
		'Medical &amp; Emergency Response' => 'Y tế &amp; Ứng cứu Khẩn cấp',
		'Information delivery for field response duty' => 'Truyền tải thông tin cho nhiệm vụ ứng cứu tại hiện trường',
		'Deliver triage information, treatment guidance, and remote medical support in time-sensitive environments.' => 'Truyền tải thông tin phân loại, hướng dẫn điều trị và hỗ trợ y tế từ xa trong các tình huống cần xử lý gấp về thời gian.',
		'Public Safety' => 'An toàn Công cộng',
		'Real-time intelligence for public safety operations' => 'Thông tin tình báo theo thời gian thực cho hoạt động an toàn công cộng',
		'Support law enforcement and security personnel with dispatch information, incident updates, and secure communications.' => 'Hỗ trợ lực lượng hành pháp và nhân viên an ninh bằng thông tin điều động, cập nhật sự cố và thông tin liên lạc bảo mật.',
		'Critical Infrastructure' => 'Hạ tầng Trọng yếu',
		'Hands-free support for critical infrastructure' => 'Hỗ trợ rảnh tay cho hạ tầng trọng yếu',
		'Improve inspections, facility security, and operations across utilities, transportation, energy, and government facilities.' => 'Cải thiện công tác kiểm tra, an ninh cơ sở vật chất và hoạt động vận hành tại các cơ sở tiện ích, giao thông, năng lượng và chính phủ.',
		'Disaster Response' => 'Ứng cứu Thảm họa',
		'Situational awareness for emergency management' => 'Nhận thức tình huống cho công tác quản lý khẩn cấp',
		'Coordinate emergency response teams with real-time information sharing, navigation, and remote collaboration.' => 'Điều phối các đội ứng cứu khẩn cấp bằng chia sẻ thông tin theo thời gian thực, điều hướng và phối hợp từ xa.',
		'Beyond helmet HUDs, what other defense applications use Vuzix technology?' => 'Ngoài HUD trên mũ bảo hộ, còn những ứng dụng quốc phòng nào khác sử dụng công nghệ Vuzix?',
		'Vuzix optical technology supports defense workflows across six categories: situational awareness with heads-up data delivery, training and simulation with procedural overlays, maintenance and logistics with step-by-step guidance, human-machine teaming with sensor and autonomous system interfaces, navigation and field operations with positional awareness cues, and support and medical operations with task-relevant information delivery. The same waveguide and OEM platform foundation adapts to each application.' => 'Công nghệ quang học của Vuzix hỗ trợ các quy trình quốc phòng thuộc sáu nhóm: nhận thức tình huống với khả năng truyền tải dữ liệu heads-up, đào tạo và mô phỏng với các lớp hướng dẫn quy trình, bảo trì và hậu cần với chỉ dẫn từng bước, phối hợp người-máy với giao diện cảm biến và hệ thống tự hành, điều hướng và hoạt động hiện trường với các gợi ý nhận thức vị trí, và hỗ trợ cùng hoạt động y tế với việc truyền tải thông tin liên quan đến nhiệm vụ. Cùng một nền tảng waveguide và OEM được điều chỉnh phù hợp cho từng ứng dụng.',
		'What waveguide and platform combinations work best for defense AR?' => 'Những sự kết hợp waveguide và nền tảng nào phù hợp nhất cho AR quốc phòng?',
		'The Vuzix CV-40 waveguide is specifically optimized for helmet-mounted and vertical integration in defense HUDs, with a 40° field of view and DLP light engine at 1280 × 720 resolution. Combined with Vuzix Incognito technology to reduce forward light leakage, the CV-40 supports low-signature operation. Custom waveguide geometries beyond the standard configurations are also developed for defense OEM programs with specific form factor or performance requirements.' => 'Waveguide Vuzix CV-40 được tối ưu hóa đặc biệt cho việc lắp trên mũ bảo hộ và tích hợp theo chiều thẳng đứng trong các HUD quốc phòng, với góc nhìn 40° và động cơ ánh sáng DLP ở độ phân giải 1280 × 720. Kết hợp với công nghệ Vuzix Incognito để giảm rò rỉ ánh sáng ra phía trước, CV-40 hỗ trợ khả năng tác nghiệp có độ nhận diện thấp (low-signature). Các hình dạng waveguide tùy chỉnh ngoài các cấu hình tiêu chuẩn cũng được phát triển cho các chương trình OEM quốc phòng có yêu cầu cụ thể về kiểu dáng hoặc hiệu suất.',
		'Accelerate your headworn display program' => 'Đẩy nhanh chương trình phát triển màn hình đội đầu của bạn',
		'Vuzix supports defense OEMs and integrators developing waveguide-based AR systems for operational environments.' => 'Vuzix hỗ trợ các OEM quốc phòng và đơn vị tích hợp phát triển hệ thống AR dựa trên waveguide cho môi trường tác nghiệp.',
		'Contact Our Defense Team →' => 'Liên hệ Đội ngũ Quốc phòng của chúng tôi →',
		'M300XL &amp; M300 Compliance' => 'Tuân thủ M300XL &amp; M300',
		'This guide lists product information for M300XL and M300 users:' => 'Hướng dẫn này cung cấp thông tin sản phẩm cho người dùng M300XL và M300:',
		'Electronic Regulatory Labels: Settings &amp;gt; About Smart Glasses &amp;gt; Regulatory information' => 'Nhãn quy định điện tử: Cài đặt &amp;gt; Về Kính thông minh &amp;gt; Thông tin quy định',
		'M300XL and M300 comply with Part 15 of the FCC Rules. Operation is subject to the following 2 conditions:' => 'M300XL và M300 tuân thủ Phần 15 của Quy định FCC. Việc vận hành phải tuân theo 2 điều kiện sau:',
		'4 Declaration of Conformity' => '4 Tuyên bố hợp chuẩn',
		'EU Declaration of Conformity (English):' => 'Tuyên bố hợp chuẩn EU (Tiếng Anh):',
		'5 Human exposure to radio frequency' => '5 Phơi nhiễm tần số vô tuyến đối với con người',
		'M300XL and M300 were tested and certified to not exceed limits in US, Canada, and EU.' => 'M300XL và M300 đã được kiểm tra và chứng nhận không vượt quá giới hạn tại Hoa Kỳ, Canada và EU.',
		'WLAN 2.4G Ear Pinnae:' => 'WLAN 2.4G ở vành tai:',
		'Limit 4.0W/g SAR' => 'Giới hạn 4.0W/g SAR',
		'End User Licensing Agreement' => 'Thỏa thuận Cấp phép Người dùng Cuối',
		'SOFTWARE LICENSE AGREEMENT' => 'THỎA THUẬN CẤP PHÉP PHẦN MỀM',
		'PLEASE READ THIS SOFTWARE LICENSE AGREEMENT (THIS "LICENSE" or “EULA”) CAREFULLY BEFORE USING THE VUZIX SOFTWARE. BY USING THE VUZIX SOFTWARE AND BY FIRST CLICKING "I ACCEPT" BEFORE FIRST USE, YOU ARE AGREEING TO BE BOUND BY THE TERMS OF THIS LICENSE, AS UPDATED OR MODIFIED AND PUBLISHED ON THE VUZIX WEBSITE FROM TIME-TO TIME. IF YOU DO NOT AGREE TO THE TERMS OF THIS LICENSE, DO NOT USE THE VUZIX SOFTWARE. IF YOU DO NOT AGREE TO THE TERMS OF THIS LICENSE WHILE YOU ARE UNDER COVERAGE OF THE MINIMUM WARRANTY PERIOD OF THIRTY (30) DAYS FROM THE DATE OF DELIVERY OR SUCH GREAT MINIMUM TIME PERIOD AS MAY BE REQUIRED BY YOUR JURISDICTION, YOU MAY RETURN THE VUZIX SOFTWARE, OR THE VUZIX HARDWARE PRODUCT WHICH INCLUDED THE VUZIX SOFTWARE (IF APPLICABLE), TO THE PLACE WHERE YOU OBTAINED IT FOR A REFUND IN ACCORDANCE WITH STANDARD RETURN POLICIES, PROCEDURES AND REQUIREMENTS.' => 'VUI LÒNG ĐỌC KỸ THỎA THUẬN CẤP PHÉP PHẦN MỀM NÀY (GỌI LÀ "GIẤY PHÉP" HOẶC "EULA") TRƯỚC KHI SỬ DỤNG PHẦN MỀM VUZIX. BẰNG VIỆC SỬ DỤNG PHẦN MỀM VUZIX VÀ NHẤN "TÔI ĐỒNG Ý" LẦN ĐẦU TRƯỚC KHI SỬ DỤNG, BẠN ĐỒNG Ý BỊ RÀNG BUỘC BỞI CÁC ĐIỀU KHOẢN CỦA GIẤY PHÉP NÀY, NHƯ ĐƯỢC CẬP NHẬT HOẶC SỬA ĐỔI VÀ CÔNG BỐ TRÊN TRANG WEB CỦA VUZIX THEO TỪNG THỜI ĐIỂM. NẾU BẠN KHÔNG ĐỒNG Ý VỚI CÁC ĐIỀU KHOẢN CỦA GIẤY PHÉP NÀY, VUI LÒNG KHÔNG SỬ DỤNG PHẦN MỀM VUZIX. NẾU BẠN KHÔNG ĐỒNG Ý VỚI CÁC ĐIỀU KHOẢN CỦA GIẤY PHÉP NÀY TRONG THỜI GIAN VẪN CÒN TRONG THỜI HẠN BẢO HÀNH TỐI THIỂU BA MƯƠI (30) NGÀY KỂ TỪ NGÀY GIAO HÀNG, HOẶC THỜI HẠN TỐI THIỂU DÀI HƠN THEO YÊU CẦU CỦA KHU VỰC PHÁP LÝ CỦA BẠN, BẠN CÓ THỂ TRẢ LẠI PHẦN MỀM VUZIX, HOẶC SẢN PHẨM PHẦN CỨNG VUZIX CÓ KÈM PHẦN MỀM VUZIX (NẾU CÓ), CHO NƠI BẠN ĐÃ MUA ĐỂ ĐƯỢC HOÀN TIỀN THEO CÁC CHÍNH SÁCH, QUY TRÌNH VÀ YÊU CẦU HOÀN TRẢ TIÊU CHUẨN.',
		'IMPORTANT NOTE: This software may be used to reproduce materials. It is licensed to you only for reproduction of non-copyrighted materials, materials in which you own the copyright, or materials you are authorized or legally permitted to reproduce. If you are uncertain about your right to copy any material, you should contact your legal advisor.' => 'LƯU Ý QUAN TRỌNG: Phần mềm này có thể được dùng để sao chép tài liệu. Phần mềm được cấp phép cho bạn chỉ để sao chép các tài liệu không có bản quyền, tài liệu mà bạn sở hữu bản quyền, hoặc tài liệu mà bạn được cho phép hoặc được pháp luật cho phép sao chép. Nếu bạn không chắc chắn về quyền sao chép bất kỳ tài liệu nào, bạn nên liên hệ với cố vấn pháp lý của mình.',
		'1. General. The software to which this License relates (including any Vuzix-provided content), together with its documentation and any fonts accompanying the software, whether on disk, in read-only memory, on any other media, downloaded, or in any other form (collectively, the "Vuzix Software") are licensed, not sold, to you by Vuzix Corporation ("Vuzix") for use only under the terms of this License, and Vuzix reserves all rights not expressly granted to you. The rights granted herein are limited to Vuzix\' and its third-party licensors\' (collectively, the “Vuzix Software”) intellectual property rights in the Vuzix Software and do not include any other patents or intellectual property rights. You may own the media on which the Vuzix Software is recorded, but Vuzix and/or Vuzix\' licensor(s) retain ownership of the Vuzix Software itself. The terms of this License will govern your use of any software upgrades provided by Vuzix that replace and/or supplement prior versions of the Vuzix Software, unless such upgrade is accompanied by a separate license in which case the terms of that license will govern. Title and intellectual property rights in and to any content displayed by or accessed through the Vuzix Software belongs to the respective content owner. Such content may be protected by copyright or other intellectual property laws and treaties, and may be subject to terms of use of the third party providing such content. This License does not grant you any rights to use such content.' => '1. Điều khoản chung. Phần mềm mà Giấy phép này áp dụng (bao gồm bất kỳ nội dung nào do Vuzix cung cấp), cùng với tài liệu hướng dẫn và bất kỳ phông chữ nào đi kèm phần mềm, cho dù trên đĩa, trong bộ nhớ chỉ đọc, trên bất kỳ phương tiện lưu trữ khác, được tải xuống, hoặc dưới bất kỳ hình thức nào khác (gọi chung là "Phần mềm Vuzix") được cấp phép, không phải được bán, cho bạn bởi Vuzix Corporation ("Vuzix") để sử dụng chỉ theo các điều khoản của Giấy phép này, và Vuzix bảo lưu mọi quyền không được cấp một cách rõ ràng cho bạn. Các quyền được cấp tại đây chỉ giới hạn ở quyền sở hữu trí tuệ của Vuzix và các bên cấp phép thứ ba của Vuzix (gọi chung là "Phần mềm Vuzix") đối với Phần mềm Vuzix, và không bao gồm bất kỳ bằng sáng chế hoặc quyền sở hữu trí tuệ khác nào. Bạn có thể sở hữu phương tiện lưu trữ mà Phần mềm Vuzix được ghi trên đó, nhưng Vuzix và/hoặc (các) bên cấp phép của Vuzix vẫn giữ quyền sở hữu đối với Phần mềm Vuzix. Các điều khoản của Giấy phép này sẽ điều chỉnh việc bạn sử dụng bất kỳ bản cập nhật phần mềm nào do Vuzix cung cấp để thay thế và/hoặc bổ sung cho các phiên bản trước của Phần mềm Vuzix, trừ khi bản cập nhật đó đi kèm với một giấy phép riêng, trong trường hợp đó các điều khoản của giấy phép đó sẽ được áp dụng. Quyền sở hữu và quyền sở hữu trí tuệ đối với bất kỳ nội dung nào được hiển thị hoặc truy cập thông qua Phần mềm Vuzix thuộc về chủ sở hữu nội dung tương ứng. Nội dung đó có thể được bảo vệ bởi luật bản quyền hoặc các luật và hiệp ước sở hữu trí tuệ khác, và có thể phải tuân theo các điều khoản sử dụng của bên thứ ba cung cấp nội dung đó. Giấy phép này không cấp cho bạn bất kỳ quyền nào để sử dụng nội dung đó.',
		'2. Permitted License Uses and Restrictions.' => '2. Các Sử dụng và Hạn chế Được Cấp phép.',
		'A. This License allows you to install and/or use one copy of the Vuzix Software on only one computer or hardware device.' => 'A. Giấy phép này cho phép bạn cài đặt và/hoặc sử dụng một bản sao Phần mềm Vuzix trên duy nhất một máy tính hoặc thiết bị phần cứng.',
		'B. This License does not allow the Vuzix Software to run on more than one computer or hardware device at a time, and you may not make the Vuzix Software available over a network where it could be used by multiple computers at the same time. You may make one copy of the Vuzix Software in machine-readable form for backup purposes only; provided that the backup copy must include all copyright or other proprietary notices contained on the original. No license is granted to you in the human readable code of the Vuzix Software (i.e., the source code).' => 'B. Giấy phép này không cho phép Phần mềm Vuzix chạy trên nhiều hơn một máy tính hoặc thiết bị phần cứng cùng một lúc, và bạn không được cung cấp Phần mềm Vuzix qua một mạng nơi nó có thể được nhiều máy tính sử dụng đồng thời. Bạn có thể tạo một bản sao Phần mềm Vuzix ở dạng máy có thể đọc được chỉ nhằm mục đích lưu trữ dự phòng; với điều kiện bản sao dự phòng đó phải bao gồm tất cả các thông báo về bản quyền hoặc quyền sở hữu khác có trong bản gốc. Không có giấy phép nào được cấp cho bạn đối với mã nguồn mà con người có thể đọc được của Phần mềm Vuzix (tức là mã nguồn - source code).',
		'C. Except as expressly set forth herein or as otherwise approved by Vuzix in writing, you may not copy, modify, decompile, translate, reverse engineer, disassemble or otherwise create derivative works of or attempt to derive the source code of the Vuzix Software or any part thereof.' => 'C. Ngoại trừ những trường hợp được quy định rõ ràng tại đây hoặc được Vuzix chấp thuận bằng văn bản, bạn không được sao chép, sửa đổi, dịch ngược mã (decompile), dịch, thực hiện kỹ thuật đảo ngược (reverse engineer), phân tách (disassemble) hoặc bằng cách khác tạo ra các tác phẩm phái sinh, hoặc cố gắng suy ra mã nguồn của Phần mềm Vuzix hoặc bất kỳ phần nào của nó.',
		'THE VUZIX SOFTWARE IS NOT INTENDED FOR USE, AND SHALL NOT IN ANY CASE BE USED, IN THE OPERATION OF NUCLEAR FACILITIES, AIRCRAFT NAVIGATION OR COMMUNICATION SYSTEMS, AIR TRAFFIC CONTROL SYSTEMS, LIFE SUPPORT MACHINES OR OTHER EQUIPMENT IN WHICH THE FAILURE OF THE VUZIX SOFTWARE COULD LEAD TO DEATH, PERSONAL INJURY, OR SEVERE PHYSICAL OR ENVIRONMENTAL DAMAGE.' => 'PHẦN MỀM VUZIX KHÔNG ĐƯỢC DÙNG CHO, VÀ TRONG MỌI TRƯỜNG HỢP KHÔNG ĐƯỢC SỬ DỤNG TRONG, VIỆC VẬN HÀNH CÁC CƠ SỞ HẠT NHÂN, HỆ THỐNG DẪN ĐƯỜNG HOẶC THÔNG TIN HÀNG KHÔNG, HỆ THỐNG KIỂM SOÁT KHÔNG LƯU, THIẾT BỊ DUY TRÌ SỰ SỐNG HOẶC CÁC THIẾT BỊ KHÁC MÀ SỰ CỐ CỦA PHẦN MỀM VUZIX CÓ THỂ DẪN ĐẾN TỬ VONG, THƯƠNG TÍCH CÁ NHÂN, HOẶC THIỆT HẠI NGHIÊM TRỌNG VỀ VẬT CHẤT HOẶC MÔI TRƯỜNG.',
		'D. You agree that you shall only use the Software in a manner that complies with all applicable laws in the jurisdictions in which you use the Software, including, but not limited to, applicable restrictions concerning copyright and other intellectual property rights.' => 'D. Bạn đồng ý rằng bạn sẽ chỉ sử dụng Phần mềm theo cách tuân thủ mọi luật áp dụng tại các khu vực pháp lý mà bạn sử dụng Phần mềm, bao gồm nhưng không giới hạn ở các hạn chế áp dụng liên quan đến bản quyền và các quyền sở hữu trí tuệ khác.',
		'E. You agree to hold harmless, indemnify and defend each of Vuzix, its licensors or suppliers, and their respective affiliates, officers, directors, and employees, from and against any losses, damages, fines and expenses (including attorneys\' fees and costs) arising out of or relating to any claims that you have violated any terms of this Agreement.' => 'E. Bạn đồng ý miễn trừ trách nhiệm, bồi thường và bảo vệ Vuzix, các bên cấp phép hoặc nhà cung cấp của Vuzix, cùng các công ty liên kết, cán bộ, giám đốc và nhân viên tương ứng của họ, khỏi và trước mọi tổn thất, thiệt hại, tiền phạt và chi phí (bao gồm phí và chi phí luật sư) phát sinh từ hoặc liên quan đến bất kỳ khiếu nại nào cho rằng bạn đã vi phạm các điều khoản của Thỏa thuận này.',
		'F. To the extent that the Vuzix Software is included in a Vuzix Solutions Kit and/or a kit that is advertised unambiguously as a Demo Product or Demo Kit, you agree to abide by the terms of this EULA as well as any restrictions or limitations advertised for those specific products, whether presented on an associated webpage, Purchase Order, and/or other documentation provided with the Demo Product, Demo Kit, or the Vuzix Solutions Kit.' => 'F. Trong trường hợp Phần mềm Vuzix được bao gồm trong một Vuzix Solutions Kit và/hoặc một bộ sản phẩm được quảng cáo rõ ràng là Sản phẩm Demo hoặc Bộ Demo, bạn đồng ý tuân thủ các điều khoản của EULA này cũng như bất kỳ hạn chế hoặc giới hạn nào được quảng cáo cho các sản phẩm cụ thể đó, cho dù được trình bày trên trang web liên quan, Đơn đặt hàng (Purchase Order), và/hoặc tài liệu khác được cung cấp cùng với Sản phẩm Demo, Bộ Demo, hoặc Vuzix Solutions Kit.',
		'3. Transfer. You may not rent, lease, lend or sublicense the Vuzix Software. You may, however, make a one-time permanent transfer of all of your license rights to the Vuzix Software to another party, provided that: (a) the transfer must include all of the Vuzix Software, including all its component parts, original media, printed materials and this License; (b) you do not retain any copies of the Vuzix Software, full or partial, including copies stored on a computer or other storage device; and (c) the party receiving the Vuzix Software reads and agrees to accept the terms and conditions of this License. All components of the Vuzix Software are provided as part of a bundle and may not be separated from the bundle and distributed as standalone applications.' => '3. Chuyển giao. Bạn không được cho thuê, cho mượn hoặc cấp phép lại (sublicense) Phần mềm Vuzix. Tuy nhiên, bạn có thể thực hiện việc chuyển giao vĩnh viễn, một lần, toàn bộ quyền cấp phép của bạn đối với Phần mềm Vuzix cho một bên khác, với điều kiện: (a) việc chuyển giao phải bao gồm toàn bộ Phần mềm Vuzix, kể cả tất cả các thành phần cấu thành, phương tiện lưu trữ gốc, tài liệu in và Giấy phép này; (b) bạn không giữ lại bất kỳ bản sao nào của Phần mềm Vuzix, toàn bộ hoặc một phần, kể cả các bản sao được lưu trên máy tính hoặc thiết bị lưu trữ khác; và (c) bên nhận Phần mềm Vuzix đọc và đồng ý chấp nhận các điều khoản và điều kiện của Giấy phép này. Tất cả các thành phần của Phần mềm Vuzix được cung cấp như một phần của một bộ trọn gói (bundle) và không được tách ra khỏi bộ đó để phân phối dưới dạng ứng dụng độc lập.',
		'UPDATES: If a Vuzix Software update substantially replaces (full install) a previously licensed version of the Vuzix Software, you may not use both versions of the Vuzix Software at the same time nor may you transfer them separately.' => 'CẬP NHẬT: Nếu một bản cập nhật Phần mềm Vuzix thay thế đáng kể (cài đặt đầy đủ) một phiên bản Phần mềm Vuzix đã được cấp phép trước đó, bạn không được sử dụng đồng thời cả hai phiên bản của Phần mềm Vuzix, và cũng không được chuyển giao chúng một cách riêng biệt.',
		'4. Consent to Use of Data. You agree that Vuzix and its subsidiaries may collect and use technical and related information, including but not limited to technical information about your computer or hardware device, system and application software and peripherals, use patterns and other data that is gathered periodically to facilitate the provision of software updates, product support and other services to you (if any related to the Vuzix Software, and to verify compliance with the terms of this License. Vuzix may also use this information, so long as it is in an anonymized and aggregated form or otherwise does not personally identify you, to develop, improve and enhancement products, services and other technologies.' => '4. Đồng ý về Việc Sử dụng Dữ liệu. Bạn đồng ý rằng Vuzix và các công ty con của Vuzix có thể thu thập và sử dụng thông tin kỹ thuật và các thông tin liên quan, bao gồm nhưng không giới hạn ở thông tin kỹ thuật về máy tính hoặc thiết bị phần cứng, hệ điều hành và phần mềm ứng dụng cùng các thiết bị ngoại vi của bạn, các mẫu hình sử dụng và các dữ liệu khác được thu thập định kỳ nhằm hỗ trợ việc cung cấp các bản cập nhật phần mềm, hỗ trợ sản phẩm và các dịch vụ khác cho bạn (nếu có liên quan đến Phần mềm Vuzix), và để xác minh việc tuân thủ các điều khoản của Giấy phép này. Vuzix cũng có thể sử dụng thông tin này, miễn là ở dạng ẩn danh và tổng hợp hoặc không nhận diện cá nhân bạn, để phát triển, cải tiến và nâng cao sản phẩm, dịch vụ và các công nghệ khác.',
		'5. Termination. This License is effective until terminated. Your rights under this License will terminate automatically without notice from Vuzix if you fail to comply with any term(s) of this License. Upon the termination of this License, you shall cease all use of the Vuzix Software and destroy or return to Vuzix all copies, full or partial, of the Vuzix Software.' => '5. Chấm dứt. Giấy phép này có hiệu lực cho đến khi bị chấm dứt. Các quyền của bạn theo Giấy phép này sẽ tự động chấm dứt mà không cần thông báo từ Vuzix nếu bạn không tuân thủ bất kỳ điều khoản nào của Giấy phép này. Khi Giấy phép này chấm dứt, bạn phải ngừng mọi việc sử dụng Phần mềm Vuzix và tiêu hủy hoặc trả lại cho Vuzix tất cả các bản sao, toàn bộ hoặc một phần, của Phần mềm Vuzix.',
		'6. Limited Warranty on Media (if applicable). Vuzix warrants the media on which the Vuzix Software is recorded and delivered by Vuzix to be free from defects in materials and workmanship under normal use for a period of ninety (90) days from the date of original retail purchase. Your exclusive remedy under this Section shall be, at Vuzix\' option, a refund of the purchase price of the product containing the Vuzix Software or replacement of the Vuzix Software which is returned to Vuzix or an authorized representative of Vuzix with a copy of the receipt.' => '6. Bảo hành Có Giới hạn đối với Phương tiện Lưu trữ (nếu có). Vuzix bảo đảm rằng phương tiện lưu trữ mà Phần mềm Vuzix được ghi và giao bởi Vuzix không có lỗi về vật liệu và tay nghề gia công trong điều kiện sử dụng thông thường, trong thời hạn chín mươi (90) ngày kể từ ngày mua lẻ ban đầu. Biện pháp khắc phục duy nhất của bạn theo Mục này sẽ là, theo lựa chọn của Vuzix, hoàn lại giá mua sản phẩm có chứa Phần mềm Vuzix hoặc thay thế Phần mềm Vuzix được trả lại cho Vuzix hoặc đại diện được ủy quyền của Vuzix cùng với một bản sao biên nhận.',
		'THIS LIMITED WARRANTY AND ANY IMPLIED WARRANTIES ON THE MEDIA INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY, OF SATISFACTORY QUALITY, AND OF FITNESS FOR A PARTICULAR PURPOSE, ARE LIMITED IN DURATION TO NINETY (90) DAYS FROM THE DATE OF ORIGINAL RETAIL PURCHASE. SOME JURISDICTIONS DO NOT ALLOW LIMITATIONS ON HOW LONG AN IMPLIED WARRANTY LASTS, SO THE ABOVE LIMITATION MAY NOT APPLY TO YOU.' => 'BẢO HÀNH CÓ GIỚI HẠN NÀY VÀ MỌI BẢO HÀNH NGỤ Ý ĐỐI VỚI PHƯƠNG TIỆN LƯU TRỮ, BAO GỒM NHƯNG KHÔNG GIỚI HẠN Ở CÁC BẢO HÀNH NGỤ Ý VỀ TÍNH THƯƠNG MẠI, VỀ CHẤT LƯỢNG THỎA ĐÁNG, VÀ VỀ SỰ PHÙ HỢP CHO MỘT MỤC ĐÍCH CỤ THỂ, ĐƯỢC GIỚI HẠN THỜI HẠN LÀ CHÍN MƯƠI (90) NGÀY KỂ TỪ NGÀY MUA LẺ BAN ĐẦU. MỘT SỐ KHU VỰC PHÁP LÝ KHÔNG CHO PHÉP GIỚI HẠN VỀ THỜI GIAN HIỆU LỰC CỦA BẢO HÀNH NGỤ Ý, VÌ VẬY GIỚI HẠN NÊU TRÊN CÓ THỂ KHÔNG ÁP DỤNG ĐỐI VỚI BẠN.',
		'THE LIMITED WARRANTY SET FORTH HEREIN IS THE ONLY WARRANTY MADE TO YOU WITH RESPECT TO THE SOFTWARE AND IS PROVIDED IN LIEU OF ANY OTHER WARRANTIES (IF ANY) CREATED BY ANY DOCUMENTATION OR PACKAGING. THIS LIMITED WARRANTY GIVES YOU SPECIFIC LEGAL RIGHTS, AND YOU MAY ALSO HAVE OTHER RIGHTS WHICH VARY BY JURISDICTION.' => 'BẢO HÀNH CÓ GIỚI HẠN ĐƯỢC NÊU TẠI ĐÂY LÀ BẢO HÀNH DUY NHẤT ĐƯỢC CUNG CẤP CHO BẠN ĐỐI VỚI PHẦN MỀM VÀ ĐƯỢC CUNG CẤP THAY CHO MỌI BẢO HÀNH KHÁC (NẾU CÓ) ĐƯỢC TẠO RA BỞI BẤT KỲ TÀI LIỆU HOẶC BAO BÌ NÀO. BẢO HÀNH CÓ GIỚI HẠN NÀY MANG LẠI CHO BẠN CÁC QUYỀN PHÁP LÝ CỤ THỂ, VÀ BẠN CŨNG CÓ THỂ CÓ CÁC QUYỀN KHÁC KHÁC NHAU THEO TỪNG KHU VỰC PHÁP LÝ.',
		'7. Disclaimer of Warranties. YOU EXPRESSLY ACKNOWLEDGE AND AGREE THAT USE OF THE VUZIX SOFTWARE IS AT YOUR SOLE RISK AND THAT THE ENTIRE RISK AS TO SATISFACTORY QUALITY, PERFORMANCE, ACCURACY AND EFFORT IS WITH YOU. EXCEPT FOR THE LIMITED WARRANTY ON MEDIA SET FORTH ABOVE AND TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, THE VUZIX SOFTWARE IS PROVIDED "AS IS", WITH ALL FAULTS AND WITHOUT WARRANTY OF ANY KIND, AND VUZIX AND VUZIX\' LICENSORS (COLLECTIVELY REFERRED TO AS "VUZIX" FOR THE PURPOSES OF SECTIONS 7 AND 8) HEREBY DISCLAIM ALL WARRANTIES AND CONDITIONS WITH RESPECT TO THE VUZIX SOFTWARE, EITHER EXPRESS, IMPLIED OR STATUTORY, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES AND/OR CONDITIONS OF MERCHANTABILITY, OF SATISFACTORY QUALITY, OF FITNESS FOR A PARTICULAR PURPOSE, OF ACCURACY, OF QUIET ENJOYMENT, AND NON-INFRINGEMENT OF THIRD PARTY RIGHTS. VUZIX DOES NOT WARRANT AGAINST INTERFERENCE WITH YOUR ENJOYMENT OF THE VUZIX SOFTWARE, THAT THE FUNCTIONS CONTAINED IN THE VUZIX SOFTWARE WILL MEET YOUR REQUIREMENTS, THAT THE OPERATION OF THE VUZIX SOFTWARE WILL BE UNINTERRUPTED OR ERROR-FREE, OR THAT DEFECTS IN THE VUZIX SOFTWARE WILL BE CORRECTED. NO ORAL OR WRITTEN INFORMATION OR ADVICE GIVEN BY VUZIX OR AN VUZIX AUTHORIZED REPRESENTATIVE SHALL CREATE A WARRANTY. SHOULD THE VUZIX SOFTWARE PROVE DEFECTIVE, YOU ASSUME THE ENTIRE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION. SOME JURISDICTIONS DO NOT ALLOW THE EXCLUSION OF IMPLIED WARRANTIES OR LIMITATIONS ON APPLICABLE STATUTORY RIGHTS OF A CONSUMER, SO THE ABOVE EXCLUSION AND LIMITATIONS MAY NOT APPLY TO YOU.' => '7. Từ chối Bảo đảm. BẠN THỪA NHẬN VÀ ĐỒNG Ý RÕ RÀNG RẰNG VIỆC SỬ DỤNG PHẦN MỀM VUZIX HOÀN TOÀN DO BẠN TỰ CHỊU RỦI RO VÀ TOÀN BỘ RỦI RO VỀ CHẤT LƯỢNG THỎA ĐÁNG, HIỆU SUẤT, ĐỘ CHÍNH XÁC VÀ CÔNG SỨC THUỘC VỀ BẠN. NGOẠI TRỪ BẢO HÀNH CÓ GIỚI HẠN ĐỐI VỚI PHƯƠNG TIỆN LƯU TRỮ ĐƯỢC NÊU TRÊN VÀ TRONG PHẠM VI TỐI ĐA ĐƯỢC PHÁP LUẬT HIỆN HÀNH CHO PHÉP, PHẦN MỀM VUZIX ĐƯỢC CUNG CẤP "NGUYÊN TRẠNG" ("AS IS"), VỚI TẤT CẢ CÁC LỖI VÀ KHÔNG CÓ BẢO ĐẢM DƯỚI BẤT KỲ HÌNH THỨC NÀO, VÀ VUZIX CÙNG CÁC BÊN CẤP PHÉP CỦA VUZIX (GỌI CHUNG LÀ "VUZIX" CHO MỤC ĐÍCH CỦA MỤC 7 VÀ 8) THEO ĐÂY TỪ CHỐI MỌI BẢO ĐẢM VÀ ĐIỀU KIỆN LIÊN QUAN ĐẾN PHẦN MỀM VUZIX, CHO DÙ LÀ RÕ RÀNG, NGỤ Ý HAY THEO QUY ĐỊNH PHÁP LUẬT, BAO GỒM NHƯNG KHÔNG GIỚI HẠN Ở CÁC BẢO ĐẢM VÀ/HOẶC ĐIỀU KIỆN NGỤ Ý VỀ TÍNH THƯƠNG MẠI, VỀ CHẤT LƯỢNG THỎA ĐÁNG, VỀ SỰ PHÙ HỢP CHO MỘT MỤC ĐÍCH CỤ THỂ, VỀ ĐỘ CHÍNH XÁC, VỀ QUYỀN SỬ DỤNG KHÔNG BỊ GIÁN ĐOẠN (QUIET ENJOYMENT), VÀ KHÔNG XÂM PHẠM QUYỀN CỦA BÊN THỨ BA. VUZIX KHÔNG BẢO ĐẢM CHỐNG LẠI SỰ CAN THIỆP VÀO VIỆC SỬ DỤNG PHẦN MỀM VUZIX CỦA BẠN, RẰNG CÁC CHỨC NĂNG CÓ TRONG PHẦN MỀM VUZIX SẼ ĐÁP ỨNG YÊU CẦU CỦA BẠN, RẰNG VIỆC VẬN HÀNH PHẦN MỀM VUZIX SẼ KHÔNG BỊ GIÁN ĐOẠN HOẶC KHÔNG CÓ LỖI, HOẶC RẰNG CÁC LỖI TRONG PHẦN MỀM VUZIX SẼ ĐƯỢC SỬA CHỮA. KHÔNG CÓ THÔNG TIN HOẶC LỜI KHUYÊN BẰNG LỜI NÓI HAY VĂN BẢN NÀO DO VUZIX HOẶC ĐẠI DIỆN ĐƯỢC VUZIX ỦY QUYỀN CUNG CẤP TẠO THÀNH MỘT BẢO ĐẢM. NẾU PHẦN MỀM VUZIX BỊ PHÁT HIỆN CÓ LỖI, BẠN CHỊU TOÀN BỘ CHI PHÍ CHO MỌI DỊCH VỤ, SỬA CHỮA HOẶC KHẮC PHỤC CẦN THIẾT. MỘT SỐ KHU VỰC PHÁP LÝ KHÔNG CHO PHÉP LOẠI TRỪ CÁC BẢO ĐẢM NGỤ Ý HOẶC HẠN CHẾ CÁC QUYỀN THEO QUY ĐỊNH PHÁP LUẬT HIỆN HÀNH CỦA NGƯỜI TIÊU DÙNG, VÌ VẬY CÁC LOẠI TRỪ VÀ HẠN CHẾ NÊU TRÊN CÓ THỂ KHÔNG ÁP DỤNG ĐỐI VỚI BẠN.',
		'8. Limitation of Liability. TO THE EXTENT NOT PROHIBITED BY LAW, IN NO EVENT SHALL VUZIX BE LIABLE FOR PERSONAL INJURY, OR ANY INCIDENTAL, SPECIAL, INDIRECT OR CONSEQUENTIAL DAMAGES WHATSOEVER, INCLUDING, WITHOUT LIMITATION, DAMAGES FOR LOSS OF PROFITS, LOSS OF DATA, BUSINESS INTERRUPTION OR ANY OTHER COMMERCIAL DAMAGES OR LOSSES, ARISING OUT OF OR RELATED TO YOUR USE OR INABILITY TO USE THE VUZIX SOFTWARE, HOWEVER CAUSED, REGARDLESS OF THE THEORY OF LIABILITY (CONTRACT, TORT OR OTHERWISE) AND EVEN IF VUZIX HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES. SOME JURISDICTIONS DO NOT ALLOW THE LIMITATION OF LIABILITY FOR PERSONAL INJURY, OR OF INCIDENTAL OR CONSEQUENTIAL DAMAGES, SO THIS LIMITATION MAY NOT APPLY TO YOU. In no event shall Vuzix\' total liability to you for all damages (other than as may be required by applicable law in cases involving personal injury) exceed the amount of fifty dollars ($50.00). The foregoing limitations will apply even if the above stated remedy fails of its essential purpose.' => '8. Giới hạn Trách nhiệm. TRONG PHẠM VI KHÔNG BỊ PHÁP LUẬT CẤM, VUZIX SẼ KHÔNG TRONG BẤT KỲ TRƯỜNG HỢP NÀO PHẢI CHỊU TRÁCH NHIỆM VỀ THƯƠNG TÍCH CÁ NHÂN, HOẶC BẤT KỲ THIỆT HẠI NGẪU NHIÊN, ĐẶC BIỆT, GIÁN TIẾP HAY HỆ QUẢ NÀO, BAO GỒM NHƯNG KHÔNG GIỚI HẠN Ở THIỆT HẠI DO MẤT LỢI NHUẬN, MẤT DỮ LIỆU, GIÁN ĐOẠN KINH DOANH HOẶC BẤT KỲ THIỆT HẠI HAY TỔN THẤT THƯƠNG MẠI KHÁC, PHÁT SINH TỪ HOẶC LIÊN QUAN ĐẾN VIỆC BẠN SỬ DỤNG HOẶC KHÔNG THỂ SỬ DỤNG PHẦN MỀM VUZIX, DÙ ĐƯỢC GÂY RA BỞI BẤT KỲ NGUYÊN NHÂN NÀO, BẤT KỂ THEO LÝ THUYẾT TRÁCH NHIỆM NÀO (HỢP ĐỒNG, TRÁCH NHIỆM NGOÀI HỢP ĐỒNG HAY KHÁC) VÀ NGAY CẢ KHI VUZIX ĐÃ ĐƯỢC THÔNG BÁO VỀ KHẢ NĂNG XẢY RA CÁC THIỆT HẠI ĐÓ. MỘT SỐ KHU VỰC PHÁP LÝ KHÔNG CHO PHÉP GIỚI HẠN TRÁCH NHIỆM ĐỐI VỚI THƯƠNG TÍCH CÁ NHÂN, HOẶC ĐỐI VỚI THIỆT HẠI NGẪU NHIÊN HAY HỆ QUẢ, VÌ VẬY GIỚI HẠN NÀY CÓ THỂ KHÔNG ÁP DỤNG ĐỐI VỚI BẠN. Trong mọi trường hợp, tổng trách nhiệm của Vuzix đối với bạn cho tất cả các thiệt hại (trừ trường hợp pháp luật hiện hành yêu cầu khác liên quan đến thương tích cá nhân) sẽ không vượt quá số tiền năm mươi đô la ($50.00). Các giới hạn nêu trên sẽ vẫn được áp dụng ngay cả khi biện pháp khắc phục nêu trên không đạt được mục đích cốt yếu của nó.',
		'9. Export Control. You may not use or otherwise export or re-export the Vuzix Software except as authorized by United States law and the laws of the jurisdiction in which the Vuzix Software was obtained. In particular, but without limitation, the Vuzix Software may not be exported or re-exported (a) into any U.S. embargoed countries or (b) to anyone on the U.S. Treasury Department\'s list of Specially Designated Nationals or the U.S. Department of Commerce Denied Person\'s List or Entity List. By using the Vuzix Software, you represent and warrant that you are not located in any such country or on any such list. You also agree that you will not use these products for any purposes prohibited by United States law, including, without limitation, the development, design, manufacture or production of nuclear, missiles, or chemical or biological weapons.' => '9. Kiểm soát Xuất khẩu. Bạn không được sử dụng hoặc xuất khẩu hoặc tái xuất khẩu Phần mềm Vuzix, trừ khi được pháp luật Hoa Kỳ và pháp luật của khu vực pháp lý nơi Phần mềm Vuzix được mua cho phép. Đặc biệt, nhưng không giới hạn, Phần mềm Vuzix không được xuất khẩu hoặc tái xuất khẩu (a) vào bất kỳ quốc gia nào bị Hoa Kỳ cấm vận hoặc (b) cho bất kỳ ai có tên trong danh sách Công dân Được Chỉ định Đặc biệt (Specially Designated Nationals) của Bộ Tài chính Hoa Kỳ hoặc Danh sách Người bị Từ chối (Denied Person\'s List) hoặc Danh sách Thực thể (Entity List) của Bộ Thương mại Hoa Kỳ. Bằng việc sử dụng Phần mềm Vuzix, bạn tuyên bố và bảo đảm rằng bạn không ở tại bất kỳ quốc gia nào như vậy hoặc không có tên trong bất kỳ danh sách nào như vậy. Bạn cũng đồng ý rằng bạn sẽ không sử dụng các sản phẩm này cho bất kỳ mục đích nào bị pháp luật Hoa Kỳ cấm, bao gồm nhưng không giới hạn ở việc phát triển, thiết kế, chế tạo hoặc sản xuất vũ khí hạt nhân, tên lửa, hoặc vũ khí hóa học hay sinh học.',
		'10. Government End Users. The Vuzix Software and related documentation are "Commercial Items", as that term is defined at 48 C.F.R. 2.101, consisting of "Commercial Computer Software" and "Commercial Computer Software Documentation", as such terms are used in 48 C.F.R. 12.212 or 48 C.F.R. 227.7202, as applicable. Consistent with 48 C.F.R. 12.212 or 48 C.F.R. 227.7202-1 through 227.7202-4, as applicable, the Commercial Computer Software and Commercial Computer Software Documentation are being licensed to U.S. Government end users (a) only as Commercial Items and (b) with only those rights as are granted to all other end users pursuant to the terms and conditions herein. Unpublished-rights reserved under the copyright laws of the United States.' => '10. Người dùng Cuối Thuộc Chính phủ. Phần mềm Vuzix và tài liệu liên quan là "Hàng hóa Thương mại" (Commercial Items), theo định nghĩa tại 48 C.F.R. 2.101, bao gồm "Phần mềm Máy tính Thương mại" (Commercial Computer Software) và "Tài liệu Phần mềm Máy tính Thương mại" (Commercial Computer Software Documentation), theo cách các thuật ngữ này được sử dụng tại 48 C.F.R. 12.212 hoặc 48 C.F.R. 227.7202, tùy trường hợp áp dụng. Phù hợp với 48 C.F.R. 12.212 hoặc 48 C.F.R. 227.7202-1 đến 227.7202-4, tùy trường hợp áp dụng, Phần mềm Máy tính Thương mại và Tài liệu Phần mềm Máy tính Thương mại được cấp phép cho người dùng cuối thuộc Chính phủ Hoa Kỳ (a) chỉ với tư cách là Hàng hóa Thương mại và (b) chỉ với các quyền được cấp cho tất cả các người dùng cuối khác theo các điều khoản và điều kiện tại đây. Các quyền chưa công bố (unpublished rights) được bảo lưu theo luật bản quyền của Hoa Kỳ.',
		'11. Controlling Law and Severability. This License will be governed by and construed in accordance with the laws of the State of New York, as applied to agreements entered into and to be performed entirely within New York between New York residents. This License shall not be governed by the United Nations Convention on Contracts for the International Sale of Goods, the application of which is expressly excluded. If for any reason a court of competent jurisdiction finds any provision, or portion thereof, to be unenforceable, the remainder of this License shall continue in full force and effect.' => '11. Luật Áp dụng và Tính Hiệu lực Từng phần. Giấy phép này sẽ được điều chỉnh và giải thích theo pháp luật của Tiểu bang New York, như được áp dụng đối với các thỏa thuận được ký kết và thực hiện hoàn toàn trong phạm vi New York giữa các cư dân New York. Giấy phép này sẽ không bị điều chỉnh bởi Công ước Liên Hợp Quốc về Hợp đồng Mua bán Hàng hóa Quốc tế, việc áp dụng công ước này được loại trừ một cách rõ ràng. Nếu vì bất kỳ lý do nào, một tòa án có thẩm quyền xác định rằng bất kỳ điều khoản nào, hoặc một phần của điều khoản đó, không thể thi hành được, phần còn lại của Giấy phép này sẽ tiếp tục có đầy đủ hiệu lực và giá trị.',
		'12. Complete Agreement; Governing Language. This License constitutes the entire agreement between the parties with respect to the use of the Vuzix Software licensed hereunder and supersedes all prior or contemporaneous understandings regarding such subject matter. Any amendment to or modification of this License, other than as published by Vuzix on its website from time-to-time or in associated documentation provided with any Vuzix Product, Software, or kit, will only be binding to the extent set forth in writing and signed by Vuzix. Any translation of this License is done for local requirements and in the event of a dispute between the English and any non-English versions, the English version of this License shall govern.' => '12. Thỏa thuận Toàn vẹn; Ngôn ngữ Áp dụng. Giấy phép này cấu thành toàn bộ thỏa thuận giữa các bên liên quan đến việc sử dụng Phần mềm Vuzix được cấp phép theo đây, và thay thế mọi hiểu biết trước đó hoặc đồng thời liên quan đến vấn đề này. Bất kỳ sửa đổi hoặc điều chỉnh nào đối với Giấy phép này, ngoại trừ những sửa đổi được Vuzix công bố trên trang web của mình theo từng thời điểm hoặc trong tài liệu liên quan được cung cấp cùng với bất kỳ Sản phẩm, Phần mềm hoặc bộ sản phẩm nào của Vuzix, sẽ chỉ có hiệu lực ràng buộc trong phạm vi được lập thành văn bản và có chữ ký của Vuzix. Bất kỳ bản dịch nào của Giấy phép này được thực hiện nhằm đáp ứng yêu cầu tại địa phương, và trong trường hợp có tranh chấp giữa phiên bản tiếng Anh và bất kỳ phiên bản không phải tiếng Anh nào, phiên bản tiếng Anh của Giấy phép này sẽ được áp dụng.',
		'13. Third Party Acknowledgements.' => '13. Xác nhận Bên Thứ Ba.',
		'Portions of the Vuzix Software utilize or include third party software, other copyrighted material and/or public domain licensed software (for more information on public domain licenses see: https://creativecommons.org/share-your-work/public-domain/). Acknowledgements, licensing terms and disclaimers for such material may be contained in the printed and/or electronic documentation for the Vuzix Software, and your use of such material is governed by their respective terms.' => 'Một số phần của Phần mềm Vuzix sử dụng hoặc bao gồm phần mềm của bên thứ ba, tài liệu có bản quyền khác và/hoặc phần mềm được cấp phép thuộc phạm vi công cộng (public domain) (để biết thêm thông tin về các giấy phép phạm vi công cộng, xem: https://creativecommons.org/share-your-work/public-domain/). Các xác nhận, điều khoản cấp phép và tuyên bố miễn trừ trách nhiệm đối với các tài liệu đó có thể được nêu trong tài liệu in và/hoặc tài liệu điện tử của Phần mềm Vuzix, và việc bạn sử dụng các tài liệu đó được điều chỉnh theo các điều khoản tương ứng của chúng.',
		'All contents of this document are subject to change without notice.' => 'Toàn bộ nội dung của tài liệu này có thể thay đổi mà không cần thông báo trước.',
		'For a copy of the latest Software License Agreement/EULA, go to the Legal Documentation page of the Vuzix website: http://www.vuzix.com/support/legal' => 'Để có bản sao mới nhất của Thỏa thuận Cấp phép Phần mềm/EULA, vui lòng truy cập trang Tài liệu Pháp lý trên trang web của Vuzix: http://www.vuzix.com/support/legal',
		'All Vuzix trademarks, whether registered or unregistered, are trademarks of Vuzix Corporation. For a list of potentially relevant patents, please visit http://www.vuzix.com/pat' => 'Tất cả các nhãn hiệu của Vuzix, cho dù đã đăng ký hay chưa đăng ký, đều là nhãn hiệu của Vuzix Corporation. Để xem danh sách các bằng sáng chế có thể liên quan, vui lòng truy cập http://www.vuzix.com/pat',
		'Copyright 2026' => 'Bản quyền 2026',
		'Designed by Vuzix in New York' => 'Được thiết kế bởi Vuzix tại New York',
		'Legal Documentation' => 'Tài liệu Pháp lý',
		'EULA (End User License Agreement)' => 'EULA (Thỏa thuận Cấp phép Người dùng Cuối)',
		'Terms and Conditions of Sale' => 'Điều khoản và Điều kiện Bán hàng',
		'Terms and Conditions of Purchase' => 'Điều khoản và Điều kiện Mua hàng',
		'Website Terms and Conditions of Use' => 'Điều khoản và Điều kiện Sử dụng Trang web',
		'Vuzix Open Source License' => 'Giấy phép Mã Nguồn Mở của Vuzix',
		'Website Privacy Policy' => 'Chính sách Bảo mật Trang web',
		'Vuzix SDK Kit License and Confidentiality Agreement' => 'Thỏa thuận Cấp phép và Bảo mật Bộ SDK của Vuzix',
		'Publisher Distribution Agreement' => 'Thỏa thuận Phân phối Nhà phát hành',
		'Safety &amp; Warranty' => 'An toàn &amp; Bảo hành',
		'Vuzix Smart Glasses and Near-Eye Displays' => 'Kính Thông minh Vuzix và Màn hình Gần Mắt',
		'LX1, Safety and Warranty Guide, English' => 'LX1, Hướng dẫn An toàn và Bảo hành, Tiếng Anh',
		'M4000 Compliance ' => 'Tuân thủ M4000',
		'Blade Declaration of Conformity' => 'Tuyên bố Hợp chuẩn Blade',
		'Shield Declaration of Conformity' => 'Tuyên bố Hợp chuẩn Shield',
		'Z100 Declaration of Conformity' => 'Tuyên bố Hợp chuẩn Z100',
		'LX1 Declaration of Conformity' => 'Tuyên bố Hợp chuẩn LX1',
		'M300XL &amp;amp; M300 Compliance' => 'Tuân thủ M300XL &amp;amp; M300',
		'Safety Certifications for Vuzix batteries' => 'Chứng nhận An toàn cho Pin Vuzix',
		'UK PSTI Statement of Compliance' => 'Tuyên bố Tuân thủ PSTI Vương quốc Anh',
		'Country availability for Vuzix devices' => 'Khả năng cung cấp theo quốc gia cho thiết bị Vuzix',
		'Federal &amp; NYS Labor Law Posters' => 'Áp phích Luật Lao động Liên bang &amp; Tiểu bang New York',
		'English' => 'Tiếng Anh',
		'Spanish' => 'Tiếng Tây Ban Nha',

		// Footer mobile accordion menu (missing / entity-encoded variants)
		'Documentation'   => 'Tài liệu',
		'Shipping Policy' => 'Chính sách vận chuyển',
		'Return Policy'   => 'Chính sách đổi trả',
		'Terms &amp; Conditions' => 'Điều khoản & Điều kiện',

		// Header menu (OEM Services / Smart Glasses submenu items still missing)
		'Defense'         => 'Quốc phòng',
		'Smart Eyewear'   => 'Kính mắt thông minh',
		'Vuzix LX1™'      => 'Vuzix LX1™',
		'MDM'             => 'MDM',
		'AI-Assist'       => 'Trợ lý AI',
		'Remote Support'  => 'Hỗ trợ từ xa',

		// Header chrome / cart drawer / accessibility strings shared across pages
		'Skip to content'   => 'Bỏ qua đến nội dung chính',
		'Partner Login'     => 'Đăng nhập Đối tác',
		'Log in'            => 'Đăng nhập',
		'Country/region'    => 'Quốc gia/Khu vực',
		'Item added to your cart' => 'Đã thêm sản phẩm vào giỏ hàng',
		'View cart'         => 'Xem giỏ hàng',
		'Check out'         => 'Thanh toán',
		'Continue shopping' => 'Tiếp tục mua sắm',
		'Choosing a selection results in a full page refresh.' => 'Việc chọn một lựa chọn sẽ làm tải lại toàn bộ trang.',
		'Opens in a new window.' => 'Mở trong cửa sổ mới.',
		'Invalid password'  => 'Mật khẩu không hợp lệ',
		'Enter'             => 'Nhập',

		// Homepage hero (vzx-hero__content / vzx-hero__cards)
		'The Optical Foundation for AI Smart Glasses' => 'Nền tảng Quang học cho Kính thông minh AI',
		'Waveguide optics and OEM solutions for the next generation of enterprise, defense, and consumer eyewear' => 'Quang học ống dẫn sóng và các giải pháp OEM cho thế hệ tiếp theo của kính mắt doanh nghiệp, quốc phòng và tiêu dùng',
		'Explore waveguides & OEM services →' => 'Khám phá ống dẫn sóng & dịch vụ OEM →',
		'Deploy with Vuzix' => 'Triển khai cùng Vuzix',
		'Explore enterprise smart glasses →' => 'Khám phá kính thông minh doanh nghiệp →',

		// Homepage: stats / news / product cards / footer CTA
		'~30 years' => '~30 năm',
		'Programs and partnerships across defense, enterprise, consumer, and medical markets built on Vuzix waveguide optics.' => 'Các chương trình và quan hệ đối tác trải rộng trên các thị trường quốc phòng, doanh nghiệp, tiêu dùng và y tế, được xây dựng trên nền quang học ống dẫn sóng của Vuzix.',
		'Tier-1 Waveguide Display Program' => 'Chương trình Màn hình Ống dẫn sóng Tier-1',
		'Vuzix receives customer-funded development order from leading Tier-1 defense supplier for next-generation waveguide display program.' => 'Vuzix nhận đơn đặt hàng phát triển do khách hàng tài trợ từ một nhà cung cấp quốc phòng Tier-1 hàng đầu cho chương trình màn hình ống dẫn sóng thế hệ tiếp theo.',
		'Waveguide-based Ultralite Pro OEM Deployment' => 'Triển khai OEM Ultralite Pro dựa trên Ống dẫn sóng',
		'Leading global online retailer expands Vuzix smart glasses deployment with Ultralite Pro OEM program orders to enable AI business cases.' => 'Nhà bán lẻ trực tuyến toàn cầu hàng đầu mở rộng triển khai kính thông minh Vuzix với các đơn hàng chương trình OEM Ultralite Pro nhằm hiện thực hóa các ứng dụng kinh doanh AI.',
		'Avegant and Vuzix Announce New Binocular Reference Design' => 'Avegant và Vuzix Công bố Thiết kế Tham chiếu Hai mắt Mới',
		"Design features Avegant's AG-30L3 light engine and Vuzix waveguide optics, to be co-designed and manufactured by Quanta Computer." => 'Thiết kế sử dụng bộ engine ánh sáng AG-30L3 của Avegant và quang học ống dẫn sóng của Vuzix, được đồng thiết kế và sản xuất bởi Quanta Computer.',
		'10-hour battery. Freezer-rated. Rugged workhorse.' => 'Pin 10 giờ. Chịu được nhiệt độ đông lạnh. Bền bỉ vượt trội.',
		'Industrial'       => 'Công nghiệp',
		'Hot-swappable battery. All-purpose device.' => 'Pin có thể thay nóng. Thiết bị đa năng.',
		'Inspections'      => 'Kiểm tra',
		'Real-time expert guidance with MS Teams & Zoom.' => 'Hướng dẫn chuyên gia theo thời gian thực với MS Teams & Zoom.',
		'Remote Expert'    => 'Chuyên gia từ xa',
		'Assisted evaluation for warehouse operations.' => 'Đánh giá có hỗ trợ cho hoạt động kho hàng.',
		'Warehouse'        => 'Kho hàng',
		'Logistics'        => 'Hậu cần',
		'Insights, events, & research' => 'Thông tin chuyên sâu, sự kiện & nghiên cứu',
		'Events & Live Experiences' => 'Sự kiện & Trải nghiệm trực tiếp',
		'JUNE 28, 2026' => '28 Tháng 6, 2026',
		'JUNE 09, 2026' => '09 Tháng 6, 2026',
		'JULY 14, 2026' => '14 Tháng 7, 2026',
		'MAY 01, 2026'  => '01 Tháng 5, 2026',
		'Vuzix Smart Glasses' => 'Kính thông minh Vuzix',
		"Whether you're developing smart glasses or deploying them at scale, your journey starts here." => 'Dù bạn đang phát triển kính thông minh hay triển khai chúng ở quy mô lớn, hành trình của bạn bắt đầu từ đây.',

		// Waveguide configurations page (literal & variant of an already-translated heading)
		'Vuzix Core™ Waveguides Configuration & Specifications' => 'Cấu hình & Thông số kỹ thuật Ống dẫn sóng Vuzix Core™',

		// Literal '&' variants of already-translated headings (source HTML mixes raw & and &amp;)
		'Explore Configurations & Specs →' => 'Khám phá Cấu hình &amp; Thông số kỹ thuật →',
		'Backed by one of the industry’s most extensive optics IP portfolios—with over 500 patents and patents pending—Vuzix has developed core innovations across waveguide geometry, display coupling, optical manufacturing, and system integration. Nearly 30 years of continuous R&D in wearable optics underpins every waveguide we design.' => 'Được hậu thuẫn bởi một trong những danh mục sở hữu trí tuệ (IP) quang học rộng lớn nhất trong ngành—với hơn 500 bằng sáng chế đã được cấp và đang chờ cấp—Vuzix đã phát triển các đổi mới cốt lõi trên nhiều lĩnh vực như hình học waveguide, ghép nối hiển thị, sản xuất quang học và tích hợp hệ thống. Gần 30 năm nghiên cứu và phát triển (R&amp;D) liên tục trong lĩnh vực quang học đeo được là nền tảng cho mọi waveguide mà chúng tôi thiết kế.',
		'Design & manufacturing' => 'Thiết kế &amp; sản xuất',
		'PICK & PACK' => 'LẤY HÀNG &amp; ĐÓNG GÓI',
		'Vuzix smart glasses support warehouse workflows including hands-free pick-pack-sort operations, inventory management, putaway and replenishment, cycle counting, and quality inspection. Workers see digital instructions, item details, bin locations, and quantity confirmations directly in their field of view while both hands remain free for product handling. This has been proven to improve picking accuracy, increase throughput compared to handheld scanner or paper-based workflows, and reduce training time for new hires. Vuzix offers a structured Pick & Pack Validation Program for organizations evaluating smart glasses for warehouse use.' => 'Kính thông minh Vuzix hỗ trợ các quy trình vận hành kho hàng bao gồm lấy hàng-đóng gói-phân loại rảnh tay, quản lý hàng tồn kho, xếp hàng và bổ sung hàng, kiểm kê theo chu kỳ, và kiểm tra chất lượng. Người lao động có thể xem hướng dẫn số, thông tin chi tiết sản phẩm, vị trí ngăn hàng, và xác nhận số lượng trực tiếp trong trường nhìn của họ, trong khi cả hai tay vẫn được tự do để xử lý sản phẩm. Điều này đã được chứng minh giúp cải thiện độ chính xác khi lấy hàng, tăng lưu lượng xử lý so với các quy trình dùng máy quét cầm tay hoặc dựa trên giấy tờ, và giảm thời gian đào tạo cho nhân viên mới. Vuzix cung cấp Chương trình Đánh giá Pick &amp; Pack (Pick &amp; Pack Validation Program) có cấu trúc cho các tổ chức đang đánh giá việc sử dụng kính thông minh trong kho vận.',
		'What is the Vuzix Pick & Pack Validation Program?' => 'Chương trình Đánh giá Pick &amp; Pack của Vuzix là gì?',
		'The Vuzix Pick & Pack Validation Program is a structured evaluation specifically designed for warehouse operations considering smart glasses for picking, packing, and sorting workflows. It provides guided device evaluation, configuration support, and workflow validation before broader deployment. This helps operations teams confirm that smart glasses will deliver expected accuracy and throughput improvements for their specific environment. The program launched at MODEX 2026 and is offered to qualified warehouse operations.' => 'Chương trình Đánh giá Pick &amp; Pack của Vuzix là một quy trình đánh giá có cấu trúc, được thiết kế riêng cho các hoạt động kho vận đang xem xét sử dụng kính thông minh cho các quy trình lấy hàng, đóng gói và phân loại. Chương trình cung cấp đánh giá thiết bị có hướng dẫn, hỗ trợ cấu hình, và xác thực quy trình vận hành trước khi triển khai rộng rãi. Điều này giúp các nhóm vận hành xác nhận rằng kính thông minh sẽ mang lại độ chính xác và cải thiện lưu lượng xử lý như mong đợi cho môi trường cụ thể của họ. Chương trình được ra mắt tại MODEX 2026 và được cung cấp cho các đơn vị kho vận đủ điều kiện.',
		'Manufacturing / Healthcare / Deaf & hearing impaired' => 'Sản xuất / Y tế / Người khiếm thính',
		'Vuzix Solutions Kits (Pick & Pack, Remote Assist)' => 'Bộ Giải pháp Vuzix (Pick &amp; Pack, Remote Assist)',
		'Defense & government' => 'Quốc phòng &amp; chính phủ',
		'Q&A: Vuzix CEO Paul Travers On Smart Glasses, Frontline Work And Enterprise Rollouts' => 'Hỏi &amp; Đáp: CEO Vuzix Paul Travers Nói về Kính Thông minh, Công việc Tuyến đầu và Triển khai Doanh nghiệp',
		'Warehouse & Logistics' => 'Kho vận &amp; Logistics',
		'Manufacturing & Assembly' => 'Sản xuất &amp; Lắp ráp',
		'Field Service & Maintenance' => 'Dịch vụ hiện trường &amp; Bảo trì',
		'Match the device to environment: the LX1 fits warehouse and industrial workflows with a 10-hour battery, freezer-rated design, and rugged construction; the M400 fits varied workflows (field service, manufacturing, inspections, healthcare) with hot-swappable batteries and all-purpose capabilities; the Remote Assist Kit best fits any workflow needing live remote expert guidance through Microsoft Teams or Zoom. The Pick & Pack Validation Program offers a structured evaluation specifically for warehouse pick-pack-sort operations before broader deployment.' => 'Hãy chọn thiết bị phù hợp với môi trường: LX1 phù hợp với quy trình kho vận và công nghiệp nhờ pin dùng được 10 giờ, thiết kế chịu được nhiệt độ đông lạnh và cấu tạo bền chắc; M400 phù hợp với nhiều quy trình khác nhau (dịch vụ hiện trường, sản xuất, kiểm tra, y tế) nhờ pin có thể thay nóng và khả năng đa dụng; Remote Assist Kit phù hợp nhất với bất kỳ quy trình nào cần hướng dẫn từ chuyên gia trực tiếp qua Microsoft Teams hoặc Zoom. Chương trình Đánh giá Pick &amp; Pack cung cấp một quy trình đánh giá có cấu trúc dành riêng cho hoạt động lấy-đóng gói-phân loại tại kho trước khi triển khai rộng hơn.',
		'Training & Readiness' => 'Đào tạo &amp; Sẵn sàng triển khai',
		'Explore Pick & Pack Kit →' => 'Khám phá Pick &amp; Pack Kit →',
		'Design & engineering' => 'Thiết kế &amp; kỹ thuật',
		'Rapid prototyping & validation' => 'Tạo mẫu nhanh &amp; xác nhận',
		'Production & lifecycle support' => 'Sản xuất &amp; hỗ trợ vòng đời sản phẩm',
		'Navigation & Contextual Awareness' => 'Điều hướng &amp; Nhận biết ngữ cảnh',
		'Media & Content Interaction' => 'Tương tác Media &amp; Nội dung',
		'Communication & Notifications' => 'Giao tiếp &amp; Thông báo',
		'FORM FACTOR & DESIGN CONSIDERATIONS' => 'KIỂU DÁNG &amp; CÁC YẾU TỐ THIẾT KẾ',
		'Electronic Regulatory Labels: Settings &gt; System &gt; About Glasses &gt; Regulatory labels' => 'Nhãn quy định điện tử: Settings &gt; System &gt; About Glasses &gt; Regulatory labels',
		'Each kit comes pre-configured to work with Zoom & Microsoft Teams' => 'Mỗi bộ kit đều được cấu hình sẵn để hoạt động với Zoom &amp; Microsoft Teams',
		'Receive a pre-configured Pick & Pack Starter Kit loaded with five warehouse workflows.' => 'Nhận bộ kit khởi đầu Pick &amp; Pack đã được cấu hình sẵn với năm quy trình làm việc trong kho hàng.',
		'4. Review & Next Steps' => '4. Đánh giá &amp; Các bước tiếp theo',
		'The Pick & Pack Validation Program is available through consultation with our logistics team.' => 'Chương trình Thử nghiệm Pick &amp; Pack được cung cấp thông qua tư vấn với đội ngũ logistics của chúng tôi.',
		'Detect & Resolve Issues in Real Time' => 'Phát hiện &amp; Giải quyết sự cố theo thời gian thực',
		'With our Pick & Pack Validation program, see how hands-free smart glasses can improve picking accuracy, packing speed, receiving efficiency, and inventory confidence, before you commit to deployment.' => 'Với chương trình Thử nghiệm Pick &amp; Pack của chúng tôi, hãy xem cách kính thông minh rảnh tay có thể cải thiện độ chính xác khi lấy hàng, tốc độ đóng gói, hiệu quả nhận hàng và độ tin cậy trong kiểm kê, trước khi bạn quyết định triển khai.',
		'Defense, Security, & First Responders' => 'Quốc phòng, An ninh &amp; Lực lượng Ứng cứu Đầu tiên',
		'Integration into defense & first responder systems' => 'Tích hợp vào hệ thống quốc phòng &amp; ứng cứu khẩn cấp',
		'Training & Simulation' => 'Đào tạo &amp; Mô phỏng',
		'Maintenance & Logistics' => 'Bảo trì &amp; Hậu cần',
		'Navigation & Field Operations' => 'Điều hướng &amp; Hoạt động Hiện trường',
		'Medical & Emergency Response' => 'Y tế &amp; Ứng cứu Khẩn cấp',
		'M300XL & M300 Compliance' => 'Tuân thủ M300XL &amp; M300',
		'Electronic Regulatory Labels: Settings &gt; About Smart Glasses &gt; Regulatory information' => 'Nhãn quy định điện tử: Settings &gt; About Smart Glasses &gt; Regulatory information',

		// Product detail page hero eyebrow (shared across all original-products/*.html)
		'PRODUCT' => 'SẢN PHẨM',

		// Smart glasses page
		'Security & device management' => 'Bảo mật & quản lý thiết bị',
		'• Rugged IP 4 Certification & Freezer rated operating temperatures' => '• Chứng nhận chống chịu IP 4 & nhiệt độ hoạt động chịu được đông lạnh',
		'Match the device to the operating environment. The Vuzix LX1 is purpose-built for warehouse and industrial environments — 10-hour battery, freezer-rated, ruggedized for cold-storage and high-impact use. The Vuzix M400 is an all-purpose device with hot-swappable batteries, suited to varied workflows including field service, manufacturing, inspections, and healthcare. The Remote Assist Kit pairs Vuzix smart glasses with Microsoft Teams and Zoom integration for live remote expert workflows. For warehouse pick-pack-sort specifically, the Pick & Pack Validation Program provides a structured evaluation before broader deployment.' => 'Lựa chọn thiết bị phù hợp với môi trường vận hành. Vuzix LX1 được thiết kế riêng cho môi trường kho hàng và công nghiệp — pin 10 giờ, chịu được nhiệt độ đông lạnh, cấu tạo chắc chắn cho kho lạnh và va đập mạnh. Vuzix M400 là thiết bị đa năng với pin có thể thay nóng, phù hợp với nhiều quy trình làm việc như dịch vụ hiện trường, sản xuất, kiểm tra và y tế. Remote Assist Kit kết hợp kính thông minh Vuzix với Microsoft Teams và Zoom để hỗ trợ chuyên gia từ xa theo thời gian thực. Riêng với quy trình lấy-đóng gói-phân loại trong kho hàng, Pick & Pack Validation Program cung cấp một đánh giá có cấu trúc trước khi triển khai rộng rãi.',
	);

	foreach ( $labels as $english => $vietnamese ) {
		// Source HTML often hard-wraps long text nodes with raw newlines mid-sentence,
		// so internal whitespace must match loosely (any run of whitespace), not just
		// the leading/trailing whitespace around the text node.
		$words        = preg_split( '/\s+/', trim( $english ) );
		$words        = array_map(
			static function ( $word ) {
				return preg_quote( $word, '#' );
			},
			$words
		);
		$english_body = implode( '\s+', $words );

		$pattern = '#>(\s*)' . $english_body . '(\s*)<#';
		$html    = preg_replace_callback(
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
 * Cache kết quả của các bước xử lý tốn kém (link/asset rewrite + dịch qua hàng
 * nghìn mục trong $labels) cho một vùng HTML tĩnh (header/footer/main content
 * của 1 trang). Nếu không cache, pipeline này chạy lại từ đầu trên MỌI lượt
 * truy cập, kể cả với khách không đăng nhập — với số lượng $labels hiện tại,
 * đây là nguyên nhân chính khiến Performance score rất thấp.
 *
 * Cache key gắn với thời gian sửa đổi (mtime) của các file nguồn liên quan,
 * nên tự động làm mới khi original-*.html hoặc functions.php (nơi khai báo
 * $labels) thay đổi — không cần xóa cache thủ công.
 */
function vuzix_cached_html_pipeline( $cache_key, $source_paths, $callback ) {
	$version = '';
	foreach ( $source_paths as $path ) {
		$version .= '|' . ( file_exists( $path ) ? filemtime( $path ) : '0' );
	}

	$transient_key = 'vuzix_html_' . md5( $cache_key . $version );
	$cached        = get_transient( $transient_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$output = call_user_func( $callback );
	set_transient( $transient_key, $output, WEEK_IN_SECONDS );

	return $output;
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

	return vuzix_cached_html_pipeline(
		'main:' . $original_filename,
		array( $index_path, get_theme_file_path( 'functions.php' ) ),
		function () use ( $index_path, $theme_uri ) {
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
	);
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
		Mua ngay
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
		'billing_phone'      => array(
			'label'       => __( 'Số điện thoại', 'vuzix-practice' ),
			'required'    => true,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 10,
			'type'        => 'tel',
			'autocomplete'=> 'tel',
		),
		'billing_email'      => array(
			'label'       => __( 'Email', 'vuzix-practice' ),
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 20,
			'type'        => 'email',
			'autocomplete'=> 'email',
		),
		'billing_first_name' => array(
			'label'       => __( 'Họ', 'vuzix-practice' ),
			'required'    => false,
			'class'       => array( 'form-row-first' ),
			'priority'    => 30,
			'autocomplete'=> 'given-name',
		),
		'billing_last_name'  => array(
			'label'       => __( 'Tên', 'vuzix-practice' ),
			'required'    => false,
			'class'       => array( 'form-row-last' ),
			'priority'    => 40,
			'autocomplete'=> 'family-name',
		),
		'billing_address_1'  => array(
			'label'       => __( 'Địa chỉ', 'vuzix-practice' ),
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 50,
			'autocomplete'=> 'street-address',
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
 * By default WooCommerce sends the "New order" and "On-hold order" emails
 * synchronously, inline, while the checkout AJAX request is still running —
 * the browser only shows the "Order received" page after the SMTP round-trip
 * finishes, which is why checkout felt slow. WooCommerce already ships a
 * built-in deferred-email queue (via Action Scheduler); this just turns it on
 * so emails are dispatched right after the response is sent instead of
 * blocking it.
 */
add_filter( 'woocommerce_defer_transactional_emails', '__return_true' );

/**
 * Tùy biến nội dung email của WooCommerce cho luồng đặt hàng nội bộ (on-hold):
 * - "customer_on_hold_order": mail cảm ơn gửi cho khách hàng. WooCommerce chỉ gửi
 *   mail này nếu đơn hàng có billing_email hợp lệ, nên tự động đáp ứng yêu cầu
 *   "chỉ gửi khi khách có nhập email".
 * - "new_order": mail thông báo đơn hàng mới, gửi tới email đã cấu hình trong
 *   WooCommerce > Cài đặt > Email > Đơn hàng mới (Recipient(s)) — tận dụng đúng
 *   phần cấu hình SMTP/email cho sales đã được thiết lập sẵn.
 */
function vuzix_email_subject_customer_on_hold_order( $subject, $order ) {
	return sprintf(
		__( 'Vuzix đã nhận đơn hàng #%s của bạn', 'vuzix-practice' ),
		$order ? $order->get_order_number() : ''
	);
}
add_filter( 'woocommerce_email_subject_customer_on_hold_order', 'vuzix_email_subject_customer_on_hold_order', 10, 2 );

function vuzix_email_heading_customer_on_hold_order( $heading, $order ) {
	return __( 'Cảm ơn bạn đã đặt hàng!', 'vuzix-practice' );
}
add_filter( 'woocommerce_email_heading_customer_on_hold_order', 'vuzix_email_heading_customer_on_hold_order', 10, 2 );

function vuzix_email_subject_new_order( $subject, $order ) {
	$name = $order ? trim( $order->get_formatted_billing_full_name() ) : '';
	return sprintf(
		__( '[Đơn hàng mới] Khách hàng %s vừa chốt đơn #%s', 'vuzix-practice' ),
		$name !== '' ? $name : __( '(chưa rõ tên)', 'vuzix-practice' ),
		$order ? $order->get_order_number() : ''
	);
}
add_filter( 'woocommerce_email_subject_new_order', 'vuzix_email_subject_new_order', 10, 2 );

function vuzix_email_heading_new_order( $heading, $order ) {
	return __( 'Khách hàng vừa chốt đơn hàng mới', 'vuzix-practice' );
}
add_filter( 'woocommerce_email_heading_new_order', 'vuzix_email_heading_new_order', 10, 2 );

/**
 * Định dạng ngày kiểu Việt Nam ("12 Tháng 8, 2026") cho các mẫu email đặt hàng.
 * Không dùng date()/date_i18n() với chuỗi định dạng chứa "Tháng" vì các ký tự
 * T/h/n/g trùng với ký tự định dạng ngày-giờ của PHP, dễ gây lỗi escape.
 */
function vuzix_format_order_date( $wc_datetime ) {
	if ( ! $wc_datetime ) {
		return '';
	}
	return sprintf(
		'%d Tháng %d, %s',
		(int) $wc_datetime->date( 'j' ),
		(int) $wc_datetime->date( 'n' ),
		$wc_datetime->date( 'Y' )
	);
}

/**
 * Dựng các hàng <tr> của bảng sản phẩm dùng chung cho cả 2 mẫu email đặt hàng
 * (khách hàng và sales), tránh lặp lại logic ở 2 file template.
 */
function vuzix_email_order_item_rows( $order ) {
	$rows = '';
	foreach ( $order->get_items() as $item ) {
		$rows .= '<tr>';
		$rows .= '<td><strong>' . esc_html( $item->get_name() ) . '</strong></td>';
		$rows .= '<td style="text-align: center;">' . esc_html( $item->get_quantity() ) . '</td>';
		$rows .= '<td class="price">' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</td>';
		$rows .= '</tr>';
	}
	return $rows;
}

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
