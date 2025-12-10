<?php
// Error reporting (disable on live server)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// -----------------------------------------------------
// Detect environment
// -----------------------------------------------------
function detectEnvironment(){
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (strpos($host, 'localhost') !== false ||
            strpos($host, '127.0.0.1') !== false)
           ? 'local' : 'server';
}

// -----------------------------------------------------
// Database credentials
// -----------------------------------------------------
function getDB(){
    $env = detectEnvironment();

    if($env == 'local'){
        return [
            'host' => 'localhost',
            'user' => 'root',
            'pass' => 'Machuek',
            'name' => 'webtech_2025a_peter_mayen'
        ];
    }

    return [
        'host' => 'localhost',
        'user' => 'peter.mayen',
        'pass' => 'Machuek',
        'name' => 'webtech_2025a_peter_mayen'
    ];
}

$db = getDB();

define('DB_HOST',$db['host']);
define('DB_USER',$db['user']);
define('DB_PASS',$db['pass']);
define('DB_NAME',$db['name']);


// -----------------------------------------------------
// Global error handler for database connections
// -----------------------------------------------------
$GLOBALS['db_error'] = null;

// -----------------------------------------------------
// Get connection
// -----------------------------------------------------
function getDBConnection(){
    global $db_error;
    $db_error = null;
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if($conn->connect_error){
            $db_error = "Database connection failed: " . $conn->connect_error;
            error_log($db_error);
            
            // If database doesn't exist, try to create it (if permissions allow)
            if(strpos($conn->connect_error, "Unknown database") !== false){
                $temp_conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
                if(!$temp_conn->connect_error){
                    $create_db = $temp_conn->query("CREATE DATABASE IF NOT EXISTS `" . $temp_conn->real_escape_string(DB_NAME) . "`");
                    if($create_db){
                        $temp_conn->close();
                        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                        if($conn->connect_error){
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
        if(!$conn->set_charset("utf8mb4")){
            error_log("Error setting charset: " . $conn->error);
        }
        
        // Check if tables exist, if not create them automatically
        $tables_check = $conn->query("SHOW TABLES LIKE 'users'");
        if($tables_check && $tables_check->num_rows == 0){
            if(!createTables($conn)){
                $db_error = "Failed to create database tables";
                error_log($db_error);
                return false;
            }
        }
        
        return $conn;
    } catch(Exception $e){
        $db_error = "Database error: " . $e->getMessage();
        error_log($db_error);
        return false;
    } catch(Error $e){
        $db_error = "Fatal database error: " . $e->getMessage();
        error_log($db_error);
        return false;
    }
}

// -----------------------------------------------------
// Create tables if they don't exist
// -----------------------------------------------------
function createTables($conn){
    if(!$conn) return false;
    
    try {
        // Disable foreign key checks temporarily
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
        
        if(!$conn->query($sql_users)){
            error_log("Error creating users table: " . $conn->error);
            return false;
        }
        
        // Create students table
        $sql_students = "CREATE TABLE IF NOT EXISTS students (
            student_id INT PRIMARY KEY,
            FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if(!$conn->query($sql_students)){
            error_log("Error creating students table: " . $conn->error);
            return false;
        }
        
        // Create faculty table
        $sql_faculty = "CREATE TABLE IF NOT EXISTS faculty (
            faculty_id INT PRIMARY KEY,
            FOREIGN KEY (faculty_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if(!$conn->query($sql_faculty)){
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
        
        if(!$conn->query($sql_courses)){
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
        
        if(!$conn->query($sql_course_student)){
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
            INDEX idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if(!$conn->query($sql_sessions)){
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
        
        if(!$conn->query($sql_attendance)){
            error_log("Error creating attendance table: " . $conn->error);
            return false;
        }
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        return true;
    } catch(Exception $e){
        error_log("Error in createTables: " . $e->getMessage());
        return false;
    }
}

// -----------------------------------------------------
// Security helpers
// -----------------------------------------------------
function hashPassword($p){ return password_hash($p, PASSWORD_BCRYPT); }
function verifyPassword($p, $h){ return password_verify($p, $h); }
function sanitize($d){ return htmlspecialchars(strip_tags(trim($d))); }

// -----------------------------------------------------
// SESSION helpers
// -----------------------------------------------------
if(session_status() == PHP_SESSION_NONE) session_start();

// Check if user is logged in
function isLoggedIn(){
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Alias for backward compatibility
function isLogged(){
    return isLoggedIn();
}

// Check if user has specific role
function hasRole($role){
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin(){
    if(!isLoggedIn()){
        header('Location: login.php');
        exit();
    }
}

// Redirect if not faculty
function requireFaculty(){
    requireLogin();
    if(!hasRole('faculty')){
        header('Location: dashboard.php');
        exit();
    }
    
    // Ensure faculty record exists in faculty table
    $conn = getDBConnection();
    if($conn !== false){
        $faculty_id = $_SESSION['user_id'];
        
        // First verify the user exists in users table
        $check_user = $conn->prepare("SELECT user_id, role FROM users WHERE user_id = ? AND role = 'faculty'");
        $check_user->bind_param("i", $faculty_id);
        $check_user->execute();
        $user_result = $check_user->get_result();
        
        if($user_result->num_rows == 0){
            // User doesn't exist or role doesn't match - invalid session
            $check_user->close();
            $conn->close();
            session_destroy();
            header('Location: login.php');
            exit();
        }
        $check_user->close();
        
        // Now check if faculty record exists
        $check = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_id = ?");
        $check->bind_param("i", $faculty_id);
        $check->execute();
        $result = $check->get_result();
        
        if($result->num_rows == 0){
            // Faculty record missing, create it (user exists, so this should work)
            $insert = $conn->prepare("INSERT INTO faculty (faculty_id) VALUES (?)");
            $insert->bind_param("i", $faculty_id);
            
            if(!$insert->execute()){
                error_log("Failed to create faculty record for user_id $faculty_id: " . $insert->error);
            }
            $insert->close();
        }
        $check->close();
        $conn->close();
    }
}

// Redirect if not student
function requireStudent(){
    requireLogin();
    if(!hasRole('student')){
        header('Location: dashboard.php');
        exit();
    }
    
    // Ensure student record exists in students table
    $conn = getDBConnection();
    if($conn !== false){
        $student_id = $_SESSION['user_id'];
        
        // First verify the user exists in users table
        $check_user = $conn->prepare("SELECT user_id, role FROM users WHERE user_id = ? AND role = 'student'");
        $check_user->bind_param("i", $student_id);
        $check_user->execute();
        $user_result = $check_user->get_result();
        
        if($user_result->num_rows == 0){
            // User doesn't exist or role doesn't match - invalid session
            $check_user->close();
            $conn->close();
            session_destroy();
            header('Location: login.php');
            exit();
        }
        $check_user->close();
        
        // Now check if student record exists
        $check = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
        $check->bind_param("i", $student_id);
        $check->execute();
        $result = $check->get_result();
        
        if($result->num_rows == 0){
            // Student record missing, create it (user exists, so this should work)
            $insert = $conn->prepare("INSERT INTO students (student_id) VALUES (?)");
            $insert->bind_param("i", $student_id);
            
            if(!$insert->execute()){
                error_log("Failed to create student record for user_id $student_id: " . $insert->error);
            }
            $insert->close();
        }
        $check->close();
        $conn->close();
    }
}

?>
