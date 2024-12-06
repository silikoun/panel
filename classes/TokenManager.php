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
            // Log validation attempt
            error_log("Attempting to validate {$type} token");
            
            // Trim the token to handle whitespace issues
            $token = trim($token);
            
            // Check if token is empty after trimming
            if (empty($token)) {
                throw new Exception('Empty token provided');
            }

            // Check if token is revoked
            $stmt = $this->db->prepare("SELECT * FROM revoked_tokens WHERE token_hash = ?");
            $tokenHash = hash('sha256', $token);
            $stmt->execute([$tokenHash]);
            
            if ($stmt->fetch()) {
                error_log("Token validation failed: Token is revoked");
                throw new Exception('Token has been revoked');
            }

            // Decode and verify the token
            try {
                $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            } catch (\Firebase\JWT\ExpiredException $e) {
                error_log("Token validation failed: Token has expired");
                throw new Exception('Token has expired');
            } catch (\Firebase\JWT\SignatureInvalidException $e) {
                error_log("Token validation failed: Invalid signature");
                throw new Exception('Invalid token signature');
            } catch (Exception $e) {
                error_log("Token validation failed: " . $e->getMessage());
                throw new Exception('Invalid token format');
            }

            // Verify token type
            if (!isset($decoded->type) || $decoded->type !== $type) {
                error_log("Token validation failed: Invalid token type");
                throw new Exception('Invalid token type');
            }

            // Log successful validation
            error_log("Token successfully validated for user ID: {$decoded->sub}");
            
            return $decoded;
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            throw $e;
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
    
    public function checkRateLimit($ipAddress, $maxAttempts = 10, $timeWindow = 300) {
        try {
            // Clean up old attempts
            $stmt = $this->db->prepare("DELETE FROM validation_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? SECOND)");
            $stmt->execute([$timeWindow]);

            // Count recent attempts
            $stmt = $this->db->prepare("SELECT COUNT(*) as attempts FROM validation_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)");
            $stmt->execute([$ipAddress, $timeWindow]);
            $result = $stmt->fetch();

            if ($result['attempts'] >= $maxAttempts) {
                error_log("Rate limit exceeded for IP: {$ipAddress}");
                return false;
            }

            // Record new attempt
            $stmt = $this->db->prepare("INSERT INTO validation_attempts (ip_address) VALUES (?)");
            $stmt->execute([$ipAddress]);

            return true;
        } catch (Exception $e) {
            error_log("Rate limiting error: " . $e->getMessage());
            // If there's an error checking rate limit, allow the request
            return true;
        }
    }
}
