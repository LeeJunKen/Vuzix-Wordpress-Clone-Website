<?php
defined( 'ABSPATH' ) || exit;

$blog_query = isset( $vuzix_blog_query ) && $vuzix_blog_query instanceof WP_Query ? $vuzix_blog_query : $wp_query;
$categories = get_categories( array( 'hide_empty' => true ) );
$selected   = isset( $_GET['blog_category'] ) ? sanitize_title( wp_unslash( $_GET['blog_category'] ) ) : '';
?>
<main id="MainContent" class="content-for-layout focus-none" role="main" tabindex="-1">
	<?php vuzix_render_blog_hero( isset( $vuzix_blog_title ) ? $vuzix_blog_title : __( 'Vuzix Blog', 'vuzix-practice' ) ); ?>
	<?php vuzix_render_blog_styles(); ?>
	<div class="vuzix-blog-main">
		<?php if ( $categories ) : ?>
			<form class="vuzix-blog-toolbar" method="get">
				<label for="BlogCategoryFilter"><?php esc_html_e( 'Filter:', 'vuzix-practice' ); ?></label>
				<select id="BlogCategoryFilter" name="blog_category" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All', 'vuzix-practice' ); ?></option>
					<?php foreach ( $categories as $category ) : ?>
						<option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $selected, $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</form>
		<?php endif; ?>

		<?php if ( $blog_query->have_posts() ) : ?>
			<div class="vuzix-blog-grid">
				<?php while ( $blog_query->have_posts() ) : $blog_query->the_post();
					$category_list = get_the_category();
					$category_name = $category_list ? $category_list[0]->name : '';
					?>
					<article <?php post_class( 'vuzix-blog-card' ); ?>>
						<a class="vuzix-blog-card__link" href="<?php the_permalink(); ?>">
							<div class="vuzix-blog-card__image-wrap">
								<?php if ( $category_name ) : ?><span class="vuzix-blog-card__tag"><?php echo esc_html( $category_name ); ?></span><?php endif; ?>
								<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'large', array( 'class' => 'vuzix-blog-card__image', 'loading' => 'lazy' ) ); else : ?><span class="vuzix-blog-card__placeholder"><?php esc_html_e( 'Vuzix Blog', 'vuzix-practice' ); ?></span><?php endif; ?>
							</div>
							<div class="vuzix-blog-card__content">
								<h2 class="vuzix-blog-card__title"><?php the_title(); ?></h2>
								<div class="vuzix-blog-card__date"><?php echo esc_html( get_the_date() ); ?></div>
							</div>
						</a>
					</article>
				<?php endwhile; ?>
			</div>
			<div class="vuzix-blog-pagination">
				<?php echo wp_kses_post( paginate_links( array( 'total' => $blog_query->max_num_pages, 'current' => max( 1, get_query_var( 'paged' ) ) ) ) ); ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Chưa có bài viết.', 'vuzix-practice' ); ?></p>
		<?php endif; wp_reset_postdata(); ?>
	</div>
</main>
