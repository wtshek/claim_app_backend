<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require ("../../../entities/user.php");
require ("../../../utils/validation.php");



$data = json_decode(file_get_contents('php://input'), true);
$validate = new Validation();

// check if all required info exist
if(!isset($data['phone']))
{
    echo json_encode(['msg'=>'Incomplete Information', "code"=>401]);
    exit();
}



if(!$validate->isPhoneValid($data['phone']))
{
    echo json_encode(['msg'=>'Phone number format invalid', "code"=>402]);
    exit();
}

// if no otp, send sms
$user = new User();
$jwt;

if(!isset($data['pwd']))
{
    $res = $user->generateOTP($data['phone']);
    if(!$res){
        echo json_encode(['msg'=>'User does not exist', 'code'=>403]);
    }

} else
{
    $jwt = $user->authenticate($data['phone'], $data['pwd']);

    if($jwt)
    {
        echo json_encode(['msg'=>'Login succesfully', 'code'=>200, "jwt"=>$jwt]);
    } else 
    {
        echo json_encode(['msg'=>'Wrong Verfication Code', 'code'=>404]);
    }
}


?>