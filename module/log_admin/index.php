<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The log admin module.
 *
 * This module allows user to administrate logs.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2008-11-05
 */
class log_admin_module extends admin_module
{
    public $module = 'log_admin';

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
                case "view":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "view";
                    break;
                case "export":
                    $this->rights_required[] = Right::EXPORT;
                    $this->method = "export";
                    break;
                case "delete":
                    $this->rights_required[] = Right::EXPORT;
                    $this->method = "delete";
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
     * @since   2008-11-05
     * @param   find    An array of query variables
     * @return  The FROM and WHERE values in SQL statement
     */
    function get_query_values( $find )
    {
        $unwanted = $this->kernel->conf['unwanted_matching_characters'];    // To shorten the name

        // Default FROM and WHERE values
        $from = 'logs';
        $where = array( '1 = 1' );

        // Keyword
        if ( $find['keyword'] !== '' )
        {
            $keyword_where = array();
            $fields = array(
                //'logs.type',
                //'logs.locale',
                //'logs.module',
                'logs.description',
                //'logs.file_path',
                //'logs.ip_address',
                //'logs.user_agent',
                //'logs.referer_uri',
                //'logs.request_uri',
                //'logs.logged_date'
            );
            $value = $this->kernel->db->escape( '%' . $this->kernel->db->cleanupStringForMatching($find['keyword'], $unwanted) . '%' );
            foreach ( $fields as $field )
            {
                $keyword_where[] = $this->kernel->db->cleanupFieldForMatching( $field, $unwanted ) . " LIKE $value";
            }
            $where[] = '(' . implode(' OR ', $keyword_where) . ')';
        }

        // Locale
        if ( $find['locale'] !== '' )
        {
            $where[] = 'logs.locale = ' . $this->kernel->db->escape( $find['locale'] );
        }

        // Module
        if ( $find['module'] !== '' )
        {
            $where[] = 'logs.module = ' . $this->kernel->db->escape( $find['module'] );
        }

        // Start date
        if ( !is_null($find['start_date']) )
        {
            $where[] = 'logs.logged_date >= CONVERT_TZ('
                . $this->kernel->db->escape( $find['start_date'] . ' 00:00:00' ) . ','
                . $this->kernel->conf['escaped_timezone'] . ", 'GMT')";
        }

        // End date
        if ( !is_null($find['end_date']) )
        {
            $where[] = 'logs.logged_date <= CONVERT_TZ('
                . $this->kernel->db->escape( $find['end_date'] . ' 23:59:59' ) . ','
                . $this->kernel->conf['escaped_timezone'] . ", 'GMT')";
        }

        return array(
            'from' => $from,
            'where' => $where
        );
    }

