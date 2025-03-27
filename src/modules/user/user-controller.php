<?php
namespace src\modules\user;
require_once 'src/modules/controller-interface.php';
require_once 'src/modules/user/user-service.php';
require_once 'src/common/constants/request-constants.php';
require_once 'src/modules/auth/guards/auth-guard.php';
require_once 'src/modules/auth/guards/role-guard.php';

use src\modules\user\UserService;
use src\modules\ControllerInterface;
use src\common\constants\FormatRequest;
use src\modules\auth\guards\AuthGuard;
use src\modules\auth\guards\RoleGuard;

class UserController implements ControllerInterface {
    private UserService $userService;
    private FormatRequest $request;

    public function __construct() {
        $this->userService = new UserService();
        $this->request = new FormatRequest();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method) {
            case 'GET':
                if($this->request->paths[1] === "check"){
                    return $this->userService->check();
                }
                if(isset($this->request->paths[1]) && isset($this->request->paths[2])){
                   if(AuthGuard::authenticate()){
                        switch($this->request->paths[2]){
                            case 'all-users':
                                if( (RoleGuard::roleGuard("ADMIN") || RoleGuard::roleGuard("OWNER")) &&
                                    isset($this->request->paths[1]) && 
                                    isset($this->request->paths[2]) 
                                 ){
                                    return $this->userService->getAllUsers($this->request->paths[3] ?? 1);
                                }else{
                                    return json_encode(['message' => 'You don\'t have permission to access this route']);
                                }
                                break;
                            case 'EMPLOYEE':
                                if( (RoleGuard::roleGuard("ADMIN") || RoleGuard::roleGuard("OWNER")) && 
                                    isset($this->request->paths[1]) && 
                                    isset($this->request->paths[2]) && 
                                    isset($this->request->paths[3])
                                ){
                                    return $this->userService->getUserByRole("EMPLOYEE", ($this->request->paths[3] ?? 1));
                                }else{
                                    return json_encode(['message' => 'You don\'t have permission to access this route']);
                                }
                                break;

                            case 'ADMIN':
                                if( (RoleGuard::roleGuard("ADMIN") || RoleGuard::roleGuard("OWNER")) &&
                                    isset($this->request->paths[1]) && 
                                    isset($this->request->paths[2]) && 
                                    isset($this->request->paths[3])
                                 ){
                                    return $this->userService->getUserByRole("ADMIN", ($this->request->paths[3] ?? 1));
                                }else{
                                    return json_encode(['message' => 'You don\'t have permission to access this route']);
                                }
                                break;
                            case 'find-one':
                                if( RoleGuard::roleGuard("ADMIN") || RoleGuard::roleGuard("OWNER") ){
                                    return $this->userService->getUser($this->request->paths[3]);
                                }else{
                                    return json_encode(['message' => 'You don\'t have permission to access this route']);
                                }
                                break;
                            case 'search-by-name':
                                if( (RoleGuard::roleGuard("ADMIN") || RoleGuard::roleGuard("OWNER")) && isset($this->request->paths[4]) ){
                                    return $this->userService->searchByName($this->request->paths[3], $this->request->paths[4]);
                                }else{
                                    return json_encode(['message' => 'You don\'t have permission to access this route']);
                                }
                                break;
                            default:
                                return json_encode(['message' => 'Invalid get request method']);
                                break;
                        }
                   }
                }
                break;
            case 'POST':
                if(isset($this->request->paths[1]) && isset($this->request->paths[2])){
                    switch($this->request->paths[2]){
                        case 'change-role':
                            if( RoleGuard::roleGuard("ADMIN") || RoleGuard::roleGuard("OWNER") ){
                                return $this->userService->changeRole($this->request->body, $this->request->paths[1]);
                            }else{
                                return json_encode(['message' => 'You don\'t have permission to access this route']);
                            }
                            break;
                        case 'update-user':
                            if( AuthGuard::authenticate() ){
                                return $this->userService->updateUser($this->request->paths[1], $this->request->body);
                            }else{
                                return json_encode(['message' => 'You don\'t have permission to access this route']);
                            }
                            break;
                        default:
                            return json_encode(['message' => 'Invalid request method']);
                            break;
                    }
                }
                break;
            case 'DELETE':
                if(isset($this->request->paths[1]) && isset($this->request->paths[2]) && isset($this->request->paths[3])){
                    switch($this->request->paths[2]){
                        case 'delete-user':
                            if( RoleGuard::roleGuard("ADMIN") || RoleGuard::roleGuard("OWNER") ){
                                return $this->userService->deleteUser($this->request->paths[3]);
                            }else{
                                return json_encode(['message' => 'You don\'t have permission to access this route']);
                            }
                            break;
                        default:
                            return json_encode(['message' => 'Invalid request method']);
                    }
                }
                break;
            default:
                return json_encode(['message' => 'Invalid request method']);
                break;
        }
    }
}
?>
