<?php
/**
 * File: role.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 01/11/2013 11:28
 * Description:
 */

/**
 * Class role
 * To store and retrieve the structure and page data of the site.
 */
abstract class role {
    /** @var roleRights $role_rights */
    protected $role_rights;
    protected $webpage_rights = array();
    protected $name;
    protected $id;
    protected $_type;
    protected $is_root = false;
    protected $_enabled = true;
    protected $_deleted = false;

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = intval($id);
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setTitle($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->name;
    }

    /**
     * @param boolean $is_root
     */
    public function setIsRoot($is_root)
    {
        $this->is_root = $is_root;
    }

    /**
     * @return boolean
     */
    public function getIsRoot()
    {
        return $this->is_root;
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



    function __construct() {
        $this->_type = get_class($this);
    }

}

/**
 * Class adminRole
 * A class to store the data of the admin role of the user in administration panel
 */
class adminRole extends role {
    /**
     * Check if the current admin role has the right or not
     *
     * @param       $module
     * @param array $rights
     * @return bool
     */
    function hasRights($module, $rights = array(Right::ACCESS)) {
        if(is_null($this->role_rights)) {
            $this->getRights();
        }

        return $this->role_rights->hasRights($module, $rights);
    }

    /**
     * Add specific right to admin role
     *
     * @param $entity
     * @param $right
     */
    function addRight($entity, $right) {
        if(is_null($this->role_rights)) {
            $this->role_rights = new roleRights();
        }

        $this->role_rights->addRight($entity, $right);
    }

    /**
     * Get the rights of this role
     *
     * @return roleRights
     */
    function getRights() {
        if(is_null($this->role_rights)) {
            $conn = db::Instance();

            $this->role_rights = new roleRights();
            $sql = sprintf('SELECT rr.* FROM roles r JOIN role_rights rr ON(r.id = rr.role_id) WHERE r.id = %d'
                . ' ORDER BY rr.entity, rr.`right` ASC', $this->getId());
            $rows = $conn->getAll($sql);

            foreach($rows as $row) {
                $this->role_rights->addRight($row['entity'], $row['right']);
            }
        }

        return $this->role_rights;
    }
}

/**
 * Class publicRole
 * A class to store the data of the public role of the user in public site
 */
class publicRole extends role {
    public function __construct() {
        parent::__construct();

        $this->setId(1); // anonymous role
    }
}