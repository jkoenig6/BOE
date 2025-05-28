<?php
/**
 * Policy Builder Backend
 * File: includes/policy-builder.php
 */

class PolicyBuilder {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_save_policy', [$this, 'save_policy']);
        add_action('admin_menu', [$this, 'remove_policies_menu'], 999);
    }
    
    public function remove_policies_menu() {
        // Remove the default Policies menu since we have Policy Builder
        remove_menu_page('edit.php?post_type=policy');
    }
    
    public function add_admin_menu() {
        // Main Policy Builder menu
        add_menu_page(
            'Policy Builder',
            'Policy Builder',
            'edit_others_posts',
            'policy-builder',
            [$this, 'policy_builder_page'],
            'dashicons-admin-page',
            27
        );
        
        // Submenu items
        add_submenu_page(
            'policy-builder',
            'New Policy',
            'New Policy',
            'edit_others_posts',
            'policy-builder-new',
            [$this, 'new_policy_page']
        );
        
        add_submenu_page(
            'policy-builder',
            'All Policies',
            'All Policies',
            'edit_others_posts',
            'edit.php?post_type=policy'
        );
        
        add_submenu_page(
            'policy-builder',
            'Policy Sections',
            'Policy Sections',
            'edit_others_posts',
            'edit-tags.php?taxonomy=policy-section&post_type=policy'
        );
    }
    
    public function policy_builder_page() {
        echo '<div class="wrap"><h1>Policy Builder</h1>';
        echo '<p>Manage board policies efficiently:</p>';
        echo '<div class="policy-type-buttons">';
        echo '<a href="' . admin_url('admin.php?page=policy-builder-new') . '" class="button button-primary button-hero">New Policy</a>';
        echo '<a href="' . admin_url('edit.php?post_type=policy') . '" class="button button-secondary button-hero">All Policies</a>';
        echo '<a href="' . admin_url('edit-tags.php?taxonomy=policy-section&post_type=policy') . '" class="button button-secondary button-hero">Policy Sections</a>';
        echo '</div></div>';
    }
    
    public function new_policy_page() {
        // Check if we have a policy_id parameter
        $policy_id = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0;
        $this->render_policy_builder($policy_id);
    }
    
    private function render_policy_builder($post_id = 0) {
        $post = $post_id ? get_post($post_id) : null;
        
        // If no post ID, create a new draft
        if (!$post) {
            $post_id = wp_insert_post([
                'post_title' => 'Draft Policy - ' . date('M j, Y'),
                'post_type' => 'policy',
                'post_status' => 'draft'
            ]);
            $post = get_post($post_id);
            
            // Redirect to include the new post ID in URL
            $redirect_url = admin_url('admin.php?page=policy-builder-new&policy_id=' . $post_id);
            echo '<script>window.location.href = "' . $redirect_url . '";</script>';
            return;
        }
        
        // Get policy data
        $code = get_field('code-policy', $post_id);
        $title_policy = get_field('title-policy', $post_id);
        $adopted = get_field('adopted-policy', $post_id);
        $last_revised = get_field('last_revised-policy', $post_id);
        $policy_content = get_field('policy', $post_id);
        
        // Get taxonomy terms
        $policy_sections = get_terms(['taxonomy' => 'policy-section', 'hide_empty' => false]);
        $post_sections = wp_get_post_terms($post_id, 'policy-section', ['fields' => 'ids']);
        
        ?>
        <div class="wrap policy-builder-wrap">
            <h1>Policy Builder</h1>
            
            <div class="policy-builder-layout">
                <!-- Left Column: Policy Details -->
                <div class="policy-details-column">
                    <div class="policy-header">
                        <h2>Policy Details</h2>
                        <div class="policy-status-badge status-<?php echo esc_attr($post->post_status); ?>">
                            <?php echo $this->get_status_label($post->post_status); ?>
                        </div>
                    </div>
                    
                    <!-- Policy Title -->
                    <div class="policy-field-group">
                        <label for="policy-title">Policy Title</label>
                        <input type="text" id="policy-title" value="<?php echo esc_attr($post->post_title); ?>" />
                    </div>
                    
                    <!-- URL Slug -->
                    <div class="policy-field-group">
                        <label for="policy-slug">URL Slug</label>
                        <div class="permalink-edit">
                            <span class="permalink-base"><?php echo home_url('/policy/'); ?></span>
                            <input type="text" id="policy-slug" value="<?php echo esc_attr($post->post_name); ?>" style="display: inline-block; width: auto; min-width: 200px;" />
                            <span class="permalink-suffix">/</span>
                        </div>
                        <p class="description">The URL slug for this policy. Manually edit to match your policy code (e.g., "ac" for /policy/ac/)</p>
                    </div>
                    
                    <!-- Code -->
                    <div class="policy-field-group">
                        <label for="policy-code">Code *</label>
                        <input type="text" id="policy-code" value="<?php echo esc_attr($code); ?>" required />
                    </div>
                    
                    <!-- Title (ACF Field) -->
                    <div class="policy-field-group">
                        <label for="policy-title-field">Title *</label>
                        <input type="text" id="policy-title-field" value="<?php echo esc_attr($title_policy); ?>" required />
                    </div>
                    
                    <!-- Policy Section -->
                    <div class="policy-field-group">
                        <label for="policy-sections">Policy Section</label>
                        <select id="policy-sections" multiple>
                            <?php foreach ($policy_sections as $section): ?>
                                <option value="<?php echo $section->term_id; ?>" <?php echo in_array($section->term_id, $post_sections) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($section->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Dates -->
                    <div class="policy-dates-row">
                        <div class="policy-field-group">
                            <label for="adopted-date">Adopted Date</label>
                            <input type="date" id="adopted-date" value="<?php echo $adopted ? date('Y-m-d', strtotime($adopted)) : ''; ?>" />
                        </div>
                        <div class="policy-field-group">
                            <label for="last-revised-date">Last Revised</label>
                            <input type="date" id="last-revised-date" value="<?php echo $last_revised ? date('Y-m-d', strtotime($last_revised)) : ''; ?>" />
                        </div>
                    </div>
                    
                    <!-- Policy Content -->
                    <div class="policy-field-group">
                        <label for="policy-content">Policy Content</label>
                        <?php
                        wp_editor($policy_content, 'policy-content', [
                            'textarea_name' => 'policy-content',
                            'media_buttons' => true,
                            'textarea_rows' => 15,
                            'teeny' => false,
                            'tinymce' => [
                                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
                                'toolbar2' => 'styleselect,fontsize,forecolor,backcolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help'
                            ]
                        ]);
                        ?>
                    </div>
                </div>
                
                <!-- Right Column: Actions & Info -->
                <div class="policy-actions-column">
                    <!-- Status & Actions Box -->
                    <div class="policy-actions-box">
                        <div class="actions-header">
                            <h3>Actions</h3>
                        </div>
                        <div class="actions-content">
                            <div class="current-status">
                                <label>Current Status:</label>
                                <span class="status-display status-<?php echo esc_attr($post->post_status); ?>">
                                    <?php echo $this->get_status_label($post->post_status); ?>
                                </span>
                            </div>
                            
                            <div class="action-buttons">
                                <?php $this->render_action_buttons($post->post_status); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Meta Info -->
                    <div class="policy-meta-box">
                        <h3>Information</h3>
                        <div class="meta-info">
                            <div class="meta-row">
                                <label>Created:</label>
                                <span><?php echo date('M j, Y g:i a', strtotime($post->post_date)); ?></span>
                            </div>
                            <div class="meta-row">
                                <label>Modified:</label>
                                <span><?php echo date('M j, Y g:i a', strtotime($post->post_modified)); ?></span>
                            </div>
                            <div class="meta-row">
                                <label>Author:</label>
                                <span><?php echo get_the_author_meta('display_name', $post->post_author); ?></span>
                            </div>
                            <?php if ($code): ?>
                            <div class="meta-row">
                                <label>Code:</label>
                                <span><?php echo esc_html($code); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="policy-links-box">
                        <h3>Quick Links</h3>
                        <div class="quick-links">
                            <a href="<?php echo get_permalink($post_id); ?>" target="_blank" class="button">View Policy</a>
                            <a href="<?php echo admin_url('edit.php?post_type=policy'); ?>" class="button">All Policies</a>
                            <a href="<?php echo admin_url('edit-tags.php?taxonomy=policy-section&post_type=policy'); ?>" class="button">Manage Sections</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Hidden inputs -->
            <input type="hidden" id="policy-id" value="<?php echo $post_id; ?>" />
        </div>
        <?php
    }
    
    private function render_action_buttons($status) {
        switch ($status) {
            case 'draft':
                echo '<button type="button" class="button button-large" id="save-draft">Save Draft</button>';
                echo '<button type="button" class="button button-primary button-large" id="publish-policy">Publish Policy</button>';
                break;
                
            case 'archived':
                echo '<button type="button" class="button button-large" id="save-archived">Save Changes</button>';
                echo '<button type="button" class="button button-secondary button-large" id="restore-policy">Restore Policy</button>';
                break;
                
            case 'publish':
                echo '<button type="button" class="button button-large" id="save-published">Update Published</button>';
                echo '<button type="button" class="button button-secondary button-large" id="archive-policy">Archive Policy</button>';
                break;
                
            default:
                echo '<button type="button" class="button button-large" id="save-draft">Save Draft</button>';
                echo '<button type="button" class="button button-primary button-large" id="publish-policy">Publish Policy</button>';
                break;
        }
    }
    
    private function get_status_label($status) {
        $labels = [
            'draft' => 'Draft',
            'publish' => 'Published',
            'archived' => 'Archived/Rescinded',
            'private' => 'Private'
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
    
    public function save_policy() {
        check_ajax_referer('policy_builder_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $policy_id = intval($_POST['policy_id']);
        $title = sanitize_text_field($_POST['title']);
        $slug = sanitize_text_field($_POST['slug']);
        $status = sanitize_text_field($_POST['status']);
        
        // Validate and sanitize slug
        if (!empty($slug)) {
            $slug = sanitize_title($slug);
            // Check if slug is unique
            $existing_post = get_page_by_path($slug, OBJECT, 'policy');
            if ($existing_post && $existing_post->ID !== $policy_id) {
                // Slug exists, make it unique
                $slug = wp_unique_post_slug($slug, $policy_id, $status, 'policy', 0);
            }
        } else {
            // Generate slug from title if empty
            $slug = sanitize_title($title);
        }
        
        // Update post
        $result = wp_update_post([
            'ID' => $policy_id,
            'post_title' => $title,
            'post_name' => $slug,
            'post_status' => $status
        ], true); // Return WP_Error on failure
        
        // Check for errors
        if (is_wp_error($result)) {
            wp_send_json_error('Error updating post: ' . $result->get_error_message());
        }
        
        // Update ACF fields
        update_field('code-policy', sanitize_text_field($_POST['code']), $policy_id);
        update_field('title-policy', sanitize_text_field($_POST['title_field']), $policy_id);
        update_field('adopted-policy', sanitize_text_field($_POST['adopted_date']), $policy_id);
        update_field('last_revised-policy', sanitize_text_field($_POST['last_revised_date']), $policy_id);
        update_field('policy', wp_kses_post($_POST['policy_content']), $policy_id);
        
        // Update taxonomy
        $sections = $_POST['policy_sections'] ?? [];
        wp_set_post_terms($policy_id, array_map('intval', $sections), 'policy-section');
        
        wp_send_json_success(['message' => 'Policy saved successfully', 'policy_id' => $policy_id]);
    }
}

new PolicyBuilder();