<?php
// Enable error reporting for debugging (temporarily enable display for troubleshooting)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporarily enabled to see errors
ini_set('log_errors', 1);

// Start output buffering to catch any errors
ob_start();

// Include config with error handling
if (!file_exists('config.php')) {
    // If config doesn't exist, show error but still display form
    $config_error = 'Configuration file not found. Please contact the administrator.';
    $config_loaded = false;
} else {
    require_once 'config.php';
    $config_loaded = true;
}

// Redirect if already logged in (only if config loaded successfully)
if ($config_loaded && function_exists('isLoggedIn') && isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$debug_info = ''; // For debugging - remove in production

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if required functions exist
        if (!function_exists('sanitize')) {
            throw new Exception('sanitize() function not found. Please check config.php');
        }
        if (!function_exists('hashPassword')) {
            throw new Exception('hashPassword() function not found. Please check config.php');
        }
        
        // Sanitize and validate input
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = sanitize($_POST['role'] ?? 'student');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (!in_array($role, ['student', 'faculty'])) {
            $error = 'Invalid role selected.';
        } else {
            // Check if config loaded
            if (!$config_loaded) {
                $error = 'System configuration error. Please contact the administrator.';
            } elseif (!function_exists('getDBConnection')) {
                $error = 'Database functions not available. Please check configuration.';
            } else {
                // Get database connection
                $conn = getDBConnection();
                
                if ($conn === false) {
                    global $db_error;
                    $error_msg = $db_error ?? 'Unknown database error';
                    error_log("Signup DB connection error: " . $error_msg);
                    
                    // Provide more helpful error message
                    if (strpos($error_msg, "Unknown database") !== false) {
                        $error = 'Database not found. Please run init_db.php to set up the database, or create the database manually.';
                    } elseif (strpos($error_msg, "Access denied") !== false) {
                        $error = 'Database access denied. Please check your database credentials in config.php';
                    } elseif (strpos($error_msg, "Connection refused") !== false || strpos($error_msg, "Can't connect") !== false) {
                        $error = 'Cannot connect to database server. Please ensure MySQL is running and check your database host settings.';
                    } else {
                        $error = 'Database connection failed: ' . htmlspecialchars($error_msg);
                    }
                } else {
                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                    if (!$stmt) {
                        $error = 'Database error. Please try again.';
                        error_log("Prepare error: " . $conn->error);
                    } else {
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $error = 'Email already registered. Please use a different email.';
                            $stmt->close();
                        } else {
                            $stmt->close();
                            
                            // Insert user with proper error handling
                            $password_hash = hashPassword($password);
                            if ($password_hash === false) {
                                $error = 'Error hashing password. Please try again.';
                                error_log("Password hash error");
                            } else {
                                // Handle NULL dob properly
                                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, dob) VALUES (?, ?, ?, ?, ?, ?)");
                                if (!$stmt) {
                                    $error = 'Database error. Please try again.';
                                    error_log("Prepare error (INSERT users): " . $conn->error);
                                } else {
                                    $stmt->bind_param("ssssss", $first_name, $last_name, $email, $password_hash, $role, $dob);
                                    
                                    if ($stmt->execute()) {
                                        $user_id = $conn->insert_id;
                                        
                                        // Add to students or faculty table based on role
                                        if ($role === 'student') {
                                            $stmt2 = $conn->prepare("INSERT INTO students (student_id) VALUES (?)");
                                            if ($stmt2) {
                                                $stmt2->bind_param("i", $user_id);
                                                if (!$stmt2->execute()) {
                                                    error_log("Error inserting student: " . $stmt2->error);
                                                    $error = 'Account created but student record failed. Please contact support.';
                                                }
                                                $stmt2->close();
                                            } else {
                                                error_log("Prepare error (INSERT students): " . $conn->error);
                                                $error = 'Account created but student record failed. Please contact support.';
                                            }
                                        } elseif ($role === 'faculty') {
                                            $stmt2 = $conn->prepare("INSERT INTO faculty (faculty_id) VALUES (?)");
                                            if ($stmt2) {
                                                $stmt2->bind_param("i", $user_id);
                                                if (!$stmt2->execute()) {
                                                    error_log("Error inserting faculty: " . $stmt2->error);
                                                    $error = 'Account created but faculty record failed. Please contact support.';
                                                }
                                                $stmt2->close();
                                            } else {
                                                error_log("Prepare error (INSERT faculty): " . $conn->error);
                                                $error = 'Account created but faculty record failed. Please contact support.';
                                            }
                                        }
                                        
                                        if (empty($error)) {
                                            $success = 'Account created successfully! Redirecting to login...';
                                        }
                                    } else {
                                        $error = 'Error creating account. Please try again.';
                                        error_log("Execute error (INSERT users): " . $stmt->error);
                                    }
                                    $stmt->close();
                                }
                            }
                        }
                    }
                    
                    if (isset($conn) && $conn) {
                        $conn->close();
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = 'An unexpected error occurred: ' . htmlspecialchars($e->getMessage());
        error_log("Signup exception: " . $e->getMessage());
        error_log("Signup exception trace: " . $e->getTraceAsString());
    } catch (Error $e) {
        $error = 'Fatal error: ' . htmlspecialchars($e->getMessage());
        error_log("Signup fatal error: " . $e->getMessage());
        error_log("Signup fatal error trace: " . $e->getTraceAsString());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Attendance Management System</title>
    <?php
    // Determine correct CSS path based on server setup
    $base_path = '';
    if (isset($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, '~') !== false) {
            // Shared hosting with ~username path
            $base_path = '/~peter.mayen/';
        } elseif (strpos($uri, '/public_html/') !== false) {
            $base_path = dirname($_SERVER['SCRIPT_NAME']) . '/';
        }
    }
    ?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    <style>
        /* Beautiful Signup Page Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body.auth-page {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body.auth-page::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.3;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0); }
            100% { transform: translateY(-100px); }
        }

        .auth-container {
            width: 100%;
            max-width: 550px;
            position: relative;
            z-index: 1;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .auth-header h1 {
            color: #1f2937;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-header p {
            color: #6b7280;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            box-sizing: border-box;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
            color: #1f2937;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-group input:hover,
        .form-group select:hover {
            border-color: #d1d5db;
            background: white;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-row .form-group {
            flex: 1;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .alert {
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 12px;
            font-size: 0.95rem;
            animation: slideDown 0.3s ease-out;
            border-left: 4px solid;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border-color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #16a34a;
        }

        .auth-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
        }

        .auth-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .auth-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .auth-form {
            margin-bottom: 0;
        }

        /* Responsive Design */
        @media (max-width: 640px) {
            .auth-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }

            .auth-header h1 {
                font-size: 2rem;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        /* Input placeholder styling */
        .form-group input::placeholder {
            color: #9ca3af;
        }

        /* Select arrow styling */
        .form-group select {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="%236b7280" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 40px;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div style="width: 80px; height: 80px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);">
                    <span style="font-size: 2.5rem;">📚</span>
                </div>
                <h1>Create Account</h1>
                <p>Join us to get started</p>
            </div>
            
            <?php if (isset($config_error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($config_error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                    <?php if (strpos($error, 'Database') !== false || strpos($error, 'database') !== false): ?>
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(220, 38, 38, 0.2);">
                            <p style="margin-bottom: 8px; font-size: 0.9rem;"><strong>Need help?</strong></p>
                            <p style="margin-bottom: 8px; font-size: 0.85rem;">1. Make sure MySQL/XAMPP is running</p>
                            <p style="margin-bottom: 8px; font-size: 0.85rem;">2. Visit <a href="init_db.php" style="color: #dc2626; text-decoration: underline; font-weight: 600;">init_db.php</a> to set up your database</p>
                            <p style="margin-bottom: 0; font-size: 0.85rem;">3. Or create the database manually in phpMyAdmin</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 2000);
                    </script>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="signup.php" class="auth-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="role">I am a *</label>
                    <select id="role" name="role" required>
                        <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : 'selected'; ?>>Student</option>
                        <option value="faculty" <?php echo (isset($_POST['role']) && $_POST['role'] == 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="dob">Date of Birth (Optional)</label>
                    <input type="date" id="dob" name="dob" value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>

