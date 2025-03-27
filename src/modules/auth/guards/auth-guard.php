<?php
namespace src\modules\auth\guards;

require_once 'vendor/autoload.php';
require_once 'src/common/constants/auth-constants.php';
require_once 'src/config/db-config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use src\common\constants\AuthConstants;
use src\config\Database;
use Exception;
use PDO;

class AuthGuard {
    private static $conn = null;

    private function __construct() {
        AuthConstants::initialize();
    }

    private static function ensureDbConnection() {
        if (self::$conn === null) {
            self::$conn = Database::connect();
        }
    }

    public static function authenticate($caller = null) {
        self::ensureDbConnection();
        AuthConstants::initialize(); 

        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return false;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        $token = str_replace("Bearer ", "", $authHeader);
        if (empty($token)) {
            return false;
        }

        try {
            $decoded = JWT::decode($token, new Key(AuthConstants::$secretKey, 'HS256'));

            if (!isset($decoded->data) || !isset($decoded->data->id)) {
                return false;
            }

            $urlParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
            $userId = $urlParts[1] ?? null;
            if ($userId === null) {
                return false;
            }
            $user = self::read("users", "id", $userId);            
            if (empty($user)) {
                return false;
            }

            if($user['isActive'] == 0 && $user['role'] !== 'OWNER' && $caller !== "newUser") {
                http_response_code(401);
                return false;
            }

            if (!self::tokenOwnership($userId, $decoded)) {
                return false;
            }
            
            if (!self::validateToken($token, $decoded->data->id)) {
                return false;
            }
            if ($caller === "guard"){
                return $decoded->data;
            }
            
            return true;
        } catch (Exception $e) {
            http_response_code(401);
            return false;
        }
    }

    private static function tokenOwnership($userId, $decoded) {
        return $userId && isset($decoded->data->id) && (string) $userId === (string) $decoded->data->id;
    }

    private static function validateToken($token, $id) {
        self::ensureDbConnection();
        try {
            $sql = 'SELECT token FROM token WHERE userId = ? AND token = ?';
            $stmt = self::$conn->prepare($sql);
            $stmt->execute([$id, $token]);
            $userToken = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$userToken) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function read($table, $column = 'id', $value = null){
        try {
            $sql = "SELECT * FROM $table WHERE $column = :value";
            $stmt = self::$conn->prepare($sql);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}
?>