<?php
require_once 'src/modules/user/user-controller.php';
require_once 'src/main.php';
require_once 'src/modules/auth/auth-controller.php';
require_once 'src/modules/orders/orders-controller.php';
require_once 'src/modules/constants/constants-controller.php';

use src\modules\user\UserController;
use src\modules\auth\AuthController;
use src\modules\orders\OrdersController;
use src\modules\constants\ConstantsController;
use src\App;

$app = new App();

$app->registerRoute('users', UserController::class);
$app->registerRoute('auth', AuthController::class);
$app->registerRoute('orders', OrdersController::class);
$app->registerRoute('constants', ConstantsController::class);

$app->handleRequest();
?>
