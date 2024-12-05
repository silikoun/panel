<?php
require_once __DIR__ . '/../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class JwtHandler {
    private $secretKey;
    private $algorithm;
    private $issuer;
    
    public function __construct() {
        $this->secretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-here';
        $this->algorithm = 'HS256';
        $this->issuer = 'woo-scraper-panel';
    }
    
    public function generateToken($payload) {
        $issuedAt = time();
        $tokenData = array(
            'iat' => $issuedAt,
            'iss' => $this->issuer,
            'nbf' => $issuedAt,
            'data' => $payload
        );
        
        if (isset($payload['exp'])) {
            $tokenData['exp'] = $payload['exp'];
        }
        
        return JWT::encode($tokenData, $this->secretKey, $this->algorithm);
    }
    
    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function getTokenData($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return $decoded->data;
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function isTokenExpired($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            $now = time();
            return isset($decoded->exp) && $decoded->exp < $now;
        } catch (Exception $e) {
            return true;
        }
    }
}
?>
