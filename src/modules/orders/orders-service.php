<?php
namespace src\modules\Orders;

require_once 'src/config/db-config.php';

use PDO;
use src\config\Database;
use Exception;

class OrdersService {
    private PDO $conn;

    public function __construct(){
        $this->conn = Database::connect();
    }

    public function createOrder($body, $employeeId){
        $data = json_decode($body);

        $sender = $this->getOrCreateSender($data);
        if (!isset($sender)) {
            $sender = $this->getOrCreateSender($data);
        }

        $receiverId = $this->getReceiverId($data);
        if (!$receiverId) {
            return "Receiver not found.";
        }

        $itemId = $this->generateUUID();
        $this->insertItem($itemId, $data->weight, $data->description, $data->quantity, $sender['totalAmount']);

        $isPaid = ($data->paymentMethod === 'Now') ? 1 : 0;
        $status = "Pending";
        $orderId = $this->generateUUID();
        $this->insertOrder($orderId, $itemId, $sender['id'], $receiverId, $isPaid, $employeeId, $status);
        
        $transactionId = $this->generateUUID();
        $this->insertTransaction($transactionId, $orderId, $sender['code']);

        $statusId = $this->generateUUID();
        $this->insertStatus($statusId, $orderId, $sender['location'], 'Pending');

        return $this->getOrderByTransaction($sender['code']);
    }

