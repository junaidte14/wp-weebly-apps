<?php
/**
 * The template for displaying all single products.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">

    <?php while ( have_posts() ) : the_post(); ?>
        <?php $quiz_type = get_post_meta( get_the_ID(), 'quiz_type', true );?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <div class="course-thumbnail">
                    <?php the_post_thumbnail(); ?>
                </div>
                <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				<h3><strong><?php _e('Price:', 'wpwa');?> </strong><?php echo '$'.floatval(get_post_meta( get_the_ID(), 'wpwa_product_price', true )); ?></h3>
				<h3><a href="<?php echo esc_url(get_post_meta( get_the_ID(), "wpwa_product_center_url", true ));?>" target="_blank"><?php _e('View/Buy in Weebly App Center', 'wpwa');?></a></h3>
                
            </header><!-- .entry-header -->

            <div class="entry-content-wrapper">
                <div class="entry-content">
                   
					<?php 
					the_content();

					?>
                                        
                </div><!-- .entry-content -->
            </div><!-- .entry-content-wrapper -->

        </article><!-- #post-## -->

        <?php
        // If comments are open or we have at least one comment, load up the comment template.
        if ( comments_open() || get_comments_number() ) :
            comments_template();
        endif;
        ?>

    <?php endwhile; // End of the loop. ?>

    </main><!-- #main -->
</div><!-- #primary -->
<?php get_sidebar(); ?>

<?php get_footer(); ?>
