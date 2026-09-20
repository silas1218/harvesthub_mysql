<?php
/**
 * db.php
 * Connects to the HarvestHub MySQL database. The schema itself (tables
 * + demo seed data) lives in schema.sql — import that once via
 * phpMyAdmin or the mysql CLI before running the app. This file no
 * longer creates or migrates tables at runtime (that was a SQLite-only
 * workaround from the earlier prototype); it just opens the connection.
 */

function getDb(): PDO {
    $config = require __DIR__ . '/db_config.php';

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['dbname'],
        $config['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['user'], $config['password']);
    } catch (PDOException $e) {
        // A connection failure almost always means either MySQL isn't
        // running, or schema.sql hasn't been imported yet — surface a
        // clearer message than PDO's default one.
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => 'Could not connect to the database. Make sure MySQL is running and that '
                . 'schema.sql has been imported (see README.md), and that db_config.php has the '
                . 'right credentials.',
        ]);
        exit;
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}
