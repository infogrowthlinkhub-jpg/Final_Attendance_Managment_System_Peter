<?php
require_once 'config.php';
requireFaculty();

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get session ID from URL or form
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : (isset($_POST['session_id']) ? intval($_POST['session_id']) : 0);

// Verify session belongs to faculty
$session = null;
if ($session_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, c.course_code, c.course_name FROM sessions s 
                            INNER JOIN courses c ON s.course_id = c.course_id 
                            WHERE s.session_id = ? AND c.faculty_id = ?");
    $stmt->bind_param("ii", $session_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();
}

// Get all sessions for faculty's courses
$stmt = $conn->prepare("SELECT s.*, c.course_code FROM sessions s 
                        INNER JOIN courses c ON s.course_id = c.course_id 
                        WHERE c.faculty_id = ? 
                        ORDER BY s.date DESC, s.start_time DESC");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$all_sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    if ($session_id > 0 && $student_id > 0 && in_array($status, ['present', 'absent', 'late'])) {
        // Check if attendance already exists
        $stmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE session_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $session_id, $student_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            // Update existing
            $stmt = $conn->prepare("UPDATE attendance SET status = ?, remarks = ? WHERE attendance_id = ?");
            $stmt->bind_param("ssi", $status, $remarks, $existing['attendance_id']);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO attendance (session_id, student_id, status, remarks) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $session_id, $student_id, $status, $remarks);
        }
        
        if ($stmt->execute()) {
            $message = 'Attendance marked successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error marking attendance.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Get enrolled students for the session's course
$students = [];
if ($session) {
    $stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, u.email 
                            FROM users u 
                            INNER JOIN course_student_list csl ON u.user_id = csl.student_id 
                            WHERE csl.course_id = ? 
                            ORDER BY u.last_name, u.first_name");
    $stmt->bind_param("i", $session['course_id']);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get existing attendance records
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE session_id = ?");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Create a map for quick lookup
    $attendance_map = [];
    foreach ($attendance_records as $record) {
        $attendance_map[$record['student_id']] = $record;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Mark Attendance</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="attendance-section">
            <div class="session-selector">
                <form method="GET" action="attendance.php" class="inline-form">
                    <div class="form-group">
                        <label for="session_id">Select Session:</label>
                        <select id="session_id" name="session_id" onchange="this.form.submit()">
                            <option value="">-- Select a session --</option>
                            <?php foreach ($all_sessions as $s): ?>
                                <option value="<?php echo $s['session_id']; ?>" <?php echo ($session_id == $s['session_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['course_code'] . ' - ' . date('M d, Y', strtotime($s['date'])) . ' (' . $s['topic'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <?php if ($session): ?>
                <div class="session-info">
                    <h3><?php echo htmlspecialchars($session['course_code'] . ' - ' . $session['course_name']); ?></h3>
                    <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($session['date'])); ?></p>
                    <p><strong>Topic:</strong> <?php echo htmlspecialchars($session['topic']); ?></p>
                    <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($session['start_time'])); ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($session['location']); ?></p>
                </div>
                
                <?php if (empty($students)): ?>
                    <div class="alert alert-info">
                        No students enrolled in this course. <a href="course_details.php?id=<?php echo $session['course_id']; ?>">Enroll students</a>
                    </div>
                <?php else: ?>
                    <?php
                    // Calculate attendance statistics
                    $total_students = count($students);
                    $marked_count = count($attendance_map);
                    $present_count = 0;
                    $absent_count = 0;
                    $late_count = 0;
                    foreach ($attendance_map as $record) {
                        if ($record['status'] === 'present') $present_count++;
                        if ($record['status'] === 'absent') $absent_count++;
                        if ($record['status'] === 'late') $late_count++;
                    }
                    ?>
                    <div class="attendance-stats">
                        <div class="stat-card">
                            <div class="stat-content">
                                <h3><?php echo $total_students; ?></h3>
                                <p>Total Students</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <h3><?php echo $marked_count; ?></h3>
                                <p>Marked</p>
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
                                <h3><?php echo $late_count; ?></h3>
                                <p>Late</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="attendance-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <?php 
                                    $existing = $attendance_map[$student['user_id']] ?? null;
                                    $current_status = $existing ? $existing['status'] : '';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <?php if ($current_status): ?>
                                                <span class="badge badge-<?php echo $current_status; ?>"><?php echo ucfirst($current_status); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-pending">Not Marked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $existing ? htmlspecialchars($existing['remarks']) : '-'; ?></td>
                                        <td>
                                            <div class="quick-actions-inline">
                                                <button class="btn btn-sm btn-success" onclick="quickMark(<?php echo $student['user_id']; ?>, 'present', '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" title="Mark Present">✓</button>
                                                <button class="btn btn-sm btn-danger" onclick="quickMark(<?php echo $student['user_id']; ?>, 'absent', '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" title="Mark Absent">✗</button>
                                                <button class="btn btn-sm btn-warning" onclick="quickMark(<?php echo $student['user_id']; ?>, 'late', '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" title="Mark Late">⏰</button>
                                                <button class="btn btn-sm btn-primary" onclick="openAttendanceModal(<?php echo $student['user_id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>', '<?php echo $current_status; ?>', '<?php echo $existing ? htmlspecialchars($existing['remarks']) : ''; ?>')">
                                                    <?php echo $existing ? 'Edit' : 'Details'; ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>Please select a session to mark attendance.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Attendance Modal -->
    <div id="attendance-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAttendanceModal()">&times;</span>
            <h2>Mark Attendance</h2>
            <form method="POST" action="attendance.php">
                <input type="hidden" name="action" value="mark">
                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                <input type="hidden" name="student_id" id="modal-student-id">
                
                <div class="form-group">
                    <label>Student:</label>
                    <p id="modal-student-name" class="form-readonly"></p>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3" placeholder="Optional remarks..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAttendanceModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        function openAttendanceModal(studentId, studentName, currentStatus, currentRemarks) {
            document.getElementById('modal-student-id').value = studentId;
            document.getElementById('modal-student-name').textContent = studentName;
            document.getElementById('status').value = currentStatus || 'present';
            document.getElementById('remarks').value = currentRemarks || '';
            document.getElementById('attendance-modal').style.display = 'block';
        }
        
        function closeAttendanceModal() {
            document.getElementById('attendance-modal').style.display = 'none';
        }
        
        function quickMark(studentId, status, studentName) {
            if (confirm('Mark ' + studentName + ' as ' + status.toUpperCase() + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'attendance.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'mark';
                form.appendChild(actionInput);
                
                const sessionInput = document.createElement('input');
                sessionInput.type = 'hidden';
                sessionInput.name = 'session_id';
                sessionInput.value = '<?php echo $session_id; ?>';
                form.appendChild(sessionInput);
                
                const studentInput = document.createElement('input');
                studentInput.type = 'hidden';
                studentInput.name = 'student_id';
                studentInput.value = studentId;
                form.appendChild(studentInput);
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = status;
                form.appendChild(statusInput);
                
                const remarksInput = document.createElement('input');
                remarksInput.type = 'hidden';
                remarksInput.name = 'remarks';
                remarksInput.value = '';
                form.appendChild(remarksInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

