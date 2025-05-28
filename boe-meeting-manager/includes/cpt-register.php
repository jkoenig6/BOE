<?php
add_action('init', function () {
    // Meeting CPT
    register_post_type('meeting', [
        'labels' => [
            'name'               => 'Meetings',
            'singular_name'      => 'Meeting',
            'add_new'            => 'Add Meeting',
            'add_new_item'       => 'Add New Meeting',
            'edit_item'          => 'Edit Meeting',
            'new_item'           => 'New Meeting',
            'view_item'          => 'View Meeting',
            'search_items'       => 'Search Meetings',
            'not_found'          => 'No Meetings found',
            'not_found_in_trash' => 'No Meetings found in Trash',
            'all_items'          => 'All Meetings',
        ],
        'public'              => true,
        'has_archive'         => true, // ✅ this enables /meetings/
        'rewrite'             => ['slug' => 'meetings'], // ✅ pretty URLs
        'menu_position'       => 20,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'supports'            => ['title', 'editor'],
    ]);


    // Resolution CPT with extended support and capabilities
    register_post_type('resolution', [
        'labels' => [
            'name'                  => 'Resolutions',
            'singular_name'         => 'Resolution',
            'add_new'               => 'Add Resolution',
            'add_new_item'          => 'Add New Resolution',
            'edit_item'             => 'Edit Resolution',
            'new_item'              => 'New Resolution',
            'view_item'             => 'View Resolution',
            'search_items'          => 'Search Resolutions',
            'not_found'             => 'No Resolutions found',
            'not_found_in_trash'    => 'No Resolutions found in Trash',
            'all_items'             => 'All Resolutions',
        ],
        'public'             => true,
        'show_in_rest'       => true,
        'capability_type'    => 'post',
        'supports'           => ['title', 'editor', 'author', 'custom-fields'],
        'capabilities'       => [], // default capabilities
        'map_meta_cap'       => true,
    ]);

    // Meeting Types taxonomy
    register_taxonomy('meeting_type', 'meeting', [
        'labels'            => ['name' => 'Meeting Types', 'singular_name' => 'Meeting Type'],
        'public'            => true,
        'hierarchical'      => false,
        'show_in_rest'      => true,
    ]);
});
