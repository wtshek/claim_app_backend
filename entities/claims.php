<?php
require __DIR__."/../conf/db.php";


class Claim
{
    public $table = "claims";
    private $db;

    public function __construct()
    {
        $db = new DB();
        $this->db = $db->getConnection();
    }

    public function getClaims($user_id)
    {
        $sql = $this->db->prepare(
            'SELECT *' . 
            " FROM " . $this->table . 
            " WHERE creator_id = '" . $user_id . "'"
        );
        $sql->execute();
        $claims = $sql->fetchAll();
        return $claims;
    }

    public function getUpdatedClaims($user_id, $last_update)
    {   
        $sql = $this->db->prepare(
            'SELECT *' . 
            " FROM " . $this->table . 
            " WHERE creator_id = '" . $user_id . "' AND UNIX_TIMESTAMP(updated_date) >= '" . (int)$last_update/1000 . "'"
        );
        $sql->execute();
        $claims = $sql->fetchAll();
        return $claims;
    }

    public function addClaim($user_id, $data){
        $sqlKeys = "";
        $sqlValues = "";
        foreach(array_keys($data) as $key)
        {
            $sqlKeys .= $key.",";
            $sqlValues .= "'".$data[$key]."',";
        }
        
        $sql = $this->db->prepare(
            'INSERT INTO ' . $this->table . "(" . $sqlKeys . " creator_id " .")" .
            " VALUES (" . $sqlValues . "'" . $user_id . "')"
        );
        $sql->execute();

        $id = $this->db->lastInsertId();

        $lastest = $this->db->prepare(
            'SELECT * FROM ' . $this->table .
            ' WHERE id = ' . $id
        );

        $lastest->execute();

        return $lastest->fetch();
    }

    public function editClaim($id, $data){ 
        $sqlPair = "";
        $index = 0;
        foreach($data as $key=>$value)
        {
            $isInteger = is_numeric($value);
            
            if($isInteger)
            {
                $sqlPair .= $key . " = ". (float)$value . ",";
            }else{
                $sqlPair .= $key . "='". $value . "',";
            }
        }

        $sqlPair = "UPDATE " . $this->table .
        " SET " . $sqlPair . " updated_date = '" .date('Y-m-d'). " " .date('h:i:s'). "'".
        " WHERE id = '" . $id ."'" ;
        
        $sql = $this->db->prepare(
            $sqlPair
        );
        $sql->execute();

        $lastest = $this->db->prepare(
            'SELECT * FROM ' . $this->table .
            ' WHERE id = ' . $data['id']
        );

        $lastest->execute();

        return $lastest->fetch();
    }

    public function deleteClaim($id){
        $sql = $this->db->prepare(
            "DELETE FROM " . $this->table .
            " WHERE id = " . $id
        );
        $sql->execute();
        return true;
    }
}

?>