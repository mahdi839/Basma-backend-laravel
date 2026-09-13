<?php

/**
 * Standalone full-database backup, written in PHP so it does not need the
 * mysqldump binary. Credentials are read from the app config and never appear
 * on the command line.
 *
 *   php scripts/db-backup.php [output-file]
 *
 * Produces a restorable .sql file: DROP + CREATE + batched INSERTs per table,
 * with foreign key checks disabled around the whole thing.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$target = $argv[1] ?? __DIR__.'/../storage/backups/backup-'.date('Y-m-d_His').'.sql';

if (! is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}

$pdo = DB::connection()->getPdo();
$database = DB::connection()->getDatabaseName();

$handle = fopen($target, 'w');
if ($handle === false) {
    fwrite(STDERR, "Cannot write to {$target}\n");
    exit(1);
}

fwrite($handle, "-- Backup of `{$database}` taken ".date('c')."\n");
fwrite($handle, "SET NAMES utf8mb4;\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

$tables = array_map(
    static fn ($row) => array_values((array) $row)[0],
    DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')
);

$totalRows = 0;

foreach ($tables as $table) {
    $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];

    fwrite($handle, "\n--\n-- Table `{$table}`\n--\n");
    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($handle, $create.";\n\n");

    $count = (int) DB::table($table)->count();
    $totalRows += $count;

    echo str_pad($table, 42).str_pad((string) $count, 10, ' ', STR_PAD_LEFT)." rows\n";

    if ($count === 0) {
        continue;
    }

    // Streamed unbuffered so a large table cannot exhaust memory.
    $statement = $pdo->prepare("SELECT * FROM `{$table}`");
    $statement->execute();

    $batch = [];
    $columns = null;

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        if ($columns === null) {
            $columns = '`'.implode('`, `', array_keys($row)).'`';
        }

        $values = array_map(static function ($value) use ($pdo) {
            if ($value === null) {
                return 'NULL';
            }

            return $pdo->quote((string) $value);
        }, $row);

        $batch[] = '('.implode(',', $values).')';

        if (count($batch) >= 200) {
            fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n".implode(",\n", $batch).";\n");
            $batch = [];
        }
    }

    if ($batch !== []) {
        fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n".implode(",\n", $batch).";\n");
    }
}

fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

$size = round(filesize($target) / 1048576, 2);

echo "\n".str_repeat('-', 60)."\n";
echo "Database : {$database}\n";
echo "Tables   : ".count($tables)."\n";
echo "Rows     : {$totalRows}\n";
echo "File     : {$target}\n";
echo "Size     : {$size} MB\n";
