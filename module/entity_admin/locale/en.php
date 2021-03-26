<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Application-level dictionary (string bundle)                  //
// Date: 2015-11-24                                                           //
// Unless otherwise specified, all strings are plain text (e.g. not HTML)     //
////////////////////////////////////////////////////////////////////////////////

$DICT = array(
    // Actions
    'ACTION_change_locale' => 'Language',
    'ACTION_change_order' => 'Change Order',

    // Labels
    'LABEL_image' => 'Image',
    'LABEL_no_image' => 'No Image',
    'LABEL_publicized' => 'Publicly Accessible',
    'LABEL_record_id' => 'ID',
    'LABEL_url' => 'URL',

    // Descriptions
    'DESCRIPTION_change_order' => 'Drag and drop the items to change their order.',
    'DESCRIPTION_preview_exception' => 'Unable to preview Please close this window and make sure all required fields have been correctly entered.',

    // Errors
    'ERROR_content_blank' => 'Please enter the content.',
    'ERROR_filesize_get' => 'Error getting file size.',
    'ERROR_image_blank' => 'Please select the image.',
    'ERROR_locale_blank' => 'Please select the locale.',
    'ERROR_locale_table_blank' => 'Please enter data for at least one locale.',
    'ERROR_name_blank' => 'Please enter the name.',
    'ERROR_name_used' => 'Please enter another name as it has been used.',

    // Formats
    'FORMAT_new_entity' => 'New %s',
    'FORMAT_locale_name_blank' => 'Please enter the name for the following locales: %s.',
    'FORMAT_locale_name_used' => 'Please enter another name as it has been used for the following locales: %s.',

    // Sets
    'SET_operations' => array(
        'change_order' => 'Changing Order of %s',
    ),
    'SET_panel_icons' => array(
        'basic' => 'icon-info-sign',
        'details' => 'icon-align-justify',
        'global' => 'icon-globe',
        'more' => 'icon-chevron-right',
    ),
    'SET_panels' => array(
        'basic' => 'Basic Info',
        'details' => 'Details',
        'global' => 'Global',
        'more' => 'More',
    ),
    'SET_statuses' => array(
        'draft' => 'Draft',
        'pending' => 'Pending Approval',
        'approved' => 'Approved'
    )
);
