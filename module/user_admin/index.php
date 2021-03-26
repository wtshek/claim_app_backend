<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The user admin module.
 *
 * This module allows user to administrate users.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2008-11-06
 */
class user_admin_module extends admin_module
{
    public $module = 'user_admin';
    private $type = 'admin';

    /**
     * Constructor.
     *
     * @since   2008-11-06
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        $this->type = trim(array_ifnull($_REQUEST, 't', 'admin'));

        if($this->type != 'public') {
            $this->type = 'admin';
        }

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );
    }

    /**
     * Process the request.
     *
     * @since   2008-11-06
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
                case "edit":
                    $this->rights_required[] = array_ifnull( $_REQUEST, 'id', 0 ) ? Right::EDIT : Right::CREATE;
                    $this->method = "edit";
                    break;
                case "export":
                    $this->rights_required[] = Right::EXPORT;
                    $this->method = "export";
                    break;
                default:
                    return parent::process();
            }

            // process right checking and throw error and stop further process if any
            $this->user->checkRights($this->type . '_' . $this->module, array_unique($this->rights_required));

            if($this->method) {
                call_user_func_array(array($this, $this->method), $this->params);
                $this->module_title = $this->type == "public" ? $this->kernel->dict['LABEL_public_users'] : $this->kernel->dict['LABEL_admin_users'];
            }

            return TRUE;
        } catch(Exception $e) {
            $this->processException($e);
        }

        return FALSE;
    }

    /**
     * Get FROM and WHERE values in SQL statement for users.
     *
     * @since   2008-11-06
     * @param   find    An array of query variables
     * @return  The FROM and WHERE values in SQL statement
     */
    function get_query_values( $find )
    {
        $unwanted = $this->kernel->conf['unwanted_matching_characters'];    // To shorten the name

        // Default FROM and WHERE values
        $from = 'users JOIN roles r ON(users.role_id = r.id)';
        $from .= 'LEFT JOIN user_locale_rights ulr ON (ulr.user_id=users.id)';
        $from .= 'LEFT JOIN locales l ON (l.alias=ulr.locale)';
        
        $where = array( '1 = 1' );

        // Keyword
        if ( $find['keyword'] !== '' )
        {
            $keyword_where = array();
            $fields = array(
                'users.first_name',
                'users.last_name',
                'users.username',
                'users.email',
                'l.name',
                'l.alias'
                
            );
            $value = $this->kernel->db->escape( '%' . $this->kernel->db->cleanupStringForMatching($find['keyword'], $unwanted) . '%' );
            foreach ( $fields as $field )
            {
                $keyword_where[] = $this->kernel->db->cleanupFieldForMatching( $field, $unwanted ) . " LIKE $value";
            }
            $where[] = '(' . implode(' OR ', $keyword_where) . ')';
        }

        // Start created date
        if ( !is_null($find['start_created_date']) )
        {
            $where[] = 'users.created_date >= CONVERT_TZ('
                . $this->kernel->db->escape( $find['start_created_date'] . ' 00:00:00' ) . ','
                . $this->kernel->conf['escaped_timezone'] . ", '+00:00')";
        }

        // End created date
        if ( !is_null($find['end_created_date']) )
        {
            $where[] = 'users.created_date <= CONVERT_TZ('
                . $this->kernel->db->escape( $find['end_created_date'] . ' 23:59:59' ) . ','
                . $this->kernel->conf['escaped_timezone'] . ", '+00:00')";
        }

        // Start updated date
        if ( !is_null($find['start_updated_date']) )
        {
            $where[] = 'users.updated_date >= CONVERT_TZ('
                . $this->kernel->db->escape( $find['start_updated_date'] . ' 00:00:00' ) . ','
                . $this->kernel->conf['escaped_timezone'] . ", '+00:00')";
        }

        // End updated date
        if ( !is_null($find['end_updated_date']) )
        {
            $where[] = 'users.updated_date <= CONVERT_TZ('
                . $this->kernel->db->escape( $find['end_updated_date'] . ' 23:59:59' ) . ','
                . $this->kernel->conf['escaped_timezone'] . ", '+00:00')";
        }

        // Enabled
        if ( $find['enabled'] !== '' )
        {
            $where[] = 'users.enabled = ' . ($find['enabled'] == 'true' ? 1 : 0);
        }
        
        // User Accessible Languages
        if( count($find['user_languages'])>0)
        {
            $where[] = 'ulr.locale IN ('.implode(',', array_map(array($this->kernel->db, 'escape'), $find['user_languages'])).')';
        }

        return array(
            'from' => $from,
            'where' => $where
        );
    }

