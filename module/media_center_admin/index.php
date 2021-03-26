<?php

// Get the root directory of this application
// e.g. C:\www\avalade_cms\module\media_center_admin\index.php -> C:\www\avalade_cms
$APP_ROOT = dirname( dirname(dirname( __FILE__ )) );

// Include required files
require_once( "$APP_ROOT/module/entity_admin/index.php" );

/**
 * The media center admin module.
 *
 * This module allows user to administrate media center entities.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2015-11-24
 */
class media_center_admin_module extends entity_admin_module
{
    public $module = 'media_center_admin';

    /**
     * Constructor
     *
     * @param   $kernel
     * @since   2015-11-24
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );

        // Load additional definition file
        require( dirname(__FILE__) . "/def.php" );
        $this->def = &$DEF;
    }

    /**
     * Edit an entity based on ID.
     *
     * @since   2020-03-23
     */
    function edit()
    {
        parent::edit();

        // Custom logic for announcement
        if ( $_GET['entity'] == 'announcement' )
        {
            // Edit page
            if ( $_SERVER['REQUEST_METHOD'] == 'GET' )
            {
                $this->kernel->response['content'] = $this->kernel->smarty->fetch( "module/{$this->module}/edit_announcement.html" );
            }
        }
    }

    /**
     * Save an entity based on data.
     *
     * @since   2020-03-24
     * @param   data                The data
     * @param   status              The status
     * @param   selected_locale     The selected locale
     * @return  The ID
     */
    function save( $data, $status, $selected_locale = '' )
    {
        // Custom logic for announcement
        if ( $_GET['entity'] == 'announcement' )
        {
            $errors = array();

            if ( $data['base_table']['shown_in_webpages'] == 'specific' )
            {
                if ( count($data['multivalued_tables']['announcement_webpages']) == 0 )
                {
                    $errors['multivalued_tables[announcement_webpages][]'][] = 'announcement_webpages_blank';
                }
            }
            else
            {
                $data['multivalued_tables']['announcement_webpages'] = array();
            }

            if ( count($errors) > 0 )
            {
                throw new fieldsException( $errors );
            }
        }

        parent::save( $data, $status, $selected_locale );
    }
}
