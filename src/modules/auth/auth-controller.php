<?php
namespace src\modules\auth;

require_once 'auth-service.php';
require_once 'src/modules/controller-interface.php';
require_once 'src/common/constants/request-constants.php';
require_once 'src/modules/auth/guards/auth-guard.php';
require_once 'src/modules/auth/guards/role-guard.php';

use src\modules\ControllerInterface;
use src\common\constants\FormatRequest;
use src\modules\auth\AuthService;
use src\modules\auth\guards\AuthGuard;
use src\modules\auth\guards\RoleGuard;
use Exception;

class AuthController implements ControllerInterface {
    private AuthService $authService;
    private FormatRequest $request;

    public function __construct() {
        $this->authService = new AuthService();
        $this->request = new FormatRequest();
    }

    public function handleRequest() {
        try {
            switch ($this->request->method) {
                case 'GET':
                    if (isset($this->request->paths[2]) && isset($this->request->paths[1])) {
                        switch($this->request->paths[2]){
                            case 'log-out':
                                if(AuthGuard::authenticate()){
                                    return $this->authService->logOut($this->request->paths[1]);
                                }else{
                                    http_response_code(401);
                                    return json_encode(["message" => "Unauthorized!"]);
                                }
                                break;
                            case 'all-log-out':
                                if(AuthGuard::authenticate()){
                                    return $this->authService->allLogOut($this->request->paths[1]);
                                }else{
                                    http_response_code(401);
                                    return json_encode(["message" => "Unauthorized!"]);
                                }
                            case 'remember-me':
                                if(AuthGuard::authenticate()){
                                    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
                                    $token = str_replace("Bearer ", "", $authHeader);
                                    return $this->authService->rememberMe($token);
                                }else{
                                    http_response_code(401);
                                    return json_encode(["message" => "Unauthorized!"]);
                                }
                        }
                    }
                    return json_encode(['message' => 'Invalid request!']);
                    break;
                
                case 'POST':
                    if (isset($this->request->paths[1])) {

                        if(isset($this->request->paths[2]) && ($this->request->paths[1] !== 'reset-password')) {
                            switch ($this->request->paths[2]) {
                                case 'sign-up':
                                    if (AuthGuard::authenticate("newUser")) {
                                        return $this->authService->addDetails($this->request->body, $this->request->paths[1]);
                                    }
                                    break;
                                case 'add-user':
                                    if (AuthGuard::authenticate() && RoleGuard::roleGuard('OWNER')) {
                                        return $this->authService->addUser($this->request->body);
                                    }
                                    break;
                                default:
                                    return json_encode(['message' => 'Invalid request!']);
                            }
                        }

                        switch ($this->request->paths[1]) {
                            case 'log-in':
                                return $this->authService->logIn($this->request->body);
                                break;
                            case 'reset-password':
                                return $this->authService->resetPassword($this->request->body, $this->request->paths[2]);
                                break;
                            case 'forgot-password':
                                return $this->authService->forgotPassword($this->request->body);
                                break;
                        }
                    }
                    return json_encode(['message' => 'Invalid request!']);
                
                default:
                    return json_encode(['message' => 'Invalid request!']);
            }
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}
?>
