<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );

/**
 * The webpage admin module.
 *
 * This module allows user to administrate webpages.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @author  Patrick Yeung <patrick@avalade.com>
 * @since   2008-11-06
 */
class webpage_admin_module extends admin_module
{
    public $module = 'webpage_admin';
    public $ref = "";
    public $action_title = "";
    public $locked_pages = array();
    public $publicRoleTree = null;

    public $user_accessible_locales = array();
    public $user_accessible_region_locales = array();
    public $user_default_locale_read_only = true;
    public $_accessible_webpages;
    //public $_time_start = 0;

    // null will be all
    public static $user_accessible_webpages;

    public $AWS = null;
    public $AWS_config = array();

    /**
     * Constructor.
     *
     * @since   2008-11-06
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {//$this->_time_start = microtime(true);
        //echo 'Webpage admin index loading start at: '.$this->_time_start;
        parent::__construct( $kernel );

        $this->ref = array_ifnull($_SERVER, 'HTTP_REFERER', "{$this->kernel->sets['paths']['mod_from_doc']}/");
        $this->kernel->smarty->assignByRef("reference_url", $this->ref);

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );

        // Change working directory
        chdir( "{$this->kernel->sets['paths']['app_root']}/file/" );
    }

    /**
     * Process the request.
     *
     * @since   2008-11-06
     * @return  Processed or not
     */
    function process()
    {//echo 'Time used when call process function: '. (microtime(true)-$this->_time_start);
        $this->kernel->smarty->assignByRef('action_title', $this->action_title);

        try{

            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case "index":
                    $this->rights_required[] = Right::ACCESS;
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "index";
                    break;

                case "get_webpage_nodes":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getWebpageNodes";
                    break;

                case 'lock':
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "lock";
                    break;

                case 'unlock':
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "unlock";
                    $this->apply_template = false;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( array(
                                                                           'result' => 'success'
                                                                      ));
                    break;

                case 'edit':
                    $id = intval(array_ifnull($_REQUEST, 'id', 0));

                    if(isset($_POST['status'])) {
                        $_POST['status'] = trim(array_ifnull($_REQUEST, 'status', ''));
                        if($_POST['status'] == "approved")
                            $this->rights_required[] = Right::APPROVE;
                    }

                    if($id)
                        $this->rights_required[] = Right::EDIT;
                    else
                        $this->rights_required[] = Right::CREATE;

                    $this->method = "edit";
                    break;

                case 'move':
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "move";
                    break;

                case 'delete':
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "delete_page";
                    break;

                case 'undelete':
                    $_GET['dr'] = trim(array_ifnull($_GET, 'dr', 'd'));
                    if($_GET['dr'] != 'm')
                        $_GET['dr'] = 'd'; // desktop by default

                    $this->set_deleted( 0, $_GET['dr'] );
                    break;

                case 'view':
                    $this->view();
                    break;

                case 'save_comment':
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "save_comment";
                    break;

                case 'update_p_ses':
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "update_p_ses";
                    break;

                case 'check_p_unlock':
                    $webpage_id = intval(array_ifnull($_GET, 'webpage_id', ''));

                    $this->rights_required[] = Right::EDIT;
                    $this->method = "check_page_session_timer";
                    $this->params = array($webpage_id);
                    break;

                case 'check_in':
                    $webpage_id = intval(array_ifnull($_GET, 'id', ''));

                    $this->rights_required[] = Right::EDIT;
                    $this->method = "check_in_webpage";
                    $this->params = array($webpage_id);
                    break;

                case 'checkout':
                    //$this->checkout();
                    break;

                case 'update_status':
                    $action = trim(array_ifnull($_REQUEST, 'a', ''));

                    switch($action) {
                        case "approved":
                            $this->rights_required[] = Right::APPROVE;
                            $this->params = array('approved');
                            break;
                        case "pending":
                            $this->rights_required[] = Right::EDIT;
                            $this->params = array('pending');
                            break;
                        default:
                            throw new generalException('action_not_exists', "html", NULL, NULL, 0, NULL, false);
                            break;
                    }

                    $this->method = "updateStatus";
                    break;

                case 'generate_token':
                    $webpage_id = intval(array_ifnull($_GET, 'id', ''));

                    $this->rights_required[] = Right::EDIT;
                    $this->method = "genToken";
                    $this->params = array($webpage_id);
                    break;

                case 'remove_token':
                    $webpage_id = intval(array_ifnull($_GET, 'id', ''));

                    $this->rights_required[] = Right::EDIT;
                    $this->method = "removeToken";
                    $this->params = array($webpage_id);
                    break;

                case 'quick_edit_page_search':
                    $keyword = trim(array_ifnull($_GET, 'kw', ''));
                    $pages_ingore = array_map('intval', array_ifnull($_GET, 'ignore', array()));

                    $this->rights_required[] = Right::VIEW;
                    $this->method = "quickEditPgSearch";
                    $this->params = array($keyword, $pages_ingore);
                    //$this->quickEditPgSearch($this->params);
                    break;
                case 'load_older_locale_versions':
                    $webpage_id = intval(array_ifnull($_GET, 'webpage_id', 0));
                    $locale = trim(array_ifnull($_GET, 'locale', ''));
                    $this->rights_required[] = Right::VIEW;
                    $this->method = 'load_older_locale_versions';
                    $this->params = array($webpage_id, $locale);
                    break;

                /*
                case 'load_customize_snippets':
                    $keyword = trim(array_ifnull($_GET, 'keyword', ''));
                    $this->rights_required[] = Right::VIEW;
                    $this->method = 'load_customize_snippets';
                    $this->params = array($keyword);
                    break;
                */

                default:
                    return parent::process();
            }

            // process right checking and throw error and stop further process if any
            $this->user->checkRights($this->module, array_unique($this->rights_required));

            // get accessible webpages and denied request to non accessible webpages
            // check by id
            $this->_accessible_webpages = $accessible_webpages = webpage_admin_module::getWebpageAccessibility();
            $wid = intval(array_ifnull($_REQUEST, 'id', 0));
            if($wid && !is_null($accessible_webpages) && !in_array($wid, $accessible_webpages)) {
                throw new privilegeException('insufficient_rights');
            }

            $this->kernel->smarty->assign('accessible_webpages', $accessible_webpages);

            // Set accessible locales set for the user
			if(count($this->user->getAccessibleLocales())>0)
            {
                $sql = 'SELECT region_alias, alias, name FROM locales WHERE alias IN ('.implode(',', array_map(array($this->conn, 'escape'), $this->user->getAccessibleLocales())).') ORDER BY region_alias, `default` DESC, name';
                $statement = $this->conn->query($sql);

                while($row = $statement->fetch()) {
                    $this->user_accessible_locales[$row['alias']] = $row['name'];
                    $this->user_accessible_region_locales[$row['region_alias']][$row['alias']] = $row['name'];
                    if($this->kernel->default_public_locale == $row['alias'])

                        $this->user_default_locale_read_only = false;
                }
            }

            // get locked webpages
            $sql = sprintf('SELECT DISTINCT webpage_id FROM webpage_locks WHERE locker_id <> %d', $this->user->getId());
            $this->locked_pages = $this->kernel->get_set_from_db($sql);

            $this->publicRoleTree = $this->getRoleTree(false, 'public');
//echo 'Time used before call index function: '. (microtime(true)-$this->_time_start);
            if($this->method) {
                call_user_func_array(array($this, $this->method), $this->params);
            }

            return TRUE;
        } catch(Exception $e) {
            $this->processException($e);

            return FALSE;
        }
    }

    function getPageData($id = 0, $version = 0) {
		$accessible_locales = array_keys($this->user_accessible_locales);
        $escaped_accessible_locales = array();
        foreach($accessible_locales as $alias)
        {
            $escaped_accessible_locales[] = $this->kernel->db->escape($alias);
        }

        // see if the webpage really exists
        $sql = sprintf('SELECT w.id, w.major_version, w.minor_version, w.`type`, w.shown_in_site'
            . ', CONVERT_TZ(wl.publish_date, "GMT", %2$s) AS publish_date'
            . ', CONVERT_TZ(wl.removal_date, "GMT", %2$s) AS removal_date'
            . ', w.deleted, wl.status AS status'
            . ', CONVERT_TZ(w.created_date, "GMT", %2$s) AS created_date, w.creator_id'
            //. ', CONVERT_TZ( w.updated_date, "GMT", %2$s) AS updated_date, w.updater_id'
            //. ', updater.first_name AS updater_name, updater.email AS updater_email'
            . ', creator.first_name AS creator_name, creator.email AS creator_email'
            . ', CONCAT(w.major_version, ".", w.minor_version) AS version'
            . ' FROM webpages w'
            //. ' LEFT JOIN (SELECT * FROM (SELECT * FROM webpage_locales WHERE webpage_id=%1$d AND locale IN (%4$s) ORDER BY FIELD(status, "draft", "pending", "approve") DESC) AS wl GROUP BY major_version, minor_version) wl'
			. ' LEFT JOIN (SELECT webpage_id, major_version, minor_version, publish_date, removal_date, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY FIELD (status, "draft", "approved")), ",", 1) AS status FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.webpage_id=wv.id AND wl.domain=wv.domain AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE webpage_id=%1$d AND locale IN (%4$s)) AS wl GROUP BY webpage_id) wl'
            . ' ON (wl.webpage_id=w.id AND wl.major_version=w.major_version AND wl.minor_version=w.minor_version)'
            . ' LEFT JOIN users creator ON(w.creator_id = creator.id)'
            //. ' LEFT JOIN users updater ON(w.updater_id = updater.id)'
            . ' WHERE w.domain = \'private\' AND w.id = %1$d'
            //. ' AND (w.status NOT IN("rejected"))'
            , $id, $this->kernel->conf['escaped_timezone'], $this->user->getId(), implode(',', $escaped_accessible_locales));
        if($version > 0) {
            $sql .= sprintf(" AND major_version = %d AND minor_version = %d"
                , floor($version), intval(strpos($version, '.') ? substr($version, strpos($version, '.')+1) : 0));
        } else {
            $sql .= sprintf(" ORDER BY major_version DESC, minor_version DESC");
        }

        $sql .= " LIMIT 0, 1";
        $statement = $this->conn->query($sql);

        if( ($webpage_data = $statement->fetch())
            && ($version || !$webpage_data['deleted'] || $webpage_data['status'] != 'approved')
        ) {
            // Get updated_date, updater_id, updater_name,  updater_email
            $sql = sprintf('SELECT CONVERT_TZ( wl.updated_date, "GMT", %2$s) AS updated_date, wl.updater_id, '
                    . ' updater.first_name AS updater_name, updater.email AS updater_email FROM webpage_locales wl'
                    . ' LEFT JOIN users updater ON(wl.updater_id = updater.id)'
                    . ' WHERE domain = \'private\' AND webpage_id = %1$d AND major_version = %3$d AND minor_version = %4$d'
                    . ' ORDER BY wl.updated_date DESC LIMIT 0,1'
                    , $id, $this->kernel->conf['escaped_timezone'], $webpage_data['major_version'], $webpage_data['minor_version']
            );
            $statement = $this->conn->query($sql);
            if($meta_data = $statement->fetch())
            {
                $webpage_data = array_merge($webpage_data, $meta_data);
            }

            $webpage_data['platforms'] = array();
            if(isset($webpage_data['type']) && !count($_POST)) {
                $sql = sprintf('SELECT *, SUBSTRING_INDEX(SUBSTRING_INDEX(path, "/", -2), "/", 1) AS alias'
                                . ' FROM webpage_platforms'
                                . ' WHERE domain = \'private\' AND webpage_id = %1$d'
                                . ' AND major_version = %2$d AND minor_version = %3$d'
                                .  ($webpage_data['status'] == "approved" ? ' AND (deleted = 0)' : '')
                                , $id, $webpage_data['major_version'], $webpage_data['minor_version']);
                $statement = $this->conn->query($sql);

                while($row = $statement->fetch()) {
                    if(in_array($webpage_data['type'], array("webpage_link"))) {
                        $sql = sprintf('SELECT * FROM (SELECT * FROM webpages WHERE'
                            . ' domain = \'private\' AND id = %1$d'
                            . ' ORDER BY major_version DESC, minor_version DESC LIMIT 0, 1'
                            . ' ) w JOIN webpage_platforms p'
                            . ' ON(w.domain = p.domain AND w.id = p.webpage_id AND w.major_version = p.major_version AND w.minor_version = p.minor_version)'
                            . ' WHERE p.platform = %2$s'
                            , $row['linked_webpage_id']
                            , $this->conn->escape($row['platform']));
                        $statement2 = $this->conn->query($sql);
                        if($tmp = $statement2->fetch()) {
                            $row['path'] = $tmp['path'];
                        }
                    }

                    if ($webpage_data['type'] == "url_link") {
                        $sql = sprintf('SELECT * FROM webpage_locales WHERE domain = \'private\' AND webpage_id = %1$d'
                                        . ' AND major_version = %2$d AND minor_version = %3$d'
                                        , $id, $webpage_data['major_version'], $webpage_data['minor_version']);
                        $statement2 = $this->conn->query($sql);
                        while ($tmp_row = $statement2->fetch()) {
                            $webpage_data['locale_urls'][$tmp_row['locale']] = $tmp_row;
                        }
                    }

                    if ($webpage_data['type'] == "static" && $row['template_id']) {
                        $sql = sprintf('SELECT * FROM templates WHERE id = %d', $row['template_id']);
                        $statement2 = $this->conn->query($sql);
                        if($tmp = $statement2->fetch()) {
                            $row['template_name'] = $tmp['template_name'];
                            $row['template_thumbnail'] = $tmp['thumbnail'];
                        }
                    }

                    // get the webpage title
                    $sql = sprintf('SELECT webpage_title FROM webpage_locales WHERE domain = \'private\' AND webpage_id = %1$d'
                                    . ' AND major_version = %2$d AND minor_version = %3$d'
                                    . ' AND webpage_title IS NOT NULL AND webpage_title <> ""'
                                    . ' ORDER BY locale = %4$s DESC, locale ASC'
                                    . ' LIMIT 0, 1'
                                    , $id, $webpage_data['major_version'], $webpage_data['minor_version']
                                    //, $this->conn->escape($this->kernel->request['locale']));
                                    //, $this->conn->escape($this->kernel->default_public_locale)); // the default locale of public site is not same as that of the admin panel all the time
                                    , $this->conn->escape($this->user->getPreferredLocale()));
                    $statement2 = $this->conn->query($sql);

                    if($tmp = $statement2->fetch()) {
                        $webpage_data['webpage_title'] = $tmp['webpage_title'];
                    }

                    if(!isset($webpage_data['webpage_title']) || !$webpage_data['webpage_title'])
                        $webpage_data['webpage_title'] = $this->kernel->dict['LABEL_no_title'];

                    $webpage_data['platforms'][$row['platform']] = $row;
                }
            }

            $platforms = array_keys($webpage_data['platforms']);
            $webpage_data['public_accessible_roles'] = array();
            /** @var roleNode $publicRoleTree */
            $publicRoleTree = $this->getRoleTree(false, "public");
            foreach($platforms as $platform) {
                $sm = $this->get_sitemap('edit', $platform);

                $root = $sm->getRoot();

                if($root) {
                    /** @var pageNode $page */
                    $page = $root->findById($webpage_data['id']);

                    if($page) {
                        $role_ids = $page->getAccessiblePublicRoles();

                        foreach($role_ids as $role_id) {
                            /** @var roleNode $role */
                            $role = $publicRoleTree->findById($role_id);

                            if($role) {
                                $webpage_data['public_accessible_roles'][] = $role->getItem();
                            }
                        }
                        break;
                    }
                }
            }
            $this->get_sitemap('edit', 'desktop');

            return $webpage_data;
        }

        return false;
    }

    function find($str = "", $status = null, $callback = false, $escaped_accessible_locales) {
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);
        $str = trim($str);

        $limit_pages = webpage_admin_module::getWebpageAccessibility();
        if(!is_null($limit_pages) && !count($limit_pages)) {
            $limit_pages[] = 0;
        }

        try {
            $keywords = $this->consrtuctKeywords($str);

            foreach($keywords as &$keyword) {
                $keyword = "%" . $keyword . "%";

                unset($keyword);
            }

            if(count($keywords) > 0 || !is_null($status)) {
                $where_conditions = array('(deleted = 0 OR statuses <> "approved")');
                $search_query = array();

                if(is_array($limit_pages) && count($limit_pages)) {
                    $where_conditions[] = '(webpage_id IN (' . implode(', ', $limit_pages) . '))';
                }

                if(count($keywords) > 0) {
                    $fields = array('webpage_id', 'path', 'webpage_title', 'headline_title', 'keywords', 'description');
                    $query_str = array_map(array($this->conn, 'escape'), $keywords);
                    foreach($fields as $field) {
                        $search_query[] = '(' . $field . ' LIKE ' . implode(' OR ' . $field . ' LIKE ', $query_str) . ')';
                    }
                }

                if(count($search_query) > 0) {
                    $where_conditions[] = '(' . implode(' OR ', $search_query) . ')';
                }

                if(!is_null($status) && isset($this->kernel->dict['SET_webpage_statuses'][$status])) {
                    //$where_conditions[] = '(status = ' . $this->conn->escape($status) . ')';
                    $where_conditions[] = '(statuses LIKE ' . $this->conn->escape('%'.$status.'%') . ')';
                }

                $sql = sprintf('SELECT DISTINCT webpage_id, major_version, minor_version, `type`, webpage_title, headline_title'
                    . ', keywords, description, paths, platforms, statuses, status_locale_infos'
                    . ' FROM( SELECT *, GROUP_CONCAT(DISTINCT platform SEPARATOR ", ") AS platforms'
                    . ', GROUP_CONCAT(DISTINCT platform_path SEPARATOR ", ") AS paths'
                    . ' FROM( SELECT p.*, `type`, wl.statuses, wl.status_locale_infos, CONCAT(p.platform, "|", p.path) AS platform_path'
                    . ', webpage_title, wl.headline_title, wl.keywords, wl.description'
                    . ' FROM webpage_platforms p JOIN '

                    // get only the latest version
                    //. '(SELECT * FROM(SELECT * FROM webpages'
                    //. " WHERE domain = 'private'"
                    //. ' ORDER BY major_version DESC'
                    //. ', minor_version DESC) tmp2 GROUP BY id)'
					. '(SELECT w.* FROM webpages w JOIN webpage_versions wv ON (wv.id=w.id AND wv.major_version=w.major_version AND wv.minor_version=w.minor_version AND w.domain=wv.domain) WHERE w.domain="private")'
					. ' tmp ON(p.domain = tmp.domain AND p.webpage_id = tmp.id AND p.major_version = tmp.major_version AND p.minor_version = tmp.minor_version)'
                    //. ' JOIN webpage_locales AS wl ON (tmp.domain = wl.domain AND tmp.id = wl.webpage_id AND tmp.major_version = wl.major_version AND tmp.minor_version = wl.minor_version)'
                    . ' JOIN (SELECT *, GROUP_CONCAT(status_locales SEPARATOR "||") AS status_locale_infos, GROUP_CONCAT(status SEPARATOR ",") AS statuses FROM ('
                    . ' SELECT *, CONCAT(status, " (", IF(char_length(status_locale)<=40, status_locale, CONCAT(SUBSTRING(status_locale, 1, 40), "...")), ")") AS status_locales FROM ('
                    //. ' SELECT wl.*, GROUP_CONCAT(name SEPARATOR ", ") AS status_locale FROM webpage_locales wl LEFT JOIN locales ON (locales.alias=wl.locale AND locales.site="public_site" AND enabled=1) WHERE domain="private" GROUP BY webpage_id, major_version, minor_version, status ORDER BY major_version DESC, minor_version DESC'
					. ' SELECT wl.*, GROUP_CONCAT(name SEPARATOR ", ") AS status_locale FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) JOIN locales ON (locales.alias=wl.locale AND locales.site="public_site" AND enabled=1) WHERE wl.domain="private" GROUP BY wl.webpage_id, wl.major_version, wl.minor_version, status'
					. ') AS wl ) AS wl GROUP BY webpage_id, major_version, minor_version ) AS wl'
                    . ' ON (tmp.domain = wl.domain AND tmp.id = wl.webpage_id AND tmp.major_version = wl.major_version AND tmp.minor_version = wl.minor_version)'
                    . ' ORDER BY platform = "desktop" DESC'
                    . ' ) tb'

                    . ' GROUP BY webpage_id) tb'
                    . ' WHERE (%s)'
                    , implode(' AND ', $where_conditions));
                $statement = $this->conn->query($sql);
                $rows = $statement->fetchAll();

                $records = array();

                $replace_patterns = array();
                $replacements = array();
                foreach($this->kernel->dict['SET_webpage_statuses'] as $key=>$status)
                {
                    $replace_patterns[] = '/'.$key.'/i';
                    $replacements[] = $status;
                }
                foreach($rows as &$row) {
                    $row['platforms_ary'] = array_map('trim', explode(',', $row['platforms'])) ;
                    $path_sets = array();
                    $paths = array_map('trim', explode(',', $row['paths']));
                    foreach($paths as $path) {
                        $tmp = explode('|', $path);
                        $path_sets[$tmp[0]] = $tmp[1];
                    }
                    $row['paths'] = $path_sets;
                    $tmp_locale_info = preg_replace($replace_patterns, $replacements, $row['status_locale_infos']);
                    $row['status_locale'] = explode("||", $tmp_locale_info);

                    unset($row);
                }

                if($ajax && !$callback) {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( array(
                                                                           'result' => 'success',
                                                                           'data' => $rows,
                                                                           'keyword' => $str,
                                                                           'status' => is_null($status) ? "" : $status
                                                                      ));
                } else {
                    return $rows;
                }
            }
        } catch(Exception $e) {
            $this->processException($e);

        }

        return false;
    }

    /**
     * List webpages.
     *
     * @since   2008-11-06
     */
    function index()
    {//echo 'Time used when call index function: '. (microtime(true)-$this->_time_start);
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);
        $site_tree = array();
        $_GET['p'] = trim(array_ifnull($_GET, 'p', ''));
        $platforms = array();
        // whether the root node exists in the platform
        $existed_platforms = array();

        $_GET['keywords'] = trim(array_ifnull($_GET, 'keywords', ''));
        $_GET['status'] = trim(array_ifnull($_GET, 'status', ''));

        $is_root = true;

        $accessible_locales = array_keys($this->user_accessible_locales);
        $escaped_accessible_locales = array();
        foreach($accessible_locales as $alias)
        {
            $escaped_accessible_locales[] = $this->kernel->db->escape($alias);
        }

        try {
            $display_search = false;
            //$accessible_webpages = webpage_admin_module::getWebpageAccessibility();
            $accessible_webpages = $this->_accessible_webpages; // Performance enhancement
            if(is_null($accessible_webpages)) {
                $_GET['id'] = intval(array_ifnull($_GET, 'id', 1));
            } else {
                $sitemap = $this->get_sitemap('edit', "desktop");
                // Performance enhancement
                $pages = null;
                $default_id = 0;
//echo 'Time used when obtain sitemap in index function: '. (microtime(true)-$this->_time_start);
                $root = $sitemap->getRoot();

                if(!is_null($root) && $root) {
                    $pages = array_merge(array($sitemap->getRoot()), $sitemap->getRoot()->getChildren());

                    /** @var pageNode $pageNode */
                    $pageNode = null;

                    if(count($accessible_webpages))
                        $default_id = $accessible_webpages[0];

                    foreach($pages as $pageNode) {
                        if(in_array($pageNode->getItem()->getId(), $accessible_webpages)) {
                            $default_id = $pageNode->getItem()->getId();
                            break;
                        }
                    }

                    $pages = null;
                }

                $_GET['id'] = intval(array_ifnull($_GET, 'id', $default_id));
            }

            // search action to perform search
            if($ajax) {
                $this->find($_GET['keywords'], $_GET['status'], false, $escaped_accessible_locales);
                return;
            } else {
                if($_GET['keywords'] || $_GET['status']) {
                    $search_results = $this->find($_GET['keywords'], $_GET['status'], false, $escaped_accessible_locales);
                    $display_search = true;
                    $this->kernel->smarty->assign('search_result_list', $search_results);
                }

                /*$sql = sprintf('SELECT COUNT(*) AS count_pages FROM'
                                    . '(SELECT * FROM (SELECT * FROM webpages WHERE domain = \'private\' ORDER BY id, major_version DESC, minor_version DESC)'
                                    . ' AS w GROUP BY w.id) AS w WHERE (w.deleted = 0 OR w.status <> "approved")');*/
                // Performance enhancement
                $sql = 'SELECT COUNT(*) AS count_pages';
                $sql .= ' FROM webpages w';
                $sql .= ' JOIN webpage_versions wv ON (w.domain = wv.domain AND w.id = wv.id AND w.major_version = wv.major_version AND w.minor_version = wv.minor_version)';
                $sql .= ' JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)';
                $sql .= " WHERE w.domain = 'private'";
                $sql .= " AND (w.deleted = 0 OR wl.status <> 'approved')";
                $statement = $this->conn->query($sql);
                extract($statement->fetch());

                $id_exists = false;
                $default_root = null;

                if($count_pages) {
                    foreach(array_keys($this->kernel->dict['SET_content_types']) as $platform) {

//echo 'Time used after draw site tree in index function: '. (microtime(true)-$this->_time_start);
                        $sitemap = $this->get_sitemap('edit', $platform);
                        $tmp_root = $sitemap->getRoot();

                        if($tmp_root) {
                            $existed_platforms[] = $platform;

                            if($tmp_root->findById($_GET['id'])) {
                                $id_exists = true;
                                if(!$_GET['p']) {
                                    $_GET['p'] = $platform;
                                }
                            }

                            if(is_null($default_root) && $tmp_root->getItem()) {
                                $default_root = $tmp_root;
                            }
                        }
                    }

                    if(!in_array($_GET['p'], $existed_platforms)) {
                        $_GET['p'] = $existed_platforms[0];
                    }

                    if(!$id_exists && !is_null($default_root)) {
                        $_GET['id'] = $default_root->getItem()->getId();
                    }

                    if($_GET['id'] != $default_root->getItem()->getId())
                        $is_root = false;

//echo 'Time used after calculate default root page id in index function: '. (microtime(true)-$this->_time_start);
                    $data = $this->getPageData($_GET['id']);
//echo 'Time used after get webpage general data(id=1) in index function: '. (microtime(true)-$this->_time_start);
                    if($data) {
                        $data['locale'] = $this->kernel->request['locale'];
                        $data['pages_to_approve'] = 0;

                        // Get rights of current webpage
                        $sql = sprintf('SELECT `right` FROM role_webpage_rights rwr LEFT JOIN users u ON (u.role_id=rwr.role_id) WHERE u.id=%d AND rwr.webpage_id=%d', $this->user->getId(), $_GET['id']);
                        $current_webpage_rights = $this->kernel->get_set_from_db($sql);

                        // get it and its decedent webpages to see if any of it need approval
                        $webpages_to_approval = array();
                        $sql = sprintf('SELECT DISTINCT tb.id AS possible_to_approve FROM( SELECT * FROM('
                                        //. 'SELECT * FROM webpages WHERE domain = \'private\' ORDER BY id, major_version DESC, minor_version DESC'
                                        . 'SELECT wl.*, w.id, deleted FROM webpage_locales wl JOIN webpage_versions wv ON (wv.domain=wl.domain AND wv.id=wl.webpage_id AND wv.major_version=wl.major_version AND wv.minor_version = wl.minor_version) JOIN webpages w ON(w.id=wl.webpage_id AND wl.major_version=w.major_version AND wl.minor_version=w.minor_version) WHERE wl.domain = \'private\' %4$s AND (wl.status = "pending" OR wl.status="draft") ORDER BY wl.webpage_id, wl.major_version DESC, wl.minor_version, FIELD("wl.status", "pending", "draft")'
                                        . ') AS tb GROUP BY id'
                                        . ') AS tb '
                                        . ' JOIN webpage_platforms p ON(p.domain = tb.domain AND p.webpage_id = tb.id'
                                        . ' AND p.major_version = tb.major_version AND p.minor_version = tb.minor_version)'
                                        . ' WHERE (tb.deleted <> 1 OR tb.status <> "approved")'
                                        . ' AND EXISTS('
                                            . 'SELECT * FROM webpage_platforms p2 WHERE p2.domain = \'private\' AND p2.webpage_id = %1$d '
                                            . ' AND p2.major_version = %2$d AND p2.minor_version = %3$d'
                                            . ' AND p2.platform = p.platform AND INSTR(p.path, p2.path) = 1'
                                        . ') AND (status = "pending" OR status="draft")'
                                        , $data['id'], $data['major_version'], $data['minor_version']
                                        , count($escaped_accessible_locales)==0 ? '' : 'AND locale IN ('.implode(',', $escaped_accessible_locales).')');
                        $possible_to_approval = $this->kernel->get_set_from_db($sql);

                        if(count($possible_to_approval) > 0)
                        {

                            $sql = sprintf('SELECT COUNT(*) AS count_to_approve FROM role_webpage_rights WHERE `right`=%d AND webpage_id IN(%s) AND role_id=%d', Right::APPROVE, implode(',', $possible_to_approval), $this->user->getRole()->getId());
                            $statement = $this->conn->query($sql);
                            if($record = $statement->fetch())
                            {
                                $data['pages_to_approve'] = $record['count_to_approve'];

                                $pages_id_to_approve = array();
                                $sql = sprintf('SELECT DISTINCT webpage_id FROM role_webpage_rights WHERE `right`=%d AND webpage_id IN(%s) AND role_id=%d', Right::APPROVE, implode(',', $possible_to_approval), $this->user->getRole()->getId());
                                $pages_id_to_approve = $this->kernel->get_set_from_db($sql);

                                if(count($escaped_accessible_locales)>0 && count($pages_id_to_approve)>0)
                                {
                                    foreach($escaped_accessible_locales as $escaped_locale)
                                    {
                                        $sql = 'SELECT locale, path, wl.webpage_id, webpage_title FROM webpage_versions wv';
                                        $sql .= ' JOIN webpage_locales wl ON (wv.domain=wl.domain AND wv.id=wl.webpage_id AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version)';
                                        $sql .= ' LEFT JOIN webpage_platforms wp ON (wl.domain=wv.domain AND wl.webpage_id=wp.webpage_id AND wl.major_version=wp.major_version AND wl.minor_version=wp.minor_version)';
                                        $sql .= ' WHERE wv.domain=\'private\' AND wv.id IN ('.implode(',', $pages_id_to_approve).')';
                                        $sql .= ' AND (status = "pending" OR status="draft")';
                                        $sql .= ' AND locale='.$escaped_locale.' ORDER BY path';
                                        $statement = $this->conn->query($sql);
                                        while($record = $statement->fetch()) {
                                            $webpages_to_approval[$record['locale']][$record['webpage_id']] = $record;
                                        }
                                    }
                                }
                            }
                        }

//echo 'Time used after count webpages to approve in index function: '. (microtime(true)-$this->_time_start);
                        // approve self
                        //if($data['status'] == 'draft')
                            //$data['pages_to_approve']++;

                        foreach(array_keys($data['platforms']) as $platform) {
                            $platforms[] = $this->kernel->dict['SET_webpage_page_types'][$platform];
                        }

						$platform = array_keys($data['platforms']);
                        $platform = array_shift($platform);

                        // preview
                        $data['token'] = array();
                        $sql = sprintf('SELECT t.token, t.initial_id, t.creator_id, t.grant_role_id, '
                                        . ' u.first_name AS creator_name, u.email AS creator_email, '
                                        . " CONVERT_TZ(t.created_date, 'GMT',"
                                        . " {$this->kernel->conf['escaped_timezone']}) AS created_date,"
                                        . " CONVERT_TZ(t.expire_time, 'GMT',"
                                        . " {$this->kernel->conf['escaped_timezone']}) AS expire_time,"
                                        . " CONVERT_TZ(t.last_access, 'GMT',"
                                        . " {$this->kernel->conf['escaped_timezone']}) AS last_access"
                                        . ' FROM webpage_preview_tokens t '
                                        . ' JOIN users u ON(t.creator_id = u.id)'
                                        . ' WHERE t.type = "webpage" AND t.initial_id = %d'
                                        . ' AND t.expire_time > UTC_TIMESTAMP()'
                                        . ' ORDER BY t.expire_time DESC LIMIT 0, 1', $data['id']);
                        $statement = $this->conn->query($sql);
                        if($data['token'] = $statement->fetch()) {
                            // get the default language for preview link
                            $sql = sprintf('SELECT locale FROM webpage_locales WHERE domain = \'private\' AND webpage_id = %2$d'
                                            . ' AND major_version = %3$d AND minor_version = %4$d'
                                            . ' ORDER BY locale = %5$s DESC LIMIT 0, 1'
                                            , $data['type'], $data['id']
                                            , $data['major_version'], $data['minor_version']
                                            //, $this->conn->escape($this->kernel->request['locale'])
                                            , $this->conn->escape($this->kernel->default_public_locale) // default locale of public site is not always same as that of admin site
                            );
                            $statement = $this->conn->query($sql);
                            if($tmp = $statement->fetch()) {
                                $data['locale'] = $tmp['locale'];
                            }

                            $data['token']['encoded_code'] = $this->encodePvToken($data['token']['token']);
                        }

                        // BreadCrumb
                        $this->_breadcrumb->push(new breadcrumbNode(sprintf('%1$s - [#%2$d]'
                            , $data['platforms'][$platform]['path'], $data['id']), $this->kernel->sets['paths']['mod_from_doc'] . '/?id=' . $_GET['id'])
                        );
//echo 'Time used after calc preview link token(id=1) in index function: '. (microtime(true)-$this->_time_start);
                        //$session_cleared = $this->check_page_session_timer($data['id']);
                        $data['editable'] = $this->is_editable( $data['id'] );
                        $data['locked'] = $this->is_locked( $data['id'] );

                        /*
                        $data['shown_in_site'] = $data['shown_in_site'] ? sprintf('%1$s%2$s', $this->kernel->dict['LABEL_yes']
                            , $data['shown_in_site_start_date'] ? " (" . sprintf($this->kernel->dict['DESCRIPTION_datetime_period_start']
                            , $data['shown_in_site_start_date']) . ")" : "")
                            , $shown_in_site_period)
                            : $this->kernel->dict['LABEL_no'];
                        */
                        $data['shown_in_site'] = $data['shown_in_site'] ? $this->kernel->dict['LABEL_yes'] : $this->kernel->dict['LABEL_no'];
                        $data['deleted'] = $data['deleted'] ? $this->kernel->dict['LABEL_yes'] : $this->kernel->dict['LABEL_no'];

                        $platform = array_keys($data['platforms']);
						$platform = array_shift($platform);

                        // Handle locking
                        $archive_edit_actions = array(
                            $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'] . '/preview'
                            . $data['platforms'][$platform]['path'] . '?m=' . ($platform == "mobile" ? 1 : 0) . '&v=' => $this->kernel->dict['ACTION_view']
                        );
                        $archive_view_actions = array();
                        if ( $data['locked'] )
                        {
                            $sql = sprintf('SELECT u.*, CONVERT_TZ(last_active_timestamp, "GMT",'.$this->kernel->conf['escaped_timezone'].') AS last_active_time FROM webpage_locks l JOIN users u ON(l.locker_id = u.id)'
                            . ' WHERE webpage_id = %d ORDER BY last_active_timestamp DESC LIMIT 0,1', $data['id']);
                            $statement = $this->conn->query($sql);
                            if($locker_info = $statement->fetch()) {
                                $this->kernel->dict['DESCRIPTION_edit_locked'] = sprintf(
                                    $this->kernel->dict['DESCRIPTION_edit_locked'],
                                    "{$locker_info['first_name']} <{$locker_info['email']}>",
                                    date('Y/m/d H:i:s', strtotime($locker_info['last_active_time'])+intval($this->kernel->conf['page_session_timer']))
                                );
                            }
                        }
                        else
                        {
                            //$archive_edit_actions["?op=edit&id={$data['id']}&v="] = $this->kernel->dict['ACTION_edit'];
                            $archive_view_actions["?op=edit&id={$data['id']}&l="] = $this->kernel->dict['ACTION_view_archive'];
                        }

						$this->kernel->smarty->assign('accessible_locales', $accessible_locales);
//echo 'Time used after check lock status (id=1) in index function: '. (microtime(true)-$this->_time_start);
                        // Get the status-locale list of current version
                        $data['current_version_status'] = $this->kernel->get_smarty_list_from_db(
                            'webpage_locale_status_list',
                            'locale',
                            array(
                                 'select' => "locale, l.name AS language,"
                                 . $this->kernel->db->translateField('wl.status', $this->kernel->dict['SET_webpage_statuses'], 'status').','
                                 . " CONVERT_TZ(IFNULL(wl.updated_date, w.created_date), 'GMT',"
                                 . " {$this->kernel->conf['escaped_timezone']}) AS datetime,"
                                 . " CONVERT_TZ(wl.publish_date, 'GMT', {$this->kernel->conf['escaped_timezone']}) AS publish_date,"
                                 . " CONVERT_TZ(wl.removal_date, 'GMT', {$this->kernel->conf['escaped_timezone']}) AS removal_date,"
                                 . " CONCAT(IFNULL(updaters.first_name, creators.first_name), ' <', IFNULL(updaters.email, creators.email), '>') AS user, CONCAT(wl.visual_version, '.', wl.minor_version) AS version",
                                 'from' => 'webpage_locales AS wl'
                                 . " JOIN webpages AS w ON(wl.domain=w.domain AND wl.webpage_id=w.id AND wl.major_version=w.major_version AND wl.minor_version=w.minor_version)"
                                 . ' JOIN users AS creators ON(w.creator_id = creators.id)'
                                 . ' JOIN locales AS l ON (l.alias=wl.locale AND l.site=\'public_site\' AND l.enabled=1)'
                                 . ' LEFT OUTER JOIN users AS updaters ON(wl.updater_id = updaters.id)',
                                 'where' => "wl.domain = 'private' AND wl.webpage_id = {$data['id']}"
                                            . " AND wl.major_version = {$data['major_version']} AND wl.minor_version = {$data['minor_version']}",
                                 'group_by' => '',
                                 'having' => '',
                                 'default_order_by' => 'datetime',
                                 'default_order_dir' => 'DESC'
                            ),
                            array(),
                            $archive_view_actions,
                            array(),
                            '/current-version-status',
                            'module/webpage_admin/current_version_list.html',
                            array()
                        );
//echo 'Time used after get status-locale list(id=1) in index function: '. (microtime(true)-$this->_time_start);
                        // Get the requested webpage comments
                        $data['comments'] = $this->kernel->get_smarty_list_from_db(
                            'webpage_comment_list',
                            'comment_id',
                            array(
                                 'select' => 'comments.content, '
                                 . $this->kernel->db->translateField('comments.locale', $this->kernel->sets['locales'], 'locale').','
                                 . 'comments.created_date,'
                                 . " CONVERT_TZ(comments.created_date, 'GMT',"
                                 . " {$this->kernel->conf['escaped_timezone']}) AS created_date,"
                                 . ' creators.first_name AS creator_user_name, creators.email AS email',
                                 'from' => 'webpage_comments AS comments'
                                 . ' JOIN users AS creators ON comments.creator_id = creators.id',
                                 'where' => "comments.webpage_id = {$_GET['id']}",
                                 'group_by' => '',
                                 'having' => '',
                                 'default_order_by' => 'created_date',
                                 'default_order_dir' => 'DESC'
                            ),
                            array(),
                            array(),
                            array(),
                            'module/webpage_admin/index_comment_list.html'
                        );
//echo 'Time used after get requested webpage comments list(id=1) in index function: '. (microtime(true)-$this->_time_start);
                        // Get the requested webpage archives
                        /*$data['archives'] = $this->kernel->get_smarty_list_from_db(
                            'webpage_archive_list',
                            'archive_id',
                            array(
                                 'select' => "CONCAT(w.major_version, '.', w.minor_version) AS archive_id,"
                                 . " CONCAT(w.major_version, '.', w.minor_version) AS version,"
                                 . " CONVERT_TZ(IFNULL(wl.updated_date, w.created_date), 'GMT',"
                                 . " {$this->kernel->conf['escaped_timezone']}) AS datetime,"
                                 . " CONCAT(IFNULL(updaters.first_name, creators.first_name), ' <', IFNULL(updaters.email, creators.email), '>') AS user",
                                 'from' => 'webpages AS w'
                                 . " JOIN (SELECT * FROM (SELECT * FROM (SELECT * FROM webpage_locales WHERE domain='private' AND webpage_id={$data['id']} ORDER BY major_version DESC, minor_version DESC, updated_date DESC) AS wl GROUP BY webpage_id, major_version, minor_version) AS wl) AS wl ON(wl.domain=w.domain AND wl.webpage_id=w.id AND wl.major_version=w.major_version AND wl.minor_version=w.minor_version)"
                                 . ' JOIN users AS creators ON(w.creator_id = creators.id)'
                                 . ' LEFT OUTER JOIN users AS updaters ON(wl.updater_id = updaters.id)',
                                 'where' => "w.domain = 'private' AND w.id = {$data['id']}"
                                            . " AND NOT(w.major_version = {$data['major_version']} AND w.minor_version = {$data['minor_version']})",
                                 'group_by' => '',
                                 'having' => '',
                                 'default_order_by' => 'datetime',
                                 'default_order_dir' => 'DESC'
                            ),
                            array(),
                            $archive_edit_actions,
                            array(),
                            '/webpage-versions',
                            'list.html',
                            array()
                        );*/

                        $info = array(
                            'created_date_message' => sprintf($this->kernel->dict['INFO_created_date'], '<b>' . $data['created_date'] . '</b>', '<b>' . $data['creator_name'] . '</b>', '<b>' . $data['creator_email'] . '</b>')
                        );

                        if($data['updated_date']) {
                            $info['last_update_message'] = sprintf($this->kernel->dict['INFO_last_update'], '<b>' . $data['updated_date'] . '</b>', '<b>' . $data['updater_name'] . '</b>', '<b>' . $data['updater_email'] . '</b>');
                        }

                        $this->kernel->smarty->assign('info', $info);
                    }

                    $webpage_status_options = array_merge(
                        array(
                             $this->kernel->dict['LABEL_all_status']
                        )
                        , $this->kernel->dict['SET_webpage_statuses']
                    );

					foreach(array_keys($this->kernel->dict['SET_content_types']) as $platform) {
						$site_tree[$platform] = webpage_admin_module::getWebpageNodes('html', $platform, $_GET['id'], false, $this->_accessible_webpages); //Performance enhancement
					}
                    $this->kernel->smarty->assign('webpage_status_options', $webpage_status_options);
                    $this->kernel->smarty->assign('site_tree', $site_tree);
                    $this->kernel->smarty->assign('webpage', $data);
                    $this->kernel->smarty->assign('display_types', implode(', ', $platforms));
                    $this->kernel->smarty->assign('display_search', $display_search);
                    $this->kernel->smarty->assign('existed_platforms', $existed_platforms);
                    $this->kernel->smarty->assign('is_root', $is_root);
                    $this->kernel->smarty->assign('current_webpage_rights', $current_webpage_rights);
                    $this->kernel->smarty->assign('webpages_to_approval', $webpages_to_approval);
                    $this->kernel->smarty->assign('locale_set', $this->user_accessible_locales);
                    $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/webpage_admin/index.html' );
                    //echo 'Time used when backend logic done: '. (microtime(true)-$this->_time_start);exit;
                } else {
                    $this->kernel->redirect($this->kernel->sets['paths']['mod_from_doc'] . "?" .
                                    http_build_query(array(
                                          'op' => 'edit'
                                     )));
                }
            }
        } catch(Exception $e) {
            $this->processException($e);
            return FALSE;
        }

    }

    /**
     * Lock a webpage based on webpage ID.
     *
     * @since   2009-06-24
     */
    function lock($id)
    {
        // Replace lock
        if ( $id > 0 && !$this->is_locked($id) )
        {

            if(!isset($this->session['PAGE_ACTIVE_TIMER']))
                $this->session['PAGE_ACTIVE_TIMER'] = array();

            $this->session['PAGE_ACTIVE_TIMER'][$id] = time();

            $sql = 'REPLACE INTO webpage_locks';
            $sql .= '(webpage_id, locker_id, last_active_timestamp)';
            $sql .= " VALUES($id, {$this->user->getId()}, " . $this->kernel->db->escape(gmdate('Y-m-d H:i:s', time())) . ")";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $error_msg = array_pop($this->kernel->db->errorInfo());
                $this->kernel->db->rollback();
                $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
            }
        }
    }

    /**
     * Unlock a webpage based on webpage ID.
     *
     * @since   2009-06-24
     */
    function unlock($id = null, $locker_id = null, $force = false)
    {
        //$this->kernel->log( 'message', "unlocking" . $id, __FILE__, __LINE__ );

        // Get data from query string
        $webpage_id = (is_null($id) || intval($id) <= 0 ? intval( array_ifnull($_GET, 'id', 0) ) : intval($id));
        $locker_id = ((is_null($id) || intval($id) <= 0 || is_null($locker_id) || intval($locker_id) <= 0) ? $this->user->getId() : intval($locker_id));
        $temp_folder = str_replace( '..', '', trim(array_ifnull($_GET, 'temp_folder', '')));

        // Delete lock
        if ( $webpage_id > 0 && ($locker_id == $this->user->getId() || $force) )
        {
            $sql = 'DELETE FROM webpage_locks';
            $sql .= " WHERE webpage_id = $webpage_id";
            $sql .= " AND locker_id = {$locker_id}";
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $error_msg = array_pop($this->kernel->db->errorInfo());
                $this->kernel->db->rollback();
                $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
            }

            // clear all files in that directory
            $temp_dir = "webpage/page/temp/{$temp_folder}/";
            if(preg_match("#^" . $this->user->getId() . "_#i", $temp_folder))
            {
                if($this->kernel->conf['aws_enabled']) {
                    s3_deleteDir($temp_dir);
                } else if (is_dir($temp_dir)) {
                    $this->empty_folder($temp_dir);
                }

                //if($error['Error'] != '')
                    //$this->kernel->log( 'Error', "Error happens when unlock webpage" . $id.". Error Msg: ".$error['Error'], __FILE__, __LINE__ );
            }

            //$this->kernel->log( 'message', "unlocked" . $id, __FILE__, __LINE__ );

            unset($this->session['PAGE_ACTIVE_TIMER'][$webpage_id]);
        }
    }

    /**
     * Edit a webpage
     *
     * @since   2013-07-19
     */
    function edit() {
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);
        $id = intval(array_ifnull($_REQUEST, 'id', 0));
        $version = floatval(array_ifnull($_REQUEST, 'v', 0));
        $roll_back_locale = trim(array_ifnull($_REQUEST, 'roll_back_locale', 0));
        $is_submit = (bool)array_ifnull($_REQUEST, 'submitForm', 0);
        $is_duplicate_default_content = (bool)array_ifnull($_REQUEST, 'duplicate_content', 0);
        $dup = intval(array_ifnull($_REQUEST, 'dup', 0));
        $duplication = false;
        $preview = false;

        $page_folder = str_replace( '..', '', trim(array_ifnull($_POST, 'temp_folder', '')));

        $is_dir = $this->kernel->conf['aws_enabled'] ? 's3_is_dir' : 'is_dir';
        $mkdir = $this->kernel->conf['aws_enabled'] ? 's3_mkdir' : 'force_mkdir';
        $rename = $this->kernel->conf['aws_enabled'] ? 's3_rename' : 'rename';

        $mkdir('webpage/page/');
        //$mkdir('webpage/page/private/');
        $mkdir('webpage/page/public/');
        $mkdir('webpage/page/archive/');
        $mkdir('webpage/page/temp/');

        // it is duplication only if not editing from an id
        if(!$id && $dup) {
            if(!count($_POST)) {
                $id = $dup;
            }
            $duplication = true;
        }

        $data = array(
            'webpage' => array()
        );
        $webpage_data = array();
        $page_type = trim(array_ifnull($_POST, 'webpage_type', 'static'));
        $structured_page_template = intval(array_ifnull($_POST, 'structured_page_template', 1));
        if($page_type != 'structured_page')
            $structured_page_template = 0;

        $errors = array();
        $selected_offers = array();

        if($this->user_default_locale_read_only) // append default_public_locale to user_accessible_locales and user_accessible_region_locales list, but only assign read only rights to related content
        {
            $sql = 'SELECT region_alias, alias, name FROM locales WHERE alias='.$this->kernel->db->escape($this->kernel->default_public_locale).' LIMIT 0,1';
            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
                $this->user_accessible_locales[$row['alias']] = $row['name'];
                $this->user_accessible_region_locales[$row['region_alias']][$row['alias']] = $row['name'].' '.$this->kernel->dict['LABEL_view_only'];
            }
        }

        foreach($this->kernel->dict['SET_regions'] as $region_alias=>$region_name)
        {
            if(!isset($this->user_accessible_region_locales[$region_alias]) || count($this->user_accessible_region_locales[$region_alias]) == 0)
                unset($this->kernel->dict['SET_regions'][$region_alias]);
        }

        $accessible_locale_alias = array_keys($this->user_accessible_locales);

        try {
            // check whether there is a root page already: latest version that not (deleted=1 AND status of all locales='approved')
            $sql = sprintf('SELECT COUNT(*) AS count_pages FROM'
                //. '(SELECT * FROM (SELECT * FROM webpages WHERE domain = \'private\' ORDER BY id, major_version DESC, minor_version DESC) AS w GROUP BY w.id)'

				. '(SELECT * FROM (SELECT w.* FROM webpages w JOIN webpage_versions wv ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version)) AS w GROUP BY w.id)'

				. ' AS w LEFT JOIN (SELECT * FROM(SELECT * FROM '
				//. '(SELECT * FROM (SELECT * FROM webpage_locales WHERE domain= \'private\' ORDER BY webpage_id, locale, major_version DESC, minor_version DESC) AS wl GROUP BY webpage_id, locale)'

				. '(SELECT * FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.webpage_id=wv.id AND wl.domain=wv.domain AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version)) AS wl GROUP BY wl.webpage_id, locale)'

				. ' AS wl ORDER BY CASE WHEN wl.status <> \'approved\' THEN 1 ELSE 2 END) AS wl GROUP BY webpage_id) AS wl ON (wl.webpage_id=w.id AND wl.major_version=w.major_version AND wl.minor_version=w.minor_version) WHERE (w.deleted = 0 OR wl.status <> "approved")');
            $statement = $this->conn->query($sql);
            extract( $statement->fetch() );
            $root_page = $count_pages == 0;

            // see if the webpage really exists
            $sql = sprintf('SELECT w.id, w.major_version, w.minor_version, w.`type`, w.structured_page_template'
                            . ', w.deleted'
                            . ', CONVERT_TZ(w.created_date, "GMT", %2$s) AS created_date, w.creator_id'
                            . ', creator.first_name AS creator_name, creator.email AS creator_email'
                            . ', offer_source'
                            . ' FROM webpages w'
                            . ' LEFT JOIN users creator ON(w.creator_id = creator.id)'
                            . ' WHERE w.domain = \'private\' AND w.id = %1$d'
                            , $id, $this->kernel->conf['escaped_timezone']
            );

            // Always get the latest version; $version & $roll_back_locale will be used for overwrite the specific locale data
            ///if($version > 0) {
               // $sql .= sprintf(" AND major_version = %d AND minor_version = %d"
                 //   , floor($version), intval(strpos($version, '.') ? substr($version, strpos($version, '.')+1) : 0));
            //} else {
                $sql .= sprintf(" ORDER BY major_version DESC, minor_version DESC");
            //}

            $sql .= " LIMIT 0, 1";
            $statement = $this->conn->query($sql);

            if($webpage_data = $statement->fetch())
            {
                foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $p)
                {
                    $sm = $this->get_sitemap('edit', $p);
                    // try to get the root page and see if this platform exists
                    $root = $sm->getRoot();
                    if($root)
                        break;
                }

                if(isset($webpage_data['type']) && !count($_POST))
                {
                    $page_type = $webpage_data['type'];
                }
                if(isset($webpage_data['structured_page_template']) && !count($_POST))
                {
                    $structured_page_template = $webpage_data['structured_page_template'];
                }
                if($root->getItem()->getId() == $webpage_data['id'] && !$duplication) {
                    $root_page = true;
                }

                // get public access
                $sql = sprintf('SELECT p.role_id FROM webpage_permissions p JOIN roles r ON(r.id = p.role_id AND r.type = "public")'
                                . ' WHERE p.domain = \'private\' AND p.webpage_id = %d AND p.major_version = %d AND p.minor_version = %d'
                                , $webpage_data['id'], $webpage_data['major_version'], $webpage_data['minor_version']);
                $webpage_data['accessible_public_roles'] = $this->kernel->get_set_from_db($sql);
            } else {
                $id = 0; //treated as new
                // anonymous
                $webpage_data = array( 'accessible_public_roles' => array(1) );
            }

            if(count($_POST) > 0)
            {
                $_POST = array_map_recursive('trim', $_POST);

                // preview ?
                // check to see if the desire action is preview
                $preview = (bool)array_ifnull($_POST, 'preview', 0);

                if($preview) {
                    $_REQUEST['ajax'] = $ajax = true;

                    $_POST['status'] = 'draft';
                }

                $_POST['accessible_public_roles'] = array_ifnull($_POST, 'accessible_public_roles', '');
                if(!is_array($_POST['accessible_public_roles'])) {
                    $_POST['accessible_public_roles'] = array($_POST['accessible_public_roles']);
                }
                $_POST['accessible_public_roles'] = array_unique(array_map('intval', array_ifnull($_POST, 'accessible_public_roles', array())));
                $webpage_data['accessible_public_roles'] = $_POST['accessible_public_roles'];
            }

            if(count($_POST) > 0 && $is_submit && !$is_duplicate_default_content)
            {
                $platforms = array();
                $relative_urls = array();
                $locales = array_keys($this->kernel->sets['public_locales']);

                $_POST['platforms'] = array_map('trim', array_ifnull($_POST, 'platforms', array()));
                $_POST['status'] = trim(array_ifnull($_POST, 'status', ''));
                $_POST['locales_to_save'] = trim(array_ifnull($_POST, 'save_locale_str', array()));
                $locales_to_save = explode(',', $_POST['locales_to_save']);
                foreach($_POST['platforms'] as $type) {
                    //$type = strtoupper($type);
                    $type = strtolower($type);
                    //if(defined('platforms::'.$type)){
                    if(isset($this->kernel->dict['SET_webpage_page_types'][$type])) {
                        $platform = $type;
                        $platforms[] = $platform;
                        $relative_urls[$platform] = '/';
                    }
                    //}
                }

                // Copy default title to regional locales if the title of the locale is blank and the user is a global user
                /*
                if($this->user->isGlobalUser())
                {
                    $default_lan = '';
                    $sql = 'SELECT alias FROM locales WHERE `default`=1 AND site=\'public_site\' LIMIT 0,1';
                    $statement = $this->conn->query( $sql );
                    extract( $statement->fetch() );
                    $default_lan = $alias;

                    if($_POST['webpage_title'][$default_lan] != '')
                    {
                        foreach($locales as $locale) {
                            if($_POST['webpage_title'][$locale] == '') {
                                $_POST['webpage_title'][$locale] = $_POST['webpage_title'][$default_lan];
                            }
                        }
                    }

                }
                */


                $this->conn->beginTransaction();

                // start error checking
                $errors = $this->errorChecking($_POST, $platforms, $page_type, $id, $root_page, array(), $this->user->isGlobalUser(), $locales_to_save);

                if($id) {
                    $sql = sprintf('SELECT * FROM webpage_locks WHERE locker_id = %d AND webpage_id = %d', $this->user->getId(), $id);
                    $statement = $this->conn->query($sql);
                    if(!$statement->fetch()) {
                        $errors['errorsStack'][] = 'page_not_locked';
                    }
                }

                // extra error checking
                if (count($platforms) > 0) {
                    if (in_array($page_type, array("static", "url_link", "structured_page"))) {
                        if ($page_folder == '' || !$is_dir("webpage/page/temp/" . $page_folder . "/"))
                        {
                            $errors['errorsStack'][] = sprintf($this->kernel->dict['ERROR_no_page_folder'], "webpage/page/temp/" . $page_folder . "/");
                        }
                    }
                }
                if(count($locales_to_save)>0 && $id>0)
                {
                    $no_title_locales = array();
                    foreach($locales_to_save as $alias)
                    {
                        if(isset($_POST['webpage_title'][$alias]))
                        {
                            if($_POST['webpage_title'][$alias]=='')
                                $no_title_locales[] = $this->conn->escape($alias);
                        }
                        else
                            $no_title_locales[] = $this->conn->escape($alias);
                    }
                    if(count($no_title_locales)>0)
                    {
                        $sql = 'SELECT IFNULL(COUNT(*), 0) as num';
                        $sql .= ' FROM webpage_versions wv';
                        $sql .= ' JOIN webpage_locales wl ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version)';
                        $sql .= " JOIN locales AS l ON (wl.locale = l.alias AND l.site = 'public_site' AND l.enabled = 1)";
                        $sql .= " WHERE wv.domain = 'private' AND wv.id = $id AND wl.locale NOT IN (" . implode(',', $no_title_locales).')';
                        $statement = $this->conn->query($sql);
                        extract($statement->fetch());
                        if($num==0)
                            $errors['errorsStack'][] = 'no_locale_specific';
                    }
                }
                else if(count($locales_to_save)==0 && $id==0)
                {
                    $errors['errorsStack'][] = 'no_locale_specific';
                }

                if(!$root_page) {
                    foreach($relative_urls as $platform => $url) {
                        // get the path of parent webpage
                        /*
                        $sql = sprintf('SELECT * FROM (SELECT wp.* FROM webpages p JOIN webpage_platforms wp'
                            . ' ON(p.domain = wp.domain AND p.id = wp.webpage_id AND p.major_version = wp.major_version AND p.minor_version = wp.minor_version)'
							. ' JOIN webpage_versions wv ON(p.id=wv.id AND p.domain=wv.domain AND p.major_version=wv.major_version AND p.minor_version=wv.minor_version)'
                            . ' WHERE p.domain = \'private\' AND wp.webpage_id = %1$d AND wp.platform = %2$s'
                            //. ' AND (p.status NOT IN("rejected") OR (p.status = "rejected" AND IFNULL(p.updater_id, p.creator_id) = %3$d))' //status of "rejected" was deprecated
                            //. ' ORDER BY wp.major_version DESC, wp.minor_version DESC) tb '
                            . ' ) tb '
                            . ' GROUP BY webpage_id'
                            , isset($_POST['webpage_parent_id'][$platform]) ? $_POST['webpage_parent_id'][$platform] : 0
                            , $this->conn->escape($platform)
                            , $this->user->getId());
                        */
                        $sql = sprintf('SELECT SUBSTRING_INDEX(GROUP_CONCAT(wp.path ORDER BY wp.major_version DESC, wp.minor_version DESC), \',\', 1) AS path FROM webpages AS w'
                            . ' JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version)'
                            . ' WHERE w.domain = \'private\' AND wp.webpage_id = %1$d AND wp.platform = %2$s'
                            . ' GROUP BY w.id'
                            , isset($_POST['webpage_parent_id'][$platform]) ? $_POST['webpage_parent_id'][$platform] : 0
                            , $this->conn->escape($platform));
                        $statement = $this->conn->query($sql);
                        if($record = $statement->fetch()) {
                            $relative_urls[$platform] = $record['path'];
                        }
                        if(isset($_POST['alias'][$platform]) && !isset($errors["alias[{$platform}]"])) {
                            //if(!preg_match("#^[0-9a-zA-Z\-\_\s\.]+$#", $_POST['alias'][$platform])) {
                            if(!preg_match("#^[0-9a-z\-]+$#", $_POST['alias'][$platform])) {
                                $errors["alias[{$platform}]"][] = 'invalid_alias';
                            } else {
                                $relative_urls[$platform] .= $_POST['alias'][$platform] . '/';

                                // active private pages with same path (archive pages not included)
                                /*
                                $sql = sprintf('SELECT tb.* FROM (SELECT wp.*, wl.status FROM webpage_platforms wp'
                                    //. ' JOIN webpages w ON(wp.domain = w.domain AND wp.webpage_id = w.id AND w.major_version = wp.major_version'
                                    //. ' AND w.minor_version = wp.minor_version)'
                                    . ' JOIN (SELECT * FROM(SELECT * FROM ('
									//. 'SELECT * FROM (SELECT * FROM webpage_locales WHERE domain= \'private\' ORDER BY webpage_id, locale, major_version DESC, minor_version DESC ) AS wl GROUP BY webpage_id, locale'

									. ' SELECT * FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) WHERE wl.domain="private") AS wl GROUP BY webpage_id, locale'

									. ') AS wl ORDER BY CASE WHEN wl.status <> \'approved\' THEN 1 ELSE 2 END) AS wl GROUP BY webpage_id) AS wl ON (wl.webpage_id=wp.webpage_id AND wl.major_version=wp.major_version AND wl.minor_version=wp.minor_version)'
                                    . ' LEFT JOIN webpages w ON (w.id=wl.webpage_id AND w.major_version=wl.major_version AND w.minor_version=wl.minor_version)'
                                    . ' WHERE wp.domain = \'private\' AND wp.webpage_id <> %1$d AND wp.platform = %2$s'
                                    //. ' AND (w.status NOT IN("rejected") OR (w.status = "rejected" AND IFNULL(w.updater_id, w.creator_id) = %4$d))'
                                    . ' AND NOT(wl.status = "approved" AND w.deleted = 1)'
                                    . ' ORDER BY major_version DESC, minor_version DESC) tb '
                                    . ' GROUP BY tb.webpage_id'
                                    . ' HAVING tb.path = %3$s AND (tb.deleted = 0 OR tb.status <> "approved")'
                                    , $id
                                    , $this->conn->escape($platform)
                                    , $this->conn->escape($relative_urls[$platform])
                                    , $this->user->getId());
                                $statement = $this->conn->query($sql);
                                if($statement->fetch()) {
                                    $errors["alias[{$platform}]"][] = 'alias_collide';
                                }
                                else{
                                    $sql = 'SELECT COUNT(*) AS collide FROM webpages AS w';
                                    $sql .= ' JOIN webpage_versions AS wv ON (w.domain = wv.domain AND w.id = wv.id AND w.major_version = wv.major_version AND w.minor_version = wv.minor_version)';
                                    $sql .= ' JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version AND wp.platform = %s AND wp.deleted = 0)';
                                    $sql .= ' LEFT OUTER JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)';
                                    $sql .= " WHERE w.domain = 'private' AND w.deleted = 0 AND wl.webpage_id IS NULL";
                                    $sql .= ' AND w.id <> %d AND wp.path = %s';
                                    $sql = sprintf(
                                        $sql,
                                        $this->conn->escape( $platform ),
                                        $id,
                                        $this->conn->escape( $relative_urls[$platform] )
                                    );
                                    $statement = $this->kernel->db->query( $sql );
                                    extract($statement->fetch());
                                    if($collide) {
                                        $errors["alias[{$platform}]"][] = 'alias_collide';
                                    }
                                }
                                */
                                $sql = 'SELECT * FROM webpages AS w';
                                $sql .= ' JOIN webpage_versions AS wv ON (w.domain = wv.domain AND w.id = wv.id AND w.major_version = wv.major_version AND w.minor_version = wv.minor_version)';
                                $sql .= ' JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id';
                                $sql .= ' AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version AND wp.platform = %s AND wp.deleted = 0)';
                                $sql .= " WHERE w.domain = 'private' AND w.deleted = 0 AND w.id <> %d GROUP BY w.domain, w.id";
                                $sql .= " HAVING SUBSTRING_INDEX(GROUP_CONCAT(wp.path ORDER BY w.major_version DESC, w.minor_version DESC SEPARATOR '\r\n'), '\r\n', 1) = %s";
                                $sql = sprintf(
                                    $sql,
                                    $this->conn->escape( $platform ),
                                    $id,
                                    $this->conn->escape( $relative_urls[$platform] )
                                );
                                $statement = $this->kernel->db->query( $sql );
                                if($record = $statement->fetch()) {
                                    $errors["alias[{$platform}]"][] = 'alias_collide';
                                }
                            }
                        }
                    }
                }

                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                } else {
                    $is_new = $id == 0;

                    $cls = preg_replace("#\s#", "", ucwords(preg_replace("#_#i", " ", $page_type))) . 'Page';
                    /** @var staticPage | webpageLinkPage | urlLinkPage $page */
                    $page = new $cls();

                    // tmp data
                    $tmp_data = array_merge($_POST, array(
                                                         //'status' => 'published',
                                                         'shown_in_site' => 1, // shown when the page published
                                                         'relative_urls' => $relative_urls,
                                                         'id' => $duplication ? 0: $id
                                                    ));
                    $has_previous = false;
                    // get current active version number: if any one of all locales version was approved, then set $has_previous as TRUE so that system will save a new version base on the CORRESPONGDING version
                    //$sql = sprintf('(SELECT * FROM (SELECT * FROM webpages w WHERE w.domain = \'private\' AND w.id = %d)) AS w'
                    $sql = sprintf('SELECT * FROM webpage_locales WHERE domain= \'private\' AND webpage_id=%d'
                                    . ' AND status NOT IN("pending", "draft")'
                                    . ' ORDER BY major_version DESC, minor_version DESC'
                                    . ' LIMIT 0, 1'
                                    , $id);
                    $statement = $this->conn->query($sql);
                    if($record = $statement->fetch()) {
                        $has_previous = true;
                        $tmp_data['major_version'] = $record['major_version'];
                        $tmp_data['minor_version'] = $record['minor_version'];
                    }

                    // set platforms and language
                    $page->setPlatforms($platforms);
                    $assign_locales = array();
                    $all_locales = $locales;

                    // Remove empty paragraphs added by TinyMCE
                    if ( $page_type == 'static' )
                    {
                        foreach ( $tmp_data['content'] as $platform => $locale_contents )
                        {
                            foreach ( $locale_contents as $locale => $content )
                            {
                                $tmp_data['content'][$platform][$locale] = str_replace( '<p></p>', '', $content );
                            }
                        }
                    }

                    /*foreach($locales as $locale) {
                        if(isset($tmp_data['webpage_title'][$locale]) && $tmp_data['webpage_title'][$locale] !== '') {
                            $assign_locales[] = $locale;
                        }
                    }*/
                    //$page->setLocales($assign_locales);
                    //$page->setLocales($locales);
                    //$page->setLocales($is_new ? $locales : $assign_locales);
                    // the locales that deleted should also be updated for version consistant purpose --> fix the issue when alias duplicate checking after deleted a webpage
                    $sql = 'SELECT alias FROM locales WHERE site="public_site" AND enabled=0';
                    $all_locales = array_merge( $all_locales, $this->kernel->get_set_from_db($sql) );
                    $page->setLocales($all_locales);
                    $page->setAccessibleLocales($accessible_locale_alias);
                    $page->setSavingLocales($locales_to_save);
                    $page->setData($tmp_data);

                    if($preview) {
                        // save the page object to a tmp file
                        $n = md5(sprintf('AVALADE_sh_%d_%d', $this->user->getId(), microtime(false)));

                        $desire_platform = trim(array_ifnull($_POST, 'preview_platform', ''));
                        $available_platforms = $platforms;
                        if(!in_array($desire_platform, $available_platforms)) {
                            $desire_platform = $available_platforms[0];
                        }

						// It is not necessary to write a temp file before preview 2017.Aug.22
                        /*if(!is_dir($this->kernel->sets['paths']['temp_root'] . '/live-previews/')) {
                            mkdir($this->kernel->sets['paths']['temp_root'] . '/live-previews/');
                        }*/

                        //$file = $this->kernel->sets['paths']['temp_root'] . '/live-previews/' . $n;

                        // get the locale
                        $target_locale = $this->kernel->default_public_locale;
                        $_POST['expected_locale'] = trim(array_ifnull($_POST, 'expected_locale', ''));
                        if($_POST['expected_locale']) {
                            $_POST['expected_locale'] = preg_replace('#^locale\-#', '', $_POST['expected_locale']);
                            if(isset($this->kernel->sets['public_locales'][$_POST['expected_locale']])) {
                                $target_locale = $_POST['expected_locale'];
                            }
                        }

                        // Save draft first
                        $page->setSavingLocales(array($target_locale));
                        $id = $page->saveAsNew($this->user->getId(), true, $_POST['status']); // save as new version

                        if($is_new && isset($_POST['webpage_parent_id'])) {
                            // get parent access right and assign the rights to group
                            $parent_ids = array_unique($_POST['webpage_parent_id']);

                            foreach($parent_ids as $parent_id) {
                                //foreach($this->user->getRole()->getRights()->getModuleRights('webpage_admin') as $right)
                                //{
                                    $sql = sprintf('REPLACE INTO role_webpage_rights (role_id, webpage_id, `right`)'
                                                    . ' SELECT role_id, %1$d AS webpage_id, `right`'
                                                    . ' FROM role_webpage_rights WHERE webpage_id = %2$d'
                                                    , $id, intval($parent_id));
                                    $this->conn->exec($sql);
                                //}
                            }
                        }

                        $page->setDeleted(false);
                        $page->setEnabled(true);
                        $page->setPublishDate(null);

                        $urls = $page->getRelativeUrls();
                        foreach($urls as $k => $url) {
                            $urls[$k] = '/preview' . $url;
                        }
                        $page->setRelativeUrls($urls);

                        $page->setRemovalDate(null);
                        //file_put_contents( $file, serialize($page) ); //structured page preview logic nov 10

						//fix the page specific image missing issue
						if(in_array($page_type, array("static", "url_link", "structured_page"))) {
							$copied_files = $this->pageFilesCopy(array('type' => 'temp'), array('type' => 'archive',
													'sub_path' => "{$page->getMajorVersion()}_{$page->getMinorVersion()}")
													, $id, $page_folder);
						}

						$this->clear_private_cache();

                        $redirect = $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc']
                            . '/' . $target_locale . $page->getRelativeUrl($desire_platform);//echo $redirect; exit;
                        $this->kernel->redirect($redirect . '?' . http_build_query(array(
                                                                                        'm' => ($desire_platform == "mobile" ? 1 : 0)//,
                                                                                        //'pd' => $n
                                                                                   )));

                        $this->conn->commit();
                        return;

                    }
                    else
                    {
                        // move descendant webpages if necessary
                        // put before save to ensure that the paths are previous ones
                        $rows = array();
                        if($id) {
                            $sql = sprintf('SELECT * FROM webpage_platforms wp JOIN(SELECT * FROM('
								//. 'SELECT * FROM webpages w'
                                //. ' WHERE w.domain = \'private\' AND w.id = %d ORDER BY w.major_version DESC, w.minor_version DESC) AS tmp'
                                //. ' GROUP BY tmp.domain, tmp.id) '

								. 'SELECT w.* FROM webpages w JOIN webpage_versions wv ON (wv.id=w.id AND wv.domain=w.domain AND wv.major_version=w.major_version AND wv.minor_version=w.minor_version) WHERE w.domain="private" AND w.id=%d) AS tmp GROUP BY tmp.domain, tmp.id)'

								. ' AS tb ON(tb.domain = wp.domain AND tb.id = wp.webpage_id'
                                . ' AND tb.major_version = wp.major_version AND tb.minor_version = wp.minor_version)'
                                , $id);
                            $statement = $this->conn->query($sql);
                            $rows = $statement->fetchAll();
                        }

                        $draft = $_POST['status'] == "draft";

                        $id = $page->saveAsNew($this->user->getId(), true, $draft); // save as new version

                        $original_urls = array();

                        // perform move actions here after the page has updated
                        foreach($rows as $row) {
                            $relative_urls = $page->getRelativeUrls();

                            $original_urls[$row['platform']] = $row['path'];

                            // not same as previous
                            if(isset($relative_urls[$row['platform']]) && $relative_urls[$row['platform']] != $row['path']) {
                                $this->move_descendant($row['path'], $relative_urls[$row['platform']], 'private', $row['platform']);
                            }
                        }

                        if($is_new && isset($_POST['webpage_parent_id'])) {
                            // get parent access right and assign the rights to group
                            $parent_ids = array_unique($_POST['webpage_parent_id']);

                            foreach($parent_ids as $parent_id) {
                                //foreach($this->user->getRole()->getRights()->getModuleRights('webpage_admin') as $right)
                                //{
                                    $sql = sprintf('REPLACE INTO role_webpage_rights (role_id, webpage_id, `right`)'
                                                    . ' SELECT role_id, %1$d AS webpage_id, `right`'
                                                    . ' FROM role_webpage_rights WHERE webpage_id = %2$d'
                                                    , $id, intval($parent_id));
                                    $this->conn->exec($sql);
                                //}
                            }
                        }

                        // log
                        if($is_new) {
                            $this->kernel->log( 'message', sprintf("User %d <%s> created webpage %d", $this->user->getId(), $this->user->getEmail(), $id), __FILE__, __LINE__ );

                            // Rename temp folders
                            foreach ( $this->user->getAccessibleLocales() as $locale )
                            {
                                $tf = "webpage/page/temp/$page_folder/" . str_replace( '/', '-', $locale) . '/';
                                $rename($tf . '0/', $tf . "$id/");
                            }
                        }
                        else {
                            //Get the webpage title of the webpage $locales_to_save
                            $webpage_titles = array();
                            $msg_locales = array();
                            $sql = sprintf('SELECT name, webpage_title FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) JOIN locales l ON (wl.locale=l.alias AND l.site=\'public_site\') WHERE webpage_id=%1$d AND wv.domain=\'private\' AND locale IN (%2$s)', $id, implode(',', array_map(array($this->kernel->db, 'escape'), $locales_to_save))).' ORDER BY l.order_index';
                            $statement = $this->conn->query($sql);
                            while($r = $statement->fetch())
                            {
                                $webpage_titles[] = $r['webpage_title'];
                                $msg_locales[] = $r['name'];
                            }

                            if(count($msg_locales)>0)
                                $this->kernel->log( 'message', sprintf("User %d <%s> edited webpage %d (%s)", $this->user->getId(), $this->user->getEmail(), $id, $webpage_titles[0].': '.implode(', ', $msg_locales)), __FILE__, __LINE__ );
                            else
                                $this->kernel->log( 'message', sprintf("User %d <%s> edited webpage %d", $this->user->getId(), $this->user->getEmail(), $id), __FILE__, __LINE__ );
                        }

                        if(in_array($page_type, array("static", "url_link", "structured_page"))) {
                            // Copy all files in temp folder to private folder
                            //$this->pageFilesCopy(array('type' => 'temp'), array('type' => 'private'), $id, $page_folder);
                            $copied_files = $this->pageFilesCopy(array('type' => 'temp'), array('type' => 'archive',
                                                                                'sub_path' => "{$page->getMajorVersion()}_{$page->getMinorVersion()}")
                                                                                , $id, $page_folder);

                            if(!$preview && $page_folder != '') {
                                // clear all files in that directory
                                $temp_dir = "webpage/page/temp/{$page_folder}/";
                                if($this->kernel->conf['aws_enabled']) {
                                    s3_deleteDir($temp_dir);
                                } else if (is_dir($temp_dir)) {
                                    $this->empty_folder($temp_dir);
                                }
                            }

                        }

                        if($page->getStatus() == "approved") {
                            $this->changeDecendentStatus("approved", $id, $page->getMajorVersion(), $page->getMinorVersion(), true); // prevent approving children pages

                            $locales_to_publicize = array();
                            $now = string_to_date( 'now', TRUE );
                            foreach ( $locales_to_save as $locale )
                            {
                                if ( is_null($tmp_data['publish_date'][$locale]) || $tmp_data['publish_date'][$locale] <= $now )
                                {
                                    $locales_to_publicize[] = $locale;
                                }
                            }
                            $this->publicize($page->getId(), true, $locales_to_publicize); // prevent approving children pages
                        } elseif($page->getStatus() == "pending") {
                            $child_affected = false;
                            if($id) {
                                $r_urls = $page->getRelativeUrls();

                                foreach($r_urls as $platform => $r_url) {
                                    if(!isset($original_urls[$platform]) || $original_urls[$platform] != $r_url) {
                                        $child_affected = true;
                                        break;
                                    }
                                }

                                if(!$child_affected && count($original_urls) != count($r_urls)) {
                                    $child_affected = true;
                                }
                            }

                            $this->send_pending_email(
                                array($page),
                                $id ? 'edit' : 'new',
                                $child_affected
                            );
                        }

                        //if($is_submit)
                        //    $this->unlock($id, $this->user->getId());\

                        if($has_previous && ($page_type == "static" || $page_type == "structured_page")) {
                            // previous active page becomes archive - delete unused files
                            $sql = sprintf('SELECT content FROM webpage_locale_contents WHERE domain = \'private\' AND webpage_id = %d AND major_version = %d AND minor_version = %d'
                                , $id, $tmp_data['major_version'], $tmp_data['minor_version']);
                            $previous_contents = $this->kernel->get_set_from_db( $sql );

                            // list the files under the directory
                            //$folder = "page/archive/p" . $id . "/" . $tmp_data['major_version'] . "_" . $tmp_data['minor_version'];
                            //$files = $this->directoryToArray($folder, true);

                            // delete unused files
                            //$this->unlink_unused_files($files, $folder, $id, $previous_contents, $page_type);
                        }
                    }
                    $this->conn->commit();
                }

                // continue to process (successfully)
                if($preview) {
                    $redirect = $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc']
                                . '/' . $_POST['expected_locale'] . '/preview' . $page->getRelativeUrl($platforms[0]);
                } else {
                    if($is_new)
                    {
                        // redirect to edit page for continue edit locale contents
                        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?op=edit&id=" . $id;
                    }
                    else
                        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                            http_build_query(array(
                                              'op' => 'dialog',
                                              'type' => 'message',
                                              'code' => 'DESCRIPTION_saved',
                                              'redirect_url' => $this->kernel->sets['paths']['server_url']
                                              . $this->kernel->sets['paths']['mod_from_doc']
                                              . '?id=' . $id,
                                              'actions' => array(
                                                  $this->dialogActionEncode(
                                                      $this->kernel->sets['paths']['mod_from_doc'] . '?op=edit&id=' . $id . '#/content-desktop',
                                                      $this->kernel->dict['ACTION_continue_editing'],
                                                      '_top',
                                                      'icon-edit'
                                                  ),
                                                  $this->dialogActionEncode(
                                                      $this->kernel->sets['paths']['app_from_doc'] . '/'
                                                          . $this->kernel->default_public_locale
                                                          . '/preview' . $page->getRelativeUrl($platforms[0]),
                                                      $this->kernel->dict['ACTION_preview'],
                                                      '_blank',
                                                      'icon-eye-open'
                                                  )
                                              )
                                            ));
                }

                if($ajax) {
                    $this->apply_template = FALSE;
                    $this->kernel->response['mimetype'] = 'application/json';
                    $this->kernel->response['content'] = json_encode( array(
                                                                           'result' => 'success',
                                                                           'redirect' => $redirect,
                                                                           'target' => $preview ? '_blank' : '_top'
                                                                      ));
                } else {
                    $this->kernel->redirect($redirect);
                }

                if($is_submit) {
                    $this->unlock($id, $this->user->getId());
					$this->clear_private_cache();
                }

                return;
            }
        } catch(Exception $e) {
            $this->processException($e);

            if($preview) {
                $this->kernel->response['mimetype'] = 'text/html';
                $this->kernel->response['content'] = $this->kernel->smarty->fetch('module/webpage_admin/preview_exception.html');
            }
            return FALSE;
        }

        // Set media directory for editors
        //$media_dir = "{$this->kernel->sets['paths']['app_root']}/file/media";
        //force_mkdir( $media_dir );

        //$media_dir = "{$this->kernel->sets['paths']['app_root']}/file/media/share";
        //force_mkdir( $media_dir );

        if(!$ajax) {
            $temp_folder = $this->setTempFolder();
            $this->kernel->control['editor_media_subfolder'] = "page/temp/{$temp_folder}";
            $base_temp_folder = 'webpage/' . $this->kernel->control['editor_media_subfolder'] . '/';
            $mkdir( $base_temp_folder );
            foreach ( $this->user->getAccessibleLocales() as $locale )
            {
                $locale_temp_folder = $base_temp_folder . str_replace( '/', '-', $locale) . '/';
                $mkdir( $locale_temp_folder );
                $mkdir( $locale_temp_folder . $id . '/' );
            }
        }

        $data['webpage'] = $webpage_data;
        $default_order = "asc";
        $data['webpage']['is_single_locale'] = true; // set default locale number for new / duplicate webpage

        if($id) {
            // For existing webpage, see if the webpage is not editable or locked
            if ( !$this->is_editable($id) || $this->is_locked($id) )
            {
                $this->kernel->redirect( '?id=' . $id );
                return;
            }

            // Lock the webpage
            //if(!$duplication)
                $this->lock($id);

            // Set the first locale as default expected locale for regional user
            if($this->user_default_locale_read_only && (!isset($data['webpage']['expected_locale']) || (isset($data['webpage']['expected_locale']) && $data['webpage']['expected_locale'] == '')))
            {
                $i=0;
                foreach($this->user->getAccessibleLocales() as $locale)
                {
                    if($i==0 && $locale != $this->kernel->default_public_locale)
                    {
                        $data['webpage']['expected_locale'] = $locale;
                        $i++;
                    }
                }
            }

            // use user preferred locale
            $data['webpage']['expected_locale'] = $this->user->getPreferredLocale();

            $major_version = $data['webpage']['major_version'];
            $minor_version = $data['webpage']['minor_version'];
            if(!$is_submit && !$page_type)
                $page_type = $data['webpage']['type'];

            // get the selected offers in current version
            if($page_type == "static" || $page_type == "structured_page") {
                $sql = sprintf('SELECT po.offer_id FROM webpage_offers po JOIN offers o ON(o.domain = po.domain AND o.id = po.offer_id)'
                                . ' WHERE po.domain = \'private\' AND po.webpage_id = %d AND po.major_version = %d'
                                . ' AND po.minor_version = %d AND o.deleted = 0 ORDER BY `order` ASC'
                                , $id, $major_version, $minor_version);
                $selected_offers = $this->kernel->get_set_from_db( $sql );
            }

            $sql = sprintf("SELECT * FROM webpage_platforms WHERE domain = 'private'"
                . ' AND webpage_id = %1$d AND major_version = %2$d'
                . ' AND minor_version = %3$d AND deleted = 0'
                , $id
                , $major_version
                , $minor_version);
            $statement = $this->conn->query($sql);

            $data['webpage']['platforms'] = array();

            while($row = $statement->fetch()) {
                $data['webpage']['platforms'][] = $row['platform'];
                $data['webpage'][$row['platform']] = $row;
                $paths = array_filter(explode('/',$row['path']), 'strlen');

                $data['webpage'][$row['platform']]['alias'] = array_pop($paths);
                $parent_path = implode('/', $paths);

                $page_link_data = $this->getPageLinkData($parent_path, $row['platform']);

                $data['webpage'][$row['platform']]['webpage_parent_text'] = $page_link_data['webpage_text'];
                $data['webpage'][$row['platform']]['webpage_parent_id'] = $page_link_data['webpage_id'];
                $data['webpage'][$row['platform']]['webpage_parent_path'] = $page_link_data['webpage_path'];

                if(!count($data['webpage']['accessible_public_roles'])) {
                    $sm = $this->get_sitemap('edit', $row['platform']);

                    // try to get the root page and see if this platform exists
                    /** @var pageNode $pn */
                    $pn = $sm->findPage($page_link_data['webpage_path']);
                    if($pn) {
                        $data['webpage']['accessible_public_roles'] = $pn->getAccessiblePublicRoles();
                    }
                }
            }

            $sql = sprintf("SELECT * FROM webpage_locales WHERE domain = 'private'"
                . ' AND webpage_id = %2$d AND major_version = %3$d'
                . ' AND minor_version = %4$d'
                , $data['webpage']['type']
                , $id
                , $major_version
                , $minor_version);
            $statement = $this->conn->query($sql);
            $data['webpage']['locales'] = array();

            while($row = $statement->fetch()) {
                // re-version data for specific locales
                if($version>0 && $roll_back_locale!='' && $roll_back_locale==$row['locale'])
                {
                    $sql = sprintf("SELECT * FROM webpage_locales WHERE domain = 'private'"
                        . ' AND webpage_id = %2$d AND major_version = %3$d'
                        . ' AND minor_version = %4$d AND locale=%5$s'
                        , $data['webpage']['type']
                        , $id
                        , floor($version)
                        , intval(strpos($version, '.') ? substr($version, strpos($version, '.')+1) : 0)
                        , $this->kernel->db->escape($roll_back_locale));
                    $statement2 = $this->conn->query($sql);
                    $record = $statement2->fetch();
                    $record['publish_date'] = convert_tz( $record['publish_date'], 'gmt', $this->kernel->conf['timezone'] );
                    $record['removal_date'] = convert_tz( $record['removal_date'], 'gmt', $this->kernel->conf['timezone'] );
                    $data['webpage']['locales'][$row['locale']] = $record;
                    $this->kernel->smarty->assign('roll_back_locale', $roll_back_locale);
                }
                else
                {
                    $row['publish_date'] = convert_tz( $row['publish_date'], 'gmt', $this->kernel->conf['timezone'] );
                    $row['removal_date'] = convert_tz( $row['removal_date'], 'gmt', $this->kernel->conf['timezone'] );
                    $data['webpage']['locales'][$row['locale']] = $row;
                }
            }

            $data['webpage']['is_single_locale'] = count($data['webpage']['locales'])>1 ? false : true;

            if($page_type == "static" || $page_type == "structured_page") {
                $sql = sprintf("SELECT * FROM webpage_locale_contents WHERE domain = 'private'"
                    . ' AND webpage_id = %1$d AND major_version = %2$d'
                    . ' AND minor_version = %3$d'
                    , $id
                    , $major_version
                    , $minor_version);
                $statement = $this->conn->query($sql);

                while($row = $statement->fetch()) {
                    // re-version data for specific locales
                    if($version>0 && $roll_back_locale!='' && $roll_back_locale=$row['locale'])
                    {
                         $sql = sprintf("SELECT * FROM webpage_locale_contents WHERE domain = 'private'"
                            . ' AND webpage_id = %2$d AND major_version = %3$d'
                            . ' AND minor_version = %4$d AND locale=%5$s'
                            , $data['webpage']['type']
                            , $id
                            , floor($version)
                            , intval(strpos($version, '.') ? substr($version, strpos($version, '.')+1) : 0)
                            , $this->kernel->db->escape($roll_back_locale));
                         $statement2 = $this->conn->query($sql);
                         $res = $statement2->fetch();
                         $content = $this->imgPathDecode('temp', $id, $res['content'], $temp_folder, $page_type);
                    }
                    else
                        $content = $this->imgPathDecode('temp', $id, $row['content'], $temp_folder, $page_type);
                    if($page_type == "static")
                        $data['webpage']['locales'][$row['locale']][$row['platform']][$row['type']] = $content['content'];
                    else
                    {
                        // Order structured page form data
                        $spd_tmp = json_decode($content['content'], true) ?? array();
                        foreach($spd_tmp as $section_id=>&$field_data)
                        {
                            $order_data = array();
                            $loop_fields = array();
                            foreach($field_data as $key=>&$val)
                            {
                                if(preg_match('/^([A-Za-z_]+)([\d]+)$/i', $key, $matches))
                                {
                                    $order_data[$matches[2]][$matches[1]] = $val;
                                    $loop_fields[] = $matches[1];
                                    if(preg_match('/_order/i', $matches[1]) && preg_match('/^[\d]+$/i', $val))
                                    {
                                        $order_data['display_order'][$matches[2]]=$val;
                                    }
                                }
                            }
                            if(isset($order_data['display_order']))
                            {
                                asort($order_data['display_order']);

                                $i=1;
                                //echo print_r($order_data);
                                foreach($order_data['display_order'] as $old_order=>$new_order)
                                {
                                    foreach($loop_fields as $field_key)
                                    {
                                        if(isset($order_data[$old_order][$field_key]))
                                            $field_data[$field_key.$i] = $order_data[$old_order][$field_key];
                                    }
                                    $i++;
                                }
                                unset($order_data);
                                unset($loop_fields);
                                //echo print_r($field_data);
                            }
                        }
                        $data['webpage']['locales'][$row['locale']][$row['platform']][$row['type']] = $spd_tmp;
                        unset($spd_tmp);
                    }
                }

                if($page_type == 'static')
                {
                    $sql = sprintf('SELECT *,'
                        . " IF(image_xs LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                        . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_xs, INSTR(image_xs, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_xs) AS image_xs,"
                        . " IF(image_md LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                        . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_md, INSTR(image_md, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_md) AS image_md,"
                        . " IF(image_xl LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                        . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_xl, INSTR(image_xl, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_xl) AS image_xl"
                        . " FROM webpage_locale_banners WHERE domain = 'private'"
                        . ' AND webpage_id = %1$d AND major_version = %2$d'
                        . ' AND minor_version = %3$d'
                        , $id
                        , $major_version
                        , $minor_version);
                    $statement = $this->conn->query($sql);
                    while($row = $statement->fetch()) {
                        $data['webpage']['locales'][$row['locale']]['banners'][] = $row;
                    }

                    // re-version data for specific locales
                    if($version>0 && $roll_back_locale!='')
                    {
                        $sql = sprintf('SELECT *,'
                            . " IF(image_xs LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                            . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_xs, INSTR(image_xs, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_xs) AS image_xs,"
                            . " IF(image_md LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                            . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_md, INSTR(image_md, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_md) AS image_md,"
                            . " IF(image_xl LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                            . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_xl, INSTR(image_xl, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_xl) AS image_xl"
                            . " FROM webpage_locale_banners WHERE domain = 'private'"
                            . ' AND webpage_id = %1$d AND major_version = %2$d'
                            . ' AND minor_version = %3$d AND locale=%4$s'
                            , $id
                            , floor($version)
                            , intval(strpos($version, '.') ? substr($version, strpos($version, '.')+1) : 0)
                            , $this->kernel->db->escape($roll_back_locale));
                        $statement = $this->conn->query($sql);
                        $data['webpage']['locales'][$roll_back_locale]['banners'] = $statement->fetchAll();
                    }
                }
            }
            elseif($data['webpage']['type'] == "webpage_link")
            {
                foreach($data['webpage']['platforms'] as $platform)
                {
                    if($data['webpage'][$platform]['linked_webpage_id']) {
                        $sql = sprintf('SELECT wp.* FROM webpage_platforms wp JOIN webpages w'
                                        . ' ON(w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version)'
                                        . " WHERE wp.domain = 'private'"
                                        . ' AND wp.webpage_id = %2$d AND wp.platform = %3$s'
                                        . ' ORDER BY w.major_version DESC, w.minor_version DESC LIMIT 0, 1'
                                        , $this->user->getId(), $data['webpage'][$platform]['linked_webpage_id'], $this->conn->escape($platform));
                        $statement = $this->conn->query($sql);

                        if($record = $statement->fetch()) {
                            $page_link_data = $this->getPageLinkData(substr($record['path'], 1, -1), $platform);

                            $data['webpage'][$platform]['linked_page_text'] = $page_link_data['webpage_text'];
                            $data['webpage'][$platform]['linked_page_id'] = $page_link_data['webpage_id'];
                            $data['webpage'][$platform]['linked_page_path'] = $page_link_data['webpage_path'];
                        }
                    }
                }
            }
            elseif($data['webpage']['type'] == "url_link")
            {
                foreach($data['webpage']['locales'] as $locale => &$info) {
                    $content = $this->imgPathDecode('temp', $id, $info['url'], $temp_folder, $data['webpage']['type']);
                    $info['url'] = $content['content'];
                }
                unset($info);
            }

            if($duplication) {
                // remove unnecessary data for duplication
                unset($data['webpage']['id']);
                foreach($data['webpage']['platforms'] as $platform) {
                    unset($data['webpage'][$platform]['alias']);
                }
            }

            // copy files
            if(!$ajax) {
                foreach($this->kernel->sets['public_locales'] as $alias => $v) {
                    $folder = "webpage/page/archive/p{$id}/{$major_version}_{$minor_version}/$alias/";
                    $mkdir( $folder );
                    $mkdir( $folder . "$id/" );
                }
                $this->pageFilesCopy(array('type' => 'archive', 'sub_path' => "{$major_version}_{$minor_version}"), array('type' => 'temp'), $id, $temp_folder);
            }
        } elseif ($root_page) {
            foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
                $data['webpage']['platforms'][] = $platform;
                $data['webpage'][$platform]['path'] = '/';
            }
        } else {
            $default_platform = array_ifnull($_GET, 't', 'desktop');
            $data['webpage']['platforms'][] = $default_platform;

            $parent_id = intval(array_ifnull($_GET, 'parent', 0));

            $sql = sprintf("SELECT * FROM (SELECT * FROM webpages w WHERE domain = 'private'"
                . ' AND id = %1$d'
                //. ' AND (w.status NOT IN("rejected"))'
                . ' ORDER BY w.major_version DESC, w.minor_version DESC LIMIT 0, 1) AS w'
                . ' JOIN webpage_platforms p ON(p.domain = w.domain AND p.webpage_id = w.id AND w.major_version = p.major_version'
                . ' AND w.minor_version = p.minor_version)'
                , $parent_id, $this->user->getId());

            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
                $page_link_data = $this->getPageLinkData($row['path'], $row['platform']);

                $data['webpage'][$row['platform']]['webpage_parent_text'] = $page_link_data['webpage_text'];
                $data['webpage'][$row['platform']]['webpage_parent_id'] = $page_link_data['webpage_id'];
                $data['webpage'][$row['platform']]['webpage_parent_path'] = $page_link_data['webpage_path'];

                $sm = $this->get_sitemap('edit', $row['platform']);

                // try to get the root page and see if this platform exists
                /** @var pageNode $pn */
                $pn = $sm->findPage($page_link_data['webpage_path']);

                if($pn) {
                    $default_order = $pn->getItem()->getChildOrderDirection();
                    $data['webpage']['accessible_public_roles'] = $pn->getAccessiblePublicRoles();
                }
            }
        }

        // Get rights of current webpage
        $sql = sprintf('SELECT `right` FROM role_webpage_rights rwr LEFT JOIN users u ON (u.role_id=rwr.role_id) WHERE u.id=%d AND rwr.webpage_id=%d', $this->user->getId(), $id==0 ? intval(array_ifnull($_GET, 'parent', 0)) : $id);
        $current_webpage_rights = $this->kernel->get_set_from_db( $sql );

        if(count($_POST)) {
            $locale_fields = array('webpage_title', 'seo_title', 'headline_title', 'seo_keywords', 'seo_description', 'url');

            foreach($locale_fields as $field) {
                if(isset($_POST[$field])) {
                    foreach($_POST[$field] as $locale => $value) {
                        if($field == 'seo_keywords')
                            $data['webpage']['locales'][$locale]['keywords'] = $value;
                        else if($field == 'seo_description')
                            $data['webpage']['locales'][$locale]['description'] = $value;
                        else
                            $data['webpage']['locales'][$locale][$field] = $value;
                    }
                }
            }

            $platform_fields = array('webpage_parent_text', 'webpage_parent_id', 'webpage_parent_path', 'alias'
                , 'shown_in_menu', 'shown_in_sitemap', 'submenu_shown', 'template', 'child_order_field'
                , 'child_order_direction', 'order_index'
                , 'target');
            foreach($platform_fields as $field) {
                if(isset($_POST[$field])) {
                    foreach($_POST[$field] as $platform => $value) {
                        $data['webpage'][$platform][$field] = $value;
                    }
                }
            }

            if(isset($_POST['expected_locale']))
                $data['webpage']['expected_locale'] = $_POST['expected_locale'];

            if(isset($_POST['content'])){
                /*$data['webpage']['content'] = $_POST['content'];*/
                if (is_array($_POST['content']) || is_object($_POST['content']))
                {
                    foreach($_POST['content'] as $platform => $locale_values){
                        if (is_array($locale_values) || is_object($locale_values))
                        {
                            foreach($locale_values as $locale=>$value){
                                $data['webpage']['locales'][$locale][$platform]['content'] = $value['content'];
                            }
                        }
                    }
                }
            }

            if(isset($_POST['platforms'])) {
                $data['webpage']['platforms'] = $_POST['platforms'];
            }

            if(isset($_POST['selected_offers'])) {
                $selected_offers = $_POST['selected_offers'];
            }

            if(isset($_POST['structured_page_template'])) {
                $structured_page_template = $_POST['structured_page_template'];
            }

            if($page_type == "structured_page")
            {
                if (is_array($accessible_locale_alias) || is_object($accessible_locale_alias))
                {
                    foreach($accessible_locale_alias as $locale)
                    {
                        if (isset($_POST[$locale]) && (is_array($_POST[$locale]) || is_object($_POST[$locale])))
                        {
                            foreach($_POST[$locale] as $section_id=>$field)
                            {
                                $data['webpage']['locales'][$locale]['desktop']['content'][$section_id]=$field;
                            }
                        }
                    }
                }
            }

            //copy default locale content
            if($is_duplicate_default_content)
            {
                $sql = sprintf("SELECT * FROM webpage_locales WHERE domain = 'private'"
                    . ' AND webpage_id = %1$d AND major_version = %2$d'
                    . ' AND minor_version = %3$d AND locale=%4$s'
                    , $id
                    , $major_version
                    , $minor_version
                    , $this->kernel->db->escape($this->kernel->default_public_locale));
                $statement = $this->conn->query($sql);

                $data['webpage']['locales'][$_POST['expected_locale']] = $statement->fetch();
                if($page_type == "static" || $page_type == "structured_page") {
                    $sql = sprintf("SELECT * FROM webpage_locale_contents WHERE domain = 'private'"
                        . ' AND webpage_id = %1$d AND major_version = %2$d'
                        . ' AND minor_version = %3$d AND locale=%4$s'
                        , $id
                        , $major_version
                        , $minor_version
                        , $this->kernel->db->escape($this->kernel->default_public_locale));
                    $statement = $this->conn->query($sql);
                    $row = $statement->fetch();
                    $content = $this->imgPathDecode('temp', $id, $row['content'], $temp_folder, $page_type);

                    if($page_type == "static")
                        $data['webpage']['locales'][$_POST['expected_locale']]['desktop']['content'] = $content['content'];
                    else
                    {
                        // Order structured page form data
                        $spd_tmp = json_decode($content['content'], true);
                        foreach($spd_tmp as $section_id=>&$field_data)
                        {
                            $order_data = array();
                            $loop_fields = array();
                            foreach($field_data as $key=>&$val)
                            {
                                if(preg_match('/^([A-Za-z_]+)([\d]+)$/i', $key, $matches))
                                {
                                    $order_data[$matches[2]][$matches[1]] = $val;
                                    $loop_fields[] = $matches[1];
                                    if(preg_match('/_order/i', $matches[1]) && preg_match('/^[\d]+$/i', $val))
                                    {
                                        $order_data['display_order'][$matches[2]]=$val;
                                    }
                                }
                            }
                            if(isset($order_data['display_order']))
                            {
                                asort($order_data['display_order']);
                            }
                            $i=1;
                            //echo print_r($order_data);
                            foreach($order_data['display_order'] as $old_order=>$new_order)
                            {
                                foreach($loop_fields as $field_key)
                                {
                                    $field_data[$field_key.$i] = $order_data[$old_order][$field_key];
                                }
                                $i++;
                            }
                            unset($order_data);
                            unset($loop_fields);
                            //echo print_r($field_data);
                        }
                        $data['webpage']['locales'][$_POST['expected_locale']]['desktop']['content'] = $spd_tmp;
                        unset($spd_tmp);
                    }

                    if($page_type == 'static')
                    {
                        $sql = sprintf('SELECT *,'
                            . " IF(image_xs LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                            . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_xs, INSTR(image_xs, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_xs) AS image_xs,"
                            . " IF(image_md LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                            . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_md, INSTR(image_md, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_md) AS image_md,"
                            . " IF(image_xl LIKE 'webpage/page/private/%%', CONCAT('webpage/page/temp/', "
                            . $this->conn->escape($temp_folder) . ", '/', locale, '/', SUBSTRING(image_xl, INSTR(image_xl, CONCAT('/', locale, '/')) + LENGTH(locale) + 2)), image_xl) AS image_xl"
                            . " FROM webpage_locale_banners WHERE domain = 'private'"
                            . ' AND webpage_id = %1$d AND major_version = %2$d'
                            . ' AND minor_version = %3$d AND locale=%4$s'
                            , $id
                            , $major_version
                            , $minor_version
                            , $this->kernel->db->escape($this->kernel->default_public_locale));
                        $statement = $this->conn->query($sql);
                        $data['webpage']['locales'][$_POST['expected_locale']]['banners'] = $statement->fetchAll();
                    }
                }
                elseif($data['webpage']['type'] == "webpage_link")
                {

                }
                elseif($data['webpage']['type'] == "url_link")
                {
                    $content = $this->imgPathDecode('temp', $id, $data['webpage']['locales'][$this->kernel->default_public_locale]['url'], $temp_folder, $data['webpage']['type']);
                    $data['webpage']['locales'][$_POST['expected_locale']]['url'] = $content['content'];
                }

            }
        }

        if($page_type == "structured_page" && $id>0)
        {
            if($structured_page_template > 0)
            {
                // Load template form sections and fields
                $structured_sections = array();
                $max_loop_to_overwrite = array();
                $sql = 'SELECT sps.*, spsr.* FROM structured_page_sections sps LEFT JOIN structured_page_section_repeats spsr ON (sps.id=spsr.section_id) WHERE spsr.type_id='.$structured_page_template.' ORDER BY spsr.display_order';
                $statement = $this->conn->query($sql);
                while($r = $statement->fetch())
                {
                    $structured_sections[$r['id']] = $r;
                    if($r['max_loop']>0)
                    {
                        //Overwrite max_loop after parse the webpage data
                        foreach($data['webpage']['locales'] as $locale=>$form_data)
                        {
                            foreach($data['webpage']['platforms'] as $pf)
                            {
                                $max_loop_to_overwrite[$locale][$pf][$r['id']] = 1;
                                $num_of_general_fields = 0;
                                $tmp_sql = 'SELECT COUNT(*) AS num FROM structured_page_fields spf LEFT JOIN structured_page_section_fields spsf ON (spf.id=spsf.field_id) WHERE spsf.type_id='.$structured_page_template.' AND spsf.section_id='.$r['id'].' AND spf.is_section_general=1';
                                $statement2 = $this->conn->query($tmp_sql);
                                extract($statement2->fetch());
                                $num_of_general_fields = $num;
                                $tmp_sql = 'SELECT COUNT(*) AS num FROM structured_page_section_fields WHERE (special_parent_field_id IS NULL OR special_parent_field_id=0) AND type_id='.$structured_page_template.' AND section_id='.$r['id'];
                                $statement2 = $this->conn->query($tmp_sql);
                                extract($statement2->fetch());
                                if(isset($form_data[$pf]['content'][$r['id']]) && count($form_data[$pf]['content'][$r['id']])>0) // at least loop once
                                {
                                    $max_loop_to_overwrite[$locale][$pf][$r['id']] = ceil((count($form_data[$pf]['content'][$r['id']])-$num_of_general_fields)/($num-$num_of_general_fields));
                                }
                            }

                        }
                    }
                    $sql = 'SELECT spf.*, note_text, maxlength, special_parent_field_id, field_display_order FROM structured_page_fields spf LEFT JOIN structured_page_section_fields spsf ON (spf.id=spsf.field_id) WHERE spsf.type_id='.$structured_page_template.' AND spsf.section_id='.$r['id'].' ORDER BY spf.is_section_general DESC, spsf.field_display_order ASC';
                    $statement2 = $this->conn->query($sql);
                    $structured_sections[$r['id']]['fields'] = array();
                    while($f = $statement2->fetch())
                    {
                        if($f['is_section_general']>0)
                            $structured_sections[$r['id']]['general_fields'][$f['id']]=$f;
                        else
                            $structured_sections[$r['id']]['fields'][$f['id']]=$f;
                        if($f['special_parent_field_id']>0)
                        {
                             $structured_sections[$r['id']]['fields_to_replace'][$f['special_parent_field_id']]=$f;
                             unset($structured_sections[$r['id']]['fields'][$f['id']]);
                        }
                        if($f['field_type'] == 'select')
                        {
                            if($f['field_name'] == 'content_block')
                            {
                                $sql = 'SELECT id AS value, name AS label_text FROM customize_snippets WHERE snippet_type_id=16 AND deleted=0';
                            }
                            else if($f['field_name'] == 'room_webpage')
                            {
                                $sql = "SELECT NULL AS value, '' AS label_text UNION ALL";
                                $sql .= " SELECT w.id AS value, CONCAT(SUBSTRING_INDEX(GROUP_CONCAT(wp.path ORDER BY w.major_version DESC SEPARATOR '\r\n'), '\r\n', 1), ' - [#', w.id, ']') AS label_text";
                                $sql .= ' FROM webpages AS w';
                                $sql .= ' JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id)';
                                $sql .= " WHERE w.domain = 'private' AND w.deleted = 0 AND w.structured_page_template = 3 AND w.id <> $id";
                                $sql .= ' GROUP BY w.id';
                                $sql .= ' ORDER BY label_text';
                            }
                            else
                            {
                                $sql = 'SELECT * FROM structured_page_field_values WHERE field_id='.$f['id'];
                            }
                            $statement3 = $this->conn->query($sql);
                            while($v = $statement3->fetch())
                            {
                                if($f['is_section_general']>0)
                                    $structured_sections[$r['id']]['general_fields'][$f['id']]['options'][$v['value']] = $v['label_text'];
                                else
                                    $structured_sections[$r['id']]['fields'][$f['id']]['options'][$v['value']] = $v['label_text'];
                            }
                        }
                    }
                }
                $this->kernel->smarty->assign('structured_sections', $structured_sections);
                $this->kernel->smarty->assign('max_loop_to_overwrite', $max_loop_to_overwrite);
                //echo print_r($structured_sections);
                //echo print_r($max_loop_to_overwrite);
                //exit;
            }
        }

        // continue to process if not ajax
        if(!$ajax) {
            $data['webpage']['selected_offers'] = $selected_offers;

            if($root_page) {
                unset($this->kernel->dict['SET_offer_sources']['inherited']);
            }

            // get the template data
            $templates = array();
            $sql = sprintf('SELECT * FROM templates WHERE deleted = 0 ORDER BY id');
            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
                if(!isset($templates[$row['platform']])) {
                    $templates[$row['platform']] = array();
                }
                $templates[$row['platform']][$row['id']] = array(
                    'label' => $row['template_name'],
                    'img' => $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc'] . '/' . $row['thumbnail']
                );
            }

            $has_previous = false;
            $sql = sprintf('SELECT * FROM webpage_locales WHERE domain= \'private\' AND webpage_id=%d'
                            . ' AND status NOT IN("pending", "draft")'
                            . ' ORDER BY major_version DESC, minor_version DESC'
                            . ' LIMIT 0, 1'
                            , $id);
            $statement = $this->conn->query($sql);
            if($statement->fetch()) {
                $has_previous = true;
            }
            $this->kernel->smarty->assign('has_previous', $has_previous);

            // Load the top 10 created customize snippets
            /*
            $sql = 'SELECT cs.id, s.snippet_name AS snippet_type, cs.name AS snippet_name FROM customize_snippets cs LEFT JOIN snippets s ON (cs.snippet_type_id=s.id) WHERE cs.deleted=0 ORDER BY created_time DESC LIMIT 0, 10';
            $snippet_list = $this->kernel->get_set_from_db( $sql );
            $this->kernel->smarty->assign('snippet_list', $snippet_list);
            */

            if($id && !$duplication) {
                // BreadCrumb
                $this->_breadcrumb->push(new breadcrumbNode(sprintf('%1$s - [#%2$d]'
                        , $data['webpage'][$data['webpage']['platforms'][0]]['path'], $id), $this->kernel->sets['paths']['mod_from_doc'] . '/?id=' . $id)
                );
                $this->action_title = sprintf($this->kernel->dict['SET_operations']['edit']
                    , sprintf('%s (%s: %d)', $this->kernel->dict['LABEL_webpage'], $this->kernel->dict['LABEL_webpage_id'], $id));

                $info = array(
                    'created_date_message' => sprintf($this->kernel->dict['INFO_created_date'], '<b>' . $webpage_data['created_date'] . '</b>', '<b>' . $webpage_data['creator_name'] . '</b>', '<b>' . $webpage_data['creator_email'] . '</b>')
                );

                /*if($webpage_data['updated_date']) {
                    $info['last_update_message'] = sprintf($this->kernel->dict['INFO_last_update'], '<b>' . $webpage_data['updated_date'] . '</b>', '<b>' . $webpage_data['updater_name'] . '</b>', '<b>' . $webpage_data['updater_email'] . '</b>');
                }*/

                $this->kernel->smarty->assign('info', $info);
            } else {
                $this->action_title = sprintf($this->kernel->dict['SET_operations']['new']
                    , $this->kernel->dict['SET_webpage_types'][$page_type]);
            }

            if($page_type == "static" || $page_type == "structured_page") {
                // get all the available offers
                $sql = sprintf('SELECT offer_started, id, type, status, deleted'
                    . ', CONVERT_TZ(start_date, "+00:00", %2$s) AS start_date'
                    . ', CONVERT_TZ(end_date, "+00:00", %2$s) AS end_date, title'
                    . ' FROM(SELECT o.*, l.title FROM('

                    // offers that are started and available
                    . '(SELECT 1 AS offer_started, o.id, o.type, o.status, o.deleted, o.start_date, o.end_date, o.created_date'
                    . ' , o.creator_id, o.updated_date, o.updater_id FROM offers o WHERE domain = \'private\' AND status <> "draft" AND deleted = 0 AND '
                    . '(start_date IS NULL OR start_date <= UTC_TIMESTAMP()) AND (end_date IS NULL OR end_date >= UTC_TIMESTAMP()) )'

                    . ' UNION ALL '

                    // offers that are not yet started but available
                    . '(SELECT 0 AS offer_started, o.id, o.type, o.status, o.deleted, o.start_date, o.end_date, o.created_date'
                    . ', o.creator_id, o.updated_date, o.updater_id FROM offers o WHERE domain = \'private\' AND status <> "draft" AND deleted = 0 AND '
                    . ' start_date > UTC_TIMESTAMP() AND (end_date IS NULL OR end_date >= UTC_TIMESTAMP()) )'

                    . ') AS o JOIN offer_locales l ON(l.domain = \'private\' AND l.offer_id = o.id) ORDER BY o.id ASC, l.locale = %1$s DESC) AS tb GROUP BY tb.id'
                    . ' ORDER BY tb.offer_started DESC, tb.created_date DESC'
                    , $this->conn->escape($this->kernel->request['locale'])
                    , $this->kernel->conf['escaped_timezone']
                );
                $statement = $this->conn->query($sql);
                $available_offers = $statement->fetchAll();

                $this->kernel->smarty->assign('available_offers', $available_offers);
            }

            // BreadCrumb
            $this->_breadcrumb->push($id && !$duplication ? new breadcrumbNode($this->kernel->dict['ACTION_edit']
                    , $this->kernel->sets['paths']['mod_from_doc'] . '?op=edit&id=' . $id)
                    : new breadcrumbNode(sprintf($this->kernel->dict['SET_operations']['new']
                    , $this->kernel->dict['SET_webpage_types'][$page_type]), $this->kernel->sets['paths']['mod_from_doc'] . '?op=edit'));

            $data['temp_folder'] = $temp_folder;

            $queries = array();
            foreach($_GET as $k => $v) {
                switch($k) {
                    case "parent":
                        $queries[$k] = $parent_id;
                        break;
                    case "t":
                        $queries[$k] = $default_platform;
                        break;
                    default:
                        $queries[$k] = trim($v);
                        break;
                }
            }
            $queries["op"] = "edit";

            $this->kernel->smarty->assign('templates', $templates);
            $this->kernel->smarty->assign('data', $data);
            $this->kernel->smarty->assign('id', $id);
            $this->kernel->smarty->assign('root_page', $root_page);
            $this->kernel->smarty->assign('page_type', $page_type);
            $this->kernel->smarty->assign('query_str', http_build_query($queries));
            $this->kernel->smarty->assign('default_order', $default_order);
            $this->kernel->smarty->assign('current_webpage_rights', $current_webpage_rights);

            $public_role_options = $this->publicRoleTree->generateOptions(false);
            $role_keys = array_keys($public_role_options);
            $role_keys = array_map('substr', array_keys($public_role_options), array_fill(0,count($public_role_options),1));

            $public_role_options = array_combine($role_keys, array_values($public_role_options));

            $this->kernel->smarty->assign('public_role_options', $public_role_options);
            $this->kernel->smarty->assign('locale_set', $this->user_accessible_locales);
            $this->kernel->smarty->assign('locale_option', $this->user_accessible_region_locales);
            $this->kernel->smarty->assign('default_locale_read_only', $this->user_default_locale_read_only);
            $this->kernel->smarty->assign('default_locale', $this->kernel->default_public_locale);
            $this->kernel->smarty->assign('structured_page_template', $structured_page_template);

            $this->kernel->smarty->assign('hasPageFolderRight', $this->user->hasRights('file_admin', Right::VIEW));
            $this->kernel->smarty->assign('hasShareFolderRight', $this->user->hasRights('share_file_admin', Right::VIEW));

            $this->kernel->smarty->assign('page_specific_content', $this->kernel->smarty->fetch('module/webpage_admin/edit_' . $page_type . '.html'));
            $this->kernel->response['content'] = $this->kernel->smarty->fetch('module/webpage_admin/edit.html');
        }

    }

    function errorChecking(&$data, $platforms, $page_type, $id = 0, $root_page = false, $ignore_fields = array(), $isGlobalUser = false, $locales_to_save = array()) {
        $base_url = $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc'];
        //$locales = array_keys($this->kernel->sets['public_locales']);
        $locales = $this->user->getAccessibleLocales();
        $default_lan = '';
        $sql = 'SELECT alias FROM locales WHERE `default`=1 AND site=\'public_site\' LIMIT 0,1';
        $statement = $this->conn->query( $sql );
        extract( $statement->fetch() );
        $default_lan = $alias;

        $errors = array();

        if ( !in_array($page_type, array('static', 'webpage_link', 'url_link', 'structured_page')) )
        {
            $errors["errorsStack"][] = 'page_type_invalid';
        }

        // check following fields to ensure they are not empty
        // locale
        $ary = array('webpage_title');
        $locale_require_fields = array();
        foreach($ary as $itm) {
            $data[$itm] = array_map('trim', array_ifnull($data, $itm, array()));
            foreach($locales as $locale) {
                if($data['status'] != 'draft' && (!isset($data[$itm][$locale]) || $data[$itm][$locale] === "") ) {
                    //if(($isGlobalUser&&$locale==$default_lan) || !$isGlobalUser)
                        $locale_require_fields[$itm . "[{$locale}]"][] = $itm . '_empty';
                }
            }
        }

        //if(!$isGlobalUser)
        //{
            $no_input_locale_count = 0;
            $no_inner_content_locale_count = 0;
            $tmp_wrapper = array();
            // need to check all fields for that locale is empty or not
            $locale_fields = array();
            switch($page_type) {
                case "static":
                    $locale_fields = array('seo_title', 'headline_title', 'content', 'seo_keywords', 'seo_description');
                case "webpage_link":
                    break;
                case "url_link":
                    $locale_fields = array('url');
                    break;
            }

            $error_locales = array(); // Summarize what locales have errors
            foreach($locale_require_fields as $n => $locale_error_fields) {
                $has_inner_content = false;
                $locale = preg_replace('#^.*?\[(.+)\]$#', '\\1', $n);

                foreach($locale_fields as $fname) {
                    switch($fname) {
                        case "content":
                            foreach($this->kernel->dict['SET_content_types'] as $platform => $platform_content_blocks) {
                                foreach(array_keys($platform_content_blocks) as $content_block) {
                                    if(isset($data[$fname]) && isset($data[$fname][$platform][$locale]) && !is_null($data[$fname][$platform][$locale][$content_block]) && $data[$fname][$platform][$locale][$content_block] !== '') {
                                        $has_inner_content = true;
                                    }
                                }
                            }
                            break;
                        default:
                            if(isset($data[$fname][$locale]) && !is_null($data[$fname][$locale]) && $data[$fname][$locale] !== '') {
                                $has_inner_content = true;
                            }
                            break;
                    }

                    if($has_inner_content)
                        break;
                }

                $locale_error_fields = array($n => $locale_error_fields);
                if(($data['status'] != 'draft' && !$has_inner_content)) { // all fields for that locale have not entered at all
                    $errors = array_merge($errors, $locale_error_fields);
                    $error_locales[] = $locale;
                } else {
                    $no_input_locale_count++;
                    $tmp_wrapper = array_merge($tmp_wrapper, $locale_error_fields);
                }
                if(!$has_inner_content) {
                    $no_inner_content_locale_count++;
                }
            }

            if($no_input_locale_count == count($this->kernel->sets['public_locales']) || $root_page) {
                $errors = array_merge($errors, $tmp_wrapper);
                if(count($tmp_wrapper)>0)
                    $error_locales = $locales;
            }
            if($no_inner_content_locale_count == count($locales)) {
                $errors["errorsStack"][] = $this->kernel->dict['ERROR_region_blank'];
            }
            else {
                foreach($locale_require_fields as $n => $locale_error_fields) {
                    $locale = preg_replace('#^.*?\[(.+)\]$#', '\\1', $n);
                    unset($errors[$n]);
                    $error_locales = array_diff($error_locales, array($locale));
                }
            }

            if($page_type == "url_link") {
                $itm = 'url';
                foreach($locales as $locale) {
                    if(isset($data['webpage_title'][$locale]) && isset($data[$itm]) && isset($data[$locale][$itm]))
                    {
                        if(($no_input_locale_count == count($this->kernel->sets['public_locales'])
                            || $data['webpage_title'][$locale]) && $data[$itm][$locale] === '') {
                            $errors[$itm . "[{$locale}]"][] = $itm . '_empty';
                            $error_locales[] = $locale;
                        } elseif($data[$itm][$locale] !== '') {
                            if(filter_var($data[$itm][$locale], FILTER_VALIDATE_URL) === FALSE) {
                                $data[$itm][$locale] = preg_replace("#^/+#", "/", '/' . $data[$itm][$locale]);
                            }

                            // Change from absolute to relative URL for internal URL
                            if ( strpos($data[$itm][$locale], $base_url) === 0 )
                            {
                                $data[$itm][$locale] = substr(
                                    $data[$itm][$locale],
                                    strlen($base_url)
                                );
                            }
                        }
                    }
                }
            }

            // check publish date and removal dates
            foreach ( $locales_to_save as $locale )
            {
                $data['publish_date'][$locale] = isset( $data['publish_date'][$locale] )
                    ? convert_tz( string_to_date($data['publish_date'][$locale], TRUE), $this->kernel->conf['timezone'], 'gmt' )
                    : NULL;
                $data['removal_date'][$locale] = isset( $data['removal_date'][$locale] )
                    ? convert_tz( string_to_date($data['removal_date'][$locale], TRUE), $this->kernel->conf['timezone'], 'gmt' )
                    : NULL;

                if ( !is_null($data['publish_date'][$locale]) )
                {
                    $sql ='SELECT publish_date FROM webpage_locales';
                    $sql .= " WHERE domain = 'private' AND webpage_id = :id AND locale = :locale AND status = 'approved'";
                    $sql .= ' ORDER BY major_version DESC, minor_version DESC';
                    $sql = strtr( $sql, array_map(array($this->kernel->db, 'escape'), array(
                        ':id' => $id,
                        ':locale' => $locale
                    )) );
                    $statement = $this->kernel->db->query($sql);
                    if ( ($record = $statement->fetch())
                        && !is_null($record['publish_date'])
                        && $data['publish_date'][$locale] < $record['publish_date'] )
                    {
                        $errors["publish_schedule[$locale]"][] = 'publish_date_range_invalid';
                        $error_locales[] = $locale;
                    }
                }

                if ( !is_null($data['publish_date'][$locale])
                    && !is_null($data['removal_date'][$locale])
                    && $data['publish_date'][$locale] > $data['removal_date'][$locale] )
                {
                    $errors["publish_schedule[$locale]"][] = 'removal_date_range_invalid';
                    $error_locales[] = $locale;
                }
            }

            $error_locales = array_unique($error_locales);
            $error_locale_names = array();
            foreach($error_locales as $locale_alias)
            {
                $error_locale_names[] = $this->kernel->sets['public_locales'][$locale_alias];
            }
            if(count($error_locale_names)>0 && !in_array($this->kernel->dict['ERROR_region_blank'], $errors["errorsStack"]))
            {
                $errors["errorsStack"][] = sprintf($this->kernel->dict['ERROR_region_contents'], implode(', ', $error_locale_names));
            }
        //}

        $ary = array(
            'order_index' => array(
                'pointer' => 'order_index',
                'method' => 'floatval',
                'draft_required' => false
            )
        );

        // platform
        switch($page_type) {
            case "static":
                $ary= array_merge($ary, array(
                                             'template' => array(
                                                 'pointer' => 'template',
                                                 'method' => 'intval',
                                                 'draft_required' => false
                                             ),
                                             'child_order_field' => array(
                                                 'pointer' => 'child_order_field',
                                                 'draft_required' => false
                                             ),
                                             'child_order_direction' => array(
                                                 'pointer' => 'child_order_direction',
                                                 'draft_required' => false
                                             )
                                        ));
                break;
            case "webpage_link":
                $ary= array_merge($ary, array(
                                             'linked_page_id' => array(
                                                 'pointer' => 'linked_page_text',
                                                 'method' => 'intval',
                                                 'draft_required' => false
                                             ),
                                             'target' => array(
                                                 'pointer' => 'target',
                                                 'draft_required' => false
                                             )
                                        ));
                break;
            case "url_link":
                $ary= array_merge($ary, array(
                                             'target' => array(
                                                 'pointer' => 'target',
                                                 'draft_required' => false
                                             )
                                        ));
                break;
            default:
                break;
        }

        if(!$root_page) {
            if(!in_array('webpage_parent_id', $ignore_fields)) {
                $ary['webpage_parent_id'] = array(
                    'pointer' => 'webpage_parent_text',
                    'method' => 'intval',
                    'draft_required' => true
                );
            }
            if(!in_array('alias', $ignore_fields)) {
                $ary['alias'] = array(
                    'pointer' => 'alias',
                    'draft_required' => true
                );
            }
        }

        foreach($ary as $field => $info) {
            $data[$field] = array_map('trim', array_ifnull($data, $field, array()));

            foreach($platforms as $platform) {
                if(($data['status'] != 'draft' || $info['draft_required'])
                    && (!isset($data[$field][$platform]) || $data[$field][$platform] === "") ) {
                    $errors[$info['pointer'] . "[{$platform}]"][] = $info['pointer'] . '_empty';
                } elseif(isset($data[$field][$platform]) && $data[$field][$platform] !== "" && isset($info['method'])) {
                    if($info['method'] == 'intval') {
                        if(!preg_match("#\-?((?:[0-9])|(?:[1-9][0-9]*))#i", $data[$field][$platform]))
                            $errors[$info['pointer'] . "[{$platform}]"][] = $info['pointer'] . '_invalid';
                    } elseif ($info['method'] == 'floatval') {
                        if(!is_numeric($data[$field][$platform]))
                            $errors[$info['pointer'] . "[{$platform}]"][] = $info['pointer'] . '_invalid';

                    }
                }
            }

            // make sure the data is correct to insert
            if(isset($info['method'])) {
                $data[$field] = array_map($info['method'], $data[$field]);
            }
        }

        if(count($platforms) < 1) {
            $errors["platforms[]"][] = 'platforms_empty';
        } else {
            if($id) {
                foreach($platforms as $platform) {
                    if(!isset($errors["webpage_parent_text[{$platform}]"])) {
                        $sitemap = $this->get_sitemap('edit', $platform);
                        $root = $sitemap->getRoot();

                        if($root) {
                            $tp = $root->findById($id);
                            // move parent to child
                            if(!$root_page && !in_array('parent', $ignore_fields) && $tp
                                && ($tp->getItem()->getId() == $data["webpage_parent_id"][$platform] || $tp->findById($data["webpage_parent_id"][$platform]))
                            ) {
                                $errors["webpage_parent_id[{$platform}]"][] = 'invalid_parent_node';
                            }
                        }

                    }
                }
            }

            // webpage link error checking
            if($page_type == "webpage_link") {
                foreach($platforms as $platform) {
                    if(!isset($errors["linked_page_text[{$platform}]"]) && $_POST["linked_page_id"][$platform] == $id) {
                        $errors["linked_page_text[{$platform}]"][] = 'linked_page_id_collided';
                    } else {
                        // check if the pointing page exists
                        $sql = sprintf('SELECT * FROM webpages WHERE domain = \'private\' AND id = %d ORDER BY major_version DESC, minor_version DESC LIMIT 0,1'
                            , intval($data["linked_page_id"][$platform]));
                        $statement= $this->conn->query($sql);

                        if(!isset($errors["linked_page_text[{$platform}]"]) && (!($record = $statement->fetch()) || $record['deleted'])) {
                            $errors["linked_page_text[{$platform}]"][] = 'linked_page_not_exists';
                        }
                    }
                }
            }
        }

        if(!isset($this->kernel->dict['SET_webpage_statuses'][$data['status']])) {
            $errors["errorsStack"][] = 'webpage_status_invalid';
        }

        return $errors;
    }

    function getPageLinkData($parent_path, $platform) {
        $data = array(
            'webpage_text' => '',
            'webpage_id' => '',
            'webpage_path' => ''
        );

        // get its parent page no matter what status that page currently is
        /*
        $sql = sprintf('SELECT * FROM(SELECT wp.*, l.webpage_title'
            . ' FROM( SELECT * FROM ( SELECT w.*, wp.webpage_id, wp.path, wp.platform, wp.deleted AS platform_deleted'
            . ' FROM webpages w JOIN webpage_platforms wp'
            . ' ON(w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version)'
			. ' JOIN webpage_versions wv ON (wv.id=w.id AND wv.domain=w.domain AND wv.major_version=w.major_version AND wv.minor_version=wv.minor_version)'
            . " WHERE w.domain = 'private'"
            //. ' ORDER BY id, major_version DESC, minor_version DESC, platform = %2$s DESC) AS tb GROUP BY tb.webpage_id) AS wp '
            . ' ORDER BY platform = %2$s DESC) AS tb GROUP BY tb.webpage_id) AS wp '

            . ' LEFT JOIN (SELECT * FROM webpage_locales ORDER BY major_version DESC, minor_version DESC, locale = %3$s DESC, locale ASC)'
            . ' l ON(l.domain = wp.domain AND l.webpage_id = wp.webpage_id AND l.major_version = wp.major_version AND l.minor_version = wp.minor_version)'

            . ' WHERE path = %1$s AND deleted=0 LIMIT 0, 1) AS tb WHERE (tb.platform_deleted = 0 OR tb.status <> "approved")' // filtered deleted page that with the same alias before limit 1 record

            , $this->conn->escape(($parent_path ? (preg_match("#^\/#", $parent_path) ? "" : "/") . $parent_path : "") . (preg_match("#\/$#", $parent_path) ? "" : "/"))
            , $this->conn->escape($platform)
            , $this->conn->escape($this->kernel->default_public_locale)
        );
        */
        $sql = sprintf('SELECT w.id AS webpage_id, SUBSTRING_INDEX(GROUP_CONCAT(wp.path ORDER BY wp.major_version DESC, wp.minor_version DESC), \',\', 1) AS path,'
            . ' SUBSTRING_INDEX(GROUP_CONCAT(wp.deleted ORDER BY wp.major_version DESC, wp.minor_version DESC), \',\', 1) AS deleted,'
            . ' SUBSTRING_INDEX(GROUP_CONCAT(wl.webpage_title ORDER BY wp.major_version DESC, wp.minor_version DESC, locale = %3$s DESC, locale ASC SEPARATOR \'\r\n\'), \'\r\n\', 1) AS webpage_title,'
            . ' SUBSTRING_INDEX(GROUP_CONCAT(wl.status ORDER BY wp.major_version DESC, wp.minor_version DESC, locale = %3$s DESC, locale ASC), \',\', 1) AS status'
            . ' FROM webpages AS w'
            . ' JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version)'
            . ' JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)'
            . ' WHERE w.domain = \'private\' AND wp.platform = %2$s'
            . ' GROUP BY w.id'
            . ' HAVING path = %1$s AND (deleted = 0 OR status <> \'approved\')'
            , $this->conn->escape(($parent_path ? (preg_match("#^\/#", $parent_path) ? "" : "/") . $parent_path : "") . (preg_match("#\/$#", $parent_path) ? "" : "/"))
            , $this->conn->escape($platform)
            , $this->conn->escape($this->kernel->default_public_locale)
        );

        $statement = $this->conn->query($sql);

        if($record = $statement->fetch()) {
            $data['webpage_text'] = sprintf('%s - [#%d]'
                , $record['webpage_title'], $record['webpage_id']);
            $data['webpage_id'] = $record['webpage_id'];
            $data['webpage_path'] = $record['path'];
        }

        return $data;
    }

    static function updateOfferLinkage(page $me, pageNode $newParent) {
        /** @var admin_module $module */
        $module = kernel::$module;
        $conn = $module->conn;

        foreach(array_keys($module->kernel->dict['SET_webpage_page_types']) as $platform) {
            $sm = $module->get_sitemap('edit', $platform);
            $root = $sm->getRoot();

            if($root)
                break;
        }
        $myNode = null;
        $subsqls = array();

        // process only if root page is found
        if(!is_null($root) && $root) {
            $myNode = $root->findById($me->getId());

            if(in_array($me->getType(), array('static', 'structured_page')) && $me->getOfferSource() == "specific") {
                $node = $myNode;
                $new_inherited_parent_id = $me->getId();
            } else {
                /** @var pageNode $node */
                $node = $newParent;

                if(is_null($node) || !$node) {
                    $sm = $module->get_sitemap('edit', $platform);

                    $node = $sm->getRoot();
                }

                while($node && $node->getLevel() > 0) {
                    if(in_array($node->getItem()->getType(), array('static', 'structured_page')) && $node->getItem()->getOfferSource() == "specific") {
                        break;
                    }

                    $node = $node->getParent();
                }
            }

            if($node) {
                $new_inherited_parent_id = $node->getItem()->getId();
            }

            // find all its children inherited to previous parent to inherited to another parent
            // can get previous parent by previous sitemap because it has not updated
            // (only the data inside the page node updated)
            if(in_array($me->getType(), array('static', 'structured_page')) && $me->getOfferSource() == "inherited") {
                $subsqls[] = sprintf('(\'private\', %d, %d)', $me->getId(), $new_inherited_parent_id);
            }
        }

        $inheriting_children = webpage_admin_module::getInheritingChildren($myNode);

        if(isset($myNode) && !is_null($myNode) && $myNode) {
            // get all its children
            $children = $myNode->getChildren();

            /** @var pageNode $child */
            $child = null;
            foreach($children as $child) {
                if(in_array($child->getItem()->getId(), $inheriting_children)
                    && in_array($child->getItem()->getType(), array('static', 'structured_page')) && $child->getItem()->getOfferSource() == "inherited") {
                    $subsqls[] = sprintf('(\'private\', %d, %d)', $child->getItem()->getId(), $new_inherited_parent_id);
                }
            }
        }

        if(count($subsqls)) {
            $sql = sprintf('REPLACE INTO webpage_offer_inheritences(domain, webpage_id, inherited_from_webpage)'
            . ' VALUES %s', implode(',', $subsqls));
            $conn->exec($sql);
        }
    }

    static function getInheritingChildren($node) {
        $ary = array();

        if(!is_null($node) && $node) {
            $children = $node->getChildren(0);
            foreach($children as $child) {
                if(in_array($child->getItem()->getType(), array('static', 'structured_page')) && $child->getItem()->getOfferSource() == "inherited") {
                    $ary[] = $child->getItem()->getId();
                    $ary = array_merge($ary, webpage_admin_module::getInheritingChildren($child));
                }
            }
        }

        return $ary;
    }

    /**
     * Move the page and its descendants to a new page
     *
     * @param $old_prefix
     * @param $new_prefix
     * @param $page_type
     * @param $platform
     * @param $status
     * @return int
     */
    function move_descendant($old_prefix, $new_prefix, $page_type, $platform, $status = "") {
        if($old_prefix != $new_prefix) {

            $domain_condition = $page_type == "private" ? " WHERE w.domain = 'private'" : "";

            // level changes
            $lvl_change = count(array_filter(explode('/', $new_prefix), 'strlen')) - count(array_filter(explode('/', $old_prefix), 'strlen'));

            $sql = sprintf('UPDATE webpage_platforms p1 JOIN ('
				//.'SELECT * FROM(SELECT * FROM webpage_platforms '
                //. $domain_condition
                //. ' ORDER BY major_version DESC, minor_version DESC) AS tmp GROUP BY webpage_id'
				. 'SELECT w.* FROM webpage_platforms w JOIN webpage_versions wv ON (w.webpage_id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version) '. $domain_condition
                . ') AS tb ON (tb.domain = p1.domain AND tb.webpage_id = p1.webpage_id AND tb.major_version = p1.major_version AND tb.minor_version = p1.minor_version)'
                . ' SET p1.path = CONCAT(%2$s, SUBSTRING(p1.path FROM %3$d)), p1.level = p1.level + (1 *%5$d)'
                . ' WHERE INSTR(p1.path, %1$s) = 1'
                . ' AND p1.platform = %4$s'
                , $this->kernel->db->escape($old_prefix)
                , $this->kernel->db->escape($new_prefix)
                , strlen($old_prefix) + 1
                , $this->conn->escape($platform)
                , $lvl_change);
            $rows_count = $this->conn->exec($sql);

            if($status) {
                $sql = sprintf('UPDATE webpage_locales wl LEFT JOIN('
                    . 'SELECT tb.* FROM webpage_platforms p1 JOIN ('
					//. 'SELECT * FROM(SELECT * FROM webpages '. $domain_condition
                    //. ' ORDER BY major_version DESC, minor_version DESC) AS tmp GROUP BY domain, id'
					. 'SELECT w.* FROM webpages w JOIN webpage_versions wv ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version) '. $domain_condition
                    . ' ) AS tb ON (tb.domain = p1.domain AND tb.id = p1.webpage_id AND tb.major_version = p1.major_version AND tb.minor_version = p1.minor_version)'
                    . ' WHERE INSTR(p1.path, %1$s) = 1'
                    . ' AND p1.platform = %2$s'
                    . ') wp ON(wp.domain = wl.domain AND wp.id = wl.webpage_id AND wp.major_version = wl.major_version AND wp.minor_version = wl.minor_version)'
                    . ' SET wl.status = %3$s'
                    , $this->conn->escape($new_prefix)
                    , $this->conn->escape($platform)
                    , $this->conn->escape($status));
                $this->conn->exec($sql);

            }

            return $rows_count;
        }

        return 0;
    }

    /**
     * Move a webpage based on webpage ID.
     *
     * @since   2013-07-23
     */
    function move()
    {
        // Get data from query string
        $id = intval( array_ifnull($_REQUEST, 'id', 0) );
        $ajax = (bool)intval(array_ifnull($_REQUEST, 'ajax', 0));
        $data = array();
        $errors = array();
		$locales_to_publish = array();

        $movable_webpages = array();
        $sql = sprintf('SELECT * FROM role_webpage_rights WHERE role_id=%d AND `right`=%d', $this->user->getRole()->getId(), Right::EDIT);
        $statement = $this->conn->query($sql);
        while($r = $statement->fetch())
        {
            if(in_array($r['webpage_id'], $this->_accessible_webpages))
            {
                $key = array_keys($this->_accessible_webpages, $r['webpage_id']);
                $movable_webpages[$key[0]] = $r['webpage_id'];
            }

        }

        try {
            $this->conn->beginTransaction();
            // see if the webpage really exists
            $sql = sprintf('SELECT id, major_version, minor_version, `type`, offer_source'
                . ', deleted, created_date, creator_id'
                . ' FROM webpages WHERE domain = \'private\' AND id = %1$d'
                . ' ORDER BY major_version DESC, minor_version DESC'
                . ' LIMIT 0, 1'
                , $id);
            $statement = $this->conn->query($sql);

            if(!($data['webpage'] = $statement->fetch())) {
                throw new generalException("page_not_exists", "html", NULL, NULL, 0, NULL, false);
            }

            $sql = sprintf('SELECT status, updated_date, updater_id, locale FROM webpage_locales WHERE webpage_id=%1$d AND domain=\'private\' AND major_version=%2$d AND minor_version=%3$d ORDER BY updated_date DESC'
                , $id, $data['webpage']['major_version'], $data['webpage']['minor_version']);
            $statement = $this->conn->query($sql);
            for($i=0; $record = $statement->fetch(); $i++)
            {
                if($i==0)
                {
                    $data['webpage']['status']=array($record['status']);
                    $data['webpage']['updated_date']=$record['updated_date'];
                    $data['webpage']['updater_id']=$record['updater_id'];
                }
                else
                {
                    if(!in_array($record['status'], $data['webpage']['status']))
                        $data['webpage']['status'][]=$record['status'];
                }

				if($record['status'] == 'approved')
				{
					$locales_to_publish[] = $record['locale'];
				}
            }

            foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
                $sm = $this->get_sitemap('edit', $platform);

                $node = $sm->getRoot()->findById($id);

                if($node) {
                    $data['webpage']['webpage_title'] = $node->getItem()->getTitle(null, true);
                    break;
                }
            }

            $sql = sprintf('SELECT * FROM webpage_platforms WHERE domain = \'private\' AND webpage_id = %d'
                            . ' AND major_version = %d AND minor_version = %d'
                            , $data['webpage']['id'], $data['webpage']['major_version'], $data['webpage']['minor_version']
                        );
            $statement = $this->conn->query($sql);
            $data['webpage']['platforms'] = array();
            while($row = $statement->fetch()) {
                $data['webpage']['platforms'][] = $row;
                $site_tree[$row['platform']] = webpage_admin_module::getWebpageNodes('html', $row['platform'], $id, true, $movable_webpages); //Performance enhancement
            }

			$site_tree_keys = array_keys($site_tree);
            $default_platform = array_shift($site_tree_keys);

            if(count($_POST)) {
                $_POST['parent'] = array_map('intval', array_ifnull($_POST, 'parent', array()));
                $new_parents = array();

                // error checking
                foreach(array_keys($site_tree) as $platform) {
                    if(!isset($_POST['parent'][$platform]) || !$_POST['parent'][$platform]) {
                        $errors["parent[{$platform}]"][] = 'parent_empty';
                    } else {
                        $sitemap = $this->get_sitemap('edit', $platform);
                        $tp = $sitemap->getRoot()->findById($id);
                        // move parent to child
                        if(!($new_parent = $sitemap->getRoot()->findById($_POST['parent'][$platform]))) {
                            $errors["parent[{$platform}]"][] = 'parent_not_exists';
                        } elseif($tp->getItem()->getId() == $_POST['parent'][$platform] || $tp->findById($_POST['parent'][$platform])) {
                            $errors["parent[{$platform}]"][] = 'invalid_parent_node';
                        }

                        $new_parents[$platform] = $new_parent->getItem()->getRelativeUrl($platform);
                    }

                }

                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                }

                // continue going on if no error is thrown
                if(in_array('approved', $data['webpage']['status']) || in_array('pending', $data['webpage']['status'])) {
                    // make archive (new version)
                    $version_info = $this->archive($id, null, null, false);

                    if(in_array($data['webpage']['type'], array('static', 'url_link', 'structured_page'))) {
                        // Copy all files in old archive folder to the new archive folder
                        $this->pageFilesCopy(array('type' => 'archive',
                                                    'sub_path' => "{$data['webpage']['major_version']}_{$data['webpage']['minor_version']}")
                                            , array('type' => 'archive',
                                                    'sub_path' => "{$version_info['major_version']}_{$version_info['minor_version']}")
                                            , $id);
                    }
                }

                $sm = $this->get_sitemap('edit', $default_platform);
                if(isset($new_parents['desktop']) && $new_parents[$default_platform]) {
                    $parent = $sm->findPage($new_parents[$default_platform]);
                } else {
                    $parent = $sm->getRoot();
                }

                $item = $sm->getRoot()->findById($id)->getItem();

                //webpage_admin_module::updateOfferLinkage($item, $parent);

                foreach($data['webpage']['platforms'] as $platform_data) {
                    $alias = preg_replace('#^.*?([^\/]*)\/$#i', "\\1", $platform_data['path']);
                    $new_path = $new_parents[$platform_data['platform']] . $alias . '/';

                    /*
                    $sql = sprintf('SELECT * FROM ('
						//. 'SELECT * FROM (SELECT * FROM webpages WHERE domain = \'private\' ORDER BY id, major_version DESC,'
                        //. ' minor_version DESC) AS w GROUP BY domain, id'
						. 'SELECT w.* FROM webpages w JOIN webpage_versions wv ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version) WHERE w.domain="private"'
                        . ' ) AS w JOIN webpage_platforms p ON(p.domain = w.domain AND p.webpage_id = w.id AND w.major_version = p.major_version'
                        . ' AND w.minor_version = p.minor_version)'
                        . ' LEFT JOIN ('
						//. ' SELECT webpage_id, status, major_version, minor_version FROM(SELECT * FROM webpage_locales WHERE domain = \'private\' ORDER BY webpage_id, major_version DESC, minor_version DESC, FIELD(status, "draft", "pending", "approved")) AS wl GROUP BY webpage_id'
						. 'SELECT webpage_id, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY FIELD (status, "draft", "approved")), ",", 1) AS status, major_version, minor_version FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.webpage_id=wv.id AND wl.domain=wv.domain AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE wl.domain="private") AS wl GROUP BY webpage_id'
						. ') AS wl ON (wl.webpage_id=w.id AND wl.major_version=w.major_version AND wl.minor_version=w.minor_version)'
                        . ' WHERE (p.deleted = 0 OR wl.status <> "approved") AND p.platform = %1$s AND path = %2$s'
                        . ' AND w.id <> %3$d'
                        , $this->conn->escape($platform_data['platform']), $this->conn->escape($new_path), $id);
                    $statement = $this->conn->query($sql);
                    if($statement->fetch()) {
                        throw new fieldsException(array('errorStack' => 'alias_collide'));
                    }
                    else{
                        $sql = 'SELECT COUNT(*) AS collide FROM webpages AS w';
                        $sql .= ' JOIN webpage_versions AS wv ON (w.domain = wv.domain AND w.id = wv.id AND w.major_version = wv.major_version AND w.minor_version = wv.minor_version)';
                        $sql .= ' JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version AND wp.platform = %s AND wp.deleted = 0)';
                        $sql .= ' LEFT OUTER JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)';
                        $sql .= " WHERE w.domain = 'private' AND w.deleted = 0 AND wl.webpage_id IS NULL";
                        $sql .= ' AND w.id <> %d AND wp.path = %s';
                        $sql = sprintf(
                            $sql,
                            $this->conn->escape( $platform_data['platform'] ),
                            $id,
                            $this->conn->escape( $new_path )
                        );
                        $statement = $this->kernel->db->query( $sql );
                        extract($statement->fetch());
                        if($collide) {
                            throw new fieldsException(array('errorStack' => 'alias_collide'));
                        }
                    }
                    */
                    $sql = 'SELECT * FROM webpages AS w';
                    $sql .= ' JOIN webpage_versions AS wv ON (w.domain = wv.domain AND w.id = wv.id AND w.major_version = wv.major_version AND w.minor_version = wv.minor_version)';
                    $sql .= ' JOIN webpage_platforms AS wp ON (w.domain = wp.domain AND w.id = wp.webpage_id';
                    $sql .= ' AND w.major_version = wp.major_version AND w.minor_version = wp.minor_version AND wp.platform = %s AND wp.deleted = 0)';
                    $sql .= " WHERE w.domain = 'private' AND w.deleted = 0 AND w.id <> %d GROUP BY w.domain, w.id";
                    $sql .= " HAVING SUBSTRING_INDEX(GROUP_CONCAT(wp.path ORDER BY w.major_version DESC, w.minor_version DESC SEPARATOR '\r\n'), '\r\n', 1) = %s";
                    $sql = sprintf(
                        $sql,
                        $this->conn->escape( $platform_data['platform'] ),
                        $id,
                        $this->conn->escape( $new_path )
                    );
                    $statement = $this->kernel->db->query( $sql );
                    if($record = $statement->fetch()) {
                        throw new fieldsException(array('errorStack' => 'alias_collide'));
                    }

                    //$total_affected_rows = $this->move_descendant( $platform_data['path'], $new_path, 'private', $platform_data['platform'], $this->user->hasRights('webpage_admin', Right::APPROVE) ? "approved" : "pending");
					$total_affected_rows = $this->move_descendant( $platform_data['path'], $new_path, 'private', $platform_data['platform']); // keep the original status of the webpages to be moved

                    if($total_affected_rows) {
                        //Get the webpage title of the webpage
                        $sql = sprintf('SELECT webpage_title FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) JOIN locales l ON (wl.locale=l.alias) WHERE webpage_id=%1$d AND wv.domain=\'private\' ORDER BY l.order_index LIMIT 0,1', $id);
                        $statement = $this->conn->query($sql);
                        extract($statement->fetch());
                        $message = "User {$this->user->getId()} <{$this->user->getEmail()}> moved webpage $id ($webpage_title) and its descendant {$platform_data['platform']} webpages ($total_affected_rows pages).";
                        $this->kernel->log( 'message', $message, __FILE__, __LINE__ );
                    }
                }

                if($this->user->hasRights('webpage_admin', Right::APPROVE)) {
                    $this->publicize($id, true, $locales_to_publish);
                } else {
                    // send pending approval email
                    $this->send_pending_email( array($item), 'move', TRUE );
                }

                // continue to process (successfully)
                $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                    http_build_query(array(
                                          'op' => 'dialog',
                                          'type' => 'message',
                                          'code' => 'DESCRIPTION_saved',
                                          'redirect_url' => $this->kernel->sets['paths']['server_url']
                                          . $this->kernel->sets['paths']['mod_from_doc']
                                          . '?id=' . $id
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

                $this->conn->commit();

                return;
            }

            $this->conn->commit();

			$this->clear_private_cache();

        } catch(Exception $e) {
            $this->processException($e);

        }

        if($ajax) {

        } else {
            // For existing webpage, see if the webpage is not editable or locked
            if ( !$this->is_editable($id) || $this->is_locked($id) )
            {
                $this->kernel->redirect( '?id=' . $id );
                return;
            }

            $this->_breadcrumb->push(new breadcrumbNode(sprintf($this->kernel->dict['SET_operations']['move']
                                                            , $data['webpage']['platforms'][0]['path']), $this->kernel->sets['paths']['mod_from_doc'] . '?op=move&id=' . $id));

            $this->kernel->smarty->assign('data', $data);
            $this->kernel->smarty->assign('site_tree', $site_tree);
            $this->kernel->response['content'] = $this->kernel->smarty->fetch('module/webpage_admin/move.html');
        }
    }

    /**
     * delete a saved page
     *
     * @since 2013-07-24
     */
    function delete_page($publicize = true) {
        // Get data from query string
        $id = intval( array_ifnull($_REQUEST, 'id', 0) );
        $ajax = (bool)intval(array_ifnull($_REQUEST, 'ajax', 0));
        $data = array();
        $errors = array();
        $deleted_platforms = array();

        try {
            $this->conn->beginTransaction();
            // see if the webpage really exists
            $sql = sprintf('SELECT id, major_version, minor_version, `type`'
                . ', deleted, created_date, creator_id'
                . ' FROM webpages WHERE domain = \'private\' AND id = %1$d'
                . ' ORDER BY major_version DESC, minor_version DESC'
                . ' LIMIT 0, 1'
                , $id);
            $statement = $this->conn->query($sql);

            if(!($data['webpage'] = $statement->fetch())) {
                throw new generalException("page_not_exists", "html", NULL, NULL, 0, NULL, false);
            }

            $sql = sprintf('SELECT status, updated_date, updater_id FROM webpage_locales WHERE webpage_id=%1$d AND domain=\'private\' AND major_version=%2$d AND minor_version=%3$d ORDER BY updated_date DESC'
                , $id, $data['webpage']['major_version'], $data['webpage']['minor_version']);
            $statement = $this->conn->query($sql);
            for($i=0; $record = $statement->fetch(); $i++)
            {
                if($i==0)
                {
                    $data['webpage']['status']=array($record['status']);
                    $data['webpage']['updated_date']=$record['updated_date'];
                    $data['webpage']['updater_id']=$record['updater_id'];
                }
                else
                {
                    if(!in_array($record['status'], $data['webpage']['status']))
                        $data['webpage']['status'][]=$record['status'];
                }
            }


            foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
                $sm = $this->get_sitemap('edit', $platform);

                $node = $sm->getRoot()->findById($id);

                if($node) {
                    $data['webpage']['webpage_title'] = $node->getItem()->getTitle(null, true);
                    break;
                }
            }

            $sql = sprintf('SELECT * FROM webpage_platforms WHERE domain = \'private\' AND webpage_id = %d'
                . ' AND major_version = %d AND minor_version = %d'
                , $data['webpage']['id'], $data['webpage']['major_version'], $data['webpage']['minor_version']
            );
            $statement = $this->conn->query($sql);
            while($row = $statement->fetch()) {
                $data['webpage']['platforms'][$row['platform']] = $row;
                $data['platforms'][$row['platform']] = $this->kernel->dict['SET_webpage_page_types'][$row['platform']];
                if($row['deleted']) {
                    $deleted_platforms[] = $row['platform'];
                }
            }

            if(count($_POST)) {
                $_POST['delete_platform'] = array_map('trim', array_ifnull($_POST, 'delete_platform', array()));

                foreach($_POST['delete_platform'] as $k => $platform) {
                    if(!isset($this->kernel->dict['SET_webpage_page_types'][$platform])) {
                        unset($_POST['delete_platform'][$k]);
                    }
                }
                if(count($_POST['delete_platform']) == 0) {
                    // error checking
                    $errors["errorsStack"][] = 'platform_empty';
                }

                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                }

                // tables
                $tables = array( 'webpage_locales', 'webpage_locale_contents', 'webpage_platforms', 'webpage_permissions', 'webpages' );
                $table_columns = array();

                if(in_array('approved', $data['webpage']['status'])) {
                    foreach($tables as $tb_name) {
                        $sql = sprintf('SHOW COLUMNS FROM %1$s WHERE field <> "major_version"', $tb_name);
                        $statement = $this->conn->query($sql);

                        $columns = array();
                        while($row = $statement->fetch()) {
                            $columns[] = $row['Field'];
                        }

                        $table_columns[$tb_name] = $columns;

                        $sql = sprintf('INSERT INTO %1$s (%2$s, major_version)'
                            . ' (SELECT %3$s, (t.major_version + 1) AS major_version FROM %1$s t'
                            . ' WHERE t.domain = \'private\' AND t.%4$s = %5$d AND t.major_version = %6$d AND t.minor_version = %7$d)'
                            , $tb_name
                            , '`' . implode('`, `', $columns). '`'
                            , 't.`' . implode('`, t.`', $columns) . '`'
                            , $tb_name == "webpages" ? 'id' : 'webpage_id'
                            , $id, $data['webpage']['major_version'], $data['webpage']['minor_version']
                        );
                        $this->conn->exec($sql);
                    }

                    $data['webpage']['major_version']++;
                    $data['webpage']['minor_version'] = 0;
                }

                $sql = sprintf('UPDATE webpage_platforms SET deleted = 0 WHERE domain = \'private\' AND webpage_id = %d'
                    . ' AND major_version = %d AND minor_version = %d'
                    , $id, $data['webpage']['major_version'], $data['webpage']['minor_version']
                    );
                $this->conn->exec($sql);

                $sql = sprintf('UPDATE webpage_platforms SET deleted = 1 WHERE domain = \'private\' AND webpage_id = %d'
                            . ' AND major_version = %d AND minor_version = %d AND platform IN(%s)'
                            , $id, $data['webpage']['major_version'], $data['webpage']['minor_version']
                            , implode(', ', array_map(array($this->conn, 'escape'), $_POST['delete_platform'])));
                $this->conn->exec($sql);

                $status = $this->user->hasRights($this->module, Right::APPROVE) ? "approved" : "pending";

                $sql = sprintf('UPDATE webpages SET deleted = %d WHERE domain = \'private\' AND id = %d'
                                . ' AND major_version = %d AND minor_version = %d'
                                , count($_POST['delete_platform']) == count($data['platforms']) ? 1 : 0
                                , $id, $data['webpage']['major_version'], $data['webpage']['minor_version']);
                $this->conn->exec($sql);

                $sql = sprintf('UPDATE webpage_locales SET updated_date = UTC_TIMESTAMP(), status = %s, updater_id = %d WHERE domain = \'private\' AND webpage_id = %d'
                        . ' AND major_version = %d AND minor_version = %d'
                        , $this->conn->escape($status)
                        , $this->user->getId()
                        , $id, $data['webpage']['major_version'], $data['webpage']['minor_version']);
                $this->conn->exec($sql);

                // Performance enhancement
                $sql = sprintf('UPDATE webpage_versions SET major_version=%d WHERE domain = \'private\' AND id = %d'
                        , $data['webpage']['major_version']
                        , $id);
                $this->conn->exec($sql);

                foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
                    $sm = $this->get_sitemap('edit', $platform);

                    $node = $sm->getRoot()->findById($id);

                    if($node) {
                        $page = $node->getItem();
                        break;
                    }
                }

                if($status == "approved") {
                    $ids_affected = array();
                    foreach($_POST['delete_platform'] as $i => $platform) {
                        $ids_affected[$platform] = array();

                        // delete its decedents
                        $sql = sprintf('SELECT * FROM('
                                    //. 'SELECT * FROM(SELECT * FROM webpage_platforms'
                                    //. ' WHERE domain = \'private\' AND platform = %1$s'
                                    //. ' ORDER BY webpage_id, major_version DESC, minor_version DESC'
                                    //. ') AS tmp GROUP BY tmp.webpage_id'
									. 'SELECT wp.* FROM webpage_platforms wp JOIN webpage_versions wv  ON (wp.webpage_id=wv.id AND wp.domain=wv.domain AND wp.major_version=wv.major_version AND wp.minor_version=wv.minor_version) WHERE wp.domain = "private" AND platform = %1$s '
									. ') AS p WHERE INSTR(p.path, %2$s) = 1 AND p.deleted = 0'
                                    , $this->conn->escape($platform)
                                    , $this->conn->escape($data['webpage']['platforms'][$platform]['path']));
                        $statement = $this->conn->query($sql);
                        while($row = $statement->fetch()) {
                            $ids_affected[$platform][] = $row['webpage_id'];
                        }

                        if(count($ids_affected[$platform])) {
                            $ids_updated = array(0);
                            // copy as a new version for all decendent pages as they are going to be deleted

                            foreach($tables as $tb_name) {
                                $columns = $table_columns[$tb_name];
                                if(count($columns)) {
                                    $sql = sprintf('INSERT INTO %1$s (%4$s, major_version)'
                                        . ' (SELECT %5$s, (t.major_version + 1) AS major_version FROM %1$s t'
                                        . ' JOIN (SELECT * FROM('
                                        //. 'SELECT * FROM(SELECT domain, id, major_version, minor_version, deleted'
                                        //. ' FROM webpages w WHERE w.domain = \'private\' AND w.id IN(%2$s) AND w.id NOT IN(%3$s)'
                                        //. ' ORDER BY id, major_version DESC, minor_version DESC'
                                        //. ') AS w GROUP BY w.domain, w.id) AS w'
										. 'SELECT w.domain, w.id, w.major_version, w.minor_version, deleted FROM webpages w JOIN webpage_versions wv ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version) WHERE w.domain = \'private\' AND w.id IN(%2$s) AND w.id NOT IN (%3$s) GROUP BY w.domain, w.id) AS w'
                                        . ' LEFT JOIN ('
										//. 'SELECT webpage_id, status FROM(SELECT * FROM webpage_locales WHERE domain = \'private\' AND webpage_id IN(%2$s) AND webpage_id NOT IN(%3$s) ORDER BY webpage_id, major_version DESC, minor_version DESC, FIELD(status, "draft", "pending", "approved")) AS wl GROUP BY webpage_id'
										. 'SELECT webpage_id, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY FIELD (status, "draft", "approved")), ",", 1) AS status FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.webpage_id=wv.id AND wl.domain=wv.domain AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE wl.domain="private" AND webpage_id IN (%1$s)) AS wl GROUP BY webpage_id'
										. ') AS wl ON (wl.webpage_id=w.id) WHERE w.deleted = 0 OR wl.status <> "approved") AS tb ON(tb.domain = t.domain AND tb.id = t.%6$s'
                                        . ' AND tb.major_version = t.major_version AND tb.minor_version = t.minor_version))'
                                        , $tb_name
                                        , implode(', ', $ids_affected[$platform])
                                        , implode(', ', $ids_updated)
                                        , '`' . implode('`, `', $columns). '`'
                                        , 't.`' . implode('`, t.`', $columns) . '`'
                                        , $tb_name == "webpages" ? 'id' : 'webpage_id'
                                        );
                                    $this->conn->exec($sql);
                                }
                            }

                            $sql = sprintf('UPDATE webpage_platforms p JOIN (SELECT * FROM(SELECT * FROM('
                                //. 'SELECT domain, id, major_version, minor_version, deleted'
                                //. ' FROM webpages w WHERE w.domain = \'private\' AND w.id IN(%1$s)'
                                //. ' ORDER BY id, major_version DESC, minor_version DESC'
                                //. ') AS w GROUP BY w.domain, w.id) AS w'
                                . 'SELECT w.domain, w.id, w.major_version, w.minor_version, deleted FROM webpages w JOIN webpage_versions wv ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version) WHERE w.domain = \'private\' AND w.id IN(%1$s) GROUP BY w.domain, w.id) AS w'

								. ' LEFT JOIN ('
								//. 'SELECT webpage_id, status FROM(SELECT * FROM webpage_locales WHERE domain = \'private\' AND webpage_id IN(%1$s) ORDER BY webpage_id, major_version DESC, minor_version DESC, FIELD(status, "draft", "pending", "approved")) AS wl GROUP BY webpage_id'
								. 'SELECT webpage_id, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY FIELD (status, "draft", "approved")), ",", 1) AS status FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.webpage_id=wv.id AND wl.domain=wv.domain AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE wl.domain="private" AND webpage_id IN (%1$s)) AS wl GROUP BY webpage_id'
								. ') AS wl ON (wl.webpage_id=w.id) WHERE w.deleted = 0 OR wl.status <> "approved") AS tb ON(tb.domain = p.domain AND tb.id = p.webpage_id'
                                . ' AND tb.major_version = p.major_version AND tb.minor_version = p.minor_version)'
                                . ' SET p.deleted = 1 WHERE p.platform = %2$s'
                                , implode(', ', $ids_affected[$platform])
                                , $this->conn->escape($platform));
                            $this->conn->exec($sql);

                            $sql = sprintf('UPDATE webpage_versions SET major_version=major_version+1 WHERE id IN (%1$s)'
                                , implode(', ', $ids_affected[$platform]));
                            $this->conn->exec($sql);

                            $ids_updated = array_merge($ids_updated, $ids_affected[$platform]);
                        }
                    }

                    $flat_ids = array();
                    foreach($ids_affected as $t) {
                        $flat_ids = array_unique(array_merge($flat_ids, $t));
                    }

                    if(count($flat_ids)) {
                        $sql = sprintf('UPDATE webpages w JOIN (SELECT * FROM(SELECT * FROM('
                            //. 'SELECT domain, id, major_version, minor_version, deleted'
                            //. ' FROM webpages w WHERE w.domain = \'private\' AND w.id IN(%1$s)'
                            //. ' ORDER BY id, major_version DESC, minor_version DESC'
                            //. ') AS w GROUP BY w.domain, w.id) AS w '

							. 'SELECT w.domain, w.id, w.major_version, w.minor_version, deleted FROM webpages w JOIN webpage_versions wv ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version) WHERE w.domain = \'private\' AND w.id IN(%1$s) GROUP BY w.domain, w.id) AS w'

                            . ' LEFT JOIN ('
							//. 'SELECT webpage_id, status FROM(SELECT * FROM webpage_locales WHERE domain = \'private\' AND webpage_id IN(%1$s) ORDER BY webpage_id, major_version DESC, minor_version DESC, FIELD(status, "draft", "pending", "approved")) AS wl GROUP BY webpage_id'
							. 'SELECT webpage_id, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY FIELD (status, "draft", "approved")), ",", 1) AS status FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.webpage_id=wv.id AND wl.domain=wv.domain AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE wl.domain="private" AND webpage_id IN (%1$s)) AS wl GROUP BY webpage_id'
							. ') AS wl ON (wl.webpage_id=w.id) WHERE w.deleted = 0 OR wl.status <> "approved") AS w2'
                            . ' ON(w2.domain = w.domain AND w2.id = w.id AND w2.major_version = w.major_version AND w2.minor_version = w.minor_version)'
                            . ' SET w.deleted = 1'
                                . ' WHERE NOT EXISTS('
                                . ' SELECT tmp.webpage_id FROM webpage_platforms tmp WHERE tmp.domain = \'private\' AND tmp.webpage_id = w.id '
                                . ' AND tmp.major_version = w.major_version AND tmp.minor_version = w.minor_version'
                                . ' AND tmp.deleted = 0'
                            . ')'
                            , implode(', ', $flat_ids)
                            , $this->user->getId());
                        $sql = sprintf('UPDATE webpage_locales wl JOIN (SELECT * FROM('
							//. 'SELECT * FROM('
                            //. 'SELECT domain, id, major_version, minor_version, deleted'
                            //. ' FROM webpages w WHERE w.domain = \'private\' AND w.id IN(%1$s)'
                            //. ' ORDER BY id, major_version DESC, minor_version DESC'
                            //. ') AS w GROUP BY w.domain, w.id) AS w'

							. 'SELECT w.domain, w.id, w.major_version, w.minor_version, deleted FROM webpages w JOIN webpage_versions wv ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version) WHERE w.domain = \'private\' AND w.id IN(%1$s) GROUP BY w.domain, w.id) AS w'

                            . ' LEFT JOIN ('
							//. 'SELECT webpage_id, status FROM(SELECT * FROM webpage_locales WHERE domain = \'private\' AND webpage_id IN(%1$s) ORDER BY webpage_id, major_version DESC, minor_version DESC, FIELD(status, "draft", "pending", "approved")) AS wl GROUP BY webpage_id'

							. 'SELECT * FROM (SELECT webpage_id, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY FIELD (status, "draft", "approved")), ",", 1) AS status FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.webpage_id=wv.id AND wl.domain=wv.domain AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE wl.domain="private" AND webpage_id IN (%1$s)) AS wl GROUP BY webpage_id) AS wl'

							. ') AS wl ON (wl.webpage_id=w.id) WHERE w.deleted = 0 OR wl.status <> "approved") AS w2'
                            . ' ON(w2.domain = wl.domain AND w2.id = wl.webpage_id AND w2.major_version = wl.major_version AND w2.minor_version = wl.minor_version)'
                            . ' SET wl.status = "approved", wl.updater_id = %2$d, wl.updated_date = UTC_TIMESTAMP()'
                                . ' WHERE NOT EXISTS('
                                . ' SELECT tmp.webpage_id FROM webpage_platforms tmp WHERE tmp.domain = \'private\' AND tmp.webpage_id = wl.webpage_id '
                                . ' AND tmp.major_version = wl.major_version AND tmp.minor_version = wl.minor_version'
                                . ' AND tmp.deleted = 0'
                            . ')'
                            , implode(', ', $flat_ids)
                            , $this->user->getId());
                        $this->conn->exec($sql);
                    }


                    if($publicize)
                        $this->publicize($id, false, array_keys($this->kernel->sets['public_locales']));
                } elseif($status == "pending") {
                    $this->send_pending_email(
                        array($page),
                        'delete',
                        TRUE
                    );
                }

                //Get the webpage title of the webpage
                $sql = sprintf('SELECT webpage_title FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) JOIN locales l ON (wl.locale=l.alias) WHERE webpage_id=%1$d AND wv.domain=\'private\' ORDER BY l.order_index LIMIT 0,1', $id);
                $statement = $this->conn->query($sql);
                extract($statement->fetch());
                $message = "User {$this->user->getId()} <{$this->user->getEmail()}> deleted webpage $id ($webpage_title) webpages(" . implode(', ', $_POST['delete_platform']) . ") "
                    . (isset($flat_ids) && count($flat_ids) ? "and its decedents(" . implode(', ', $flat_ids) . ")" : '')
                    . ".";
                $this->kernel->log( 'message', $message, __FILE__, __LINE__ );

                // continue to process (successfully)
                $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                    http_build_query(array(
                                          'op' => 'dialog',
                                          'type' => 'message',
                                          'code' => 'DESCRIPTION_saved',
                                          'redirect_url' => $this->kernel->sets['paths']['server_url']
                                          . $this->kernel->sets['paths']['mod_from_doc']
                                          . '?id=' . $id
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

                $this->conn->commit();
				$this->clear_private_cache();

                return;
            }
            $this->conn->commit();
			$this->clear_private_cache();

        } catch(Exception $e) {
            $this->processException($e);

        }

        if($ajax) {

        } else {
            // For existing webpage, see if the webpage is not editable or locked
            if ( !$this->is_editable($id) || $this->is_locked($id) )
            {
                $this->kernel->redirect( '?id=' . $id );
                return;
            }

			$wp_platform_keys = array_keys($data['webpage']['platforms']);

            $this->_breadcrumb->push(new breadcrumbNode(sprintf($this->kernel->dict['SET_operations']['delete']
                , $data['webpage']['platforms'][array_shift($wp_platform_keys)]['path']), $this->kernel->sets['paths']['mod_from_doc'] . '?op=delete&id=' . $id));


            $this->kernel->smarty->assign('data', $data);
            $this->kernel->smarty->assign('deleted_platforms', $deleted_platforms);
            $this->kernel->response['content'] = $this->kernel->smarty->fetch('module/webpage_admin/delete.html');
        }
    }

    /**
     * Save the comment of a webpage based on webpage ID.
     *
     * @since   2009-04-21
     */
    function save_comment()
    {
        $id = intval( array_ifnull($_POST, 'id', '') );
        $ajax = (bool) intval(array_ifnull($_POST, 'ajax', 0));
        $errors = array();
        $webpage_data = array();

        // Get data from form post
        $_test = (bool) array_ifnull( $_POST, '_test', FALSE );
        $data['content'] = trim( array_ifnull($_POST, 'content', '') );

        try {
            // see if the webpage really exists
            $sql = sprintf('SELECT id, major_version, minor_version, `type`'
                . ', deleted, created_date, creator_id'
                . ' FROM webpages WHERE domain = \'private\' AND id = %1$d'
                . ' ORDER BY major_version DESC, minor_version DESC'
                . ' LIMIT 0, 1'
                , $id);
            $statement = $this->conn->query($sql);

            if(!($webpage_data = $statement->fetch())) {
                $id = 0;
            }

            // Validate data
            if($id == 0) {
                $errors["errorsStack"][] = 'page_webpage_unknown';
            } elseif($data['content'] === '') {
                $errors["content"][] = 'content_blank';
            }

            //TODO: ERROR_webpage_noneditable

            // Stop if there is error
            if(count($errors) > 0) {
                throw new fieldsException($errors);
            }

            // Insert new webpage
            $sql = 'INSERT INTO webpage_comments(webpage_id,';
            $sql .= ' content, locale, created_date, creator_id) VALUES(';
            $sql .= "$id,";
            $sql .= $this->kernel->db->escape( $data['content'] ) . ',';
            $sql .= $this->kernel->db->escape( $this->kernel->request['locale'] ) . ',';
            $sql .= 'UTC_TIMESTAMP(),';
            $sql .= "{$this->user->getId()})";
            $this->conn->exec( $sql );

            $comment_id = $this->kernel->db->lastInsertId();

            //Get the webpage title of the webpage
            $sql = sprintf('SELECT webpage_title FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) JOIN locales l ON (wl.locale=l.alias) WHERE webpage_id=%1$d AND wv.domain=\'private\' ORDER BY l.order_index LIMIT 0,1', $id);
            $statement = $this->conn->query($sql);
            extract($statement->fetch());
            $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> created webpage comment $comment_id for webpage $id ($webpage_title)", __FILE__, __LINE__ );

            // continue to process (successfully)
            $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                http_build_query(array(
                                      'op' => 'dialog',
                                      'type' => 'message',
                                      'code' => 'DESCRIPTION_saved',
                                      'redirect_url' => $this->kernel->sets['paths']['server_url']
                                      . $this->kernel->sets['paths']['mod_from_doc']
                                      . '?id=' . $id
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

            return;

        } catch(Exception $e) {
            $this->processException($e);
            return FALSE;
        }
    }

    /**
     * Check to see if the webpage is editable by the current user.
     *
     * @since   2009-04-20
     * @param   webpage_id     The webpage ID
     * @return  Editable or not
     */
    function is_editable( $webpage_id )
    {
        return true;
    }

    /**
     * Check to see if the webpage is locked by another user.
     *
     * @since   2009-06-24
     * @param   webpage_id     The webpage ID
     * @return  Editable or not
     */
    function is_locked( $webpage_id )
    {
        // Webpage ID must be a positive integer
        if ( $webpage_id < 1 )
        {
            return FALSE;
        }

        // Clear dead locked webpage
        $sql = 'SELECT * FROM webpage_locks WHERE webpage_id = ' . intval($webpage_id);
        $statement = $this->kernel->db->query($sql);
        if ( !$statement )
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }

        while($record = $statement->fetch())
        {
            // unlocked by others or timeout
            if(time() - strtotime($record['last_active_timestamp']) > intval($this->kernel->conf['page_session_timer']))
            {
                $sql = 'DELETE FROM webpage_locks WHERE webpage_id = ' . intval($webpage_id).' AND locker_id='.$record['locker_id'];
                $this->kernel->db->exec($sql);
            }
        }

        // update $this->locked_pages
        $sql = sprintf('SELECT DISTINCT webpage_id FROM webpage_locks WHERE locker_id <> %d', $this->user->getId());
        $this->locked_pages = $this->kernel->get_set_from_db( $sql );

        return in_array($webpage_id, $this->locked_pages);
    }

    function currently_locked( $webpage_id )
    {
        // Webpage ID must be a positive integer
        if ( $webpage_id < 1 )
        {
            return FALSE;
        }

        // Check lock
        $query = 'SELECT COUNT(*) AS locked FROM webpage_locks';
        $query .= " WHERE webpage_id = $webpage_id";
        $statement = $this->kernel->db->query( $sql );
        if ( !$statement )
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
        extract( $statement->fetch() );
        return $locked > 0;
    }

    function publicize($ids, $ignor_child_pages = false, $locales_to_publish = array() ) {
        $original_data = array();
        $dependent_ids = array(); // ids that are the current dependents of the parent
        $ids_to_delete = array();
		$esaped_locales_str = implode(',', array_map(array($this->kernel->db, 'escape'), $locales_to_publish));

        if(!is_array($ids)) {
            $ids = array($ids);
        }

        $tbs = array('webpage_platforms', 'webpage_locales', 'webpage_locale_contents', 'webpage_locale_banners', 'webpage_permissions', 'webpage_offers', 'webpage_offer_inheritences', 'webpage_versions', 'webpages');

        $id_str = implode(',', array_unique(array_map('intval', $ids)));
        $ids_to_delete = array_unique(array_map('intval', $ids));

        $sql = sprintf('SELECT * FROM webpage_platforms WHERE domain = \'public\' AND webpage_id IN(%s)', $id_str);
        $statement = $this->conn->query($sql);
        while($row = $statement->fetch()) {
            $original_data[$row['webpage_id']][$row['platform']] = $row;
        }

        $path_condition = $ignor_child_pages != true ? '(platform = %1$s AND INSTR(path, %2$s) = 1)' : '(platform = %1$s AND path = %2$s)';
        foreach($original_data as $id => $platforms) {
            $pconds = array();
            foreach($platforms as $platform => $pdata) {
                $pconds[] = sprintf($path_condition
                                    , $this->conn->escape($platform)
                                    , $this->conn->escape($pdata['path']));
            }
/*
 * group by locale, id
 */
            if(count($pconds) > 0) {
                $sql = sprintf('SELECT DISTINCT webpage_id FROM webpage_platforms p1 WHERE (%s)'
                                . ' AND domain = \'public\' AND webpage_id <> %d AND NOT EXISTS(SELECT * FROM(SELECT * FROM('
                                //. ' SELECT * FROM webpages'
                                //. ' SELECT * FROM webpage_locales'
                                //. ' WHERE domain = \'private\' AND status NOT IN("draft")'
                                //. ' AND locale IN (%s)'
                                //. ' ORDER BY id, major_version DESC'
                                //. ' ORDER BY webpage_id, major_version DESC'
                                //. ', minor_version DESC'
                                //. ') tmp2 GROUP BY id) AS p2'
                                //. ') tmp2 GROUP BY webpage_id) AS p2'

								. ' SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.domain = wv.domain AND wl.webpage_id=wv.id AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE wl.domain="private" AND status NOT IN ("draft") AND locale IN (%s)) tmp2 GROUP BY webpage_id) AS p2'

                                . ' WHERE p2.webpage_id = p1.webpage_id AND p2.major_version = p1.major_version AND p2.minor_version = p1.minor_version)'
                                , implode(' OR ', $pconds)
                                , $id
                                , implode(',', array_map(array($this->conn, 'escape'), $this->user->getAccessibleLocales())));
                $dependent_ids[$id] = $this->kernel->get_set_from_db( $sql );

                $ids_to_delete = array_merge($ids_to_delete, $dependent_ids[$id]);
            }
        }

        $ids_to_delete = array_unique($ids_to_delete);

        $id_str2 = implode(',', array_unique(array_map('intval', $ids_to_delete)));

        $original_contents = array();

        // put it here to prevent replacement
        $sql = sprintf('SELECT id, url FROM webpages w JOIN webpage_locales l ON(w.domain = l.domain AND w.id = l.webpage_id)'
                        . ' WHERE w.domain = \'public\' AND w.type= "url_link" AND webpage_id IN(%s)', $id_str);
        $statement2 = $this->conn->query($sql);
        while($row2 = $statement2->fetch()) {
            if(!isset($original_contents[$row2['id']]))
                $original_contents[$row2['id']] = array();
            $original_contents[$row2['id']][] = $row2['url'];
        }

        // remove published table with same id and accessible locales
        foreach($tbs as $tb) {
            if(in_array($tb, array('webpage_locales', 'webpage_locale_contents', 'webpage_locale_banners')))
            {
                if(count($locales_to_publish) > 0)
                {
                    $sql = sprintf('DELETE FROM %s WHERE domain = \'public\' AND %s IN(%s) %s',
                        $tb,
                        $tb == "webpages" ? 'id' : 'webpage_id',
                        $id_str2,
                        'AND locale IN ('.$esaped_locales_str.')');
                }
            }
            else
                $sql = sprintf('DELETE FROM %s WHERE domain = \'public\' AND %s IN(%s)', $tb, in_array($tb, array('webpages', 'webpage_versions')) ? 'id' : 'webpage_id', $id_str2);
            $this->conn->exec($sql);
        }

        // GET the ids (including the subsets) for current ids and its major and minor version
        // TODO: check with the performance, is it faster or the above method faster?

        // describe table to get fields
        $tb_fields = array();
        foreach($tbs as $tb) {
            $sql = sprintf('DESCRIBE %s', $tb);
            $statement = $this->conn->query($sql);

            while($row = $statement->fetch()) {
                if($tb!='webpages' || !in_array($row['Field'], array('status', 'updated_date', 'updater_id')))
                {//exclude status, updated_date and updater_id in webpages.db
                    if($row['Field']=='domain')
                    {
                        $tb_fields[$tb]['domain'] = "'public'";
                    }
                    else if($tb=='webpage_locale_banners' && in_array($row['Field'], array('image_xs', 'image_md', 'image_xl')))
                    {
                        $tb_fields[$tb][$row['Field']] = "IF({$row['Field']} LIKE 'webpage/page/private/%', CONCAT('webpage/page/public/', SUBSTRING({$row['Field']}, 23)), {$row['Field']})";
                    }
                    else
                    {
                        $tb_fields[$tb]["`{$row['Field']}`"] = "`{$row['Field']}`";
                    }
                }
            }
        }

        $path_condition = $ignor_child_pages != true ? 'INSTR(tb.path, tb2.path) = 1' : 'tb.path= tb2.path';
        $sql = sprintf('SELECT DISTINCT webpage_id, major_version, minor_version, `type`, path FROM(SELECT * FROM( SELECT p.*, `type`'
            . ' FROM webpage_platforms p JOIN '

            // get only the latest version
            //. '(SELECT * FROM(SELECT * FROM webpages'
            //. '(SELECT * FROM(SELECT * FROM webpage_locales'
            // pending also required ?
            //. " WHERE domain = 'private'"
            //. ' AND status = "approved"'
            //. ' ORDER BY major_version DESC'
            //. ', minor_version DESC) tmp2 GROUP BY domain, webpage_id) '

			. '(SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.id=wl.webpage_id AND wv.minor_version=wl.minor_version) WHERE wv.domain="private" GROUP BY wv.domain, wv.id)'

			. 'tmp ON(p.domain = tmp.domain AND p.webpage_id = tmp.webpage_id AND p.major_version = tmp.major_version AND p.minor_version = tmp.minor_version)'
            . ' JOIN webpages w ON (w.id=p.webpage_id AND w.domain=p.domain AND w.major_version=p.major_version AND w.minor_version=p.minor_version)'
            . ' ) tb GROUP BY webpage_id, platform) tb'

            . ' WHERE '

            // get its children
            . 'EXISTS(SELECT * FROM( SELECT p.* FROM webpage_platforms p JOIN '
			//. '(SELECT * FROM(SELECT * FROM webpages WHERE domain = \'private\' AND id IN(%1$s) ORDER BY major_version DESC, minor_version DESC) tmp2 GROUP BY domain, id)'

			. '(SELECT w.* FROM webpages w JOIN webpage_versions wv ON (wv.domain=w.domain AND wv.major_version=w.major_version AND wv.id=w.id AND wv.minor_version=w.minor_version) WHERE wv.domain=\'private\' AND w.id IN(%1$s) GROUP BY wv.domain, wv.id)'

			. ' tmp ON(p.domain = tmp.domain AND p.major_version = tmp.major_version AND p.minor_version = tmp.minor_version AND p.webpage_id = tmp.id)) tb2 WHERE tb.platform = tb2.platform AND '.$path_condition.')'

            // same version not currently exists in public table
            . ' AND NOT EXISTS(SELECT * FROM(SELECT * FROM(SELECT * FROM webpages WHERE domain = \'public\' ORDER BY id, major_version DESC'
            . ', minor_version DESC) tb GROUP BY id) AS p2 WHERE p2.id = tb.webpage_id AND p2.major_version = tb.major_version'
            . ' AND p2.minor_version = tb.minor_version AND tb.deleted = 0 AND p2.deleted = 0)'

             // not (deleted and not exists in public already)
            . ' AND (tb.deleted = 0 OR ( tb.deleted = 1 AND EXISTS (SELECT id FROM webpages WHERE domain = \'public\' AND id = tb.webpage_id) ))'
            , $id_str);

        $statement = $this->conn->query($sql);

        $update_ids = array();
        $table_subsqls = array();
        while($row = $statement->fetch()) {
            foreach($tbs as $tb) {
                if(in_array($tb, array('webpage_locales', 'webpage_locale_contents', 'webpage_locale_banners')))
                {
                    if(count($locales_to_publish)>0)
                    {
                        $sql = sprintf(
                            'REPLACE INTO %1$s(%2$s) SELECT %3$s FROM %1$s'
                                . ' WHERE domain = \'private\' AND %4$s = %5$d AND major_version = %6$d AND minor_version = %7$d %8$s',
                            $tb,
                            implode( ', ', array_keys($tb_fields[$tb]) ),
                            implode( ', ', $tb_fields[$tb] ),
                            $tb == "webpages" ? 'id' : 'webpage_id',
                            $row['webpage_id'],
                            $row['major_version'],
                            $row['minor_version'],
                            'AND locale IN ('.$esaped_locales_str.')'
                        );
                        if ( $tb == 'webpage_locales' ) // Do not publicize webpage without title
                        {
                            $sql .= ' AND webpage_title IS NOT NULL';
                        }
                        $this->conn->exec($sql);
                    }

                    $sql = sprintf('SELECT MAX(major_version) AS major_version FROM webpages WHERE id=%1$d AND domain = \'private\'',
                        $row['webpage_id']);
                    $statement2 = $this->conn->query($sql);
                    extract($statement2->fetch());

                    $sql = sprintf(
                        'UPDATE %1$s SET major_version = %6$d, minor_version = %7$d '
                            . ' WHERE domain = \'public\' AND %4$s = %5$d %8$s',
                        $tb,
                        implode( ', ', array_keys($tb_fields[$tb]) ),
                        implode( ', ', $tb_fields[$tb] ),
                        $tb == "webpages" ? 'id' : 'webpage_id',
                        $row['webpage_id'],
                        $major_version,
                        $row['minor_version'],
                        count($locales_to_publish) > 0 ? 'AND locale NOT IN ('.$esaped_locales_str.')' : ''
                    );
                    $this->conn->exec($sql);
                }
                else if ( $tb == 'webpage_offer_inheritences' )
                {
                    $path_condition = $ignor_child_pages != true ? 'INSTR(path, %1$s) = 1' : 'path= %1$s';
                    $sql = sprintf('REPLACE INTO webpage_offer_inheritences(domain, webpage_id, inherited_from_webpage)'
                        . ' (SELECT \'public\', webpage_id, inherited_from_webpage FROM webpage_offer_inheritences'
                        . ' WHERE domain = \'private\' AND webpage_id IN( SELECT webpage_id FROM webpage_platforms WHERE domain = \'private\' AND '.$path_condition.' ))'
                        , $this->conn->escape($row['path']));
                    $this->conn->exec($sql);
                }
                else
                {
                    $sql = sprintf(
                        'REPLACE INTO %1$s(%2$s) SELECT %3$s FROM %1$s'
                            . ' WHERE domain = \'private\' AND %4$s = %5$d AND major_version = %6$d AND minor_version = %7$d',
                        $tb,
                        implode( ', ', array_keys($tb_fields[$tb]) ),
                        implode( ', ', $tb_fields[$tb] ),
                        in_array($tb, array('webpages', 'webpage_versions')) ? 'id' : 'webpage_id',
                        $row['webpage_id'],
                        $row['major_version'],
                        $row['minor_version']
                    );

                    $this->conn->exec($sql);
                }

            };

            // Make sure major version number in webpage_versions table is updated as webpages
            $sql = 'UPDATE webpage_versions SET major_version='.$row['major_version'].' WHERE id='.$row['webpage_id'];
            $this->conn->exec($sql);

            $update_ids[] = $row['webpage_id'];

            if($row['type'] == "static" || $row['type'] == 'structured_page') {
                $sql = sprintf('SELECT * FROM webpage_locale_contents WHERE domain = \'public\' AND webpage_id = %d', $row['webpage_id']);
                $statement2 = $this->conn->query($sql);

                $total_replacements = 0;
                while($row2 = $statement2->fetch()) {
                    $replacements = array();

                    if($row2['content'] != '') {
                        if(!isset($original_contents[$row['webpage_id']]))
                            $original_contents[$row['webpage_id']] = array();
                        $original_contents[$row['webpage_id']][] = $row2['content'];
                        $content_replacement = $this->imgPathDecode('public', $row2['webpage_id'], $row2['content'], null, $row['type']);
                        $total_replacements += $content_replacement['rp_num'];
                        $replacements[$row2['platform'] . '_' . $row2['type']] = $content_replacement['content'];

                        $tmp = array();
                        foreach($row2 as $row_key => $row_value) {
                            $tmp[$row_key] = sprintf('%s', $this->conn->escape($row_value));
                        }

                        $tmp['content'] = $this->conn->escape($content_replacement['content']);

                        if(!isset($table_subsqls[$row['type']]))
                            $table_subsqls[$row['type']] = array();

                        $table_subsqls[$row['type']][] = sprintf('(%s)'
                                             , implode(', ', $tmp)
                        );

                    }
                }

                /*
                $copied_files = $this->pageFilesCopy(array('type' => 'archive', 'sub_path' => "{$row['major_version']}_{$row['minor_version']}"), array('type' => 'public'), $row['webpage_id']);

                if(count($copied_files) && count($original_contents[$row['webpage_id']]))
                {
                    // delete unused files
                    //$this->unlink_unused_files($copied_files, "page/public/p" . $row['webpage_id'], $row['webpage_id'], $original_contents[$row['webpage_id']], $row['type']);
                }
                */
            } elseif($row['type'] == "url_link") {

                /*
                $copied_files = $this->pageFilesCopy(array('type' => 'archive', 'sub_path' => "{$row['major_version']}_{$row['minor_version']}"), array('type' => 'public'), $row['webpage_id']);

                if(count($copied_files) && count($original_contents[$row['webpage_id']]))
                {
                    // delete unused files
                    //$this->unlink_unused_files($copied_files, "page/public/p" . $row['webpage_id'], $row['webpage_id'], $original_contents[$row['webpage_id']], $row['type']);
                }
                */

                $temp_path = '[file_loc_folder:' . $row['webpage_id'] . ']';

                $sql = sprintf('UPDATE webpage_locales SET url = REPLACE(url, %s, %s) WHERE domain = \'public\' AND webpage_id = %d'
                    , $this->conn->escape($temp_path)
                    , $this->conn->escape('public/p' . $row['webpage_id'])
                    , $row['webpage_id']);
                $this->conn->exec($sql);
            }

            if(in_array($row['type'], array('static', 'url_link', 'structured_page'))) {
                foreach ( $locales_to_publish as $locale )
                {
                    $this->pageFilesCopy( array('type' => 'archive', 'sub_path' => "{$row['major_version']}_{$row['minor_version']}"), array('type' => 'public'), $row['webpage_id'], NULL, $locale );
                }
            }
        }

        foreach($table_subsqls as $table => $subsqls) {
            if(count($subsqls) > 0) {
                $sql = sprintf('REPLACE INTO %s VALUES %s', in_array($table, array("static", 'structured_page')) ? 'webpage_locale_contents' : 'webpage_locales', implode(', ', $subsqls));
                $this->conn->exec($sql);
            }
        }

        // get paths from public table and delete all and its decendents
        $sql = sprintf('SELECT * FROM webpages WHERE domain = \'public\' AND id IN(%s) ORDER BY major_version DESC'
            . ', minor_version DESC LIMIT 0, 1'
            , $id_str);
        $statement = $this->conn->query($sql);
        while($row = $statement->fetch()) {
            $major_version = $row['major_version'];
            $minor_version = $row['minor_version'];

            // get the paths of the current active record
            $sql = sprintf('SELECT * FROM webpage_platforms WHERE domain = \'public\' AND webpage_id = %d'
                . ' AND major_version = %d AND minor_version = %d AND deleted = 1'
                , $row['id'], $major_version, $minor_version);
            $statement2 = $this->conn->query($sql);

            $path_condition = $ignor_child_pages != true ? ' WHERE INSTR(p1.path, %1$s) = 1' : ' WHERE p1.path= %1$s';
            while($row2 = $statement2->fetch()) {
                // get the decendent webpage
                $sql = sprintf('UPDATE webpage_platforms p1 JOIN (SELECT * FROM(SELECT * FROM webpages '
                    . ' WHERE domain = \'public\' ORDER BY major_version DESC, minor_version DESC) AS tmp GROUP BY domain, id) AS tb'
                    . ' ON (tb.domain = p1.domain AND tb.id = p1.webpage_id AND tb.major_version = p1.major_version AND tb.minor_version = p1.minor_version)'
                    . ' SET p1.deleted = 1'
                    . $path_condition
                    . ' AND p1.platform = %2$s'
                    , $this->conn->escape($row2['path'])
                    , $this->conn->escape($row2['platform']));
                $this->conn->exec($sql);

            }
        }

        // delete the page that its all platforms have set to deleted
        $sql = sprintf('SELECT p.*, COUNT(DISTINCT p.deleted) AS all_deleted, p.deleted'
        . ' FROM webpage_platforms p JOIN(SELECT * FROM(SELECT * FROM webpages '
        . ' WHERE domain = \'public\' ORDER BY major_version DESC, minor_version DESC) AS tmp GROUP BY domain, id) AS tb'
        . ' ON (tb.domain = p.domain AND tb.id = p.webpage_id AND tb.major_version = p.major_version AND tb.minor_version = p.minor_version)'
        . ' GROUP BY p.webpage_id'
        . ' HAVING all_deleted = 1 AND deleted = 1');
        $statement = $this->conn->query($sql);
        $deleted_ids = array();
        while($row = $statement->fetch()) {
            $deleted_ids[] = $row['webpage_id'];
        }

        if(count($deleted_ids)) {
            $sqls = array();
            $sqls[] = sprintf('DELETE FROM webpage_platforms WHERE domain = \'public\' AND webpage_id IN(%s)'
                , implode(', ', $deleted_ids));
            $sqls[] = sprintf('DELETE FROM webpage_locales WHERE domain = \'public\' AND webpage_id IN(%s)'
                , implode(', ', $deleted_ids));
            $sqls[] = sprintf('DELETE FROM webpage_locale_contents WHERE domain = \'public\' AND webpage_id IN(%s)'
                , implode(', ', $deleted_ids));
            $sqls[] = sprintf('DELETE FROM webpage_locale_banners WHERE domain = \'public\' AND webpage_id IN(%s)'
                , implode(', ', $deleted_ids));
            $sqls[] = sprintf('DELETE FROM webpages WHERE domain = \'public\' AND id IN(%s)'
                , implode(', ', $deleted_ids));
            $sqls[] = sprintf('DELETE FROM webpage_snippets WHERE webpage_id IN (%s)'
                , implode(', ', $deleted_ids));
            foreach($sqls as $sql) {
                $this->conn->exec($sql);
            }
        }

        $new_data = array();
        $sql = sprintf('SELECT * FROM webpage_platforms WHERE domain = \'public\' AND webpage_id IN(%s)', $id_str);
        $statement = $this->conn->query($sql);
        while($row = $statement->fetch()) {
            $new_data[$row['webpage_id']][$row['platform']] = $row;
        }

        // move unupdated descendants to new path
        foreach($new_data as $id => $platforms) {
            foreach($platforms as $platform => $platform_data) {
                if(isset($original_data[$id]) && isset($original_data[$id][$platform])
                      && $original_data[$id][$platform]['path'] != $platform_data['path']) {
                    $this->move_descendant($original_data[$id][$platform]['path']
                        , $platform_data['path'], 'public', $platform);
                }
            }
        }

        $update_ids = array_unique($update_ids);

        if(count($update_ids)) {
            // Update webpage_snippets
            $sql = sprintf('DELETE FROM webpage_snippets WHERE webpage_id IN (%s)'
                , implode(', ', $update_ids));
            $this->conn->exec($sql);

            $sql = 'SELECT * FROM webpage_locale_contents WHERE domain=\'public\' AND webpage_id IN('.implode(',', $update_ids).')';
            $statement = $this->conn->query($sql);
            while($r = $statement->fetch())
            {
                /*
                $snippet_calls = array();
                preg_match_all( '/\{\{[^\}]+\}\}/', $r['content'], $snippet_calls );

                foreach ( $snippet_calls[0] as $snippet_call )
                {
                    $snippet_call = html_entity_decode(
                        strip_tags(substr($snippet_call, 2, strlen($snippet_call)-4)),
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    // Get the id of customized snippet
                    $snippet_call_parts = explode('=', $snippet_call);
                    $customize_snippet_id = $snippet_call_parts[1];

                    $sql = sprintf('INSERT INTO webpage_snippets (webpage_id, snippet_id, webpage_locale, webpage_status, major_version, minor_version) VALUES (%1$d, %2$d, %3$s, %4$s, %5$d, %6$d)'
                    , $r['webpage_id']
                    , $customize_snippet_id
                    , $this->kernel->db->escape($r['locale'])
                    , $this->kernel->db->escape('publish')
                    , $r['major_version']
                    , $r['minor_version']);
                    $this->conn->exec($sql);
                }
                */

                // avacontent block
                $doc = new DOMDocument();
                $doc->loadHTML( "<?xml version='1.0' encoding='UTF-8'?><body>".$r['content']."</body>" );
                $content_blocks = iterator_to_array( $doc->getElementsByTagName('avacontentblock') );
                foreach ( $content_blocks as $content_block )
                {
                    $sql = sprintf('INSERT INTO webpage_snippets (webpage_id, snippet_id, webpage_locale, webpage_status, major_version, minor_version) VALUES (%1$d, %2$d, %3$s, %4$s, %5$d, %6$d)'
                    , $r['webpage_id']
                    , $content_block->getAttribute( 'id' )
                    , $this->kernel->db->escape($r['locale'])
                    , $this->kernel->db->escape('publish')
                    , $r['major_version']
                    , $r['minor_version']);
                    $this->conn->exec($sql);
                }
            }

            // remove the token for preview without login
            $sql = sprintf('DELETE FROM webpage_preview_tokens WHERE `type` = "webpage" AND initial_id IN(%s)', implode(',', $update_ids));
            $this->conn->exec($sql);

            // send emails
            //$sql = sprintf('SELECT DISTINCT requested_by FROM approval_requests WHERE `type` = "webpage" AND target_id IN(%s)'
            //                , implode(', ', $update_ids));
            // Only send notification to requestor has the same language rights or less than the user
            $sql = sprintf('SELECT DISTINCT requested_by FROM approval_requests WHERE `type` = "webpage" AND target_id IN(%s) AND requested_by IN (SELECT DISTINCT user_id FROM user_locale_rights ulr WHERE NOT EXISTS (SELECT DISTINCT user_id FROM user_locale_rights ulr2 WHERE locale NOT IN(%s) AND ulr2.user_id=ulr.user_id))'
                            , implode(', ', $update_ids), implode(',', array_map(array($this->conn, 'escape'), $this->user->getAccessibleLocales())));
            $notification_to = $this->kernel->get_set_from_db($sql);

            if(count($notification_to)) {
                $pages = array();

                $sms = array();
                foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
                    $sm = $this->get_sitemap('preview', $platform, true);
                    $sms[$platform] = $sm;
                }

                foreach($update_ids as $id) {
                    /** @var sitemap $sm */
                    $sm = null;
                    foreach($sms as $platform => $sm) {
                        $p = $sm->getRoot()->findById($id);
                        if($p) {
                            $pages[$id][$platform] = $p->getItem();
                        }
                    }
                }

                // Data container
                $user = array();
                $user['first_name'] = $this->user->getFirstname();
                $user['email'] = $this->user->getEmail();
                $this->kernel->smarty->assignByRef( 'user', $user );
                $this->kernel->smarty->assignByRef( 'pages', $pages );


                $sql = sprintf('SELECT * FROM users WHERE enabled = 1 AND id IN(%s)', implode(', ', $notification_to));
                $statement = $this->conn->query($sql);

                // Try to send email one by one
                $success = FALSE;
                $lines = explode( "\n", $this->kernel->smarty->fetch("module/webpage_admin/locale/{$this->kernel->request['locale']}_approved_email.html") );
                $this->kernel->mailer->isHTML( TRUE );
                $this->kernel->mailer->ContentType = 'text/html';
                $this->kernel->mailer->Subject = trim( array_shift($lines) );
                $this->kernel->mailer->Body = implode( "\n", $lines );

                while ( $recipient = $statement->fetch() )
                {
                    $data['recipient_email'] = $recipient['email'];
                    $data['recipient_name'] = $recipient['first_name'];

                    $this->kernel->mailer->addAddress(
                        $data['recipient_email'],
                        $data['recipient_name']
                    );
                }

                try {
                    $success = $this->kernel->mailer->send();
                } catch(Exception $e) {
                    $this->kernel->log('error', sprintf("User %d <%s> experienced failure in sending mail: %s\n"
                        , $this->user->getId(), $this->user->getEmail(), $e->getTraceAsString()), __FILE__, __LINE__);
                }
                $this->kernel->mailer->ClearAllRecipients();

                // remove pending approval
                $sql = sprintf('DELETE FROM approval_requests WHERE `type` = "webpage" AND target_id IN(%s)', implode(', ', $update_ids));
                $this->conn->exec($sql);

            }
        }

        $this->clear_cache();

        if(count($update_ids) > 0) {
            $messages = array();
            foreach($update_ids as $id)
            {
                if(count($locales_to_publish)>0) {
                    //Get the webpage title of the webpage
                    $sql = sprintf('SELECT name, webpage_title FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) JOIN locales l ON (wl.locale=l.alias AND l.site = \'public_site\') WHERE webpage_id=%1$d AND wv.domain=\'private\' %2$s ORDER BY l.order_index', $id, 'AND locale IN ('.$esaped_locales_str.')');
                    $statement = $this->conn->query($sql);
                    $webpage_titles = array();
                    $msg_locales = array();
                    while($r = $statement->fetch())
                    {
                        $webpage_titles[] = $r['webpage_title'];
                        $msg_locales[] = $r['name'];
                    }
                    if(count($webpage_titles)>0)
                    {
                        $messages[] = $id.' ('.$webpage_titles[0].': '.implode(', ', $msg_locales).')';
                    }
                    else
                    {
                        $messages[] = $id;
                    }
                }
                else {
                    $messages[] = $id;
                }
            }
            $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> published webpages " . implode(', ', $messages) . ".", __FILE__, __LINE__ );
        }
    }

    /**
     * Send email notification for pending webpage.
     *
     * @param array  $pages
     * @param string $op
     * @param bool   $is_child_affected
     * @return bool
     */
    function send_pending_email( $pages, $op, $is_child_affected = false )
    {
        $recipients = array();
        $page_ids = array();

        /** @var page $page */
        $page = null;

        foreach($pages as $page) {
            $page_ids[] = $page->getId();
        }

        if(count($page_ids) > 0)
        {

            /** @var roleNode $ExpApproveRole */
            $ExpApproveRole = $this->roleTree->findById($this->user->getRole()->getId());

            while($ExpApproveRole->getLevel() >= 0 && $ExpApproveRole = $ExpApproveRole->getParent()) {
                if($ExpApproveRole->hasRights('webpage_admin', array(Right::APPROVE))) {
                    $sql = sprintf('SELECT * FROM role_webpage_rights WHERE role_id = %d'
                                    . ' AND webpage_id IN(%s) AND `right`=%d'
                                    , $ExpApproveRole->getItem()->getId()
                                    , implode(', ', array_map('intval', $page_ids)), Right::APPROVE);
                    $statement = $this->conn->query($sql);

                    if($statement->fetch())
                        break;
                }
            }

            if($ExpApproveRole->getItem()->getId() != $this->user->getRole()->getId()) {
                $id = $ExpApproveRole->getItem()->getId();

                //$sql = sprintf('SELECT * FROM users u WHERE role_id = %d AND enabled = 1', $id);
                //Only send email to users have the same language access rights
                $sql = sprintf('SELECT * FROM users u WHERE role_id = %d AND enabled = 1 AND id IN (SELECT user_id FROM (SELECT user_id, GROUP_CONCAT(locale ORDER BY locale ASC SEPARATOR ",") AS acc_locales FROM user_locale_rights ulr GROUP BY user_id HAVING acc_locales = %s) AS ulr)',
                $id,
                $this->conn->escape(implode(',', $this->user->getAccessibleLocales())));
                $statement = $this->conn->query($sql);
                $recipients = $statement->fetchAll();
            }

            $ids = array();
            if(count($recipients)) {
                $tmp = $recipients;
                $recipients = array();

                foreach($tmp as $u) {
                    $ids[] = $u['id'];
                    $recipients[$u['id']] = $u;
                }

                // has same number of requested webpage email sent to avoid sending again
                $sql = sprintf('SELECT target_user, COUNT(DISTINCT target_id) AS w_count'
                                    . ' FROM approval_requests'
                                    . ' WHERE `type` = "webpage" AND requested_by = %d AND target_id IN(%s)'
                                    . ' AND target_user IN(%s) GROUP BY target_user HAVING w_count = %d'
                                    , $this->user->getId()
                                    , implode(', ', $page_ids)
                                    , implode(', ', $ids)
                                    , count($page_ids));
                $statement = $this->conn->query($sql);
                while($row = $statement->fetch()) {
                    unset($recipients[$row['target_user']]);
                }
            }
        }

        // Found
        if ( count($recipients) > 0 )
        {
            $sub_sqls = array();

            // Data container
            $user = array();
            $data = compact( 'pages', 'op', 'is_child_affected' );
            $user['first_name'] = $this->user->getFirstname();
            $user['email'] = $this->user->getEmail();
            $this->kernel->smarty->assignByRef( 'data', $data );
            $this->kernel->smarty->assignByRef( 'user', $user );

            // Try to send email one by one
            $success = FALSE;
            $lines = explode( "\n", $this->kernel->smarty->fetch("module/webpage_admin/locale/{$this->kernel->request['locale']}_pending_email.html") );
            $this->kernel->mailer->isHTML( TRUE );
            $this->kernel->mailer->ContentType = 'text/html';
            $this->kernel->mailer->Subject = trim( array_shift($lines) );
            $this->kernel->mailer->Body = implode( "\n", $lines );

            foreach ( $recipients as $recipient )
            {
                $data['recipient_email'] = $recipient['email'];
                $data['recipient_name'] = $recipient['first_name'];

                $this->kernel->mailer->addAddress(
                    $data['recipient_email'],
                    $data['recipient_name']
                );

                foreach($page_ids as $pid) {

                    $sub_sqls[] = sprintf('(%s, %d, %d, %d, UTC_TIMESTAMP())', '"webpage"', $pid, $this->user->getId(), $recipient['id']);
                }
            }

            if(count($sub_sqls)) {
                $sql = sprintf('REPLACE INTO approval_requests(type, target_id, requested_by, target_user, requested_time)'
                                . ' VALUES %s', implode(', ', $sub_sqls));
                $this->conn->exec($sql);
            }

            try {
                $success = $this->kernel->mailer->send();
            } catch(Exception $e) {
                $this->kernel->log('error', sprintf("User %d <%s> experienced failure in sending mail: %s\n"
                                                , $this->user->getId(), $this->user->getEmail(), $e->getTraceAsString()), __FILE__, __LINE__);
            }
            $this->kernel->mailer->ClearAllRecipients();

            return $success;
        }

        return FALSE;
    }

    function unlink_unused_files($paths, $prefix_path, $id, $contents, $page_type) {

        foreach($paths as $path) {
            $temp_path = '[file_loc_folder:' . $id . ']' . substr($path, strlen($prefix_path));

            $static_path = preg_replace('#(page\/)(archive\/)(p[1-9][0-9]*\/)([1-9][0-9]*_[0-9]+\/)#i', '\\1public/\\3', preg_replace('#^' . preg_quote($this->kernel->sets['paths']['app_root'], '#') . '\/?(.+)$#', '\\1', $path));

            $static_path_regExp = '#' . preg_replace('#\/#', '[\/]+', preg_quote($static_path, '#')) . '#i';

            $in_content = false;
            foreach($contents as $content)
            {
                if($page_type == 'structured_page')
                {
                    $tmp = json_decode($content, true);
                    if(in_array($temp_path, $tmp))
                    {
                        $in_content = true;
                    }
                    unset($tmp);
                }
                else
                {
                    if(strpos($content, $temp_path) !== FALSE || strpos($content, $static_path) !== FALSE || preg_match($static_path_regExp, $content))
                    {
                        $in_content = true;
                    }
                }
            }

            if(!$in_content)
            {
                //@unlink($path);
                //$this->AWS->s3Delete($path);
            }
        }
    }

    function pageFilesCopy($from, $to, $id, $temp_folder = null, $locale = null){
        $mkdir = $this->kernel->conf['aws_enabled'] ? 's3_mkdir' : 'force_mkdir';
        $mkdir('webpage/page/');
        //$mkdir('webpage/page/private/');
        $mkdir('webpage/page/public/');
        $mkdir('webpage/page/archive/');
        $mkdir('webpage/page/temp/');

        $temp_path = '';

        if($temp_folder && $temp_folder != '')
        {
            $temp_path = "webpage/page/temp/" . $temp_folder . "/";
            $mkdir($temp_path);
        }

        if($id > 0)
        {
            //$private_path = "webpage/page/private/p" . $id . "/";
            $public_path = "webpage/page/public/p" . $id . "/";
            //$mkdir($private_path);
            $mkdir($public_path);

            switch($from['type']) {
                case 'public':
                    $source_path = $public_path;
                    break;
                /*
                case 'private':
                    $source_path = $private_path;
                    break;
                */
                case 'archive':
                    $mkdir("webpage/page/archive/p".$id."/");
                    $source_path = "webpage/page/archive/p" . $id . "/" . $from['sub_path'] . "/";
                    $mkdir($source_path);
                    break;
                case 'temp':
                    $source_path = $temp_path;
                    break;
                default:
                    $source_path = '';
                    break;
            }

            switch($to['type']) {
                case 'public':
                    $target_path = $public_path;
                    break;
                /*
                case 'private':
                    $target_path = $private_path;
                    break;
                */
                case 'archive':
                    $mkdir("webpage/page/archive/p".$id."/");
                    $target_path = "webpage/page/archive/p" . $id . "/" . $to['sub_path'] . "/";
                    $mkdir($target_path);
                    break;
                case 'temp':
                    $target_path = $temp_path;
                    break;
                default:
                    $target_path = '';
                    break;
            }

            if($target_path != '' && $source_path != '')
            {
                if(!is_null($locale)) {
                    $target_path .= $locale . '/';
                    $source_path .= $locale . '/';
                }
                if($this->kernel->conf['aws_enabled']) {
                    if($source_path != $target_path)
                    {
                        $paginator = $this->kernel->s3->getPaginator( 'ListObjects', array(
                            'Bucket' => $this->kernel->conf['s3_bucket'],
                            'Prefix' => $source_path,
                            'Delimiter' => '/'
                        ) );
                        $source_filelist = array();
                        foreach ( $paginator->search('[CommonPrefixes[].Prefix, Contents[].Key][]') as $key )
                        {
                            if ( $key != $source_path )
                            {
                                $source_filelist[] = $key;
                            }
                        }
                        foreach($source_filelist as $dir_to_copy)
                        {
                            rcopy( $dir_to_copy, $target_path, FALSE, 's3_' );
                        }
                    }
                }
                else
                {
                    if(is_dir($target_path))
                        $this->empty_folder($target_path);

                    force_mkdir($target_path);

                    if($source_path != $target_path)
                    {
                        return smartCopy($source_path, $target_path);
                    }
                }
            }
        }
    }

    function imgPathDecode($type, $id, $content, $temp_folder = null, $page_type) {
        $pattern = '/\[file_loc_folder:' . $id . '\]/';
        $rp_num = 0;

        switch($type) {
            case 'private':
                $replacement = 'private/p' . $id;
                break;
            case 'public':
                $replacement = 'public/p' . $id;
                break;
            default:
                $replacement = 'temp/' . $temp_folder;
                break;
        }

        if($page_type == 'structured_page')
        {
            $tmp = json_decode($content, true) ?? array();
            foreach($tmp as &$val)
            {
                foreach($val as &$v)
                {
                    if(gettype($v)=='string')
                    {
                        $tmp_val = preg_replace($pattern, $replacement, $v, -1, $rp_num);
                        $v = $tmp_val;
                        unset($tmp_val);
                    }
                }
            }
            $content = json_encode($tmp);
            unset($tmp);
        }
        else
            $content = preg_replace($pattern, $replacement, $content, -1, $rp_num);

        return array(
            'content' => $content,
            'rp_num' => $rp_num
        );
    }

    function setTempFolder() {
        $is_dir = $this->kernel->conf['aws_enabled'] ? 's3_is_dir' : 'is_dir';
        $mkdir = $this->kernel->conf['aws_enabled'] ? 's3_mkdir' : 'force_mkdir';
        $tid = 0;
        do {
            $folder_name = $this->user->getId() . '_' . (++$tid);
            $path = "webpage/page/temp/" . $folder_name . "/";
        } while($is_dir($path));

        $mkdir( $path );
        return $folder_name;
    }

    function imgPathEncode($type, $id, $content) {
        $rp_num = 0;

        switch($type) {
            case 'private':
                $pattern = '/(.+)(\/page\/)(private\/p' . $id . '\/)/';
                break;
            case 'public':
                $pattern = '/(.+)(\/page\/)(public\/p' . $id . '\/)/';
                break;
            default:
                $pattern = '/(.+)(\/page\/)(temp\/' . $this->user->getId() . '_\d+\/)/';
                break;
        }
        $replacement = '${1}${2}[file_loc_folder:' . $id . ']/';
        $content = preg_replace($pattern, $replacement, $content, -1, $rp_num);

        return array(
            'content' => $content,
            'rp_num' => $rp_num
        );
    }

    function update_p_ses()
    {
        $webpage_id = intval(array_ifnull($_GET, 'webpage_id', 0));

        if($webpage_id > 0)
        {
            $sql = 'SELECT * FROM webpage_locks';
            $sql .= " WHERE webpage_id = $webpage_id";
            $sql .= " AND locker_id = {$this->user->getId()}";
            $statement = $this->kernel->db->query( $sql );

            if($statement->fetch()) {
                if(!isset($this->session['PAGE_ACTIVE_TIMER']))
                    $this->session['PAGE_ACTIVE_TIMER'] = array();

                $this->session['PAGE_ACTIVE_TIMER'][$webpage_id] = time();

                $sql = 'REPLACE INTO webpage_locks';
                $sql .= '(webpage_id, locker_id, last_active_timestamp)';
                $sql .= " VALUES($webpage_id, {$this->user->getId()}, " . $this->kernel->db->escape(date('Y-m-d H:i:s', $this->session['PAGE_ACTIVE_TIMER'][$webpage_id])) . ")";
                $statement = $this->kernel->db->query( $sql );
                if ( !$statement )
                {
                    $error_msg = array_pop($this->kernel->db->errorInfo());
                    $this->kernel->db->rollback();
                    $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
                }

                $json = array(
                    'result' => 'success'
                );
            } else {
                $json = array(
                    'result' => 'error',
                    'errors' => array('errorStack' => array($this->kernel->dict['MESSAGE_page_timeout']))
                );
            }
        }

        $this->apply_template = FALSE;
        $this->kernel->response['mimetype'] = 'application/json';
        $this->kernel->response['content'] = json_encode($json);
    }

    function check_page_session_timer($webpage_id) {
        $sql = 'SELECT * FROM webpage_locks WHERE webpage_id = ' . intval($webpage_id);
        $statement = $this->kernel->db->query( $sql );
        if ( !$statement )
        {
            $error_msg = array_pop( $this->kernel->db->errorInfo() );
            $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
        }

        $json = array();

        if ( $record = $statement->fetch() )
        {
            // unlocked by others or timeout
            if( $record['locker_id'] != $this->user->getId() ||
                (time() - strtotime($record['last_active_timestamp']) > intval($this->kernel->conf['page_session_timer'])))
            {
                if ( $record['locker_id'] == $this->user->getId() )
                    $this->unlock( $webpage_id, $record['locker_id'] );
                $json = array(
                    'result' => 'error',
                    'errors' => array('errorStack' => array($this->kernel->dict['MESSAGE_page_timeout']))
                );
            }
        }

        $this->apply_template = FALSE;
        $this->kernel->response['mimetype'] = 'application/json';
        $this->kernel->response['content'] = json_encode($json);
    }

    function check_in_webpage($webpage_id = 0) {

        if( $webpage_id > 0)
        {
            $sql = 'SELECT DISTINCT p.id, l.locker_id FROM webpages p JOIN webpage_locks l ON(l.webpage_id = p.id) WHERE p.domain = \'private\' AND p.id = ' . $webpage_id. ' AND l.locker_id <> ' . $this->user->getId();
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $error_msg = array_pop( $this->kernel->db->errorInfo() );
                $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
            }

            if ( $record = $statement->fetch() )
            {
                $this->unlock( $webpage_id, $record['locker_id'], true );
                $webpage_id = $record['id'];
            }
        }

        $this->kernel->redirect( '?id=' . urlencode($webpage_id) );
    }

    function checkout() {
        $_GET['webpage_id'] = intval(array_ifnull($_GET, 'webpage_id', 0));
        $_GET['prev_temp_folder'] = trim(array_ifnull($_GET, 'prev_temp_folder', ''));
        $path = trim(array_ifnull($_GET, 'path', ''));

        if($_GET['webpage_id'] > 0)
        {
            $sql = 'SELECT p.webpage_id, p.path, l.locker_id FROM webpages p JOIN webpage_locks l ON(l.webpage_id = p.webpage_id) WHERE p.domain = \'private\' AND p.webpage_id = ' . $_GET['webpage_id'] . ' AND l.locker_id = ' . $this->user->getId();
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $error_msg = array_pop( $this->kernel->db->errorInfo() );
                $this->kernel->quit( 'DB Error: ' . $error_msg, $sql, __FILE__, __LINE__ );
            }

            if ( $record = $statement->fetch() )
            {
                $this->unlock( $_GET['webpage_id'], $record['locker_id'] );
            }
        }

        $this->kernel->redirect( '?path=' . urlencode($path) . ($_GET['prev_temp_folder'] != '' ? '&prev_temp_folder=' . urlencode($_GET['prev_temp_folder']) : '' ) );
    }

	function getWebpageNodeChildren($type = 'json', $platform = 'desktop', $target = 0, $disable_target = false, $accessible_webpages = '') {
        $locale = $module->user->getPreferredLocale();

        $parent = intval(array_ifnull($_GET, 'parent', 0));
        $target = intval(array_ifnull($_GET, 'target', $target));
        $platform = trim(array_ifnull($_GET, 'platform', $platform));
        $disable_root = intval(array_ifnull($_GET, 'disable_root', 0));

        //$accessible_webpages = webpage_admin_module::getWebpageAccessibility();
        $accessible_webpages = $accessible_webpages;
        if($accessible_webpages == '')
            //$accessible_webpages = webpage_admin_module::getWebpageAccessibility();
			$accessible_webpages = $this->_accessible_webpages; // Performance enhancement

        if($target) {
            // see if the target really exists
            $sql = sprintf('SELECT * FROM webpage_versions WHERE domain = \'private\' AND id = %d', $target);
            $statement = $conn->query($sql);
            if($statement->fetch()) {
                $parent = 0;
            } else {
                $target = 0; // not exists
            }
        }

        if($parent < 1) {
            $parent = null;
        }

        /** @var sitemap $sitemap */
        if(isset($_GET['ajax']) && $_GET['ajax'] == 1)
		{
			echo 'start: '.microtime(true);
			$sitemap = $this->get_sitemap('edit', $platform, true);
			echo 'end: '.microtime(true);exit;
		}
        else
            //$sitemap = $module->get_sitemap('edit', $platform, true);
            $sitemap = $this->get_sitemap('index', $platform, true); // for 'index' mode, only globle/en and user preferred locale will be loaded

        /** @var pageNode $root */
        $root = $sitemap->getRoot();
//echo print_r($sitemap);exit;
        if(!$root) {
            // no tree can be generated base on the data provided
            return false;
        }

        $tree_array = array();
        $tree_array2 = array();
        $default_disabled = false;
        $disabled_root_node = false;
        $parent_to_process = null;

        if($target) {
            $ary = $root->findUntilId($target, $accessible_webpages, $locale);
            if($ary) {
                $tree_array[] = $ary;
            } else {
                $tree_array[] = $root->getNodeInfo($locale);
            }
        } elseif(!is_null($parent)) {
            $parent_to_process = $root->findById($parent);
            $disabled_root_node = $root->findById($disable_root);
            if($parent == $disable_root) {
                $default_disabled = true;
            }

            $tree_array2 =& $tree_array;
        } else {
            $tree_array[] = $root->getNodeInfo($locale);
        }

        if(count($tree_array) == 1 && $tree_array[0]['id'] == $root->getItem()->getId()
            && (!isset($tree_array[0]['children']) || !count($tree_array[0]['children']))) {
            $parent_to_process = $root;
            $tree_array2 =& $tree_array[0]['children'];
        }

        /** @var pageNode $child */
        $child = null;

        if(!is_null($parent_to_process) && $parent_to_process) {
            foreach($parent_to_process->getChildren(0) as $child) {
                if(!is_array($accessible_webpages)
                    || in_array($child->getItem()->getId(), $accessible_webpages)
                    // necessary to construct the tree
                    || $child->childrenExists($accessible_webpages)) {

                    $tree_array2[] = $child->getNodeInfo($locale);

                    if($disabled_root_node) {
                        $default_disabled = (bool) $disabled_root_node->findById($child->getItem()->getId());
                        if($default_disabled) {
                        }
                    }
                }
            }
        }

        // get the pages which has languages less than all available languages
        /*$sql = sprintf('SELECT wl.webpage_id, GROUP_CONCAT(wl.locale SEPARATOR ", ") AS available_locales FROM webpage_locales wl JOIN('

            . 'SELECT * FROM(SELECT wl.domain, wl.webpage_id, wl.major_version, wl.minor_version, w.deleted, wl.status FROM webpage_locales wl'
            . ' JOIN webpages w ON(w.domain = wl.domain AND w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)'
            . " WHERE wl.domain = 'private'"
            . ' ORDER BY webpage_id ASC, major_version DESC, minor_version DESC '
            . ' ) AS tb WHERE tb.deleted = 0 OR tb.status <> "approved" GROUP BY tb.domain, tb.webpage_id) AS tb'

            . ' ON(wl.domain = tb.domain AND wl.webpage_id = tb.webpage_id AND wl.major_version = tb.major_version AND wl.minor_version = tb.minor_version)'
            . " WHERE wl.domain = 'private'"
            . ' GROUP BY wl.webpage_id'*/ // Performance enhancement
        $escaped_public_locale = array();
//$escaped_public_locale = array_unique(array($module->kernel->default_public_locale, $module->kernel->dict['SET_accessible_languages'][$module->user->getPreferredLocaleId()]['alias']));
//$escaped_public_locale = array_map(array($conn, 'escape'), $escaped_public_locale);
        foreach($this->kernel->sets['public_locales'] as $alias=>$v)
        {
            $escaped_public_locale[] = $this->kernel->db->escape($alias);
        }

        $sql = sprintf('SELECT wl.webpage_id, GROUP_CONCAT(wl.locale SEPARATOR ", ") AS available_locales FROM webpage_versions wv LEFT JOIN'
            . ' webpage_locales wl ON (wv.domain=wl.domain AND wv.id=wl.webpage_id
AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version AND wl.locale IN (%s))'
            . ' JOIN webpages w ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version
AND w.minor_version=wl.minor_version)'
            . ' WHERE wv.domain=\'private\' AND (w.deleted=0 OR wl.status<>\'approved\')'
            . ' GROUP BY wv.id'
            . ' HAVING COUNT(DISTINCT wl.locale) < %d', implode(',', $escaped_public_locale), count($module->kernel->sets['public_locales']));
        $statement = $this->conn->query($sql);

        $locale_specific_pages = array();
        while($row = $statement->fetch()) {
            $locale_specific_pages[$row['webpage_id']] = $row['available_locales'];
        }

        $tree = $this->generateDynaTree($tree_array, $type, $disable_target ? $target : 0, $default_disabled, false, false, $locale_specific_pages);
        if($type == "json") {
            $this->apply_template = false;
            $this->kernel->response['mimetype'] = 'application/json';
            $this->kernel->response['content'] = json_encode($tree);
        } else {
            if(count($tree)) {
                return $tree;
            }
            return false;
        }
    }

    public static function getWebpageNodes($type = 'json', $platform = 'desktop', $target = 0, $disable_target = false, $accessible_webpages = '') {
        /** @var base_module $module */
        $module = kernel::$module;
        $conn = db::Instance();
        $locale = $module->user->getPreferredLocale();

        $parent = intval(array_ifnull($_GET, 'parent', 0));
        $target = intval(array_ifnull($_GET, 'target', $target));
        $platform = trim(array_ifnull($_GET, 'platform', $platform));
        $disable_root = intval(array_ifnull($_GET, 'disable_root', 0));

        //$accessible_webpages = webpage_admin_module::getWebpageAccessibility();
        $accessible_webpages = $accessible_webpages;
        if($accessible_webpages == '')
            $accessible_webpages = webpage_admin_module::getWebpageAccessibility();

        if($target) {
            // see if the target really exists
            $sql = sprintf('SELECT * FROM webpage_versions WHERE domain = \'private\' AND id = %d', $target);
            $statement = $conn->query($sql);
            if($statement->fetch()) {
                $parent = 0;
            } else {
                $target = 0; // not exists
            }
        }

        if($parent < 1) {
            $parent = null;
        }
        /** @var sitemap $sitemap */
        if(isset($_GET['ajax']) && $_GET['ajax'] == 1)
		{
			$sitemap = $module->get_sitemap('edit', $platform, false);
		}
        else
            //$sitemap = $module->get_sitemap('edit', $platform, true);
            $sitemap = $module->get_sitemap('index', $platform, true); // for 'index' mode, only globle/en and user preferred locale will be loaded

        /** @var pageNode $root */
        $root = $sitemap->getRoot();
//echo print_r($sitemap);exit;
        if(!$root) {
            // no tree can be generated base on the data provided
            return false;
        }

        $tree_array = array();
        $tree_array2 = array();
        $default_disabled = false;
        $disabled_root_node = false;
        $parent_to_process = null;

        if($target) {
            $ary = $root->findUntilId($target, $accessible_webpages, $locale);
            if($ary) {
                $tree_array[] = $ary;
            } else {
                $tree_array[] = $root->getNodeInfo($locale);
            }
        } elseif(!is_null($parent)) {
            $parent_to_process = $root->findById($parent);
            $disabled_root_node = $root->findById($disable_root);
            if($parent == $disable_root) {
                $default_disabled = true;
            }

            $tree_array2 =& $tree_array;
        } else {
            $tree_array[] = $root->getNodeInfo($locale);
        }

        if(count($tree_array) == 1 && $tree_array[0]['id'] == $root->getItem()->getId()
            && (!isset($tree_array[0]['children']) || !count($tree_array[0]['children']))) {
            $parent_to_process = $root;
            $tree_array2 =& $tree_array[0]['children'];
        }

        /** @var pageNode $child */
        $child = null;

        if(!is_null($parent_to_process) && $parent_to_process) {
            foreach($parent_to_process->getChildren(0) as $child) {
                if(!is_array($accessible_webpages)
                    || in_array($child->getItem()->getId(), $accessible_webpages)
                    // necessary to construct the tree
                    || $child->childrenExists($accessible_webpages)) {

                    $tree_array2[] = $child->getNodeInfo($locale);

                    if($disabled_root_node) {
                        $default_disabled = (bool) $disabled_root_node->findById($child->getItem()->getId());
                        if($default_disabled) {
                        }
                    }
                }
            }
        }

        // get the pages which has languages less than all available languages
        /*$sql = sprintf('SELECT wl.webpage_id, GROUP_CONCAT(wl.locale SEPARATOR ", ") AS available_locales FROM webpage_locales wl JOIN('

            . 'SELECT * FROM(SELECT wl.domain, wl.webpage_id, wl.major_version, wl.minor_version, w.deleted, wl.status FROM webpage_locales wl'
            . ' JOIN webpages w ON(w.domain = wl.domain AND w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)'
            . " WHERE wl.domain = 'private'"
            . ' ORDER BY webpage_id ASC, major_version DESC, minor_version DESC '
            . ' ) AS tb WHERE tb.deleted = 0 OR tb.status <> "approved" GROUP BY tb.domain, tb.webpage_id) AS tb'

            . ' ON(wl.domain = tb.domain AND wl.webpage_id = tb.webpage_id AND wl.major_version = tb.major_version AND wl.minor_version = tb.minor_version)'
            . " WHERE wl.domain = 'private'"
            . ' GROUP BY wl.webpage_id'*/ // Performance enhancement
        $escaped_public_locale = array();
        foreach($module->kernel->sets['public_locales'] as $alias=>$v)
        {
            $escaped_public_locale[] = $conn->escape($alias);
        }
        $sql = sprintf('SELECT wl.webpage_id, GROUP_CONCAT(wl.locale SEPARATOR ", ") AS available_locales FROM webpage_versions wv LEFT JOIN'
            . ' webpage_locales wl ON (wv.domain=wl.domain AND wv.id=wl.webpage_id
AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version AND wl.locale IN (%s))'
            . ' JOIN webpages w ON (w.id=wv.id AND w.domain=wv.domain AND w.major_version=wv.major_version
AND w.minor_version=wl.minor_version)'
            . ' WHERE wv.domain=\'private\' AND (w.deleted=0 OR wl.status<>\'approved\')'
            . ' GROUP BY wv.id'
            . ' HAVING COUNT(DISTINCT wl.locale) < %d', implode(',', $escaped_public_locale), count($module->kernel->sets['public_locales']));
        $statement = $conn->query($sql);

        $locale_specific_pages = array();
        while($row = $statement->fetch()) {
            $locale_specific_pages[$row['webpage_id']] = $row['available_locales'];
        }

        $tree = $module->generateDynaTree($tree_array, $type, $disable_target ? $target : 0, $default_disabled, false, false, $locale_specific_pages);

        if($type == "json") {
            $module->apply_template = false;
            $module->kernel->response['mimetype'] = 'application/json';
            $module->kernel->response['content'] = json_encode($tree);
        } else if ($type == "html") {
            return $tree;
        } else {
            if(count($tree)) {
                return $tree;
            }
            return false;
        }
    }


    function generateDynaTree($ary = array(), $return = 'json', $id = 0, $disabled = false, $deleted = false, $status = false, $locale_specific_pages = null) {
        $output = array();
        $accessible_webpages = webpage_admin_module::getWebpageAccessibility();
//echo print_r($ary);exit;
        foreach($ary as $item) {
            $classes = array(
                'status' => false
            );

            $child = array(
                'title' => ($item['title'] ? $item['title'] : '(' . $this->kernel->dict['LABEL_no_title'] . ')') . ' - [#' . $item['id'] . ']' . (isset($locale_specific_pages[$item['id']]) ? ' {' . $locale_specific_pages[$item['id']] . '}' : ''),
                'key' => isset($item['id']) ?  $item['id'] : $item['name'],
                'href' => $this->kernel->sets['paths']['mod_from_doc'] . '/?id=' . $item['id'],
                'tooltip' => $item['path'],
                'unselectable' => false,
                'locked' => in_array($item['id'], $this->locked_pages),
                'accessible' => is_null($accessible_webpages) || in_array($item['id'], $accessible_webpages),
                'started' => $item['started']
            );

            if($item['deleted']) {
                $classes[] = 'deleted';
            } elseif($deleted) {
                // parent has been deleted
                $classes[] = 'deleted-parent';
            }

            if($child['key'] == $id) {
                $classes[] = 'disabled';
                $child['unselectable'] = true;
            }

            if(!$child['accessible']) {
                $child['icon'] =  $this->kernel->sets['paths']['app_from_doc'] . '/module/admin/css/ban.gif';
                $classes[] = 'disabled';
                $classes[] = 'not_accessible';
            } elseif($child['locked']) {
                $child['icon'] =  $this->kernel->sets['paths']['app_from_doc'] . '/module/admin/css/lock.gif';
            } elseif(!$child['started']) {
                $child['icon'] =  $this->kernel->sets['paths']['app_from_doc'] . '/module/admin/css/icon-clock.gif';
            }

            if(in_array($item['status'], array("pending", "draft"))) {
                $classes['status'] = in_array($item['status'], array("pending", "draft")) ? $item['status'] : $status;
            }

            if($item['hasChild']) {
                $child['lazy'] = true;
                if(isset($item['children'])) {
                    if($child['key'] == $id) {
                        //exit;
                    }
                    $tmp = $this->generateDynaTree($item['children'], $return, $id, ($child['key'] == $id || $disabled), ($deleted || in_array('deleted', $classes)), $classes['status'] == "pending" ? "pending" : false, $locale_specific_pages);
                    if($tmp) {
                        $child['children'] = $tmp;
                    }
                }
            }

            $child['extraClasses'] = implode( ' ', array_filter($classes, 'strlen') );

            $output[] = $child;
        }

        if($return == "html") {
            $html = "<ul>";
            foreach($output as $item) {
                $data = array();
                if($item['unselectable']) {
                    $data['unselectable'] = true;
                }
                if(isset($item['icon'])) {
                    $data['icon'] = $item['icon'];
                }
                $html .= sprintf('<li id="%1$s" class="%4$s %5$s %6$s" data-json="%8$s" title="%9$s"><a href="%3$s">%2$s</a>%7$s</li>'
                            , $item['key']
                            , htmlspecialchars($item['title'])
                            , htmlspecialchars($item['href'])
                            , isset($item['lazy']) ? 'lazy' : ''
                            , htmlspecialchars($item['extraClasses'])
                            , isset($item['children']) ? "expanded" : ''
                            , isset($item['children']) ? $item['children'] : ''
                            , htmlspecialchars(json_encode($data))
                            , htmlspecialchars($item['tooltip']));
            }
            $html .= "</ul>";
            return $html;
        } else {
            return $output;
        }
    }

    // duplicating a new version
    function archive($id, $major_version = null, $minor_version = null, $new_transaction = true) {
        $sql = sprintf('SELECT id, major_version, minor_version, `type`'
            . ', deleted, created_date, creator_id'
            . ' FROM webpages WHERE domain = \'private\' AND id = %1$d'
            . ' ORDER BY major_version DESC, minor_version DESC'
            . ' LIMIT 0, 1'
            , $id);
        $statement = $this->conn->query($sql);
        if(($data['webpage'] = $statement->fetch()) && (is_null($major_version) || is_null($minor_version)) ) {
            $major_version = $data['webpage']['major_version'];
            $minor_version = $data['webpage']['minor_version'];

            $sql = sprintf('SELECT status, updated_date, updater_id FROM webpage_locales WHERE webpage_id=%1$d AND domain=\'private\' AND major_version=%2$d AND minor_version=%3$d ORDER BY updated_date DESC'
                , $id, $data['webpage']['major_version'], $data['webpage']['minor_version']);
            $statement = $this->conn->query($sql);
            for($i=0; $record = $statement->fetch(); $i++)
            {
                if($i==0)
                {
                    $data['webpage']['status']=array($record['status']);
                    $data['webpage']['updated_date']=$record['updated_date'];
                    $data['webpage']['updater_id']=$record['updater_id'];
                }
                else
                {
                    if(!in_array($record['status'], $data['webpage']['status']))
                        $data['webpage']['status'][]=$record['status'];
                }
            }
        } else
            return false; // return immediately because no record is founded

        //$tables = array( 'webpage_platforms', 'webpage_locales', 'webpage_locale_contents' );
        $tables = array( 'webpage_platforms', 'webpage_locale_contents' );

        if($new_transaction)
            $this->conn->beginTransaction();

        $new_major_version = $major_version + 1;
        $new_minor_version = 0;

        $sql = sprintf('INSERT INTO webpages(domain, id, `type`, structured_page_template'
                        . ', shown_in_site, offer_source, deleted, created_date'
                        . ', creator_id, major_version, minor_version)'
                        . ' (SELECT \'private\', id, `type`, structured_page_template'
                        . ', shown_in_site, offer_source, deleted, created_date'
                        . ', creator_id, %6$d, %7$d FROM webpages'
                        . ' WHERE domain = \'private\' AND id = %3$d AND major_version = %4$d AND minor_version = %5$d)'
                        //, $this->conn->escape($data['webpage']['status'] == "draft" ? "draft" : "pending")
                        , ''
                        , $this->user->getId()
                        , $id, $major_version, $minor_version
                        , $new_major_version, $new_minor_version);
        $this->conn->exec($sql);

        $sql = sprintf('INSERT INTO webpage_locales(domain, webpage_id, locale'
                        . ', webpage_title, seo_title, headline_title, keywords'
                        . ', description, url, query_string, publish_date, removal_date'
                        . ', status, updated_date, updater_id'
                        . ', major_version, minor_version, visual_version)'
                        . ' (SELECT \'private\', webpage_id, locale'
                        . ', webpage_title, seo_title, headline_title, keywords'
                        //. ', description, url, %1$s, UTC_TIMESTAMP(), %2$d'
                        . ', description, url, query_string, publish_date, removal_date'
                        . ', status, UTC_TIMESTAMP(), %2$d'
                        . ', %6$d, %7$d, visual_version+1 FROM webpage_locales'
                        . ' WHERE domain = \'private\' AND webpage_id = %3$d AND major_version = %4$d AND minor_version = %5$d)'
                        , $this->conn->escape((!in_array('approved', $data['webpage']['status']) && !in_array('pending', $data['webpage']['status'])) ? "draft" : "pending")
                        , $this->user->getId()
                        , $id, $major_version, $minor_version
                        , $new_major_version, $new_minor_version);
        $this->conn->exec($sql);

		$sql = sprintf('UPDATE webpage_versions SET major_version=%d, minor_version=%d WHERE domain=%s AND id=%d'
						, $new_major_version, $new_minor_version, $this->conn->escape('private'), $id );
		$this->conn->exec($sql);


        // walk through each table to make the update
        foreach($tables as $table) {
            $fields = array();

            // describe table to get fields
            $sql = sprintf('DESCRIBE %s', $table);
            $statement = $this->conn->query($sql);

            while($row = $statement->fetch()) {
                if(!in_array($row['Field'], array("minor_version", "major_version")))
                    $fields[$row['Field']] = $row['Field'];
            }

            $sql = sprintf('INSERT INTO %1$s(%2$s, major_version, minor_version) (SELECT %2$s, %3$d, %4$d FROM %1$s'
                            . ' WHERE domain = \'private\' AND %5$s = %6$d AND major_version = %7$d AND minor_version = %8$d)'
                            , $table, '`' . implode('`, `', $fields) . '`'
                            , $new_major_version, $new_minor_version, "webpage_id"
                            , $id, $major_version, $minor_version);
            $this->conn->exec($sql);
        }

        // changed to rejected status if previous version is "pending"
        // No "rejected" status
        /*if(in_array($data['webpage']['status'], array("pending"))) {
            $sql = sprintf('UPDATE webpages SET status = \'rejected\' WHERE domain = \'private\' AND id = %1$d AND major_version = %2$d'
                            . ' AND minor_version = %3$d', $id, $major_version, $minor_version);
            $this->conn->exec($sql);
        }*/

        if($new_transaction)
            $this->conn->commit();

        return array(
            'major_version' => $new_major_version,
            'minor_version' => $new_minor_version
        );

    }

    private function genToken($id = 0) {
        $token_days_expired = 5;

        $token = $this->createPvToken($id);

        try {
            $this->conn->beginTransaction();

            // see if the page exists
            foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
                $sm = $this->get_sitemap('edit', $platform);

                if($target = $sm->getRoot()->findById($id)) {
                    break;
                }
            }

            if($target) {
                $sql = sprintf('DELETE FROM webpage_preview_tokens WHERE `type` = "webpage" AND initial_id = %d', $target->getItem()->getId());
                $this->conn->exec($sql);

                $sql = sprintf('INSERT INTO webpage_preview_tokens(token, initial_id, created_date, creator_id, grant_role_id'
                                . ', expire_time) VALUES(%1$s, %2$d, UTC_TIMESTAMP(), %3$d, %5$d, DATE_ADD(UTC_TIMESTAMP(), INTERVAL %4$d DAY) )'
                                , $this->conn->escape($token['token']), $id, $this->user->getId(), $token_days_expired, $this->user->getRole()->getId());
                $this->conn->exec($sql);

                //Get the webpage title of the webpage
                $sql = sprintf('SELECT name, webpage_title FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) JOIN locales l ON (wl.locale=l.alias) WHERE webpage_id=%1$d AND wv.domain=\'private\' ORDER BY l.order_index LIMIT 0,1', $id);
                $statement = $this->conn->query($sql);
                extract($statement->fetch());
                $this->kernel->log('message', sprintf(
                    'User %1$d <%3$s> generated an anonymous preview token for page %2$s (%4$s).'
                , $this->user->getId(), $id, $this->user->getEmail(), $webpage_title), __FILE__, __LINE__);
            }

            $this->conn->commit();
        } catch(Exception $e) {
            $this->processException($e);
            return FALSE;
        }

        // continue to process (successfully)
        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
            http_build_query(array(
                                  'op' => 'dialog',
                                  'type' => 'message',
                                  'code' => 'DESCRIPTION_preview_link_generated',
                                  'redirect_url' => $this->kernel->sets['paths']['server_url']
                                      . $this->kernel->sets['paths']['mod_from_doc']
                                      . '?id=' . $id
                             ));
        $this->kernel->redirect($redirect);
    }

    private function removeToken($id = 0) {
        try {
            $this->conn->beginTransaction();

            // see if the page exists
            foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
                $sm = $this->get_sitemap('edit', $platform);

                if($target = $sm->getRoot()->findById($id)) {
                    break;
                }
            }

            if($target) {
                $sql = sprintf('DELETE FROM webpage_preview_tokens WHERE `type` = "webpage" AND initial_id = %d', $target->getItem()->getId());
                $this->conn->exec($sql);

                //Get the webpage title of the webpage
                $sql = sprintf('SELECT name, webpage_title FROM webpage_locales wl JOIN webpage_versions wv ON (wv.id=wl.webpage_id AND wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) JOIN locales l ON (wl.locale=l.alias) WHERE webpage_id=%1$d AND wv.domain=\'private\' ORDER BY l.order_index LIMIT 0,1', $id);
                $statement = $this->conn->query($sql);
                extract($statement->fetch());
                $this->kernel->log('message', sprintf(
                    'User %1$d <%3$s> removed an anonymous preview token for page %2$s (%4$s)'
                    , $this->user->getId(), $id, $this->user->getEmail(), $webpage_title), __FILE__, __LINE__);
            }

            $this->conn->commit();
        } catch(Exception $e) {
            $this->processException($e);
            return FALSE;
        }

        // continue to process (successfully)
        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
            http_build_query(array(
                                  'op' => 'dialog',
                                  'type' => 'message',
                                  'code' => 'DESCRIPTION_preview_link_removed',
                                  'redirect_url' => $this->kernel->sets['paths']['server_url']
                                      . $this->kernel->sets['paths']['mod_from_doc']
                                      . '?id=' . $id
                             ));
        $this->kernel->redirect($redirect);
    }

    function updateStatus($action) {
        $errors = array();
        $ajax = (bool)intval(array_ifnull($_REQUEST, 'ajax', 0));

        try{
            $this->conn->beginTransaction();

            $id = intval(array_ifnull($_GET, 'id', 0));
            if(count($_POST)>0 && $action = "approved")
                $id = intval(array_ifnull($_POST, 'id', 0));

            //$rights_required = array();
            switch($action) {
                case "approved":
                case "pending":
                    $sql = sprintf('SELECT * FROM webpages WHERE domain = \'private\' AND id = %1$d'
                        //. ' AND (status NOT IN("rejected"))'
                        . ' ORDER BY major_version DESC, minor_version DESC'
                        . ' LIMIT 0, 1', $id, $this->user->getId());
                    $statement = $this->conn->query($sql);

                    if($data = $statement->fetch()) {
                        $sql = sprintf('SELECT * FROM webpage_platforms'
                            . ' WHERE domain = \'private\' AND webpage_id = %1$d'
                            . ' AND major_version = %2$d AND minor_version = %3$d'
                            , $data['id'], $data['major_version'], $data['minor_version']);
                        $statement = $this->conn->query($sql);
                        $rows = $statement->fetchAll();
                        $platforms = array();
                        $deleted_platforms = array();

                        switch($data['type']) {
                            case 'static':
                                foreach($rows as $row) {
                                    $platforms[] = $row['platform'];
                                    $data['template'][$row['platform']] = $row['template_id'];
                                    $data['child_order_field'][$row['platform']] = $row['child_order_field'];
                                    $data['child_order_direction'][$row['platform']] = $row['child_order_direction'];
                                    $data['order_index'][$row['platform']] = $row['order_index'];
                                    if($row['deleted'])
                                        $deleted_platforms[] = $row['platform'];
                                }
                                break;
                            case 'structured_page':
                                foreach($rows as $row) {
                                    $platforms[] = $row['platform'];
                                    $data['child_order_field'][$row['platform']] = $row['child_order_field'];
                                    $data['child_order_direction'][$row['platform']] = $row['child_order_direction'];
                                    $data['order_index'][$row['platform']] = $row['order_index'];
                                    if($row['deleted'])
                                        $deleted_platforms[] = $row['platform'];
                                }
                                break;
                            case 'webpage_link':
                                foreach($rows as $row) {
                                    $platforms[] = $row['platform'];
                                    $data['linked_page_id'][$row['platform']] = $row['linked_webpage_id'];
                                    $data['target'][$row['platform']] = $row['target'];
                                    $data['order_index'][$row['platform']] = $row['order_index'];
                                    if($row['deleted'])
                                        $deleted_platforms[] = $row['platform'];
                                }
                                break;
                            case 'url_link':
                                foreach($rows as $row) {
                                    $platforms[] = $row['platform'];
                                    $data['target'][$row['platform']] = $row['target'];
                                    $data['order_index'][$row['platform']] = $row['order_index'];
                                    if($row['deleted'])
                                        $deleted_platforms[] = $row['platform'];
                                }
                                break;
                        }

                        $row_names = array();

                        $sql = sprintf('SELECT * FROM webpage_locales WHERE domain = \'private\' AND webpage_id = %1$d'
                            . ' AND major_version = %2$d AND minor_version = %3$d'
                            , $data['id'], $data['major_version'], $data['minor_version']);
                        $statement = $this->conn->query($sql);

                        while($row = $statement->fetch()) {

                            foreach($row as $row_name => $row_value) {
                                if(!in_array($row_name, array('major_version', 'minor_version', 'webpage_id', 'locale'))) {
                                    $data[$row_name] = array();
                                    $data[$row_name][$row['locale']] = $row_value;
                                    $row_names[] = $row_name;
                                }
                            }
                        }

                        // ensure the data are available in all locales
                        foreach($row_names as $row_name) {
                            foreach(array_keys($this->kernel->sets['public_locales']) as $locale) {
                                if(!isset($data[$row_name][$locale])) {
                                    $data[$row_name][$locale] = '';
                                }
                            }
                        }

                        $data['status'] = $action;

                        // assume it is root page and ignore checking alias / related things because it should have been checked before
                        $errors = $this->errorChecking($data, $platforms, $data['type'], $data['id'], false, array('webpage_parent_id', 'alias', 'parent'), true);

                        if(count($errors) > 0) {
                            throw new generalException("fields_incorrect", "html", NULL, NULL, 0, NULL, false);
                        } else {
                            /*$sql = sprintf('UPDATE webpages SET status = %1$s WHERE domain = \'private\' AND id = %2$d'
                                . ' AND major_version = %3$d AND minor_version = %4$d'
                                , $this->conn->escape($action), $id, $data['major_version']
                                , $data['minor_version']);*/
                            $updated_locales = array();
                            if($action != "approved")
                            {
                                foreach($this->user->getAccessibleLocales() as $locale_alias)
                                {
                                    $updated_locales[] = $this->kernel->db->escape($locale_alias);
                                }
                            }
                            else
                            {
                                if(count(array_keys($_POST['approve_webpages']))>0)
                                {
                                    foreach($_POST['approve_webpages'] as $locale_alias=>$wps)
                                    {
                                        if(in_array($id, $wps))
                                            $updated_locales[] = $this->kernel->db->escape($locale_alias);
                                    }
                                }

                                if(count($updated_locales) == 0)
                                    $updated_locales = array(0);
                            }

                            $sql = sprintf('UPDATE webpage_locales SET status = %1$s WHERE domain = \'private\' AND webpage_id = %2$d'
                                . ' AND major_version = %3$d AND minor_version = %4$d %5$s'
                                , $this->conn->escape($action), $id, $data['major_version']
                                , $data['minor_version']
                                , count($updated_locales)==0 ? '' : 'AND locale IN('.implode(',', $updated_locales).')');
                            $this->conn->exec($sql);
                            //
                            if($action == "approved") {
                                // deleted specific platform only if not all platform is deleted

                                if(count($deleted_platforms)) {
                                    $_POST['id'] = $data['id'];
                                    $_POST['delete_platform'] = $deleted_platforms;
                                    $this->delete_page(false);
                                }

                                //$this->changeDecendentStatus($action, $id, $data['major_version'], $data['minor_version']);

                                //$this->publicize($id);

                                if(count(array_keys($_POST['approve_webpages']))>0)
                                {
                                    foreach($_POST['approve_webpages'] as $locale_alias=>$wps)
                                    {
                                        foreach($wps as $wp_id)
                                        {
                                            $sql = 'SELECT wv.major_version AS wp_major_version, wv.minor_version AS wp_minor_version, (wl.publish_date IS NULL OR wl.publish_date < UTC_TIMESTAMP()) AS wp_published';
                                            $sql .= ' FROM webpage_versions AS wv';
                                            $sql .= ' JOIN webpage_locales AS wl ON (wv.domain = wl.domain AND wv.id = wl.webpage_id';
                                            $sql .= ' AND wv.major_version = wl.major_version AND wv.minor_version = wl.minor_version AND wl.locale = ' . $this->conn->escape( $locale_alias ) . ')';
                                            $sql .= " WHERE wv.domain = 'private' AND wv.id = $wp_id";
                                            $statement = $this->conn->query( $sql );
                                            extract( $statement->fetch() );

                                            $sql = sprintf('UPDATE webpage_locales SET status = %s WHERE domain = \'private\' AND webpage_id=%d AND major_version = %d AND minor_version = %d AND locale = %s'
                                            , $this->conn->escape($action)
                                            , $wp_id
                                            , $wp_major_version
                                            , $wp_minor_version
                                            , $this->kernel->db->escape($locale_alias));
                                            $this->conn->exec($sql);

                                            $this->publicize($wp_id, true, $wp_published ? array($locale_alias) : array());
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                default:
                    throw new generalException("action_not_exists", "html", NULL, NULL, 0, NULL, false);
                    break;
            }

            $this->conn->commit();

        } catch(Exception $e) {
            $this->processException($e, true);

            return;
        }

        // continue to process (successfully)
        $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
            http_build_query(array(
                                  'op' => 'dialog',
                                  'type' => 'message',
                                  'code' => 'DESCRIPTION_saved',
                                  'redirect_url' => $this->kernel->sets['paths']['server_url']
                                  . $this->kernel->sets['paths']['mod_from_doc']
                                  . '?id=' . $id
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
    }

    function changeDecendentStatus($action = "approved", $id, $major_version = null, $minor_version = null, $ignor_child_pages = false) {
        $path_condition = $ignor_child_pages != true ? 'INSTR(p.path, p2.path) = 1' : 'p.path = p2.path';
        $accessible_locales = array_keys($this->user_accessible_locales);
        $escaped_accessible_locales = array();
        foreach($accessible_locales as $alias)
        {
            $escaped_accessible_locales[] = $this->kernel->db->escape($alias);
        }
        // find all its unapproved children and change to approved
        $sql = sprintf('SELECT DISTINCT p.webpage_id, p.major_version, p.minor_version FROM webpage_platforms p JOIN('
            //. ' SELECT * FROM( SELECT * FROM webpages p WHERE domain = \'private\' AND status IN("approved", "pending")'
            //. ' SELECT * FROM( SELECT * FROM webpage_locales p WHERE domain = \'private\' AND status IN("approved", "pending") %5$s'
            //. ' ORDER BY major_version DESC, minor_version DESC) tb GROUP BY domain, webpage_id) w'

			. ' SELECT * FROM (SELECT p.* FROM webpage_locales p JOIN webpage_versions wv ON (wv.domain=p.domain AND wv.id=p.webpage_id AND wv.major_version=p.major_version AND wv.minor_version=p.minor_version) WHERE wv.domain=\'private\' AND status IN ("approved", "pending") %5$s) tb GROUP BY domain, webpage_id) w'

            . ' ON(w.major_version = p.major_version AND w.minor_version = p.minor_version AND w.webpage_id = p.webpage_id AND w.domain = p.domain)'
            . ' WHERE EXISTS(SELECT * FROM webpage_platforms p2 WHERE p2.domain = \'private\' AND p2.webpage_id = %2$d'
            . ' AND p2.major_version = %3$d AND p2.minor_version = %4$d AND p2.platform = p.platform'
            . ' AND '.$path_condition.') AND p.webpage_id <> %2$d'
            , $this->user->getId()
            , $id
            , $major_version
            , $minor_version
            , count($this->user->getAccessibleLocales()) == 0 ? '' : 'AND locale IN ('.implode(',', $escaped_accessible_locales).')');

        $statement = $this->conn->query($sql);
        $child_conds = array();

        while($row = $statement->fetch()) {
            $child_conds[] = sprintf('(webpage_id = %d AND major_version = %d AND minor_version = %d)'
                , $row['webpage_id'], $row['major_version'], $row['minor_version']);
        }

        if(count($child_conds) > 0) {
            //$sql = sprintf('UPDATE webpages SET status = %s WHERE domain = \'private\' AND (%s)'
            $sql = sprintf('UPDATE webpage_locales SET status = %s WHERE domain = \'private\' AND (%s) AND locale IN (%s)'
                , $this->conn->escape($action)
                , implode(' OR ', $child_conds)
                , implode(',', $escaped_accessible_locales));

            $this->conn->exec($sql);
        }
    }

    public static function getWebpageAccessibility() {
        $module = kernel::$module;
        $conn = db::Instance();

        $module_name = get_class($module);

        $accessible_webpages =& webpage_admin_module::$user_accessible_webpages;

        if(!isset($accessible_webpages)) {
            $accessible_webpages = null;
            if(in_array($module_name, array('webpage_admin_module', 'preview_module', 'offer_admin_module', 'admin_module'))) {
                $accessible_webpages = array();

                // get the webpages which is accessible to user role
                $sql = sprintf('SELECT * FROM role_webpage_rights WHERE role_id = %d'
                    , $module->user->getRole()->getId());
                $statement = $conn->query($sql);
                while($row = $statement->fetch()) {
                    $accessible_webpages[] = intval($row['webpage_id']);
                }

                $accessible_webpages = array_unique($accessible_webpages);
            }
        }

        return $accessible_webpages;
    }

	// Not used anymore
    public function quickEditPgSearch($keyword_str, $ignore_ids = array()) {
        $json = array(
            'result' => 'success'
        );

        if(!is_array($ignore_ids))
            $ignore_ids = array($ignore_ids);

        $ignore_ids = array_map('intval', $ignore_ids);

        // split the keyword string to different segments
        $keywords = $this->consrtuctKeywords($keyword_str);
        $quoted_keywords = $keywords;
        $data = array();

        if(count($quoted_keywords)) {
            $page_types = array_keys($this->kernel->dict['SET_webpage_types']);

            foreach($quoted_keywords as &$keyword) {
                $keyword = "%" . $keyword . "%";

                unset($keyword);
            }

            $search_queries = array();
            $query_str = array_map(array($this->conn, 'escape'), $quoted_keywords);

            $fields = array('webpage_title', 'description', 'url');
            foreach($fields as $field) {
                $search_query[] = '(' . $field . ' LIKE ' . implode(' OR ' . $field . ' LIKE ', $query_str) . ')';
            }

            $tsql = '(SELECT tb.*, wl.webpage_title, wl.webpage_id, wl.description, wl.url FROM(SELECT * FROM('
                . ' SELECT * FROM webpages WHERE domain = \'private\' AND deleted = 0 ORDER BY id ASC, major_version DESC, minor_version DESC'
                . ') AS pw GROUP BY id) AS tb JOIN webpage_locales wl ON (tb.id=wl.webpage_id AND tb.major_version=wl.major_version AND tb.domain=wl.domain) WHERE tb.deleted = 0 OR tb.status <> "approved")';

            $sql = sprintf('SELECT DISTINCT id FROM (SELECT pw.id, m.webpage_title FROM %1$s AS pw'
                . ' JOIN ('

                . "SELECT DISTINCT wl.webpage_id, wl.major_version, wl.minor_version, wl.webpage_title, IF(w.type = 'static', wl.description, IF(w.type = 'url_link', '', wl.url)) AS description"
                . ' FROM webpages AS w JOIN (SELECT * FROM %1$s AS tmp) wl ON(w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)'
                . ' WHERE w.domain = \'private\' AND %2$s'

                . ') AS m ON(m.webpage_id = pw.id AND m.major_version = pw.major_version AND m.minor_version = pw.minor_version)'
                // putting match title at first
                . ' ORDER BY CASE WHEN (pw.id LIKE %3$s) THEN 1 ELSE 0 END DESC'
                . ', CASE WHEN (m.webpage_title LIKE %3$s) THEN 1 ELSE 0 END DESC LIMIT 0, 20) AS tb'
                . ' WHERE id NOT IN(%4$s)'
                , $tsql
                , '(' . implode(' OR ', $search_query) . ')'
                , implode(' OR webpage_title LIKE ', array_map(array($this->conn, 'escape'), $quoted_keywords))
                , count($ignore_ids) ? implode(', ', $ignore_ids) : 0
                );
            $ids = $this->kernel->get_set_from_db($sql);

            $id_keys = array(0);
            foreach($quoted_keywords as $kw) {
                if($tmp = preg_replace('#[^0-9]#', '', $kw)) {
                    $id_keys[] = $tmp;
                }
            }

            $id_keys = array_unique($id_keys);

            // get ids base on other keywords
            $sql = sprintf('SELECT DISTINCT p.webpage_id AS id FROM webpage_platforms p JOIN (SELECT * FROM(%1$s) AS tb) w ON(w.id = p.webpage_id AND'
                            . ' w.major_version = p.major_version AND w.minor_version = p.minor_version) WHERE p.domain = \'private\' AND p.webpage_id NOT IN(%4$s) AND ((p.path LIKE %2$s) OR (p.webpage_id LIKE %3$s))'
                            , $tsql
                            , implode(' OR p.path LIKE ', $query_str)
                            , implode(' OR p.webpage_id LIKE ', array_map('intval', $id_keys))
                            , count($ignore_ids) ? implode(', ', $ignore_ids) : 0
            );
            $ids = array_merge($ids, $this->kernel->get_set_from_db($sql));

            $ids = array_unique($ids);

            if(count($ids)) {

                // get content for return
                $sql = sprintf(
                    'SELECT tb.*, p.path FROM ('
                    . "SELECT * FROM(SELECT DISTINCT w.type, wl.webpage_id, wl.major_version, wl.minor_version, wl.webpage_title, IF(w.type = 'static', wl.description, IF(w.type = 'url_link', '', wl.url)) AS description"
                    . ' FROM webpage_locales wl '
                    . ' JOIN (SELECT * FROM %1$s AS tmp) w ON(w.id = wl.webpage_id AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)'
                    . ' WHERE %2$s ORDER BY wl.major_version DESC, wl.minor_version DESC, wl.locale = %3$s DESC) AS tb GROUP BY webpage_id'
                    . ') AS tb JOIN '
                    . '(SELECT * FROM('
                    . 'SELECT * FROM webpage_platforms WHERE domain = \'private\' ORDER BY webpage_id ASC, platform = "desktop" DESC, major_version DESC, minor_version DESC)'
                    . ' AS tb GROUP BY webpage_id ORDER BY level ASC) AS p ON(p.webpage_id = tb.webpage_id AND p.major_version = tb.major_version AND p.minor_version = tb.minor_version)'
                    , $tsql
                    , 'w.id IN(' . implode(', ', $ids) . ')'
                    , $this->conn->escape($this->kernel->request['locale']));

                $statement = $this->conn->query($sql);
                $data = array();
                $tmp = array();

                while($row = $statement->fetch()) {
                    $tmp[$row['webpage_id']] = $row;
                }

                foreach($ids as $id) {
                    if(isset($tmp[$id])) {
                        $data[] = $tmp[$id];
                    }
                }
            }

            $records = array();
            foreach($data as $record) {
                $r = new searchDataRecord($record['webpage_id'], $record['major_version'], $record['minor_version']
                        , $record['webpage_title'], $record['description'], $record['path']);
                $r->setKeywords($keywords);
                $records[$record['webpage_id']] = $r;
            }

            $tmp = array();
            foreach($ids as $id) {
                if(isset($records[$id])) {
                    $tmp[$id] = $records[$id];
                    unset($records[$id]);
                }
            }

            $records = $tmp;

            $record_pts = array();
            foreach($records as $record) {
                $record_pts[$record->getId()] = $record->getPts();
            }

            asort($record_pts);
            $record_pts = array_reverse($record_pts, TRUE);

            foreach(array_keys($record_pts) as $k) {
                $record = $records[$k];
                $json['data'][] = array(
                    'webpage_id' => $record->getId(),
                    'webpage_title' => $record->getWebpageTitle(),
                    'description' => $record->getDescription(),
                    'path' => $record->getPath(),
                    'html' => $record->generateResultBlock()
                );
            }
        }

        $this->apply_template = FALSE;
        $this->kernel->response['mimetype'] = 'application/json';
        $this->kernel->response['content'] = json_encode($json);
    }

    private function consrtuctKeywords($str) {
        $keywords = array();
        $replace_str = $str;

        // find the keyword string which have quoted
        $quoted_signs = array('"', '\'');
        foreach($quoted_signs as $sign) {
            $regExp = sprintf('#\%1$s([^\%1$s]*?)\%1$s#i', $sign);
            while(preg_match($regExp, $replace_str, $matches, PREG_OFFSET_CAPTURE)) {
                $tmp = $matches[0][0];
                $keywords[] = trim($matches[1][0]);
                $s = $matches[0][1];
                $e = $s + strlen($tmp);
                $replace_str = substr($replace_str, 0, $s) . substr($replace_str, $e);
            }
        }

        $sep_signs = array(",", '\s');
        foreach($sep_signs as $sign) {
            $regExp = sprintf('#([^' . implode('', $sep_signs) . ']*?)%1$s#i', $sign);
            while(preg_match($regExp, $replace_str, $matches, PREG_OFFSET_CAPTURE)) {
                $s = $matches[0][1];
                $e = $s + strlen($matches[0][0]);
                $tmp = preg_replace(sprintf('#%1$s#i', $sign), "", trim($matches[0][0]));
                //echo $replace_str . $s . "<br />";
                $replace_str = trim(substr($replace_str, 0, $s) . substr($replace_str, $e));

                if($tmp !== "") {
                    $keywords[] = $tmp;
                }
            }
        }

        $keywords[] = $replace_str;
        $keywords = array_unique(array_filter(array_map('trim', $keywords), "strlen"));

        return $keywords;
    }

    public function load_older_locale_versions(){
        $module = kernel::$module;
        $old_version_list = array();

        if($this->params[0]>0 && $this->params[1]!='') // $this->params = array($webpage_id, $locale)
        {
            /*
            $sql = "SELECT CONCAT(wl.latest_visual_version, '.', wl.minor_version) AS visual_version, CONCAT(wl.latest_major_version, '.', wl.minor_version) AS real_version, wl.locale AS locale, CONVERT_TZ(IFNULL(wl.latest_updated_date, w.created_date), 'GMT',{$this->kernel->conf['escaped_timezone']}) AS datetime, CONCAT(IFNULL(updaters.first_name, creators.first_name), ' <', IFNULL(updaters.email, creators.email), '>') AS user";
            */
            //$sql .= ' FROM (SELECT * FROM (SELECT * FROM webpage_locales WHERE domain=\'private\' AND locale='.$this->kernel->db->escape($this->params[1]).' AND webpage_id='.$this->params[0].' ORDER BY major_version DESC, minor_version, updated_date DESC) AS wl GROUP BY visual_version) AS wl ';
            /*
            $sql .= " FROM (SELECT *, SUBSTRING_INDEX(GROUP_CONCAT(visual_version ORDER BY major_version DESC, minor_version, updated_date DESC), ',', 1) AS latest_visual_version, SUBSTRING_INDEX(GROUP_CONCAT(major_version ORDER BY major_version DESC, minor_version, updated_date DESC), ',', 1) AS latest_major_version, SUBSTRING_INDEX(GROUP_CONCAT(updated_date ORDER BY major_version DESC, minor_version, updated_date DESC), ',', 1) AS latest_updated_date FROM webpage_locales WHERE domain='private' AND locale=".$this->kernel->db->escape($this->params[1]).' AND webpage_id='.$this->params[0].' GROUP BY visual_version) AS wl ';
            $sql .= ' LEFT JOIN webpages w ON (w.id=wl.webpage_id AND w.domain=wl.domain AND w.major_version=wl.major_version AND w.minor_version=wl.minor_version)';
            */
            $sql = "SELECT CONCAT(wl.visual_version, '.', wl.minor_version) AS visual_version, CONCAT(wl.major_version, '.', wl.minor_version) AS real_version, " . $this->kernel->db->escape($this->params[1]) . ' AS locale,';
            $sql .= " CAST(CONVERT_TZ(wl.publish_date, 'GMT',{$this->kernel->conf['escaped_timezone']}) AS DATETIME) AS publish_date,";
            $sql .= " CAST(CONVERT_TZ(wl.removal_date, 'GMT',{$this->kernel->conf['escaped_timezone']}) AS DATETIME) AS removal_date,";
            $sql .= " CAST(CONVERT_TZ(IFNULL(wl.updated_date, w.created_date), 'GMT',{$this->kernel->conf['escaped_timezone']}) AS DATETIME) AS datetime, CONCAT(IFNULL(updaters.first_name, creators.first_name), ' <', IFNULL(updaters.email, creators.email), '>') AS user";
            $sql .= ' FROM (SELECT visual_version AS visual_version,';
            $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(major_version ORDER BY major_version DESC, minor_version DESC), ',', 1) AS major_version,";
            $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(minor_version ORDER BY major_version DESC, minor_version DESC), ',', 1) AS minor_version,";
            $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(publish_date, '') ORDER BY major_version DESC, minor_version DESC), ',', 1) AS publish_date,";
            $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(removal_date, '') ORDER BY major_version DESC, minor_version DESC), ',', 1) AS removal_date,";
            $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(updated_date ORDER BY major_version DESC, minor_version DESC), ',', 1) AS updated_date,";
            $sql .= " SUBSTRING_INDEX(GROUP_CONCAT(updater_id ORDER BY major_version DESC, minor_version DESC), ',', 1) AS updater_id";
            $sql .= ' FROM webpage_locales';
            $sql .= " WHERE domain = 'private' AND locale = " . $this->kernel->db->escape($this->params[1]) . " AND webpage_id = {$this->params[0]}";
            $sql .= ' GROUP BY visual_version) AS wl';
            $sql .= " LEFT JOIN webpages w ON (w.id = {$this->params[0]} AND w.domain = 'private' AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version)";
            $sql .= ' JOIN users AS creators ON(w.creator_id = creators.id)';
            $sql .= ' LEFT OUTER JOIN users AS updaters ON(wl.updater_id = updaters.id) ORDER BY wl.visual_version DESC';
            $statement = $this->conn->query($sql);
            while($r = $statement->fetch()) {
                $old_version_list[] = $r;
            }
        }

        $module->apply_template = false;
        $module->kernel->response['mimetype'] = 'application/json';
        $module->kernel->response['content'] = json_encode($old_version_list);
    }

    /*
    public function load_customize_snippets($keyword){
        $module = kernel::$module;

        $snippet_list = array();
        if($keyword != '')
        {
            $sql = sprintf('SELECT cs.id, s.snippet_name AS snippet_type, cs.name AS snippet_name FROM customize_snippets cs LEFT JOIN snippets s ON (cs.snippet_type_id=s.id) WHERE cs.deleted=0 AND (s.snippet_name LIKE %1$s OR cs.name LIKE %1$s OR cs.id LIKE %1$s OR s.alias LIKE %1$s) ORDER BY created_time DESC LIMIT 0, 10',
            $this->kernel->db->escape('%'.$keyword.'%'));
            $statement = $this->conn->query($sql);
            while($r = $statement->fetch()) {
                $snippet_list[] = $r;
            }
        }

        $module->apply_template = false;
        $module->kernel->response['mimetype'] = 'application/json';
        $module->kernel->response['content'] = json_encode($snippet_list);
    }
    */
};

