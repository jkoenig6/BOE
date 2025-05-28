<?php
/**
 * Resolution Builder Backend
 * File: includes/resolution-builder.php
 */

class ResolutionBuilder {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_save_resolution', [$this, 'save_resolution']);
        add_action('wp_ajax_submit_for_approval', [$this, 'submit_for_approval']);
        add_action('wp_ajax_approve_resolution', [$this, 'approve_resolution']);
        add_action('wp_ajax_deny_resolution', [$this, 'deny_resolution']);
        add_action('init', [$this, 'register_resolution_statuses']);
        add_action('admin_menu', [$this, 'remove_resolutions_menu'], 999);
    }
    
    public function register_resolution_statuses() {
        // Register custom post statuses for resolution workflow
        register_post_status('submit_approval', [
            'label' => _x('Submit for Approval', 'resolution'),
            'public' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Submit for Approval <span class="count">(%s)</span>', 'Submit for Approval <span class="count">(%s)</span>'),
        ]);
        
        register_post_status('approved', [
            'label' => _x('Approved', 'resolution'),
            'public' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>'),
        ]);
        
        register_post_status('denied', [
            'label' => _x('Denied', 'resolution'),
            'public' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Denied <span class="count">(%s)</span>', 'Denied <span class="count">(%s)</span>'),
        ]);
    }
    
    public function remove_resolutions_menu() {
        // Remove the default Resolutions menu since we have Resolution Builder
        remove_menu_page('edit.php?post_type=resolution');
    }
    
    public function add_admin_menu() {
        // Main Resolution Builder menu
        add_menu_page(
            'Resolution Builder',
            'Resolution Builder',
            'edit_others_posts',
            'resolution-builder',
            [$this, 'resolution_builder_page'],
            'dashicons-admin-page',
            26
        );
        
        // Submenu items
        add_submenu_page(
            'resolution-builder',
            'New Resolution',
            'New Resolution',
            'edit_others_posts',
            'resolution-builder-new',
            [$this, 'new_resolution_page']
        );
        
        add_submenu_page(
            'resolution-builder',
            'All Resolutions',
            'All Resolutions',
            'edit_others_posts',
            'edit.php?post_type=resolution'
        );
        
        add_submenu_page(
            'resolution-builder',
            'Pending Approval',
            'Pending Approval',
            'edit_others_posts',
            'resolution-builder-pending',
            [$this, 'pending_resolutions_page']
        );
    }
    
    public function resolution_builder_page() {
        echo '<div class="wrap"><h1>Resolution Builder</h1>';
        echo '<p>Manage board resolutions through the approval workflow:</p>';
        echo '<div class="resolution-type-buttons">';
        echo '<a href="' . admin_url('admin.php?page=resolution-builder-new') . '" class="button button-primary button-hero">New Resolution</a>';
        echo '<a href="' . admin_url('admin.php?page=resolution-builder-pending') . '" class="button button-secondary button-hero">Pending Approval</a>';
        echo '<a href="' . admin_url('edit.php?post_type=resolution') . '" class="button button-secondary button-hero">All Resolutions</a>';
        echo '</div></div>';
    }
    
    public function new_resolution_page() {
        // Check if we have a resolution_id parameter
        $resolution_id = isset($_GET['resolution_id']) ? intval($_GET['resolution_id']) : 0;
        $this->render_resolution_builder($resolution_id);
    }
    
    public function pending_resolutions_page() {
        $this->render_pending_resolutions();
    }
    
    private function render_resolution_builder($post_id = 0) {
        $post = $post_id ? get_post($post_id) : null;
        
        // If no post ID, create a new draft
        if (!$post) {
            $post_id = wp_insert_post([
                'post_title' => 'Draft Resolution - ' . date('M j, Y'),
                'post_type' => 'resolution',
                'post_status' => 'draft'
            ]);
            $post = get_post($post_id);
            
            // Redirect to include the new post ID in URL
            $redirect_url = admin_url('admin.php?page=resolution-builder-new&resolution_id=' . $post_id);
            echo '<script>window.location.href = "' . $redirect_url . '";</script>';
            return;
        }
        
        // Get resolution data
        $assigned_meeting = get_field('assigned_meeting', $post_id);
        $subject = get_field('subject', $post_id);
        $fiscal_impact = get_field('fiscal_impact', $post_id);
        $budgeted = get_field('budgeted', $post_id);
        $amount = get_field('amount', $post_id);
        $budget_source = get_field('budget_source', $post_id);
        $recommended_action = get_field('recommended_action', $post_id);
        $resolution_text = get_field('resolution', $post_id);
        
        ?>
        <div class="wrap resolution-builder-wrap">
            <h1>Resolution Builder</h1>
            
            <div class="resolution-builder-layout">
                <!-- Left Column: Resolution Details -->
                <div class="resolution-details-column">
                    <div class="resolution-header">
                        <h2>Resolution Details</h2>
                        <div class="resolution-status-badge status-<?php echo esc_attr($post->post_status); ?>">
                            <?php echo $this->get_status_label($post->post_status); ?>
                        </div>
                    </div>
                    
                    <!-- Resolution Title -->
                    <div class="resolution-field-group">
                        <label for="resolution-title">Resolution Title</label>
                        <input type="text" id="resolution-title" value="<?php echo esc_attr($post->post_title); ?>" />
                    </div>
                    
                    <!-- Subject -->
                    <div class="resolution-field-group">
                        <label for="resolution-subject">Subject *</label>
                        <input type="text" id="resolution-subject" value="<?php echo esc_attr($subject); ?>" required />
                    </div>
                    
                    <!-- Assigned Meeting -->
                    <div class="resolution-field-group">
                        <label for="assigned-meeting">Assigned Meeting *</label>
                        <select id="assigned-meeting" required>
                            <option value="">Select Meeting...</option>
                            <?php
                            $meetings = get_posts([
                                'post_type' => 'meeting',
                                'post_status' => ['draft', 'pending', 'future', 'private'],
                                'numberposts' => -1,
                                'orderby' => 'date',
                                'order' => 'DESC'
                            ]);
                            foreach ($meetings as $meeting) {
                                $selected = ($assigned_meeting && $assigned_meeting->ID == $meeting->ID) ? 'selected' : '';
                                echo '<option value="' . $meeting->ID . '" ' . $selected . '>' . esc_html($meeting->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Fiscal Impact Section -->
                    <div class="resolution-field-group fiscal-section">
                        <h3>Fiscal Impact</h3>
                        
                        <div class="fiscal-impact-toggle">
                            <label>
                                <input type="checkbox" id="fiscal-impact" <?php checked($fiscal_impact); ?> />
                                This resolution has fiscal impact
                            </label>
                        </div>
                        
                        <div class="fiscal-details" style="<?php echo $fiscal_impact ? '' : 'display: none;'; ?>">
                            <div class="fiscal-row">
                                <div class="fiscal-field">
                                    <label>
                                        <input type="checkbox" id="budgeted" <?php checked($budgeted); ?> />
                                        Budgeted
                                    </label>
                                </div>
                                <div class="fiscal-field">
                                    <label for="amount">Amount</label>
                                    <input type="number" id="amount" step="0.01" value="<?php echo esc_attr($amount); ?>" />
                                </div>
                            </div>
                            <div class="fiscal-field">
                                <label for="budget-source">Budget Source</label>
                                <input type="text" id="budget-source" value="<?php echo esc_attr($budget_source); ?>" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recommended Action -->
                    <div class="resolution-field-group">
                        <label for="recommended-action">Recommended Action</label>
                        <input type="text" id="recommended-action" value="<?php echo esc_attr($recommended_action); ?>" />
                    </div>
                    
                    <!-- Resolution Text -->
                    <div class="resolution-field-group">
                        <label for="resolution-text">Resolution Text</label>
                        <textarea id="resolution-text" rows="12"><?php echo esc_textarea($resolution_text); ?></textarea>
                    </div>
                </div>
                
                <!-- Right Column: Actions & Info -->
                <div class="resolution-actions-column">
                    <!-- Status & Actions Box -->
                    <div class="resolution-actions-box">
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
                    
                    <!-- Workflow Info -->
                    <div class="workflow-info-box">
                        <h3>Workflow Steps</h3>
                        <div class="workflow-steps">
                            <div class="workflow-step <?php echo $post->post_status === 'draft' ? 'current' : 'completed'; ?>">
                                <span class="step-number">1</span>
                                <span class="step-label">Draft</span>
                            </div>
                            <div class="workflow-step <?php echo $post->post_status === 'submit_approval' ? 'current' : ($this->is_past_status($post->post_status, 'submit_approval') ? 'completed' : ''); ?>">
                                <span class="step-number">2</span>
                                <span class="step-label">Submit for Approval</span>
                            </div>
                            <div class="workflow-step <?php echo in_array($post->post_status, ['approved', 'denied']) ? 'current' : ''; ?>">
                                <span class="step-number">3</span>
                                <span class="step-label">Review</span>
                            </div>
                            <div class="workflow-step <?php echo $post->post_status === 'publish' ? 'current' : ''; ?>">
                                <span class="step-number">4</span>
                                <span class="step-label">Published</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Meta Info -->
                    <div class="resolution-meta-box">
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
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Hidden inputs -->
            <input type="hidden" id="resolution-id" value="<?php echo $post_id; ?>" />
        </div>
        <?php
    }
    
    private function render_pending_resolutions() {
        $pending_resolutions = get_posts([
            'post_type' => 'resolution',
            'post_status' => 'submit_approval',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'ASC'
        ]);
        
        ?>
        <div class="wrap">
            <h1>Pending Resolutions</h1>
            
            <?php if (empty($pending_resolutions)): ?>
                <div class="no-resolutions">
                    <p>No resolutions pending approval.</p>
                    <a href="<?php echo admin_url('admin.php?page=resolution-builder-new'); ?>" class="button button-primary">Create New Resolution</a>
                </div>
            <?php else: ?>
                <div class="pending-resolutions-list">
                    <?php foreach ($pending_resolutions as $resolution): ?>
                        <div class="pending-resolution-card">
                            <div class="resolution-card-header">
                                <h3><?php echo esc_html($resolution->post_title); ?></h3>
                                <span class="submission-date">Submitted: <?php echo date('M j, Y', strtotime($resolution->post_modified)); ?></span>
                            </div>
                            <div class="resolution-card-content">
                                <div class="resolution-meta">
                                    <div class="meta-item">
                                        <label>Subject:</label>
                                        <span><?php echo esc_html(get_field('subject', $resolution->ID)); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <label>Meeting:</label>
                                        <?php 
                                        $meeting = get_field('assigned_meeting', $resolution->ID);
                                        echo $meeting ? esc_html($meeting->post_title) : 'Not assigned';
                                        ?>
                                    </div>
                                    <div class="meta-item">
                                        <label>Fiscal Impact:</label>
                                        <span><?php echo get_field('fiscal_impact', $resolution->ID) ? 'Yes' : 'No'; ?></span>
                                    </div>
                                </div>
                                <div class="resolution-actions">
                                    <a href="<?php echo admin_url('admin.php?page=resolution-builder-new&resolution_id=' . $resolution->ID); ?>" class="button">Review</a>
                                    <button class="button button-primary approve-resolution" data-resolution-id="<?php echo $resolution->ID; ?>">Approve</button>
                                    <button class="button deny-resolution" data-resolution-id="<?php echo $resolution->ID; ?>">Deny</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_action_buttons($status) {
        switch ($status) {
            case 'draft':
                echo '<button type="button" class="button button-large" id="save-draft">Save Draft</button>';
                echo '<button type="button" class="button button-primary button-large" id="submit-approval">Submit for Approval</button>';
                break;
                
            case 'submit_approval':
                echo '<button type="button" class="button button-large" id="save-draft">Save Changes</button>';
                echo '<p class="status-message">This resolution is pending approval.</p>';
                break;
                
            case 'approved':
                echo '<button type="button" class="button button-large" id="save-draft">Save Changes</button>';
                echo '<button type="button" class="button button-primary button-large" id="publish-resolution">Publish</button>';
                break;
                
            case 'denied':
                echo '<button type="button" class="button button-large" id="save-draft">Save Changes</button>';
                echo '<button type="button" class="button button-secondary button-large" id="resubmit-approval">Resubmit for Approval</button>';
                break;
                
            case 'publish':
                echo '<button type="button" class="button button-large" id="save-published">Update Published</button>';
                break;
                
            default:
                echo '<button type="button" class="button button-large" id="save-draft">Save Draft</button>';
                break;
        }
    }
    
    private function get_status_label($status) {
        $labels = [
            'draft' => 'Draft',
            'submit_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'denied' => 'Denied',
            'publish' => 'Published'
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
    
    private function is_past_status($current_status, $check_status) {
        $order = ['draft', 'submit_approval', 'approved', 'publish'];
        $current_index = array_search($current_status, $order);
        $check_index = array_search($check_status, $order);
        
        return $current_index !== false && $check_index !== false && $current_index > $check_index;
    }
    
    public function save_resolution() {
        check_ajax_referer('resolution_builder_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // ✅ FIXED: Proper input validation
        $resolution_id = intval($_POST['resolution_id'] ?? 0);
        if (!$resolution_id) {
            wp_send_json_error('Invalid resolution ID');
        }
        
        // ✅ ADDED: Verify user can edit this specific resolution
        if (!current_user_can('edit_post', $resolution_id)) {
            wp_send_json_error('Insufficient permissions to edit this resolution');
        }
        
        // ✅ FIXED: Sanitize all inputs
        $title = sanitize_text_field($_POST['title'] ?? '');
        $status = sanitize_key($_POST['status'] ?? 'draft');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $assigned_meeting = intval($_POST['assigned_meeting'] ?? 0);
        $fiscal_impact = (bool)($_POST['fiscal_impact'] ?? false);
        $budgeted = (bool)($_POST['budgeted'] ?? false);
        $amount = floatval($_POST['amount'] ?? 0);
        $budget_source = sanitize_text_field($_POST['budget_source'] ?? '');
        $recommended_action = sanitize_text_field($_POST['recommended_action'] ?? '');
        $resolution_text = wp_kses_post($_POST['resolution_text'] ?? '');
        
        // ✅ ADDED: Validate status against allowed values
        $allowed_statuses = ['draft', 'submit_approval', 'approved', 'denied', 'publish'];
        if (!in_array($status, $allowed_statuses)) {
            wp_send_json_error('Invalid status');
        }
        
        // ✅ ADDED: Validate assigned meeting exists
        if ($assigned_meeting && !get_post($assigned_meeting)) {
            wp_send_json_error('Invalid meeting assignment');
        }
        
        // Update post
        $result = wp_update_post([
            'ID' => $resolution_id,
            'post_title' => $title,
            'post_status' => $status
        ]);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Failed to update resolution: ' . $result->get_error_message());
        }
        
        // Update ACF fields with sanitized data
        update_field('subject', $subject, $resolution_id);
        update_field('assigned_meeting', $assigned_meeting, $resolution_id);
        update_field('fiscal_impact', $fiscal_impact, $resolution_id);
        update_field('budgeted', $budgeted, $resolution_id);
        update_field('amount', $amount, $resolution_id);
        update_field('budget_source', $budget_source, $resolution_id);
        update_field('recommended_action', $recommended_action, $resolution_id);
        update_field('resolution', $resolution_text, $resolution_id);
        
        wp_send_json_success(['message' => 'Resolution saved successfully', 'resolution_id' => $resolution_id]);
    }
    
    public function submit_for_approval() {
        check_ajax_referer('resolution_builder_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $resolution_id = intval($_POST['resolution_id']);
        
        wp_update_post([
            'ID' => $resolution_id,
            'post_status' => 'submit_approval'
        ]);
        
        wp_send_json_success(['message' => 'Resolution submitted for approval']);
    }
    
    public function approve_resolution() {
        check_ajax_referer('resolution_builder_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $resolution_id = intval($_POST['resolution_id']);
        
        wp_update_post([
            'ID' => $resolution_id,
            'post_status' => 'approved'
        ]);
        
        wp_send_json_success(['message' => 'Resolution approved']);
    }
    
    public function deny_resolution() {
        check_ajax_referer('resolution_builder_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $resolution_id = intval($_POST['resolution_id']);
        
        wp_update_post([
            'ID' => $resolution_id,
            'post_status' => 'denied'
        ]);
        
        wp_send_json_success(['message' => 'Resolution denied']);
    }
}

new ResolutionBuilder();