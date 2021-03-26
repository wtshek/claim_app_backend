<?php
/**
 * File: snippet.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 06/11/2013 12:03
 * Description:
 */


interface iSnippet {
    public function getData();
    public function getVariables();
    public function decodeFromXml($xml); // decode snippet plain content to snippet object with variables
    public function output();
}


/**
 * Class snippet
 * An abstract class for the snippets
 */
abstract class snippet {
    protected $_id;
    protected $_name;
    protected $_alias;
    protected $_description;
    protected $_deleted = FALSE;
    protected $_vars;

    protected $_sets = array();

    private static $module = null;
    private static $kernel = null;

    function __construct($info) {

        $info_vars = array('id', 'snippet_name', 'alias', 'description', 'content', 'deleted');

        foreach($info_vars as $info_var) {
            if(isset($info[$info_var])) {
                $val = $info[$info_var];
                switch($info_var) {
                    case 'id':
                        $this->setId($val);
                        break;
                    case 'snippet_name':
                        $this->setName($val);
                        break;
                    default:
                        $n = 'set' . ucwords(preg_replace('#_#', ' ', $info_var));
                        $this->$n($val);
                        break;
                }
            }
        }
    }

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
     * @param boolean $deleted
     */
    public function setDeleted($deleted)
    {
        $this->_deleted = (bool)$deleted;
    }

    /**
     * @return boolean
     */
    public function getDeleted()
    {
        return $this->_deleted;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->_description;
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
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @return array
     */
    public function getSets()
    {
        return $this->_sets;
    }



    public function getVar($name) {
        if(isset($this->_vars[$name]))
            return $this->_vars[$name];
        else
            return false;
    }


    protected static function getModule() {
        if (self::$module === null) self::$module = kernel::$module;
        return self::$module;
    }

    protected static function getKernel() {
        if (self::$kernel === null) self::$kernel = kernel::getInstance();
        return self::$kernel;
    }

    /**
     * Decode the xml in string to xml object in php
     *
     * @param $xml
     */
    public function decodeFromXml($xml) {
        if(isset($xml->params)) {
            /** @var SimpleXMLElement $param */
            $param = null;
            foreach($xml->params->children() as $param) {
                $name = (string) $param['name'];

                if(isset($this->_vars[$name])) {
                    /** @var snippetVar $var */
                    $var = $this->_vars[$name];

                    foreach($param->xpath('value') as $val) {
                        $var->addVal($val);

                        if(!$var->getMultiple()) // ignore other children if this is not a multiple value
                            break;
                    }
                }
            }
        }
    }

    /**
     * Get the variables
     *
     * @return array|bool
     */
    public function getVariables() {
        if(count($this->_vars)) {
            return array_keys($this->_vars);
        }

        return false;
    }

}


/**
 * Class snippetVar
 * Store the data of the snippet attributes for retrieval
 */
class snippetVar {
    protected $_name;
    protected $_label;
    protected $_type;
    protected $_multiple = false;
    protected $_val = "";
    protected $_val_set = false;

    /**
     * @param boolean $multiple
     */
    public function setMultiple($multiple)
    {
        $this->_multiple = $multiple;
    }

    /**
     * @return boolean
     */
    public function getMultiple()
    {
        return $this->_multiple;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @param string|array $val
     */
    public function setVal($val)
    {
        $this->_val = $val;
    }

    /**
     * @return string
     */
    public function getVal()
    {
        return $this->_val;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label)
    {
        $this->_label = $label;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        if(!is_null($this->_label))
            return $this->_label;
        else
            return $this->_name;
    }

    public function addVal($val) {
        if(is_array($val)) {
            $this->addArrayVal($val);
        } else {
            $this->addSingleVal($val);
        }
        $this->_val_set = true;
    }

    protected function addArrayVal($val) {
        if($this->getMultiple()) {
            if(!$this->_val_set)
                $this->_val = array();
            $this->_val = array_merge($val);
        } else {
            $this->_val = end($val);
        }
    }

    protected function addSingleVal($val) {
        if(!is_null($val))
            $val = (string) $val;
        if($this->getMultiple()) {
            if(!$this->_val_set)
                $this->_val = array();
            $this->_val[] = $val;
        } else {
            $this->setVal($val);
        }
    }

    function __construct($name, $type = "string", $multiple = false, $default_value = null, $display_name = null) {
        $this->setName($name);
        $this->setType($type);
        $this->setMultiple((bool)$multiple);

        if(!is_null($display_name)) {
            $this->setLabel($display_name);
        }

        if($this->getMultiple()) {
            $this->_val = array('');
        }
    }
}