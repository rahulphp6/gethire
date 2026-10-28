<?php
// =========================================================================
// ADVANCED PHP & MySQL Backend Logic
// This script combines the frontend HTML/JS with a functional PHP backend.
// =========================================================================
// To make this functional, you need a MySQL database.
// 1. Create a database named 'youtuber_sponsors_portal'.
// 2. Create the tables using the SQL commands below.
// 3. Update the database credentials ($servername, $username, $password, $dbname).
// =========================================================================

// --- Database Credentials ---
$servername = "localhost";
$db_username = "root"; // CHANGE ME: Use your database username
$db_password = "";     // CHANGE ME: Use your database password
$dbname = "gethire"; // CHANGE ME: Use your database name

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}