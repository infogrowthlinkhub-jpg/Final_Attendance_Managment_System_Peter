<?php
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="landing-page">
    <!-- Navigation -->
    <nav class="landing-nav">
        <div class="nav-container">
            <div class="nav-logo">
                <span class="logo-icon">📚</span>
                <span class="logo-text">Attendance Manager</span>
            </div>
            <div class="nav-actions">
                <a href="login.php" class="nav-link">Sign In</a>
                <a href="signup.php" class="nav-btn">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title">Streamline Your Attendance Management</h1>
                <p class="hero-description">A comprehensive platform for tracking student attendance, managing courses, and generating detailed reports. Built for educational institutions.</p>
                <div class="hero-cta">
                    <a href="signup.php" class="cta-primary">Create Account</a>
                    <a href="login.php" class="cta-secondary">Sign In</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="section-container">
            <div class="section-header">
                <h2>Why Choose Our System</h2>
                <p>Everything you need to manage attendance efficiently</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>User Management</h3>
                    <p>Separate portals for students and faculty with role-based access control</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📖</div>
                    <h3>Course Management</h3>
                    <p>Create, manage, and organize courses with student enrollment</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Session Tracking</h3>
                    <p>Schedule class sessions with topics, locations, and time slots</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">✓</div>
                    <h3>Quick Attendance</h3>
                    <p>Mark attendance quickly with one-click status updates</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Analytics & Reports</h3>
                    <p>Generate comprehensive attendance reports and statistics</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Secure & Reliable</h3>
                    <p>Your data is protected with industry-standard security measures</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="section-container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Secure</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Accessible</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">Easy</div>
                    <div class="stat-label">To Use</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">Fast</div>
                    <div class="stat-label">Performance</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="section-container">
            <div class="cta-content">
                <h2>Ready to Get Started?</h2>
                <p>Join thousands of educators managing attendance efficiently</p>
                <div class="cta-buttons">
                    <a href="signup.php" class="cta-primary-large">Create Your Account</a>
                    <a href="login.php" class="cta-secondary-large">Sign In</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-container">
            <p>&copy; <?php echo date('Y'); ?> Attendance Management System. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>

