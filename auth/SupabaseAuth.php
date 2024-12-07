<?php

class SupabaseAuth {
    private $supabaseUrl;
    private $supabaseKey;
    private $supabaseServiceKey;
    private $jwtSecret;

    public function __construct() {
        $this->supabaseUrl = getenv('SUPABASE_URL');
        $this->supabaseKey = getenv('SUPABASE_KEY'); // anon key
        $this->supabaseServiceKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
        $this->jwtSecret = getenv('JWT_SECRET_KEY');
    }

    public function signIn($email, $password) {
        $client = new GuzzleHttp\Client();
        try {
            $response = $client->post($this->supabaseUrl . '/auth/v1/token?grant_type=password', [
                'headers' => [
                    'apikey' => $this->supabaseKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'email' => $email,
                    'password' => $password
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            throw new Exception($response->getBody());
        }
    }

    private function getUserSubscription($userId) {
        $client = new GuzzleHttp\Client();
        try {
            $response = $client->get($this->supabaseUrl . '/rest/v1/subscriptions?user_id=eq.' . $userId . '&order=created_at.desc&limit=1', [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey
                ]
            ]);

            $subscriptions = json_decode($response->getBody(), true);
            
            if (empty($subscriptions)) {
                return null;
            }

            return $subscriptions[0];
        } catch (Exception $e) {
            throw new Exception('Error checking subscription: ' . $e->getMessage());
        }
    }

    public function generateApiKey($userId) {
        // Check subscription status
        $subscription = $this->getUserSubscription($userId);
        
        if (!$subscription || $subscription['status'] !== 'active') {
            throw new Exception('No active subscription found');
        }

        $apiKey = bin2hex(random_bytes(32));
        
        // Set expiration based on subscription end date
        $expiresAt = $subscription['current_period_end'] ?? date('Y-m-d H:i:s', strtotime('+999 years'));

        $client = new GuzzleHttp\Client();
        try {
            $response = $client->patch($this->supabaseUrl . '/rest/v1/users?id=eq.' . $userId, [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
                ],
                'json' => [
                    'api_token' => $apiKey,
                    'api_token_expires' => $expiresAt,
                    'subscription_id' => $subscription['id'] ?? null
                ]
            ]);

            if ($response->getStatusCode() === 204) {
                return [
                    'api_key' => $apiKey,
                    'expires_at' => $expiresAt,
                    'subscription' => [
                        'id' => $subscription['id'],
                        'plan' => $subscription['plan'],
                        'status' => $subscription['status'],
                        'current_period_end' => $subscription['current_period_end']
                    ]
                ];
            }

            throw new Exception('Failed to update API key');
        } catch (Exception $e) {
            throw new Exception('Error generating API key: ' . $e->getMessage());
        }
    }

    public function verifyApiKey($apiKey) {
        $client = new GuzzleHttp\Client();
        try {
            // Get user by API key
            $response = $client->get($this->supabaseUrl . '/rest/v1/users?api_token=eq.' . $apiKey, [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey
                ]
            ]);

            $users = json_decode($response->getBody(), true);
            
            if (empty($users)) {
                return false;
            }

            $user = $users[0];
            
            // Check subscription status
            $subscription = $this->getUserSubscription($user['id']);
            
            if (!$subscription || $subscription['status'] !== 'active') {
                return false;
            }

            // Update expiration if needed
            if ($subscription['current_period_end'] !== $user['api_token_expires']) {
                $this->updateApiKeyExpiration($user['id'], $subscription['current_period_end']);
            }

            return [
                'user' => $user,
                'subscription' => $subscription
            ];
        } catch (Exception $e) {
            return false;
        }
    }

    private function updateApiKeyExpiration($userId, $newExpiration) {
        $client = new GuzzleHttp\Client();
        try {
            $client->patch($this->supabaseUrl . '/rest/v1/users?id=eq.' . $userId, [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
                ],
                'json' => [
                    'api_token_expires' => $newExpiration
                ]
            ]);
        } catch (Exception $e) {
            // Log error but don't throw - this is a background update
            error_log('Error updating API key expiration: ' . $e->getMessage());
        }
    }

    public function refreshToken($refreshToken) {
        $client = new GuzzleHttp\Client();
        try {
            $response = $client->post($this->supabaseUrl . '/auth/v1/token?grant_type=refresh_token', [
                'headers' => [
                    'apikey' => $this->supabaseKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'refresh_token' => $refreshToken
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw new Exception('Error refreshing token: ' . $e->getMessage());
        }
    }

    public function generateJWT($userId, $email) {
        $issuedAt = time();
        $expiresAt = $issuedAt + (60 * 60 * 24); // 24 hours

        $payload = [
            'iss' => $this->supabaseUrl,
            'sub' => $userId,
            'email' => $email,
            'iat' => $issuedAt,
            'exp' => $expiresAt
        ];

        return \Firebase\JWT\JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    public function verifyJWT($token) {
        try {
            return \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->jwtSecret, 'HS256'));
        } catch (Exception $e) {
            return false;
        }
    }
}
