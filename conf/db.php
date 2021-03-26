<?php
require __DIR__."/config.php";

class DB{
    private $db;
    public function __construct(){
        global $CONF;
        list('db_host'=>$host, 'db_schema'=>$db, 'db_user' => $user, 'db_pass' => $pass) = $CONF;
        // $this->db = new PDO('mysql:host='. $host .";dbname=". $db, $user, $pass);
        $this->db = new PDO('mysql:host='. $host .";dbname=". $db, 'root', '');
        $this->db->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
        $this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $this->db->exec( 'SET CHARACTER SET utf8mb4' );
        $this->db->exec( "SET NAMES 'utf8mb4'" );
    }

    public function getConnection(){
        return $this->db;
    }
}
?>