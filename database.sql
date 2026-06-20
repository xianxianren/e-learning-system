-- =========================================================================
-- Database Schema for Interactive E-Learning System
-- Tailored for Primary School Students
-- =========================================================================

-- Drop Database if exists to ensure clean slate and avoid foreign key issues from old tables
DROP DATABASE IF EXISTS elearning_db;
CREATE DATABASE elearning_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE elearning_db;

-- Clear existing tables if they exist to allow clean re-runs
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS gamification_stats;
DROP TABLE IF EXISTS quiz_scores;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS student_progress;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------------------
-- 1. Table: users
-- Holds authentication credentials and profile parameters.
-- For Students: username is a friendly handle (e.g., 'timmy') and password_hash
-- contains the bcrypt hash of a 4-digit PIN (e.g., '1234').
-- For Teachers/Admins: username is their email, password_hash contains standard password.
-- -------------------------------------------------------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE COMMENT 'Student handle or Staff email',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Bcrypt hash of student PIN or staff password',
    role ENUM('student', 'teacher', 'admin') NOT NULL COMMENT 'System access permission level',
    full_name VARCHAR(100) NOT NULL COMMENT 'Display name shown in headers/dashboards',
    class_section VARCHAR(50) NULL COMMENT 'E.g., Standard 1A, Null for Staff',
    avatar_url VARCHAR(255) NULL COMMENT 'Asset path or SVG designation for student character',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 2. Table: subjects
-- Academic subjects created by teachers or admins.
-- -------------------------------------------------------------------------
CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'E.g., Primary Mathematics',
    teacher_id INT NULL COMMENT 'FK to user teaching this class',
    icon_url VARCHAR(255) NULL COMMENT 'CSS class name or image name for student navigation',
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 3. Table: lessons
-- Lesson content pages under each subject.
-- -------------------------------------------------------------------------
CREATE TABLE lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL COMMENT 'Parent subject association',
    title VARCHAR(150) NOT NULL COMMENT 'E.g., Addition up to 20',
    video_url VARCHAR(255) NULL COMMENT 'Local path to standard HTML5 video asset',
    worksheet_url VARCHAR(255) NULL COMMENT 'Local path to downloadable PDF task sheet',
    order_num INT NOT NULL DEFAULT 1 COMMENT 'Sequencing position in curriculum list',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 4. Table: student_progress
