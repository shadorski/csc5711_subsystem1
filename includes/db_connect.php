<?php

class Database {
    private $conn;

    public function __construct($host, $user, $pass, $dbname) {
        $this->conn = new mysqli($host, $user, $pass, $dbname);
        if ($this->conn->connect_error) {
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection() {
        return $this->conn;
    }
}

    return new Database(
        'localhost',      // Host
        'root',      // Username
        '',  // Password
        'subsystem1'   // Database name
    );
