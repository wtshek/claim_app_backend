<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Entity definition                                             //
// Date: 2016-12-21                                                           //
////////////////////////////////////////////////////////////////////////////////

$DEF = array();

$DEF['announcement'] = array(
    'status_table' => 'locale_table',
    'name' => array(
        'table' => 'locale_table',
        'field' => 'name'
    ),
    'base_table' => array(
        'name' => 'announcements',
        'fields' => array(
            'start_date' => array( 'required' => FALSE, 'type' => 'datetime' ),
            'end_date' => array( 'required' => FALSE, 'type' => 'datetime' ),
            'session_check' => array( 'required' => TRUE, 'type' => 'radio', 'options' => &$this->kernel->dict['SET_bool'] ),
            'enabled' => array( 'required' => TRUE, 'type' => 'radio', 'options' => &$this->kernel->dict['SET_bool'] ),
            'shown_in_webpages' => array( 'required' => TRUE, 'type' => 'radio', 'options' => &$this->kernel->dict['SET_shown_in_webpages'] )
        )
    ),
    'locale_table' => array(
        'name' => 'announcement_locales',
        'fields' => array(
            'name' => array( 'required' => TRUE, 'type' => 'text', 'maxlength' => 255 ),
            'content' => array( 'required' => TRUE, 'type' => 'html', 'maxlength' => POW(2, 24) )
        )
    ),
    'multivalued_tables' => array(
        'announcement_webpages' => array( 'field' => 'webpage_id', 'required' => FALSE, 'type' => 'webpage' )
    ),
    'panels' => array(
        'basic' => array(
            'locale_table.name',
            'locale_table.content'
        ),
        'global' => array(
            'base_table.start_date',
            'base_table.end_date',
            'base_table.session_check',
            'base_table.enabled',
            'base_table.shown_in_webpages',
            'multivalued_tables.announcement_webpages'
        )
    ),
    'search_fields' => array(
        'keyword' => array(
            'type' => 'text',
            'operator' => 'like',
            'fields' => array( 'locale_table.name', 'locale_table.content' )
        ),
        'start_date' => array(
            'type' => 'date',
            'operator' => 'range',
            'fields' => array( 'base_table.start_date' )
        ),
        'end_date' => array(
            'type' => 'date',
            'operator' => 'range',
            'fields' => array( 'base_table.end_date' )
        ),
        'session_check' => array(
            'type' => 'select',
            'options' => &$this->kernel->dict['SET_bool'],
            'operator' => '=',
            'fields' => array( 'base_table.session_check' )
        ),
        'enabled' => array(
            'type' => 'select',
            'options' => &$this->kernel->dict['SET_bool'],
            'operator' => '=',
            'fields' => array( 'base_table.enabled' )
        )
    ),
    'list_fields' => array(
        'locale_table.name',
        'base_table.start_date',
        'base_table.end_date',
        'base_table.session_check',
        'base_table.enabled'
    ),
    'order_by' => array(
        'field' => 'order_index',
        'dir' => 'ASC'
    )
);

$DEF['press_release'] = array(
    'status_table' => 'locale_table',
    'name' => array(
        'table' => 'locale_table',
        'field' => 'title'
    ),
    'base_table' => array(
        'name' => 'press_releases',
        'fields' => array(
            'release_date' => array( 'required' => TRUE, 'type' => 'datetime' ),
            'release_date_shown_in_detail_page' => array( 'required' => TRUE, 'type' => 'radio', 'options' => &$this->kernel->dict['SET_bool'] ),
            'start_date' => array( 'required' => TRUE, 'type' => 'datetime' ),
            'end_date' => array( 'required' => FALSE, 'type' => 'datetime' )
        )
    ),
    'locale_table' => array(
        'name' => 'press_release_locales',
        'fields' => array(
            'title' => array( 'required' => TRUE, 'type' => 'text', 'maxlength' => 255 ),
            'content' => array( 'required' => TRUE, 'type' => 'html', 'maxlength' => POW(2, 24) ),
            'pdf' => array( 'required' => FALSE, 'type' => 'file', 'maxlength' => 255 ),
            'album_text_1' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'album_url_1' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'album_text_2' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'album_url_2' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'album_text_3' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'album_url_3' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 )
        )
    ),
    'multivalued_tables' => array(
        'press_release_banners' => array( 'field' => 'banner_id', 'required' => FALSE, 'type' => 'tabular', 'fields' => array(
            'image_xs' => array( 'required' => TRUE, 'type' => 'file', 'maxlength' => 255 ),
            'background_position_xs' => array( 'required' => FALSE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_background_positions'] ),
            'image_md' => array( 'required' => FALSE, 'type' => 'file', 'maxlength' => 255 ),
            'background_position_md' => array( 'required' => FALSE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_background_positions'] ),
            'image_xl' => array( 'required' => FALSE, 'type' => 'file', 'maxlength' => 255 ),
            'background_position_xl' => array( 'required' => FALSE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_background_positions'] )
        ) ),
        'press_release_dinings' => array( 'field' => 'webpage_id', 'required' => FALSE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_dinings'] ),
        'press_release_rooms' => array( 'field' => 'webpage_id', 'required' => FALSE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_rooms'] )
    ),
    'panels' => array(
        'basic' => array(
            'locale_table.title',
            'locale_table.content',
            'locale_table.pdf',
            'locale_table.album_text_1',
            'locale_table.album_url_1',
            'locale_table.album_text_2',
            'locale_table.album_url_2',
            'locale_table.album_text_3',
            'locale_table.album_url_3'
        ),
        'global' => array(
            'multivalued_tables.press_release_banners',
            'base_table.release_date',
            'base_table.release_date_shown_in_detail_page',
            'base_table.start_date',
            'base_table.end_date',
            'multivalued_tables.press_release_dinings',
            'multivalued_tables.press_release_rooms'
        )
    ),
    'search_fields' => array(
        'keyword' => array(
            'type' => 'text',
            'operator' => 'like',
            'fields' => array( 'locale_table.title', 'locale_table.content' )
        ),
        'release_date' => array(
            'type' => 'date',
            'operator' => 'range',
            'fields' => array( 'base_table.release_date' )
        ),
        'start_date' => array(
            'type' => 'date',
            'operator' => 'range',
            'fields' => array( 'base_table.start_date' )
        ),
        'end_date' => array(
            'type' => 'date',
            'operator' => 'range',
            'fields' => array( 'base_table.end_date' )
        )
    ),
    'list_fields' => array(
        'locale_table.title',
        'base_table.release_date',
        'base_table.start_date',
        'base_table.end_date'
    ),
    'order_by' => array(
        'field' => 'release_date',
        'dir' => 'DESC'
    )
);
