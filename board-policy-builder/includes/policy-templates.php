<?php
/**
 * Policy Template Handler for Board Policy Builder
 * File: includes/policy-templates.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BPB_PolicyTemplates {
    
    public function __construct() {
        // Set high priority to ensure this runs first
        add_filter('template_include', [$this, 'policy_template_loader'], 5);
        add_filter('the_content', [$this, 'policy_content_filter']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_policy_styles']);
        
        // Debug hook to check what's happening
        add_action('wp', [$this, 'debug_template_loading']);
    }
    
    /**
     * Debug template loading
     */
    public function debug_template_loading() {
        if (is_singular('policy') && current_user_can('manage_options') && isset($_GET['template_debug'])) {
            $plugin_template = BPB_PLUGIN_PATH . 'templates/single-policy.php';
            $theme_template = locate_template(['single-policy.php']);
            
            error_log("Template Debug:");
            error_log("Plugin template path: " . $plugin_template);
            error_log("Plugin template exists: " . (file_exists($plugin_template) ? 'YES' : 'NO'));
            error_log("Theme template: " . ($theme_template ? $theme_template : 'NONE'));
        }
    }
    
    /**
     * Load custom template for policies - FIXED VERSION
     */
    public function policy_template_loader($template) {
        if (!is_singular('policy')) {
            return $template;
        }
        
        // Check if child theme has a single-policy.php template first
        $theme_template = locate_template(['single-policy.php']);
        
        // If theme template exists, use it
        if ($theme_template) {
            error_log("Using theme template: " . $theme_template);
            return $theme_template;
        }
        
        // Use plugin template - FIXED PATH
        $plugin_template = BPB_PLUGIN_PATH . 'templates/single-policy.php';
        
        if (file_exists($plugin_template)) {
            error_log("Using plugin template: " . $plugin_template);
            return $plugin_template;
        }
        
        // Log if template not found
        error_log("Policy template not found! Checked: " . $plugin_template);
        
        return $template;
    }
    
    /**
     * Filter policy content to use ACF field instead of post_content
     */
    public function policy_content_filter($content) {
        if (is_singular('policy') && in_the_loop() && is_main_query()) {
            global $post;
            
            // Enhanced content retrieval with multiple fallbacks
            $policy_content = $this->get_policy_content($post->ID);
            
            if (!empty($policy_content)) {
                return $policy_content;
            }
        }
        
        return $content;
    }
    
    /**
     * Enhanced policy content retrieval with multiple fallbacks
     */
    private function get_policy_content($post_id) {
        // Method 1: Standard ACF get_field()
        $policy_content = get_field('policy', $post_id);
        
        if (!empty($policy_content)) {
            return $policy_content;
        }
        
        // Method 2: Direct meta query
        $policy_content = get_post_meta($post_id, 'policy', true);
        
        if (!empty($policy_content)) {
            return $policy_content;
        }
        
        // Method 3: Underscore-prefixed meta (ACF internal)
        $policy_content = get_post_meta($post_id, '_policy', true);
        
        if (!empty($policy_content)) {
            return $policy_content;
        }
        
        // Method 4: Last resort - use post content
        $post = get_post($post_id);
        if ($post && !empty($post->post_content)) {
            return $post->post_content;
        }
        
        return '';
    }
    
    /**
     * Enqueue styles for policy pages using new asset structure
     */
    public function enqueue_policy_styles() {
        if (is_singular('policy') || is_post_type_archive('policy')) {
            BPB_Asset_Manager::enqueue_frontend_css('policy-styles', 'policy-frontend.css', [], BPB_PLUGIN_VERSION);
        }
        
        // Search highlighting styles
        if (is_search() || (is_singular('policy') && !empty($_GET['highlight']))) {
            BPB_Asset_Manager::enqueue_frontend_css('policy-search-highlighting', 'search-highlighting.css', [], BPB_PLUGIN_VERSION);
        }
        
        // Policy cleanup script for removing author/date
        if (is_singular('policy')) {
            BPB_Asset_Manager::enqueue_frontend_js('policy-cleanup', 'policy-cleanup.js', ['jquery'], BPB_PLUGIN_VERSION, true);
        }
    }
}

new BPB_PolicyTemplates();