<?php
$host = 'localhost';
$dbname = 'smartclinic_db';
$username = 'root';
$password = '';  // Default XAMPP password is empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Connected successfully"; // Remove this in production
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>