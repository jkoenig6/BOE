<?php
/**
 * Policy Accordion Shortcode System
 * File: includes/policy-accordion.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PolicyAccordion {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Register shortcodes
        add_shortcode('policy_accordion', [$this, 'policy_accordion_shortcode']);
        add_shortcode('policy_sections', [$this, 'policy_sections_shortcode']);
        add_shortcode('policy_list', [$this, 'policy_list_shortcode']);
        add_shortcode('policy_debug', [$this, 'policy_debug_shortcode']);
        
        // Enqueue assets when shortcode is used
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        
        // Add page template support
        add_filter('template_include', [$this, 'policy_template_loader']);
        
        // Add admin interface for template creation
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }
    
    /**
     * Main policy accordion shortcode
     */
    public function policy_accordion_shortcode($atts) {
        $atts = shortcode_atts([
            'sections' => '', // Comma-separated section slugs
            'show_controls' => 'true',
            'order' => 'ASC',
            'orderby' => 'title'
        ], $atts);
        
        // Enqueue assets
        $this->enqueue_accordion_assets();
        
        ob_start();
        
        // Debug: Check if we have any policies at all
        $all_policies = get_posts([
            'post_type' => 'policy',
            'post_status' => 'publish',
            'numberposts' => 1
        ]);
        
        if (empty($all_policies)) {
            echo '<div class="no-policies-notice">';
            echo '<p><strong>' . esc_html__('No published policies found.', 'boe-meeting-manager') . '</strong></p>';
            echo '<p>' . esc_html__('Please create some policies first or check if they are published.', 'boe-meeting-manager') . '</p>';
            echo '</div>';
            return ob_get_clean();
        }
        
        // Accordion controls
        if ($atts['show_controls'] === 'true') {
            echo '<div class="accordion-controls">';
            echo '<button id="expand-all" class="accordion-toggle-btn">' . esc_html__('Expand All', 'boe-meeting-manager') . '</button>';
            echo '<button id="collapse-all" class="accordion-toggle-btn">' . esc_html__('Collapse All', 'boe-meeting-manager') . '</button>';
            echo '</div>';
        }
        
        echo '<div class="accordion-wrapper">';
        
        // Get sections to display
        $sections_args = [
            'taxonomy' => 'policy-section',
            'hide_empty' => false, // Show even empty sections for debugging
            'orderby' => 'name',
            'order' => 'ASC'
        ];
        
        if (!empty($atts['sections'])) {
            $section_slugs = array_map('trim', explode(',', $atts['sections']));
            $sections_args['slug'] = $section_slugs;
        }
        
        $sections = get_terms($sections_args);
        
        if (!empty($sections) && !is_wp_error($sections)) {
            foreach ($sections as $index => $section) {
                $this->render_section_accordion($section, $atts, $index);
            }
        } else {
            echo '<div class="no-sections-notice">';
            echo '<p><strong>' . esc_html__('No policy sections found.', 'boe-meeting-manager') . '</strong></p>';
            echo '<p>' . sprintf(
                esc_html__('Please create policy sections first: %s', 'boe-meeting-manager'),
                '<a href="' . esc_url(admin_url('edit-tags.php?taxonomy=policy-section&post_type=policy')) . '">' . 
                esc_html__('Manage Policy Sections', 'boe-meeting-manager') . '</a>'
            ) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Policy sections shortcode - just lists sections
     */
    public function policy_sections_shortcode($atts) {
        $atts = shortcode_atts([
            'format' => 'list', // list, grid, or dropdown
            'show_count' => 'false'
        ], $atts);
        
        $sections = get_terms([
            'taxonomy' => 'policy-section',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);
        
        if (empty($sections) || is_wp_error($sections)) {
            return '<p>' . esc_html__('No policy sections found.', 'boe-meeting-manager') . '</p>';
        }
        
        ob_start();
        
        switch ($atts['format']) {
            case 'grid':
                echo '<div class="policy-sections-grid">';
                foreach ($sections as $section) {
                    $count = $atts['show_count'] === 'true' ? ' (' . $section->count . ')' : '';
                    echo '<div class="policy-section-item">';
                    echo '<a href="' . esc_url(get_term_link($section)) . '">' . esc_html($section->name) . esc_html($count) . '</a>';
                    echo '</div>';
                }
                echo '</div>';
                break;
                
            case 'dropdown':
                echo '<select class="policy-sections-dropdown" onchange="if(this.value) window.location.href=this.value">';
                echo '<option value="">' . esc_html__('Select a Policy Section...', 'boe-meeting-manager') . '</option>';
                foreach ($sections as $section) {
                    $count = $atts['show_count'] === 'true' ? ' (' . $section->count . ')' : '';
                    echo '<option value="' . esc_url(get_term_link($section)) . '">' . esc_html($section->name) . esc_html($count) . '</option>';
                }
                echo '</select>';
                break;
                
            default: // list
                echo '<ul class="policy-sections-list">';
                foreach ($sections as $section) {
                    $count = $atts['show_count'] === 'true' ? ' <span class="count">(' . $section->count . ')</span>' : '';
                    echo '<li><a href="' . esc_url(get_term_link($section)) . '">' . esc_html($section->name) . wp_kses($count, ['span' => ['class' => []]]) . '</a></li>';
                }
                echo '</ul>';
                break;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Policy list shortcode - lists policies from specific sections
     */
    public function policy_list_shortcode($atts) {
        $atts = shortcode_atts([
            'sections' => '',
            'orderby' => 'title',
            'order' => 'ASC',
            'limit' => -1,
            'show_section' => 'false',
            'show_code' => 'true',
            'format' => 'list' // list, table, or cards
        ], $atts);
        
        $args = [
            'post_type' => 'policy',
            'posts_per_page' => intval($atts['limit']),
            'orderby' => sanitize_text_field($atts['orderby']),
            'order' => sanitize_text_field($atts['order']),
            'post_status' => 'publish'
        ];
        
        if (!empty($atts['sections'])) {
            $section_slugs = array_map('trim', explode(',', $atts['sections']));
            $args['tax_query'] = [
                [
                    'taxonomy' => 'policy-section',
                    'field' => 'slug',
                    'terms' => array_map('sanitize_text_field', $section_slugs)
                ]
            ];
        }
        
        $policies = get_posts($args);
        
        if (empty($policies)) {
            return '<p>' . esc_html__('No policies found.', 'boe-meeting-manager') . '</p>';
        }
        
        ob_start();
        
        switch ($atts['format']) {
            case 'table':
                $this->render_policies_table($policies, $atts);
                break;
                
            case 'cards':
                $this->render_policies_cards($policies, $atts);
                break;
                
            default: // list
                $this->render_policies_list($policies, $atts);
                break;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Debug shortcode to check policy setup
     */
    public function policy_debug_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>' . esc_html__('Debug info only available to administrators.', 'boe-meeting-manager') . '</p>';
        }
        
        ob_start();
        
        echo '<div style="background: #f0f0f0; padding: 15px; border: 1px solid #ccc; margin: 10px 0;">';
        echo '<h3>' . esc_html__('Policy Debug Information', 'boe-meeting-manager') . '</h3>';
        
        // Check policies
        $policies = get_posts([
            'post_type' => 'policy',
            'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => -1
        ]);
        
        echo '<p><strong>' . esc_html__('Total Policies:', 'boe-meeting-manager') . '</strong> ' . count($policies) . '</p>';
        
        $published_policies = get_posts([
            'post_type' => 'policy',
            'post_status' => 'publish',
            'numberposts' => -1
        ]);
        
        echo '<p><strong>' . esc_html__('Published Policies:', 'boe-meeting-manager') . '</strong> ' . count($published_policies) . '</p>';
        
        // Check sections
        $sections = get_terms([
            'taxonomy' => 'policy-section',
            'hide_empty' => false
        ]);
        
        echo '<p><strong>' . esc_html__('Policy Sections:', 'boe-meeting-manager') . '</strong> ' . count($sections) . '</p>';
        
        if (!empty($sections)) {
            echo '<ul>';
            foreach ($sections as $section) {
                $section_policies = get_posts([
                    'post_type' => 'policy',
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'tax_query' => [
                        [
                            'taxonomy' => 'policy-section',
                            'field' => 'term_id',
                            'terms' => $section->term_id
                        ]
                    ]
                ]);
                
                echo '<li>' . esc_html($section->name) . ' (' . count($section_policies) . ' ' . esc_html__('policies', 'boe-meeting-manager') . ')</li>';
            }
            echo '</ul>';
        }
        
        // Sample published policies
        if (!empty($published_policies)) {
            echo '<p><strong>' . esc_html__('Sample Published Policies:', 'boe-meeting-manager') . '</strong></p>';
            echo '<ul>';
            foreach (array_slice($published_policies, 0, 5) as $policy) {
                $sections = get_the_terms($policy->ID, 'policy-section');
                $section_names = $sections && !is_wp_error($sections) ? wp_list_pluck($sections, 'name') : [__('No section', 'boe-meeting-manager')];
                echo '<li>' . esc_html($policy->post_title) . ' (' . esc_html__('Section:', 'boe-meeting-manager') . ' ' . esc_html(implode(', ', $section_names)) . ')</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Render section accordion
     */
    private function render_section_accordion($section, $atts, $index) {
        $args = [
            'post_type' => 'policy',
            'posts_per_page' => -1,
            'orderby' => sanitize_text_field($atts['orderby']),
            'order' => sanitize_text_field($atts['order']),
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'policy-section',
                    'field' => 'slug',
                    'terms' => $section->slug,
                ],
            ],
        ];

        $loop = new WP_Query($args);
        
        if ($loop->have_posts()) {
            $section_id = 'accordion-' . esc_attr($section->slug);
            ?>
            <div class="accordion-item">
                <button class="accordion-title" aria-expanded="false" aria-controls="<?php echo $section_id; ?>">
                    <?php echo esc_html($section->name); ?>
                    <span class="policy-count">(<?php echo $loop->found_posts; ?>)</span>
                </button>
                <div id="<?php echo $section_id; ?>" class="accordion-content" style="display: none;">
                    <ul class="post-list">
                        <?php while ($loop->have_posts()) : $loop->the_post(); ?>
                            <li>
                                <a href="<?php the_permalink(); ?>" target="_blank">
                                    <?php the_title(); ?>
                                </a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
            <?php
        } else {
            // Show empty sections for debugging
            ?>
            <div class="accordion-item">
                <button class="accordion-title" aria-expanded="false">
                    <?php echo esc_html($section->name); ?>
                    <span class="policy-count">(0)</span>
                </button>
                <div class="accordion-content" style="display: none;">
                    <p><em><?php esc_html_e('No published policies in this section yet.', 'boe-meeting-manager'); ?></em></p>
                </div>
            </div>
            <?php
        }
        
        wp_reset_postdata();
    }
    
    /**
     * Render policies as table
     */
    private function render_policies_table($policies, $atts) {
        echo '<table class="policies-table">';
        echo '<thead><tr>';
        if ($atts['show_code'] === 'true') echo '<th>' . esc_html__('Code', 'boe-meeting-manager') . '</th>';
        echo '<th>' . esc_html__('Title', 'boe-meeting-manager') . '</th>';
        if ($atts['show_section'] === 'true') echo '<th>' . esc_html__('Section', 'boe-meeting-manager') . '</th>';
        echo '<th>' . esc_html__('Adopted', 'boe-meeting-manager') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($policies as $policy) {
            echo '<tr>';
            
            if ($atts['show_code'] === 'true') {
                $code = get_field('code-policy', $policy->ID);
                echo '<td>' . esc_html($code) . '</td>';
            }
            
            echo '<td><a href="' . esc_url(get_permalink($policy->ID)) . '">' . esc_html($policy->post_title) . '</a></td>';
            
            if ($atts['show_section'] === 'true') {
                $sections = get_the_terms($policy->ID, 'policy-section');
                $section_names = $sections && !is_wp_error($sections) ? wp_list_pluck($sections, 'name') : [];
                echo '<td>' . esc_html(implode(', ', $section_names)) . '</td>';
            }
            
            $adopted = get_field('adopted-policy', $policy->ID);
            echo '<td>' . esc_html($adopted) . '</td>';
            
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Render policies as cards
     */
    private function render_policies_cards($policies, $atts) {
        echo '<div class="policies-cards">';
        
        foreach ($policies as $policy) {
            $code = get_field('code-policy', $policy->ID);
            $adopted = get_field('adopted-policy', $policy->ID);
            $sections = get_the_terms($policy->ID, 'policy-section');
            
            echo '<div class="policy-card">';
            echo '<div class="policy-card-header">';
            if ($atts['show_code'] === 'true' && $code) {
                echo '<span class="policy-code">' . esc_html($code) . '</span>';
            }
            echo '<h3><a href="' . esc_url(get_permalink($policy->ID)) . '">' . esc_html($policy->post_title) . '</a></h3>';
            echo '</div>';
            
            echo '<div class="policy-card-meta">';
            if ($atts['show_section'] === 'true' && $sections && !is_wp_error($sections)) {
                $section_names = wp_list_pluck($sections, 'name');
                echo '<div class="policy-section">' . esc_html__('Section:', 'boe-meeting-manager') . ' ' . esc_html(implode(', ', $section_names)) . '</div>';
            }
            if ($adopted) {
                echo '<div class="policy-adopted">' . esc_html__('Adopted:', 'boe-meeting-manager') . ' ' . esc_html($adopted) . '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render policies as list
     */
    private function render_policies_list($policies, $atts) {
        echo '<ul class="policies-list">';
        
        foreach ($policies as $policy) {
            $code = get_field('code-policy', $policy->ID);
            
            echo '<li>';
            echo '<a href="' . esc_url(get_permalink($policy->ID)) . '">';
            
            if ($atts['show_code'] === 'true' && $code) {
                echo '<span class="policy-code">' . esc_html($code) . '</span> - ';
            }
            
            echo esc_html($policy->post_title);
            echo '</a>';
            
            if ($atts['show_section'] === 'true') {
                $sections = get_the_terms($policy->ID, 'policy-section');
                if ($sections && !is_wp_error($sections)) {
                    $section_names = wp_list_pluck($sections, 'name');
                    echo ' <span class="policy-section-info">(' . esc_html(implode(', ', $section_names)) . ')</span>';
                }
            }
            
            echo '</li>';
        }
        
        echo '</ul>';
    }
    
    /**
     * Enqueue accordion assets using new structure
     */
    public function enqueue_accordion_assets() {
        BOE_Asset_Manager::enqueue_admin_js('policy-accordion-js', 'policy-accordion.js', ['jquery'], BOE_PLUGIN_VERSION, true);
        BOE_Asset_Manager::enqueue_admin_css('policy-accordion-css', 'policy-accordion.css', [], BOE_PLUGIN_VERSION);
    }
    
    /**
     * Maybe enqueue assets based on content
     */
    public function maybe_enqueue_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'policy_accordion') ||
            has_shortcode($post->post_content, 'policy_sections') ||
            has_shortcode($post->post_content, 'policy_list') ||
            is_page_template('page-policy-accordion-basic.php')
        )) {
            $this->enqueue_accordion_assets();
        }
    }
    
    /**
     * Template loader for policy accordion pages
     */
    public function policy_template_loader($template) {
        if (is_page() && is_page_template('page-policy-accordion-basic.php')) {
            // Check if theme has the template
            $theme_template = locate_template(['page-policy-accordion-basic.php']);
            
            if ($theme_template) {
                return $theme_template;
            }
            
            // Use plugin template
            $plugin_template = plugin_dir_path(__FILE__) . '../templates/page-policy-accordion-basic.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Add admin menu for template management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'policy-builder',
            __('Policy Display', 'boe-meeting-manager'),
            __('Policy Display', 'boe-meeting-manager'),
            'edit_others_posts',
            'policy-display',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Admin page for policy display management
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Policy Display Management', 'boe-meeting-manager'); ?></h1>
            
            <div class="policy-display-tabs">
                <h2><?php esc_html_e('Available Shortcodes', 'boe-meeting-manager'); ?></h2>
                
                <div class="shortcode-docs">
                    <h3><?php esc_html_e('Policy Accordion', 'boe-meeting-manager'); ?></h3>
                    <p><?php esc_html_e('Display policies in collapsible sections:', 'boe-meeting-manager'); ?></p>
                    <code>[policy_accordion]</code>
                    <p><?php esc_html_e('Options:', 'boe-meeting-manager'); ?></p>
                    <ul>
                        <li><code>sections</code> - <?php esc_html_e('Comma-separated section slugs (default: all)', 'boe-meeting-manager'); ?></li>
                        <li><code>show_controls</code> - <?php esc_html_e('Show expand/collapse buttons (default: true)', 'boe-meeting-manager'); ?></li>
                        <li><code>order</code> - <?php esc_html_e('ASC or DESC (default: ASC)', 'boe-meeting-manager'); ?></li>
                        <li><code>orderby</code> - <?php esc_html_e('title, date, menu_order (default: title)', 'boe-meeting-manager'); ?></li>
                    </ul>
                    <p><?php esc_html_e('Example:', 'boe-meeting-manager'); ?> <code>[policy_accordion sections="administration,board" show_controls="false"]</code></p>
                    
                    <h3><?php esc_html_e('Policy Sections', 'boe-meeting-manager'); ?></h3>
                    <p><?php esc_html_e('Display list of policy sections:', 'boe-meeting-manager'); ?></p>
                    <code>[policy_sections]</code>
                    <p><?php esc_html_e('Options:', 'boe-meeting-manager'); ?></p>
                    <ul>
                        <li><code>format</code> - <?php esc_html_e('list, grid, or dropdown (default: list)', 'boe-meeting-manager'); ?></li>
                        <li><code>show_count</code> - <?php esc_html_e('Show policy count (default: false)', 'boe-meeting-manager'); ?></li>
                    </ul>
                    <p><?php esc_html_e('Example:', 'boe-meeting-manager'); ?> <code>[policy_sections format="grid" show_count="true"]</code></p>
                    
                    <h3><?php esc_html_e('Policy List', 'boe-meeting-manager'); ?></h3>
                    <p><?php esc_html_e('Display filtered list of policies:', 'boe-meeting-manager'); ?></p>
                    <code>[policy_list]</code>
                    <p><?php esc_html_e('Options:', 'boe-meeting-manager'); ?></p>
                    <ul>
                        <li><code>sections</code> - <?php esc_html_e('Comma-separated section slugs', 'boe-meeting-manager'); ?></li>
                        <li><code>format</code> - <?php esc_html_e('list, table, or cards (default: list)', 'boe-meeting-manager'); ?></li>
                        <li><code>show_code</code> - <?php esc_html_e('Show policy codes (default: true)', 'boe-meeting-manager'); ?></li>
                        <li><code>show_section</code> - <?php esc_html_e('Show section names (default: false)', 'boe-meeting-manager'); ?></li>
                        <li><code>limit</code> - <?php esc_html_e('Number of policies to show (default: -1 for all)', 'boe-meeting-manager'); ?></li>
                    </ul>
                    <p><?php esc_html_e('Example:', 'boe-meeting-manager'); ?> <code>[policy_list sections="administration" format="table" show_section="true"]</code></p>
                </div>
                
                <h2><?php esc_html_e('Template Status', 'boe-meeting-manager'); ?></h2>
                <div class="template-status">
                    <?php 
                    $theme_has_template = locate_template(['page-policy-accordion-basic.php']);
                    $plugin_has_template = file_exists(plugin_dir_path(__FILE__) . '../templates/page-policy-accordion-basic.php');
                    ?>
                    
                    <p><strong><?php esc_html_e('Policy Accordion Template:', 'boe-meeting-manager'); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e('Theme template:', 'boe-meeting-manager'); ?> <?php echo $theme_has_template ? '✅ ' . esc_html__('Found', 'boe-meeting-manager') : '❌ ' . esc_html__('Not found', 'boe-meeting-manager'); ?></li>
                        <li><?php esc_html_e('Plugin template:', 'boe-meeting-manager'); ?> <?php echo $plugin_has_template ? '✅ ' . esc_html__('Available', 'boe-meeting-manager') : '❌ ' . esc_html__('Not available', 'boe-meeting-manager'); ?></li>
                    </ul>
                    
                    <?php if (!$theme_has_template && $plugin_has_template): ?>
                        <p class="description"><?php esc_html_e('The plugin will provide the template since your theme doesn\'t have one.', 'boe-meeting-manager'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize Policy Accordion
new PolicyAccordion();