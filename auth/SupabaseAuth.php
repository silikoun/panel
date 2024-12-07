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
                // For backward compatibility, if no subscription exists, create a lifetime subscription
                return $this->createLifetimeSubscription($userId);
            }

            return $subscriptions[0];
        } catch (Exception $e) {
            // For backward compatibility, if there's an error, assume it's a valid old client
            error_log('Error checking subscription: ' . $e->getMessage());
            return [
                'status' => 'active',
                'plan' => 'legacy',
                'current_period_end' => date('Y-m-d H:i:s', strtotime('+999 years'))
            ];
        }
    }

    private function createLifetimeSubscription($userId) {
        $client = new GuzzleHttp\Client();
        try {
            $subscription = [
                'user_id' => $userId,
                'status' => 'active',
                'plan' => 'legacy',
                'current_period_start' => date('Y-m-d H:i:s'),
                'current_period_end' => date('Y-m-d H:i:s', strtotime('+999 years'))
            ];

            $response = $client->post($this->supabaseUrl . '/rest/v1/subscriptions', [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                    'Content-Type' => 'application/json',
                    'Prefer': 'return=minimal'
                ],
                'json' => $subscription
            ]);

            if ($response->getStatusCode() === 201) {
                return $subscription;
            }

            throw new Exception('Failed to create lifetime subscription');
        } catch (Exception $e) {
            error_log('Error creating lifetime subscription: ' . $e->getMessage());
            return [
                'status' => 'active',
                'plan' => 'legacy',
                'current_period_end' => date('Y-m-d H:i:s', strtotime('+999 years'))
            ];
        }
    }

    public function verifyApiKey($apiKey) {
        $client = new GuzzleHttp\Client();
        try {
            // First try to find user by new API key
            $response = $client->get($this->supabaseUrl . '/rest/v1/users?api_token=eq.' . $apiKey, [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey
                ]
            ]);

            $users = json_decode($response->getBody(), true);
            
            if (empty($users)) {
                // If not found, try to find by old token field
                $response = $client->get($this->supabaseUrl . '/rest/v1/users?token=eq.' . $apiKey, [
                    'headers' => [
                        'apikey' => $this->supabaseServiceKey,
                        'Authorization' => 'Bearer ' . $this->supabaseServiceKey
                    ]
                ]);
                
                $users = json_decode($response->getBody(), true);
                
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
        } catch (Exception $e) {
            error_log('Error verifying API key: ' . $e->getMessage());
            return false;
        }
    }

    private function migrateOldToken($userId, $oldToken) {
        try {
            $client = new GuzzleHttp\Client();
            $response = $client->patch($this->supabaseUrl . '/rest/v1/users?id=eq.' . $userId, [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
                ],
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

    public function generateExtensionToken($userId) {
        $client = new GuzzleHttp\Client();
        try {
            // Generate a secure random token
            $token = bin2hex(random_bytes(32));
            
            // Current timestamp and expiration (30 days from now)
            $currentTime = time();
            $expirationTime = $currentTime + (30 * 24 * 60 * 60);
            
            // Deactivate any existing active tokens for this user
            $client->patch($this->supabaseUrl . '/rest/v1/extension_tokens', [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
                ],
                'json' => [
                    'is_active' => false
                ],
                'query' => [
                    'user_id' => 'eq.' . $userId,
                    'is_active' => 'eq.true'
                ]
            ]);

            // Store the new token
            $response = $client->post($this->supabaseUrl . '/rest/v1/extension_tokens', [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
                ],
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
        } catch (Exception $e) {
            error_log('Failed to generate extension token: ' . $e->getMessage());
            throw new Exception('Failed to generate extension token');
        }
    }

    public function verifyExtensionToken($token) {
        $client = new GuzzleHttp\Client();
        try {
            // Query the token
            $response = $client->get($this->supabaseUrl . '/rest/v1/extension_tokens', [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey
                ],
                'query' => [
                    'token' => 'eq.' . $token,
                    'is_active' => 'eq.true',
                    'select' => 'user_id,expires_at'
                ]
            ]);

            $tokenData = json_decode($response->getBody(), true);
            
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
        } catch (Exception $e) {
            error_log('Token verification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function deactivateToken($token) {
        $client = new GuzzleHttp\Client();
        try {
            $client->patch($this->supabaseUrl . '/rest/v1/extension_tokens', [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
                ],
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
}
