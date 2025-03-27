<?php
require_once 'db-config.php';
require_once 'vendor/autoload.php';

use src\config\Database;
use Faker\Factory;

function resetDatabase($conn) {
    try {
        $conn->exec("DROP DATABASE IF EXISTS diredegd_mydb");
        $conn->exec("CREATE DATABASE diredegd_mydb");
        $conn->exec("USE diredegd_mydb"); 

        $sqlFile = __DIR__ . "/db.sql"; 
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $conn->exec($sql);
            echo "✅ Database schema created successfully.\n";
            main($conn);  
        } else {
            die("❌ Error: db.sql file not found.");
        }
    } catch (PDOException $e) {
        die("❌ Database reset failed: " . $e->getMessage());
    }
}

function seedOwner($conn) {
    try {
        $sql = "INSERT INTO users (id, email, password, role, isDeleted) VALUES (?, ?, ?, ?, ?)";

        $id = trim($conn->query("SELECT UUID()")->fetchColumn());
        $hashedPassword = password_hash('dire-delivery-owner', PASSWORD_BCRYPT);

        $stmt = $conn->prepare($sql);
        $stmt->execute([$id, 'Owner@dire.com', $hashedPassword, 'OWNER', true]);
        echo "✅ Owner seeded successfully.\n";
    } catch (PDOException $e) {
        echo "❌ Error seeding owner: " . $e->getMessage() . "\n";
    }
}

function seedLocation($conn) {
    try {
        $sql = "INSERT INTO location (id, name, code) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        $locations = [
            ['Addis Ababa', 'ETAA'],
            ['Dire Dawa', 'ETDD'],
            ['Jigjiga', 'ETJJ'],
            ['Wajale', 'ETWJ']
        ];
        
        foreach ($locations as $location) {
            $id = trim($conn->query("SELECT UUID()")->fetchColumn());
            $stmt->execute([$id, $location[0], $location[1]]);
        }

        echo "✅ Location seeded successfully.\n";
    } catch (PDOException $e) {
        echo "❌ Error seeding location: " . $e->getMessage() . "\n";
    }
}

function seedprice($conn){
    try{
        $sql = "REPLACE INTO price (price, supportTel) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([200, '0912345678']);
        echo "✅ Price seeded successfully.\n";
    }catch (PDOException $e) {
        echo "❌ Error seeding price: " . $e->getMessage() . "\n";
    }
}

function seedConstants($conn) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM constants");
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            $sql = "INSERT INTO constants (lastTrxCode) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([10000]);
            echo "✅ Constants seeded successfully.\n";
        } else {
            echo "⚠️ Constants already exist, skipping seeding.\n";
        }
    } catch (PDOException $e) {
        echo "❌ Error seeding constants: " . $e->getMessage() . "\n";
    }
}


function main($conn) {
    $faker = Factory::create();
    try {
        $conn->beginTransaction();

        seedOwner($conn);
        seedLocation($conn);
        seedprice($conn);
        seedConstants($conn);

        $conn->commit();
        echo "✅ Data seeded successfully.\n";
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "❌ Error seeding data: " . $e->getMessage() . "\n";
    }
}

try {
    $conn = Database::connect();
    resetDatabase($conn);
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit;
}
?>
