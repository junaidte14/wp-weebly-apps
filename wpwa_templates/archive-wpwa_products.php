<?php get_header(); ?>
<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
    <?php if ( have_posts() ) : ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="page-header">
                <h1 class="page-title"><?php _e('Weebly Products', 'wpwa');?></h1>
            </header>
            <table>
                <!-- Display table headers -->
				<tr>
                <!-- Start the Loop -->
                <?php 
					$count = 0;
					while ( have_posts() ) : the_post();
						$count+=1;
				?>   
                        <td style="text-align: center;">
							<?php the_post_thumbnail(); ?>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><br>
							<strong><?php echo '$'.floatval(get_post_meta( get_the_ID(), 'wpwa_product_price', true )); ?></strong><br>
							<a href="<?php echo esc_url(get_post_meta( get_the_ID(), 'wpwa_product_center_url', true )); ?>" target="_blank"><?php _e('View in Weebly App Center', 'wpwa');?></a><br>
						</td>
                    
                <?php 
					if($count%3==0){
						echo '</tr><tr>';
					}
					endwhile; ?>
     			</tr>
                <!-- Display page navigation -->
     
            </table>
            <?php global $wp_query;
            if ( isset( $wp_query->max_num_pages ) && $wp_query->max_num_pages > 1 ) { ?>
                <nav style="overflow: hidden;">
                    <div class="nav-previous alignleft"><?php next_posts_link( '<span class="meta-nav">&larr;</span> Older Products', $wp_query->max_num_pages); ?></div>
                    <div class="nav-next alignright"><?php previous_posts_link( 'Newer Products <span class= "meta-nav">&rarr;</span>' ); ?></div>
                </nav>
            <?php };?>
        </article>
        <?php
    endif; ?>
    </main>
</div>
<?php get_sidebar(); ?>
<?php get_footer(); ?>