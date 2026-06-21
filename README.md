# 🌟 Interactive E-Learning System for Primary School Students

## 📖 Project Overview
This project is a fully custom, gamified E-Learning platform designed specifically for primary school students (ages 7-12). Built with a strict **"Zero External Libraries"** constraint, the system relies entirely on native HTML5, CSS3, Vanilla JavaScript, and raw PHP/MySQL to deliver a rich, interactive educational experience.

The platform bridges the gap between engaging mobile-first student learning and professional desktop-first curriculum management for educators.

---

## 🚀 Key Features

### 🎒 Student Portal (Mobile-First)
* **Gamified Authentication:** A 4-digit tactile PIN pad with visual pagination for large class rosters and custom animal avatars.
* **Interactive Quiz Engine:** Supports 5 complex question types built entirely in Vanilla JS:
  * Single Choice & Multiple Choice
  * Fill in the Blank
  * Drag & Put (1-to-Many Categorization Drop Zones)
  * Connecting the Link (Two-Tap Match Engine)
* **Web Audio API Integration:** Native browser-generated sound synthesis for correct/incorrect answers—no external MP3/WAV files required.
* **Gamification Tracking:** Real-time XP tracking, daily challenge routing, and a dynamic Class Leaderboard.

### 🍎 Teacher Dashboard (Desktop-First)
* **Curriculum Manager:** Upload `.mp4` video lessons directly to the local server, attach Teacher's Notes, and manage custom subjects.
* **Advanced Quiz Builder:** Construct complex interactive quizzes and assign them strictly to a single lesson or broadcast them as a "Class Quiz".
* **Contextual Grading:** Review student quiz scores and manually award bonus XP directly from the Class Roster workflow.

### ⚙️ System Admin Console
* **Master User Management:** Centralized control over teacher and student account creation.
* **Dynamic Storage Metrics:** Real-time calculation of server storage utilized by uploaded video files and PDF worksheets.

---

## 🛠️ Technology Stack
* **Front-End:** HTML5, CSS3 (Custom CSS Variables & Flexbox/Grid), Vanilla JavaScript (ES6+).
* **Back-End:** PHP 8+ (PDO API wrapper for secure transactions).
* **Database:** MySQL (Relational schema with strict `ON DELETE CASCADE` integrity constraints).

---

## 💻 Installation & Setup Guide

1. Install the project files from GitHub.
2. Unzip it and move the folder to the `htdocs` directory under XAMPP, and rename the folder (e.g., to `e-learning system`).
3. Open your XAMPP Control Panel and start **MySQL** and **Apache**.
4. Import the unified database script `database.sql` into your MySQL server:
   - Either run the automated web utility: `http://localhost/e-learning system/import_db.php`
   - Or run `mysql -u root < database.sql` (or use the phpMyAdmin Import tab).
5. Load the application in your browser at:
   `http://localhost/e-learning system/`
