<?php
defined( 'ABSPATH' ) || exit;
get_header();
$vuzix_blog_query = $wp_query;
$vuzix_blog_title = get_the_archive_title();
require get_theme_file_path( 'template-parts/blog-archive-content.php' );
get_footer();
