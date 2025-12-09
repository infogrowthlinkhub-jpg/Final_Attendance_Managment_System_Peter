<?php
require_once 'config.php';
requireFaculty();

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $course_code = sanitize($_POST['course_code'] ?? '');
    $course_name = sanitize($_POST['course_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $credit_hours = intval($_POST['credit_hours'] ?? 0);
    
    if (empty($course_code) || empty($course_name)) {
        $message = 'Course code and name are required.';
        $message_type = 'error';
    } else {
        // Check if course code already exists
        $check_stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ?");
        $check_stmt->bind_param("s", $course_code);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Course code "' . htmlspecialchars($course_code) . '" already exists. Please use a different course code.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, credit_hours, faculty_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $course_code, $course_name, $description, $credit_hours, $faculty_id);
            
            if ($stmt->execute()) {
                $message = 'Course created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error creating course: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Handle course update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $course_code = sanitize($_POST['course_code'] ?? '');
    $course_name = sanitize($_POST['course_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $credit_hours = intval($_POST['credit_hours'] ?? 0);
    
    // Verify course belongs to faculty
    $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $course_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'Course not found or you do not have permission to edit it.';
        $message_type = 'error';
        $stmt->close();
    } elseif (empty($course_code) || empty($course_name)) {
        $message = 'Course code and name are required.';
        $message_type = 'error';
        $stmt->close();
    } else {
        $stmt->close();
        
        // Check if course code already exists (excluding current course)
        $check_stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ? AND course_id != ?");
        $check_stmt->bind_param("si", $course_code, $course_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'Course code "' . htmlspecialchars($course_code) . '" already exists. Please use a different course code.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $stmt = $conn->prepare("UPDATE courses SET course_code = ?, course_name = ?, description = ?, credit_hours = ? WHERE course_id = ? AND faculty_id = ?");
            $stmt->bind_param("sssiii", $course_code, $course_name, $description, $credit_hours, $course_id, $faculty_id);
            
            if ($stmt->execute()) {
                $message = 'Course updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating course: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Handle course deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $course_id = intval($_POST['course_id'] ?? 0);
    
    // Verify course belongs to faculty
    $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $course_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'Course not found or you do not have permission to delete it.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ? AND faculty_id = ?");
        $stmt->bind_param("ii", $course_id, $faculty_id);
        
        if ($stmt->execute()) {
            $message = 'Course deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting course. It may have associated sessions or enrollments.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Get all courses for this faculty with statistics
$stmt = $conn->prepare("SELECT c.*, 
                       (SELECT COUNT(*) FROM course_student_list WHERE course_id = c.course_id) as student_count,
                       (SELECT COUNT(*) FROM sessions WHERE course_id = c.course_id) as session_count
                       FROM courses c 
                       WHERE c.faculty_id = ? 
                       ORDER BY c.course_code");
$stmt->bind_param("i", $faculty_id);
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
    <title>Courses - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Course Management</h1>
            <button class="btn btn-primary" onclick="document.getElementById('create-course-modal').style.display='block'">+ Create New Course</button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="courses-grid">
            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <p>No courses yet. Create your first course!</p>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?php echo htmlspecialchars($course['course_code']); ?></h3>
                            <span class="badge"><?php echo $course['credit_hours']; ?> Credits</span>
                        </div>
                        <h4><?php echo htmlspecialchars($course['course_name']); ?></h4>
                        <p><?php echo htmlspecialchars($course['description'] ?: 'No description provided.'); ?></p>
                        <div class="course-stats">
                            <span>👥 <?php echo $course['student_count']; ?> Students</span>
                            <span>📅 <?php echo $course['session_count']; ?> Sessions</span>
                        </div>
                        <div class="course-actions">
                            <a href="course_details.php?id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                            <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($course)); ?>)">Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this course? This will also delete all sessions and enrollments.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Course Modal -->
    <div id="create-course-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateModal()">&times;</span>
            <h2>Create New Course</h2>
            <form method="POST" action="courses.php">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="course_code">Course Code *</label>
                    <input type="text" id="course_code" name="course_code" required placeholder="e.g., CS101" maxlength="20">
                    <small>Unique course identifier (e.g., CS101, MATH201)</small>
                </div>
                
                <div class="form-group">
                    <label for="course_name">Course Name *</label>
                    <input type="text" id="course_name" name="course_name" required placeholder="e.g., Introduction to Computer Science" maxlength="150">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Course description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="credit_hours">Credit Hours</label>
                    <input type="number" id="credit_hours" name="credit_hours" min="1" max="6" value="3" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Course</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Course Modal -->
    <div id="edit-course-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Course</h2>
            <form method="POST" action="courses.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="course_id" id="edit-course-id">
                
                <div class="form-group">
                    <label for="edit-course_code">Course Code *</label>
                    <input type="text" id="edit-course_code" name="course_code" required placeholder="e.g., CS101" maxlength="20">
                    <small>Unique course identifier</small>
                </div>
                
                <div class="form-group">
                    <label for="edit-course_name">Course Name *</label>
                    <input type="text" id="edit-course_name" name="course_name" required placeholder="e.g., Introduction to Computer Science" maxlength="150">
                </div>
                
                <div class="form-group">
                    <label for="edit-description">Description</label>
                    <textarea id="edit-description" name="description" rows="3" placeholder="Course description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit-credit_hours">Credit Hours</label>
                    <input type="number" id="edit-credit_hours" name="credit_hours" min="1" max="6" value="3" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        function closeCreateModal() {
            document.getElementById('create-course-modal').style.display = 'none';
            document.querySelector('#create-course-modal form').reset();
        }
        
        function openEditModal(course) {
            document.getElementById('edit-course-id').value = course.course_id;
            document.getElementById('edit-course_code').value = course.course_code;
            document.getElementById('edit-course_name').value = course.course_name;
            document.getElementById('edit-description').value = course.description || '';
            document.getElementById('edit-credit_hours').value = course.credit_hours;
            document.getElementById('edit-course-modal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('edit-course-modal').style.display = 'none';
        }
    </script>
</body>
</html>

