<?php

class GetTransportDataCest
{

    public function _before(ApiTester $I)
    {
    }

    // tests
    public function tryToTest(ApiTester $I)
    {
        $I->sendGet('/transports.php');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }
}
