<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");

require("../../entities/export_hk.php");

$export = new export_hk();


if(empty($_GET['date'])){
    $export->generate_all();
} else {
    $export->generate_update(strtotime($_GET['date']));
}

?>