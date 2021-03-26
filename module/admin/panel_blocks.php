<?php
/**
 * File: panel_blocks.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 23/12/2013 16:42
 * Description:
 */

/**
 * Interface IpanelBlock
 * Interface for each panel block to implement
 */
interface IpanelBlock {
    function prepare();
    function getOutputHtml();
}

/**
 * Class panelBlock
 * An abstract class for the panel blocks
 */
abstract class panelBlock implements IpanelBlock {
    /** @var  db $conn */
    protected $conn;
    /** @var  kernel $kernel */
    protected $kernel;
    /** @var  admin_module $module */
    protected $module;
    /** @var  int $id */
    protected $id;
    protected $list_template;
    /** @var  string $title */
    protected $title;
    /** @var  string $icon_name */
    protected $icon_name;

    /** @var  panelBlockItemList $item_list */
    protected $item_list;

    protected $size = 6;
    protected $color_theme = 'lightgrey';

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
    /**
     * @return \panelBlockItemList
     */
    public function getItemList()
    {
        return $this->item_list;
    }

    /**
     * @return string
     */
    public function getIconName()
    {
        return $this->icon_name;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param string $color_theme
     */
    public function setColorTheme($color_theme)
    {
        $this->color_theme = $color_theme;
    }

    /**
     * @return string
     */
    public function getColorTheme()
    {
        return $this->color_theme;
    }

    function __construct($module) {
        $this->kernel = kernel::getInstance();
        $this->conn = db::Instance();
        $this->module = $module;
        $this->list_template = $this->kernel->sets['paths']['app_root'] . '/module/admin/default_panel_list.html';
        $this->icon_name = 'icon-th-list';

        $this->item_list = new panelBlockItemList();
    }

    /**
     * See if the list has any item inside
     *
     * @return bool
     */
    function hasItem() {
        return $this->item_list->count() > 0;
    }

    /**
     * Generate the html output
     *
     * @return string
     */
    function getOutputHtml() {
        $this->kernel->smarty->assignByRef('pb_item', $this);
        $this->getItemList()->rewind();

        return $this->kernel->smarty->fetch($this->list_template);
    }
}

/**
 * Class aboutExpireWebpageBlock
 * Prepare and generate the html in the admin panel on notifications
 * about the webpages that are going to expire soon
 */
class aboutExpireWebpageBlock extends panelBlock {
    const PRIOR_DAYS = 3;

    function __construct($module) {
        parent::__construct($module);

        $this->title = $this->kernel->dict['TITLE_expiring_webpages'];

        $this->icon_name = 'icon-time';
    }

