<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();

if ($conn === false) {
    global $db_error;
    die("Database connection failed. Please contact the administrator.");
}

$student_id = $_SESSION['user_id'];

// Get enrolled courses
$stmt = $conn->prepare("SELECT c.*, u.first_name, u.last_name 
                        FROM courses c 
                        INNER JOIN course_student_list csl ON c.course_id = csl.course_id 
                        INNER JOIN users u ON c.faculty_id = u.user_id 
                        WHERE csl.student_id = ? 
                        ORDER BY c.course_code");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>My Courses</h1>
        </div>
        
        <div class="courses-grid">
            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <p>You are not enrolled in any courses yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?php echo htmlspecialchars($course['course_code']); ?></h3>
                            <span class="badge"><?php echo $course['credit_hours']; ?> Credits</span>
                        </div>
                        <h4><?php echo htmlspecialchars($course['course_name']); ?></h4>
                        <p><?php echo htmlspecialchars($course['description']); ?></p>
                        <div class="course-info">
                            <p><strong>Instructor:</strong> <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></p>
                        </div>
                        <div class="course-actions">
                            <a href="course_attendance.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-primary">View Attendance</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

