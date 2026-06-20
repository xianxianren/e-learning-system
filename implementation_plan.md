# Interactive E-Learning System Implementation Plan

This document details the final architecture, folder layout, normalized database design, and verification plan for the Interactive E-Learning System for Primary School Students.

---

## 1. Core Architectural Strategy

* **Authentication Flow**: Students authenticate via a mobile-friendly 4-digit PIN selector pad. Teachers and Administrators authenticate using standard school email and password credentials. All login entry points reside in the project root to prevent relative directory routing errors and simplify session redirection.
* **Sound Effects**: To eliminate external media asset dependencies, all game sound effects (success chimes, error buzzers) are dynamically synthesized client-side via the browser's Web Audio API using pure `AudioContext` oscillator nodes.
* **Visual Styling Separation**:
  * **Students**: Bubbly primary-colored themes, giant touch targets, mobile-first viewports (`max-width: 480px`), and high-contrast circular progress rings.
  * **Staff (Teachers/Admins)**: Clean, desktop-first data-dense grids, metrics cards, table layouts, and management control panels.

---

## 2. Implemented Project Structure

The final implemented folder structure in the `c:\xampp\htdocs\e-learning system` workspace:

```
e-learning system/
├── admin/                     # Admin-facing viewports & system diagnostics
│   ├── dashboard.php          # Main administrative panel displaying storage metrics
│   ├── manage_curriculum.php  # CRUD editor for subjects
│   ├── manage_users.php       # Master portal to manage student and teacher accounts
│   └── system_reports.php     # System-wide reporting and active table diagnostics
├── admin_login.php            # Secure admin authentication page
├── assets/                    # Project asset storage directories
│   ├── css/                   # Stylesheets
│   │   ├── admin.css          # Styling rules for administrative views
│   │   ├── dashboard.css      # Styling rules for teacher portal views
│   │   └── student.css        # Colorful bubble styles for student views
│   ├── js/                    # Client-side scripts
│   │   ├── audio.js           # Web Audio API Synthesizer (oscillator sound generator)
│   │   ├── quiz_engine.js     # Unified student quiz game engine (5 question layouts)
│   │   └── student.js         # Student UI interactions, PIN keypad, and video player
│   ├── videos/                # Storage directory for local video uploads (.mp4)
│   └── images/
│       └── icons/             # Custom subject thumbnails and UI decoration icons
├── auth/                      # Session management utilities
│   └── logout.php             # Session destuction handler
├── config/                    # Configuration settings
│   └── db.php                 # Centralized PDO MySQL connection wrapper
├── database.sql               # Unified database schema and master seed definitions
├── implementation_plan.md     # Project PM guide and architecture plan
├── import_db.php              # Automated SQL importing utility
├── includes/                  # Common components and helpers
│   └── auth.php               # User authentication check hooks
├── index.php                  # Primary entry point router
├── login.php                  # Student mobile PIN keypad login (paginated 4x2 grid)
├── student/                   # Student-facing viewport scripts
│   ├── class_leaderboard.php  # Gamified class ranks list
│   ├── course.php             # Course subject timeline and navigation grid
│   ├── dashboard.php          # Home screen displaying XP, streaks, and standalone quizzes
│   ├── lesson.php             # Active lesson page handling multiple nested quizzes
│   ├── save_score.php         # Endpoint saving quiz attempt results
│   ├── student_quiz.php       # Mobile quiz player shell
│   ├── subjects.php           # Active subject list with progress calculation bars
│   └── update_progress.php    # Endpoint tracking lesson completion progress
├── teacher/                   # Teacher-facing viewport scripts
│   ├── dashboard.php          # Teacher analytics dashboard
│   ├── curriculum.php         # Curriculum, lessons list, and PDF uploads
│   ├── quizzes.php            # Interactive multi-type quiz builder interface
│   ├── review.php             # Contextual student profile dashboard & manual grading subpage
│   └── roster.php             # Student roster view and central grading hub
└── teacher_login.php          # Secure teacher authentication page
```