    /**
     * List logs.
     *
     * @since   2008-11-05
     */
    function index()
    {
        $list_id = 'admin_log_list';

        // Query condition
        $_GET['keyword'] = trim( array_ifnull($_GET, 'keyword', '') );
        $_GET['locale'] = trim( array_ifnull($_GET, 'locale', '') );
        $_GET['module'] = trim( array_ifnull($_GET, 'module', '') );
        $_GET['start_date'] = string_to_date( array_ifnull($_GET, 'start_date', ''), FALSE );
        $_GET['end_date'] = string_to_date( array_ifnull($_GET, 'end_date', ''), FALSE );
        extract( $this->get_query_values($_GET) );

        // Actions
        $view_action = '?op=view&referer_url=' . urlencode('?' . http_build_query($_GET)) . '&id=';
        $export_action = '?' . http_build_query( array_merge($_GET, array('op' => 'export')) );
        $delete_action = '?' . http_build_query( array_merge($_GET, array('op' => 'delete')) );

        // Get the requested logs
		$list = $this->kernel->get_smarty_list_from_db(
            $list_id,
            'id',
            array(
                'select' => 'id,'
                    . $this->kernel->db->translateField('type', $this->kernel->dict['SET_log_types'], 'type') . ','
                    . " CONVERT_TZ(logged_date, 'GMT',"
                    . " {$this->kernel->conf['escaped_timezone']}) AS datetime,"
                    . $this->kernel->db->translateField('locale', $this->kernel->sets['locales'], 'locale') . ','
                    . $this->kernel->db->translateField('module', $this->kernel->dict['SET_modules'], 'module') . ','
                    . " SUBSTRING_INDEX(description, '\r\n', 1) AS description",
                'from' => $from,
                'where' => implode(' AND ', $where),
                'group_by' => '',
                'having' => '',
                'default_order_by' => 'datetime',
                'default_order_dir' => 'DESC'
            ),
            array(),
            array(
                $view_action => $this->kernel->dict['ACTION_view']
            ),
            array(
                $export_action => $this->kernel->dict['ACTION_export'],
                $delete_action => $this->kernel->dict['ACTION_delete']
            ),
            $list_id
        );
        $this->kernel->smarty->assignByRef( 'list',  $list);

        // Get the requested logs (by log type)
        $type_lists = array();
        foreach ( $this->kernel->dict['SET_log_types'] as $type => $type_name )
        {
            $type_where = array_merge( $where, array('type = ' . $this->kernel->db->escape($type)) );
            $type_list = $this->kernel->get_smarty_list_from_db(
                "{$list_id}_{$type}",
                'id',
                array(
                    'select' => "id, CONCAT(CONVERT_TZ(logged_date, 'GMT',"
                        . " {$this->kernel->conf['escaped_timezone']})) AS datetime,"
                        . $this->kernel->db->translateField('locale', $this->kernel->sets['locales'], 'locale') . ','
                        . $this->kernel->db->translateField('module', $this->kernel->dict['SET_modules'], 'module') . ','
                        . " SUBSTRING_INDEX(description, '\r\n', 1) AS description",
                    'from' => $from,
                    'where' => implode(' AND ', $type_where),
                    'group_by' => '',
                    'having' => '',
                    'default_order_by' => 'datetime',
                    'default_order_dir' => 'DESC'
                ),
                array(),
                array(
                    $view_action => $this->kernel->dict['ACTION_view']
                ),
                array(
                    $export_action . '&type=' . urlencode($type) => $this->kernel->dict['ACTION_export'],
                    $delete_action . '&type=' . urlencode($type) => $this->kernel->dict['ACTION_delete']
                ),
                "{$list_id}_{$type}"
            );
            $type_list['name'] = $type_name;
            $type_lists[$type] = $type_list;
        }
        $this->kernel->smarty->assignByRef( 'type_lists', $type_lists );

        $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/log_admin/index.html' );
    }

    /**
     * Export logs.
     *
     * @since   2008-11-05
     */
    function export()
    {
        // Query condition
        $type = array_ifnull( $_GET, 'type', '' );
        $_GET['keyword'] = trim( array_ifnull($_GET, 'keyword', '') );
        $_GET['locale'] = trim( array_ifnull($_GET, 'locale', '') );
        $_GET['module'] = trim( array_ifnull($_GET, 'module', '') );
        $_GET['start_date'] = string_to_date( array_ifnull($_GET, 'start_date', ''), FALSE );
        $_GET['end_date'] = string_to_date( array_ifnull($_GET, 'end_date', ''), FALSE );
        extract( $this->get_query_values($_GET) );
        if ( $type !== '' )
        {
            $where[] = 'type = ' . $this->kernel->db->escape( $type );
        }

        // Get the list
        $query = 'SELECT id, ';
        $query .= $this->kernel->db->translateField('type', $this->kernel->dict['SET_log_types'], 'type') . ',';
        $query .= " CONVERT_TZ(logged_date, 'GMT',";
        $query .= " {$this->kernel->conf['escaped_timezone']}) AS datetime,";
        $query .= $this->kernel->db->translateField('locale', $this->kernel->sets['locales'], 'locale') . ',';
        $query .= $this->kernel->db->translateField('module', $this->kernel->dict['SET_modules'], 'module') . ',';
        $query .= ' ip_address, user_agent, referer_uri, request_uri,';
        $query .= ' file_path, line_number, description';
        $query .= " FROM $from";
        $query .= ' WHERE ' . implode( ' AND ', $where );
        $query .= ' ORDER BY logged_date DESC, id DESC';
        
        // Check number of records
        $sql = 'SELECT COUNT(*) AS total_records FROM '.$from.' WHERE '.implode( ' AND ', $where );
        $statement = $this->conn->query( $sql );
        extract( $statement->fetch() );
    
        if($total_records<10000)
        {
            // Set outputs
            $list = $this->kernel->get_spreadsheet_list_from_db( $query );
            $this->apply_template = FALSE;
            $this->kernel->response['charset'] = '';
            $this->kernel->response['filename'] = 'logs.xls';
            $this->kernel->response['disposition'] = 'attachment';
            $this->kernel->response['mimetype'] = 'application/vnd.ms-excel';
            $this->kernel->response['content'] = $list['content'];
        }
        else
        {
            $item_per_page = 6000;
            $total_pages = ceil($total_records / $item_per_page);
            
            for($j=0; $j<$total_pages; $j++)
            {
                $limit = " LIMIT ".$j*$item_per_page.",".$item_per_page;
                $statement = $this->conn->query( $query. $limit);
                if($j==0)
                {
                    $csv = fopen( 'php://temp', 'r+' );
                    $header_row = array(
                        $this->kernel->dict['LABEL_id'], $this->kernel->dict['LABEL_type'], $this->kernel->dict['LABEL_datetime'], $this->kernel->dict['LABEL_locale'], $this->kernel->dict['LABEL_module'], $this->kernel->dict['LABEL_ip_address'], $this->kernel->dict['LABEL_user_agent'], $this->kernel->dict['LABEL_referer_uri'], $this->kernel->dict['LABEL_request_uri'], $this->kernel->dict['LABEL_file_path'], $this->kernel->dict['LABEL_line_number'], $this->kernel->dict['LABEL_description']
                    );
                    fputcsv( $csv, $header_row );
                }
                
                while($cells = $statement->fetch())
                {
                    fputcsv( $csv, $cells );
                }
            }
            
            rewind( $csv );
            $content = stream_get_contents( $csv );
            fclose( $csv );
            $this->apply_template = FALSE;
            $this->kernel->response['charset'] = '';
            $this->kernel->response['filename'] = 'logs.csv';
            $this->kernel->response['disposition'] = 'attachment';
            $this->kernel->response['mimetype'] = 'text/csv';
            $this->kernel->response['content'] = $content;
        }
        
        $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> exported $total_records logs.", __FILE__, __LINE__ );
    }

