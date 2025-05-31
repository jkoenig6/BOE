<?php
/*
Plugin Name: Board Policy Builder
Description: Standalone policy management system for Board of Education with builder interface, search, and PDF generation.
Version: 1.0.0
Author: Jason Koenig & Claude.ai
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Text Domain: board-policy-builder
Domain Path: /languages
*/

defined('ABSPATH') || exit;

// Define plugin constants
define('BPB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BPB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BPB_PLUGIN_VERSION', '1.0.0');
define('BPB_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BPB_PLUGIN_TEXTDOMAIN', 'board-policy-builder');

// Check for ACF dependency before loading the plugin
add_action('plugins_loaded', 'bpb_check_dependencies');

function bpb_check_dependencies() {
    // Check if ACF is available
    if (!function_exists('get_field') || !class_exists('ACF')) {
        add_action('admin_notices', 'bpb_acf_missing_notice');
        return false;
    }
    
    // ACF is available, load the plugin
    bpb_load_plugin();
    return true;
}

function bpb_acf_missing_notice() {
    if (current_user_can('activate_plugins')) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Board Policy Builder Error:', 'board-policy-builder'); ?></strong>
                <?php esc_html_e('This plugin requires Advanced Custom Fields (ACF) to function properly.', 'board-policy-builder'); ?>
            </p>
            <p>
                <?php printf(
                    esc_html__('Please install and activate %s before using this plugin.', 'board-policy-builder'),
                    '<a href="' . esc_url(admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term')) . '">' . esc_html__('Advanced Custom Fields', 'board-policy-builder') . '</a>'
                ); ?>
            </p>
        </div>
        <?php
    }
}

function bpb_load_plugin() {
    // Load Asset Manager FIRST - this prevents the duplicate class error
    require_once BPB_PLUGIN_PATH . 'includes/class-asset-manager.php';

    // Core includes
    require_once BPB_PLUGIN_PATH . 'includes/policy-cpt.php';
    require_once BPB_PLUGIN_PATH . 'includes/policy-acf-fields.php';
    require_once BPB_PLUGIN_PATH . 'includes/policy-builder.php';
    require_once BPB_PLUGIN_PATH . 'includes/policy-templates.php';
    require_once BPB_PLUGIN_PATH . 'includes/policy-pdf-generator.php';
    require_once BPB_PLUGIN_PATH . 'includes/policy-accordion.php';
    require_once BPB_PLUGIN_PATH . 'includes/policy-search.php';
    require_once BPB_PLUGIN_PATH . 'includes/theme-compatibility.php';

    // Initialize plugin features
    bpb_init_plugin_features();
}

function bpb_init_plugin_features() {
    // Enqueue Policy Builder assets
    add_action('admin_enqueue_scripts', function($hook) {
        if (strpos($hook, 'policy-builder') !== false || strpos($hook, 'policy-migration') !== false) {
            // Verify asset files exist before enqueuing
            if (BPB_Asset_Manager::asset_exists('policy-builder.js', 'js', 'admin')) {
                BPB_Asset_Manager::enqueue_admin_js('policy-builder-js', 'policy-builder.js', ['jquery'], BPB_PLUGIN_VERSION, true);
                
                wp_localize_script('policy-builder-js', 'policyBuilder', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('policy_builder_nonce'),
                    'policyId' => isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0
                ]);
            }
            
            if (BPB_Asset_Manager::asset_exists('policy-builder.css', 'css', 'admin')) {
                BPB_Asset_Manager::enqueue_admin_css('policy-builder-css', 'policy-builder.css', [], BPB_PLUGIN_VERSION);
            }
        }
    });

    // Redirect policy edit links to Policy Builder
    add_filter('post_row_actions', function($actions, $post) {
        if ($post->post_type === 'policy') {
            if (isset($actions['edit'])) {
                $policy_builder_url = admin_url('admin.php?page=policy-builder-new&policy_id=' . $post->ID);
                $actions['edit'] = '<a href="' . esc_url($policy_builder_url) . '">' . esc_html__('Edit in Policy Builder', 'board-policy-builder') . '</a>';
            }
            
            $wp_edit_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
            $actions['wp_edit'] = '<a href="' . esc_url($wp_edit_url) . '">' . esc_html__('WordPress Editor', 'board-policy-builder') . '</a>';
        }
        
        return $actions;
    }, 10, 2);

    // Handle page title edit links
    add_filter('get_edit_post_link', function($link, $post_id, $context) {
        $post = get_post($post_id);
        if ($post && $context === 'display' && $post->post_type === 'policy') {
            return admin_url('admin.php?page=policy-builder-new&policy_id=' . $post_id);
        }
        return $link;
    }, 10, 3);

    // Initialize TinyMCE plugin
    add_action('admin_init', 'bpb_init_tinymce_plugin');
}



