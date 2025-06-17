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
    // Enqueue Policy Builder assets with Word Import support
    add_action('admin_enqueue_scripts', function($hook) {
        // ✅ Updated to handle both new and edit pages
        if (strpos($hook, 'policy-builder') !== false || strpos($hook, 'policy-migration') !== false) {
            // Enqueue Mammoth.js from CDN
            wp_enqueue_script(
                'mammoth-js',
                'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.8.0/mammoth.browser.min.js',
                [],
                '1.8.0',
                true
            );
            
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
            
            // NEW: Enqueue Word import assets
            if (BPB_Asset_Manager::asset_exists('word-import.js', 'js', 'admin')) {
                BPB_Asset_Manager::enqueue_admin_js(
                    'word-import-js',
                    'word-import.js',
                    ['jquery', 'mammoth-js'],
                    BPB_PLUGIN_VERSION,
                    true
                );
            }
            
            if (BPB_Asset_Manager::asset_exists('word-import.css', 'css', 'admin')) {
                BPB_Asset_Manager::enqueue_admin_css(
                    'word-import-css',
                    'word-import.css',
                    [],
                    BPB_PLUGIN_VERSION
                );
            }
        }
    });

    // ✅ Updated redirect policy edit links to use new edit page structure
    add_filter('post_row_actions', function($actions, $post) {
        if ($post->post_type === 'policy') {
            if (isset($actions['edit'])) {
                // Use the dedicated edit page
                $policy_builder_url = admin_url('admin.php?page=policy-builder-edit&policy_id=' . $post->ID);
                $actions['edit'] = '<a href="' . esc_url($policy_builder_url) . '">' . esc_html__('Edit in Policy Builder', 'board-policy-builder') . '</a>';
            }
            
            $wp_edit_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
            $actions['wp_edit'] = '<a href="' . esc_url($wp_edit_url) . '">' . esc_html__('WordPress Editor', 'board-policy-builder') . '</a>';
        }
        
        return $actions;
    }, 10, 2);

    // ✅ Updated handle page title edit links to use new edit page
    add_filter('get_edit_post_link', function($link, $post_id, $context) {
        $post = get_post($post_id);
        if ($post && $context === 'display' && $post->post_type === 'policy') {
            return admin_url('admin.php?page=policy-builder-edit&policy_id=' . $post_id);
        }
        return $link;
    }, 10, 3);

    // Initialize TinyMCE plugin
    add_action('admin_init', 'bpb_init_tinymce_plugin');
}

// Enhanced TinyMCE Plugin Functions with Full AET Integration
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
    
    // Register the external plugin and button with higher priority
    add_filter('mce_external_plugins', 'bpb_add_olstart_plugin', 999);
    add_filter('mce_buttons', 'bpb_add_olstart_button', 999);
    
    if (current_user_can('manage_options')) {
        error_log('TinyMCE olstart plugin filters added with priority 999');
    }
}

