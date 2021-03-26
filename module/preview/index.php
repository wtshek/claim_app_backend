<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/default/index.php' );
require_once( dirname(dirname(__FILE__)) . '/webpage_admin/index.php' );

/**
 * The preview module.
 *
 * This module display the preview page to anonymous users.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2009-03-31
 */
class preview_module extends default_module
{
    protected $roleTree;

    protected $extra_wraps = array();

    public $_wrap = true;
    /** @var adminUser admin_user */
    public $user;
    private $rights_required = array(
        Right::ACCESS,
        Right::VIEW
    );

    private $token;

    /**
     * Constructor.
     *
     * @since   2009-03-31
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        // Set the open mode
        $this->data['mode'] = 'preview';
        $this->token = "";

        parent::__construct( $kernel );
    }

    /**
     * Process the request.
     *
     * @since   2009-05-14
     * @return  Processed or not
     */
    function process()
    {
		
        try {
            $t = trim(array_ifnull($_GET, 'pvtk', ''));
            $t2 = trim(array_ifnull($_GET, 'pvtpl', ''));
            $override_authentication = false;

            $this->user = new adminUser();
            $this->roleTree = $this->getRoleTree(false);

            if($t) {
                $token = admin_module::decodePvToken($t);

                if($token) {
                    $sql = sprintf('SELECT * FROM webpage_preview_tokens WHERE token = %s AND expire_time > UTC_TIMESTAMP()'
                                    , $this->conn->escape($token['token']));
                    $statement = $this->conn->query($sql);

                    if($record = $statement->fetch()) {
                        $this->token = array_merge($token, array('original' => $t));

                        // dont set the token due to security concern: i.e. people should always have the token to view the page
                        //$_SESSION['pvtk'] = $token;
                        // if the link is not referencing from self domain, the initial page for this token will be shown
                        $referer = array_ifnull($_SERVER, 'HTTP_REFERER', NULL);
                        $from_self = preg_match('#^https?\:\/\/(www\.)?((' . preg_quote($this->kernel->conf['mobile_domain'], '#')
                                        . ')|(' . preg_quote(preg_replace('#^www\.#', '', $this->kernel->conf['default_domain']), '#') . '))#i', $referer);
                        $from_admin = preg_match('#\/admin\/#i', $referer);

                        if(!$from_self || $from_admin) {
                            switch($this->token['token_type']) {
                                case 'announcement':
                                    $sql = 'SELECT a.shown_in_webpages, GROUP_CONCAT(aw.webpage_id) AS webpage_ids FROM announcements AS a';
                                    $sql .= ' LEFT OUTER JOIN announcement_webpages AS aw ON (a.domain = aw.domain AND a.id = aw.announcement_id)';
                                    $sql .= " WHERE a.domain = 'private' AND a.deleted = 0 AND a.id = {$record['initial_id']}";
                                    $statement = $this->conn->query( $sql );
                                    if ( $tmp = $statement->fetch() )
                                    {
                                        if ( $tmp['shown_in_webpages'] == 'specific' )
                                        {
                                            $webpage_ids = explode( ',', $tmp['webpage_ids'] );
                                            foreach ( $webpage_ids as $webpage_id )
                                            {
                                                $pn = $this->sitemap->getRoot()->findById( $webpage_id );
                                                if ( $pn !== FALSE )
                                                {
                                                    $this->data['webpage']['path'] = $pn->getItem()->getRelativeUrl( $this->platform );
                                                }
                                            }
                                        }
                                        else
                                        {
                                            $root = $this->sitemap->getRoot();
                                            if ( $root )
                                            {
                                                $this->data['webpage']['path'] = $root->getItem()->getRelativeUrl( $this->platform );
                                            }
                                        }
                                    }
                                    break;
                                case 'offer':
                                    $pn = $this->sitemap->getRoot()->findById($this->kernel->conf['offer_webpage_id']);
                                    $sql = sprintf('SELECT * FROM offers WHERE domain = \'private\' AND type = \'page\' AND id = %d', $record['initial_id']);
                                    $statement = $this->conn->query($sql);
                                    if($tmp = $statement->fetch()) {
                                        $this->data['webpage']['path'] = $pn->getItem()->getRelativeUrl($this->platform) . $tmp['alias'] . '/';
                                    }
                                    break;
                                case 'press_release':
                                    $sql = sprintf('SELECT * FROM press_releases WHERE domain = \'private\' AND deleted = 0 AND id = %d', $record['initial_id']);
                                    $statement = $this->conn->query($sql);
                                    if($tmp = $statement->fetch()) {
                                        $sql = 'SELECT webpage_id FROM webpage_snippets WHERE snippet_id = 11 AND webpage_locale = ' . $this->conn->escape( $this->kernel->request['locale'] );
                                        $webpage_ids = $this->kernel->get_set_from_db($sql);
                                        foreach ( $webpage_ids as $webpage_id )
                                        {
                                            $pn = $this->sitemap->getRoot()->findById( $webpage_id );
                                            if ( $pn !== FALSE )
                                            {
                                                $this->data['webpage']['path'] = $pn->getItem()->getRelativeUrl( $this->platform ) . $tmp['id'] . '/';
                                                break;
                                            }
                                        }
                                    }
                                    break;
                                case 'webpage':
                                default:
                                    $pn = $this->sitemap->getRoot()->findById($record['initial_id']);
                                    if($pn) {
                                        $this->data['webpage']['path'] = $pn->getItem()->getRelativeUrl($this->platform);
                                    }
                                    break;
                            }
                        }

                        $sql = sprintf('UPDATE webpage_preview_tokens SET last_access = UTC_TIMESTAMP() WHERE initial_id = %d', $record['initial_id']);
                        $this->conn->exec($sql);
                        $override_authentication = true;

                        $this->user->setEnabled(true);
                        $this->user->setEmail('');
                        $this->user->setId(0);
                        $this->user->setFirstName('');
                        $this->user->setLastName('');

                        /** @var roleNode $role */
                        $role = $this->roleTree->findById($record['grant_role_id']);
                        if($role) {
                            $this->user->setRole($role->getItem());
                            $this->user->getRole()->addRight('webpage_admin', Right::VIEW);
                        }

                    } else {
                        unset($_SESSION['pvtk']);
                    }
                }
            }

            if($t2) {
                require_once(dirname(dirname(__FILE__)) . '/template_admin/index.php');
                $info = template_admin_module::decodeTplToken($t2);

                $this->_target_template_id = $info['tpl_id'];

                switch($info['token_type']) {
                    case 'html':
                        $this->dummy_content = true;
                        $path = $this->kernel->sets['paths']['temp_root'] . '/live-previews/' . $info['token'] . '.tpl_html';
                        if(file_exists($path)) {
                            $tpl_id = preg_replace('#^([0-9]+?)\/.*$#', '\\1', $info['path']);
                            $this->overwrite_tpl_id = $tpl_id;
                            $this->overwrite_tpl_file = $this->kernel->sets['paths']['temp_root'] . '/live-previews/' . $info['token'] . '.tpl_html';
                        }
                        break;
                    case 'css':
                        $this->dummy_content = true;
                        $this->extra_wraps[$this->kernel->sets['paths']['app_from_doc'] . '/admin/' . $this->kernel->request['locale'] . '/template/?op=retrieve_file&f=' . $t2] = 'css';
                        break;
                    case 'txt': // assume to be locale
                        $this->dummy_content = true;
                        $path = $this->kernel->sets['paths']['temp_root'] . '/live-previews/' . $info['token'] . '.tpl_txt';
                        $dict = kernel::decode_locale_file($path);
                        $this->kernel->dict = array_merge($this->kernel->dict, $dict);
                        break;
                }
            }

            if(!$override_authentication) {
                // Check authentication
                if ( !isset($_SESSION['admin']['user']) )
                {
                    throw new loginException("");
                }

                if ( array_ifnull($_SESSION['admin'], 'user', 0) > 0 )
                {
                    $query = 'SELECT * FROM users WHERE enabled = 1';
                    $query .= ' AND id = ' . intval( $_SESSION['admin']['user'] );
                    $statement = $this->kernel->db->query( $query );
                    if ( !$statement )
                    {
                        $this->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $query, __FILE__, __LINE__ );
                    }

                    if ( $record = $statement->fetch() )
                    {
                        $this->user->setData( $record );

                        // Multiple login || user expired user session || user is an investor
                        if ( $this->user->getToken() != md5(session_id()) || (isset($_SESSION['admin']['USER_PREV_ACTIVE_TIME']) &&
                                (time() - $_SESSION['admin']['USER_PREV_ACTIVE_TIME']) > intval($this->kernel->conf['user_session_timer'])) )
                        {
                            $this->session = array();
                            session_destroy();   // destroy session data in storage
                            session_unset();     // unset $_SESSION variable for the runtime

                            unset( $_SESSION['admin']['user'] );
                            throw new loginException("");
                        }

                        // Single login
                        else
                        {
                            // update session timer
                            $this->session['admin']['USER_PREV_ACTIVE_TIME'] = time();
                        }

                        /** @var roleNode $role */
                        $role = $this->roleTree->findById($record['role_id']);
                        if($role) {
                            $this->user->setRole($role->getItem());
                            $this->user->getRole()->getRights();
                        }
                    }
                }

                // process right checking and throw error and stop further process if any
                $this->user->checkRights('webpage_admin', array_unique($this->rights_required));
            }

            // get accessible webpages and denied request to non accessible webpages
            // check by id
            /** @var pageNode $page_node */
            $page_node = null;
            extract($this->findPage($this->data['webpage']['path']));

            if(!is_null($page_node) && $page_node) {
                $accessible_webpages = webpage_admin_module::getWebpageAccessibility();
                $wid = $page_node->getItem()->getId();

                if($wid && !is_null($accessible_webpages) && !in_array($wid, $accessible_webpages)) {
                    throw new privilegeException('insufficient_rights');
                }

                // set public user to the role that have permission to access the page
                /*
                $accessible_roles = $page_node->getAccessiblePublicRoles();
                if(count($accessible_roles)) {
                    $roleTree = $this->getRoleTree(false, "public");
                    $role = $roleTree->findById($accessible_roles[0]);

                    if($role)
                        $this->user->setRole($role->getItem());
                }
                */
            } elseif(is_null($this->sitemap->getRoot()) && $this->data['webpage']['path'] == "/") {

                /** @var staticPage $p */
                $p = new staticPage();
                $p->setPlatforms(array(
                                      $this->platform
                                 ));
                $p->setRelativeUrls(
                    array(
                         $this->platform => "/"
                    )
                );
                $p->setLocales(array(
                                    $this->kernel->request['locale']
                               ));
                $p->setContents(array(
                                     $this->platform => array()
                                ));

                $pn = new pageNode($p, $this->platform);

                $this->sitemap->add($pn);
            }

            // Choose operation, if not yet processed
            if ( !parent::process() )
            {
                // Nothing
            }

        } catch (Exception $e) {
            $cls = get_class($e);
            if($cls == 'loginException') {
                $this->kernel->redirect($this->kernel->sets['paths']['app_from_doc'] . '/admin/' . $this->kernel->request['locale'] . '/?op=login&redirect_url=' . urlencode(array_ifnull($_SERVER, 'REQUEST_URI', "{$this->kernel->sets['paths']['mod_from_doc']}/")), 302);
            } elseif($cls == 'privilegeException') {
                $this->processException(new statusException(401));
            } elseif($cls != 'statusException')
                $this->processException(new statusException(500));
            else {
                $this->processException($e);
            }
        }

