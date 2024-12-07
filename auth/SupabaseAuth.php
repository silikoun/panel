<?php

class SupabaseAuth {
    private $supabaseUrl;
    private $supabaseKey;
    private $supabaseServiceKey;
    private $jwtSecret;
    private $client;

    public function __construct() {
        $this->supabaseUrl = getenv('SUPABASE_URL');
        $this->supabaseKey = getenv('SUPABASE_KEY'); // anon key
        $this->supabaseServiceKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
        $this->jwtSecret = getenv('JWT_SECRET_KEY');
        $this->client = new GuzzleHttp\Client(['verify' => false]);
    }

    public function createClient() {
        return new SupabaseClient($this);
    }

    public function query($table) {
        return new QueryBuilder($this, $table);
    }

    public function executeQuery($method, $endpoint, $options = []) {
        try {
            $defaultHeaders = [
                'apikey' => $this->supabaseServiceKey,
                'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation'
            ];

            $options['headers'] = array_merge($defaultHeaders, $options['headers'] ?? []);
            
            $response = $this->client->request($method, $this->supabaseUrl . $endpoint, $options);
            
            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            error_log('Supabase query error: ' . $e->getMessage());
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }

    public function signIn($email, $password) {
        $response = $this->executeQuery('POST', '/auth/v1/token?grant_type=password', [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);

        $authData = $response;

        // Fetch user data including admin status
        $userResponse = $this->executeQuery('GET', '/rest/v1/users?select=*');

        $users = $userResponse;
        $currentUser = array_filter($users, function($user) use ($email) {
            return strtolower($user['email']) === strtolower($email);
        });

        $currentUser = reset($currentUser);
        
        // Merge auth data with user data
        $authData['user']['is_admin'] = isset($currentUser['is_admin']) ? $currentUser['is_admin'] : false;
        
        return $authData;
    }

    private function getUserSubscription($userId) {
        $response = $this->executeQuery('GET', '/rest/v1/subscriptions?user_id=eq.' . $userId . '&order=created_at.desc&limit=1');

        $subscriptions = $response;
        
        if (empty($subscriptions)) {
            // For backward compatibility, if no subscription exists, create a lifetime subscription
            return $this->createLifetimeSubscription($userId);
        }

        return $subscriptions[0];
    }

    private function createLifetimeSubscription($userId) {
        $subscription = [
            'user_id' => $userId,
            'status' => 'active',
            'plan' => 'legacy',
            'current_period_start' => date('Y-m-d H:i:s'),
            'current_period_end' => date('Y-m-d H:i:s', strtotime('+999 years'))
        ];

        $response = $this->executeQuery('POST', '/rest/v1/subscriptions', [
            'json' => $subscription
        ]);

        if ($response) {
            return $subscription;
        }

        throw new Exception('Failed to create lifetime subscription');
    }

    public function verifyApiKey($apiKey) {
        // First try to find user by new API key
        $response = $this->executeQuery('GET', '/rest/v1/users?api_token=eq.' . $apiKey);

        $users = $response;
        
        if (empty($users)) {
            // If not found, try to find by old token field
            $response = $this->executeQuery('GET', '/rest/v1/users?token=eq.' . $apiKey);
            
            $users = $response;
            
            if (!empty($users)) {
                // Found an old token, migrate it to new system
                $this->migrateOldToken($users[0]['id'], $apiKey);
            }
        }
        
        if (empty($users)) {
            return false;
        }

        $user = $users[0];
        
        // For old tokens or migrated tokens, create/get subscription
        $subscription = $this->getUserSubscription($user['id']);
        
        if (!$subscription || $subscription['status'] !== 'active') {
            return false;
        }

        // Update expiration if needed
        if (isset($subscription['current_period_end']) && 
            (!isset($user['api_token_expires']) || $subscription['current_period_end'] !== $user['api_token_expires'])) {
            $this->updateApiKeyExpiration($user['id'], $subscription['current_period_end']);
        }

        return [
            'user' => $user,
            'subscription' => $subscription
        ];
    }

    private function migrateOldToken($userId, $oldToken) {
        try {
            $this->executeQuery('PATCH', '/rest/v1/users?id=eq.' . $userId, [
                'json' => [
                    'api_token' => $oldToken,
                    'api_token_expires' => date('Y-m-d H:i:s', strtotime('+999 years')),
                    'token' => null // Clear old token field after migration
                ]
            ]);
        } catch (Exception $e) {
            error_log('Error migrating old token: ' . $e->getMessage());
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

        try {
            $this->executeQuery('PATCH', '/rest/v1/users?id=eq.' . $userId, [
                'json' => [
                    'api_token' => $apiKey,
                    'api_token_expires' => $expiresAt,
                    'subscription_id' => $subscription['id'] ?? null
                ]
            ]);

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
        } catch (Exception $e) {
            throw new Exception('Error generating API key: ' . $e->getMessage());
        }
    }

    private function updateApiKeyExpiration($userId, $newExpiration) {
        try {
            $this->executeQuery('PATCH', '/rest/v1/users?id=eq.' . $userId, [
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
        $response = $this->executeQuery('POST', '/auth/v1/token?grant_type=refresh_token', [
            'json' => [
                'refresh_token' => $refreshToken
            ]
        ]);

        return $response;
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

    public function generateExtensionToken($userId) {
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        
        // Current timestamp and expiration (30 days from now)
        $currentTime = time();
        $expirationTime = $currentTime + (30 * 24 * 60 * 60);
        
        // Deactivate any existing active tokens for this user
        $this->executeQuery('PATCH', '/rest/v1/extension_tokens', [
            'json' => [
                'is_active' => false
            ],
            'query' => [
                'user_id' => 'eq.' . $userId,
                'is_active' => 'eq.true'
            ]
        ]);

        // Store the new token
        $response = $this->executeQuery('POST', '/rest/v1/extension_tokens', [
            'json' => [
                'user_id' => $userId,
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s', $currentTime),
                'expires_at' => date('Y-m-d H:i:s', $expirationTime),
                'is_active' => true
            ]
        ]);
        
        return [
            'token' => $token,
            'expires_at' => $expirationTime
        ];
    }

    public function verifyExtensionToken($token) {
        // Query the token
        $response = $this->executeQuery('GET', '/rest/v1/extension_tokens', [
            'query' => [
                'token' => 'eq.' . $token,
                'is_active' => 'eq.true',
                'select' => 'user_id,expires_at'
            ]
        ]);

        $tokenData = $response;
        
        if (empty($tokenData)) {
            return false;
        }

        $tokenInfo = $tokenData[0];
        $expirationTime = strtotime($tokenInfo['expires_at']);
        
        // Check if token is expired
        if (time() > $expirationTime) {
            // Deactivate expired token
            $this->deactivateToken($token);
            return false;
        }

        return [
            'valid' => true,
            'user_id' => $tokenInfo['user_id'],
            'expires_at' => $expirationTime
        ];
    }

    private function deactivateToken($token) {
        try {
            $this->executeQuery('PATCH', '/rest/v1/extension_tokens', [
                'json' => [
                    'is_active' => false
                ],
                'query' => [
                    'token' => 'eq.' . $token
                ]
            ]);
        } catch (Exception $e) {
            error_log('Failed to deactivate token: ' . $e->getMessage());
        }
    }

    public function logActivity($userId, $userEmail, $action, $details = null) {
        try {
            $data = [
                'user_id' => $userId,
                'user_email' => $userEmail,
                'action' => $action,
                'details' => $details ? json_encode($details) : null
            ];

            $response = $this->executeQuery('POST', '/rest/v1/activity_logs', [
                'json' => $data
            ]);

            return $response;
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return null;
        }
    }
}
