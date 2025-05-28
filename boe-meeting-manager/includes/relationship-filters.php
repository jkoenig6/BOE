<?php
/**
 * Only show “approved” Resolution posts in the Consent Agenda relationship UI.
 */
add_filter('acf/fields/relationship/query/name=resolutions', function( $args, $field, $post_id ) {
    // only target the 'resolutions' relationship field on Meeting CPT
    if ( get_post_type( $post_id ) === 'meeting' && $field['name'] === 'resolutions' ) {
        $args['post_status'] = ['approved'];    // only fetch approved ones
        $args['orderby']     = 'date';
        $args['order']       = 'ASC';
    }
    return $args;
}, 10, 3);
