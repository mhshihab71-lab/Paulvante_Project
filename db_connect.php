<?php
// Database connection credentials for local XAMPP server
$servername = "localhost";
$username = "root";       // Default XAMPP username
$password = "";           // Default XAMPP password is empty
$dbname = "farm_management"; // The database you just created

// Create the connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>