<?php
/**
 * Updated pie-calendar.php - Replace your existing file with this version
 */

// Only include PUBLISHED meetings that have a meeting_start meta
add_filter('piecal_event_query_args', function($args, $atts) {
    $args['post_status'] = 'publish'; // Only published meetings
    $args['meta_query'] = [
        [
            'key'     => 'meeting_start',
            'value'   => '',
            'compare' => '!=',
        ],
    ];
    return $args;
}, 10, 2);

// Tell Pie Calendar to use these meta-keys for start and end dates
add_filter('piecal_start_date_meta_key', function($key) { 
    return 'meeting_start'; 
});

add_filter('piecal_end_date_meta_key', function($key) { 
    return 'meeting_end'; 
});

// Customize the event display in the calendar
add_filter('piecal_event_title', function($title, $post) {
    if ($post->post_type === 'meeting') {
        // Get meeting location for display
        $location = get_post_meta($post->ID, 'meeting_location_display', true);
        
        if (!empty($location)) {
            $title .= ' @ ' . $location;
        }
        
        // Add meeting type if available
        $meeting_types = wp_get_post_terms($post->ID, 'meeting_type', ['fields' => 'names']);
        if (!empty($meeting_types)) {
            $title = '(' . implode(', ', $meeting_types) . ') ' . $title;
        }
    }
    
    return $title;
}, 10, 2);

// Optionally customize the event link to go to Agenda Builder
add_filter('piecal_event_link', function($link, $post) {
    if ($post->post_type === 'meeting') {
        // Link to Agenda Builder for editing
        return admin_url('admin.php?page=agenda-builder-regular&post_id=' . $post->ID);
    }
    
    return $link;
}, 10, 2);

// Add custom CSS class for meeting events
add_filter('piecal_event_css_class', function($classes, $post) {
    if ($post->post_type === 'meeting') {
        $classes[] = 'meeting-event';
        
        // Add different classes based on meeting type
        $meeting_types = wp_get_post_terms($post->ID, 'meeting_type', ['fields' => 'slugs']);
        foreach ($meeting_types as $type_slug) {
            $classes[] = 'meeting-type-' . $type_slug;
        }
    }
    
    return $classes;
}, 10, 2);

// Function to retroactively update existing published meetings
function update_all_published_meetings_calendar_meta() {
    $meetings = get_posts([
        'post_type' => 'meeting',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_query' => [
            [
                'key' => 'meeting_start',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ]);
    
    foreach ($meetings as $meeting) {
        update_meeting_calendar_meta($meeting->ID);
    }
    
    return count($meetings);
}

// Admin function to manually update calendar meta for all meetings
add_action('wp_ajax_update_meeting_calendar_meta', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $updated_count = update_all_published_meetings_calendar_meta();
    
    wp_send_json_success([
        'message' => "Updated calendar meta for {$updated_count} meetings",
        'count' => $updated_count
    ]);
});

// Add admin notice with button to update existing meetings
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && in_array($screen->id, ['toplevel_page_agenda-builder', 'agenda-builder_page_agenda-builder-regular'])) {
        // Check if there are published meetings without calendar meta
        $meetings_need_update = get_posts([
            'post_type' => 'meeting',
            'post_status' => 'publish',
            'numberposts' => 1,
            'meta_query' => [
                [
                    'key' => 'meeting_start',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        
        if (!empty($meetings_need_update)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Calendar Integration:</strong> Some published meetings need calendar meta data updated.</p>';
            echo '<p><button type="button" class="button button-secondary" id="update-calendar-meta">Update Calendar Data</button></p>';
            echo '</div>';
            
            // Add JavaScript for the button
            echo '<script>
            document.getElementById("update-calendar-meta").addEventListener("click", function() {
                this.textContent = "Updating...";
                this.disabled = true;
                
                fetch(ajaxurl, {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "action=update_meeting_calendar_meta"
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.textContent = "✓ " + data.data.message;
                        this.style.background = "#00a32a";
                        this.style.color = "white";
                        setTimeout(() => location.reload(), 2000);
                    }
                })
                .catch(error => {
                    this.textContent = "Error updating";
                    this.style.background = "#d63638";
                    this.style.color = "white";
                });
            });
            </script>';
        }
    }
});