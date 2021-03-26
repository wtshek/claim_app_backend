<?php
/**
 * File: tree.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 13?6?28?
 * Time: ??8:20
 * Description:
 */

/**
 * Class treeNode
 * An abstract class to store the tree node.
 * A whole tree should be a set of multiple tree nodes that are
 * linked with its children and parent defined.
 * Ability to have multiple roots
 */
class treeNode {
    /** @var  int $_level */
    protected $_level;
    /** @var  array $_children */
    protected $_children;
    /** @var  bool $_hasChild */
    protected $_hasChild = false;
    /** @var treeNode $_parent */
    protected $_parent;

    /**
     * @param int $level
     */
    public function setLevel($level)
    {
        $this->_level = $level;
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->_level;
    }

    /**
     * @param bool $hasChild
     */
    public function setHasChild($hasChild)
    {
        $this->_hasChild = $hasChild;
    }

    /**
     * @return bool
     */
    public function hasChild()
    {
        return $this->_hasChild || count($this->_children);
    }

    /**
     * @param \treeNode $parent
     */
    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    /**
     * @return \treeNode
     */
    public function getParent()
    {
        return $this->_parent;
    }

    function __construct() {

    }

    /**
     * Prepend the child node to the tree
     *
     * @param pageNode | roleNode $p
     */
    public function PrependChild($p) {
        array_unshift($this->_children, $p);
        $this->_hasChild = true;
        $p->setParent($this);
        $p->setLevel($this->_level+1);
    }

    /**
     * Append the child node to the tree
     *
     * @param pageNode | roleNode $p
     */
    public function AddChild($p) {
        $this->_children[] = $p;
        $this->_hasChild = true;
        $p->setParent($this);
        $p->setLevel($this->_level+1);
    }

    /**
     * Generate html options with tree structure displayed
     *
     * @param bool $html
     * @return array|string
     */
    public function generateOptions($html = false) {
        $output = $html ? "" : array();

        foreach($this->_children as $k => $child) {
            $c = $child->generateOptions($html);

            if($html) {
                $output .= $c;
            } else {
                $output = array_merge($output, $c);
            }
        }

        return $output;
    }

    /**
     * Get the node and see if it has been deleted
     *
     * @param bool $dependent its parent is deleted
     * @return bool
     */
    public function getDeleted($dependent = true)
    {
        if($this->getItem()->getDeleted()) {
            return true;
        }

        if($dependent) {
            $p = $this->getParent();

            if(!is_null($p) && get_class($this) == get_class($p)) {
                return $p->getDeleted($dependent);
            } else {
                //echo get_class($this) . ', ' .  get_class($p) . (is_null($p) ? 'a' : 'b');
                //exit;
            }
        }

        return false;
    }

    /**
     * Get the node and see if it has been set as enabled
     *
     * @param bool $dependent its parent is deleted
     * @return bool
     */
    public function getEnabled($dependent = true)
    {
        if(!$this->getItem()->getEnabled()) {
            return false;
        }

        if($dependent) {
            $p = $this->getParent();

            if(!is_null($p) && get_class($this) == get_class($p)) {
                return $p->getEnabled($dependent);
            }
        }

        return true;
    }

    /**
     * find the node by id
     *
     * @param $id
     * @return treeNode|pageNode|bool
     */
    public function findById($id) {
        if(method_exists($this, 'getItem') && method_exists($this->getItem(), 'getId') && $this->getItem()->getId() == $id) {
            return $this;
        }

        // DFS
        foreach($this->_children as $k => $child) {
            if($item = $child->findById($id)) {
                return $item;
            }
        }

        return false;
    }

