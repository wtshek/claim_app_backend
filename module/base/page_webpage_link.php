<?php

/**
 * Class webpageLinkPage
 * Webpage link pages linking to an internal page id for the redirections to another page when request
 * of this page type is made.
 */
class webpageLinkPage extends page {
    protected $_type = "webpage_link";

    /** @var array $_locale_query_string */
    protected $_locale_query_string;

    /** @var array $_targets */
    protected $_targets;

    /** @var  array $linked_page_ids */
    protected $_linked_page_ids;

    /** @var array $_query_strings */
    protected $_query_strings;

    /**
     * @param array $locale_query_string
     */
    public function setLocaleQueryString($locale_query_string)
    {
        $this->_locale_query_string = $locale_query_string;
    }

    /**
     * Get the query string with locale provided
     *
     * @param null $locale
     * @param bool $alternate_locale
     * @return mixed|null
     */
    public function getLocaleQueryString($locale = null, $alternate_locale = false)
    {
        if(is_null($locale)) {
            $locale = $this->getLocales();
            if(is_array($locale)) {
                $locale = array_values($locale);
                $locale = array_shift($locale);
            }
        }

        return $this->getLocaleValue($this->_locale_query_string, $locale, $alternate_locale);
    }

    /**
     * Get the webpage id that linked
     *
     * @return array
     */
    public function getLinkedPageIds() {
        return $this->_linked_page_ids;
    }

    /**
     * Set the linked page id to an array
     *
     * @param $ids
     */
    public function setLinkedPageIds ($ids) {
        $this->_linked_page_ids = $ids;
    }

    /**
     * Get the linked page id with platform provided
     *
     * @param $platform
     * @return null|string
     */
    public function getLinkedPageId($platform) {
        return $this->getPlatformValue($this->_linked_page_ids, $platform);
    }

    /**
     * Set the query string to an array
     *
     * @param array $query_strings
     */
    public function setQueryStrings($query_strings) {
        $this->_query_strings = $query_strings;
    }

    /**
     * Get the query string
     *
     * @return mixed|null
     */
    public function getQueryStrings() {
        return $this->_query_strings;
    }

    /**
     * Get the query string with platform provided
     *
     * @param $platform
     * @return null|string
     */
    public function getQueryString($platform) {
        return $this->getPlatformValue($this->_query_strings, $platform);
    }

    /**
     * Set the targets in an array
     *
     * @param $targets
     */
    public function setTargets ($targets) {
        $this->_targets = $targets;
    }

    /**
     * Get the target with platform provided
     *
     * @param $platform
     * @return null|string
     */
    public function getTarget($platform) {
        return $this->getPlatformValue($this->_targets, $platform);
    }

    /**
     * Get the targets in an array
     *
     * @return array
     */
    public function getTargets () {
        return $this->_targets;
    }

    function __construct() {
        /** @var kernel $kernel */
        $kernel = kernel::getInstance();
    }

    /**
     * Get method name with the variable name provided
     *
     * @param $name
     * @return string
     */
    public function getMethodName($name) {
        switch($name) {
            case "linked_page_id":
                $name = "linked_page_ids";
                break;
            case "query_string":
                $name = "query_strings";
                break;
            case "target":
                $name = "targets";
                break;
            case "type":
            default:
                $name = parent::getMethodName($name);
                break;
        }
        return $name;
    }

