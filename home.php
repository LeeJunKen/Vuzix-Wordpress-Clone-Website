<?php
defined( 'ABSPATH' ) || exit;
get_header();
$vuzix_blog_query = $wp_query;
$vuzix_blog_title = __( 'Blog Vuzix', 'vuzix-practice' );
require get_theme_file_path( 'template-parts/blog-archive-content.php' );
get_footer();
