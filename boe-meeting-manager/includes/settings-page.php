<?php
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=meeting',
        'Editor Settings',
        'Settings',
        'edit_others_posts',
        'boe-editor-settings',
        'boe_editor_settings_page'
    );
});

function boe_editor_settings_page() {
    if (!current_user_can('edit_others_posts')) return;

    // Editor mode settings - UPDATED: Removed policy references
    $meeting_post_types = ['meeting', 'resolution']; // Only meeting-related post types
    $settings = get_option('boe_editor_modes', []);
    $locations = get_option('boe_meeting_locations', []);
    
    echo '<div class="wrap"><h1>BOE Meeting Manager Settings</h1><form method="post">';
    wp_nonce_field('boe_editor_settings');

    echo '<h2>Editor Mode</h2>';
    echo '<p>Configure the editor type for meeting-related content:</p>';
    
    foreach ($meeting_post_types as $post_type) {
        $post_type_obj = get_post_type_object($post_type);
        if ($post_type_obj) {
            $label = $post_type_obj->labels->singular_name;
            $checked = isset($settings[$post_type]) && $settings[$post_type] === 'block' ? 'checked' : '';
            echo "<p><label><input type='checkbox' name='boe_editor_modes[{$post_type}]' value='block' {$checked}> Enable Block Editor for <strong>{$label}</strong></label></p>";
        }
    }

    echo '<h2>Meeting Locations</h2>';
    echo '<p>Define common meeting locations (one per line):</p>';
    echo '<p><label for="boe_meeting_locations">Meeting Locations:</label><br>';
    echo '<textarea name="boe_meeting_locations" rows="5" cols="50">'. esc_textarea(implode("\n", $locations)) .'</textarea></p>';

    submit_button('Save Settings', 'primary', 'submit');
    echo '</form></div>';
}

add_action('admin_init', function () {
    if (!current_user_can('edit_others_posts')) return;
    
    // Single handler for the entire form
    if (isset($_POST['submit']) && check_admin_referer('boe_editor_settings')) {
        
        // Handle editor modes - UPDATED: Only for meeting-related post types
        $meeting_post_types = ['meeting', 'resolution'];
        $editor_modes = [];
        
        // Default all to classic
        foreach ($meeting_post_types as $post_type) {
            $editor_modes[$post_type] = 'classic';
        }
        
        // Override with block for checked items
        if (isset($_POST['boe_editor_modes'])) {
            foreach ($_POST['boe_editor_modes'] as $post_type => $mode) {
                if ($mode === 'block' && in_array($post_type, $meeting_post_types)) {
                    $editor_modes[sanitize_text_field($post_type)] = 'block';
                }
            }
        }
        
        update_option('boe_editor_modes', $editor_modes);
        
        // Handle meeting locations  
        if (isset($_POST['boe_meeting_locations'])) {
            $raw = explode("\n", sanitize_textarea_field($_POST['boe_meeting_locations']));
            $clean = array_filter(array_map('sanitize_text_field', array_map('trim', $raw)));
            update_option('boe_meeting_locations', $clean);
        }
        
        // Show success message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        });
    }
});

// Populate Location select field dynamically from settings
add_filter('acf/load_field/name=location', function($field) {
    $field['choices'] = [];
    $locations = get_option('boe_meeting_locations', []);
    foreach ($locations as $loc) {
        $field['choices'][$loc] = $loc;
    }
    return $field;
});