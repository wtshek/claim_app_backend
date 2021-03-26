<?php

$dir = dirname(__FILE__);
require_once("$dir/page.php");
require_once("$dir/role.php");
require_once("$dir/rights.php");
require_once("$dir/sitemap.php");
require_once("$dir/exception.php");
require_once("$dir/user.php");
require_once("$dir/breadcrumb.php");
require_once("$dir/processing_bar.php");

/**
 * The base module.
 *
 * This module does virtually nothing. It is just an abstract base class.
 *
 * @author  Martin Ng <martin@avalade.com>
 * @since   2008-10-31
 */
class base_module
{
    private $_sitemap;
    protected $_breadcrumb;
    protected $conn;

    /**
     * Constructor.
     *
     * @since   2008-10-31
     * @param   kernel: The kernel
     */
    function __construct( kernel &$kernel )
    {
        $this->kernel =& $kernel;
        $this->conn = $this->kernel->db;
        $this->_sitemap = null;

        $this->_breadcrumb = new breadcrumb();

        // Load additional dictionary file
        $query = 'SELECT alias FROM locales WHERE site='.$this->conn->escape('admin_site').' AND enabled=1 AND `default`=1';
        $statement = $this->conn->query($query);
        if($record = $statement->fetch())
        {
            require( dirname(__FILE__) . "/locale/{$record['alias']}.php" );
            $this->kernel->dict = array_merge_recursive_unique( $this->kernel->dict, $DICT );
        }
    }

    /**
     * Destructor.
     *
     * @since   2008-10-31
     */
    function close()
    {
        // Nothing
    }

    /**
     * Process the request.
     *
     * @since   2008-10-31
     * @return  Processed or not
     */
    function process()
    {
        return false;
    }

    /**
     * Output the response.
     *
     * @since   2008-10-31
     */
    function output()
    {
        // Nothing
    }

