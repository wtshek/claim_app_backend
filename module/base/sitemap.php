<?php
/**
 * File: sitemap.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 13年6月21日
 * Time: 上午11:38
 * Description: Sitemap class
 */

class sitemap {
    /** @var array $_hash_table */
    private $_hash_table;
    /** @var pageNode $_hash_table */
    private $_root;
    private $_type;

    /**
     * @param \pageNode $root
     */
    public function setRoot(pageNode &$root)
    {
        $this->_root =& $root;
    }

    /**
     * @return \pageNode
     */
    public function getRoot()
    {
        if(is_null($this->_root)) {
            return false;
        }
        return $this->_root;
    }

    function __construct($type) {
        $this->_hash_table = array();
        $this->_type = $type;
    }

    /**
     * Add a page node into the sitemap to the appropriate position automatically
     *
     * @param pageNode $p
     * @return bool
     */
    public function add(pageNode &$p) {

        /** @var staticPage | urlLinkPage | webpageLinkPage $sp */
        $sp = $p->getPage();

        if(is_null($sp)) {
            return false;
        }

        if(count($this->_hash_table) == 0) {
            $this->setRoot($p);
        }

        $this->_hash_table[md5($sp->getRelativeUrl($this->_type))] =& $p;

        // see if parent exists
        $parent = preg_replace(@"#^(.*\/)[^\/]+\/?$#i", "\\1", $sp->getRelativeUrl($this->_type));
        //echo md5($parent) . " ----- " . $sp->getRelativeUrl() . "------" . $parent . "<br />";

        if(isset($this->_hash_table[md5($parent)]) && $parent != $sp->getRelativeUrl($this->_type)) {
            $this->_hash_table[md5($parent)]->AddChild($p);
        }
        return true;
    }


    /**
     * find the page base on the relative url from root page
     *
     * @param string $url
     * @return bool | pageNode
     */
    public function findPage(string $url) {
        if(isset($this->_hash_table[md5($url)])) {
            return $this->_hash_table[md5($url)];
        }

        return false;
    }

    /**
     * Copy the structure of the node provided (including the position)
     *
     * @param pageNode $node
     * @param bool     $same_level_struct
     */
    public function copyNodeStruct(pageNode $node, $same_level_struct = true) {
        $toAdd = array();
        while(!is_null($node) && !($baseNode = $this->getRoot()->findById($node->getItem()->getId()))) {
            array_unshift($toAdd, $node);
            $node = $node->getParent();
        }

        $nextBase = $baseNode;

        /** @var pageNode $tmpNode */
        $tmpNode = null;

        foreach($toAdd as $i => $tmpNode) {

            if($same_level_struct) {
                /** @var pageNode $parent */
                $parent = $tmpNode->getParent();

                if(!is_null($parent)) {
                    $children = $parent->getChildren(0);
                }
            } else {
                $children = array($tmpNode);
            }

            if(count($children)) {
                /** @var pageNode $child */
                $child = null;
                foreach($children as $child) {
                    $p = $child->cloneNode();
                    if($p->getItem()->getId() == $tmpNode->getItem()->getId())
                        $nextBase = $p;

                    if(!$baseNode) {
                        $this->getRoot()->AddChild($p);
                        $baseNode = $p;
                    } else {
                        $baseNode->AddChild($p);
                    }
                }

                //if($p->getItem()->getId() == $tmpNode->getItem()->getId()) {
                //    $baseNode = $p;
                //}

                $baseNode = $nextBase;
            }

            unset($tmpNode);
        }

    }


    /**
     * Count the total number of pages
     *
     * @return int
     */
    public function countPages() {
        return count($this->_hash_table);
    }

}
?>