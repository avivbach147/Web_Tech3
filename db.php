<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Attempt to connect
$conn = new mysqli("localhost", "root", "", "aspm");

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
} else {
    // Optional message: only show in development
    // echo "✅ Database connection successful!";
}
?>