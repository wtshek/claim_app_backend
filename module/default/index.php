<?php

// Include required files
require_once( dirname(__FILE__) . '/menu.php' );
require_once( dirname(dirname(__FILE__)) . '/base/breadcrumb.php' );

/**
 * The default module.
 *
 * This module displays the page to anonymous users.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2008-11-13
 */
class default_module extends menu_module
{
    /** @var pageNode $page_node */
    protected $page_node = null;
    /** @var bool $_is_mobile */
    protected $is_mobile = null;

    protected $_root_template_id = 0;
    protected $_target_template_id = null;

    protected $overwrite_tpl_id = null;
    protected $overwrite_tpl_file = "";
    protected $dummy_content = false;

    /** @var roleTree $roleTree */
    protected $roleTree = null;

    /** @var publicUser $user */
    public $user = null;


    /**
     * @var array
     */
    public $data = array();
    public $base_url = "";
    public $current_url = "";
    public $user_device = "default";
    public $user_device_type = "";

    // Member variables
    public $apply_template;    // Apply master template or not
    public $session;           // The module session
    public $pg_type = "public";
    public $outputPageType = "";
    public $filesToFetch = array();

    /** @var sitemap  */
    public $sitemap = null;
    public $page_found = false;
    public $platform = null;

    public $alternate_urls = array();

    /** @var  breadcrumb $breadcrumb */
    public $breadcrumb;

    protected $_root_property_id = 0;

    public function getPageNode() {
        return  is_null($this->page_node) ? false : $this->page_node;
    }

    /**
     * Constructor.
     *
     * @since   2008-11-13
     * @param   kernel kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        $sql = 'SELECT alias FROM locales WHERE site='.$this->kernel->db->escape('admin_site').' AND enabled=1 AND `default`=1';
        $statement = $this->kernel->db->query($sql);
        if (!$statement)
        {
            $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
        }
        if($record = $statement->fetch())
        {
            require( dirname(__FILE__) . "/locale/{$record['alias']}.php" );
            $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );
        }

        // The data to display
        /** @var array data */
        $this->data = array(
            'now' => gmdate( 'Y-m-d H:i:s' ),
            'mode' => isset($this->data['mode']) ? $this->data['mode'] : 'view',
            'webpage' => array(),
            'sitemap' => array(),
            'breadcrumb' => array(),
            'parent_webpage' => array(),
            'footer_sitemap' => array(),
            'bottom_menu_sitemap' => array(),
            'current_url' => &$this->current_url,
            'menu' => ''
        );

        // Apply master template or not
        /** @var bool apply_template */
        $this->apply_template = TRUE;

        // Set session
        if ( !isset($_SESSION['default']) )
        {
            $_SESSION['default'] = array();
        }
        $this->session =& $_SESSION['default'];
        $platform_priority = array();

        $first_platform = "desktop";

        $this->alternate_urls = array();
        $this->roleTree = $this->getRoleTree(false);

        $this->current_url = "/";

        $this->breadcrumb = new breadcrumb();
        $this->kernel->smarty->assignByRef('breadcrumb', $this->breadcrumb);
        $this->base_url = $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'];

        /***********************************************************************
         * Determine whether or not to display mobile site
         */
        /** @var Mobile_Detect $detect */
        //$detect = new Mobile_Detect();

        /** @var bool is_mobile */
        //$this->is_mobile = true; // Patrick: testing purpose
        //$this->is_mobile = $detect->isMobile() && !$detect->isIpad() && !$detect->isAndroidtablet() && !$detect->isBlackberrytablet();
        $this->is_mobile = false;
        /*
        $this->user_device = $detect->isIpad() ? "ipad" : ($detect->isAndroidtablet() ? "android_tablet" : "default");
        $this->user_device_type = ($detect->isAndroidtablet() || $detect->isBlackberrytablet() || $detect->isIpad()) ? "tablet"
                                    : ($detect->isIphone() || $detect->isBlackberry() || $detect->isPalm() || $detect->isWindowsphone()) ? "phone"
                                    : "desktop";
        */
        $this->user_device = 'default';
        $this->user_device_type = 'desktop';

        if($this->kernel->conf['mobile_domain']
            && $this->kernel->conf['mobile_domain'] != $this->kernel->conf['default_domain']
            && preg_match('#^https?\:\/\/' . preg_quote($this->kernel->conf['mobile_domain'], '#') . '#i', $this->kernel->sets['paths']['server_url'])
        ) {
            // default to be displayed in mobile if the path matches
            $first_platform = "mobile";
        } else {
            // display in desktop (only if it has not been overridden)
            if(isset($_GET['m'])) {
                $_GET['m'] = (bool) intval($_GET['m']);
                $_SESSION['mobile_override'] = $_GET['m'];
            } else if(isset($_SESSION['mobile_override'])) {
                $_GET['m'] = $_SESSION['mobile_override'] ? true : null;
            } else {
                $_GET['m'] = null;
            }
            $_GET['m'] = null;
            if($_GET['m']) {
                $first_platform = "mobile";
            }
        }

        if(is_null($this->user)) {
            // user
            $this->user = new publicUser();
        }

        $platform_priority[] = $first_platform;

        $platform_priority = array_unique(array_merge($platform_priority, array_keys($this->kernel->dict['SET_webpage_page_types'])));
        foreach($platform_priority as $platform) {
            $this->platform = $platform;
            $this->sitemap = $this->get_sitemap($this->data['mode'], $platform);
            $root = $this->sitemap->getRoot();

            if($root) {
                break;
            }

        }

        //$this->platform = "mobile";

        foreach(array_keys($this->kernel->dict['SET_webpage_page_types']) as $platform) {
            if($platform != $this->platform) {
                $this->alternate_urls[$platform] = '';
            }
        }

        $this->pg_type = $this->data['mode'] == 'preview' ? 'private' : 'public';

        // Get the current webpage

        $this->data['webpage'] = array(
            'path' => '/' . implode('/', $this->kernel->request['path_segments']),
            'webpage_id' => 0,
            'template_id' => 0,
            'webpage_title' => $this->kernel->dict['LABEL_404'],
            'short_title' => $this->kernel->dict['LABEL_404'],
            'long_title' => $this->kernel->dict['LABEL_404']
        );

        if($this->data['webpage']['path'] != '/') {
            $this->data['webpage']['path'] .= '/';
        }

        $this->data['webpage']['path'] = urldecode($this->data['webpage']['path']);


