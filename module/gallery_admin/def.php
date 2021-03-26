<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Entity definition                                             //
// Date: 2016-12-21                                                           //
////////////////////////////////////////////////////////////////////////////////

$DEF = array();

// Get gallery tag set
$sql = 'SELECT id, alias FROM gallery_tags';
$sql .= " WHERE domain = 'private' AND deleted = 0";
$sql .= ' ORDER BY order_index';
$this->kernel->dict['SET_gallery_tags'] = $this->kernel->get_set_from_db( $sql );

/*******************************************************************************
 * sky100
 ******************************************************************************/

$DEF['gallery_tag'] = array(
    'status_table' => 'locale_table',
    'name' => array(
        'table' => 'base_table',
        'field' => 'alias'
    ),
    'base_table' => array(
        'name' => 'gallery_tags',
        'fields' => array(
            'alias' => array( 'required' => TRUE, 'type' => 'text', 'maxlength' => 255 )
        )
    ),
    'locale_table' => array(
        'name' => 'gallery_tag_locales',
        'fields' => array(
            'title' => array( 'required' => TRUE, 'type' => 'text', 'maxlength' => 255 )
        )
    ),
    'panels' => array(
        '' => array(
            'base_table.alias',
            'locale_table.title'
        )
    ),
    'search_fields' => array(
        'keyword' => array(
            'type' => 'text',
            'operator' => 'like',
            'fields' => array( 'base_table.alias', 'locale_table.title' )
        )
    ),
    'list_fields' => array(
        'base_table.alias',
        'locale_table.title'
    ),
    'order_by' => array(
        'field' => 'order_index',
        'dir' => 'ASC'
    )
);

$DEF['gallery_image'] = array(
    'status_table' => 'locale_table',
    'name' => array(
        'table' => 'base_table',
        'field' => 'image'
    ),
    'base_table' => array(
        'name' => 'gallery_images',
        'fields' => array(
            'image' => array( 'required' => TRUE, 'type' => 'file' )
        )
    ),
    'locale_table' => array(
        'name' => 'gallery_image_locales',
        'fields' => array(
            'caption' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'alternative_text' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 )
        )
    ),
    'multivalued_tables' => array(
        'gallery_image_tags' => array( 'field' => 'gallery_tag_id', 'required' => TRUE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_gallery_tags'] ),
    ),
    'panels' => array(
        '' => array(
            'base_table.image',
            'locale_table.caption',
            'locale_table.alternative_text',
            'multivalued_tables.gallery_image_tags'
        )
    ),
    'search_fields' => array(
        'keyword' => array(
            'type' => 'text',
            'operator' => 'like',
            'fields' => array( 'base_table.image', 'locale_table.caption', 'locale_table.alternative_text' )
        ),
        'tag' => array(
            'type' => 'select',
            'options' => &$this->kernel->dict['SET_gallery_tags'],
            'operator' => '=',
            'fields' => array( 'multivalued_tables.gallery_image_tags' )
        )
    ),
    'list_fields' => array(
        'base_table.image',
        'locale_table.caption',
        'locale_table.alternative_text',
        'multivalued_tables.gallery_image_tags'
    ),
    'order_by' => array(
        'field' => 'image',
        'dir' => 'ASC'
    )
);

$DEF['gallery_container'] = array(
    'status_table' => 'base_table',
    'name' => array(
        'table' => 'base_table',
        'field' => 'name'
    ),
    'base_table' => array(
        'name' => 'gallery_containers',
        'fields' => array(
            'name' => array( 'required' => TRUE, 'type' => 'text', 'maxlength' => 255 )
        )
    ),
    'multivalued_tables' => array(
        'gallery_container_tags' => array( 'field' => 'gallery_tag_id', 'required' => TRUE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_gallery_tags'] ),
    ),
    'panels' => array(
        'basic' => array(
            'base_table.name',
            'multivalued_tables.gallery_container_tags'
        ),
        'images' => array(
        )
    ),
    'search_fields' => array(
        'keyword' => array(
            'type' => 'text',
            'operator' => 'like',
            'fields' => array( 'base_table.name' )
        ),
        'tag' => array(
            'type' => 'select',
            'options' => &$this->kernel->dict['SET_gallery_tags'],
            'operator' => '=',
            'fields' => array( 'multivalued_tables.gallery_container_tags' )
        )
    ),
    'list_fields' => array(
        'base_table.name',
        'multivalued_tables.gallery_container_tags'
    ),
    'order_by' => array(
        'field' => 'name',
        'dir' => 'ASC'
    )
);
