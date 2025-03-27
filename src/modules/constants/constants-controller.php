<?php
    namespace src\modules\constants;
    
    require_once 'src/modules/constants/constants-service.php';
    require_once 'src/common/constants/request-constants.php';
    require_once 'src/modules/auth/guards/auth-guard.php';
    require_once 'src/modules/auth/guards/role-guard.php';
    require_once 'src/modules/controller-interface.php';

    use src\modules\constants\ConstantsService;
    use src\common\constants\FormatRequest;
    use src\modules\auth\guards\AuthGuard;
    use src\modules\auth\guards\RoleGuard;
    use src\modules\ControllerInterface;

    class ConstantsController implements ControllerInterface {
        private ConstantsService $constantService;
        private FormatRequest $request;
        public function __construct() {
            $this->constantService = new ConstantsService();
            $this->request = new FormatRequest();
        }

        public function handleRequest() {
            switch($this->request->method) {
                case "GET":
                    return $this->constantService->getConstants();
                    break;
                case "POST":
                    if(isset($this->request->paths[1]) && isset($this->request->paths[2])) {
                        switch($this->request->paths[2]) {
                            case "update-constants":
                                if(AuthGuard::authenticate() && (RoleGuard::roleGuard("OWNER") || RoleGuard::roleGuard("ADMIN"))) {
                                    return $this->constantService->updateConstants($this->request->paths[3], $this->request->body);
                                }else{
                                    return  json_encode(['message' => 'Unauthorized']);
                                }
                                break;
                            case "add-location":
                                if(AuthGuard::authenticate() && (RoleGuard::roleGuard("OWNER") || RoleGuard::roleGuard("ADMIN"))) {
                                    return $this->constantService->addLocation($this->request->body);
                                }else{
                                    return  json_encode(['message' => 'Unauthorized']);
                                }
                                break;
                            case "update-location":
                                if(
                                    isset($this->request->paths[1]) && 
                                    isset($this->request->paths[2]) &&
                                    isset($this->request->paths[3]) &&
                                    AuthGuard::authenticate() && 
                                    (
                                        RoleGuard::roleGuard('OWNER') || 
                                        RoleGuard::roleGuard('ADMIN')
                                    ) 
                                ){
                                    return $this->constantService->updateLocation( $this->request->body, $this->request->paths[3]);
                                }else{
                                    return  json_encode(['message' => 'Unauthorized']);
                                }
                                break;
                                
                        }
                        
                    }
                    break;
                case "DELETE":
                    if(
                        isset($this->request->paths[1]) && 
                        isset($this->request->paths[2]) &&
                        isset($this->request->paths[3]) &&
                        ($this->request->paths[2] === 'delete-location') &&
                        AuthGuard::authenticate() && 
                        (
                            RoleGuard::roleGuard('OWNER') || 
                            RoleGuard::roleGuard('ADMIN')
                        ) 
                    ){
                        return $this->constantService->deleteLocation( $this->request->paths[3]);
                    }else{
                        return  json_encode(['message' => 'Unauthorized']);
                    }
                default:
                    return json_encode(['message' => 'Invalid request']);
                    break;
            }
        }

    }
?>
