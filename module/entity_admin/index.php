<?php

// Get the root directory of this application
// e.g. C:\www\avalade_cms\module\entity_admin\index.php -> C:\www\avalade_cms
$APP_ROOT = dirname( dirname(dirname( __FILE__ )) );

// Include required files
require_once( "$APP_ROOT/module/admin/index.php" );
require_once( dirname(dirname(__FILE__)) . '/webpage_admin/index.php' );

/**
 * The entity admin module.
 *
 * This module allows user to administrate entities.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2015-11-24
 */
class entity_admin_module extends admin_module
{
    public $module = 'entity_admin';

    protected $def = array();
    protected $entity_def = array();
    protected $preferred_locale = NULL;

    /**
     * Constructor
     *
     * @param   $kernel
     * @since   2015-06-17
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );

        // Get preferred locale
        $this->preferred_locale = $this->user->getPreferredLocale();
    }

    /**
     * Process the request.
     *
     * @since   2015-06-17
     * @return  Processed or not
     */
    function process()
    {
        // Set entity def
        $_GET['entity'] = array_ifnull( $_GET, 'entity', '' );
        if ( array_key_exists($_GET['entity'], $this->def) )
        {
            $this->module_title = $this->kernel->entity_admin_def[$this->module]['children'][$_GET['entity']]['name'];
            $this->_breadcrumb->pop();
            $this->_breadcrumb->push( new breadcrumbNode(
                $this->module_title,
                $this->kernel->sets['paths']['mod_from_doc'] . '/?entity=' . urlencode( $_GET['entity'] )
            ) );
            $this->entity_def = &$this->def[$_GET['entity']];
        }

        // Assign members to Smarty Template Engine
        $this->kernel->smarty->assignByRef( 'def', $this->def );
        $this->kernel->smarty->assignByRef( 'entity_def', $this->entity_def );

        // Check entity
        if ( !array_key_exists($_GET['entity'], $this->def) )
        {
            $this->kernel->redirect( '?' . http_build_query(array_merge(
                $_GET, array( 'entity' => current(array_keys($this->def)) )
            )) );
            return TRUE;
        }

        // Choose operation
        $this->rights_required[] = Right::ACCESS;
        try
        {
            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case 'index':
                case 'export':
                    $this->method = 'index';
                    break;

                case 'change_order':
                    $this->method = 'change_order';
                    break;

                case 'edit':
                    $this->method = 'edit';
                    break;

                case 'delete':
                    $this->method = 'prune';
                    break;

                case 'generate_token':
                    $this->method = 'generate_token';
                    break;

                case 'remove_token':
                    $this->method = 'remove_token';
                    break;

                default:
                    return parent::process();
            }
            $content = call_user_func_array( array($this, $this->method), $this->params );
            if ( !is_null($content) )
            {
                $this->apply_template = FALSE;
                $this->kernel->response['mimetype'] = 'application/json';
                $this->kernel->response['content'] = json_encode( $content );
            }
            return TRUE;
        }
        catch ( Exception $e )
        {
            $this->processException( $e );
            return FALSE;
        }
    }

    /**
     * Get FROM and WHERE values in SQL statement for entities.
     *
     * @since   2019-09-04
     * @return  The FROM, WHERE and GROUP BY values in SQL statement
     */
    function get_query_values()
    {
        // Base query condition
        $from = "{$this->entity_def['base_table']['name']} AS base_table";
        $from .= ' LEFT OUTER JOIN webpage_preview_tokens ON (base_table.id = webpage_preview_tokens.initial_id AND webpage_preview_tokens.type = ' . $this->kernel->db->escape($_GET['entity']) . ' AND webpage_preview_tokens.expire_time > UTC_TIMESTAMP())';
        $where = array( 'base_table.deleted = 0' );
        $group_by = '';

        // With locale field in base table
        if ( array_key_exists('locale', $this->entity_def['base_table']['fields']) )
        {
            $where[] = 'base_table.locale IN (' . implode( ', ', array_map(
                array( $this->kernel->db, 'escape' ),
                array_merge( $this->user->getAccessibleLocales(), array('') )
            ) ) . ')';
        }

        // With locale table
        if ( array_key_exists('locale_table', $this->entity_def) )
        {
            $join_on = "base_table.id = locale_table.{$_GET['entity']}_id";
            if ( $this->entity_def['status_table'] )
            {
                $join_on .= ' AND base_table.domain = locale_table.domain';
            }
            $from .= " JOIN {$this->entity_def['locale_table']['name']} AS locale_table ON ($join_on)";
            $group_by = 'base_table.id';
        }

        // With domain field
        if ( $this->entity_def['status_table'] )
        {
            $where[] = "base_table.domain = 'private'";
        }

        return compact( 'from', 'where', 'group_by' );
    }

    /**
     * List the entities.
     *
     * @since   2015-06-19
     */
    function index()
    {
        $is_export = $_GET['op'] == 'export';

        // Check authentication
        $this->rights_required[] = Right::VIEW;
        if ( $is_export )
        {
            $this->rights_required[] = Right::EXPORT;
        }
        $this->user->checkRights( $this->kernel->response['module'], $this->rights_required );

        // Get the message
        $data = array(
            'message' => array_ifnull(
                $this->kernel->dict,
                'DESCRIPTION_' . array_ifnull( $_GET, 'message', '' ),
                ''
            )
        );
        unset( $_GET['message'] );
        $this->kernel->smarty->assignByRef( 'data', $data );

        // Custom entity
        $method = "index_{$_GET['entity']}";
        if ( method_exists($this, $method) )
        {
            $data['list'] = $this->$method();
        }

        // Generic entity
        else
        {
            $method = 'index_generic';

            // Base query condition
            extract( $this->get_query_values() );
            $select = array(
                'id' => 'base_table.id',
                'record_id' => 'base_table.id AS record_id',
                'preview_token' => 'webpage_preview_tokens.token AS preview_token',
                'preview_expiry_time' => "CONVERT_TZ(webpage_preview_tokens.expire_time, 'gmt', {$this->kernel->conf['escaped_timezone']}) AS preview_expiry_time"
            );

            // With multivalued tables
            if ( array_key_exists('multivalued_tables', $this->entity_def) )
            {
                foreach ( $this->entity_def['multivalued_tables'] as $table => $table_def )
                {
                    if ( $table != 'product_entities' && $table_def['type'] != 'tabular' )
                    {
                        $join_on = "base_table.id = $table.{$_GET['entity']}_id";
                        if ( $this->entity_def['status_table'] )
                        {
                            $join_on .= " AND base_table.domain = $table.domain";
                        }
                        $from .= " LEFT OUTER JOIN $table ON ($join_on)";
                        $from .= " LEFT OUTER JOIN $table AS {$table}_k ON (";
                        $from .= str_replace( $table, "{$table}_k", $join_on ) . ')';
                    }
                }
                $group_by = 'base_table.id';
            }

            // Select fields
            $list_fields = $this->entity_def['list_fields'];
            if ( $is_export )
            {
                unset( $select['record_id'] );
                unset( $select['preview_token'] );
                unset( $select['preview_expiry_time'] );
                $list_fields = array();
                foreach ( $this->entity_def['panels'] as $panel => $panel_fields )
                {
                    $list_fields = array_merge( $list_fields, $panel_fields );
                }
            }
            foreach ( $list_fields as $field )
            {
                $field_parts = explode( '.', $field );

                // Base or locale table
                if ( in_array($field_parts[0], array('base_table', 'locale_table')) )
                {
                    list( $table, $field ) = $field_parts;
                    $field_def = $this->entity_def[$table]['fields'][$field];
                    $exp = "$table.$field";
                    if ( in_array($field_def['type'], array('radio', 'select')) )
                    {
                        $exp = $this->kernel->db->translateField( $exp, $field_def['options'] );
                    }
                    else if ( $field_def['type'] == 'datetime' )
                    {
                        $exp = "CONVERT_TZ($exp, 'gmt', {$this->kernel->conf['escaped_timezone']})";
                    }
                    else if ( $field_def['type'] == 'pair' )
                    {
                        $exp = sprintf(
                            'CONCAT(%s, %s, %s)',
                            in_array( $field_def['fields'][0]['type'], array('radio', 'select') )
                                ? $this->kernel->db->translateField( "$table.{$field_def['fields'][0]['field']}", $field_def['fields'][0]['options'] )
                                : "$table.{$field_def['fields'][0]['field']}",
                            $this->kernel->db->escape( $field_def['separator'] ),
                            in_array( $field_def['fields'][1]['type'], array('radio', 'select') )
                                ? $this->kernel->db->translateField( "$table.{$field_def['fields'][1]['field']}", $field_def['fields'][1]['options'] )
                                : "$table.{$field_def['fields'][1]['field']}"
                        );
                    }

                    if ( $table == 'base_table' )
                    {
                        $select[$field] = "$exp AS $field";
                    }
                    else
                    {
                        $select[$field] = strtr(
                            'SUBSTRING_INDEX(GROUP_CONCAT(:exp'
                                . ' ORDER BY locale_table.locale <> :locale'
                                . " SEPARATOR '\r\n'), '\r\n', 1) AS :field",
                            array(
                                ':exp' => $exp,
                                ':locale' => $this->kernel->db->escape( $this->preferred_locale ),
                                ':field' => $field,
                            )
                        );
                    }
                }

                // Multivalued table
                else
                {
                    $table = $field_parts[1];
                    $field_def = $this->entity_def['multivalued_tables'][$table];

                    // Select field
                    if ( $field_def['type'] == 'select' )
                    {
                        $exp = $this->kernel->db->translateField( "$table.{$field_def['field']}", $field_def['options'] );
                        $select[$field] = strtr(
                            'GROUP_CONCAT(DISTINCT :exp ORDER BY :exp SEPARATOR :separator) AS :field',
                            array(
                                ':exp' => $exp,
                                ':separator' => $this->kernel->db->escape( $this->kernel->dict['VALUE_word_separator'] ),
                                ':field' => $table,
                            )
                        );
                    }
                }
            }

            // With domain field
            if ( $this->entity_def['status_table'] )
            {
                $field = "{$this->entity_def['status_table']}.status";
                $this->entity_def['search_fields']['status'] = array(
                    'type' => 'select',
                    'options' => &$this->kernel->dict['SET_statuses'],
                    'operator' => '=',
                    'fields' => array( $field )
                );
                $select['status'] = $this->kernel->db->translateField(
                    $field,
                    $this->kernel->dict['SET_statuses'],
                    'status'
                );
            }

            // Search criteria
            foreach ( $this->entity_def['search_fields'] as $field => $field_def )
            {
                // Match query
                $value = trim( array_ifnull($_GET, "search_$field", '') );
                if ( $value !== '' )
                {
                    if ( strcasecmp($field_def['operator'], 'LIKE') == 0 )
                    {
                        $value = '%' . $this->kernel->db->escapeWildCards( $value ) . '%';
                    }
                    $value = $this->kernel->db->escape( $value );

                    $field_where = array();
                    foreach ( $field_def['fields'] as $field )
                    {
                        $field_parts = explode( '.', $field );

                        // Base table
                        if ( $field_parts[0] == 'base_table' )
                        {
                            $field_where[] = "$field {$field_def['operator']} $value";
                        }

                        // Locale table
                        else if ( $field_parts[0] == 'locale_table' )
                        {
                            $locale_table = "locale_table_{$field_parts[1]}";
                            $join_on = "base_table.id = $locale_table.{$_GET['entity']}_id";
                            $join_on .= " AND $locale_table.{$field_parts[1]} {$field_def['operator']} $value";
                            if ( $this->entity_def['status_table'] )
                            {
                                $join_on .= " AND base_table.domain = $locale_table.domain";
                            }
                            $from .= " LEFT OUTER JOIN {$this->entity_def['locale_table']['name']} AS $locale_table ON ($join_on)";
                            $field_where[] = "$locale_table.{$_GET['entity']}_id IS NOT NULL";
                        }

                        // Base or locale table
                        if ( in_array($field_parts[0], array('base_table', 'locale_table')) )
                        {
                            $field_where[] = "$field {$field_def['operator']} $value";
                        }

                        // Multivalued table
                        else
                        {
                            $field = $this->entity_def['multivalued_tables'][$field_parts[1]]['field'];
                            $field_where[] = "{$field_parts[1]}_k.$field {$field_def['operator']} $value";
                        }
                    }
                    $where[] = '(' . implode( ' OR ', $field_where ) . ')';
                }

                // Range query
                $min_value = trim( array_ifnull($_GET, "search_min_{$field}", '') );
                $max_value = trim( array_ifnull($_GET, "search_max_{$field}", '') );
                if ( $min_value !== '' )
                {
                    if ( $field_def['type'] == 'number' )
                    {
                        $min_value = floatval( $min_value );
                    }
                    $min_value = $this->kernel->db->escape( $min_value );
                    $field_where = array();
                    foreach ( $field_def['fields'] as $field )
                    {
                        $field_parts = explode( '.', $field );
                        if ( $this->entity_def[$field_parts[0]]['fields'][$field_parts[1]]['type'] == 'datetime' )
                        {
                            $field = "DATE(CONVERT_TZ($field, 'gmt', {$this->kernel->conf['escaped_timezone']}))";
                        }
                        $field_where[] = "$field >= $min_value";
                    }
                    $where[] = '(' . implode( ' OR ', $field_where ) . ')';
                }
                if ( $max_value !== '' )
                {
                    if ( $field_def['type'] == 'number' )
                    {
                        $max_value = floatval( $max_value );
                    }
                    $max_value = $this->kernel->db->escape( $max_value );
                    $field_where = array();
                    foreach ( $field_def['fields'] as $field )
                    {
                        $field_parts = explode( '.', $field );
                        if ( $this->entity_def[$field_parts[0]]['fields'][$field_parts[1]]['type'] == 'datetime' )
                        {
                            $field = "DATE(CONVERT_TZ($field, 'gmt', {$this->kernel->conf['escaped_timezone']}))";
                        }
                        $field_where[] = "$field <= $max_value";
                    }
                    $where[] = '(' . implode( ' OR ', $field_where ) . ')';
                }
            }

            // Status value becomes something like: Draft (4/10), Approved (6/10)
            if ( $this->entity_def['status_table'] == 'locale_table' )
            {
                $select['status'] = sprintf(
                    "GROUP_CONCAT(DISTINCT CONCAT(%s, ' (', status_table.status_count, '/%s)')"
                        . ' ORDER BY FIND_IN_SET(status_table.status, %s) SEPARATOR %s) AS status',
                    $this->kernel->db->translateField( 'status_table.status', $this->kernel->dict['SET_statuses'] ),
                    count( $this->kernel->sets['public_locales'] ),
                    $this->kernel->db->escape( implode(',', array_keys($this->kernel->dict['SET_statuses'])) ),
                    $this->kernel->db->escape( $this->kernel->dict['VALUE_word_separator'] )
                );
                $from .= " LEFT OUTER JOIN (SELECT domain, {$_GET['entity']}_id, status, COUNT(*) AS status_count";
                $from .= " FROM {$this->entity_def['locale_table']['name']}";
                $from .= ' WHERE locale IN (' . implode( ', ', array_map(array($this->kernel->db, 'escape'), array_keys($this->kernel->sets['public_locales'])) ) . ')';
                $from .= " GROUP BY domain, {$_GET['entity']}_id, status) AS status_table";
                $from .= " ON (base_table.domain = status_table.domain AND base_table.id = status_table.{$_GET['entity']}_id)";

                $select['publicized'] = strtr(
                    sprintf(
                        'IF(publicized_table.publicized_count > 0, %s, %s) AS publicized',
                        sprintf( "CONCAT(%s, ' (', publicized_table.publicized_count, '/', :public_locale_count, ')')", $this->kernel->db->escape($this->kernel->dict['LABEL_yes']) ),
                        sprintf( "CONCAT(%s, ' (', :public_locale_count, '/', :public_locale_count, ')')", $this->kernel->db->escape($this->kernel->dict['LABEL_no']) )
                    ),
                    array( ':public_locale_count' => count($this->kernel->sets['public_locales']) )
                );
                $from .= " LEFT OUTER JOIN (SELECT {$_GET['entity']}_id, COUNT(*) AS publicized_count";
                $from .= " FROM {$this->entity_def['locale_table']['name']}";
                $from .= " WHERE domain = 'public' AND locale IN (" . implode( ', ', array_map(array($this->kernel->db, 'escape'), array_keys($this->kernel->sets['public_locales'])) ) . ')';
                $from .= " GROUP BY {$_GET['entity']}_id) AS publicized_table ON (base_table.id = publicized_table.{$_GET['entity']}_id)";
            }

            // Actions
            $referer_url = '?' . http_build_query( $_GET );
            $record_actions = $list_actions = array();
            if ( $this->user->hasRights($this->kernel->response['module'], array(Right::CREATE)) )
            {
                $list_actions['?' . http_build_query(array(
                    'entity' => $_GET['entity'],
                    'op' => 'edit',
                    'referer_url' => $referer_url
                )).'#/edit_global'] = $this->kernel->dict['ACTION_new'];
            }
            if ( $this->user->hasRights($this->kernel->response['module'], array(Right::EXPORT)) )
            {
                $list_actions['?' . http_build_query(array(
                    'entity' => $_GET['entity'],
                    'op' => 'export',
                    'referer_url' => $referer_url
                ))] = $this->kernel->dict['ACTION_export'];
            }
            if ( $this->user->hasRights($this->kernel->response['module'], array(Right::EDIT)) )
            {
                $record_actions['?' . http_build_query(array(
                    'entity' => $_GET['entity'],
                    'op' => 'edit',
                    'referer_url' => $referer_url,
                    'id' => ''
                ))] = $this->kernel->dict['ACTION_edit'];

                if ( $this->user->hasRights($this->kernel->response['module'], array(Right::CREATE)) )
                {
                    $record_actions['?' . http_build_query(array(
                        'entity' => $_GET['entity'],
                        'op' => 'edit',
                        'referer_url' => $referer_url,
                        'duplicate' => 1,
                        'id' => ''
                    ))] = $this->kernel->dict['ACTION_duplicate'];
                }

                if ( $this->user->hasRights($this->kernel->response['module'], array(Right::APPROVE))
                    && ($this->entity_def['status_table'] == 'base_table' || $this->user->isGlobalUser()) )
                {
                    $record_actions['?' . http_build_query(array(
                        'entity' => $_GET['entity'],
                        'op' => 'delete',
                        'referer_url' => $referer_url,
                        'id' => ''
                    ))] = $this->kernel->dict['ACTION_delete'];
                }

                if ( $this->entity_def['order_by']['field'] == 'order_index' )
                {
                    $list_actions['?' . http_build_query(array(
                        'entity' => $_GET['entity'],
                        'op' => 'change_order',
                        'referer_url' => $referer_url
                    ))] = $this->kernel->dict['ACTION_change_order'];
                }
            }

            // Get the requested entities
            if ( $is_export )
            {
                $select['created_date'] = "CONVERT_TZ(base_table.created_date, 'gmt', {$this->kernel->conf['escaped_timezone']}) AS created_date";
                $select['creator'] = 'creators.email AS creator';
                $select['updated_date'] = "CONVERT_TZ(base_table.updated_date, 'gmt', {$this->kernel->conf['escaped_timezone']}) AS updated_date";
                $select['updater'] = 'updaters.email AS updater';
                $from .= ' LEFT OUTER JOIN users AS creators ON (base_table.creator_id = creators.id)';
                $from .= ' LEFT OUTER JOIN users AS updaters ON (base_table.updater_id = updaters.id)';
                $sql = 'SELECT ' . implode( ', ', $select ) . " FROM $from";
                $sql .= ' WHERE ' . implode( ' AND ', $where );
                if ( $group_by !== '' )
                {
                    $sql .= " GROUP BY $group_by";
                }
                $sql .= " ORDER BY {$this->entity_def['order_by']['field']} {$this->entity_def['order_by']['dir']}";
                $data['list'] = $this->kernel->get_spreadsheet_list_from_db( $sql );
            }
            else
            {
                $data['list'] = $this->kernel->get_smarty_list_from_db(
                    'entity_list',
                    'id',
                    array(
                        'select' => implode( ',', $select ),
                        'from' => $from,
                        'where' => implode( ' AND ', $where ),
                        'group_by' => $group_by,
                        'having' => '',
                        'default_order_by' => $this->entity_def['order_by']['field'],
                        'default_order_dir' => $this->entity_def['order_by']['dir']
                    ),
                    array(),
                    $record_actions,
                    $list_actions,
                    '',
                    'module/entity_admin/list_generic.html'
                );
            }
        }

        // Set page title and content
        if ( $is_export )
        {
            $entities = strtolower( $this->kernel->entity_admin_def[$this->module]['children'][$_GET['entity']]['name'] );
            $spreadsheet_type_attributes = $this->kernel->sets['spreadsheet_type_attributes'][$this->kernel->conf['spreadsheet_type']];
            $this->apply_template = FALSE;
            $this->kernel->response['charset'] = '';
            $this->kernel->response['filename'] = $_GET['entity'] . '.' . $spreadsheet_type_attributes['file_extension'];
            $this->kernel->response['disposition'] = 'attachment';
            $this->kernel->response['mimetype'] = $spreadsheet_type_attributes['mimetype'];
            $this->kernel->response['content'] = $data['list']['content'];
            $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> exported {$data['list']['count']} $entities.", __FILE__, __LINE__ );
        }
        else
        {
            $this->kernel->response['titles'][count($this->kernel->response['titles']) - 1] = $this->module_title;

            $load_search_form = true;
            if ( $method != 'index_move_category' )
            {
                $this->kernel->response['content'] = $this->kernel->smarty->fetch(
                    file_exists( "module/{$this->module}/$method.html" )
                        ? "module/{$this->module}/$method.html"
                        : "module/entity_admin/$method.html"
                );
            }
            else
            {
                $load_search_form = false;
            }

            $this->kernel->smarty->assign( 'load_search_form', $load_search_form );
            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/entity_admin/index.html' );
        }
    }

    /**
    * Change order of entities.
    *
    * @since   2019-09-04
    */
    function change_order()
    {
        try
        {
            // Check authentication
            $this->rights_required[] = Right::EDIT;
            $this->user->checkRights( $this->kernel->response['module'], $this->rights_required );
            if ( $this->entity_def['order_by']['field'] != 'order_index' )
            {
                throw new privilegeException( 'insufficient_rights' );
            }
        }
        catch ( \Exception $e )
        {
            $this->processException( $e );
        }

        // Data container
        $data = array();

        // Base query condition
        extract( $this->get_query_values() );

        // Edit page
        if ( $_SERVER['REQUEST_METHOD'] == 'GET' )
        {
            // Select fields
            $select = array( 'base_table.id', 'base_table.order_index' );
            $exp = '';
            if ( array_key_exists('field', $this->entity_def['name']) )
            {
                $exp = "{$this->entity_def['name']['table']}.{$this->entity_def['name']['field']}";
            }
            else
            {
                $exp = 'CONCAT(' . implode(
                    ', ' . $this->kernel->db->escape( $this->kernel->dict['VALUE_title_separator'] ) . ', ',
                    array_map( function($f) { return "{$this->entity_def['name']['table']}.$f"; }, $this->entity_def['name']['fields'] )
                ) . ')';
            }
            if ( $this->entity_def['name']['table'] == 'base_table' )
            {
                $select[] = "$exp AS name";
            }
            else
            {
                $select[] = strtr(
                    'SUBSTRING_INDEX(GROUP_CONCAT(:exp'
                        . ' ORDER BY locale_table.locale <> :locale'
                        . " SEPARATOR '\r\n'), '\r\n', 1) AS name",
                    array(
                        ':exp' => $exp,
                        ':locale' => $this->kernel->db->escape( $this->preferred_locale )
                    )
                );
            }

            // Get the requested entities
            $sql = 'SELECT ' . implode( ', ', $select ) . " FROM $from";
            $sql .= ' WHERE ' . implode( ' AND ', $where );
            if ( $group_by )
            {
                $sql .= " GROUP BY $group_by";
            }
            $sql .= ' ORDER BY order_index, id';
            $statement = $this->kernel->db->query( $sql );
            $data['entities'] = $statement->fetchAll();

            // Set page title and content
            $this->kernel->smarty->assignByRef( 'data', $data );
            $this->kernel->response['titles'][] = sprintf(
                $this->kernel->dict['SET_operations']['change_order'],
                $this->kernel->entity_admin_def[$this->module]['children'][$_GET['entity']]['name']
            );
            $this->_breadcrumb->push( new breadcrumbNode(
                end( $this->kernel->response['titles'] ),
                $this->kernel->sets['paths']['mod_from_doc'] . '/?' . http_build_query( $_GET )
            ) );
            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/entity_admin/change_order.html' );
        }

        // Save data
        else if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
        {
            // Get data from form post
            $entity_ids = array_unique( array_map('intval', array_ifnull($_POST, 'entity_ids', array())) );

            // Update existing entities
            $where = array_map( function($w) {
                return $w == "base_table.domain = 'private'"
                    ? "(base_table.domain = 'private' OR locale_table.status = 'approved')"
                    : $w;
            }, $where );
            $where[] = 'base_table.id = :id';
            $sql_template = "UPDATE $from";
            $sql_template .= ' SET base_table.order_index = :order_index';
            $sql_template .= ' WHERE ' . implode( ' AND ', $where );
            foreach ( $entity_ids as $i => $entity_id )
            {
                $sql = strtr( $sql_template, array(
                    ':id' => $entity_id,
                    ':order_index' => $i + 1
                ) );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
            }
            $this->kernel->log( 'message', sprintf(
                "User %s <%s> changed order of %s",
                $this->user->getId(),
                $this->user->getEmail(),
                strtolower( $this->kernel->entity_admin_def[$this->module]['children'][$_GET['entity']]['name'] )
            ), __FILE__, __LINE__ );

            // Redirect to next page
            $this->kernel->redirect( array_ifnull($_GET, 'referer_url', "?entity={$_GET['entity']}") . '&message=saved' );
        }

        // Clear cache
        $this->clear_cache();
    }

    /**
     * Get an entity based on ID.
     *
     * @since   2015-06-22
     * @return  The data
     */
    function get()
    {
        // Data container
        $data = array(
            'preferred_locale' => $this->preferred_locale,
            'name' => sprintf( $this->kernel->dict['FORMAT_new_entity'], $this->kernel->dict['SET_entities'][$_GET['entity']] ),
            'base_table' => array( 'id' => intval(array_ifnull($_GET, 'id', '')) )
        );

        // Get the requested base entity
        $sql = "SELECT * FROM {$this->entity_def['base_table']['name']}";
        $sql .= " WHERE deleted = 0 AND id = {$data['base_table']['id']}";
        if ( $this->entity_def['status_table'] )
        {
            $sql .= " AND domain = 'private'";
        }
        if ( array_key_exists('locale', $this->entity_def['base_table']['fields']) )
        {
            $sql .= ' AND locale IN (' . implode( ', ', array_map(
                array( $this->kernel->db, 'escape' ),
                array_merge( $this->user->getAccessibleLocales(), array('') )
            ) ) . ')';
        }
        $statement = $this->kernel->db->query( $sql );
        if ( !$statement )
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
        $data['base_table'] = ( $record = $statement->fetch() ) ? $record : array( 'id' => 0 );

        // Get additional data for existing entity
        if ( $data['base_table']['id'] )
        {
            foreach ( $this->entity_def['base_table']['fields'] as $field => $field_def )
            {
                if ( $field_def['type'] == 'datetime' )
                {
                    $data['base_table'][$field] = convert_tz( $data['base_table'][$field], 'gmt', $this->kernel->conf['timezone'] );
                }
            }

            $where = "{$_GET['entity']}_id = {$data['base_table']['id']}";
            if ( $this->entity_def['status_table'] )
            {
                $where .= " AND domain = 'private'";
            }

            // Get the requested locale entity
            if ( array_key_exists('locale_table', $this->entity_def) )
            {
                $sql = "SELECT * FROM {$this->entity_def['locale_table']['name']} WHERE $where";
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
                while ( $record = $statement->fetch() )
                {
                    $data['locale_table'][$record['locale']] = $record;
                }
            }

            // Get the requested multivalued entity
            if ( array_key_exists('multivalued_tables', $this->entity_def) )
            {
                foreach ( $this->entity_def['multivalued_tables'] as $table => $field_def )
                {
                    // Select and webpage field
                    if ( in_array($field_def['type'], array('select', 'webpage')) )
                    {
                        $sql = "SELECT {$field_def['field']} FROM $table WHERE $where";
                        if ( $table == 'product_entities' )
                        {
                            $sql = 'SELECT product_id FROM product_entities';
                            $sql .= " WHERE domain = 'private'";
                            $sql .= ' AND locale = ' . $this->kernel->db->escape( $data['base_table']['locale'] );
                            $sql .= " AND entity_type = '{$_GET['entity']}'";
                            $sql .= " AND entity_id = {$data['base_table']['id']}";
                        }
                        $data['multivalued_tables'][$table] = $this->kernel->get_set_from_db( $sql );
                    }

                    // Tabular field
                    else if ( $field_def['type'] == 'tabular' )
                    {
                        $sql = "SELECT * FROM $table WHERE $where";
                        $statement = $this->kernel->db->query( $sql );
                        if ( !$statement )
                        {
                            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                        }
                        while ( $record = $statement->fetch() )
                        {
                            $data['multivalued_tables'][$table][$record[$field_def['field']]] = $record;
                        }
                    }
                }
            }

            // Get the requested entity name
            $data['name'] = $this->get_name( $data );
        }

        // Get additional data for new entity
        else if ( array_key_exists('locale', $this->entity_def['base_table']['fields']) )
        {
            $data['base_table']['locale'] = $this->preferred_locale;
            $opener_field = trim( array_ifnull($_GET, 'opener_field', '') );
            preg_match( '/\[([^\]]*)\]/', $opener_field, $opener_field_matches );
            if ( array_ifnull($opener_field_matches, 1, '') !== '' )
            {
                foreach ( $this->kernel->dict['SET_accessible_locales'] as $locale => $locale_name )
                {
                    if ( $locale !== $opener_field_matches[1] )
                    {
                        unset( $this->kernel->dict['SET_accessible_locales'][$locale] );
                    }
                }
            }
        }

        // Custom entity
        $method = "get_{$_GET['entity']}";
        if ( method_exists($this, $method) )
        {
            $this->$method( $data );
        }

        // Duplicate
        if ( array_ifnull($_GET, 'duplicate', 0) )
        {
            $data['base_table']['id'] = 0;
            switch ( $this->entity_def['status_table'] )
            {
                case 'base_table':
                    unset( $data['base_table']['status'] );
                    break;

                case 'locale_table':
                    foreach ( $data['locale_table'] as $locale => $locale_data )
                    {
                        unset( $data['locale_table'][$locale]['status'] );
                    }
                    break;
            }
        }

        return $data;
    }

    /**
     * Get the name of an entity.
     *
     * @since   2015-06-25
     * @param   data    The data
     * @return  The name
     */
    function get_name( $data )
    {
        extract( $this->entity_def['name'] );
        $target = NULL;
        if ( $table == 'base_table' )
        {
            $target = &$data['base_table'];
        }
        else if ( array_key_exists($this->kernel->request['locale'], $data['locale_table']) )
        {
            $target = &$data['locale_table'][$this->kernel->request['locale']];
        }
        else if ( array_key_exists($this->preferred_locale, $data['locale_table']) )
        {
            $target = &$data['locale_table'][$this->preferred_locale];
        }
        else
        {
            $locale_data = current( $data['locale_table'] );
            $target = &$locale_data;
        }
        if ( isset($field) )
        {
            return $target[$field];
        }
        else
        {
            $values = array();
            foreach ( $fields as $field )
            {
                $values[] = $target[$field];
            }
            return implode( $this->kernel->dict['VALUE_title_separator'], $values );
        }
    }

    /**
     * Get webpage nodes in Fancytree format (no lazying loading).
     *
     * @since   2020-03-24
     * @param   inputs                  The input nodes
     * @param   accessible_webpages     The accessible webpages
     * @param   deleted                 Deleted in parent
     * @param   status                  Status in parent
     * @return  The webpage nodes
     */
    function get_webpage_nodes( $inputs, $accessible_webpages = '', $deleted = FALSE, $status = NULL )
    {
        $outputs = array();
        foreach ( $inputs as $input )
        {
            if ( !is_array($accessible_webpages)
                || in_array($input->getItem()->getId(), $accessible_webpages)
                || $input->childrenExists($accessible_webpages) )
            {
                $classes = array(
                    'status' => FALSE
                );
                $info = $input->getNodeInfo( $this->user->getPreferredLocale() );
                $output = array(
                    'title' => ( $info['title'] ? $info['title'] : '(' . $this->kernel->dict['LABEL_no_title'] . ')' ) . ' - [#' . $info['id'] . ']',
                    'key' => $info['id'],
                    'tooltip' => $info['path']
                );

                if ( $info['deleted'] || $deleted )
                {
                    $classes[] = 'deleted';
                }

                if ( in_array($info['status'], array('pending', 'draft')) )
                {
                    $classes['status'] = $info['status'];
                }
                else if ( $status )
                {
                    $classes['status'] = $status;
                }

                if ( $info['hasChild'] )
                {
                    $children = $this->get_webpage_nodes( $input->getChildren(0), $accessible_webpages, in_array('deleted', $classes), $classes['status'] == 'pending' ? 'pending' : NULL );
                    if ( count($children) > 0 )
                    {
                        $output['children'] = $children;
                    }
                }

                $output['extraClasses'] = implode( ' ', array_filter($classes, 'strlen') );

                $outputs[] = $output;
            }
        }
        return $outputs;
    }

    /**
     * Edit an entity based on ID.
     *
     * @since   2015-06-22
     */
    function edit()
    {
        $is_xhr = array_ifnull( $_SERVER, 'HTTP_X_REQUESTED_WITH', '' ) == 'XMLHttpRequest';
        $method = "edit_{$_GET['entity']}";
        if ( !method_exists($this, $method) )
        {
            $method = 'edit_generic';
        }

        // Get the requested entity
        $data = $this->get();

        try
        {
            $preview = FALSE;

            // Check authentication
            $this->rights_required[] = $data['base_table']['id'] ? Right::EDIT : Right::CREATE;
            $this->user->checkRights( $this->kernel->response['module'], $this->rights_required );
            if ( !$data['base_table']['id']
                && in_array($_GET['entity'], array('category'))
                && !$this->user->isGlobalUser() )
            {
                throw new privilegeException( 'insufficient_rights' );
            }

            // Edit page
            if ( $_SERVER['REQUEST_METHOD'] == 'GET' )
            {
                // Add an asterisk to locales not yet approved
                if ( $this->entity_def['status_table'] == 'locale_table' )
                {
                    foreach ( $this->kernel->dict['SET_accessible_locales'] as $locale => &$locale_name )
                    {
                        if ( !$data['base_table']['id']
                            || !array_key_exists($locale, $data['locale_table'])
                            || $data['locale_table'][$locale]['status'] != 'approved' )
                        {
                            $locale_name .= ' *';
                        }
                    }
                }

                // Generate webpage nodes
                $data['webpage_nodes'] = array();
                $sitemap = $this->get_sitemap( 'index', 'desktop', TRUE );
                $root = $sitemap->getRoot();
                if ( $root )
                {
                    $data['webpage_nodes'] = $this->get_webpage_nodes( array($root), webpage_admin_module::getWebpageAccessibility() );
                }

                // Set page title and content
                $this->kernel->smarty->assignByRef( 'data', $data );
                $this->kernel->response['titles'][] = sprintf(
                    $this->kernel->dict['SET_operations'][$data['base_table']['id'] ? 'edit' : (array_ifnull($_GET, 'duplicate', 0) ? 'duplicate' : 'new')],
                    $data['name']
                );
                $this->_breadcrumb->push( new breadcrumbNode(
                    end( $this->kernel->response['titles'] ),
                    $this->kernel->sets['paths']['mod_from_doc'] . '/?' . http_build_query( $_GET )
                ) );
                $this->kernel->response['content'] = $this->kernel->smarty->fetch(
                    file_exists( "module/{$this->module}/$method.html" )
                        ? "module/{$this->module}/$method.html"
                        : "module/entity_admin/$method.html"
                );
            }

            // Save data
            else if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
            {
                $errors = array();

                // Select locales
                if ( $this->entity_def['status_table'] == 'locale_table' )
                {
                    $locales = array_ifnull( $_POST, 'locales', array() );
                    foreach ( $this->kernel->dict['SET_accessible_locales'] as $locale => $locale_name )
                    {
                        if ( !in_array($locale, $locales) )
                        {
                            unset( $this->kernel->dict['SET_accessible_locales'][$locale] );
                        }
                    }
                }

                // Generic entity
                if ( $method == 'edit_generic' )
                {
                    // Cleanup locale data in form post
                    if ( $this->entity_def['status_table'] == 'locale_table' )
                    {
                        $data['locale_table'] = array();
                        if ( count($this->kernel->dict['SET_accessible_locales']) > 0 )
                        {
                            $_POST['locale_table'] = array_ifnull( $_POST, 'locale_table', array() );
                            foreach ( $_POST['locale_table'] as $locale => $locale_data )
                            {
                                if ( !array_key_exists($locale, $this->kernel->dict['SET_accessible_locales'])
                                    || implode('', array_map('trim', $locale_data)) === '' )
                                {
                                    unset( $_POST['locale_table'][$locale] );
                                }
                            }
                            if ( count($_POST['locale_table']) == 0 )
                            {
                                $errors['locale_table[' . current(
                                    array_keys( $this->entity_def['locale_table']['fields'] )
                                ) . ']'][] = 'locale_table_blank';
                            }
                        }
                        else
                        {
                            $_POST['locale_table'] = array();
                        }
                    }

                    // Process data from form post
                    foreach ( $this->entity_def['panels'] as $panel => $fields )
                    {
                        foreach ( $fields as $field )
                        {
                            list( $table, $field ) = explode( '.', $field );

                            // Base table
                            if ( $table == 'base_table' )
                            {
                                $field_def = $this->entity_def['base_table']['fields'][$field];

                                // Pair field
                                if ( $field_def['type'] == 'pair' )
                                {
                                    // Get and cleanup data
                                    $values = array();
                                    foreach ( $field_def['fields'] as $pair_field_def )
                                    {
                                        // Get data
                                        $value = isset( $_POST['base_table'][$pair_field_def['field']] )
                                            && trim( $_POST['base_table'][$pair_field_def['field']] ) !== ''
                                            ? $_POST['base_table'][$pair_field_def['field']] : NULL;

                                        // Cleanup data
                                        if ( !is_null($value) )
                                        {
                                            // Text, HTML or file field
                                            if ( in_array($pair_field_def['type'], array('text', 'html', 'file')) )
                                            {
                                                $value = trim( mb_substr($value, 0, $pair_field_def['maxlength']) );
                                            }

                                            // Radio or select field
                                            if ( in_array($pair_field_def['type'], array('radio', 'select'))
                                                && !array_key_exists($value, $pair_field_def['options']) )
                                            {
                                                $value = NULL;
                                            }

                                            // Date field
                                            else if ( $pair_field_def['type'] == 'date' )
                                            {
                                                $value = string_to_date( $value );
                                            }
                                        }

                                        $data['base_table'][$pair_field_def['field']] = $values[] = $value;
                                    }

                                    // Validate data
                                    foreach ( $field_def['fields'] as $i => $pair_field_def )
                                    {
                                        if ( is_null($values[$i])
                                            && ($field_def['required'] || !is_null($values[($i + 1) % 2])) )
                                        {
                                            $errors["base_table[$field]"][] = "{$field}_blank";
                                        }
                                    }
                                    continue;
                                }

                                // Get data
                                $value = isset( $_POST['base_table'][$field] )
                                    && trim( $_POST['base_table'][$field] ) !== ''
                                    ? $_POST['base_table'][$field] : NULL;

                                // Cleanup data
                                if ( !is_null($value) )
                                {
                                    // Text, HTML or file field
                                    if ( in_array($field_def['type'], array('text', 'html', 'file')) )
                                    {
                                        $value = trim( mb_substr($value, 0, $field_def['maxlength']) );
                                    }

                                    // Number field
                                    else if ( $field_def['type'] == 'number' )
                                    {
                                        $value = min(
                                            max( floatval($value), $field_def['min'] ),
                                            $field_def['max']
                                        );
                                    }

                                    // Radio or select field
                                    else if ( in_array($field_def['type'], array('radio', 'select'))
                                        && !array_key_exists($value, $field_def['options']) )
                                    {
                                        $value = NULL;
                                    }

                                    // Date field
                                    else if ( $field_def['type'] == 'date' )
                                    {
                                        $value = string_to_date( $value );
                                    }
                                }

                                // Validate data
                                if ( $field_def['required'] && is_null($value) )
                                {
                                    $errors["base_table[$field]"][] = "{$field}_blank";
                                }

                                $data['base_table'][$field] = $value;
                            }

                            // Locale table
                            else if ( $table == 'locale_table' )
                            {
                                $field_def = $this->entity_def['locale_table']['fields'][$field];

                                $locale_errors = array();
                                foreach ( $_POST['locale_table'] as $locale => $locale_data )
                                {
                                    $locale_name = $this->kernel->dict['SET_accessible_locales'][$locale];

                                    // Get data
                                    $value = trim( array_ifnull($locale_data, $field, '') );
                                    if ( $value === '' ) $value = NULL;

                                    // Cleanup data
                                    if ( !is_null($value) )
                                    {
                                        // Text, HTML or file field
                                        if ( in_array($field_def['type'], array('text', 'html', 'file')) )
                                        {
                                            $value = trim( mb_substr($value, 0, $field_def['maxlength']) );
                                        }
                                    }

                                    // Validate data
                                    if ( $field_def['required'] && is_null($value) )
                                    {
                                        $locale_errors["locale_{$field}_blank"][] = $locale_name;
                                    }

                                    $data['locale_table'][$locale][$field] = $value;
                                }
                                if ( count($locale_errors) > 0 )
                                {
                                    foreach ( $locale_errors as $error => $locale_names )
                                    {
                                        $this->kernel->dict["ERROR_$error"] = sprintf(
                                            $this->kernel->dict["FORMAT_$error"],
                                            implode( $this->kernel->dict['VALUE_word_separator'], $locale_names )
                                        );
                                        $errors["locale_table[$field]"][] = $error;
                                    }
                                }
                            }

                            // Multivalued table
                            else if ( $table == 'multivalued_tables' )
                            {
                                $field_def = $this->entity_def['multivalued_tables'][$field];
                                $values = array();
                                $error_key = "multivalued_tables[$field][]";

                                // Select and webpage fields
                                if ( in_array($field_def['type'], array('select', 'webpage')) )
                                {
                                    // Get data
                                    $values = isset( $_POST['multivalued_tables'][$field] )
                                        && is_array( $_POST['multivalued_tables'][$field] )
                                        ? $_POST['multivalued_tables'][$field] : array();

                                    // Cleanup data
                                    $values = array_unique( array_diff(array_map('trim', $values), array('')) );
                                    if ( $field_def['type'] == 'select' )
                                    {
                                        $values = array_intersect( $values, array_keys($field_def['options']) );
                                    }
                                    $values = array_values( $values );

                                    // Validate data
                                    if ( $field_def['required'] && count($values) == 0 )
                                    {
                                        $errors[$error_key][] = "{$field}_blank";
                                    }
                                }

                                // Tabular field
                                else if ( $field_def['type'] == 'tabular' )
                                {
                                    // Get and cleanup data
                                    $keys = isset( $_POST['multivalued_tables'][$field][$field_def['field']] )
                                        && is_array( $_POST['multivalued_tables'][$field][$field_def['field']] )
                                        ? array_keys( $_POST['multivalued_tables'][$field][$field_def['field']] ) : array();
                                    foreach ( $keys as $key )
                                    {
                                        $item = array();
                                        foreach ( $field_def['fields'] as $tabular_field => $tabular_field_def )
                                        {
                                            $item[$tabular_field] = isset( $_POST['multivalued_tables'][$field][$tabular_field][$key] )
                                                ? trim( $_POST['multivalued_tables'][$field][$tabular_field][$key] ) : '';
                                            if ( $item[$tabular_field] === '' ) $item[$tabular_field] = NULL;

                                            // Cleanup data
                                            if ( !is_null($item[$tabular_field]) )
                                            {
                                                // Text field
                                                if ( $tabular_field_def['type'] == 'text' )
                                                {
                                                    $item[$tabular_field] = trim( mb_substr($item[$tabular_field], 0, $tabular_field_def['maxlength']) );
                                                }

                                                // Number field
                                                else if ( $tabular_field_def['type'] == 'number' )
                                                {
                                                    $item[$tabular_field] = min(
                                                        max( floatval($item[$tabular_field]), $tabular_field_def['min'] ),
                                                        $tabular_field_def['max']
                                                    );
                                                }
                                            }
                                        }
                                        if ( implode('', $item) !== '' )
                                        {
                                            $values[] = $item;
                                        }
                                    }

                                    // Validate data
                                    if ( $field_def['required'] && count($values) == 0 )
                                    {
                                        $errors[$error_key][] = "{$field}_blank";
                                    }
                                    else
                                    {
                                        foreach ( $values as $item )
                                        {
                                            foreach ( $field_def['fields'] as $tabular_field => $tabular_field_def )
                                            {
                                                if ( $tabular_field_def['required'] && is_null($item[$tabular_field]) )
                                                {
                                                    $errors[$error_key][] = "{$field}_{$tabular_field}_blank";
                                                }
                                            }
                                        }
                                        if ( array_key_exists($error_key, $errors) )
                                        {
                                            $errors[$error_key] = array_values( array_unique($errors[$error_key]) );
                                        }
                                    }
                                }

                                $data['multivalued_tables'][$field] = $values;
                            }
                        }
                    }

                    // Validate name
                    $fields = array_key_exists( 'fields', $this->entity_def['name'] )
                        ? $this->entity_def['name']['fields']
                        : array( $this->entity_def['name']['field'] );
                    extract( $this->get_query_values() );
                    $where[] = "base_table.id <> {$data['base_table']['id']}";
                    if ( $this->entity_def['name']['table'] == 'base_table' )
                    {
                        $values = array();
                        foreach ( $fields as $field )
                        {
                            $values[$field] = $data['base_table'][$field];
                        }
                        if ( implode('', $values) !== '' )
                        {
                            if ( array_key_exists('locale', $this->entity_def['base_table']['fields']) )
                            {
                                $where[] = 'base_table.locale = ' . $this->kernel->db->escape( $data['base_table']['locale'] );
                            }
                            foreach ( $values as $field => $value )
                            {
                                $where[] = "base_table.$field " . ( is_null($value) ? ' IS NULL ' : ' = ' . $this->kernel->db->escape($value) );
                            }
                            $sql = "SELECT COUNT(*) AS value_used FROM $from";
                            $sql .= ' WHERE ' . implode( ' AND ', $where );
                            $statement = $this->conn->query( $sql );
                            if ( !$statement )
                            {
                                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                            }
                            extract( $statement->fetch() );
                            if ( $value_used )
                            {
                                $errors['base_table[' . reset($fields) . ']'][] = implode( '_', $fields ) . '_used';
                            }
                        }
                    }
                    else
                    {
                        $locale_names = array();
                        foreach ( $this->kernel->dict['SET_accessible_locales'] as $locale => $locale_name )
                        {
                            $values = array();
                            foreach ( $fields as $field )
                            {
                                $values[$field] = array_key_exists( $locale, $data['locale_table'] )
                                    ? $data['locale_table'][$locale][$field]
                                    : NULL;
                            }
                            if ( implode('', $values) !== '' )
                            {
                                $locale_where = $where;
                                $locale_where[] = 'locale_table.locale = ' . $this->kernel->db->escape( $locale );
                                foreach ( $values as $field => $value )
                                {
                                    $locale_where[] = "locale_table.$field" . ( is_null($value) ? ' IS NULL ' : ' = ' . $this->kernel->db->escape($value) );
                                }
                                $sql = "SELECT COUNT(*) AS value_used FROM $from";
                                $sql .= ' WHERE ' . implode( ' AND ', $locale_where );
                                $statement = $this->conn->query( $sql );
                                if ( !$statement )
                                {
                                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                                }
                                extract( $statement->fetch() );
                                if ( $value_used )
                                {
                                    $locale_names[] = $locale_name;
                                }
                            }
                        }
                        if ( count($locale_names) > 0 )
                        {
                            $error = 'locale_' . implode( '_', $fields ) . '_used';
                            $this->kernel->dict["ERROR_$error"] = sprintf(
                                $this->kernel->dict["FORMAT_$error"],
                                implode( $this->kernel->dict['VALUE_word_separator'], $locale_names )
                            );
                            $errors['locale_table[' . reset($fields) . ']'][] = $error;
                        }
                    }
                }

                // Custom entity
                else
                {
                    $this->$method( $data, $errors );
                }

                // Get status from form post
                $status = $this->entity_def['status_table'] ? array_ifnull( $_POST, 'status', '' ) : '';
                if ( $status == 'preview' )
                {
                    $preview = $_REQUEST['ajax'] = TRUE;
                }
                if ( !array_key_exists($status, $this->kernel->dict['SET_statuses'])
                    || $status == 'pending' && $this->user->hasRights($this->kernel->response['module'], array(Right::APPROVE)) )
                {
                    $status = 'draft';
                }
                if ( $status == 'approved' && !$this->user->hasRights($this->kernel->response['module'], array(Right::APPROVE)) )
                {
                    $status = 'pending';
                }

                // Stop if there is error
                if ( count($errors) > 0 )
                {
                    throw new fieldsException( $errors );
                }

                // Stop to allow file upload
                else if ( $is_xhr && $method == 'edit_product' )
                {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = '{}';
                    return;
                }

                // Save entity
                $_GET['id'] = $id = $this->save( $data, $status );

                // Choose operation
                $message = $status == 'draft' ? 'saved' : $status;
                $redirect = '';
                if ( $preview )
                {
                    $redirect = '?' . http_build_query( array(
                        'entity' => $_GET['entity'],
                        'op' => 'preview',
                        'locale' => array_ifnull( $_POST, 'preview_locale', '' ),
                        'id' => $id
                    ) );
                }
                else if ( array_ifnull($_GET, 'opener_field', '') === '' )
                {
                    $referer_url = array_ifnull( $_GET, 'referer_url', '' );
                    $redirect = str_replace(
                        'entity=',
                        "message=$message&entity=",
                        strpos( $referer_url, 'entity=' ) === FALSE
                            ? '?entity=' . urlencode( $_GET['entity'] )
                            : $referer_url
                    );
                }
                else
                {
                    $redirect = '?' . http_build_query( array(
                        'entity' => $_GET['entity'],
                        'op' => 'add_option',
                        'opener_field' => $_GET['opener_field'],
                        'option_id' => $id,
                        'option_name' => $this->get_name( $data ),
                        'message' => $message
                    ) );
                }

                // Redirect to next page
                if ( $is_xhr )
                {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( array(
                       'result' => 'success',
                       'redirect' => $redirect
                    ));
                }
                else
                {
                    $this->kernel->redirect( $redirect );
                }
            }
        }
        catch ( Exception $e )
        {
            $this->processException( $e );
            if ( $preview )
            {
                $this->apply_template = FALSE;
                $this->kernel->response['mimetype'] = 'text/html';
                $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/entity_admin/preview_exception.html' );
            }
        }
    }

    /**
     * Save an entity based on data.
     *
     * @since   2015-06-25
     * @param   data                The data
     * @param   status              The status
     * @param   selected_locale     The selected locale
     * @return  The ID
     */
    function save( $data, $status, $selected_locale = '' )
    {
        $id = $data['base_table']['id'];
        $entity_key = "{$_GET['entity']}_id";
        $method = "save_{$_GET['entity']}";

        // Custom entity
        if ( method_exists($this, $method) )
        {
            $id = $this->$method( $data, $status, $selected_locale );
        }

        // Generic entity
        else
        {
            /***********************************************************************
             * Base table
             **********************************************************************/

            $table = $this->entity_def['base_table']['name'];
            $fields = $where = array();
            foreach ( $this->entity_def['base_table']['fields'] as $field => $field_def )
            {
                if ( $field_def['type'] == 'datetime' )
                {
                    $data['base_table'][$field] = convert_tz(
                        $data['base_table'][$field],
                        $this->kernel->conf['timezone'],
                        'gmt'
                    );
                }
                else if ( $field_def['type'] == 'pair' )
                {
                    foreach ( $field_def['fields'] as $pair_field_def )
                    {
                        $fields[$pair_field_def['field']] = $this->kernel->db->escape( $data['base_table'][$pair_field_def['field']] );
                    }
                    continue;
                }
                $fields[$field] = $this->kernel->db->escape( $data['base_table'][$field] );
            }
            if ( $this->entity_def['status_table'] )
            {
                $fields['domain'] = "'private'";
                $where[] = "domain = 'private'";
                if ( $this->entity_def['status_table'] == 'base_table' )
                {
                    $fields['status'] = $this->kernel->db->escape( $status );
                }
            }

            // Update existing base entity
            if ( $id )
            {
                $sql = "UPDATE $table SET";
                foreach ( $fields as $field => $value )
                {
                    $sql .= " $field = $value,";
                }
                $sql .= ' updated_date = UTC_TIMESTAMP(),';
                $sql .= " updater_id = {$this->user->getId()}";
                $sql .= ' WHERE ' . implode( ' AND ', array_merge($where, array("id = $id")) );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
            }

            // Insert new base entity
            else
            {
                $sql = 'SELECT IFNULL(MAX(id), 0) + 1 AS next_id';
                if ( $this->entity_def['order_by']['field'] == 'order_index' )
                {
                    $sql .=  ', IFNULL(MAX(order_index), 0) + 1 AS next_order_index';
                }
                $sql .= " FROM $table";
                $sql .= ' WHERE ' . implode( ' AND ', $where );
                $statement = $this->conn->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
                $record = $statement->fetch();
                $fields['id'] = $record['next_id'];
                if ( $this->entity_def['order_by']['field'] == 'order_index' )
                {
                    $fields['order_index'] = $record['next_order_index'];
                }
                $fields['created_date'] = 'UTC_TIMESTAMP()';
                $fields['creator_id'] = $this->user->getId();
                while ( !$id )
                {
                    $sql = "INSERT INTO $table(" . implode( ', ', array_keys($fields) ) . ')';
                    $sql .= ' VALUES(' . implode( ', ', $fields ) . ')';
                    $statement = $this->kernel->db->query( $sql );
                    if ( !$statement )
                    {
                        $fields['id']++;
                    }
                    else
                    {
                        $id = $fields['id'];
                    }
                }
            }

            /***********************************************************************
             * Locale table
             **********************************************************************/

            if ( $this->entity_def['status_table'] == 'locale_table' && count($data['locale_table']) > 0 )
            {
                // Delete existing locale entities
                $sql = "DELETE FROM {$this->entity_def['locale_table']['name']}";
                $sql .= " WHERE $entity_key = $id";
                $sql .= ' AND locale IN (' . implode(', ', array_map(
                    array( $this->kernel->db, 'escape' ),
                    array_keys( $this->kernel->dict['SET_accessible_locales'] )
                )) . ')';
                if ( $this->entity_def['status_table'] )
                {
                    $sql .= " AND domain = 'private'";
                }
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }

                // Insert new locale entities
                $fields = $values = array();
                foreach ( $data['locale_table'] as $locale => $locale_data )
                {
                    $fields = array(
                        $entity_key => $id,
                        'locale' => $this->kernel->db->escape( $locale )
                    );
                    foreach ( $this->entity_def['locale_table']['fields'] as $field => $field_def )
                    {
                        $fields[$field] = $this->kernel->db->escape( $locale_data[$field] );
                    }
                    if ( $this->entity_def['status_table'] )
                    {
                        $fields['domain'] = "'private'";
                        $fields['status'] = $this->kernel->db->escape( $status );
                    }
                    $values[] = '(' . implode( ', ', $fields ) . ')';
                }
                $sql = "INSERT INTO {$this->entity_def['locale_table']['name']}(" . implode( ', ', array_keys($fields) ) . ')';
                $sql .= ' VALUES ' . implode( ', ', $values );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
            }

            /***********************************************************************
             * Multivalued tables
             **********************************************************************/

            if ( count(array_ifnull($this->entity_def, 'multivalued_tables', array())) > 0 )
            {
                foreach ( $this->entity_def['multivalued_tables'] as $table => $field_def )
                {
                    // Delete existing multivalued entities
                    if ( $data['base_table']['id'] )
                    {
                        $sql = "DELETE FROM $table WHERE " . implode( ' AND ', array_merge($where, array("$entity_key = $id")) );
                        if ( $table == 'product_entities' )
                        {
                            $sql = 'DELETE FROM product_entities';
                            $sql .= " WHERE domain = 'private'";
                            $sql .= ' AND locale = ' . $this->kernel->db->escape( $data['base_table']['locale'] );
                            $sql .= " AND entity_type = '{$_GET['entity']}'";
                            $sql .= " AND entity_id = $id";
                        }
                        $statement = $this->kernel->db->query( $sql );
                        if ( !$statement )
                        {
                            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                        }
                    }

                    // Insert new multivalued entities
                    $fields = $values = array();
                    foreach ( $data['multivalued_tables'][$table] as $i => $value )
                    {
                        $fields = array( $entity_key => $id );
                        if ( $this->entity_def['status_table'] )
                        {
                            $fields['domain'] = 'private';
                        }

                        // Select and webpage fields
                        if ( in_array($field_def['type'], array('select', 'webpage')) )
                        {
                            $fields[$field_def['field']] = $value;
                            if ( $table == 'product_entities' )
                            {
                                $fields = array(
                                    'domain' => 'private',
                                    'product_id' => $value,
                                    'locale' => $data['base_table']['locale'],
                                    'entity_type' => $_GET['entity'],
                                    'entity_id' => $id
                                );
                            }
                        }

                        // Tabular field
                        else if ( $field_def['type'] == 'tabular' )
                        {
                            $fields = array_merge( $fields, $value );
                            $fields[$field_def['field']] = $i + 1;
                        }

                        $values[] = '(' . implode( ', ', array_map(
                            array( $this->kernel->db, 'escape' ), $fields
                        ) ) . ')';
                    }
                    if ( count($fields) > 0 )
                    {
                        $sql = "INSERT INTO $table(" . implode( ', ', array_keys($fields) ) . ')';
                        $sql .= ' VALUES ' . implode( ', ', $values );
                        $statement = $this->kernel->db->query( $sql );
                        if ( !$statement )
                        {
                            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                        }
                    }
                }
            }
        }

        /***********************************************************************
         * Post-save actions
         **********************************************************************/

        $name = $this->get_name( $data );
        $this->kernel->log( 'message', sprintf(
            "User %s <%s> edited %s %u (%s)",
            $this->user->getId(),
            $this->user->getEmail(),
            str_replace( '_', ' ', $_GET['entity'] ),
            $id,
            $name
        ), __FILE__, __LINE__ );

        // Publicize entity if it is approved
        if ( $status == 'approved' )
        {
            if ( isset($data['multivalued_tables']['product_entities']) )
            {
                $selected_locale = $data['base_table']['locale'];
            }
            $this->publicize( $id, $name, $selected_locale );
        }

        // Send emails
        $method = "send_{$status}_email";
        if ( method_exists($this, $method) )
        {
            $this->$method( $id, $name );
        }

        // Clear cache
        $this->clear_cache();

        return $id;
    }

    /**
     * Publicize an entity based on ID.
     *
     * @since   2015-06-25
     * @param   id      The ID
     * @param   name                The name
     * @param   selected_locale     The selected locale
     */
    function publicize( $id, $name, $selected_locale = '' )
    {
        $method = "publicize_{$_GET['entity']}";

        // Custom entity
        if ( method_exists($this, $method) )
        {
            $this->$method( $id, $name, $selected_locale );
        }

        // Generic entity
        else
        {
            $entity_key = "{$_GET['entity']}_id";

            // Base table
            $data = array(
                ':table' => $this->entity_def['base_table']['name'],
                ':fields' => array( 'id', 'deleted', 'created_date', 'creator_id', 'updated_date', 'updater_id' ),
                ':id' => $id
            );
            foreach ( $this->entity_def['base_table']['fields'] as $field => $field_def )
            {
                if ( $field_def['type'] == 'pair' )
                {
                    foreach ( $field_def['fields'] as $pair_field_def )
                    {
                        $data[':fields'][] = $pair_field_def['field'];
                    }
                }
                else
                {
                    $data[':fields'][] = $field;
                }
            }
            if ( $this->entity_def['status_table'] == 'base_table' )
            {
                $data[':fields'][] = 'status';
            }
            if ( $this->entity_def['order_by']['field'] == 'order_index' )
            {
                $data[':fields'][] = 'order_index';
            }
            $data[':fields'] = implode( ', ', $data[':fields'] );
            $sql = "REPLACE INTO :table(:fields, domain)";
            $sql .= " SELECT :fields, 'public'";
            $sql .= " FROM :table WHERE domain = 'private' AND id = :id";
            $sql = strtr( $sql, $data );
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }

            // Locale table
            if ( $this->entity_def['status_table'] == 'locale_table' )
            {
                $table = $this->entity_def['locale_table']['name'];
                $fields = implode( ', ', array_merge(
                    array_keys( $this->entity_def['locale_table']['fields'] ),
                    array( $entity_key, 'locale', 'status' )
                ) );
                $where = array(
                    "$entity_key = $id",
                    'locale IN (' . implode( ', ', array_map(
                        array( $this->kernel->db, 'escape' ),
                        array_merge( $this->user->getAccessibleLocales(), array('') )
                    ) ) . ')'
                );

                // Delete existing locale entities
                $sql = "DELETE FROM $table WHERE ";
                $sql .= implode( ' AND ', array_merge($where, array("domain = 'public'")) );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }

                // Insert new locale entities
                $sql = "INSERT INTO $table($fields, domain)";
                $sql .= " SELECT $fields, 'public' FROM $table WHERE ";
                $sql .= implode( ' AND ', array_merge($where, array("domain = 'private'")) );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
            }

            // Multivalued tables
            if ( count(array_ifnull($this->entity_def, 'multivalued_tables', array())) > 0 )
            {
                foreach ( $this->entity_def['multivalued_tables'] as $table => $field_def )
                {
                    // Delete existing multivalued entities
                    $sql = "DELETE FROM $table WHERE domain = 'public' AND $entity_key = $id";
                    if ( $table == 'product_entities' )
                    {
                        $sql = 'DELETE FROM product_entities';
                        $sql .= " WHERE domain = 'public'";
                        $sql .= ' AND locale = ' . $this->kernel->db->escape( $selected_locale );
                        $sql .= " AND entity_type = '{$_GET['entity']}'";
                        $sql .= " AND entity_id = $id";
                    }
                    $statement = $this->kernel->db->query( $sql );
                    if ( !$statement )
                    {
                        $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                    }

                    // Insert new multivalued entities
                    $data = array(
                        ':table' => $table,
                        ':fields' => implode( ', ', array($entity_key, $field_def['field']) ),
                        ':id' => $id
                    );
                    if ( $field_def['type'] == 'tabular' )
                    {
                        $data[':fields'] .= ', ' . implode( ', ', array_keys($field_def['fields']) );
                    }
                    $sql = "INSERT INTO :table(:fields, domain)";
                    $sql .= " SELECT :fields, 'public'";
                    $sql .= " FROM :table WHERE domain = 'private' AND $entity_key = :id";
                    $sql = strtr( $sql, $data );
                    if ( $table == 'product_entities' )
                    {
                        $sql = 'INSERT INTO product_entities(domain, product_id, locale, entity_type, entity_id)';
                        $sql .= " SELECT 'public', product_id, locale, entity_type, entity_id";
                        $sql .= ' FROM product_entities';
                        $sql .= " WHERE domain = 'private'";
                        $sql .= ' AND locale = ' . $this->kernel->db->escape( $selected_locale );
                        $sql .= " AND entity_type = '{$_GET['entity']}'";
                        $sql .= " AND entity_id = $id";
                    }
                    $statement = $this->kernel->db->query( $sql );
                    if ( !$statement )
                    {
                        $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                    }
                }
            }
        }

        $this->kernel->log( 'message', sprintf(
            "User %s <%s> published %s %u (%s)",
            $this->user->getId(),
            $this->user->getEmail(),
            str_replace( '_', ' ', $_GET['entity'] ),
            $id,
            $name
        ), __FILE__, __LINE__ );
    }

    /**
     * Send pending email based on ID.
     *
     * @since   2015-06-25
     * @param   id      The ID
     * @param   name    The name
     */
    function send_pending_email( $id, $name )
    {
        $escaped_entity = $this->kernel->db->escape( $_GET['entity'] );
        $locale_ids = implode( ',', $this->user->getAccessibleLanguages() );

        // Get the requested recipients
        $sql = 'SELECT DISTINCT u.* FROM users AS u';
        $sql .= ' JOIN roles AS r ON (u.role_id = r.id AND r.enabled = 1)';
        $sql .= " JOIN role_rights AS rr ON (r.id = rr.role_id AND rr.entity = :module AND rr.`right` = :right)";
        $sql .= ' JOIN (SELECT user_id, GROUP_CONCAT(locale_id) AS locale_ids FROM user_locale_rights GROUP BY user_id) AS ul';
        $sql .= ' ON (u.id = ul.user_id AND ul.locale_ids = ' . $this->kernel->db->escape( $locale_ids ) . ')';
        $sql .= ' LEFT OUTER JOIN approval_requests AS ar ON (u.id = ar.target_user AND ar.type = :entity_type AND ar.target_id = :entity_id AND ar.requested_by = :requester_id)';
        $sql .= ' WHERE u.enabled = 1 AND ar.target_user IS NULL';
        $sql = strtr( $sql, array(
            ':module' => $this->kernel->db->escape( $this->kernel->response['module'] ),
            ':right' => Right::APPROVE,
            ':entity_type' => $escaped_entity,
            ':entity_id' => $id,
            ':requester_id' => $this->user->getId()
        ) );
        $statement = $this->kernel->db->query( $sql );
        if ( !$statement )
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
        $recipients = $statement->fetchAll();

        // Send email to recipients
        if ( count($recipients) > 0 )
        {
            // Compose email
            $values = array();
            $data = compact( 'id', 'name' );
            $this->kernel->smarty->assignByRef( 'data', $data );
            $lines = explode( "\n", $this->kernel->smarty->fetch("module/entity_admin/locale/{$this->kernel->request['locale']}_pending_email.html") );
            $this->kernel->mailer->isHTML( TRUE );
            $this->kernel->mailer->ContentType = 'text/html';
            $this->kernel->mailer->Subject = trim( array_shift($lines) );
            $this->kernel->mailer->Body = implode( "\n", $lines );
            foreach ( $recipients as $recipient )
            {
                $this->kernel->mailer->addAddress( $recipient['email'], $recipient['first_name'] );
                $values[] = sprintf(
                    '(%s, %d, %d, %d, UTC_TIMESTAMP())',
                    $escaped_entity,
                    $id,
                    $this->user->getId(),
                    $recipient['id']
                );
            }

            // Send email
            try
            {
                $this->kernel->mailer->send();
                $sql = 'INSERT INTO approval_requests(type, target_id, requested_by, target_user, requested_time) VALUES ' . implode( ', ', $values );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
            }
            catch( Exception $e )
            {
                $this->kernel->log( 'message', sprintf(
                    "User %d experienced failure in sending mail: %s\n",
                    $this->user->getId(),
                    $e->getTraceAsString()
                ), __FILE__, __LINE__ );
            }
            $this->kernel->mailer->ClearAllRecipients();
        }
    }

    /**
     * Send approved email based on ID.
     *
     * @since   2015-06-25
     * @param   id      The ID
     * @param   name    The name
     */
    function send_approved_email( $id, $name )
    {
        $params = array(
            ':entity_type' => $this->kernel->db->escape( $_GET['entity'] ),
            ':entity_id' => $id
        );

        // Get the requested recipients
        $sql = 'SELECT DISTINCT u.* FROM approval_requests AS ar';
        $sql .= ' JOIN users AS u ON (ar.requested_by = u.id)';
        $sql .= ' WHERE ar.type = :entity_type AND ar.target_id = :entity_id AND u.enabled = 1';
        $sql = strtr( $sql, $params );
        $statement = $this->kernel->db->query( $sql );
        if ( !$statement )
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
        $recipients = $statement->fetchAll();

        // Send email to recipients
        if ( count($recipients) > 0 )
        {
            // Compose email
            $data = compact( 'id', 'name' );
            $this->kernel->smarty->assignByRef( 'data', $data );
            $lines = explode( "\n", $this->kernel->smarty->fetch("module/entity_admin/locale/{$this->kernel->request['locale']}_approved_email.html") );
            $this->kernel->mailer->isHTML( TRUE );
            $this->kernel->mailer->ContentType = 'text/html';
            $this->kernel->mailer->Subject = trim( array_shift($lines) );
            $this->kernel->mailer->Body = implode( "\n", $lines );
            foreach ( $recipients as $recipient )
            {
                $this->kernel->mailer->addAddress( $recipient['email'], $recipient['first_name'] );
            }

            // Send email
            try
            {
                $this->kernel->mailer->send();
                $sql = 'DELETE FROM approval_requests WHERE type = :entity_type AND target_id = :entity_id';
                $sql = strtr( $sql, $params );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
            }
            catch( Exception $e )
            {
                $this->kernel->log( 'message', sprintf(
                    "User %d experienced failure in sending mail: %s\n",
                    $this->user->getId(),
                    $e->getTraceAsString()
                ), __FILE__, __LINE__ );
            }
            $this->kernel->mailer->ClearAllRecipients();
        }
    }

    /**
     * Delete an entity based on ID.
     *
     * @since   2015-12-07
     */
    function prune()
    {
        // Get the requested entity
        $data = $this->get();

        try
        {
            $id = $data['base_table']['id'];
            $name = $this->get_name( $data );

            // Check authentication
            $this->rights_required[] = Right::EDIT;
            $this->rights_required[] = Right::APPROVE;
            $this->user->checkRights( $this->kernel->response['module'], $this->rights_required );
            if ( !$id || !($this->entity_def['status_table'] == 'base_table' || $this->user->isGlobalUser()) )
            {
                throw new privilegeException( 'insufficient_rights' );
            }

            // Update existing base entity
            $sql = "UPDATE {$this->entity_def['base_table']['name']} SET";
            if ( $this->entity_def['status_table'] == 'base_table' )
            {
                $sql .= " status = 'approved',";
            }
            $sql .= ' deleted = 1,';
            $sql .= ' updated_date = UTC_TIMESTAMP(),';
            $sql .= " updater_id = {$this->user->getId()}";
            $sql .= " WHERE id = $id";
            if ( $this->entity_def['status_table'] )
            {
                $sql .= " AND domain = 'private'";
            }
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            $this->kernel->log( 'message', sprintf(
                "User %s <%s> deleted %s %u (%s)",
                $this->user->getId(),
                $this->user->getEmail(),
                str_replace( '_', ' ', $_GET['entity'] ),
                $id,
                $name
            ), __FILE__, __LINE__ );

            // Update existing locale entities
            if ( $this->entity_def['status_table'] == 'locale_table' )
            {
                $sql = "UPDATE {$this->entity_def['locale_table']['name']} SET";
                $sql .= " status = 'approved'";
                $sql .= " WHERE {$_GET['entity']}_id = $id";
                $sql .= " AND domain = 'private'";
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
            }

            // Publicize entity
            if ( $this->entity_def['status_table'] )
            {
                $this->publicize( $id, $name );
            }

            // Redirect to next page
            $this->kernel->redirect( array_ifnull($_GET, 'referer_url', "?entity={$_GET['entity']}") . '&message=deleted' );
        }
        catch ( Exception $e )
        {
            $this->processException( $e );
        }
    }

    /**
     * Generate the preview token of an entity based on ID.
     *
     * @since   2017-03-21
     */
    function generate_token()
    {
        // Get the requested entity
        $data = $this->get();

        try
        {
            $id = $data['base_table']['id'];
            $name = $this->get_name( $data );

            // Check authentication
            if ( !$id )
            {
                throw new privilegeException( 'insufficient_rights' );
            }

            $token = $this->createPvToken( $id, $_GET['entity'] );

            // Delete existing preview token
            $sql = 'DELETE FROM webpage_preview_tokens WHERE type = ' . $this->kernel->db->escape( $_GET['entity'] ) . " AND initial_id = $id";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }

            // Insert new preview token
            $sql = 'INSERT INTO webpage_preview_tokens(token, type, initial_id, created_date, creator_id, grant_role_id, expire_time)';
            $sql .= ' VALUES(%s, %s, %d, UTC_TIMESTAMP(), %d, %d, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY))';
            $sql = sprintf(
                $sql,
                $this->kernel->db->escape( $token['token'] ),
                $this->kernel->db->escape( $_GET['entity'] ),
                $id,
                $this->user->getId(),
                $this->user->getRole()->getId()
            );
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            $this->kernel->log( 'message', sprintf(
                "User %s <%s> generated an anonymous preview token for %s %u (%s)",
                $this->user->getId(),
                $this->user->getEmail(),
                str_replace( '_', ' ', $_GET['entity'] ),
                $id,
                $name
            ), __FILE__, __LINE__ );

            // Redirect to next page
            $this->kernel->redirect( array_ifnull($_GET, 'referer_url', "?entity={$_GET['entity']}") . '&message=preview_link_generated' );
        }
        catch ( Exception $e )
        {
            $this->processException( $e );
        }
    }

    /**
     * Remove the preview token of an entity based on ID.
     *
     * @since   2017-03-21
     */
    function remove_token()
    {
        // Get the requested entity
        $data = $this->get();

        try
        {
            $id = $data['base_table']['id'];
            $name = $this->get_name( $data );

            // Check authentication
            if ( !$id )
            {
                throw new privilegeException( 'insufficient_rights' );
            }

            $token = $this->createPvToken( $id, $_GET['entity'] );

            // Delete existing preview token
            $sql = 'DELETE FROM webpage_preview_tokens WHERE type = ' . $this->kernel->db->escape( $_GET['entity'] ) . " AND initial_id = $id";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            $this->kernel->log( 'message', sprintf(
                "User %s <%s> removed an anonymous preview token for %s %u (%s)",
                $this->user->getId(),
                $this->user->getEmail(),
                str_replace( '_', ' ', $_GET['entity'] ),
                $id,
                $name
            ), __FILE__, __LINE__ );

            // Redirect to next page
            $this->kernel->redirect( array_ifnull($_GET, 'referer_url', "?entity={$_GET['entity']}") . '&message=preview_link_removed' );
        }
        catch ( Exception $e )
        {
            $this->processException( $e );
        }
    }
}
