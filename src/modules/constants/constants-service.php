<?php
namespace src\modules\constants;

require_once 'src/config/db-config.php';

use src\config\Database;
use PDO;
use Exception;

class ConstantsService{
    private PDO $conn;
    public function __construct(){
        $this->conn = Database::connect();
    }

    public function updateConstants($id, $body) {
        try {
            $data = json_decode($body);
            if(!$data) {
                return json_encode(['error' => 'Invalid JSON data']);
            }
            $sql = "SELECT * FROM price WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return json_encode(['error' => 'Constants not found']);
            }

            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($key === 'id') {
                    continue;
                }

                $fields[] = "$key = COALESCE(NULLIF(?, ''), $key)";
                $values[] = $value;
            }
            if (empty($fields)) {
                return json_encode(['error' => 'No fields to update']);
            }

            $sql = "UPDATE price SET " . implode(", ", $fields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($sql);                       
            $stmt->execute([...$values, $id]);

            return json_encode(['message' => 'Constants updated successfully']);
        } catch (PDOException $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }


    public function getConstants(){
        try{
            $sql = "SELECT * FROM price";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if(!$result){
                return json_encode(['error' => 'Constants not found']);
            }
            return json_encode($result[0]);
        }catch(Exception $e){
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function deleteLocation($code){
        try{
            $sql = "DELETE FROM location WHERE location.code = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$code]);
            if ($stmt->rowCount() > 0) {
                return json_encode(['message' => 'Location deleted successfully!']);
            } else {
                return json_encode(['error' => 'Location not found!']);
            }
        }catch(Exception $e){
            return json_encode(["error"=>$e->getMessage()]);
        }
    }

    public function updateLocation($body, $code){
        try{
            $data = json_decode($body);
            $sql = "UPDATE location SET name = ?, code = ? WHERE code = ? ";
            if(!isset($data->name) || !isset($data->code) || !$data){
                return json_encode(['error'=>'invalid body contents!']);
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$data->name, $data->code, $code]);
            if ($stmt->rowCount() > 0) {
                return json_encode(['message' => 'Location updated successfully!']);
            } else {
                return json_encode(['error' => 'Location not found!']);
            }
        }catch(Exception $e){
            return json_encode(["error"=>$e->getMessage()]);
        }
    }

    public function addLocation($body){
        try{
            $data = json_decode($body);
            if(!$data || !$data->name || !$data->code){
                return json_encode(['error' => 'Invalid JSON data']);
            }
            $data->code = strtoupper($data->code);
            $id = trim($this->conn->query("SELECT UUID()")->fetchColumn());
            $sql = "INSERT INTO location (id, name, code) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id, $data->name, $data->code]);
            return json_encode(['message' => 'Location added successfully']);
        }catch(Exception $e){
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}
?>