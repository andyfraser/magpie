<?php
/**
 * Magpie Database Configuration
 *
 * To use MySQL, change 'driver' to 'mysql' and fill in the connection details.
 * To use SQLite, change 'driver' to 'sqlite'.
 */
return [
    'db' => [
        'driver'      => 'sqlite', // 'sqlite' or 'mysql'
        'sqlite_path' => __DIR__ . '/magpie.db',
        'host'        => 'localhost',
        'dbname'      => 'magpie',
        'user'        => 'root',
        'pass'        => '',
        'charset'     => 'utf8mb4',
    ],
];