    /**
     * Get the sitemap
     *
     * @param        $mode
     * @param string $platform
     * @param bool   $force
     * @return mixed|sitemap
     */
    function get_sitemap( $mode, $platform = 'desktop', $force = false )
    {
        if($force || is_null($this->_sitemap) || !isset($this->_sitemap[$platform])) {
            $now = gmdate( 'Y-m-d H:i:s' );

            if ( $force || !isset($this->_sitemap[$platform]) )
            {
                $paths = array();
                $tree = array();
                $list = array();
                $locale = $this->kernel->request['locale'];
                $escaped_locale = str_replace( '/', '~', $locale );

                // Set the table prefix and query condition
                $table_prefix = "public";

                // get the data from different table according to the mode provided
                switch ( $mode )
                {
                    case 'edit':
                    case 'index':
                        $table_prefix = 'private';
                        $locale = $this->user->getPreferredLocale();
                        break;

                    case 'preview':
                        $table_prefix = 'private';
						//$locales = array_map(array($this->conn, 'escape'), array($locale));
                        break;
					
                    /*
					case 'index':
                        $table_prefix = 'private';
						$locales = array_unique(array($this->kernel->default_public_locale, $this->kernel->dict['SET_accessible_locales'][$this->user->getPreferredLocale()]));
						$locales = array_map(array($this->conn, 'escape'), $locales);
                        break;
                    */
                }

                $id = 0;
                if($table_prefix == "private") {
                    $id = isset($_SESSION['admin']['user']) ? intval($_SESSION['admin']['user']) : 0;
                }
                $cache_path = "{$this->kernel->sets['paths']['app_root']}/file/cache/sitemap.$escaped_locale.$table_prefix-$id.$platform.tmp";
				
				if($mode == 'preview')
				{
					$this->clear_private_cache();
                    $cache_path .= '.preview';
				}

                if(!file_exists($cache_path)) {
                    $page_types = array('static', 'url_link', 'webpage_link', 'structured_page');
                    $sqls = array();

                    // run through different page types to construct the sql required
                    foreach($page_types as $page_type) {
                        if($table_prefix == "public") {
                            // suppose only one record for each webpage, hence the performance should be faster
                            // actually no need to compare the versions
                            // TODO: possiblity to have an approved but not published version(i.e. approved but no update until the published date)
                            $sqls[] = sprintf('(SELECT * FROM(SELECT w.id, w.major_version, w.minor_version, w.type, w.shown_in_site'
                                . ', wl.publish_date, wl.removal_date, w.offer_source'
                                . ', w.deleted AS all_deleted, w.created_date, w.creator_id, w.structured_page_template'
                                . ', wl.locale, wp.path, wp.`level` AS page_level, wl.webpage_title AS title'
                                . ", IF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ExtractValue(IFNULL(wlc.content, ''), '//text()'), ' ', ''), '\n', ''), '\r', ''), '\t', ''), '&nbsp;', '') = '', 0, 1) AS has_content"
                                . ', wp.child_order_field, wp.child_order_direction, wp.order_index, wp.deleted'
                                . ', wp.shown_in_menu, wp.shown_in_sitemap, wp.platform'
                                . ', SUBSTRING_INDEX(SUBSTRING_INDEX(wp.path, "/", -2), "/", 1) AS alias'
                                . (($page_type != "static" && $page_type != "structured_page") ? ', wp.target' : ', NULL AS target')
                                . ', tmp.status AS locale_status, tmp.updated_date, tmp.updater_id'
                                . ' FROM webpages w'
                                . ' JOIN webpage_platforms wp ON('
                                . ' w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND'
                                . ' w.minor_version = wp.minor_version)'
                                . ' JOIN webpage_locales wl ON('
                                . ' wl.domain = w.domain AND wl.webpage_id = w.id AND w.major_version = wl.major_version'
                                . ' AND w.minor_version = wl.minor_version)'
                                . ' LEFT OUTER JOIN webpage_locale_contents AS wlc ON (wl.domain = wlc.domain AND wl.webpage_id = wlc.webpage_id'
                                . ' AND wl.major_version = wlc.major_version AND wl.minor_version = wlc.minor_version AND wl.locale = wlc.locale)'

                                // just to filter out content - join will be faster in this case
                                // As only global user can delete a webpage, then if a webpage is approved as deleted, status of all locales should be "approved"; same as pending for deleting
                                . ' JOIN '
                                //. '(SELECT webpage_id, locale, major_version, minor_version, status, updater_id, updated_date FROM (SELECT domain, webpage_id, locale, major_version, minor_version, status, updater_id, updated_date FROM webpage_locales WHERE domain = %1$s ORDER BY major_version DESC, minor_version DESC, locale = %3$s DESC, locale ASC) tmp'
								. '(SELECT webpage_id, SUBSTRING_INDEX(GROUP_CONCAT(locale ORDER BY FIELD (locale, %3$s) DESC), ",", 1) AS locale, major_version, minor_version, status, SUBSTRING_INDEX(GROUP_CONCAT(updater_id), ",", 1) AS updater_id, SUBSTRING_INDEX(GROUP_CONCAT(updated_date), ",", 1) AS updated_date FROM (SELECT * FROM webpage_locales WHERE domain=%1$s ORDER BY major_version DESC, minor_version DESC, locale = %3$s DESC, locale ASC) AS tmp'
                                . ' GROUP BY webpage_id) AS tmp'
                                . ' ON(wl.webpage_id = tmp.webpage_id AND wl.locale = tmp.locale AND wl.major_version = tmp.major_version AND wl.minor_version = tmp.minor_version)'
								
                                . ' WHERE w.domain = %1$s AND w.type=%4$s'
                                . ' ORDER BY wp.platform = %2$s DESC) AS tb'
                                . ' GROUP BY id'
                                . ' )'
                                , $this->conn->escape($table_prefix)
                                , $this->conn->escape($platform)
                                , $this->conn->escape($locale)
                                , $this->conn->escape($page_type));
                        } else {
                            // commented out due to poor performance
                            // rewritten after the foreach loop
                            /*
							// optimize sql for preview case
							if($mode=='preview' || $mode=='index')
							{
								$sqls[] = sprintf('(SELECT * FROM(SELECT w.id, w.major_version, w.minor_version, w.type, w.shown_in_site'
									. ', w.shown_in_site_start_date, w.shown_in_site_end_date, w.offer_source'
									. ', w.deleted AS all_deleted, w.created_date, w.creator_id, w.structured_page_template'
									. ', wl.locale, wp.path, wp.`level` AS page_level, wl.webpage_title AS title'
									. ', wp.child_order_field, wp.child_order_direction, wp.order_index, wp.deleted'
									. ', wp.shown_in_menu, wp.shown_in_sitemap, wp.platform'
									. ', SUBSTRING_INDEX(SUBSTRING_INDEX(wp.path, "/", -2), "/", 1) AS alias'
									. ', IFNULL(w.shown_in_site_start_date, w.created_date) AS published_date'
									. (($page_type != "static" && $page_type != "structured_page") ? ', wp.target' : ', NULL AS target')
									. ', tmp_1.status AS locale_status, tmp_2.updater_id, tmp_2.updated_date'
									. ' FROM webpage_versions wv'
									. ' JOIN webpages w ON (w.domain=wv.domain AND w.id=wv.id AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version)'
									. ' JOIN webpage_platforms wp ON('
									. 'wp.domain=wv.domain AND wp.webpage_id=wv.id AND wp.major_version=wv.major_version AND wp.minor_version=wv.minor_version)'
									. ' JOIN (SELECT * FROM (SELECT wl.* FROM webpage_versions wv JOIN webpage_locales wl ON (wl.domain=wv.domain AND wl.webpage_id=wv.id AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE wl.domain = %1$s AND wl.locale IN (%3$s)) wl ) AS wl ON (wl.domain=wv.domain AND wl.webpage_id=wv.id AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version)'
									
									// just get status that is not approved for locales if any
									. ' JOIN '
									//. '(SELECT webpage_id, major_version, minor_version, status FROM (SELECT wl.webpage_id, wv.major_version, wv.minor_version, status FROM webpage_versions wv JOIN webpage_locales wl ON (wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.id=wl.webpage_id AND wv.minor_version=wl.minor_version) ORDER BY FIELD(status, "draft", "pending", "approved")) tmp_1 GROUP BY webpage_id) AS tmp_1'
									. '(SELECT webpage_id, major_version, minor_version, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY FIELD (status, "draft", "approved")), ",", 1) AS status FROM (SELECT wl.* FROM webpage_locales wl JOIN webpage_versions wv ON (wl.webpage_id=wv.id AND wl.domain=wv.domain AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version) WHERE wl.domain=%1$s) AS tmp_1 GROUP BY webpage_id) AS tmp_1'
									. ' ON(wl.webpage_id = tmp_1.webpage_id AND wl.major_version = tmp_1.major_version AND wl.minor_version = tmp_1.minor_version)'
									
									// just get the latest updated time
									. ' JOIN '
									. '(SELECT webpage_id, major_version, minor_version, updater_id, updated_date FROM (SELECT wl.webpage_id, wv.major_version, wv.minor_version, updater_id, updated_date FROM webpage_versions wv JOIN webpage_locales wl ON (wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.id=wl.webpage_id AND wv.minor_version=wl.minor_version AND locale IN (%3$s)) ORDER BY updated_date DESC) tmp_2 GROUP BY webpage_id) AS tmp_2'

									. ' ON(wl.webpage_id = tmp_2.webpage_id AND wl.major_version = tmp_2.major_version AND wl.minor_version = tmp_2.minor_version)'

									. ' WHERE w.domain = %1$s AND w.type=%4$s'

									// sort here to get the latest version while it is the correct platform
									. ' ORDER BY w.id, w.major_version DESC, w.minor_version DESC, wp.platform = %2$s DESC) AS tmp GROUP BY id)'
									, $this->conn->escape($table_prefix)
									, $this->conn->escape($platform)
									//, $this->conn->escape($locale)
									, implode(',', $locales)
									, $this->conn->escape($page_type));
							}
							else
							{
								// take a record only from many versions
								// TODO: get the specific page from speicfic webpage id and version
								// then overwrite the webpage to latest path to prevent the path wrong due to the versioning of different page
								//SELECT id, domain, MAX(major_version), MAX(minor_version) FROM webpages WHERE domain='private' GROUP BY id
								$sqls[] = sprintf('(SELECT * FROM(SELECT w.id, w.major_version, w.minor_version, w.type, w.shown_in_site'
									. ', w.shown_in_site_start_date, w.shown_in_site_end_date, w.offer_source'
									. ', w.deleted AS all_deleted, w.created_date, w.creator_id, w.structured_page_template'
									. ', wl.locale, wp.path, wp.`level` AS page_level, wl.webpage_title AS title'
									. ', wp.child_order_field, wp.child_order_direction, wp.order_index, wp.deleted'
									. ', wp.shown_in_menu, wp.shown_in_sitemap, wp.platform'
									. ', SUBSTRING_INDEX(SUBSTRING_INDEX(wp.path, "/", -2), "/", 1) AS alias'
									. ', IFNULL(w.shown_in_site_start_date, w.created_date) AS published_date'
									. (($page_type != "static" && $page_type != "structured_page") ? ', wp.target' : ', NULL AS target')
									. ', tmp_1.status AS locale_status, tmp_2.updater_id, tmp_2.updated_date'
									// Peformance enhancement
									. ' FROM webpage_versions wv'
									//. ' FROM webpages w'
									. ' JOIN webpages w ON (w.domain=wv.domain AND w.id=wv.id AND w.major_version=wv.major_version AND w.minor_version=wv.minor_version)'
									. ' JOIN webpage_platforms wp ON('
									//. ' w.domain = wp.domain AND w.id = wp.webpage_id AND w.major_version = wp.major_version AND'
									//. ' w.minor_version = wp.minor_version)'
									. 'wp.domain=wv.domain AND wp.webpage_id=wv.id AND wp.major_version=wv.major_version AND wp.minor_version=wv.minor_version)'
									. ' JOIN (SELECT * FROM webpage_locales WHERE domain = %1$s ORDER BY major_version DESC, minor_version DESC, locale = %3$s DESC, locale ASC) wl ON('
									//. ' wl.domain = w.domain AND wl.webpage_id = w.id AND w.major_version = wl.major_version'
									//. ' AND w.minor_version = wl.minor_version)'
									. 'wl.domain=wv.domain AND wl.webpage_id=wv.id AND wl.major_version=wv.major_version AND wl.minor_version=wv.minor_version)'

                                    // just to filter out content - join will be faster in this case
                                    // Performance enhancement - remove the next 'JOIN' as JOIN webpage_versions directly
                                    //. ' JOIN '

                                    //. '(SELECT webpage_id, locale, major_version, minor_version FROM (SELECT webpage_id, locale, major_version, minor_version FROM webpage_locales WHERE domain = %1$s ORDER BY major_version DESC, minor_version DESC, locale = %3$s DESC, locale ASC) tmp'
                                    //. ' GROUP BY webpage_id) AS tmp'
                                    //. ' ON(wl.webpage_id = tmp.webpage_id AND wl.locale = tmp.locale AND wl.major_version = tmp.major_version AND wl.minor_version = tmp.minor_version)'
                                    
                                    // just get status that is not approved for locales if any
                                    . ' JOIN '
                                    //. '(SELECT webpage_id, major_version, minor_version, status FROM (SELECT webpage_id, status, major_version, minor_version FROM webpage_locales WHERE domain = %1$s ORDER BY major_version DESC, minor_version DESC, FIELD(status, "draft", "pending", "approved")) tmp_1'
                                    //. ' GROUP BY webpage_id) AS tmp_1' // Performance enhancement
                                    . '(SELECT webpage_id, major_version, minor_version, status FROM (SELECT wl.webpage_id, wv.major_version, wv.minor_version, status FROM webpage_versions wv JOIN webpage_locales wl ON (wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.id=wl.webpage_id AND wv.minor_version=wl.minor_version) ORDER BY FIELD(status, "draft", "pending", "approved")) tmp_1 GROUP BY webpage_id) AS tmp_1'
                                    . ' ON(wl.webpage_id = tmp_1.webpage_id AND wl.major_version = tmp_1.major_version AND wl.minor_version = tmp_1.minor_version)'
                                    
                                    // just get the latest updated time
                                    . ' JOIN '
                                    //. '(SELECT webpage_id, major_version, minor_version, updater_id, updated_date FROM (SELECT webpage_id, updater_id, updated_date, major_version, minor_version FROM webpage_locales WHERE domain = %1$s ORDER BY major_version DESC, minor_version DESC, updated_date DESC) tmp_2'
                                    //. ' GROUP BY webpage_id) AS tmp_2' // Performance enhancement
                                    . '(SELECT webpage_id, major_version, minor_version, updater_id, updated_date FROM (SELECT wl.webpage_id, wv.major_version, wv.minor_version, updater_id, updated_date FROM webpage_versions wv JOIN webpage_locales wl ON (wv.domain=wl.domain AND wv.major_version=wl.major_version AND wv.id=wl.webpage_id AND wv.minor_version=wl.minor_version) ORDER BY updated_date DESC) tmp_2 GROUP BY webpage_id) AS tmp_2'
                                    . ' ON(wl.webpage_id = tmp_2.webpage_id AND wl.major_version = tmp_2.major_version AND wl.minor_version = tmp_2.minor_version)'

                                    . ' WHERE w.domain = %1$s AND w.type=%4$s'

                                    // sort here to get the latest version while it is the correct platform
                                    . ' ORDER BY w.id, w.major_version DESC, w.minor_version DESC, wp.platform = %2$s DESC) AS tmp GROUP BY id)'
                                    , $this->conn->escape($table_prefix)
                                    , $this->conn->escape($platform)
                                    , $this->conn->escape($locale)
                                    , $this->conn->escape($page_type));
							}
                            */
                        }
                    }

                    // rewritten for private table
                    if($table_prefix == 'private') {
                        $sqls[] = strtr(
                            "SELECT wv.*, w.type, w.structured_page_template, w.shown_in_site, w.offer_source, w.deleted AS all_deleted, w.created_date, w.creator_id,
                                SUBSTRING_INDEX(GROUP_CONCAT(wl.locale ORDER BY wl.locale <> :locale, wl.locale), ',', 1) AS locale,
                                SUBSTRING_INDEX(GROUP_CONCAT(wl.webpage_title ORDER BY wl.locale <> :locale, wl.locale SEPARATOR '\r\n'), '\r\n', 1) AS title,
                                SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(wl.publish_date, '') ORDER BY wl.locale <> :locale, wl.locale), ',', 1) AS publish_date,
                                SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(wl.removal_date, '') ORDER BY wl.locale <> :locale, wl.locale), ',', 1) AS removal_date,
                                SUBSTRING_INDEX(GROUP_CONCAT(wl.status ORDER BY FIND_IN_SET(wl.status, 'draft,pending,approved')), ',', 1) AS locale_status,
                                IF(GROUP_CONCAT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ExtractValue(IFNULL(wlc.content, ''), '//text()'), ' ', ''), '\n', ''), '\r', ''), '\t', ''), '&nbsp;', '')) = '', 0, 1) AS has_content,
                                MAX(wl.updated_date) AS updated_date,
                                SUBSTRING_INDEX(GROUP_CONCAT(wl.updater_id ORDER BY wl.updated_date DESC), ',', 1) AS updater_id,
                                wp.path, wp.level AS page_level, wp.child_order_field, wp.child_order_direction, wp.order_index, wp.deleted, wp.shown_in_menu, wp.shown_in_sitemap, wp.platform,
                                SUBSTRING_INDEX(SUBSTRING_INDEX(wp.path, '/', -2), '/', 1) AS alias, wp.target
                                FROM webpage_versions AS wv
                                JOIN webpages AS w ON (wv.domain = w.domain AND wv.id = w.id AND wv.major_version = w.major_version AND wv.minor_version = w.minor_version)
                                LEFT OUTER JOIN webpage_locales AS wl ON (wv.domain = wl.domain AND wv.id = wl.webpage_id AND wv.major_version = wl.major_version AND wv.minor_version = wl.minor_version)
                                LEFT OUTER JOIN webpage_locale_contents AS wlc ON (wl.domain = wlc.domain AND wl.webpage_id = wlc.webpage_id
                                AND wl.major_version = wlc.major_version AND wl.minor_version = wlc.minor_version AND wl.locale = wlc.locale)
                                JOIN webpage_platforms AS wp ON (wv.domain = wp.domain AND wv.id = wp.webpage_id AND wv.major_version = wp.major_version AND wv.minor_version = wp.minor_version AND wp.platform = :platform)
                                WHERE wv.domain = 'private'
                                GROUP BY wv.id", array(
                                    ':platform' => $this->conn->escape($platform),
                                    ':locale' => $this->conn->escape($locale)
                                )
                        );
                    }

                    /*
                    $sql = sprintf('SELECT w.*, GROUP_CONCAT(p.role_id SEPARATOR ",") AS accessible_public_roles FROM(SELECT tb.* FROM (SELECT %1$s as platforms, tb.* FROM (%2$s) AS tb '
                        . ' ORDER BY id ASC, major_version DESC, minor_version DESC) '
                        // make it display only the latest version - may have different types
                        . ' tb GROUP BY tb.id HAVING NOT(locale_status = "approved" AND deleted = 1) AND platform = %1$s '
                        . ' ) w'
                        . ' LEFT JOIN webpage_permissions p ON(p.domain = %3$s AND p.webpage_id = w.id AND p.major_version = w.major_version AND p.minor_version = w.minor_version)'
                        . ' GROUP BY w.id ORDER BY `page_level` ASC, w.id ASC'
                        , $this->conn->escape($platform), implode(' UNION ALL ', $sqls), $this->conn->escape($table_prefix));
                    */

                    $sql = strtr(
                        "SELECT w.*, w.platform AS platforms, GROUP_CONCAT(p.role_id) AS accessible_public_roles
                            FROM (:sqls) AS w
                            LEFT JOIN webpage_permissions p ON(p.domain = :domain AND p.webpage_id = w.id AND p.major_version = w.major_version AND p.minor_version = w.minor_version)
                            WHERE w.deleted = 0 OR w.locale_status <> 'approved'
                            GROUP BY w.id ORDER BY w.path",
                        array(
                            ':sqls' => implode(' UNION ALL ', $sqls),
                            ':domain' => $this->conn->escape($table_prefix)
                        )
                    );

                    $statement = $this->conn->query($sql);
                    $rows = $statement->fetchAll();
                    $sm = new sitemap($platform);

                    foreach($rows as $record)
                    {
                        $t = explode("_", $record['type']);
                        $t = array_shift($t) . preg_replace("#\s#", "", ucwords(strtolower(implode(" ", $t)))) . 'Page';

                        /** @var staticPage | webpageLinkPage | urlLinkPage $p */
                        $p = new $t($this);

                        $p->setLocales(array(
                                          $record['locale']
                                          //$locale
                                       ));

                        //$record = array_merge(array('platforms' => array($platform)), $record);
                        $record['platforms'] = explode(',', $record['platforms']);
                        $record['path'] = array(
                            $platform => ($mode == "preview" ? "/preview" : "") . $record['path']
                                            . ($mode == "preview" ? (isset($_GET['pvtk']) ? '?pvtk=' . $_GET['pvtk'] : "") : "")// make it an array
                        );

                        $record['target'] = array(
                            $platform => $record['target'] // make it an array
                        );
                        $locale_fields = array( 'title', 'publish_date', 'removal_date' );
                        foreach ( $locale_fields as $locale_field )
                        {
                            $record[$locale_field] = array(
                                $locale => $record[$locale_field] === '' ? NULL : $record[$locale_field]
                            );
                        }
                        $record['status'] = $record['locale_status'];
                        
                        // accessible roles
                        $accessible_roles = array_unique(array_filter(explode(',', $record['accessible_public_roles']), 'strlen'));
                        $record['accessible_public_roles'] = $accessible_roles;

                        $p->setData($record);

                        // Set dummy contents
                        if ( in_array($record['type'], array('static', 'structured_page')) && $record['has_content'] )
                        {
                            $keys = array_keys( $this->kernel->dict['SET_content_types'][$platform] );
                            $p->setContents( array($platform => array(
                                $locale => array_combine( $keys, array_fill(0, count($keys), '1') )
                            )) );
                        }

                        $pn = new pageNode($p, $platform);
                        $sm->add($pn);
                        unset($p);
                        unset($pn);
                    }
                    $root = $sm->getRoot();

                    if($root) {
                        $sm->getRoot()->reOrder();

                        // only public platform get cache
                        if($table_prefix == "public")
                            file_put_contents( $cache_path, serialize($sm) );
                    }
                } else {
                    // replace the whole object with data that has already set
                    try {
                        $sm = unserialize(file_get_contents($cache_path));
                        if(!$sm) {
                            throw new Exception("");
                        }

                    } catch(Exception $e) {
                        // clear cache
                        rm($cache_path);
                        // make sure the sitemap is constructed at this point
                        return $this->get_sitemap( $mode, $platform );
                    }
                }

                if($mode != 'preview' || !isset($this->_sitemap[$platform]) || !$force) {
                    // assign the sitemap
                    $this->_sitemap[$platform] = $sm;
                }

                return $sm;
            }
        }

        return $this->_sitemap[$platform];
    }

    /**
     * Add index to the sitemap.
     *
     * @since   2008-11-27
     * @param   $sitemap     The sitemap
     */
    function index_sitemap( &$sitemap, $prefix = '' )
    {
        if ( isset($sitemap['child_webpages']) )
        {
            $child_order_field = $sitemap[$prefix.'child_order_field'];
            $child_order_direction = $sitemap[$prefix.'child_order_direction'];

            $sitemap['index'] = array();
            foreach ( $sitemap['child_webpages'] as $alias => $child_webpage )
            {
                $sitemap['index'][$alias] = array(
                    'order_field_value' => $child_webpage[$child_order_field],
                    'primary_key_value' => $child_webpage['webpage_id']
                );
                $this->index_sitemap( $sitemap['child_webpages'][$alias], $prefix );
            }

            uasort( $sitemap['index'], array($this, 'compare_sitemap_index_entry') );
            if ( $child_order_direction == 'desc' )
            {
                $sitemap['index'] = array_reverse( $sitemap['index'], true );
            }
            $sitemap['index'] = array_keys( $sitemap['index'] );

        }
    }


    /**
     * Compare the index entries of the sitemap.
     *
     * @since   2008-11-27
     * @param   $a   The first entry
     * @param   $b   The second entry
     * @return  Comparison result
     */
    function compare_sitemap_index_entry( $a, $b )
    {
        // Use order field value, if not equal
        if ( $a['order_field_value'] !== $b['order_field_value'] )
        {
            if ( $a['order_field_value'] > $b['order_field_value'] )
            {
                return 1;
            }
            else
            {
                return -1;
            }
        }

        // Fallback to primary key value
        else
        {
            if ( $a['primary_key_value'] > $b['primary_key_value'] )
            {
                return 1;
            }
            else if ( $a['primary_key_value'] < $b['primary_key_value'] )
            {
                return -1;
            }
            else
            {
                return 0;
            }
        }
    }

    /**
     * Clear cache.
     *
     * @since   2009-12-14
     */

    function clear_cache()
    {
        rm( "{$this->kernel->sets['paths']['app_root']}/file/cache/*.*" );
    }
	
	/**
     * Clear private cache.
     *
     * @since   2017-09-14
     */

    function clear_private_cache()
    {
        rm( "{$this->kernel->sets['paths']['app_root']}/file/cache/*private*.*" );
    }
	
    // Member variables
    var $kernel;           // The kernel
}