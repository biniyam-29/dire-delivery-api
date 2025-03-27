<?php
namespace src\config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $conn = null;
    private static string $host = "localhost";
    private static string $dbname = "diredegd_mydb";
    private static string $username = "diredegd_root";
    private static string $password = "dire@delivery";

    public static function connect(): PDO {
        if (self::$conn === null) {
            try {
                $conn = new PDO("mysql:host=" . self::$host, self::$username, self::$password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $conn->exec("CREATE DATABASE IF NOT EXISTS " . self::$dbname);

                self::$conn = new PDO("mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8", self::$username, self::$password);
                self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sqlFile = __DIR__ . "/db.sql"; 
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    self::$conn->exec($sql);
                } else {
                    die("Error: db.sql file not found.");
                }
            } catch (PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return self::$conn;
    }
}
?>