<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
/*
// Delete all policies
$policies = get_posts([
    'post_type' => 'policy',
    'numberposts' => -1,
    'post_status' => 'any'
]);

foreach ($policies as $policy) {
    wp_delete_post($policy->ID, true);
}

// Delete taxonomy
register_taxonomy('policy-section', []);
$terms = get_terms(['taxonomy' => 'policy-section', 'hide_empty' => false]);
foreach ($terms as $term) {
    wp_delete_term($term->term_id, 'policy-section');
}

// Delete options
delete_option('policy_pdf_logo_url');

*/