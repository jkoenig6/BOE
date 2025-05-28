<?php
/**
 * Plugin Path Fixes & Theme Deference
 * File: includes/theme-compatibility.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PolicyThemeCompatibility {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Remove hardcoded theme references
        add_filter('policy_pdf_generator_url', [$this, 'fix_pdf_generator_url']);
        add_filter('policy_accordion_assets_url', [$this, 'fix_accordion_assets_url']);
        
        // Template location fixes
        add_filter('template_include', [$this, 'policy_template_hierarchy'], 5);
        
        // Asset loading improvements
        add_action('wp_enqueue_scripts', [$this, 'enqueue_policy_frontend_assets']);
        
        // Admin asset path fixes
        add_action('admin_enqueue_scripts', [$this, 'fix_admin_asset_paths']);
    }
    
    /**
     * Fix PDF generator URL to work with any theme
     */
    public function fix_pdf_generator_url($url) {
        // Remove hardcoded astra-child references
        $url = str_replace('/astra-child/', '/' . get_stylesheet() . '/', $url);
        return $url;
    }
    
    /**
     * Fix accordion assets URL
     */
    public function fix_accordion_assets_url($url) {
        // Use plugin URL instead of theme URL
        return plugin_dir_url(__FILE__) . '../assets/';
    }
    
    /**
     * Enhanced template hierarchy for policies
     */
    public function policy_template_hierarchy($template) {
        global $wp_query;
        
        if (is_singular('policy')) {
            return $this->locate_policy_template([
                'single-policy-' . get_post_field('post_name') . '.php',
                'single-policy.php',
                'single.php',
                'index.php'
            ], $template);
        }
        
        if (is_post_type_archive('policy')) {
            return $this->locate_policy_template([
                'archive-policy.php',
                'archive.php',
                'index.php'
            ], $template);
        }
        
        if (is_tax('policy-section')) {
            $term = get_queried_object();
            return $this->locate_policy_template([
                'taxonomy-policy-section-' . $term->slug . '.php',
                'taxonomy-policy-section.php',
                'taxonomy.php',
                'archive.php',
                'index.php'
            ], $template);
        }
        
        return $template;
    }
    
    /**
     * Locate policy template with fallback to plugin
     */
    private function locate_policy_template($template_names, $default_template) {
        // First, try to find in active theme
        $theme_template = locate_template($template_names);
        if ($theme_template) {
            return $theme_template;
        }
        
        // Then check plugin templates
        foreach ($template_names as $template_name) {
            $plugin_template = plugin_dir_path(__FILE__) . '../templates/' . $template_name;
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $default_template;
    }
    
    /**
     * Enqueue frontend assets with proper theme deference using new structure
     */
    public function enqueue_policy_frontend_assets() {
        // Policy frontend styles
        if (is_singular('policy') || is_post_type_archive('policy') || is_tax('policy-section')) {
            // Check if theme has custom policy styles
            $theme_policy_css = get_stylesheet_directory() . '/css/policy-frontend.css';
            
            if (file_exists($theme_policy_css)) {
                wp_enqueue_style(
                    'policy-frontend-theme',
                    get_stylesheet_directory_uri() . '/css/policy-frontend.css',
                    [],
                    filemtime($theme_policy_css)
                );
            } else {
                BOE_Asset_Manager::enqueue_frontend_css('policy-frontend-plugin', 'policy-frontend.css', [], BOE_PLUGIN_VERSION);
            }
        }
        
        // Search highlighting styles
        if (is_search() || (is_singular('policy') && !empty($_GET['highlight']))) {
            BOE_Asset_Manager::enqueue_frontend_css('policy-search-highlighting', 'search-highlighting.css', [], BOE_PLUGIN_VERSION);
        }
    }
    
    /**
     * Fix admin asset paths to remove theme dependencies
     */
    public function fix_admin_asset_paths($hook) {
        // Only on policy-related admin pages
        if (!$this->is_policy_admin_page($hook)) {
            return;
        }
        
        // Dequeue any theme-specific policy assets and replace with plugin versions
        wp_dequeue_style('astra-child-policy-admin');
        wp_dequeue_script('astra-child-policy-admin');
        
        // Enqueue plugin admin styles using new structure
        BOE_Asset_Manager::enqueue_admin_css('policy-admin-plugin', 'policy-admin.css', [], BOE_PLUGIN_VERSION);
    }
    
    /**
     * Check if current admin page is policy-related
     */
    private function is_policy_admin_page($hook) {
        global $typenow, $pagenow;
        
        $policy_hooks = [
            'edit.php',
            'post.php',
            'post-new.php',
            'edit-tags.php'
        ];
        
        $policy_pages = [
            'policy-builder',
            'policy-builder-new',
            'policy-display'
        ];
        
        // Check for policy post type pages
        if (in_array($pagenow, $policy_hooks) && $typenow === 'policy') {
            return true;
        }
        
        // Check for policy taxonomy pages
        if ($pagenow === 'edit-tags.php' && isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'policy-section') {
            return true;
        }
        
        // Check for policy builder pages
        if (isset($_GET['page'])) {
            foreach ($policy_pages as $page) {
                if (strpos($_GET['page'], $page) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get theme-aware asset URL
     */
    public static function get_asset_url($asset_path, $asset_type = 'css') {
        // Check if theme has the asset
        $theme_asset_path = get_stylesheet_directory() . '/' . $asset_type . '/' . $asset_path;
        
        if (file_exists($theme_asset_path)) {
            return get_stylesheet_directory_uri() . '/' . $asset_type . '/' . $asset_path;
        }
        
        // Use Asset Manager for plugin assets with new structure
        $context = ($asset_type === 'css') ? 'frontend' : 'frontend';
        return BOE_Asset_Manager::get_asset_url($asset_path, $asset_type, $context);
    }
    
    /**
     * Get theme-aware template path
     */
    public static function get_template_path($template_name) {
        // Check theme first
        $theme_template = locate_template([$template_name]);
        if ($theme_template) {
            return $theme_template;
        }
        
        // Check plugin templates
        $plugin_template = plugin_dir_path(__FILE__) . '../templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return false;
    }
    
    /**
     * Create migration helper for moving from theme to plugin
     */
    public static function migrate_from_theme() {
        $migration_log = [];
        
        // Check for existing theme files that should be moved
        $theme_files_to_check = [
            'generate-pdf.php' => __('PDF generator', 'boe-meeting-manager'),
            'page-policy-accordion-basic.php' => __('Policy accordion template', 'boe-meeting-manager'),
            'css/policy-accordion.css' => __('Policy accordion styles', 'boe-meeting-manager'),
            'js/policy-accordion.js' => __('Policy accordion JavaScript', 'boe-meeting-manager'),
            'js/highlight-links.js' => __('Search highlighting script', 'boe-meeting-manager'),
            'js/olstart-plugin.js' => __('TinyMCE plugin', 'boe-meeting-manager'),
            'single-policy.php' => __('Single policy template', 'boe-meeting-manager')
        ];
        
        $theme_dir = get_stylesheet_directory();
        
        foreach ($theme_files_to_check as $file => $description) {
            $file_path = $theme_dir . '/' . $file;
            
            if (file_exists($file_path)) {
                $migration_log[] = [
                    'file' => $file,
                    'description' => $description,
                    'status' => 'found_in_theme',
                    'action_needed' => __('Can be removed from theme - plugin provides this functionality', 'boe-meeting-manager')
                ];
            }
        }
        
        // Check for hardcoded theme references in plugin files
        $files_to_scan = [
            BOE_PLUGIN_PATH . 'boe-meeting-manager.php',
            BOE_PLUGIN_PATH . 'includes/policy-builder.php',
            BOE_PLUGIN_PATH . 'includes/policy-templates.php'
        ];
        
        foreach ($files_to_scan as $file_path) {
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                if (strpos($content, 'astra-child') !== false) {
                    $migration_log[] = [
                        'file' => basename($file_path),
                        'description' => __('Contains hardcoded theme reference', 'boe-meeting-manager'),
                        'status' => 'needs_update',
                        'action_needed' => __('Update to use dynamic theme detection', 'boe-meeting-manager')
                    ];
                }
            }
        }
        
        return $migration_log;
    }
    
    /**
     * Display migration status in admin
     */
    public static function display_migration_status() {
        $migration_log = self::migrate_from_theme();
        
        if (empty($migration_log)) {
            echo '<div class="notice notice-success"><p>✅ ' . esc_html__('No theme migration issues detected.', 'boe-meeting-manager') . '</p></div>';
            return;
        }
        
        echo '<div class="migration-status-panel">';
        echo '<h3>' . esc_html__('Theme Migration Status', 'boe-meeting-manager') . '</h3>';
        echo '<table class="widefat">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('File', 'boe-meeting-manager') . '</th>';
        echo '<th>' . esc_html__('Description', 'boe-meeting-manager') . '</th>';
        echo '<th>' . esc_html__('Status', 'boe-meeting-manager') . '</th>';
        echo '<th>' . esc_html__('Action Needed', 'boe-meeting-manager') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($migration_log as $item) {
            $status_class = $item['status'] === 'found_in_theme' ? 'notice-warning' : 'notice-error';
            echo '<tr class="' . esc_attr($status_class) . '">';
            echo '<td><code>' . esc_html($item['file']) . '</code></td>';
            echo '<td>' . esc_html($item['description']) . '</td>';
            echo '<td>' . esc_html(str_replace('_', ' ', $item['status'])) . '</td>';
            echo '<td>' . esc_html($item['action_needed']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
        
        // Add CSS for status indicators
        echo '<style>
            .migration-status-panel .notice-warning td {
                background-color: #fff3cd;
                border-left: 4px solid #ffc107;
            }
            .migration-status-panel .notice-error td {
                background-color: #f8d7da;
                border-left: 4px solid #dc3545;
            }
            .migration-status-panel code {
                background: rgba(0,0,0,0.1);
                padding: 2px 4px;
                border-radius: 3px;
                font-family: monospace;
            }
        </style>';
    }
}

// Initialize Theme Compatibility
new PolicyThemeCompatibility();