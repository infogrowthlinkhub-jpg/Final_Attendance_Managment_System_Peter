<?php
require_once 'config.php';
requireFaculty();

$conn = getDBConnection();
$faculty_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$message_type = '';

// Verify course belongs to faculty
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ? AND faculty_id = ?");
$stmt->bind_param("ii", $course_id, $faculty_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Handle student unenrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unenroll') {
    $student_id = intval($_POST['student_id'] ?? 0);
    
    if ($student_id > 0) {
        $stmt = $conn->prepare("DELETE FROM course_student_list WHERE course_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $course_id, $student_id);
        
        if ($stmt->execute()) {
            $message = 'Student unenrolled successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error unenrolling student.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Handle student enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter an email address.';
        $message_type = 'error';
    } else {
        // Find student by email
        $stmt = $conn->prepare("SELECT u.user_id FROM users u INNER JOIN students s ON u.user_id = s.student_id WHERE u.email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $message = 'Student not found with this email.';
            $message_type = 'error';
        } else {
            $student = $result->fetch_assoc();
            $student_id = $student['user_id'];
            
            // Check if already enrolled
            $stmt2 = $conn->prepare("SELECT * FROM course_student_list WHERE course_id = ? AND student_id = ?");
            $stmt2->bind_param("ii", $course_id, $student_id);
            $stmt2->execute();
            $existing = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            
            if ($existing) {
                $message = 'Student is already enrolled in this course.';
                $message_type = 'error';
            } else {
                $stmt2 = $conn->prepare("INSERT INTO course_student_list (course_id, student_id) VALUES (?, ?)");
                $stmt2->bind_param("ii", $course_id, $student_id);
                
                if ($stmt2->execute()) {
                    $message = 'Student enrolled successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error enrolling student.';
                    $message_type = 'error';
                }
                $stmt2->close();
            }
        }
        $stmt->close();
    }
}

// Get enrolled students
$stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, u.email 
                        FROM users u 
                        INNER JOIN course_student_list csl ON u.user_id = csl.student_id 
                        WHERE csl.course_id = ? 
                        ORDER BY u.last_name, u.first_name");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Details - Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></h1>
            <a href="courses.php" class="btn btn-secondary">← Back to Courses</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="course-details">
            <div class="detail-card">
                <h3>Course Information</h3>
                <p><strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
                <p><strong>Course Name:</strong> <?php echo htmlspecialchars($course['course_name']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($course['description'] ?: 'No description'); ?></p>
                <p><strong>Credit Hours:</strong> <?php echo $course['credit_hours']; ?></p>
            </div>
            
            <div class="detail-card">
                <div class="card-header">
                    <h3>Enrolled Students (<?php echo count($students); ?>)</h3>
                    <button class="btn btn-sm btn-primary" onclick="document.getElementById('enroll-modal').style.display='block'">+ Enroll Student</button>
                </div>
                
                <?php if (empty($students)): ?>
                    <p class="empty-state">No students enrolled yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to unenroll this student?');">
                                            <input type="hidden" name="action" value="unenroll">
                                            <input type="hidden" name="student_id" value="<?php echo $student['user_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Unenroll</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Enroll Student Modal -->
    <div id="enroll-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('enroll-modal').style.display='none'">&times;</span>
            <h2>Enroll Student</h2>
            <form method="POST" action="course_details.php?id=<?php echo $course_id; ?>">
                <input type="hidden" name="action" value="enroll">
                
                <div class="form-group">
                    <label for="email">Student Email *</label>
                    <input type="email" id="email" name="email" required placeholder="student@example.com">
                    <small>Enter the email address of the student to enroll.</small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('enroll-modal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

