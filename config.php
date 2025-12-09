<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'peter.mayen');
define('DB_PASS', 'Machuek');
define('DB_NAME', 'webtech_2025a_peter_mayen');

// Start session
session_start();

// Create database connection
function getDBConnection() {
    // First try to connect without selecting database to check if database exists
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Check if database exists, if not create it
    $result = $conn->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
    if ($result->num_rows == 0) {
        // Database doesn't exist, create it
        if ($conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME) === TRUE) {
            // Database created, now create tables
            $conn->select_db(DB_NAME);
            createTables($conn);
        } else {
            die("Error creating database: " . $conn->error);
        }
    } else {
        $conn->select_db(DB_NAME);
        // Check if tables exist, if not create them
        $tables_check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($tables_check->num_rows == 0) {
            createTables($conn);
        }
    }
    
    return $conn;
}

// Create tables if they don't exist
function createTables($conn) {
    // Disable foreign key checks temporarily to allow table creation in any order
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Create users table
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('student', 'faculty', 'admin') NOT NULL,
        dob DATE
    )");
    
    // Create students table
    $conn->query("CREATE TABLE IF NOT EXISTS students (
        student_id INT PRIMARY KEY,
        FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    
    // Create faculty table
    $conn->query("CREATE TABLE IF NOT EXISTS faculty (
        faculty_id INT PRIMARY KEY,
        FOREIGN KEY (faculty_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    
    // Create courses table
    $conn->query("CREATE TABLE IF NOT EXISTS courses (
        course_id INT PRIMARY KEY AUTO_INCREMENT,
        course_code VARCHAR(20) UNIQUE,
        course_name VARCHAR(150) NOT NULL,
        description TEXT,
        credit_hours INT,
        faculty_id INT NOT NULL,
        FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE
    )");
    
    // Create course_student_list table
    $conn->query("CREATE TABLE IF NOT EXISTS course_student_list (
        course_id INT NOT NULL,
        student_id INT NOT NULL,
        PRIMARY KEY (course_id, student_id),
        FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
    )");
    
    // Create sessions table
    $conn->query("CREATE TABLE IF NOT EXISTS sessions (
        session_id INT PRIMARY KEY AUTO_INCREMENT,
        course_id INT NOT NULL,
        topic VARCHAR(150),
        location VARCHAR(100),
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        date DATE NOT NULL,
        FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
    )");
    
    // Create attendance table
    $conn->query("CREATE TABLE IF NOT EXISTS attendance (
        attendance_id INT PRIMARY KEY AUTO_INCREMENT,
        session_id INT NOT NULL,
        student_id INT NOT NULL,
        status ENUM('present', 'absent', 'late') NOT NULL,
        check_in_time TIME,
        remarks TEXT,
        FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (session_id, student_id)
    )");
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
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

