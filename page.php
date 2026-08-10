<?php
/**
 * Template hiển thị các trang con (Pages)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

// Lấy slug của trang hiện tại
$slug = get_post_field( 'post_name', get_post() );

// Danh sách các thư mục chứa file HTML mẫu để tìm kiếm
$search_dirs = array(
	'original-pages/',
	'original-products/',
	'original-policies/',
	'original-blogs/',
	'original-blogs/release-notes/',
	'original-blogs/vuzix-white-papers-and-case-studies-across-industries/',
	'original-blogs/white-papers/',
	'original-tools/',
	'original-tools/perfect-product-finder/'
);

$original_file = '';
foreach ( $search_dirs as $dir ) {
	$temp_file = $dir . $slug . '.html';
	if ( file_exists( get_theme_file_path( $temp_file ) ) ) {
		$original_file = $temp_file;
		break;
	}
}

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