    /**
     * Prepare for the output
     *
     * @return void
     */
    function prepare() {
        $sqls = array();

        if ( $this->module->user->hasRights('webpage_admin', Right::VIEW))
        {
            $sql = "SELECT 'webpage' AS type, w.id, CONCAT(:title_prefix, wl.webpage_title) AS title,";
            $sql .= " TIME_TO_SEC(TIMEDIFF(wl.removal_date, UTC_TIMESTAMP())) AS diff_in_sec,";
            $sql .= " CONVERT_TZ(wl.removal_date, 'gmt', {$this->kernel->conf['escaped_timezone']}) AS removal_date";
            $sql .= ' FROM webpages AS w';
            $sql .= ' JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id';
            $sql .= ' AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version';
            $sql .= ' AND wl.locale = :locale AND wl.removal_date BETWEEN UTC_TIMESTAMP() AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL :days DAY))';
            $sql .= " WHERE w.domain = 'public' AND w.deleted = 0";
            $sql = strtr( $sql, array_map(array($this->conn, 'escape'), array(
                ':title_prefix' => "[{$this->kernel->dict['LABEL_webpage']}] ",
                ':locale' => $this->module->user->getPreferredLocale(),
                ':days' => aboutExpireWebpageBlock::PRIOR_DAYS
            )) );
            $sqls[] = $sql;
        }

        $sql = sprintf('SELECT * FROM (%1$s) AS tb ORDER BY removal_date ASC'
                            , implode(' UNION ALL ', $sqls));
        $statement = $this->conn->query($sql);

        $min = 60;
        $hour = $min * 60;
        $day = $hour * 24;

        while($row = $statement->fetch()) {
            extract( $row );

            // less than an hour
            if($diff_in_sec < $hour) {
                $description = sprintf($this->kernel->dict['DESCRIPTION_mins_to_expired'], ceil($diff_in_sec / $min));

            } elseif($diff_in_sec < $day) { // less than a day
                $description = sprintf($this->kernel->dict['DESCRIPTION_hours_to_expired'], ceil($diff_in_sec / $hour));

            } else { // more than a day
                $description = sprintf($this->kernel->dict['DESCRIPTION_date_to_expired'], $row['removal_date']);

            }

            $description = sprintf($this->kernel->dict['DESCRIPTION_this_page_about'], $description);

            if($type == "offer") {
                $url = sprintf('%s/admin/%s/offer/?op=edit&id=%d', $this->kernel->sets['paths']['app_from_doc'], $this->kernel->request['locale'], $id);
            } else {
                $url = sprintf('%s/admin/%s/webpage/?id=%d', $this->kernel->sets['paths']['app_from_doc'], $this->kernel->request['locale'], $id);
            }

            $item = new panelBlockItem($title, $description, $url, array($this->kernel->dict['ACTION_go']));
            if($type == "offer") {
                $item->setIconName('star');
            } else {
                $item->setIconName('sitemap');
            }
            $this->item_list->push($item);
        }
    }
}

/**
 * Class aboutLiveWebpageBlock
 * Prepare and generate the html in the admin panel on notifications
 * about the webpages that are going to live soon
 */
class aboutLiveWebpageBlock extends panelBlock {
    const PRIOR_DAYS = 3;

    function __construct($module) {
        parent::__construct($module);

        $this->title = $this->kernel->dict['TITLE_living_webpages'];

        $this->icon_name = 'icon-time';
    }

