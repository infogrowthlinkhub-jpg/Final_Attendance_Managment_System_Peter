<?php
require_once 'config.php';
requireFaculty();

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get faculty's courses
$stmt = $conn->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_code");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle session update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $session_id = intval($_POST['session_id'] ?? 0);
    $topic = sanitize($_POST['topic'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $date = $_POST['date'] ?? '';
    
    // Verify session belongs to faculty
    $stmt = $conn->prepare("SELECT s.session_id FROM sessions s 
                            INNER JOIN courses c ON s.course_id = c.course_id 
                            WHERE s.session_id = ? AND c.faculty_id = ?");
    $stmt->bind_param("ii", $session_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'Session not found or you do not have permission to edit it.';
        $message_type = 'error';
    } elseif (empty($topic) || empty($date) || empty($start_time) || empty($end_time)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE sessions SET topic = ?, location = ?, start_time = ?, end_time = ?, date = ? WHERE session_id = ?");
        $stmt->bind_param("sssssi", $topic, $location, $start_time, $end_time, $date, $session_id);
        
        if ($stmt->execute()) {
            $message = 'Session updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating session.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Handle session deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $session_id = intval($_POST['session_id'] ?? 0);
    
    // Verify session belongs to faculty
    $stmt = $conn->prepare("SELECT s.session_id FROM sessions s 
                            INNER JOIN courses c ON s.course_id = c.course_id 
                            WHERE s.session_id = ? AND c.faculty_id = ?");
    $stmt->bind_param("ii", $session_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'Session not found or you do not have permission to delete it.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM sessions WHERE session_id = ?");
        $stmt->bind_param("i", $session_id);
        
        if ($stmt->execute()) {
            $message = 'Session deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting session.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Handle session creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $topic = sanitize($_POST['topic'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $date = $_POST['date'] ?? '';
    
    // Verify course belongs to faculty
    $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $course_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'Invalid course selected.';
        $message_type = 'error';
    } elseif (empty($course_id) || empty($topic) || empty($date) || empty($start_time) || empty($end_time)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO sessions (course_id, topic, location, start_time, end_time, date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $course_id, $topic, $location, $start_time, $end_time, $date);
        
        if ($stmt->execute()) {
            $message = 'Session created successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error creating session.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Get all sessions for faculty's courses with attendance statistics
$course_ids = array_column($courses, 'course_id');
if (!empty($course_ids)) {
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT s.*, c.course_code, c.course_name,
                           (SELECT COUNT(*) FROM attendance WHERE session_id = s.session_id) as attendance_count,
                           (SELECT COUNT(*) FROM attendance WHERE session_id = s.session_id AND status = 'present') as present_count
                           FROM sessions s 
                           INNER JOIN courses c ON s.course_id = c.course_id 
                           WHERE s.course_id IN ($placeholders) 
                           ORDER BY s.date DESC, s.start_time DESC");
    $stmt->bind_param(str_repeat('i', count($course_ids)), ...$course_ids);
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $sessions = [];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Session Management</h1>
            <button class="btn btn-primary" onclick="document.getElementById('create-session-modal').style.display='block'">+ Create New Session</button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (empty($courses)): ?>
            <div class="alert alert-info">
                You need to create a course first before creating sessions. <a href="courses.php">Create Course</a>
            </div>
        <?php else: ?>
            <div class="sessions-list">
                <?php if (empty($sessions)): ?>
                    <div class="empty-state">
                        <p>No sessions yet. Create your first session!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Topic</th>
                                <th>Location</th>
                                <th>Time</th>
                                <th>Attendance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($session['date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($session['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($session['topic']); ?></td>
                                    <td><?php echo htmlspecialchars($session['location'] ?: '-'); ?></td>
                                    <td><?php echo date('g:i A', strtotime($session['start_time'])); ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $session['attendance_count']; ?> Marked</span>
                                        <?php if ($session['attendance_count'] > 0): ?>
                                            <span class="badge badge-present"><?php echo $session['present_count']; ?> Present</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="attendance.php?session_id=<?php echo $session['session_id']; ?>" class="btn btn-sm btn-primary">Take Attendance</a>
                                        <button class="btn btn-sm btn-secondary" onclick="openEditSessionModal(<?php echo htmlspecialchars(json_encode($session)); ?>)">Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this session? This will also delete all attendance records.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create Session Modal -->
    <div id="create-session-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateSessionModal()">&times;</span>
            <h2>Create New Session</h2>
            <form method="POST" action="sessions.php">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="course_id">Course *</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="topic">Topic *</label>
                    <input type="text" id="topic" name="topic" required placeholder="e.g., Introduction to Arrays" maxlength="150">
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="e.g., Room 101" maxlength="100">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Date *</label>
                        <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">Start Time *</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">End Time *</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateSessionModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Session</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Session Modal -->
    <div id="edit-session-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditSessionModal()">&times;</span>
            <h2>Edit Session</h2>
            <form method="POST" action="sessions.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="session_id" id="edit-session-id">
                
                <div class="form-group">
                    <label for="edit-topic">Topic *</label>
                    <input type="text" id="edit-topic" name="topic" required placeholder="e.g., Introduction to Arrays" maxlength="150">
                </div>
                
                <div class="form-group">
                    <label for="edit-location">Location</label>
                    <input type="text" id="edit-location" name="location" placeholder="e.g., Room 101" maxlength="100">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-date">Date *</label>
                        <input type="date" id="edit-date" name="date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-start_time">Start Time *</label>
                        <input type="time" id="edit-start_time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-end_time">End Time *</label>
                        <input type="time" id="edit-end_time" name="end_time" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditSessionModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Session</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        function closeCreateSessionModal() {
            document.getElementById('create-session-modal').style.display = 'none';
            document.querySelector('#create-session-modal form').reset();
            document.getElementById('date').value = '<?php echo date('Y-m-d'); ?>';
        }
        
        function openEditSessionModal(session) {
            document.getElementById('edit-session-id').value = session.session_id;
            document.getElementById('edit-topic').value = session.topic;
            document.getElementById('edit-location').value = session.location || '';
            document.getElementById('edit-date').value = session.date;
            document.getElementById('edit-start_time').value = session.start_time;
            document.getElementById('edit-end_time').value = session.end_time;
            document.getElementById('edit-session-modal').style.display = 'block';
        }
        
        function closeEditSessionModal() {
            document.getElementById('edit-session-modal').style.display = 'none';
        }
    </script>
</body>
</html>

