<?php
// CLI migration script: Add store_address and note to stores table if missing
// Run: php scripts/add_store_columns.php [--force]
require_once __DIR__ . '/../config/db.php';

// Only allow execution from CLI
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$force = in_array('--force', $argv);

// Check existing columns
$required = [
    'store_address' => "ALTER TABLE stores ADD COLUMN store_address TEXT NULL",
    'note' => "ALTER TABLE stores ADD COLUMN note TEXT NULL"
];

$added = [];
foreach ($required as $col => $sql) {
    $res = $conn->query("SHOW COLUMNS FROM stores LIKE '" . $conn->real_escape_string($col) . "'");
    if ($res && $res->num_rows) {
        echo "Column '$col' already exists.\n";
        continue;
    }

    echo "Column '$col' is missing.";
    if (!$force) {
        echo " Run with --force to add it.\n";
        continue;
    }

    if ($conn->query($sql) === TRUE) {
        echo " Added '$col'.\n";
        $added[] = $col;
    } else {
        echo " Failed to add '$col': " . $conn->error . "\n";
    }
}

if (!$force && empty($added)) {
    echo "No changes were made. To apply the migration, re-run with --force.\n";
}

echo "Done.\n";
$conn->close();

?>