    /**
     * Save the page into db with the data in the object
     *
     * @param $id
     */
    public function save($id) {
        parent::save($id);
        $conn = db::Instance();
        $kernel = kernel::getInstance();

        foreach($this->getPlatforms() as $platform) {
            $sql = 'UPDATE webpage_platforms SET linked_webpage_id = %d, query_string = %s, target = %s';
            $sql .= " WHERE domain = 'private' AND webpage_id = %d";
            $sql .= ' AND platform = %s AND major_version = %d AND minor_version = %d';
            $sql = sprintf(
                $sql,
                $this->getPlatformValue( $this->_linked_page_ids, $platform ),
                $conn->escape( $this->getPlatformValue($this->_query_strings, $platform) ),
                $conn->escape( $this->getPlatformValue($this->_targets, $platform) ),
                $this->_id,
                $conn->escape( $platform ),
                $this->_major_version,
                $this->_minor_version
            );
            $conn->exec($sql);
        }

        // check if has at least one title, if not, add empty locale to default language
        $has_title = false;
        foreach($this->getLocales() as $locale) {
            $title = $this->getLocaleValue($this->_title, $locale);
            if(!is_null($title) && $title) {
                $has_title = true;
                break;
            }
        }

        foreach($this->getLocales() as $locale) {
            if(in_array($locale, $this->getSavingLocales()))
            {
                $title = $this->getLocaleValue($this->_title, $locale);

                if(!is_null($title) && $title ||
                    (!$has_title && $locale == $kernel->default_public_locale)) {

                    // Set the visual version of each saving-locale
                    $visual_version = 1;
                    $sql = sprintf('SELECT visual_version FROM webpage_locales WHERE domain= \'private\' AND webpage_id=%d'
                                    . ' AND status NOT IN("pending", "draft") AND locale=%s'
                                    . ' ORDER BY visual_version DESC'
                                    . ' LIMIT 0, 1'
                                    , $this->_id
                                    , $conn->escape($locale));
                    $statement = $conn->query($sql);
                    if($row = $statement->fetch()) {
                        $visual_version = $row['visual_version']+1;
                    }

                    $sql = sprintf('REPLACE webpage_locales(domain, webpage_id, locale, major_version, minor_version, visual_version'
                        . ', webpage_title, query_string, publish_date, removal_date, status, updated_date, updater_id)'
                        . " VALUES ('private', %d, %s, %d, %d, %d, %s, %s, %s, %s, %s, UTC_TIMESTAMP(), %d)"
                        , $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version, $visual_version
                        , $conn->escape($this->getLocaleValue($this->_title, $locale))
                        , $conn->escape($this->getLocaleValue($this->_locale_query_string, $locale))
                        , $conn->escape($this->getLocaleValue($this->_publish_date, $locale))
                        , $conn->escape($this->getLocaleValue($this->_removal_date, $locale))
                        , $conn->escape($this->getStatus())
                        , $id);
                    $conn->exec($sql);
                }
				else
				{
					// Both content and title was removed
					$has_previous = false;
					$sql = sprintf('SELECT COUNT(*) AS has_previous FROM webpage_locales WHERE domain=%s AND webpage_id=%d AND locale=%s', $conn->escape('private'), $this->_id, $conn->escape($locale));
					$statement = $conn->query($sql);
					if($row = $statement->fetch()) {
						$has_previous = $row['has_previous']>0 ? true : false;
					}
					if($has_previous)
					{
						$sql = sprintf('REPLACE webpage_locales(domain, webpage_id, locale, major_version, minor_version, visual_version'
						. ', webpage_title, seo_title, headline_title, keywords, description, url, query_string, status, updated_date, updater_id)'
						. " VALUES ('private', %d, %s, %d, %d, %d, NULL, NULL, NULL, NULL, NULL, NULL, NULL, %s, UTC_TIMESTAMP(), %d)"
						, $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version, $visual_version
						, $conn->escape($this->getStatus())
						, intval($id));
						$conn->exec($sql);
					}
				}
            }
            else
            {
                // only update the versions of the locales that current user cannot access and uncheck from saving locales list
				$sql = sprintf('SELECT major_version, minor_version FROM webpage_locales WHERE domain="private" AND webpage_id=%d AND locale=%s ORDER BY major_version DESC, minor_version DESC LIMIT 0,1', $this->_id, $conn->escape($locale));
				$statement = $conn->query($sql);
				$last_version = ($row = $statement->fetch()) ? $row['major_version'] : 0;
				if($last_version >= $this->_major_version-1)
				{
					$sql = sprintf('REPLACE INTO webpage_locales(domain, webpage_id, locale, major_version, minor_version, visual_version'
						. ', webpage_title, query_string, status, updated_date, updater_id)'
						. ' SELECT "private", webpage_id, %2$s, %3$d, %4$d, visual_version, webpage_title, query_string, status, updated_date, updater_id FROM (SELECT domain, webpage_id, locale, major_version, minor_version, visual_version, webpage_title, query_string, status, updated_date, updater_id FROM webpage_locales WHERE webpage_id=%1$d AND locale=%2$s AND domain="private" ORDER BY major_version DESC, minor_version DESC LIMIT 0, 1) AS wl'
						, $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version);
					$conn->exec($sql);
				}
            }
        }
    }

    /**
     * retrieve the data according to known platforms with locale provided
     *
     * @param array  $locales
     * @param string $type
     */
    public function retrieveData($locales = array(), $type = "public") {
        $platforms = $this->getPlatforms();
        $conn = db::Instance();

        if(!is_array($locales)) {
            $locales = array($locales);
        }

        $sql = sprintf('SELECT * FROM webpage_platforms WHERE domain = %s AND webpage_id = %d'
            . ' AND platform IN(%s) AND major_version = %d AND minor_version = %d'
            , $conn->escape($type)
            , $this->getId(), implode(', ', array_map(array($conn, 'escape'), $platforms))
            , $this->getMajorVersion(), $this->getMinorVersion());
        $statement = $conn->query($sql);

        while($row = $statement->fetch()) {

            $platform = $row['platform'];

            $this->_linked_page_ids[$platform] = $row['linked_webpage_id'];
            $this->_query_strings[$platform] = $row['query_string'];
            $this->_targets[$platform] = $row['target'];

        }

        // no need to get title becuase it should already got in sitemap
        $sql = sprintf('SELECT * FROM webpage_locales WHERE domain = %s AND webpage_id = %d'
            . ' AND major_version = %d AND minor_version = %d AND locale IN(%s) AND (domain = \'private\' OR status=\'approved\')'
            , $conn->escape($type)
            , $this->getId()
            , $this->getMajorVersion(), $this->getMinorVersion()
            , implode(', ', array_map(array($conn, 'escape'), $locales))
        );
        $statement = $conn->query($sql);
        $locales = array();

        while($row = $statement->fetch()) {

            $locale = $row['locale'];
            $locales[] = $locale;

            $this->_title[$locale] = $row['webpage_title'];
			$this->_locale_query_string[$locale] = $row['query_string'];

        }

        $this->setLocales($locales);

        parent::retrieveData($locales, $type);
    }
}
