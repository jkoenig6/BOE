<?php get_header(); ?>

<main id="primary" class="site-main">

    <!-- ✅ Custom SearchWP form and template output -->
    <?php echo do_shortcode('[searchwp_form id="3"]'); ?>
    <?php echo do_shortcode('[searchwp_template id="1"]'); ?>

    <h1>Search Results for: <?php echo get_search_query(); ?></h1>

    <?php if (have_posts()) : ?>
        <div class="search-results-list">
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div class="entry-summary">
                        <?php the_excerpt(); ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <div class="pagination">
            <?php the_posts_pagination(); ?>
        </div>

    <?php else : ?>
        <p>No results found. Try refining your search.</p>
    <?php endif; ?>

</main>

<?php get_footer(); ?>