        return TRUE;
    }

    function index() {
        $public_accessible_roles = null;
        // temporary page object
        $pd_obj = trim(array_ifnull($_GET, 'pd', false));
        // view by page version
        $version = floatval(array_ifnull($_GET, 'v', 0));
        $major_version = floor($version);
        $minor_version = intval(preg_replace('#^0\.#', '', $version % 1));
        if($minor_version < 0) {
            $minor_version = 0;
        }

        /** @var staticPage | webpageLinkPage | urlLinkPage | structuredPagePage $tmp_page */
        $tmp_page = null;

        /** @var pageNode $page_node */
        $page_node = null;
        
        if($pd_obj) {
            $path = $this->kernel->sets['paths']['temp_root'] . '/live-previews/' . $pd_obj;
            if(file_exists($path)) {
                try {
                    $tmp_page = unserialize(file_get_contents($path));
                    $page_type = get_class($tmp_page);

                    if(!(is_object($tmp_page) && in_array($page_type, array('staticPage', 'webpageLinkPage', 'urlLinkPage')))) {
                        $tmp_page = null;
                    }
                } catch(Exception $e) {
                    $tmp_page = null;
                }

                if(!is_null($tmp_page)) {
                    $url = $tmp_page->getRelativeUrl($this->platform);
                    $parent_url = preg_replace('#^(.*?\/)[^\/]*\/?$#i', '\\1', $url);

                    /** @var pageNode $parent_page */
                    $parent_page = $this->sitemap->findPage($parent_url);

                    // replace empty content in specific platform with content in other platforms
                    if($page_type == "staticPage") {

                        $platforms = $tmp_page->getPlatforms();
                        $locales = array_keys($this->kernel->sets['public_locales']);
                        unset($locales[array_search($this->kernel->request['locale'], $locales)]);

                        foreach($platforms as $platform) {
                            $tmp = $tmp_page->getPlatformContent($platform, $this->kernel->request['locale']);
                            $content_blocks = array_keys($tmp);

                            $has_content = false;

                            foreach($content_blocks as $content_block) {
                                if($tmp[$content_block] !== "") {
                                    $has_content = true;
                                    break;
                                }
                            }

                            // replace content only if title exists
                            if(!$has_content && $tmp_page->getTitle($this->kernel->request['locale']) !== '') {
                                $content_replaced = false;
                                // replace with content in other locale
                                foreach($locales as $locale) {
                                    $tmp2 = $tmp_page->getPlatformContent($platform, $locale);

                                    foreach($content_blocks as $content_block) {
                                        if(isset($tmp2[$content_block]) && $tmp2[$content_block] !== "") {

                                            $tmp_page->setPlatformContent($platform, $this->kernel->request['locale'], $tmp2);
                                            $content_replaced = true;
                                            break;
                                        }
                                    }

                                    if($content_replaced)
                                        break;
                                }
                            }
                        }
                    }

                    if(is_null($parent_page) || !$parent_page) {
                        // add to root node
                        $this->sitemap->getRoot()->setItem($tmp_page);
                    } else {

                        $tmp_page->data_retrieved = true;

                        /** @var pageNode $target_node */
                        $target_node = $parent_page->findById($tmp_page->getId());

                        /** offer module START */
                        /** to FETCH offers for each page according to language */
                        if($page_type == "staticPage" && $tmp_page->getOfferSource() == "inherited") {
                            require_once( dirname(dirname(__FILE__)) . '/offer_admin/index.php' );

                            $offer_ids = array();
                            $offers = offer_admin_module::getWebpageOffers($parent_page->getItem()->getId(), 'private');
                            foreach($offers as $offer) {
                                $offer_ids[] = $offer['id'];
                            }

                            $tmp_page->setOfferIds($offer_ids);
                        }

                        if(is_null($target_node) || !$target_node) {
                            $target_node = new pageNode($tmp_page, $this->platform);
                            $this->sitemap->add($target_node);
                        } else {
                            $target_node->setItem($tmp_page);
                        }

                        $parent_page->reOrder();

                        $this->_wrap = false;

                        $page_node = $target_node;
                    }

                }
            }
        } elseif($major_version) {

            extract($this->findPage($this->data['webpage']['path']));
            if($page_node) {
                /** @var staticPage | urlLinkPage | webpageLinkPage | structuredPagePage $page */
                $page = $page_node->getItem();

                // see if the page version exists
                $sql = sprintf('SELECT * FROM webpages WHERE domain = \'private\' AND id = %d AND major_version = %d AND minor_version = %d'
                    , $page->getId(), $major_version, $minor_version);
                $statement = $this->conn->query($sql);

                if($statement->fetch()) {
                    $page->setMajorVersion($major_version);
                    $page->setMinorVersion($minor_version);
                }
            }
        } else {

            extract($this->findPage($this->data['webpage']['path']));
        }

        if(!is_null($page_node)) {
            $available_platforms = array();
            $r_platforms = array();
            $public_accessible_role_ids = array();

            /** @var roleNode $publicRoleTree */
            $publicRoleTree = $this->getRoleTree(false, "public");

            $path = $this->data['webpage']['path'];
            $page = $page_node->getItem();
            $url = $page->getRelativeUrl($this->platform);
            $path = substr($path, strlen($url));
            $segments = array_filter(explode('/', $path), 'strlen');

            foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
                $sm = $this->get_sitemap('preview', $platform);

                /** @var pageNode $page */
                $page = $sm->getRoot()->findById($page_node->getItem()->getId());

                if($page) {
                    if($platform != $this->platform) {
                        $r_platforms[] = array(
                            'platform' => $platform,
                            'path' => $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'] . $page->getItem()->getRelativeUrl($platform) . (count($segments) ? implode('/', $segments) . '/' : '') . '?m=' . ($platform == 'mobile' ? '1' : '0') . ($this->token ? "&pvtk=" . $this->token['original'] : "")
                        );
                    }

                    $available_platforms[] = $platform;

                    if(is_null($public_accessible_roles)) {
                        $public_accessible_roles = array();
                        $role_ids = $page->getAccessiblePublicRoles();

                        foreach($role_ids as $role_id) {
                            /** @var roleNode $role */
                            $role = $publicRoleTree->findById($role_id);

                            if($role) {
                                $public_accessible_roles[] = $role->getItem();
                                $public_accessible_role_ids[] = $role_id;
                            }
                        }
                    }
                }
            }

            $page_node->getItem()->setAccessiblePublicRoles($public_accessible_role_ids);

            $this->kernel->smarty->assign('public_accessible_roles', $public_accessible_roles);
            $this->kernel->smarty->assign('available_platforms', $available_platforms);
            $this->kernel->smarty->assign('alternate_platforms', $r_platforms);
            $this->kernel->smarty->assignByRef('user', $this->user);
        }

        parent::index();
    }

    function output() {
        parent::output();

        if(!$this->dummy_content && $this->apply_template)
            $this->kernel->response['content'] = $this->kernel->smarty->fetch('module/preview/content_info_wrapper.html');

        if($this->_wrap && $this->kernel->response['status_code'] == 200 && $this->outputPageType == "staticPage") {
            $this->kernel->smarty->assignByRef('extra_wraps', $this->extra_wraps);
            $this->kernel->response['content'] = $this->kernel->smarty->fetch('module/preview/extra_wrapper.html');
        }
    }

    protected function getRoleTree($show_enabled = false, $type = "admin") {
        // treat as a container to store root nodes
        $tmp_roles = array();
        $roleTree = new treeNode();
        $roleTree->setLevel(-1);

        $sql = sprintf('SELECT * FROM roles WHERE `type` = %s ORDER BY root_role DESC, parent_id IS NULL DESC, `level` ASC', $this->conn->escape($type));
        $statement = $this->conn->query($sql);
        while($row = $statement->fetch()) {
            $cls = $type.'Role';
            $r = new $cls();
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

    public function findPage($path, $finding_parent = false) {

        return parent::findPage($path, $finding_parent);
    }
}
