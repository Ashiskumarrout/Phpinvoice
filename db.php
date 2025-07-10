<?php
// Database configuration
$host = "localhost";
$user = "root";
$pass = "password"; // ← Make sure this is correct
$db   = "bill";

// Create a new MySQLi connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session only once, even if included multiple times
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