    private function getOrCreateSender($data){
        try {
            $customer = $this->read('customers', 'email', $data->senderEmail);
            if ($customer) {
                return $this->updateSenderWeightAndCalculateTotal($customer, $data);
            }
            
            $this->insertNewSender($data);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function updateSenderWeightAndCalculateTotal($customer, $data){
        try {
            $weight = $customer['weight'] + $data->weight;
            $loyalty = $weight % 10;
            $discount = $loyalty != $weight;
            $newWeight = $discount ? $loyalty : $weight;

            $this->updateCustomerWeight($newWeight, $data->senderEmail);
            $price = $this->getPrice();
            $totalAmount = abs($data->weight * $price - ($discount ? 2 * $price : 0));
            $totalAmount = $totalAmount == 0 ? 200 : $totalAmount;
            if(!isset($totalAmount)){
                $totalAmount = abs($data->weight * $price - ($discount ? 2 * $price : 0));
                $totalAmount = $totalAmount == 0 ? 200 : $totalAmount;
            }
            return [
                'totalAmount' => $totalAmount,
                'id' => $customer['id'],
                'location'=> $data->senderAddress,
                'code' => $this->getLocationCode($data->senderAddress)
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getReceiverId($data){
        try{
            $receiver = $this->read('customers', 'email', $data->reciverEmail);
            if ($receiver) {
                return $receiver['id'];
            }
            return $this->insertNewReceiver($data)['id'];
        }catch (Exception $e){
            return ['error' => $e->getMessage()];
        }
    }

    private function getLocationCode($address) {

        $location = $this->read('location', 'name', $address);
        
        if (!$location || !isset($location['code'])) {
            throw new Exception("Invalid location or missing location code.");
        }

        $sql = 'SELECT lastTrxCode FROM constants';
        $stmt = $this->conn->query($sql);
        $lastCode = $stmt->fetchColumn();
        $lastCode += 1;
        $this->executeStatement('UPDATE constants SET lastTrxCode = :code', ['code' => $lastCode]);
        $trxCode = $location['code'] . $lastCode;

        return $trxCode;
    }


    private function insertOrder($orderId, $itemId, $senderId, $receiverId, $isPaid, $employeeId, $status){
        $sql = 'INSERT INTO orders (id, itemId, senderId, receiverId, isPaid, employeeId, status) VALUES (:id, :itemId, :senderId, :receiverId, :isPaid, :employeeId, :status)';
        $this->executeStatement($sql, [
            'id' => $orderId, 
            'itemId' => $itemId,
            'senderId' => $senderId, 
            'receiverId' => $receiverId, 
            'isPaid' => $isPaid, 
            'employeeId' => $employeeId,
            'status' => $status
        ]);
    }

    private function insertItem($itemId, $weight, $description, $quantity, $totalPrice){
        $sql = "INSERT INTO items (id, weight, description, quantity, totalPrice) VALUES (:id, :weight, :description, :quantity, :totalPrice)";
        $this->executeStatement($sql, ['id' => $itemId, 'weight' => $weight, 'description' => $description, 'quantity' => $quantity, 'totalPrice' => $totalPrice]);
    }

    private function insertTransaction($transactionId, $orderId, $code){
        $sql = "INSERT INTO transactions (id, orderId, code) VALUES (:id, :orderId, :code)";
        $this->executeStatement($sql, ['id' => $transactionId, 'orderId' => $orderId, 'code' => $code]);
    }

    private function insertStatus($statusId, $orderId, $location, $status){
        $sql = "INSERT INTO status (id, orderId, location, status) VALUES (:id, :orderId, :location, :status)";
        $this->executeStatement($sql, ['id' => $statusId, 'orderId' => $orderId, 'location' => $location, 'status' => $status]);
    }

    private function insertNewSender($data){
        $senderId = $this->generateUUID();
        $sql = 'INSERT INTO customers (id, name, email, phone, address, weight) VALUES (:id, :name, :email, :phone, :address, :weight)';
        $params = [
            'id' => $senderId,
            'name' => $data->senderName,
            'email' => $data->senderEmail,
            'phone' => $data->senderPhoneNumber,
            'address' => $data->senderAddress,
            'weight' => $data->weight
        ];
        $this->executeStatement($sql, $params);

        return $this->getOrCreateSender($data);
    }

    private function insertNewReceiver($data){
        $receiverId = $this->generateUUID();
        $sql = 'INSERT INTO customers (id, name, email, phone, address) VALUES (:id, :name, :email, :phone, :address)';
        $params = [
            'id' => $receiverId,
            'name' => $data->reciverName,
            'email' => $data->reciverEmail,
            'phone' => $data->reciverPhoneNumber,
            'address' => $data->reciverAddress,
        ];
        $this->executeStatement($sql, $params);
        return $this->read('customers', 'email', $data->reciverEmail);
    }

    private function updateCustomerWeight($weight, $email){
        $sql = 'UPDATE customers SET weight = :weight WHERE email = :email';
        $this->executeStatement($sql, ['weight' => $weight, 'email' => $email]);
    }

    private function getPrice(){
        return $this->executeStatement('SELECT price FROM price LIMIT 1')->fetchColumn();
    }

    private function generateUUID(){
        return trim($this->conn->query("SELECT UUID()")->fetchColumn());
    }

    private function executeStatement($sql, $params = []){
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindValue(":$key", $val);
        }
        $stmt->execute();
        return $stmt;
    }

   public function getAllOrders($offsetValue = 0){
        $offset = abs($offsetValue - 1);
        try {
            $sql = "SELECT 
                JSON_OBJECT(
                    'sender', JSON_OBJECT('name', sender.name, 'phone', sender.phone, 'address', sender.address, 'email', sender.email),
                    'receiver', JSON_OBJECT('name', receiver.name, 'phone', receiver.phone, 'address', receiver.address, 'email', receiver.email),
                    'status', JSON_ARRAYAGG(JSON_OBJECT('status', status.status, 'date', status.createdAt, 'location', status.location)),
                    'item', JSON_OBJECT('description', items.description, 'weight', items.weight, 'quantity', items.quantity, 'totalPrice', items.totalPrice),
                    'order', JSON_OBJECT('payment', orders.isPaid, 'transactionCode', MAX(transactions.code), 'status', MAX(orders.status), 'createdAT', MAX(orders.createdAt)),
                    'employeeInfo', JSON_OBJECT('name', users.name, 'email', users.email, 'phone', users.phone, 'location', users.location)
                ) AS orderDetails
            FROM orders
            JOIN customers AS sender ON orders.senderId = sender.id
            JOIN customers AS receiver ON orders.receiverId = receiver.id
            JOIN items ON orders.itemId = items.id
            JOIN transactions ON orders.id = transactions.orderId
            JOIN users ON orders.employeeId = users.id
            LEFT JOIN status ON orders.id = status.orderId
            GROUP BY orders.id
            LIMIT 10 OFFSET " . (int)($offset * 10);

            $result = $this->executeStatement($sql)->fetchAll(PDO::FETCH_ASSOC);
            if (empty($result)) {
                return json_encode(['error' => 'No orders found']);
            }

            $orders = array_map(function ($order) {
                $order['orderDetails'] = json_decode($order['orderDetails'], true);
                return $order;
            }, $result);

            $sql = "SELECT COUNT(*) AS totalOrders FROM orders";
            $totalorder = $this->executeStatement($sql)->fetch(PDO::FETCH_ASSOC)['totalOrders'];
            $totalPage = ceil($totalorder / 10);

            return json_encode(["orders" => $orders, "totalPage" => $totalPage, "currentPage" => ($offset + 1), "totalOrders"=>$totalorder]);
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }


    public function getOrderByTransaction($code){
        $transaction = $this->read('transactions', 'code', $code);
        if(!$transaction) {
            return json_encode(['error' => 'Transaction not found!']);
        }
        $order = $this->read('orders', 'id', $transaction['orderId']);
        if(!$order) {
            return json_encode(['error' => 'Order not found!']);
        }
        $sql = "SELECT 
                JSON_OBJECT(
                    'sender', JSON_OBJECT('name', sender.name, 'phone', sender.phone, 'address', sender.address, 'email', sender.email),
                    'receiver', JSON_OBJECT('name', receiver.name, 'phone', receiver.phone, 'address', receiver.address, 'email', receiver.email),
                    'status', JSON_ARRAYAGG(JSON_OBJECT('status', status.status, 'date', status.createdAt, 'location', status.location)),
                    'item', JSON_OBJECT('description', items.description, 'weight', items.weight, 'quantity', items.quantity, 'totalPrice', items.totalPrice),
                    'order', JSON_OBJECT('payment', orders.isPaid, 'transactionCode', MAX(transactions.code), 'status', MAX(orders.status), 'createdAT', MAX(orders.createdAt)),
                    'employeeInfo', JSON_OBJECT('name', users.name, 'email', users.email, 'phone', users.phone, 'location', users.location)
                ) AS orderDetails
            FROM orders
            JOIN customers AS sender ON orders.senderId = sender.id
            JOIN customers AS receiver ON orders.receiverId = receiver.id
            JOIN items ON orders.itemId = items.id
            JOIN transactions ON orders.id = transactions.orderId
            JOIN users ON orders.employeeId = users.id
            LEFT JOIN status ON orders.id = status.orderId
            WHERE orders.id = :id";
        $result = $this->executeStatement($sql, ['id' => $order['id']])->fetch(PDO::FETCH_ASSOC);
        if(empty($result)) {
            return json_encode(['error' => 'Order not found!']);
        }
        $order['orderDetails'] = json_decode($result['orderDetails'], true);

        return json_encode($order);
    }

    public function getOrderByStatus($filter = 'status', $value = 'Pending', $offsetValue = 1)
    {
        try {
            $offset = max(0, $offsetValue - 1) * 10;
            $allowedFilters = ['status', 'createdAt', 'updatedAt'];
            $value = (string) $value;
            if (!in_array($filter, $allowedFilters)) {
                return json_encode(['error' => 'Invalid filter provided!']);
            }
            if ($filter === 'createdAt' || $filter === 'updatedAt') {
                $column = "DATE(orders.$filter)";
            } else {
                $column = "orders." . $filter;
            }
            $sql = "SELECT 
                JSON_OBJECT(
                    'sender', JSON_OBJECT('name', sender.name, 'phone', sender.phone, 'address', sender.address, 'email', sender.email),
                    'receiver', JSON_OBJECT('name', receiver.name, 'phone', receiver.phone, 'address', receiver.address, 'email', receiver.email),
                    'status', JSON_ARRAYAGG(JSON_OBJECT('status', status.status, 'date', status.createdAt, 'location', status.location)),
                    'item', JSON_OBJECT('description', items.description, 'weight', items.weight, 'quantity', items.quantity, 'totalPrice', items.totalPrice),
                    'order', JSON_OBJECT('payment', orders.isPaid, 'transactionCode', MAX(transactions.code), 'status', MAX(orders.status), 'createdAT', MAX(orders.createdAt)),
                    'employeeInfo', JSON_OBJECT('name', users.name, 'email', users.email, 'phone', users.phone, 'location', users.location)
                ) AS orderDetails
            FROM orders
            JOIN customers AS sender ON orders.senderId = sender.id
            JOIN customers AS receiver ON orders.receiverId = receiver.id
            JOIN items ON orders.itemId = items.id
            JOIN transactions ON orders.id = transactions.orderId
            JOIN users ON orders.employeeId = users.id
            LEFT JOIN status ON orders.id = status.orderId
            WHERE $column = :value
            GROUP BY orders.id
            LIMIT 10 OFFSET $offset";

            $params = [
                'value' => $value
            ];

            $result = $this->executeStatement($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                return json_encode(['error' => 'No Order could be found!']);
            }

            $orders = array_map(function ($order) {
                $order['orderDetails'] = json_decode($order['orderDetails'], true);
                return $order;
            }, $result);

            $sql = "SELECT COUNT(*) AS totalOrders FROM orders WHERE $column = :value";
            $totalOrder = $this->executeStatement($sql, ['value' => $value])->fetch(PDO::FETCH_ASSOC)['totalOrders'];
            $totalPage = ceil($totalOrder / 10); 

            return json_encode(["orders" => $orders, "totalPage" => $totalPage, "currentPage" => $offsetValue, "totalOrders"=>$totalOrder]);
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getOrderByStatusAndDate($status, $date, $offsetValue, $type) {
        try {
            $offset = max(0, $offsetValue - 1) * 10;
            if($type === 'createdAt') {
                $column = "orders.status = :status AND DATE(orders.createdAt) = :date";
            }if($type === 'updatedAt'){
                $column = "orders.status = :status AND DATE(orders.updatedAt) = :date";
            }

            $sql = "SELECT 
                    JSON_OBJECT(
                        'sender', JSON_OBJECT('name', sender.name, 'phone', sender.phone, 'address', sender.address, 'email', sender.email),
                        'receiver', JSON_OBJECT('name', receiver.name, 'phone', receiver.phone, 'address', receiver.address, 'email', receiver.email),
                        'status', JSON_ARRAYAGG(JSON_OBJECT('status', status.status, 'date', status.createdAt, 'location', status.location)),
                        'item', JSON_OBJECT('description', items.description, 'weight', items.weight, 'quantity', items.quantity, 'totalPrice', items.totalPrice),
                        'order', JSON_OBJECT('payment', orders.isPaid, 'transactionCode', COALESCE(MAX(transactions.code), 'N/A'), 'status', orders.status, 'createdAT', orders.createdAt),
                        'employeeInfo', JSON_OBJECT('name', users.name, 'email', users.email, 'phone', users.phone, 'location', users.location)
                    ) AS orderDetails
                FROM orders
                JOIN customers AS sender ON orders.senderId = sender.id
                JOIN customers AS receiver ON orders.receiverId = receiver.id
                JOIN items ON orders.itemId = items.id
                JOIN transactions ON orders.id = transactions.orderId
                JOIN users ON orders.employeeId = users.id
                LEFT JOIN status ON orders.id = status.orderId
                WHERE $column
                GROUP BY orders.id
                LIMIT 10 OFFSET $offset";

            $params = [
                'status' => $status,
                'date' => $date
            ];

            $result = $this->executeStatement($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                return json_encode(['error' => 'No Order could be found!']);
            }

            $orders = array_map(function ($order) {
                $order['orderDetails'] = json_decode($order['orderDetails'], true);
                return $order;
            }, $result);

            $countSql = "SELECT COUNT(*) AS totalOrders FROM orders WHERE status = :status AND DATE(updatedAt) = :date";
            $totalOrder = $this->executeStatement($countSql, ['status' => $status, 'date' => $date])->fetch(PDO::FETCH_ASSOC)['totalOrders'];
            $totalPage = ceil($totalOrder / 10);

            return json_encode(["orders" => $orders, "totalPage" => $totalPage, "currentPage" => $offsetValue, "totalOrders"=>$totalOrder]);
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

 

    public function updateOrderStatus($body, $id){
        try{
            $data = json_decode($body);
            if (!isset($data->status) || !isset($data->trxCode)) {
                return json_encode(['error' => 'Invalid data']);
            }
            $user = $this->read('users', 'id', $id);
            if(!$user){
                return json_encode(['error' => 'User not found!']);
            }
            $transaction = $this->read('transactions', 'code', $data->trxCode);
            if(!$transaction){
                return json_encode(['error' => 'Transaction not found!']);
            }
            $order = $this->read('orders', 'id', $transaction['orderId']);
            if(empty($order)){
                return json_encode(['error' => 'Order not found!']);
            }
            $reciever = $this->read('customers', 'id', $order['receiverId']);
            if(!$order) {
                return json_encode(['error' => 'Order not found!']);
            }
            if($order['employeeId'] == $id || $user['role'] == "ADMIN" || $user['role'] == "OWNER"){
                return json_encode(["message"=>"This user can not update this order status!"]);
            }
            $sql = "UPDATE orders SET status = :status, updatedAt = :update WHERE id = :id";
            $this->executeStatement($sql, ['status' => $data->status, 'update' => date('Y-m-d H:i:s'), 'id' => $order['id']]);

            $statusId = $this->generateUUID();
            $this->insertStatus($statusId, $order['id'], $reciever['address'], $data->status);

            return json_encode(['message' => 'Order updated successfully']);
        }catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function deleteOrder($trxCode){
        try{
            $transaction = $this->read('transactions', 'code', $trxCode);
            $order = $this->read('orders', 'id', $transaction['orderId']);
            if(!$order) {
                return json_encode(['error' => 'Order not found!']);
            }
            $sql = "DELETE FROM status WHERE orderId = :id";
            $this->executeStatement($sql, ['id' => $order['id']]);
            $sql = "DELETE FROM transactions WHERE orderId = :id";
            $this->executeStatement($sql, ['id' => $order['id']]);
            $sql = "DELETE FROM orders WHERE id = :id";
            $this->executeStatement($sql, ['id' => $order['id']]);
            $sql = "DELETE FROM items WHERE id = :id";
            $this->executeStatement($sql, ['id' => $order['itemId']]);

            return json_encode(['message' => 'Order deleted successfully']);
        }catch(Exception $e){
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function preOrder(){
        try{
            $sql = "SELECT name, code FROM location";
            $locations = $this->executeStatement($sql)->fetchAll(PDO::FETCH_ASSOC);

            $sql = "SELECT * FROM price";
            $price = $this->executeStatement($sql)->fetch(PDO::FETCH_ASSOC);

            return json_encode(["price"=>$price, "locations"=>$locations]);
        }catch(Exception $e){
            return json_encode(["error"=>$e->getMessage()]);
        }
    }

    public function employeeOrder($employeeId, $offsetValue) {
        try {
            $limit = 10; 
            $offset = max(0, $offsetValue - 1) * 10;
            $limit = (int) $limit;
            $column = "orders.employeeId = :employeeId";

              $sql = "SELECT 
                    JSON_OBJECT(
                        'sender', JSON_OBJECT('name', sender.name, 'phone', sender.phone, 'address', sender.address, 'email', sender.email),
                        'receiver', JSON_OBJECT('name', receiver.name, 'phone', receiver.phone, 'address', receiver.address, 'email', receiver.email),
                        'status', JSON_ARRAYAGG(JSON_OBJECT('status', status.status, 'date', status.createdAt, 'location', status.location)),
                        'item', JSON_OBJECT('description', items.description, 'weight', items.weight, 'quantity', items.quantity, 'totalPrice', items.totalPrice),
                        'order', JSON_OBJECT('payment', orders.isPaid, 'transactionCode', COALESCE(MAX(transactions.code), 'N/A'), 'status', orders.status, 'createdAT', orders.createdAt),
                        'employeeInfo', JSON_OBJECT('name', users.name, 'email', users.email, 'phone', users.phone, 'location', users.location)
                    ) AS orderDetails
                FROM orders
                JOIN customers AS sender ON orders.senderId = sender.id
                JOIN customers AS receiver ON orders.receiverId = receiver.id
                JOIN items ON orders.itemId = items.id
                JOIN transactions ON orders.id = transactions.orderId
                JOIN users ON orders.employeeId = users.id
                LEFT JOIN status ON orders.id = status.orderId
                WHERE $column
                GROUP BY orders.id
                LIMIT 10 OFFSET $offset";
 

            $result = $this->executeStatement($sql, ['employeeId' => $employeeId])->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($result)) {
                return json_encode(['error' => 'No orders found']);
            }

            $orders = array_map(function ($order) {
                $order['orderDetails'] = json_decode($order['orderDetails'], true);
                return $order;
            }, $result);
            
            $sql = "SELECT COUNT(*) AS totalOrders FROM orders WHERE employeeId = :id";
            $totalOrder = $this->executeStatement($sql, ['id' => $employeeId])->fetch(PDO::FETCH_ASSOC)['totalOrders'];
            $totalPage = ceil($totalOrder / $limit);

            return json_encode(["orders" => $orders, "totalPage" => $totalPage, "currentPage" => $offsetValue, "totalOrders"=>$totalOrder]);
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }


    private function read($table, $column = 'id', $value = null){
        try {
            $sql = "SELECT * FROM $table WHERE $column = :value";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}
?>
