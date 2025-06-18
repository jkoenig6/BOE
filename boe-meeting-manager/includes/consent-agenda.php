<?php
/**
 * Enhanced Consent Agenda Functionality with Drag-and-Drop Ordering
 * File: includes/consent-agenda.php
 * 
 * Add this as a new file in your plugin's includes/ directory
 * and require it from your main plugin file
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ConsentAgendaManager {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Auto-populate consent agenda with approved resolutions
        add_filter('acf/load_value/name=resolutions', [$this, 'auto_populate_resolutions'], 10, 3);
        
        // Auto-publish resolutions when meeting is published
        add_action('transition_post_status', [$this, 'auto_publish_resolutions'], 10, 3);
        
        // AJAX endpoints
        add_action('wp_ajax_get_meeting_resolutions', [$this, 'ajax_get_meeting_resolutions']);
        add_action('wp_ajax_save_resolution_order', [$this, 'ajax_save_resolution_order']);
        
        // Enhance admin columns for resolutions
        add_filter('manage_resolution_posts_columns', [$this, 'add_resolution_columns']);
        add_action('manage_resolution_posts_custom_column', [$this, 'populate_resolution_columns'], 10, 2);
        
        // Add shortcode for displaying resolutions
        add_shortcode('meeting_resolutions', [$this, 'meeting_resolutions_shortcode']);
        
        // Add admin styles
        add_action('admin_head', [$this, 'add_admin_styles']);
    }
    
    /**
     * Auto-populate consent agenda with approved resolutions for assigned meeting
     */
    public function auto_populate_resolutions($value, $post_id, $field) {
        // Only auto-populate if it's a meeting and the field is empty
        if (get_post_type($post_id) !== 'meeting' || !empty($value)) {
            return $value;
        }
        
        // Get all approved resolutions assigned to this meeting
        $approved_resolutions = get_posts([
            'post_type' => 'resolution',
            'post_status' => 'approved',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => 'assigned_meeting',
                    'value' => $post_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'ASC'
        ]);
        
        // Return array of resolution IDs
        return wp_list_pluck($approved_resolutions, 'ID');
    }
    
    /**
     * Enhanced AJAX endpoint to get formatted resolution data with ordering
     */
    public function ajax_get_meeting_resolutions() {
        check_ajax_referer('agenda_builder_nonce', 'nonce');
        
        $meeting_id = intval($_POST['meeting_id']);
        
        if (!$meeting_id) {
            wp_send_json_error('Invalid meeting ID');
        }
        
        // Get approved resolutions for this meeting
        $resolutions = get_posts([
            'post_type' => 'resolution',
            'post_status' => 'approved',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => 'assigned_meeting',
                    'value' => $meeting_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'ASC'
        ]);
        
        // Get custom ordering if it exists
        $custom_order = get_post_meta($meeting_id, 'consent_agenda_order', true);
        
        // If custom order exists, reorder resolutions accordingly
        if (!empty($custom_order) && is_array($custom_order)) {
            $ordered_resolutions = [];
            $remaining_resolutions = $resolutions;
            
            // First, add resolutions in custom order
            foreach ($custom_order as $resolution_id) {
                foreach ($remaining_resolutions as $key => $resolution) {
                    if ($resolution->ID == $resolution_id) {
                        $ordered_resolutions[] = $resolution;
                        unset($remaining_resolutions[$key]);
                        break;
                    }
                }
            }
            
            // Then add any new resolutions that weren't in the custom order
            $ordered_resolutions = array_merge($ordered_resolutions, $remaining_resolutions);
            $resolutions = $ordered_resolutions;
        }
        
        $formatted_resolutions = [];
        foreach ($resolutions as $index => $resolution) {
            $formatted_resolutions[] = [
                'id' => $resolution->ID,
                'title' => $this->get_formatted_resolution_title($resolution->ID, $meeting_id, $index + 1),
                'original_title' => $resolution->post_title,
                'subject' => get_field('subject', $resolution->ID),
                'fiscal_impact' => get_field('fiscal_impact', $resolution->ID) ? 'Yes' : 'No',
                'sequence' => $index + 1
            ];
        }
        
        wp_send_json_success($formatted_resolutions);
    }
    
    /**
     * AJAX handler to save custom resolution order
     */
    public function ajax_save_resolution_order() {
        // ✅ Good nonce check
        check_ajax_referer('agenda_builder_nonce', 'nonce');
        
        // ✅ Good capability check
        if (!current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // ✅ FIXED: Proper input sanitization
        $meeting_id = intval($_POST['meeting_id']);
        $resolution_order = isset($_POST['resolution_order']) ? $_POST['resolution_order'] : [];
        
        if (!$meeting_id) {
            wp_send_json_error('Invalid meeting ID');
        }
        
        // ✅ FIXED: Validate and sanitize the resolution order array
        if (!is_array($resolution_order)) {
            wp_send_json_error('Invalid resolution order data');
        }
        
        $clean_order = [];
        foreach ($resolution_order as $id) {
            $clean_id = intval($id);
            if ($clean_id > 0) {
                // ✅ ADDED: Verify resolution exists and user can edit it
                $resolution = get_post($clean_id);
                if ($resolution && $resolution->post_type === 'resolution') {
                    $clean_order[] = $clean_id;
                }
            }
        }
        
        // ✅ ADDED: Verify user can edit the meeting
        if (!current_user_can('edit_post', $meeting_id)) {
            wp_send_json_error('Insufficient permissions to edit this meeting');
        }
        
        // Save the custom order
        update_post_meta($meeting_id, 'consent_agenda_order', $clean_order);
        
        wp_send_json_success([
            'message' => 'Resolution order saved successfully',
            'order' => $clean_order
        ]);
    }
    
    /**
     * Modified resolution number generation with manual sequence
     */
    public function generate_resolution_number($resolution_id, $meeting_id, $sequence = null) {
        // Get meeting date for year/month
        $meeting_date = get_field('agenda_items', $meeting_id);
        $meeting_datetime = '';
        
        // Extract datetime from agenda items
        if ($meeting_date && is_array($meeting_date)) {
            foreach ($meeting_date as $item) {
                if (($item['acf_fc_layout'] ?? '') === 'meeting_datetime' && !empty($item['datetime'])) {
                    $meeting_datetime = $item['datetime'];
                    break;
                }
            }
        }
        
        if (empty($meeting_datetime)) {
            $meeting_datetime = get_the_date('Y-m-d H:i:s', $meeting_id);
        }
        
        $date = new DateTime($meeting_datetime);
        $year = $date->format('y'); // 2-digit year
        $month = $date->format('n'); // Month without leading zero
        
        // If sequence is provided, use it; otherwise calculate it
        if ($sequence === null) {
            // Get custom ordering first
            $custom_order = get_post_meta($meeting_id, 'consent_agenda_order', true);
            
            if (!empty($custom_order) && is_array($custom_order)) {
                $position = array_search($resolution_id, $custom_order);
                $sequence = $position !== false ? $position + 1 : 1;
            } else {
                // Fall back to date-based ordering
                $meeting_resolutions = get_posts([
                    'post_type' => 'resolution',
                    'post_status' => 'approved',
                    'numberposts' => -1,
                    'meta_query' => [
                        [
                            'key' => 'assigned_meeting',
                            'value' => $meeting_id,
                            'compare' => '='
                        ]
                    ],
                    'orderby' => 'date',
                    'order' => 'ASC',
                    'fields' => 'ids'
                ]);
                
                $position = array_search($resolution_id, $meeting_resolutions);
                $sequence = $position !== false ? $position + 1 : 1;
            }
        }
        
        return "{$year}.{$month}.{$sequence}";
    }
    
    /**
     * Modified get formatted title with sequence parameter
     */
    public function get_formatted_resolution_title($resolution_id, $meeting_id, $sequence = null) {
        $resolution_title = get_the_title($resolution_id);
        $resolution_number = $this->generate_resolution_number($resolution_id, $meeting_id, $sequence);
        
        return "Resolution #{$resolution_number} - {$resolution_title}";
    }
    
    /**
     * Enhanced auto-publish with proper numbering based on custom order
     */
    public function auto_publish_resolutions($new_status, $old_status, $post) {
        if ($post->post_type !== 'meeting' || $new_status !== 'publish') {
            return;
        }
        
        // Get all approved resolutions assigned to this meeting
        $assigned_resolutions = get_posts([
            'post_type' => 'resolution',
            'post_status' => 'approved',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => 'assigned_meeting',
                    'value' => $post->ID,
                    'compare' => '='
                ]
            ],
            'fields' => 'ids'
        ]);
        
        if (empty($assigned_resolutions)) {
            return;
        }
        
        // Get custom ordering
        $custom_order = get_post_meta($post->ID, 'consent_agenda_order', true);
        
        // Reorder resolutions if custom order exists
        if (!empty($custom_order) && is_array($custom_order)) {
            $ordered_resolutions = [];
            $remaining_resolutions = $assigned_resolutions;
            
            // First, add resolutions in custom order
            foreach ($custom_order as $resolution_id) {
                if (in_array($resolution_id, $remaining_resolutions)) {
                    $ordered_resolutions[] = $resolution_id;
                    $remaining_resolutions = array_diff($remaining_resolutions, [$resolution_id]);
                }
            }
            
            // Then add any remaining resolutions
            $ordered_resolutions = array_merge($ordered_resolutions, $remaining_resolutions);
            $assigned_resolutions = $ordered_resolutions;
        }
        
        // Publish all resolutions with proper sequential numbering
        foreach ($assigned_resolutions as $sequence => $resolution_id) {
            wp_update_post([
                'ID' => $resolution_id,
                'post_status' => 'publish'
            ]);
            
            // Store the resolution number as meta with the correct sequence
            $resolution_number = $this->generate_resolution_number($resolution_id, $post->ID, $sequence + 1);
            update_post_meta($resolution_id, 'resolution_number', $resolution_number);
            update_post_meta($resolution_id, 'published_in_meeting', $post->ID);
            update_post_meta($resolution_id, 'resolution_sequence', $sequence + 1);
        }
        
        // Log the action
        error_log("Published " . count($assigned_resolutions) . " resolutions for meeting: " . $post->post_title);
    }
    
    /**
     * Add custom columns to resolution admin list
     */
    public function add_resolution_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['resolution_number'] = 'Resolution #';
                $new_columns['meeting'] = 'Meeting';
                $new_columns['status'] = 'Status';
            }
        }
        return $new_columns;
    }
    
    /**
     * Populate custom columns in resolution admin list
     */
    public function populate_resolution_columns($column, $post_id) {
        switch ($column) {
            case 'resolution_number':
                $resolution_number = get_post_meta($post_id, 'resolution_number', true);
                if ($resolution_number) {
                    echo esc_html($resolution_number);
                } else {
                    $meeting_id = get_field('assigned_meeting', $post_id);
                    if ($meeting_id && get_post_status($post_id) === 'approved') {
                        echo esc_html($this->generate_resolution_number($post_id, $meeting_id->ID));
                    } else {
                        echo '—';
                    }
                }
                break;
                
            case 'meeting':
                $meeting = get_field('assigned_meeting', $post_id);
                if ($meeting) {
                    echo '<a href="' . get_edit_post_link($meeting->ID) . '">' . esc_html($meeting->post_title) . '</a>';
                } else {
                    echo '—';
                }
                break;
                
            case 'status':
                $status = get_post_status($post_id);
                $status_labels = [
                    'draft' => 'Draft',
                    'submit_approval' => 'Pending Approval',
                    'approved' => 'Approved',
                    'denied' => 'Denied',
                    'publish' => 'Published'
                ];
                $label = $status_labels[$status] ?? ucfirst($status);
                echo '<span class="status-' . $status . '">' . esc_html($label) . '</span>';
                break;
        }
    }
    
    /**
     * Get meeting's consent agenda resolutions
     */
    public function get_meeting_consent_resolutions($meeting_id) {
        $agenda_items = get_field('agenda_items', $meeting_id);
        
        if (empty($agenda_items) || !is_array($agenda_items)) {
            return [];
        }
        
        foreach ($agenda_items as $item) {
            if (($item['acf_fc_layout'] ?? '') === 'consent_agenda') {
                // Get all approved resolutions for this meeting (auto-included)
                $approved_resolutions = get_posts([
                    'post_type' => 'resolution',
                    'post_status' => ['approved', 'publish'],
                    'numberposts' => -1,
                    'meta_query' => [
                        [
                            'key' => 'assigned_meeting',
                            'value' => $meeting_id,
                            'compare' => '='
                        ]
                    ],
                    'orderby' => 'date',
                    'order' => 'ASC',
                    'fields' => 'ids'
                ]);
                
                return $approved_resolutions;
            }
        }
        
        return [];
    }
    
    /**
     * Shortcode to display formatted resolution list for meetings
     */
    public function meeting_resolutions_shortcode($atts) {
        $atts = shortcode_atts([
            'meeting_id' => get_the_ID(),
            'format' => 'list' // list, table, or brief
        ], $atts);
        
        $meeting_id = intval($atts['meeting_id']);
        $resolutions = $this->get_meeting_consent_resolutions($meeting_id);
        
        if (empty($resolutions)) {
            return '<p>No resolutions for this meeting.</p>';
        }
        
        $output = '';
        
        if ($atts['format'] === 'table') {
            $output .= '<table class="meeting-resolutions-table">';
            $output .= '<thead><tr><th>Resolution #</th><th>Title</th><th>Subject</th></tr></thead>';
            $output .= '<tbody>';
            
            foreach ($resolutions as $resolution_id) {
                $formatted_title = $this->get_formatted_resolution_title($resolution_id, $meeting_id);
                $subject = get_field('subject', $resolution_id);
                $resolution_number = $this->generate_resolution_number($resolution_id, $meeting_id);
                
                $output .= '<tr>';
                $output .= '<td>' . esc_html($resolution_number) . '</td>';
                $output .= '<td><a href="' . get_permalink($resolution_id) . '">' . esc_html(get_the_title($resolution_id)) . '</a></td>';
                $output .= '<td>' . esc_html($subject) . '</td>';
                $output .= '</tr>';
            }
            
            $output .= '</tbody></table>';
        } else {
            $output .= '<ul class="meeting-resolutions-list">';
            
            foreach ($resolutions as $resolution_id) {
                $formatted_title = $this->get_formatted_resolution_title($resolution_id, $meeting_id);
                
                if ($atts['format'] === 'brief') {
                    $resolution_number = $this->generate_resolution_number($resolution_id, $meeting_id);
                    $output .= '<li>Resolution #' . esc_html($resolution_number) . '</li>';
                } else {
                    $output .= '<li><a href="' . get_permalink($resolution_id) . '">' . esc_html($formatted_title) . '</a></li>';
                }
            }
            
            $output .= '</ul>';
        }
        
        return $output;
    }
    
    /**
     * Add admin styles for status indicators and tables
     */
    public function add_admin_styles() {
        echo '<style>
            .status-draft { color: #646970; }
            .status-submit_approval { color: #b26900; font-weight: 600; }
            .status-approved { color: #00a32a; font-weight: 600; }
            .status-denied { color: #d63638; font-weight: 600; }
            .status-publish { color: #0073aa; font-weight: 600; }
            
            .meeting-resolutions-table {
                width: 100%;
                border-collapse: collapse;
                margin: 1em 0;
            }
            
            .meeting-resolutions-table th,
            .meeting-resolutions-table td {
                padding: 8px 12px;
                border: 1px solid #ddd;
                text-align: left;
            }
            
            .meeting-resolutions-table th {
                background: #f8f9fa;
                font-weight: 600;
            }
            
            .meeting-resolutions-list {
                list-style-type: decimal;
                padding-left: 2em;
            }
            
            .meeting-resolutions-list li {
                margin-bottom: 0.5em;
            }
            
            .column-resolution_number { width: 100px; }
            .column-meeting { width: 200px; }
            .column-status { width: 120px; }
        </style>';
    }
}

// Initialize the Consent Agenda Manager
new ConsentAgendaManager();