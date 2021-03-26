<?php

// Get the root directory of this application
// e.g. C:\www\pure-cms\module\form_admin\index.php -> C:\www\pure-cms
$APP_ROOT = dirname( dirname(dirname( __FILE__ )) );

// Include required files
require_once( "$APP_ROOT/module/entity_admin/index.php" );

/**
 * The form admin module.
 *
 * This module allows user to administrate form entities.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2021-03-02
 */
class form_admin_module extends entity_admin_module
{
    public $module = 'form_admin';

    /**
     * Constructor
     *
     * @param   kernel  The kernel
     * @since   2021-03-02
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
     * Process the request.
     *
     * @since   2021-03-02
     * @return  Processed or not
     */
    function process()
    {
        if ( !parent::process() )
        {
            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case 'export':
                    $this->method = 'export';
                    break;
            }
            if ( $this->method )
            {
                $content = call_user_func_array( array($this, $this->method), $this->params );
                if ( !is_null($content) )
                {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( $content );
                }
                return TRUE;
            }
            return FALSE;
        }
    }
}
