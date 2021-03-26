<?php
/**
 * File: breadcrumb.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 10/07/2013 15:34
 * Description: The idea of having throws and catch is to have a centralized place to handle all exceptions (including
 *              logout, permissions, sql or server errors) in a centralized location so that it is not necessary to have
 *              so many duplicating codes in handling errors in each method. By having different classes to indicate
 *              the exception, kernel could understand what and how to handle when an error occurred and proceeding for
 *              an output.
 */

 /**
 * Class generalException
 * Abstract class for the exceptions in cms
 */
class generalException extends Exception {
    /**
     * @var string
     */
    public $output_type;
    /**
     * @var null
     */
    public $redirect;
    /**
     * @var array
     */
    public $error;

    public $debug;

    public $status_code = 500;

    /**
     * @param string    $message
     * @param string    $output_type
     * @param null      $error
     * @param null      $redirect
     * @param int       $code
     * @param Exception $previous
     * @param bool      $debug
     */
    public function __construct($message, $output_type = "html", $error = NULL, $redirect = NULL, $code = 0, Exception $previous = null, $debug = true) {
        // some code
        $this->output_type = $output_type == "json" ? "json" : "html";
        $this->redirect = !is_null($redirect) && $redirect != '' ? $redirect : NULL;
        $this->error = array(
            'error_code' => isset($error['error_code']) ? $error['error_code'] : NULL,
            'error_field' => isset($error['error_field']) ? $error['error_field'] : (isset($this->field_name) ? $this->field_name : NULL),
            'error_text' => isset($error['error_text']) ? $error['error_text'] : NULL
        );

        $this->debug = $debug;

        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
};

/**
 * Class sqlException
 * SQL errors will be stored in this object type
 */
class sqlException extends generalException {

    public function __construct($message, $error = NULL, $redirect = NULL, $code = 0, Exception $previous = null) {
        parent::__construct($message, array_ifnull($_REQUEST, 'ajax', 0) ? "json" : "html", $error, $redirect, $code = 0, $previous);
    }
};

/**
 * Class fieldException
 * Errors related to field data received from user (usually related to form submission)
 */
class fieldException extends generalException {
    /**
     * @var null
     */
    public $field_name;

    /**
     * @param string    $message
     * @param null      $field_name
     * @param null      $error
     * @param null      $redirect
     * @param int       $code
     * @param Exception $previous
     */
    public function __construct($message, $field_name = NULL, $error = NULL, $redirect = NULL, $code = 0, Exception $previous = null) {
        if(!is_null($field_name)) {
            $this->field_name = $field_name;
        }

        parent::__construct($message, "html", $error, $redirect, $code = 0, $previous);
    }
};


/**
 * Class fieldsException
 * Errors related to field data received from user (usually related to form submission)
 */
class fieldsException extends generalException {
    public $errors;

    /**
     * @param array    $array
     * @param null      $error
     * @param null      $redirect
     * @param int       $code
     * @param Exception $previous
     */
    public function __construct($array = array(), $error = NULL, $redirect = NULL, $code = 0, Exception $previous = null) {
        $this->errors = $array;

        $tmp = array();
        foreach($array as $ary2) {
            if(is_array($ary2))
                $tmp = array_merge($tmp, $ary2);
        }
        parent::__construct(implode("\n", $tmp), array_ifnull($_REQUEST, 'ajax', 0) ? "json" : "html", $error, $redirect, $code = 0, $previous);
    }
};

/**
 * Class fileException
 * File Exception (not used): reserved for future use
 */
class fileException extends generalException {};

/**
 * Class dataException
 * Errors related to the data in the object / process which lead to not able to proceed further (not used): reserved for future use
 */
class dataException extends generalException {};

/**
 * Class recordException
 */
class recordException extends generalException {
    public function __construct($message = "", $output_type = "html", $error = NULL, $redirect = NULL, $code = 0, Exception $previous = null, $debug = true) {
        if($message != "" && is_null($error)) {
            $error['error_code'] = $message;
        }

        parent::__construct($message, $output_type, $error, $redirect, $code, $previous, $debug);
    }
};

/**
 * Class loginException
 * Errors related to the data in the object / process which lead to not able to proceed further (not used): reserved for future use
 */
class loginException extends generalException {
    /**
     * @param string    $message
     * @param null      $error
     * @param null      $redirect
     * @param int       $code
     * @param Exception $previous
     */
    public function __construct($message, $error = NULL, $redirect = NULL, $code = 0, Exception $previous = null) {
        parent::__construct($message, array_ifnull($_REQUEST, 'ajax', 0) ? "json" : "html", $error, $redirect, $code = 0, $previous);
    }
};

/**
 * Class privilegeException
 * Errors occurred and could not proceed further on because the user is regarded has no right to do so.
 */
class privilegeException extends generalException {
    /**
     * @param string    $message
     * @param array     $privileges
     * @param null      $error
     * @param null      $redirect
     * @param int       $code
     * @param Exception $previous
     */
    public function __construct($message, $privileges = array(), $error = NULL, $redirect = NULL, $code = 0, Exception $previous = null) {
        parent::__construct($message, array_ifnull($_REQUEST, 'ajax', 0) ? "json" : "html", $error, $redirect, $code = 0, $previous);
    }
};

/**
 * Class statusException
 * Error with status code to indicate the type of error return to user
 */
class statusException extends Exception {

    public $status_code = 200;

    public function __construct($status = 500) {

        // make all types of error other than 404 to be server internal error
        if(!in_array($status, array(404, 403, 401, 400)))
            $status = 500;

        $this->status_code = $status;


        // make sure everything is assigned properly
        parent::__construct($status, $status);
    }
}

/**
 * Class requestException
 * The exception occured during the the request
 */
class requestException extends generalException {
    /**
     * @param string    $message
     * @param null      $redirect
     * @param int       $code
     * @param Exception $previous
     */
    public function __construct($message, $redirect = NULL, $code = 0, Exception $previous = null) {
        parent::__construct($message, array_ifnull($_REQUEST, 'ajax', 0) ? "json" : "html", NULL, $redirect, $code = 0, $previous);
    }
};

?>