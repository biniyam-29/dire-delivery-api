<?php
namespace src\common\constants;

class AuthConstants {
    public static string $secretKey;
    public static int $expirationTime;

    public static function initialize() {
        try{
            self::$secretKey ="direSECRETdeliveryKEY";
            self::$expirationTime = 86400;
        }catch(Exception $e){
            return ['error' => $e->getMessage()];
        }
    }
}
?>
