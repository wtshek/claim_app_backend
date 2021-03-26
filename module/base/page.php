<?php
require_once('tree.php');

/**
 * File: page.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 2013-06-18
 * Time: 12:03 p.m.
 * Modifier: Draco Wang <draco.wang@avalade.com>
 * Date: 2015-06-10
 * Description:
 */


abstract class page {
    const DATE_FORMAT = "Y-m-d H:i:s";

    protected $_is_draft = false;

    /** @var array $_order_index */
    protected $_order_index;
    /** @var  bool $_shown_in_menu */
    protected $_shown_in_menu;
    /** @var  bool $_shown_in_sitemap */
    protected $_shown_in_sitemap;
    /** @var  bool $_shown_in_site */
    protected $_shown_in_site;
    /** @var  array $_publish_date */
    protected $_publish_date;
    /** @var  array $_removal_date */
    protected $_removal_date;
    /** @var  bool $_deleted */
    protected $_deleted;
    /** @var  bool $_enabled */
    protected $_enabled = true;
    /** @var  int $_major_version */
    protected $_major_version = 0;
    /** @var  int $_minor_version */
    protected $_minor_version = 0;
    /** @var  int $_visual_version */
    protected $_visual_version = 0;
    /** @var array $_title */
    protected $_title;
    /** @var  string $_status */
    protected $_status;
    /** @var  string $_created_date */
    protected $_created_date;
    /** @var  int $_creator */
    protected $_creator;
    /** @var  string $_updated_date */
    protected $_updated_date;
    /** @var  int $_updater */
    protected $_updater;
    /** @var  array $_locales */
    protected $_locales;
    /** @var  array $_accessible_locales */
    protected $_accessible_locales;
    /** @var  array platforms */
    protected $_platforms;
    /** @var  string $_type */
    protected $_type;
    /** @var  int $_id */
    protected $_id = 0;
    /** @var array $_relative_urls */
    protected $_relative_urls;
    /** @var array $_alternate_urls */
    protected $_alternate_urls;
    /** @var array $_locale_urls */
    protected $_locale_urls;
    /** @var array $_root_url */
    protected $_root_url;
    protected $_child_order_field;
    protected $_child_order_direction;

    /** @var  array $_accessible_public_roles */
    protected $_accessible_public_roles;
    
    /** @var array $_saving_locales */
    protected $_saving_locales;

    /** @var bool $data_retrieved */
    public $data_retrieved = false;

    /**
     * @param mixed $locales
     */
    public function setLocales($locales)
    {
        $this->_locales = $locales;
    }

    /**
     * @return mixed
     */
    public function getLocales()
    {
        return $this->_locales;
    }

    public function hasLocale($locale) {
        return in_array($locale, $this->getLocales());
    }
    
    /**
     * @param mixed $locales
     */
    public function setAccessibleLocales($locales)
    {
        $this->_accessible_locales = $locales;
    }

    /**
     * @return mixed
     */
    public function getAccessibleLocales()
    {
        return $this->_accessible_locales;
    }
    
    /**
     * @param mixed $locales
     */
    public function setSavingLocales($locales)
    {
        $this->_saving_locales = $locales;
    }

    /**
     * @return mixed
     */
    public function getSavingLocales()
    {
        return $this->_saving_locales;
    }

    /**
     * @param mixed $platforms
     */
    public function setPlatforms($platforms)
    {
        $this->_platforms = $platforms;
    }

    /**
     * @return mixed
     */
    public function getPlatforms()
    {
        $conn = db::Instance();

        if(is_null($this->_platforms) || !count($this->_platforms)) {
            $sql = sprintf("SELECT * FROM webpage_platforms WHERE domain = 'private'"
                . ' AND webpage_id = %d AND major_version = %d AND minor_version = %d'
                , $this->getId(), $this->getMajorVersion(), $this->getMinorVersion());
            $statement = $conn->query($sql);
            while($row = $statement->fetch()) {
                $this->_platforms[] = $row['platform'];
            }
        }
        return $this->_platforms;
    }

    /**
     * Set title
     *
     * @param array $title
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * Get titles in array
     *
     * @return array
     */
    public function getTitles()
    {
        return $this->_title;
    }

