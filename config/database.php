<?php
// Database configuration for HelpDesk CRM

$host = 'localhost'; // Database host
$username = 'root'; // Database username
$password = ''; // Database password
$database = 'crmhelpdesk'; // Database name

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>