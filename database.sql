-- =========================================================================
-- Database Schema & Master Seed Data for Interactive E-Learning System
-- Tailored for Primary School Students
-- Consolidated and Finalized Master Setup
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
CREATE TABLE IF NOT EXISTS users (
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
CREATE TABLE IF NOT EXISTS subjects (
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
CREATE TABLE IF NOT EXISTS lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL COMMENT 'Parent subject association',
    title VARCHAR(150) NOT NULL COMMENT 'E.g., Addition up to 20',
    video_url VARCHAR(255) NULL COMMENT 'Local path to standard HTML5 video asset',
    worksheet_url VARCHAR(255) NULL COMMENT 'Local path to downloadable PDF task sheet',
    teacher_notes TEXT NULL COMMENT 'Optional text notes or homework details',
    order_num INT NOT NULL DEFAULT 1 COMMENT 'Sequencing position in curriculum list',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 4. Table: quizzes
-- Evaluation details linked to lessons or standalone class quizzes.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NULL COMMENT 'Parent lesson association, NULL for standalone class quizzes',
    class_section VARCHAR(50) NULL COMMENT 'Associated student group/class for standalone quizzes',
    quiz_title VARCHAR(150) DEFAULT 'Knowledge Check' COMMENT 'User-facing title of the quiz',
    total_marks INT NOT NULL DEFAULT 10 COMMENT 'Maximum potential score points',
    questions_json TEXT NOT NULL COMMENT 'Serialized questions and choices logic',
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5. Table: questions
-- Relational representation of quiz questions.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL COMMENT 'FK to quizzes table',
    question_text TEXT NOT NULL COMMENT 'Text prompt of the question',
    question_type ENUM('single_choice', 'multiple_choice', 'fill_in_the_blank', 'drag_and_put', 'connecting_the_link') NOT NULL COMMENT 'Supported question layout pattern',
    order_num INT DEFAULT 1 COMMENT 'Sorting order index',
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 6. Table: question_options
-- Choices, correct designations, category buckets, and pair items.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS question_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL COMMENT 'FK to questions table',
    option_text TEXT NOT NULL COMMENT 'Option display content or category item',
    is_correct TINYINT DEFAULT 0 COMMENT 'Boolean identifier for correct answer (1=true, 0=false)',
    matching_pair TEXT NULL COMMENT 'Associated pair match for connecting the link format',
    category VARCHAR(100) NULL COMMENT 'Target category box for drag & put classification',
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 7. Table: student_progress
-- Tracks student curriculum pathway completion states.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL COMMENT 'FK to users (students)',
    lesson_id INT NOT NULL COMMENT 'FK to lessons',
    status ENUM('not_started', 'in_progress', 'completed') NOT NULL DEFAULT 'not_started',
    last_accessed DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_student_progress (student_id, lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 8. Table: quiz_scores
-- Results of students taking quiz assessments.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_scores (
    score_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL COMMENT 'FK to users (students)',
    quiz_id INT NOT NULL COMMENT 'FK to quizzes',
    marks_earned INT NOT NULL COMMENT 'Raw marks scored by the student',
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 9. Table: gamification_stats
-- Live engagement statistics for students.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gamification_stats (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE COMMENT 'FK to users (students)',
    total_points INT DEFAULT 0 COMMENT 'Accumulated XP point metrics',
    login_streak INT DEFAULT 0 COMMENT 'Consecutive days logged in',
    last_login DATE NULL COMMENT 'Tracks daily logins for streak checking',
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- DATABASE SEEDING SECTION
-- =========================================================================

/*
PLAIN-TEXT CREDENTIALS FOR TESTING:

1. Staff / Teachers (Password: "password123" for all):
   - Admin: admin@school.com
   - Teacher 1: cikgu.aminah@school.edu
   - Teacher 2: mr.tan@school.edu

2. Students (PIN: "1234" for all):
   - Ahmad (Class: Tahun 1 Mawar) - username: ahmad
   - Mei Ling (Class: Tahun 1 Mawar) - username: mei_ling
   - Muthu (Class: Tahun 2 Melati) - username: muthu
   - Siti (Class: Tahun 2 Melati) - username: siti
   - Johnny (Class: Tahun 3 Orkid) - username: johnny
   - Fatimah (Class: Tahun 3 Orkid) - username: fatimah
   - Ravi (Class: Tahun 3 Orkid) - username: ravi
   - Chong (Class: Tahun 1 Mawar) - username: chong
   - Saraswathy (Class: Tahun 2 Melati) - username: saraswathy
*/

-- -------------------------------------------------------------------------
-- Seed Users (Bcrypt hash of 'password123' / PIN '1234')
-- -------------------------------------------------------------------------
INSERT INTO users (user_id, username, password_hash, role, full_name, class_section, avatar_url) VALUES
(1, 'admin@school.com', '$2y$10$doTjD/zWpnm2VCANhGMeVeETZyw1I1zzJlSWVDinvp5xqlHPgyzX2', 'admin', 'Admin Alice', NULL, NULL),
(2, 'cikgu.aminah@school.edu', '$2y$10$doTjD/zWpnm2VCANhGMeVeETZyw1I1zzJlSWVDinvp5xqlHPgyzX2', 'teacher', 'Cikgu Aminah', NULL, NULL),
(3, 'mr.tan@school.edu', '$2y$10$doTjD/zWpnm2VCANhGMeVeETZyw1I1zzJlSWVDinvp5xqlHPgyzX2', 'teacher', 'Mr. Tan', NULL, NULL),
(4, 'ahmad', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Ahmad Bin Yusof', 'Tahun 1 Mawar', 'monkey'),
(5, 'mei_ling', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Mei Ling Tan', 'Tahun 1 Mawar', 'bunny'),
(6, 'muthu', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Muthu Arumugam', 'Tahun 2 Melati', 'panda'),
(7, 'siti', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Siti Aminah', 'Tahun 2 Melati', 'fox'),
(8, 'johnny', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Johnny Depp', 'Tahun 3 Orkid', 'monkey'),
(9, 'fatimah', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Fatimah Mohamad', 'Tahun 3 Orkid', 'bunny'),
(10, 'ravi', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Ravi Chandran', 'Tahun 3 Orkid', 'fox'),
(11, 'chong', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Chong Wei Lee', 'Tahun 1 Mawar', 'panda'),
(12, 'saraswathy', '$2y$10$qgEFoXPP.PPToiCRW9E4De5K6VSY.HeyQAPZWgHTd4DS/Bj/MrUR6', 'student', 'Saraswathy Devi', 'Tahun 2 Melati', 'fox');

-- -------------------------------------------------------------------------
-- Seed Gamification Stats
-- -------------------------------------------------------------------------
INSERT INTO gamification_stats (student_id, total_points, login_streak, last_login) VALUES
(4, 120, 3, CURRENT_DATE),
(5, 240, 5, CURRENT_DATE),
(6, 80, 1, CURRENT_DATE),
(7, 150, 4, CURRENT_DATE),
(8, 200, 6, CURRENT_DATE),
(9, 310, 8, CURRENT_DATE),
(10, 175, 4, CURRENT_DATE),
(11, 150, 2, CURRENT_DATE),
(12, 95, 1, CURRENT_DATE);

-- -------------------------------------------------------------------------
-- Seed Subjects
-- -------------------------------------------------------------------------
INSERT INTO subjects (subject_id, subject_name, teacher_id, icon_url) VALUES
(1, 'Sains Tahun 1', 2, 'science-icon'),
(2, 'Bahasa Melayu Tahun 1', 2, 'default-icon'),
(3, 'Mathematics Standard 2', 3, 'math-icon'),
(4, 'Bahasa Inggeris Standard 2', 3, 'english-icon');

-- -------------------------------------------------------------------------
-- Seed Lessons
-- -------------------------------------------------------------------------
INSERT INTO lessons (lesson_id, subject_id, title, video_url, worksheet_url, teacher_notes, order_num) VALUES
-- Sains Tahun 1
(1, 1, 'Benda Hidup dan Benda Bukan Hidup', 'assets/videos/sample.mp4', 'assets/uploads/worksheet_living.pdf', 'Fahami perbezaan antara benda hidup yang bernafas dan benda bukan hidup.', 1),
(2, 1, 'Dunia Tumbuhan', 'assets/videos/sample.mp4', 'assets/uploads/worksheet_plants.pdf', 'Kenali bahagian-bahagian tumbuhan seperti akar, daun, batang, dan bunga.', 2),
-- Bahasa Melayu Tahun 1
(3, 2, 'Kata Nama Am dan Khas', 'assets/videos/sample.mp4', 'assets/uploads/worksheet_nouns.pdf', 'Belajar membezakan kata nama am (buku, rumah) dengan kata nama khas (Siti, Proton Saga).', 1),
(4, 2, 'Membina Ayat Mudah', 'assets/videos/sample.mp4', 'assets/uploads/worksheet_sentences.pdf', 'Latihan membina ayat mudah menggunakan struktur subjek + kata kerja + objek.', 2),
-- Mathematics Standard 2
(5, 3, 'Addition Within 1000', 'assets/videos/sample.mp4', 'assets/uploads/worksheet_add.pdf', 'Master adding three-digit numbers with carrying.', 1),
(6, 3, 'Subtraction Within 1000', 'assets/videos/sample.mp4', 'assets/uploads/worksheet_sub.pdf', 'Master subtracting three-digit numbers with borrowing.', 2),
-- Bahasa Inggeris Standard 2
(7, 4, 'Simple Present Tense', 'assets/videos/sample.mp4', 'assets/uploads/worksheet_tense.pdf', 'Learn how to use simple present tense for daily routines.', 1),
(8, 4, 'Nouns and Pronouns', 'assets/videos/sample.mp4', 'assets/uploads/worksheet_pronouns.pdf', 'Understand how pronouns replace nouns in standard sentences.', 2);

-- -------------------------------------------------------------------------
-- Seed Quizzes
-- -------------------------------------------------------------------------
INSERT INTO quizzes (quiz_id, lesson_id, quiz_title, class_section, total_marks, questions_json) VALUES
(1, 1, 'Living Things Pre-Test', 'Tahun 1 Mawar', 10, '[]'),
(2, 1, 'Living Things Review', 'Tahun 1 Mawar', 10, '[]'),
(3, 2, 'Plants Pre-Test', 'Tahun 1 Mawar', 10, '[]'),
(4, 2, 'Plants Review', 'Tahun 1 Mawar', 10, '[]'),
(5, 3, 'Grammar Pre-Test', 'Tahun 1 Mawar', 10, '[]'),
(6, 3, 'Grammar Review', 'Tahun 1 Mawar', 10, '[]'),
(7, 4, 'Sentences Pre-Test', 'Tahun 1 Mawar', 10, '[]'),
(8, 4, 'Sentences Review', 'Tahun 1 Mawar', 10, '[]'),
(9, 5, 'Math Addition Pre-Test', 'Tahun 2 Melati', 10, '[]'),
(10, 5, 'Math Addition Review', 'Tahun 2 Melati', 10, '[]'),
(11, 6, 'Math Subtraction Pre-Test', 'Tahun 2 Melati', 10, '[]'),
(12, 6, 'Math Subtraction Review', 'Tahun 2 Melati', 10, '[]'),
(13, 7, 'English Tense Pre-Test', 'Tahun 3 Orkid', 10, '[]'),
(14, 7, 'English Tense Review', 'Tahun 3 Orkid', 10, '[]'),
(15, 8, 'English Nouns Pre-Test', 'Tahun 3 Orkid', 10, '[]'),
(16, 8, 'English Nouns Review', 'Tahun 3 Orkid', 10, '[]');

-- -------------------------------------------------------------------------
-- Seed Questions & Options
-- -------------------------------------------------------------------------

-- QUIZ 1 (Living Things Pre-Test)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(1, 1, 'Adakah kucing benda hidup?', 'single_choice', 1),
(2, 1, 'Pilih benda hidup di bawah.', 'multiple_choice', 2),
(3, 1, 'Benda hidup memerlukan _____ untuk bernafas.', 'fill_in_the_blank', 3),
(4, 1, 'Kategorikan benda berikut.', 'drag_and_put', 4),
(5, 1, 'Padankan benda dengan sifatnya.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(1, 'Ya', 1, NULL, NULL),
(1, 'Tidak', 0, NULL, NULL),
(1, 'Mungkin', 0, NULL, NULL),
(1, 'Tidak Pasti', 0, NULL, NULL),
(2, 'Kucing', 1, NULL, NULL),
(2, 'Batu', 0, NULL, NULL),
(2, 'Pokok Bunga', 1, NULL, NULL),
(2, 'Kereta', 0, NULL, NULL),
(3, 'udara', 1, NULL, NULL),
(4, 'Burung', 0, NULL, 'Benda Hidup'),
(4, 'Meja', 0, NULL, 'Benda Bukan Hidup'),
(4, 'Buku', 0, NULL, 'Benda Bukan Hidup'),
(4, 'Gajah', 0, NULL, 'Benda Hidup'),
(5, 'Manusia', 0, 'Bernafas', NULL),
(5, 'Kereta', 0, 'Bergerak guna petrol', NULL),
(5, 'Batu', 0, 'Tidak membesar', NULL);

-- QUIZ 2 (Living Things Review)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(6, 2, 'Adakah batu boleh membesar?', 'single_choice', 1),
(7, 2, 'Pilih ciri-ciri benda hidup.', 'multiple_choice', 2),
(8, 2, 'Manusia makan untuk mendapatkan _____.', 'fill_in_the_blank', 3),
(9, 2, 'Kategorikan benda mengikut jenis pemakanan.', 'drag_and_put', 4),
(10, 2, 'Padankan haiwan dengan jenisnya.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(6, 'Tidak', 1, NULL, NULL),
(6, 'Ya', 0, NULL, NULL),
(6, 'Hanya jika disiram air', 0, NULL, NULL),
(6, 'Mungkin', 0, NULL, NULL),
(7, 'Memerlukan makanan', 1, NULL, NULL),
(7, 'Bernafas', 1, NULL, NULL),
(7, 'Sentiasa keras', 0, NULL, NULL),
(7, 'Boleh membiak', 1, NULL, NULL),
(8, 'tenaga', 1, NULL, NULL),
(9, 'Lembu', 0, NULL, 'Makan Tumbuhan'),
(9, 'Harimau', 0, NULL, 'Makan Daging'),
(9, 'Kambing', 0, NULL, 'Makan Tumbuhan'),
(9, 'Singa', 0, NULL, 'Makan Daging'),
(10, 'Helang', 0, 'Burung', NULL),
(10, 'Ikan Emas', 0, 'Haiwan Air', NULL),
(10, 'Kucing', 0, 'Mamalia', NULL);

-- QUIZ 3 (Plants Pre-Test)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(11, 3, 'Bahagian manakah yang menyerap air dari tanah?', 'single_choice', 1),
(12, 3, 'Apakah yang diperlukan oleh tumbuhan untuk hidup?', 'multiple_choice', 2),
(13, 3, 'Tumbuhan membuat makanannya sendiri melalui proses _____.', 'fill_in_the_blank', 3),
(14, 3, 'Kategorikan tumbuhan berikut.', 'drag_and_put', 4),
(15, 3, 'Padankan bahagian tumbuhan dengan tugasnya.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(11, 'Akar', 1, NULL, NULL),
(11, 'Daun', 0, NULL, NULL),
(11, 'Bunga', 0, NULL, NULL),
(11, 'Batang', 0, NULL, NULL),
(12, 'Air', 1, NULL, NULL),
(12, 'Cahaya matahari', 1, NULL, NULL),
(12, 'Makanan ringan', 0, NULL, NULL),
(12, 'Tanah/Nutrisi', 1, NULL, NULL),
(13, 'fotosintesis', 1, NULL, NULL),
(14, 'Bunga Raya', 0, NULL, 'Berbunga'),
(14, 'Pokok Paku Pakis', 0, NULL, 'Tidak Berbunga'),
(14, 'Bunga Mawar', 0, NULL, 'Berbunga'),
(14, 'Cendawan', 0, NULL, 'Tidak Berbunga'),
(15, 'Akar', 0, 'Sokongan dan serap air', NULL),
(15, 'Daun', 0, 'Tempat buat makanan', NULL),
(15, 'Bunga', 0, 'Bertukar jadi buah', NULL);

-- QUIZ 4 (Plants Review)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(16, 4, 'Apakah warna kebanyakan daun tumbuhan?', 'single_choice', 1),
(17, 4, 'Antara berikut, manakah pokok berkayu keras?', 'multiple_choice', 2),
(18, 4, 'Pokok yang menjalar ke atas memerlukan _____ untuk menyokong batangnya.', 'fill_in_the_blank', 3),
(19, 4, 'Kategorikan tumbuhan mengikut habitat.', 'drag_and_put', 4),
(20, 4, 'Padankan pokok dengan buahnya.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(16, 'Hijau', 1, NULL, NULL),
(16, 'Merah', 0, NULL, NULL),
(16, 'Biru', 0, NULL, NULL),
(16, 'Kuning', 0, NULL, NULL),
(17, 'Pokok Durian', 1, NULL, NULL),
(17, 'Pokok Pisang', 0, NULL, NULL),
(17, 'Pokok Getah', 1, NULL, NULL),
(17, 'Lallang', 0, NULL, NULL),
(18, 'sokongan', 1, NULL, NULL),
(19, 'Teratai', 0, NULL, 'Tumbuhan Air'),
(19, 'Pokok Mangga', 0, NULL, 'Tumbuhan Darat'),
(19, 'Keladi Bunting', 0, NULL, 'Tumbuhan Air'),
(19, 'Pokok Getah', 0, NULL, 'Tumbuhan Darat'),
(20, 'Pokok Kelapa', 0, 'Kelapa', NULL),
(20, 'Pokok Epal', 0, 'Epal', NULL),
(20, 'Pokok Betik', 0, 'Betik', NULL);

-- QUIZ 5 (Grammar Pre-Test)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(21, 5, 'Antara berikut, yang manakah kata nama am?', 'single_choice', 1),
(22, 5, 'Pilih kata nama khas di bawah.', 'multiple_choice', 2),
(23, 5, 'Kata nama khas ditulis menggunakan huruf _____ di awal perkataan.', 'fill_in_the_blank', 3),
(24, 5, 'Kategorikan kata nama berikut.', 'drag_and_put', 4),
(25, 5, 'Padankan kata nama am dengan padanan khasnya.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(21, 'sekolah', 1, NULL, NULL),
(21, 'Kuala Lumpur', 0, NULL, NULL),
(21, 'Proton Saga', 0, NULL, NULL),
(21, 'Sang Kancil', 0, NULL, NULL),
(22, 'Cikgu Aminah', 1, NULL, NULL),
(22, 'kereta', 0, NULL, NULL),
(22, 'Malaysia', 1, NULL, NULL),
(22, 'kucing', 0, NULL, NULL),
(23, 'besar', 1, NULL, NULL),
(24, 'Budak', 0, NULL, 'Kata Nama Am'),
(24, 'Ali', 0, NULL, 'Kata Nama Khas'),
(24, 'Negara', 0, NULL, 'Kata Nama Am'),
(24, 'Singapura', 0, NULL, 'Kata Nama Khas'),
(25, 'kereta', 0, 'Proton Persona', NULL),
(25, 'kucing', 0, 'Si Comel', NULL),
(25, 'negeri', 0, 'Selangor', NULL);

-- QUIZ 6 (Grammar Review)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(26, 6, 'Yang manakah kata ganti nama diri pertama?', 'single_choice', 1),
(27, 6, 'Pilih kata ganti nama diri ketiga.', 'multiple_choice', 2),
(28, 6, '_____ digunakan untuk menggantikan nama orang yang kita ajak berbual.', 'fill_in_the_blank', 3),
(29, 6, 'Kategorikan kata ganti nama berikut.', 'drag_and_put', 4),
(30, 6, 'Padankan kata ganti nama dengan penggunaannya.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(26, 'Saya', 1, NULL, NULL),
(26, 'Kamu', 0, NULL, NULL),
(26, 'Dia', 0, NULL, NULL),
(26, 'Mereka', 0, NULL, NULL),
(27, 'Dia', 1, NULL, NULL),
(27, 'Saya', 0, NULL, NULL),
(27, 'Beliau', 1, NULL, NULL),
(27, 'Mereka', 1, NULL, NULL),
(28, 'kamu', 1, NULL, NULL),
(29, 'Aku', 0, NULL, 'Diri Pertama'),
(29, 'Kamu', 0, NULL, 'Diri Kedua'),
(29, 'Kami', 0, NULL, 'Diri Pertama'),
(29, 'Kalian', 0, NULL, 'Diri Kedua'),
(30, 'Saya', 0, 'Diri sendiri', NULL),
(30, 'Dia', 0, 'Orang lain', NULL),
(30, 'Beliau', 0, 'Orang dihormati', NULL);

-- QUIZ 7 (Sentences Pre-Test)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(31, 7, 'Pilih ayat tunggal yang betul.', 'single_choice', 1),
(32, 7, 'Pilih kata hubung yang betul untuk membina ayat majmuk.', 'multiple_choice', 2),
(33, 7, 'Ayat tunggal mengandungi satu subjek dan satu _____.', 'fill_in_the_blank', 3),
(34, 7, 'Kategorikan perkataan berikut mengikut fungsinya dalam ayat.', 'drag_and_put', 4),
(35, 7, 'Padankan subjek dengan predikat yang sesuai.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(31, 'Ali makan nasi.', 1, NULL, NULL),
(31, 'Ali makan nasi dan minum air.', 0, NULL, NULL),
(31, 'Sebab Ali lapar, dia makan nasi.', 0, NULL, NULL),
(31, 'Ali yang sedang makan nasi.', 0, NULL, NULL),
(32, 'dan', 1, NULL, NULL),
(32, 'atau', 1, NULL, NULL),
(32, 'tetapi', 1, NULL, NULL),
(32, 'makan', 0, NULL, NULL),
(33, 'predikat', 1, NULL, NULL),
(34, 'Muthu', 0, NULL, 'Subjek'),
(34, 'membaca buku', 0, NULL, 'Predikat'),
(34, 'Siti', 0, NULL, 'Subjek'),
(34, 'bermain bola', 0, NULL, 'Predikat'),
(35, 'Bapa saya', 0, 'memandu kereta baharu.', NULL),
(35, 'Kucing itu', 0, 'sedang tidur nyenyak.', NULL),
(35, 'Adik bongsu', 0, 'menangis kelaparan.', NULL);

-- QUIZ 8 (Sentences Review)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(36, 8, 'Apakah tanda baca di hujung ayat tanya?', 'single_choice', 1),
(37, 8, 'Pilih kata tanya yang betul.', 'multiple_choice', 2),
(38, 8, 'Ayat penyata diakhiri dengan tanda _____.', 'fill_in_the_blank', 3),
(39, 8, 'Kategorikan ayat-ayat berikut.', 'drag_and_put', 4),
(40, 8, 'Padankan kata tanya dengan tujuannya.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(36, '?', 1, NULL, NULL),
(36, '.', 0, NULL, NULL),
(36, '!', 0, NULL, NULL),
(36, ',', 0, NULL, NULL),
(37, 'Siapa', 1, NULL, NULL),
(37, 'Mengapa', 1, NULL, NULL),
(37, 'Bila', 1, NULL, NULL),
(37, 'Sebab', 0, NULL, NULL),
(38, 'noktah', 1, NULL, NULL),
(39, 'Ali suka membaca.', 0, NULL, 'Ayat Penyata'),
(39, 'Siapakah nama kamu?', 0, NULL, 'Ayat Tanya'),
(39, 'Adik sedang tidur.', 0, NULL, 'Ayat Penyata'),
(39, 'Di manakah kamu tinggal?', 0, NULL, 'Ayat Tanya'),
(40, 'Siapa', 0, 'Tanya orang', NULL),
(40, 'Mana', 0, 'Tanya tempat', NULL),
(40, 'Bila', 0, 'Tanya masa', NULL);

-- QUIZ 9 (Math Addition Pre-Test)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(41, 9, 'What is 350 + 250?', 'single_choice', 1),
(42, 9, 'Which sum totals 500?', 'multiple_choice', 2),
(43, 9, 'Fill in: 400 + _____ = 1000.', 'fill_in_the_blank', 3),
(44, 9, 'Drag sums to their correct value category.', 'drag_and_put', 4),
(45, 9, 'Connect the equation to its correct total.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(41, '600', 1, NULL, NULL),
(41, '500', 0, NULL, NULL),
(41, '700', 0, NULL, NULL),
(41, '550', 0, NULL, NULL),
(42, '250 + 250', 1, NULL, NULL),
(42, '300 + 200', 1, NULL, NULL),
(42, '450 + 50', 1, NULL, NULL),
(42, '100 + 300', 0, NULL, NULL),
(43, '600', 1, NULL, NULL),
(44, '150 + 150', 0, NULL, 'Equals 300'),
(44, '200 + 100', 0, NULL, 'Equals 300'),
(44, '250 + 150', 0, NULL, 'Equals 400'),
(44, '300 + 100', 0, NULL, 'Equals 400'),
(45, '120 + 80', 0, '200', NULL),
(45, '450 + 50', 0, '500', NULL),
(45, '900 + 100', 0, '1000', NULL);

-- QUIZ 10 (Math Addition Review)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(46, 10, 'What is 850 + 150?', 'single_choice', 1),
(47, 10, 'Which addends equal 1000?', 'multiple_choice', 2),
(48, 10, 'The addition of 0 to any number yields the _____ number.', 'fill_in_the_blank', 3),
(49, 10, 'Drag equations to their sum range.', 'drag_and_put', 4),
(50, 10, 'Match the word problem to its sum.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(46, '1000', 1, NULL, NULL),
(46, '900', 0, NULL, NULL),
(46, '950', 0, NULL, NULL),
(46, '1100', 0, NULL, NULL),
(47, '500 + 500', 1, NULL, NULL),
(47, '750 + 250', 1, NULL, NULL),
(47, '800 + 200', 1, NULL, NULL),
(47, '650 + 450', 0, NULL, NULL),
(48, 'same', 1, NULL, NULL),
(49, '50 + 30', 0, NULL, 'Under 100'),
(49, '120 + 80', 0, NULL, 'Over 100'),
(49, '25 + 25', 0, NULL, 'Under 100'),
(49, '300 + 500', 0, NULL, 'Over 100'),
(50, '10 apples + 5 apples', 0, '15 apples', NULL),
(50, '20 apples + 20 apples', 0, '40 apples', NULL),
(50, '100 apples + 1 apple', 0, '101 apples', NULL);

-- QUIZ 11 (Math Subtraction Pre-Test)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(51, 11, 'What is 750 - 250?', 'single_choice', 1),
(52, 11, 'Which calculations equal 200?', 'multiple_choice', 2),
(53, 11, 'Solve: 500 - _____ = 250.', 'fill_in_the_blank', 3),
(54, 11, 'Drag values to their correct remaining category.', 'drag_and_put', 4),
(55, 11, 'Match the subtraction with the correct result.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(51, '500', 1, NULL, NULL),
(51, '400', 0, NULL, NULL),
(51, '600', 0, NULL, NULL),
(51, '450', 0, NULL, NULL),
(52, '300 - 100', 1, NULL, NULL),
(52, '500 - 300', 1, NULL, NULL),
(52, '1000 - 800', 1, NULL, NULL),
(52, '200 - 50', 0, NULL, NULL),
(53, '250', 1, NULL, NULL),
(54, '90 - 40', 0, NULL, 'Remaining is 50'),
(54, '150 - 100', 0, NULL, 'Remaining is 50'),
(54, '200 - 120', 0, NULL, 'Remaining is 80'),
(54, '100 - 20', 0, NULL, 'Remaining is 80'),
(55, '350 - 50', 0, '300', NULL),
(55, '880 - 80', 0, '800', NULL),
(55, '50 - 25', 0, '25', NULL);

-- QUIZ 12 (Math Subtraction Review)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(56, 12, 'What is 1000 - 450?', 'single_choice', 1),
(57, 12, 'Which options yield a positive result?', 'multiple_choice', 2),
(58, 12, 'Subtracting a number from itself equals _____.', 'fill_in_the_blank', 3),
(59, 12, 'Drag equations to their result description.', 'drag_and_put', 4),
(60, 12, 'Match the expressions with equivalent values.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(56, '550', 1, NULL, NULL),
(56, '650', 0, NULL, NULL),
(56, '450', 0, NULL, NULL),
(56, '500', 0, NULL, NULL),
(57, '10 - 5', 1, NULL, NULL),
(57, '5 - 10', 0, NULL, NULL),
(57, '100 - 99', 1, NULL, NULL),
(57, '0 - 5', 0, NULL, NULL),
(58, 'zero', 1, NULL, NULL),
(59, '10 - 2', 0, NULL, 'Even Number'),
(59, '10 - 3', 0, NULL, 'Odd Number'),
(59, '15 - 5', 0, NULL, 'Even Number'),
(59, '9 - 8', 0, NULL, 'Odd Number'),
(60, '100 - 50', 0, '25 + 25', NULL),
(60, '10 - 2', 0, '4 + 4', NULL),
(60, '200 - 100', 0, '50 + 50', NULL);

-- QUIZ 13 (English Tense Pre-Test)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(61, 13, 'Complete: She _____ to school every day.', 'single_choice', 1),
(62, 13, 'Which sentences are in the Simple Present Tense?', 'multiple_choice', 2),
(63, 13, 'The plural form of "go" in the simple present is _____.', 'fill_in_the_blank', 3),
(64, 13, 'Drag pronouns to their matching verb form.', 'drag_and_put', 4),
(65, 13, 'Match pronouns with the correct form of "to play".', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(61, 'walks', 1, NULL, NULL),
(61, 'walk', 0, NULL, NULL),
(61, 'walking', 0, NULL, NULL),
(61, 'walked', 0, NULL, NULL),
(62, 'He plays football.', 1, NULL, NULL),
(62, 'They eat rice.', 1, NULL, NULL),
(62, 'She went home.', 0, NULL, NULL),
(62, 'I am running.', 0, NULL, NULL),
(63, 'go', 1, NULL, NULL),
(64, 'He', 0, NULL, 'Uses Verb + s'),
(64, 'They', 0, NULL, 'Uses Base Verb'),
(64, 'She', 0, NULL, 'Uses Verb + s'),
(64, 'We', 0, NULL, 'Uses Base Verb'),
(65, 'He', 0, 'plays', NULL),
(65, 'They', 0, 'play', NULL),
(65, 'I', 0, 'play', NULL);

-- QUIZ 14 (English Tense Review)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(66, 14, 'Complete: He _____ like ice cream.', 'single_choice', 1),
(67, 14, 'Identify the negative present tense verbs.', 'multiple_choice', 2),
(68, 14, 'Simple present tense is used for facts and _____ routines.', 'fill_in_the_blank', 3),
(69, 14, 'Categorize these verbs.', 'drag_and_put', 4),
(70, 14, 'Match base verb with third-person singular.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(66, 'does not', 1, NULL, NULL),
(66, 'do not', 0, NULL, NULL),
(66, 'is not', 0, NULL, NULL),
(66, 'are not', 0, NULL, NULL),
(67, 'doesn\'t play', 1, NULL, NULL),
(67, 'don\'t eat', 1, NULL, NULL),
(67, 'didn\'t go', 0, NULL, NULL),
(67, 'won\'t watch', 0, NULL, NULL),
(68, 'daily', 1, NULL, NULL),
(69, 'eat', 0, NULL, 'Base Verb'),
(69, 'eats', 0, NULL, 'Verb + s/es'),
(69, 'go', 0, NULL, 'Base Verb'),
(69, 'goes', 0, NULL, 'Verb + s/es'),
(70, 'run', 0, 'runs', NULL),
(70, 'watch', 0, 'watches', NULL),
(70, 'fly', 0, 'flies', NULL);

-- QUIZ 15 (English Nouns Pre-Test)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(71, 15, 'Which word is a Proper Noun?', 'single_choice', 1),
(72, 15, 'Select all Common Nouns.', 'multiple_choice', 2),
(73, 15, 'A noun represents a person, place, or _____.', 'fill_in_the_blank', 3),
(74, 15, 'Categorize the words.', 'drag_and_put', 4),
(75, 15, 'Match the common noun with a proper noun.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(71, 'London', 1, NULL, NULL),
(71, 'city', 0, NULL, NULL),
(71, 'boy', 0, NULL, NULL),
(71, 'pencil', 0, NULL, NULL),
(72, 'dog', 1, NULL, NULL),
(72, 'teacher', 1, NULL, NULL),
(72, 'Snoopy', 0, NULL, NULL),
(72, 'desk', 1, NULL, NULL),
(73, 'thing', 1, NULL, NULL),
(74, 'Teacher', 0, NULL, 'Common Noun'),
(74, 'Mr. Tan', 0, NULL, 'Proper Noun'),
(74, 'Country', 0, NULL, 'Common Noun'),
(74, 'Malaysia', 0, NULL, 'Proper Noun'),
(75, 'boy', 0, 'Ahmad', NULL),
(75, 'cat', 0, 'Si Comel', NULL),
(75, 'car', 0, 'Proton Saga', NULL);

-- QUIZ 16 (English Nouns Review)
INSERT INTO questions (question_id, quiz_id, question_text, question_type, order_num) VALUES
(76, 16, 'What is the plural form of "box"?', 'single_choice', 1),
(77, 16, 'Choose the irregular plural nouns.', 'multiple_choice', 2),
(78, 16, 'The plural form of "child" is _____.', 'fill_in_the_blank', 3),
(79, 16, 'Categorize these nouns.', 'drag_and_put', 4),
(80, 16, 'Match singular nouns with their plural form.', 'connecting_the_link', 5);

INSERT INTO question_options (question_id, option_text, is_correct, matching_pair, category) VALUES
(76, 'boxes', 1, NULL, NULL),
(76, 'boxs', 0, NULL, NULL),
(76, 'boxies', 0, NULL, NULL),
(76, 'boxe', 0, NULL, NULL),
(77, 'children', 1, NULL, NULL),
(77, 'men', 1, NULL, NULL),
(77, 'cats', 0, NULL, NULL),
(77, 'mice', 1, NULL, NULL),
(78, 'children', 1, NULL, NULL),
(79, 'dog', 0, NULL, 'Singular'),
(79, 'dogs', 0, NULL, 'Plural'),
(79, 'mouse', 0, NULL, 'Singular'),
(79, 'mice', 0, NULL, 'Plural'),
(80, 'tooth', 0, 'teeth', NULL),
(80, 'foot', 0, 'feet', NULL),
(80, 'person', 0, 'people', NULL);

-- -------------------------------------------------------------------------
-- Seed Student Progress records
-- -------------------------------------------------------------------------
INSERT INTO student_progress (student_id, lesson_id, status, last_accessed) VALUES
(4, 1, 'completed', NOW() - INTERVAL 1 DAY),
(4, 2, 'in_progress', NOW()),
(4, 3, 'not_started', NOW()),
(5, 1, 'completed', NOW() - INTERVAL 2 DAY),
(5, 3, 'completed', NOW() - INTERVAL 1 DAY);

-- -------------------------------------------------------------------------
-- Seed Quiz Scores
-- -------------------------------------------------------------------------
INSERT INTO quiz_scores (student_id, quiz_id, marks_earned, completed_at) VALUES
(4, 1, 10, NOW() - INTERVAL 1 DAY),
(5, 1, 8, NOW() - INTERVAL 2 DAY),
(5, 2, 10, NOW() - INTERVAL 1 DAY);
