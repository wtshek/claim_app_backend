<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/base/index.php' );
require( dirname(__FILE__) . '/lib/smarty/functions.php' );

/**
 * The admin module.
 *
 * This module allows user to log in and log out the administration section.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2008-11-04
 */
class admin_module extends base_module
{
    public $module = "admin";

    protected $roleTree;
    /** @var adminUser admin_user */
    public $user;
    /** @var array $rights_required */
    protected $rights_required;
    protected $method = "";
    protected $params = array();
    protected $module_title = "";
    private $login_salt = "8L>+q{Q)Y7v_?LvK";
    private static $pv_salt = "jVBd~nb<M49AH3d/nA%7"; // for anonymous preview page

    /**
     * Constructor.
     *
     * @since   2008-11-04
     * @param   kernel      The kernel
     */
    function __construct( kernel &$kernel )
    {
        parent::__construct( $kernel );

        $this->rights_required = array();
        $this->module_title = $this->kernel->dict['SET_modules'][$this->kernel->response['module']];
        $this->kernel->mailer->IsHTML( FALSE );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );
        $this->kernel->dict['APP_admin_copyright'] = strtr( $this->kernel->dict['APP_admin_copyright'], array(
            ':year' => intval( convert_tz('now', 'UTC', $this->kernel->conf['timezone']) )
        ) );

        // Add entity admin items
        foreach ( $this->kernel->entity_admin_def as $entity_admin => $entity_admin_def )
        {
            $this->kernel->dict['SET_side_menu_groups'][$entity_admin] = $entity_admin_def['name'];
        }

        $this->_breadcrumb->push(new breadcrumbNode($this->kernel->dict['LABEL_home'], $this->kernel->sets['paths']['app_from_doc'] . '/admin/' . $this->kernel->request['locale'] . '/'));
        if(isset($this->module))
            $this->_breadcrumb->push(new breadcrumbNode($this->kernel->dict['SET_modules'][$this->module], $this->kernel->sets['paths']['mod_from_doc'] . '/'));

        // Set session
        if ( !isset($_SESSION['admin']) )
        {
            $_SESSION['admin'] = array();
        }
        $this->session =& $_SESSION['admin'];
        $this->roleTree = $this->getRoleTree(true);

        // Set user
        $this->user = new adminUser();