    /**
     * Find the page until specified id has reached
     *
     * @param      $id
     * @param null $limit_ids
     * @return array|bool
     */
    public function findUntilId($id, $limit_ids = null, $locale = null) {
        if(is_array($limit_ids) && !in_array($id, $limit_ids)) {
            return false;
        }

        if(method_exists($this, 'getItem') && method_exists($this->getItem(), 'getId') && $this->getItem()->getId() == $id) {
            return $this->getNodeInfo($locale);
        }

        // DFS
        $in_children = false;
        foreach($this->_children as $t => $child) {
            $in_children = $child->findUntilId($id, $limit_ids,$locale);
            if($in_children) {
                break;
            }
        }

        if($in_children) {
            $children = array();
            /** @var treeNode|pageNode|roleNode $child */
            $child = null;
            foreach($this->_children as $k => $child) {
                if($k == $t) {
                    $children[] = $in_children;
                } else {
                    if(!is_array($limit_ids) || in_array($child->getItem()->getId(), $limit_ids) || $child->childrenExists($limit_ids))
                        $children[] = $child->getNodeInfo($locale);
                    /*else {
                        $has_target = $child->findById($id);

                        if(!is_null($has_target) && $has_target) {
                            $children[] = $child->getNodeInfo();
                        }
                    }*/

                }

            }

            return method_exists($this, 'getItem') ? array_merge($this->getNodeInfo($locale), array('children' => $children)) : $children;
        }
        return false;
    }


