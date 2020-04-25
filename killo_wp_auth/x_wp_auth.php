<?php
require_once(__DIR__."/phpass/PasswordHash.php");

/**
 * 
 */
class Killo_XWPAuth{
    public $pdo;
    public $hasher;
    public $deanToken;
    public $deanEndpoint;

    //Constructor
    function __construct($db_host = DB_HOST, $db_name = DB_NAME, $db_user = DB_USER, $db_pass = DB_PASSWORD, $db_port = 3306,$dean_token=DEAN_TOKEN,$dean_endpoint = "https://killo.software/api/"){
        $this->pdo = new PDO("mysql:host=".$db_host.";dbname=".$db_name.";port=".$db_port.";charset=utf8mb4",$db_user,$db_pass);
        $this->hasher = new PasswordHash(8, FALSE);
        $this->deanToken = $dean_token;

        $this->deanEndpoint = $dean_endpoint;
    }

    //Checks if the db has the right function
    private function checkOrCreateTable(){
        $this->pdo->query("CREATE TABLE IF NOT EXISTS k_UserDean(wp_id BIGINT UNSIGNED PRIMARY KEY, dean_id BIGINT UNSIGNED);");
    }

    //Send data
    private function sendDataToDean($url,$data){
        $ch = curl_init();
        $fp = fopen(dirname(__FILE__).'/errorlog.txt', 'w');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $fp);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        $r = curl_exec($ch);
        fclose($fp);
        echo $r;
        return $r;
    }

    /**
     * Login User
     * returns an associative array. success=true/false, wp_data=ID,user_login,user_email,user_pass
     * */
    public function login($email,$pass){
        $email = strtolower($email);
        $s = $this->pdo->prepare("SELECT * FROM wp_users WHERE user_email=?");
        $s->execute([$email]);
        $r = $s->fetchAll(PDO::FETCH_ASSOC);
        $res = [
            "success"=>false,
            "wp_id"=>0,
            "wp_data"=>[],
            "dean_id"=>0,
            "dean_data"=>[]
        ];

        foreach($r as $v){
            if($this->hasher->checkPassword($pass,$v["user_pass"])){
                $res["success"] = true;
                $res["wp_data"] = $v;
                break;
            }
        }

        if($res["success"] && $res["wp_data"]["ID"] > 0){
            $res["wp_id"] = $res["wp_data"]["ID"];
            $this->checkOrCreateTable();
            $dws = $this->pdo->prepare("SELECT * FROM k_UserDean WHERE wp_id=?");
            $dws->execute([$res["wp_data"]["ID"]]);
            $dwr = $dws->fetch(PDO::FETCH_ASSOC);
            if(!empty($dwr) && !empty($dwr["dean_id"])){
                $res["dean_id"] = $dwr["dean_id"];
                $res["dean_data"] = json_decode(file_get_contents($this->deanEndpoint.$this->deanToken."/students/".$dwr["dean_id"]),true);
            }
        }

        return $res;
    }

    /**
     * Local assign
     * Assign WP_ID to DEAN_ID
     */
    public function localAssign($wp_id,$dean_id){
        $s = $this->pdo->prepare("INSERT INTO k_UserDean SET wp_id=?, dean_id=? ON DUPLICATE KEY UPDATE dean_id=?");
        $s->execute([
            $wp_id,
            $dean_id,
            $dean_id
        ]);
        return $s->fetch();
    }

    /**
     * Create Dean User
     * returns an associative array. success=true/false, new=true/false, id=int>0
     * throws Exception on wrong Dean Response
     * */
    public function createDeanStudent($data,$wp_id){
        if(empty($data["metadata"])){
            $data["metadata"] = [];
        }
        $data["metadata"]["wp_id"] = $wp_id;

        $r = $this->sendDataToDean($this->deanEndpoint.$this->deanToken."/students/",$data);
        $j = json_decode($r,true);
        if(empty($j) || !$j["success"]){
            throw new Exception("Wrong response",1);
        }
        if($j["id"]){
            $this->localAssign($wp_id,$j["id"]);
        }
        
        return $j;
    }

    /**
     * GET Dean User
     * returns an associative array.
     * throws Exception on wrong Dean Response
     * */
    public function getDeanStudent($dean_id){
        $r = file_get_contents($this->deanEndpoint.$this->deanToken."/students/".$dean_id);
        $j = json_decode($r,true);
        if(empty($j)){
            throw new Exception("Wrong response",1);
        }
        return $j;
    }


    /**
     * GET WP User
     * returns an associative array.
     * throws Exception on wrong Dean Response
     * */
    public function getWPUser($wp_id){
        $res = [
            "success"=>false,
            "wp_id"=>0,
            "wp_data"=>[],
            "dean_id"=>0,
            "dean_data"=>[]
        ];

        $s = $this->pdo->prepare("SELECT * FROM wp_users WHERE ID=?");
        $s->execute([$wp_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);

        if(empty($r)){
            throw new Exception("user not found");
        }

        $res["success"] = true;
        $res["wp_data"] = $r;

        if($res["success"] && $res["wp_data"]["ID"] > 0){
            $res["wp_id"] = $res["wp_data"]["ID"];
            $this->checkOrCreateTable();
            $dws = $this->pdo->prepare("SELECT * FROM k_UserDean WHERE wp_id=?");
            $dws->execute([$res["wp_data"]["ID"]]);
            $dwr = $dws->fetch(PDO::FETCH_ASSOC);
            if(!empty($dwr) && !empty($dwr["dean_id"])){
                $res["dean_id"] = $dwr["dean_id"];
                $res["dean_data"] = json_decode(file_get_contents($this->deanEndpoint.$this->deanToken."/students/".$dwr["dean_id"]),true);
            }
        }

        return $res;
    }
}
?>