function bpb_add_olstart_plugin($plugin_array) {
    $plugin_array['olstart'] = BPB_PLUGIN_URL . 'assets/js/admin/tinymce/olstart-plugin.js?ver=' . BPB_PLUGIN_VERSION;
    
    if (current_user_can('manage_options')) {
        error_log('TinyMCE olstart plugin registered: ' . $plugin_array['olstart']);
        error_log('Current plugins in array: ' . implode(', ', array_keys($plugin_array)));
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

// ✅ COMPLETELY REWRITTEN: Full AET Integration for Policy Builder
add_action('admin_init', function() {
    // ✅ Updated to handle both new and edit pages
    if (isset($_GET['page']) && (
        strpos($_GET['page'], 'policy-builder-new') !== false || 
        strpos($_GET['page'], 'policy-builder-edit') !== false
    )) {
        
        // ✅ NEW: Force load AET settings and apply them
        if (class_exists('Advanced_Editor_Tools') || function_exists('tadv_admin_init')) {
            
            // Get AET settings
            $tadv_options = get_option('tadv_settings', []);
            $tadv_admin_settings = get_option('tadv_admin_settings', []);
            
            if (current_user_can('manage_options')) {
                error_log('AET Settings found: ' . (empty($tadv_options) ? 'NO' : 'YES'));
                error_log('AET Admin Settings: ' . (empty($tadv_admin_settings) ? 'NO' : 'YES'));
                if (!empty($tadv_options)) {
                    error_log('AET Toolbar 1: ' . (isset($tadv_options['toolbar_1']) ? $tadv_options['toolbar_1'] : 'NOT SET'));
                    error_log('AET Toolbar 2: ' . (isset($tadv_options['toolbar_2']) ? $tadv_options['toolbar_2'] : 'NOT SET'));
                }
            }
            
            // Force TinyMCE to use AET configuration
            add_filter('tiny_mce_before_init', function($init) use ($tadv_options) {
                global $tadv_options;
                
                // Apply AET settings if they exist
                if (!empty($tadv_options)) {
                    
                    // Set toolbars from AET configuration
                    if (isset($tadv_options['toolbar_1']) && !empty($tadv_options['toolbar_1'])) {
                        $toolbar1 = $tadv_options['toolbar_1'];
                        
                        // Add our olstart button after numlist if it's not already there
                        if (strpos($toolbar1, 'olstart') === false && strpos($toolbar1, 'numlist') !== false) {
                            $toolbar1 = str_replace('numlist', 'numlist,olstart', $toolbar1);
                        } elseif (strpos($toolbar1, 'olstart') === false) {
                            $toolbar1 .= ',olstart';
                        }
                        
                        $init['toolbar1'] = $toolbar1;
                    }
                    
                    if (isset($tadv_options['toolbar_2']) && !empty($tadv_options['toolbar_2'])) {
                        $init['toolbar2'] = $tadv_options['toolbar_2'];
                    }
                    
                    if (isset($tadv_options['toolbar_3']) && !empty($tadv_options['toolbar_3'])) {
                        $init['toolbar3'] = $tadv_options['toolbar_3'];
                    }
                    
                    if (isset($tadv_options['toolbar_4']) && !empty($tadv_options['toolbar_4'])) {
                        $init['toolbar4'] = $tadv_options['toolbar_4'];
                    }
                    
                    // Apply other AET settings
                    if (isset($tadv_options['options'])) {
                        $options = explode(',', $tadv_options['options']);
                        
                        foreach ($options as $option) {
                            $option = trim($option);
                            switch ($option) {
                                case 'menubar':
                                    $init['menubar'] = true;
                                    break;
                                case 'advlist':
                                    if (!isset($init['plugins'])) $init['plugins'] = '';
                                    if (strpos($init['plugins'], 'advlist') === false) {
                                        $init['plugins'] .= ',advlist';
                                    }
                                    break;
                                // Add other AET options as needed
                            }
                        }
                    }
                }
                
                // Ensure our plugin is loaded
                if (!isset($init['plugins'])) {
                    $init['plugins'] = '';
                }
                if (strpos($init['plugins'], 'olstart') === false) {
                    $init['plugins'] .= (empty($init['plugins']) ? '' : ',') . 'olstart';
                }
                
                // Clean up plugins string
                $init['plugins'] = trim($init['plugins'], ',');
                
                if (current_user_can('manage_options')) {
                    error_log('Policy Builder TinyMCE Final Config:');
                    error_log('- Plugins: ' . $init['plugins']);
                    error_log('- Toolbar1: ' . (isset($init['toolbar1']) ? $init['toolbar1'] : 'NOT SET'));
                    error_log('- Toolbar2: ' . (isset($init['toolbar2']) ? $init['toolbar2'] : 'NOT SET'));
                }
                
                return $init;
            }, 1000); // High priority
            
            // Also hook into AET's filter functions directly
            if (function_exists('tadv_mce_buttons')) {
                add_filter('mce_buttons', function($buttons) {
                    // Let AET modify first
                    $buttons = tadv_mce_buttons($buttons);
                    // Then add our button
                    return bpb_add_olstart_button($buttons);
                }, 1001);
            }
            
            if (function_exists('tadv_mce_buttons_2')) {
                add_filter('mce_buttons_2', 'tadv_mce_buttons_2', 999);
            }
            
            if (function_exists('tadv_mce_buttons_3')) {
                add_filter('mce_buttons_3', 'tadv_mce_buttons_3', 999);
            }
            
            if (function_exists('tadv_mce_buttons_4')) {
                add_filter('mce_buttons_4', 'tadv_mce_buttons_4', 999);
            }
            
            if (function_exists('tadv_mce_external_plugins')) {
                add_filter('mce_external_plugins', function($plugins) {
                    // Let AET add its plugins first
                    $plugins = tadv_mce_external_plugins($plugins);
                    // Then add ours
                    return bpb_add_olstart_plugin($plugins);
                }, 1001);
            }
            
        } else {
            // AET not active, use basic configuration
            add_filter('tiny_mce_before_init', function($init) {
                // Basic toolbar with our button
                if (!isset($init['toolbar1'])) {
                    $init['toolbar1'] = 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,olstart,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv';
                } else {
                    // Add our button if not present
                    if (strpos($init['toolbar1'], 'olstart') === false) {
                        if (strpos($init['toolbar1'], 'numlist') !== false) {
                            $init['toolbar1'] = str_replace('numlist', 'numlist,olstart', $init['toolbar1']);
                        } else {
                            $init['toolbar1'] .= ',olstart';
                        }
                    }
                }
                
                // Ensure plugins are set
                if (!isset($init['plugins'])) {
                    $init['plugins'] = '';
                }
                if (strpos($init['plugins'], 'olstart') === false) {
                    $init['plugins'] .= (empty($init['plugins']) ? '' : ',') . 'olstart';
                }
                
                return $init;
            }, 999);
        }
    }
});

// TinyMCE global settings
add_filter('tiny_mce_before_init', function ($init) {
    $init['entities'] = '';
    $init['convert_entities'] = false;
    $init['verify_html'] = false;
    return $init;
});

// Debug functions for troubleshooting
function bpb_debug_tinymce_status() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $screen = get_current_screen();
    if (!$screen || (strpos($screen->id, 'policy-builder') === false)) {
        return;
    }
    
    $plugin_file = BPB_PLUGIN_PATH . 'assets/js/admin/tinymce/olstart-plugin.js';
    $plugin_url = BPB_PLUGIN_URL . 'assets/js/admin/tinymce/olstart-plugin.js';
    
    echo '<script>console.log("=== BPB TinyMCE Debug ===");</script>';
    echo '<script>console.log("Plugin file exists: ' . (file_exists($plugin_file) ? 'YES' : 'NO') . '");</script>';
    echo '<script>console.log("Plugin URL: ' . esc_js($plugin_url) . '");</script>';
    echo '<script>console.log("Current page: ' . esc_js($screen->id) . '");</script>';
    echo '<script>console.log("Rich editing enabled: ' . (get_user_option('rich_editing') === 'true' ? 'YES' : 'NO') . '");</script>';
    echo '<script>console.log("User can edit posts: ' . (current_user_can('edit_posts') ? 'YES' : 'NO') . '");</script>';
    
    // Check if AET is active
    if (class_exists('Advanced_Editor_Tools')) {
        echo '<script>console.log("Advanced Editor Tools: ACTIVE");</script>';
    } else {
        echo '<script>console.log("Advanced Editor Tools: NOT ACTIVE");</script>';
    }
    
    echo '<script>console.log("========================");</script>';
}

function bpb_debug_aet_status() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $screen = get_current_screen();
    if (!$screen || (strpos($screen->id, 'policy-builder') === false)) {
        return;
    }
    
    $aet_active = class_exists('Advanced_Editor_Tools') || function_exists('tadv_admin_init');
    $tadv_options = get_option('tadv_settings', []);
    
    echo '<script>console.log("=== AET Integration Debug ===");</script>';
    echo '<script>console.log("AET Active: ' . ($aet_active ? 'YES' : 'NO') . '");</script>';
    
    if ($aet_active && !empty($tadv_options)) {
        echo '<script>console.log("AET Toolbar 1: ' . esc_js($tadv_options['toolbar_1'] ?? 'NOT SET') . '");</script>';
        echo '<script>console.log("AET Toolbar 2: ' . esc_js($tadv_options['toolbar_2'] ?? 'NOT SET') . '");</script>';
        if (isset($tadv_options['options'])) {
            echo '<script>console.log("AET Options: ' . esc_js($tadv_options['options']) . '");</script>';
        }
    }
    
    echo '<script>console.log("=============================");</script>';
}

add_action('admin_head', 'bpb_debug_tinymce_status');
add_action('admin_head', 'bpb_debug_aet_status');

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