-- Tracks student curriculum pathway completion states.
-- -------------------------------------------------------------------------
CREATE TABLE student_progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL COMMENT 'FK to users',
    lesson_id INT NOT NULL COMMENT 'FK to lessons',
    status ENUM('not_started', 'in_progress', 'completed') NOT NULL DEFAULT 'not_started',
    last_accessed DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_student_progress (student_id, lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5. Table: quizzes
-- Evaluation details linked to lessons.
-- -------------------------------------------------------------------------
CREATE TABLE quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL UNIQUE COMMENT 'One quiz per lesson model',
    total_marks INT NOT NULL DEFAULT 10 COMMENT 'Maximum potential score points',
    questions_json TEXT NOT NULL COMMENT 'Serialized questions and choices logic',
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5b. Table: questions
-- Relational representation of quiz questions.
-- -------------------------------------------------------------------------
CREATE TABLE questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('single_choice', 'multiple_choice', 'fill_in_the_blank', 'drag_and_put', 'connecting_the_link') NOT NULL,
    order_num INT DEFAULT 1,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5c. Table: question_options
-- Choices, correct designations, and category bounds for quiz questions.
-- -------------------------------------------------------------------------
CREATE TABLE question_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT DEFAULT 0,
    matching_pair TEXT NULL,
    category VARCHAR(100) NULL,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 6. Table: quiz_scores
-- Results of students taking quiz assessments.
-- -------------------------------------------------------------------------
CREATE TABLE quiz_scores (
    score_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    quiz_id INT NOT NULL,
    marks_earned INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 7. Table: gamification_stats
-- Live engagement statistics for students.
-- -------------------------------------------------------------------------
CREATE TABLE gamification_stats (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE COMMENT 'Each student has one gamification profile',
    total_points INT DEFAULT 0 COMMENT 'Accumulated XP point metrics',
    login_streak INT DEFAULT 0 COMMENT 'Consecutive days logged in',
    last_login DATE NULL COMMENT 'Tracks daily logins for streak checking',
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================================
-- DUMMY DATA SEEDING SECTION
-- =========================================================================

-- Seed Users
-- Passwords:
-- Admin: admin@school.com -> 'admin123'
-- Teacher: teacher@school.com -> 'teacher123'
-- Student 1: timmy -> '1234' (PIN)
-- Student 2: sara -> '5678' (PIN)
-- Bcrypt hashes below are pre-computed using PASSWORD_DEFAULT (standard PHP crypt format)
INSERT INTO users (user_id, username, password_hash, role, full_name, class_section, avatar_url) VALUES
(1, 'admin@school.com', '$2y$10$lfM/tuTZcOjCYDXsA4N7NOpkHsI3DCwbzYKLpzCiJqA5leijHKBJe', 'admin', 'Admin Alice', NULL, NULL),
(2, 'teacher@school.com', '$2y$10$l1NhV1t1Kjq9tqhJfrTqjO4AD3g87ezgQ0q7smou9hltgL3Anl.SS', 'teacher', 'Mr. Davis', NULL, NULL),
(3, 'timmy', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Timmy Taylor', 'Standard 1A', 'monkey'),
(4, 'sara', '$2y$10$LfCIQ/QsOJi0RuCB0Na20uaoIO36dVvk9QJRv9chpDsb6/VUoazJ2', 'student', 'Sara Smith', 'Standard 1B', 'bunny');

-- Seed Subjects
INSERT INTO subjects (subject_id, subject_name, teacher_id, icon_url) VALUES
(1, 'Primary Mathematics', 2, 'math-icon'),
(2, 'Primary Science', 2, 'science-icon');

-- Seed Lessons for Math (Subject 1)
INSERT INTO lessons (lesson_id, subject_id, title, video_url, worksheet_url, order_num) VALUES
(1, 1, 'Addition Up to 20', 'assets/videos/math_addition_20.mp4', 'assets/uploads/math_addition_20.pdf', 1),
(2, 1, 'Subtraction Under 20', 'assets/videos/math_subtraction_20.mp4', 'assets/uploads/math_subtraction_20.pdf', 2);

-- Seed Lessons for Science (Subject 2)
INSERT INTO lessons (lesson_id, subject_id, title, video_url, worksheet_url, order_num) VALUES
(3, 2, 'The Five Senses', 'assets/videos/science_five_senses.mp4', 'assets/uploads/science_five_senses.pdf', 1),
(4, 2, 'Living and Non-Living Things', 'assets/videos/science_living_things.mp4', 'assets/uploads/science_living_things.pdf', 2);

-- Seed Student Progress records
-- Timmy has completed Lesson 1, is in progress on Lesson 2, and hasn't started others.
INSERT INTO student_progress (student_id, lesson_id, status, last_accessed) VALUES
(3, 1, 'completed', NOW() - INTERVAL 1 DAY),
(3, 2, 'in_progress', NOW()),
(3, 3, 'not_started', NOW()),
-- Sara has completed Lesson 1 and Lesson 3.
(4, 1, 'completed', NOW() - INTERVAL 2 DAY),
(4, 3, 'completed', NOW() - INTERVAL 1 DAY);

-- Seed Quizzes for Mathematics Lesson 1
-- Serialized JSON questions containing: id, questionText, type ('mcq' or 'drag_drop'), choices (for mcq) or pairs (for matching), correctAnswers
INSERT INTO quizzes (quiz_id, lesson_id, total_marks, questions_json) VALUES
(1, 1, 10, '[
  {
    "id": 1,
    "type": "mcq",
    "question": "What is 8 + 5?",
    "choices": ["11", "12", "13", "14"],
    "answer": "13"
  },
  {
    "id": 2,
    "type": "mcq",
    "question": "If you have 10 apples and Mr. Davis gives you 4 more, how many apples do you have?",
    "choices": ["12", "13", "14", "15"],
    "answer": "14"
  }
]'),
(2, 3, 10, '[
  {
    "id": 1,
    "type": "mcq",
    "question": "Which organ do you use to SEE a rainbow?",
    "choices": ["Ears", "Nose", "Eyes", "Tongue"],
    "answer": "Eyes"
  },
  {
    "id": 2,
    "type": "mcq",
    "question": "Which sense helps you hear the music play?",
    "choices": ["Sight", "Hearing", "Touch", "Taste"],
    "answer": "Hearing"
  }
]');

-- Seed Quiz Scores
-- Timmy scored 10/10 on Math Quiz 1
INSERT INTO quiz_scores (student_id, quiz_id, marks_earned, completed_at) VALUES
(3, 1, 10, NOW() - INTERVAL 1 DAY),
-- Sara scored 10/10 on Math Quiz 1 and 10/10 on Science Quiz 2
(4, 1, 10, NOW() - INTERVAL 2 DAY),
(4, 2, 10, NOW() - INTERVAL 1 DAY);

-- Seed Gamification Stats
-- Timmy has 150 points, streak of 3, last logged in today
-- Sara has 250 points, streak of 5, last logged in today
INSERT INTO gamification_stats (student_id, total_points, login_streak, last_login) VALUES
(3, 150, 3, CURRENT_DATE),
(4, 250, 5, CURRENT_DATE);
