<?php
require_once __DIR__ . '/../classes/SubscriptionManager.php';

header('Content-Type: application/json');

$subscriptionManager = new SubscriptionManager();

try {
    $action = $_GET['action'] ?? '';
    $userId = $_SESSION['user_id'] ?? null; // Assuming you have user authentication
    
    if (!$userId) {
        throw new Exception('Unauthorized', 401);
    }
    
    switch ($action) {
        case 'create':
            $planId = $_POST['plan_id'] ?? null;
            if (!$planId) {
                throw new Exception('Plan ID is required');
            }
            
            $result = $subscriptionManager->createSubscription($userId, $planId);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'validate':
            $token = $_POST['token'] ?? null;
            if (!$token) {
                throw new Exception('Token is required');
            }
            
            $result = $subscriptionManager->validateToken($token);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'refresh':
            $refreshToken = $_POST['refresh_token'] ?? null;
            if (!$refreshToken) {
                throw new Exception('Refresh token is required');
            }
            
            $result = $subscriptionManager->refreshToken($refreshToken);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'cancel':
            $subscriptionId = $_POST['subscription_id'] ?? null;
            if (!$subscriptionId) {
                throw new Exception('Subscription ID is required');
            }
            
            $result = $subscriptionManager->cancelSubscription($subscriptionId);
            echo json_encode([
                'success' => true,
                'data' => ['canceled' => $result]
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