    /**
     * Prepare for the output
     *
     * @return void
     */
    function prepare() {
        $sqls = array();

        if ( $this->module->user->hasRights('webpage_admin', Right::VIEW))
        {
            $sql = "SELECT 'webpage' AS type, w.id, wl.major_version, wl.minor_version,";
            $sql .= " CONCAT('[', :title_prefix, '][', wl.visual_version, '.' , wl.minor_version, '] ', wl.webpage_title) AS title,";
            $sql .= " TIME_TO_SEC(TIMEDIFF(wl.publish_date, UTC_TIMESTAMP())) AS diff_in_sec,";
            $sql .= " CONVERT_TZ(wl.publish_date, 'gmt', {$this->kernel->conf['escaped_timezone']}) AS publish_date";
            $sql .= ' FROM webpages AS w';
            $sql .= ' JOIN webpage_locales AS wl ON (w.domain = wl.domain AND w.id = wl.webpage_id';
            $sql .= ' AND w.major_version = wl.major_version AND w.minor_version = wl.minor_version';
            $sql .= " AND wl.locale = :locale AND wl.publish_date BETWEEN UTC_TIMESTAMP() AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL :days DAY) AND wl.status = 'approved')";
            $sql .= " LEFT OUTER JOIN webpage_locales AS pwl ON (pwl.domain = 'public' AND wl.webpage_id = pwl.webpage_id";
            $sql .= ' AND wl.locale = pwl.locale AND wl.updated_date <= pwl.updated_date)';
            $sql .= " WHERE w.domain = 'private' AND w.deleted = 0 AND wl.webpage_title IS NOT NULL AND pwl.webpage_id IS NULL";
            $sql = strtr( $sql, array_map(array($this->conn, 'escape'), array(
                ':title_prefix' => $this->kernel->dict['LABEL_webpage'],
                ':locale' => $this->module->user->getPreferredLocale(),
                ':days' => aboutLiveWebpageBlock::PRIOR_DAYS
            )) );
            $sqls[] = $sql;
        }

        $sql = sprintf('SELECT * FROM (%1$s) AS tb ORDER BY publish_date, major_version, minor_version'
            , implode(' UNION ALL ', $sqls));

        $statement = $this->conn->query($sql);

        $min = 60;
        $hour = $min * 60;
        $day = $hour * 24;

        while($row = $statement->fetch()) {
            extract( $row );

            // less than an hour
            if($diff_in_sec < $hour) {
                $description = sprintf($this->kernel->dict['DESCRIPTION_mins_to_lived'], ceil($diff_in_sec / $min));

            } elseif($diff_in_sec < $day) { // less than a day
                $description = sprintf($this->kernel->dict['DESCRIPTION_hours_to_lived'], ceil($diff_in_sec / $hour));

            } else { // more than a day
                $description = sprintf($this->kernel->dict['DESCRIPTION_date_to_lived'], $row['publish_date']);

            }

            $description = sprintf($this->kernel->dict['DESCRIPTION_this_page_about'], $description);

            if($type == "offer") {
                $url = sprintf('%s/admin/%s/offer/?op=edit&id=%d', $this->kernel->sets['paths']['app_from_doc'], $this->kernel->request['locale'], $id);
            } else {
                $url = sprintf('%s/admin/%s/webpage/?id=%d', $this->kernel->sets['paths']['app_from_doc'], $this->kernel->request['locale'], $id);
            }

            $item = new panelBlockItem($title, $description, $url, array($this->kernel->dict['ACTION_go']));
            if($type == "offer") {
                $item->setIconName('star');
            } else {
                $item->setIconName('sitemap');
            }
            $this->item_list->push($item);
        }
    }
}

/**
 * Class anonymousLinksBlock
 * Prepare and generate the html in the admin panel on notifications
 * about the entities that contains active anonymous preview link
 */
class anonymousLinksBlock extends panelBlock {
    function __construct($module) {
        parent::__construct($module);

        $this->title = $this->kernel->dict['TITLE_anonymous_links_pages'];

        $this->icon_name = 'icon-eye-open';
        $this->item_list = array(
            'offer' => new panelBlockItemList(),
            'press_release' => new panelBlockItemList(),
            'webpage' => new panelBlockItemList()
        );

        $this->setSize(12);
        $this->list_template = $this->kernel->sets['paths']['app_root'] . '/module/admin/anonymous_links_list.html';
    }

    /**
     * See if the list has any item inside
     *
     * @return bool
     */
    function hasItem() {
        foreach($this->item_list as $list) {
            if($list->count() > 0)
                return true;
        }

        return false;
    }

