<?php
// Test script to verify the CRM system setup

echo "HelpDesk CRM - System Test\n";
echo "==========================\n\n";

// Test 1: Check if config files exist
echo "1. Checking configuration files...\n";
$files = [
    'config/database.php',
    'models/User.php',
    'models/Ticket.php',
    'models/Note.php',
    'controllers/authController.php',
    'controllers/ticketController.php',
    'controllers/userController.php',
    'controllers/reportController.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✓ $file exists\n";
    } else {
        echo "   ✗ $file missing\n";
    }
}

// Test 2: Check database connection
echo "\n2. Testing database connection...\n";
try {
    require_once 'config/database.php';
    echo "   ✓ Database connection successful\n";

    // Test 3: Check if tables exist
    echo "\n3. Checking database tables...\n";
    $tables = ['users', 'roles', 'tickets', 'ticket_notes', 'categories', 'priorities', 'statuses'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Table '$table' exists\n";
        } else {
            echo "   ✗ Table '$table' missing\n";
        }
    }

    // Test 4: Check sample data
    echo "\n4. Checking sample data...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "   ✓ $userCount users found\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets");
    $ticketCount = $stmt->fetch()['count'];
    echo "   ✓ $ticketCount tickets found\n";

} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n5. System Status:\n";
echo "   - PHP Version: " . PHP_VERSION . "\n";
echo "   - Server: " . $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' . "\n";

echo "\nTest completed!\n";
echo "If all checks passed, your HelpDesk CRM is ready to use.\n";
echo "Access the application at: http://localhost/CRMTicket/\n";
?>