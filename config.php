<?php
// Enable error reporting for debugging (disable in production or use logging)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporarily enabled to see errors - DISABLE IN PRODUCTION
ini_set('log_errors', 1); // Log errors to server log

// ============================================================================
// DATABASE CONFIGURATION - Auto-detects environment (XAMPP or Live Server)
// ============================================================================

/**
 * Detect if we're running on local XAMPP or live server
 * @return string 'local' or 'server'
 */
function detectEnvironment() {
    // Check if we're on localhost/XAMPP
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $is_local = (
        strpos($host, 'localhost') !== false ||
        strpos($host, '127.0.0.1') !== false ||
        strpos($host, '::1') !== false ||
        (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1')
    );
    
    return $is_local ? 'local' : 'server';
}

/**
 * Get database configuration based on environment
 * @return array Database configuration array
 */
function getDatabaseConfig() {
    $env = detectEnvironment();
    
    if ($env === 'local') {
        // XAMPP Local Development Configuration
        return [
            'host' => 'localhost',
            'user' => 'root',
            'pass' => '', // Empty password for XAMPP
            'name' => 'webtech_2025a_peter_mayen'
        ];
    } else {
        // Live Server Configuration
        return [
            'host' => 'localhost',
            'user' => 'peter.mayen',
            'pass' => 'Machuek',
            'name' => 'webtech_2025a_peter_mayen'
        ];
    }
}

// Get database configuration
$db_config = getDatabaseConfig();

// Define database constants
define('DB_HOST', $db_config['host']);
define('DB_USER', $db_config['user']);
define('DB_PASS', $db_config['pass']);
define('DB_NAME', $db_config['name']);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global error handler for database connections
$GLOBALS['db_error'] = null;

/**
 * Get database connection - Works for both XAMPP and Live Server
 * Automatically uses correct credentials based on environment
 * 
 * @return mysqli|false Returns mysqli connection object or false on failure
 */
function getDBConnection() {
    global $db_error;
    $db_error = null;
    
    try {
        // Connect directly to the database with DB_NAME included
        // This is the correct way - always include database name in connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            $db_error = "Database connection failed: " . $conn->connect_error;
            error_log($db_error);
            
            // If database doesn't exist, try to create it (if permissions allow)
            if (strpos($conn->connect_error, "Unknown database") !== false) {
                // Try to connect without database to create it
                $temp_conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
                if (!$temp_conn->connect_error) {
                    $create_db = $temp_conn->query("CREATE DATABASE IF NOT EXISTS `" . $temp_conn->real_escape_string(DB_NAME) . "`");
                    if ($create_db) {
                        // Database created, now try connecting again
                        $temp_conn->close();
                        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                        if ($conn->connect_error) {
                            $db_error = "Database connection failed after creation: " . $conn->connect_error;
                            error_log($db_error);
                            return false;
                        }
                    } else {
                        $db_error = "Database does not exist and could not be created. Please create it manually: " . DB_NAME;
                        error_log($db_error);
                        $temp_conn->close();
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        
        // Set charset to UTF-8
        if (!$conn->set_charset("utf8mb4")) {
            error_log("Error setting charset: " . $conn->error);
        }
        
        // Check if tables exist, if not create them automatically
        $tables_check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($tables_check && $tables_check->num_rows == 0) {
            // Tables don't exist, create them
            if (!createTables($conn)) {
                $db_error = "Failed to create database tables";
                error_log($db_error);
                return false;
            }
        }
        
        return $conn;
    } catch (Exception $e) {
        $db_error = "Database error: " . $e->getMessage();
        error_log($db_error);
        return false;
    } catch (Error $e) {
        $db_error = "Fatal database error: " . $e->getMessage();
        error_log($db_error);
        return false;
    }
}

/**
 * Test database connection and return connection info
 * Useful for debugging and setup verification
 * 
 * @return array Connection test results
 */
function testDatabaseConnection() {
    $env = detectEnvironment();
    $db_config = getDatabaseConfig();
    
    $result = [
        'environment' => $env,
        'host' => $db_config['host'],
        'user' => $db_config['user'],
        'database' => $db_config['name'],
        'connected' => false,
        'error' => null,
        'tables_exist' => false
    ];
    
    $conn = getDBConnection();
    
    if ($conn === false) {
        global $db_error;
        $result['error'] = $db_error ?? 'Unknown error';
    } else {
        $result['connected'] = true;
        
        // Check if tables exist
        $tables_check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($tables_check && $tables_check->num_rows > 0) {
            $result['tables_exist'] = true;
        }
        
        $conn->close();
    }
    
    return $result;
}

// Create tables if they don't exist
function createTables($conn) {
    if (!$conn) {
        return false;
    }
    
    try {
        // Disable foreign key checks temporarily to allow table creation in any order
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Create users table
        $sql_users = "CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('student', 'faculty', 'admin') NOT NULL,
            dob DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql_users)) {
            error_log("Error creating users table: " . $conn->error);
            return false;
        }
        
        // Create students table
        $sql_students = "CREATE TABLE IF NOT EXISTS students (
            student_id INT PRIMARY KEY,
            FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql_students)) {
            error_log("Error creating students table: " . $conn->error);
            return false;
        }
        
        // Create faculty table
        $sql_faculty = "CREATE TABLE IF NOT EXISTS faculty (
            faculty_id INT PRIMARY KEY,
            FOREIGN KEY (faculty_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql_faculty)) {
            error_log("Error creating faculty table: " . $conn->error);
            return false;
        }
        
        // Create courses table
        $sql_courses = "CREATE TABLE IF NOT EXISTS courses (
            course_id INT PRIMARY KEY AUTO_INCREMENT,
            course_code VARCHAR(20) UNIQUE,
            course_name VARCHAR(150) NOT NULL,
            description TEXT,
            credit_hours INT,
            faculty_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
            INDEX idx_course_code (course_code),
            INDEX idx_faculty (faculty_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql_courses)) {
            error_log("Error creating courses table: " . $conn->error);
            return false;
        }
        
        // Create course_student_list table
        $sql_course_student = "CREATE TABLE IF NOT EXISTS course_student_list (
            course_id INT NOT NULL,
            student_id INT NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (course_id, student_id),
            FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql_course_student)) {
            error_log("Error creating course_student_list table: " . $conn->error);
            return false;
        }
        
        // Create sessions table
        $sql_sessions = "CREATE TABLE IF NOT EXISTS sessions (
            session_id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT NOT NULL,
            topic VARCHAR(150),
            location VARCHAR(100),
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
            INDEX idx_course (course_id),
            INDEX idx_date (date),
            INDEX idx_course_date (course_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql_sessions)) {
            error_log("Error creating sessions table: " . $conn->error);
            return false;
        }
        
        // Create attendance table
        $sql_attendance = "CREATE TABLE IF NOT EXISTS attendance (
            attendance_id INT PRIMARY KEY AUTO_INCREMENT,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            status ENUM('present', 'absent', 'late') NOT NULL,
            check_in_time TIME,
            remarks TEXT,
            marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendance (session_id, student_id),
            INDEX idx_session (session_id),
            INDEX idx_student (student_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql_attendance)) {
            error_log("Error creating attendance table: " . $conn->error);
            return false;
        }
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        return true;
    } catch (Exception $e) {
        error_log("Error in createTables: " . $e->getMessage());
        return false;
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check if user has specific role
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Redirect if not faculty
function requireFaculty() {
    requireLogin();
    if (!hasRole('faculty')) {
        header('Location: dashboard.php');
        exit();
    }
}

// Redirect if not student
function requireStudent() {
    requireLogin();
    if (!hasRole('student')) {
        header('Location: dashboard.php');
        exit();
    }
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>

