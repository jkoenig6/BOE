<?php
/**
 * Fixed Policy Template Handler
 * File: includes/policy-templates.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PolicyTemplates {
    
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
            $plugin_template = BOE_PLUGIN_PATH . 'templates/single-policy.php';
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
        $plugin_template = BOE_PLUGIN_PATH . 'templates/single-policy.php';
        
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
            
            // Get the policy content from ACF field
            $policy_content = get_field('policy', $post->ID);
            
            if (!empty($policy_content)) {
                return $policy_content;
            }
        }
        
        return $content;
    }
    
    /**
     * Enqueue styles for policy pages using new asset structure
     */
    public function enqueue_policy_styles() {
        if (is_singular('policy') || is_post_type_archive('policy')) {
            BOE_Asset_Manager::enqueue_frontend_css('policy-styles', 'policy-frontend.css', [], BOE_PLUGIN_VERSION);
        }
    }
}

new PolicyTemplates();