<?php
/**
 * Policy PDF Generator
 * File: includes/policy-pdf-generator.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PolicyPDFGenerator {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Add PDF download links to policy content
        //add_filter('the_content', [$this, 'add_pdf_download_link']);
        
        // Handle PDF generation requests
        add_action('wp_ajax_generate_policy_pdf', [$this, 'generate_pdf']);
        add_action('wp_ajax_nopriv_generate_policy_pdf', [$this, 'generate_pdf']);
        
        // Add query var for PDF generation
        add_filter('query_vars', [$this, 'add_pdf_query_var']);
        
        // Handle PDF generation via URL parameter
        add_action('template_redirect', [$this, 'handle_pdf_request']);
        
        // Enqueue PDF assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_pdf_assets']);
    }
    
    /**
     * Add PDF query variable
     */
    public function add_pdf_query_var($vars) {
        $vars[] = 'generate_pdf';
        $vars[] = 'policy_pdf';
        return $vars;
    }
    
    /**
     * Handle PDF generation requests via URL
     */
    public function handle_pdf_request() {
        if (get_query_var('generate_pdf') && get_query_var('policy_pdf')) {
            $post_id = intval(get_query_var('policy_pdf'));
            $this->generate_pdf_output($post_id);
            exit;
        }
    }
    
    /**
     * Enqueue PDF-related assets using new structure
     */
    public function enqueue_pdf_assets() {
        if (is_singular('policy')) {
            BPB_Asset_Manager::enqueue_frontend_css('policy-pdf-styles', 'policy-pdf.css', [], BPB_PLUGIN_VERSION);
        }
    }
        
    /**
     * Add PDF download link to policy content
     */
    public function add_pdf_download_link($content) {
        if (is_singular('policy') && in_the_loop() && is_main_query()) {
            global $post;
            
            // Debug info for admins
            $debug_info = '';
            if (current_user_can('manage_options') && isset($_GET['pdf_debug'])) {
                $debug_info = $this->get_debug_info($post->ID);
            }
            
            // Generate PDF URL
            $pdf_url = add_query_arg([
                'generate_pdf' => '1',
                'policy_pdf' => $post->ID
            ], home_url('/'));
            
            // Check if we should add the download link
            if ($this->should_show_pdf_link($post->ID)) {
                $pdf_link = $this->get_pdf_download_html($pdf_url);
                
                // Add PDF link before content
                $content = $debug_info . $pdf_link . $content;
            } else {
                // Add debug info for admins even if PDF link doesn't show
                if (current_user_can('manage_options')) {
                    $debug_info = $this->get_debug_info($post->ID);
                    $content = $debug_info . $content;
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Get debug information for admins
     */
    private function get_debug_info($post_id) {
        $dompdf_available = $this->load_dompdf();
        $code = get_field('code-policy', $post_id);
        $title_policy = get_field('title-policy', $post_id);
        $should_show = $this->should_show_pdf_link($post_id);
        
        $debug = '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">';
        $debug .= '<strong>PDF Debug (Admin Only):</strong><br>';
        $debug .= 'DOMPDF: ' . ($dompdf_available ? '✅' : '❌') . '<br>';
        $debug .= 'Policy Code: ' . ($code ? '✅ ' . esc_html($code) : '❌ Missing') . '<br>';
        $debug .= 'Policy Title: ' . ($title_policy ? '✅ ' . esc_html($title_policy) : '❌ Missing') . '<br>';
        $debug .= 'Should Show PDF: ' . ($should_show ? '✅ Yes' : '❌ No') . '<br>';
        
        if (!$dompdf_available) {
            $paths = [
                plugin_dir_path(__FILE__) . '../includes/dompdf/autoload.inc.php',
                plugin_dir_path(__FILE__) . '../vendor/dompdf/autoload.inc.php'
            ];
            $debug .= '<strong>Checked paths:</strong><br>';
            foreach ($paths as $path) {
                $exists = file_exists($path);
                $debug .= ($exists ? '✅' : '❌') . ' ' . $path . '<br>';
            }
        }
        
        if ($should_show) {
            $pdf_url = add_query_arg([
                'generate_pdf' => '1',
                'policy_pdf' => $post_id
            ], home_url('/'));
            $debug .= '<a href="' . esc_url($pdf_url) . '" target="_blank">Test PDF Generation</a><br>';
        }
        
        $debug .= '</div>';
        
        return $debug;
    }
    
    /**
     * Check if PDF link should be shown
     */
    private function should_show_pdf_link($post_id) {
        // Check if policy has required fields
        $code = get_field('code-policy', $post_id);
        $title_policy = get_field('title-policy', $post_id);
        
        // Check if DOMPDF is available
        $dompdf_available = $this->load_dompdf();
        
        // Debug logging
        error_log("PDF Link Debug - Code: " . ($code ? 'Yes' : 'No') . ", Title: " . ($title_policy ? 'Yes' : 'No') . ", DOMPDF: " . ($dompdf_available ? 'Yes' : 'No'));
        
        return !empty($code) && !empty($title_policy) && $dompdf_available;
    }
    
    /**
     * Get PDF download HTML
     */
    private function get_pdf_download_html($pdf_url) {
        // Try to get icon from active theme first, then fallback to plugin
        $icon_url = $this->get_pdf_icon_url();
        
        return sprintf(
            '<div class="pdf-download-link" style="text-align: right; margin-bottom: 12px;">
                <a href="%s" target="_blank" title="Download this policy as PDF">
                    <img src="%s" alt="Download PDF" style="height: 32px; width: auto;">
                </a>
            </div>',
            esc_url($pdf_url),
            esc_url($icon_url)
        );
    }
    
    /**
     * Get PDF icon URL - updated to use new asset structure
     */
    private function get_pdf_icon_url() {
        // Check if theme has the icon
        $theme_icon_path = get_stylesheet_directory() . '/images/pdf_download_icon.png';
        if (file_exists($theme_icon_path)) {
            return get_stylesheet_directory_uri() . '/images/pdf_download_icon.png';
        }
        
        // Check if specific icon exists in uploads
        $uploads_dir = wp_upload_dir();
        $icon_path = $uploads_dir['basedir'] . '/pdf_download_icon.png';
        if (file_exists($icon_path)) {
            return $uploads_dir['baseurl'] . '/pdf_download_icon.png';
        }
        
        // Fallback to plugin default icon using new structure
        return BPB_Asset_Manager::get_asset_url('icons/pdf-icon.png', 'image');
    }
        
    /**
     * AJAX handler for PDF generation
     */
    public function generate_pdf() {
        if (!isset($_GET['post_id'])) {
            wp_die('Missing post ID');
        }
        
        $post_id = intval($_GET['post_id']);
        $this->generate_pdf_output($post_id);
    }
    
    /**
     * Generate and output PDF
     */
    public function generate_pdf_output($post_id) {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'policy') {
            wp_die('Invalid post');
        }
        
        // Check if DOMPDF is available
        if (!$this->load_dompdf()) {
            wp_die('PDF generation library not available');
        }
        
        // Generate PDF content
        $html = $this->generate_pdf_html($post_id);
        
        // Create PDF
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        
        // Add page numbers
        $this->add_page_numbers($dompdf);
        
        // Stream to browser
        $filename = $this->generate_pdf_filename($post);
        $dompdf->stream($filename, ["Attachment" => true]);
    }
    
    /**
     * Load DOMPDF library
     */
    private function load_dompdf() {
        // Check if already loaded
        if (class_exists('\Dompdf\Dompdf')) {
            return true;
        }
        
        // Primary locations to check
        $dompdf_paths = [
            plugin_dir_path(__FILE__) . '../includes/dompdf/autoload.inc.php',
            plugin_dir_path(__FILE__) . '../vendor/dompdf/autoload.inc.php',
            plugin_dir_path(__FILE__) . 'dompdf/autoload.inc.php',
            ABSPATH . 'vendor/dompdf/dompdf/autoload.inc.php'
        ];
        
        foreach ($dompdf_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                error_log("DOMPDF loaded from: " . $path);
                return class_exists('\Dompdf\Dompdf');
            }
        }
        
        error_log("DOMPDF not found in any location. Checked: " . implode(', ', $dompdf_paths));
        return false;
    }
    
    /**
     * Generate PDF HTML content
     */
    private function generate_pdf_html($post_id) {
        $post = get_post($post_id);
        
        // Retrieve metadata
        $title = esc_html(get_the_title($post_id));
        $content = apply_filters('the_content', get_field('policy', $post_id) ?: $post->post_content);
        $printed_datetime = date('m/d/Y');
        
        // Custom Fields
        $title_policy = get_field('title-policy', $post_id);
        $code_policy = get_field('code-policy', $post_id);
        $adopted_policy = get_field('adopted-policy', $post_id);
        $last_revised_policy = get_field('last_revised-policy', $post_id);
        
        // Taxonomy
        $terms = get_the_terms($post_id, 'policy-section');
        $section = '';
        if ($terms && !is_wp_error($terms)) {
            $section = implode(', ', wp_list_pluck($terms, 'name'));
        }
        
        // Get logo URL
        $logo_url = $this->get_logo_url();
        
        // Construct HTML
        $html = '
            <style>
                ' . $this->get_pdf_styles() . '
            </style>

            <div class="header">
                <table class="header-table">
                    <tr>
                        <td class="logo-cell">
                            <img src="' . esc_url($logo_url) . '" alt="District Logo">
                        </td>
                        <td class="title-cell">
                            Board of Education Policy
                        </td>
                        <td class="date-cell">
                            Printed: ' . $printed_datetime . '
                        </td>
                    </tr>
                </table>
            </div>

            <div>
                <h1>' . esc_html($title) . '</h1>
            </div>

            <div class="meta-section">
                <div><strong>Section:</strong> ' . esc_html($section) . '</div>
                <div><strong>Title:</strong> ' . esc_html($title_policy) . '</div>
                <div><strong>Code:</strong> ' . esc_html($code_policy) . '</div>
                <div><strong>Adopted:</strong> ' . esc_html($adopted_policy) . '</div>
                <div><strong>Last Revised:</strong> ' . esc_html($last_revised_policy) . '</div>
            </div>

            <div class="content">
                ' . $content . '
            </div>
        ';
        
        return $html;
    }
    
    /**
     * Get logo URL - updated to use new asset structure
     */
    private function get_logo_url() {
        // Check for custom logo in theme
        $theme_logo = get_stylesheet_directory() . '/images/logo.png';
        if (file_exists($theme_logo)) {
            return get_stylesheet_directory_uri() . '/images/logo.png';
        }
        
        // Check for uploaded logo
        $logo_url = get_option('policy_pdf_logo_url');
        if ($logo_url) {
            return $logo_url;
        }
        
        // Check for site logo
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
            if ($logo_data) {
                return $logo_data[0];
            }
        }
        
        // Fallback to plugin default using new structure
        return BPB_Asset_Manager::get_asset_url('icons/default-logo.png', 'image');
    }
    
    /**
     * Get PDF CSS styles
     */
    private function get_pdf_styles() {
        return '
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                margin-top: 100px;
                margin-bottom: 60px;
                line-height: 1.4;
            }

            .header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: 80px;
                padding: 10px;
            }

            .header-table {
                width: 100%;
                border-collapse: collapse;
            }

            .header-table td {
                vertical-align: middle;
            }

            .logo-cell {
                width: 25%;
            }

            .logo-cell img {
                max-height: 60px;
            }

            .title-cell {
                width: 50%;
                text-align: center;
                font-size: 18px;
                font-weight: bold;
            }

            .date-cell {
                width: 25%;
                text-align: right;
                font-size: 10px;
                color: #333;
            }

            h1 {
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 10px;
                line-height: 1.2;
            }

            .meta-section {
                margin-bottom: 20px;
            }

            .meta-section div {
                margin-bottom: 4px;
            }

            .content {
                line-height: 1.5;
            }
            
            .content table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
            }
            
            .content table.black-bordered th,
            .content table.black-bordered td,
            .content table[border="1"] th,
            .content table[border="1"] td {
                border: 1px solid black;
                padding: 6px;
                text-align: left;
            }
            
            .content ol.policy-list {
                margin-left: 0;
                padding-left: 1.25em;
            }
            
            .content .policy-list li {
                margin-bottom: 0.25em;
            }
        ';
    }
    
    /**
     * Add page numbers to PDF
     */
    private function add_page_numbers($dompdf) {
        $canvas = $dompdf->get_canvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $font_size = 8;

        $canvas->page_text(
            $canvas->get_width() / 2,
            $canvas->get_height() - 30,
            "Page {PAGE_NUM} of {PAGE_COUNT}",
            $font,
            $font_size,
            [0, 0, 0],
            0,
            true
        );
    }
    
    /**
     * Generate PDF filename
     */
    private function generate_pdf_filename($post) {
        $code = get_field('code-policy', $post->ID);
        $filename = $code ? sanitize_title($code) : sanitize_title($post->post_title);
        return $filename . '.pdf';
    }
    
    /**
     * Updated debug shortcode with internationalization
     */
    public function pdf_debug_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>' . esc_html__('Debug info only available to administrators.', 'board-policy-builder') . '</p>';
        }
        
        ob_start();
        
        echo '<div style="background: #f0f0f0; padding: 15px; border: 1px solid #ccc; margin: 10px 0;">';
        echo '<h3>' . esc_html__('PDF Debug Information', 'board-policy-builder') . '</h3>';
        
        // Check DOMPDF
        $dompdf_available = $this->load_dompdf();
        echo '<p><strong>' . esc_html__('DOMPDF Available:', 'board-policy-builder') . '</strong> ' . ($dompdf_available ? '✅ ' . esc_html__('Yes', 'board-policy-builder') : '❌ ' . esc_html__('No', 'board-policy-builder')) . '</p>';
        
        if (!$dompdf_available) {
            $check_paths = [
                BPB_PLUGIN_PATH . 'includes/dompdf/autoload.inc.php',
                BPB_PLUGIN_PATH . 'vendor/dompdf/autoload.inc.php',
                BPB_PLUGIN_PATH . 'includes/dompdf/autoload.inc.php'
            ];
            
            echo '<p><strong>' . esc_html__('Checked paths:', 'board-policy-builder') . '</strong></p><ul>';
            foreach ($check_paths as $path) {
                $exists = file_exists($path) ? '✅' : '❌';
                echo '<li>' . $exists . ' ' . esc_html($path) . '</li>';
            }
            echo '</ul>';
        }
        
        // Check current policy (if on policy page)
        if (is_singular('policy')) {
            global $post;
            $code = get_field('code-policy', $post->ID);
            $title_policy = get_field('title-policy', $post->ID);
            
            echo '<p><strong>' . esc_html__('Current Policy:', 'board-policy-builder') . '</strong> ' . esc_html($post->post_title) . '</p>';
            echo '<p><strong>' . esc_html__('Policy Code:', 'board-policy-builder') . '</strong> ' . ($code ? esc_html($code) : '❌ ' . esc_html__('Missing', 'board-policy-builder')) . '</p>';
            echo '<p><strong>' . esc_html__('Policy Title Field:', 'board-policy-builder') . '</strong> ' . ($title_policy ? esc_html($title_policy) : '❌ ' . esc_html__('Missing', 'board-policy-builder')) . '</p>';
            
            $should_show = $this->should_show_pdf_link($post->ID);
            echo '<p><strong>' . esc_html__('Should Show PDF Link:', 'board-policy-builder') . '</strong> ' . ($should_show ? '✅ ' . esc_html__('Yes', 'board-policy-builder') : '❌ ' . esc_html__('No', 'board-policy-builder')) . '</p>';
            
            if ($should_show) {
                $pdf_url = add_query_arg([
                    'generate_pdf' => '1',
                    'policy_pdf' => $post->ID
                ], home_url('/'));
                echo '<p><strong>' . esc_html__('PDF URL:', 'board-policy-builder') . '</strong> <a href="' . esc_url($pdf_url) . '" target="_blank">' . esc_html__('Test PDF Generation', 'board-policy-builder') . '</a></p>';
            }
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Admin settings for PDF configuration
     */
    public static function add_admin_settings() {
        add_settings_section(
            'policy_pdf_settings',
            'PDF Generation Settings',
            [__CLASS__, 'pdf_settings_section_callback'],
            'boe-editor-settings'
        );
        
        add_settings_field(
            'policy_pdf_logo_url',
            'PDF Logo URL',
            [__CLASS__, 'logo_url_field_callback'],
            'boe-editor-settings',
            'policy_pdf_settings'
        );
        
        register_setting('boe_editor_settings', 'policy_pdf_logo_url');
    }
    
    public static function pdf_settings_section_callback() {
        echo '<p>Configure PDF generation settings for policies.</p>';
    }
    
    public static function logo_url_field_callback() {
        $logo_url = get_option('policy_pdf_logo_url', '');
        echo '<input type="url" name="policy_pdf_logo_url" value="' . esc_attr($logo_url) . '" class="regular-text" />';
        echo '<p class="description">URL to logo for PDF headers. Must be from your domain or uploads folder.</p>';
    }
    
}

// Initialize PDF Generator
new PolicyPDFGenerator();

// Add settings to admin
add_action('admin_init', ['PolicyPDFGenerator', 'add_admin_settings']);

// ✅ ADDED: Validate logo URL on save
add_action('admin_init', function() {
    add_filter('pre_update_option_policy_pdf_logo_url', function($new_value, $old_value) {
        if (empty($new_value)) {
            return '';
        }
        
        // Validate URL format
        $new_value = esc_url_raw($new_value);
        if (!$new_value) {
            add_settings_error('policy_pdf_logo_url', 'invalid_url', 'Invalid logo URL format.');
            return $old_value;
        }
        
        // Check domain restrictions
        $parsed_url = parse_url($new_value);
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        
        if (!isset($parsed_url['host']) || 
            ($parsed_url['host'] !== $site_domain && 
             strpos($new_value, wp_upload_dir()['baseurl']) !== 0)) {
            add_settings_error('policy_pdf_logo_url', 'invalid_domain', 'Logo must be from your domain or uploads folder.');
            return $old_value;
        }
        
        return $new_value;
    }, 10, 2);
});
