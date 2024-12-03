<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "Testing Supabase Configuration\n";
echo "-----------------------------\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Supabase URL: " . (isset($_ENV['SUPABASE_URL']) ? $_ENV['SUPABASE_URL'] : 'Not set') . "\n";
echo "Supabase Key Length: " . (isset($_ENV['SUPABASE_KEY']) ? strlen($_ENV['SUPABASE_KEY']) : 'Not set') . "\n";
echo "SSL Certificate Path: " . __DIR__ . '/certs/cacert.pem' . "\n";
echo "Certificate exists: " . (file_exists(__DIR__ . '/certs/cacert.pem') ? 'Yes' : 'No') . "\n";

// Test SSL connection
$ch = curl_init($_ENV['SUPABASE_URL']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/certs/cacert.pem');
$result = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

echo "\nTesting SSL Connection\n";
echo "--------------------\n";
if ($error) {
    echo "SSL Error: " . $error . "\n";
} else {
    echo "SSL Connection: Success\n";
}
