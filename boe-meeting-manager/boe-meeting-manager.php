<?php
/*
Plugin Name: BOE Meeting Manager
Description: Comprehensive Board of Education meeting management with agenda templates, resolution workflows, policy management, and PDF generation.
Version: 2.0.1
Author: Jason Koenig & Claude.ai
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Text Domain: boe-meeting-manager
Domain Path: /languages
*/

defined('ABSPATH') || exit;

// Define plugin constants
define('BOE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BOE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BOE_PLUGIN_VERSION', '2.0.1');
define('BOE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BOE_PLUGIN_TEXTDOMAIN', 'boe-meeting-manager');

// Core meeting management includes
require_once BOE_PLUGIN_PATH . 'includes/cpt-register.php';
require_once BOE_PLUGIN_PATH . 'includes/agenda-defaults.php';
require_once BOE_PLUGIN_PATH . 'includes/menu-meeting-templates.php';
require_once BOE_PLUGIN_PATH . 'includes/settings-page.php';
require_once BOE_PLUGIN_PATH . 'includes/pie-calendar.php';
require_once BOE_PLUGIN_PATH . 'includes/relationship-filters.php';
require_once BOE_PLUGIN_PATH . 'includes/agenda-builder.php';
require_once BOE_PLUGIN_PATH . 'includes/calendar-integration.php';
require_once BOE_PLUGIN_PATH . 'includes/resolution-builder.php';
require_once BOE_PLUGIN_PATH . 'includes/consent-agenda.php';

// Policy management includes (moved from theme)
require_once BOE_PLUGIN_PATH . 'includes/policy-cpt.php';
require_once BOE_PLUGIN_PATH . 'includes/policy-acf-fields.php';
require_once BOE_PLUGIN_PATH . 'includes/policy-builder.php';
require_once BOE_PLUGIN_PATH . 'includes/policy-templates.php';

// New policy components (moved from theme)
require_once BOE_PLUGIN_PATH . 'includes/policy-pdf-generator.php';
require_once BOE_PLUGIN_PATH . 'includes/policy-accordion.php';
require_once BOE_PLUGIN_PATH . 'includes/policy-search.php';
require_once BOE_PLUGIN_PATH . 'includes/theme-compatibility.php';

// Asset management
require_once BOE_PLUGIN_PATH . 'includes/class-asset-manager.php';

// Enhanced asset enqueuing for Agenda Builder with jQuery UI
add_action('admin_enqueue_scripts', function($hook) {
    // Only load on agenda builder pages
    if (strpos($hook, 'agenda-builder') !== false) {
        
        // Load jQuery UI (required for sortable)
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-mouse');
        
        // Load jQuery UI CSS
        wp_enqueue_style('jquery-ui-core');
        wp_enqueue_style('jquery-ui-theme', 'https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css');
        
        // Load agenda builder assets using new structure
        BOE_Asset_Manager::enqueue_admin_js('agenda-builder-js', 'agenda-builder.js', [
            'jquery', 
            'jquery-ui-core',
            'jquery-ui-sortable',
            'jquery-ui-widget',
            'jquery-ui-mouse'
        ], BOE_PLUGIN_VERSION, true);
        
        BOE_Asset_Manager::enqueue_admin_css('agenda-builder-css', 'agenda-builder.css', [], BOE_PLUGIN_VERSION);
        
        // Load media library for file uploads
        wp_enqueue_media();
        
        // Localize script with AJAX data
        wp_localize_script('agenda-builder-js', 'agendaBuilder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('agenda_builder_nonce'),
            'postId' => isset($_GET['post_id']) ? intval($_GET['post_id']) : 0,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
    }
});

// Enqueue Resolution Builder assets
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'resolution-builder') !== false) {
        BOE_Asset_Manager::enqueue_admin_js('resolution-builder-js', 'resolution-builder.js', ['jquery'], BOE_PLUGIN_VERSION, true);
        BOE_Asset_Manager::enqueue_admin_css('resolution-builder-css', 'resolution-builder.css', [], BOE_PLUGIN_VERSION);
        
        wp_localize_script('resolution-builder-js', 'resolutionBuilder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('resolution_builder_nonce'),
            'resolutionId' => isset($_GET['resolution_id']) ? intval($_GET['resolution_id']) : 0
        ]);
    }
});

