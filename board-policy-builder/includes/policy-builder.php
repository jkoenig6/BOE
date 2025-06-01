<?php
/**
 * Fixed Policy Builder Backend - JavaScript-based TinyMCE Integration with Word Import
 * File: includes/policy-builder.php
 */

class PolicyBuilder {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_save_policy', [$this, 'save_policy']);
        add_action('admin_menu', [$this, 'remove_policies_menu'], 999);
        add_action('admin_head', [$this, 'add_menu_highlighting']);
    }
    
    public function remove_policies_menu() {
        remove_menu_page('edit.php?post_type=policy');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Policy Builder',
            'Policy Builder',
            'edit_others_posts',
            'policy-builder',
            [$this, 'policy_builder_page'],
            'dashicons-admin-page',
            27
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
            'New Policy',
            'New Policy',
            'edit_others_posts',
            'policy-builder-new',
            [$this, 'policy_page_handler'] // ✅ Same handler
        );
        
        // Add separate edit page (hidden from menu)
        add_submenu_page(
            null, // Hidden from menu
            'Edit Policy',
            'Edit Policy',
            'edit_others_posts',
            'policy-builder-edit',
            [$this, 'policy_page_handler'] // ✅ Same handler
        );
        
        add_submenu_page(
            'policy-builder',
            'Policy Sections',
            'Policy Sections',
            'edit_others_posts',
            'edit-tags.php?taxonomy=policy-section&post_type=policy'
        );
    }
    
    /**
     * Add proper menu highlighting based on context
     */
    public function add_menu_highlighting() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'policy-builder_page_policy-builder-edit') {
            // When editing, highlight "All Policies" instead
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Remove current highlighting
                $('#adminmenu .wp-submenu li').removeClass('current');
                
                // Highlight "All Policies" menu item when editing
                $('#adminmenu a[href="edit.php?post_type=policy"]').parent().addClass('current');
            });
            </script>
            <style>
            .policy-breadcrumb a {
                color: #0073aa;
                text-decoration: none;
            }
            .policy-breadcrumb a:hover {
                text-decoration: underline;
            }
            </style>
            <?php
        }
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
    
    /**
     * ✅ SINGLE method handles both new and edit pages
     * All business logic stays in one place - no duplication!
     */
    public function policy_page_handler() {
        $current_page = $_GET['page'] ?? '';
        $policy_id = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0;
        
        // Determine the context based on URL and parameters
        if ($current_page === 'policy-builder-edit') {
            // Edit page context
            if (!$policy_id) {
                wp_die('Invalid policy ID for editing');
            }
            
            $post = get_post($policy_id);
            if (!$post || $post->post_type !== 'policy') {
                wp_die('Policy not found');
            }
            
            $context = 'edit';
            
        } elseif ($current_page === 'policy-builder-new') {
            // New page context
            if ($policy_id) {
                // Someone accessed new page with policy_id - redirect to edit
                $redirect_url = admin_url('admin.php?page=policy-builder-edit&policy_id=' . $policy_id);
                wp_redirect($redirect_url);
                exit;
            }
            
            $context = 'new';
            
        } else {
            wp_die('Unknown policy builder context');
        }
        
        // ✅ Single method handles all the rendering logic
        $this->render_policy_builder($policy_id, $context);
    }
    
    /**
     * ✅ Enhanced render method with context awareness and Word Import
     * This is your SINGLE source of truth for the policy builder UI
     */
    private function render_policy_builder($post_id = 0, $context = 'new') {
        $post = $post_id ? get_post($post_id) : null;
        $is_editing = ($context === 'edit');
        
        // Handle new policy creation
        if ($context === 'new' && !$post) {
            $post_id = wp_insert_post([
                'post_title' => 'Draft Policy - ' . date('M j, Y'),
                'post_type' => 'policy',
                'post_status' => 'draft'
            ]);
            $post = get_post($post_id);
            
            // Redirect to edit page with the new policy
            $redirect_url = admin_url('admin.php?page=policy-builder-edit&policy_id=' . $post_id);
            echo '<script>window.location.href = "' . $redirect_url . '";</script>';
            return;
        }
        
        // Set page context for JavaScript and CSS
        $page_config = [
            'context' => $context,
            'isEditing' => $is_editing,
            'policyId' => $post_id,
            'pageTitle' => $is_editing ? 'Edit Policy: ' . $post->post_title : 'Create New Policy',
            'browserTitle' => $is_editing ? 'Edit Policy' : 'New Policy'
        ];
        
        // Set browser title
        echo '<script>document.title = "' . esc_js($page_config['browserTitle']) . ' ‹ Cherry Creek Schools Board of Education — WordPress";</script>';
        
        // Enhanced policy data retrieval with multiple fallbacks
        $policy_data = $this->get_policy_data($post_id);
        
        // Enqueue Word import assets
        $this->enqueue_word_import_assets();
        
        ?>
        <div class="wrap policy-builder-wrap" data-context="<?php echo esc_attr($context); ?>">
            <h1><?php echo esc_html($page_config['pageTitle']); ?></h1>
            
            <?php if ($is_editing): ?>
            <!-- Add breadcrumb for editing context -->
            <nav class="policy-breadcrumb" style="margin-bottom: 20px; font-size: 14px; color: #666;">
                <a href="<?php echo admin_url('admin.php?page=policy-builder'); ?>">Policy Builder</a> 
                › <a href="<?php echo admin_url('edit.php?post_type=policy'); ?>">All Policies</a> 
                › Edit Policy
            </nav>
            
            <!-- Edit context info -->
            <div class="policy-context-info" style="background: #f0f8ff; border-left: 4px solid #0073aa; padding: 12px; margin-bottom: 20px;">
                <strong>Editing:</strong> <?php echo esc_html($post->post_title); ?>
                <span style="margin-left: 15px; color: #666; font-size: 14px;">
                    Last modified: <?php echo date('M j, Y g:i a', strtotime($post->post_modified)); ?>
                </span>
            </div>
            <?php endif; ?>
            
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
                        <p class="description">The URL slug for this policy. Manually edit to match your policy code (e.g., "ac-r-6" for /policy/ac-r-6/)</p>
                    </div>
                    
                    <!-- Code -->
                    <div class="policy-field-group">
                        <label for="policy-code">Code *</label>
                        <input type="text" id="policy-code" value="<?php echo esc_attr($policy_data['code']); ?>" required />
                    </div>
                    
                    <!-- Title (ACF Field) -->
                    <div class="policy-field-group">
                        <label for="policy-title-field">Title *</label>
                        <input type="text" id="policy-title-field" value="<?php echo esc_attr($policy_data['title_policy']); ?>" required />
                    </div>
                    
                    <!-- Policy Section -->
                    <div class="policy-field-group">
                        <label for="policy-sections">Policy Section</label>
                        <select id="policy-sections" multiple>
                            <?php 
                            $policy_sections = get_terms(['taxonomy' => 'policy-section', 'hide_empty' => false]);
                            foreach ($policy_sections as $section): ?>
                                <option value="<?php echo $section->term_id; ?>" <?php echo in_array($section->term_id, $policy_data['post_sections']) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($section->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Dates -->
                    <div class="policy-dates-row">
                        <div class="policy-field-group">
                            <label for="adopted-date">Adopted Date</label>
                            <input type="date" id="adopted-date" value="<?php echo $policy_data['adopted'] ? date('Y-m-d', strtotime($policy_data['adopted'])) : ''; ?>" />
                        </div>
                        <div class="policy-field-group">
                            <label for="last-revised-date">Last Revised</label>
                            <input type="date" id="last-revised-date" value="<?php echo $policy_data['last_revised'] ? date('Y-m-d', strtotime($policy_data['last_revised'])) : ''; ?>" />
                        </div>
                    </div>
                    
                    <!-- Policy Content - STANDARD WP_EDITOR -->
                    <div class="policy-field-group">
                        <label for="policy-content">Policy Content</label>
                        <?php
                        // Use the enhanced content retrieval
                        $editor_content = $policy_data['policy_content'];
                        
                        // Standard wp_editor call - NO external plugins configuration
                        wp_editor($editor_content, 'policy-content', [
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
                        
                        <!-- Debug info for admins -->
                        <?php if (current_user_can('manage_options') && isset($_GET['debug'])): ?>
                        <div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffeaa7; border-radius: 4px;">
                            <strong>Debug Info (Admin Only):</strong><br>
                            ACF Field 'policy': <?php echo !empty($policy_data['policy_content']) ? '✅ Found (' . strlen($policy_data['policy_content']) . ' chars)' : '❌ Empty'; ?><br>
                            Post Content Fallback: <?php echo !empty($post->post_content) ? '✅ Available (' . strlen($post->post_content) . ' chars)' : '❌ Empty'; ?><br>
                            Final Content: <?php echo !empty($editor_content) ? '✅ Loaded (' . strlen($editor_content) . ' chars)' : '❌ Empty'; ?><br>
                            Mammoth.js Status: <span id="mammoth-status">Checking...</span><br>
                            <a href="<?php echo add_query_arg('debug', '1', $_SERVER['REQUEST_URI']); ?>">Refresh with Debug</a>
                        </div>
                        <script>
                        jQuery(document).ready(function($) {
                            $('#mammoth-status').text(typeof mammoth !== 'undefined' ? '✅ Loaded' : '❌ Not loaded');
                        });
                        </script>
                        <?php endif; ?>
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
                    
                    <!-- Word Document Import Section - JavaScript will populate this -->
                    <!-- This will be inserted by JavaScript after the Actions box -->
                    
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
                            <?php if ($policy_data['code']): ?>
                            <div class="meta-row">
                                <label>Code:</label>
                                <span><?php echo esc_html($policy_data['code']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="policy-links-box">
                        <h3>Quick Links</h3>
                        <div class="quick-links">
                            <?php if ($is_editing): ?>
                                <a href="<?php echo get_permalink($post_id); ?>" target="_blank" class="button">View Policy</a>
                            <?php endif; ?>
                            <a href="<?php echo admin_url('edit.php?post_type=policy'); ?>" class="button">All Policies</a>
                            <a href="<?php echo admin_url('edit-tags.php?taxonomy=policy-section&post_type=policy'); ?>" class="button">Manage Sections</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Hidden inputs -->
            <input type="hidden" id="policy-id" value="<?php echo $post_id; ?>" />
            <input type="hidden" id="policy-context" value="<?php echo esc_attr($context); ?>" />
        </div>

        <!-- Pass context to JavaScript -->
        <script>
            window.policyBuilderContext = <?php echo json_encode($page_config); ?>;
        </script>

        <!-- ✅ NEW: JavaScript-based TinyMCE Button Addition -->
        <script>
        jQuery(document).ready(function($) {
            console.log('Policy Builder: Starting TinyMCE OL Start button integration...');
            
            // Function to add the OL Start button
            function addOLStartButton() {
                if (typeof tinymce === 'undefined') {
                    console.log('Policy Builder: TinyMCE not ready, retrying...');
                    setTimeout(addOLStartButton, 200);
                    return;
                }
                
                var editor = tinymce.get('policy-content');
                if (!editor) {
                    console.log('Policy Builder: Editor not ready, retrying...');
                    setTimeout(addOLStartButton, 200);
                    return;
                }
                
                if (!editor.initialized) {
                    console.log('Policy Builder: Editor not initialized, retrying...');
                    setTimeout(addOLStartButton, 200);
                    return;
                }
                
                // Check if button already exists
                if (editor.buttons && editor.buttons.olstart) {
                    console.log('Policy Builder: OL Start button already exists');
                    return;
                }
                
                console.log('Policy Builder: Adding OL Start button to TinyMCE...');
                
                // Add the button using TinyMCE's API
                editor.addButton('olstart', {
                    title: 'Set Start Number for Ordered List',
                    icon: 'numlist',
                    onclick: function() {
                        console.log('Policy Builder: OL Start button clicked');
                        
                        var start = prompt("Enter the starting number:", "1");
                        if (!start || isNaN(start) || parseInt(start) <= 0) {
                            alert("Please enter a valid positive number.");
                            return;
                        }

                        start = parseInt(start);
                        var node = editor.selection.getNode();
                        
                        // Find the nearest <ol>
                        while (node && node.nodeName !== 'OL') {
                            node = node.parentNode;
                        }

                        if (node && node.nodeName === 'OL') {
                            editor.dom.setAttrib(node, 'start', start);
                            editor.selection.select(node);
                            editor.selection.collapse(false);
                            console.log('Policy Builder: Set start attribute to ' + start + ' on existing list');
                        } else {
                            editor.insertContent('<ol start="' + start + '"><li>List item</li></ol>');
                            console.log('Policy Builder: Created new list with start=' + start);
                        }
                    }
                });
                
                // Try to add the button to the toolbar
                var toolbar = editor.theme.panel.find('toolbar')[0];
                if (toolbar) {
                    // Find the numbered list button
                    var numlistBtn = toolbar.find('button').filter(function(btn) {
                        return btn.settings && (btn.settings.cmd === 'InsertOrderedList' || btn.settings.icon === 'numlist');
                    })[0];
                    
                    if (numlistBtn) {
                        // Create the button element
                        var olstartBtn = new tinymce.ui.Button({
                            text: '',
                            icon: 'numlist',
                            tooltip: 'Set Start Number for Ordered List',
                            onclick: function() {
                                // Trigger the same function as our registered button
                                if (editor.buttons.olstart) {
                                    editor.buttons.olstart.onclick.call(this);
                                }
                            }
                        });
                        
                        // Render the button after the numlist button
                        try {
                            olstartBtn.renderTo(toolbar.getEl());
                            console.log('Policy Builder: OL Start button successfully added to toolbar');
                        } catch (e) {
                            console.log('Policy Builder: Error adding button to toolbar:', e);
                        }
                    } else {
                        console.log('Policy Builder: Could not find numbered list button to position after');
                    }
                } else {
                    console.log('Policy Builder: Could not find toolbar');
                }
            }
            
            // Start the process
            addOLStartButton();
            
            // Also try when TinyMCE fires its init event
            $(document).on('tinymce-editor-init', function(event, editor) {
                if (editor.id === 'policy-content') {
                    console.log('Policy Builder: TinyMCE editor init event fired');
                    setTimeout(addOLStartButton, 100);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Enqueue Word import assets - NEW METHOD
     */
    private function enqueue_word_import_assets() {
        // Enqueue Mammoth.js from CDN
        wp_enqueue_script(
            'mammoth-js',
            'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.8.0/mammoth.browser.min.js',
            [],
            '1.8.0',
            true
        );
        
        // Enqueue our Word import script
        if (BPB_Asset_Manager::asset_exists('word-import.js', 'js', 'admin')) {
            BPB_Asset_Manager::enqueue_admin_js(
                'word-import-js',
                'word-import.js',
                ['jquery', 'mammoth-js'],
                BPB_PLUGIN_VERSION,
                true
            );
        }
        
        // Enqueue Word import CSS
        if (BPB_Asset_Manager::asset_exists('word-import.css', 'css', 'admin')) {
            BPB_Asset_Manager::enqueue_admin_css(
                'word-import-css',
                'word-import.css',
                [],
                BPB_PLUGIN_VERSION
            );
        }
    }
    
    /**
     * Enhanced policy data retrieval with multiple fallbacks
     */
    private function get_policy_data($post_id) {
        $post = get_post($post_id);
        
        // Initialize data array
        $data = [
            'code' => '',
            'title_policy' => '',
            'adopted' => '',
            'last_revised' => '',
            'policy_content' => '',
            'post_sections' => []
        ];
        
        // Try multiple methods to get ACF data
        
        // Method 1: Standard get_field()
        $data['code'] = get_field('code-policy', $post_id) ?: '';
        $data['title_policy'] = get_field('title-policy', $post_id) ?: '';
        $data['adopted'] = get_field('adopted-policy', $post_id) ?: '';
        $data['last_revised'] = get_field('last_revised-policy', $post_id) ?: '';
        $data['policy_content'] = get_field('policy', $post_id) ?: '';
        
        // Method 2: If ACF fields are empty, try direct meta queries
        if (empty($data['policy_content'])) {
            $meta_content = get_post_meta($post_id, 'policy', true);
            if (!empty($meta_content)) {
                $data['policy_content'] = $meta_content;
            }
        }
        
        // Method 3: If still empty, check for underscore-prefixed meta (ACF sometimes stores this way)
        if (empty($data['policy_content'])) {
            $underscore_content = get_post_meta($post_id, '_policy', true);
            if (!empty($underscore_content)) {
                $data['policy_content'] = $underscore_content;
            }
        }
        
        // Method 4: Last resort - use post_content if ACF field is completely empty
        if (empty($data['policy_content']) && !empty($post->post_content)) {
            $data['policy_content'] = $post->post_content;
        }
        
        // Try alternative meta keys for other fields if empty
        if (empty($data['code'])) {
            $data['code'] = get_post_meta($post_id, 'code-policy', true) ?: get_post_meta($post_id, '_code-policy', true) ?: '';
        }
        
        if (empty($data['title_policy'])) {
            $data['title_policy'] = get_post_meta($post_id, 'title-policy', true) ?: get_post_meta($post_id, '_title-policy', true) ?: '';
        }
        
        if (empty($data['adopted'])) {
            $data['adopted'] = get_post_meta($post_id, 'adopted-policy', true) ?: get_post_meta($post_id, '_adopted-policy', true) ?: '';
        }
        
        if (empty($data['last_revised'])) {
            $data['last_revised'] = get_post_meta($post_id, 'last_revised-policy', true) ?: get_post_meta($post_id, '_last_revised-policy', true) ?: '';
        }
        
        // Get taxonomy terms
        $terms = wp_get_post_terms($post_id, 'policy-section', ['fields' => 'ids']);
        $data['post_sections'] = is_array($terms) && !is_wp_error($terms) ? $terms : [];
        
        return $data;
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
            $existing_post = get_page_by_path($slug, OBJECT, 'policy');
            if ($existing_post && $existing_post->ID !== $policy_id) {
                $slug = wp_unique_post_slug($slug, $policy_id, $status, 'policy', 0);
            }
        } else {
            $slug = sanitize_title($title);
        }
        
        // Update post
        $result = wp_update_post([
            'ID' => $policy_id,
            'post_title' => $title,
            'post_name' => $slug,
            'post_status' => $status
        ], true);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Error updating post: ' . $result->get_error_message());
        }
        
        // Save ACF fields with additional verification
        $this->save_policy_fields($policy_id);
        
        wp_send_json_success(['message' => 'Policy saved successfully', 'policy_id' => $policy_id]);
    }
    
    /**
     * Save policy fields with better error handling
     */
    private function save_policy_fields($policy_id) {
        $fields = [
            'code-policy' => sanitize_text_field($_POST['code'] ?? ''),
            'title-policy' => sanitize_text_field($_POST['title_field'] ?? ''),
            'adopted-policy' => sanitize_text_field($_POST['adopted_date'] ?? ''),
            'last_revised-policy' => sanitize_text_field($_POST['last_revised_date'] ?? ''),
            'policy' => wp_kses_post($_POST['policy_content'] ?? '')
        ];
        
        foreach ($fields as $field_name => $value) {
            // Try ACF update_field first
            $acf_result = update_field($field_name, $value, $policy_id);
            
            // If ACF update fails, try direct meta update as backup
            if (!$acf_result) {
                update_post_meta($policy_id, $field_name, $value);
                // Also try with underscore prefix (ACF internal storage)
                update_post_meta($policy_id, '_' . $field_name, $value);
            }
        }
        
        // Update taxonomy
        $sections = $_POST['policy_sections'] ?? [];
        wp_set_post_terms($policy_id, array_map('intval', $sections), 'policy-section');
    }
}

new PolicyBuilder();