<?php
/**
 * Policy CPT Registration
 * File: includes/policy-cpt.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    // Policy CPT - FIXED VERSION
    register_post_type('policy', [
        'labels' => [
            'name'               => 'Policies',
            'singular_name'      => 'Policy',
            'add_new'            => 'Add Policy',
            'add_new_item'       => 'Add New Policy',
            'edit_item'          => 'Edit Policy',
            'new_item'           => 'New Policy',
            'view_item'          => 'View Policy',
            'search_items'       => 'Search Policies',
            'not_found'          => 'No Policies found',
            'not_found_in_trash' => 'No Policies found in Trash',
            'all_items'          => 'All Policies',
        ],
        'public'              => true,
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'policy'], // CHANGED: singular to match rewrite rules
        'menu_position'       => 21,
        'show_in_menu'        => false, // We'll handle this in Policy Builder
        'show_in_rest'        => true,
        'supports'            => ['title', 'editor', 'custom-fields'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);

    // Policy Section taxonomy
    register_taxonomy('policy-section', 'policy', [
        'labels'            => [
            'name' => 'Policy Sections', 
            'singular_name' => 'Policy Section',
            'add_new_item' => 'Add New Policy Section',
            'edit_item' => 'Edit Policy Section',
            'update_item' => 'Update Policy Section',
            'view_item' => 'View Policy Section',
            'search_items' => 'Search Policy Sections',
            'not_found' => 'No Policy Sections found',
        ],
        'public'            => true,
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => ['slug' => 'policy-section'],
    ]);
});

// Register archived post status for policies
add_action('init', function() {
    register_post_status('archived', [
        'label'                     => _x('Archived/Rescinded', 'policy'),
        'public'                    => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Archived/Rescinded <span class="count">(%s)</span>', 'Archived/Rescinded <span class="count">(%s)</span>')
    ]);
});

// Add archived status to dropdown on policy edit screen
add_action('admin_footer-post.php', function() {
    global $post;
    if ($post && $post->post_type === 'policy') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            const statusValue = 'archived';
            const statusLabel = 'Archived/Rescinded';
            const $dropdown = $('#post_status');

            // Add custom status to dropdown if it's missing
            if ($dropdown.length && $dropdown.find("option[value='" + statusValue + "']").length === 0) {
                $dropdown.append('<option value="' + statusValue + '">' + statusLabel + '</option>');
            }

            // If current post is archived, set it in the UI
            <?php if ($post->post_status === 'archived') : ?>
                $dropdown.val(statusValue);
                $('#post-status-display').text(statusLabel);
                $('.misc-pub-post-status span').text(statusLabel);
            <?php endif; ?>
        });
        </script>
        <?php
    }
});

// Add custom admin columns for policies
add_filter('manage_policy_posts_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['policy_code'] = 'Code';
            $new_columns['policy_section'] = 'Section';
            $new_columns['adopted_date'] = 'Adopted';
            $new_columns['last_revised'] = 'Last Revised';
        }
    }
    return $new_columns;
});

// Populate custom columns
add_action('manage_policy_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'policy_code':
            $code = get_field('code-policy', $post_id);
            echo $code ? esc_html($code) : '—';
            break;
            
        case 'policy_section':
            $terms = get_the_terms($post_id, 'policy-section');
            if ($terms && !is_wp_error($terms)) {
                $term_names = wp_list_pluck($terms, 'name');
                echo esc_html(implode(', ', $term_names));
            } else {
                echo '—';
            }
            break;
            
        case 'adopted_date':
            $adopted = get_field('adopted-policy', $post_id);
            echo $adopted ? esc_html($adopted) : '—';
            break;
            
        case 'last_revised':
            $revised = get_field('last_revised-policy', $post_id);
            echo $revised ? esc_html($revised) : '—';
            break;
    }
}, 10, 2);

// Make columns sortable
add_filter('manage_edit-policy_sortable_columns', function($columns) {
    $columns['policy_code'] = 'policy_code';
    $columns['adopted_date'] = 'adopted_date';
    $columns['last_revised'] = 'last_revised';
    return $columns;
});

// Handle sorting
add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'policy' && $screen->base === 'edit') {
        // ✅ FIXED: Sanitize and validate orderby parameter
        $orderby = sanitize_key($query->get('orderby'));
        
        // ✅ ADDED: Whitelist allowed orderby values
        $allowed_orderby = [
            'policy_code' => 'code-policy',
            'adopted_date' => 'adopted-policy', 
            'last_revised' => 'last_revised-policy'
        ];
        
        if (isset($allowed_orderby[$orderby])) {
            $meta_key = $allowed_orderby[$orderby];
            $query->set('meta_key', $meta_key);
            $query->set('orderby', 'meta_value');
        }
        
        // Default sort by title if no valid sort specified
        if (!isset($_GET['orderby']) && !$query->get('orderby')) {
            $query->set('orderby', 'title');
            $query->set('order', 'ASC');
        }
    }
});

// Add admin styles for status indicators
add_action('admin_head', function() {
    echo '<style>
        .status-archived { color: #d63638; font-weight: 600; }
        .column-policy_code { width: 100px; }
        .column-policy_section { width: 150px; }
        .column-adopted_date { width: 120px; }
        .column-last_revised { width: 120px; }
    </style>';
});