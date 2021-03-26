<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Application-level dictionary (string bundle)                  //
// Date: 2008-11-06                                                           //
// Unless otherwise specified, all strings are plain text (e.g. not HTML)     //
////////////////////////////////////////////////////////////////////////////////

$DICT = array(
    'ACTION_change_image' => 'Change featured image',
    'ACTION_generate_token' => 'Generate',
    'ACTION_remove_token' => 'Remove',

    'LABEL_alias' => 'Alias',
    'LABEL_anonymous_preview' => 'Anonymous Preview',
    'LABEL_button_url' => 'External URL',
    'LABEL_catagory' => 'Category',
    'LABEL_copy_from' => 'Copy from',
    'LABEL_description' => 'Description',
    'LABEL_dinings' => 'Restaurants',
    'LABEL_extras' => 'Extras',
    'LABEL_featured' => 'Featured Promotion',
    'LABEL_full' => 'Full',
    'LABEL_inherited' => 'Copy from section',
    'LABEL_keywords' => 'Keywords',
    'LABEL_manage_offers' => 'Manage Promotions',
    'LABEL_menus' => 'Menus',
    'LABEL_new_offer' => 'New Promotion',
    'LABEL_rooms' => 'Room Types',
    'LABEL_offer' => 'Promotion',
    'LABEL_offer_attributes' => 'Promotion attributes',
    'LABEL_offer_management' => 'Promotion Management Panel',
    'LABEL_offer_period' => 'Promotion Period ',
    'LABEL_offer_target' => 'Button target',
    'LABEL_offers' => 'Promotions',
    'LABEL_offers_count' => 'Promotion count (Published / Pending)',
    'LABEL_order_index' => 'Order Index (Ascending order)',
    'LABEL_page_offers' => 'Promotions on page',
    'LABEL_period_from' => 'Period From',
    'LABEL_period_to' => 'Period To',
    'LABEL_price' => 'Price',
    'LABEL_publish_attributes' => 'Publish attributes',
    'LABEL_publish_schedule' => 'Publish Schedule',
    'LABEL_reservation_info' => 'Reservation Info',
    'LABEL_share_files' => 'Share Files',
    'LABEL_short_description' => 'Short Description',
    'LABEL_site_distribution' => 'Settings',
    'LABEL_target' => 'Target',
    'LABEL_thumbnail' => 'Thumbnail',
    'LABEL_video_url' => 'Video URL',
    'LABEL_webpage_title' => 'Page title',

    'DESCRIPTION_action_text' => 'Enter button text here in the list page',
    'DESCRIPTION_action_url' => 'Enter button url here in the list page',
    'DESCRIPTION_alias' => 'Alias of the detail page',
    'DESCRIPTION_button_url' => 'Enter external url for promotion detail',
    'DESCRIPTION_seo_title' => 'Enter alternate title here which will replace the webpage title in <title> for SEO purpose',
    'DESCRIPTION_site_distribution' => 'The distribution of this promotion on site:',
    'DESCRIPTION_title' => 'Enter promotion title',

    'MESSAGE_delete_confirm' => 'You are about to remove this promotion. Are you sure to continue?',
    'MESSAGE_publish_confirm' => 'You are about to publish the changes. Are you sure to continue?',
    'MESSAGE_offer_about_to_delete' => 'This promotion is about to delete once the page is publish.',

    'ERROR_action_text_empty' => 'Please enter the text for action button.',
    'ERROR_alias_collide' => 'Alias has already been used. Please use andother alias for page identification.',
    'ERROR_alias_empty' => 'Please enter the alias',
    'ERROR_content_empty' => 'Please input something for your content',
    'ERROR_date_invalid' => 'Invalid date, please make sure the date value is correct',
    'ERROR_description_empty' => 'Please enter the description for SEO',
    'ERROR_img_url_empty' => 'Please select a feature image.',
    'ERROR_keywords_empty' => 'Please enter the keywords for SEO',
    'ERROR_offer_type_invalid' => 'Invalid promotion type. Please make sure the promotion type is a valid one.',
    'ERROR_title_empty' => 'Please enter the promotion title you hope to display on the slider.',
    'ERROR_url_empty' => 'Please enter a url for action of the promotion',
    'ERROR_url_invalid' => 'Invalid URL. Please make sure the url you entered is valid',

    'FORMAT_date' => 'YYYY-MM-DD HH:MM:SS',

    'SET_offer_active_stat' => array(
        '' => 'All Promotions',
        'active' => 'Active Promotions',
        'inactive' => 'Inactive Promotions'
    ),
    'SET_offer_types' => array(
        'page' => 'Detail page',
        'link' => 'Link to external webpage'
    )
);
