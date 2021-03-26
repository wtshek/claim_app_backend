<?php

class CreateUserCest
{
    public function _before(ApiTester $I)
    {
    }

    // tests
      public function register(ApiTester $I)
      {
          $I->sendPost('/user/register.php',[
              'first_name' => "John",
              'last_name'=>'Wick',
              'phone' => "+85296786780"
          ]);
          $I->seeResponseCodeIs(200);
          $I->seeResponseContainsJson(['msg'=>'New user is created', "code" => 200]);
      }
  
      public function userIsRegistered(ApiTester $I)
      {
          $I->sendPost("/user/register.php",[
              'first_name' => "John",
              'last_name'=>'Wick',
              'phone' => "+85296786780"
          ]);
          $I->seeResponseCodeIs(200);
          $I->seeResponseContainsJson(['error'=>'Phone number is registered', "code" => 400]);
      },
    
      
}
