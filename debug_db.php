<?php
require_once 'config/database.php';
require_once 'models/User.php';

echo "<h1>Database Debug - Login Test</h1>";

try {
    echo "<h2>1. Database Connection</h2>";
    echo "<p>Connected successfully to database: crmhelpdesk</p>";

    echo "<h2>2. Test User Lookup</h2>";
    $userModel = new User();
    $testUsers = ['admin', 'manager', 'agent', 'viewer'];
    foreach ($testUsers as $username) {
        $user = $userModel->findByUsername($username);
        echo "<h3>User: $username</h3>";
        if ($user) {
            echo "<pre>";
            print_r($user);
            echo "</pre>";
        } else {
            echo "<p>User not found</p>";
        }
    }

    echo "<h2>3. All Users in Database</h2>";
    $stmt = $pdo->query("SELECT id, username, password, fullname FROM users");
    $users = $stmt->fetchAll();
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Password</th><th>Fullname</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['password']}</td>";
        echo "<td>{$user['fullname']}</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>