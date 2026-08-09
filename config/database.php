<?php

$host = 'localhost';
$dbname = 'crud_tarefas';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

if (file_exists(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$opcoes = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opcoes);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'Falha ao conectar ao banco de dados: ' . $e->getMessage()]));
}
