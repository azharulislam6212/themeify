<?php
/**
 * Template Name: Contact page
 *
 * Any page can use this template to show its title, its content and the contact form.
 */
get_header();

while (have_posts()) :
    the_post();
    get_template_part('template-parts/contact-page');
endwhile;

get_footer();
