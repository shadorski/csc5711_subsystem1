<?php
class Database {
    private $conn;

    public function __construct($host, $user, $pass, $dbname) {
        error_log("[DB DEBUG] Attempting connection to $host, db: $dbname\n");
        try {
            $this->conn = new mysqli($host, $user, $pass, $dbname);
            if ($this->conn->connect_error) {
                error_log("[DB ERROR] Connection failed: " . $this->conn->connect_error . "\n");
                $this->conn = null;
            } else {
                $this->conn->set_charset("utf8mb4");
            }
        } catch (mysqli_sql_exception $e) {
            error_log("[DB ERROR] Connection failed: " . $e->getMessage() . "\n");
            $this->conn = null;
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function isConnected() {
        return $this->conn !== null && !$this->conn->connect_error;
    }
}

$db = new Database(
    'localhost',
    'root',
    '',
    'subsystem1'  // Update to your actual DB name
);

return $db;