    /**
     * List users.
     *
     * @since   2008-11-06
     */
    function index()
    {
        $list_id = $this->type . '_user_list';
        $enabled_sets = array(
            "" => $this->kernel->dict['LABEL_any'],
            'true' => $this->kernel->dict['SET_bool'][1],
            'false' => $this->kernel->dict['SET_bool'][0]
        );
        $this->kernel->smarty->assign('enabled_sets', $enabled_sets);

        // Query condition
        $_GET['keyword'] = trim( array_ifnull($_GET, 'keyword', '') );
        $_GET['enabled'] = trim( array_ifnull($_GET, 'enabled', 'true') );
        $_GET['start_created_date'] = string_to_date( array_ifnull($_GET, 'start_created_date', ''), FALSE );
        $_GET['end_created_date'] = string_to_date( array_ifnull($_GET, 'end_created_date', ''), FALSE );
        $_GET['start_updated_date'] = string_to_date( array_ifnull($_GET, 'start_updated_date', ''), FALSE );
        $_GET['end_updated_date'] = string_to_date( array_ifnull($_GET, 'end_updated_date', ''), FALSE );
        $_GET['user_languages'] = array_map('intval', array_ifnull($_GET, 'user_language', array()));

        // Query condition
        extract( $this->get_query_values($_GET) );
        $where[] = 'r.`type` = ' . $this->conn->escape($this->type);

        // Actions
        $referer_url = '?' . http_build_query( $_GET );
        $record_actions = $list_actions = array();
        if ( $this->user->hasRights($this->type . '_' . $this->module, array(Right::EDIT)) )
        {
            $record_actions['?' . http_build_query(array(
                'op' => 'edit',
                't' => $this->type,
                'referer_url' => $referer_url,
                'id' => ''
            ))] = $this->kernel->dict['ACTION_edit'];
            
            if ( $this->type == 'admin' )
            {
                $list_actions['?' . http_build_query(array(
                    'op' => 'edit',
                    't' => $this->type,
                    'referer_url' => $referer_url
                ))] = $this->kernel->dict['ACTION_new'];
            }
        }
        if ( $this->user->hasRights($this->type . '_' . $this->module, array(Right::EXPORT)) )
        {
            $list_actions['?' . http_build_query(array(
                'op' => 'export',
                't' => $this->type,
                'referer_url' => $referer_url
            ))] = $this->kernel->dict['ACTION_export'];
        }

        $roleTree = $this->type == "public" ? $this->getRoleTree(true, $this->type) : $this->roleTree;

        // Get the requested users
        $select = array(
            'users.id', 'r.name AS role', 'users.first_name', 'users.last_name',
            'users.username', 'users.email', 'IF(users.enabled = 1, '
            . $this->kernel->db->escape($this->kernel->dict['LABEL_yes']) . ', '
            . $this->kernel->db->escape($this->kernel->dict['LABEL_no']) . ' ) AS enabled'
        );

		$list = $this->kernel->get_smarty_list_from_db(
            $list_id,
            'id',
            array(
                'select' => implode( ',', $select ),
                'from' => $from,
                'where' => implode(' AND ', $where),
                'group_by' => 'users.id',
                'having' => '',
                'default_order_by' => 'email',
                'default_order_dir' => 'ASC'
            ),
            array(),
            $record_actions,
            $list_actions
        );
        $this->kernel->smarty->assignByRef( 'list', $list);

        // Get the requested users (by user type)
        $type_lists = array();
        $roles = $roleTree->getChildren();
        if($this->type == "public") {
            unset($roles[0]);
        }

        array_splice( $select, 1, 1 );
        foreach ( $roles as $role )
        {
            $role_id = $role->getItem()->getId();
            $type_where = array_merge( $where, array('users.role_id = ' . $this->kernel->db->escape($role_id)) );
            $type_list = $this->kernel->get_smarty_list_from_db(
                "{$list_id}_{$role_id}",
                'id',
                array(
                    'select' => implode( ',', $select ),
                    'from' => $from,
                    'where' => implode(' AND ', $type_where),
                    'group_by' => 'users.id',
                    'having' => '',
                    'default_order_by' => 'email',
                    'default_order_dir' => 'ASC'
                ),
                array(),
                $record_actions,
                $list_actions
            );
            $type_list['name'] = $role->getItem()->getName();
            $type_lists[$role_id] = $type_list;
        }
        $this->kernel->smarty->assignByRef( 'type_lists', $type_lists );

        $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/user_admin/index.html' );
    }


