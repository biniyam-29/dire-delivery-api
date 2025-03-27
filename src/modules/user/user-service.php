<?php
namespace src\modules\user;

header("Content-Type: application/json");
require_once 'src/config/db-config.php';
require_once 'src/common/mailer.php';

use src\config\Database;
use PDO;
use src\common\Mailer;

class UserService {
    private PDO $conn;
    private $mailer;

    public function __construct() {
        $this->conn = Database::connect();
        $this->mailer = Mailer::getInstance();
    }

    public function getAllUsers($offsetValue = 0) {
        $offset = max(0, ($offsetValue - 1)) * 10;
        try{
            $stmt = $this->conn->prepare("SELECT id, name, email, role, image, location, phone, joinedAt FROM users WHERE isDeleted = 0 LIMIT 10 OFFSET :offset");
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($result)) {
                return json_encode(['error' => 'No users found']);
            }
            $sql = 'SELECT COUNT(*) AS total FROM users WHERE isDeleted = 0';
            $totalStmt = $this->conn->prepare($sql);
            $totalStmt->execute();
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC);
            $totalUsers = $total['total'];
            return json_encode(['users' => $result, 'totalPage' => ceil($totalUsers / 10), 'currentPage' => ($offset + 1), "totalUsers"=> $total['total'] ]);  

        }catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

   public function getUserByRole($role, $offsetValue) {
        $offset = max(0, ($offsetValue - 1)) * 10;

        try {
            $stmt = $this->conn->prepare(
                "SELECT id, name, email, role, image, location, phone, joinedAt 
                FROM users 
                WHERE role = :role AND isDeleted = 0
                LIMIT 10 OFFSET :offset"
            );
            $stmt->bindValue(':role', $role);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                return json_encode(['error' => 'User not found']);
            }

            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE role = :role AND isDeleted = 0");
            $stmt->bindValue(':role', $role);
            $stmt->execute();
            $total = (int) $stmt->fetchColumn();

            return json_encode([
                "users" => $result,
                "totalPage" => ceil($total / 10),
                "currentPage" => $offsetValue,
                "totalUsers"=> $total
            ]);

        } catch (PDOException $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }


    public function getUser($id) {
       try{
            $stmt = $this->conn->prepare("SELECT id, name, email, role, image, location, phone, joinedAt FROM users WHERE id = ? AND isDeleted = 0");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!isset($result)){
                return json_encode(['error' => 'User not found']);
            }
            return json_encode($result);
       }catch(PDOException $e){
            return json_encode(['error' => $e->getMessage()]);
       }
    }

    public function updateUser($id, $body) {
        try {
            $data = json_decode($body);
            $user = $this->read($id, 'users');
            if(empty($user)){
                return json_encode(['error' => 'User not found']);
            }

            if ($user['role'] === "OWNER") {
                return json_encode(['error' => 'Cannot update owner']);
            }

            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($key === 'id' || $key === 'role' || $key === 'email') {
                    continue;
                }

                $fields[] = "$key = COALESCE(NULLIF(?, ''), $key)";
                $values[] = $value;
            }

            if (empty($fields)) {
                return json_encode(['error' => 'No fields to update']);
            }

            $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([...$values, $id]);

            return json_encode(['message' => 'User updated successfully']);
        } catch (PDOException $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function searchByName($name, $offsetValue = 0){
        $offset = max(0, ($offsetValue - 1)) * 10;
        try{

            $stmt = $this->conn->prepare("SELECT id, name, email, role, image, location, phone, joinedAt FROM users WHERE name LIKE :name AND isDeleted = 0 LIMIT 10 OFFSET $offset");
            $stmt->bindValue(':name', "%$name%");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                return json_encode(['error' => 'User not found']);
            }
            
            $sql = "SELECT COUNT(*) FROM users WHERE name LIKE :name AND isDeleted = 0";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':name', "%$name%", PDO::PARAM_STR);
            $stmt->execute();
            $total = (int) $stmt->fetchColumn();

            return json_encode([
                "users" => $result,
                "totalPage" => ceil($total / 10),
                "currentPage" => $offsetValue,
                "totalUsers"=> $total ?? 0
            ]);
        }catch(Exception $e){
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function changeRole($body, $id) {
        try{
            $data = json_decode($body);
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$data->userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if($user['role'] === "OWNER" || $data->role === "OWNER"){
                return json_encode(['error' => 'Cannot change role of owner']);
            }
            if($user['id'] === $id){
                return json_encode(["error"=>"you cannot change your own role!"]);
            }
            if(!isset($data->role) || !isset($data->userId) || $data->role == "OWNER"){
                return json_encode(['error' => 'Invalid data']);
            }
            
            $stmt = $this->conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$data->role, $data->userId]);
            return json_encode(['message' => 'Role updated successfully']);
        }catch(PDOException $e){
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function deleteUser($id) {
        try{
            $user = $this->read($id, 'users', "id");
            if(!isset($user)){
                return json_encode(['error' => 'User not found']);
            }
            if($user['role'] === "OWNER"){
                return json_encode(['error' => 'Cannot delete owner']);
            }
            $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return json_encode(['message' => 'User deleted successfully']);
        }catch(PDOException $e){
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    private function read($id, $table, $column = 'id'){
        $stmt = $this->conn->prepare("SELECT * FROM $table WHERE $column = ? AND isDeleted = 0");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function check(){
        $stmt = $this->conn->prepare("SELECT id, name, email, phone, role, image, location FROM users");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return json_encode($result);
    }
}
?>