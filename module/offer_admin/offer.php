<?php
/**
 * File: offer.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 28/08/2013 11:40
 * Description:
 */

class offer {
    protected $_id = null;
    protected $_type = null;
    protected $_img_url = null;
    protected $_status = null;
    protected $_start_date = null;
    protected $_end_date = null;
    protected $_created_date = null;
    protected $_creator_id = null;
    protected $_updated_date = null;
    protected $_updater_id = null;
    protected $_deleted = 0;
    protected $_video_url = null;
	protected $_period_from = null;
	protected $_period_to = null;
    protected $_price = null;
    protected $_order_index = null;
    protected $_dinings = array();
    protected $_rooms = array();

    /** @var array $_webpage_id */
    protected $_webpage_id = null;
	/** @var array $_properties */
	protected $_properties = null;
	/** @var array $_categories */
	protected $_categories = null;

    /** @var localeSet $_title */
    protected $_title;
    /** @var localeSet $_seo_title */
    protected $_seo_title;
	/** @var localeSet $_action_text */
    protected $_action_text;
    /** @var localeSet $_action_url */
    protected $_action_url;

    /**
     * @param      $action_text
     * @param null $locale
     */
    public function setActionText($action_text, $locale = null)
    {
        $this->_action_text->addDataSet($action_text, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getActionText()
    {
        return $this->_action_text;
    }

    /**
     * @param      $action_url
     * @param null $locale
     */
    public function setActionUrl($action_url, $locale = null)
    {
        $this->_action_url->addDataSet($action_url, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getActionUrl()
    {
        return $this->_action_url;
    }

	/**
     * @param mixed $created_date
     */
    public function setCreatedTime($created_date)
    {
        $this->_created_date = $created_date;
    }

    /**
     * @return mixed
     */
    public function getCreatedTime()
    {
        return $this->_created_date;
    }

    /**
     * @param mixed $creator_id
     */
    public function setCreatorId($creator_id)
    {
        $this->_creator_id = $creator_id;
    }

    /**
     * @return mixed
     */
    public function getCreatorId()
    {
        return $this->_creator_id;
    }

    /**
     * @param mixed $end_date
     */
    public function setEndDate($end_date)
    {
        $this->_end_date = $end_date;
    }

    /**
     * @return mixed
     */
    public function getEndDate()
    {
        return $this->_end_date ? $this->_end_date : NULL;
    }

    /**
     * @param mixed $period_from
     */
    public function setVideoUrl($video_url)
    {
        $this->_video_url = $video_url;
    }

    /**
     * @return mixed
     */
    public function getVideoUrl()
    {
        return $this->_video_url ? $this->_video_url : NULL;
    }

	/**
     * @param mixed $period_from
     */
    public function setPeriodFrom($period_from)
    {
        $this->_period_from = $period_from;
    }

    /**
     * @return mixed
     */
    public function getPeriodFrom()
    {
        return $this->_period_from ? $this->_period_from : NULL;
    }

	/**
     * @param mixed $period_to
     */
    public function setPeriodTo($period_to)
    {
        $this->_period_to = $period_to;
    }

    /**
     * @return mixed
     */
    public function getPeriodTo()
    {
        return $this->_period_to ? $this->_period_to : NULL;
    }

    /**
     * @param mixed $price
     */
    public function setPrice($price)
    {
        $this->_price = $price;
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->_price ? $this->_price : NULL;
    }

    /**
     * @param mixed $order_index
     */
    public function setOrderIndex($order_index)
    {
        $this->_order_index = $order_index;
    }

    /**
     * @return mixed
     */
    public function getOrderIndex()
    {
        return $this->_order_index ? $this->_order_index : NULL;
    }

    /**
     * @param mixed $dinings
     */
    public function setDinings($dinings)
    {
        $this->_dinings = $dinings;
    }

    /**
     * @return mixed
     */
    public function getDinings()
    {
        return $this->_dinings;
    }

    /**
     * @param mixed $rooms
     */
    public function setRooms($rooms)
    {
        $this->_rooms = $rooms;
    }

    /**
     * @return mixed
     */
    public function getRooms()
    {
        return $this->_rooms;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param mixed $img_url
     */
    public function setImgUrl($img_url)
    {
        $this->_img_url = $img_url;
    }

    /**
     * @return mixed
     */
    public function getImgUrl()
    {
        $url = $this->_img_url;
        if($url && strpos($url, '//') === FALSE) {
            $kernel = kernel::getInstance();
            if($kernel->conf['aws_enabled'])
            {
                $url = 'https://' . $kernel->conf['s3_domain'] . '/' . $url;
            }
            else
            {
                $url = $kernel->sets['paths']['server_url']
                    . $kernel->sets['paths']['app_from_doc'] . '/file/' . $url;
            }
        }
        return $url;
    }

    /**
     * @param mixed $start_date
     */
    public function setStartDate($start_date)
    {
        $this->_start_date = $start_date;
    }

    /**
     * @return mixed
     */
    public function getStartDate()
    {
        return $this->_start_date ? $this->_start_date : NULL;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->_status = $status;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * @param      $title
     * @param null $locale
     */
    public function setTitle($title, $locale = null)
    {
        $this->_title->addDataSet($title, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * @param      $seo_title
     * @param null $locale
     */
    public function setSeoTitle($seo_title, $locale = null)
    {
        $this->_seo_title->addDataSet($seo_title, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getSeoTitle()
    {
        return $this->_seo_title;
    }

    /**
     * @param mixed $updated_date
     */
    public function setUpdatedTime($updated_date)
    {
        $this->_updated_date = $updated_date;
    }

    /**
     * @return mixed
     */
    public function getUpdatedTime()
    {
        return $this->_updated_date;
    }

    /**
     * @param mixed $updater_id
     */
    public function setUpdaterId($updater_id)
    {
        $this->_updater_id = $updater_id;
    }

    /**
     * @return mixed
     */
    public function getUpdaterId()
    {
        return $this->_updater_id;
    }

    /**
     * @param null $webpage_ids
     */
    public function setWebpageId($webpage_ids)
    {
        $this->_webpage_id = $webpage_ids;
    }

    /**
     * @param int $deleted
     */
    public function setDeleted($deleted)
    {
        $this->_deleted = $deleted;
    }

    /**
     * @return int
     */
    public function getDeleted()
    {
        return $this->_deleted;
    }

    /**
     * @return null
     */
    public function getWebpageId()
    {
        return $this->_webpage_id;
    }

	/**
     * @param null $properties
     */
    public function setProperties($properties)
    {
        $this->_properties = $properties;
    }

	/**
     * @return null
     */
    public function getProperties()
    {
        return $this->_properties;
    }

	/**
     * @param null $categories
     */
	public function setCategories($categories)
    {
        $this->_categories = $categories;
    }
	/**
     * @return null
     */
	public function getCategories()
    {
        return $this->_categories;
    }

    function __construct() {
        $this->_title = new localeSet();
        $this->_seo_title = new localeSet();
        $this->_action_text = new localeSet();
        $this->_action_url = new localeSet();
    }

    /**
     * Set the data to the object with the data provided
     *
     * @param $data
     */
    function setData($data) {
        $locale_data = array();
        $locales = method_exists($this, 'getContent') ? $this->getContent()->getLocales() : $this->getUrl()->getLocales();

        if(!array_key_exists('categories', $data) || !in_array(1, $data['categories'])) {
            $data['rooms'] = array();
        }
        if(!array_key_exists('categories', $data) || (!in_array(2, $data['categories']) && !in_array(3, $data['categories']) && !in_array(5, $data['categories']))) {
            $data['dinings'] = array();
        }

		foreach($data as $name => $value) {
			$method = 'set' . preg_replace('#\s#', "", ucwords(preg_replace("#_#", " ", $name)));
            if(method_exists($this, $method)) {
                $this->$method($value);
            }
            else
            {
                if(in_array($name, $locales))
                {
                    foreach($value as $key => $val) {
                        foreach($val as $k => $v) {
                            preg_match('/^(.*)(\d+)$/', $k, $matches);
                            $locale_data[$key][$name][$matches[2]][$matches[1]] = $v;
                        }
                    }
                }
            }
        }

        foreach($locale_data as $name => $value)
        {
            $methodName = "set" . preg_replace(@"#\s#i", "", ucwords(preg_replace("#_#", " ", $name)));
            if(method_exists($this,$methodName)) {
                $this->$methodName($value);
            }
        }
    }

    /**
     * @return null
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Save the offer with the user id provided as the creator / updater
     *
     * @param int $user_id
     */
    function save($user_id = 0, $locales = array()) {
        $conn = db::Instance();
        $data_mapping = array(
            'type' => $conn->escape($this->_type),
            'img_url' => $conn->escape($this->_img_url),
            'status' => $conn->escape($this->_status),
            'start_date' => $conn->escape($this->getStartDate()),
            'end_date' => $conn->escape($this->getEndDate()),
            'video_url' => $conn->escape($this->getVideoUrl()),
			'period_from' => $conn->escape($this->getPeriodFrom()),
			'period_to' => $conn->escape($this->getPeriodTo()),
            'price' => $conn->escape($this->getPrice()),
            'order_index' => $conn->escape($this->getOrderIndex()),
            'deleted' => $this->getDeleted() ? 1 : 0
        );


        $id = $this->getId();
        $current_webpage_ids = array(); //direct pages only

        if(is_null($id) || !$id) {
            $sql = "SELECT IFNULL(MAX(id), 0) + 1 AS id FROM offers WHERE domain = 'private'";
            $statement = $conn->query($sql);
            extract( $statement->fetch() );
            $this->setId( $id );
            $data_mapping['domain'] = "'private'";
            $data_mapping['id'] = $id;
            $data_mapping['creator_id'] = $user_id;
            $data_mapping['created_date'] = 'UTC_TIMESTAMP()';

            $sql = sprintf('INSERT INTO offers(%s) VALUES (%s)'
                , implode(', ', array_keys($data_mapping))
                , implode(', ', $data_mapping));
            $conn->exec($sql);
        } else {
            $id = intval($this->getId());
            if(count($locales) > 0) {
                $sql = sprintf('DELETE FROM offer_locales WHERE domain = \'private\' AND offer_id = %d AND locale IN (%s)', $id, implode(', ', array_map(array($conn, 'escape'), $locales)));
                $conn->exec($sql);
            }

			$sql = sprintf('DELETE FROM offer_categories WHERE domain = \'private\' AND offer_id = %d', $id);
			$conn->exec($sql);

            $sql = sprintf('DELETE FROM offer_dinings WHERE domain = \'private\' AND offer_id = %d', $id);
            $conn->exec($sql);

            $sql = sprintf('DELETE FROM offer_rooms WHERE domain = \'private\' AND offer_id = %d', $id);
            $conn->exec($sql);

            $attrs = array();
            $data_mapping['updater_id'] = $user_id;
            $data_mapping['updated_date'] = 'UTC_TIMESTAMP()';

            foreach($data_mapping as $key => $value) {
                $attrs[] = sprintf('%s = %s', $key, $value);
            }

            $sql = sprintf('UPDATE offers SET %s WHERE domain = \'private\' AND id = %d', implode(', ', $attrs), $this->getId());
            $conn->exec($sql);

            /** @var offer_admin_module $module */
            $module = kernel::$module;
            $distributions = $module->getOfferDistributions($id);

            foreach($distributions as $webpage_id => $type) {
                if($type == "direct")
                    $current_webpage_ids[] = $webpage_id;
            }
        }

		$category = $this->getCategories();
		//echo print_r($category);
		//echo print_r($hotels);exit;
		foreach($category as $category_id)
		{
			$sql = sprintf('INSERT INTO offer_categories(domain, offer_id, category_id) VALUES (\'private\', %1$d, %2$d)'
						, $this->getId(), intval($category_id));
			$conn->exec($sql);
		}

        foreach($this->getDinings() as $webpage_id)
        {
            $sql = sprintf('INSERT INTO offer_dinings(domain, offer_id, webpage_id) VALUES (\'private\', %1$d, %2$d)'
                        , $this->getId(), intval($webpage_id));
            $conn->exec($sql);
        }

        foreach($this->getRooms() as $webpage_id)
        {
            $sql = sprintf('INSERT INTO offer_rooms(domain, offer_id, webpage_id) VALUES (\'private\', %1$d, %2$d)'
                        , $this->getId(), intval($webpage_id));
            $conn->exec($sql);
        }

        //$locales = $this->getTitle()->getLocales();
        foreach($locales as $locale) {
            $title = $this->getTitle()->getData($locale);
            $seo_title = $this->getSeoTitle()->getData($locale);
            $action_text = $this->getActionText()->getData($locale);
            $action_url = $this->getActionUrl()->getData($locale);

            if(!is_null($title) && $title) {
                $sql = sprintf('INSERT INTO offer_locales(domain, offer_id, locale, title, seo_title, action_text, action_url)'
                            . ' VALUES(\'private\', %1$d, %2$s, %3$s, %4$s, %5$s, %5$s)'
                            , $this->getId(), $conn->escape($locale)
                            , $conn->escape($title), $conn->escape($seo_title), $conn->escape($action_text), $conn->escape($action_url));
                $conn->exec($sql);
            }
        }

        /** @var kernel $kernel */
        $kernel = kernel::getInstance();

        /** @var offer_admin_module $module */
        $module = kernel::$module;
        /** @var sitemap $sitemap */
        $sitemap = $module->get_sitemap('edit', 'desktop');

        $webpages_to_add = $this->getWebpageId();
		$webpage_ids = array_merge($webpages_to_add, $current_webpage_ids);
        $webpage_ids = array_unique($webpage_ids);
        if(is_array($webpage_ids)) {
            $locales = array_keys($kernel->sets['locales']);
            foreach($webpage_ids as $webpage_id) {
                $data = array();
                /** @var pageNode $node */
                $node = $sitemap->getRoot()->findById($webpage_id);

                if(!is_null($node) && $node) {
                    $page = $node->getItem();
                    if(in_array($page->getType(), array("static", "structured_page"))
                        // changes in the webpage
                        && (!in_array($webpage_id, $current_webpage_ids) || !in_array($webpage_id, $webpages_to_add))) {

                        //$sql = sprintf('SELECT pw.shown_in_site_start_date, p.* FROM webpages pw JOIN webpage_platforms p'
                        $sql = sprintf('SELECT p.* FROM webpages pw JOIN webpage_platforms p'
                            . ' ON(p.domain = pw.domain AND p.webpage_id = pw.id AND p.major_version = pw.major_version AND p.minor_version = pw.minor_version)'
                            . ' WHERE pw.domain = \'private\' AND pw.id = %1$d'
                            . ' AND pw.major_version = %2$d AND pw.minor_version = %3$d'
                            , $page->getId(), $page->getMajorVersion(), $page->getMinorVersion());
                        $statement = $conn->query($sql);
                        $platforms = array();
                        $relative_urls = array();
                        $locales = array_keys($kernel->sets['public_locales']);

                        while($row = $statement->fetch()) {
                            $platforms[] = $row['platform'];

                            $data['template'][$row['platform']] = $row['template_id'];
                            $data['shown_in_sitemap'][$row['platform']] = $row['shown_in_sitemap'];
                            $data['shown_in_menu'][$row['platform']] = $row['shown_in_menu'];
                            $data['child_order_field'][$row['platform']] = $row['child_order_field'];
                            $data['child_order_direction'][$row['platform']] = $row['child_order_direction'];
                            $data['order_index'][$row['platform']] = $row['order_index'];
                            $relative_urls[$row['platform']] = $row['path'];
                            //$data['publish_date'] = $row['shown_in_site_start_date'];

                        }

                        $page->setPlatforms($platforms);
                        $page->setLocales($locales);
                        $page->setSavingLocales($module->user->getAccessibleLocales());

                        $page->retrieveData($locales, "private");


                        $data['status'] = "pending";

                        $data['type'] = $page->getType();
                        $data['id'] = $page->getId();

                        $data['webpage_title'] = $page->getTitles();
                        if(method_exists($page, 'getHeadlineTitles'))
                            $data['headline_title'] = $page->getHeadlineTitles();

                        $data['content'] = $page->getContents(false);
                        if ( $page->getType() == 'structured_page' )
                        {
                            foreach ( $data['content'] as $platform => $locale_contents )
                            {
                                foreach ( $locale_contents as $locale => $content )
                                {
                                    $data[$locale] = json_decode( $content['content'], TRUE );
                                }
                            }
                        }

                        /*
                        $webpage_offers = $page->getOfferIds();
                        if ( !is_array($webpage_offers) ) $webpage_offers = array();
                        */
                        $sql = sprintf('SELECT offer_id FROM webpage_offers po JOIN offers o ON(o.domain = po.domain AND o.id = po.offer_id)'
                            . ' WHERE po.domain = \'private\' AND po.webpage_id = %d AND po.major_version = %d'
                            . ' AND po.minor_version = %d AND o.deleted = 0 ORDER BY `order` ASC'
                            , $webpage_id, $page->getMajorVersion(), $page->getMinorVersion());
                        $webpage_offers = $kernel->get_set_from_db($sql);
                        $page->setOfferSource('specific');

                        if(!in_array($webpage_id, $current_webpage_ids)) {
                            $webpage_offers[] = $this->getId();
                            $page->setOfferIds($webpage_offers);
                        } elseif(!in_array($webpage_id, $webpages_to_add)) {
                            $pivot = array_search($this->getId(), $webpage_offers);

                            unset($webpage_offers[$pivot]);
                            $webpage_offers = array_values($webpage_offers);
                            $page->setOfferIds($webpage_offers);
                        }

                        $page->setData(array_merge($data, array(
                                                               'status' => $data['status'],
                                                               'shown_in_site' => 1, // shown when the page published
                                                               'relative_urls' => $relative_urls
                                                          )));

                        $page->setStatus('pending');

                        $source_path = "webpage/page/archive/p{$page->getId()}/{$page->getMajorVersion()}_{$page->getMinorVersion()}/";
                        $page->saveAsNew($module->user->getId(), true); // save as new version
                        $target_path = "webpage/page/archive/p{$page->getId()}/{$page->getMajorVersion()}_{$page->getMinorVersion()}/";
                        if ( $kernel->conf['aws_enabled'] )
                        {
                            $paginator = $kernel->s3->getPaginator( 'ListObjects', array(
                                'Bucket' => $kernel->conf['s3_bucket'],
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
                            foreach ( $source_filelist as $dir_to_copy )
                            {
                                rcopy( $dir_to_copy, $target_path, FALSE, 's3_' );
                            }
                        }
                        else
                        {
                            chdir( "{$kernel->sets['paths']['app_root']}/file/" );
                            force_mkdir( $target_path );
                            smartCopy( $source_path, $target_path );
                        }
                    }
                }
            }
        }
    }
}

/**
 * Class pageOffer
 * The offer that has a details / more page
 */
class pageOffer extends offer {
    protected $_alias = null;
	protected $_action_url_target = null;

	/** @var localeSet $_action_url */
	protected $_action_url;
	/** @var localeSet $_content */
    protected $_content;
    /** @var localeSet $_short_description */
    protected $_short_description;
    /** @var localeSet $_reservation_info */
    protected $_reservation_info;
    /** @var localeSet $_keywords */
    protected $_keywords;
    /** @var localeSet $_description */
    protected $_description;

    /** @var  array $_banners */
    protected $_banners;

    /** @var  array $_menus */
    protected $_menus;


    /**
     * @param mixed $alias
     */
    public function setAlias($alias)
    {
        $this->_alias = $alias;
    }

    /**
     * @return mixed
     */
    public function getAlias()
    {
        return $this->_alias;
    }

	/**
     * @param mixed $action_target
     */
    public function setActionUrlTarget($action_url_target)
    {
        $this->_action_url_target = $action_url_target;
    }

    /**
     * @return mixed
     */
    public function getActionUrlTarget()
    {
        return $this->_action_url_target;
    }

	/**
     * @param      $action_url
     * @param null $locale
     */
    public function setActionUrl($action_url, $locale = null)
    {
        $this->_action_url->addDataSet($action_url, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getActionUrl()
    {
        return $this->_action_url;
    }

    /**
     * @param      $content
     * @param null $locale
     */
    public function setContent($content, $locale = null)
    {
        $this->_content->addDataSet($content, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getContent()
    {
        return $this->_content;
    }

    /**
     * @param      $description
     * @param null $locale
     */
    public function setDescription($description, $locale = null)
    {
        $this->_description->addDataSet($description, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @param      $short_descriptions
     * @param null $locale
     */
    public function setShortDescription($short_description, $locale = null)
    {
        $this->_short_description->addDataSet($short_description, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getShortDescription()
    {
        return $this->_short_description;
    }

    /**
     * @param      $reservation_info
     * @param null $locale
     */
    public function setReservationInfo($reservation_info, $locale = null)
    {
        $this->_reservation_info->addDataSet($reservation_info, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getReservationInfo()
    {
        return $this->_reservation_info;
    }

    /**
     * @param      $keywords
     * @param null $locale
     */
    public function setKeywords($keywords, $locale = null)
    {
        $this->_keywords->addDataSet($keywords, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getKeywords()
    {
        return $this->_keywords;
    }

    /**
     * Get the banners with language specified
     *
     * @param null $locale
     * @return mixed|null
     */
    public function getBanners($locale = null)
    {
        if(is_null($locale)) {
            $locale = $this->getLocales();
            if(is_array($locale)) {
                $locale = array_shift(array_values($locale));
            }
        }

        return array_ifnull($this->_banners, $locale, array());
    }

    /**
     * @param string $banners
     */
    public function setBanners($banners)
    {
        $this->_banners = $banners;
    }

    /**
     * Get the menus with language specified
     *
     * @param null $locale
     * @return mixed|null
     */
    public function getMenus($locale = null)
    {
        if(is_null($locale)) {
            $locale = $this->getLocales();
            if(is_array($locale)) {
                $locale = array_shift(array_values($locale));
            }
        }

        return array_ifnull($this->_menus, $locale, array());
    }

    /**
     * @param string $menus
     */
    public function setMenus($menus)
    {
        $this->_menus = $menus;
    }

    function __construct() {
        parent::__construct();

        $this->_type = "page";

        $this->_action_url = new localeSet();
        $this->_content = new localeSet();
        $this->_short_description = new localeSet();
        $this->_reservation_info = new localeSet();
        $this->_keywords = new localeSet();
        $this->_description = new localeSet();

        $this->_banners = array();
        $this->_menus = array();
    }

    /**
     * Save the offer with the user id provided as the creator / updater
     *
     * @param int $user_id
     * @return mixed|void
     */
    public function save($user_id = 0, $locales = array()) {
        parent::save($user_id, $locales);

        $conn = db::Instance();

        $sql = 'UPDATE offers SET alias = %1$s, action_url_target = %3$s';
        $sql .= ' WHERE domain = \'private\' AND id = %2$d';
        $sql = sprintf(
            $sql,
            $conn->escape( $this->getAlias() ),
            $this->getId(),
			$conn->escape( $this->getActionUrlTarget())
        );
        $conn->exec($sql);

        //$locales = $this->getContent()->getLocales();
        foreach($locales as $locale) {
            $title = $this->getTitle()->getData($locale);
            $sql = 'UPDATE offer_locales SET content = %s, short_description = %s, reservation_info = %s, keywords = %s, description = %s, action_url = %s';
            $sql .= " WHERE domain = 'private' AND offer_id = %d AND locale = %s";
            $sql = sprintf(
                $sql,
                $conn->escape( $this->getContent()->getData($locale) ),
                $conn->escape( $this->getShortDescription()->getData($locale) ),
                $conn->escape( $this->getReservationInfo()->getData($locale) ),
                $conn->escape( $this->getKeywords()->getData($locale) ),
                $conn->escape( $this->getDescription()->getData($locale) ),
                $conn->escape( $this->getActionUrl()->getData($locale) ),
                $this->getId(),
                $conn->escape( $locale )
            );
            $conn->exec($sql);

            // Banners
            $sql = sprintf('DELETE FROM offer_locale_banners WHERE domain = \'private\' AND offer_id = %d AND locale = %s', $this->getId(), $conn->escape($locale));
            $conn->exec($sql);

            $values = array();
            $banner_id = 1;
            foreach ( $this->getBanners($locale) as $banner )
            {
                if ( trim(implode('', $banner)) !== '' && !is_null($title) && $title )
                {
                    $values[] = sprintf(
                        "('private', %d, %s, %d, %s, %s, %s, %s, %s, %s, %s)",
                        $this->getId(),
                        $conn->escape( $locale ),
                        $banner_id++,
                        $conn->escape( $banner['image_xs'] ),
                        $conn->escape( $banner['background_position_xs'] ),
                        $conn->escape( $banner['image_md'] ),
                        $conn->escape( $banner['background_position_md'] ),
                        $conn->escape( $banner['image_xl'] ),
                        $conn->escape( $banner['background_position_xl'] ),
                        $conn->escape( $banner['url'] )
                    );
                }
            }
            if ( count($values) > 0 )
            {
                $sql = 'INSERT INTO offer_locale_banners(domain, offer_id, locale, banner_id, image_xs, background_position_xs, image_md, background_position_md, image_xl, background_position_xl, url)';
                $sql .= ' VALUES ' . implode( ', ', $values );
                $conn->exec($sql);
            }

            // Menus
            $sql = sprintf('DELETE FROM offer_locale_menus WHERE domain = \'private\' AND offer_id = %d AND locale = %s', $this->getId(), $conn->escape($locale));
            $conn->exec($sql);

            $values = array();
            $menu_id = 1;
            foreach ( $this->getMenus($locale) as $menu )
            {
                if ( trim(implode('', $menu)) !== '' && !is_null($title) && $title )
                {
                    $values[] = sprintf(
                        "('private', %d, %s, %d, %s, %s)",
                        $this->getId(),
                        $conn->escape( $locale ),
                        $menu_id++,
                        $conn->escape( $menu['name'] ),
                        $conn->escape( $menu['file'] )
                    );
                }
            }
            if ( count($values) > 0 )
            {
                $sql = 'INSERT INTO offer_locale_menus(domain, offer_id, locale, menu_id, name, file)';
                $sql .= ' VALUES ' . implode( ', ', $values );
                $conn->exec($sql);
            }
        }

        return $this->getId();
    }
}

/**
 * Class linkOffer
 * The offer that has a link to another location for details / more
 */
class linkOffer extends offer {
    protected $_target = null;

    /** @var localeSet $_action_url */
    protected $_action_url;

    /** @var localeSet $_short_description */
    protected $_short_description;

    /** @var localeSet $_url */
    protected $_url;


    /**
     * @param mixed $target
     */
    public function setTarget($target)
    {
        $this->_target = $target;
    }

    /**
     * @return mixed
     */
    public function getTarget()
    {
        return $this->_target;
    }

    /**
     * @param      $action_url
     * @param null $locale
     */
    public function setActionUrl($action_url, $locale = null)
    {
        $this->_action_url->addDataSet($action_url, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getActionUrl()
    {
        return $this->_action_url;
    }

    /**
     * @param      $short_descriptions
     * @param null $locale
     */
    public function setShortDescription($short_description, $locale = null)
    {
        $this->_short_description->addDataSet($short_description, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getShortDescription()
    {
        return $this->_short_description;
    }

    /**
     * @param      $url
     * @param null $locale
     */
    public function setUrl($url, $locale = null)
    {
        $this->_url->addDataSet($url, $locale);
    }

    /**
     * @return \localeSet
     */
    public function getUrl()
    {
        return $this->_url;
    }

    function __construct() {
        parent::__construct();

        $this->_type = "link";
        $this->_action_url = new localeSet();
        $this->_short_description = new localeSet();
        $this->_url = new localeSet();
    }

    /**
     * Save the offer with the user id provided as the creator / updater
     *
     * @param int $user_id
     * @return mixed|void
     */
    public function save($user_id = 0, $locales = array()) {
        parent::save($user_id, $locales);

        $conn = db::Instance();

        $sql = 'UPDATE offers SET target = %s';
        $sql .= " WHERE domain = 'private' AND id = %d";
        $sql = sprintf(
            $sql,
            $conn->escape( $this->getTarget() ),
            $this->getId()
        );
        $conn->exec($sql);

        //$locales = $this->getUrl()->getLocales();
        foreach($locales as $locale) {
            $sql = 'UPDATE offer_locales SET action_url = %s, short_description = %s, url = %s';
            $sql .= " WHERE domain = 'private' AND offer_id = %d AND locale = %s";
            $sql = sprintf(
                $sql,
                $conn->escape( $this->getActionUrl()->getData($locale) ),
                $conn->escape( $this->getShortDescription()->getData($locale) ),
                $conn->escape( $this->getUrl()->getData($locale) ),
                $this->getId(),
                $conn->escape( $locale )
            );
            $conn->exec($sql);
        }

        return $this->getId();
    }
}

/**
 * Class localeSet
 * The locale languages data used in the offer
 */
class localeSet {
    private $_locales = array();
    private $_data_set = array();
    const LOCALE_NA = 'dummy';

    /**
     * @return array
     */
    public function getLocales()
    {
        $locales = array();
        foreach($this->_locales as $locale) {
            if($this->getData($locale))
                $locales[] = $locale;
        }

        return $locales;
    }

    function __construct($locales = null) {
        if(!is_null($locales)) {
            if(!(is_array($locales))) {
                $locales = array($locales);
            }

            $this->_locales = array_unique($locales);
        }
    }

    public function addDataSet($data, $locale = null) {
        $data_to_add = array();
        if(is_array($data))
            $data_to_add = $data;
        else
            $data_to_add[$locale] = $data;

        foreach($data_to_add as $locale => $value) {
            $this->addData($value, $locale);
        }
    }

    private function addData($data, $locale = null) {
        if(is_null($locale)) {
            $locale = localeSet::LOCALE_NA;
        }

        if(!in_array($locale, $this->_locales)) {
            $this->_locales[] = $locale;
        }

        $this->_data_set[$locale] = $data;
    }

    public function getData($locale = null) {
        if(is_null($locale) && count($this->_locales)) {
            $locale = array_values($this->_locales);
            $locale = $locale[0];
        }

        if(in_array($locale, $this->_locales)) {
            $val = isset($this->_data_set[$locale]) ? $this->_data_set[$locale] : null;
            return $val;
        }

        return false;
    }
}
