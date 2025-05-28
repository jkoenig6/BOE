<?php
/**
 * Policy Search & Highlighting Integration with Auto-Detection
 * File: includes/policy-search.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PolicySearch {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Search highlighting
        add_filter('the_content', [$this, 'highlight_search_term_in_policy']);
        
        // Enqueue search assets
        add_action('wp_enqueue_scripts', [$this, 'conditional_search_assets'], 20);
        
        // SearchWP integration
        add_action('plugins_loaded', [$this, 'searchwp_integration']);
        
        // Override SearchWP display elements for policies
        add_filter('the_author', [$this, 'hide_policy_author_in_search'], 10, 1);
        add_filter('get_the_author', [$this, 'hide_policy_author_in_search'], 10, 1);
        add_filter('the_date', [$this, 'hide_policy_date_in_search'], 10, 1);
        add_filter('get_the_date', [$this, 'hide_policy_date_in_search'], 10, 1);
        add_filter('the_excerpt', [$this, 'ensure_policy_excerpt_in_search'], 10, 1);
        
        // Clean up SearchWP output for policies
        add_action('wp_footer', [$this, 'clean_policy_search_output']);
        
        // Add search shortcodes
        add_shortcode('policy_search', [$this, 'policy_search_shortcode']);
        add_shortcode('policy_search_form', [$this, 'policy_search_form_shortcode']);
        
        // Custom search template override
        add_filter('template_include', [$this, 'search_template_loader']);
        
        // Modify search query for policies
        add_action('pre_get_posts', [$this, 'modify_search_query']);
        
        // Add admin notices for SearchWP
        add_action('admin_notices', [$this, 'searchwp_admin_notices']);
        
        // NEW: Auto-highlighting features
        add_filter('post_link', [$this, 'add_highlight_to_policy_links'], 10, 2);
        add_filter('post_type_link', [$this, 'add_highlight_to_policy_links'], 10, 2);
        add_action('wp', [$this, 'capture_search_context']);
        add_action('wp_footer', [$this, 'add_search_highlighting_script']);
    }
    
    /**
     * Automatically add highlight parameter to policy links when coming from search
     */
    public function add_highlight_to_policy_links($permalink, $post) {
        // Only for policy posts
        if (!$post || $post->post_type !== 'policy') {
            return $permalink;
        }
        
        // Only when we're in a search context
        if (!$this->is_search_context()) {
            return $permalink;
        }
        
        $search_term = $this->get_current_search_term();
        
        if ($search_term) {
            return add_query_arg('highlight', $search_term, $permalink);
        }
        
        return $permalink;
    }
    
    /**
     * Check if we're in a search context
     */
    private function is_search_context() {
        // Direct search page
        if (is_search()) {
            return true;
        }
        
        // SearchWP form submission
        if (!empty($_GET['s']) || !empty($_GET['swp_form'])) {
            return true;
        }
        
        // Coming from search (check referrer)
        $referrer = wp_get_referer();
        if ($referrer && (strpos($referrer, '?s=') !== false || strpos($referrer, 'swp_form') !== false)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get current search term from various sources
     */
    private function get_current_search_term() {
        // Direct search query
        if (get_search_query()) {
            return get_search_query();
        }
        
        // URL parameter
        if (!empty($_GET['s'])) {
            return sanitize_text_field($_GET['s']);
        }
        
        // SearchWP parameter
        if (!empty($_GET['swp_query'])) {
            return sanitize_text_field($_GET['swp_query']);
        }
        
        // Check referrer
        $referrer = wp_get_referer();
        if ($referrer) {
            $parsed_url = parse_url($referrer);
            if (isset($parsed_url['query'])) {
                parse_str($parsed_url['query'], $query_params);
                if (!empty($query_params['s'])) {
                    return sanitize_text_field($query_params['s']);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Capture search context in session for later use
     */
    public function capture_search_context() {
        if ($this->is_search_context()) {
            $search_term = $this->get_current_search_term();
            if ($search_term) {
                // Store in session for later pages
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['policy_search_term'] = $search_term;
                $_SESSION['policy_search_time'] = time();
            }
        }
    }
    
    /**
     * Highlight search terms in policy content (works with ACF fields)
     */
    public function highlight_search_term_in_policy($content) {
        if (!is_singular('policy')) {
            return $content;
        }
        
        // Get search term from various sources
        $search_term = $this->get_search_term();
        
        if (empty($search_term)) {
            return $content;
        }
        
        return $this->apply_highlighting_private($content, $search_term);
    }
    
    /**
     * Apply highlighting to any content (public method for templates)
     */
    public function apply_highlighting($content, $search_term) {
        if (empty($search_term) || empty($content)) {
            return $content;
        }
        
        // Sanitize search term
        $search_term = wp_strip_all_tags($search_term);
        $search_term = preg_quote($search_term, '/');
        
        // Highlight the term (case-insensitive)
        $highlighted_content = preg_replace(
            '/(' . $search_term . ')/i',
            '<mark class="search-highlight">$1</mark>',
            $content
        );
        
        return $highlighted_content ?: $content;
    }
    
    /**
     * Apply highlighting to any content (private helper)
     */
    private function apply_highlighting_private($content, $search_term) {
        return $this->apply_highlighting($content, $search_term);
    }
    
    /**
     * Enhanced search term detection including session
     */
    private function get_search_term() {
        // First try current page sources
        $search_term = $this->get_current_search_term();
        
        if ($search_term) {
            return $search_term;
        }
        
        // Check URL highlight parameter
        if (!empty($_GET['highlight'])) {
            return sanitize_text_field($_GET['highlight']);
        }
        
        // Check session (for users navigating from search)
        if (!session_id()) {
            session_start();
        }
        
        if (!empty($_SESSION['policy_search_term'])) {
            // Only use session term if it's recent (within 30 minutes)
            $time_diff = time() - ($_SESSION['policy_search_time'] ?? 0);
            if ($time_diff < 1800) {
                return $_SESSION['policy_search_term'];
            } else {
                // Clear old search term
                unset($_SESSION['policy_search_term']);
                unset($_SESSION['policy_search_time']);
            }
        }
        
        return '';
    }
    
    /**
     * Conditionally enqueue search assets
     */
    public function conditional_search_assets() {
        // Always enqueue on search pages
        if (is_search()) {
            $this->enqueue_search_assets();
            return;
        }
        
        // Enqueue on policy pages if we have a search term
        if (is_singular('policy')) {
            $search_term = $this->get_search_term();
            if ($search_term) {
                $this->enqueue_search_assets();
            }
        }
    }
    
    /**
     * Enqueue search-related assets using new structure
     */
    public function enqueue_search_assets() {
        BOE_Asset_Manager::enqueue_frontend_js('policy-search-js', 'policy-search.js', ['jquery'], BOE_PLUGIN_VERSION, true);
        
        wp_localize_script('policy-search-js', 'policySearch', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('policy_search_nonce'),
            'searchTerm' => $this->get_search_term()
        ]);
        
        BOE_Asset_Manager::enqueue_frontend_css('policy-search-css', 'search-highlighting.css', [], BOE_PLUGIN_VERSION);
    }
    
    /**
     * Add CSS for search highlighting and policy formatting
     */
    public function add_search_highlighting_script() {
        if (!is_singular('policy')) {
            return;
        }
        
        $search_term = $this->get_search_term();
        
        // Always add policy formatting CSS
        ?>
        <style>
        /* Policy content formatting */
        .entry-content span {
            display: inline-block;
            margin-right: 0.5em;
        }
        
        .entry-content span + span {
            margin-left: 0.25em;
        }
        
        /* Ensure spans in paragraphs have proper spacing */
        .entry-content p span {
            margin-right: 0.5em;
        }
        
        .entry-content p span:last-child {
            margin-right: 0;
        }
        
        <?php if ($search_term) : ?>
        /* Search highlighting */
        .search-highlight {
            background-color: #ffcccb !important;
            color: #000 !important;
            padding: 2px 4px !important;
            border-radius: 3px !important;
            font-weight: 600 !important;
        }
        mark.search-highlight {
            background-color: #ffcccb !important;
            color: #000 !important;
        }
        <?php endif; ?>
        </style>
        <?php
    }
    
    /**
     * Hide author display for policies in search results
     */
    public function hide_policy_author_in_search($author) {
        if (is_search() && get_post_type() === 'policy') {
            return '';
        }
        return $author;
    }
    
    /**
     * Hide date display for policies in search results
     */
    public function hide_policy_date_in_search($date) {
        if (is_search() && get_post_type() === 'policy') {
            return '';
        }
        return $date;
    }
    
    /**
     * Ensure policies have excerpts in search results
     */
    public function ensure_policy_excerpt_in_search($excerpt) {
        if (is_search() && get_post_type() === 'policy' && empty($excerpt)) {
            // Generate excerpt from policy field content
            $policy_content = get_field('policy', get_the_ID());
            if ($policy_content) {
                $text = wp_strip_all_tags($policy_content);
                $excerpt = wp_trim_words($text, 30, '...');
            }
        }
        return $excerpt;
    }
    
    /**
     * Clean up SearchWP output for policies (remove leftover separators)
     */
    public function clean_policy_search_output() {
        if (!is_search()) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Find and clean up leftover separators in search results
            var searchResults = document.querySelectorAll('.searchwp-live-search-result, .search-result, [class*="search"]');
            
            searchResults.forEach(function(result) {
                // Look for standalone "/" or " / " separators
                var textNodes = getTextNodes(result);
                textNodes.forEach(function(node) {
                    if (node.textContent.trim() === '/' || node.textContent.trim() === ' / ') {
                        node.textContent = '';
                    }
                    // Also clean up patterns like " / " that might be left over
                    node.textContent = node.textContent.replace(/^\s*\/\s*$/, '').replace(/\s*\/\s*$/, '');
                });
            });
            
            function getTextNodes(element) {
                var textNodes = [];
                var walker = document.createTreeWalker(
                    element,
                    NodeFilter.SHOW_TEXT,
                    null,
                    false
                );
                var node;
                while (node = walker.nextNode()) {
                    textNodes.push(node);
                }
                return textNodes;
            }
        });
        </script>
        <?php
    }
    
    /**
     * SearchWP integration
     */
    public function searchwp_integration() {
        if (!class_exists('SearchWP')) {
            return;
        }
        
        // Configure SearchWP for policies
        add_filter('searchwp\query\mods', [$this, 'searchwp_policy_mods']);
        add_filter('searchwp\tokens', [$this, 'searchwp_policy_tokens']);
        
        // Add policy-specific search logic
        add_action('searchwp\index\post\content', [$this, 'searchwp_index_policy_content'], 10, 2);
    }
    
    /**
     * SearchWP query modifications for policies
     */
    public function searchwp_policy_mods($mods) {
        // Boost policy matches
        if (is_search() && get_query_var('post_type') === 'policy') {
            // Add custom boosting logic here if needed
        }
        
        return $mods;
    }
    
    /**
     * SearchWP token modifications
     */
    public function searchwp_policy_tokens($tokens) {
        // Add policy-specific token processing
        return $tokens;
    }
    
    /**
     * Index policy content for SearchWP
     */
    public function searchwp_index_policy_content($content, $post) {
        if ($post->post_type !== 'policy') {
            return $content;
        }
        
        // Include ACF policy field content
        $policy_content = get_field('policy', $post->ID);
        if ($policy_content) {
            $content .= ' ' . wp_strip_all_tags($policy_content);
        }
        
        // Include policy metadata
        $metadata_fields = [
            'code-policy',
            'title-policy',
            'adopted-policy',
            'last_revised-policy'
        ];
        
        foreach ($metadata_fields as $field) {
            $field_value = get_field($field, $post->ID);
            if ($field_value) {
                $content .= ' ' . $field_value;
            }
        }
        
        // Include policy section names
        $sections = get_the_terms($post->ID, 'policy-section');
        if ($sections && !is_wp_error($sections)) {
            $section_names = wp_list_pluck($sections, 'name');
            $content .= ' ' . implode(' ', $section_names);
        }
        
        return $content;
    }
    
    /**
     * Policy search shortcode
     */
    public function policy_search_shortcode($atts) {
        $atts = shortcode_atts([
            'placeholder' => __('Search policies...', 'boe-meeting-manager'),
            'button_text' => __('Search', 'boe-meeting-manager'),
            'show_results' => 'true',
            'results_per_page' => 10
        ], $atts);
        
        ob_start();
        
        // Search form
        echo '<div class="policy-search-widget">';
        echo $this->get_search_form($atts);
        
        // Results
        if ($atts['show_results'] === 'true' && get_search_query()) {
            echo '<div class="policy-search-results">';
            echo $this->get_search_results($atts);
            echo '</div>';
        }
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Policy search form shortcode
     */
    public function policy_search_form_shortcode($atts) {
        $atts = shortcode_atts([
            'placeholder' => __('Search policies...', 'boe-meeting-manager'),
            'button_text' => __('Search', 'boe-meeting-manager'),
            'action' => home_url('/'),
            'method' => 'get'
        ], $atts);
        
        return $this->get_search_form($atts);
    }
    
    /**
     * Generate search form HTML
     */
    private function get_search_form($atts) {
        $search_query = get_search_query();
        
        ob_start();
        ?>
        <form class="policy-search-form" action="<?php echo esc_url($atts['action']); ?>" method="<?php echo esc_attr($atts['method']); ?>">
            <div class="search-input-wrapper">
                <input type="search" 
                       name="s" 
                       value="<?php echo esc_attr($search_query); ?>" 
                       placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                       class="policy-search-input" />
                <input type="hidden" name="post_type" value="policy" />
            </div>
            <button type="submit" class="policy-search-button">
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </form>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get search results
     */
    private function get_search_results($atts) {
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        
        $args = [
            'post_type' => 'policy',
            'post_status' => 'publish',
            's' => get_search_query(),
            'posts_per_page' => intval($atts['results_per_page']),
            'paged' => $paged
        ];
        
        $search_query = new WP_Query($args);
        
        ob_start();
        
        if ($search_query->have_posts()) {
            echo '<h3 class="search-results-title">' . sprintf(esc_html__('Search Results for "%s"', 'boe-meeting-manager'), esc_html(get_search_query())) . '</h3>';
            echo '<div class="search-results-count">' . sprintf(esc_html__('Found %d result(s)', 'boe-meeting-manager'), $search_query->found_posts) . '</div>';
            
            echo '<div class="search-results-list">';
            while ($search_query->have_posts()) {
                $search_query->the_post();
                $this->render_search_result_item();
            }
            echo '</div>';
            
            // Pagination
            if ($search_query->max_num_pages > 1) {
                echo '<div class="search-pagination">';
                echo paginate_links([
                    'total' => $search_query->max_num_pages,
                    'current' => $paged,
                    'format' => '?paged=%#%',
                    'add_args' => ['s' => get_search_query(), 'post_type' => 'policy']
                ]);
                echo '</div>';
            }
            
        } else {
            echo '<div class="no-search-results">';
            echo '<p>' . esc_html__('No policies found matching your search.', 'boe-meeting-manager') . '</p>';
            echo '<p>' . sprintf(
                esc_html__('Try different keywords or browse our %s.', 'boe-meeting-manager'),
                '<a href="' . esc_url(get_post_type_archive_link('policy')) . '">' . esc_html__('policy sections', 'boe-meeting-manager') . '</a>'
            ) . '</p>';
            echo '</div>';
        }
        
        wp_reset_postdata();
        
        return ob_get_clean();
    }
    
    /**
     * Render individual search result
     */
    private function render_search_result_item() {
        $code = get_field('code-policy');
        $section_terms = get_the_terms(get_the_ID(), 'policy-section');
        $sections = $section_terms && !is_wp_error($section_terms) ? wp_list_pluck($section_terms, 'name') : [];
        
        echo '<article class="search-result-item">';
        echo '<div class="search-result-header">';
        
        if ($code) {
            echo '<span class="policy-code">' . esc_html($code) . '</span>';
        }
        
        echo '<h4 class="search-result-title">';
        echo '<a href="' . esc_url(add_query_arg('highlight', get_search_query(), get_permalink())) . '">';
        echo get_the_title();
        echo '</a>';
        echo '</h4>';
        echo '</div>';
        
        echo '<div class="search-result-meta">';
        if (!empty($sections)) {
            echo '<span class="policy-sections">' . esc_html__('Section:', 'boe-meeting-manager') . ' ' . esc_html(implode(', ', $sections)) . '</span>';
        }
        
        $adopted = get_field('adopted-policy');
        if ($adopted) {
            echo '<span class="policy-adopted">' . esc_html__('Adopted:', 'boe-meeting-manager') . ' ' . esc_html($adopted) . '</span>';
        }
        echo '</div>';
        
        echo '<div class="search-result-excerpt">';
        echo $this->get_highlighted_excerpt();
        echo '</div>';
        
        echo '</article>';
    }
    
    /**
     * Get highlighted excerpt from policy content
     */
    private function get_highlighted_excerpt($length = 200) {
        $search_term = get_search_query();
        $policy_content = get_field('policy') ?: get_the_content();
        
        // Strip HTML and get plain text
        $text = wp_strip_all_tags($policy_content);
        
        if (empty($search_term)) {
            return wp_trim_words($text, 30, '...');
        }
        
        // Find the search term position
        $pos = stripos($text, $search_term);
        
        if ($pos !== false) {
            // Extract text around the search term
            $start = max(0, $pos - $length / 2);
            $excerpt = substr($text, $start, $length);
            
            // Add ellipsis if needed
            if ($start > 0) $excerpt = '...' . $excerpt;
            if (strlen($text) > $start + $length) $excerpt .= '...';
            
            // Highlight the search term
            $excerpt = preg_replace(
                '/(' . preg_quote($search_term, '/') . ')/i',
                '<mark class="search-highlight">$1</mark>',
                $excerpt
            );
            
            return $excerpt;
        }
        
        return wp_trim_words($text, 30, '...');
    }
    
    /**
     * Search template loader
     */
    public function search_template_loader($template) {
        if (is_search() && get_query_var('post_type') === 'policy') {
            // Check for theme template first
            $theme_templates = [
                'search-policy.php',
                'search.php'
            ];
            
            $theme_template = locate_template($theme_templates);
            if ($theme_template) {
                return $theme_template;
            }
            
            // Use plugin template
            $plugin_template = plugin_dir_path(__FILE__) . '../templates/search-policy.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Modify search query for policies
     */
    public function modify_search_query($query) {
        if (!$query->is_search() || is_admin()) {
            return;
        }
        
        // If searching for policies specifically
        if ($query->get('post_type') === 'policy') {
            // Ensure we're only searching published policies
            $query->set('post_status', 'publish');
            
            // Add meta query to search in ACF fields if SearchWP isn't active
            if (!class_exists('SearchWP')) {
                $meta_query = $query->get('meta_query') ?: [];
                $search_term = sanitize_text_field($query->get('s')); // ✅ FIXED: Sanitize search term
                
                if ($search_term) {
                    $meta_query['relation'] = 'OR';
                    
                    // ✅ FIXED: Use proper meta query structure to prevent injection
                    $safe_meta_queries = [
                        [
                            'key' => 'policy',
                            'value' => $search_term,
                            'compare' => 'LIKE'
                        ],
                        [
                            'key' => 'code-policy',
                            'value' => $search_term,
                            'compare' => 'LIKE'
                        ],
                        [
                            'key' => 'title-policy',
                            'value' => $search_term,
                            'compare' => 'LIKE'
                        ]
                    ];
                    
                    $meta_query = array_merge($meta_query, $safe_meta_queries);
                    $query->set('meta_query', $meta_query);
                }
            }
        }
    }
    
    /**
     * Admin notices for SearchWP
     */
    public function searchwp_admin_notices() {
        if (!class_exists('SearchWP') && current_user_can('manage_options')) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['policy_page_policy-display', 'edit-policy'])) {
                echo '<div class="notice notice-info">';
                echo '<p><strong>' . esc_html__('Enhanced Policy Search:', 'boe-meeting-manager') . '</strong> ' . 
                     sprintf(
                         esc_html__('For advanced search functionality, consider installing the %s. It will automatically enhance policy search with better relevance and custom field searching.', 'boe-meeting-manager'),
                         '<a href="https://searchwp.com/" target="_blank">' . esc_html__('SearchWP plugin', 'boe-meeting-manager') . '</a>'
                     ) . '</p>';
                echo '</div>';
            }
        }
    }
}

// Initialize Policy Search
new PolicySearch();