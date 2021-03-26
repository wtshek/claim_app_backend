<?php
require_once 'vendor/autoload.php';


class LoggingCest
{
    public function __constructor(){
        $faker = Faker\Factory::create();
        $this->user = array('firstName' => $faker->firstName, 'lastName' => $faker->lastName, 'phone'=> $faker->phoneNumber);
    }

    public function _before(ApiTester $I)
    {
    }

    // tests
    public function getOTP(ApiTester $I)
    {
        $I->sendPost('/user/login.php',['phone' => "+85290420941"]);
        $I->seeResponseCodeIs(200);
    }

    public function logInWithOTP(ApiTester $I)
    {
        $I->sendPost('/user/login.php',['phone' => "+85296786780", 'pw'=>"1234"]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseMatchesJsonType([
            'msg'=>'string',
            'code'=>'integer',
            'jwt'=>'string'
        ]);
        $I->seeResponseContainsJson(['msg'=>'Login succesfully', 'code'=>200]);
    }

    public function loginFail(ApiTester $I)
    {
        $I->sendPost('/user/login.php',['phone' => '1234', 'pw'=>"12324"]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseMatchesJsonType([
            'msg'=>'string',
            'code'=>'integer',
        ]);
        $I->seeResponseContainsJson(['msg'=>'Login fail. Please try again', 'code'=>500]);
    }

}
