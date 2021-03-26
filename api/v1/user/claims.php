<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");

require "../../../entities/claims.php";
require "../../../utils/jwt.php";
require "../../../utils/validation.php";


function getBearerToken() {
    $headers = apache_request_headers();
    // HEADER: Get the access token from the header
    if(isset($headers['Authorization'])){
        if (preg_match('/Bearer (\S+)/',$headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    
    return false;
}

// check if token is provided
function validateToken()
{
    $decoded;
    $extracted = getBearerToken();

    if ($extracted == false)
    {
        echo json_encode(array("msg"=>"Wrong Token", "code"=>500));
        exit();
    } else {
        $token = new Token();
        $decoded = $token->decodeJWT($extracted);
    } 
    
    return $decoded;
}

$claim = new Claim();
$token = validateToken();
$user_id = $token["_id"];
$data = json_decode(file_get_contents('php://input'), true);
$validate = new Validation();

// GET ROTUE /claims
if($_SERVER['REQUEST_METHOD'] === 'GET')
{
    if(isset($_GET['last-update']))
    {
        $claims = $claim->getUpdatedClaims($user_id, $_GET['last-update']); 
    } else {
        $claims = $claim->getClaims($user_id);
    }
    echo json_encode($claims);
    exit();
}
else // POST method
{
    if(!empty($_SERVER['QUERY_STRING'])){
        $queries = $_SERVER['QUERY_STRING'];

        preg_match('/id=(\d+)&?/',$queries, $id);
        preg_match('/&delete=(\S+)/',$queries, $isDelete);


        if($isDelete && $isDelete[1] == 'true')
        {
            // POST ROUTE /claims?id=&delete=true
            $deleted = $claim->deleteClaim($id[1]);
            echo json_encode(['msg'=>($deleted?'deleted':'problem occur'), "code"=>200]);
            exit();
        } 
        else 
        {
            // edit claim
            // POST ROUTE /claims?id=
            if($data['status'] === 'pending')
            {
                $return = $claim->editClaim($id[1], $data);;
                echo json_encode($return);
            } 
            else 
            {
                echo json_encode(['msg'=>'Claim approved.', "code"=> 200]);
            }
            exit();
        }

    } 
    else 
    {

        // create new claim
        // POST ROUTE /claims
        if($validate->isClaimValid($data))
        {
            $return = $claim->addClaim($user_id, $data);
            echo json_encode($return);
        } else {
            echo json_encode(['msg'=>"Something went wrong. Please Contact administrator", 'code'=>500]);
        }
        exit();
    }
}

?>