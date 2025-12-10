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
            'pass' => '',
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
// Get connection
// -----------------------------------------------------
function getDBConnection(){
    $conn = new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);

    if($conn->connect_error){
        die("DB Error: ".$conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    return $conn;
}



// -----------------------------------------------------
// Security helpers
// -----------------------------------------------------
function hashPassword($p){ return password_hash($p,PASSWORD_BCRYPT); }
function verifyPassword($p,$h){ return password_verify($p,$h); }
function sanitize($d){ return htmlspecialchars(strip_tags(trim($d))); }


// -----------------------------------------------------
// SESSION helpers
// -----------------------------------------------------
if(session_status()==PHP_SESSION_NONE) session_start();

function isLogged(){ return isset($_SESSION['user_id']);}

?>
