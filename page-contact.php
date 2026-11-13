<?php
/**
 * The page with the slug "contact": title, page content and the contact form (template-parts/contact-page.php).
 */
get_header();

while (have_posts()) :
    the_post();
    get_template_part('template-parts/contact-page');
endwhile;

get_footer();
