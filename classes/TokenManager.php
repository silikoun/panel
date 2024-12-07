<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenManager {
    private $secretKey;
    private $supabaseUrl;
    private $supabaseKey;
    private $algorithm = 'HS256';
    
    public function __construct() {
        // Set default values for environment variables if not set
        if (!getenv('JWT_SECRET_KEY')) {
            putenv('JWT_SECRET_KEY=+WQFBKMY3oH7qSqi0Vx+3kW5RA8PI/zCCTOCw8NFaELLVKNvtuxqVPadVTm5JqQIPjGbNT9FU1YT7juByrFSdg==');
        }
        if (!getenv('SUPABASE_URL')) {
            putenv('SUPABASE_URL=https://kgqwiwjayaydewyuygxt.supabase.co');
        }
        if (!getenv('SUPABASE_SERVICE_ROLE_KEY')) {
            putenv('SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.icrGci0zm7HppVhF5BNnXZiBwLgtj2s8am2cHOdwtho');
        }
        
        $this->secretKey = getenv('JWT_SECRET_KEY');
        $this->supabaseUrl = getenv('SUPABASE_URL');
        $this->supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
        
        // Decode base64-encoded JWT secret if needed
        if ($this->secretKey && preg_match('/^[A-Za-z0-9+\/=]+$/', $this->secretKey)) {
            $decoded = base64_decode($this->secretKey);
            if ($decoded !== false) {
                $this->secretKey = $decoded;
            }
        }
        
        error_log("TokenManager initialized successfully");
    }
    
    public function validateToken($token) {
        try {
            // First, try to validate as JWT token
            try {
                $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
                $userId = $decoded->sub;
                
                // Get user from database
                $user = $this->getUserFromDatabase($userId);
                if (!$user) {
                    return ['valid' => false, 'message' => 'User not found'];
                }
                
                return ['valid' => true, 'user' => $user];
            } catch (\Exception $e) {
                // If JWT validation fails, try as hashed token
                return $this->validateHashedToken($token);
            }
        } catch (\Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function validateHashedToken($token) {
        try {
            // Query the database for a user with this token
            $ch = curl_init($this->supabaseUrl . '/rest/v1/users?api_token=eq.' . urlencode($token));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $this->supabaseKey,
                    'Authorization: Bearer ' . $this->supabaseKey
                ]
            ]);
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($statusCode !== 200) {
                error_log("Supabase API error. Status code: " . $statusCode);
                return ['valid' => false, 'message' => 'Database error'];
            }
            
            $users = json_decode($response, true);
            
            if (empty($users)) {
                return ['valid' => false, 'message' => 'Invalid token'];
            }
            
            $user = $users[0];
            
            // Check if token is expired
            if (isset($user['api_token_expires'])) {
                $expiryDate = strtotime($user['api_token_expires']);
                if ($expiryDate < time()) {
                    return ['valid' => false, 'message' => 'Token expired'];
                }
            }
            
            return ['valid' => true, 'user' => $user];
            
        } catch (\Exception $e) {
            error_log("Error validating hashed token: " . $e->getMessage());
            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function getUserFromDatabase($userId) {
        try {
            $ch = curl_init($this->supabaseUrl . '/rest/v1/users?id=eq.' . urlencode($userId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $this->supabaseKey,
                    'Authorization: Bearer ' . $this->supabaseKey
                ]
            ]);
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($statusCode !== 200) {
                error_log("Supabase API error. Status code: " . $statusCode);
                return null;
            }
            
            $users = json_decode($response, true);
            return empty($users) ? null : $users[0];
            
        } catch (\Exception $e) {
            error_log("Error getting user from database: " . $e->getMessage());
            return null;
        }
    }
    
    private function supabaseRequest($endpoint, $method = 'GET', $data = null) {
        $ch = curl_init($this->supabaseUrl . $endpoint);
        
        $headers = [
            'Authorization: Bearer ' . $this->supabaseKey,
            'apikey: ' . $this->supabaseKey,
            'Content-Type: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Supabase API request failed: " . $error);
        }
        
        curl_close($ch);
        
        if ($statusCode >= 400) {
            throw new Exception("Supabase API error (HTTP $statusCode): " . $response);
        }
        
        return json_decode($response, true);
    }
    
    public function generateTokenPair($userId) {
        $accessToken = $this->createToken($userId, 'access', 3600); // 1 hour
        $refreshToken = $this->createToken($userId, 'refresh', 604800); // 7 days
        
        // Store refresh token in database
        $data = [
            'id' => $userId,
            'refresh_token' => hash('sha256', $refreshToken),
            'token_expires' => date('c', time() + 604800)
        ];
        
        $this->supabaseRequest('/rest/v1/users', 'PATCH', $data);
        
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
    
    public function revokeToken($token, $reason = 'manual_revocation') {
        $tokenHash = hash('sha256', $token);
        
        $data = [
            'token_hash' => $tokenHash,
            'reason' => $reason
        ];
        
        $this->supabaseRequest('/rest/v1/revoked_tokens', 'POST', $data);
        
        return true;
    }
    
    public function refreshTokens($refreshToken) {
        try {
            // Validate refresh token
            $decoded = $this->validateToken($refreshToken);
            
            // Check if refresh token exists in database
            $response = $this->supabaseRequest('/rest/v1/users?id=eq.' . $decoded['user']['id'] . '&refresh_token=eq.' . urlencode(hash('sha256', $refreshToken)));
            
            if (empty($response)) {
                throw new Exception('Invalid refresh token');
            }
            
            // Generate new token pair
            return $this->generateTokenPair($decoded['user']['id']);
            
        } catch (Exception $e) {
            error_log("Token refresh error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function checkRateLimit($ipAddress, $maxAttempts = 10, $timeWindow = 300) {
        try {
            // Clean up old entries
            $this->supabaseRequest(
                '/rest/v1/rate_limit?attempt_time=lt.' . date('c', time() - $timeWindow),
                'DELETE'
            );
            
            // Count recent attempts
            $response = $this->supabaseRequest(
                '/rest/v1/rate_limit?ip_address=eq.' . urlencode($ipAddress) . '&attempt_time=gt.' . date('c', time() - $timeWindow)
            );
            
            $attempts = count($response);
            
            // Add new attempt
            $this->supabaseRequest('/rest/v1/rate_limit', 'POST', [
                'ip_address' => $ipAddress
            ]);
            
            return [
                'allowed' => $attempts < $maxAttempts,
                'remaining' => max(0, $maxAttempts - $attempts - 1),
                'reset' => time() + $timeWindow
            ];
            
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            // If there's an error checking rate limit, allow the request
            return ['allowed' => true, 'remaining' => 1, 'reset' => time() + $timeWindow];
        }
    }
}
