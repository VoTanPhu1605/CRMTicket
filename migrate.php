<?php
$secret = $_GET['key'] ?? '';
if ($secret !== 'run_migrate_2024') die('Unauthorized');

require_once __DIR__ . '/config/database.php';

// Enable buffered queries to avoid "unbuffered queries" error
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

$files = [
    'database/railway_full_setup.sql',
    'database/payment_methods_migration.sql',
    'database/status_address_migration.sql',
    'database/cleanup_demo.sql',
];

$results = [];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) { $results[] = "⚠️ File not found: $file"; continue; }

    $sql = file_get_contents($path);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $ok = 0; $fail = 0;

    foreach ($statements as $stmt) {
        // Strip comment lines
        $lines = array_filter(explode("\n", $stmt), fn($l) => !str_starts_with(trim($l), '--'));
        $stmt  = trim(implode("\n", $lines));
        if (empty($stmt)) continue;

        // Skip SELECT/DESCRIBE/SHOW — they're just for verification, not needed here
        $first = strtoupper(strtok($stmt, " \t\n"));
        if (in_array($first, ['SELECT', 'DESCRIBE', 'SHOW'])) { $ok++; continue; }

        try {
            $pdo->exec($stmt);
            $ok++;
        } catch (PDOException $e) {
            // Ignore "already exists" / "duplicate column" — safe to skip
            $code = $e->getCode();
            $msg  = $e->getMessage();
            if (str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists') || $code === '42S21' || $code === '42S01') {
                $ok++; // treat as success
            } else {
                $fail++;
                $results[] = "❌ [$file] $msg";
            }
        }
    }
    $results[] = "✅ $file — $ok OK, $fail failed";
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Migration</title>
<style>body{font-family:monospace;padding:20px;background:#111;color:#eee}</style></head><body>
<h2>Database Migration Results</h2>
<?php foreach ($results as $r): ?>
<p><?= htmlspecialchars($r) ?></p>
<?php endforeach; ?>
<p style="color:#aaa;margin-top:30px">Done. You may delete migrate.php after this.</p>
</body></html>
