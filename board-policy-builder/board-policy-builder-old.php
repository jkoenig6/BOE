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






    // TinyMCE custom button
    add_action('admin_head', 'bpb_add_tinymce_plugin');
}

function bpb_add_tinymce_plugin() {
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        return;
    }
    
    if (get_user_option('rich_editing') !== 'true') {
        return;
    }
    
    // Verify TinyMCE plugin file exists
    if (BPB_Asset_Manager::asset_exists('olstart-plugin.js', 'tinymce')) {
        add_filter('mce_external_plugins', function($plugins) {
            $plugins['olstart'] = BPB_Asset_Manager::get_tinymce_url('olstart-plugin.js');
            return $plugins;
        });
        
        add_filter('mce_buttons', function($buttons) {
            array_push($buttons, 'olstart');
            return $buttons;
        });
    }
}

// TinyMCE settings
add_filter('tiny_mce_before_init', function ($init) {
    $init['entities'] = '';
    $init['convert_entities'] = false;
    $init['verify_html'] = false;
    return $init;
});




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




// Add this to board-policy-builder.php temporarily for debugging

function bpb_debug_tinymce_loading() {
    if (current_user_can('manage_options')) {
        $file_path = BPB_PLUGIN_PATH . 'assets/js/admin/tinymce/olstart-plugin.js';
        $exists = file_exists($file_path);
        $asset_manager_check = BPB_Asset_Manager::asset_exists('olstart-plugin.js', 'tinymce');
        $url = BPB_Asset_Manager::get_tinymce_url('olstart-plugin.js');
        
        error_log("=== TinyMCE Debug ===");
        error_log("Plugin path: " . $file_path);
        error_log("File exists: " . ($exists ? 'YES' : 'NO'));
        error_log("Asset Manager check: " . ($asset_manager_check ? 'YES' : 'NO'));
        error_log("Generated URL: " . $url);
        error_log("Current hook: " . current_action());
        error_log("User can edit posts: " . (current_user_can('edit_posts') ? 'YES' : 'NO'));
        error_log("Rich editing enabled: " . (get_user_option('rich_editing') === 'true' ? 'YES' : 'NO'));
        
        // Also check if we're on a policy page
        global $post;
        if ($post) {
            error_log("Current post type: " . $post->post_type);
        }
        error_log("==================");
    }
}

// Hook this to multiple actions to see what's happening
add_action('admin_init', 'bpb_debug_tinymce_loading');
add_action('admin_head', 'bpb_debug_tinymce_loading');