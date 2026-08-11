<?php
defined( 'ABSPATH' ) || exit;
get_header();

$paged = max( 1, absint( get_query_var( 'paged' ) ) );
$args  = array( 'post_type' => 'post', 'post_status' => 'publish', 'paged' => $paged, 'posts_per_page' => 18 );
if ( ! empty( $_GET['blog_category'] ) ) {
	$args['category_name'] = sanitize_title( wp_unslash( $_GET['blog_category'] ) );
}
$vuzix_blog_query = new WP_Query( $args );
$vuzix_blog_title = __( 'Vuzix Blog', 'vuzix-practice' );
require get_theme_file_path( 'template-parts/blog-archive-content.php' );
get_footer();
