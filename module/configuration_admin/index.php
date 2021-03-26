<?php

// Include required files
require_once( dirname(dirname(__FILE__)) . '/admin/index.php' );
require_once( dirname(dirname(__FILE__)) . '/webpage_admin/index.php' );

/**
 * The configuration admin module.
 *
 * This module allows user to administrate configuration settings.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2008-12-01
 */
class configuration_admin_module extends admin_module
{
    public $module = 'configuration_admin';

    /**
     * Constructor.
     *
     * @since   2008-12-01
     * @param   kernel      The kernel
     */
    function __construct( &$kernel )
    {
        parent::__construct( $kernel );

        // Load additional dictionary file
        require( dirname(__FILE__) . "/locale/{$this->kernel->request['locale']}.php" );
        $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );

        $types = array( 'public', 'admin' );
        foreach ( $types as $type )
        {
            $roleTree = $this->getRoleTree( TRUE, $type );
            $role_options = $roleTree->generateOptions( FALSE );
            $role_keys = array_keys( $role_options );
            $role_keys = array_map( 'substr', array_keys($role_options), array_fill(0, count($role_options), 1) );
            $role_options = array_combine( $role_keys, array_values($role_options) );
            $this->kernel->dict["SET_{$type}_roles"] = $role_options;
        }
    }

    /**
     * Process the request.
     *
     * @since   2008-12-01
     */
    function process()
    {
        try{
            $op = array_ifnull( $_GET, 'op', 'index' );
            switch ( $op )
            {
                case "index":
                    $this->rights_required[] = Right::EDIT;
                    $this->method = "index";
                    break;
                case "tree":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "tree";
                    break;
                case "get_webpage_nodes":
                    $this->rights_required[] = Right::VIEW;
                    $this->method = "getWebpageNodes";
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

        return TRUE;
    }

    /**
     * Get the tree for webpage.
     *
     * @since   2009-04-27
     * @param   name        Name of radio input
     * @param   webpages    The webpages
     * @param   index       The order index
     * @param   path        The opened path
     * @return  The tree
     */
    function get_tree( $name, &$webpages, $index, $path = '' )
    {
        $menu = array();
        foreach ( $index as $i => $index_alias )
        {
            $webpage = $webpages[$index_alias];
            $has_child = isset( $webpage['child_webpages'] );
            $opened = strpos( $path, $webpage['path'] ) !== FALSE;

            // Wrapper HTML for webpage title
            $open_html = '';
            $close_html = '';
            if ( $webpage['status'] == 'pending' )
            {
                $open_html .= '<i>';
                $close_html .= '</i>';
            }
            if ( $webpage['deleted'] == 1 )
            {
                $open_html .= '<del>';
                $close_html .= '</del>';
            }

            $submenu = array(
                'text' => sprintf(
                    '<label title="%4$s" onclick="%7$s"><input type="radio" name="%1$s" value="%2$s"%5$s%6$s>%3$s</label>',
                    $name,
                    $webpage['webpage_id'],
                    $open_html . htmlspecialchars( "{$webpage['short_title']} (#{$webpage['webpage_id']})" ) . $close_html,
                    htmlspecialchars( $webpage['alias'] ),
                    $webpage['path'] === $path ? ' checked' : '',
                    $name == 'footer_webpage_id' && $webpage['path'] == '/' ? ' disabled' : '',
                    'var e = arguments[0] || window.event; if ( e.stopPropagation ) e.stopPropagation(); else e.cancelBubble = true;'
                )
            );
            if ( $has_child )
            {
                if ( $opened )
                {
                    $submenu['expanded'] = TRUE;
                    $submenu['children'] = $this->get_tree( $name, $webpage['child_webpages'], $webpage['index'], $path );
                }
                else
                {
                    $submenu['id'] = $webpage['path'];
                    $submenu['hasChildren'] = TRUE;
                }
            }

            $menu[] = $submenu;

        }
        return $menu;
    }

    /**
     * Tree for webpage.
     *
     * @since   2009-04-27
     */
    function tree()
    {
        // Get data from query string
        $target_path = trim( array_ifnull($_GET, 'root', '/') );
        if ( $target_path == 'source' )
        {
            $target_path = '/';
        }

        // Get the sitemap
        $sitemap = $this->get_sitemap( 'edit' );

        // Get the target webpage
        $target_path_segments = explode( '/', $target_path );
        $target_aliases = array_slice( $target_path_segments, 1, count($target_path_segments) - 2 );
        $target_webpage =& $sitemap['tree'];
        while ( $target_webpage && count($target_aliases) > 0 )
        {
            $target_alias = array_shift( $target_aliases );
            if ( isset($target_webpage['child_webpages'][$target_alias]) )
            {
                $target_webpage =& $target_webpage['child_webpages'][$target_alias];
            }
            else
            {
                $dummy_webpage = array();
                $target_webpage =& $dummy_webpage;
            }
        }

        $this->apply_template = FALSE;
        $this->kernel->response['mimetype'] = 'application/json';
        if ( isset($_GET['root']) && $_GET['root'] == 'source' )
        {
            $target_child_webpages = array( '' => $target_webpage );
            $target_index = array( '' );
            $this->kernel->response['content'] = json_encode( $this->get_tree(
                array_ifnull( $_GET, 'name', '' ),
                $target_child_webpages,
                $target_index,
                array_ifnull( $_GET, 'path', '' )
            ) );
        }
        else if ( isset( $target_webpage['child_webpages'] ) )
        {
            $target_child_webpages = $target_webpage['child_webpages'];
            $target_index = $target_webpage['index'];
            $this->kernel->response['content'] = json_encode( $this->get_tree(
                array_ifnull( $_GET, 'name', '' ),
                $target_child_webpages,
                $target_index,
                array_ifnull( $_GET, 'path', '' )
            ) );
        }
        else
        {
            $this->kernel->response['content'] = '[]';
        }
    }

    function getWebpageNodes($type = 'json', $platform = 'desktop', $target = 0, $disable_target = false) {
        return webpage_admin_module::getWebpageNodes($type, $platform, $target, $disable_target, webpage_admin_module::getWebpageAccessibility());
    }

    /**
     * List configuration settings.
     *
     * @since   2008-12-01
     */
    function index()
    {
        // Subsystems
        $subsystems = array(
            //'crm' => 'system/?op=configuration_edit',
            //'edm' => 'System/config',
            //'redemption' => 'configuration/',
            //'request' => 'system/?op=configuration_edit'
        );
        foreach ( $subsystems as $subsystem => $redirect_url )
        {
            $subsystems[$subsystem] = strtr(
                $this->kernel->conf["{$subsystem}_sso_url"],
                array(
                    ':key' => urlencode( $this->kernel->encrypt(json_encode(array(
                        'id' => $this->user->getId(),
                        'time' => time()
                    ))) ),
                    ':locale' => urlencode( $this->kernel->request['locale'] ),
                    ':redirect_url' => urlencode( $redirect_url ),
                )
            );
        }

        // Data container
        $data = array();
        $data_locales = array();
        $locales = array();
        $site_tree = array();
        $conf_locales = array();
        $tracking_code = array();
        $tracking_code_header = array();
        $country_default_locales = array();
        $countries_list = array();
        $domains = array('private', 'public');

        // get all public language locales
        $sql = "SELECT * FROM locales WHERE site='public_site' AND enabled=1 ORDER BY `default` DESC, order_index ASC";
        $statement = $this->conn->query($sql);
        $locales = $statement->fetchAll();

        // get all default locales assigned to countries
        $sql = 'SELECT * FROM country_default_locale';
        $statement = $this->conn->query($sql);
        while($r = $statement->fetch())
        {
            $country_default_locales[$r['iso_code']] = $r['locale'];
        }

        // Group countries name by first letter
        foreach($this->kernel->dict['SET_iso_country_codes'] as $iso_code=>$country_name)
        {
            $first_letter = $country_name[0];
            $countries_list[$first_letter][$iso_code] = $country_name;
        }

        // get the conf settings of user accessible locales
        $conf_locales_items = array('footer_static_content', 'site_name');
        foreach($this->kernel->dict['SET_accessible_locales'] as $locale=>$locale_name)
        {
            foreach($conf_locales_items as $item)
            {
                $conf_locales[$locale][$item] = '';
            }

            $sql = sprintf('SELECT * FROM configurations_locale WHERE locale=%1s',
                $this->conn->escape($locale)
            );
            $statement = $this->conn->query($sql);
            while($row = $statement->fetch())
            {
                if(in_array($row['name'], $conf_locales_items) && $locale == $row['locale'])
                    $conf_locales[$locale][$row['name']] = $row['value'];
            }
        }

        //Create tracking code directory and create file for each locale if not exit; else get the js code
        $script_folder = "{$this->kernel->sets['paths']['app_root']}/file/tracking_code_script";
        $header_script_folder = "{$this->kernel->sets['paths']['app_root']}/file/header_tracking_code_script";
        if(!is_dir($script_folder))
            force_mkdir($script_folder);
        if(!is_dir($header_script_folder))
            force_mkdir($header_script_folder);
        foreach($this->kernel->dict['SET_accessible_locales'] as $locale=>$locale_name)
        {
            $locale_escaped = preg_replace('/\//', '~', $locale);
            if(!file_exists($script_folder.'/'.$locale_escaped.'.js'))
            {
                //force_mkdir($script_folder.'/'.$locale.'.js');
            }
            else
            {
                $tracking_code[$locale] = file_get_contents($script_folder.'/'.$locale_escaped.'.js');
            }
            if(!file_exists($header_script_folder.'/'.$locale_escaped.'.js'))
            {
                //force_mkdir($script_folder.'/'.$locale.'.js');
            }
            else
            {
                $tracking_code_header[$locale] = file_get_contents($header_script_folder.'/'.$locale_escaped.'.js');
            }
        }

        $sp_pages = array('404_webpage_id', 'footer_webpage_id', 'offer_webpage_id', 'login_webpage_id');
        $ajax = (bool)array_ifnull($_REQUEST, 'ajax', 0);

        foreach($sp_pages as $name) {
            if(isset($this->kernel->conf[$name])) {

                $site_tree[$name] = array();
                // see if the webpage really exists
                $sql = sprintf('SELECT * FROM(SELECT id, major_version, minor_version, `type`'
                    . ', deleted, created_date, creator_id'
                    . ' FROM webpages WHERE domain = \'private\' AND id = %1$d'
                    . ' ORDER BY major_version DESC, minor_version DESC'
                    . ' LIMIT 0, 1) AS tb WHERE deleted = 0'
                    , $this->kernel->conf[$name], $this->kernel->conf['escaped_timezone']);
                $statement = $this->conn->query($sql);

                if($record = $statement->fetch()) {
                    $tmp = $record;
                } else {
                    $sm = $this->get_sitemap('edit', 'desktop');
                    if($sm->countPages()) {
                        $tmp = array(
                            'id' => $sm->getRoot()->getItem()->getId(),
                            'major_version' => $sm->getRoot()->getItem()->getMajorVersion(),
                            'minor_version' => $sm->getRoot()->getItem()->getMinorVersion()
                        );
                    }
                }

                if(isset($tmp) && $tmp['id']) {
                    $sql = sprintf("SELECT * FROM webpage_platforms WHERE domain = 'private'"
                        . ' AND webpage_id = %d AND major_version = %d AND minor_version = %d'
                        , $tmp['id'], $tmp['major_version'], $tmp['minor_version']
                    );
                    $statement = $this->conn->query($sql);
                    $tmp['platforms'] = array();
                    while($row = $statement->fetch()) {
                        $tmp['platforms'][] = $row;
                        $site_tree[$name][$row['platform']] = webpage_admin_module::getWebpageNodes('html', $row['platform'], $this->kernel->conf[$name], true, webpage_admin_module::getWebpageAccessibility());
                    }
                } else {
                    $site_tree[$name]['desktop'] = $this->kernel->dict['DESCRIPTION_no_webpage_tree_constructed'];
                }
            }
        }

        try {
            if(count($_POST) > 0) {
                $errors = array();

                // Get data from query string and form post
                $data['mailer_name'] = trim( array_ifnull($_POST, 'mailer_name', '') );
                $data['mailer_email'] = trim( array_ifnull($_POST, 'mailer_email', '') );
                $data['mailer_smtp_host'] = trim( array_ifnull($_POST, 'mailer_smtp_host', '') );
                $data['mailer_smtp_port'] = intval( array_ifnull($_POST, 'mailer_smtp_port', '') );
                $data['mailer_smtp_secure'] = trim( array_ifnull($_POST, 'mailer_smtp_secure', '') );
                $data['mailer_smtp_auth'] = (bool) array_ifnull( $_POST, 'mailer_smtp_auth', '' );
                $data['mailer_smtp_username'] = trim( array_ifnull($_POST, 'mailer_smtp_username', '') );
                $data['mailer_smtp_password'] = trim( array_ifnull($_POST, 'mailer_smtp_password', '') );
                $data['page_enabled'] = (bool) array_ifnull( $_POST, 'page_enabled', '' );
                $data['page_sortable'] = (bool) array_ifnull( $_POST, 'page_sortable', '' );
                $data['page_size'] = intval( array_ifnull($_POST, 'page_size', '') );
                $data['page_limit'] = intval( array_ifnull($_POST, 'page_limit', '') );
                $data['404_webpage_id'] = intval( array_ifnull($_POST, '404_webpage_id', '') );
                $data['footer_webpage_id'] = intval( array_ifnull($_POST, 'footer_webpage_id', '') );
                $data['offer_webpage_id'] = intval( array_ifnull($_POST, 'offer_webpage_id', '') );
                $data['login_webpage_id'] = intval( array_ifnull($_POST, 'login_webpage_id', '') );
                $data['aws_enabled'] = (bool) array_ifnull( $_POST, 'aws_enabled', '' );
                $data['aws_access_key'] = trim( array_ifnull($_POST, 'aws_access_key', '') );
                $data['aws_secret_key'] = trim( array_ifnull($_POST, 'aws_secret_key', '') );
                $data['s3_region'] = trim( array_ifnull($_POST, 's3_region', '') );
                $data['s3_bucket'] = trim( array_ifnull($_POST, 's3_bucket', '') );
                $data['s3_domain'] = trim( array_ifnull($_POST, 's3_domain', '') );
                $data['cloudfront_domain'] = trim( array_ifnull($_POST, 'cloudfront_domain', '') );
                $data['banner_image_dimension_xs'] = trim( array_ifnull($_POST, 'banner_image_dimension_xs', '') );
                $data['banner_image_dimension_md'] = trim( array_ifnull($_POST, 'banner_image_dimension_md', '') );
                $data['banner_image_dimension_xl'] = trim( array_ifnull($_POST, 'banner_image_dimension_xl', '') );
                $data['offer_image_dimension'] = trim( array_ifnull($_POST, 'offer_image_dimension', '') );
                $data['hcaptcha_sitekey'] = trim( array_ifnull($_POST, 'hcaptcha_sitekey', '') );
                $data['hcaptcha_secret'] = trim( array_ifnull($_POST, 'hcaptcha_secret', '') );
                $data['timezone'] = trim( array_ifnull($_POST, 'timezone', '') );
                $data['dialog_timeout'] = floatval( array_ifnull($_POST, 'dialog_timeout', '') );
                $data['user_session_timer'] = intval( array_ifnull($_POST, 'user_session_timer', 0) );
                $data['page_session_timer'] = intval( array_ifnull($_POST, 'page_session_timer', 0) );
                $data['spreadsheet_type'] = trim( array_ifnull($_POST, 'spreadsheet_type', '') );
                $data['webpage_n_month_before'] = intval( array_ifnull($_POST, 'webpage_n_month_before', 0) );
                $data['default_domain'] = trim( array_ifnull($_POST, 'default_domain', '') );
                $data_locales['language_alias'] = array_ifnull($_POST, 'language_alias', array());
                $data_locales['language_id'] = array_ifnull($_POST, 'language_id', array());
                $data_locales['language_name'] = array_ifnull($_POST, 'language_name', array());
                $data_locales['language_order'] = array_ifnull($_POST, 'language_order', array());
                $data_locales['language_default_value'] = array_ifnull($_POST, 'language_default_value', array());
                $data_default_locales['country_default_language'] = array_ifnull($_POST, 'country_default_language', array());
                $data_default_locales['iso_code'] = array_ifnull($_POST, 'iso_code', array());
                foreach($this->kernel->dict['SET_accessible_locales'] as $locale=>$locale_name)
                {
                    $conf_locales[$locale] = array();
                    foreach($conf_locales_items as $item)
                    {
                        $conf_locales[$locale][$item] = array_ifnull($_POST, $locale.'_'.$item, '');
                    }

                    $data_tracking_code[$locale] = trim( array_ifnull($_POST, $locale.'_tracking_code', '') );
                    $data_tracking_code_header[$locale] = trim( array_ifnull($_POST, $locale.'_tracking_code_header', '') );
                }

                //clean data
                $text_fields = array('language_alias', 'language_name');
                $int_fields = array('language_id', 'language_order', 'language_default_value');
                foreach($text_fields as $t_field)
                {
                    $data_locales[$t_field.'s'] = array_map('trim', $data_locales[$t_field]);
                    if($t_field=='language_alias')
                        $data_locales[$t_field.'s'] = array_map('strtolower', $data_locales[$t_field]);
                }
                foreach($int_fields as $i_field)
                {
                    $data_locales[$i_field.'s'] = array_map('intval', $data_locales[$i_field]);
                }

                if($data['user_session_timer'] <= 0)
                    $data['user_session_timer'] = 900;
                if($data['page_session_timer'] <= 0)
                    $data['page_session_timer'] = 900;

                //Data Validation
                //Alias: not blank, invalid chars, distinct; Name: not blank, invalid chars, distinct
                $alias_temp = array();
                $name_temp = array();
                foreach($data_locales['language_aliass'] as $k=>$alias)
                {
                    if($alias!='' && $alias != '/')
                    {
                        //invalid chars
                        if(!preg_match("/^[A-Za-z][A-Za-z\-\/]{0,18}$/", $alias))
                        {
                            // country blank
                            if(preg_match("/^\//", $alias))
                                $errors['language_country_'.$k][] = 'language_country_empty';
                            else if(preg_match("/\/$/", $alias))
                                $errors['language_alias_'.$k][] = 'language_alias_empty';

                            $errors['language_alias_'.$k][] = 'language_alias_invalid';
                        }

                        if($data_locales['language_names'][$k]!='')
                        {
                            //invalid chars
                            /*if(!preg_match("/^[a-zA-Z]+$/", $data_locales['language_names'][$k]))
                            {
                                $errors["errorsStack"][] = 'language_name_invalid';
                                echo $data_locales['language_names'][$k];
                            }*/
                        }
                        else
                        {
                            //nameblank
                            $errors['language_name_'.$k][] = 'language_name_empty';
                        }
                        if(in_array($alias, $alias_temp))
                        {
                            //not idstinct
                            $errors['language_alias_'.$k][] = 'language_alias_duplicate';
                        }
                        else
                        {
                            $alias_temp[] = $alias;
                        }
                    }
                    else
                    {
                        if($data_locales['language_names'][$k]!='')
                        {
                            //alias blank
                            $errors['language_alias_'.$k][] = 'language_alias_empty';
                        }

                    }
                }
                foreach($data_locales['language_names'] as $k=>$name)
                {
                    if($name!='')
                    {
                        if(in_array($name, $name_temp))
                        {
                            //not distinct
                            $errors['language_name_'.$k] = 'language_name_duplicate';
                        }
                        else
                        {
                            $name_temp[] = $name;
                        }
                    }
                }
                //a default language
                if(!in_array(1, $data_locales['language_default_values']))
                {
                    $errors['errorStack'] = 'language_default_none';
                }

                // continue to process (successfully)
                if(count($errors) > 0) {
                    throw new fieldsException($errors);
                } else {
                    $this->conn->beginTransaction();

                    // Get all global users
                    $sql = 'SELECT COUNT(*) AS acc_lan, user_id FROM user_locale_rights ulr'
                        . " JOIN locales l ON (l.alias=ulr.locale AND l.site='public_site' AND l.enabled=1)"
                        . " GROUP BY user_id HAVING acc_lan=(SELECT COUNT(*) AS num FROM locales WHERE site='public_site' AND enabled=1)";
                    $statement = $this->conn->query($sql);
                    $global_users = array();
                    while($r = $statement->fetch())
                    {
                        $global_users[] = $r['user_id'];
                    }

                    // Replace configuration settings
                    $sql_values = array();
                    foreach ( $data as $name => $value )
                    {
                        $sql_value = '(%s, %s)';
                        $sql_value = sprintf(
                            $sql_value,
                            $this->conn->escape($name),
                            $this->conn->escape(var_export($value, TRUE))
                        );
                        $sql_values[] = $sql_value;
                    }
                    $sql = 'REPLACE INTO configurations(name, value) VALUES ';
                    $sql .= implode( ',', $sql_values );
                    $this->conn->exec( $sql );
                    $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> saved configurations.", __FILE__, __LINE__ );

                    // Save locale configuration settings
                    $sql_value = array();
                    $sql_values = array();
                    $sql_value_del = array();
                    foreach ( $conf_locales as $locale => $items )
                    {
                        $sql_value_del[] = $this->conn->escape($locale);
                        foreach($items as $name => $value)
                        {
                            $sql_value = '(%s, %s, %s)';
                            $sql_value = sprintf(
                                $sql_value,
                                $this->conn->escape($locale),
                                $this->conn->escape($name),
                                $this->conn->escape($value)
                            );
                            $sql_values[] = $sql_value;
                        }
                    }
                    $sql = 'DELETE FROM configurations_locale WHERE locale IN (';
                    $sql .= implode(',', $sql_value_del).')';
                    $this->conn->exec( $sql );
                    $sql = 'INSERT INTO configurations_locale(locale, name, value) VALUES ';
                    $sql .= implode( ',', $sql_values );

                    $this->conn->exec( $sql );
                    $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> saved configurations in languages.", __FILE__, __LINE__ );

                    // Save locale settings
                    $snippet_replace_fields = array(
                        'annualreport_url', 'url', 'redirect_url', 'URL_contact_us', 'URL_awardswon',
                        'URL_discontinued_products', 'URL_eservice', 'URL_eservice_image',
                        'URL_product_free_trial', 'URL_product_registration', 'URL_product_statement',
                        'URL_realease_note', 'URL_sample_evaluation', 'URL_schedule_demo', 'URL_where_to_buy',
                        'tab_link', 'url_how_to', 'URL_realease_note', 'firstquarter', 'secondquarter',
                        'thirdquarter', 'forthquarter'
                    );
                    // Inactive all old locales
                    $sql = "Update locales SET enabled=0 WHERE site=".$this->conn->escape('public_site');
                    $this->conn->exec( $sql );
                    $new_aliases = array();
                    for($i=0; $i<count($data_locales['language_ids']); $i++)
                    {
                        if($data_locales['language_aliass'][$i]!='' && $data_locales['language_names'][$i]!='')
                        {
                            //check whether the alias existed
                            $sql = "SELECT id FROM locales WHERE alias=".$this->conn->escape($data_locales['language_aliass'][$i])." AND `site`=".$this->conn->escape('public_site');
                            $statement = $this->conn->query($sql);
                            if($record = $statement->fetch()) //alias existed
                            {
                                // language_id==0 -> Update data in `locales` <=> Add back an old locale
                                // language_id!=0 -> language_id == original id -> Update this locale: name, default, order_index...
                                // language_id!=0 -> language_id != original id -> Update `locales` on alias <=> Hide present language and add back an old locale
                                $sql = "UPDATE locales SET name=".$this->conn->escape($data_locales['language_names'][$i]).', ';
                                $sql .= "`default`=".$this->conn->escape($data_locales['language_default_values'][$i]).', ';
                                $sql .= "order_index=".$this->conn->escape($data_locales['language_orders'][$i]).', ';
                                $sql .= 'enabled=1, ';
                                $sql .= "updated_date=UTC_TIMESTAMP(), updator_id=".$this->user->getId();
                                $sql .= " WHERE site=".$this->conn->escape('public_site')." AND alias=".$this->conn->escape($data_locales['language_aliass'][$i]);
                                $this->conn->exec( $sql );
                            }
                            else //alias not existed yet
                            {
                                //check whether language_id==0
                                if($data_locales['language_ids'][$i]==0)
                                {
                                    //Insert new record in `locales` <=> Add a new locale
                                    $sql = "INSERT INTO locales (alias, name, `default`, order_index, site, enabled, created_date, creator_id) VALUES (";
                                    $sql .= $this->conn->escape($data_locales['language_aliass'][$i]).", ";
                                    $sql .= $this->conn->escape($data_locales['language_names'][$i]).", ";
                                    $sql .= $this->conn->escape($data_locales['language_default_values'][$i]).", ";
                                    $sql .= $this->conn->escape($data_locales['language_orders'][$i]).", ";
                                    $sql .= $this->conn->escape('public_site').', 1, ';
                                    $sql .= "UTC_TIMESTAMP(), ".$this->user->getId().")";
                                    $this->conn->exec( $sql );

                                    $new_aliases[] = $data_locales['language_aliass'][$i];
                                    $new_alias_id[] = $this->conn->insert_ID();
                                }
                                else
                                {
                                    $sql = "SELECT alias FROM locales WHERE site=".$this->conn->escape('public_site')." AND id=".$data_locales['language_ids'][$i];
                                    $statement = $this->conn->query($sql);
                                    if($record = $statement->fetch())
                                    {
                                        $old_alias = $record['alias'];

                                        //Update tables
                                        $tables = array(
                                            // Webpage
                                            'configurations_locale',
                                            'crawled_webpages',
                                            'webpage_comments',
                                            'webpage_locale_contents',
                                            'webpage_locales',

                                            // Product
                                            'application_guides',
                                            'award_locales',
                                            'banners',
                                            'category_locales',
                                            'certifications',
                                            'enews',
                                            'epublications',
                                            'featured_articles',
                                            'features',
                                            'gotos',
                                            'manuals',
                                            'notifications',
                                            'os_locales',
                                            'past_events',
                                            'press_releases',
                                            'product_categories',
                                            'product_entities',
                                            'product_feature_options',
                                            'product_locales',
                                            'product_related_products',
                                            'related_product_group_locales',
                                            'spec_sheets',
                                            'success_stories',
                                            'upcoming_events',
                                            'videos',
                                            'whitepapers',

                                            // Others
                                            'user_locale_rights'
                                        );
                                        foreach($tables as $table)
                                        {
                                            $sql = "UPDATE ".$table." SET locale=".$this->conn->escape($data_locales['language_aliass'][$i]);
                                            $sql .= " WHERE locale=".$this->conn->escape($old_alias);
                                            $this->conn->exec( $sql );
                                        }

                                        // Update users
                                        $sql = "UPDATE users SET preferred_locale=".$this->conn->escape($data_locales['language_aliass'][$i]);
                                        $sql .= " WHERE preferred_locale=".$this->conn->escape($old_alias);
                                        $this->conn->exec( $sql );

                                        // Replace urls of strutured pages
                                        $sql = 'SELECT * FROM webpage_locale_contents WHERE locale='.$this->conn->escape($data_locales['language_aliass'][$i]).' AND webpage_id IN (SELECT DISTINCT id FROM webpages WHERE `type`=\'structured_page\')';
                                        $statement = $this->conn->query($sql);
                                        while($r = $statement->fetch())
                                        {
                                            $tmp_data = array();
                                            $tmp_data = json_decode($r['content'], true);

                                            if (is_array($tmp_data) || is_object($tmp_data))
                                            {
                                                foreach($tmp_data as $section_id=>&$field_data)
                                                {
                                                    foreach($field_data as $key=>&$val)
                                                    {
                                                        $key_root = preg_replace('/[\d]+$/', '', $key);
                                                        if(in_array($key_root, array('call_to_action_url', 'url')))
                                                        {
                                                            $val = preg_replace('#^'.$old_alias.'#i', $data_locales['language_aliass'][$i], $val);
                                                            $val = preg_replace('#^/'.$old_alias.'#i', '/'.$data_locales['language_aliass'][$i], $val);
                                                        }
                                                    }
                                                }
                                            }

                                            $r['content'] = json_encode($tmp_data);

                                            $sql = sprintf('UPDATE webpage_locale_contents SET content=%s WHERE domain=%s AND webpage_id=%d AND locale=%s AND major_version=%d',
                                                $this->conn->escape($r['content']),
                                                $this->conn->escape($r['domain']),
                                                $r['webpage_id'],
                                                $this->conn->escape($r['locale']),
                                                $r['major_version']
                                            );
                                            $this->conn->exec($sql);
                                        }

                                        // Replace url fields of webpage_locales
                                        $sql = 'SELECT * FROM webpage_locales WHERE locale='.$this->conn->escape($data_locales['language_aliass'][$i]).' AND url IS NOT NULL AND url<>""';
                                        $statement = $this->conn->query($sql);
                                        while($r = $statement->fetch())
                                        {
                                            $r['url'] = preg_replace('#^'.$old_alias.'#i', $data_locales['language_aliass'][$i], $r['url']);
                                            $r['url'] = preg_replace('#^/'.$old_alias.'#i', '/'.$data_locales['language_aliass'][$i], $r['url']);

                                            $sql = sprintf('UPDATE webpage_locales SET url=%s WHERE domain=%s AND webpage_id=%d AND locale=%s AND major_version=%d',
                                                $this->conn->escape($r['url']),
                                                $this->conn->escape($r['domain']),
                                                $r['webpage_id'],
                                                $this->conn->escape($r['locale']),
                                                $r['major_version']
                                            );
                                            $this->conn->exec($sql);
                                        }

                                        // Replace urls of snippets
                                        $sql = 'SELECT * FROM customize_snippet_locales WHERE locale_id='.$data_locales['language_ids'][$i];
                                        $statement = $this->conn->query($sql);
                                        while($r = $statement->fetch())
                                        {
                                            $tmp_data = array();
                                            $tmp_data = json_decode($r['parameter_values'], true);

                                            if (is_array($tmp_data) || is_object($tmp_data))
                                            {
                                                foreach($tmp_data as $key=>&$val)
                                                {
                                                    $key_root = preg_replace('/[\d]+$/', '', $key);
                                                    if(in_array($key_root, $snippet_replace_fields))
                                                    {
                                                        $val = preg_replace('#^'.$old_alias.'#i', $data_locales['language_aliass'][$i], $val);
                                                        $val = preg_replace('#^/'.$old_alias.'#i', '/'.$data_locales['language_aliass'][$i], $val);
                                                    }
                                                }
                                            }

                                            $r['parameter_values'] = json_encode($tmp_data);

                                            $sql = sprintf('UPDATE customize_snippet_locales SET parameter_values=%s WHERE snippet_id=%d AND locale_id=%d',
                                                $this->conn->escape($r['parameter_values']),
                                                $r['snippet_id'],
                                                $r['locale_id']
                                            );
                                            $this->conn->exec($sql);
                                        }

                                        //Rename Template locale file & tracking code js
                                        $old_locale_escaped = preg_replace('/\//', '~', $old_alias);
                                        $new_locale_escaped = preg_replace('/\//', '~', $data_locales['language_aliass'][$i]);
                                        //$this->kernel->log('message', $old_locale_escaped.'||'.$new_locale_escaped.'||'.$old_alias, __FILE__, __LINE__);
                                        $old_file = $this->kernel->sets['paths']['app_root'].'/file/template/locale/'.$old_locale_escaped.'.txt';
                                        $new_file = $this->kernel->sets['paths']['app_root'].'/file/template/locale/'.$new_locale_escaped.'.txt';
                                        rename($old_file, $new_file);

                                        $old_file = $script_folder.'/'.$old_locale_escaped.'.js';
                                        $new_file = $script_folder.'/'.$new_locale_escaped.'.js';
                                        rename($old_file, $new_file);

                                        $old_file = $header_script_folder.'/'.$old_locale_escaped.'.js';
                                        $new_file = $header_script_folder.'/'.$new_locale_escaped.'.js';
                                        rename($old_file, $new_file);

                                        //Rename locale file of Snippet 10
                                        $old_file = $this->kernel->sets['paths']['app_root'].'/file/snippet/10/locale/'.$old_locale_escaped.'.php';
                                        $new_file = $this->kernel->sets['paths']['app_root'].'/file/snippet/10/locale/'.$new_locale_escaped.'.php';
                                        rename($old_file, $new_file);
                                    }

                                    //Update alias in `locales` on language_id <=> Change alias
                                    $sql = "UPDATE locales SET name=".$this->conn->escape($data_locales['language_names'][$i]).', ';
                                    $sql .= "alias=".$this->conn->escape($data_locales['language_aliass'][$i]).', ';
                                    $sql .= "`default`=".$this->conn->escape($data_locales['language_default_values'][$i]).', ';
                                    $sql .= "order_index=".$this->conn->escape($data_locales['language_orders'][$i]).', ';
                                    $sql .= 'enabled=1, ';
                                    $sql .= "updated_date=UTC_TIMESTAMP(), updator_id=".$this->user->getId();
                                    $sql .= " WHERE site=".$this->conn->escape('public_site')." AND id=".$this->conn->escape($data_locales['language_ids'][$i]);
                                    $this->conn->exec( $sql );
                                }
                            }
                        }
                        //[If want to switch language alias with each other, need to 1.change both the alias to temp ones and save, then 2.rename alias as wish and save again]
                    }

                    //Duplicate a set of records of new languages
                    if(count($new_aliases)>0)
                    {
                        $default_alias = '';
                        $sql = sprintf('SELECT id, alias FROM locales WHERE site=%1$s AND enabled=1 AND `default`=1',
                            $this->conn->escape('public_site')
                        );
                        $statement = $this->conn->query($sql);
                        if($record = $statement->fetch()) {
                            $default_alias = $record['alias'];
                            $default_alias_id = $record['id'];
                        }

                        if($default_alias!='')
                        {
                            foreach($new_aliases as $index=>$new_alias)
                            {
                                //Webpage locales
                                $sql = sprintf(
                                    'REPLACE INTO webpage_locales'
                                        . ' SELECT w.domain, w.id, %1$s, w.major_version, w.minor_version, 1, c.webpage_title, c.seo_title, c.headline_title, c.keywords, c.description, c.url, \'draft\', UTC_TIMESTAMP(), %3$d'
                                        . ' FROM (SELECT * FROM'
                                        . ' (SELECT * FROM'
                                        . ' (SELECT * FROM webpages WHERE domain = \'private\' ORDER BY id, major_version DESC, minor_version DESC)'
                                        . ' AS t GROUP BY t.id)'
                                        . ' AS t WHERE t.deleted = 0) AS w JOIN webpage_locales c'
                                        . ' ON (w.domain = c.domain AND w.id = c.webpage_id AND w.major_version = c.major_version AND w.minor_version = c.minor_version AND c.locale = %2$s)',
                                    $this->conn->escape( $new_alias ),
                                    $this->conn->escape( $default_alias ),
                                    $this->user->getId()
                                );
                                $this->conn->exec( $sql );

                                //Webpage locale contents
                                $sql = sprintf(
                                    'REPLACE INTO webpage_locale_contents'
                                        . ' SELECT w.domain, w.id, %1$s, w.major_version, w.minor_version, 1, p.platform, \'content\' AS `type`, c.content AS content'
                                        . ' FROM (SELECT * FROM'
                                        . ' (SELECT * FROM'
                                        . ' (SELECT * FROM webpages WHERE domain = \'private\' ORDER BY id, major_version DESC, minor_version DESC)'
                                        . ' AS t GROUP BY t.id)'
                                        . ' AS t WHERE t.deleted = 0) AS w JOIN webpage_locale_contents c'
                                        . ' ON (w.domain = c.domain AND w.id = c.webpage_id AND w.major_version = c.major_version AND w.minor_version = c.minor_version AND c.locale = %2$s)'
                                        . ' JOIN webpage_platforms p ON (w.domain = p.domain AND w.id = p.webpage_id AND p.major_version = w.major_version AND p.minor_version = w.minor_version)',
                                    $this->conn->escape( $new_alias ),
                                    $this->conn->escape( $default_alias )
                                );
                                $this->conn->exec( $sql );

                                // Content block locales
                                $sql = sprintf(
                                    'REPLACE INTO customize_snippet_locales SELECT snippet_id, %1$d, parameter_values FROM customize_snippet_locales WHERE locale_id=%2$d AND snippet_id IN (%3$s)',
                                    $new_alias_id[$index],
                                    $default_alias_id,
                                    'SELECT id FROM customize_snippets WHERE deleted=0'
                                );
                                $this->conn->exec( $sql );

                                //Configurations locale
                                $conf_names = array('footer_static_content', 'site_name');
                                foreach($conf_names as $conf_name)
                                {
                                    $sql = sprintf('REPLACE INTO configurations_locale SELECT name, %1$s, value FROM configurations_locale WHERE name=%2$s AND locale=%3$s',
                                    $this->conn->escape( $new_alias ),
                                    $this->conn->escape( $conf_name ),
                                    $this->conn->escape( $default_alias ));
                                    $this->conn->exec( $sql );
                                }

                                // Locale tables
                                $tables = array(
                                    'award_locales' => 'award',
                                    'category_locales' => 'category',
                                    'os_locales' => 'os',
                                    'product_categories' => 'product',
                                    'product_locales' => 'product',
                                    'product_related_products' => 'product',
                                    'related_product_group_locales' => 'related_product_group_id'
                                );
                                foreach ( $tables as $table => $entity )
                                {
                                    $fields = array( 'locale' => $this->conn->escape($new_alias) );
                                    $sql = "DESC $table";
                                    $statement = $this->conn->query( $sql );
                                    while ( $record = $statement->fetch() )
                                    {
                                        extract( $record );
                                        if ( !array_key_exists($Field, $fields) )
                                        {
                                            $fields[$Field] = $Field;
                                        }
                                    }
                                    $sql = sprintf(
                                        'REPLACE INTO %1$s(%2$s) SELECT %3$s FROM %1$s'
                                            . ' WHERE locale = %4$s',
                                        $table,
                                        implode( ', ', array_keys($fields) ),
                                        implode( ', ', $fields ),
                                        $this->conn->escape( $default_alias )
                                    );
                                    $this->conn->exec( $sql );
                                }

                                // Base tables
                                $tables = array(
                                    'application_guides' => 'application_guide',
                                    'banners' => 'banner',
                                    'certifications' => 'certification',
                                    'enews' => 'enew',
                                    'epublications' => 'epublication',
                                    'featured_articles' => 'featured_article',
                                    'features' => 'feature',
                                    'gotos' => 'goto',
                                    'manuals' => 'manual',
                                    'notifications' => 'notification',
                                    'past_events' => 'past_event',
                                    'press_releases' => 'press_release',
                                    'spec_sheets' => 'spec_sheet',
                                    'success_stories' => 'success_story',
                                    'upcoming_events' => 'upcoming_event',
                                    'videos' => 'video',
                                    'whitepapers' => 'whitepaper'
                                );
                                $base_table_mappings = array();
                                foreach ( $tables as $table => $entity )
                                {
                                    // Get the next ID
                                    $sql = 'SELECT IFNULL(MAX(id), 0) + 1 AS next_id';
                                    $sql .= " FROM $table WHERE domain = 'private'";
                                    $statement = $this->conn->query( $sql );
                                    if ( !$statement )
                                    {
                                        $this->kernel->quit( 'DB Error: ' . array_pop($this->kernel->db->errorInfo()), $sql, __FILE__, __LINE__ );
                                    }
                                    extract( $statement->fetch() );

                                    // Copy the records
                                    $sql = "SELECT * FROM $table WHERE domain = 'private'";
                                    $sql .= ' AND locale = ' . $this->conn->escape( $default_alias );
                                    $statement = $this->conn->query( $sql );
                                    while ( $fields = $statement->fetch() )
                                    {
                                        $previous_id = $fields['id'];
                                        $base_table_mappings[$entity][$previous_id] = $next_id;
                                        $fields['id'] = $next_id;
                                        $fields['locale'] = $new_alias;

                                        $fields = array_map( array($this->conn, 'escape'), $fields );
                                        $fields['created_date'] = 'UTC_TIMESTAMP()';
                                        $fields['creator_id'] = $this->user->getId();
                                        $fields['updated_date'] = 'NULL';
                                        $fields['updater_id'] = 'NULL';

                                        foreach ( $domains as $domain )
                                        {
                                            if ( $domain == 'private' || $fields['status'] == "'approved'" )
                                            {
                                                // Insert new record
                                                $fields['domain'] = $this->conn->escape( $domain );
                                                $sql = sprintf(
                                                    'INSERT INTO %s(%s) VALUE(%s)',
                                                    $table,
                                                    implode( ', ', array_keys($fields) ),
                                                    implode( ', ', $fields )
                                                );
                                                $this->conn->exec( $sql );

                                                // Copy the epublication records
                                                if ( $table == 'epublications' )
                                                {
                                                    $sql = 'INSERT INTO epublication_links(domain, epublication_id, link_id, name, url)';
                                                    $sql .= " SELECT domain, $next_id, link_id, name, url";
                                                    $sql .= " FROM epublication_links WHERE domain = '$domain' AND epublication_id = $previous_id";
                                                    $this->conn->exec( $sql );
                                                }

                                                // Copy the feature records
                                                else if ( $table == 'features' )
                                                {
                                                    $sql = 'INSERT INTO feature_categories(domain, feature_id, category_id)';
                                                    $sql .= " SELECT domain, $next_id, category_id";
                                                    $sql .= " FROM feature_categories WHERE domain = '$domain' AND feature_id = $previous_id";
                                                    $this->conn->exec( $sql );

                                                    $sql = 'INSERT INTO feature_category_orders(domain, feature_id, category_id, order_index)';

                                                    $sql .= " SELECT domain, $next_id, category_id, order_index";
                                                    $sql .= " FROM feature_category_orders WHERE domain = '$domain' AND feature_id = $previous_id";
                                                    $this->conn->exec( $sql );

                                                    $sql = 'INSERT INTO feature_options(domain, feature_id, option_id, value, shown_in_filter, shown_in_comparison, order_index)';

                                                    $sql .= " SELECT domain, $next_id, option_id, value, shown_in_filter, shown_in_comparison, order_index";
                                                    $sql .= " FROM feature_options WHERE domain = '$domain' AND feature_id = $previous_id";
                                                    $this->conn->exec( $sql );

                                                    $sql = 'INSERT INTO product_feature_options(domain, product_id, locale, feature_id, option_id)';
                                                    $sql .= ' SELECT domain, product_id, ' . $this->conn->escape( $new_alias ) . ", $next_id, option_id";
                                                    $sql .= " FROM product_feature_options WHERE domain = '$domain' AND feature_id = $previous_id";
                                                    $sql .= ' AND locale = ' . $this->conn->escape( $default_alias );
                                                    $this->conn->exec( $sql );
                                                }

                                                // Copy the success story records
                                                else if ( $table == 'success_stories' )
                                                {
                                                    $sql = 'INSERT INTO success_story_industries(domain, success_story_id, industry)';
                                                    $sql .= " SELECT domain, $next_id, industry";
                                                    $sql .= " FROM success_story_industries WHERE domain = '$domain' AND success_story_id = $previous_id";
                                                    $this->conn->exec( $sql );
                                                }

                                                // Copy the video records
                                                else if ( $table == 'videos' )
                                                {
                                                    $sql = 'INSERT INTO video_channels(domain, video_id, channel)';
                                                    $sql .= " SELECT domain, $next_id, channel";
                                                    $sql .= " FROM video_channels WHERE domain = '$domain' AND video_id = $previous_id";
                                                    $this->conn->exec( $sql );

                                                    $sql = 'INSERT INTO video_links(domain, video_id, link_id, name, url, width, height)';
                                                    $sql .= " SELECT domain, $next_id, link_id, name, url, width, height";
                                                    $sql .= " FROM video_links WHERE domain = '$domain' AND video_id = $previous_id";
                                                    $this->conn->exec( $sql );
                                                }
                                            }
                                        }

                                        $next_id++;
                                    }
                                }

                                // Copy product entities
                                $entities = array(
                                    'application_guide',
                                    'banner',
                                    'certification',
                                    'featured_article',
                                    'goto',
                                    'manual',
                                    'notification',
                                    'spec_sheet',
                                    'success_story',
                                    'whitepaper'
                                );
                                foreach ( $entities as $entity )
                                {
                                    if ( array_key_exists($entity, $base_table_mappings) )
                                    {
                                        $sql = sprintf(
                                            'INSERT INTO product_entities(domain, product_id, locale, entity_type, entity_id)'
                                                . ' SELECT domain, product_id, %s, entity_type, %s'
                                                . " FROM product_entities WHERE domain = 'private' AND locale = %s AND entity_type = %s AND entity_id IN (%s)",
                                            $this->conn->escape( $new_alias ),
                                            $this->kernel->db->translateField( 'entity_id', $base_table_mappings[$entity] ),
                                            $this->conn->escape( $default_alias ),
                                            $this->conn->escape( $entity ),
                                            implode( ', ', array_keys($base_table_mappings[$entity]) )
                                        );
                                        $this->conn->exec( $sql );
                                    }
                                }

                                // Update success story IDs in webpage locale contents
                                if ( array_key_exists('success_story', $base_table_mappings) )
                                {
                                    $sql = 'SELECT * FROM webpage_locale_contents';
                                    $sql .= " WHERE domain = 'private' AND locale = " . $this->conn->escape( $new_alias );
                                    $sql .= ' AND content LIKE \'%"case_study_chooser":%\'';
                                    $statement = $this->conn->query( $sql );
                                    while ( $record = $statement->fetch() )
                                    {
                                        $content = json_decode( $record['content'], TRUE );
                                        foreach ( $content as $id => $row )
                                        {
                                            if ( array_key_exists('case_study_chooser', $row) )
                                            {
                                                $case_study_chooser = array();
                                                foreach ( $row['case_study_chooser'] as $previous_id )
                                                {
                                                    if ( array_key_exists($previous_id, $base_table_mappings['success_story']) )
                                                    {
                                                        $case_study_chooser[] = strval( $base_table_mappings['success_story'][$previous_id] );
                                                    }
                                                }
                                                $content[$id]['case_study_chooser'] = $case_study_chooser;
                                            }
                                        }
                                        $sql = 'UPDATE webpage_locale_contents SET content = %s';
                                        $sql .= " WHERE domain = 'private'";
                                        $sql .= ' AND webpage_id = %u AND locale = %s';
                                        $sql .= ' AND major_version = %u AND minor_version = %u';
                                        $sql .= ' AND platform = %s AND type = %s';
                                        $sql = sprintf(
                                            $sql,
                                            $this->conn->escape( json_encode($content) ),
                                            $record['webpage_id'],
                                            $this->conn->escape( $new_alias ),
                                            $record['major_version'],
                                            $record['minor_version'],
                                            $this->conn->escape( $record['platform'] ),
                                            $this->conn->escape( $record['type'] )
                                        );
                                        $this->conn->exec( $sql );
                                    }
                                }

                                // Replace urls of strutured pages
                                $sql = 'SELECT * FROM webpage_locale_contents WHERE locale='.$this->conn->escape($new_alias).' AND webpage_id IN (SELECT DISTINCT id FROM webpages WHERE `type`=\'structured_page\')';
                                $statement = $this->conn->query($sql);
                                while($r = $statement->fetch())
                                {
                                    $tmp_data = array();
                                    $tmp_data = json_decode($r['content'], true);

                                    if (is_array($tmp_data) || is_object($tmp_data))
                                    {
                                        foreach($tmp_data as $section_id=>&$field_data)
                                        {
                                            foreach($field_data as $key=>&$val)
                                            {
                                                $key_root = preg_replace('/[\d]+$/', '', $key);
                                                if(in_array($key_root, array('call_to_action_url', 'url')))
                                                {
                                                    $val = preg_replace('#^'.$default_alias.'#i', $new_alias, $val);
                                                    $val = preg_replace('#^/'.$default_alias.'#i', '/'.$new_alias, $val);
                                                }
                                            }
                                        }
                                    }

                                    $r['content'] = json_encode($tmp_data);

                                    $sql = sprintf('UPDATE webpage_locale_contents SET content=%s WHERE domain=%s AND webpage_id=%d AND locale=%s AND major_version=%d',
                                        $this->conn->escape($r['content']),
                                        $this->conn->escape($r['domain']),
                                        $r['webpage_id'],
                                        $this->conn->escape($r['locale']),
                                        $r['major_version']
                                    );
                                    $this->conn->exec($sql);
                                }

                                // Replace url fields of webpage_locales
                                $sql = 'SELECT * FROM webpage_locales WHERE locale='.$this->conn->escape($new_alias).' AND url IS NOT NULL AND url<>""';
                                $statement = $this->conn->query($sql);
                                while($r = $statement->fetch())
                                {
                                    $r['url'] = preg_replace('#^'.$default_alias.'#i', $new_alias, $r['url']);
                                    $r['url'] = preg_replace('#^/'.$default_alias.'#i', '/'.$new_alias, $r['url']);

                                    $sql = sprintf('UPDATE webpage_locales SET url=%s WHERE domain=%s AND webpage_id=%d AND locale=%s AND major_version=%d',
                                        $this->conn->escape($r['url']),
                                        $this->conn->escape($r['domain']),
                                        $r['webpage_id'],
                                        $this->conn->escape($r['locale']),
                                        $r['major_version']
                                    );
                                    $this->conn->exec($sql);
                                }

                                // Replace urls of snippets
                                $sql = 'SELECT * FROM customize_snippet_locales WHERE locale_id='.$new_alias_id[$index];
                                $statement = $this->conn->query($sql);
                                while($r = $statement->fetch())
                                {
                                    $tmp_data = array();
                                    $tmp_data = json_decode($r['parameter_values'], true);

                                    if (is_array($tmp_data) || is_object($tmp_data))
                                    {
                                        foreach($tmp_data as $key=>&$val)
                                        {
                                            $key_root = preg_replace('/[\d]+$/', '', $key);
                                            if(in_array($key_root, $snippet_replace_fields))
                                            {
                                                $val = preg_replace('#^'.$default_alias.'#i', $new_alias, $val);
                                                $val = preg_replace('#^/'.$default_alias.'#i', '/'.$new_alias, $val);
                                            }
                                        }
                                    }

                                    $r['parameter_values'] = json_encode($tmp_data);

                                    $sql = sprintf('UPDATE customize_snippet_locales SET parameter_values=%s WHERE snippet_id=%d AND locale_id=%d',
                                        $this->conn->escape($r['parameter_values']),
                                        $r['snippet_id'],
                                        $r['locale_id']
                                    );
                                    $this->conn->exec($sql);
                                }

                                //Copy a New Template locale file
                                $default_locale_escaped = preg_replace('/\//', '~', $default_alias);
                                $new_locale_escaped = preg_replace('/\//', '~', $new_alias);
                                $old_file = $this->kernel->sets['paths']['app_root'].'/file/template/locale/'.$default_locale_escaped.'.txt';
                                $new_file = $this->kernel->sets['paths']['app_root'].'/file/template/locale/'.$new_locale_escaped.'.txt';
                                copy($old_file, $new_file);

                                //Copy a new locale file of Snippet 10
                                $old_file = $this->kernel->sets['paths']['app_root'].'/file/snippet/10/locale/'.$default_locale_escaped.'.php';
                                $new_file = $this->kernel->sets['paths']['app_root'].'/file/snippet/10/locale/'.$new_locale_escaped.'.php';
                                copy($old_file, $new_file);

                                // Create S3 folders
                                $underscore_alias = str_replace( '/', '_', $new_alias );
                                s3_mkdir( "product/diagram/$underscore_alias/" );
                                s3_mkdir( "product/feature/$underscore_alias/" );
                                s3_mkdir( "product/overview/$underscore_alias/" );
                                s3_mkdir( "product/video/$underscore_alias/" );

                                // Keep the global users as they were
                                $active_alias_id = array();
                                $sql = "SELECT id FROM locales WHERE enabled=1 AND site='public_site'";
                                $statement = $this->conn->query($sql);
                                while($r = $statement->fetch())
                                {
                                    $active_alias_id[] = $r['id'];
                                }
                                foreach($active_alias_id as $nl)
                                {
                                    foreach($global_users as $gu)
                                    {
                                        $sql = 'REPLACE INTO user_locale_rights (user_id, locale_id) VALUES ('.$gu.', '.$nl.')';
                                        $this->conn->exec( $sql );
                                    }
                                }
                                //Delete the inactive locales in user_locale_rights
                                $inactive_alias_id = array();
                                $sql = "SELECT id FROM locales WHERE enabled=0 AND site='public'";
                                $statement = $this->conn->query($sql);
                                while($r = $statement->fetch())
                                {
                                    $inactive_alias_id[] = $r['id'];
                                }
                                foreach($inactive_alias_id as $nl)
                                {
                                    $sql = 'DELETE FROM user_locale_rights WHERE locale_id='.$nl;
                                    $this->conn->exec( $sql );
                                }
                            }
                        }
                    }

                    $this->kernel->log( 'message', "User {$this->user->getId()} <{$this->user->getEmail()}> saved languages.", __FILE__, __LINE__ );

                    // Save tracking code
                    foreach($data_tracking_code as $locale=>$script)
                    {
                        $locale_escaped = preg_replace('/\//', '~', $locale);
                        file_put_contents($script_folder.'/'.$locale_escaped.'.js', $script);
                    }
                    $this->kernel->log('message', "User {$this->user->getId()} <{$this->user->getEmail()}> updated footer tracking codes of ".implode(', ', array_keys($data_tracking_code)).".", __FILE__, __LINE__ );

                    foreach($data_tracking_code_header as $locale=>$script)
                    {
                        $locale_escaped = preg_replace('/\//', '~', $locale);
                        file_put_contents($header_script_folder.'/'.$locale_escaped.'.js', $script);
                    }
                    $this->kernel->log('message', "User {$this->user->getId()} <{$this->user->getEmail()}> updated header tracking codes of ".implode(', ', array_keys($data_tracking_code_header)).".", __FILE__, __LINE__ );

                    // Save countries default locale
                    $sql = 'DELETE FROM country_default_locale';
                    $this->conn->exec( $sql );
                    $update_country_name = array();
                    foreach($data_default_locales['country_default_language'] as $k=>$dl)
                    {
                        if($dl!='')
                        {
                            $sql = 'INSERT INTO country_default_locale (iso_code, locale) VALUES ('.$this->kernel->db->escape($data_default_locales['iso_code'][$k]).','.$this->kernel->db->escape($dl).')';
                            $this->conn->exec( $sql );
                            $update_country_name[] = $this->kernel->dict['SET_iso_country_codes'][$data_default_locales['iso_code'][$k]];
                        }
                    }

                    if(count($update_country_name)>0) {
                        $this->kernel->log('message', "User {$this->user->getId()} <{$this->user->getEmail()}> updated default locales  of ".implode(', ', $update_country_name).".", __FILE__, __LINE__ );
                    }

                    // Clear cache
                    $this->clear_cache();
                    $this->conn->commit();

                    $redirect = $this->kernel->sets['paths']['mod_from_doc'] . "?" .
                        http_build_query(array(
                                              'op' => 'dialog',
                                              'type' => 'message',
                                              'code' => 'DESCRIPTION_saved',
                                              'redirect_url' => '.'
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
            }
        } catch(Exception $e) {
            $this->processException($e);
        }

        // continue to process if not ajax
        if(!$ajax) {
            $this->kernel->smarty->assign('site_tree', $site_tree);

            // Assign data to view
            $this->kernel->smarty->assignByRef( 'subsystems', $subsystems );
            $this->kernel->smarty->assignByRef( 'data', $data );
            $this->kernel->smarty->assignByRef( 'conf_locales', $conf_locales );
            $this->kernel->smarty->assignByRef( 'locales', $locales );
            $this->kernel->smarty->assignByRef( 'country_default_locales', $country_default_locales );
            $this->kernel->smarty->assignByRef( 'countries_list', $countries_list );
            $this->kernel->smarty->assign('tracking_code', $tracking_code);
            $this->kernel->smarty->assign('tracking_code_header', $tracking_code_header);

            $this->kernel->response['content'] = $this->kernel->smarty->fetch( 'module/configuration_admin/index.html' );
        }
    }

    function generateDynaTree($ary = array(), $return = 'json', $id = 0, $disabled = false, $deleted = false, $status = false) {
        $output = array();

        foreach($ary as $item) {
            $classes = array(
                'status' => false
            );
            $child = array(
                'title' => ($item['title'] ? $item['title'] : '(' . $this->kernel->dict['LABEL_no_title'] . ')') . ' - [#' . $item['id'] . ']',
                'key' => isset($item['id']) ?  $item['id'] : $item['name'],
                'href' => $this->kernel->sets['paths']['server_url'] . $this->kernel->sets['paths']['app_from_doc'] . '/[lang]' . $item['path'],
                'tooltip' => $item['path'],
                'unselectable' => false,
                'selected' => false
            );

            if($item['deleted'] || $deleted) {
                $classes[] = 'deleted';
            }

            if($disabled) {
                $classes[] = 'disabled';
                $child['unselectable'] = true;
            }

            if($child['key'] == $id) {
                $child['selected'] = true;
            }

            if(in_array($item['status'], array("pending", "draft")) || $status) {
                $classes['status'] = in_array($item['status'], array("pending", "draft")) ? $item['status'] : $status;
            }

            if($item['hasChild']) {
                $child['lazy'] = true;
                if(isset($item['children'])) {
                    if($child['key'] == $id) {
                        //exit;
                    }
                    $tmp = $this->generateDynaTree($item['children'], $return, $id, ($child['key'] == $id || $disabled), ($deleted || in_array('deleted', $classes)), $classes['status'] == "pending" ? "pending" : false);
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
                if($item['selected']) {
                    $data['selected'] = true;
                }
                $html .= sprintf('<li id="%1$s" class="%4$s %5$s %6$s" data-json="%8$s" title="%9$s"><a href="%3$s">%2$s</a>%7$s</li>'
                    , $item['key']
                    , htmlspecialchars($item['title'])
                    , htmlspecialchars($item['href'])
                    , isset($item['lazy']) ? 'lazy' : ""
                    , htmlspecialchars($item['extraClasses'])
                    , isset($item['children']) ? 'expanded' : ''
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
}