        if ( array_ifnull($this->session, 'user', 0) > 0 )
        {
            $query = 'SELECT u.* FROM users u JOIN roles r ON(r.id = u.role_id)';
            $query .= ' WHERE r.type = "admin" AND u.enabled = 1';
            $query .= ' AND u.id = ' . intval( $this->session['user'] );
            $statement = $this->kernel->db->query( $query );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $query, __FILE__, __LINE__ );
            }
            if ( $record = $statement->fetch() )
            {
                $this->user->setData( $record );
                $this->kernel->dict['DESCRIPTION_welcome_user'] = sprintf(
                    $this->kernel->dict['DESCRIPTION_welcome_user'],
                    $this->user->getFirstName(),
                    $this->user->getLastName()
                );

                $_GET['op'] = trim(array_ifnull($_GET, 'op', 'index'));

                // Multiple login || user expired user session || public user
                if ( $_GET['op'] != 'login'
                        && ( /*$this->user->getToken() != md5(session_id()) ||*/ (isset($this->session['USER_PREV_ACTIVE_TIME']) &&
                        (time() - $this->session['USER_PREV_ACTIVE_TIME']) > intval($this->kernel->conf['user_session_timer'])) )
                )
                {
                    $_GET['op'] = 'logout';
                    if($this->user->getToken() == md5(session_id()))
                        $_GET['t'] = 'session_timeout_by_system';
                }

                // Single login
                else
                {
                    // update session timer
                    $this->session['USER_PREV_ACTIVE_TIME'] = time();
                }

                /** @var roleNode $role */
                $role = $this->roleTree->findById($record['role_id']);
                if($role) {
                    $this->user->setRole($role->getItem());
                    $this->user->getRole()->getRights();
                }

                $this->kernel->dict['SET_accessible_locales'] = $this->session['user_accessible_locales'] = array();
                $locales = $this->user->getAccessibleLocales();
                if ( count($locales) > 0 )
                {
                    $sql = "SELECT alias, name FROM locales WHERE site = 'public_site'";
                    $sql .= ' AND alias IN (' . implode( ', ', array_map(array($this->conn, 'escape'), $locales) ) . ')';
                    $sql .= ' ORDER BY `default` DESC, order_index ASC';
                    $statement = $this->conn->query( $sql );
                    while ( $row = $statement->fetch() )
                    {
                        $this->kernel->dict['SET_accessible_locales'][$row['alias']] = $row['name'];
                        $this->session['user_accessible_locales'][] = $row['alias'];
                    }
                    if ( !array_key_exists($this->user->getPreferredLocale(), $this->kernel->dict['SET_accessible_locales']) )
                    {
                        $this->user->setPreferredLocale( current(array_keys($this->kernel->dict['SET_accessible_locales'])) );
                    }
                }

                // admin menu
                $current_cls = preg_replace( '#_module$#', '', get_class($this) );
                $side_menu = array_merge(
                    // Content
                    array(
                        'content' => array(
                            'children' => array(
                                'preview' => array(
                                    'entity' => 'webpage_admin',
                                    'title' => $this->kernel->dict['SET_modules']['preview'],
                                    'icon' => 'icon-globe'
                                ),
                                'webpage_admin' => array(
                                    'entity' => 'webpage_admin',
                                    'title' => $this->kernel->dict['SET_modules']['webpage_admin'],
                                    'icon' => 'icon-sitemap'
                                ),
                                'offer_admin' => array(
                                    'entity' => 'offer_admin',
                                    'title' => $this->kernel->dict['SET_modules']['offer_admin'],
                                    'icon' => 'icon-gift'
                                ),
    							'snippet_generator_admin' => array(
    								'entity' => 'snippet_generator_admin',
    								'title' => $this->kernel->dict['SET_modules']['snippet_generator_admin'],
    								'icon' => 'icon-tasks'
    							),
                                'media_admin' => array(
                                    'entity' => 'share_file_admin',
                                    'title' => $this->kernel->dict['SET_modules']['share_file_admin'],
                                    'icon' => 'icon-picture'
                                ),
                                'vanity_url_admin' => array(
                                    'entity' => 'vanity_url_admin',
                                    'title' => $this->kernel->dict['SET_modules']['vanity_url_admin'],
                                    'icon' => 'glyphicon-new_window'
                                ),
    							'vanity_url_report_admin' => array(
    								'entity' => 'vanity_url_report_admin',
    								'title' => $this->kernel->dict['SET_modules']['vanity_url_report_admin'],
    								'icon' => 'icon-time'
    							),
                                'template_admin' => array(
                                    'entity' => 'template_admin',
                                    'title' => $this->kernel->dict['SET_modules']['template_admin'],
                                    'icon' => 'icon-file-alt'
                                )
                            )
                        )
                    ),

                    // Entity
                    $this->kernel->entity_admin_def,

                    // User
                    array(
                        'user' => array(
                            'children' => array(
                                'role_admin' => array(
                                    'entity' => 'role_admin',
                                    'title' => $this->kernel->dict['SET_modules']['role_admin'],
                                    'icon' => 'icon-group'
                                ),
                                'user_admin' => array(
                                    'entity' => 'user_admin',
                                    'title' => $this->kernel->dict['SET_modules']['user_admin'],
                                    'icon' => 'icon-user',
                                    'children' => array(
                                        'admin' => array()/*,
                                        'public' => array()*/
                                    )
                                )
                            )
                        )
                    )
                );
                foreach ( $side_menu as $group => $items )
                {
                    foreach ( $items['children'] as $child_type => $child )
                    {
                        // Set entity of entity admin child
                        if ( array_key_exists($group, $this->kernel->entity_admin_def) )
                        {
                            $child['entity'] = $group;
                        }

                        // Check access right
                        $has_rights = $this->user->hasRights( $child['entity'], Right::ACCESS );
                        if ( $child_type == 'user_admin' )
                        {
                            foreach ( $child['children'] as $grandchild_type => $grandchild )
                            {
                                if ( $this->user->hasRights($grandchild_type . '_' . $child['entity'], Right::ACCESS) )
                                {
                                    $has_rights = TRUE;
                                }
                            }
                        }

                        // Has access right
                        if ( $has_rights )
                        {
                            $child['active'] = $current_cls == $child_type;
                            $child['target'] = '_top';

                            if ( $child_type == 'preview' )
                            {
                                $child['url'] = $this->kernel->sets['paths']['server_url']
                                    . $this->kernel->sets['paths']['app_from_doc']
                                    . '/' . $this->user->getPreferredLocale() . '/preview/';
                                $child['target'] = '_blank';
                            }

                            else if ( $child_type == 'user_admin' )
                            {
                                $t = trim( array_ifnull($_GET, 'type', 'admin') );
                                $child['url'] = $this->kernel->sets['paths']['app_from_doc']
                                    . '/admin/' . $this->kernel->request['locale']
                                    . '/' . preg_replace('#_admin$#', '', $child_type) . '/';

                                foreach ( $child['children'] as $grandchild_type => $grandchild )
                                {
                                    if ( $this->user->hasRights($grandchild_type . '_' . $child['entity'], Right::ACCESS) )
                                    {
                                        $grandchild['title'] = $this->kernel->dict["LABEL_{$grandchild_type}_users"];
                                        $grandchild['icon'] = 'icon-user';
                                        $grandchild['url'] = $child['url'] . '?t=' . urlencode( $grandchild_type );
                                        $grandchild['target'] = $child['target'];
                                        $grandchild['active'] = $child['active'] && $t == $grandchild_type;
                                        $child['children'][$grandchild_type] = $grandchild;
                                    }
                                }
                            }

                            else if ( array_key_exists($group, $this->kernel->entity_admin_def) )
                            {
                                $entity = trim( array_ifnull($_GET, 'entity', '') );

                                $child['title'] = $this->kernel->entity_admin_def[$group]['children'][$child_type]['name'];
                                $child['url'] = $this->kernel->sets['paths']['app_from_doc']
                                    . '/admin/' . $this->kernel->request['locale']
                                    . '/' . preg_replace('#_admin$#', '', $child['entity']) . '/';
                                if ( array_key_exists('children', $child) )
                                {
                                    foreach ( $child['children'] as $grandchild_type => $grandchild )
                                    {
                                        $grandchild['title'] = $this->kernel->entity_admin_def[$group]['children'][$child_type]['children'][$grandchild_type]['name'];
                                        $grandchild['url'] = $child['url'] . '?entity=' . urlencode( $grandchild_type );
                                        $grandchild['target'] = $child['target'];
                                        $grandchild['active'] = $child['active'] && $entity == $grandchild_type;
                                        $child['children'][$grandchild_type] = $grandchild;
                                    }
                                    $child['active'] = array_key_exists( $entity, $child['children'] );
                                }
                                else
                                {
                                    $child['url'] .= '?entity=' . urlencode( $child_type );
                                    $child['active'] = $entity == $child_type;
                                }
                            }

                            else
                            {
                                $child['url'] = $this->kernel->sets['paths']['app_from_doc']
                                    . '/admin/' . $this->kernel->request['locale']
                                    . '/' . preg_replace('#_admin$#', '', $child_type) . '/';
                            }

                            $side_menu[$group]['children'][$child_type] = $child;
                            if ( $child['active'] )
                            {
                                $side_menu[$group]['active'] = TRUE;
                            }
                        }

                        // Has no access right
                        else
                        {
                            unset( $side_menu[$group]['children'][$child_type] );
                        }
                    }

                    // Non empty group
                    if ( count($side_menu[$group]['children']) > 0 )
                    {
                        if ( count($side_menu[$group]['children']) == 1 || count($side_menu) == 1 )
                        {
                            $side_menu[$group]['active'] = 1;
                        }
                    }

                    // Empty group
                    else
                    {
                        unset( $side_menu[$group] );
                    }
                }

                if(!$this->user->isGlobalUser())
                {
                    unset( $side_menu['product']['children']['move_category'] );
                }

                $this->kernel->smarty->assign( 'side_menu', $side_menu );

                // server timestamp
                $this->kernel->smarty->assign( 'sts', strtotime(convert_tz('now', 'UTC', $this->kernel->conf['timezone'])) * 1000 );

                $this->session['user'] = $this->user->getId();
                $this->session['user_rights'] = $this->user->getRole()->getRights()->getAllRights();
                $this->session['global_user'] = $this->user->isGlobalUser();

                $escaped_locales = array_map( array($this->kernel->db, 'escape'), array(
                    ':locale' => $this->user->getPreferredLocale(),
                    ':default_locale' => $this->kernel->default_public_locale
                ) );

                // Get dining and room sets
                $structured_sets = array( 'dinings' => 2, 'rooms' => 3 );
                foreach ( $structured_sets as $set => $template )
                {
                    $sql = "SELECT id, SUBSTRING_INDEX(GROUP_CONCAT(name ORDER BY locale <> :locale, locale <> :default_locale SEPARATOR '\r\n'), '\r\n', 1) AS name";
                    $sql .= " FROM (SELECT w.id, wl.locale, SUBSTRING_INDEX(GROUP_CONCAT(wl.webpage_title ORDER BY w.major_version DESC SEPARATOR '\r\n'), '\r\n', 1) AS name,";
                    $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(w.deleted ORDER BY w.major_version DESC SEPARATOR '\r\n'), '\r\n', 1) AS deleted";
                    $sql .= ' FROM webpages AS w';
                    $sql .= ' JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id)';
                    $sql .= " WHERE w.domain = 'private' AND w.structured_page_template = $template";
                    $sql .= ' GROUP BY w.id, wl.locale) AS t';
                    $sql .= ' WHERE deleted = 0';
                    $sql .= ' GROUP BY id';
                    $sql = strtr( $sql, $escaped_locales );
                    $this->kernel->dict["SET_$set"] = $this->kernel->get_set_from_db( $sql );
                }
            }
        }

        // Apply master template or not
        $this->apply_template = TRUE;

        // Self define smarty actions
        $this->kernel->smarty->registerPlugin("function", "field_text", "smarty_block_field_text");
        $this->kernel->smarty->registerPlugin("function", "field_select", "smarty_block_field_select");
        $this->kernel->smarty->registerPlugin("function", "field_radio", "smarty_block_field_radio");
        $this->kernel->smarty->registerPlugin("function", "field_checkbox", "smarty_block_field_checkbox");
        $this->kernel->smarty->registerPlugin("function", "field_calendar", "smarty_block_field_calendar");
        $this->kernel->smarty->registerPlugin("function", "field_calendar_old_lib", "smarty_block_field_calendar_old_lib");
        $this->kernel->smarty->registerPlugin("function", "field_textarea", "smarty_block_field_textarea");

        // Assign members to Smarty Template Engine
        $this->kernel->smarty->assignByRef( 'user', $this->user );
        $this->kernel->smarty->assignByRef( 'm', $this );
        $this->kernel->smarty->assignByRef( 'breadcrumb', $this->_breadcrumb );
        $this->kernel->smarty->assignByRef( 'module_title', $this->module_title );
    }

    /**
     * Process the request.
     *
     * @since   2008-11-05
     * @return  Processed or not
     */
     function process()
    {
        try {
            // Choose operation, if not yet processed
            if ( !parent::process() )
            {
                $op = array_ifnull( $_GET, 'op', 'index' );
                switch ( $op )
                {
                    case 'ping':
                        $this->ping();
                        return TRUE;

                    case 'dialog':
                        $this->dialog();
                        return TRUE;

                    case 'forget_password':
                        $this->forget_password();
                        return TRUE;

                    case 'login':
                        $this->login();
                        return TRUE;

                    case 'logout':
                        $this->logout();
                        return TRUE;

                    case 'index':
                        $this->index();
                        return TRUE;

                    case 'check_user_session_timeout':
                        $this->check_user_session_timeout();
                        return TRUE;

                    case 'set_preferred_locale':
                        $this->set_preferred_locale();
                        return TRUE;

                    default:
                        return FALSE;
                }
            }
            return TRUE;
        } catch(Exception $e) {
            $this->processException($e);
            return FALSE;
        }
    }

    /**
     * Output the response.
     *
     * @since   2008-11-04
     */
    function output()
    {
        $this->kernel->response['title'] = implode(
            $this->kernel->dict['VALUE_title_separator'],
            array_reverse($this->kernel->response['titles'])
        );

        // Apply master template, if needed
        if ( $this->apply_template )
        {
            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/admin/template.html' );
        }
    }

    /**
     * A dummy page for pinging.
     *
     * @since   2009-06-25
     */
    function ping()
    {
        $this->apply_template = FALSE;
        $this->kernel->response['mimetype'] = 'text/plain';
        $this->kernel->response['content'] = '{}';
    }

    /**
     * Show a dialog page.
     *
     * @since   2008-11-05
     */
    function dialog()
    {
        // Restrict redirect URL to be server URL only
        $redirect_url = array_ifnull($_GET, 'redirect_url', "{$this->kernel->sets['paths']['mod_from_doc']}/");
        $redirect_components = parse_url( $redirect_url );
        if ( strpos($redirect_url, $this->kernel->sets['paths']['server_url']) === FALSE
            && (array_key_exists('scheme', $redirect_components)
            || array_key_exists('host', $redirect_components)
            || array_key_exists('port', $redirect_components)
            || array_key_exists('user', $redirect_components)
            || array_key_exists('pass', $redirect_components)) )
        {
            $redirect_url = '';
        }

        if ( count($_POST) == 0 )
        {
            $_GET['type'] = trim( array_ifnull($_GET, 'type', 'message') );
            $_GET['type'] = array_key_exists( $_GET['type'], $this->kernel->dict['SET_dialog_types'] )
                ? $_GET['type'] : 'message';

            switch($_GET['type']) {
                case "error":
                    $icon = "icon-exclamation-sign";
                    break;
                case "warning":
                    $icon = "icon-warning-sign";
                    break;
                case "notice":
                    $icon = "icon-bullhorn";
                    break;
                case "message":
                default:
                    $icon = "icon-quote-left";
                    break;
            }

            $this->kernel->smarty->assign("icon", $icon);

            $actions = array();

            if($redirect_url) {
                $actions[] = array(
                    'icon' => 'icon-reply',
                    'title' => $this->kernel->dict['ACTION_back'],
                    'target' => '_self',
                    'href' => $redirect_url
                );
            }

            $_GET['actions'] = array_ifnull($_GET, 'actions', array());
            $_GET['actions'] = array_filter(is_array($_GET['actions'])
                                ? array_map('trim', array_ifnull($_GET, 'actions', array()))
                                : array(trim(array_ifnull($_GET, 'actions', ''))), 'strlen');

            foreach($_GET['actions'] as $action) {
                $tmp = base64_decode($action);
                $vars = explode('|', $tmp);
                if(count($vars) > 1) {
                    $action = array(
                        'href' => $vars[0],
                        'title' => $vars[1]
                    );

                    if(isset($vars[2])) {
                        $action['target'] = $vars[2];
                    }

                    if(isset($vars[3])) {
                        $action['icon'] = $vars[3];
                    }

                    $actions[] = $action;
                }
            }

            $this->kernel->smarty->assign('actions', $actions);

            $this->kernel->response['title'][] = $this->kernel->dict['SET_dialog_types'][$_GET['type']];
            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/admin/dialog.html' );
        }
        else
        {
            $this->kernel->redirect( $redirect_url );
        }
    }

    /**
     * Index page.
     *
     * @since   2008-11-05
     */
    function index()
    {
        $list_content = array();

        if(!$this->user->getId()) {
            $this->kernel->response['bodyCls'][] = 'login';
        } else {
            require_once(dirname(__FILE__) . '/panel_blocks.php');

            $blockStacks = array(
                'p12' => array(),
                'p6' => array()
            );

            // including panel block
            $pBlocks = array(
                'anonymousLinksBlock' => array(
                    'webpage_admin' => array(Right::VIEW)
                ),
                'aboutExpireWebpageBlock' => array(
                    'webpage_admin' => array(Right::VIEW)
                ),
                'aboutLiveWebpageBlock' => array(
                    'webpage_admin' => array(Right::VIEW)
                )
            );

            $i = 0;

            $color_themes = array('lightgrey', 'blue', 'lime', 'orange');
            foreach($pBlocks as $bk => $rs) {
                $hasRight = false;
                foreach($rs as $n => $rights) {
                    if($this->user->hasRights($n, $rights)) {
                        $hasRight = true;
                        break;
                    }
                }

                if($hasRight) {
                    $b = new $bk($this);
                    $b->prepare();
                    $b->setColorTheme($color_themes[$i % count($color_themes)]);

                    if($b->hasItem()) {
                        $blockStacks['p' . $b->getSize()][] = $b;
                    }
                    $i++;
                }
            }

            $this->kernel->smarty->assign('pb_list', $blockStacks);
        }

        $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/admin/index.html' );
    }

    /**
     * Forget password.
     *
     * @since   2008-01-05
     */
    function forget_password()
    {
        if ( count($_POST) > 0 )
        {
            $redirect_url = array_ifnull( $_GET, 'redirect_url', "{$this->kernel->sets['paths']['mod_from_doc']}/" );

            // Get data from query string and form post
            $_test = (bool) array_ifnull( $_POST, '_test', FALSE );
            $email = trim( array_ifnull($_POST, 'email', '') );

            // Data validation
            $error = array(
                'error_code' => '',
                'error_text' => '',
                'error_field' => ''
            );
            if ( $email === '' )
            {
                $error['error_code'] = 'ERROR_email_blank';
                $error['error_field'] = 'email';
            }
            else if($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $error['error_code'] = 'ERROR_email_invalid';
                $error['error_field'] = 'email';
            }
            else if ( !$_test )
            {
                // Get user
                $query = 'SELECT * FROM users WHERE enabled = 1';
                $query .= ' AND email=' . $this->kernel->db->escape($email);
                $statement = $this->kernel->db->query( $query );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $query, __FILE__, __LINE__ );
                }

                // Found
                if ( $data = $statement->fetch() )
                {
                    $data['password'] = generate_password();

                    // Try to send email
                    $this->kernel->smarty->assignByRef( 'data', $data );
                    $content = explode( "\n", $this->kernel->smarty->fetch( "module/admin/locale/{$this->kernel->request['locale']}_forget_password.txt") );
                    $this->kernel->mailer->Subject    = array_shift( $content );
                    $this->kernel->mailer->Body       = implode( "\n", $content );
                    $this->kernel->mailer->From       = $this->kernel->conf['mailer_email'];
                    $this->kernel->mailer->FromName   = $this->kernel->conf['mailer_name'];
                    $this->kernel->mailer->AddAddress( $data['email'], $data['username'] );
                    if ( $this->kernel->mailer->Send() )
                    {
                        // Update existing user
                        $sql = 'UPDATE users SET';
                        $sql .= ' password = ' . $this->kernel->db->escape(password_hash($data['password'], PASSWORD_DEFAULT)) . ',';
                        $sql .= ' updated_date = UTC_TIMESTAMP(),';
                        $sql .= " updater_id = {$data['id']}";
                        $sql .= " WHERE id = {$data['id']}";
                        $statement = $this->kernel->db->query( $sql );
                        if ( !$statement )
                        {
                            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                        }
                        $this->kernel->log( 'message', "Password reset for user {$data['id']}", __FILE__, __LINE__ );

                        $redirect_url = '?op=dialog&type=message&code=DESCRIPTION_sent'
                            . '&redirect_url=' . urlencode( $redirect_url );
                    }
                    else
                    {
                        $error['error_text'] = $this->kernel->mailer->ErrorInfo;
                    }
                }

                // Not found / Multiple users found (shouldn't happen)
                else
                {
                    $redirect_url = '?op=dialog&type=message&code=DESCRIPTION_sent'
                        . '&redirect_url=' . urlencode( $redirect_url );
                }
            }
            if ( $error['error_code'] != '' || $error['error_text'] != '' )
            {
                if ( $error['error_text'] === '' )
                {
                    $error['error_text'] = $this->kernel->dict[$error['error_code']];
                }
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
                    $this->kernel->redirect( $redirect_url );
                }
            }
        }
        else
        {
            $this->kernel->response['bodyCls'][] = 'login';
            $this->kernel->response['bodyCls'][] = 'forget-pwd';
            $this->kernel->response['titles'][] = $this->kernel->dict['SET_operations']['forget_password'];
            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/admin/forget_password.html' );
        }
    }

    /**
     * Login.
     *
     * @since   2008-11-05
     */
    function login()
    {
        // salt
        $max_login_attempts = 5; // maximum number of login attempt
        $login_attempt_interval = 300; // in seconds
        $redirect_url = array_ifnull( $_GET, 'redirect_url', "{$this->kernel->sets['paths']['mod_from_doc']}/" );

        $attempts = 0;
        $last_attempt = 0;
        $acc_attempts = 0;

        if(isset($this->session['_tries_attempted'])) {
            $tmp = $this->xor_decode($this->session['_tries_attempted'], $this->login_salt);
            try {
                $ary = explode('|', $tmp);
                if(count($ary) == 2) {
                    $attempts = $ary[1];
                    $last_attempt = $ary[0];

                    if(($last_attempt + $login_attempt_interval) < time()) {
                        $attempts = 0; // reset
                    }
                } else {
                    $attempts = $max_login_attempts;
                }
            } catch(Excpetion $e) {
                $attempts = $max_login_attempts;
            }
        }

        try {
            // Get data from query string and form post
            $_test = (bool) array_ifnull( $_POST, 'ajax', FALSE );
            $username = trim( array_ifnull($_POST, 'username', '') );
            $password = trim( array_ifnull($_POST, 'password', '') );
            $sid = trim( array_ifnull($_POST, 'sid', '') );

            $errors = array();

            // Validate data
            if ( $username === '' )
            {
                $errors['username'][] = 'username_blank';
            }

            if ( $password === '' )
            {
                $errors['password'][] = 'password_blank';
            }

            if( $attempts > 2) {

                $errors['errorsStack'][] = 'login_max_attempts_reach';
            }

            if(count($errors) == 0) {
				$query = 'SELECT u.* FROM users u';
                $query .= ' JOIN roles r ON(r.id = u.role_id)';
                $query .= ' WHERE u.enabled = 1 AND r.type = "admin"';
                $query .= ' AND ' . $this->kernel->db->escape($username) . ' IN (username, email)';
                $statement = $this->kernel->db->query( $query );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $query, __FILE__, __LINE__ );
                }

                if($record = $statement->fetch()) {
                    $tmp = $this->xor_decode($record['last_login_attempt'], $this->login_salt);
                    $ary = explode('|', $tmp);
                    if(count($ary) == 2) {
                        $acc_attempts = $ary[1];
                        $last_attempt = $ary[0];

                        if(($last_attempt + $login_attempt_interval) < time()) {
                            $acc_attempts = 0; // reset
                        }
                    }
                }

                if($acc_attempts > ($max_login_attempts - 1)) {
                    $errors['errorsStack'][] = 'account_max_attempts_reach';

                    $this->kernel->log('message', sprintf('An anonymous attempted to login to the admin panel with the username <%s>.'
                        . 'However, the account has been locked for 5 mins because of suspicious activity was detected.', $username));

                } else {
                    // Get user
                    $query = 'SELECT u.* FROM users u';
                    $query .= ' JOIN roles r ON(r.id = u.role_id)';
                    $query .= ' WHERE u.enabled = 1 AND r.type = "admin"';
                    $query .= ' AND ' . $this->kernel->db->escape($username) . ' IN (u.username, u.email)';
                    $statement = $this->kernel->db->query( $query );
                    if ( !$statement )
                    {
                        $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $query, __FILE__, __LINE__ );
                    }

                    // Found
                    if ( ($record = $statement->fetch()) && password_verify($password, $record['password']) && password_verify(session_id(), $sid) )
                    {
                        if(!count($errors)) {
                            // Update existing user
                            $sql = 'UPDATE users SET';
                            $sql .= ' last_login_attempt = NULL';
                            $sql .= ', token = ' . $this->kernel->db->escape( md5(session_id()) );
                            $sql .= " WHERE id = {$record['id']}";
                            $this->kernel->db->exec( $sql );
                            $this->user->setData( $record );

                            $attempts = 0;

                            $this->kernel->log( 'message', "User {$record['id']} <{$record['email']}> logged in", __FILE__, __LINE__ );
                        }
                    }

                    // Not found / Multiple users found (shouldn't happen)
                    else
                    {
                        $attempts++;
						$errors['errorsStack'][] = $attempts > ($max_login_attempts - 1) ? 'login_max_attempts_reach' : 'login_invalid';
                    }
                }
            }

            if(count($errors) > 0) {
                throw new fieldsException($errors);
            } else {
                // is lightbox popup?
                /*
                $lb = array_ifnull($_REQUEST, 'lbpopup', 0);
                if($lb) {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode(array('result' => 'success'));
                }
                else*/ if ( $_test )
                {
					$this->apply_template = FALSE;
					$this->kernel->response['mimetype'] = 'application/json';
					$this->kernel->response['content'] = json_encode( array(
																		   'result' => 'success',
																		   'redirect' => $redirect_url
																	  ));
                }
                else
                {
					$this->kernel->redirect( $redirect_url );
                }
            }

        } catch(Exception $e) {
            $this->processException($e);
        }

        $this->session['_tries_attempted'] = $this->xor_encode(time() . '|' . $attempts, $this->login_salt);

        if($attempts > ($max_login_attempts - 1) && $acc_attempts < ($max_login_attempts - 1)) {
            $this->kernel->log('message', sprintf('An anonymous attempted to login to the admin panel with the username <%s>.'
                . 'However, s/he have a unmatch username and password for at least %d times and rejected by the system.', $username, $max_login_attempts));

            if($username) {
                $sql = sprintf('UPDATE users u JOIN roles r ON(r.id = u.role_id) '
                    . ' SET u.last_login_attempt = %s '
                    . ' WHERE u.enabled = 1 AND r.type = "admin"'
                    . ' AND %s IN (u.username, u.email)'
                    , $this->conn->escape($this->xor_encode(time() . '|' . $attempts, $this->login_salt))
                    , $this->conn->escape($username));
                $this->conn->exec($sql);
            }
        }

        // Save user into session
        $this->session['user'] = $this->user->getId();

        if(count($errors) > 0 && !$_test) {
            //$this->index();
            admin_module::index();
        }
    }

    /**
     * Logout.
     *
     * @since   2008-11-05
     */
    function logout()
    {
        try {
            $_GET['t'] = trim(array_ifnull($_GET, 't', ''));

            // Clear user data if needed
            if ( $this->user->getId() )
            {
                // Delete the private webpage locks
                $sql = 'DELETE FROM webpage_locks';
                $sql .= " WHERE locker_id = {$this->user->getId()}";
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }

                // Update existing user
                $sql = 'UPDATE users SET';
                $sql .= ' token = NULL';
                $sql .= " WHERE id = {$this->user->getId()}";
                $sql .= ' AND token = ' . $this->kernel->db->escape( md5(session_id()) );
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }

                // Clear cookie
                foreach ( $_COOKIE as $key => $value )
                {
                    if ( preg_match('/^admin_/', $key) > 0 )
                    {
                        $this->kernel->set_cookie( $key, '', time()-42000 );
                    }
                }

                // Clear temp user directory
                /*
                if(!$dh = @opendir("{$this->kernel->sets['paths']['app_root']}/file/media/page/temp/"));
                else
                {
                    while (false !== ($obj = readdir($dh))) {
                        if($obj=='.' || $obj=='..') continue;

                        if(preg_match('/^'.$this->user->getId().'_\d+/', $obj ) && is_dir("{$this->kernel->sets['paths']['app_root']}/file/media/page/temp/" . $obj))
                            $this->empty_folder("{$this->kernel->sets['paths']['app_root']}/file/media/page/temp/" . $obj);
                    }
                }
                */

                // Clear session
                $this->session = array();
                /*
                session_destroy();   // destroy session data in storage
                session_unset();     // unset $_SESSION variable for the runtime
                */

                $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> logged out" . ($_GET['t'] == 'session_timeout' || $_GET['t'] == 'session_timeout_by_system' ? ' due to user session timeout' : ''), __FILE__, __LINE__ );
                $this->user->setId( NULL );
            }

            if(isset($_REQUEST['ajax']) && $_REQUEST['ajax']) {
                throw new loginException($this->kernel->dict['MESSAGE_login_to_continue'], null, "{$this->kernel->sets['paths']['app_from_doc']}/admin/{$this->kernel->request['locale']}/"
                    . '?redirect_url=' . urlencode(array_ifnull($_SERVER, 'REQUEST_URI', "{$this->kernel->sets['paths']['mod_from_doc']}/")));

            } else {
                if($_GET['t'] == 'session_timeout')
                {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'text/plain';
                    $this->kernel->response['content'] = '{}';
                }
                else
                    $this->kernel->redirect( array_ifnull($_GET, 'redirect_url', "{$this->kernel->sets['paths']['mod_from_doc']}/") );
            }
        } catch(Excpetion $e) {
            $this->processException($e);
        }
    }


	/*
	 * Tool Functions are listed below
	 *
	 */
    function empty_folder($folder, $delete_root_folder = true) {
        if(!$dh = @opendir($folder)) return;
        while (false !== ($obj = readdir($dh))) {
            if($obj=='.' || $obj=='..') continue;
            try{
                //if (!@unlink($folder.'/'.$obj)) $this->empty_folder($folder.'/'.$obj);
                if (is_dir($folder.'/'.$obj)) $this->empty_folder($folder.'/'.$obj, true);
                else @unlink($folder.'/'.$obj);
            } catch(exception $e){}
        }

        closedir($dh);
        if($delete_root_folder)
            @rmdir($folder);
    }

    function check_user_session_timeout(){
        if((isset($this->session['USER_PREV_ACTIVE_TIME']) && (time() - $this->session['USER_PREV_ACTIVE_TIME']) > intval($this->kernel->conf['user_session_timer'])))
        {
            $_GET['t'] = 'session_timeout';
            $_GET['op'] = 'logout';
            $this->logout();
        }
        else
        {
            $this->apply_template = FALSE;
            $this->kernel->response['mimetype'] = 'text/plain';
            $this->kernel->response['content'] = '{}';
        }
    }

    function set_preferred_locale(){
        $preferred_locale = array_ifnull( $_GET, 'locale', '' );
        $redirect_url = array_ifnull( $_GET, 'redirect_url', '' );
        if ( !array_key_exists($preferred_locale, $this->kernel->dict['SET_accessible_locales']) )
        {
            $preferred_locale = NULL;
        }

        // Update existing user
        $sql = 'UPDATE users SET';
        $sql .= ' preferred_locale = ' . $this->kernel->db->escape( $preferred_locale );
        $sql .= " WHERE id = {$this->user->getId()}";
        $statement = $this->kernel->db->query( $sql );
        if ( !$statement )
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }

        $this->kernel->redirect( $redirect_url );
    }

    function processException(Exception $e) {
        $log_message = "";
        $log_type = "error";
        $cls = get_class($e);

        if($this->kernel->db->inTransaction()) {
            $this->kernel->db->rollback();
        }

        if(in_array($cls, array("fieldException"))) {
            $e->error['error_code'] = 'ERROR_' . $e->getMessage();
        } else if(in_array($cls, array("fieldsException"))) {
            if($e->output_type == "json") {
                // do nothing
            } else {

            }
        } elseif($cls == "loginException" || ($cls == "privilegeException" && !$this->user->getId())) {
            if($e->output_type == "json") {
                $this->apply_template = FALSE;
                $this->kernel->response['mimetype'] = 'application/json';
                $this->kernel->smarty->assign('lightbox', true);
                $this->kernel->response['content'] = json_encode(array(
                                                                      'result' => 'session_timeout',
                                                                      'content' => $this->kernel->smarty->fetch('module/admin/index.html')
                                                                 ));
            } else {

                if(isset($e->redirect)) {
                    $redirect = $e->redirect;
                } else {
                    $redirect = "{$this->kernel->sets['paths']['app_from_doc']}/admin/{$this->kernel->request['locale']}/"
                    . '?redirect_url=' . urlencode(array_ifnull($_SERVER, 'REQUEST_URI',
                            $this->user->hasRights($this->module, Right::ACCESS)
                            ? "{$this->kernel->sets['paths']['mod_from_doc']}/"
                            : "{$this->kernel->sets['paths']['mod_from_doc']}/../"));
                }

                $this->kernel->redirect( $redirect );
            }

            return TRUE;
        } else {
            $debug_stack = array();
            $debug_string = $e->getTraceAsString();

            $debug = $e->getTrace();

            $c = count($debug);
            $line = __LINE__;
            $path = __FILE__;
            $module = $this->kernel->response['module'];
            $locale = $this->kernel->request['locale'];
            $log_message = "";


            $actual_exception = $e->getPrevious();
            if(is_null($actual_exception)) {
                $actual_exception = $e;
            }

            if(method_exists($e, 'getLine')) {
                $line = $actual_exception->getLine();
            }

            if(method_exists($e, 'getFile')) {
                $path = $actual_exception->getFile();
            }

            /*if(!isset($e->debug) || $e->debug) {
                for($i = 1; $i < $c; $i++) {
                    $s = $debug[$i];

                    $tmp = isset($s['args']) && count($s['args']) > 0 ? implode(', ', $s['args']) : "";

                    $msg = sprintf("%s(Line: %s) - %s::%s(%s)"
                        , isset($s['file']) ? $s['file'] : "--"
                        , isset($s['line']) ? $s['line'] : -1
                        , isset($s['class']) ? $s['class'] : "--"
                        , $s['function']
                        , is_array($tmp) ? array_shift($tmp) : $tmp
                    );

                    $debug_stack[] = $msg;

                    if($i == 1) {
                        $line = isset($s['line']) ? $s['line'] : -1;
                        $path = isset($s['file']) ? $s['file'] : "--";
                    }
                }
            }*/

            switch($cls) {
                case 'sqlException':
                    //$e->error['error_code'] = 'DB ERROR';
                    $log_message = 'DB Executeion Error: ' . $debug[0]['args'][0];
                    $log_message .= "\n SQL: " . $debug[1]['args'][0];

                    //$e->error['error_text'] = $log_message;
                    //$e->error['error_text'] .= "\r\n\r\nIf problem persists, please contact the system administrator.";
                    // DO NOT SHOW detail execution error to user
                    $e->error['error_text'] = "Unexpected error occurred. Your action event has been Logged.";
                    $e->error['error_text'] .= "\r\n\r\nPlease try again. If problem persists, please contact the system administrator with following Event ID presented: %s.";
                    break;
                case 'privilegeException':
                    $log_message = sprintf('Notice: User (%s) attempted to access an area but rejected by the system due to insufficient privilege.', $this->user->getFirstName());
                    $e->error['error_code'] = 'MESSAGE_insufficient_privilege';
                    $e->error['error_text'] = isset($this->kernel->dict[$e->error['error_code']]) ?  $this->kernel->dict[$e->error['error_code']] : $e->error['error_code'];
                    $log_type = "message";
                    break;
                case 'dataException':
                    $log_message = sprintf('Warning: User (%s) attempted to insert an invalid record and rejected by the system.', $this->user->getFirstName());
                    if(!isset($e->error['error_code']))
                        $e->error['error_code'] = 'ERROR_unexpected_error';
                    $log_type = "warning";
                    break;
                case 'generalException':
                    if(!isset($e->error['error_code']))
                        $e->error['error_code'] = 'ERROR_' . $e->getMessage();
                    break;
                case 'recordException':
                    if(!isset($e->error['error_code']))
                        $e->error['error_code'] = 'ERROR_unexpected_error';
                    break;
                case 'requestException':
                    $e->error['error_code'] = 'ERROR_' . $e->getMessage();
                    break;
                case 'Exception':
                default:
                    $e->error['error_code'] = "";
                    $e->error['error_text'] = $log_message = 'Unexpcted Error: ' . $e->getMessage();
                    break;
            }

            if($log_message)
                $log_message .= "\n>>" . $debug_string;
                //$log_message .= implode("\n>>", $debug_stack);

            if(!$e->redirect) {
                $e->redirect = $this->user->hasRights($this->module, Right::ACCESS)
                    ? "{$this->kernel->sets['paths']['mod_from_doc']}/"
                    : "{$this->kernel->sets['paths']['mod_from_doc']}/../";
            }

            $this->kernel->db->beginTransaction();
            //if(!is_null($e->error['error_code']) && $e->error['error_code'] && (is_null($e->error['error_text']) || $e->error['error_text'] == ""))
            //    $e->error['error_text'] = isset($this->kernel->dict[$e->error['error_code']]) ?  $this->kernel->dict[$e->error['error_code']] : $e->error['error_code'];

            if($log_message != "") {
                //exit;
                if(strlen($log_message) > 10000) {
                    $path = $this->kernel->sets['paths']['app_root'] . '/file/logs/error_' . (time() + microtime()) . '.txt';

                    $handle = fopen($path, "w");
                    fwrite($handle, $log_message);
                    fclose($handle);

                    $log_message = sprintf('Error message too long and will be stored here: %s', $path);
                }
                $id = $this->kernel->log( $log_type, $log_message, $path, $line );
                $e->error['error_text'] = sprintf($e->error['error_text'], $id);
            }
            $this->kernel->db->commit();
        }

        $tmp = isset($e->errors) ? $e->errors : array('errorStack' => array($e->error['error_text']));
        $errors = array();


        foreach($tmp as $field_name => $errs) {
            if(!is_array($errs)) {
                $errs = array($errs);
            }

            foreach($errs as $error) {
                $errors[$field_name][] = isset($this->kernel->dict["ERROR_".$error]) ?  $this->kernel->dict["ERROR_".$error] : $error;
            }
        }

        if(isset($e->output_type) && $e->output_type == "json") {
            $this->apply_template = FALSE;
            $this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode( array(
                                                                   'result' => 'error',
                                                                   'errors' => $errors
                                                              ));
        } else {
            if($this->kernel->conf['debug']
                && !in_array($cls, array('privilegeException', 'loginException', 'fieldException', 'fieldsException', 'recordException', 'requestException', 'smartyException'))
                && $e->debug
                //&& $e->message != 'fields_incorrect'
            ) {
                echo print_r($e);
                exit;
            } else {
                if(in_array($cls, array("fieldException", "fieldsException"))) {
                    // assign error info to current page
                    $this->kernel->smarty->assign('errors', $errors);
                } else {
                    $http_query = array(
                        'op' => 'dialog',
                        'type' => 'error',
                        'code' => $e->error['error_code'],
                        'redirect_url' => $cls=='smartyException' ? '' : $e->redirect
                    );
                    if(!is_null($e->error['error_text'] && $e->error['error_text'])) {
                        $http_query['text'] = $e->error['error_text'];
                    }

                    $this->kernel->redirect( $this->kernel->sets['paths']['app_from_doc'] . '/admin/' . $this->kernel->request['locale'] . '/?' . http_build_query($http_query) );
                }
            }
        }
    }

    function generateDynaTree($ary, $op = false, $target = 0, $return = "json") {
        $output = array();
        foreach($ary as $item) {
            $child = array(
                'title' => $item['name'],
                'key' => isset($item['id']) ?  $item['id'] : $item['name']
            );

            if($op) {
                $child['href'] = $this->kernel->sets['paths']['mod_from_doc'] . '/?op=' . $op . '&id=' . $child['key'];
            }

            if(!$item['enabled']) {
                $child['addClass'] = 'disabled';
            }

            if($item['hasChild']) {
                $child['lazy'] = true;
                if(isset($item['children'])) {
                    $tmp = $this->generateDynaTree($item['children'], $op, $target, $return);

                    //if(is_array($tmp) && count($tmp)) {
                        $child['children'] = $tmp;
                   // }

                    if(isset($item['key']) && $target == $item['key']) {
                        $child['selected'] = true;
                    }
                }
            }

            $output[] = $child;
        }

        if($return == "html") {
            $html = "<ul>";
            foreach($output as $item) {
                $data = array();
                if(isset($item['unselectable']) && $item['unselectable']) {
                    $data[] = 'unselectable: true';
                    $data[] = 'hideCheckbox: true';
                }
                if(isset($item['addClass']) && $item['addClass']) {
                    $data[] = sprintf("addClass: '%s'", $item['addClass']);
                }
                $html .= sprintf('<li id="%1$s" class="%4$s %5$s" data="%7$s"><a href="%3$s" title="%2$s">%2$s</a>%6$s</li>'
                    , $item['key']
                    , htmlspecialchars($item['title'])
                    , htmlspecialchars($item['href'])
                    //, htmlspecialchars("?id=" . $item['key'])
                    , isset($item['lazy']) ? "lazy" : ""
                    , isset($item['children']) ? "expanded" : ""
                    , isset($item['children']) ? $item['children'] : ""
                    , implode(', ', $data));
            }
            $html .= "</ul>";
            return $html;
        } else {
            return $output;
        }
    }

    protected function getRoleTree($show_enabled = true, $type = "admin") {
        // treat as a container to store root nodes
        $tmp_roles = array();
        $roleTree = new treeNode();
        $roleTree->setLevel(-1);

        $sql = sprintf('SELECT * FROM roles WHERE `type` = %s'
                            . ' ORDER BY root_role DESC, parent_id IS NULL DESC, `level` ASC'
                            , $this->conn->escape($type));
        $statement = $this->conn->query($sql);
        while($row = $statement->fetch()) {

            $role = $type . 'Role';
            /** @var adminRole | publicRole $r */
            $r = new $role();
            $r->setId($row['id']);
            $r->setName($row['name']);
            $r->setIsRoot((bool)$row['root_role']);
            $r->setEnabled((bool)$row['enabled']);

            $rn = new roleNode($r);

            if(!$row['parent_id'] || !isset($tmp_roles[$row['parent_id']])) {
                $roleTree->addChild($rn);
            } else {
                $tmp_roles[$row['parent_id']]->addChild($rn);
            }

            $tmp_roles[$row['id']] = $rn;
        }

        if($show_enabled) {
            $roleTree2 = new treeNode();
            $roleTree2->setLevel(-1);
            $children = $roleTree->getChildren();
            $tmp_roles2 = array();

            foreach($children as $child) {
                if($child->getEnabled()) {
                    $p = $child->getParent();
                    $rn = new roleNode($r);
                    $rn->setItem($child->getItem());
                    if(!is_null($p) && get_class($child) == get_class($p)) {
                        $tmp_roles2[$p->getItem()->getId()]->addChild($rn);
                    } else {
                        $roleTree2->addChild($rn);
                    }
                    $tmp_roles2[$rn->getItem()->getId()] = $rn;
                }
            }

            return $roleTree2;
        }

        return $roleTree;
    }

    public function dialogActionEncode($href, $title, $target = "_top", $icon = false) {
        $vars = array($href, $title);

        if($target) {
            $vars[] = $target;
        }

        if($icon) {
            $vars[] = $icon;
        }

        return base64_encode(implode('|', $vars));
    }

    function read_all_files($root = '.'){
        $files  = array('files'=>array(), 'dirs'=>array());
        $directories  = array();
        $last_letter  = $root[strlen($root)-1];
        $root  = ($last_letter == '\\' || $last_letter == '/') ? $root : $root.DIRECTORY_SEPARATOR;

        $directories[]  = $root;

        while (sizeof($directories)) {
            $dir  = array_pop($directories);
            if ($handle = opendir($dir)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    $file  = $dir.$file;
                    if (is_dir($file)) {
                        $directory_path = $file.DIRECTORY_SEPARATOR;
                        array_push($directories, $directory_path);
                        $files['dirs'][]  = $directory_path;
                        $d = $this->read_all_files($directory_path);

                        $files['dirs'] = array_merge($d['dirs'], $files['dirs']);
                        $files['files'] = array_merge($d['files'], $files['files']);
                    } elseif (is_file($file)) {
                        $files['files'][]  = $file;
                    }
                }
                closedir($handle);
            }
        }

        return $files;
    }

    protected function xor_convert( $data, $key )
    {
        for( $i = 0; $i < strlen($data); $i++ ){
            for( $j = 0; $j < strlen($key); $j++ ){ $data[$i] = $data[$i] ^ $key[$j]; }
        }
        return $data;
    }

    protected function xor_decode( $data, $key )
    {
        return admin_module::xor_convert( base64_decode($data),  $key );
    }

    protected function xor_encode( $data, $key )
    {
        return base64_encode( admin_module::xor_convert( $data,  $key ) );
    }

    public function createPvToken($id, $type = "webpage") {
        $token_parts = array(
            $type,  // type
            $id,    // id
            time(), // timestamp
            $this->user->getId(),
            generate_password(10) // random string
        );

        $token = md5(implode('|', $token_parts));

        return array(
            'token' => $token,
            'code' => $this->encodePvToken($token, $type)
        );
    }

    public static function encodePvToken($token, $type = "webpage") {
        return base64_encode(admin_module::xor_encode($type . '|' . $token, admin_module::$pv_salt));
    }

    public static function decodePvToken($encoded_content) {
        $code = base64_decode($encoded_content);

        $decoded_contents = explode('|', admin_module::xor_decode($code, admin_module::$pv_salt));
        try {
            if(count($decoded_contents) == 2) {
                switch($decoded_contents[0]) {
                    case 'announcement':
                    case 'offer':
                    case 'press_release':
                    case 'webpage':
                        return array(
                            'token_type' => $decoded_contents[0],
                            'token' => $decoded_contents[1]
                        );
                }
            }

            throw new Exception("wrong code");
        } catch (Exception $e) {
            return false;
        }

        return false;

    }

    // Member variables
    var $session;           // The module session
    var $apply_template;    // Apply master template or not
}
