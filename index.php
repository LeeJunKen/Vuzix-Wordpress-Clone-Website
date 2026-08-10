<?php
/**
 * Mẫu trang dự phòng fallback bắt buộc.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>
<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
	<div style="max-width: 800px; margin: 60px auto; padding: 0 20px;">
		<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; else : ?>
			<p><?php esc_html_e( 'Không tìm thấy nội dung.', 'vuzix-practice' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
