-- -----------------------------------------------------
-- Database: CoinFlow Academy - FINAL SCHEMA
-- This script MUST be run on a clean database to create all 11 tables.
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
-- 4. USER_STATS: Stores user-specific scoring and currencies
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
-- 5. USER_COURSE_PROGRESS: Tracks individual user progress on each course
-- =======================================================
CREATE TABLE IF NOT EXISTS User_Course_Progress (
    user_progress_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    status ENUM('Locked', 'Unlocked', 'InProgress', 'Completed') NOT NULL,
    last_lesson_completed SMALLINT UNSIGNED DEFAULT 0 COMMENT 'The index of the last lesson completed (0-10)',
    progress_percentage TINYINT UNSIGNED DEFAULT 0,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY uc_user_course (user_id, course_id),
    CONSTRAINT chk_progress_percentage CHECK (progress_percentage <= 100),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS idx_progress_status ON User_Course_Progress (user_id, status);

-- =======================================================
-- 6. BADGES and 7. USER_BADGES
-- =======================================================
CREATE TABLE IF NOT EXISTS Badges (
    badge_id INT UNSIGNED PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_url VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS User_Badges (
    user_badge_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    badge_id INT UNSIGNED NOT NULL,
    date_earned TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uc_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES Badges(badge_id) ON DELETE RESTRICT
);

-- =======================================================
-- 8. COSMETICS and 9. USER_COSMETICS
-- =======================================================
CREATE TABLE IF NOT EXISTS Cosmetics (
    cosmetic_id INT UNSIGNED PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('Icon', 'Avatar Frame', 'Theme') NOT NULL,
    cost_star_points INT UNSIGNED NOT NULL,
    image_url VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS User_Cosmetics (
    user_cosmetic_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    cosmetic_id INT UNSIGNED NOT NULL,
    date_acquired TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_equipped BOOLEAN DEFAULT FALSE,
    UNIQUE KEY uc_user_cosmetic (user_id, cosmetic_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (cosmetic_id) REFERENCES Cosmetics(cosmetic_id) ON DELETE RESTRICT
);

-- =======================================================
-- 10. MARKETPLACE_SETS and 11. DAILY_MARKET_ROTATION
-- -----------------------------------------------------
-- Logic for rotating items in the marketplace
-- =======================================================
CREATE TABLE IF NOT EXISTS Marketplace_Sets (
    set_id INT UNSIGNED PRIMARY KEY,
    set_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE IF NOT EXISTS Marketplace_Set_Items (
    set_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    set_id INT UNSIGNED NOT NULL,
    cosmetic_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uc_set_cosmetic (set_id, cosmetic_id),
    FOREIGN KEY (set_id) REFERENCES Marketplace_Sets(set_id) ON DELETE CASCADE,
    FOREIGN KEY (cosmetic_id) REFERENCES Cosmetics(cosmetic_id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Daily_Market_Rotation (
    rotation_date DATE PRIMARY KEY,
    set_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (set_id) REFERENCES Marketplace_Sets(set_id) ON DELETE RESTRICT
);
