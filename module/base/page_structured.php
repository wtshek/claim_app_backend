<?php

class structuredPagePage extends page {
    protected $_type = "structured_page";

    /** @var  array $_snippets */
    public static $_snippets;

    /** @var array $_contents */
    protected $_contents;

    /** @var array $_html */
    protected $_html;

    /** @var  array $_keywords */
    protected $_keywords;

    /** @var  array $_description */
    protected $_description;

    /** @var array $_seo_title */
    protected $_seo_titles;

    /** @var array $_submenu_shown */
    protected $_submenu_shown;

    /** @var int $_structured_page_template **/
    protected $_structured_page_template;

    /** @var  array $_offer_ids */
    protected $_offer_ids;

    /** @var  string $_offer_source */
    protected $_offer_source;

    /**
     * @param array $submenu_shown
     */
    public function setSubmenuShown($submenu_shown)
    {
        $this->_submenu_shown = $submenu_shown;
    }

    /**
     * @return array
     */
    public function getSubmenuShown()
    {
        return $this->_submenu_shown;
    }

    /** Getters AND Setters - BEGIN */

    /**
     * @return string
     */
    public function getHtml()
    {
        // TODO: if no content has been added before
        if(!is_null($this->_contents) && !isset($this->_html))
            $this->decode();

        if($this->_html == "" && count($this->getAlternates()) > 0) {
            return $this->getAlternates(0)->getHtml();
        }
        return $this->_html;
    }

    /**
     * Get the decoded html of the content with platform specified
     *
     * @param      $platform
     * @param null $locale
     * @return null|string
     */
    public function getPlatformHtml($platform, $locale = null) {
        // TODO: if no content has been added before
        if(!is_null($this->_contents) && !isset($this->_html))
            $this->decode();

        $platformHtmls = $this->getPlatformValue($this->_html, $platform);

        if(!is_null($platformHtmls) || isset($platformHtmls[$locale])) {
            return is_null($locale) ? $platformHtmls : (isset($platformHtmls[$locale]) ? $platformHtmls[$locale] : '');
        }
        return null;
    }

    /**
     * Set the contents
     *
     * @param array $contents
     */
    public function setContents($contents)
    {
        $this->_contents = $contents;
    }

    /**
     * Get the contents of the page
     *
     * @param bool $shown_as_alt
     * @return array
     */
    public function getContents($shown_as_alt = true)
    {
        if($shown_as_alt) {
            // get locale from title - it is assumed that title must exists for the locale available
            // not using getLocales because it will return all available locales in that page but not the locale user want
            $locales = array_keys($this->getTitles());
            $platforms = $this->getPlatforms();
            $kernel = kernel::getInstance();

            // get alternate content if not exists
            foreach($platforms as $platform) {
                $dummy_contents = null;
                $default_locale_values = $default_locale_keys = array();
                if ( array_key_exists($platform, $this->_contents) )
                {
                    $default_locale_values = array_values($this->_contents[$platform]);
                    $default_locale_keys = array_keys($this->_contents[$platform]);
                }

                if(count($default_locale_values)) {
                    foreach($default_locale_keys as $i => $locale) {
                        $default_locale_value = array_shift($default_locale_values);
                        $section_has_content = 0;

                        if(isset($this->_contents[$platform][$locale])) {
                            foreach($this->_contents[$platform][$locale] as $section_content) {
                                if(preg_replace("#\&nbsp;#", "", preg_replace("#[\n\r\t\s]#", "", $section_content)) !== "") {
                                    $section_has_content++;
                                    break;
                                }
                            }
                        }

                        if($section_has_content) {
                            if(is_null($dummy_contents)) {
                                $default_locale_key = $locale;
                                $dummy_contents = $default_locale_value;

                                foreach($dummy_contents as &$content_block) {
                                    if($content_block) {
                                        break;
                                    }
                                }
                            }
                        } else {
                            unset($default_locale_keys[$i]);
                        }
                    }

                    unset($content_block);

                    if(!is_null($dummy_contents)) {

                        foreach($locales as $locale) {
                            if(!in_array($locale, $default_locale_keys)) {

                                $content_replaced = false;
                                $content_to_replace = $dummy_contents;

                                foreach(array_keys($content_to_replace) as $content_block_name) {

                                    if(isset($content_to_replace[$content_block_name]) && !$content_replaced) {

                                        if(preg_replace("#\&nbsp;#", "", preg_replace("#[\n\r\t\s]#", "", strip_tags($content_to_replace[$content_block_name]))) !== "") {
                                            $content_to_replace[$content_block_name] = sprintf('<p class="from_lang from_lang_%s">%s</p>', $default_locale_key
                                                , sprintf($kernel->dict['MESSAGE_display_alt_language']
                                                    , $kernel->sets['public_locales'][$locale]
                                                    , $kernel->sets['public_locales'][$default_locale_key])
                                            ) . $content_to_replace[$content_block_name];
                                        }
                                        $content_replaced = true;
                                    }
                                }

                                $this->_contents[$platform][$locale] = $content_to_replace;
                            }

                        }
                    }

                    $intersect = array_diff(array_keys($this->_contents[$platform]), $locales);

                    foreach($intersect as $locale) {
                        unset($this->_contents[$platform][$locale]);
                    }
                }
            }
        }

        return $this->_contents;
    }

