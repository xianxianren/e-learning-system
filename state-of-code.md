# State of Code - Interactive E-Learning System

This document serves as the master technical reference and architectural blueprint for the **Interactive E-Learning System for Primary School Students**.

---

## 1. Project Overview & Constraints

* **Project Name:** Interactive E-Learning System for Primary School Students
* **Target Audience:**
  * **Students:** Ages 7-12. Mobile-first viewport (`max-width: 480px`), highly visual, bubbly primary-colored theme, rounded touch targets, minimal text, and high gamification feedback.
  * **Teachers & Admins:** Desktop-first (1440px width), professional layout, data-dense metrics dashboards, tables, and content creation forms.
* **Tech Stack:**
  * **Front-end:** HTML5, CSS3 (using custom CSS variables for design consistency), Vanilla JavaScript.
  * **Back-end:** PHP (PDO API wrapper) and MySQL Database.
  * **Dependencies:** Strict **No External Libraries or Frameworks** rule. All interactive features (sound effects, drag-and-drop systems, dynamic builders) are written in pure Vanilla HTML/CSS/JS.
* **System Constraints:** Zero runtime errors, input filtering/escaping for DB inserts, role-based authorization, and offline local-execution compatibility.

---

## 2. Master Database Schema

The database relies on a relational MySQL structure with cascade deletes to maintain relational integrity.

```mermaid
erDiagram
    users ||--o{ student_progress : tracks
    users ||--o{ quiz_scores : earns
    users ||--o| gamification_stats : accumulates
    subjects ||--o{ lessons : contains
    lessons ||--o| quizzes : evaluates
    lessons ||--o{ student_progress : associates
    quizzes ||--o{ quiz_scores : scores
    quizzes ||--o{ questions : contains
    questions ||--o{ question_options : contains
```

### Table Specifications

#### 1. `users`
Holds accounts for students, teachers, and system administrators.
* `user_id` (INT, PK, AUTO_INCREMENT)
* `username` (VARCHAR(100), UNIQUE) - Student username (e.g. 'timmy') or staff email.
* `password_hash` (VARCHAR(255)) - Bcrypt hash of student 4-digit PIN or staff password.
* `role` (ENUM('student', 'teacher', 'admin')) - Permission access tier.
* `full_name` (VARCHAR(100)) - Screen display name.
* `class_section` (VARCHAR(50), NULL) - Target school cohort.
* `avatar_url` (VARCHAR(255), NULL) - Character avatar name (e.g. 'monkey', 'bunny').
* `created_at` (TIMESTAMP)

#### 2. `subjects`
Topic areas created by admins.
* `subject_id` (INT, PK, AUTO_INCREMENT)
* `subject_name` (VARCHAR(100), UNIQUE)
* `teacher_id` (INT, FK, NULL) - Links to `users.user_id` (ON DELETE SET NULL).
* `icon_url` (VARCHAR(255), NULL)

#### 3. `lessons`
Worksheet and video files associated with a parent subject.
* `lesson_id` (INT, PK, AUTO_INCREMENT)
* `subject_id` (INT, FK) - Links to `subjects.subject_id` (ON DELETE CASCADE).
* `title` (VARCHAR(150))
* `video_url` (VARCHAR(255), NULL) - Path to video file.
* `worksheet_url` (VARCHAR(255), NULL) - Path to downloadable PDF.
* `order_num` (INT, DEFAULT 1) - Grid ordering sequence.
* `created_at` (TIMESTAMP)

#### 4. `student_progress`
Logs student curriculum completion statuses.
* `progress_id` (INT, PK, AUTO_INCREMENT)
* `student_id` (INT, FK) - Links to `users.user_id` (ON DELETE CASCADE).
* `lesson_id` (INT, FK) - Links to `lessons.lesson_id` (ON DELETE CASCADE).
* `status` (ENUM('not_started', 'in_progress', 'completed'), DEFAULT 'not_started')
* `last_accessed` (DATETIME)

#### 5. `quizzes`
Evaluation configurations linked to lessons.
* `quiz_id` (INT, PK, AUTO_INCREMENT)
* `lesson_id` (INT, UNIQUE, FK) - Links to `lessons.lesson_id` (ON DELETE CASCADE).
* `total_marks` (INT, DEFAULT 10)
* `questions_json` (TEXT) - Fallback serialized JSON representation of questions for student app compatibility.

#### 6. `questions`
Relational database storage of quiz questions.
* `question_id` (INT, PK, AUTO_INCREMENT)
* `quiz_id` (INT, FK) - Links to `quizzes.quiz_id` (ON DELETE CASCADE).
* `question_text` (TEXT)
* `question_type` (ENUM('single_choice', 'multiple_choice', 'fill_in_the_blank', 'drag_and_put', 'connecting_the_link'))
* `order_num` (INT, DEFAULT 1)

#### 7. `question_options`
Relational database storage of options, correct answers, and categories.
* `option_id` (INT, PK, AUTO_INCREMENT)
* `question_id` (INT, FK) - Links to `questions.question_id` (ON DELETE CASCADE).
* `option_text` (TEXT)
* `is_correct` (TINYINT, DEFAULT 0) - Used for choices, correct blanks, or matching left elements.
* `matching_pair` (TEXT, NULL) - Links matched right elements.
* `category` (VARCHAR(100), NULL) - Holds categorization buckets for Drag & Put.

