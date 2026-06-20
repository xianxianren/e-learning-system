# Interactive E-Learning System Implementation Plan

This document details the architecture, file layout, database design, and feature set for the Interactive E-Learning System for Primary School Students. 

## User Review Required

> [!IMPORTANT]
> - **Authentication Flow**: Students will log in using a 4-digit PIN. Teachers and Admins will log in using standard email and password credentials.
> - **Sound Effects**: To remain strictly original and avoid external files, sound effects (success chime, error buzzer) will be dynamically synthesized on the client-side using the HTML5 Web Audio API.
> - **Visual Style Separation**: 
>   - Students: Bright colors, rounded cards, large tap-friendly buttons, mobile-first layouts.
>   - Staff (Teachers/Admins): Professional, data-driven grids, clean toolbars, and dashboards.

## Project Structure Plan

The following structure is planned for the `c:\xampp\htdocs\e-learning system` workspace:

```
e-learning system/
├── assets/                    # Project assets containing stylesheets, scripts, audio, and user uploads
│   ├── css/                  # Styling files separated by role/style needs
│   │   ├── student.css       # Colorful, bubbly, mobile-first design for students
│   │   └── dashboard.css     # Professional, clean layout for teacher and admin web dashboards
│   ├── js/                   # Front-end JavaScript logic files
│   │   ├── audio.js          # Web Audio API script for synthesizing gamification success/error sound effects
│   │   ├── student.js        # Handles student quiz validation, drag-and-drop matching, and custom HTML5 video controls
│   │   └── dashboard.js      # Handles staff table search/filtering and dynamic DOM actions
│   ├── audio/                # Directory for gamification audio files (fallback if any)
│   ├── videos/               # Storage directory for educational course video content
│   └── uploads/              # Storage directory for student homework file uploads
├── config/                   # Configuration files for database and system-wide constants
│   └── db.php                # PDO-based MySQL database connection setup
├── includes/                 # Shared PHP backend helper scripts and components
│   ├── auth.php              # Handles user login status, role checks, and session security
│   ├── header.php            # Shared navbar/header that changes theme based on the user's role
│   └── footer.php            # Shared footer layout with copyright and system information
├── auth/                     # Authentication pages for login and logout
│   ├── login.php             # Unified login handler (PIN dialer for students, Email/Password form for staff)
│   └── logout.php            # Destroys current session and redirects to homepage
├── student/                  # Student-facing views (mobile-first UI)
│   ├── dashboard.php         # Student hub showing XP bar, daily streaks, badges, and assigned lessons
│   ├── course.php            # Visual subject list (Math, Science) with To-Do and Completed category filters
│   ├── lesson.php            # Interactive lesson viewer with a custom video player and homework submission form
│   └── quiz.php              # Playful quiz taker with interactive questions and real-time audio feedback
├── teacher/                  # Teacher-facing views (web dashboard UI)
│   ├── dashboard.php         # Teacher home displaying class progress analytics and pending homework notifications
│   ├── search.php            # Dynamic endpoint for AJAX-based student list searches
│   ├── curriculum.php        # Interface to create/edit subjects and add lessons to classes
│   ├── quizzes.php           # Visual builder to create interactive quizzes stored in JSON format
│   └── grade.php             # Secure interface to download student homework and assign grades or bonus points
├── admin/                    # Admin-facing views (web dashboard UI)
│   ├── dashboard.php         # System summary including active users counts and basic server metrics
│   ├── users.php             # Management console for creating, editing, and deleting student/teacher profiles
│   ├── curriculum.php        # Interface to manage and approve subjects in the master course list
│   └── storage.php           # Displays server storage allocation and media file usage logs
├── database.sql              # MySQL database schema definition file for quick import
├── index.php                 # Core entry point redirecting users to login or their respective dashboard
```

## Proposed Database Schema (MySQL)

We will use PDO with prepared statements to run queries. The tables are structured as follows:

```sql
-- User accounts table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    pin VARCHAR(4) NULL, -- Used for Student PIN login
    role ENUM('student', 'teacher', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Profiles and gamification metrics
CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    avatar_url VARCHAR(255) DEFAULT 'default_avatar.png',
    grade VARCHAR(20) DEFAULT NULL,
    class_section VARCHAR(20) DEFAULT NULL,
    xp INT DEFAULT 0,
    streak INT DEFAULT 0,
    last_login_date DATE DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Master list of academic subjects
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    icon_name VARCHAR(50) DEFAULT 'book', -- CSS icon descriptor
    is_approved TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lessons within subjects
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    content_text TEXT,
    video_url VARCHAR(255) DEFAULT NULL, -- Local or secure URL
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quizzes associated with lessons
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    questions_json TEXT NOT NULL, -- JSON structure of quiz questions
    xp_reward INT DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz attempts by students
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    quiz_id INT NOT NULL,
    score INT NOT NULL,
    max_score INT NOT NULL,
    xp_earned INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gamification badges available
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon_name VARCHAR(50) NOT NULL, -- e.g., 'star', 'fire'
    xp_required INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Badges unlocked by students
CREATE TABLE IF NOT EXISTS student_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    badge_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_badge (student_id, badge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Homework files submitted by students
CREATE TABLE IF NOT EXISTS homework (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(100) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    grade_points INT DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    graded_by INT DEFAULT NULL,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Action logs for administrators
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Verification Plan

### Automated Tests
- We will execute browser-based checks via the subagent tool to confirm:
  - Responsive alignment of student portal mobile cards.
  - Verification of PIN login dial-pad entry.
  - Live sound generation testing (making sure no web audio issues arise).
  - Validation of AJAX data reloading during search queries in the teacher portal.

### Manual Verification
- Testing registration of students, teachers, and admins, ensuring roles are strictly separated.
- Simulating a student course path: logging in, watching a short mock video file, uploading a dummy PDF homework file, taking an interactive quiz, earning points/badges, and checking the updated profile stats.
- Logging in as a teacher to review files, approve submissions, add custom bonus points, and view stats.
- Logging in as an admin to confirm dashboard indicators update correctly.
