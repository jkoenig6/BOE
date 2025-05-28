<?php
/**
 * Agenda Builder Backend
 * File: includes/agenda-builder.php
 */

class AgendaBuilder {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_save_agenda', [$this, 'save_agenda']);
        add_action('wp_ajax_load_agenda', [$this, 'load_agenda']);
    }
    
    public function add_admin_menu() {
        // Main Agenda Builder menu
        add_menu_page(
            'Agenda Builder',
            'Agenda Builder',
            'edit_others_posts',
            'agenda-builder',
            [$this, 'agenda_builder_page'],
            'dashicons-clipboard',
            25
        );
        
        // Submenu items
        add_submenu_page(
            'agenda-builder',
            'New Meeting',
            'New Meeting',
            'edit_others_posts',
            'agenda-builder-new',
            [$this, 'new_meeting_page']
        );
        
        add_submenu_page(
            'agenda-builder',
            'Regular Meeting',
            'Regular Meeting',
            'edit_others_posts',
            'agenda-builder-regular',
            [$this, 'regular_meeting_page']
        );
        
        add_submenu_page(
            'agenda-builder',
            'Special Meeting',
            'Special Meeting',
            'edit_others_posts',
            'agenda-builder-special',
            [$this, 'special_meeting_page']
        );
        
        add_submenu_page(
            'agenda-builder',
            'Study Session',
            'Study Session',
            'edit_others_posts',
            'agenda-builder-study',
            [$this, 'study_session_page']
        );
        
        // Add Settings submenu
        add_submenu_page(
            'agenda-builder',
            'Settings',
            'Settings', 
            'edit_others_posts',
            'boe-editor-settings',
            'boe_editor_settings_page'  // This function is defined in settings-page.php
        );
    }
    
    public function agenda_builder_page() {
        echo '<div class="wrap"><h1>Agenda Builder</h1>';
        echo '<p>Select a meeting type to get started:</p>';
        echo '<div class="agenda-type-buttons">';
        echo '<a href="' . admin_url('admin.php?page=agenda-builder-new') . '" class="button button-primary button-hero">New Meeting</a>';
        echo '<a href="' . admin_url('admin.php?page=agenda-builder-regular') . '" class="button button-secondary button-hero">Regular Meeting</a>';
        echo '<a href="' . admin_url('admin.php?page=agenda-builder-special') . '" class="button button-secondary button-hero">Special Meeting</a>';
        echo '<a href="' . admin_url('admin.php?page=agenda-builder-study') . '" class="button button-secondary button-hero">Study Session</a>';
        echo '</div></div>';
    }
    
    public function new_meeting_page() {
        $this->render_agenda_builder('blank');
    }
    
    public function regular_meeting_page() {
        $this->render_agenda_builder('regular');
    }
    
    public function special_meeting_page() {
        $this->render_agenda_builder('special');
    }
    
    public function study_session_page() {
        $this->render_agenda_builder('study');
    }
    
    private function render_agenda_builder($template_type) {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $post = $post_id ? get_post($post_id) : null;
        
        // If no post ID, create a new draft
        if (!$post) {
            $post_id = wp_insert_post([
                'post_title' => 'Draft Meeting - ' . date('M j, Y'),
                'post_type' => 'meeting',
                'post_status' => 'draft'
            ]);
            $post = get_post($post_id);
        }
        
        // Get agenda items
        $agenda_items = get_field('agenda_items', $post_id);
        
        // Check if we have a complete agenda or just the basic datetime/location
        $has_complete_agenda = false;
        if (!empty($agenda_items) && is_array($agenda_items)) {
            // Look for agenda items beyond just datetime and location
            foreach ($agenda_items as $item) {
                $layout = $item['acf_fc_layout'] ?? '';
                if (!in_array($layout, ['meeting_datetime', 'location'])) {
                    $has_complete_agenda = true;
                    break;
                }
            }
        }
        
        // If no complete agenda, get template items
        if (!$has_complete_agenda) {
            $agenda_items = $this->get_template_items($template_type);
        }
        
        ?>
        <div class="wrap agenda-builder-wrap">
            <h1>Agenda Builder - <?php echo ucfirst($template_type); ?> Meeting</h1>
            
            <div class="agenda-builder-layout">
                <!-- Left Column: Agenda Items -->
                <div class="agenda-items-column">
                    <div class="agenda-header">
                        <h2>Meeting Details & Agenda Items</h2>
                        <button type="button" class="button add-agenda-item">+ Add Item</button>
                    </div>
                    
                    <!-- Fixed Meeting Header - Not Sortable -->
                    <div class="meeting-header-section">
                        <?php $this->render_meeting_header($agenda_items, $post_id); ?>
                    </div>
                    
                    <!-- Sortable Agenda Items -->
                    <div class="agenda-items-header">
                        <h3>Agenda Items</h3>
                    </div>
                    <div id="agenda-sortable" class="agenda-sortable">
                        <?php $this->render_agenda_items($agenda_items, true); ?>
                    </div>
                </div>
                
                <!-- Right Column: Meeting Details & Actions -->
                <div class="meeting-details-column">
                    <!-- Meeting Title -->
                    <div class="meeting-title-box">
                        <label for="meeting-title">Meeting Title</label>
                        <input type="text" id="meeting-title" value="<?php echo esc_attr($post->post_title); ?>" />
                    </div>
                    
                    <!-- Meeting Type Taxonomy -->
                    <div class="meeting-taxonomy-box">
                        <label>Meeting Type</label>
                        <?php $this->render_taxonomy_selector($post_id); ?>
                    </div>
                    
                    <!-- Publish Box -->
                    <div class="publish-box">
                        <div class="publish-header">
                            <h3>Publish</h3>
                        </div>
                        <div class="publish-content">
                            <div class="status-row">
                                <label>Status:</label>
                                <select id="post-status">
                                    <option value="draft" <?php selected($post->post_status, 'draft'); ?>>Draft</option>
                                    <option value="pending" <?php selected($post->post_status, 'pending'); ?>>Pending Review</option>
                                    <option value="publish" <?php selected($post->post_status, 'publish'); ?>>Published</option>
                                    <option value="private" <?php selected($post->post_status, 'private'); ?>>Private</option>
                                </select>
                            </div>
                            <div class="date-row">
                                <label>Publish Date:</label>
                                <input type="datetime-local" id="post-date" value="<?php echo date('Y-m-d\TH:i', strtotime($post->post_date)); ?>" />
                            </div>
                        </div>
                        <div class="publish-actions">
                            <button type="button" class="button button-large" id="save-draft">Save Draft</button>
                            <button type="button" class="button button-primary button-large" id="publish-meeting">Update Meeting</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Hidden inputs -->
            <input type="hidden" id="post-id" value="<?php echo $post_id; ?>" />
            <input type="hidden" id="template-type" value="<?php echo $template_type; ?>" />
        </div>
        <?php
    }
    
    private function render_meeting_header($agenda_items, $post_id) {
        // Extract meeting datetime, location, and duration from agenda items
        $datetime_value = '';
        $location_value = '';
        $duration_value = '2.0 hour'; // Default
        
        foreach ($agenda_items as $item) {
            if (($item['acf_fc_layout'] ?? '') === 'meeting_datetime') {
                $datetime_value = sanitize_text_field($item['datetime'] ?? '');
                $duration_value = sanitize_text_field($item['duration'] ?? '2.0 hour');
            }
            if (($item['acf_fc_layout'] ?? '') === 'location') {
                $location_value = sanitize_text_field($item['location'] ?? '');
            }
        }
        
        // If no datetime found, set default
        if (empty($datetime_value)) {
            $datetime_value = date('Y-m-d') . ' 19:00:00';
        }
        
        echo '<div class="meeting-header-row">';
        
        // Meeting Date/Time - ✅ FIXED: Escape output
        echo '<div class="meeting-datetime-field">';
        echo '<label>Meeting Date & Time:</label>';
        echo '<input type="datetime-local" id="meeting-datetime" name="meeting_datetime" value="' . esc_attr($datetime_value) . '" />';
        echo '</div>';
        
        // Meeting Duration - ✅ FIXED: Escape output and validate values
        echo '<div class="meeting-duration-field">';
        echo '<label>Duration:</label>';
        echo '<select id="meeting-duration" name="meeting_duration">';
        
        $allowed_durations = [
            '0.5 hour', '1.0 hour', '1.5 hour', '2.0 hour', '2.5 hour', '3.0 hour',
            '3.5 hour', '4.0 hour', '4.5 hour', '5.0 hour', '5.5 hour', '6.0 hour',
            '6.5 hour', '7.0 hour', '7.5 hour', '8.0 hour'
        ];
        
        foreach ($allowed_durations as $duration) {
            $selected = selected($duration_value, $duration, false);
            echo '<option value="' . esc_attr($duration) . '" ' . $selected . '>' . esc_html($duration) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Meeting Location - ✅ FIXED: Escape output
        echo '<div class="meeting-location-field">';
        echo '<label>Meeting Location:</label>';
        echo '<select id="meeting-location" name="meeting_location">';
        echo '<option value="">Select Location...</option>';
        
        $locations = get_option('boe_meeting_locations', []);
        foreach ($locations as $loc) {
            $selected = selected($location_value, $loc, false);
            echo '<option value="' . esc_attr($loc) . '" ' . $selected . '>' . esc_html($loc) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '</div>';
    }
    
    private function render_agenda_items($items, $skip_header_items = false) {
        if (empty($items)) return;
        
        $alpha_counter = 1;
        foreach ($items as $index => $item) {
            $layout = $item['acf_fc_layout'] ?? '';
            
            // Skip meeting_datetime and location if we're rendering sortable items
            if ($skip_header_items && in_array($layout, ['meeting_datetime', 'location'])) {
                continue;
            }
            
            // For sortable items, we need to adjust the index to account for skipped items
            $display_index = $skip_header_items ? $index : $index;
            $alpha_id = $this->number_to_alpha($alpha_counter);
            
            echo '<div class="agenda-item" data-layout="' . esc_attr($layout) . '" data-index="' . $display_index . '">';
            echo '<div class="agenda-item-header">';
            echo '<span class="alpha-identifier">' . $alpha_id . '</span>';
            echo '<span class="item-title">' . $this->get_item_title($layout) . '</span>';
            echo '<div class="item-controls">';
            echo '<button type="button" class="toggle-item">▼</button>';
            echo '<button type="button" class="remove-item">×</button>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="agenda-item-content">';
            $this->render_item_fields($layout, $item, $display_index);
            echo '</div>';
            echo '</div>';
            
            $alpha_counter++;
        }
    }
    
    private function render_item_fields($layout, $data, $index) {
        switch ($layout) {
            case 'meeting_datetime':
                echo '<label>Date & Time:</label>';
                echo '<input type="datetime-local" name="agenda_items[' . $index . '][datetime]" value="' . esc_attr($data['datetime'] ?? '') . '" />';
                echo '<label>Duration:</label>';
                echo '<select name="agenda_items[' . $index . '][duration]">';
                $durations = [
                    '0.5 hour' => '0.5 hour',
                    '1.0 hour' => '1.0 hour', 
                    '1.5 hour' => '1.5 hour',
                    '2.0 hour' => '2.0 hour',
                    '2.5 hour' => '2.5 hour',
                    '3.0 hour' => '3.0 hour',
                    '3.5 hour' => '3.5 hour',
                    '4.0 hour' => '4.0 hour',
                    '4.5 hour' => '4.5 hour',
                    '5.0 hour' => '5.0 hour',
                    '5.5 hour' => '5.5 hour',
                    '6.0 hour' => '6.0 hour',
                    '6.5 hour' => '6.5 hour',
                    '7.0 hour' => '7.0 hour',
                    '7.5 hour' => '7.5 hour',
                    '8.0 hour' => '8.0 hour'
                ];
                foreach ($durations as $value => $label) {
                    $selected = selected($data['duration'] ?? '2.0 hour', $value, false);
                    echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'location':
                echo '<label>Location:</label>';
                echo '<select name="agenda_items[' . $index . '][location]">';
                echo '<option value="">Select Location...</option>';
                $locations = get_option('boe_meeting_locations', []);
                foreach ($locations as $loc) {
                    $selected = selected($data['location'] ?? '', $loc, false);
                    echo '<option value="' . esc_attr($loc) . '" ' . $selected . '>' . esc_html($loc) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'study_session':
                echo '<label>Start Time:</label>';
                echo '<input type="time" name="agenda_items[' . $index . '][start_time]" value="' . esc_attr($data['start_time'] ?? '') . '" />';
                echo '<label>Topics:</label>';
                echo '<div class="study-topics">';
                $topics = $data['topics'] ?? [];
                
                if (empty($topics)) {
                    // Add one empty topic row if none exist
                    $topics = [['title' => '', 'presenter' => '', 'time_estimate' => '', 'description' => '']];
                }
                
                foreach ($topics as $t_index => $topic) {
                    echo '<div class="topic-row">';
                    echo '<div class="topic-inputs">';
                    echo '<input type="text" placeholder="Topic Title" name="agenda_items[' . $index . '][topics][' . $t_index . '][title]" value="' . esc_attr($topic['title'] ?? '') . '" />';
                    echo '<input type="text" placeholder="Presenter" name="agenda_items[' . $index . '][topics][' . $t_index . '][presenter]" value="' . esc_attr($topic['presenter'] ?? '') . '" />';
                    echo '<input type="text" placeholder="Time Estimate" name="agenda_items[' . $index . '][topics][' . $t_index . '][time_estimate]" value="' . esc_attr($topic['time_estimate'] ?? '') . '" />';
                    echo '</div>';
                    echo '<textarea placeholder="Description/Notes" name="agenda_items[' . $index . '][topics][' . $t_index . '][description]" rows="2">' . esc_textarea($topic['description'] ?? '') . '</textarea>';
                    echo '<button type="button" class="remove-topic">Remove Topic</button>';
                    echo '</div>';
                }
                echo '<button type="button" class="add-topic">+ Add Topic</button>';
                echo '</div>';
                break;
                
            case 'approval_of_minutes':
                echo '<label>Minutes to Approve:</label>';
                echo '<div class="minutes-list">';
                $minutes = $data['minutes_list'] ?? [];
                
                if (empty($minutes)) {
                    $minutes = [['title' => '', 'link' => '']];
                }
                
                foreach ($minutes as $m_index => $minute) {
                    echo '<div class="minute-row">';
                    echo '<input type="text" placeholder="Minutes Title" name="agenda_items[' . $index . '][minutes_list][' . $m_index . '][title]" value="' . esc_attr($minute['title'] ?? '') . '" />';
                    echo '<input type="url" placeholder="Link to Minutes" name="agenda_items[' . $index . '][minutes_list][' . $m_index . '][link]" value="' . esc_attr($minute['link'] ?? '') . '" />';
                    echo '<button type="button" class="remove-minute">×</button>';
                    echo '</div>';
                }
                echo '<button type="button" class="add-minute">+ Add Minutes</button>';
                echo '</div>';
                break;
                
            case 'communications':
                echo '<label>Communications:</label>';
                echo '<div class="communications-list">';
                $messages = $data['messages'] ?? [];
                
                if (empty($messages)) {
                    $messages = [['source' => 'Board of Education', 'message' => ''], ['source' => 'Superintendent', 'message' => '']];
                }
                
                foreach ($messages as $c_index => $comm) {
                    echo '<div class="communication-row">';
                    echo '<input type="text" placeholder="Source" name="agenda_items[' . $index . '][messages][' . $c_index . '][source]" value="' . esc_attr($comm['source'] ?? '') . '" />';
                    echo '<textarea placeholder="Message/Notes" name="agenda_items[' . $index . '][messages][' . $c_index . '][message]" rows="2">' . esc_textarea($comm['message'] ?? '') . '</textarea>';
                    echo '<button type="button" class="remove-communication">×</button>';
                    echo '</div>';
                }
                echo '<button type="button" class="add-communication">+ Add Communication</button>';
                echo '</div>';
                break;
                
            case 'consent_agenda':
                $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : $this->get_current_post_id();
                
                echo '<div class="consent-agenda-section">';
                echo '<label>Approved Resolutions for this Meeting:</label>';
                
                // Get approved resolutions for this meeting
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
                
                if (empty($approved_resolutions)) {
                    echo '<div class="no-resolutions-notice">';
                    echo '<p><em>No approved resolutions assigned to this meeting yet.</em></p>';
                    echo '<p>Resolutions will appear here automatically once they are:</p>';
                    echo '<ul>';
                    echo '<li>1. Created and assigned to this meeting</li>';
                    echo '<li>2. Submitted for approval</li>';
                    echo '<li>3. Approved by administrators</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="resolutions-auto-list">';
                    echo '<p class="info-text"><strong>The following ' . count($approved_resolutions) . ' approved resolution(s) will be included in the Consent Agenda:</strong></p>';
                    
                    echo '<div class="resolutions-preview-list">';
                    foreach ($approved_resolutions as $resolution) {
                        // Get the consent agenda manager to generate resolution number
                        if (class_exists('ConsentAgendaManager')) {
                            $consent_manager = new ConsentAgendaManager();
                            $resolution_number = $consent_manager->generate_resolution_number($resolution->ID, $post_id);
                        } else {
                            $resolution_number = '00.0.0'; // Fallback
                        }
                        
                        $subject = get_field('subject', $resolution->ID);
                        $fiscal_impact = get_field('fiscal_impact', $resolution->ID);
                        
                        echo '<div class="resolution-preview-item">';
                        echo '<div class="resolution-header">';
                        echo '<span class="resolution-number">' . esc_html($resolution_number) . '</span>';
                        echo '<span class="resolution-title">' . esc_html($resolution->post_title) . '</span>';
                        echo '</div>';
                        echo '<div class="resolution-meta">';
                        echo '<span class="subject"><strong>Subject:</strong> ' . esc_html($subject) . '</span>';
                        if ($fiscal_impact) {
                            echo '<span class="fiscal-badge">Fiscal Impact</span>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    
                    echo '<div class="consent-agenda-note">';
                    echo '<p><strong>Note:</strong> These resolutions are automatically included. When this meeting is published, all listed resolutions will also be published with their assigned resolution numbers.</p>';
                    echo '</div>';
                }
                
                // Hidden field to store the resolution IDs (for form processing)
                $resolution_ids = wp_list_pluck($approved_resolutions, 'ID');
                echo '<input type="hidden" name="agenda_items[' . $index . '][resolutions]" value="' . esc_attr(implode(',', $resolution_ids)) . '" class="auto-resolutions-field" />';
                
                echo '</div>';
                break;
                
            case 'future_consideration':
                echo '<label>Items for Future Consideration:</label>';
                echo '<div class="future-items-list">';
                $items = $data['items'] ?? [];
                
                if (empty($items)) {
                    $items = [['title' => '', 'link' => '']];
                }
                
                foreach ($items as $f_index => $item) {
                    echo '<div class="future-item-row">';
                    echo '<input type="text" placeholder="Item Title" name="agenda_items[' . $index . '][items][' . $f_index . '][title]" value="' . esc_attr($item['title'] ?? '') . '" />';
                    echo '<input type="url" placeholder="Link (optional)" name="agenda_items[' . $index . '][items][' . $f_index . '][link]" value="' . esc_attr($item['link'] ?? '') . '" />';
                    echo '<button type="button" class="remove-future-item">×</button>';
                    echo '</div>';
                }
                echo '<button type="button" class="add-future-item">+ Add Item</button>';
                echo '</div>';
                break;
                
            default:
                // Generic text field for other layouts
                $field_name = $this->get_text_field_name($layout);
                echo '<label>' . ucfirst(str_replace('_', ' ', $field_name)) . ':</label>';
                echo '<textarea name="agenda_items[' . $index . '][' . $field_name . ']" rows="3" placeholder="Enter details for this agenda item...">' . esc_textarea($data[$field_name] ?? $data['text'] ?? '') . '</textarea>';
                break;
        }
    }
    
    private function get_current_post_id() {
        return isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    }
    
    private function get_text_field_name($layout) {
        $field_names = [
            'call_to_order' => 'details',
            'pledge_of_allegiance' => 'text',
            'land_acknowledgment' => 'text',
            'approval_of_agenda' => 'text',
            'special_report' => 'text',
            'audience_comments' => 'text',
            'other_business' => 'text',
            'adjournment' => 'text'
        ];
        
        return $field_names[$layout] ?? 'text';
    }
    
    private function render_taxonomy_selector($post_id) {
        $taxonomy = 'meeting_type';
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        $post_terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
        
        echo '<select name="meeting_type[]" multiple>';
        foreach ($terms as $term) {
            $selected = in_array($term->term_id, $post_terms) ? 'selected' : '';
            // ✅ FIXED: Escape all output
            echo '<option value="' . esc_attr($term->term_id) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select>';
    }
    
    private function get_template_items($type) {
        switch ($type) {
            case 'regular':
                // Default Date/Time to today 7:00 pm
                $default_dt = date('Y-m-d') . ' 19:00:00';
                
                return [
                    // Meeting Date/Time (will be moved to header)
                    [
                        'acf_fc_layout' => 'meeting_datetime', 
                        'datetime' => $default_dt,
                        'duration' => '2.0 hour'
                    ],
                    
                    // Meeting Location (will be moved to header)
                    [
                        'acf_fc_layout' => 'location', 
                        'location' => ''
                    ],
                    
                    // A. Study Session
                    [
                        'acf_fc_layout' => 'study_session', 
                        'start_time' => '17:30',
                        'topics' => [
                            [
                                'title' => 'First Read Board Policies Review',
                                'presenter' => '',
                                'time_estimate' => '15 minutes',
                                'description' => 'Discussion and questions regarding first read policies'
                            ],
                            [
                                'title' => 'Monthly Enrollment Report',
                                'presenter' => '',
                                'time_estimate' => '10 minutes', 
                                'description' => 'Review of current enrollment data and trends'
                            ],
                            [
                                'title' => 'Administrative Updates',
                                'presenter' => '',
                                'time_estimate' => '15 minutes',
                                'description' => 'Updates from district administration'
                            ]
                        ]
                    ],
                    
                    // B. Call to Order
                    [
                        'acf_fc_layout' => 'call_to_order', 
                        'details' => 'Call to order at 7:00 p.m.'
                    ],
                    
                    // C. Pledge of Allegiance
                    [
                        'acf_fc_layout' => 'pledge_of_allegiance', 
                        'text' => 'Pledge of Allegiance'
                    ],
                    
                    // D. Land Acknowledgment
                    [
                        'acf_fc_layout' => 'land_acknowledgment', 
                        'text' => 'Land Acknowledgment'
                    ],
                    
                    // E. Approval of Agenda
                    [
                        'acf_fc_layout' => 'approval_of_agenda', 
                        'text' => 'Motion to approve the agenda for the Regular Board of Education meeting.'
                    ],
                    
                    // F. Approval of Minutes
                    [
                        'acf_fc_layout' => 'approval_of_minutes', 
                        'minutes_list' => [
                            [
                                'title' => 'Minutes for the Previous Regular Board of Education meeting',
                                'link' => ''
                            ],
                            [
                                'title' => 'Minutes for any Special Board of Education meetings (if applicable)',
                                'link' => ''
                            ]
                        ]
                    ],
                    
                    // G. Communications
                    [
                        'acf_fc_layout' => 'communications', 
                        'messages' => [
                            [
                                'source' => 'Board of Education',
                                'message' => ''
                            ],
                            [
                                'source' => 'Superintendent',
                                'message' => ''
                            ]
                        ]
                    ],
                    
                    // H. Special Report
                    [
                        'acf_fc_layout' => 'special_report', 
                        'text' => 'Special Report - (Topic to be determined)'
                    ],
                    
                    // I. Audience Comments
                    [
                        'acf_fc_layout' => 'audience_comments', 
                        'text' => 'Public comments and questions from community members'
                    ],
                    
                    // J. Consent Agenda
                    [
                        'acf_fc_layout' => 'consent_agenda', 
                        'resolutions' => []
                    ],
                    
                    // K. Information for Future Consideration
                    [
                        'acf_fc_layout' => 'future_consideration', 
                        'items' => [
                            [
                                'title' => 'Items for Future Consideration',
                                'link' => ''
                            ]
                        ]
                    ],
                    
                    // L. Other Business
                    [
                        'acf_fc_layout' => 'other_business', 
                        'text' => 'Other business items not listed elsewhere on the agenda'
                    ],
                    
                    // M. Adjournment
                    [
                        'acf_fc_layout' => 'adjournment', 
                        'text' => 'Motion to adjourn the Regular Board of Education meeting.'
                    ]
                ];
                
            case 'special':
                return [
                    [
                        'acf_fc_layout' => 'meeting_datetime', 
                        'datetime' => date('Y-m-d') . ' 19:00:00',
                        'duration' => '1.5 hour'
                    ],
                    [
                        'acf_fc_layout' => 'location', 
                        'location' => ''
                    ],
                    [
                        'acf_fc_layout' => 'call_to_order', 
                        'details' => 'Call to order'
                    ],
                    [
                        'acf_fc_layout' => 'approval_of_agenda', 
                        'text' => 'Motion to approve the agenda for the Special Board of Education meeting.'
                    ],
                    [
                        'acf_fc_layout' => 'audience_comments', 
                        'text' => 'Public comments (if time permits)'
                    ],
                    [
                        'acf_fc_layout' => 'consent_agenda', 
                        'resolutions' => []
                    ],
                    [
                        'acf_fc_layout' => 'adjournment', 
                        'text' => 'Motion to adjourn the Special Board of Education meeting.'
                    ]
                ];
                
            case 'study':
            case 'study-session':
                $default_topics = [
                    [
                        'title' => 'Policy Review and Discussion',
                        'time_estimate' => '30 minutes',
                        'description' => 'Review of proposed policy changes'
                    ],
                    [
                        'title' => 'Budget Planning Discussion',
                        'presenter' => '',
                        'time_estimate' => '45 minutes',
                        'description' => 'Discussion of upcoming budget considerations'
                    ],
                    [
                        'title' => 'Strategic Planning Update',
                        'presenter' => '',
                        'time_estimate' => '20 minutes',
                        'description' => 'Updates on district strategic initiatives'
                    ]
                ];
                
                return [
                    [
                        'acf_fc_layout' => 'meeting_datetime', 
                        'datetime' => date('Y-m-d') . ' 17:30:00',
                        'duration' => '1.0 hour'
                    ],
                    [
                        'acf_fc_layout' => 'location', 
                        'location' => ''
                    ],
                    [
                        'acf_fc_layout' => 'study_session', 
                        'start_time' => '17:30', 
                        'topics' => $default_topics
                    ],
                    [
                        'acf_fc_layout' => 'adjournment', 
                        'text' => 'End of study session'
                    ]
                ];
                
            case 'blank':
            default:
                return [
                    [
                        'acf_fc_layout' => 'meeting_datetime', 
                        'datetime' => date('Y-m-d') . ' 19:00:00',
                        'duration' => '2.0 hour'
                    ],
                    [
                        'acf_fc_layout' => 'location', 
                        'location' => ''
                    ]
                ];
        }
    }
    
    private function get_item_title($layout) {
        $titles = [
            'meeting_datetime' => 'Meeting Date/Time',
            'location' => 'Location',
            'study_session' => 'Study Session',
            'call_to_order' => 'Call to Order',
            'pledge_of_allegiance' => 'Pledge of Allegiance',
            'land_acknowledgment' => 'Land Acknowledgment',
            'approval_of_agenda' => 'Approval of Agenda',
            'approval_of_minutes' => 'Approval of Minutes',
            'communications' => 'Communications',
            'special_report' => 'Special Report',
            'audience_comments' => 'Audience Comments',
            'consent_agenda' => 'Consent Agenda',
            'future_consideration' => 'Information for Future Consideration',
            'other_business' => 'Other Business',
            'adjournment' => 'Adjournment'
        ];
        
        return $titles[$layout] ?? ucfirst(str_replace('_', ' ', $layout));
    }
    
    private function number_to_alpha($number) {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)) . $letters;
            $number = intval($number / 26);
        }
        return $letters;
    }
    
    private function sanitize_agenda_items($items) {
        if (!is_array($items)) {
            return [];
        }
        
        $clean_items = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            
            $clean_item = [];
            foreach ($item as $key => $value) {
                $clean_key = sanitize_key($key);
                
                if (is_array($value)) {
                    $clean_item[$clean_key] = $this->sanitize_agenda_items($value);
                } else {
                    // Different sanitization based on field type
                    switch ($clean_key) {
                        case 'acf_fc_layout':
                            $clean_item[$clean_key] = sanitize_key($value);
                            break;
                        case 'datetime':
                            $clean_item[$clean_key] = sanitize_text_field($value);
                            break;
                        case 'duration':
                        case 'location':
                            $clean_item[$clean_key] = sanitize_text_field($value);
                            break;
                        case 'text':
                        case 'details':
                        case 'description':
                        case 'message':
                            $clean_item[$clean_key] = wp_kses_post($value);
                            break;
                        case 'link':
                            $clean_item[$clean_key] = esc_url_raw($value);
                            break;
                        default:
                            $clean_item[$clean_key] = sanitize_text_field($value);
                            break;
                    }
                }
            }
            $clean_items[] = $clean_item;
        }
        
        return $clean_items;
    }
    
    public function save_agenda() {
        check_ajax_referer('agenda_builder_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // ✅ FIXED: Sanitize all inputs
        $post_id = intval($_POST['post_id'] ?? 0);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $status = sanitize_key($_POST['status'] ?? 'draft');
        $date = sanitize_text_field($_POST['date'] ?? '');
        $agenda_items = $_POST['agenda_items'] ?? [];
        $meeting_types = $_POST['meeting_type'] ?? [];
        
        // ✅ ADDED: Validate post exists and user can edit it
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // ✅ ADDED: Validate status
        $allowed_statuses = ['draft', 'pending', 'publish', 'private'];
        if (!in_array($status, $allowed_statuses)) {
            $status = 'draft';
        }
        
        // ✅ FIXED: Sanitize meeting types
        $clean_meeting_types = [];
        if (is_array($meeting_types)) {
            foreach ($meeting_types as $type_id) {
                $clean_id = intval($type_id);
                if ($clean_id > 0) {
                    $clean_meeting_types[] = $clean_id;
                }
            }
        }
        
        // ✅ FIXED: Recursively sanitize agenda items
        $clean_agenda_items = $this->sanitize_agenda_items($agenda_items);
        
        // Update post
        $result = wp_update_post([
            'ID' => $post_id,
            'post_title' => $title,
            'post_status' => $status,
            'post_date' => $date
        ]);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Failed to update meeting: ' . $result->get_error_message());
        }
        
        // Update taxonomy
        wp_set_post_terms($post_id, $clean_meeting_types, 'meeting_type');
        
        // Update agenda items
        update_field('agenda_items', $clean_agenda_items, $post_id);
        
        wp_send_json_success(['message' => 'Meeting saved successfully', 'post_id' => $post_id]);
    }
}

// Initialize the AgendaBuilder class
function initialize_agenda_builder() {
    new AgendaBuilder();
}

// Hook the initialization to the appropriate WordPress action
add_action('init', 'initialize_agenda_builder');