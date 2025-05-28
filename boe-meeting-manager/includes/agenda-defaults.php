<?php
/**
 * Updated agenda-defaults.php with duration field support for manual field
 * Replace your existing agenda-defaults.php with this version
 */

add_filter('acf/load_value/name=agenda_items', function ($value, $post_id, $field) {
    if (!empty($value) || get_post_type($post_id) !== 'meeting') {
        return $value;
    }
    
    $type = $_GET['template_type'] ?? 'blank';
    
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
                            'description' => 'Discussion/Questions of First Read Board Policies'
                        ],
                        [
                            'title' => 'Monthly Enrollment Reports',
                            'presenter' => '',
                            'time_estimate' => '10 minutes', 
                            'description' => 'Dissemination of Enrollment, Out of District, and Expulsion Information'
                        ],
                        [
                            'title' => 'Administrative Updates',
                            'presenter' => '',
                            'time_estimate' => '15 minutes',
                            'description' => 'Updates from District Administration'
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
                    'text' => 'Motion to approve the agenda for the <###########> Regular Board of Education meeting.'
                ],
                
                // F. Approval of Minutes
                [
                    'acf_fc_layout' => 'approval_of_minutes', 
                    'text' => 'Consent Action - Motion to approve the minutes as listed on the agenda for the <###########> Regular Board of Education meeting.',
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
                
                // H. Special Report (optional - can be removed if not needed)
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
                    'text' => 'Consent Action - Motion to approve the Consent Agenda',
                    'resolutions' => [] // Will be populated with approved resolutions
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
            // Streamlined special meeting agenda
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
            // Study session only agenda
            $default_topics = [
                [
                    'title' => 'Policy Review and Discussion',
                    'presenter' => '',
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
}, 10, 3);

// Helper function to get current month's previous meeting date for minutes
function get_previous_meeting_date() {
    $current_date = new DateTime();
    $current_date->modify('first Monday of this month');
    $current_date->modify('+1 week'); // Second Monday
    
    // If we're past the second Monday, use this month's meeting
    $now = new DateTime();
    if ($now < $current_date) {
        $current_date->modify('-1 month');
    }
    
    return $current_date->format('F j, Y');
}

// Add a filter to auto-populate meeting location based on rotation or preference
add_filter('acf/load_field/name=location', function($field) {
    // You can add logic here to set a default location based on date, rotation, etc.
    $locations = get_option('boe_meeting_locations', []);
    
    if (!empty($locations)) {
        // Set the first location as default for new meetings
        $field['default_value'] = $locations[0];
    }
    
    return $field;
});