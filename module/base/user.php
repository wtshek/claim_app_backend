<?php
/**
 * File: user.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 10/07/2013 10:17
 * Description: an user object
 */

/**
 * Class user
 * An abstract class for the extension of different kind of users in cms
 */
abstract class user {
    /** @var int $_id */
    protected $_id;
    /** @var string property_id */
    protected $_property_id;
    /** @var string salutation */
    protected $_salutation;
    /** @var string first_name */
    protected $_first_name;
    /** @var string last_name */
    protected $_last_name;
    /** @var string username */
    protected $_username;
    /** @var string $_email */
    protected $_email;
    /** @var role $_role */
    protected $_role;
    /** @var bool enabled */
    protected $_enabled = true;
    /** @var string token */
    protected $_token;

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
     * @param string $property_id
     */
    public function setPropertyId($property_id)
    {
        $this->_property_id = $property_id;
    }

    /**
     * @return string
     */
    public function getPropertyId()
    {
        return $this->_property_id;
    }

    /**
     * @param string $salutation
     */
    public function setSalutation($salutation)
    {
        $this->_salutation = $salutation;
    }

    /**
     * @return string
     */
    public function getSalutation()
    {
        return $this->_salutation;
    }

    /**
     * @param string $first_name
     */
    public function setFirstName($first_name)
    {
        $this->_first_name = $first_name;
    }

    /**
     * @return string
     */
    public function getFirstName()
    {
        return $this->_first_name;
    }

    /**
     * @param string $last_name
     */
    public function setLastName($last_name)
    {
        $this->_last_name = $last_name;
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->_last_name;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->_username = $username;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->_username;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->_email = $email;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->_email;
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
     * @param \role $role
     */
    public function setRole($role)
    {
        $this->_role = $role;
    }

    /**
     * @return \role
     */
    public function getRole()
    {
        return $this->_role;
    }

    /**
     * @param string $token
     */
    public function setToken($token)
    {
        $this->_token = $token;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->_token;
    }

    function __construct() {
    }

    /**
     * set the data according to the resources provided
     *
     * @param array $data
     */
    public function setData($data = array()) {
        foreach($data as $name => $value) {
            // data to be ignored from the object (if specific method not exists)
            $methodName = "set" . preg_replace(@"#\s#i", "", ucwords(preg_replace("#_#", " ", $name)));
            if(method_exists($this,$methodName) && $value !== "") {
                $this->$methodName($value);
            }
        }
    }
}


/**
 * Class adminUser
 * The admin users who could login to administration panel
 */
class adminUser extends user {
    /** @var string $_preferred_locale */
    protected $_preferred_locale;

    /**
     * @param string $locale
     */
    public function setPreferredLocale($locale)
    {
        $this->_preferred_locale = $locale;
    }

    /**
     * @return string
     */
    public function getPreferredLocale()
    {
        return $this->_preferred_locale;
    }
	
    /**
     * Return an array of accessible locales of the user
     * 
     * @param 
     * @return array
     */
    function getAccessibleLocales(){
        $accessible_locales = array();
        
        $conn = db::Instance();

        $sql = sprintf('SELECT r.locale FROM user_locale_rights AS r'
            . " JOIN locales AS l ON (r.locale = l.alias AND l.site = 'public_site' AND l.enabled = 1)"
            . ' WHERE r.user_id=%d ORDER BY r.locale ASC', $this->getId());
        $rows = $conn->getAll($sql);
        
        foreach($rows as $row) {
            $accessible_locales[] = $row['locale'];
        }
        
        return $accessible_locales;
    }
    
    /**
     * Check whether the user can access all languages, if so return TRUE; else return FALSE
     * 
     * @param 
     * @return bool
     */
    function isGlobalUser(){
        $kernel = kernel::getInstance();
        return count($kernel->sets['public_locales']) == count($this->getAccessibleLocales());
    }

    /**
     * See if the admin user has right with module and related rights provided
     *
     * @param $module
     * @param $rights
     * @return bool
     */
    function hasRights($module, $rights) {
        if(!$this->getEnabled() || is_null($this->getRole()) || !$this->getRole()->getEnabled())
            return false;

        return $this->getRole()->hasRights($module, $rights);
    }

    /**
     * See if the admin user has right with module and related rights provided
     * and throw exceptions if any of it does not match
     *
     * @param $module_name
     * @param $rights
     * @return bool
     * @throws privilegeException
     * @throws loginException
     */
    function checkRights($module_name, $rights) {
        $kernel = kernel::getInstance();
        $ref_module = kernel::$module;

        // Check authentication
        //if ( !array_key_exists('id', kernel::$module->user)  )
        if(!($ref_module->user->getId()))
        {
            throw new loginException($kernel->dict['MESSAGE_login_to_continue'], null, "{$kernel->sets['paths']['app_from_doc']}/admin/{$kernel->request['locale']}/"
                . '?redirect_url=' . urlencode(array_ifnull($_SERVER, 'REQUEST_URI', "{$kernel->sets['paths']['mod_from_doc']}/")));
            return TRUE;
        }

        if(!$this->hasRights($module_name, $rights))
            throw new privilegeException('insufficient_rights');
    }
}


/**
 * Class publicUser
 * The users who login to public website
 */
class publicUser extends user {
    /** @var int $_contact_id */
    protected $_contact_id;

    /** @var array $_profile */
    protected $_profile;

    /**
     * @param int $contact_id
     */
    public function setContactId($contact_id)
    {
        $this->_contact_id = $contact_id;
    }

    /**
     * @return int
     */
    public function getContactId()
    {
        return $this->_contact_id;
    }

    /**
     * @param array $profile
     */
    public function setProfile($profile)
    {
        $this->_profile = $profile;
    }

    /**
     * @return array
     */
    public function getProfile()
    {
        return $this->_profile;
    }

    /**
     * @return mixed
     */
    public function getProfileField( $field )
    {
        return array_ifnull( $this->_profile, $field, NULL );
    }

    public function __construct() {
        $this->setId(0);
        $this->setEnabled(true);
        $this->setToken(null);

        $this->_role = new publicRole();
    }
}
