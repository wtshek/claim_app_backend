<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Application-level dictionary (string bundle)                  //
// Date: 2015-11-24                                                           //
// Unless otherwise specified, all strings are plain text (e.g. not HTML)     //
////////////////////////////////////////////////////////////////////////////////

$DICT = array(
    // Labels
    'LABEL_album_text_1' => 'Album Text 1',
    'LABEL_album_url_1' => 'Album Link 1',
    'LABEL_album_text_2' => 'Album Text 2',
    'LABEL_album_url_2' => 'Album Link 2',
    'LABEL_album_text_3' => 'Album Text 3',
    'LABEL_album_url_3' => 'Album Link 3',
    'LABEL_announcement_webpages' => 'Specific Web Pages',
    'LABEL_background_position' => 'Background Position',
    'LABEL_pdf' => 'PDF',
    'LABEL_press_release_banners' => 'Banners',
    'LABEL_press_release_dinings' => 'Related Restaurants',
    'LABEL_press_release_rooms' => 'Related Rooms',
    'LABEL_release_date' => 'Release Date',
    'LABEL_release_date_shown_in_detail_page' => 'Release Date Shown in Detail Page',
    'LABEL_session_check' => 'Session Check',
    'LABEL_shown_in_webpages' => 'Shown in Web Pages',
    'LABEL_title' => 'Press Title',

    // Errors
    'ERROR_announcement_webpages_blank' => 'Please select one or more specific web page(s).',
    'ERROR_press_release_banners_image_xs_blank' => 'Please select the image (phone) of the banner(s).',
    'ERROR_release_date_blank' => 'Please select the release date.',
    'ERROR_start_date_blank' => 'Please select the start date.',
    'ERROR_title_blank' => 'Please enter the press title.',

    // Formats
    'FORMAT_locale_title_used' => 'Please enter another title as it has been used for the following locales: %s.',
    'FORMAT_locale_content_blank' => 'Please enter the content for the following locales: %s.',

    // Sets
    'SET_background_positions' => array(
        'left top' => 'Left-top',
        'left center' => 'Left-center',
        'left bottom' => 'Left-bottom',
        'right top' => 'Right-top',
        'right center' => 'Right-center',
        'right bottom' => 'Right-bottom',
        'center top' => 'Center-top',
        'center center' => 'Center-center',
        'center bottom' => 'Center-bottom'
    ),
    'SET_entities' => array(
        'announcement' => 'Announcement',
        'press_release' => 'Press Release'
    ),
    'SET_shown_in_webpages' => array(
        'all' => 'All Web Pages',
        'specific' => 'Specific Web Pages',
    )
);