#### 8. `quiz_scores`
Records marks earned by students.
* `score_id` (INT, PK, AUTO_INCREMENT)
* `student_id` (INT, FK) - Links to `users.user_id` (ON DELETE CASCADE).
* `quiz_id` (INT, FK) - Links to `quizzes.quiz_id` (ON DELETE CASCADE).
* `marks_earned` (INT)
* `completed_at` (TIMESTAMP)

#### 9. `gamification_stats`
Tracks active points totals and streak calculations.
* `stat_id` (INT, PK, AUTO_INCREMENT)
* `student_id` (INT, UNIQUE, FK) - Links to `users.user_id` (ON DELETE CASCADE).
* `total_points` (INT, DEFAULT 0)
* `login_streak` (INT, DEFAULT 0)
* `last_login` (DATE, NULL)

---

## 3. Directory & File Structure

```
e-learning system/
├── admin/
│   ├── dashboard.php            # Main administrative panel
│   ├── manage_curriculum.php    # Topic editor & subject manager
│   ├── manage_users.php        # User accounts database panel
│   └── system_reports.php      # SQL logs and diagnostics
├── admin_login.php              # Secure login portal for admins
├── assets/
│   ├── css/
│   │   ├── admin.css            # Stylesheets for administrator layout
│   │   ├── dashboard.css        # Stylesheets for teacher portal layout
│   │   └── student.css          # Stylesheets for student mobile wrap layout
│   └── js/
│       ├── audio.js             # Web Audio API Synthesizer (correct/error sounds)
│       ├── quiz_engine.js       # Core student quiz player logic engine (5 types)
│       └── student.js           # Student app interactions, pinpads, and video players
├── auth/
│   └── logout.php               # Destroys session
├── config/
│   └── db.php                   # PDO Database wrapper configuration
├── database.sql                 # SQL schema dump and initial seeds
├── implementation_plan.md       # High-level architecture roadmap
├── import_db.php                # Database importer utility
├── includes/
│   └── auth.php                 # Authentication helper checks
├── index.php                    # System landing page
├── login.php                    # Mobile avatar PIN keypad login page
├── student/
│   ├── class_leaderboard.php    # Gamified class leaderboard ranks
│   ├── course.php               # Course lesson timeline path
│   ├── dashboard.php            # Student dashboard, streaks, and subject cards
│   ├── lesson.php               # Active Lesson page with player and quizzes
│   ├── save_score.php           # Score saving API endpoint
│   ├── student_quiz.php         # Mobile-first interactive quiz player wrapper
│   ├── subjects.php             # Subject catalog with progress percentage bars
│   └── update_progress.php      # Progress tracking API endpoint
├── teacher/
│   ├── curriculum.php           # Curriculum, lessons list, and PDF uploads
│   ├── dashboard.php            # Teacher overview metrics
│   ├── quizzes.php              # Advanced Quiz Builder interface
│   ├── review.php               # Grading tables and manual XP awards
│   └── roster.php               # Class rosters with "Add Student" modal
└── teacher_login.php            # Secure login portal for teachers
```

---

## 4. Current Implementation Status

### Front-End Interfaces
* **Student Mobile UI:** Wrapped inside a central container (`max-width: 480px`) with a vibrant, playful styling theme. Responsive subject selection grid, custom progress indicators, custom-rounded video controls (play, pause, rewind buttons scaled to 64px), and class leaderboard.
* **Teacher Web Dashboard:** Professional desktop layout featuring KPI metrics cards, dynamic roster filters, and a visual layout for the curriculum manager. Equipped with the **Advanced Quiz Builder** interface and **Add Student** transaction form modal.
* **Admin Web Dashboard:** Large-screen administrative panel displaying system-wide usage counts, active database table logs, and diagnostic utility forms.

### Vanilla JS Logic
* **PIN Keypad Authenticator:** Collects keystrokes from numeric buttons, manages circular dot animations, checks for completed PIN lengths, and posts responses.
* **Web Audio Sound Synthesizer:** Real-time generation of gamification alerts (success chimes and error buzzers) using pure browser `AudioContext` nodes. No audio file downloads required.
* **Background APIs Syncing:** AJAX fetch requests handle background updates to `update_progress.php` when video lessons complete, and post quiz scores to `save_score.php`.
* **Advanced Quiz Builder:** Fully functional builder supporting 5 question types. Dynamically injects specialized inputs into cards depending on chosen dropdown types.
* **Interactive Quiz Player Engine (`quiz_engine.js`):** Unified pointer events dragging, touch-friendly radio/checkbox clicks, iOS-zoom safe blanks, and line matching pairing systems.

### PHP/MySQL Back-End Integration
* **Session Manager & Auth Hooks:** Standard authentication utility checks user credentials (bcrypt verification) and redirects requests depending on user roles.
* **Curriculum Manager APIs:** Implements secure server-side form upload logic, verifying PDF attachments, resizing assets, and saving files.
* **Advanced Quiz Builder API:** Runs complete database transactions: inserts records, retrieves new keys, clear old relational indexes, seeds relational tables, and compiles standard JSON schemas to synchronize student views.
* **Leaderboard Queries:** Joined `users` and `gamification_stats` class leaderboard filtering query.

---

## 5. Next Immediate Action Items

1. **System Walkthrough & Refinements:** Validate student grading feedback pathways.
2. **Database Cohort Extension:** Explore system administration features for managing classes.
