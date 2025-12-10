<?php
/**
 * Complete File Verification Script
 * Checks all PHP files for proper connections and configuration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>File Verification Report</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #6366f1; border-bottom: 3px solid #6366f1; padding-bottom: 10px; }
        h2 { color: #4f46e5; margin-top: 30px; }
        .test-item { margin: 10px 0; padding: 12px; border-radius: 4px; border-left: 4px solid; }
        .pass { background: #d1fae5; border-color: #10b981; }
        .fail { background: #fee2e2; border-color: #ef4444; }
        .warning { background: #fef3c7; border-color: #f59e0b; }
        .info { background: #dbeafe; border-color: #3b82f6; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .summary { padding: 20px; margin: 20px 0; border-radius: 8px; font-size: 1.1em; }
        .summary.pass { background: #d1fae5; color: #065f46; }
        .summary.fail { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📋 Complete File Verification Report</h1>";

$all_checks_passed = true;
$issues = [];

// Test 1: Config file exists and loads
echo "<h2>1. Configuration File</h2>";
if (file_exists('config.php')) {
    require_once 'config.php';
    
    $functions_required = [
        'getDBConnection', 'isLoggedIn', 'hasRole', 'requireLogin', 
        'requireFaculty', 'requireStudent', 'hashPassword', 'verifyPassword', 
        'sanitize', 'createTables', 'detectEnvironment', 'getDB'
    ];
    
    $missing_functions = [];
    foreach ($functions_required as $func) {
        if (!function_exists($func)) {
            $missing_functions[] = $func;
        }
    }
    
    if (empty($missing_functions)) {
        echo "<div class='test-item pass'>✓ All required functions exist in config.php</div>";
    } else {
        echo "<div class='test-item fail'>✗ Missing functions: " . implode(', ', $missing_functions) . "</div>";
        $all_checks_passed = false;
        $issues[] = "Missing functions in config.php: " . implode(', ', $missing_functions);
    }
    
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
        echo "<div class='test-item pass'>✓ Database constants defined</div>";
    } else {
        echo "<div class='test-item fail'>✗ Database constants not defined</div>";
        $all_checks_passed = false;
        $issues[] = "Database constants not defined";
    }
} else {
    echo "<div class='test-item fail'>✗ config.php NOT FOUND</div>";
    $all_checks_passed = false;
    $issues[] = "config.php file missing";
}

// Test 2: Check all PHP files
echo "<h2>2. PHP Files Check</h2>";
$php_files = [
    'index.php' => ['requires_config' => true, 'requires_login' => false],
    'login.php' => ['requires_config' => true, 'requires_login' => false],
    'signup.php' => ['requires_config' => true, 'requires_login' => false],
    'dashboard.php' => ['requires_config' => true, 'requires_login' => true],
    'logout.php' => ['requires_config' => true, 'requires_login' => false],
    'courses.php' => ['requires_config' => true, 'requires_login' => true, 'requires_faculty' => true],
    'sessions.php' => ['requires_config' => true, 'requires_login' => true, 'requires_faculty' => true],
    'attendance.php' => ['requires_config' => true, 'requires_login' => true, 'requires_faculty' => true],
    'course_details.php' => ['requires_config' => true, 'requires_login' => true, 'requires_faculty' => true],
    'course_attendance.php' => ['requires_config' => true, 'requires_login' => true, 'requires_student' => true],
    'my_courses.php' => ['requires_config' => true, 'requires_login' => true, 'requires_student' => true],
    'my_attendance.php' => ['requires_config' => true, 'requires_login' => true, 'requires_student' => true],
    'reports.php' => ['requires_config' => true, 'requires_login' => true, 'requires_faculty' => true],
    'init_db.php' => ['requires_config' => true, 'requires_login' => false],
    'fix_user_records.php' => ['requires_config' => true, 'requires_login' => false],
];

$file_results = [];
foreach ($php_files as $file => $requirements) {
    $file_ok = true;
    $file_issues = [];
    
    if (!file_exists($file)) {
        $file_ok = false;
        $file_issues[] = "File not found";
        $all_checks_passed = false;
    } else {
        $content = file_get_contents($file);
        
        // Check for config.php inclusion
        if ($requirements['requires_config']) {
            if (strpos($content, "require_once 'config.php'") === false && 
                strpos($content, 'require_once "config.php"') === false &&
                strpos($content, "require 'config.php'") === false) {
                $file_ok = false;
                $file_issues[] = "Missing config.php include";
            }
        }
        
        // Check for database connection handling
        if ($requirements['requires_login']) {
            if (strpos($content, 'getDBConnection()') !== false) {
                // Check if connection failure is handled
                if (strpos($content, '$conn === false') === false && 
                    strpos($content, '$conn == false') === false) {
                    $file_ok = false;
                    $file_issues[] = "Missing database connection error check";
                }
            }
        }
        
        // Check for proper role requirements
        if (isset($requirements['requires_faculty']) && $requirements['requires_faculty']) {
            if (strpos($content, 'requireFaculty()') === false) {
                $file_ok = false;
                $file_issues[] = "Missing requireFaculty()";
            }
        }
        
        if (isset($requirements['requires_student']) && $requirements['requires_student']) {
            if (strpos($content, 'requireStudent()') === false) {
                $file_ok = false;
                $file_issues[] = "Missing requireStudent()";
            }
        }
    }
    
    $file_results[$file] = [
        'ok' => $file_ok,
        'issues' => $file_issues
    ];
}

echo "<table>";
echo "<tr><th>File</th><th>Status</th><th>Issues</th></tr>";
foreach ($file_results as $file => $result) {
    if ($result['ok']) {
        echo "<tr><td><strong>$file</strong></td><td><span style='color: #10b981;'>✓ OK</span></td><td>-</td></tr>";
    } else {
        echo "<tr><td><strong>$file</strong></td><td><span style='color: #ef4444;'>✗ Issues</span></td><td>" . implode(', ', $result['issues']) . "</td></tr>";
        $all_checks_passed = false;
        $issues[] = "$file: " . implode(', ', $result['issues']);
    }
}
echo "</table>";

// Test 3: Include files
echo "<h2>3. Include Files</h2>";
$include_files = ['includes/header.php', 'includes/footer.php'];
foreach ($include_files as $file) {
    if (file_exists($file)) {
        echo "<div class='test-item pass'>✓ $file exists</div>";
    } else {
        echo "<div class='test-item fail'>✗ $file NOT FOUND</div>";
        $all_checks_passed = false;
        $issues[] = "$file missing";
    }
}

// Test 4: Database connection
echo "<h2>4. Database Connection</h2>";
if (function_exists('getDBConnection')) {
    $conn = getDBConnection();
    if ($conn !== false) {
        echo "<div class='test-item pass'>✓ Database connection successful</div>";
        $conn->close();
    } else {
        echo "<div class='test-item fail'>✗ Database connection failed</div>";
        $all_checks_passed = false;
        $issues[] = "Database connection failed";
    }
} else {
    echo "<div class='test-item fail'>✗ getDBConnection() function not found</div>";
    $all_checks_passed = false;
    $issues[] = "getDBConnection() function missing";
}

// Summary
echo "<h2>📊 Summary</h2>";
if ($all_checks_passed) {
    echo "<div class='summary pass'>";
    echo "<strong>✅ ALL CHECKS PASSED!</strong><br>";
    echo "All files are properly connected and ready for GitHub deployment.";
    echo "</div>";
} else {
    echo "<div class='summary fail'>";
    echo "<strong>❌ SOME ISSUES FOUND</strong><br>";
    echo "Please fix the following issues before pushing to GitHub:<br><ul>";
    foreach ($issues as $issue) {
        echo "<li>" . htmlspecialchars($issue) . "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb;'>";
echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 4px;'>Go to Home</a>";
echo "</div>";

echo "</div></body></html>";
?>

