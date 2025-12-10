<?php
/**
 * Database Initialization Script
 * 
 * This script uses the unified getDBConnection() function from config.php
 * to initialize the database. It will:
 * 1. Create the database if it doesn't exist (if permissions allow)
 * 2. Create all tables if they don't exist
 * 
 * Access: http://169.239.251.102:341/~peter.mayen/init_db.php
 */

// Enable error display for initialization
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Initialization</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #6366f1; }
        .success { color: #10b981; padding: 10px; background: #d1fae5; border-radius: 4px; margin: 10px 0; }
        .error { color: #ef4444; padding: 10px; background: #fee2e2; border-radius: 4px; margin: 10px 0; }
        .info { color: #3b82f6; padding: 10px; background: #dbeafe; border-radius: 4px; margin: 10px 0; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px; }
        .btn:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Database Initialization</h1>";

// Use the unified database connection function
$conn = getDBConnection();

if ($conn === false) {
    global $db_error;
    echo "<div class='error'><strong>Error:</strong> " . htmlspecialchars($db_error ?? 'Unknown error') . "</div>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>Database credentials in config.php</li>";
    echo "<li>Database exists in phpMyAdmin</li>";
    echo "<li>MySQL user has proper permissions</li>";
    echo "</ul>";
    echo "<p>You can also manually import <code>database_schema.sql</code> in phpMyAdmin.</p>";
} else {
    echo "<div class='success'><strong>Success!</strong> Connected to database: " . DB_NAME . "</div>";
    
    // Check which tables exist
    $tables = ['users', 'students', 'faculty', 'courses', 'course_student_list', 'sessions', 'attendance'];
    $existing_tables = [];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $existing_tables[] = $table;
        } else {
            $missing_tables[] = $table;
        }
    }
    
    if (count($existing_tables) > 0) {
        echo "<div class='info'><strong>Existing Tables:</strong> " . implode(', ', $existing_tables) . "</div>";
    }
    
    if (count($missing_tables) > 0) {
        echo "<div class='info'><strong>Creating Missing Tables:</strong> " . implode(', ', $missing_tables) . "</div>";
        // Tables will be auto-created by getDBConnection() if they don't exist
        // Let's verify by checking again
        $conn->close();
        $conn = getDBConnection();
        
        if ($conn) {
            $all_exist = true;
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if (!$result || $result->num_rows == 0) {
                    $all_exist = false;
                    break;
                }
            }
            
            if ($all_exist) {
                echo "<div class='success'><strong>All tables created successfully!</strong></div>";
            } else {
                echo "<div class='error'><strong>Warning:</strong> Some tables may not have been created. Check error logs.</div>";
            }
        }
    } else {
        echo "<div class='success'><strong>All required tables already exist!</strong></div>";
    }
    
    // Show table counts
    if ($conn) {
        echo "<h2>Database Status</h2>";
        echo "<ul>";
        foreach ($tables as $table) {
            $result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "<li><strong>$table:</strong> " . $row['count'] . " records</li>";
            }
        }
        echo "</ul>";
        
        $conn->close();
    }
    
    echo "<div class='success'><strong>Database initialization complete!</strong></div>";
    echo "<a href='login.php' class='btn'>Go to Login Page</a>";
    echo "<a href='test_connection.php' class='btn' style='margin-left: 10px; background: #6b7280;'>Test Connection</a>";
}

echo "</div></body></html>";
?>

