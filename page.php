<?php
/**
 * Template hiển thị các trang con (Pages)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

// Lấy slug của trang hiện tại
$slug = get_post_field( 'post_name', get_post() );

// Tìm file HTML mẫu khớp slug (dùng chung logic với functions.php)
$original_file = vuzix_find_original_file( $slug );

// In thông tin Debug dưới dạng comment HTML để kiểm tra trong Source Code của trình duyệt
echo "\n<!-- === VUZIX THEME DEBUG === -->\n";
echo "<!-- Current Page Slug: " . esc_html( $slug ) . " -->\n";
echo "<!-- Matched Original File: " . esc_html( $original_file ) . " -->\n";
echo "<!-- Checked Paths: -->\n";
foreach ( vuzix_get_search_dirs() as $dir ) {
	$temp_file = $dir . $slug . '.html';
	$exists = file_exists( get_theme_file_path( $temp_file ) ) ? 'EXISTS' : 'NOT FOUND';
	echo "<!-- - " . esc_html( $temp_file ) . " : " . $exists . " (Path: " . esc_html( get_theme_file_path( $temp_file ) ) . ") -->\n";
}
echo "<!-- ========================= -->\n\n";

// Nếu tìm thấy file mẫu tĩnh, hiển thị nội dung của nó
if ( ! empty( $original_file ) ) {
	echo vuzix_get_main_content( $original_file );
} else {
	// Fallback hiển thị nội dung nhập từ WordPress Editor
	?>
	<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
		<div style="max-width: 1200px; margin: 60px auto; padding: 0 20px;">
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<article <?php post_class(); ?>>
					<h1><?php the_title(); ?></h1>
					<div class="entry-content">
						<?php the_content(); ?>
					</div>
				</article>
			<?php endwhile; endif; ?>
		</div>
	</main>
	<?php
}

get_footer();
