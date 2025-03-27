<?php
namespace src\common\constants;

class FormatRequest {
    public string $method;
    public string $uri;
    public array $headers;
    public string $body;
    public string $adress;
    public array $paths;

    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD']; 
        $this->uri = $_SERVER['REQUEST_URI']; 
        $this->headers = getallheaders(); 
        $this->body = file_get_contents("php://input");
        $this->adress = parse_url($this->uri, PHP_URL_PATH);
        $this->paths = explode('/', trim($this->adress, '/'));
    }
}
?>
