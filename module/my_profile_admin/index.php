<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The my profile admin module.
 *
 * This module allows user edit his/her own profile.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2009-01-05
 */
class my_profile_admin_module extends admin_module
{
    public $module = 'my_profile_admin';

    /**
     * Constructor.
     *
     * @since   2009-01-05
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );
    }

    /**
     * Process the request.
     *
     * @since   2009-01-05
     * @return  Processed or not
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
     * Edit my profile.
     *
     * @since   2009-01-05
     */
    function index()
    {
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);

        try {
            if(count($_POST) > 0) {
                $errors = array();

                $data = array();
                $data['salutation'] = trim( array_ifnull($_POST, 'salutation', '') );
                $data['first_name'] = trim( array_ifnull($_POST, 'first_name', '') );
                $data['last_name'] = trim( array_ifnull($_POST, 'last_name', '') );
                $data['username'] = trim( array_ifnull($_POST, 'username', '') );
                $data['email'] = trim( array_ifnull($_POST, 'email', '') );
                $data['password'] = trim( array_ifnull($_POST, 'password', '') );
                $password_confirm = trim( array_ifnull($_POST, 'password_confirm', '') );

                if ( !array_key_exists($data['salutation'], $this->kernel->dict['SET_salutations']) )
                {
                    $data['salutation'] = NULL;
                }

                // error checking
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
                } else {
                    $query = 'SELECT COUNT(*) AS user_exists FROM users';
                    $query .= ' WHERE username = ' . $this->conn->escape( $data['username'] );
                    $query .= " AND id <> {$this->user->getId()}";
                    $statement = $this->conn->query( $query );
                    extract( $statement->fetch() );
                    if ( $user_exists )
                    {
                        $errors['username'][] = 'username_used';
                    }
                }
                if ( $data['email'] === '' )
                {
                    $errors['email'][] = 'email_blank';
                } else if ( !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ) {
                    $errors['email'][] = 'email_invalid';
                } else {
                    $query = 'SELECT COUNT(*) AS user_exists FROM users';
                    $query .= ' WHERE email = ' . $this->conn->escape( $data['email'] );
                    $query .= " AND id <> {$this->user->getId()}";
                    $statement = $this->conn->query( $query );
                    extract( $statement->fetch() );
                    if ( $user_exists )
                    {
                        $errors['email'][] = 'email_used';
                    }
                }

                if ( $data['password'] !== $password_confirm )
                {
                    $errors['password'][] = 'password_unmatch';
                }

                // continue to process (successfully)
                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                } else {
                    $this->conn->beginTransaction();

                    // Update existing user
                    $sql = 'UPDATE users SET';
                    $sql .= ' salutation = ' . $this->conn->escape($data['salutation']) . ',';
                    $sql .= ' first_name = ' . $this->conn->escape($data['first_name']) . ',';
                    $sql .= ' last_name = ' . $this->conn->escape($data['last_name']) . ',';
                    $sql .= ' username = ' . $this->conn->escape($data['username']) . ',';
                    $sql .= ' email = ' . $this->conn->escape($data['email']) . ',';
                    if ( $data['password'] !== '' )
                    {
                        $sql .= ' password = ' . $this->conn->escape(password_hash($data['password'], PASSWORD_DEFAULT)) . ',';
                    }
                    $sql .= ' updated_date = UTC_TIMESTAMP(),';
                    $sql .= " updater_id = {$this->user->getId()}";
                    $sql .= " WHERE id = {$this->user->getId()}";
                    if ( $this->conn->exec($sql) > 0 )
                    {
                        $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> updated his/her profile.", __FILE__, __LINE__ );
                    }

                    $this->conn->commit();
                }

                $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                    http_build_query(array(
                                          'op' => 'dialog',
                                          'type' => 'message',
                                          'code' => 'DESCRIPTION_saved',
                                          'redirect_url' => "{$this->kernel->sets['paths']['mod_from_doc']}/"
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
            $this->kernel->dict['SET_operations']['edit'] = sprintf(
                $this->kernel->dict['SET_operations']['edit'],
                $this->kernel->dict['SET_modules']['my_profile_admin']
            );

            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/my_profile_admin/index.html' );
        }
    }