// REPLACE the TinyMCE functions in board-policy-builder.php with these:

// TinyMCE Plugin Functions
function bpb_init_tinymce_plugin() {
    // Only for users who can edit
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        return;
    }
    
    // Only if rich editing is enabled
    if (get_user_option('rich_editing') !== 'true') {
        return;
    }
    
    // Check if plugin file exists
    $plugin_file = BPB_PLUGIN_PATH . 'assets/js/admin/tinymce/olstart-plugin.js';
    if (!file_exists($plugin_file)) {
        if (current_user_can('manage_options')) {
            error_log('TinyMCE olstart plugin file not found: ' . $plugin_file);
        }
        return;
    }
    
    // Register the external plugin and button
    add_filter('mce_external_plugins', 'bpb_add_olstart_plugin');
    add_filter('mce_buttons', 'bpb_add_olstart_button', 20); // High priority to run after other plugins
    
    if (current_user_can('manage_options')) {
        error_log('TinyMCE olstart plugin filters added');
    }
}

function bpb_add_olstart_plugin($plugin_array) {
    $plugin_array['olstart'] = BPB_PLUGIN_URL . 'assets/js/admin/tinymce/olstart-plugin.js?ver=' . BPB_PLUGIN_VERSION;
    
    if (current_user_can('manage_options')) {
        error_log('TinyMCE olstart plugin registered: ' . $plugin_array['olstart']);
    }
    
    return $plugin_array;
}

function bpb_add_olstart_button($buttons) {
    // Remove olstart if it's already there (to prevent duplicates)
    $buttons = array_filter($buttons, function($button) {
        return $button !== 'olstart';
    });
    
    // Find the numbered list button position
    $numlist_position = array_search('numlist', $buttons);
    
    if ($numlist_position !== false) {
        // Insert right after the numbered list button
        array_splice($buttons, $numlist_position + 1, 0, 'olstart');
        
        if (current_user_can('manage_options')) {
            error_log('TinyMCE olstart button added after numlist at position ' . ($numlist_position + 1));
        }
    } else {
        // Fallback: find bullet list and insert after it
        $bullist_position = array_search('bullist', $buttons);
        if ($bullist_position !== false) {
            array_splice($buttons, $bullist_position + 2, 0, 'olstart');
            
            if (current_user_can('manage_options')) {
                error_log('TinyMCE olstart button added after bullist at position ' . ($bullist_position + 2));
            }
        } else {
            // Last resort: add after bold button
            $bold_position = array_search('bold', $buttons);
            if ($bold_position !== false) {
                array_splice($buttons, $bold_position + 4, 0, 'olstart');
            } else {
                // Very last resort: add to end
                $buttons[] = 'olstart';
            }
            
            if (current_user_can('manage_options')) {
                error_log('TinyMCE olstart button added as fallback');
            }
        }
    }
    
    if (current_user_can('manage_options')) {
        error_log('TinyMCE final first toolbar buttons: ' . implode(', ', $buttons));
    }
    
    return $buttons;
}




