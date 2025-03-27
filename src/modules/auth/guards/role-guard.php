<?php
namespace src\modules\auth\guards;

require_once 'vendor/autoload.php';
require_once 'src/common/constants/auth-constants.php';
require_once 'src/modules/auth/guards/auth-guard.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use src\common\constants\AuthConstants;
use src\modules\auth\guards\AuthGuard;
use Exception;

class RoleGuard {
    public static function roleGuard($role) {
        try{
            $user = AuthGuard::authenticate("guard");

            if (!$user || !isset($user->role)) {
                return false;
            }

            if ($user->role !== $role) {
                return false;
            }
            return true;
        }catch(Exception $e) {
            return false;
        }
    }
}
?>
