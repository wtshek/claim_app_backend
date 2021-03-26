<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Entity admin definition                                       //
// Date: 2020-03-05                                                           //
////////////////////////////////////////////////////////////////////////////////

$ENTITY_ADMIN_DEF = array(
    // Gallery admin
    'gallery_admin' => array(
        'name' => 'Galleries',
        'children' => array(
            'gallery_tag' => array(
                'name' => 'Tags',
                'icon' => 'icon-tags',
                'edit' => 'modal'
            ),
            'gallery_image' => array(
                'name' => 'Images',
                'icon' => 'icon-picture',
                'edit' => 'modal'
            ),
            'gallery_container' => array(
                'name' => 'Galleries',
                'icon' => 'icon-book',
                'edit' => 'modal'
            ),
        )
    ),

    // Media center admin
    'media_center_admin' => array(
        'name' => 'Media Center',
        'children' => array(
            'announcement' => array(
                'name' => 'Announcements',
                'icon' => 'icon-info-sign'
            ),
            'press_release' => array(
                'name' => 'Press Releases',
                'icon' => 'icon-camera'
            ),
        )
    ),

    // Form admin
    'form_admin' => array(
        'name' => 'Forms',
        'children' => array(
            'subscription' => array(
                'name' => 'Subscriptions',
                'icon' => 'icon-check'
            ),
        )
    ),
);
