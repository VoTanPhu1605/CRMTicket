<?php
// Database configuration for HelpDesk CRM

$mysqlUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: null;

if ($mysqlUrl) {
    $parts    = parse_url($mysqlUrl);
    $host     = $parts['host'];
    $port     = $parts['port'] ?? 3306;
    $username = $parts['user'];
    $password = $parts['pass'] ?? '';
    $database = ltrim($parts['path'], '/');
} else {
    $host     = getenv('DB_HOST') ?: 'localhost';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $database = getenv('DB_NAME') ?: 'crmhelpdesk';
    $port     = getenv('DB_PORT') ?: '3306';
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>