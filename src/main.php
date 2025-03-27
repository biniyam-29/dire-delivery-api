<?php
namespace src;
require_once 'src/modules/controller-interface.php';

use src\modules\ControllerInterface;

class App  {
    private array $routes = [];

    public function registerRoute(string $path, string $controller): void {
        try{
            if (!is_subclass_of($controller, ControllerInterface::class)) {
                throw new \Exception("$controller must implement ControllerInterface");
            }
            $this->routes[$path] = $controller;
        }catch(\Exception $e){
            echo json_encode(['message' => $e->getMessage()]);
            exit;
        }
    }


    public function handleRequest(): void {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $paths = explode('/', trim($uri, '/'));

        $module = $paths[0] ?? '';

        if (isset($this->routes[$module])) {
            $controller = new $this->routes[$module]();
            $response = $controller->handleRequest();
            echo $response;
        } else {
            echo json_encode(['message' => 'Invalid endpoint']);
            exit;
        }
    }


}

?>