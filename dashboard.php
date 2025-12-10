<?php
require_once 'config.php';
requireLogin();

// Ensure session variables are set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['name'])) {
    header('Location: logout.php');
    exit();
}

$conn = getDBConnection();

if ($conn === false) {
    global $db_error;
    die("Database connection failed. Please contact the administrator.");
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];

// Initialize default values
$course_count = 0;
$session_count = 0;
$student_count = 0;
$attendance_count = 0;
$present_count = 0;

// Get statistics based on role
if ($role === 'faculty') {
    // Get courses taught by faculty
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE faculty_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course_count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Get total sessions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions s 
                           INNER JOIN courses c ON s.course_id = c.course_id 
                           WHERE c.faculty_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $session_count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Get total students enrolled
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT csl.student_id) as count 
                           FROM course_student_list csl 
                           INNER JOIN courses c ON csl.course_id = c.course_id 
                           WHERE c.faculty_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_count = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    // Student statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM course_student_list WHERE student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course_count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Get attendance records
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Get present count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND status = 'present'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $present_count = $result->fetch_assoc()['count'];
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Welcome, <?php echo htmlspecialchars($name); ?>!</h1>
            <p class="subtitle"><?php echo ucfirst($role); ?> Dashboard</p>
        </div>
        
        <div class="stats-grid">
            <?php if ($role === 'faculty'): ?>
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-content">
                        <h3><?php echo $course_count; ?></h3>
                        <p>Courses</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-content">
                        <h3><?php echo $session_count; ?></h3>
                        <p>Sessions</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <h3><?php echo $student_count; ?></h3>
                        <p>Students</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-content">
                        <h3><?php echo $course_count; ?></h3>
                        <p>Enrolled Courses</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <h3><?php echo $present_count; ?></h3>
                        <p>Present Days</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <h3><?php echo $attendance_count; ?></h3>
                        <p>Total Records</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-grid">
                <?php if ($role === 'faculty'): ?>
                    <a href="courses.php" class="action-card">
                        <div class="action-icon">📖</div>
                        <h3>Manage Courses</h3>
                        <p>Create and manage your courses</p>
                    </a>
                    
                    <a href="sessions.php" class="action-card">
                        <div class="action-icon">📅</div>
                        <h3>Create Session</h3>
                        <p>Schedule new class sessions</p>
                    </a>
                    
                    <a href="attendance.php" class="action-card">
                        <div class="action-icon">✓</div>
                        <h3>Mark Attendance</h3>
                        <p>Take attendance for sessions</p>
                    </a>
                    
                    <a href="reports.php" class="action-card">
                        <div class="action-icon">📊</div>
                        <h3>View Reports</h3>
                        <p>Attendance reports and analytics</p>
                    </a>
                <?php else: ?>
                    <a href="my_courses.php" class="action-card">
                        <div class="action-icon">📖</div>
                        <h3>My Courses</h3>
                        <p>View enrolled courses</p>
                    </a>
                    
                    <a href="my_attendance.php" class="action-card">
                        <div class="action-icon">📊</div>
                        <h3>My Attendance</h3>
                        <p>View attendance records</p>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

