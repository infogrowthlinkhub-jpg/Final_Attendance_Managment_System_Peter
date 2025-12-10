<?php
require_once 'config.php';
requireFaculty();

$conn = getDBConnection();

if ($conn === false) {
    global $db_error;
    die("Database connection failed. Please contact the administrator.");
}

$faculty_id = $_SESSION['user_id'];

// Get faculty's courses
$stmt = $conn->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_code");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$report_data = [];

if ($selected_course_id > 0) {
    // Verify course belongs to faculty
    $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $selected_course_id, $faculty_id);
    $stmt->execute();
    $selected_course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($selected_course) {
        // Get all sessions for this course
        $stmt = $conn->prepare("SELECT * FROM sessions WHERE course_id = ? ORDER BY date DESC");
        $stmt->bind_param("i", $selected_course_id);
        $stmt->execute();
        $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get total enrolled students
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM course_student_list WHERE course_id = ?");
        $stmt->bind_param("i", $selected_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_students = $result->fetch_assoc()['count'];
        $stmt->close();
        
        // Calculate statistics for each session
        foreach ($sessions as $session) {
            $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE session_id = ? GROUP BY status");
            $stmt->bind_param("i", $session['session_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $present = 0;
            $absent = 0;
            $late = 0;
            
            while ($row = $result->fetch_assoc()) {
                if ($row['status'] === 'present') $present = $row['count'];
                if ($row['status'] === 'absent') $absent = $row['count'];
                if ($row['status'] === 'late') $late = $row['count'];
            }
            $stmt->close();
            
            $attendance_rate = $total_students > 0 ? ($present / $total_students * 100) : 0;
            
            $report_data[] = [
                'session' => $session,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'total_students' => $total_students,
                'attendance_rate' => round($attendance_rate, 2)
            ];
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Attendance Reports</h1>
        </div>
        
        <div class="report-section">
            <form method="GET" action="reports.php" class="report-filter">
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
            
            <?php if ($selected_course_id > 0 && !empty($report_data)): ?>
                <div class="report-summary">
                    <h3><?php echo htmlspecialchars($selected_course['course_code'] . ' - ' . $selected_course['course_name']); ?></h3>
                    <p>Total Enrolled Students: <strong><?php echo $total_students; ?></strong></p>
                </div>
                
                <div class="report-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Topic</th>
                                <th>Location</th>
                                <th>Time</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Attendance Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $data): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($data['session']['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($data['session']['topic']); ?></td>
                                    <td><?php echo htmlspecialchars($data['session']['location']); ?></td>
                                    <td><?php echo date('g:i A', strtotime($data['session']['start_time'])); ?> - <?php echo date('g:i A', strtotime($data['session']['end_time'])); ?></td>
                                    <td><span class="badge badge-present"><?php echo $data['present']; ?></span></td>
                                    <td><span class="badge badge-absent"><?php echo $data['absent']; ?></span></td>
                                    <td><span class="badge badge-late"><?php echo $data['late']; ?></span></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $data['attendance_rate']; ?>%"></div>
                                            <span><?php echo $data['attendance_rate']; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($selected_course_id > 0): ?>
                <div class="empty-state">
                    <p>No attendance data available for this course yet.</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Please select a course to view attendance reports.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

