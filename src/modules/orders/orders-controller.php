<?php
namespace src\modules\Orders;

require_once 'src/common/constants/request-constants.php';
require_once 'src/modules/controller-interface.php';
require_once 'src/modules/orders/orders-service.php';
require_once 'src/modules/auth/guards/auth-guard.php';
require_once 'src/modules/auth/guards/role-guard.php';

use src\common\constants\FormatRequest;
use src\modules\ControllerInterface;
use src\modules\Orders\OrdersService;
use src\modules\auth\guards\AuthGuard;
use src\modules\auth\guards\RoleGuard;
use Exception;

class OrdersController implements ControllerInterface{
    private FormatRequest $request;
    private OrdersService $orderService;

    public function  __construct(){
        $this->request = new FormatRequest();
        $this->orderService = new OrdersService();
    }
    public function handleRequest(){
        
        try{
            if($this->request->method === 'GET'){
                if($this->request->paths[1] === 'pre-order'){
                    return $this->orderService->preOrder();
                }
            if(isset($this->request->paths[1]) && !isset($this->request->paths[2])){
                return $this->orderService->getOrderByTransaction($this->request->paths[1]);
            }
            if(isset($this->request->paths[1]) && isset($this->request->paths[2]) && ($this->request->paths[2] === 'all-orders') && AuthGuard::authenticate()){
                return $this->orderService->getAllOrders(isset($this->request->paths[3]) ? $this->request->paths[3] : 0);
            }if(
                isset($this->request->paths[1]) && 
                isset($this->request->paths[2]) && 
                $this->request->paths[3] === 'orders-blame' && 
                AuthGuard::authenticate() && 
                (
                    RoleGuard::roleGuard('OWNER') || 
                    RoleGuard::roleGuard('ADMIN')
                ) 
            ){
                return $this->orderService->employeeOrder($this->request->paths[2], $this->request->paths[4]);
            }if(
                isset($this->request->paths[1]) && 
                isset($this->request->paths[2]) &&
                isset($this->request->paths[3]) &&
                isset($this->request->paths[4]) && 
                $this->request->paths[2] === 'filter-order-status' && 
                AuthGuard::authenticate() 
            ){
                return $this->orderService->getOrderByStatus( 'status', $this->request->paths[3], $this->request->paths[4]);
            }if(
                isset($this->request->paths[1]) && 
                isset($this->request->paths[2]) &&
                isset($this->request->paths[3]) &&
                isset($this->request->paths[4]) &&  
                $this->request->paths[2] === 'filter-order-date' && 
                AuthGuard::authenticate()
            ){
                return $this->orderService->getOrderByStatus( 'createdAt', $this->request->paths[3], $this->request->paths[4]);
            }if(
                isset($this->request->paths[1]) && 
                isset($this->request->paths[2]) && 
                isset($this->request->paths[3]) &&
                isset($this->request->paths[4]) && 
                $this->request->paths[2] === 'filter-status-updates' && 
                AuthGuard::authenticate()
            ){
                return $this->orderService->getOrderByStatus( 'updatedAt', $this->request->paths[3], $this->request->paths[4]);
            }if(
                isset($this->request->paths[1]) && 
                isset($this->request->paths[2]) &&
                isset($this->request->paths[3]) &&
                isset($this->request->paths[4]) &&
                isset($this->request->paths[5]) &&  
                $this->request->paths[2] === 'filter-status-date' && 
                AuthGuard::authenticate() 
            ){
                return $this->orderService->getOrderByStatusAndDate( $this->request->paths[3], $this->request->paths[4], $this->request->paths[5], 'updatedAt');
            }if(
                isset($this->request->paths[1]) && 
                isset($this->request->paths[2]) &&
                isset($this->request->paths[3]) &&
                isset($this->request->paths[4]) &&
                isset($this->request->paths[5]) && 
                $this->request->paths[2] === 'filter-recent-status-date' && 
                AuthGuard::authenticate() 
            ){
                return $this->orderService->getOrderByStatusAndDate( $this->request->paths[3], $this->request->paths[4], $this->request->paths[5], 'createdAt');
            }else{
                return json_encode(["message"=>"Route not found"]);
            }
        }

        if($this->request->method === 'POST'){
            if(isset($this->request->paths[2]) && isset($this->request->paths[1])){
                switch($this->request->paths[2]){
                    case 'create-order':
                        if(AuthGuard::authenticate()){
                            return $this->orderService->createOrder($this->request->body, $this->request->paths[1]);
                            break;
                        }
                    case 'update-status':
                        if(AuthGuard::authenticate()){
                            return $this->orderService->updateOrderStatus($this->request->body, $this->request->paths[1]);
                            break;
                        }
                    default:
                        return json_encode(["message"=>"Route not found"]);
                        break;
                }
            }else{
                return json_encode(["message"=>"Route not found"]);
            }
        }
        if($this->request->method === 'DELETE'){
            if($this->request->paths[2] === 'delete-order' && AuthGuard::authenticate() && (RoleGuard::roleGuard('OWNER') || RoleGuard::roleGuard('ADMIN'))){
                return $this->orderService->deleteOrder($this->request->paths[3]);
            }
        }
        }catch(Exception $e){
            return json_encode(['error' => $e->getMessage()]);
        }
    }
        
}
?>