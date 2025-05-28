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

    // Editor mode settings
    $args = ['public' => true, '_builtin' => false];
    $post_types = get_post_types($args, 'objects');
    $settings = get_option('boe_editor_modes', []);
    $locations = get_option('boe_meeting_locations', []);
    echo '<div class="wrap"><h1>Settings</h1><form method="post">';
    wp_nonce_field('boe_editor_settings');

    echo '<h2>Editor Mode</h2>';
    foreach ($post_types as $post_type => $obj) {
        $label = $obj->labels->singular_name;
        $checked = isset($settings[$post_type]) && $settings[$post_type] === 'block' ? 'checked' : '';
        echo "<p><label><input type='checkbox' name='boe_editor_modes[{$post_type}]' value='block' {$checked}> Enable Block Editor for <strong>{$label}</strong></label></p>";
    }

    echo '<h2>Meeting Locations</h2>';
    echo '<p><label for="boe_meeting_locations">One location per line:</label><br>';
    echo '<textarea name="boe_meeting_locations" rows="5" cols="50">'. esc_textarea(implode("\n", $locations)) .'</textarea></p>';

    submit_button('Save Settings', 'primary', 'submit');  // Make sure it has name="submit"
    echo '</form></div>';
}

add_action('admin_init', function () {
    if (!current_user_can('edit_others_posts')) return;
    
    // Single handler for the entire form
    if (isset($_POST['submit']) && check_admin_referer('boe_editor_settings')) {
        
        // Handle editor modes
        $args = ['public' => true, '_builtin' => false];
        $post_types = get_post_types($args, 'objects');
        $editor_modes = [];
        
        // Default all to classic
        foreach ($post_types as $post_type => $obj) {
            $editor_modes[$post_type] = 'classic';
        }
        
        // Override with block for checked items
        if (isset($_POST['boe_editor_modes'])) {
            foreach ($_POST['boe_editor_modes'] as $post_type => $mode) {
                if ($mode === 'block') {
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
        
        // Debug - remove this after testing
        error_log("Settings saved - Editor modes: " . print_r($editor_modes, true));
    }
});

// Control editor usage per CPT
add_filter('use_block_editor_for_post_type', function ($use_block, $post_type) {
    $post_obj = get_post_type_object($post_type);
    if (!$post_obj || !$post_obj->public || $post_obj->_builtin) {
        return $use_block;
    }
    $settings = get_option('boe_editor_modes', []);
    if (isset($settings[$post_type])) {
        return $settings[$post_type] === 'block';
    }
    return false;
}, 10, 2);

// Populate Location select field dynamically from settings
add_filter('acf/load_field/name=location', function($field) {
    $field['choices'] = [];
    $locations = get_option('boe_meeting_locations', []);
    foreach ($locations as $loc) {
        $field['choices'][$loc] = $loc;
    }
    return $field;
});
