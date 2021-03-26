<?php


/**
 * enum class for role right
 */
class Right {
    const ACCESS = 0;
    const VIEW = 1;
    const CREATE = 2;
    const EDIT = 3;
    const APPROVE = 4;
    const PUBLISH = 5;
    const EXPORT = 6;
}


/**
 * Class roleRights
 * Store and process the permission information relating to a specific role
 */
class roleRights {
    private $_rights;

    // serve as an ACL if right of its dependencies revoke, the right will be revoked too
    static private $right_dependencies = array(
        Right::ACCESS => array(),
        Right::VIEW => array(Right::ACCESS),
        Right::CREATE => array(Right::ACCESS, Right::VIEW),
        Right::EDIT => array(Right::ACCESS, Right::VIEW),
        Right::APPROVE => array(/*Right::CREATE,*/ Right::EDIT),
        Right::PUBLISH => array(Right::APPROVE),
        Right::EXPORT => array(Right::ACCESS, Right::VIEW)
    );
    
    static private $group_right_dependencies = array(
        Right::VIEW => array(Right::CREATE, Right::EDIT),
        Right::CREATE => array(Right::VIEW, Right::EDIT),
        Right::EDIT => array(Right::VIEW, Right::CREATE)
    );

    /**
     * Get the rights of specific module
     *
     * @param $module
     * @return array
     */
    public function getModuleRights($module) {
        if(isset($this->_rights[$module])) {
            return $this->_rights[$module];
        }

        return array();
    }

    /**
     * Set the right of specific module
     *
     * @param $module
     * @param $array
     */
    public function setModuleRights($module, $array) {
        $this->_rights[$module] = $array;
    }

    // should be open for array or just Right object
    /**
     * See if the role has specified rights for that module
     *
     * @param $module
     * @param $r
     * @return bool
     */
    public function hasRights($module, $r) {

        $rights_required = roleRights::getRequiredRights($r);

        foreach($rights_required as $rid) {
            if(array_search($rid, $this->getModuleRights($module)) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get required rights
     *
     * @param $rids
     * @return array
     */
    public static function getRequiredRights($rids){
        if(!is_array($rids)) {
            $rids = array($rids);
        }
        $rights_required = $rids;
        $rights_checked = array();

		$right_diff = array_diff($rights_required, $rights_checked);
		$right = array_shift($right_diff);
        while(!is_null($right)) {
            $right_diff = array_diff($rights_required, $rights_checked);
			$right = array_shift($right_diff);
			if(array_key_exists($right, roleRights::$right_dependencies)) {
                $rights_required = array_unique(array_merge(array_merge(roleRights::$right_dependencies[$right]), $rights_required));
            }
            $rights_checked[] = $right;
			$right_diff = array_diff($rights_required, $rights_checked);
			$right = array_shift($right_diff);
        }

        return array_values($rights_required);
    }

    // get all rights which depneds on such right
    /**
     * Static function to get the rights dependent to the right provided
     *
     * @param $rids
     * @return array
     */
    public static function getDependentRights($rids){
        if(!is_array($rids)) {
            $rids = array($rids);
        }
        $rights_dependent = $rids;
        $rights_checked = array();

        // try not to use recursion because there are no multiple objects in it
		$right_diff = array_diff($rights_dependent, $rights_checked);
		$right = array_shift($right_diff);
        while(!is_null($right)) {
            $right_diff = array_diff($rights_dependent, $rights_checked);
			$right = array_shift($right_diff);
			
			if(array_key_exists($right, roleRights::$right_dependencies)) {
                foreach(roleRights::$right_dependencies as $pivot => $dp) {
                    if(array_search($right, $dp) !== false) {
                        array_unshift($rights_dependent, $pivot);
                    }
                }
                $rights_dependent = array_unique($rights_dependent);
            }
            $rights_checked[] = $right;
			
			$right_diff = array_diff($rights_dependent, $rights_checked);
			$right = array_shift($right_diff);
        }

        return array_values($rights_dependent);
    }
    
    // get all rights which depneds on such right grouply -- view, edit, create
    /**
     * Static function to get the grouply rights dependent to the right provided
     *
     * @param $rids
     * @return array
     */
    public static function getGroupDependentRights($rids){
        if(!is_array($rids)) {
            $rids = array($rids);
        }
        $group_rights_dependent = $rids;
        $rights_checked = array();

        // try not to use recursion because there are no multiple objects in it
		$right_diff = array_diff($group_rights_dependent, $rights_checked);
		$right = array_shift($right_diff);
        while(!is_null($right)) {
            $right_diff = array_diff($group_rights_dependent, $rights_checked);
			$right = array_shift($right_diff);
			if(array_key_exists($right, roleRights::$group_right_dependencies)) {
                foreach(roleRights::$group_right_dependencies as $pivot => $dp) {
                    if(array_search($right, $dp) !== false) {
                        array_unshift($group_rights_dependent, $pivot);
                    }
                }
                $group_rights_dependent = array_unique($group_rights_dependent);
            }
            $rights_checked[] = $right;
			
			$right_diff = array_diff($group_rights_dependent, $rights_checked);
			$right = array_shift($right_diff);
        }

        return array_values($group_rights_dependent);
    }

    /**
     * Add extra rights to the role
     *
     * @param $module
     * @param $r
     * @return bool
     */
    public function addRight($module, $r) {
        if(array_key_exists($r, roleRights::$right_dependencies) && !$this->hasRights($module, $r)){
            $rights_required = $this->getRequiredRights($r);

            $this->setModuleRights($module, array_values(array_unique(array_merge($this->getModuleRights($module), $rights_required))));
        }

        return true;
    }

    /**
     * Revoke specific right from module
     *
     * @param $module
     * @param $r
     * @return bool
     */
    public function revokeRight($module, $r) {
        $rights = $this->getModuleRights($module);

        if(array_key_exists($r, roleRights::$right_dependencies) && $this->hasRights($module, $r)){
            $rights_to_delete = roleRights::getDependentRights($r);
            foreach($rights_to_delete as $dp) {
                if(($pivot = array_search($dp, $rights)) !== false) {
                    unset($rights[$pivot]);
                }
            }
        }

        $this->setModuleRights($module, array_values($rights));

        return true;
    }

    /**
     * Get all rights
     *
     * @return mixed
     */
    public function getAllRights() {
        return $this->_rights;
    }
}