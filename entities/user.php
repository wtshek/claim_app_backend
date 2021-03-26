<?php
// CRUD User data in db
require __DIR__."/../conf/db.php";
require __DIR__."/../utils/jwt.php";

class User 
{
    /**
     * Constructor
     *
     * @since   2020-11-27
     */
    public $table = "users";
    private $db;
    
    public function __construct()
    {   
        $db = new DB();
        $this->db = $db->getConnection();
        $this->JWT = new Token();
    }

    public function getUserByPhone(string $phone){
        $sql = $this->db->prepare(
            'SELECT * FROM ' .$this->table. 
            " WHERE users.phone = " . $phone
        );
        
        $sql->execute();

        $user = $sql->fetchAll();
        json_encode($user);

        return $user;
    }

    public function getUsreById(string $_id)
    {
        $sql = $this->db->prepare(
            'SELECT * FROM ' .$this->table. 
            'WHERE users._id =' .$_id
        );
        
        $sql->execute();

        $user = $sql->fetchAll();
        json_encode($user);

        return $user;
    }


    public function createUser(string $first_name, string $last_name, string $phone)
    {
        $user = $this->getUserByPhone($phone);

        if($user){
            return false;
        } else {
            $sql = $this->db->prepare(
                "INSERT INTO " . $this->table . "(first_name, last_name, phone)".
                " VALUES ('" .$first_name . "','" . $last_name . "','" . $phone . "')"
            );
            $sql->execute();

            $this->generateOTP($phone);
            return true;
        };
        
    }

    public function generateOTP(string $phone)
    {
        $OTP = rand(100000, 900000);
        $sql = $this->db->prepare(
            'SELECT id from ' .$this->table . 
            " WHERE users.phone = " .$phone.
            " LIMIT 1"
        );

        $sql->execute();

        $user = $sql->fetch(PDO::FETCH_ASSOC);

        if($user)
        {
            $sql = $this->db->prepare(
                "UPDATE " . $this->table .
                " SET password = '" . $OTP . "'".
                " WHERE id = " . $user['id']
            );
            $sql->execute();
            return $OTP;
        } else {
            return false;
        }
    }

    public function verify(string $phone, string $otp)
    {
        $jwt = $this->authenticate($phone, $otp);

        if($jwt)
        {
            $sql = $sql = $this->db->prepare(
                "UPDATE " . $this->table .
                " SET verified = TRUE".
                " WHERE phone = " . $phone
            );  
            $sql->execute();
            return $jwt;
        } else {
            return false;
        }
    }

    public function authenticate(string $phone, string $otp)
    {
        $sql = $this->db->prepare(
            "SELECT id from " . $this->table .
            " WHERE users.password = '" .$otp. "' AND " . "users.phone = '" .$phone ."'". 
            " LIMIT 1"
        );
        $sql->execute();

        $user = $sql->fetch(PDO::FETCH_ASSOC);

        if($user)
        {
            $jwt = $this->JWT->getJWT($user['id']);
            $sql = $this->db->prepare(
                "UPDATE " . $this->table .
                " SET token = '" . $jwt . "'".
                " WHERE id = " . $user['id']
            );
            $sql->execute();
            return $jwt;
        } else {
            return false;
        }

    }
}

?>