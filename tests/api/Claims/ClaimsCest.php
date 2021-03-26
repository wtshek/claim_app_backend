<?php
class ClaimsCest
{
    private $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJfaWQiOiIxMyIsImlzcyI6Imh0dHA6XC9cL2V4YW1wbGUub3JnIiwiYXVkIjoiaHR0cDpcL1wvZXhhbXBsZS5jb20iLCJpYXQiOjEzNTY5OTk1MjQsIm5iZiI6MTM1NzAwMDAwMH0.N38V1IwoADA8bKyWvNAWUe64lL-F2fyEHSvMIApYR50';
    private $user_id = 13; // john wick

    public function _before(ApiTester $I)
    {
    }

    // tests
    public function createClaimWithOutJWT(ApiTester $I)
    {
        $I->sendPost('/user/claims.php', ["data"=>[
            "typeOfPT" => "tram",
            "status" => "pending",
            "fare" => '$2.6']
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseMatchesJsonType([
            'msg'=>'string',
            'code'=>'integer',
        ]);
        $I->seeResponseContainsJson(['msg'=>'Wrong Token', 'code'=>500]);
    }


    public function createClaim(ApiTester $I)
    {
        $I -> amBearerAuthenticated($this->token);
        $data = [
            "typeOfPT" => "tram",
            "status" => "pending",
            "fare" => '$2.6',
        ];
        $I->sendPost('/user/claims.php', ["data"=>$data]);
        $I -> seeResponseCodeIs(200);
        $I->seeResponseContainsJson($data);

    }

    public function fieldMissing (ApiTester $I)
    {
        $I -> amBearerAuthenticated($this->token);
        $data = [
            "typeOfPT" => null,
            "status" => "pending",
            "fare" => null,
            "user_id"=> $this->user_id
        ];
        $I->sendPost('/user/claims.php', ["data"=>$data]);
        $I -> seeResponseCodeIs(200);
        $I->seeResponseMatchesJsonType([
            'msg'=>'string',
            'code'=>'integer',
        ]);
        $I->seeResponseContainsJson(['msg'=>'Incomplete Data', 'code'=>300]);

    }

    public function editClaim(ApiTester $I)
    {
        $data = [
            "typeOfPT" => 'bus',
            "status" => "pending",
            "fare" => '$2.1',
            "routeIndex"=> (int)28,
            "alightStopId" => (int)12,
            "onboardStopId" => (int)21,
        ];
        $I -> amBearerAuthenticated($this->token);
        $I->sendPost('/user/claims.php?id=68010754', ["data"=>$data]);
        $I -> seeResponseCodeIs(200);
        $I->seeResponseContainsJson($data);
    }

    public function editIsNotAllowed(ApiTester $I)
    {
        $data = [
            "typeOfPT" => 'bus',
            "status" => "approved",
            "fare" => '$2.1',
            "routeIndex"=> (int)28,
            "alightStopId" => (int)12,
            "onboardStopId" => (int)21,
        ];
        $I -> amBearerAuthenticated($this->token);
        $I->sendPost('/user/claims.php?id=3', ["data"=>$data]);
        $I -> seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['msg'=>'Claim approved.', "code"=> 300]);
    }

    public function deleteClaim(ApiTester $I)
    {
        $data = [
            "typeOfPT" => null,
            "status" => "pending",
            "fare" => null,
            "user_id"=> $this->user_id,
        ];
        $I -> amBearerAuthenticated($this->token);
        $I->sendPost('/user/claims.php?id=5&delete=true', ["data"=>$data]);
        $I -> seeResponseCodeIs(200);
        $I->seeResponseMatchesJsonType([
            'msg'=>'string',
            'code'=>'integer',
        ]);
        $I->seeResponseContainsJson(['msg'=>'deleted', 'code'=>200]);
    }

    public function getClaims(ApiTester $I)
    {
        $I -> amBearerAuthenticated($this->token);
        $I-> sendGet('/user/claims.php');
        $I -> seeResponseCodeIs(200);
        $I -> seeResponseIsJson();
    }
}
