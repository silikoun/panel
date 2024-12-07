<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/TokenManager.php';

// Set environment variables
putenv('JWT_SECRET_KEY=+WQFBKMY3oH7qSqi0Vx+3kW5RA8PI/zCCTOCw8NFaELLVKNvtuxqVPadVTm5JqQIPjGbNT9FU1YT7juByrFSdg==');
putenv('SUPABASE_URL=https://kgqwiwjayaydewyuygxt.supabase.co');
putenv('SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.icrGci0zm7HppVhF5BNnXZiBwLgtj2s8am2cHOdwtho');

try {
    // Create a TokenManager instance
    $tokenManager = new TokenManager();
    
    // The token we want to test
    $token = '8d257aab281c0179010f379fe992b3bc0068d68f78dd65e1a28258a8b95b96ed';
    
    // First, let's check if we can find any users
    echo "Checking users in database:\n";
    $ch = curl_init('https://kgqwiwjayaydewyuygxt.supabase.co/rest/v1/users?select=*');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
            'Authorization: Bearer ' . getenv('SUPABASE_SERVICE_ROLE_KEY')
        ]
    ]);
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status code: $statusCode\n";
    $users = json_decode($response, true);
    echo "Users found: " . json_encode($users, JSON_PRETTY_PRINT) . "\n\n";
    
    if (!empty($users)) {
        $userId = $users[0]['id'];
        
        // Update the existing user with the token
        echo "Updating user $userId with token:\n";
        $ch = curl_init('https://kgqwiwjayaydewyuygxt.supabase.co/rest/v1/users?id=eq.' . $userId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'apikey: ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
                'Authorization: Bearer ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
                'Content-Type: application/json',
                'Prefer: return=representation'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'api_token' => $token,
                'api_token_expires' => date('c', time() + 86400) // Expires in 24 hours
            ])
        ]);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Status code: $statusCode\n";
        echo "Response: " . json_encode(json_decode($response, true), JSON_PRETTY_PRINT) . "\n\n";
        
        // Now test the token again
        echo "Testing token after update:\n";
        $result = $tokenManager->validateToken($token);
        echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No users found in the database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