// Enqueue Policy Builder assets
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'policy-builder') !== false || strpos($hook, 'policy-migration') !== false) {
        BOE_Asset_Manager::enqueue_admin_js('policy-builder-js', 'policy-builder.js', ['jquery'], BOE_PLUGIN_VERSION, true);
        BOE_Asset_Manager::enqueue_admin_css('policy-builder-css', 'policy-builder.css', [], BOE_PLUGIN_VERSION);
        
        wp_localize_script('policy-builder-js', 'policyBuilder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('policy_builder_nonce'),
            'policyId' => isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0
        ]);
    }
});

// Add custom post status dropdown options for resolutions
add_action('admin_footer-post.php', function() {
    global $post;
    if ($post && $post->post_type === 'resolution') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            const statusOptions = {
                'submit_approval': '<?php esc_html_e('Submit for Approval', 'boe-meeting-manager'); ?>',
                'approved': '<?php esc_html_e('Approved', 'boe-meeting-manager'); ?>', 
                'denied': '<?php esc_html_e('Denied', 'boe-meeting-manager'); ?>'
            };
            
            const $dropdown = $('#post_status');
            
            // Add custom statuses to dropdown if missing
            $.each(statusOptions, function(value, label) {
                if ($dropdown.find("option[value='" + value + "']").length === 0) {
                    $dropdown.append('<option value="' + value + '">' + label + '</option>');
                }
            });
            
            // Set current status in UI
            <?php if (in_array($post->post_status, ['submit_approval', 'approved', 'denied'])) : ?>
                const currentStatus = '<?php echo esc_js($post->post_status); ?>';
                const currentLabel = statusOptions[currentStatus] || currentStatus;
                $dropdown.val(currentStatus);
                $('#post-status-display').text(currentLabel);
                $('.misc-pub-post-status span').text(currentLabel);
            <?php endif; ?>
        });
        </script>
        <?php
    }
});

// Redirect resolution edit links to Resolution Builder
add_filter('post_row_actions', function($actions, $post) {
    if ($post->post_type === 'resolution') {
        // Replace the default Edit link with Resolution Builder link
        if (isset($actions['edit'])) {
            $resolution_builder_url = admin_url('admin.php?page=resolution-builder-new&resolution_id=' . $post->ID);
            $actions['edit'] = '<a href="' . esc_url($resolution_builder_url) . '">' . esc_html__('Edit in Resolution Builder', 'boe-meeting-manager') . '</a>';
        }
        
        // Add a separate link to the regular WordPress editor if needed
        $wp_edit_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
        $actions['wp_edit'] = '<a href="' . esc_url($wp_edit_url) . '">' . esc_html__('WordPress Editor', 'boe-meeting-manager') . '</a>';
    }
    
    // Redirect policy edit links to Policy Builder
    if ($post->post_type === 'policy') {
        // Replace the default Edit link with Policy Builder link
        if (isset($actions['edit'])) {
            $policy_builder_url = admin_url('admin.php?page=policy-builder-new&policy_id=' . $post->ID);
            $actions['edit'] = '<a href="' . esc_url($policy_builder_url) . '">' . esc_html__('Edit in Policy Builder', 'boe-meeting-manager') . '</a>';
        }
        
        // Add a separate link to the regular WordPress editor if needed
        $wp_edit_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
        $actions['wp_edit'] = '<a href="' . esc_url($wp_edit_url) . '">' . esc_html__('WordPress Editor', 'boe-meeting-manager') . '</a>';
    }
    
    return $actions;
}, 10, 2);

