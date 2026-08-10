<?php
/**
 * Trang chủ động gọi nội dung từ original-index.html.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

echo vuzix_get_main_content( 'original-index.html' );

get_footer();
