<?php
/**
 * Calendar Integration for Published Meetings
 * File: includes/calendar-integration.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Function to update calendar meta - can be called manually
if (!function_exists('boe_update_meeting_calendar_meta')) {
    function boe_update_meeting_calendar_meta($post_id) {
        // Basic safety checks
        if (!$post_id || get_post_type($post_id) !== 'meeting') {
            return false;
        }
        
        // Only proceed if ACF function exists
        if (!function_exists('get_field')) {
            return false;
        }
        
        $agenda_items = get_field('agenda_items', $post_id);
        
        if (empty($agenda_items) || !is_array($agenda_items)) {
            return false;
        }
        
        $meeting_datetime = '';
        $meeting_location = '';
        $duration_hours = 2.0; // default
        
        // Extract datetime, location, and duration from agenda items
        foreach ($agenda_items as $item) {
            if (is_array($item) && isset($item['acf_fc_layout'])) {
                if ($item['acf_fc_layout'] === 'meeting_datetime' && !empty($item['datetime'])) {
                    $meeting_datetime = $item['datetime'];
                    
                    // Parse duration string (e.g., "2.5 hour" -> 2.5)
                    if (!empty($item['duration'])) {
                        $duration_string = $item['duration'];
                        $duration_hours = floatval(str_replace(' hour', '', $duration_string));
                    }
                }
                if ($item['acf_fc_layout'] === 'location' && !empty($item['location'])) {
                    $meeting_location = $item['location'];
                }
            }
        }
        
        if (!empty($meeting_datetime)) {
            $timestamp = strtotime($meeting_datetime);
            if ($timestamp !== false) {
                $start_date = date('Y-m-d H:i:s', $timestamp);
                $duration_seconds = $duration_hours * 3600;
                $end_date = date('Y-m-d H:i:s', $timestamp + $duration_seconds);
                
                update_post_meta($post_id, 'meeting_start', $start_date);
                update_post_meta($post_id, 'meeting_end', $end_date);
                
                if (!empty($meeting_location)) {
                    update_post_meta($post_id, 'meeting_location_display', $meeting_location);
                }
                
                return true;
            }
        }
        
        return false;
    }
}

// Function to bulk update all published meetings
if (!function_exists('boe_update_all_published_meetings')) {
    function boe_update_all_published_meetings() {
        $meetings = get_posts(array(
            'post_type' => 'meeting',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        $updated = 0;
        foreach ($meetings as $meeting_id) {
            if (boe_update_meeting_calendar_meta($meeting_id)) {
                $updated++;
            }
        }
        
        return $updated;
    }
}

// Hook to update calendar when meetings are saved
add_action('wp_loaded', function() {
    // Only add hooks if we're in admin and ACF is available
    if (is_admin() && function_exists('get_field')) {
        
        // Update calendar meta when meeting is saved as published
        add_action('save_post', function($post_id) {
            if (get_post_type($post_id) === 'meeting') {
                $post = get_post($post_id);
                if ($post && $post->post_status === 'publish') {
                    boe_update_meeting_calendar_meta($post_id);
                }
            }
        }, 20);
        
        // Update calendar meta when meeting status changes to published
        add_action('transition_post_status', function($new_status, $old_status, $post) {
            if ($post->post_type === 'meeting') {
                if ($new_status === 'publish') {
                    boe_update_meeting_calendar_meta($post->ID);
                } elseif ($old_status === 'publish' && $new_status !== 'publish') {
                    // Clear calendar meta if unpublished
                    delete_post_meta($post->ID, 'meeting_start');
                    delete_post_meta($post->ID, 'meeting_end');
                    delete_post_meta($post->ID, 'meeting_location_display');
                }
            }
        }, 10, 3);
        
        // AJAX handler for bulk update
        add_action('wp_ajax_boe_update_all_meetings', function() {
            if (!current_user_can('manage_options')) {
                wp_die('Insufficient permissions');
            }
            
            $updated_count = boe_update_all_published_meetings();
            
            wp_send_json_success(array(
                'message' => "Updated {$updated_count} meetings",
                'count' => $updated_count
            ));
        });
        
        // Admin notice for meetings that need updating
        add_action('admin_notices', function() {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'agenda-builder') !== false) {
                
                // Check if any published meetings are missing calendar data
                $meetings_without_calendar = get_posts(array(
                    'post_type' => 'meeting',
                    'post_status' => 'publish',
                    'numberposts' => 1,
                    'meta_query' => array(
                        array(
                            'key' => 'meeting_start',
                            'compare' => 'NOT EXISTS'
                        )
                    )
                ));
                
                if (!empty($meetings_without_calendar)) {
                    echo '<div class="notice notice-info is-dismissible">';
                    echo '<p><strong>Calendar Integration:</strong> Some published meetings need calendar data updated.</p>';
                    echo '<p><button type="button" class="button" id="boe-update-calendar">Update All Meeting Calendar Data</button></p>';
                    echo '</div>';
                    
                    // Simple JavaScript for the button
                    echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        var btn = document.getElementById("boe-update-calendar");
                        if (btn) {
                            btn.onclick = function() {
                                this.textContent = "Updating...";
                                this.disabled = true;
                                
                                var xhr = new XMLHttpRequest();
                                xhr.open("POST", ajaxurl);
                                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                                xhr.onload = function() {
                                    if (xhr.status === 200) {
                                        try {
                                            var resp = JSON.parse(xhr.responseText);
                                            if (resp.success) {
                                                btn.textContent = "✓ " + resp.data.message;
                                                btn.style.color = "green";
                                                setTimeout(function() { location.reload(); }, 1500);
                                            }
                                        } catch(e) {
                                            btn.textContent = "Error occurred";
                                            btn.style.color = "red";
                                        }
                                    }
                                };
                                xhr.send("action=boe_update_all_meetings");
                            };
                        }
                    });
                    </script>';
                }
            }
        });
    }
});