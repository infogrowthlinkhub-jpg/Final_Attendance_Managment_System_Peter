<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = sanitize($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {

        $conn = getDBConnection();

        // Prepare query
        $stmt = $conn->prepare("
            SELECT user_id, first_name, last_name, email, password_hash, role 
            FROM users 
            WHERE email = ?
        ");

        if (!$stmt) {
            $error = "Database error: ".$conn->error;
        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows == 1) {

                $user = $result->fetch_assoc();

                if (verifyPassword($password, $user['password_hash'])) {

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['first_name']." ".$user['last_name'];
                    $_SESSION['email'] = $user['email'];

                    header("Location: dashboard.php");
                    exit();

                } else {
                    $error = "Invalid email or password.";
                }

            } else {
                $error = "Invalid email or password.";
            }

            $stmt->close();
        }

        $conn->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Fallback styles in case CSS file doesn't load */
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .auth-container { max-width: 500px; margin: 50px auto; }
        .auth-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header h1 { color: #6366f1; margin-bottom: 10px; }
        .auth-header p { color: #6b7280; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #374151; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        .form-group input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .btn { background: #6366f1; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; font-weight: 600; }
        .btn:hover { background: #4f46e5; }
        .btn-block { display: block; width: 100%; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-error { background: #fee2e2; color: #ef4444; border: 1px solid #ef4444; }
        .auth-footer { text-align: center; margin-top: 20px; color: #6b7280; }
        .auth-footer a { color: #6366f1; text-decoration: none; font-weight: 500; }
        .auth-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Sign In</h1>
                <p>Welcome back! Please login to continue</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="signup.php">Sign up</a></p>
            </div>
        </div>
    </div>
</body>
</html>
