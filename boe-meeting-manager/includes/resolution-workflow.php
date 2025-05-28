<?php
// 1. Register “Review” and “Approved” statuses for Resolutions
add_action('init', function() {
    register_post_status('review', [
        'label'                     => _x('Review', 'resolution'),
        'public'                    => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Review <span class="count">(%s)</span>', 'Review <span class="count">(%s)</span>'),
    ]);
    register_post_status('approved', [
        'label'                     => _x('Approved', 'resolution'),
        'public'                    => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>'),
    ]);
});


// Only list Meetings in draft/pending/future/private when picking Assigned Meeting
add_filter('acf/fields/post_object/query/name=assigned_meeting', function($args, $field, $post_id) {
    if (get_post_type($post_id) === 'resolution') {
        $args['post_type']   = 'meeting';
        $args['post_status'] = ['draft','pending','future','private'];
        $args['orderby']     = 'date';
        $args['order']       = 'DESC';
    }
    return $args;
}, 10, 3);
