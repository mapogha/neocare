<?php
// Database Configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'neocare_system';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// SMS Configuration (mshastra.com)
class SMSConfig {
    public static $api_key = 'your_mshastra_api_key';
    public static $sender_id = 'NEOCARE';
    public static $api_url = 'https://api.mshastra.com/sendsms';
}

// System Configuration
class SystemConfig {
    public static $app_name = 'NeoCare System';
    public static $app_version = '1.0.0';
    public static $timezone = 'Africa/Dar_es_Salaam';
    public static $date_format = 'Y-m-d';
    public static $datetime_format = 'Y-m-d H:i:s';
}

// Set timezone
date_default_timezone_set(SystemConfig::$timezone);
?>