// Also handle the page title edit links
add_filter('get_edit_post_link', function($link, $post_id, $context) {
    $post = get_post($post_id);
    if ($post && $context === 'display') {
        if ($post->post_type === 'resolution') {
            return admin_url('admin.php?page=resolution-builder-new&resolution_id=' . $post_id);
        }
        if ($post->post_type === 'policy') {
            return admin_url('admin.php?page=policy-builder-new&policy_id=' . $post_id);
        }
    }
    return $link;
}, 10, 3);

// Plugin activation hook
register_activation_hook(__FILE__, 'boe_plugin_activate');
function boe_plugin_activate() {
    // Check minimum requirements
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('BOE Meeting Manager requires PHP 7.4 or higher.', 'boe-meeting-manager'),
            esc_html__('Plugin Activation Error', 'boe-meeting-manager'),
            ['back_link' => true]
        );
    }

    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('BOE Meeting Manager requires WordPress 5.0 or higher.', 'boe-meeting-manager'),
            esc_html__('Plugin Activation Error', 'boe-meeting-manager'),
            ['back_link' => true]
        );
    }
    
    // Flush rewrite rules to ensure custom post types work
    flush_rewrite_rules();
    
    // Set default options
    if (!get_option('boe_meeting_locations')) {
        update_option('boe_meeting_locations', [
            __('Board Room - District Administration Building', 'boe-meeting-manager'),
            __('Cherry Creek High School - Conference Room', 'boe-meeting-manager'),
            __('Virtual Meeting', 'boe-meeting-manager')
        ]);
    }
    
    // Create default policy sections if none exist
    if (!term_exists('Administration', 'policy-section')) {
        wp_insert_term(__('Administration', 'boe-meeting-manager'), 'policy-section', [
            'description' => __('Administrative policies and procedures', 'boe-meeting-manager')
        ]);
    }
    
    if (!term_exists('Board of Education', 'policy-section')) {
        wp_insert_term(__('Board of Education', 'boe-meeting-manager'), 'policy-section', [
            'description' => __('Board governance and operations', 'boe-meeting-manager')
        ]);
    }
    
    // Set default editor modes
    if (!get_option('boe_editor_modes')) {
        update_option('boe_editor_modes', [
            'meeting' => 'classic',
            'resolution' => 'classic',
            'policy' => 'classic'
        ]);
    }
    
    // Set activation flag for admin notices
    set_transient('boe_plugin_activated', true, 30);
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'boe_plugin_deactivate');
function boe_plugin_deactivate() {
    // Clear any cached data
    wp_cache_flush();
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clear transients
    delete_transient('boe_plugin_activated');
}

// Add activation notice
add_action('admin_notices', function() {
    if (get_transient('boe_plugin_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php esc_html_e('BOE Meeting Manager activated successfully!', 'boe-meeting-manager'); ?></strong>
                <?php printf(
                    esc_html__('Get started by visiting the %s page.', 'boe-meeting-manager'),
                    '<a href="' . esc_url(admin_url('admin.php?page=agenda-builder')) . '">' . esc_html__('Agenda Builder', 'boe-meeting-manager') . '</a>'
                ); ?>
            </p>
        </div>
        <?php
        delete_transient('boe_plugin_activated');
    }
});

// Add migration status to admin
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && in_array($screen->id, [
        'toplevel_page_policy-builder',
        'policy-builder_page_policy-display'
    ]) && current_user_can('manage_options')) {
        
        // Check if migration from theme is needed
        $migration_needed = false;
        $theme_files = [
            'generate-pdf.php',
            'page-policy-accordion-basic.php',
            'css/policy-accordion.css',
            'js/policy-accordion.js'
        ];
        
        foreach ($theme_files as $file) {
            if (file_exists(get_stylesheet_directory() . '/' . $file)) {
                $migration_needed = true;
                break;
            }
        }
        
        if ($migration_needed) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . esc_html__('Theme Migration:', 'boe-meeting-manager') . '</strong> ' . 
                 esc_html__('Policy components have been moved to the plugin.', 'boe-meeting-manager') . ' ';
            echo esc_html__('Some files in your theme can now be removed.', 'boe-meeting-manager') . ' ';
            echo '<a href="' . esc_url(admin_url('admin.php?page=policy-display#migration')) . '">' . 
                 esc_html__('View migration status', 'boe-meeting-manager') . '</a></p>';
            echo '</div>';
        }
    }
});

