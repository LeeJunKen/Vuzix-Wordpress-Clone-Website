<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
	<?php while ( have_posts() ) : the_post();
		$categories   = get_the_category();
		$category     = $categories ? $categories[0] : null;
		$archive_link = home_url( '/vuzix-blog/' );
		vuzix_render_blog_hero( get_the_title(), __( 'Tài nguyên', 'vuzix-practice' ), 320 );
		vuzix_render_blog_styles();
		?>
		<article <?php post_class( 'article-custom' ); ?>>
			<header class="vuzix-article-header">
				<h1 class="vuzix-article-title"><?php the_title(); ?></h1>
				<div class="vuzix-article-meta">
					<?php if ( $category ) : ?><a class="vuzix-article-tag" href="<?php echo esc_url( get_category_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php endif; ?>
					<time class="vuzix-article-date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				</div>
			</header>
			<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'full', array( 'class' => 'vuzix-article-featured' ) ); endif; ?>
			<div class="vuzix-article-content rte"><?php the_content(); ?></div>
			<a class="vuzix-blog-back" href="<?php echo esc_url( $archive_link ); ?>"><?php esc_html_e( 'Quay lại Blog Vuzix', 'vuzix-practice' ); ?></a>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer();
