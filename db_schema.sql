-- -----------------------------------------------------
-- Database: CoinFlow Academy - FINAL SCHEMA
-- This script MUST be run on a clean database to create all 14 tables.
-- -----------------------------------------------------
CREATE DATABASE IF NOT EXISTS coinflow_academy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE coinflow_academy_db;

-- =======================================================
-- 1. USERS: Core user credentials
-- =======================================================
CREATE TABLE IF NOT EXISTS Users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE COMMENT 'Used for identity management (e.g., recovery)',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Store secure hash, not plain password',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_user_username ON Users (username);

-- =======================================================
-- 2. COURSE_TIERS: Defines the tiered structure logic
-- Must be created before Courses
-- =======================================================
CREATE TABLE IF NOT EXISTS Course_Tiers (
    tier_id INT UNSIGNED PRIMARY KEY COMMENT 'The tier number (1, 2, 3, 4)',
    tier_name VARCHAR(50) NOT NULL UNIQUE,
    prerequisite_tier_id INT UNSIGNED NULL COMMENT 'The tier that must be fully completed to unlock this tier',
    FOREIGN KEY (prerequisite_tier_id) REFERENCES Course_Tiers(tier_id)
);

-- =======================================================
-- 3. COURSES: Static details for all 12 Vaults
-- =======================================================
CREATE TABLE IF NOT EXISTS Courses (
    course_id INT UNSIGNED PRIMARY KEY,
    tier_id INT UNSIGNED NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    core_topic TEXT,
    total_lessons SMALLINT UNSIGNED DEFAULT 10 COMMENT 'The number of lessons/quizzes in the vault (used for percentage calculation)',
    difficulty_level ENUM('Adept', 'Maven', 'Mogul', 'Grandmaster') NOT NULL,
    skill_point_cost INT UNSIGNED DEFAULT 0 COMMENT 'Cost to PURCHASE this course after tier prerequisite is met',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tier_id) REFERENCES Course_Tiers(tier_id) ON DELETE RESTRICT
);

