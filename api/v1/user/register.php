<?php
require ("../../../entities/user.php");

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$user = new User();

list("phone"=>$phone, 'first_name'=>$firstName, 'last_name'=>$lastName) = $_POST;
$isCreated = $user->createNewUser($firstName, $lastName, $phone);

if($isCreated){
    echo json_encode(['msg'=>'New user is created', "code" => 200]);
} else {
    echo json_encode(['error'=>'Phone number is registered', "code" => 400]);
}
?>