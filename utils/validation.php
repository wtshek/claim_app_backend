<?php 
require_once __DIR__ . '../../vendor/autoload.php';
require_once __DIR__."/../conf/db.php";

class Validation 
{
    private $db;

    public function __construct()
    {
        $db = new DB();
        $this->db = $db->getConnection();
    }

    public function isPhoneValid($phone)
    {
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        $region = "";
        if(str_starts_with($phone, "+852"))
        {
            $region = 'HK';
            $noRegionCode = substr($phone, 3);

        } else if (str_starts_with($phone, "+86"))
        {
            $region = 'CN';
            $noRegionCode = substr($phone, 2);
        } else 
        {
            return false;
        }

        return $noRegionCode;

        $swissNumberProto = $phoneUtil->parse($noRegionCode, $region);
        return $phoneUtil->isValidNumber($swissNumberProto);

    }

    public function isClaimValid($data)
    {
        if(!isset($data['typeOfPT'])) 
        {
            return false;
        }
        
        $sql;
        $type = $data['typeOfPT'];
        if($type === "bus" || $type === "minibus" || $type === 'ferry')
        {
            $sql = $this->db->prepare(
                'SELECT * FROM route_chart_fares' .
                " WHERE (route_chart_id = '" . $data['routeIndex'] ."' AND on_stop_id='" . $data['onboardStopId'] . "' AND off_stop_id='" . $data['alightStopId'] . "')" . 
                " OR (route_chart_id = " . $data['routeIndex'] . ")"
            );
        } else if ($type === 'mtr_lines' || $type === 'airport_express' || $type === 'light_rail')
        {
            $chartID;
            $getIDSql = $this->db->prepare(
                'SELECT id FROM railway_charts' . 
                " WHERE type='" . $type ."'". 
                " LIMIT 1"
            );
            $getIDSql->execute();
            $chartID = $getIDSql->fetch();

            $sql = $this->db->prepare(
                'SELECT * FROM railway_chart_fares' .
                " WHERE railway_chart_id = '" . $chartID['id'] ."' AND on_stop_id='" . $data['onboardStopId'] . "' AND off_stop_id='" . $data['alightStopId'] . "'"
            );
        }

        $sql->execute();
        return $sql->fetch();
        
    }
}
?>