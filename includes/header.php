<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <a href="dashboard.php" style="text-decoration: none; color: inherit;">
                <h2>📚 Attendance Manager</h2>
            </a>
        </div>
        <nav class="main-nav">
            <a href="dashboard.php">Dashboard</a>
            <?php if (hasRole('faculty')): ?>
                <a href="courses.php">Courses</a>
                <a href="sessions.php">Sessions</a>
                <a href="attendance.php">Attendance</a>
                <a href="reports.php">Reports</a>
            <?php else: ?>
                <a href="my_courses.php">My Courses</a>
                <a href="my_attendance.php">My Attendance</a>
            <?php endif; ?>
        </nav>
        <div class="user-menu">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        </div>
    </div>
</header>

