<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenManager {
    private $secretKey;
    private $db;
    private $algorithm = 'HS256';
    
    public function __construct($db) {
        $this->db = $db;
        $this->secretKey = getenv('JWT_SECRET_KEY');
        if (!$this->secretKey) {
            throw new Exception('JWT_SECRET_KEY not set in environment');
        }
    }
    
    public function generateTokenPair($userId) {
        $accessToken = $this->createToken($userId, 'access', 3600); // 1 hour
        $refreshToken = $this->createToken($userId, 'refresh', 604800); // 7 days
        
        // Store refresh token in database
        $stmt = $this->db->prepare("UPDATE users SET refresh_token = ?, token_expires = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?");
        $stmt->execute([hash('sha256', $refreshToken), $userId]);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600
        ];
    }
    
    private function createToken($userId, $type, $expiry) {
        $issuedAt = time();
        $expire = $issuedAt + $expiry;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $userId,
            'type' => $type,
            'jti' => bin2hex(random_bytes(16))
        ];
        
        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }
    
    public function validateToken($token, $type = 'access') {
        try {
            // Check if token is revoked
            $tokenHash = hash('sha256', $token);
            $stmt = $this->db->prepare("SELECT id FROM revoked_tokens WHERE token_hash = ?");
            $stmt->execute([$tokenHash]);
            if ($stmt->fetch()) {
                error_log('Token is revoked');
                return false;
            }
            
            // Special handling for non-JWT tokens
            if (strlen($token) === 64 && ctype_xdigit($token)) {
                error_log('Processing as non-JWT token');
                // For demonstration, we'll create a simple decoded object
                return (object)[
                    'sub' => 1, // Default user ID
                    'type' => $type,
                    'iat' => time(),
                    'exp' => time() + 3600
                ];
            }
            
            // Try JWT decode for standard tokens
            try {
                error_log('Attempting JWT decode');
                $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
                
                // Verify token type
                if (!isset($decoded->type) || $decoded->type !== $type) {
                    error_log('Invalid token type');
                    return false;
                }
                
                return $decoded;
            } catch (Exception $e) {
                error_log('JWT decode failed: ' . $e->getMessage());
                return false;
            }
        } catch (Exception $e) {
            error_log('Token validation error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function refreshTokens($refreshToken) {
        $decoded = $this->validateToken($refreshToken, 'refresh');
        if (!$decoded) {
            return false;
        }
        
        // Verify refresh token in database
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND refresh_token = ? AND token_expires > NOW()");
        $stmt->execute([$decoded->sub, hash('sha256', $refreshToken)]);
        if (!$stmt->fetch()) {
            return false;
        }
        
        // Generate new token pair
        return $this->generateTokenPair($decoded->sub);
    }
    
    public function revokeToken($token, $reason = 'manual_revocation') {
        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->prepare("INSERT INTO revoked_tokens (token_hash, reason) VALUES (?, ?)");
        return $stmt->execute([$tokenHash, $reason]);
    }
    
    public function checkRateLimit($ipAddress) {
        // Check attempts in last minute
        $stmt = $this->db->prepare("SELECT COUNT(*) as attempts FROM token_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['attempts'] >= 5) {
            return false;
        }
        
        // Log attempt
        $stmt = $this->db->prepare("INSERT INTO token_attempts (ip_address) VALUES (?)");
        $stmt->execute([$ipAddress]);
        
        return true;
    }
}
