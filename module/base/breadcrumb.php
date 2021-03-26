<?php
/**
 * File: breadcrumb.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 10/07/2013 15:34
 * Description: 
 */

/**
 * Class breadcrumb
 * A link list for the breadcrumb structure of where
 * the user is in website currently (for frontend and backend admin)
 */
class breadcrumb extends SplDoublyLinkedList {
    /**
     * Push a new node into the breadcrumb list
     *
     * @param breadcrumbNode $node
     */
    public function push($node) {
        parent::push($node);
    }

    /**
     * Unshift the breadcrumb node from the list
     *
     * @param breadcrumbNode $node
     */
    public function unshift($node) {
        return parent::unshift($node);
    }

    /**
     * Decode the current link list object into html with a separator provided
     *
     * @param string $separator
     * @param bool   $ignore_empty
     * @return string
     */
    public function toHtml($separator = "&middot;", $ignore_empty = true) {
        $html = "<ul>";
        $this->rewind();

        /** @var breadcrumbNode $n */
        $n = $this->current();
        while(!is_null($n)) {
            $name = $n->getName();
            $url = $n->getUrl();
            $sub_html = "";

            if(($name && !is_null($name)) || !$ignore_empty) {
                $sub_html = sprintf("<li>%s%s%s</li>"
                            , $url ? '<a href="' . htmlspecialchars($url) . '">' : ''
                            , htmlspecialchars($name)
                            , $url ? '</a>' : '');
            }

            $this->next();
            $n = $this->current();

            if(!is_null($n)) {
                if($sub_html)
                    $sub_html .= "<li>" . $separator . "</li>";
            }

            $html .= $sub_html;
        }

        $html .= "</ul>";

        return $html;
    }

    /**
     * Convert the current breadcrumb list into single line title
     *
     * @param string $separator
     * @param bool   $ignore_empty
     * @return string
     */
    public function toTitle($separator = " | ", $ignore_empty = true) {
        $html = "";
        $this->rewind();

        $l = array($this->top());

        /** @var breadcrumbNode $n */
        $n = null;

        if($this->count() > 1) {
            $l[] = $this->bottom();
        }

        $count = count($l);

        foreach($l as $i => $n) {
            $name = $n->getName();
            $sub_html = "";

            if(($name && !is_null($name)) || !$ignore_empty) {
                if($i && $count > $i) {
                    $sub_html = $separator;
                }

                $sub_html .= htmlspecialchars($name);
            }

            $html .= $sub_html;
        }

        return $html;
    }

}

/**
 * Class breadcrumbNode
 * Link list node object for the breadcrumb information.
 */
class breadcrumbNode {
    /** @var string $_name */
    protected $_name;
    /** @var string $_url */
    protected $_url;

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->_name;
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

    function __construct($name, $url) {
        $this->setName($name);
        $this->setUrl($url);
    }
}