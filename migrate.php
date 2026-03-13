<?php
// One-time migration script — DELETE THIS FILE after running!
$secret = $_GET['key'] ?? '';
if ($secret !== 'run_migrate_2024') {
    die('Unauthorized');
}

require_once __DIR__ . '/config/database.php';

$files = [
    'database/railway_full_setup.sql',
];

$results = [];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        $results[] = "⚠️ File not found: $file";
        continue;
    }
    $sql = file_get_contents($path);
    // Split by semicolon, skip empty
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $ok = 0; $fail = 0;
    foreach ($statements as $stmt) {
        if (empty($stmt) || str_starts_with(ltrim($stmt), '--')) continue;
        try {
            $pdo->exec($stmt);
            $ok++;
        } catch (PDOException $e) {
            $fail++;
            $results[] = "❌ [$file] " . $e->getMessage();
        }
    }
    $results[] = "✅ $file — $ok OK, $fail failed";
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Migration</title>
<style>body{font-family:monospace;padding:20px;background:#111;color:#eee}
.ok{color:#4caf50}.fail{color:#f44336}.warn{color:#ff9800}</style></head><body>
<h2>Database Migration Results</h2>
<?php foreach ($results as $r): ?>
<p><?= htmlspecialchars($r) ?></p>
<?php endforeach; ?>
<p style="color:#888;margin-top:30px">⚠️ Delete this file (migrate.php) after migration is done!</p>
</body></html>
