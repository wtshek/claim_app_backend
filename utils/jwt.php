<?php
require __DIR__ . '../../vendor/autoload.php';
use \Firebase\JWT\JWT;

class Token
{
    protected $secretKey = "force100_avalade";

    public function __construct(){
    
    }

    public function getJWT(string $_id){
        $jwt = JWT::encode([
            '_id'=>$_id,
            "iat" => 1356999524,
            "nbf" => 1357000000
        ], $this->secretKey);
        return $jwt;
    }

    public function decodeJWT(string $token){
        $decoded = JWT::decode($token, $this->secretKey, array('HS256'));
        return (array) $decoded;
    }


}

?>