<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Application-level dictionary (string bundle)                  //
// Date: 2008-11-06                                                           //
// Unless otherwise specified, all strings are plain text (e.g. not HTML)     //
////////////////////////////////////////////////////////////////////////////////

$DICT = array(
    // Labels
    'LABEL_active_pages' => 'Used on active page',
    'LABEL_address' => 'Address',
    'LABEL_caption' => 'Caption',
    'LABEL_city' => 'City',
    'LABEL_country' => 'Country / Region',
    'LABEL_date' => 'Date',
    'LABEL_dining_reservations_and_enquires_details' => 'Restaurant reservations and enquires details',
    'LABEL_download_pdf' => 'Download PDF',
    'LABEL_id_display' => 'Content Block ID',
    'LABEL_image' => 'Image',
    'LABEL_lanugage' => 'Language',
    'LABEL_level' => 'Level of Child page',
    'LABEL_max_column_rows' => 'Max No. of rows in each column',
    'LABEL_new_snippet' => 'New Content Block',
    'LABEL_phone' => 'Phone',
    'LABEL_photo_albums_title' => 'Photo albums title',
    'LABEL_press_contact_details' => 'Press contact details',
    'LABEL_press_contact_title' => 'Press contact title',
    'LABEL_receipient_email' => 'Reciepient Email',
    'LABEL_redirect_alias' => 'Redirect Alias',
    'LABEL_reservations_and_enquires_title' => 'Reservations and enquires title',
    'LABEL_room_reservations_and_enquires_details' => 'Room reservations and enquires details',
    'LABEL_sample_code' => 'Sample Inputs:',
    'LABEL_sample_code_youtube' => 'Sample Inputs (Youtube):',
    'LABEL_snippet_finder' => 'Content Block Finder',
    'LABEL_snippet_name' => 'Content Block Name',
    'LABEL_snippet_type' => 'Content Block Type',
    'LABEL_snippets' => 'Content Blocks',
    'LABEL_submit' => 'Submit',
    'LABEL_view_more_press_releases' => 'View more press releases',
    'LABEL_webpage_id' => 'Webpage ID',
    'LABEL_zip_code' => 'ZIP Code',

    // Errors
    'ERROR_country_blank' => 'Please enter the country / region.',
    'ERROR_country_invalid' => 'Please enter a valid country / region.',
    'ERROR_email_blank' => 'Please enter the email.',
    'ERROR_email_invalid' => 'Please enter a valid email.',
    'ERROR_first_name_blank' => 'Please enter the first name.',
    'ERROR_last_name_blank' => 'Please enter the last name.',
    'ERROR_snippet_name_blank' => 'Please input a name for the content block.',
    'ERROR_title_blank' => 'Please select the title.',

    // Messages
    'MESSAGE_child_pages_sample' => '<div><b>Level of Child page: </b>1</div>',
    'MESSAGE_enews_signup_form_sample' => '<div><b>Redirect Alias:</b> thank-you</div>',
    'MESSAGE_no_parameters' => 'This type of content block doesn\'t require to set parameters.',
    'MESSAGE_pgwSlider_sample' => '
        <div><b>Image 1: </b> file/webpage/page/public/p34/en/34/arrival-lobby.jpg</div>
        <div><b>Caption 1: </b> Entrance</div>
        <div><b>Image 2: </b> file/webpage/page/public/p34/en/34/club-floor-lounge.jpg</div>
        <div><b>Caption 2: </b> The Club</div>',
    'MESSAGE_request_for_proposal_sample' => '<div><b>Reciepient Email: </b>test@example.com</div>',
    'MESSAGE_save_confirmation' => 'The change will take effect immediately. Are you sure to continue?',
    'MESSAGE_site_map_sample' => '<div><b>Max No. of rows in each column: </b>15</div>',

    // Sets
    'SET_image_slideshow_backgroundtypes' => array(
        'image' => 'image',
        'video' => 'video'
    ),
    'SET_lightbox_btn_types' => array(
        'image' => 'image',
        'html' => 'html'
    ),
    'SET_snippet_parameter_groups' => array(
        'pgwSlider' => array('image', 'caption')
    )
);
