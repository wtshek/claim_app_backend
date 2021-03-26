<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Application-level dictionary (string bundle)                  //
// Date: 2008-12-11                                                           //
// Unless otherwise specified, all strings are plain text (e.g. not HTML)          //
////////////////////////////////////////////////////////////////////////////////

$DICT = array(
    // Labels
    'LABEL_vanity_from' => 'From',
    'LABEL_redirect_to' => 'To',
    'LABEL_description' => 'Description',
    'LABEL_start_date' => 'Start date',
    'LABEL_end_date' => 'End date',
    'LABEL_active' => 'Active',

    'LABEL_vanity_urls' => 'Update Vanity URLs',
    
	'LABEL_internal_page' => 'Select Internal Page',
	'LABEL_webpage' => 'Webpages',

	//Actions
	'ACTION_select' => 'Select',

    // Errors
    'ERROR_vanity_url_alias_empty' => 'Please enter the start point of the vanity URL.',
    'ERROR_vanity_url_alias_duplicate' => 'Please enter a unique vanity URL.',
    'ERROR_vanity_url_alias_invalid' => 'Please enter a valid vanity URL, ? * = \' " # / \ & % and ^ are not allowed.',
    'ERROR_redirect_to_empty' => "Please enter the redirect URL.",
    'ERROR_redirect_to_invalid' => 'Please enter a valid redirect URL, only alphabets, digits, -, _ and / are allowed.',
    'ERROR_start_date_empty' => 'Please select start date.',
    'ERROR_date_range_invalid' => 'Please select valid range of date.',
    
	// Message
	'MESSAGE_confirm_to_delete' => 'Please confirm to delete the selected vanity URL record.',

    // Sets
    'SET_active_status' => array(
        '1' => ''
    ),
    'SET_active_status_view' => array(
        '1' => 'Active',
        '0' => 'Inactive'
    )
);

?>