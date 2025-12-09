<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$student_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Verify student is enrolled
if ($course_id > 0) {
    $stmt = $conn->prepare("SELECT c.* FROM courses c 
                            INNER JOIN course_student_list csl ON c.course_id = csl.course_id 
                            WHERE c.course_id = ? AND csl.student_id = ?");
    $stmt->bind_param("ii", $course_id, $student_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$course) {
        header('Location: my_courses.php');
        exit();
    }
    
    // Get attendance records
    $stmt = $conn->prepare("SELECT a.*, s.topic, s.date, s.location, s.start_time, s.end_time 
                            FROM attendance a 
                            INNER JOIN sessions s ON a.session_id = s.session_id 
                            WHERE a.student_id = ? AND s.course_id = ? 
                            ORDER BY s.date DESC");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    header('Location: my_courses.php');
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Attendance - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></h1>
            <a href="my_courses.php" class="btn btn-secondary">← Back to Courses</a>
        </div>
        
        <div class="attendance-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Topic</th>
                        <th>Location</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance_records)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No attendance records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['topic']); ?></td>
                                <td><?php echo htmlspecialchars($record['location']); ?></td>
                                <td><?php echo date('g:i A', strtotime($record['start_time'])); ?> - <?php echo date('g:i A', strtotime($record['end_time'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($record['remarks'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