    /**
     * See if the id exists in its children
     *
     * @param array $ids
     * @return bool
     */
    public function childrenExists($ids) {
        if(!is_array($ids)) {
            $ids = array($ids);
        }

        foreach($ids as $id) {
            if($this->findById($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the node info
     *
     * @return array
     */
    public function getNodeInfo() {
        return array(
            'node' => array(
                'id' => $this->getItem()->getId()
            )
        );
    }


    /**
     * Get the child nodes with level provided
     *
     * @param $level
     * @return array
     */
    public function getChildren($level = -1) {
        $children = array();
        foreach($this->_children as $k => $child) {
            $children[] = $child;
            if($level)
            {
                $_level = $level-1;
                $children = array_merge($children, $child->getChildren($_level));
            }
        }

        return $children;
    }

    /**
     * Convert the tree to an array
     *
     * @return array
     */
    public function toArray() {

        // it is wrapper class only, get its child
        if(get_class($this) == "treeNode") {

            $children = array();
            foreach($this->_children as $k => $child) {
                    $children[] =  $child->toArray();
            }

            return $children;

        } else {

            $item = array(
                'id' => $this->getItem()->getId(),
                'name' => $this->getItem()->getTitle(),
                'enabled' => $this->getEnabled(),
                'deleted' => $this->getDeleted(),
                'hasChild' => $this->hasChild()
            );

            $children = array();
            foreach($this->_children as $k => $child) {
                $children[] = $child->toArray();
            }

            $item['children'] = $children;

            return $item;
        }
    }
}

/**
 * Class pageNode
 * The tree nodes for the website pages, together should form a complete structured website tree.
 */
class pageNode extends treeNode {
    /** @var  staticPage $_item */
    private $_item;
    /** @var  string $default_page_type */
    private $_default_page_type;

    /**
     * Get the order direction of the children
     *
     * @return string
     */
    public function getChildOrderDirection()
    {
        return $this->getItem() ? $this->getItem()->getChildOrderDirection() : null;
    }

    /**
     * Get the field of which the children will perform the ordering
     *
     * @param null $platform
     * @return mixed|null
     */
    public function getChildOrderField($platform = null)
    {
        return $this->getItem() ? $this->getItem()->getChildOrderField($platform) : null;
    }

    /**
     * Set the page
     *
     * @param \staticPage | \webpageLinkPage | \urlLinkPage $page
     */
    public function setPage($page)
    {
        $this->_item = $page;
    }

    /**
     * @return \staticPage | \webpageLinkPage | \urlLinkPage
     */
    public function getPage()
    {
        return $this->_item;
    }

    /**
     * @param \staticPage | \webpageLinkPage | \urlLinkPage $page
     */
    public function setItem($page)
    {
        $this->_item = $page;
    }

    /**
     * @return \staticPage | \webpageLinkPage | \urlLinkPage
     */
    public function getItem()
    {
        $item =& $this->_item;
        return $item;
    }

    /**
     * Get maximum publish date
     *
     * @param string $locale
     * @return int|mixed|string
     */
    protected function getMaxPublishDate($locale) {
        $max_ts = $this->getItem()->getLocalePublishDate($locale, 'timestamp');
        if($p = $this->getParent()) {
            $max_ts = max($max_ts, $p->getItem()->getLocalePublishDate($locale, 'timestamp'));
        }

        return $max_ts;
    }

    /**
     * Get the minimum removal date for that page
     *
     * @param string $locale
     * @return int|mixed|string
     */
    protected function getMinRemovalDate($locale) {
        $min_ts = $this->getItem()->getLocaleRemovalDate($locale, 'timestamp');
        if($p = $this->getParent()) {
            $min_ts = min($min_ts, $p->getItem()->getLocaleRemovalDate($locale, 'timestamp'));
        }

        return $min_ts;
    }

    /**
     * See if the page is available
     *
     * @param string $locale
     * @return bool
     */
    public function available($locale) {
        $maxStartDate = $this->getMaxPublishDate($locale);
        $minEndDate = $this->getMinRemovalDate($locale);

        $now = gmdate('U');

        return $now >= $maxStartDate && $now <= $minEndDate;
    }

    /**
     * See if the page is accessible
     *
     * @param int $role_id
     * @return bool
     */
    public function accessible($role_id = 1) {
        $accessible_roles = $this->getAccessiblePublicRoles();

        // 1 assume to be anonymous role, if anonymous is accessible, everyone should be accessible.
        if(!in_array(1, $accessible_roles)
            && !in_array($role_id, $accessible_roles)) {
            return false;
        }

        /*$p = $this->getParent();
        if($p && !is_null($p)) {
            return $this->getParent()->accessible($role_id);
        }*/

        return true;
    }

    /**
     * Get the public roles which are accessible
     *
     * @return array
     */
    public function getAccessiblePublicRoles()
    {
        $ids = $this->getItem()->getAccessiblePublicRoles();
        if(is_array($ids) && count($ids))
            return $ids;

        /** @var pageNode $p */
        $p = $this->getParent();
        if($p && $p->getLevel() >= 0) {
            return $p->getAccessiblePublicRoles();
        }

        return array();
    }

    /**
     * Constructor
     *
     * @param \staticPage | \webpageLinkPage | \urlLinkPage | \structuredPage $p Page
     * @param $default_page_type
     */
    function __construct($p, $default_page_type) {
        parent::__construct();
        $this->_item = $p;
        $this->_default_page_type = $default_page_type;
        $tmp = array_filter(explode("/", $p->getRelativeUrl($this->_default_page_type)), 'strlen');
        $this->_level = count($tmp);
        $this->_children = array();
    }

    /**
     * reordering of the page
     *
     * @param array $orderedPages
     * @param bool  $test
     */
    public function reOrder(&$orderedPages = array(), $test = false) {
        //echo print_r($this->_children);
        $orderedPages[] = $this->getPage()->getId();
        $orderedPages = array_unique($orderedPages);

        if(count($this->_children) > 0) {
            foreach($this->_children as $i => $pageNode) {
                //if($test)
                    //echo $pageNode->getPlatformOrderIndex();
                if(!in_array($pageNode->getPage()->getId(), $orderedPages))
                    $this->_children[$i]->reOrder($orderedPages);
            }

            if(count($this->_children) > 1)
            {
                $orderstr = preg_replace("#\s#", "", ucwords(preg_replace("#_#", " ", $this->getChildOrderField()))) . ucfirst($this->getChildOrderDirection());
                if($orderstr == '') //set default value
                    $orderstr = 'OrderIndexDesc';
                uasort( $this->_children, array('self', 'sortBy' . $orderstr ));
            }

//            if ( $this->getChildOrderDirection() == 'desc' )
//            {
//                $this->_children = array_reverse( $this->_children, true );
//            }

        }
    }

    public function generateSort($a, $b, $orderBy = "asc") {
        if ($a == $b) {
            return 0;
        }

        return ($orderBy == "desc" && $a < $b) || ($orderBy == "asc" && $a > $b) ? 1 : -1;
    }

    /**
     * get the node info
     *
     * @param string $locale
     * @return array
     */
    public function getNodeInfo($locale = NULL) {
        return array(
            'id' => $this->getItem()->getId(),
            'title' => $this->getItem()->getTitle(null, true),
            'path' => $this->getItem()->getRelativeUrl($this->_default_page_type),
            'started' => $this->getItem()->getLocalePublishDate($locale, 'timestamp') <= gmdate('U'),
            'deleted' => $this->getDeleted(false),
            'hasChild' => $this->hasChild(),
            'status' => $this->getItem()->getStatus()
        );
    }

    /**
     * Make the sort order by index in ascending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByOrderIndexAsc(pageNode $a, pageNode $b) {
        $field = "PlatformOrderIndex";
        return $this->numericSort($a, $b, $field, "asc");
    }

    /**
     * Make the sort order by index in descending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByOrderIndexDesc(pageNode $a, pageNode $b) {
        $field = "PlatformOrderIndex";
        return $this->numericSort($a, $b, $field, "desc");
    }

    /**
     * Make the sort order by alias in ascending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByAliasAsc(pageNode $a, pageNode $b) {
        $field = "Alias";
        return $this->stringSort($a, $b, $field, "asc");
    }

    /**
     * Make the sort order by alias in descending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByAliasDesc(pageNode $a, pageNode $b) {
        $field = "Alias";
        return $this->stringSort($a, $b, $field, "desc");
    }

    /**
     * Make the sort order by webpage title in ascending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByWebpageTitleAsc(pageNode $a, pageNode $b) {
        $field = "Title";
        return $this->stringSort($a, $b, $field, "asc");
    }

    /**
     * Make the sort order by webpage title in descending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByWebpageTitleDesc(pageNode $a, pageNode $b) {
        $field = "Title";
        return $this->stringSort($a, $b, $field, "desc");
    }

    /**
     * Make the sort order by created date in ascending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByCreatedDateAsc(pageNode $a, pageNode $b) {
        $field = "CreatedTimestamp";
        return $this->dateSort($a, $b, $field, "asc");
    }

    /**
     * Make the sort order by created date in descending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByCreatedDateDesc(pageNode $a, pageNode $b) {
        $field = "CreatedTimestamp";
        return $this->dateSort($a, $b, $field, "desc");
    }

    /**
     * Make the sort order by last modification date in ascending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByLastModifiedDateAsc(pageNode $a, pageNode $b) {
        $field = "LastModifiedTimestamp";
        return $this->dateSort($a, $b, $field, "asc");
    }

    /**
     * Make the sort order by last modification date in descending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByLastModifiedDateDesc(pageNode $a, pageNode $b) {
        $field = "LastModifiedTimestamp";
        return $this->dateSort($a, $b, $field, "desc");
    }

    /**
     * Make the sort order by published date in ascending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByPublishedDateDateAsc(pageNode $a, pageNode $b) {
        $field = "PublishedTimestamp";
        return $this->dateSort($a, $b, $field, "asc");
    }

    /**
     * Make the sort order by published date in descending order
     *
     * @param pageNode $a
     * @param pageNode $b
     * @return int
     */
    public function sortByPublishedDateDesc(pageNode $a, pageNode $b) {
        $field = "PublishedTimestamp";
        return $this->dateSort($a, $b, $field, "desc");
    }

    /**
     * Perform the numeric sort
     *
     * @param pageNode $a
     * @param pageNode $b
     * @param          $field
     * @param string   $orderBy
     * @return int
     */
    public function numericSort(pageNode $a, pageNode $b, $field, $orderBy = "asc") {
        $method = 'get' . $field;
        $s = $this->generateSort($a->getPage()->$method(), $b->getPage()->$method(), $orderBy);
        // Use order field value, if not equal
        if($s === 0) {
            return $this->generateSort($a->getPage()->getId(), $b->getPage()->getId(), "asc");
        }

        return $s;
    }

    // they are the same now, just put it here in case they are different
    /**
     * Perform a string sort
     *
     * @param pageNode $a
     * @param pageNode $b
     * @param          $field
     * @param string   $orderBy
     * @return int
     */
    public function stringSort(pageNode $a, pageNode $b, $field, $orderBy = "asc") {
        return $this->numericSort( $a, $b, $field, $orderBy );
    }

    // they are the same now, just put it here in case they are different
    /**
     * Perform a date sort
     *
     * @param pageNode $a
     * @param pageNode $b
     * @param          $field
     * @param string   $orderBy
     * @return int
     */
    public function dateSort(pageNode $a, pageNode $b, $field, $orderBy = "asc") {
        return $this->numericSort( $a, $b, $field, $orderBy );
    }

    /**
     * Clone a node and return as a new one
     *
     * @return pageNode
     */
    public function cloneNode() {
        $node = new pageNode($this->getItem(), $this->_default_page_type);
        $node->_hasChild = $this->_hasChild;
        $node->_level = $this->_level;
        $node->_children = array();

        return $node;
    }
}


/**
 * Class roleNode
 * The role nodes for the admin and public roles in cms, together should
 * form a complete structured role tree (public role / admin role)
 */
class roleNode extends treeNode {
    /** @var  role $_item */
    private $_item;

    /**
     * @param \role $role
     */
    public function setItem($role)
    {
        $this->_item = $role;
    }

    /**
     * @return \role
     */
    public function getItem()
    {
        return $this->_item;
    }

    /**
     * See if the node is enabled
     *
     * @param bool $dependent
     * @return bool
     */
    public function getEnabled($dependent = true)
    {
        if(!$this->getItem()->getEnabled()) {
            return false;
        }

        if($dependent) {
            $p = $this->getParent();

            if(!is_null($p) && get_class($this) == get_class($p)) {
                return $p->getEnabled($dependent);
            }
        }

        return true;
    }

    /**
     * Check if the node has appropriate rights for that module
     *
     * @param       $module
     * @param array $rights
     * @return bool
     */
    public function hasRights($module, $rights = array()) {
        if(!is_array($rights)) {
            $rights = array($rights);
        }

        $hasRight = $this->getItem()->hasRights($module, $rights);

        if(!$hasRight)
            return FALSE;

        $p = $this->getParent();
        if(!is_null($p) && get_class($this) == get_class($p)) {
            return $p->hasRights($module, $rights);
        }

        return $hasRight;
    }

    /**
     * @param role $r Role
     *
     */
    function __construct($r) {
        parent::__construct();
        $this->_item = $r;
        $this->_level = 0;
        $this->_children = array();
    }

    /**
     * Generate html options with tree structure displayed
     *
     * @param bool   $html
     * @param string $prefix
     * @param bool   $lastNode
     * @return array|string
     */
    public function generateOptions($html = false, $prefix = "", $lastNode = false) {
        $output = $html ? "" : array();

        if($this->_level > -1) {
            $text = sprintf("%s%s%s"
                , $prefix
                , $this->_level > 0 ? ($lastNode ? "+ " : "+ ") : ""
                , htmlspecialchars($this->getItem()->getName()));

            if($html) {
                $output .= sprintf("<option value=\"%s\">%s</option>\n"
                                    , htmlspecialchars($this->getItem()->getId())
                                    , $text);
            } else {
                $output["_" . $this->getItem()->getId()] = $text;
            }
        }

        foreach($this->_children as $k => $child) {
            $append = $child->generateOptions($html, $prefix . ($this->_level > 0 ? ($lastNode ? "¦" : " ") : ""), $k+1 != count($this->_children));

            if($html) {
                $output .= $append;
            } else {
                $output = array_merge($output, $append);
            }
        }

        return $output;
    }

    /**
     * Return as an array the node info
     *
     * @return array
     */
    public function getNodeInfo() {
        return array(
                'id' => $this->getItem()->getId(),
                'enabled' => $this->getEnabled(),
                'name' => $this->getItem()->getName(),
                'hasChild' => $this->hasChild()
        );
    }
}