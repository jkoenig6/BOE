<?php
/**
 * Policy ACF Fields
 * File: includes/policy-acf-fields.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('acf_add_local_field_group')):

acf_add_local_field_group([
    'key' => 'group_68195e65c2b38',
    'title' => 'Policy Fields',
    'fields' => [
        [
            'key' => 'field_68274ce02f522',
            'label' => 'Code',
            'name' => 'code-policy',
            'aria-label' => '',
            'type' => 'text',
            'instructions' => '',
            'required' => 1,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => ''
            ],
            'default_value' => '',
            'maxlength' => '',
            'allow_in_bindings' => 0,
            'placeholder' => '',
            'prepend' => '',
            'append' => ''
        ],
        [
            'key' => 'field_6828806f5e2c0',
            'label' => 'Title',
            'name' => 'title-policy',
            'aria-label' => '',
            'type' => 'text',
            'instructions' => '',
            'required' => 1,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => ''
            ],
            'default_value' => '',
            'maxlength' => '',
            'allow_in_bindings' => 0,
            'placeholder' => '',
            'prepend' => '',
            'append' => ''
        ],
        [
            'key' => 'field_682732bff04f5',
            'label' => 'Adopted',
            'name' => 'adopted-policy',
            'aria-label' => '',
            'type' => 'date_picker',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => ''
            ],
            'display_format' => 'm/d/Y',
            'return_format' => 'm/d/Y',
            'first_day' => 0,
            'allow_in_bindings' => 0
        ],
        [
            'key' => 'field_68273307f04f6',
            'label' => 'Last Revised',
            'name' => 'last_revised-policy',
            'aria-label' => '',
            'type' => 'date_picker',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => ''
            ],
            'display_format' => 'm/d/Y',
            'return_format' => 'm/d/Y',
            'first_day' => 0,
            'allow_in_bindings' => 0
        ],
        [
            'key' => 'field_68348c24d6b1f',
            'label' => 'Policy',
            'name' => 'policy',
            'aria-label' => '',
            'type' => 'textarea',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => ''
            ],
            'default_value' => '',
            'maxlength' => '',
            'allow_in_bindings' => 0,
            'rows' => '',
            'placeholder' => '',
            'new_lines' => ''
        ],

    ],
    'location' => [
        [
            [
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'policy'
            ]
        ]
    ],
    'menu_order' => 0,
    'position' => 'normal',
    'style' => 'seamless',
    'label_placement' => 'left',
    'instruction_placement' => 'label',
    'hide_on_screen' => [
        'comments',
        'featured_image',
        'send-trackbacks'
    ],
    'active' => true,
    'description' => '',
    'show_in_rest' => 0
]);

endif;