    /**
     * Export users.
     *
     * @since   2008-11-06
     */
    function export()
    {
        // Query condition
        $role_id = array_ifnull( $_GET, 'role_id', '' );
        $_GET['keyword'] = trim( array_ifnull($_GET, 'keyword', '') );
        $_GET['enabled'] = trim( array_ifnull($_GET, 'enabled', '') );
        $_GET['start_created_date'] = string_to_date( array_ifnull($_GET, 'start_created_date', ''), FALSE );
        $_GET['end_created_date'] = string_to_date( array_ifnull($_GET, 'end_created_date', ''), FALSE );
        $_GET['start_updated_date'] = string_to_date( array_ifnull($_GET, 'start_updated_date', ''), FALSE );
        $_GET['end_updated_date'] = string_to_date( array_ifnull($_GET, 'end_updated_date', ''), FALSE );
		$_GET['user_languages'] = array_map('intval', array_ifnull($_GET, 'user_language', array()));
        extract( $this->get_query_values($_GET) );
        if ( $role_id !== '' )
        {
            $where[] = 'r.id = ' . intval( $role_id );
        }

        // Get the list
        $sql = 'SELECT users.id, r.name AS role,';
        $sql .= $this->conn->translateField('users.salutation', $this->kernel->dict['SET_salutations'], 'salutation') . ',';
        $sql .= ' users.first_name, users.last_name, users.username, users.email,';
        $sql .= ' IF(users.enabled = 1, ';
        $sql .= $this->kernel->db->escape($this->kernel->dict['LABEL_yes']) . ', ';
        $sql .= $this->kernel->db->escape($this->kernel->dict['LABEL_no']) . ' ) AS enabled,';
        $sql .= " CONVERT_TZ(users.created_date, '+00:00',";
        $sql .= " {$this->kernel->conf['escaped_timezone']}) AS created_date,";
        $sql .= ' IFNULL(creators.email, users.email) AS creator,';
        $sql .= " CONVERT_TZ(users.updated_date, '+00:00',";
        $sql .= " {$this->kernel->conf['escaped_timezone']}) AS updated_date,";
        $sql .= ' updaters.email AS updater';
        $sql .= " FROM $from";
        $sql .= ' LEFT OUTER JOIN users AS creators ON (users.creator_id = creators.id)';
        $sql .= ' LEFT OUTER JOIN users AS updaters ON (users.updater_id = updaters.id)';
        $sql .= ' WHERE ' . implode(' AND ', $where);
		$sql .= ' GROUP BY users.id';
        $sql .= ' ORDER BY users.email ASC';

        // Set outputs
        $list = $this->kernel->get_spreadsheet_list_from_db( $sql );
        $this->apply_template = FALSE;
        $this->kernel->response['charset'] = '';
        $this->kernel->response['filename'] = 'users.xls';
        $this->kernel->response['disposition'] = 'attachment';
        $this->kernel->response['mimetype'] = 'application/vnd.ms-excel';
        $this->kernel->response['content'] = $list['content'];
        $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> exported {$list['count']} users.", __FILE__, __LINE__ );
    }

    /**
     * Edit a user based on user ID.
     *
     * @since   2008-11-06
     */
    function edit()
    {
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);

        // Data container
        $data = array();
        $roleTree = $this->type == "public" ? $this->getRoleTree(true, $this->type) : $this->roleTree;