    /**
     * Prepare for the output
     *
     * @return void
     */
    function prepare() {
        $escaped_locale = $this->conn->escape( $this->module->user->getPreferredLocale() );

        $sqls = array();
        if($this->module->user->hasRights('webpage_admin', Right::VIEW)) {
            foreach(array_keys($this->kernel->dict['SET_webpage_types']) as $type) {
                $sqls[] = sprintf('(SELECT * FROM(SELECT tb.id, tb.title, tb.token, tb.type, tb.initial_id'
                    . ', tb.created_date, tb.creator_id, tb.grant_role_id'
                    . ', CONVERT_TZ(tb.expire_time, "GMT", %2$s) AS expire_time'
                    . ', GROUP_CONCAT(p.platform SEPARATOR ",") AS platforms'
                    . ' FROM (SELECT w.domain, w.id, l.webpage_title AS title'
                    . ', t.*, w.major_version, w.minor_version FROM ('
                    . 'SELECT * FROM('
                    . 'SELECT * FROM webpages w WHERE domain = \'private\' ORDER BY w.id ASC, w.major_version DESC, w.minor_version DESC'
                    . ') AS w GROUP BY w.id) AS w'
                    . ' JOIN webpage_preview_tokens t ON(t.type = "webpage" AND t.initial_id = w.id)'
                    . ' JOIN webpage_locales l ON(w.domain = l.domain AND w.id = l.webpage_id AND w.major_version = l.major_version'
                    . ' AND w.minor_version = l.minor_version) WHERE (w.deleted = 0 OR l.status <> \'approved\') AND w.type = "%1$s" AND t.expire_time > UTC_TIMESTAMP() GROUP BY w.id)'
                    . ' AS tb JOIN webpage_platforms p ON(p.domain = tb.domain AND p.webpage_id = tb.id AND p.major_version = tb.major_version'
                    . ' AND p.minor_version = tb.minor_version) GROUP BY tb.id) AS tb ORDER BY tb.id)'
                    , $type
                    , $this->kernel->conf['escaped_timezone']);
            }
        }

        if($this->module->user->hasRights('offer_admin', Right::VIEW)) {
            $sqls[] = sprintf('(SELECT * FROM(SELECT o.id, l.title, t.token, t.type, t.initial_id'
                                . ', t.created_date, t.creator_id, t.grant_role_id'
                                . ', CONVERT_TZ(t.expire_time, "+00:00", %3$s) AS expire_time'
                                . ', %2$s AS platforms FROM offers o'
                                . ' JOIN webpage_preview_tokens t ON(t.type = "offer" AND t.initial_id = o.id)'
                                . ' JOIN offer_locales l ON(o.domain = l.domain AND o.id = l.offer_id)'
                                . ' WHERE o.domain = \'private\' AND o.deleted = 0 AND t.expire_time > UTC_TIMESTAMP()'
                                . ' ORDER BY l.locale = %1$s DESC) AS t GROUP BY t.id ORDER BY t.id)'
                                , $escaped_locale
                                , $this->conn->escape(implode(',', array_keys($this->kernel->dict['SET_webpage_page_types'])))
                                , $this->kernel->conf['escaped_timezone']
                            );
        }

        if($this->module->user->hasRights('media_center_admin', Right::VIEW)) {
            $sqls[] = sprintf("(SELECT p.id, SUBSTRING_INDEX(GROUP_CONCAT(pl.title ORDER BY pl.locale <> %1\$s SEPARATOR '\r\n'), '\r\n', 1) AS title,"
                . ' t.token, t.type, t.initial_id, t.created_date, t.creator_id, t.grant_role_id,'
                . ' CONVERT_TZ(t.expire_time, \'+00:00\', %2$s) AS expire_time, \'desktop\' AS platforms'
                . ' FROM press_releases AS p'
                . ' LEFT OUTER JOIN press_release_locales AS pl ON (p.domain = pl.domain AND p.id = pl.press_release_id)'
                . ' JOIN webpage_preview_tokens AS t ON (p.id = t.initial_id AND t.type = \'press_release\')'
                . ' WHERE p.domain = \'private\' AND p.deleted = 0'
                . ' GROUP BY p.id)',
                $escaped_locale,
                $this->kernel->conf['escaped_timezone']);
        }

        $sql = sprintf('SELECT * FROM (%s) tb ORDER BY expire_time ASC', implode(' UNION ALL ', $sqls));
        $statement = $this->conn->query($sql);

        require_once(dirname(dirname(__FILE__)) . '/webpage_admin/index.php');
        $accessible_webpages = webpage_admin_module::getWebpageAccessibility();

        $list = array();
        while($row = $statement->fetch()) {
            if($row['type'] == 'webpage') {
                if(in_array($row['initial_id'], $accessible_webpages)) {
                    $list[] = $row;
                }
            } else {
                $list[] = $row;
            }
        }

        foreach($list as $litem) {
            $litem['encoded_token'] = admin_module::encodePvToken($litem['token'], $litem['type']);
            
            /*
            $preview_locale = $this->kernel->default_public_locale;
            // get default locale of preview link, consist with webpage admin
            $sql = sprintf('SELECT locale FROM webpage_locales wl JOIN webpage_versions wv ON (wv.domain=wl.domain AND wv.id=wl.webpage_id AND wv.major_version=wl.major_version AND wv.minor_version=wl.minor_version) WHERE wl.domain = \'private\' AND wl.webpage_id = %1$d'
                            . ' ORDER BY locale = %2$s DESC LIMIT 0, 1'
                            , $litem['initial_id']
                            //, $this->conn->escape($this->kernel->request['locale'])
                            , $this->conn->escape($this->kernel->default_public_locale) // default locale of public site is not always same as that of admin site
            );
            $tmp = $this->conn->execute($sql);
            if($tmp->recordCount()) {
                $preview_locale = $tmp->fields['locale'];
            }
            */
            $preview_locale = $this->module->user->getPreferredLocale();
            
            $url = sprintf('%s/%s/preview/?pvtk=%s'
                , $this->kernel->sets['paths']['app_from_doc']
                //, $this->kernel->request['locale']
                , $preview_locale
                , urlencode($litem['encoded_token']));

            $platform_actions = array();

            $platforms = explode(',', $litem['platforms']);
            foreach($platforms as $platform) {
                $platform_actions[$platform] = array(
                    'title' => $this->kernel->dict['SET_webpage_page_types'][$platform],
                    'url' => $url . '&m=' . ($platform == "desktop" ? "0" : "1")
                );
            }

            $action_url = $url = sprintf('%s/admin/%s/%s/?id=%d'
                , $this->kernel->sets['paths']['app_from_doc']
                , $this->kernel->request['locale']
                , $litem['type']
                , urlencode($litem['id']));
            $item = new panelBlockItem($litem['title'], $litem['expire_time'], $action_url, $platform_actions);
            $this->item_list[$litem['type']]->push($item);
        }
    }