    function delete(){
        // Query condition
        $type = array_ifnull( $_GET, 'type', '' );
        $_GET['keyword'] = trim( array_ifnull($_GET, 'keyword', '') );
        $_GET['locale'] = trim( array_ifnull($_GET, 'locale', '') );
        $_GET['module'] = trim( array_ifnull($_GET, 'module', '') );
        $_GET['start_date'] = string_to_date( array_ifnull($_GET, 'start_date', ''), FALSE );
        $_GET['end_date'] = string_to_date( array_ifnull($_GET, 'end_date', ''), FALSE );
        extract( $this->get_query_values($_GET) );
        if ( $type !== '' )
        {
            $where[] = 'type = ' . $this->kernel->db->escape( $type );
        }

        $query = "DELETE FROM $from";
        $query .= ' WHERE ' . implode( ' AND ', $where );
        $this->conn->exec($query);
        if($type=='')
            $type = 'all types';
        $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> deleted logs of {$type}.", __FILE__, __LINE__ );

        // Redirect
        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                        http_build_query(array(
                                              'op' => 'dialog',
                                              'type' => 'message',
                                              'code' => 'DESCRIPTION_deleted',
                                              'redirect_url' => '.'
                                         ));
        $this->kernel->redirect($redirect);
    }
    
     /**
     * View a log based on log ID.
     *
     * @since   2008-11-06
     */
    function view()
    {
        // Data container
        $data = array();

        // Get data from query string
        $id = intval( array_ifnull($_GET, 'id', 0) );

        // Get the requested log
        $query = "SELECT *, DATE(CONVERT_TZ(logged_date, 'GMT',";
        $query .= " {$this->kernel->conf['escaped_timezone']})) AS date,";
        $query .= " TIME(CONVERT_TZ(logged_date, 'GMT',";
        $query .= " {$this->kernel->conf['escaped_timezone']})) AS time";
        $query .= ' FROM logs';
        $query .= " WHERE id = $id";
        $statement = $this->kernel->db->query( $query );
        if ( !$statement )
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $query, __FILE__, __LINE__ );
        }
        $data['log'] = ( $record = $statement->fetch() ) ? $record : array();

        // Assign data to view
        $this->kernel->smarty->assignByRef( 'data', $data );

        // Set page title
        $first_line = $this->kernel->dict['LABEL_unknown'];
        if ( count( $data['log'] ) > 0 )
        {
            $first_line = current( explode("\r\n", $data['log']['description']) );
        }
        else
        {
            $data['log']['id'] = $id = 0;
        }

        $this->kernel->dict['SET_operations']['view'] = sprintf(
            $this->kernel->dict['SET_operations']['view'],
            $first_line
        );

        // BreadCrumb
        $this->_breadcrumb->push(new breadcrumbNode($this->kernel->dict['ACTION_view']
                , $this->kernel->sets['paths']['mod_from_doc'] . '?op=view&id=' . $id)
        );

        $this->kernel->response['titles'][] = $this->kernel->dict['SET_operations']['view'];

        $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/log_admin/view.html' );
    }
}
