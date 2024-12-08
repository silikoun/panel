<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

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

    public function createClient() {
        return new Client([
            'base_uri' => $this->supabaseUrl,
            'headers' => [
                'apikey' => $this->supabaseServiceKey,
                'Authorization' => 'Bearer ' . $this->supabaseServiceKey
            ]
        ]);
    }

    public function signIn($email, $password) {
        $client = $this->createClient();
        try {
            // Sign in with email and password through Supabase Auth
            $response = $client->post('/auth/v1/token?grant_type=password', [
                'headers' => [
                    'apikey' => $this->supabaseKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'email' => $email,
                    'password' => $password
                ]
            ]);

            $authData = json_decode($response->getBody(), true);

            if (!isset($authData['user']['id'])) {
                throw new \Exception('Invalid credentials');
            }

            // Get or create user profile in Supabase
            $userResponse = $client->get('/rest/v1/profiles?id=eq.' . urlencode($authData['user']['id']), [
                'headers' => [
                    'apikey' => $this->supabaseServiceKey,
                    'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=representation'
                ]
            ]);

            $profiles = json_decode($userResponse->getBody(), true);
            $currentUser = $profiles[0] ?? null;
            
            if (!$currentUser) {
                // Create new profile in Supabase
                $response = $client->post('/rest/v1/profiles', [
                    'headers' => [
                        'apikey' => $this->supabaseServiceKey,
                        'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                        'Content-Type' => 'application/json',
                        'Prefer' => 'return=representation'
                    ],
                    'json' => [
                        'id' => $authData['user']['id'],
                        'email' => $email,
                        'is_admin' => false,
                        'is_premium' => false
                    ]
                ]);
                
                $currentUser = json_decode($response->getBody(), true)[0] ?? null;
            }

            // Check if user exists in Railway database
            try {
                $pdo = new PDO(
                    "mysql:host=" . getenv('MYSQLHOST') . ";dbname=" . getenv('MYSQLDATABASE'),
                    getenv('MYSQLUSER'),
                    getenv('MYSQLPASSWORD')
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $railwayUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$railwayUser) {
                    // Create user in Railway if not exists
                    $stmt = $pdo->prepare("INSERT INTO users (email, is_admin) VALUES (?, ?)");
                    $stmt->execute([$email, $currentUser['is_admin'] ? 1 : 0]);
                } else {
                    // Update admin status in Supabase to match Railway
                    if ($railwayUser['is_admin'] && !$currentUser['is_admin']) {
                        $client->patch('/rest/v1/profiles?id=eq.' . urlencode($authData['user']['id']), [
                            'headers' => [
                                'apikey' => $this->supabaseServiceKey,
                                'Authorization' => 'Bearer ' . $this->supabaseServiceKey,
                                'Content-Type' => 'application/json'
                            ],
                            'json' => [
                                'is_admin' => true
                            ]
                        ]);
                        $currentUser['is_admin'] = true;
                    }
                }
            } catch (\PDOException $e) {
                error_log('Railway DB Error: ' . $e->getMessage());
                // Continue even if Railway DB fails - we'll use Supabase data
            }

            $authData['user']['is_admin'] = $currentUser['is_admin'] ?? false;
            return $authData;
            
        } catch (ClientException $e) {
            $response = $e->getResponse();
            throw new \Exception($response->getBody());
        }
    }

    private function getUserSubscription($userId) {
        $client = $this->createClient();
        try {
            $response = $client->get('/rest/v1/subscriptions?user_id=eq.' . $userId . '&order=created_at.desc&limit=1');

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
        $client = $this->createClient();
        try {
            $subscription = [
                'user_id' => $userId,
                'status' => 'active',
                'plan' => 'legacy',
                'current_period_start' => date('Y-m-d H:i:s'),
                'current_period_end' => date('Y-m-d H:i:s', strtotime('+999 years'))
            ];

            $response = $client->post('/rest/v1/subscriptions', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
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
        $client = $this->createClient();
        try {
            // First try to find user by new API key
            $response = $client->get('/rest/v1/profiles?api_token=eq.' . $apiKey);

            $profiles = json_decode($response->getBody(), true);
            
            if (empty($profiles)) {
                // If not found, try to find by old token field
                $response = $client->get('/rest/v1/profiles?token=eq.' . $apiKey);

                $profiles = json_decode($response->getBody(), true);
                
                if (!empty($profiles)) {
                    // Found an old token, migrate it to new system
                    $this->migrateOldToken($profiles[0]['id'], $apiKey);
                }
            }
            
            if (empty($profiles)) {
                return false;
            }

            $profile = $profiles[0];
            
            // For old tokens or migrated tokens, create/get subscription
            $subscription = $this->getUserSubscription($profile['id']);
            
            if (!$subscription || $subscription['status'] !== 'active') {
                return false;
            }

            // Update expiration if needed
            if (isset($subscription['current_period_end']) && 
                (!isset($profile['api_token_expires']) || $subscription['current_period_end'] !== $profile['api_token_expires'])) {
                $this->updateApiKeyExpiration($profile['id'], $subscription['current_period_end']);
            }

            return [
                'profile' => $profile,
                'subscription' => $subscription
            ];
        } catch (Exception $e) {
            error_log('Error verifying API key: ' . $e->getMessage());
            return false;
        }
    }

    private function migrateOldToken($profileId, $oldToken) {
        try {
            $client = $this->createClient();
            $response = $client->patch('/rest/v1/profiles?id=eq.' . $profileId, [
                'headers' => [
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

    public function generateApiKey($profileId) {
        // Check subscription status
        $subscription = $this->getUserSubscription($profileId);
        
        if (!$subscription || $subscription['status'] !== 'active') {
            throw new Exception('No active subscription found');
        }

        $apiKey = bin2hex(random_bytes(32));
        
        // Set expiration based on subscription end date
        $expiresAt = $subscription['current_period_end'] ?? date('Y-m-d H:i:s', strtotime('+999 years'));

        $client = $this->createClient();
        try {
            $response = $client->patch('/rest/v1/profiles?id=eq.' . $profileId, [
                'headers' => [
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

    private function updateApiKeyExpiration($profileId, $newExpiration) {
        $client = $this->createClient();
        try {
            $client->patch('/rest/v1/profiles?id=eq.' . $profileId, [
                'headers' => [
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
        $client = new Client();
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

    public function generateJWT($profileId, $email) {
        $issuedAt = time();
        $expiresAt = $issuedAt + (60 * 60 * 24); // 24 hours

        $payload = [
            'iss' => $this->supabaseUrl,
            'sub' => $profileId,
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

    public function generateExtensionToken($profileId) {
        $client = $this->createClient();
        try {
            // Generate a secure random token
            $token = bin2hex(random_bytes(32));
            
            // Current timestamp and expiration (30 days from now)
            $currentTime = time();
            $expirationTime = $currentTime + (30 * 24 * 60 * 60);
            
            // Deactivate any existing active tokens for this user
            $client->patch('/rest/v1/extension_tokens', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
                ],
                'json' => [
                    'is_active' => false
                ],
                'query' => [
                    'profile_id' => 'eq.' . $profileId,
                    'is_active' => 'eq.true'
                ]
            ]);

            // Store the new token
            $response = $client->post('/rest/v1/extension_tokens', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=minimal'
                ],
                'json' => [
                    'profile_id' => $profileId,
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
        $client = $this->createClient();
        try {
            // Query the token
            $response = $client->get('/rest/v1/extension_tokens', [
                'query' => [
                    'token' => 'eq.' . $token,
                    'is_active' => 'eq.true',
                    'select' => 'profile_id,expires_at'
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
                'profile_id' => $tokenInfo['profile_id'],
                'expires_at' => $expirationTime
            ];
        } catch (Exception $e) {
            error_log('Token verification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function deactivateToken($token) {
        $client = $this->createClient();
        try {
            $client->patch('/rest/v1/extension_tokens', [
                'headers' => [
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

    public function logActivity($profileId, $profileEmail, $action, $details = null) {
        try {
            $data = [
                'profile_id' => $profileId,
                'profile_email' => $profileEmail,
                'action' => $action,
                'details' => $details ? json_encode($details) : null
            ];

            $response = $this->supabase
                ->from('activity_logs')
                ->insert($data)
                ->execute();

            return $response->data[0] ?? null;
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return null;
        }
    }
}
