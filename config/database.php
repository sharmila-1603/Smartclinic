<?php
// TiDB Cloud Database Configuration

$host = 'gateway.tidbcloud.com';
$port = 4000;
$dbname = 'Smartclinic-db';
$username = 'root';
$password = 'ads1HMgvawtM0gz0';  // ← Replace with your actual password

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "Database connected successfully!";  // Temporary - remove later
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>