/*


// TinyMCE Plugin Functions
function bpb_init_tinymce_plugin() {
    // Only for users who can edit
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        return;
    }
    
    // Only if rich editing is enabled
    if (get_user_option('rich_editing') !== 'true') {
        return;
    }
    
    // Check if plugin file exists
    $plugin_file = BPB_PLUGIN_PATH . 'assets/js/admin/tinymce/olstart-plugin.js';
    if (!file_exists($plugin_file)) {
        if (current_user_can('manage_options')) {
            error_log('TinyMCE olstart plugin file not found: ' . $plugin_file);
        }
        return;
    }
    
    // Register the external plugin and button
    add_filter('mce_external_plugins', 'bpb_add_olstart_plugin');
    add_filter('mce_buttons', 'bpb_add_olstart_button');
    
    if (current_user_can('manage_options')) {
        error_log('TinyMCE olstart plugin filters added');
    }
}

function bpb_add_olstart_plugin($plugin_array) {
    $plugin_array['olstart'] = BPB_PLUGIN_URL . 'assets/js/admin/tinymce/olstart-plugin.js?ver=' . BPB_PLUGIN_VERSION;
    
    if (current_user_can('manage_options')) {
        error_log('TinyMCE olstart plugin registered: ' . $plugin_array['olstart']);
    }
    
    return $plugin_array;
}

function bpb_add_olstart_button($buttons) {
    if (!in_array('olstart', $buttons)) {
        // Insert the button after the 'numlist' button (numbered list)
        $numlist_position = array_search('numlist', $buttons);
        if ($numlist_position !== false) {
            // Insert right after numlist
            array_splice($buttons, $numlist_position + 1, 0, 'olstart');
        } else {
            // If numlist not found, insert after bullist (bullet list)
            $bullist_position = array_search('bullist', $buttons);
            if ($bullist_position !== false) {
                array_splice($buttons, $bullist_position + 1, 0, 'olstart');
            } else {
                // Fallback: add to beginning of toolbar
                array_unshift($buttons, 'olstart');
            }
        }
        
        if (current_user_can('manage_options')) {
            error_log('TinyMCE olstart button positioned in first toolbar. All buttons: ' . implode(', ', $buttons));
        }
    }
    
    return $buttons;
}

// TinyMCE settings
add_filter('tiny_mce_before_init', function ($init) {
    $init['entities'] = '';
    $init['convert_entities'] = false;
    $init['verify_html'] = false;
    return $init;
});

*/



// Plugin activation hook
register_activation_hook(__FILE__, 'bpb_plugin_activate');
function bpb_plugin_activate() {
    // Check minimum requirements
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Board Policy Builder requires PHP 7.4 or higher.', 'board-policy-builder'),
            esc_html__('Plugin Activation Error', 'board-policy-builder'),
            ['back_link' => true]
        );
    }

    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Board Policy Builder requires WordPress 5.0 or higher.', 'board-policy-builder'),
            esc_html__('Plugin Activation Error', 'board-policy-builder'),
            ['back_link' => true]
        );
    }
    
    // Check for ACF during activation
    if (!function_exists('get_field') && !class_exists('ACF')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Board Policy Builder requires Advanced Custom Fields (ACF) plugin to be installed and activated.', 'board-policy-builder'),
            esc_html__('Plugin Activation Error', 'board-policy-builder'),
            ['back_link' => true]
        );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Create default policy sections if none exist
    if (!term_exists('Administration', 'policy-section')) {
        wp_insert_term(__('Administration', 'board-policy-builder'), 'policy-section', [
            'description' => __('Administrative policies and procedures', 'board-policy-builder')
        ]);
    }
    
    if (!term_exists('Board of Education', 'policy-section')) {
        wp_insert_term(__('Board of Education', 'board-policy-builder'), 'policy-section', [
            'description' => __('Board governance and operations', 'board-policy-builder')
        ]);
    }
    
    // Set activation flag for admin notices
    set_transient('bpb_plugin_activated', true, 30);
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'bpb_plugin_deactivate');
function bpb_plugin_deactivate() {
    wp_cache_flush();
    flush_rewrite_rules();
    delete_transient('bpb_plugin_activated');
}

// Add activation notice
add_action('admin_notices', function() {
    if (get_transient('bpb_plugin_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php esc_html_e('Board Policy Builder activated successfully!', 'board-policy-builder'); ?></strong>
                <?php printf(
                    esc_html__('Get started by visiting the %s page.', 'board-policy-builder'),
                    '<a href="' . esc_url(admin_url('admin.php?page=policy-builder')) . '">' . esc_html__('Policy Builder', 'board-policy-builder') . '</a>'
                ); ?>
            </p>
        </div>
        <?php
        delete_transient('bpb_plugin_activated');
    }
});

// Load plugin textdomain
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'board-policy-builder',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

// Add plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $policy_link = '<a href="' . esc_url(admin_url('admin.php?page=policy-builder')) . '">' . 
                   esc_html__('Policy Builder', 'board-policy-builder') . '</a>';
    
    array_unshift($links, $policy_link);
    
    return $links;
});