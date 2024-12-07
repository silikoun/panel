<?php

/**
 * Format number with appropriate suffix (K, M, B)
 */
function formatNumber($number) {
    if ($number >= 1000000000) {
        return round($number / 1000000000, 1) . 'B';
    }
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    }
    if ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

/**
 * Format currency amount
 */
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Format date to readable format
 */
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

/**
 * Format datetime to readable format with time
 */
function formatDateTime($datetime) {
    return date('M j, Y H:i', strtotime($datetime));
}

/**
 * Get time ago string (e.g., "2 hours ago")
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $time_difference = time() - $time;

    if ($time_difference < 1) {
        return 'less than 1 second ago';
    }
    
    $conditions = [
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60 => 'month',
        24 * 60 * 60 => 'day',
        60 * 60 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];

    foreach ($conditions as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
}

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user has admin privileges
 */
function isAdmin() {
    return isset($_SESSION['user']) && isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] === true;
}

/**
 * Redirect if not admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get user's gravatar URL
 */
function getGravatar($email, $size = 80) {
    $hash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/$hash?s=$size&d=mp";
}
