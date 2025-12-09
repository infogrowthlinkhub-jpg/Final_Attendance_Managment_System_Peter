-- Attendance Management System Database Schema

-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'faculty', 'admin') NOT NULL,
    dob DATE
);

-- Students table (only contains users with role = 'student')
CREATE TABLE students (
    student_id INT PRIMARY KEY,  -- references user_id from users
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Faculty table (only contains users with role = 'faculty')
CREATE TABLE faculty (
    faculty_id INT PRIMARY KEY,  -- references user_id from users
    FOREIGN KEY (faculty_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Courses table (each course is taught by one faculty)
CREATE TABLE courses (
    course_id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(20) UNIQUE,
    course_name VARCHAR(150) NOT NULL,
    description TEXT,
    credit_hours INT,
    faculty_id INT NOT NULL,  -- references faculty(faculty_id)
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    INDEX idx_faculty (faculty_id)
);

-- Course_Student_List table (students enrolled in courses)
CREATE TABLE course_student_list (
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (course_id, student_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_course (course_id)
);

-- Sessions table (each session belongs to a course)
CREATE TABLE sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    topic VARCHAR(150),
    location VARCHAR(100),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    date DATE NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    INDEX idx_course_date (course_id, date)
);

-- Attendance table (tracks student attendance per session)
CREATE TABLE attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    student_id INT NOT NULL,  -- references students(student_id)
    status ENUM('present', 'absent', 'late') NOT NULL,
    check_in_time TIME,
    remarks TEXT,
    FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (session_id, student_id),
    INDEX idx_session (session_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
);