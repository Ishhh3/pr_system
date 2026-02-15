<?php

// Database Configuration
// Establishes connection to MySQL database using PDO


class Database {
    private $host = "localhost";
    // private $host = "sql207.infinityfree.com";

    private $db_name = "item_request_system";
    // private $db_name = "if0_41138974_item_request_system";

    private $username = "root";
    // private $username = "if0_41138974";

    private $password = "";
    // private $password = "WqNZ4VfkGc1";
    
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
        
        return $this->conn;
    }
}

// Create global database instance
$database = new Database();
$db = $database->getConnection();
?>