-- =======================================================
-- 4. COURSE_LESSONS: Content and structure for individual lessons
-- =======================================================
CREATE TABLE IF NOT EXISTS Course_Lessons (
    lesson_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    lesson_index SMALLINT UNSIGNED NOT NULL COMMENT 'The sequential order of the lesson within the course (1, 2, 3...)',
    lesson_title VARCHAR(150) NOT NULL,
    lesson_content TEXT NOT NULL COMMENT 'The full HTML/Markdown content of the lesson',
    estimated_time_minutes SMALLINT UNSIGNED DEFAULT 5,
    -- REWARD COLUMNS 
    star_points_reward INT UNSIGNED DEFAULT 100 COMMENT 'Star Points granted for completing this lesson',
    skill_points_reward INT UNSIGNED DEFAULT 0 COMMENT 'Skill Points granted for completing this lesson (usually 0 unless final quiz)',
    leaderboard_points_reward INT UNSIGNED DEFAULT 0 COMMENT 'Leaderboard Points granted for completing this lesson (usually 0 unless final quiz)',
    UNIQUE KEY uc_course_index (course_id, lesson_index),
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_lesson_course ON Course_Lessons (course_id);

-- =======================================================
-- 5. USER_STATS: Stores user-specific scoring and currencies
-- =======================================================
CREATE TABLE IF NOT EXISTS User_Stats (
    user_id INT UNSIGNED PRIMARY KEY,
    star_points INT UNSIGNED DEFAULT 0 COMMENT 'Currency for the Marketplace',
    skill_points INT UNSIGNED DEFAULT 0 COMMENT 'Currency for unlocking Tiers',
    leaderboard_points INT UNSIGNED DEFAULT 0 COMMENT 'Total points for global leaderboard ranking',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- =======================================================
-- 6. USER_COURSE_PROGRESS: Tracks individual user progress on each course
-- =======================================================
CREATE TABLE IF NOT EXISTS User_Course_Progress (
    user_progress_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    status ENUM('Locked', 'Unlocked', 'InProgress', 'Completed') NOT NULL,
    last_lesson_completed INT UNSIGNED NULL COMMENT 'The lesson_id of the last lesson completed (used for navigation/resumption)',
    progress_percentage TINYINT UNSIGNED DEFAULT 0,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY uc_user_course (user_id, course_id),
    CONSTRAINT chk_progress_percentage CHECK (progress_percentage <= 100),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS idx_progress_status ON User_Course_Progress (user_id, status);

-- =======================================================
-- 7. USER_LESSON_COMPLETION: Tracks the completion of specific lessons
-- =======================================================
CREATE TABLE IF NOT EXISTS User_Lesson_Completion (
    user_lesson_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    lesson_id INT UNSIGNED NOT NULL,
    date_completed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uc_user_lesson (user_id, lesson_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES Course_Lessons(lesson_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_lesson_completed ON User_Lesson_Completion (lesson_id);

-- =======================================================
-- 8. PREMIUM_BADGES: Earnable, Achievement-based Badges
-- =======================================================
CREATE TABLE IF NOT EXISTS Premium_Badges (
    badge_id INT UNSIGNED PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_url VARCHAR(255)
);

-- =======================================================
-- 9. USER_PREMIUM_BADGES: Tracks which achievement badges a user has earned
-- =======================================================
CREATE TABLE IF NOT EXISTS User_Premium_Badges (
    user_badge_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    badge_id INT UNSIGNED NOT NULL,
    date_earned TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_equipped BOOLEAN DEFAULT FALSE COMMENT 'Tracks which achievement badge is displayed on profile',
    UNIQUE KEY uc_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES Premium_Badges(badge_id) ON DELETE RESTRICT
);

-- =======================================================
-- 10. STANDARD_COSMETICS: Purchasable Items (Avatar, Frame, Standard Badge)
-- =======================================================
CREATE TABLE IF NOT EXISTS Standard_Cosmetics (
    cosmetic_id INT UNSIGNED PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('Avatar', 'Frame', 'Badge') NOT NULL, 
    cost_star_points INT UNSIGNED NOT NULL,
    image_url VARCHAR(255)
);

-- =======================================================
-- 11. USER_COSMETICS: Tracks purchasable items a user owns
-- =======================================================
CREATE TABLE IF NOT EXISTS User_Cosmetics (
    user_cosmetic_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    cosmetic_id INT UNSIGNED NOT NULL,
    date_acquired TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_equipped BOOLEAN DEFAULT FALSE,
    UNIQUE KEY uc_user_cosmetic (user_id, cosmetic_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (cosmetic_id) REFERENCES Standard_Cosmetics(cosmetic_id) ON DELETE RESTRICT
);

-- =======================================================
-- 12. FEATURED_BUNDLES
-- -----------------------------------------------------
-- Logic for time-limited, themed bundles
-- =======================================================
CREATE TABLE IF NOT EXISTS Featured_Bundles (
    set_id INT UNSIGNED PRIMARY KEY,
    set_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    bundle_cost_sp INT UNSIGNED NOT NULL COMMENT 'The cost for the entire bundle in Star Points',
    start_date DATETIME NOT NULL COMMENT 'When the bundle deal starts',
    end_date DATETIME NOT NULL COMMENT 'When the bundle deal ends'
);

-- =======================================================
-- 13. BUNDLE_ITEMS: Items contained in a Featured_Bundle
-- =======================================================
CREATE TABLE IF NOT EXISTS Bundle_Items (
    set_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    set_id INT UNSIGNED NOT NULL,
    cosmetic_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uc_set_cosmetic (set_id, cosmetic_id),
    FOREIGN KEY (set_id) REFERENCES Featured_Bundles(set_id) ON DELETE CASCADE,
    FOREIGN KEY (cosmetic_id) REFERENCES Standard_Cosmetics(cosmetic_id) ON DELETE RESTRICT
);

-- =======================================================
-- 14. WEEKLY_DEALS
-- -----------------------------------------------------
-- Tracks the three individual cosmetics featured for the current week
-- =======================================================
CREATE TABLE IF NOT EXISTS Weekly_Deals (
    rotation_week_start DATE PRIMARY KEY COMMENT 'The start date (e.g., Monday) of the active week',
    
    -- IDs link directly to the Standard_Cosmetics table
    avatar_cosmetic_id INT UNSIGNED NOT NULL COMMENT 'Cosmetic ID for the featured Avatar',
    frame_cosmetic_id INT UNSIGNED NOT NULL COMMENT 'Cosmetic ID for the featured Frame',
    badge_cosmetic_id INT UNSIGNED NOT NULL COMMENT 'Cosmetic ID for the featured Badge (Standard, purchasable type)',
    
    FOREIGN KEY (avatar_cosmetic_id) REFERENCES Standard_Cosmetics(cosmetic_id) ON DELETE RESTRICT,
    FOREIGN KEY (frame_cosmetic_id) REFERENCES Standard_Cosmetics(cosmetic_id) ON DELETE RESTRICT,
    FOREIGN KEY (badge_cosmetic_id) REFERENCES Standard_Cosmetics(cosmetic_id) ON DELETE RESTRICT
);