// Add admin menu link for migration status
add_action('admin_menu', function() {
    add_submenu_page(
        'policy-builder',
        __('Migration Status', 'boe-meeting-manager'),
        __('Migration Status', 'boe-meeting-manager'),
        'manage_options',
        'policy-migration-status',
        function() {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Policy Migration Status', 'boe-meeting-manager') . '</h1>';
            PolicyThemeCompatibility::display_migration_status();
            echo '</div>';
        }
    );
}, 999);

// Comments removal (preserved from original)
add_action('admin_init', function () {
    global $pagenow;
    
    if ($pagenow === 'edit-comments.php') {
        wp_redirect(admin_url());
        exit;
    }
    
    remove_meta_box('commentsdiv', 'post', 'normal');
    remove_meta_box('commentstatusdiv', 'post', 'normal');
    remove_meta_box('trackbacksdiv', 'post', 'normal');
    
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
});

// Disable comments feed
add_action('do_feed_rdf', function() { wp_die(); });
add_action('do_feed_rss', function() { wp_die(); });
add_action('do_feed_rss2', function() { wp_die(); });
add_action('do_feed_atom', function() { wp_die(); });
add_action('do_feed_rss2_comments', function() { wp_die(); });
add_action('do_feed_atom_comments', function() { wp_die(); });

// Remove comments from admin menu
add_action('admin_menu', function() {
    remove_menu_page('edit-comments.php');
});

// Remove comments from admin bar
add_action('wp_before_admin_bar_render', function() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
});

// TinyMCE custom button (with updated path)
function boe_add_olstart_plugin($plugins) {
    $plugins['olstart'] = BOE_Asset_Manager::get_tinymce_url('olstart-plugin.js');
    return $plugins;
}

function boe_add_olstart_button($buttons) {
    array_push($buttons, 'olstart');
    return $buttons;
}

function boe_add_tinymce_plugin() {
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        return;
    }
    
    if (get_user_option('rich_editing') !== 'true') {
        return;
    }
    
    add_filter('mce_external_plugins', 'boe_add_olstart_plugin');
    add_filter('mce_buttons', 'boe_add_olstart_button');
}

add_action('admin_head', 'boe_add_tinymce_plugin');

// TinyMCE settings (preserved from original)
add_filter('tiny_mce_before_init', function ($init) {
    $init['entities'] = '';
    $init['convert_entities'] = false;
    $init['verify_html'] = false;
    return $init;
});

// Load plugin textdomain for translations
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'boe-meeting-manager',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

// Add plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=boe-editor-settings')) . '">' . 
                     esc_html__('Settings', 'boe-meeting-manager') . '</a>';
    $agenda_link = '<a href="' . esc_url(admin_url('admin.php?page=agenda-builder')) . '">' . 
                   esc_html__('Agenda Builder', 'boe-meeting-manager') . '</a>';
    
    array_unshift($links, $settings_link);
    array_unshift($links, $agenda_link);
    
    return $links;
});

// Add plugin meta links
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $links[] = '<a href="https://github.com/your-repo/boe-meeting-manager" target="_blank">' . 
                   esc_html__('Documentation', 'boe-meeting-manager') . '</a>';
        $links[] = '<a href="https://github.com/your-repo/boe-meeting-manager/issues" target="_blank">' . 
                   esc_html__('Support', 'boe-meeting-manager') . '</a>';
    }
    return $links;
}, 10, 2);