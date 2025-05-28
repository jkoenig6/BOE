<?php
/**
 * Updated menu-meeting-templates.php to integrate with Agenda Builder
 * Replace your existing menu-meeting-templates.php with this version
 */

// Remove the old submenu additions and replace with Agenda Builder integration
add_action('admin_menu', function () {
    // Keep the taxonomy menu item for Meeting Types
    add_submenu_page(
        'agenda-builder', // Changed parent to agenda-builder instead of meetings
        'Meeting Types',
        'Meeting Types',
        'edit_others_posts',
        'edit-tags.php?taxonomy=meeting_type&post_type=meeting'
    );
    
    // Add a link back to the regular meetings list
    add_submenu_page(
        'agenda-builder',
        'All Meetings',
        'All Meetings',
        'edit_others_posts',
        'edit.php?post_type=meeting'
    );
    
}, 20);

// Modify the original meetings menu to be less prominent
add_action('admin_menu', function() {
    global $menu, $submenu;
    
    // Change the main Meetings menu to be more of a "backend" option
    foreach ($menu as $key => $item) {
        if ($item[2] === 'edit.php?post_type=meeting') {
            $menu[$key][0] = 'Meetings (List)'; // Change the title
            $menu[$key][6] = 'dashicons-list-view'; // Change icon
            break;
        }
    }
    
    // Clean up the meetings submenu to keep it minimal
    if (isset($submenu['edit.php?post_type=meeting'])) {
        $new_submenu = [];
        foreach ($submenu['edit.php?post_type=meeting'] as $item) {
            // Only keep essential items
            if (in_array($item[2], [
                'edit.php?post_type=meeting',
                'edit-tags.php?taxonomy=meeting_type&post_type=meeting'
            ])) {
                $new_submenu[] = $item;
            }
        }
        $submenu['edit.php?post_type=meeting'] = $new_submenu;
    }
}, 999);

// Add admin notice to guide users to the new Agenda Builder
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'meeting') {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>New!</strong> Use the enhanced <a href="' . admin_url('admin.php?page=agenda-builder') . '">Agenda Builder</a> for a better meeting creation experience.</p>';
        echo '</div>';
    }
});

// Redirect old template URLs to new Agenda Builder
add_action('admin_init', function() {
    if (isset($_GET['post_type']) && $_GET['post_type'] === 'meeting' && 
        isset($_GET['template_type']) && 
        strpos($_SERVER['REQUEST_URI'], 'post-new.php') !== false) {
        
        $template_type = sanitize_text_field($_GET['template_type']);
        $redirect_url = admin_url("admin.php?page=agenda-builder-{$template_type}");
        wp_safe_redirect($redirect_url);
        exit;
    }
});

// Redirect meeting edit links to Agenda Builder
add_filter('post_row_actions', function($actions, $post) {
    if ($post->post_type === 'meeting') {
        // Replace the default Edit link with Agenda Builder link
        if (isset($actions['edit'])) {
            $agenda_builder_url = admin_url('admin.php?page=agenda-builder-regular&post_id=' . $post->ID);
            $actions['edit'] = '<a href="' . $agenda_builder_url . '">Edit in Agenda Builder</a>';
        }
        
        // Add a separate link to the regular WordPress editor if needed
        $wp_edit_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
        $actions['wp_edit'] = '<a href="' . $wp_edit_url . '">WordPress Editor</a>';
    }
    return $actions;
}, 10, 2);

// Also handle the page title edit link
add_filter('get_edit_post_link', function($link, $post_id, $context) {
    $post = get_post($post_id);
    if ($post && $post->post_type === 'meeting' && $context === 'display') {
        return admin_url('admin.php?page=agenda-builder-regular&post_id=' . $post_id);
    }
    return $link;
}, 10, 3);