---

## 3. Implemented Database Schema (MySQL)

All interactions utilize standard PDO prepared statement mappings. Cascaded foreign key constraints are applied across tables to maintain database integrity:

```sql
-- 1. Table: users
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    class_section VARCHAR(50) NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: subjects
CREATE TABLE IF NOT EXISTS subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE,
    teacher_id INT NULL,
    icon_url VARCHAR(255) NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: lessons
CREATE TABLE IF NOT EXISTS lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    video_url VARCHAR(255) NULL,
    worksheet_url VARCHAR(255) NULL,
    teacher_notes TEXT NULL,
    order_num INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table: quizzes
CREATE TABLE IF NOT EXISTS quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NULL,
    class_section VARCHAR(50) NULL,
    quiz_title VARCHAR(150) DEFAULT 'Knowledge Check',
    total_marks INT NOT NULL DEFAULT 10,
    questions_json TEXT NOT NULL,
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table: questions
CREATE TABLE IF NOT EXISTS questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('single_choice', 'multiple_choice', 'fill_in_the_blank', 'drag_and_put', 'connecting_the_link') NOT NULL,
    order_num INT DEFAULT 1,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table: question_options
CREATE TABLE IF NOT EXISTS question_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT DEFAULT 0,
    matching_pair TEXT NULL,
    category VARCHAR(100) NULL,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table: student_progress
CREATE TABLE IF NOT EXISTS student_progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') NOT NULL DEFAULT 'not_started',
    last_accessed DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_student_progress (student_id, lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Table: quiz_scores
CREATE TABLE IF NOT EXISTS quiz_scores (
    score_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    quiz_id INT NOT NULL,
    marks_earned INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Table: gamification_stats
CREATE TABLE IF NOT EXISTS gamification_stats (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    total_points INT DEFAULT 0,
    login_streak INT DEFAULT 0,
    last_login DATE NULL,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Verification Plan

### Automated System Checks
- **Linter Auditing**: Running terminal lint commands on PHP scripts to prevent compile-time syntax failures.
- **Database Import Test**: Verify the integrity of `database.sql` queries against clean local MySQL database instances.

### Manual Testing Scenarios
- **Roster & Grading Loop**: Log in as a teacher, click a student name on the class roster, view their progress timeline, and adjust their bonus XP.
- **Student Engagement Loop**: Log in as a student using the 4x2 paginated avatar PIN keypad. Watch the custom HTML5 video lesson, complete the attached interactive quizzes, and verify that progress records are written in real-time.
- **Admin Garbage Collection Loop**: Upload a temporary course video, delete the parent lesson, and verify the physical video asset is removed from the directory.

---

## 5. Architectural Pivot Rationale

During the development cycle, several design pivots were executed to improve the performance, reliability, and security of the system:

1. **Migration to Normalized Database Schema**: The system moved from storing questions inside a unstructured JSON column (`quizzes.questions_json`) to a fully normalized database model (`questions` and `question_options` tables). This allows granular student analytics, robust performance, and seamless database-level tracking of complex interactive tasks like Drag & Put and Matching Pairs.
2. **Simplified Teacher Roster Grading Interface**: Legacy workflows utilizing stand-alone pages for manual searches (`search.php`) and separate grade pages (`grade.php`) were integrated directly into the `roster.php` class list. Clicking a student on the roster now dynamically transitions into a detailed review modal or subpage, eliminating unnecessary menus and dropdown choices.
3. **Local Storage Ownership**: Video paths and curricular assets are hosted locally inside the `/assets/` directory rather than utilizing external source links. This prevents breaking page states when third-party streaming resources expire and allows the Administrator to enforce storage metric limits using standard file size calculations.
4. **Pure Audio Synthesizer implementation**: Rather than downloading, serving, and caching static audio assets, sound generation was offloaded entirely to client-side browsers using Web Audio API nodes. This reduces total project package size and ensures immediate compatibility on mobile layouts without browser cross-origin policy blockages.