    /**
     * Generate the html output
     *
     * @return string
     */
    function getOutputHtml() {
        $this->kernel->smarty->assignByRef('pb_item', $this);
        foreach($this->getItemList() as $list) {
            $list->rewind();
        }

        return $this->kernel->smarty->fetch($this->list_template);
    }
}

/**
 * Class panelBlockItemList
 * A link list for the items to be shown in the admin panel block
 */
class panelBlockItemList extends SplDoublyLinkedList {

    /**
     * Append the item to the end of the list
     *
     * @param panelBlockItem $node
     */
    public function push($node) {
        parent::push($node);
    }

    /**
     * Put the item to the first of the list
     *
     * @param panelBlockItem $node
     */
    public function unshift($node) {
        parent::unshift($node);
    }

    function __construct() {
        $this->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_KEEP); // stack
    }
}

/**
 * Class panelBlockItem
 * The link list item in the link list which to be shown in the admin panel.
 */
class panelBlockItem {
    /** @var string $_title */
    protected $_title;
    /** @var string $_url */
    protected $_url;
    /** @var string $_description */
    protected $_description;
    /** @var  string $actions */
    protected $_actions;

    protected $_icon_name;


    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->_url = $url;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * @param string $actions
     */
    public function setActions($actions)
    {
        $this->_actions = $actions;
    }

    /**
     * @return string
     */
    public function getActions()
    {
        return $this->_actions;
    }

    /**
     * @param string $icon_name
     */
    public function setIconName($icon_name)
    {
        $this->_icon_name = $icon_name;
    }

    /**
     * @return string
     */
    public function getIconName()
    {
        return $this->_icon_name;
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

    function __construct($name, $description, $url, $action) {
        $this->setTitle($name);
        $this->setDescription($description);
        $this->setUrl($url);
        $this->_icon_name = "";
        $this->_type = "";

        $this->setActions($action);
    }
}