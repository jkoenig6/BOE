<?php
/**
 * Simple Asset Management Class for Board Policy Builder
 * File: includes/class-asset-manager.php
 */

defined('ABSPATH') || exit;

class BPB_Asset_Manager {
    
    /**
     * Get asset URL with proper path structure
     */
    public static function get_asset_url($file, $type = 'css', $context = 'admin') {
        $base_url = plugin_dir_url(dirname(__FILE__)) . 'assets/';
        
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
    
    /**
     * Enqueue admin CSS
     */
    public static function enqueue_admin_css($handle, $file, $deps = [], $version = null) {
        if (!$version) {
            $version = defined('BPB_PLUGIN_VERSION') ? BPB_PLUGIN_VERSION : '1.0.0';
        }
        
        wp_enqueue_style(
            $handle,
            self::get_asset_url($file, 'css', 'admin'),
            $deps,
            $version
        );
    }
    
    /**
     * Enqueue admin JS
     */
    public static function enqueue_admin_js($handle, $file, $deps = ['jquery'], $version = null, $in_footer = true) {
        if (!$version) {
            $version = defined('BPB_PLUGIN_VERSION') ? BPB_PLUGIN_VERSION : '1.0.0';
        }
        
        wp_enqueue_script(
            $handle,
            self::get_asset_url($file, 'js', 'admin'),
            $deps,
            $version,
            $in_footer
        );
    }
    
    /**
     * Enqueue frontend CSS
     */
    public static function enqueue_frontend_css($handle, $file, $deps = [], $version = null) {
        if (!$version) {
            $version = defined('BPB_PLUGIN_VERSION') ? BPB_PLUGIN_VERSION : '1.0.0';
        }
        
        wp_enqueue_style(
            $handle,
            self::get_asset_url($file, 'css', 'frontend'),
            $deps,
            $version
        );
    }
    
    /**
     * Enqueue frontend JS
     */
    public static function enqueue_frontend_js($handle, $file, $deps = ['jquery'], $version = null, $in_footer = true) {
        if (!$version) {
            $version = defined('BPB_PLUGIN_VERSION') ? BPB_PLUGIN_VERSION : '1.0.0';
        }
        
        wp_enqueue_script(
            $handle,
            self::get_asset_url($file, 'js', 'frontend'),
            $deps,
            $version,
            $in_footer
        );
    }
    
    /**
     * Get TinyMCE plugin URL
     */
    public static function get_tinymce_url($file) {
        return self::get_asset_url($file, 'tinymce');
    }
    
    /**
     * Check if asset file exists
     */
    public static function asset_exists($file, $type = 'css', $context = 'admin') {
        $base_path = plugin_dir_path(dirname(__FILE__)) . 'assets/';
        
        switch ($type) {
            case 'css':
                $file_path = $base_path . "css/{$context}/{$file}";
                break;
            case 'js':
                $file_path = $base_path . "js/{$context}/{$file}";
                break;
            case 'image':
                $file_path = $base_path . "images/{$file}";
                break;
            case 'tinymce':
                $file_path = $base_path . "js/admin/tinymce/{$file}";
                break;
            default:
                $file_path = $base_path . $file;
        }
        
        return file_exists($file_path);
    }
}