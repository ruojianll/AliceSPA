<?php
namespace AliceSPA\service;
use \AliceSPA\helper\time as timeHelper;
use \AliceSPA\helper\config as configHelper;
use \AliceSPA\helper\database as dbHelper;
use \AliceSPA\helper\utilities as utils;
use \AliceSPA\service\database as db;
class authentication
{
    private $isLoggedIn = false;
    private $userInfo = null;
    private static $_instance;

    private function __construct(){
    }

    public function __clone(){
    }

    public static function getInstance(){
        if(!(self::$_instance instanceof self)){
            self::$_instance = new self;
        }
        return self::$_instance;
    }


    public function loginByUnionField($nameMap,$password){
        $db = db::getInstance();
        $nameMap = $this->filterUnionField($nameMap);
        $where = [
            'AND' => [
                'AND' => $nameMap,
                'password' => $password
            ]
        ];
        $user = $db->get('account',['id'],$where);
        if(!$user){
            return false;
        }

        $token = utils::generateToken($user['id']);
        $db->update('account',['web_token' => $token,'web_token_create_time'=>timeHelper::datetimePHP2Mysql(time())],['id'=>$user['id']]);
        $user = $this->authenticateByWebToken($user['id'],$token);

        return $user;
    }

    public function isExistByUnionField($nameMap){
        $db = db::getInstance();
        $nameMap = $this->filterUnionField($nameMap);
        $fieldNames = configHelper::getCoreConfig()['authenticateFieldNames'];
        foreach($nameMap as $key => $value){ // !PERFORMANCE
            $where = utils::arrayMap($fieldNames,$value);
            if($db->has('account',['OR' => $where])){
                return true;
            }
        }
        return false;
    }

    public function registerByUnionField($nameMap,$password){
        $db = db::getInstance();
        
        $nameMap = $this->filterUnionField($nameMap);

        if($this->isExistByUnionField($nameMap)){
            return false;
        }
        $data = $nameMap;
        $data['password'] = $password;
        $id = $db->insert('account',$data);
        if(intval($id) < configHelper::getCoreConfig()['autoincrementBeginValue']){
            return false;
        }
        return $id;
    }

    private function filterUnionField($nameMap){

        $fieldNames = configHelper::getCoreConfig()['authenticateFieldNames'];
        $nameMap = array_filter($nameMap,
            function($value,$key)use($fieldNames){ // remove null or empty string
                if(empty($value) || !in_array($key,$fieldNames)){
                    return false;
                }
                return true;
            },ARRAY_FILTER_USE_BOTH);
        return $nameMap;     
    }

    public function authenticateByWebToken($userId,$webToken){
        $db = db::getInstance();
        $user = $db->get('account',
            '*',
            ['AND' =>
                ['id'=>$userId,
                'web_token'=>$webToken
                ]
            ]);
        if(!$user){
            return false;
        }
        $web_token_create_time = $user['web_token_create_time'];
        if(empty($web_token_create_time)){
            return false;
        }
        if(time() - timeHelper::datetimeMysql2PHP($web_token_create_time) > 
            configHelper::getCoreConfig()['webTokenValidTime']){
            return false;
        }
        unset($user['password']);
        unset($user['web_token_create_time']);
        $isLoggedIn = true;
        $this->userInfo = $user;
        return $this->userInfo;
    }
}

$container['auth'] = function(){
    return \AliceSPA\service\authentication::getInstance();
};