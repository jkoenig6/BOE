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

// Core includes
require_once BPB_PLUGIN_PATH . 'includes/class-asset-manager.php';
require_once BPB_PLUGIN_PATH . 'includes/policy-cpt.php';
require_once BPB_PLUGIN_PATH . 'includes/policy-acf-fields.php';
require_once BPB_PLUGIN_PATH . 'includes/policy-builder.php';
require_once BPB_PLUGIN_PATH . 'includes/policy-templates.php';
require_once BPB_PLUGIN_PATH . 'includes/policy-pdf-generator.php';
require_once BPB_PLUGIN_PATH . 'includes/policy-accordion.php';
require_once BPB_PLUGIN_PATH . 'includes/policy-search.php';
require_once BPB_PLUGIN_PATH . 'includes/theme-compatibility.php';

// Asset management for Policy Builder
class BPB_Asset_Manager {
    
    public static function get_asset_url($file, $type = 'css', $context = 'admin') {
        $base_url = plugin_dir_url(__FILE__) . 'assets/';
        
        switch ($type) {
            case 'css':
                return $base_url . "css/{$context}/{$file}";
            case 'js':
                return $base_url . "js/{$context}/{$file}";
            case 'image':
                return $base_url . "images/{$file}";
            case 'tinymce':
                return $base_url . "js/admin/tinymce/{$file}";
            default:
                return $base_url . $file;
        }
    }
    
    public static function enqueue_admin_css($handle, $file, $deps = [], $version = null) {
        if (!$version) {
            $version = BPB_PLUGIN_VERSION;
        }
        
        wp_enqueue_style(
            $handle,
            self::get_asset_url($file, 'css', 'admin'),
            $deps,
            $version
        );
    }
    
    public static function enqueue_admin_js($handle, $file, $deps = ['jquery'], $version = null, $in_footer = true) {
        if (!$version) {
            $version = BPB_PLUGIN_VERSION;
        }
        
        wp_enqueue_script(
            $handle,
            self::get_asset_url($file, 'js', 'admin'),
            $deps,
            $version,
            $in_footer
        );
    }
    
    public static function enqueue_frontend_css($handle, $file, $deps = [], $version = null) {
        if (!$version) {
            $version = BPB_PLUGIN_VERSION;
        }
        
        wp_enqueue_style(
            $handle,
            self::get_asset_url($file, 'css', 'frontend'),
            $deps,
            $version
        );
    }
    
    public static function enqueue_frontend_js($handle, $file, $deps = ['jquery'], $version = null, $in_footer = true) {
        if (!$version) {
            $version = BPB_PLUGIN_VERSION;
        }
        
        wp_enqueue_script(
            $handle,
            self::get_asset_url($file, 'js', 'frontend'),
            $deps,
            $version,
            $in_footer
        );
    }
    
    public static function get_tinymce_url($file) {
        return self::get_asset_url($file, 'tinymce');
    }
}

// Enqueue Policy Builder assets
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'policy-builder') !== false || strpos($hook, 'policy-migration') !== false) {
        BPB_Asset_Manager::enqueue_admin_js('policy-builder-js', 'policy-builder.js', ['jquery'], BPB_PLUGIN_VERSION, true);
        BPB_Asset_Manager::enqueue_admin_css('policy-builder-css', 'policy-builder.css', [], BPB_PLUGIN_VERSION);
        
        wp_localize_script('policy-builder-js', 'policyBuilder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('policy_builder_nonce'),
            'policyId' => isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0
        ]);
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

// TinyMCE custom button
function bpb_add_olstart_plugin($plugins) {
    $plugins['olstart'] = BPB_Asset_Manager::get_tinymce_url('olstart-plugin.js');
    return $plugins;
}

function bpb_add_olstart_button($buttons) {
    array_push($buttons, 'olstart');
    return $buttons;
}

function bpb_add_tinymce_plugin() {
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        return;
    }
    
    if (get_user_option('rich_editing') !== 'true') {
        return;
    }
    
    add_filter('mce_external_plugins', 'bpb_add_olstart_plugin');
    add_filter('mce_buttons', 'bpb_add_olstart_button');
}

add_action('admin_head', 'bpb_add_tinymce_plugin');

// TinyMCE settings
add_filter('tiny_mce_before_init', function ($init) {
    $init['entities'] = '';
    $init['convert_entities'] = false;
    $init['verify_html'] = false;
    return $init;
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

// Add plugin meta links
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $links[] = '<a href="https://github.com/your-repo/board-policy-builder" target="_blank">' . 
                   esc_html__('Documentation', 'board-policy-builder') . '</a>';
        $links[] = '<a href="https://github.com/your-repo/board-policy-builder/issues" target="_blank">' . 
                   esc_html__('Support', 'board-policy-builder') . '</a>';
    }
    return $links;
}, 10, 2);