    /**
     * Get the content with platform specified
     *
     * @param      $platform
     * @param null|string|array $locale
     * @return mixed
     */
    public function getPlatformContent($platform, $locale = null)
    {
        $platformContents = $this->getPlatformValue($this->_contents, $platform);
        if(!is_null($platformContents) && (is_null($locale) || isset($platformContents[$locale]))) {
            return is_null($locale) ? $platformContents : $platformContents[$locale];
        }
        return null;
    }

    /**
     * Set the platform content with platform, locale specified
     *
     * @param $platform
     * @param $locale
     * @param $content
     */
    public function setPlatformContent($platform, $locale, $content) {
        if(in_array($platform, $this->getPlatforms()) && in_array($locale, $this->getLocales())) {
            $this->_contents[$platform][$locale] = $content;
        }
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * Get the description with language specified
     *
     * @param null $locale
     * @return mixed|null
     */
    public function getDescription($locale = null)
    {
        if(is_null($locale)) {
            $locale = $this->getLocales();
            if(is_array($locale)) {
                $locale = array_values($locale);
                $locale = array_shift($locale);
            }
        }

        return $this->getLocaleValue($this->_description, $locale);
    }

    /**
     * Set the keywords in array
     *
     * @param string $keywords
     */
    public function setKeywords($keywords)
    {
        $this->_keywords = $keywords;
    }

    /**
     * Get the keywords
     *
     * @param null $locale
     * @return mixed|null
     */
    public function getKeywords($locale = null)
    {
        if(is_null($locale)) {
            $locale = $this->getLocales();
            if(is_array($locale)) {
                $locale = array_values($locale);
                $locale = array_shift($locale);
            }
        }

        return $this->getLocaleValue($this->_keywords, $locale);
    }

    /**
     * Set the seo title
     *
     * @param array $seo_titles
     */
    public function setSeoTitles($seo_titles)
    {
        $this->_seo_titles = $seo_titles;
    }

    /**
     * Get the seo titles
     *
     * @return array
     */
    public function getSeoTitles()
    {
        return $this->_seo_titles;
    }

    /**
     * Get the Seo title with locale provided
     *
     * @param null $locale
     * @return mixed|null
     */
    public function getSeoTitle($locale = null) {

        if(is_null($locale)) {
            $locale = $this->getLocales();
            if(is_array($locale)) {
				$locale = array_values($locale);
                $locale = array_shift($locale);
            }
        }

        $title = $this->getLocaleValue($this->_seo_titles, $locale);

        if($title == "" || is_null($title)) {
            return $this->getTitle($locale);
        }

        return $title;
    }

    /**
     * Get header title (the title tag shown in header)
     *
     * @param null $locale
     * @return mixed|null
     */
    public function getHeaderTitle($locale = null) {
        if(is_null($locale)) {
            $locale = $this->getLocales();
            if(is_array($locale)) {
				$locale = array_values($locale);
                $locale = array_shift($locale);
            }
        }

        return $this->getTitle($locale);
    }

    function __construct($ref = null) {
        /** @var kernel $kernel */
        $kernel = kernel::getInstance();
    }
    /** Getters AND Setters - END */

    /**
     * Decode the links into html
     *
     * @param null $contents
     * @return array|null     *
     */
    public function linksDecode($contents = null) {
        /** @var default_module $module */
        $module = kernel::$module;

        /** @var kernel $kernel */
        $kernel = kernel::getInstance();

        if(is_null($contents)) {
            $contents = $this->getContents();
        }

        foreach($contents as $platform => &$locale_content) {
            $sm = $module->get_sitemap($module->data['mode'], $platform);

            foreach($locale_content as $locale => &$section_contents) {

                foreach($section_contents as &$content) {
                    $link_calls = array();
                    preg_match_all( '/\[\[(\d+)\]\]/', $content, $link_calls );
                    foreach ( $link_calls[1] as $link_call )
                    {
                        $webpage_id = intval( $link_call );

                        /** @var pageNode $pn */
                        $pn = $sm->getRoot()->findById($webpage_id);

                        if($pn && !is_null($pn)) {
                            $url = $kernel->sets['paths']['app_from_doc'] . '/' . $kernel->request['locale']
                                . $pn->getItem()->getRelativeUrl($platform);

                            // Replace the link content
                            $section_contents = preg_replace(
                                '/\[\[' . $link_call . '\]\]/',
                                $url,
                                $section_contents
                            );
                        }
                    }
                }
            }
        }

        unset($locale_content);
        unset($section_contents);
        unset($content);

        return $contents;
    }

    /**
     * Get the snippets from db / cache file
     *
     * @return array
     */
    public static function getSnippets()
    {
        $db = db::Instance();
        if(!isset(self::$_snippets)) {
            $cache_path = kernel::getInstance()->sets['paths']['app_root'] . '/file/cache/snippets.json';
            if ( file_exists($cache_path) )
            {
                self::$_snippets = json_decode( file_get_contents($cache_path), TRUE );
            }
            else
            {
                $query = 'SELECT * FROM snippets WHERE deleted = 0';
                $statement = $db->query( $query );

                while($row = $statement->fetch()) {
                    self::$_snippets[$row['alias']] = $row;
                }

                file_put_contents( $cache_path, json_encode(self::$_snippets) );
            }
        }

        return self::$_snippets;
    }

    /**
     * Decode snippet code into viewable html
     *
     * @param null $contents
     * @return array|null
     */
    public function snippetDecode($content = null) {
        /** @var kernel $kernel */
        $kernel = kernel::getInstance();

        // Get the snippets
        $snippets = self::getSnippets();

        // Grab the snippet calls
        $snippet_calls = array();
        preg_match_all( '/\{\{[^\}]+\}\}/', $content, $snippet_calls );

        foreach ( $snippet_calls[0] as $snippet_call )
        {
            $snippet_call = html_entity_decode(
                strip_tags(substr($snippet_call, 2, strlen($snippet_call)-4)),
                ENT_QUOTES,
                'UTF-8'
            );

            // Get the id of customized snippet
            $snippet_call_parts = explode('=', $snippet_call);
            if(isset($snippet_call_parts[1]))
                $customize_snippet_id = $snippet_call_parts[1];
            else
                $customize_snippet_id = 0;
            if(preg_match('/^content_block/i', $snippet_call_parts[0]) && preg_match('/^\d+$/i', $customize_snippet_id))
            {
                // Grab the snippet alias and parameters
                $snippet_data = self::getCustomizeSnippet($customize_snippet_id);
                $alias = $snippet_data['alias'];
                $parameters = array();
                if(isset($snippet_data['content']) && gettype($snippet_data['content']) == 'array' && count($snippet_data['content'])>0)
                {
                    if (is_array($snippet_data['content'][$locale]) || is_object($snippet_data['content'][$locale]))
                    {
                        foreach ( $snippet_data['content'][$locale] as $parameter_name => $parameter_value)
                        {
                            $parameters[$parameter_name] = trim( $parameter_value );
                        }
                    }
                    
                }
                if(isset($snippet_data['general_content']) && gettype($snippet_data['general_content']) == 'array' && count($snippet_data['general_content'])>0)
                {
                    if (is_array($snippet_data['general_content']) || is_object($snippet_data['general_content']))
                    {
                        foreach ( $snippet_data['general_content'] as $parameter_name => $parameter_value)
                        {
                            $parameters[$parameter_name] = trim( $parameter_value );
                        }
                    }
                }
            }
            else
            {
               // Compatible with old snippet code style
               $snippet_call_parts = strpos($snippet_call, "\r") !== false
                || strpos($snippet_call, "\n") !== false
                    ? $snippet_call_parts = preg_split( '/[\r|\n]+/', $snippet_call )
                    : $snippet_call_parts = explode( ' ', $snippet_call );
                $snippet_call_parts = array_map( 'trim', $snippet_call_parts );
                $snippet_call_parts = array_diff( $snippet_call_parts, array('') );
                
                $snippet_data['content'] = array(1); // make $snippet_data not empty to run the "Execute the snippet" part

                // Grab the snippet alias and parameters
                $alias = array_shift( $snippet_call_parts );
                $parameters = array();
                foreach ( $snippet_call_parts as $snippet_call_part )
                {
                    $parameter_parts = explode( '=', $snippet_call_part );
                    $parameter_name = array_shift( $parameter_parts );
                    $parameter_value = implode( '=', $parameter_parts );
                    $parameters[$parameter_name] = trim( $parameter_value );
                }
            }

            // Execute the snippet
            if ( array_key_exists($alias, $snippets) && !empty($snippet_data) )
            {
                $snippet = $snippets[$alias];
                // Require the snippet function
                $snippet_function = $alias . '_snippet';
                if ( !function_exists($snippet_function) )
                {
                    require_once(
                        $kernel->sets['paths']['app_root']
                        . '/file/snippet/'
                        . $snippet['id']
                        . '/index.php'
                    );
                }

                // Replace the snippet content
                preg_match('/\{\{[^\}]+\}\}/', $content, $matches, PREG_OFFSET_CAPTURE);

                $content = substr($content, 0, $matches[0][1])
                    . $snippet_function( kernel::$module, $snippet, $parameters, $this )
                    . substr($content, $matches[0][1] + strlen($matches[0][0]));
            }
            else
            {
                // Replace the snippet content
                $content = preg_replace(
                    '/\{\{[^\}]+\}\}/',
                    '',
                    $content,
                    1
                );
            }
        }

        return $content;
    }

    public function snippetDecodeById($snippet_id, $locale){
        /** @var kernel $kernel */
        $kernel = kernel::getInstance();

        $db = db::Instance();

        // Get the snippets
        $snippets = self::getSnippets();

        $snippet_content = '';

        $query = 'SELECT cs.*, s.alias FROM customize_snippets cs LEFT JOIN snippets s ON (s.id=cs.snippet_type_id) WHERE cs.id = '.$snippet_id;
        $statement = $db->query( $query );
        if($customize_snippet = $statement->fetch())
        {
            if($customize_snippet['general_para_value'] != '')
                $customize_snippet['general_content'] = json_decode($customize_snippet['general_para_value'], true);
            else
                $customize_snippet['general_content'] = array();
            $customize_snippet['content'] = array();
            $query = 'SELECT * FROM customize_snippet_locales csl LEFT JOIN locales ON (locales.alias=csl.locale) WHERE snippet_id='.$snippet_id;
            $statement = $db->query( $query );
            while($r = $statement->fetch())
            {
                $customize_snippet['content'][$r['alias']] = json_decode($r['parameter_values'], true);
            }

            $parameters = array();
            foreach ( $customize_snippet['content'][$locale] as $parameter_name => $parameter_value)
            {
                $parameters[$parameter_name] = trim( $parameter_value );
            }
            foreach ( $customize_snippet['general_content'] as $parameter_name => $parameter_value)
            {
                $parameters[$parameter_name] = trim( $parameter_value );
            }
            // Require the snippet function
            $snippet_function = $customize_snippet['alias'] . '_snippet';
            if ( !function_exists($snippet_function) )
            {
                require(
                    $kernel->sets['paths']['app_root']
                    . '/file/snippet/'
                    . $customize_snippet['snippet_type_id']
                    . '/index.php'
                );
            }

            $snippet_content = $snippet_function( kernel::$module, $snippets[$customize_snippet['alias']], $parameters, $this );
        }

        return $snippet_content;
    }

    /**
     * Decode the flvs into html
     *
     * @param null $contents
     * @return array|null
     */
    public function flvsDecode($contents = null) {
        /** @var kernel $kernel */
        $kernel = kernel::getInstance();

        if(is_null($contents)) {
            $contents = $this->getContents();
        }

        foreach($contents as $platform => &$locale_content) {
            foreach($locale_content as $locale => &$section_contents) {

                foreach($section_contents as &$content) {

                    $content = str_replace(
                        'player.swf?video=',
                        "player.swf?video={$kernel->sets['paths']['app_from_doc']}/",
                        $content
                    );
                }
            }
        }

        unset($locale_content);
        unset($section_contents);
        unset($content);

        return $contents;
    }

    /**
     * Decode media paths into viewable html
     *
     * @param null $contents
     * @return array|null
     */
    public function mediaDecode($contents = null) {
        $table_prefix = kernel::$module->data['mode'] == 'preview' ? 'private' : 'public';

        /** @var kernel $kernel */
        $kernel = kernel::getInstance();

        if(is_null($contents)) {
            $contents = $this->getContents();
        }

        if($table_prefix == "private") {
            foreach($contents as $platform => &$locale_content) {
                foreach($locale_content as $locale => &$section_contents)
                {
                    $tmp_locale_content = json_decode($section_contents['content'], true);
                    foreach($tmp_locale_content as &$content) {
                        $tmp = $content;
                        foreach($tmp as &$v)
                        {
                            //foreach($val as &$v)
                            //{
                                if(gettype($v)=='string')
                                {
                                    $tmp_val = $this->tempImgPathDecode($v);
                                    $v = $tmp_val;
                                    unset($tmp_val);
                                }
                            //}
                        }
                        //$content = json_encode($tmp);
                        $content = $tmp;
                        unset($tmp);
                    }
                    $section_contents['content'] = json_encode($tmp_locale_content);
                    unset($tmp_locale_content);
                }
            }
        }

        unset($locale_content);
        unset($section_contents);
        unset($content);

        return $contents;
    }

    /**
     * perform the decode process to decode the content into html
     *
     * @return array|null
     */
    public function decode() {
        $content = $this->mediaDecode();
        $content = $this->linksDecode($content);
        $content = $this->flvsDecode($content);

        if($this->_structured_page_template == 3)
        {
            foreach($content as $platform => &$locale_content) {
                foreach($locale_content as $locale => &$section_content) {
                    $snippet_id = 0;
                    $structured_page_data = json_decode($section_content['content'], true);
                    foreach($structured_page_data as $section_id=>$field_data)
                    {
                        foreach($field_data as $key=>$val)
                        {
                            if($key == 'main_content_snippet_id')
                                $snippet_id = $val;
                        }
                    }
                    if($snippet_id>0)
                    {
                        $structured_page_data[0]['real_content'] = $this->snippetDecodeById($snippet_id, $locale);
                    }
                    $section_content['content'] = json_encode($structured_page_data);
                    unset($structured_page_data);
                }
            }
        }

        $this->_html = $content;

        return $this->_html;
    }

    /**
     * @param int $structured_page_template
     */
    public function setStructuredPageTemplate($structured_page_template)
    {
        $this->_structured_page_template = $structured_page_template;
    }

    /**
     * @return int
     */
    public function getStructuredPageTemplate()
    {
        return $this->_structured_page_template;
    }

    /**
     * Set the offer ids
     *
     * @param array $offer_ids
     */
    public function setOfferIds($offer_ids)
    {
        $this->_offer_ids = $offer_ids;
    }

    /**
     * Get all offer ids and return as an array
     *
     * @return array
     */
    public function getOfferIds()
    {
        return $this->_offer_ids;
    }

    /**
     * Set the source of offer for that page (specific / inherited)
     *
     * @param string $offer_source
     */
    public function setOfferSource($offer_source)
    {
        $this->_offer_source = $offer_source;
    }

    /**
     * Get the current offer source
     *
     * @return string
     */
    public function getOfferSource()
    {
        return $this->_offer_source;
    }

    /**
     * Get the method name base on the variable name provided
     *
     * @param $name
     * @return string
     */
    public function getMethodName($name) {
        switch($name) {
            case "desktop_deleted":
                break;
            case "seo_description":
                $name = "description";
                break;
            case "seo_keywords":
                $name = "keywords";
                break;
            /*case "content":
                $name = "contents";
                break;*/
            case "seo_title":
                $name = "seo_titles";
                break;
            case "selected_offers";
                $name = 'offer_ids';
            case "type":
            default:
                $name = parent::getMethodName($name);
                break;
        }
        return $name;
    }

    /**
     * Check if the page has any content inside
     * (with html space, line breaks, tabs are removed)
     *
     * @return bool
     */
    public function hasContent() {

        if(is_null($this->_contents))
            return false;

        $platforms = $this->getPlatforms();
        $locales = $this->getLocales();

        if(count($platforms) && count($locales)) {
            $contents = array();
            foreach($locales as $locale) {
                $tmp = $this->getPlatformContent($platforms[0], $locale);
                if(is_array($tmp)) {
                    $contents = array_merge($contents, $tmp);
                }
            }

            foreach($contents as $content) {
                $content = preg_replace("#\&nbsp;#", "", preg_replace("#[\n\r\t\s]#", "", strip_tags($content)));

                if($content !== "")
                    return true;
            }
        }

        return false;
    }

    /**
     * Check and compare whether the pages are equal
     *
     * @param staticPage $target
     * @return bool
     */
    public function equal(staticPage $target) {
        return get_class($target) == get_class($this) && $target->getId() === $this->getId();
    }

    /**
     * Clear the content
     */
    public function clear() {
        $this->_html = $this->_keywords = $this->_description
            = $this->_title = $this->_headline_title = $this->_relative_urls
            = $this->_root_url = null;

        $this->_contents = $this->_templates = $this->_accessible_public_roles = array();

        parent::clear();
    }

    /**
     * set the data according to the resources provided
     *
     * @param array $data
     */
    public function setData($data = array()) {
        $contents = array();

        foreach($data as $name => $value) {
            $name = $this->getMethodName($name);

            // data to be ignored from the object (if specific method not exists)
            $methodName = "set" . preg_replace(@"#\s#i", "", ucwords(preg_replace("#_#", " ", $name)));
            if(method_exists($this,$methodName) && $value !== "") {
                $this->$methodName($value);
            }
            else
            {
                if(in_array($name, $this->getLocales()))
                {
                    foreach($this->getPlatforms() as $platform)
                    {
                        $contents[$platform][$name]['content'] = json_encode($value);
                    }
                }
            }
        }

        $this->setContents($contents);
    }

    /**
     * Save the page into db with the data in the object
     *
     * @param $id
     */
    public function save($id) {
        parent::save($id);

        $conn = db::Instance();
        /** @var admin_module $module */
        $module = kernel::$module;

        // offer module
        $sql = "DELETE FROM webpage_offers WHERE domain = 'private' AND webpage_id = {$this->_id}";
        $sql .= " AND major_version = {$this->_major_version} AND minor_version = {$this->_minor_version}";
        $conn->exec($sql);

        if($this->getOfferSource() == "specific") {
            if(is_array($this->_offer_ids) && count($this->_offer_ids)) {
                $subsqls = array();
                foreach($this->_offer_ids as $order => $offer_id) {
                    $subsqls[] = sprintf('(\'private\', %1$d, %2$d, %3$d, %4$d, %5$d)'
                                        , $this->_id, $this->_major_version, $this->_minor_version
                                        , $offer_id, $order);
                }

                $sql = sprintf('INSERT INTO webpage_offers(domain, webpage_id, major_version, minor_version, offer_id, `order`)'
                                . ' VALUES %s'
                                , implode(', ', $subsqls));
                $conn->exec($sql);
            }
        }

        $sm = $module->get_sitemap('edit', 'desktop');
        $url = $this->getRelativeUrl('desktop');
        if(is_null($url) || !$url) {
            $url = "/";
        } elseif($url != "/") {
            // find its parent
            $url = preg_replace('#^(.+?)[^\/]+\/$#', "\\1", $url);
        }
        require_once(dirname(dirname(__FILE__)) . '/webpage_admin/index.php');
        webpage_admin_module::updateOfferLinkage($this, $sm->findPage($url));

        $sql = sprintf('UPDATE webpages SET structured_page_template = %d WHERE domain = \'private\' AND id = %d AND major_version = %d AND minor_version = %d',
        $this->_structured_page_template,
        $this->_id,
        $this->_major_version,
        $this->_minor_version);
        $conn->exec($sql);

        foreach($this->getPlatforms() as $platform) {
            $sql = 'UPDATE webpage_platforms SET submenu_shown = %d';
            $sql .= " WHERE domain = 'private' AND webpage_id = %d";
            $sql .= ' AND platform = %s AND major_version = %d AND minor_version = %d';
            $sql = sprintf(
                $sql,
                $this->_submenu_shown[$platform] ? 1 : 0,
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

                $hasContent = false;

                // to handle case when has content but no title (draft)
                if($this->getStatus() == "draft") {
                    if (is_array($this->getPlatforms()) || is_object($this->getPlatforms()))
                    {
                        foreach($this->getPlatforms() as $platform) {
                            if (is_array($this->getPlatformContent($platform, $locale)) || is_object($this->getPlatformContent($platform, $locale)))
                            {
                                foreach($this->getPlatformContent($platform, $locale) as $type => $content) {
                                    // encoding
                                    $tmp = json_decode($content, true);
                                    $contents = array();
                                    foreach($tmp as &$val)
                                    {
                                        foreach($val as &$v)
                                        {
                                            if(gettype($v)=='string')
                                            {
                                                $tmp_val = $this->imgPathEncode('temp', $this->_id, $v, $id);
                                                $v = $tmp_val['content'];
                                                unset($tmp_val);
                                            }
                                            $contents[] = $v;
                                        }
                                    }
                                    $encoded_content['content'] = json_encode($tmp);
                                    unset($tmp);
                                    //$encoded_content = $this->imgPathEncode('temp', $this->_id, $tmp_content, $id);
                                    unset($tmp_content);
                                    //if($encoded_content['content'] !== '') {
                                    if(implode('', $contents) !== '') {
                                        $hasContent = true;

                                        break;
                                    }
                                }
                            }
                            if($hasContent)
                                break;
                        }
                    }
                }

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

                if($hasContent || !is_null($title) && $title ||
                    (!$has_title && $locale == $module->kernel->default_public_locale)) {
                    $sql = sprintf('REPLACE webpage_locales(domain, webpage_id, locale, major_version, minor_version, visual_version'
                        . ', webpage_title, seo_title, keywords, description, publish_date, removal_date, status, updated_date, updater_id)'
                        . " VALUES ('private', %d, %s, %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, UTC_TIMESTAMP(), %d)"
                        , $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version, $visual_version
                        , $conn->escape($this->getLocaleValue($this->_title, $locale))
                        , $conn->escape($this->getLocaleValue($this->_seo_titles, $locale))
                        , $conn->escape($this->getLocaleValue($this->_keywords, $locale))
                        , $conn->escape($this->getLocaleValue($this->_description, $locale))
                        , $conn->escape($this->getLocaleValue($this->_publish_date, $locale))
                        , $conn->escape($this->getLocaleValue($this->_removal_date, $locale))
                        , $conn->escape($this->getStatus())
                        , intval($id));
                    $conn->exec($sql);
                    foreach($this->getPlatforms() as $platform) {
                        $content = $this->getPlatformContent($platform, $locale);
                        $type = 'content';
                        // encoding
                        $tmp = json_decode($content[$type], true);
						if(is_array($tmp))
						{
							foreach($tmp as &$val)
							{
								foreach($val as &$v)
								{
									if(gettype($v)=='string')
									{
										$tmp_val = $this->imgPathEncode('temp', $this->_id, $v, $id);
										$v = $tmp_val['content'];
										unset($tmp_val);
									}
								}
							}
						}
                        $encoded_content['content'] = json_encode($tmp);
                        unset($tmp);
                        //$encoded_content = $this->imgPathEncode('temp', $this->_id, $content, $id);

                        $sql = sprintf('REPLACE webpage_locale_contents(domain, webpage_id, locale, major_version, minor_version, visual_version'
                            . ", platform, type, content) VALUES('private', %d, %s, %d, %d, %d, %s, %s, %s)"
                            , $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version, $visual_version
                            , $conn->escape($platform), $conn->escape($type), $conn->escape($encoded_content['content']));
                        $conn->exec($sql);
                    }
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
						. ', webpage_title, seo_title, headline_title, keywords, description, url, status, updated_date, updater_id)'
						. " VALUES ('private', %d, %s, %d, %d, %d, NULL, NULL, NULL, NULL, NULL, NULL, %s, UTC_TIMESTAMP(), %d)"
						, $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version, $visual_version
						, $conn->escape($this->getStatus())
						, intval($id));
						$conn->exec($sql);

                        foreach($this->getPlatforms() as $platform) {
                            $type = 'content';
                            $sql = sprintf('REPLACE webpage_locale_contents(domain, webpage_id, locale, major_version, minor_version, visual_version'
                            . ", platform, type, content) VALUES('private', %d, %s, %d, %d, %d, %s, %s, NULL)"
                            , $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version, $visual_version
                            , $conn->escape($platform), $conn->escape($type));
                            $conn->exec($sql);
                        }
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
					. ', webpage_title, seo_title, headline_title, keywords, description, status, updated_date, updater_id)'
					. ' SELECT "private", webpage_id, %2$s, %3$d, %4$d, visual_version, webpage_title, seo_title, headline_title, keywords, description, status, updated_date, updater_id FROM (SELECT domain, webpage_id, locale, major_version, minor_version, visual_version'
					. ', webpage_title, seo_title, headline_title, keywords, description, status, updated_date, updater_id FROM webpage_locales WHERE webpage_id=%1$d AND locale=%2$s AND domain="private" ORDER BY major_version DESC, minor_version DESC LIMIT 0,1) AS wl'
					, $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version);
					$conn->exec($sql);
				}

                foreach($this->getPlatforms() as $platform) {
                    $sql = sprintf('SELECT * FROM webpage_locale_contents WHERE webpage_id=%1$d AND locale=%2$s AND platform=%3$s AND domain="private" ORDER BY major_version DESC, minor_version DESC LIMIT 0,1'
                            , $this->_id, $conn->escape($locale), $conn->escape($platform));
                    $statement = $conn->query($sql);
                    if($row = $statement->fetch())
                    {
                        // encoding
                        $tmp = json_decode($row['content'], true);
                        foreach($tmp as &$val)
                        {
                            foreach($val as &$v)
                            {
                                if(gettype($v)=='string')
                                {
                                    $tmp_val = $this->imgPathEncode('temp', $this->_id, $v, $id);
                                    $v = $tmp_val['content'];
                                    unset($tmp_val);
                                }
                            }
                        }
                        $encoded_content['content'] = json_encode($tmp);
                        unset($tmp);
                        //$encoded_content = $this->imgPathEncode('temp', $this->_id, $row['content'], $id);
                        $sql = sprintf('REPLACE INTO webpage_locale_contents(domain, webpage_id, locale, major_version, minor_version, visual_version'
                            . ', platform, type, content) VALUES ("private", %1$d, %2$s, %3$d, %4$d, %8$d, %5$s, %6$s, %7$s)'
                            , $this->_id, $conn->escape($locale), $this->_major_version, $this->_minor_version
                            , $conn->escape($platform), $conn->escape($row['type']), $conn->escape($encoded_content['content'])
                            , $row['visual_version']);
                        $conn->exec($sql);
                    }
                }
            }
        }