    /**
     * Save the edit of my profile.
     *
     * @since   2009-01-05
     */
    function save()
    {
        $redirect_url = "{$this->kernel->sets['paths']['mod_from_doc']}/";

        // Data container
        $data = array();

        // Get data from form post
        $_test = (bool) array_ifnull( $_POST, '_test', FALSE );
        $data['user_name'] = trim( array_ifnull($_POST, 'user_name', '') );
        $data['email'] = trim( array_ifnull($_POST, 'email', '') );
        $data['password'] = trim( array_ifnull($_POST, 'password', '') );
        $password_confirm = trim( array_ifnull($_POST, 'password_confirm', '') );
        $data['timezone'] = trim( array_ifnull($_POST, 'timezone', '') );

        // Cleanup data
        if ( !isset($this->kernel->sets['timezones'][$data['timezone']]) )
        {
            $data['timezone'] = '';
        }
        else
        {
            $data['timezone'] = floatval( $data['timezone'] );
        }

        // Validate data
        $error = array(
            'error_code' => '',
            'error_text' => '',
            'error_field' => ''
        );
        if ( $data['user_name'] === '' )
        {
            $error['error_code'] = 'ERROR_user_name_blank';
            $error['error_field'] = 'user_name';
        }
        else if ( $data['email'] === '' )
        {
            $error['error_code'] = 'ERROR_email_blank';
            $error['error_field'] = 'email';
        }
        else if ( !filter_var($data['email'], FILTER_VALIDATE_EMAIL) )
        {
            $error['error_code'] = 'ERROR_email_invalid';
            $error['error_field'] = 'email';
        }
        else
        {
            $query = 'SELECT COUNT(*) AS user_exists FROM users';
            $query .= ' WHERE email = ' . $this->kernel->db->escape( $data['email'] );
            $query .= " AND id <> {$this->user->getId()}";
            $statement = $this->kernel->db->query( $query );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $query, __FILE__, __LINE__ );
            }
            extract( $statement->fetch() );
            if ( $user_exists )
            {
                $error['error_code'] = 'ERROR_email_used';
                $error['error_field'] = 'email';
            }
        }

        if ( $error['error_code'] === '' )
        {
            if ( $data['password'] !== $password_confirm )
            {
                $error['error_code'] = 'ERROR_password_unmatch';
                $error['error_field'] = 'password';
            }
            else if ( $data['timezone'] === '' )
            {
                $error['error_code'] = 'ERROR_timezone_blank';
                $error['error_field'] = 'timezone';
            }
        }

        // Stop if there is error
        if ( $error['error_code'] !== '' )
        {
            $error['error_text'] = $this->kernel->dict[$error['error_code']];
            if ( $_test )
            {
                $this->apply_template = FALSE;
                $this->kernel->response['mimetype'] = 'application/json';
                $this->kernel->response['content'] = json_encode( $error );
            }
            else
            {
                $this->kernel->redirect( '?' . http_build_query(array(
                    'op' => 'dialog',
                    'type' => 'error',
                    'code' => $error['error_code'],
                    'text' => $error['error_text'],
                    'redirect_url' => $redirect_url
                )) );
            }
        }

        // No error
        else
        {
            if ( $_test )
            {
                $this->apply_template = FALSE;
                $this->kernel->response['mimetype'] = 'application/json';
                $this->kernel->response['content'] = '{}';
            }
            else
            {
                // Update existing user
                $sql = 'UPDATE users SET';
                $sql .= ' user_name = ' . $this->kernel->db->escape($data['user_name']) . ',';
                $sql .= ' email = ' . $this->kernel->db->escape($data['email']) . ',';
                if ( $data['password'] !== '' )
                {
                    $sql .= ' password = ' . $this->kernel->db->escape(password_hash($data['password'], PASSWORD_DEFAULT)) . ',';
                }
                $sql .= " timezone = {$data['timezone']},";
                $sql .= ' updated_date = UTC_TIMESTAMP(),';
                $sql .= " updater_id = {$this->user->getId()}";
                $sql .= " WHERE id = {$this->user->getId()}";
                if ( $this->kernel->db->exec($sql) > 0 )
                {
                    $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> updated his/her profile.", __FILE__, __LINE__ );
                }

                // Redirect to next page
                $redirect_get = array(
                    'op' => 'dialog',
                    'type' => 'message',
                    'code' => 'DESCRIPTION_saved',
                    'redirect_url' => $redirect_url
                );
                $this->kernel->redirect( '?' . http_build_query($redirect_get) );
            }
        }
    }
}
