<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The vanity URLs requested report admin module.
 *
 * This module allows user to administrate logs of requested vanity URLs.
 *
 * @author  Steve Hua <stevehua1992@gmail.com>
 * @author  Martin Ng <martin@avalade.com>
 * @since   2015-06-11
 */
class vanity_url_report_admin_module extends admin_module
{
    public $module = 'vanity_url_report_admin';

    /**
     * Constructor.
     *
     * @since   2008-11-05
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );
    }

    /**
     * Process the request.
     *
     * @since   2008-11-05
     */
    function process()
    {
        try{
            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case "index":
                    $this->rights_required[] = Right::ACCESS;
                    $this->method = "index";
                    break;
                default:
                    return parent::process();
            }

            // process right checking and throw error and stop further process if any
            $this->user->checkRights($this->module, array_unique($this->rights_required));

            if($this->method) {
                call_user_func_array(array($this, $this->method), $this->params);
            }

            return TRUE;
        } catch(Exception $e) {
            $this->processException($e);
        }

        return FALSE;

    }

    /**
     * Get FROM and WHERE values in SQL statement for logs.
     *
     * @since   2015-06-11
     * @param   find    An array of query variables
     * @return  The FROM and WHERE values in SQL statement
     */
    function get_query_values( $find )
    {
        $unwanted = $this->kernel->conf['unwanted_matching_characters'];    // To shorten the name

        // Default FROM and WHERE values
        $from = 'vanity_url_trackings';
        $where = array( '1 = 1' );

        // Keyword
        /*
        if ( $find['keyword'] !== '' )
        {
            $keyword_where = array();
            $fields = array(
                'vanity_url',
                'redirect_to',
                'visitor_ip',
                'visitor_country'
            );
            $value = $this->kernel->db->escape( '%' . $this->kernel->db->cleanupStringForMatching($find['keyword'], $unwanted) . '%' );
            foreach ( $fields as $field )
            {
                $keyword_where[] = $this->kernel->db->cleanupFieldForMatching( $field, $unwanted ) . " LIKE $value";
            }
            $where[] = '(' . implode(' OR ', $keyword_where) . ')';
        }
		*/

		// Vanity url
		if($find['vanity_url']!='')
		{
			$where[] = 'vanity_url LIKE '.$this->kernel->db->escape('%'.$find['vanity_url'].'%');
		}
		
		// Redirect to
		if($find['redirect_to']!='')
		{
			$where[] = 'redirect_to LIKE '.$this->kernel->db->escape('%'.$find['redirect_to'].'%');
		}

		// Vistor country
		if($find['visitor_country']!='')
		{
			$where[] = '(visitor_country LIKE '.$this->kernel->db->escape('%'.$find['visitor_country'].'%').' OR visitor_country LIKE '.$this->kernel->db->escape('%'.$this->kernel->dict['SET_country'][$find['visitor_country']].'%').')';
		}
		
		// Visitor ip
		if($find['visitor_ip']!='')
		{
			$where[] = 'visitor_ip LIKE '.$this->kernel->db->escape('%'.$find['visitor_ip'].'%');
		}
		
        // Start date
        if ( !is_null($find['start_date']) )
        {
            $where[] = 'visit_time >= CONVERT_TZ('
                . $this->kernel->db->escape( $find['start_date'] . ' 00:00:00' ) . ','
                . $this->kernel->conf['escaped_timezone'] . ", 'GMT')";
        }

        // End date
        if ( !is_null($find['end_date']) )
        {
            $where[] = 'visit_time <= CONVERT_TZ('
                . $this->kernel->db->escape( $find['end_date'] . ' 23:59:59' ) . ','
                . $this->kernel->conf['escaped_timezone'] . ", 'GMT')";
        }

        return array(
            'from' => $from,
            'where' => $where
        );
    }

    /**
     * List logs of requested vanity URLs.
     *
     * @since   2015-06-11
     */
    function index()
    {
        $list_id = 'vanity_url_report_list';

        // Query condition
        //$_GET['keyword'] = trim( array_ifnull($_GET, 'keyword', '') );
        $_GET['vanity_url'] = trim( array_ifnull($_GET, 'vanity_url', '') );
        $_GET['redirect_to'] = trim( array_ifnull($_GET, 'redirect_to', '') );
        $_GET['visitor_country'] = trim( array_ifnull($_GET, 'visitor_country', '') );
        $_GET['visitor_ip'] = trim( array_ifnull($_GET, 'visitor_ip', '') );
        $_GET['start_date'] = string_to_date( array_ifnull($_GET, 'start_date', ''), FALSE );
        $_GET['end_date'] = string_to_date( array_ifnull($_GET, 'end_date', ''), FALSE );
        extract( $this->get_query_values($_GET) );
//echo print_r($where);exit;
        // Get the requested logs
		$list = $this->kernel->get_smarty_list_from_db(
            $list_id,
            'id',
            array(
                'select' => 'id, vanity_url, redirect_to, visitor_ip, '.$this->conn->translateField('visitor_country', $this->kernel->dict['SET_iso_country_codes'], 'visitor_country').', '
                    . " CONVERT_TZ(visit_time, 'utc',"
                    . " {$this->kernel->conf['escaped_timezone']}) AS visit_time",
                'from' => $from,
                'where' => implode(' AND ', $where),
                'group_by' => '',
                'having' => '',
                'default_order_by' => 'visit_time',
                'default_order_dir' => 'DESC'
            ),
            array(),
            array(),
            array(),
            $list_id
        );
        $this->kernel->smarty->assignByRef( 'list', $list );

        $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/vanity_url_report_admin/index.html' );
    }

}