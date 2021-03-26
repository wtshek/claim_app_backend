<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The role admin module.
 *
 * This module allows user to administrate user roles.
 *
 * @author  Patrick Yeung <patrick@avalade.com>
 * @since   2013-07-03
 *
 */
class role_admin_module extends admin_module
{
    protected $main_content;
    protected $js;
    public $module = 'role_admin';
    private $available_rights;
    private $webpage_max_available_right;

    private $selected_webpages = array();

    // not real role tree that is currently using (include disabled)
    private $roles = array(
        'admin' => array(),
        'public' => array()
    );

    private $role_accessible_webpages = array();
    private $parent_role_accessible_webpages = null;

    /**
     * Constructor
     *
     * @param $kernel
     * @since 2013-07-03
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );

        $this->available_rights = array(
            'admin' => array()
        );


        $role_types = array('public', 'admin');

        foreach($role_types as $role_type) {
            $this->roles[$role_type] = $this->getRoleTree(false, $role_type);
        }
        $this->selected_webpages = array();
    }

    /**
     * Process the request.
     *
     * @since   2013-07-03
     */
    function process()
    {
        foreach($this->kernel->dict['SET_modules'] as $k => $v) {
            if(preg_match("#_admin$#", $k)) {
                switch($k) {
                    case 'communication_admin':
                    case 'form_admin':
                    case 'log_admin':
                    case 'report_admin':
                        $this->available_rights['admin'][$k] = array(
                            Right::ACCESS, Right::VIEW
                        );
                        break;
                    case 'configuration_admin':
                    case 'my_profile_admin':
                    case 'template_admin':
                        $this->available_rights['admin'][$k] = array(
                            Right::ACCESS, Right::VIEW, Right::EDIT
                        );
                        break;
                    default:
                        $this->available_rights['admin'][$k] = array(
                            Right::ACCESS, Right::CREATE, Right::EDIT, Right::VIEW
                        );
                    break;
                }
            }
        }

        // approval and publish rights
        $approval_modules = array_merge(
            array('offer_admin', 'webpage_admin'),
            array_diff(array_keys($this->kernel->entity_admin_def), array('form_admin'))
        );

        foreach($approval_modules as $module_name) {
            if(isset($this->available_rights['admin'][$module_name])) {
                $this->available_rights['admin'][$module_name] = array_merge(
                    $this->available_rights['admin'][$module_name], array(
                                                                   Right::APPROVE/*,
                                                                   Right::PUBLISH,
                                                              */)
                );
            }
        }

        // export right
        $export_modules = array('admin_user_admin', 'backup_admin', 'form_admin', 'log_admin', 'public_user_admin');
        foreach($export_modules as $module_name) {
            if(isset($this->available_rights['admin'][$module_name])) {
                $this->available_rights['admin'][$module_name] = array_merge(
                    $this->available_rights['admin'][$module_name], array(
                                                                   Right::EXPORT
                                                              )
                );
            }
        }

        try {
            $wrap = true;

            // Choose operation, if not yet processed
            //if ( !parent::process() )
            //{
            $op = $_GET['op'] = array_ifnull( $_GET, 'op', 'index' );
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
                case "get_privileges":
                    $id = intval(array_ifnull($_GET, 'id', 0));
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getPrivileges";
                    $this->params = array($id);
                    break;
                case "edit":
                    $this->rights_required[] = Right::CREATE;
                    if(array_ifnull($_REQUEST, 'id', 0))
                        $this->rights_required[] = Right::EDIT;
                    $this->method = "edit";
                    break;
                case "enable":
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "setEnabled";
                    $this->params = array(1);
                    $wrap = false;
                    break;
                case "disable":
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "setEnabled";
                    $this->params = array(0);
                    $wrap = false;
                    break;
                case "get_admin_nodes":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getRoleNodes";
                    $this->params = array("admin");
                    break;
                case "get_public_nodes":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getRoleNodes";
                    $this->params = array("public");
                    break;
                case "get_child_pages":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getWebpagesInJson";
                    break;
                default:
                    return parent::process();
            }

            // process right checking and throw error and stop further process if any
            $this->user->checkRights($this->module, array_unique($this->rights_required));

            if($this->method) {
                call_user_func_array(array($this, $this->method), $this->params);
            }

            if($this->apply_template && $wrap) {
                $role_types = array('admin', 'public');
                $id = array_ifnull($_GET, 'id', 0);

                foreach($role_types as $role_type) {
                    $n = 'selected_' . $role_type . '_role';
                    $$n = $id;

                    if(!$this->roles[$role_type]->findById($id) && $this->roles[$role_type]->hasChild()) {
                        $children = $this->roles[$role_type]->getChildren(0);
                        $$n = $children[0]->getItem()->getId();
                    }
                }

                $this->kernel->smarty->assign('admin_tree', $this->getRoleNodes("admin", "html", 0, $selected_admin_role, "html"));
                $this->kernel->smarty->assign('public_tree', $this->getRoleNodes("public", "html", 0, $selected_public_role, "html"));
                $this->kernel->smarty->assign('main_content', $this->main_content);
                $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/role_admin/wrap.html' );
            }

            return TRUE;
        } catch(Exception $e) {
            $this->processException($e);
        }
    }

    /**
     * Index
     *
     * @since 2013-07-03
     */
    function index() {
        $_GET['id'] = 0;
        $first_child = $this->roleTree->getChildren();
        if(count($first_child)) {
            $_GET['id'] = $first_child[0]->getItem()->getId();
        }
        $this->view();

        $this->kernel->smarty->assign('main_content', $this->main_content);
        $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/role_admin/wrap.html' );
    }

    function view() {
        $_GET['id'] = intval(array_ifnull($_GET, 'id', 0));
        $type = trim(array_ifnull($_REQUEST, 't', 'admin'));
        if($type != 'public') {
            $type = "admin";
        }

        try {
            $sql = sprintf('SELECT id, `type` FROM roles WHERE id = %d', $_GET['id']);
            $statement = $this->conn->query($sql);
            if($record = $statement->fetch()) {
                $type = $record['type'];
            }

            if($type == "admin") {
                $data = $this->getAdminData($_GET['id']);
            } else {
                $data = $this->getPublicData($_GET['id']);
            }

            $this->_breadcrumb->push(new breadcrumbNode($this->kernel->dict['ACTION_view'] . ' ' . $data['role']['name'], $this->kernel->sets['paths']['mod_from_doc'] . '?op=view&id=' . $data['role']['id']));

            $this->kernel->smarty->assign('viewOnly', true);

            // actions
            $actions = array();
            if($this->user->hasRights($this->module, Right::EDIT)) {
                $actions[] = array(
                    'href' => $this->kernel->sets['paths']['mod_from_doc'] . '/?op=edit&id=' . $_GET['id'],
                    'icon' => 'edit',
                    'text' => $this->kernel->dict['ACTION_edit']
                );

                // disabled / enable button
                if(!$data['role']['root_role']) {
                    if($data['role']['enabled']) {
                        if(!$data['has_active_users'])
                            array_unshift($actions, array(
                                                         'href' => $this->kernel->sets['paths']['mod_from_doc'] . '/?op=disable&id=' . $_GET['id'],
                                                         'icon' => 'remove',
                                                         'text' => $this->kernel->dict['ACTION_disable']
                                                    ));
                    } else {
                        array_unshift($actions, array(
                                                     'href' => $this->kernel->sets['paths']['mod_from_doc'] . '/?op=enable&id=' . $_GET['id'],
                                                     'icon' => 'star',
                                                     'text' => $this->kernel->dict['ACTION_enable']
                                                ));
                    }
                }
            }

            $this->kernel->smarty->assign('actions', $actions);

            $this->main_content = $this->kernel->smarty->fetch('module/role_admin/edit.html');
        } catch(Exception $e) {
            $this->processException($e);
        }
    }

    /**
     * Add and edit role
     *
     */
    function edit() {
        $_GET['id'] = intval(array_ifnull($_GET, 'id', 0));
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);
        $type = trim(array_ifnull($_REQUEST, 't', 'admin'));
        if($type != 'public') {
            $type = "admin";
        }

        try {
            // overwrite the type if id is provided
            if($_GET['id']) {
                $sql = sprintf('SELECT `type` FROM roles WHERE id = %d', $_GET['id']);
                $statement = $this->conn->query($sql);
                if($record = $statement->fetch()) {
                    $type = $record['type'];
                }
            }
            if(count($_POST) > 0) {
                $root_role = false;
                $_POST['role_id'] = intval(array_ifnull($_POST, 'role_id', 0));
                $_POST['role_name'] = trim(array_ifnull($_POST, 'role_name', ''));
                $_POST['parent_role'] = intval(array_ifnull($_POST, 'parent_role', 0));
                $_POST['edm_role'] = trim(array_ifnull($_POST, 'edm_role', ''));
                $_POST['enabled'] = intval(array_ifnull($_POST, 'enabled', 0));
                if(isset($_POST['rights']) && !is_array($_POST['rights'])) {
                    $_POST['rights'] = array($_POST['rights']);
                }
                if(isset($_POST['webpage_rights']) && !is_array($_POST['webpage_rights'])) {
                    $_POST['webpage_rights'] = array($_POST['webpage_rights']);
                }
                $_POST['rights'] = array_unique(array_map('trim', array_ifnull($_POST, 'rights', array())));
                $_POST['webpage_rights'] = array_unique(array_map('trim', array_ifnull($_POST, 'webpage_rights', array())));
                $_POST['webpage_id'] = array_ifnull($_POST, 'webpage_id', array());
                /*$selected_webpage_ids = array();

                if(is_array($_POST['webpage_id'])) {
                    foreach($_POST['webpage_id'] as $platform => $ids) {
                        $selected_webpage_ids = array_merge(array_map('intval', $ids), $selected_webpage_ids);
                    }
                }*/

                //$selected_webpage_ids = array_unique($selected_webpage_ids);

                if($_POST['role_id'] > 0) {
                    $sql = sprintf("SELECT * FROM roles WHERE id = %d", $_POST['role_id']);
                    $statement = $this->conn->query($sql);
                    if($record = $statement->fetch()) {
                        if($record['root_role']) {
                            $root_role = true;
                        }
                        // cannot change role type
                        $type = $record['type'];
                    } else {
                        $_POST['role_id'] = 0;
                    }
                }

                if($root_role)
                    $_POST['enabled'] = 1;

                if($_POST['parent_role'] <= 0)
                    $_POST['parent_role'] = null;

                if($_POST['role_id'] <= 0)
                    $_POST['role_id'] = null;

                if(!array_key_exists($_POST['edm_role'], $this->kernel->dict['SET_edm_roles']))
                    $_POST['edm_role'] = null;

                $errors = array();

                if($_POST['role_name'] === "") {
                    $errors['role_name'][] = 'role_name_empty';
                } else {
                    // check if the name already exists
                    $sql = sprintf('SELECT * FROM roles WHERE `name` = %s AND id <> %d AND `type` = %s'
                                    , $this->conn->escape($_POST['role_name'])
                                    , intval($_POST['role_id'])
                                    , $this->conn->escape($type));
                    $statement = $this->conn->query($sql);
                    if($statement->fetch()) {
                        $errors['role_name'][] = 'role_name_exists';
                    }
                }

                if($root_role && !$_POST['enabled']) {
                    $errors['enabled'][] = 'root_role_cannot_disable';
                }

                $level = 0;
                if(!is_null($_POST['parent_role']) && $_POST['parent_role'] > 0) {
                    $sql = sprintf('SELECT * FROM roles WHERE id = %d AND `type` = %s'
                        , $_POST['parent_role']
                        , $this->conn->escape($type));
                    $statement = $this->conn->query($sql);
                    if($parent = $statement->fetch()) {
                        $level = $parent['level'] + 1;
                    } else {
                        $errors['parent_role'][] = 'parent_role_invalid';
                    }
                }

                if($type == 'admin') {
                    /** @var roleNode $roleTree */
                    $roleTree = $this->roles['admin'];

                    /** @var roleRights $parent_rights */
                    $parent_rights = null;
                    if(is_null($_POST['parent_role']) && !$root_role) {
                        $errors['parent_role'][] = 'parent_role_empty';
                    } elseif(@!$root_role) {

                        /** @var roleNode $role */
                        $parent_role = $roleTree->findById($_POST['parent_role']);
                        $parent_rights = $parent_role->getItem()->getRights();
                    }

                    // create a tmp role object to perform the action
                    $roleRights = new roleRights();
                    foreach($_POST['rights'] as $right) {
                        $p = explode('|', $right);
                        if(count($p) === 2) {
                            if(isset($this->kernel->dict['SET_modules'][$p[0]])) {
                                if(!is_null($parent_rights) && !$parent_rights->hasRights($p[0], $p[1])) {
                                    $errors['errorsStack'][] = 'role_rights_invalid';
                                    break;
                                }

                                $roleRights->addRight($p[0], $p[1]);
                            }
                        }
                    }
                }

                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                } else {
                    $this->conn->beginTransaction();
                    if($_POST['role_id'] > 0) {
                        $sql = sprintf('SELECT * FROM roles WHERE id = %d', $_POST['role_id']);
                        $statement = $this->conn->query($sql);
                        if(!$statement->fetch()) {
                            $_POST['role_id'] = null;
                        }
                    }

                    // new
                    if(is_null($_POST['role_id'])) {
                        $sql = sprintf("INSERT INTO roles(`type`, `name`, `parent_id`, `edm_role`, `enabled`, `created_date`, `creator_id`, `level`)"
                                        . " VALUES(%s, %s, %s, %s, %d, UTC_TIMESTAMP(), %d, %d)"
                                        , $this->conn->escape($type)
                                        , $this->conn->escape($_POST['role_name'])
                                        , $this->conn->escape($_POST['parent_role'])
                                        , $this->conn->escape($_POST['edm_role'])
                                        , $_POST['enabled'] ? 1 : 0
                                        , intval($this->user->getId())
                                        , $level);
                        $this->conn->exec($sql);
                        $id = $this->conn->lastInsertId();
                    } else {
                        $sql = sprintf('UPDATE roles SET `name` = %s, `parent_id` = %s, `edm_role` = %s, `enabled` = %d, `updated_date` = UTC_TIMESTAMP(), updater_id = %d, `level` = %d'
                                        . ' WHERE id = %d'
                                        , $this->conn->escape($_POST['role_name'])
                                        , $this->conn->escape($_POST['parent_role'])
                                        , $this->conn->escape($_POST['edm_role'])
                                        , $_POST['enabled'] ? 1 : 0
                                        , intval($this->user->getId())
                                        , $level
                                        , $_POST['role_id']);
                        $this->conn->exec($sql);
                        $id = $_POST['role_id'];

                        // remove all previous rights
                        $sql = sprintf('DELETE FROM role_rights WHERE role_id = %d', $id);
                        $this->conn->exec($sql);

                        $sql = sprintf('DELETE FROM role_webpage_rights WHERE role_id = %d', $id);
                        $this->conn->exec($sql);
                    }

                    if($type == "admin") {
                        $module_rights = $roleRights->getAllRights();
                        foreach($module_rights as $entity => $rights) {
                            $insert_data = array();
                            foreach($rights as $right) {
                                $insert_data[] = sprintf("(%d, %s, %d)", $id, $this->conn->escape($entity), $right);
                            }

                            $insert_data = array_unique($insert_data);
                            if(count($insert_data) > 0) {
                                $sql = sprintf("INSERT INTO role_rights(`role_id`, `entity`, `right`) VALUES %s", implode(", ", $insert_data));
                                $this->conn->exec($sql);
                            }
                        }

                        $sqls = array();
                        $parent_webpages = array();
                        if(isset($parent_role) && !is_null($parent_role)) {
                            $parent_webpages = $this->getRoleWebpageRights($parent_role->getItem()->getId());
                        }
                        /*foreach($selected_webpage_ids as $wid) {
                            if($root_role || in_array($wid, $parent_webpages))
                                $sqls[] = sprintf('(%d, %d, %d)', $id, $wid, Right::ACCESS);
                        }*/
                        foreach($_POST['webpage_rights'] as $wid_rid)
                        {
                            $p = explode('|', $wid_rid);
                            if(count($p)==2)
                            {
                                if($root_role || (!is_null($parent_rights) && $parent_rights->hasRights('webpage_admin', $p[1])))
                                {
                                    $sqls[] = sprintf('(%d, %d, %d)', $id, $p[0], $p[1]);
                                }
                            }
                        }

                        if(count($sqls)) {
                            $sql = sprintf('REPLACE INTO role_webpage_rights(role_id, webpage_id, `right`) VALUES %s'
                                            , implode(', ', $sqls));
                            $this->conn->exec($sql);
                        }

                        /** @var roleNode $role */
                        $role = $roleTree->findById($id);
                        if(!is_null($role) && $role) {
                            $children = $role->getChildren();
                            $children_ids = array();

                            /** @var roleNode $child */
                            $child = null;
                            foreach($children as $child) {
                                $children_ids[] = $child->getItem()->getId();
                            }

                            if(count($children_ids)) {
                                $sql = sprintf('DELETE FROM role_webpage_rights WHERE role_webpage_rights.role_id IN(%s) AND NOT EXISTS('
                                                . ' SELECT * FROM(SELECT * FROM role_webpage_rights) AS r2'
                                                . ' WHERE r2.role_id = %d AND r2.webpage_id = role_webpage_rights.webpage_id)'
                                                , implode(', ', $children_ids), $id);
                                $this->conn->exec($sql);
                            }
                        }
                    }

                    $this->conn->commit();

                    // log
                    $this->kernel->log( 'message', sprintf("User %s %s role %s", $this->user->getId().' <'.$this->user->getEmail().'>', $_GET['id'] ? "edited" : "created", $id.' ('.$_POST['role_name'].')'), __FILE__, __LINE__ );
                }

                // continue to process (successfully)
                $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                            http_build_query(array(
                                 'op' => 'dialog',
                                 'type' => 'message',
                                 'code' => 'DESCRIPTION_saved',
                                 'redirect_url' => $this->kernel->sets['paths']['server_url']
                                                      . $this->kernel->sets['paths']['mod_from_doc']
                                                      . '?op=view&id=' . $id
                            ));
                if($ajax) {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( array(
                                                                           'result' => 'success',
                                                                           'redirect' => $redirect
                                                                      ));
                    return TRUE;
                } else {
                    $this->kernel->redirect($redirect);
                }
            }
        } catch(Exception $e) {
            $this->processException($e);
        }

        // continue to process if not ajax
        if(!$ajax) {
            $_GET['parent_id'] = intval(array_ifnull($_GET, 'parent_id', 0));

            if($type == "admin") {
                $data = $this->getAdminData($_GET['id']);
            } else {
                $data = $this->getPublicData($_GET['id']);
            }

            $this->kernel->smarty->assign('default_parent_id', $_GET['parent_id']);

            $this->_breadcrumb->push(new breadcrumbNode(($data['role']['id'] ? $this->kernel->dict['ACTION_edit'] . ' ' . $data['role']['name'] : $this->kernel->dict['ACTION_new']), $this->kernel->sets['paths']['mod_from_doc'] . '?op=view&id=' . $data['role']['id']));


            // actions
            $this->kernel->smarty->assign('actions', array(
                                                          array(
                                                              'name' => 'action-submit',
                                                              'type' => 'submit',
                                                              'icon' => 'save',
                                                              'text' => $this->kernel->dict['ACTION_save']
                                                          ),
                                                          array(
                                                              'name' => 'action-cancel',
                                                              'type' => '',
                                                              'icon' => 'remove-circle',
                                                              'text' => $this->kernel->dict['ACTION_cancel']
                                                          )
                                                     ));

            $this->kernel->smarty->assign('type', $type);

            $this->main_content = $this->kernel->smarty->fetch('module/role_admin/edit.html');
        }

    }

    function getAdminData($id) {
        $data = array(
            'role' => array(
                'id' => 0,
                'name' => '',
                'type' => 'admin'
            ),
            'module_rights' => array(),
            'accessible_webpages' => array(),
            'has_active_users' => true
        );

        $sitemaps = array();
        $tree_html = array();
        $flatten_tree_html = array();

        $admin_modules = $dependent_rights = array();
        /** @var roleNode $roleTree */
        $roleTree = $this->roles['admin'];

        $rights = new ReflectionClass('Right');
        foreach($rights->getConstants() as $rn => $rid) {
            $dependent_rights['r' . $rid] = roleRights::getDependentRights($rid);
        }

        foreach($rights->getConstants() as $rn => $rid) {
            $group_dependent_rights['r' . $rid] = roleRights::getGroupDependentRights($rid);
        }

        $group_rights_modules = array('share_file_admin', 'configuration_admin');

        // language resources mapping
        $rights = array(
            Right::VIEW => $this->kernel->dict['LABEL_view'],
            Right::CREATE => $this->kernel->dict['LABEL_create'],
            Right::EDIT => $this->kernel->dict['LABEL_edit'],
            Right::APPROVE => $this->kernel->dict['LABEL_approve'],
            //Right::PUBLISH => $this->kernel->dict['LABEL_publish'],
            Right::EXPORT => $this->kernel->dict['LABEL_export']
        );

        $available_rights = array();
        foreach($this->kernel->dict['SET_modules'] as $k => $v) {
            if(preg_match("#_admin$#", $k) && !in_array($k, array('entity_admin', 'media_admin', 'subsystem_admin', 'user_admin'))) {
                $admin_modules[$k] = $v;
            }
        }

        $target = $roleTree->findById($id);

        $this->getData($id, $data, $roleTree);

        // get data
        if($id > 0) {
            $target = $roleTree->findById($id);
            /** @var roleNode $parent */
            $parent = null;
            if(!is_null($target) && $target) {
                if($target->getLevel() > 0) {
                    $parent = $target->getParent();
                    $this->parent_role_accessible_webpages = $this->getRoleWebpageRights($parent->getItem()->getId());
                }
            }
            if($id) {
                if(count($_POST) > 0) {
                    $roleRights = new roleRights();

                    $data['module_rights'] = $roleRights->getAllRights();
                } else {
                    $sql = 'SELECT IFNULL(COUNT(*), 0) AS num FROM users WHERE role_id='.$id.' AND enabled=1';
                    $statement = $this->conn->query($sql);
                    extract($statement->fetch());
                    $data['has_active_users'] = $num==0 ? false : true;

                    $sql = sprintf('SELECT entity, GROUP_CONCAT(`right` SEPARATOR ",") AS module_rights FROM role_rights WHERE role_id = %d GROUP BY entity', $id);
                    $statement = $this->conn->query($sql);

                    while($row = $statement->fetch()) {
                        $data['module_rights'][$row['entity']] = array_map('intval', explode(',', $row['module_rights']));
                    }

                    foreach($this->kernel->dict['SET_modules'] as $k => $v) {
                        $available_rights[$k] = $rights;

                        foreach($rights as $adminRight => $dummy) {
                            if(!$this->roleModuleRightAvailable($target, $k, $adminRight)) {
                                unset($available_rights[$k][$adminRight]);
                            }
                        }

                        if(!count($available_rights[$k]))
                            unset($available_rights[$k]);
                    }

                    // get webpage rights for webpage admin
                    $this->role_accessible_webpages = $data['accessible_webpages'] = $this->getRoleWebpageRights($id);
                }
            }
            else
                $data['has_active_users'] = false;

            $sql = sprintf('SELECT IFNULL(MAX(`right`), 0) AS max_right FROM role_rights WHERE role_id = %d AND entity="webpage_admin"', $id);
            $statement = $this->conn->query($sql);
            if($tmp = $statement->fetch()) {
                $this->webpage_max_available_right=$tmp['max_right'];
            }
        }

        foreach(array_keys($this->kernel->dict['SET_content_types']) as $platform) {
            /** @var sitemap $sitemap */
            $sitemap = $this->get_sitemap('edit', $platform);
            $tree_html[$platform] = $this->kernel->dict['DESCRIPTION_no_webpage_tree_constructed'];
            $flatten_tree_html[$platform] = $this->kernel->dict['DESCRIPTION_no_webpage_tree_constructed'];

            if(!is_null($sitemap) && $sitemap->countPages()) {
                $sitemaps[$platform] = $sitemap;

                // make it desktop by default
                // a temp sitemap for visible distribution
                $sm = new sitemap($platform);

                // tmp page node as wrapper
                $tmp = new staticPage();
                $tmp->setPlatforms(array($platform));
                $tmp->setId(-1);
                $root = new pageNode($tmp, $platform);

                $sm->add($root);

                // display as root
                //$node = $sitemap->getRoot()->cloneNode();
                $node = $sitemap->getRoot();
                $root->AddChild($node);

                //$tree_html[$platform] = $this->generateWebpageDynaTree($root, $_GET['op'] != "edit");
                $tree_html[$platform] = $this->generateWebpageDynaTree($sm->getRoot(), $_GET['op'] != "edit", 'json');

                // flatten the array of tree_html
                $flatten_tree_html[$platform] = array();
                $flatten_tree_html[$platform] = $this->flattenWebpageTree($tree_html[$platform], $flatten_tree_html[$platform], $id);
            }
        }

        //echo print_r($flatten_tree_html);exit;

        $this->kernel->smarty->assign('tree_html', $tree_html);
        $this->kernel->smarty->assign('flatten_tree_html', $flatten_tree_html);
        $this->kernel->smarty->assign('webpage_max_available_right', $this->webpage_max_available_right);

        $this->kernel->smarty->assign('admin_modules', $admin_modules);
        $this->kernel->smarty->assign('available_rights', $available_rights);
        $this->kernel->smarty->assign('dependent_rights', $dependent_rights);
        $this->kernel->smarty->assign('group_rights_modules', $group_rights_modules);
        $this->kernel->smarty->assign('group_dependent_rights', $group_dependent_rights);

        $this->kernel->smarty->assign('admin_rights', $rights);

        $this->kernel->smarty->assignByRef('data', $data);
        return $data;
    }

    function flattenWebpageTree($webpage_tree = array(), $flatten_array = array(), $role_id=0, $level=0, $parent_webpage=0)
    {
        if (is_array($webpage_tree) || is_object($webpage_tree))
        {
            foreach($webpage_tree as $k=>$webpages)
            {
                $tmp = $webpages;
                $tmp['level'] = $level;
                if($parent_webpage>0)
                    $tmp['parent'] = $parent_webpage;
                $sql = 'SELECT `right` FROM role_webpage_rights WHERE role_id='.$role_id.' AND webpage_id='.$tmp['key'];
                $tmp['rights'] = $this->kernel->get_set_from_db( $sql );
                unset($tmp['children']);
                $flatten_array[] = $tmp;
                if(count($webpages['children'])>0)
                {
                    $flatten_array = $this->flattenWebpageTree($webpages['children'], $flatten_array, $role_id, $level+1, $webpages['key']);
                }
            }
        }

        return $flatten_array;
    }

    function getPublicData($id) {
        $data = array(
            'role' => array(
                'id' => 0,
                'name' => '',
                'type' => 'public'
            )
        );

        /** @var roleNode $roleTree */
        $roleTree = $this->roles['public'];
        $this->getData($id, $data, $roleTree);

        $this->kernel->smarty->assignByRef('data', $data);
        return $data;
    }

    function getData(&$id, &$data, &$roleTree) {

        $role_options = $roleTree->generateOptions(false);
        $role_keys = array_keys($role_options);
        $role_keys = array_map('substr', array_keys($role_options), array_fill(0,count($role_options),1));

        if($id > 0) {
            $sql = sprintf("SELECT * FROM roles WHERE id = %d", $id);
            $statement = $this->conn->query($sql);
            if($data['role'] = $statement->fetch()) {
                if(count($_POST) > 0) {
                    $data['role'] = array(
                        'name' => $_POST['role_name'],
                        'parent_id' => $_POST['parent_role'],
                        'enabled' => $_POST['enabled'],
                    );
                }
            } else {
                $id = 0;
                throw new recordException('ERROR_record_not_exists');
            }

            $exclude_ids = array($id);

            if($id) {
                $target = $roleTree->findById($id);

                if($target) {
                    $children = $target->getChildren();
                    foreach($children as $child) {
                        $exclude_ids[] = $child->getItem()->getId();
                    }
                }
            }

            $this->kernel->smarty->assign('exclude_ids', $exclude_ids);
        }

        $this->kernel->smarty->assign('role_tree_options', array_combine($role_keys, array_values($role_options)));
    }

    function getRoleNodes($type, $output = "json", $parent = 0, $target = 0) {
        $type = $type == "public" ? $type : "admin";

        $parent = intval(array_ifnull($_GET, 'parent', $parent));
        $target = intval(array_ifnull($_GET, 'target', $target));
        if($target) {
            // see if the target really exists
            $sql = sprintf('SELECT * FROM roles WHERE id = %d', $target);
            $statement = $this->conn->query($sql);
            if($statement->fetch()) {
                $parent = 0;
            } else {
                $target = 0; // not exists
            }
        }

        if($parent < 1) {
            $parent = null;
        }

        if(is_null($parent) && !$target) {
            $sql = 'SELECT * FROM roles ORDER BY `level` ASC LIMIT 0, 1';
            $statement = $this->conn->query($sql);
            $record = $statement->fetch();
            $target = $record['id'];
        }
        $nodeTree = $this->generateDynaTree($this->getRolesInArray($type, $target, $parent), 'view', 0, $output);

        if($output == "json") {
            $this->apply_template = false;
            $this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode($nodeTree);
        } else {
            return $nodeTree;
        }

    }

    private function getRolesInArray($type, $target = false, $parent = false) {
        $direct_children = array();

//        if($_GET['target']) {
//            $sql = sprintf('SELECT * FROM roles '
//                            . 'WHERE `level` <= %d ORDER BY root_role DESC, parent_id IS NULL DESC, `level` ASC'
//                            , intval($result->fields['level']));
//            $rows = $this->conn->getAll($sql);
//
//            // get all roles with direct parent
//            foreach($rows as $row) {
//                if($row['level'] == $result->fields['level'])
//                    $direct_children[] = intval($row['id']);
//            }
//        } else {
//            $sql = sprintf('SELECT * FROM roles WHERE `type` = "%1$s" ORDER BY root_role DESC, parent_id IS NULL DESC, `level` ASC'
//                , $type);
//
//            $rows = $this->conn->getAll($sql);
//
//            // get all roles with direct parent
////            foreach($rows as $row) {
////                $direct_children[] = intval($row['id']);
////            }
//        }

//        if(count($direct_children) > 0) {
//            // get parent's direct child
//            $sql = sprintf('SELECT *, "1" AS lazy FROM roles WHERE `type` = "%1$s" AND parent_id IN(%2$s) ORDER BY root_role DESC, parent_id IS NULL DESC, `level` ASC'
//                , $type, implode(', ', $direct_children));
//            $rows = array_merge($rows, $this->conn->getAll($sql));
//        }

        $sql = sprintf('SELECT * FROM roles WHERE `type` = "%1$s" ORDER BY root_role DESC, parent_id IS NULL DESC, `level` ASC'
            , $type);

        $statement = $this->conn->query($sql);

        // treat as a container to store root nodes
        $tmp_roles = array();
        $roleTree = new treeNode();
        $roleTree->setLevel(-1);

        $targetTree = $roleTree;

        while($row = $statement->fetch()) {
            if(isset($row['lazy']) && $row['lazy']) {
                $tmp_roles[$row['parent_id']]->setHasChild(true);
            } else {
                $r = new adminRole();
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
        }

        if($parent) {
            $targetTreeAry = array();
            $pNode = $roleTree->findById($parent);

            if($pNode) {
                $tmp = $pNode->toArray();

                foreach($tmp['children'] as $node) {
                    // get only direct child
                    $tmp = $node;
                    unset($tmp['children']);
                    $targetTreeAry[] = $tmp;
                }
            }
        } elseif($target) {
            // this is already the correct array
            $targetTreeAry = $roleTree->findUntilId($target);
        }

        if(!is_array($targetTreeAry)) {
            $first_id = $roleTree->getChildren(0);
            $first_id = $first_id[0]->getItem()->getId();
            $targetTreeAry = $roleTree->findUntilId($first_id);
        }

        return $targetTreeAry;
    }

    private function setEnabled($enable = true) {
        try {
            $_GET['id'] = intval(array_ifnull($_GET, 'id', 0));

            if($_GET['id']) {
                $sql = sprintf('SELECT * FROM roles WHERE id = %d AND root_role = 0', $_GET['id']);
                $statement = $this->conn->query($sql);

                if($record = $statement->fetch()) {
                    $sql = sprintf('UPDATE roles SET enabled = %d WHERE id = %d', $enable ? 1 : 0, $_GET['id']);
                    $this->conn->exec($sql);

                    $_GET['type'] = 'message';
                    $_GET['code'] = 'DESCRIPTION_saved';
                    $_GET['redirect_url'] = $this->kernel->sets['paths']['server_url']
                                        . $this->kernel->sets['paths']['mod_from_doc']
                                        . '?op=view&id=' . $_GET['id'];
                    $this->dialog();
                } else {
                    throw new generalException("role_not_exists");
                }
            }

            // log
            $this->kernel->log( 'message', sprintf("User %d %s role %d", $this->user->getId().' <'.$this->user->getEmail().'>', $enable ? "enabled" : "disabled", $_GET['id'].' ('.$record['name'].')'), __FILE__, __LINE__ );

        } catch(Exception $e) {
            $this->processException($e);
        }
    }

    public function moduleRightAvailable($module, $right) {
        if(isset($this->available_rights['admin'][$module])) {
            return in_array($right, $this->available_rights['admin'][$module]);

        }
        return false;
    }


    /**
     * Check whether the role right is available by going through the role tree
     * (start from parent and go up and see if it is available or not)
     *
     * @param roleNode $role
     * @param          $module
     * @param          $rights
     * @return bool
     */
    public function roleModuleRightAvailable(roleNode $role, $module, $rights) {
        if(!is_array($rights)) {
            $rights = array($rights);
        }

        foreach($rights as $right) {
            if(!$this->moduleRightAvailable($module, $right))
                return false;
        }

        if($role->getLevel() <= 0) {
            return true;
        }

        return $role->getParent()->hasRights($module, $right);
    }

    protected function getPrivileges($id) {
        $roleTree = $this->roles['admin'];

        $output = array(
            'result' => 'success',
            'data' => array(
                'modules' => array(),
                'webpages' => array()
            )
        );

        /** @var roleNode $role */
        $role = $roleTree->findById($id);
        if($role) {
            $rights = $role->getItem()->getRights();
            $output['data']['modules'] = $rights->getAllRights();
            //$output['data']['webpages'] = $this->getRoleWebpageRights($id);
            $sql = sprintf('SELECT * FROM role_webpage_rights WHERE role_id = %d', $id);
            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
                $output['data']['webpages'][$row['webpage_id']][] = $row['right'];
            }
        } else {
            $output['data']['modules'] = $this->available_rights['admin'];
/*
            // get all pages
            $ids = array();
            $platforms = array_keys($this->kernel->dict['SET_webpage_page_types']);
            foreach($platforms as $platform) {
                $sm = $this->get_sitemap('edit', $platform);
                $ids[] = $sm->getRoot()->getItem()->getId();

                $children = $sm->getRoot()->getChildren();
                foreach($children as $child) {
                    $ids[] = $child->getItem()->getId();
                }
            }
            $ids = array_map('intval', array_unique($ids));

            $output['data']['webpages'] = $ids;
*/
        }

        $this->apply_template = FALSE;
        $this->kernel->response['mimetype'] = 'application/json';
        $this->kernel->response['content'] = json_encode($output);
    }

    protected function generateWebpageDynaTree(pageNode $node, $unselectable = true, $output = "html", $lazy = true) {
        $children = $node->getChildren(0);

        if($output == "html") {
            $html = "<ul>";
        } else {
            $ary = array();
        }

        /** @var pageNode $child */
        $child = null;
        foreach($children as $child) {
            $innerhtml = array();
            $classes = array();
            $data = array(
                'key' => $child->getItem()->getId()
            );
            $extras = array();

            if($child->hasChild()) {
                //if($output == "ajax")
                //echo print_r($child);
                if(!count($child->getChildren(0))) {
                    if($lazy) {
                        $classes[] = 'lazy';
                        $data['isLazy'] = $output == "html" ? 'true' : true;
                    }
                } else {
                    $innerhtml = $this->generateWebpageDynaTree($child, $unselectable, $output, $lazy);
                    $classes[] = 'expanded';
                    $data['expand'] = $output == "html" ? 'true' : true;
                }
            }

            //                  root webpage has null value for parents
            if($unselectable || (!is_null($this->parent_role_accessible_webpages)
                    && !in_array($data['key'], $this->parent_role_accessible_webpages))) {
                $data['unselectable'] = $output == "html" ? 'true' : true;
            }

            if(in_array($data['key'], $this->role_accessible_webpages)) {
                $data['select'] = $output == "html" ? 'true' : true;
            }

            if(isset($data['unselectable']) && @$data['unselectable']) {
                $classes[] = 'disabled';
            }

            if(in_array($child->getItem()->getId(), $this->selected_webpages)) {
                $data['select'] = $output == "html" ? 'true' : true;;
            }

            if(count($classes)) {
                $data['addClass'] = implode(' ', $classes);
            }

            $item = $child->getItem();
            $item_info = $child->getNodeInfo();
            $data['href'] = $this->kernel->sets['paths']['app_from_doc'] . '/' . $item->getLocales()[0] . '/preview'
                . $item->getRelativeUrl();

            $title = sprintf('<span>%1$s%2$s</span>'
                , ($item_info['title'] ? $item_info['title'] : '(' . $this->kernel->dict['LABEL_no_title'] . ')') . ' (#' . $item->getId() . ')'
                , count($extras) > 0 ? " " . implode("\n ", $extras) : "");

            if($output == "html") {
                $data_html = array();
                foreach($data as $key => $val) {
                    if(in_array($key, array('addClass', 'href'))) {
                        $val = "'" . $val ."'";
                    }

                    $data_html[] = $key . ': ' . $val;
                }

                $html .= sprintf('<li data="%3$s">%1$s%2$s</li>'
                    , $title, $innerhtml
                    , implode(', ', $data_html));
            } else {
                $data['children'] = $innerhtml;
                $tmp = array_merge(array(
                                        'title' => $title,
                                   ), $data);

                $ary[] = $tmp;
            }
        }

        if($output == "html") {
            $html .= "</ul>";

            return $html;
        } else {
            return $ary;
        }
    }

    /* not used because the tree is generated in the init function */
    function getWebpagesInJson() {
        try {
            $id = intval(array_ifnull($_GET, 'id', 0));
            $platform = trim(array_ifnull($_GET, 'p', 'desktop'));
            $unselectable = (bool) intval(array_ifnull($_GET, 'u', 0));

            if(!isset($this->kernel->dict['SET_webpage_page_types'][$platform]))
                $platform = "desktop";

            $sitemap = $this->get_sitemap('edit', $platform);
            $target = $sitemap->getRoot()->findById($id);

            $json = array(
                'result' => 'success',
                'items' => $this->generateWebpageDynaTree($target, $unselectable, 'json')
            );

            $this->apply_template = FALSE;
            $this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode($json);
        } catch(Exception $e) {
            $this->processException($e);
        }
    }

    function getRoleWebpageRights($role_id = 0) {
        $sql = sprintf('SELECT DISTINCT webpage_id FROM role_webpage_rights WHERE role_id = %d', $role_id);
        return $this->kernel->get_set_from_db( $sql );
    }
}

?>