        // Get data from query string
        $id = intval( array_ifnull($_REQUEST, 'id', 0) );

        try {
            if(count($_POST) > 0) {
                $errors = array();

                $data['role_id'] = intval(array_ifnull($_POST, 'role_id', 0));
                $data['property_id'] = intval(array_ifnull($_POST, 'property_id', 0));
                $data['salutation'] = trim( array_ifnull($_POST, 'salutation', '') );
                $data['first_name'] = trim( array_ifnull($_POST, 'first_name', '') );
                $data['last_name'] = trim( array_ifnull($_POST, 'last_name', '') );
                $data['username'] = trim( array_ifnull($_POST, 'username', '') );
                $data['email'] = trim( array_ifnull($_POST, 'email', '') );
                $data['password'] = trim( array_ifnull($_POST, 'password', '') );
                $password_confirm = trim( array_ifnull($_POST, 'password_confirm', '') );
                $data['enabled'] = intval( array_ifnull($_POST, 'enabled', '') ) ? 1 : 0;
                $data['user_locales'] = array_map('trim', array_ifnull($_POST, 'user_locales', array()));

                if ( !array_key_exists($data['salutation'], $this->kernel->dict['SET_salutations']) )
                {
                    $data['salutation'] = NULL;
                }

                // change role type to the role user belongs to (user has one role at this stage)
                // it is not supposed to changed user from admin to public role and vise vesa
                if($id) {
                    $sql = sprintf('SELECT u.*, r.type AS role_type FROM users u'
                                        . ' JOIN roles r ON(u.role_id = r.id) WHERE u.id = %d '
                                        , $id);
                    $statement = $this->conn->query($sql);
                    if($record = $statement->fetch()) {
                        $this->type = $record['role_type'];
                    }
                }

                // error checking
                $root = $roleTree->getChildren(0);
                if ( !$roleTree->findById($data['role_id'])
                    || ($this->type == 'public' && $root[0]->getItem()->getId() == $data['role_id']) )
                {
                    $errors['role_id'][] = 'role_blank';
                }
                if ( $this->type == 'admin' && is_null($data['property_id']) )
                {
                    $errors['property_id'][] = 'property_blank';
                }
                if ( $data['first_name'] === '' )
                {
                    $errors['first_name'][] = 'first_name_blank';
                }
                if ( $data['last_name'] === '' )
                {
                    $errors['last_name'][] = 'last_name_blank';
                }
                if ( $data['username'] === '' )
                {
                    $errors['username'][] = 'username_blank';
                }
                else
                {
                    $sql = 'SELECT COUNT(*) AS user_exists FROM users';
                    $sql .= ' WHERE username = ' . $this->kernel->db->escape( $data['username'] );
                    $sql .= " AND id <> $id";
                    $statement = $this->conn->query( $sql );
                    extract( $statement->fetch() );
                    if ( $user_exists )
                    {
                        $errors['username'][] = 'username_used';
                    }
                }
                if ( $data['email'] === '' )
                {
                    $errors['email'][] = 'email_blank';
                }
                else if ( !filter_var($data['email'], FILTER_VALIDATE_EMAIL) )
                {
                    $errors['email'][] = 'email_invalid';
                }
                else
                {
                    $sql = 'SELECT COUNT(*) AS user_exists FROM users';
                    $sql .= ' WHERE email = ' . $this->kernel->db->escape( $data['email'] );
                    $sql .= " AND id <> $id";
                    $statement = $this->conn->query( $sql );
                    extract( $statement->fetch() );
                    if ( $user_exists )
                    {
                        $errors['email'][] = 'email_used';
                    }
                }

                if ( $id == 0 && $data['password'] === '' )
                {
                    $errors['password'][] = 'password_blank';
                }
                else if ( $data['password'] !== $password_confirm )
                {
                    $errors['password'][] = 'password_unmatch';
                }
                
                if(count($data['user_locales']) == 0)
                {
                    $errors['locale_check_all_0'][] = 'language_access_blank';
                }

                // continue to process (successfully)
                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                } else {
                    $this->conn->beginTransaction();

                    // Update existing user
                    if ( $id )
                    {
                        $sql = 'UPDATE users SET';
                        $sql .= ' salutation = ' . $this->kernel->db->escape($data['salutation']) . ',';
                        $sql .= ' first_name = ' . $this->kernel->db->escape($data['first_name']) . ',';
                        $sql .= ' last_name = ' . $this->kernel->db->escape($data['last_name']) . ',';
                        $sql .= ' username = ' . $this->kernel->db->escape($data['username']) . ',';
                        $sql .= ' email = ' . $this->kernel->db->escape($data['email']) . ',';
                        if ( $data['password'] !== '' )
                        {
                            $sql .= ' password = ' . $this->kernel->db->escape(password_hash($data['password'], PASSWORD_DEFAULT)) . ',';
                        }
                        $sql .= ' role_id = ' . $this->kernel->db->escape($data['role_id']) . ',';
                        $sql .= ' property_id = NULL,';
                        $sql .= " enabled = {$data['enabled']},";
                        $sql .= ' updated_date = UTC_TIMESTAMP(),';
                        $sql .= " updater_id = {$this->user->getId()}";
                        $sql .= " WHERE id = $id";
                        if ( $this->conn->exec($sql) > 0 )
                        {
                            $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> updated user $id ({$data['email']})", __FILE__, __LINE__ );
                        }
                    }

                    // Insert new user
                    else
                    {
                        $sql = 'INSERT INTO users(salutation, first_name, last_name, username, email, password,';
                        $sql .= ' role_id, property_id, enabled,';
                        $sql .= ' created_date, creator_id) VALUES(';
                        $sql .= $this->kernel->db->escape($data['salutation']) . ',';
                        $sql .= $this->kernel->db->escape($data['first_name']) . ',';
                        $sql .= $this->kernel->db->escape($data['last_name']) . ',';
                        $sql .= $this->kernel->db->escape($data['username']) . ',';
                        $sql .= $this->kernel->db->escape($data['email']) . ',';
                        $sql .= $this->kernel->db->escape(password_hash($data['password'], PASSWORD_DEFAULT)) . ',';
                        $sql .= $this->kernel->db->escape($data['role_id']) . ',';
                        $sql .= 'NULL,';
                        $sql .= "{$data['enabled']},";
                        $sql .= 'UTC_TIMESTAMP(),';
                        $sql .= "{$this->user->getId()})";
                        $this->conn->exec( $sql );
                        $id = $this->conn->lastInsertId();
                        $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> created user $id ({$data['email']})", __FILE__, __LINE__ );
                    }

                    // Update user_languages
                    $sql = 'DELETE FROM user_locale_rights WHERE user_id='.$id;
                    $this->conn->exec( $sql );
                    foreach($data['user_locales'] as $locale)
                    {
                        $sql = 'INSERT INTO user_locale_rights (user_id, locale) VALUES ('.$id.','.$this->kernel->db->escape($locale).')';
                        $this->conn->exec( $sql );
                    }

                    $this->conn->commit();
                }

                $redirect = $this->kernel->sets['paths']['mod_from_doc'] . '?' .
                    http_build_query(array(
                        'op' => 'dialog',
                        'type' => 'message',
                        'code' => 'DESCRIPTION_saved',
                        'redirect_url' => $this->kernel->sets['paths']['server_url']
                            . $this->kernel->sets['paths']['mod_from_doc']
                            . '?' . http_build_query( array(
                                'op' => 'edit',
                                'id' => $id,
                                't' => $this->type,
                                'referer_url' => array_ifnull( $_GET, 'referer_url', '' )
                            ) )
                    ));
                if($ajax) {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( array(
                                                                           'result' => 'success',
                                                                           'redirect' => $redirect
                                                                      ));
                } else {
                    $this->kernel->redirect($redirect);
                }
                return TRUE;
            }

        } catch(Exception $e) {
            $this->processException($e);
        }

        // continue to process if not ajax
        if(!$ajax) {

            // Get the requested user
            $sql = "SELECT users.*, r.type AS role_type, CONVERT_TZ(users.created_date, '+00:00', ";
            $sql .= " {$this->kernel->conf['escaped_timezone']}) AS created_date,";
            $sql .= " CONVERT_TZ(users.updated_date, '+00:00',";
            $sql .= " {$this->kernel->conf['escaped_timezone']}) AS updated_date,";
            $sql .= ' IFNULL(creators.first_name, users.first_name) AS creator_user_name,';
            $sql .= ' IFNULL(creators.email, users.email) AS creator_email,';
            $sql .= ' updaters.first_name AS updater_user_name,';
            $sql .= ' updaters.email AS updater_email';
            $sql .= ' FROM users';
            $sql .= ' LEFT OUTER JOIN users AS creators ON (users.creator_id = creators.id)';
            $sql .= ' LEFT OUTER JOIN users AS updaters ON (users.updater_id = updaters.id)';
            $sql .= ' JOIN roles r ON(r.id = users.role_id)';
            $sql .= " WHERE users.id = $id";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            $data = ($record = $statement->fetch()) ? array_merge($record, $data) : array();
            
            if(count($data)>0)
            {
                // get accessible locales
                $sql = 'SELECT locale FROM user_locale_rights WHERE user_id='.$id;
                $data['user_locales'] = $this->kernel->get_set_from_db($sql);
            }
            
            

            // Set default values for new user
            if ( count($data) == 0 )
            {
                $data['id'] = $id = 0;
                $data['role_id'] = array_ifnull( $_GET, 'role_id', '' );
                $data['enabled'] = 1;
            }

            // Get additional data for existing user
            else
            {
                $this->type = $data['role_type'];
            }

            // BreadCrumb
            $this->_breadcrumb->push(new breadcrumbNode($this->kernel->dict[$id == 0 ? 'ACTION_new' : 'ACTION_edit']
                    , $this->kernel->sets['paths']['mod_from_doc'] . '?op=edit' . ($id == 0 ? "" : ("&id=" . $id)))
            );

            // Get the tree for user webpages
            $sitemap = $this->get_sitemap( 'edit' );

            // Assign data to view
            $this->kernel->smarty->assignByRef( 'data', $data );

            // Set page title
            if ( $id > 0 )
            {
                $this->kernel->dict['SET_operations']['edit'] = sprintf(
                    $this->kernel->dict['SET_operations']['edit'],
                    $data['email']
                );
                $this->kernel->response['titles'][] = $this->kernel->dict['SET_operations']['edit'];
            }
            else
            {
                $this->kernel->dict['SET_operations']['new'] = sprintf(
                    $this->kernel->dict['SET_operations']['new'],
                    $this->kernel->dict['LABEL_new_user']
                );
                $this->kernel->response['titles'][] = $this->kernel->dict['SET_operations']['new'];
            }

            $role_options = $roleTree->generateOptions(false);
            $role_keys = array_keys($role_options);
            $role_keys = array_map('substr', array_keys($role_options), array_fill(0,count($role_options),1));

            $role_options = array_combine($role_keys, array_values($role_options));

            $role_options2 = array('' => '');

            foreach($role_options as $k => $option) {
                $role_options2[$k] = $option;
            }

            $this->kernel->smarty->assign('role_options', $role_options2);

            if($id > 0) {
                $info = array(
                    'created_date_message' => sprintf($this->kernel->dict['INFO_created_date'], '<b>' . $data['created_date'] . '</b>', '<b>' . $data['creator_user_name'] . '</b>', '<b>' . $data['creator_email'] . '</b>')
                );

                if($data['updated_date']) {
                    $info['last_update_message'] = sprintf($this->kernel->dict['INFO_last_update'], '<b>' . $data['updated_date'] . '</b>', '<b>' . $data['updater_user_name'] . '</b>', '<b>' . $data['updater_email'] . '</b>');
                }

                $this->kernel->smarty->assign('info', $info);
            }

            $disabled_roles = array();
            if($this->type == "public") {
                $roots = $roleTree->getChildren(0);
                // let anonymous become disabled
                $disabled_roles[] = $roots[0]->getItem()->getId();
            }

            $this->kernel->smarty->assign('type', $this->type);
            $this->kernel->smarty->assign('disabled_roles', $disabled_roles);

            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/user_admin/edit.html' );
        }
    }
}

?>