class searchDataRecord {
    private $_id;
    private $_major_version;
    private $_minor_version;
    private $_webpage_title;
    private $_description;
    private $_path;
    private $_keywords;

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return mixed
     */
    public function getMajorVersion()
    {
        return $this->_major_version;
    }

    /**
     * @return mixed
     */
    public function getMinorVersion()
    {
        return $this->_minor_version;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * @return mixed
     */
    public function getWebpageTitle()
    {
        return $this->_webpage_title;
    }

    public function setKeywords($keywords) {
        $this->_keywords = $keywords;
    }

    public function getKeywords() {
        return $this->_keywords;
    }

    function __construct($id, $major_version, $minor_version, $title, $desc, $path) {
        $this->_id = $id;
        $this->_major_version = $major_version;
        $this->_minor_version = $minor_version;
        $this->_webpage_title = $title;
        $this->_description = $desc;
        $this->_path = $path;
    }

    private function hlvalue($value, $keywords = array(), $is_numeric = false) {
        foreach($keywords as $keyword) {
            if($is_numeric) {
                $keyword = preg_replace('#[^0-9]#', '', $keyword);
            }
            if($keyword) {
                $value = preg_replace_callback('#' . preg_quote($keyword, '#') . '#i', function($matches){
                        return '<span class="highlighted">' . htmlspecialchars($matches[0]) . '</span>';
                    }, $value);
            }

        }

        return $value;
    }

    // simple calculation to do the sorting
    public function getPts() {
        $pt = 0;
        $keywords = $this->_keywords;

        $fs = array('id', 'webpageTitle', 'description', 'path');
        foreach($fs as $k) {
            $spt = 0;
            $t = 'get' . ucfirst($k);
            $value = $this->$t();

            foreach($keywords as $keyword) {
                $spt2 = preg_match('#' . preg_quote($keyword, '#') . '#i', $value) ? ($k == 'webpageTitle' ? 5 : 1) : 0;
                $spt += $spt2 + $spt2 * $spt * 0.5;
            }

            $pt +=  $spt;
        }

        return $pt;
    }

    public function generateResultBlock() {
        $keywords = $this->_keywords;
        $str = '<div class="qs-result-row"><h4>' . $this->hlvalue($this->_webpage_title, $keywords) . '</h4>';
        $str .= '<div class="qs-id">[#' . $this->hlvalue($this->_id, $keywords, true) . ']</div>';
        //$str .= '<div class="qs-pg-type">' . $this->hlvalue($this->) . '</div>'
        $str .= '<div class="qs-path">' . $this->hlvalue($this->_path, $keywords) . '</div>';
        $str .= '<div class="qs-description">' . $this->hlvalue($this->_description, $keywords) . '</div>';
        $str .= '</div>';

        return $str;
    }
};
