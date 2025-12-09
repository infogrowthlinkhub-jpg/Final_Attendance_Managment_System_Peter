<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$student_id = $_SESSION['user_id'];

// Get enrolled courses
$stmt = $conn->prepare("SELECT c.* FROM courses c 
                        INNER JOIN course_student_list csl ON c.course_id = csl.course_id 
                        WHERE csl.student_id = ? 
                        ORDER BY c.course_code");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$attendance_records = [];

if ($selected_course_id > 0) {
    // Verify student is enrolled
    $stmt = $conn->prepare("SELECT * FROM course_student_list WHERE course_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $selected_course_id, $student_id);
    $stmt->execute();
    $enrolled = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($enrolled) {
        // Get course info
        $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
        $stmt->bind_param("i", $selected_course_id);
        $stmt->execute();
        $selected_course = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get all sessions for this course
        $stmt = $conn->prepare("SELECT * FROM sessions WHERE course_id = ? ORDER BY date DESC");
        $stmt->bind_param("i", $selected_course_id);
        $stmt->execute();
        $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get attendance records
        $session_ids = array_column($sessions, 'session_id');
        if (!empty($session_ids)) {
            $placeholders = str_repeat('?,', count($session_ids) - 1) . '?';
            $stmt = $conn->prepare("SELECT a.*, s.topic, s.date, s.location, s.start_time, s.end_time 
                                    FROM attendance a 
                                    INNER JOIN sessions s ON a.session_id = s.session_id 
                                    WHERE a.student_id = ? AND a.session_id IN ($placeholders) 
                                    ORDER BY s.date DESC");
            $params = array_merge([$student_id], $session_ids);
            $stmt->bind_param(str_repeat('i', count($params)), ...$params);
            $stmt->execute();
            $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        
        // Calculate statistics
        $total_sessions = count($sessions);
        $present_count = 0;
        $absent_count = 0;
        $late_count = 0;
        
        foreach ($attendance_records as $record) {
            if ($record['status'] === 'present') $present_count++;
            if ($record['status'] === 'absent') $absent_count++;
            if ($record['status'] === 'late') $late_count++;
        }
        
        $attendance_rate = $total_sessions > 0 ? ($present_count / $total_sessions * 100) : 0;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>My Attendance</h1>
        </div>
        
        <div class="attendance-section">
            <form method="GET" action="my_attendance.php" class="report-filter">
                <div class="form-group">
                    <label for="course_id">Select Course:</label>
                    <select id="course_id" name="course_id" onchange="this.form.submit()">
                        <option value="">-- Select a course --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>" <?php echo ($selected_course_id == $course['course_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            
            <?php if ($selected_course_id > 0 && isset($selected_course)): ?>
                <div class="attendance-summary">
                    <h3><?php echo htmlspecialchars($selected_course['course_code'] . ' - ' . $selected_course['course_name']); ?></h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-content">
                                <h3><?php echo $total_sessions; ?></h3>
                                <p>Total Sessions</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <h3><?php echo $present_count; ?></h3>
                                <p>Present</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <h3><?php echo $absent_count; ?></h3>
                                <p>Absent</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <h3><?php echo round($attendance_rate, 1); ?>%</h3>
                                <p>Attendance Rate</p>
                            </div>
                        </div>
                    </div>
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
            <?php elseif ($selected_course_id > 0): ?>
                <div class="alert alert-error">You are not enrolled in this course.</div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Please select a course to view your attendance records.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