    /**
     * Get the title with locale specified
     *
     * @param null $locale
     * @param bool $alternate_locale
     * @return mixed|null
     */
    public function getTitle($locale = null, $alternate_locale = false)
    {
        return $this->getLocaleValue($this->_title, $locale, $alternate_locale);
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->_status = $status;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * @param string $created_date
     */
    public function setCreatedDate($created_date)
    {
        $this->_created_date = $created_date;
    }

    /**
     * @return string
     */
    public function getCreatedDate()
    {
        return $this->_created_date;
    }

    public function getCreatedTimestamp()
    {
        return strtotime($this->_created_date);
    }

    /**
     * @param string $updated_date
     */
    public function setUpdatedDate($updated_date)
    {
        $this->_updated_date = $updated_date;
    }

    /**
     * @return string
     */
    public function getUpdatedDate()
    {
        return $this->_updated_date;
    }

    public function getUpdatedTimestamp()
    {
        return is_null($this->_updated_date) || !strtotime($this->_updated_date) ? 0 : strtotime($this->_updated_date);
    }

    /**
     * @param int $updater
     */
    public function setUpdater($updater)
    {
        $this->_updater = $updater;
    }

    /**
     * @return int
     */
    public function getUpdater() {
        return $this->_updater;
    }

    public function getLastModifiedTimestamp()
    {
        return $this->getUpdatedTimestamp() ? $this->getUpdatedTimestamp() : $this->getCreatedTimestamp();
    }

    public function getPublishedTimestamp()
    {
        return is_null($this->getPublishDate('timestamp')) ? $this->getUpdatedTimestamp() : $this->getPublishDate('timestamp');
    }

    /**
     * @param int $creator
     */
    public function setCreator($creator)
    {
        $this->_creator = $creator;
    }

    /**
     * @return int
     */
    public function getCreator()
    {
        return $this->_creator;
    }

    /**
     * @param boolean $deleted
     */
    public function setDeleted($deleted)
    {
        $this->_deleted = $deleted;
    }

    /**
     * @return boolean
     */
    public function getDeleted()
    {
        return $this->_deleted;
    }

    /**
     * @param boolean $enabled
     */
    public function setEnabled($enabled)
    {
        $this->_enabled = $enabled;
    }

    /**
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->_enabled;
    }


    /**
     * @param int $major_version
     */
    public function setMajorVersion($major_version)
    {
        $this->_major_version = $major_version;
    }

    /**
     * @return int
     */
    public function getMajorVersion()
    {
        return $this->_major_version;
    }

    /**
     * @param int $minor_version
     */
    public function setMinorVersion($minor_version)
    {
        $this->_minor_version = $minor_version;
    }

    /**
     * @return int
     */
    public function getMinorVersion()
    {
        return $this->_minor_version;
    }

    /**
     * @return int
     */
    public function getVisualVersion($locale)
    {
        $conn = db::Instance();
        $visual_version = 0;
        $sql = sprintf('SELECT visual_version FROM webpage_locales WHERE domain = \'private\' AND webpage_id = %d AND major_version = %d AND minor_version = %d AND locale=%s'
            , $this->getId(), $this->getMajorVersion(), $this->getMinorVersion(), $conn->escape($locale));
        $statement = $conn->query($sql);

        if($record = $statement->fetch()) {
            $visual_version = $record['visual_version'];
        }

        return $visual_version;
    }

    public function getPlatformOrderIndex($platform = null) {
        if(is_array($this->_order_index)) {
            return $this->getPlatformValue($this->_order_index, $platform);
        } else {
            return $this->_order_index;
        }
    }

    public function getPlatformChildOrderDirection($platform) {
        return $this->getPlatformValue($this->_child_order_direction, $platform);
    }

    /**
     * @param array $order_index
     */
    public function setOrderIndex($order_index)
    {
        $this->_order_index = $order_index;
    }

    /**
     * @return array
     */
    public function getOrderIndex()
    {
        return $this->_order_index;
    }

    /**
     * @param string $publish_date
     */
    public function setPublishDate($publish_date)
    {
        $this->_publish_date = $publish_date;
    }

    /**
     * @return array
     */
    public function getPublishDate() {
        return $this->_publish_date;
    }

    /**
     * @param string $locale
     * @param string $format
     * @return int|string
     */
    public function getLocalePublishDate($locale, $format = 'date')
    {
        $date = array_ifnull( $this->_publish_date, $locale, NULL );
        if ( $format == 'timestamp' )
        {
            return is_null( $date ) ? 0 : strtotime( $date );
        }
        return $date;
    }

    /**
     * @param string $removal_date
     */
    public function setRemovalDate($removal_date)
    {
        $this->_removal_date = $removal_date;
    }

    /**
     * @return array
     */
    public function getRemovalDate() {
        return $this->_removal_date;
    }

    /**
     * @param string $locale
     * @param string $format
     * @return int|string
     */
    public function getLocaleRemovalDate($locale, $format = 'date')
    {
        $date = array_ifnull( $this->_removal_date, $locale, NULL );
        if ( $format == 'timestamp' )
        {
            return is_null( $date ) ? PHP_INT_MAX : strtotime( $date );
        }
        return $date;
    }

    /**
     * @param boolean $shown_in_menu
     */
    public function setShownInMenu($shown_in_menu)
    {
        $this->_shown_in_menu = $shown_in_menu;
    }

    /**
     * @return boolean
     */
    public function getShownInMenu()
    {
        return $this->_shown_in_menu;
    }

    /**
     * @param boolean $shown_in_site
     */
    public function setShownInSite($shown_in_site)
    {
        $this->_shown_in_site = $shown_in_site;
    }

    /**
     * @param array|boolean $shown_in_sitemap
     */
    public function setShownInSitemap($shown_in_sitemap)
    {
        $this->_shown_in_sitemap = $shown_in_sitemap;
    }

    /**
     * @return string
     */
    public function getShownInSitemap($platform = null)
    {
        if(is_null($platform)) {
            return $this->_shown_in_sitemap;
        }
        return $this->getPlatformValue($this->_shown_in_sitemap, $platform);
    }

    /**
     * @return boolean
     */
    public function getShownInSite()
    {
        return $this->_shown_in_site;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param $relative_urls
     */
    public function setRelativeUrls($relative_urls)
    {
        $this->_relative_urls = $relative_urls;
    }

    /**
     * @return array
     */
    public function getRelativeUrls()
    {
        return $this->_relative_urls;
    }

    public function getAlternateUrl($platform) {
        return isset($this->_alternate_urls[$platform]) ? $this->_alternate_urls[$platform]
                : "/";
    }

    public function setAlternateUrl($platform, $url) {
        $this->_alternate_urls[$platform] = $url;
    }

    public function getLocaleUrl($locale) {
        return isset($this->_locale_urls[$locale]) ? $this->_locale_urls[$locale] : NULL;
    }

    public function setLocaleUrl($locale, $url) {
        $this->_locale_urls[$locale] = $url;
    }

    public function available($locale) {
        $now = gmdate('U'); // get current timestamp

        return $now >= $this->getLocalePublishDate($locale, 'timestamp') && $now < $this->getLocaleRemovalDate($locale, 'timestamp');
    }

    /**
     * @param $platform
     * @return int|null
     */
    public function getRelativeUrl($platform = null, $with_query_string = false) {
        $url = $this->getPlatformValue($this->_relative_urls, $platform);
        return $with_query_string ? $url : preg_replace('#\?.*$#', '', $url);
    }

    public function getAlias($platform = null) {
        $path = $this->getRelativeUrl($platform);

        if($path) {
            return preg_replace("#^(.*?\/)([^\/]*)\/?$#", "\\2", $path);
        }

        return null;
    }

    /**
     * @param string $root_url
     */
    public function setRootUrl($root_url)
    {
        $this->_root_url = $root_url;
    }

    /**
     * @return string
     */
    public function getRootUrl()
    {
        return $this->_root_url;
    }

    /**
     * @param mixed $child_corder_field
     */
    public function setChildOrderField($child_corder_field)
    {
        $this->_child_order_field = $child_corder_field;
    }

    /**
     * @param null | string $platform
     * @return null | string
     */
    public function getChildOrderField($platform = null)
    {
        if(is_array($this->_child_order_field)) {
            return $this->getPlatformValue($this->_child_order_field, $platform);
        }
        return $this->_child_order_field;
    }

    /**
     * @param mixed $child_order_direction
     */
    public function setChildOrderDirection($child_order_direction)
    {
        $this->_child_order_direction = $child_order_direction;
    }

    /**
     * @param null | string $platform
     * @return null | string
     */
    public function getChildOrderDirection($platform = null)
    {
        if(is_array($this->_child_order_direction)) {
            return $this->getPlatformValue($this->_child_order_direction, $platform, true);
        }
        return $this->_child_order_direction;
    }

    /**
     * @param array $accessible_public_roles
     */
    public function setAccessiblePublicRoles($accessible_public_roles)
    {
        if(!is_array($accessible_public_roles)) {
            $accessible_public_roles = array($accessible_public_roles);
        }

        $accessible_public_roles = array_unique(array_map('intval', $accessible_public_roles));

        $this->_accessible_public_roles = $accessible_public_roles;
    }

    /**
     * @return array
     */
    public function getAccessiblePublicRoles()
    {
        return $this->_accessible_public_roles;
    }

    /**
     * @param array $alternate_urls
     */
    public function setAlternateUrls($alternate_urls)
    {
        $this->_alternate_urls = $alternate_urls;
    }

    /**
     * @return array
     */
    public function getAlternateUrls()
    {
        return $this->_alternate_urls;
    }

    /**
     * @param boolean $is_draft
     */
    public function setIsDraft($is_draft)
    {
        $this->_is_draft = $is_draft;
    }

    /**
     * @return boolean
     */
    public function getIsDraft()
    {
        return $this->_is_draft;
    }

    /**
     * @param boolean $data_retrieved
     */
    public function setDataRetrieved($data_retrieved)
    {
        $this->data_retrieved = $data_retrieved;
    }

    /**
     * @return boolean
     */
    public function getDataRetrieved()
    {
        return $this->data_retrieved;
    }

    /**
     * clear the data
     * @return void
     */
    public function clear() {
        $this->_order_index = $this->_id = 0;

        $this->_publish_date = $this->_shown_in_menu = $this->_shown_in_site = $this->_shown_in_sitemap
            = $this->_publish_date = $this->_removal_date
            = $this->_deleted = $this->_major_version = $this->_minor_version
            = $this->_title = $this->_status = $this->_created_date = $this->_creator
            = $this->_type = null;

        $this->_locales = array();
    }

    function __construct($ref = null) {
        $this->_alternate_urls = array();
        $this->_accessible_public_roles = array();
        $this->data_retrieved = false;
    }

    /**
     * Save the page as new version
     *
     * @param      $id
     * @param bool $major_version
     * @param bool $is_draft
     * @return int
     */
    public function saveAsNew($id, $major_version = true, $is_draft = false) {
        if(!$this->_id) {
            $this->setId(0);
        }

        //$this->setId(0);
        if($major_version || $this->_major_version == 0) {
            $this->setMajorVersion($this->_major_version+1);
            $this->setMinorVersion(0);
        } else {
            $this->setMinorVersion($this->_minor_version+1);
        }

        if($is_draft) {
            $this->_is_draft = true;
        }

        $this->save($id);

        return $this->_id;
    }

    /**
     * Get the method name according to the string provided
     *
     * @param $name
     * @return string
     */
    public function getMethodName($name) {
        switch($name) {
            case "webpage_id":
                $name = "id";
                break;
            case "path":
                $name = "relative_urls";
                break;
            case "shown_in_mobile_menu":
                $name = "shown_in_menu";
                break;
            case "shown_in_site_start_date":
                $name = "publish_date";
                break;
            case "shown_in_site_end_date":
                $name = "removal_date";
                break;
            case "creator_id";
                $name = "creator";
                break;
            case "updater_id";
                $name = "updater";
                break;
            case "webpage_title":
                $name = "title";
                break;
        }

        return $name;
    }

    /**
     * set the data according to the resources provided
     *
     * @param array $data
     */
    public function setData($data = array()) {
        foreach($data as $name => $value) {
            $name = $this->getMethodName($name);

            // data to be ignored from the object (if specific method not exists)
            $methodName = "set" . preg_replace(@"#\s#i", "", ucwords(preg_replace("#_#", " ", $name)));
            if(method_exists($this,$methodName) && $value !== "") {
                $this->$methodName($value);
            }
        }
    }

    /**
     * Save the page to db with the provided user id as the person who took the action
     *
     * @param $id
     */
    public function save($id) {
        $conn = db::Instance();

        $created_by = $id;
        $created_date = gmdate('Y-m-d H:i:s', time());

        if($this->_id) {
            $version_sets = array(
                sprintf('(major_version = %d AND minor_version = %d)', $this->getMajorVersion(), $this->getMinorVersion())
            );
/* To do: Test the necessacity of this logic 
            // get temp versions (i.e. draft, pending for approval)
            $sql = sprintf('SELECT wp.* FROM webpages w JOIN webpage_platforms wp ON('
            . 'w.domain = wp.domain AND w.id = wp.webpage_id AND wp.major_version = w.major_version AND wp.minor_version = w.minor_version)'
            . " WHERE w.domain = 'private' AND w.status IN('draft', 'pending') AND w.id = %d", $this->_id);
            $statement = $conn->query($sql);
            while($row = $statement->fetch()) {
                $version_sets[] = sprintf('(major_version = %d AND minor_version = %d)', $row['major_version'], $row['minor_version']);
            }

            // CLEAN DATA - clean all data that are not yet finalized
            // here because we dont know whether the page belongs to another type in previous version
            $sqls = array();
            $sqls[] = sprintf("DELETE FROM webpage_platforms WHERE domain = 'private' AND webpage_id = %d AND (%s)"
                , $this->_id, implode(' OR ', $version_sets));
            $sqls[] = sprintf("DELETE FROM webpage_locales WHERE domain = 'private' AND webpage_id = %d AND (%s)"
                , $this->_id, implode(' OR ', $version_sets));
            $sqls[] = sprintf("DELETE FROM webpage_locale_contents WHERE domain = 'private' AND webpage_id = %d AND (%s)"
                , $this->_id, implode(' OR ', $version_sets));
            $sqls[] = sprintf("DELETE FROM webpages WHERE domain = 'private' AND id = %d AND (%s)"
                , $this->_id, implode(' OR ', $version_sets));
            $sqls[] = sprintf("DELETE FROM webpage_permissions WHERE domain = 'private' AND webpage_id = %d AND (%s)"
                , $this->_id, implode(' OR ', $version_sets));

            foreach($sqls as $sql) {
                $conn->exec($sql);
            }
*/
            $sql = sprintf('SELECT creator_id, created_date FROM webpages'
                . " WHERE domain = 'private' AND id = %d"
                . ' ORDER BY major_version, minor_version LIMIT 0, 1'
                , $this->_id);
            $statement = $conn->query($sql);

            if($record = $statement->fetch()) {
                $created_by = $record['creator_id'];
                $created_date = $record['created_date'];
            }
        }

        $new_id = 0;
        if ( !$this->_id )
        {
            $sql = "SELECT IFNULL(MAX(id), 0) + 1 AS new_id FROM webpages WHERE domain = 'private'";
            $statement = $conn->query($sql);
            extract( $statement->fetch() );
        }

        $sql = strtr(
            'REPLACE INTO webpages(domain, id, major_version, minor_version, type, shown_in_site, offer_source, deleted, created_date, creator_id)'
                . " VALUES('private', :id, :major_version, :minor_version, :type, :shown_in_site, :offer_source, :deleted, :created_date, :creator_id)",
            array_map( array($conn, 'escape'), array(
                ':id' => $this->_id ? $this->_id : $new_id,
                ':major_version' => $this->getMajorVersion(),
                ':minor_version' => $this->getMinorVersion(),
                ':type' => $this->_type,
                ':shown_in_site' => $this->_shown_in_site,
                ':offer_source' => isset( $this->_offer_source ) ? $this->_offer_source : NULL,
                ':deleted' => $this->getDeleted() ? 1 : 0,
                ':created_date' => $created_date,
                ':creator_id' => $created_by
            ) )
        );
        $conn->exec($sql);

        if(!$this->_id) {
            $this->setId($new_id);
        }

        // update webpage versions
        $sql = sprintf('DELETE FROM webpage_versions WHERE id=%d', $this->_id);
        $conn->exec($sql);
        
        $sql = sprintf('REPLACE INTO webpage_versions(domain, id, major_version, minor_version) VALUES('
            . " 'private', %d, %d, %d)"
            , $this->_id
            , $this->getMajorVersion()
            , $this->getMinorVersion());
        $conn->exec($sql);

        // update platforms
        foreach($this->getPlatforms() as $platform) {
            $relative_url = $this->_relative_urls[$platform];

            $sql = sprintf('REPLACE INTO webpage_platforms(domain, webpage_id, platform, major_version, minor_version, path'
                . ', level, shown_in_menu, shown_in_sitemap, child_order_field, child_order_direction'
                . ", order_index) VALUES('private', %d, %s, %d, %d, %s, %d, %d, %d, %s, %s, %s)"
                , $this->_id, $conn->escape($platform), $this->_major_version, $this->_minor_version
                , $conn->escape($relative_url), count(array_filter(explode("/", $relative_url), 'strlen'))
                , $this->_shown_in_menu[$platform] ? 1 : 0, $this->_shown_in_sitemap[$platform] ? 1 : 0
                , $conn->escape($this->_child_order_field[$platform])
                , $conn->escape($this->getPlatformChildOrderDirection($platform))
                , $conn->escape($this->getPlatformOrderIndex($platform)));

            $conn->exec($sql);
        }

        $sql = sprintf('UPDATE webpage_platforms SET deleted = 1 WHERE domain = \'private\' AND webpage_id = %1$d'
                        . ' AND major_version = %2$d AND minor_version = %3$d AND platform NOT IN(%4$s)'
                        , $this->_id, $this->_major_version, $this->_minor_version
                        , implode(', ', array_map(array($conn, 'escape'), $this->getPlatforms())));
        $conn->exec($sql);

        // get the ids allowed and
        $sub_dataset = array();
        $rid_sets = array();

        $this->_accessible_public_roles = array_unique($this->_accessible_public_roles);

        foreach($this->_accessible_public_roles as $rid) {
            if($rid) {
                $sub_dataset[] = sprintf("('private', %d, %d, %d, %d)", $this->_id, $this->_major_version, $this->_minor_version, $rid);
                $rid_sets[] = $rid;
            }
        }

        // see if the access right is the same as parent in all platform, if yes, no need to insert data into permission
        // table and let it inherit
        /** @var admin_module $module */
        $module = kernel::$module;
        $sms = array();
        foreach($this->getPlatforms() as $platform) {
            $sms[$platform] = $module->get_sitemap('edit', $platform);
        }

        /** @var sitemap $sm */
        $sm = null;

        $s_count = 0;
        sort($rid_sets);
        $md5_rids = md5(var_export($rid_sets, true));

        foreach($sms as $platform => $sm) {
            $relative_url = $this->_relative_urls[$platform];
            $path = preg_replace('#([^\/]*\/)$#', '', $relative_url);

            if($path) {
                /** @var pageNode $page */
                $pn = $sm->findPage($path);
                if($pn) {
                    $r = $pn->getAccessiblePublicRoles();
                    sort($r);

                    if(count($r) && md5(var_export($r, true)) == $md5_rids) {
                        $s_count++;
                        break;
                    }
                }
            }
        }

        if($s_count != count($sms) && count($sub_dataset)) {
            $sql = sprintf("REPLACE INTO webpage_permissions(domain, webpage_id, major_version, minor_version, role_id) VALUES %s"
                , implode(', ', $sub_dataset));

            $conn->exec($sql);
        }
    }

    /**
     * Get the value base on platform
     *
     * @param      $sets
     * @param      $platform
     * @param bool $test
     * @return null|string
     */
    protected function getPlatformValue($sets, $platform, $test = false) {
        if(is_null($platform)) {
            $platforms = $this->getPlatforms();
            //if(is_array($platforms) && count($platforms) === 1) {
            if(is_array($platforms)) {
                $platform = array_shift($platforms);
            }
        }

        if(in_array($platform, $this->getPlatforms()) && isset($sets[$platform]) && $sets[$platform] !== '') {
            return $sets[$platform];
        }
        return null;
    }

    /**
     * Set the value base on platform
     *
     * @param $sets
     * @param $platform
     * @param $value
     * @return bool
     */
    protected function setPlatformValue(&$sets, $platform, $value) {
        if(in_array($platform, $this->getPlatforms())) {
            $sets[$platform] = $value;
            return true;
        }
        return false;
    }

    /**
     * Get the value based on locale provided
     *
     * @param      $sets
     * @param      $locale
     * @param bool $alternate_locale
     * @return mixed|null
     */
    protected function getLocaleValue($sets, $locale, $alternate_locale = false) {
        if(is_null($locale)) {
            $locale = $this->getLocales();
            if(is_array($locale)) {
                $locale = array_values($locale);
				$locale = array_shift($locale);
            }
        }

        if(in_array($locale, $this->getLocales()) && isset($sets[$locale])) {
            return $sets[$locale];
        }

        if($alternate_locale) {
            $sets = array_values($sets);
			return array_shift($sets);
        }
        return null; // return empty field instead of null value if it is a draft
    }

    public function publish() {

    }

    /**
     * Retrieve data for the page base on the locale and table type provided
     *
     * @param array  $locales
     * @param string $type
     */
    public function retrieveData($locales = array(), $type = "public") {
        $conn = db::Instance();

        // get its available locale (but other data are not available until retrieve)
        // added "status=approved" as additional condition because different locales may have different status
        $sql = sprintf(
            'SELECT DISTINCT locale FROM webpage_locales WHERE domain = %s'
                . ' AND webpage_id = %d AND major_version = %d AND minor_version = %d AND (domain = \'private\' OR status=\'approved\')',
            $conn->escape( $type ),
            $this->getId(),
            $this->getMajorVersion(),
            $this->getMinorVersion()
        );
        $statement = $conn->query($sql);

        $ls = array();
        while($row = $statement->fetch()) {
            $ls[] = $row['locale'];
        }

        $this->setLocales(array_unique(array_merge($this->getLocales(), $ls)));
        $this->data_retrieved = count( $ls ) > 0;
    }

    /**
     * Encode webpage content image paths
     *
     * @param $type
     * @param $webpage_id
     * @param $content
     * @param $user_id
     * @return array
     */
    protected function imgPathEncode($type, $webpage_id, $content, $user_id) {
        $rp_num = 0;
        switch($type) {
            case 'private':
                $pattern = '/(\/page\/)(private\/p' . $webpage_id . '\/)/';
                break;
            case 'public':
                $pattern = '/(\/page\/)(public\/p' . $webpage_id . '\/)/';
                break;
            default:
                $pattern = '/(\/page\/)(temp\/' . $user_id . '_\d+\/)/';
                break;
        }

        $replacement = '${1}[file_loc_folder:' . $webpage_id . ']/';
        $content = preg_replace($pattern, $replacement, $content, -1, $rp_num);

        return array(
            'content' => $content,
            'rp_num' => $rp_num
        );
    }

    /**
     * Decode Image path identifications to archive directory path
     *
     * @param $content
     * @return mixed
     */
    protected function tempImgPathDecode($content) {
        $pattern = '/\[file_loc_folder:' . $this->getId() . '\]/';
        $replacement = 'archive/p' . $this->getId() . '/' . $this->getMajorVersion() . '_' . $this->getMinorVersion();
        $content = preg_replace($pattern, $replacement, $content);

        return $content;
    }
}

require_once( 'page_static.php' );
require_once( 'page_webpage_link.php' );
require_once( 'page_url_link.php' );
require_once( 'page_structured.php' );