        // Assign members to Smarty Template Engine
        $this->kernel->smarty->assignByRef('data', $this->data );
        $this->kernel->smarty->assignByRef('root_template_id', $this->_root_template_id);
        $this->kernel->smarty->assignByRef('base_template_id', $this->_target_template_id);
        $this->kernel->smarty->assignByRef('user', $this->user);
        $this->kernel->smarty->assignByRef('user_device', $this->user_device);
        $this->kernel->smarty->assignByRef('user_device_type', $this->user_device_type);
        $this->kernel->smarty->assignByRef('sitemap', $this->sitemap );
    }

    /**
     * Process the request.
     *
     * @since   2008-11-13
     * @return  Processed or not
     */
    function process()
    {
        try {
            // Choose operation, if not yet processed
            if ( !parent::process() )
            {
                $this->kernel->smarty->assignByRef( 'platform', $this->platform );
                $this->kernel->smarty->assignByRef( 'alternate_urls', $this->alternate_urls );
                $this->kernel->smarty->assignByRef( 'is_mobile', $this->is_mobile );

                $root = $this->sitemap->getRoot();

                if(is_null($root) || $root === FALSE) {
                    echo 'Please set up at least a page in admin panel.';
                    exit;
                }

                // Decrypt query string
                if ( count($_GET) > 0 )
                {
                    $first_key = array_key_first( $_GET );
                    $qs = json_decode( $this->kernel->decrypt($first_key), TRUE );
                    if ( !is_null($qs) )
                    {
                        unset( $_GET[$first_key] );
                        $_GET = array_merge( $qs, $_GET );
                    }
                }

                $op = array_ifnull( $_GET, 'op', 'index' );

                switch ( $op )
                {
                    case 'msg':                         $this->msg();                           return TRUE;
                    case 'sitemap':                     $this->sitemap();                       return TRUE;
                    case 'index':
                    default:
                        $this->index();
                        return TRUE;
                }
            }
        } catch (Exception $e) {
            if(get_class($e) != 'statusException')
                $this->processException(new statusException(500));
            else {
                $this->processException($e);
            }
        }


        return TRUE;
    }

    /**
     * Output the response.
     *
     * @since   2008-11-13
     */
    function output()
    {
        $_GET['plaintext'] = array_ifnull($_GET, 'plaintext', false) ? true : null;

        if(!preg_match('#^30[\d]+#i', $this->kernel->response['status_code'])) { // not redirect
            $referer = array_ifnull($_SERVER, 'HTTP_REFERER', NULL);
            $from_self = preg_match('#^https?\:\/\/(www\.)?((' . preg_quote($this->kernel->conf['mobile_domain'], '#') . ')|(' . preg_quote(preg_replace('#^www\.#', '', $this->kernel->conf['default_domain']), '#') . '))#i', $referer);

            // extra process before output
            if($this->apply_template && ($this->outputPageType == "staticPage" || $this->outputPageType = 'structuredPagePage')) {
                //get locale configurations
                $conf_locales = array();
                $default_alias = '';
                $conf_platform = '';
                $sql = sprintf('SELECT alias FROM locales WHERE site=%1$s AND enabled=1 AND `default`=1',
                    $this->kernel->db->escape('public_site')
                );
                $statement = $this->kernel->db->query($sql);
                if($record = $statement->fetch()) {
                    $default_alias = $record['alias'];
                }
                $sql = sprintf('SELECT * FROM configurations_locale WHERE locale=%1s',
                    $this->kernel->db->escape($this->kernel->request['locale']));
                $statement = $this->kernel->db->query($sql);
                while($r = $statement->fetch())
                {
                    $conf_locales[$r['name']] = strtr( $r['value'], array(
                        ':year' => intval( convert_tz('now', 'UTC', $this->kernel->conf['timezone']) )
                    ) );
                }
                if($this->kernel->request['locale'] != $default_alias)
                {
                    $sql = sprintf('SELECT * FROM configurations_locale WHERE locale=%1s',
                        $this->kernel->db->escape($default_alias));
                    $statement = $this->kernel->db->query($sql);
                    while($r = $statement->fetch())
                    {
                        $default_conf_locales[$r['name']] = $r['value'];
                    }
                    foreach($conf_locales as $n=>&$v)
                    {
                        if($v == '')
                            $v = $default_conf_locales[$n];
                    }
                }

                // Overwrite announcement in configuration
                $this->kernel->conf['announcement_enabled'] = FALSE;
                $page = $this->kernel->smarty->getTemplateVars( 'pg' );
                $sql = 'SELECT a.*, al.name, al.content FROM announcements AS a';
                $sql .= ' JOIN announcement_locales AS al ON (a.domain = al.domain AND a.id = al.announcement_id AND al.locale = ' . $this->kernel->db->escape( $this->kernel->request['locale'] ) . ')';
                $sql .= " LEFT JOIN announcement_webpages AS aw ON (a.domain = aw.domain AND a.id = aw.announcement_id AND aw.webpage_id = {$page->getId()})";
                if ( $this->pg_type == 'private' && array_key_exists('pvtk', $_GET) )
                {
                    $token = admin_module::decodePvToken( $_GET['pvtk'] );
                    if ( $token && $token['token_type'] == 'announcement' )
                    {
                        $sql .= ' JOIN webpage_preview_tokens AS t ON (a.id = t.initial_id AND t.token = ' . $this->kernel->db->escape( $token['token'] ) . " AND t.type = 'announcement')";
                    }
                }
                $sql .= ' WHERE a.domain = ' . $this->kernel->db->escape( $this->pg_type ) . ' AND a.deleted = 0 AND a.enabled = 1';
                $sql .= ' AND UTC_TIMESTAMP() BETWEEN IFNULL(a.start_date, UTC_TIMESTAMP()) AND IFNULL(a.end_date, UTC_TIMESTAMP())';
                $sql .= " AND (a.shown_in_webpages = 'all' OR aw.webpage_id IS NOT NULL)";
                $sql .= ' ORDER BY a.order_index';
                $statement = $this->kernel->db->query($sql);
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }
                if ( $record = $statement->fetch() )
                {
                    $this->kernel->conf['announcement_enabled'] = TRUE;
                    $this->kernel->conf['announcement_start_date'] = $record['start_date'];
                    $this->kernel->conf['announcement_end_date'] = $record['end_date'];
                    $this->kernel->conf['announcement_session_check'] = $record['session_check'];
                    $this->kernel->conf['announcement_name'] = array(
                        $this->kernel->request['locale'] => $record['name']
                    );
                    $this->kernel->conf['announcement_content'] = array(
                        $this->kernel->request['locale'] => $record['content']
                    );
                }

                if($this->platform != "desktop")
                    $conf_platform = '_mobile';
                $this->kernel->smarty->assign('conf_locales', $conf_locales);
                $this->kernel->smarty->assign('conf', $this->kernel->conf);
                $this->kernel->smarty->assign('conf_platform', $conf_platform);

                // get menu
                $menu = $this->get_menu($this->sitemap->getRoot(), -1);

                $footer_nav = "";

                $footer = false;

                // get footer
                if($this->kernel->conf['footer_webpage_id']) {
                    $footer = $this->sitemap->getRoot()->findById($this->kernel->conf['footer_webpage_id']);
                }

                if(!$footer) {
                    $fp = new staticPage();
                    $fp->setPlatforms(array($this->platform));
                    $footer = new pageNode($fp, $this->platform);
                }

                if($this->platform != "desktop" || $this->is_mobile) {
                    foreach($this->alternate_urls as $platform => $url) {
                        $q_str = array();

                        if($this->kernel->conf['mobile_domain'] == $this->kernel->conf['default_domain']) {
                            switch($platform) {
                                case "desktop":
                                    $q_str['m'] = 0;
                                    break;
                                case "mobile":
                                    $q_str['m'] = 1;
                                    break;
                                default:
                                    break;
                            }
                        }

                        $tmp_page = new staticPage();
                        $tmp_page->setLocales(array($this->kernel->request['locale']));
                        $tmp_page->setPlatforms(array($this->platform));

                        $tmp_page->setRelativeUrls(
                            array($this->platform => '//'.($platform == "mobile" ? $this->kernel->conf['mobile_domain'] : $this->kernel->conf['default_domain']) . $this->kernel->sets['paths']['app_from_doc'] . $url . (count($q_str) > 0 ? '?' . http_build_query($q_str) : ''))
                        );
                        $tmp_page->setTitle(
                            array($this->kernel->request['locale'] => $this->kernel->dict['TEXT_' . ($this->platform == "desktop" ? "mobile" : "desktop") . '_site'])
                        );
                        $tmp_page->setShownInMenu(true);

                        $footer->PrependChild(new pageNode($tmp_page, $this->platform));
                    }
                }

                if($footer) {
                    $footer_nav = $this->get_menu($footer, 0);
                }

                $this->kernel->smarty->assign('main_nav', $menu);
                $this->kernel->smarty->assign('footer_nav', $footer_nav);

                foreach($this->filesToFetch as $type => $path) {
                    if(file_exists($path)) {
                        $this->kernel->response[$type] = $this->kernel->smarty->fetch(
                            $path
                        );
                    }
                }
            }

            // Apply master template, if needed
            if ( $this->apply_template )
            {

                if($_GET['plaintext'])
                    $this->kernel->response['content'] = array_ifnull( $this->data['webpage'], 'content' );
                else {

                    $this->breadcrumb->unshift(new breadcrumbNode(
                            // Remove site name
                            // $this->kernel->conf[$this->kernel->request['locale']]['site_name'],
                            '',
                            $this->base_url
                        )
                    );

                    $files = array(
                        'content' => !is_null($this->overwrite_tpl_id) && $this->_root_template_id == $this->overwrite_tpl_id ? $this->overwrite_tpl_file : ("file/template/" . $this->_root_template_id . "/index.html"),
                        'js' => "file/template/" . $this->_root_template_id . "/index.js"
                    );

                    $this->kernel->response['title'] = $this->breadcrumb->toTitle();

                    foreach($files as $type => $path) {
                        if(file_exists($path)) {
                            $this->kernel->response[$type] = $this->kernel->smarty->fetch(
                                $path
                            );
                        }
                    }
                }
            }

            // Resolved URLs for HTML internal anchors
            if ( $this->kernel->response['mimetype'] == 'text/html' && count($this->data['webpage']) > 0 )
            {
                $prefix = $this->kernel->sets['paths']['mod_from_doc'] . $this->data['webpage']['path'];
                if ( isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' )
                {
                    $prefix .= '?' . $_SERVER['QUERY_STRING'];
                }
                $this->kernel->response['content'] = preg_replace(
                    '/href\=\"\#/',
                    'href="' . htmlspecialchars( $prefix ) . '#',
                    $this->kernel->response['content']
                );
            }
        }
    }

    protected function msg($msg = null, $redirect = null) {
        $_GET['l'] = strtolower(trim(array_ifnull($_GET, 'l', '')));
        $_GET['r'] = trim(array_ifnull($_GET, 'r', ''));

        if(is_null($msg))
            $msg = $_GET['l'];

        if(is_null($redirect))
            $redirect = $_GET['r'];

       if($msg && $this->kernel->dict['MESSAGE_' . $msg]) {
            $this->kernel->dict['LABEL_message'] = $this->kernel->dict['LABEL_' . $msg];
            switch($msg) {
                case 'login_success':
                    $msg = sprintf($this->kernel->dict['MESSAGE_' . $msg], $this->user->getUsername());
                    break;
                default:
                    $msg = $this->kernel->dict['MESSAGE_' . $msg];
                    break;
            }
            $redirect = $this->get_valid_redirect($redirect);
            $this->kernel->smarty->assign('message', $msg);
            $this->kernel->smarty->assign('redirect', $redirect);
            $this->set_page_content( 'message' );
        } else {
            throw new statusException(400);
        }
    }

    /**
     * View a webpage based on path.
     *
     * @since   2008-11-13
     */
    function index()
    {
        // TODO: dynamically set the root template
        // set the root template
        // Get the template ID of static root webpage
        switch($this->platform) {
            case "desktop":
                $this->_root_template_id = 0;
                break;
            case "mobile":
                $this->_root_template_id = 100;
                break;
        }

        if(is_null($this->_target_template_id))
            $this->_target_template_id = $this->_root_template_id;

        /** @var pageNode $page_node */
        $page_node = null;
        $path_exact_match = true;

        extract($this->findPage($this->data['webpage']['path']));

        // page found and not yet expired / deleted / disabled
        if($page_node) {
            $this->page_node = $page_node;
            /** @var staticPage | webpageLinkPage | urlLinkPage $page | structuredPagePage */
            $page = $page_node->getItem();
            $this->current_url = $page->getRelativeUrl($this->platform);

            //if($this->kernel->conf['404_webpage_id'] == $page->getId())
               //$this->kernel->response['status_code'] = 404;

            if($path_exact_match)
                $this->page_found = true;

            $pageType = $this->outputPageType = get_class($page);

            $webpage_cached = false;

            // it must be a static page or other types which the page is exactly found to continue processing
            // otherwise not found should be returned with any process at below
            if($pageType == "staticPage" || $pageType == "structuredPagePage"
                || (in_array($pageType, array("webpageLinkPage", "urlLinkPage")) && $this->page_found)) {
                if($this->dummy_content) {
                    $dummy_contents = array();
                    foreach(array_keys($this->kernel->dict['SET_content_types'][$this->platform]) as $cb_name) {
                        $dummy_contents[$cb_name] = $this->kernel->dict['MESSAGE_dummy_content'];
                    }

                    $page->setDescription(array( $this->kernel->request['locale'] => $this->kernel->dict['MESSAGE_dummy_description'] ));
                    if($pageType != "structuredPagePage")
                        $page->setHeadlineTitle(array( $this->kernel->request['locale'] => $this->kernel->dict['MESSAGE_dummy_title'] ));
                    $page->setLocales(array($this->kernel->request['locale']));
                    $page->setMajorVersion(0);
                    $page->setMinorVersion(0);
                    $page->setPlatforms(array($this->platform));
                    $page->setTitle(array( $this->kernel->request['locale'] => $this->kernel->dict['MESSAGE_dummy_title']));

                    if($this->_target_template_id != $this->_root_template_id) {
                        $page->setTemplate($this->platform, $this->_target_template_id);
                    }

                    $page->setContents(
                        array(
                             $this->platform => array( $this->kernel->request['locale'] => $dummy_contents )
                        )
                    );

                } else {
                    // get page content for the page specified
                    $cache_path = sprintf("{$this->kernel->sets['paths']['app_root']}/file/cache/webpage.%s.%s.%s.%s.tmp",
                        $page->getId(),
                        $this->kernel->request['escaped_locale'],
                        $this->data['mode'],
                        $this->platform
                    );

                    // TODO: overwrite the data to new one if version has specified and has found (for private)
                    $webpage_cached = file_exists( $cache_path );
                    if(!$page->data_retrieved) {
                        if ( $webpage_cached )
                        {
                            // replace the whole object with data that has already set
                            try {
                                $p = unserialize(base64_decode(file_get_contents($cache_path)));
                                if(!$p) {
                                    throw new Exception("");
                                }

                                $page = $p;
                                $this->page_node->setItem($page);
                            } catch(Exception $e) {
                                rm($cache_path);
                                $page->retrieveData($this->kernel->request['locale'], $this->pg_type);
                            }
                        } else {
                            $page->retrieveData($this->kernel->request['locale'], $this->pg_type);
                        }
                    }

                    // Delete cache and reload if major version of sitemap and page do not match
                    if(!$page->data_retrieved)
                    {
                        $this->clear_cache();
                        $this->apply_template = FALSE;
                        $this->kernel->response['refresh'] = 0;
                        return;
                    }

                    // see if related locale could be found for this page - if not, will process below
                    if(!$page->hasLocale($this->kernel->request['locale'])) {
                        // find all its children and see if any of it has this locale, if yes, just redirect to that page
                        $children = $page_node->getChildren();

                        /** @var pageNode $c2 */
                        $c2 = null;
                        foreach($children as $c2) {
                            if($c2->getItem()->hasLocale($this->kernel->request['locale'])) {
                                // redirection to that page
                                $url = $this->kernel->sets['paths']['app_from_doc'] . '/'
                                    . $this->kernel->request['locale'] . $c2->getItem()->getRelativeUrl($this->platform)
                                    . '?ref=' . urlencode($_SERVER['REQUEST_URI']);
                                $this->kernel->redirect($url, 302);
                                return;
                            }
                        }

                        $p = $this->sitemap->getRoot();
                        if($this->kernel->conf['404_webpage_id'] && $p)
                        {
                            $this->kernel->response['status_code'] = 404;

                            $p = $p->findById($this->kernel->conf['404_webpage_id']);
                            extract($this->findPage($p->getItem()->getRelativeUrl($this->platform)));
                            $this->page_node = $page_node;
                            $not_found_page = $page_node->getItem();
                            $not_found_page->retrieveData($this->kernel->request['locale'], $this->pg_type);

                            $sql = sprintf('SELECT * FROM templates WHERE id = %d AND deleted = 0'
                            , $not_found_page->getTemplate($this->platform));
                            $statement = $this->kernel->db->query($sql);
                            if ( !$statement )
                            {
                                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                            }

                            if($record = $statement->fetch()) {
                                $this->_target_template_id = $record['base_template_id'];
                                //$this->kernel->smarty->assign('base_template_id', $this->_target_template_id);
                                $this->kernel->smarty->assign('bodyClass', $record['css_class']);
                            }

                            $section_contents = $not_found_page->getPlatformHtml($this->platform, $this->kernel->request['locale']);

                            $this->kernel->smarty->assign('pg', $not_found_page);
                            $this->kernel->smarty->assign('section_contents', $section_contents);

                            // Check whether load tracing code script
                            $load_tracing_script = false;
                            $tracing_script = '';
                            if(file_exists('file/tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js'))
                            {
                                $load_tracing_script = true;
                                $tracing_script = file_get_contents('file/tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js');
                            }

                            $load_header_tracing_script = false;
                            $header_tracing_script = '';
                            if(file_exists('file/header_tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js'))
                            {
                                $load_header_tracing_script = true;
                                $header_tracing_script = file_get_contents('file/header_tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js');
                            }

                            $this->filesToFetch = array(
                                'content' => !is_null($this->overwrite_tpl_id) && $this->_target_template_id == $this->overwrite_tpl_id ? $this->overwrite_tpl_file : ("file/template/" . $this->_target_template_id . "/index.html"),
                                //'content' => "file/template/" . $this->_target_template_id . "/index.html",
                                'js' => "file/template/" . $this->_target_template_id . "/index.js"
                            );

                            $this->kernel->smarty->assign('load_tracing_script', $load_tracing_script);
                            $this->kernel->smarty->assign('load_header_tracing_script', $load_header_tracing_script);
                            $this->kernel->smarty->assign('tracing_script', $tracing_script);
                            $this->kernel->smarty->assign('header_tracing_script', $header_tracing_script);

                            //$this->kernel->redirect($not_found_url, 302);
                        }
                        else
                            throw new statusException(404);
                    }

                    //
                    // or do it here to have a better response code (403)
                    if(!$page_node->accessible($this->user->getRole()->getId())) {
                        // login page
                        $p = $this->sitemap->getRoot();
                        if($this->kernel->conf['login_webpage_id'] && $p) {
                            $p = $p->findById($this->kernel->conf['login_webpage_id']);
                        } else
                            $p = false;

                        // anonymous user
                        if(!$this->user->getId() && $p) {
                            // redirection to login page
                            $url = $this->kernel->sets['paths']['app_from_doc'] . '/'
                                    . $this->kernel->request['locale'] . $p->getItem()->getRelativeUrl($this->platform)
                                    . '?ref=' . urlencode($_SERVER['REQUEST_URI']);
                            $this->kernel->redirect($url, 302);
                            return;
                        }
                        throw new statusException(403);
                    }
                }

                // perform different action according to page
                switch($pageType) {
                    case "staticPage":
                    case "structuredPagePage":
                        $referer = array_ifnull($_SERVER, 'HTTP_REFERER', NULL);
                        $from_self = preg_match('#^https?\:\/\/(www\.)?((' . preg_quote($this->kernel->conf['mobile_domain'], '#') . ')|(' . preg_quote(preg_replace('#^www\.#', '', $this->kernel->conf['default_domain']), '#') . '))#i', $referer);

                        $extra_path = $this->data['webpage']['path'];

                        /** @var staticPage $page */
                        $page = $this->getPageNode()->getItem();
                        $url = $page->getRelativeUrl($this->platform);

                        $extra_path = substr($extra_path, strlen($url));

                        foreach($this->alternate_urls as $alternate_platform => $dummy) {
                            $this->alternate_urls[$alternate_platform] = $page->getAlternateUrl($alternate_platform) . $extra_path;
                        }

                        if(!$from_self && $this->is_mobile && $this->platform != "mobile"
                            && (!isset($_SESSION['mobile_override']))) {

                            $get_vars = $_GET;
                            unset($get_vars['m']);
                            if($this->kernel->conf['mobile_domain'] == $this->kernel->conf['default_domain']) {
                                $get_vars['m'] = 1;
                            }

                            $this->kernel->redirect( '//' . $this->kernel->conf['mobile_domain']
                                    . $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale']
                                    . $this->alternate_urls["mobile"]
                                    . (count($get_vars) ? '?' . http_build_query($get_vars) : '')
                                    //. ($this->kernel->conf['mobile_domain'] == $this->kernel->conf['default_domain'] ? "?m=1" : "")
                                    , 302 );
                            return TRUE;
                        }

                        break;
                    case "webpageLinkPage":
                        // find the target page
                        $target_id = $page->getLinkedPageId($this->platform);
                        if($target_id && in_array($this->kernel->request['locale'], $page->getLocales())) {
                            /** @var pageNode $target_page */
                            $target_page = $this->sitemap->getRoot($this->platform)->findById($target_id);
                            if($target_page) {
                                $query_string = $page->getLocaleQueryString($this->kernel->request['locale']);
                                if ( !$query_string )
                                {
                                    $query_string = $page->getQueryString($this->platform);
                                }
                                if ( $query_string && $query_string[0] != '?' )
                                {
                                    $query_string = '?' . $query_string;
                                }
                                $target_url = $this->kernel->sets['paths']['mod_from_doc'] . $target_page->getItem()->getRelativeUrl($this->platform) . $query_string;
                                $this->kernel->redirect( $target_url, 302 );
                            }
                        } else {
                            // 404
							$this->page_found = false;
                        }

                        break;
                    case "urlLinkPage":
                        $page->decode();
                        $url = $page->getUrl($this->kernel->request['locale'], $this->kernel->conf['cloudfront_domain']);
                        $this->kernel->redirect( $url, 302 );

                        break;
                }

                if(!$webpage_cached && $this->data['mode'] == 'view') {
                    file_put_contents( $cache_path, base64_encode(serialize($page)) );
                }

                if($pageType == "staticPage" || $pageType == 'structuredPagePage') {
                    $page->getContents();

                    if($this->sitemap->getRoot()->getItem()->getId() == $page->getId()
                        || ($page->hasContent() || !$page_node->hasChild())) {
                        $page->decode();

                        if($this->page_found) {
                            $tmp = $page_node;

                            while($tmp && !is_null($tmp)) {
                                $this->breadcrumb->unshift(new breadcrumbNode(
                                        (get_class($tmp->getItem()) == "staticPage" || get_class($tmp->getItem()) == 'structuredPagePage') ?
                                        $tmp->getItem()->getSeoTitle($this->kernel->request['locale'])
                                        : $tmp->getItem()->getTitle($this->kernel->request['locale']),
                                        $this->base_url . $tmp->getItem()->getRelativeUrl($this->platform)
                                    )
                                );

                                $tmp = $tmp->getParent();
                            }

                            // TODO: store also the template information under the object, no time to do it now
                            if($pageType == 'structuredPagePage')
                            {
                                $this->_target_template_id = 1;
                                $this->kernel->smarty->assign('bodyClass', '');
                                $this->kernel->smarty->assign('aswfrontdomain', '//'.$this->kernel->conf['cloudfront_domain']);
                            }
                            else
                            {
                                $sql = sprintf('SELECT * FROM templates WHERE id = %d AND deleted = 0'
                                , $page->getTemplate($this->platform));
                                $statement = $this->kernel->db->query($sql);
                                if ( !$statement )
                                {
                                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                                }

                                if(is_null($this->_target_template_id))
                                    $this->_target_template_id = 0;

                                if($record = $statement->fetch()) {
                                    $this->_target_template_id = $record['base_template_id'];
                                    //$this->kernel->smarty->assign('base_template_id', $target_template_id);
                                    $this->kernel->smarty->assign('bodyClass', $record['css_class']);
                                }
                            }

                            /** offer module START */
                            /** to FETCH offers for each page according to language */
                            require_once( dirname(__DIR__) . '/offer_admin/index.php' );

                            // get offers
                            $offer_ids = $page->getOfferIds();

                            if(is_null($offer_ids) || !count($offer_ids)) {
                                $offers = offer_admin_module::getWebpageOffers($page->getId(), $this->pg_type);

                                /*
                                if(!count($offers)) {
                                    // take offers from root page
                                    $offers = offer_admin_module::getWebpageOffers($this->sitemap->getRoot()->getItem()->getId(), $this->pg_type);
                                }
                                */

                                if(count($offers)) {
                                    $offer_ids = array();
                                    foreach($offers as $offer) {
                                        $offer_ids[] = $offer['id'];
                                    }
                                }
                            }

                            $detail_offers = array();

                            if(!is_null($offer_ids) && count($offer_ids)) {
                                $tmp = offer_admin_module::getOfferDetails($offer_ids, $this->kernel->request['locale'], 'desktop', $this->pg_type);
                                foreach($offer_ids as $id) {
                                    // exist and still valid
                                    if(isset($tmp[$id]) && $tmp[$id]['started'] && !$tmp[$id]['ended']) {
                                        $detail_offers[$id] = $tmp[$id];
                                    }
                                }
                            }

                            $this->kernel->smarty->assign('offers', $detail_offers);
                            /** offer module END */

                            if($pageType == "staticPage")
                            {
                                $section_contents = $page->getPlatformHtml($this->platform, $this->kernel->request['locale']);
                            }
                            else
                            {
                                $structured_page_template = $page->getStructuredPageTemplate();
                                $structured_page_data = $page->getPlatformHtml($this->platform, $this->kernel->request['locale']);
                                $structured_page_data = json_decode($structured_page_data['content'], true);
                                $spd_tmp = $structured_page_data;
                                $structured_page_data = array();
                                if (is_array($spd_tmp) || is_object($spd_tmp))
                                {
                                    foreach($spd_tmp as $section_id=>$field_data)
                                    {
                                        if(!isset($structured_page_data[$section_id]))
                                            $structured_page_data[$section_id]=array();
                                        foreach($field_data as $key=>$val)
                                        {
                                            if ( $key == 'content' )
                                            {
                                                $val = $page->snippetDecode( $val );
                                            }

                                            if(!preg_match('/^([A-Za-z_]+)([\d]+)$/i', $key, $matches))
                                            {
                                                if($key == 'content_block')
                                                {
                                                    $structured_page_data[$section_id]['general']['snippet_content'] = '';
                                                    if($path_exact_match)
                                                    {
                                                        if($val != '' && $val>0)
                                                        {
                                                            $structured_page_data[$section_id]['general']['snippet_content'] = $page->snippetDecodeById($val, $this->kernel->request['locale']);
                                                        }
                                                    }
                                                    else
                                                    {
                                                        $structured_page_data[$section_id]['general']['snippet_content'] = $spd_tmp[0]['real_content'];
                                                    }
                                                }
                                                else
                                                {
                                                    $structured_page_data[$section_id]['general'][$key] = $val;
                                                }
                                            }
                                            else
                                            {
                                                if(!isset($structured_page_data[$section_id]['loop'][$matches[2]]))
                                                    $structured_page_data[$section_id]['loop'][$matches[2]] = array();
                                                $structured_page_data[$section_id]['loop'][$matches[2]][$matches[1]] = $val;
                                                if(preg_match('/_order/i', $matches[1]))
                                                {
                                                    if(preg_match('/^[\d]+$/i', $val))
                                                        $structured_page_data[$section_id]['display_order'][$matches[2]]=$val;
                                                    else if($val == '')
                                                        $structured_page_data[$section_id]['display_order'][$matches[2]]=0;
                                                }
                                                if($matches[1] == 'room_webpage')
                                                {
                                                    $p = $this->sitemap->getRoot()->findById($val);
                                                    if($p)
                                                    {
                                                        $i = $p->getItem();
                                                        $d = json_decode($i->getPlatformHtml($this->platform, $this->kernel->request['locale'])['content'], TRUE);
                                                        $structured_page_data[$section_id]['loop'][$matches[2]] += array(
                                                            'title' => $d[15]['section_heading'],
                                                            'short_description' => $d[15]['short_description1'],
                                                            'image' => $d[15]['image1'],
                                                            'path' => $i->getRelativeUrl($this->platform, TRUE)
                                                        );
                                                    }
                                                }
                                            }
                                        }
                                        if(isset($structured_page_data[$section_id]['display_order']))
                                        {
                                            //asort($structured_page_data[$section_id]['display_order']);
                                            $this->aksort($structured_page_data[$section_id]['display_order']);
                                        }
                                    }

                                    // Remove empty loop items
                                    foreach($structured_page_data as $section_id => &$s)
                                    {
                                        if(array_key_exists('loop', $s))
                                        {
                                            foreach($s['loop'] as $k => $v)
                                            {
                                                if(implode('', $v) == '')
                                                {
                                                    unset($s['loop'][$k]);
                                                }
                                            }
                                        }
                                    }

                                    unset($spd_tmp);

                                    // Dining and room landing
                                    $structured_pages = array(
                                        4 => array('template' => 2, 'section' => 11),
                                        5 => array('template' => 3, 'section' => 15)
                                    );
                                    if(array_key_exists($structured_page_template, $structured_pages)) {
                                        extract($structured_pages[$structured_page_template]);
                                        $loop = array();
                                        foreach($page_node->getParent()->getChildren() as $p)
                                        {
                                            $i = $p->getItem();
                                            if(get_class($i) == 'structuredPagePage' && $i->getStructuredPageTemplate() == $template
                                                && (!$i->getPublicationDate() || $i->getPublicationDate() <= $this->data['now'])
                                                && (!$i->getRemovalDate() || $i->getRemovalDate() >= $this->data['now']))
                                            {
                                                $i->retrieveData($this->kernel->request['locale'], $this->pg_type);
                                                $d = json_decode($i->getPlatformHtml($this->platform, $this->kernel->request['locale'])['content'], TRUE);
                                                $loop[count($loop)+1] = array(
                                                    'title' => $d[$section]['section_heading'],
                                                    'short_description' => $d[$section]['short_description1'],
                                                    'image' => $d[$section]['image1'],
                                                    'path' => $i->getRelativeUrl($this->platform, TRUE),
                                                    'reservation_url' => $structured_page_template == 4 ? $d[12]['reservation_url'] : ''
                                                );
                                            }
                                        }
                                        $structured_page_data[] = array(
                                            'loop' => $loop
                                        );
                                    }
                                }

                                $this->kernel->smarty->assign('structured_page_data', $structured_page_data);
                                $this->kernel->smarty->assign('path_exact_match', $path_exact_match);
                                $section_contents['content'] = $this->kernel->smarty->fetch('file/template/'.$this->_target_template_id.'/structured_page_template/'. $structured_page_template.'.html');
                            }

                            $this->kernel->smarty->assign('section_contents', $section_contents);

                            // Check whether load tracing code script
                            $load_tracing_script = false;
                            $tracing_script = '';
                            if(file_exists('file/tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js'))
                            {
                                $load_tracing_script = true;
                                $tracing_script = file_get_contents('file/tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js');
                            }

                            $load_header_tracing_script = false;
                            $header_tracing_script = '';
                            if(file_exists('file/header_tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js'))
                            {
                                $load_header_tracing_script = true;
                                $header_tracing_script = file_get_contents('file/header_tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js');
                            }

                            $this->filesToFetch = array(
                                'content' => !is_null($this->overwrite_tpl_id) && $this->_target_template_id == $this->overwrite_tpl_id ? $this->overwrite_tpl_file : ("file/template/" . $this->_target_template_id . "/index.html"),
                                //'content' => "file/template/" . $this->_target_template_id . "/index.html",
                                'js' => "file/template/" . $this->_target_template_id . "/index.js"
                            );

                            $this->kernel->smarty->assign('load_tracing_script', $load_tracing_script);
                            $this->kernel->smarty->assign('load_header_tracing_script', $load_header_tracing_script);
                            $this->kernel->smarty->assign('tracing_script', $tracing_script);
                            $this->kernel->smarty->assign('header_tracing_script', $header_tracing_script);
                        }

                    } else {
                        //$children = $page_node->getChildren(0);
///..
                        //$target_page = $children[0];

                        // Redirect to the first available child page rather than first child page
                        $children = $page_node->getChildren();

                        $target_page = null;
                        foreach($children as $c) {
                            if($c->getItem()->hasLocale($this->kernel->request['locale'])) {
                                $target_page = $c;

                                break;
                            }
                        }

                        if($target_page) {
                            $target_url = $this->kernel->sets['paths']['mod_from_doc'] .  $target_page->getItem()->getRelativeUrl($this->platform);
                            $this->kernel->redirect( $target_url, 302 );
                        }
                    }

                    $this->kernel->smarty->assign('pg', $page);
                }
            }
        }

        if(!$this->page_found) {
            // found in other platform
            $alternate_platforms = array_diff(array_keys($this->kernel->dict['SET_webpage_page_types']), array($this->platform));

            foreach($alternate_platforms as $alternate_platform) {
                $sm = $this->get_sitemap($this->data['mode'], $alternate_platform);
                $pn = $sm->findPage($this->data['webpage']['path']);

                // the page is found and is available
                if($pn && !is_null($pn) && !$pn->getDeleted() && $pn->getEnabled() && $pn->available($this->kernel->request['locale'])) {
                    $this->page_found = true;

                    $prefix_path = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
                    $extra_get_vars = array();

                    switch($alternate_platform) {
                        case 'mobile':
                            $prefix_path .= $this->kernel->conf['mobile_domain'];
                            $extra_get_vars = array('m' => 1);

                            break;
                        case 'desktop':
                        default:
                            $prefix_path .= $this->kernel->conf['default_domain'];
                            $extra_get_vars = array('m' => 0);

                            break;
                    }

                    $get_vars = array_merge($_GET, $extra_get_vars);
                    $url = $prefix_path . $this->kernel->sets['paths']['app_from_doc'] . '/'
                        . $this->kernel->request['locale'] . $pn->getItem()->getRelativeUrl($alternate_platform)
                        . (count($get_vars) ? '?' . http_build_query($get_vars) : '');

                    $this->kernel->redirect($url, 302);

                    break;
                }
            }
        }

        if(!$this->page_found) {
            $p = $this->sitemap->getRoot();
            if($this->kernel->conf['404_webpage_id'] && $p)
            {
                //$desclog = "Dead link: ".$_SERVER['REQUEST_URI'];
                //$refererlog = array_ifnull($_SERVER , 'HTTP_REFERER','');
                //$this->kernel->log( "Page NOT found", $desclog, $refererlog, 0 );

                $this->kernel->response['status_code'] = 404;

                $p = $p->findById($this->kernel->conf['404_webpage_id']);
                extract($this->findPage($p->getItem()->getRelativeUrl($this->platform)));
                $this->page_node = $page_node;
                $not_found_page = $page_node->getItem();
                $not_found_page->retrieveData($this->kernel->request['locale'], $this->pg_type);

                $sql = sprintf('SELECT * FROM templates WHERE id = %d AND deleted = 0'
                , $not_found_page->getTemplate($this->platform));
                $statement = $this->kernel->db->query($sql);
                if ( !$statement )
                {
                    $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                }

                if($record = $statement->fetch()) {
                    $this->_target_template_id = $record['base_template_id'];
                    //$this->kernel->smarty->assign('base_template_id', $this->_target_template_id);
                    $this->kernel->smarty->assign('bodyClass', $record['css_class']);
                }

                $section_contents = $not_found_page->getPlatformHtml($this->platform, $this->kernel->request['locale']);

                $this->kernel->smarty->assign('pg', $not_found_page);
                $this->kernel->smarty->assign('section_contents', $section_contents);

                // Check whether load tracing code script
                $load_tracing_script = false;
                $tracing_script = '';
                if(file_exists('file/tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js'))
                {
                    $load_tracing_script = true;
                    $tracing_script = file_get_contents('file/tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js');
                }

                $load_header_tracing_script = false;
                $header_tracing_script = '';
                if(file_exists('file/header_tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js'))
                {
                    $load_header_tracing_script = true;
                    $header_tracing_script = file_get_contents('file/header_tracking_code_script/'.$this->kernel->request['escaped_locale'].'.js');
                }

                $this->filesToFetch = array(
                    'content' => !is_null($this->overwrite_tpl_id) && $this->_target_template_id == $this->overwrite_tpl_id ? $this->overwrite_tpl_file : ("file/template/" . $this->_target_template_id . "/index.html"),
                    //'content' => "file/template/" . $this->_target_template_id . "/index.html",
                    'js' => "file/template/" . $this->_target_template_id . "/index.js"
                );

                $this->kernel->smarty->assign('load_tracing_script', $load_tracing_script);
                $this->kernel->smarty->assign('load_header_tracing_script', $load_header_tracing_script);
                $this->kernel->smarty->assign('tracing_script', $tracing_script);
                $this->kernel->smarty->assign('header_tracing_script', $header_tracing_script);

                //$not_found_url = $this->kernel->sets['paths']['app_from_doc'] . '/' . $this->kernel->request['locale'] . $p->getItem()->getRelativeUrl($this->platform);

                //$this->kernel->redirect($not_found_url, 302);
            }
            else
                throw new statusException(404);
        }

    }


    /**
     * Generate the sitemap XML.
     * http://www.sitemaps.org
     *
     * @since   2009-07-30
     * @return  Processed or not
     */
    function sitemap()
    {
        $content = '<?xml version="1.0" encoding="UTF-8"?>';
        $content .= '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $content .= ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"';
        $content .= ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $nodes = $this->sitemap->getRoot()->getChildren();
        /** @var pageNode $node */
        $node = null;
        foreach($nodes as $node) {
            /** @var page $page */
            $page = $node->getItem();
            if( $node->getItem()->hasLocale($this->kernel->request['locale'])
                && !$node->getDeleted() && $node->accessible($this->user->getRole()->getId())
                && !$node->getEnabled() && $node->available($this->kernel->request['locale']) && $page->getShownInSitemap()){
                $content .= '<url>';
                $content .= sprintf('<loc>%s</loc>', $this->base_url . $node->getItem()->getRelativeUrl($this->platform));
                $content .= sprintf( '<lastmod>%s</lastmod>', str_replace(' ', 'T', $node->getItem()->getUpdatedDate()) . '+00:00');
                $content .= '</url>';
            }
        }

        $content .= '</urlset>';

        // Set outputs
        $this->apply_template = FALSE;
        $this->kernel->response['charset'] = '';
        $this->kernel->response['filename'] = 'sitemap ' . gmdate('Ymd His', time()) . '.xls';
        $this->kernel->response['mimetype'] = 'application/xml';
        $this->kernel->response['content'] = $content;
    }

    function processException($e) {
        // not found - create a dynamic static page
        $pageType = $this->outputPageType = "staticPage";

        /** @var staticPage $page */
        $page = new $pageType();
        $page->setPlatforms(array($this->platform));
        $page->setLocales(array($this->kernel->request['locale']));
        if(get_class($e) == 'smartyException')
        {
            $page->setTitle(array( $this->kernel->request['locale'] => $this->kernel->dict['LABEL_500'] ));
        }
        else
            $page->setTitle(array( $this->kernel->request['locale'] => $this->kernel->dict['LABEL_' . $e->status_code] ));
        $page->setTemplate($this->platform,  ($this->_root_template_id + 1));

        $this->kernel->smarty->assign('pg', $page);

        if(get_class($e) == 'smartyException')
        {
            $this->kernel->response['status_code'] = 500;
        }
        else
            $this->kernel->response['status_code'] = $e->status_code;

        if(get_class($e) == 'smartyException')
        {
            $this->kernel->smarty->assign('section_contents', array(
                   'content' => sprintf(
                       $this->kernel->dict['DESCRIPTION_500_html'],
                       htmlspecialchars($this->data['webpage']['path'])
                   )
              ));
        }
        else
            $this->kernel->smarty->assign('section_contents', array(
                                                               'content' => sprintf(
                                                                   $this->kernel->dict['DESCRIPTION_' . $e->status_code . '_html'],
                                                                   htmlspecialchars($this->data['webpage']['path'])
                                                               )
                                                          ));

        $this->_target_template_id = $page->getTemplate($this->platform);

        $this->filesToFetch = array(
            'content' => "file/template/" . $page->getTemplate($this->platform) . "/index.html"
        );

        if(get_class($e) == 'smartyException' || $e->status_code == 500) {
            $debug_stack = array();
            $debug = $e->getTrace();
            $c = count($debug);
            $line = __LINE__;
            $path = __FILE__;

            if(!isset($e->debug) || $e->debug) {
                for($i = 0; $i < $c; $i++) {
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
            }

            $log_message = implode("\n>>", $debug_stack);

            $this->kernel->db->rollBack();
            $this->kernel->db->beginTransaction();
            if($log_message != "") {
                //exit;
                if(strlen($log_message) > 10000) {
                    $path = $this->kernel->sets['paths']['app_root'] . '/file/logs/error_' . (time() + microtime()) . '.txt';

                    $handle = fopen($path, "w");
                    fwrite($handle, $log_message);
                    fclose($handle);

                    $log_message = sprintf('Error message too long and will be stored here: %s', $path);
                }
                $this->kernel->log( "error", $log_message, $path, $line );
            }
            $this->kernel->db->commit();
        }
    }


    function findPage($path, $finding_parent = false) {
        /** @var pageNode $page_node */
        $page_node = $this->sitemap->findPage($path);

        $available = $page_node && $page_node->available($this->kernel->request['locale']);
        if($this->data['mode'] == 'preview' && $path == $this->data['webpage']['path']) {   // Enable direct access of unavailable page in preview site
            $available = TRUE;
        }
        if($page_node && !$page_node->getDeleted() && $page_node->getEnabled() && $available) {
            return array(
                'page_node' => $page_node,
                'path_exact_match' => !$finding_parent
            );
        } else {
            // parent
            $prev_path = $path;
            $path = preg_replace('#^(.*?\/)[^\/]*\/?$#i', '\\1', $path);
            if($path !== "" && $prev_path != $path)
                return $this->findPage($path, true);
        }

        return array(
            'page_node' => null,
            'path_exact_match' => false
        );
    }

    protected function getRoleTree($show_enabled = false, $type = "public") {
        // treat as a container to store root nodes
        $tmp_roles = array();
        $roleTree = new treeNode();
        $roleTree->setLevel(-1);

        $sql = 'SELECT * FROM roles';
        if ( $type == 'admin' )
        {
            $sql .= ' WHERE `type` = ' . $this->kernel->db->escape($type);
        }
        $sql .= ' ORDER BY root_role DESC, parent_id IS NULL DESC, `level` ASC';
        $statement = $this->kernel->db->query($sql);
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

    protected function get_valid_redirect($url) {
        $redirect = "";

        // should be redirections to internel locations
        if(preg_match('/^([a-zA-Z0-9\-\._\?\,\'\/\\\+&%\$#\=~\s\(\)]*\/)([a-zA-Z0-9\-\._\?\,\'\/\\\+&%\$#\=~\s\(\)]+\/)*/i', $url)) {
            if(preg_match('#^[^\/]#i', $url)) {
                $redirect = '/' . $url;
            } else {
                $redirect = $url;
            }

            return $redirect;
        }

        return false;
    }

    /* sort an array consider both keys and values, esp the values are same */
    function aksort(&$array,$valrev=false,$keyrev=false)
    {
        $sorted_array = array();
        if($valrev){
            arsort($array);
        }
        else{
            asort($array);
        }
        $vals = array_count_values($array);
        $i = 0;
        foreach ($vals AS $val=>$num) {
            $tmp = array_slice($array,$i,$num, true);
            if ($keyrev){
                krsort($tmp);
            }
            else{
                ksort($tmp);
            }
            $sorted_array = $sorted_array + $tmp;
            unset($tmp);
            $i = $i+$num;
        }
        $array = $sorted_array;
        unset($sorted_array);
    }

    /**
     * Set page content
     *
     * @since   2020-12-30
     * @param   type    The type
     * @param   page    The page, if any
     */
    protected function set_page_content( $type, $page = NULL )
    {
        $this->_target_template_id = $this->_root_template_id + 1;
        $locale = $this->kernel->request['locale'];
        $title = array_ifnull( $this->kernel->dict, "LABEL_$type", $type );
        $titles = array( $locale => $title );
        $section_contents = array();

        // Select or create page
        if ( is_null($page) )
        {
            $pageType = $this->outputPageType = 'staticPage';
            $page = new $pageType();
            $page->setPlatforms( array($this->platform) );
            $page->setLocales( array_keys($this->kernel->sets['public_locales']) );
            $page->setTemplate( $this->platform, $this->_target_template_id );
            $page->setTitle( $titles );
            $section_contents = array(
                'content' => $this->kernel->smarty->fetch( "file/template/{$this->_root_template_id}/$type.html" )
            );
        }
        else
        {
            $section_contents = $page->getPlatformHtml( $this->platform, $locale );
            $sql = sprintf( 'SELECT * FROM templates WHERE id = %d AND deleted = 0', $page->getTemplate($this->platform) );
            $statement = $this->kernel->db->query( $sql );
            if ( !$statement )
            {
                $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
            }
            if ( $record = $statement->fetch() )
            {
                $this->_target_template_id = $record['base_template_id'];
                $this->kernel->smarty->assign( 'bodyClass', $record['css_class'] );
            }
        }
        $page->setHeadlineTitle( $titles );
        $page->setSeoTitles( $titles );

        // Set page content
        $this->breadcrumb->unshift( new breadcrumbNode(
            $title,
            $this->kernel->sets['paths']['server_url'] . $_SERVER['REQUEST_URI']
        ) );
        $this->filesToFetch = array(
            'content' => "file/template/{$this->_target_template_id}/index.html"
        );
        $this->kernel->smarty->assign( 'pg', $page );
        $this->kernel->smarty->assign( 'section_contents', $section_contents );
        $this->kernel->response['status_code'] = 200;
    }

    /**
     * Validate hCaptcha response.
     *
     * @since   2020-12-31
     * @param   response    The response
     * @return  The error message, if any
     */
    public function validate_hcaptcha_response( $response )
    {
        $secret = $this->kernel->conf['hcaptcha_secret'];
        $error = NULL;
        $ch = curl_init( 'https://hcaptcha.com/siteverify' );
        curl_setopt( $ch, CURLOPT_POST, TRUE );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query(compact('response', 'secret')) );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        if ( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' )
        {
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        }
        $result = curl_exec( $ch );
        $curl_error = curl_error( $ch );
        if ( $curl_error === '' )
        {
            $result = json_decode( $result, TRUE );
            if ( is_null($result) )
            {
                $error = 'The response is not a valid JSON response.';
            }
            else
            {
                if ( !$result['success'] )
                {
                    $hcaptcha_errors = array(
                        'missing-input-secret' => 'Your secret key is missing.',
                        'invalid-input-secret' => 'Your secret key is invalid or malformed.',
                        'missing-input-response' => 'The response parameter (verification token) is missing.',
                        'invalid-input-response' => 'The response parameter (verification token) is invalid or malformed.',
                        'bad-request' => 'The request is invalid or malformed.',
                        'invalid-or-already-seen-response' => 'The captcha has already been checked, or has another issue.',
                        'sitekey-secret-mismatch' => 'The sitekey is not registered with the provided secret.'
                    );
                    $error = implode( "\r\n", array_intersect_key($hcaptcha_errors, array_combine(
                        $result['error-codes'],
                        array_fill( 0, count($result['error-codes']), '' )
                    )) );
                }
            }
        }
        else
        {
            $error = $curl_error;
        }
        return $error;
    }
}
