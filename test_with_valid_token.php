<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/TokenManager.php';

use Firebase\JWT\JWT;

// Set environment variables
putenv('JWT_SECRET_KEY=+WQFBKMY3oH7qSqi0Vx+3kW5RA8PI/zCCTOCw8NFaELLVKNvtuxqVPadVTm5JqQIPjGbNT9FU1YT7juByrFSdg==');
putenv('SUPABASE_URL=https://kgqwiwjayaydewyuygxt.supabase.co');
putenv('SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.icrGci0zm7HppVhF5BNnXZiBwLgtj2s8am2cHOdwtho');

try {
    // Create a TokenManager instance
    $tokenManager = new TokenManager();
    
    // Get and decode the JWT secret
    $jwt_secret = getenv('JWT_SECRET_KEY');
    if (preg_match('/^[A-Za-z0-9+\/=]+$/', $jwt_secret)) {
        $jwt_secret = base64_decode($jwt_secret, true);
        if ($jwt_secret === false) {
            throw new Exception("Failed to decode JWT secret");
        }
        echo "Successfully decoded JWT secret\n\n";
    }
    
    // Create a valid token
    $payload = [
        'sub' => '123456',  // User ID
        'email' => 'test@example.com',
        'iat' => time(),
        'exp' => time() + 3600  // Expires in 1 hour
    ];
    
    $token = JWT::encode($payload, $jwt_secret, 'HS256');
    
    echo "Generated token: " . $token . "\n\n";
    
    // Test the token
    echo "Testing valid token:\n";
    $result = $tokenManager->validateToken($token);
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    // Test the provided token
    $providedToken = '8d257aab281c0179010f379fe992b3bc0068d68f78dd65e1a28258a8b95b96ed';
    
    echo "Testing provided token:\n";
    echo "Token: " . $providedToken . "\n\n";
    
    $result = $tokenManager->validateToken($providedToken);
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
