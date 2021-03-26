<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Entity definition                                             //
// Date: 2021-03-02                                                           //
////////////////////////////////////////////////////////////////////////////////

$DEF = array();

$DEF['subscription'] = array(
    'status_table' => '',
    'name' => array(
        'table' => 'base_table',
        'field' => 'id'
    ),
    'base_table' => array(
        'name' => 'subscriptions',
        'fields' => array(
            'locale' => array( 'required' => TRUE, 'type' => 'select', 'options' => &$this->kernel->sets['public_locales'] ),
            'title' => array( 'required' => TRUE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_salutations'] ),
            'first_name' => array( 'required' => TRUE, 'type' => 'text', 'maxlength' => 255 ),
            'last_name' => array( 'required' => TRUE, 'type' => 'text', 'maxlength' => 255 ),
            'email' => array( 'required' => TRUE, 'type' => 'text', 'maxlength' => 255 ),
            'phone' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'address' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'city' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'zip_code' => array( 'required' => FALSE, 'type' => 'text', 'maxlength' => 255 ),
            'country' => array( 'required' => TRUE, 'type' => 'select', 'options' => &$this->kernel->dict['SET_countries'] ),
            'created_date' => array( 'required' => TRUE, 'type' => 'datetime' ),
        )
    ),
    'panels' => array(
        '' => array(
            'base_table.locale',
            'base_table.title',
            'base_table.first_name',
            'base_table.last_name',
            'base_table.email',
            'base_table.phone',
            'base_table.address',
            'base_table.city',
            'base_table.zip_code',
            'base_table.country',
        )
    ),
    'search_fields' => array(
        'locale' => array(
            'type' => 'select',
            'options' => &$this->kernel->sets['public_locales'],
            'operator' => '=',
            'fields' => array( 'base_table.locale' )
        ),
        'title' => array(
            'type' => 'select',
            'options' => &$this->kernel->dict['SET_salutations'],
            'operator' => '=',
            'fields' => array( 'base_table.title' )
        ),
        'country' => array(
            'type' => 'select',
            'options' => &$this->kernel->dict['SET_countries'],
            'operator' => '=',
            'fields' => array( 'base_table.country' )
        ),
        'created_date' => array(
            'type' => 'date',
            'operator' => 'range',
            'fields' => array( 'base_table.created_date' )
        ),
        'keyword' => array(
            'type' => 'text',
            'operator' => 'like',
            'fields' => array( 'base_table.first_name', 'base_table.last_name', 'base_table.email', 'base_table.phone', 'base_table.address', 'base_table.city', 'base_table.zip_code' )
        ),
    ),
    'list_fields' => array(
        'base_table.title',
        'base_table.first_name',
        'base_table.last_name',
        'base_table.email',
        'base_table.country',
        'base_table.created_date',
    ),
    'order_by' => array(
        'field' => 'created_date',
        'dir' => 'DESC'
    )
);