        if($has_title) {
            $sql = sprintf('DELETE FROM webpage_locales'
                . " WHERE domain = 'private' AND webpage_id = %u"
                . ' AND major_version = %u AND minor_version = %u'
                . ' AND webpage_title IS NULL AND seo_title IS NULL'
                . ' AND headline_title IS NULL AND keywords IS NULL'
                . ' AND description IS NULL AND url IS NULL'
                , $this->_id, $this->_major_version, $this->_minor_version);
            $conn->exec($sql);
        }
    }

    /**
     * Retrieve the data according to known platforms with locale provided
     *
     * @param array  $locales
     * @param string $type
     */
    public function retrieveData($locales = array(), $type = "public") {
        $conn = db::Instance();
        $platforms = $this->getPlatforms();

        if(!is_array($locales)) {
            $locales = array($locales);
        }

        $sql = sprintf("SELECT * FROM webpage_platforms WHERE domain = %s AND webpage_id = %d"
            . ' AND platform IN(%s) AND major_version = %d AND minor_version = %d'
            , $conn->escape($type)
            , $this->getId(), implode(', ', array_map(array($conn, 'escape'), $platforms))
            , $this->getMajorVersion(), $this->getMinorVersion());

        $statement = $conn->query($sql);
        while($row = $statement->fetch()) {
            $platform = $row['platform'];

            $this->_templates[$platform] = $row['template_id'];
            $this->_submenu_shown[$platform] = $row['submenu_shown'];
        }

        $sql = sprintf("SELECT * FROM webpage_locales WHERE domain = %s AND webpage_id = %d"
            . ' AND major_version = %d AND minor_version = %d AND locale IN(%s) AND (domain = \'private\' OR status=\'approved\')'
            , $conn->escape($type)
            , $this->getId()
            , $this->getMajorVersion(), $this->getMinorVersion()
            , implode(', ', array_map(array($conn, 'escape'), $locales))
        );

        $statement = $conn->query($sql);
        while($row = $statement->fetch()) {
            $locale = $row['locale'];
            $this->_title[$locale] = $row['webpage_title'];
            $this->_seo_titles[$locale] = $row['seo_title'];
            $this->_keywords[$locale] = $row['keywords'];
            $this->_description[$locale] = $row['description'];
        }

        $sql = sprintf("SELECT * FROM webpage_locale_contents WHERE domain = %s AND webpage_id = %d"
            . ' AND platform IN(%s) AND major_version = %d AND minor_version = %d AND locale IN(%s)'
            , $conn->escape($type), $this->getId(), implode(', ', array_map(array($conn, 'escape'), $platforms))
            , $this->getMajorVersion(), $this->getMinorVersion()
            , implode(', ', array_map(array($conn, 'escape'), $locales))
        );
        $statement = $conn->query($sql);

        $has_content = false;

        while($row = $statement->fetch()) {
            //if(isset($this->_contents[$row['platform']][$row['locale']]))
            //{
                $this->_contents[$row['platform']][$row['locale']][$row['type']] = $row['content'];
                if($row['content'] !== "")
                    $has_content = true;
            //}
        }

        require_once( dirname(__DIR__) . '/offer_admin/index.php');

        // with not yet started offers for caching purpose
        $offers = offer_admin_module::getWebpageOffers($this->getId(), $type, true);
        $this->_offer_ids = array();

        foreach($offers as $offer) {
            $this->_offer_ids[] = $offer['id'];
        }

        /** @var default_module $module */
        $module = kernel::$module;
        /** @var kernel $kernel */
        $kernel = kernel::getInstance();

        // none of the page and platform has content, use alternate platform content to display on this platform
        if(//$type == "public" &&
            !$has_content
            // not root - root may have no content
            && $module->sitemap->getRoot()->getItem()->getId() != $this->getId()) {

            // build the order
            $order_conds = array();
            $order_conds2 = array();

            foreach(array_keys($module->kernel->dict['SET_webpage_page_types']) as $i => $possible_platform) {
                if(!in_array($possible_platform, $platforms)) {
                    $order_conds[] = sprintf(' WHEN %s THEN %d ', $conn->escape($possible_platform), $i);
                }
            }

            foreach(array_keys($kernel->sets['public_locales']) as $j => $locale_key) {
                $order_conds2[] = sprintf(' WHEN %s THEN %d ', $conn->escape($locale_key), $j);
            }

            $sqls = array();

            if(count($order_conds2) > 0) {
                $order_conds2[] = ' ELSE ' . ($j + 1);
                $sqls[] = sprintf(
                    // from alternate language in same platform
                    '(SELECT "alt_language" AS content_source, %8$s AS display_locale, s.* FROM webpage_locale_contents s JOIN('
                    . ' SELECT locale, platform, webpage_id, major_version, minor_version FROM webpage_locale_contents'
                    . ' WHERE domain = %1$s AND webpage_id = %2$d AND content <> \'\''
                    . ' AND platform IN(%3$s) AND major_version = %4$d AND minor_version = %5$d AND locale NOT IN(%6$s)'
                    . ' ORDER BY CASE locale '
                    . ' %7$s'
                    . ' END ASC'
                    . ' LIMIT 0, 1) AS tb ON(s.webpage_id = tb.webpage_id AND tb.major_version = s.major_version'
                    . ' AND s.minor_version = tb.minor_version AND tb.locale = s.locale AND tb.platform = s.platform)'
                    . ' WHERE s.domain = %1$s)'
                    , $conn->escape($type)
                    , $this->getId(), implode(', ', array_map(array($conn, 'escape'), $platforms))
                    , $this->getMajorVersion(), $this->getMinorVersion()
                    , implode(', ', array_map(array($conn, 'escape'), $locales))
                    , implode("\n", $order_conds2)
                    , $conn->escape($kernel->request['locale'])
                );
            }

            if(count($order_conds) > 0
                //&& $type == "public"
            ) {
                $order_conds[] = ' ELSE ' . ($i + 1);
                $sqls[] = sprintf(
                    // from alternate platform source
                    '(SELECT "alt_platform" AS content_source, s.locale AS display_locale, s.* FROM webpage_locale_contents s'
                    . ' WHERE domain = %1$s AND webpage_id = %2$d AND content <> \'\''
                    . ' AND platform NOT IN(%3$s) AND major_version = %4$d AND minor_version = %5$d '
                    . ' ORDER BY '
                    . ' locale = %6$s DESC, '
                    . ' CASE locale '
                    . ' %8$s'
                    . ' END ASC,'
                    . ' CASE platform '
                    . ' %7$s'
                    . ' END)'
                    , $conn->escape($type)
                    , $this->getId(), implode(', ', array_map(array($conn, 'escape'), $platforms))
                    , $this->getMajorVersion(), $this->getMinorVersion()
                    , implode(', ', array_map(array($conn, 'escape'), $locales))
                    , implode("\n", $order_conds)
                    , implode("\n", $order_conds2)
                );
            }

            if(count($sqls)) {
                foreach($sqls as $sql) {
                    $statement = $conn->query($sql);
                    $dummy_contents = array();

                    $has_content = false;
                    $current_platform = "";
                    while($row = $statement->fetch()) {
                        if($has_content && $current_platform != $row['platform']) {
                            break;
                        }

                        if($row['content'] !== "" && !$has_content) {
                            $has_content = true;
                        }

                        $dummy_contents[$row['locale']][$row['type']] = $row['content'];
                    }

                    if(count($dummy_contents) && $has_content) {
                        // replace the content
                        foreach(array_keys($this->_contents) as $tmp) {
                            $this->_contents[$tmp] = $dummy_contents;
                        }

                        break;
                    }
                }
            }
        }

        $exclude_platforms = array();
        foreach($platforms as $tmp) {
            $exclude_platforms[] = $tmp;
        }

        // get alternate urls for each platform
        $sql = sprintf("SELECT p.* FROM webpage_platforms p WHERE p.domain = %s AND p.webpage_id = %d"
            . ' AND p.platform NOT IN(%s) AND p.major_version = %d AND p.minor_version = %d AND deleted = 0'
            , $conn->escape($type), $this->getId(), implode(', ', array_map(array($conn, 'escape'), $exclude_platforms))
            , $this->getMajorVersion(), $this->getMinorVersion());
        $statement = $conn->query($sql);
        while($row = $statement->fetch()) {
            $this->_alternate_urls[$row['platform']] = $row['path'];
        }

        parent::retrieveData($locales, $type);
    }

    // abstract functions
    /**
     * @return mixed
     */
    public function render() {
    }
}
