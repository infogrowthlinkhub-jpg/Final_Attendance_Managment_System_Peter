<?php
/**
 * Fix User Records Script
 * 
 * This script ensures that all users with role 'faculty' have a record in the faculty table
 * and all users with role 'student' have a record in the students table.
 * 
 * Access: http://169.239.251.102:341/~peter.mayen/fix_user_records.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix User Records</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #6366f1; border-bottom: 3px solid #6366f1; padding-bottom: 10px; }
        .success { color: #10b981; padding: 10px; background: #d1fae5; border-radius: 4px; margin: 10px 0; }
        .error { color: #ef4444; padding: 10px; background: #fee2e2; border-radius: 4px; margin: 10px 0; }
        .info { color: #3b82f6; padding: 10px; background: #dbeafe; border-radius: 4px; margin: 10px 0; }
        .warning { color: #f59e0b; padding: 10px; background: #fef3c7; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px; }
        .btn:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Fix User Records</h1>";

$conn = getDBConnection();

if ($conn === false) {
    global $db_error;
    echo "<div class='error'><strong>Error:</strong> " . htmlspecialchars($db_error ?? 'Unknown error') . "</div>";
    echo "</div></body></html>";
    exit;
}

// Get all users
$users_result = $conn->query("SELECT user_id, first_name, last_name, email, role FROM users ORDER BY user_id");

if (!$users_result) {
    echo "<div class='error'><strong>Error:</strong> Could not fetch users: " . $conn->error . "</div>";
    echo "</div></body></html>";
    exit;
}

$fixed_faculty = [];
$fixed_students = [];
$errors = [];

echo "<div class='info'><strong>Checking user records...</strong></div>";

while ($user = $users_result->fetch_assoc()) {
    $user_id = $user['user_id'];
    $role = $user['role'];
    $name = $user['first_name'] . ' ' . $user['last_name'];
    
    if ($role === 'faculty') {
        // Check if faculty record exists
        $check = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows == 0) {
            // Faculty record missing, create it
            $insert = $conn->prepare("INSERT INTO faculty (faculty_id) VALUES (?)");
            $insert->bind_param("i", $user_id);
            
            if ($insert->execute()) {
                $fixed_faculty[] = ['id' => $user_id, 'name' => $name, 'email' => $user['email']];
            } else {
                $errors[] = "Failed to create faculty record for user ID $user_id: " . $insert->error;
            }
            
            $insert->close();
        }
        $check->close();
    } elseif ($role === 'student') {
        // Check if student record exists
        $check = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows == 0) {
            // Student record missing, create it
            $insert = $conn->prepare("INSERT INTO students (student_id) VALUES (?)");
            $insert->bind_param("i", $user_id);
            
            if ($insert->execute()) {
                $fixed_students[] = ['id' => $user_id, 'name' => $name, 'email' => $user['email']];
            } else {
                $errors[] = "Failed to create student record for user ID $user_id: " . $insert->error;
            }
            
            $insert->close();
        }
        $check->close();
    }
}

// Display results
if (count($fixed_faculty) > 0) {
    echo "<div class='success'><strong>✓ Fixed " . count($fixed_faculty) . " faculty record(s):</strong></div>";
    echo "<table>";
    echo "<tr><th>User ID</th><th>Name</th><th>Email</th></tr>";
    foreach ($fixed_faculty as $faculty) {
        echo "<tr><td>{$faculty['id']}</td><td>{$faculty['name']}</td><td>{$faculty['email']}</td></tr>";
    }
    echo "</table>";
}

if (count($fixed_students) > 0) {
    echo "<div class='success'><strong>✓ Fixed " . count($fixed_students) . " student record(s):</strong></div>";
    echo "<table>";
    echo "<tr><th>User ID</th><th>Name</th><th>Email</th></tr>";
    foreach ($fixed_students as $student) {
        echo "<tr><td>{$student['id']}</td><td>{$student['name']}</td><td>{$student['email']}</td></tr>";
    }
    echo "</table>";
}

if (count($errors) > 0) {
    echo "<div class='error'><strong>✗ Errors encountered:</strong></div>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
}

if (count($fixed_faculty) == 0 && count($fixed_students) == 0 && count($errors) == 0) {
    echo "<div class='success'><strong>✓ All user records are correct!</strong> No fixes needed.</div>";
}

echo "<div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb;'>";
echo "<a href='dashboard.php' class='btn'>Go to Dashboard</a> ";
echo "<a href='courses.php' class='btn' style='background: #10b981;'>Go to Courses</a>";
echo "</div>";

$conn->close();

echo "</div></body></html>";
?>

