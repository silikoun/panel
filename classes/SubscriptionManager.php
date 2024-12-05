<?php

class SubscriptionManager {
    private $pdo;
    private $redis;
    
    public function __construct() {
        $this->pdo = require __DIR__ . '/../config/database.php';
        if (extension_loaded('redis')) {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        }
    }
    
    public function createSubscription($userId, $planId) {
        try {
            // Get plan details
            $stmt = $this->pdo->prepare("SELECT * FROM plans WHERE id = ?");
            $stmt->execute([$planId]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                throw new Exception("Invalid plan selected");
            }
            
            $this->pdo->beginTransaction();
            
            // Create subscription
            $stmt = $this->pdo->prepare("
                INSERT INTO subscriptions (
                    user_id, 
                    plan_id, 
                    status,
                    start_date,
                    end_date
                ) VALUES (?, ?, 'active', NOW(), NOW() + INTERVAL '? days')
                RETURNING id
            ");
            
            $stmt->execute([$userId, $planId, $plan['duration_days']]);
            $subscriptionId = $stmt->fetchColumn();
            
            // Generate tokens
            $tokens = $this->generateTokens($userId, $subscriptionId);
            
            $this->pdo->commit();
            return [
                'subscription_id' => $subscriptionId,
                'tokens' => $tokens,
                'plan' => $plan
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creating subscription: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function generateTokens($userId, $subscriptionId) {
        $accessToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_tokens (
                user_id,
                subscription_id,
                access_token,
                refresh_token,
                expires_at
            ) VALUES (?, ?, ?, ?, ?)
            RETURNING id
        ");
        
        $stmt->execute([$userId, $subscriptionId, $accessToken, $refreshToken, $expiresAt]);
        
        // Cache token in Redis if available
        if ($this->redis) {
            $this->redis->setex(
                "token:$accessToken",
                3600,
                json_encode([
                    'user_id' => $userId,
                    'subscription_id' => $subscriptionId,
                    'status' => 'active'
                ])
            );
        }
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600
        ];
    }
    
    public function validateToken($token) {
        // Check Redis cache first if available
        if ($this->redis) {
            $cached = $this->redis->get("token:$token");
            if ($cached) {
                $data = json_decode($cached, true);
                if ($this->verifySubscription($data['subscription_id'])) {
                    return [
                        'valid' => true,
                        'user_id' => $data['user_id']
                    ];
                }
            }
        }
        
        // Check database
        $stmt = $this->pdo->prepare("
            SELECT t.*, s.status as sub_status, s.end_date
            FROM user_tokens t
            JOIN subscriptions s ON t.subscription_id = s.id
            WHERE t.access_token = ?
            AND t.status = 'active'
            AND t.expires_at > NOW()
        ");
        
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && 
            $result['sub_status'] === 'active' && 
            strtotime($result['end_date']) > time()) {
            
            // Update Redis cache if available
            if ($this->redis) {
                $this->redis->setex(
                    "token:$token",
                    3600,
                    json_encode([
                        'user_id' => $result['user_id'],
                        'subscription_id' => $result['subscription_id'],
                        'status' => 'active'
                    ])
                );
            }
            
            return [
                'valid' => true,
                'user_id' => $result['user_id']
            ];
        }
        
        return ['valid' => false];
    }
    
    public function refreshToken($refreshToken) {
        $stmt = $this->pdo->prepare("
            SELECT user_id, subscription_id 
            FROM user_tokens 
            WHERE refresh_token = ? 
            AND status = 'active'
        ");
        
        $stmt->execute([$refreshToken]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token && $this->verifySubscription($token['subscription_id'])) {
            // Revoke old token
            $this->revokeToken($refreshToken, 'refresh');
            
            // Generate new tokens
            return $this->generateTokens($token['user_id'], $token['subscription_id']);
        }
        
        throw new Exception("Invalid refresh token");
    }
    
    public function revokeToken($token, $type = 'access') {
        $column = $type === 'refresh' ? 'refresh_token' : 'access_token';
        
        $stmt = $this->pdo->prepare("
            UPDATE user_tokens 
            SET status = 'revoked' 
            WHERE $column = ?
        ");
        
        $stmt->execute([$token]);
        
        if ($this->redis && $type === 'access') {
            $this->redis->del("token:$token");
        }
    }
    
    private function verifySubscription($subscriptionId) {
        $stmt = $this->pdo->prepare("
            SELECT status, end_date 
            FROM subscriptions 
            WHERE id = ?
        ");
        
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $subscription && 
               $subscription['status'] === 'active' && 
               strtotime($subscription['end_date']) > time();
    }
    
    public function cancelSubscription($subscriptionId) {
        try {
            $this->pdo->beginTransaction();
            
            // Update subscription status
            $stmt = $this->pdo->prepare("
                UPDATE subscriptions 
                SET status = 'canceled',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subscriptionId]);
            
            // Revoke all active tokens
            $stmt = $this->pdo->prepare("
                SELECT access_token 
                FROM user_tokens 
                WHERE subscription_id = ? 
                AND status = 'active'
            ");
            $stmt->execute([$subscriptionId]);
            
            while ($token = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->revokeToken($token['access_token']);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error canceling subscription: " . $e->getMessage());
            throw $e;
        }
    }
}
