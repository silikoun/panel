<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    public function __construct() {
        // Parse Supabase connection string
        $url = parse_url(getenv('SUPABASE_URL'));
        $this->host = str_replace('.supabase.co', '.supabase.co:5432', $url['host']);
        $this->db_name = 'postgres';
        $this->username = 'postgres';
        $this->password = getenv('SUPABASE_SERVICE_ROLE_KEY');
        $this->port = 5432;
    }

    public function connect() {
        $this->conn = null;

        try {
            $dsn = "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";sslmode=require;";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration: " . $e->getMessage());
        }
    }
}
