<?php
require_once 'config.php';

// Create database if it doesn't exist
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists.<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

$conn->close();

// Now connect to the database
$conn = getDBConnection();

// Read and execute SQL schema
$sql_file = 'attendance_schema.sql';
$sql_content = file_get_contents($sql_file);

// Remove CREATE DATABASE and USE statements as we're already connected
$sql_content = preg_replace('/CREATE DATABASE.*?;/i', '', $sql_content);
$sql_content = preg_replace('/USE.*?;/i', '', $sql_content);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql_content)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if ($conn->query($statement) === TRUE) {
            echo "Query executed successfully: " . substr($statement, 0, 50) . "...<br>";
        } else {
            // Ignore "already exists" errors
            if (strpos($conn->error, 'already exists') === false) {
                echo "Error: " . $conn->error . "<br>";
            }
        }
    }
}

$conn->close();
echo "<br><strong>Database initialization complete!</strong><br>";
echo "<a href='login.php' style='display:inline-block;margin-top:1rem;padding:0.75rem 1.5rem;background:#6366f1;color:white;text-decoration:none;border-radius:0.5rem;'>Go to Login Page</a>";
?>

