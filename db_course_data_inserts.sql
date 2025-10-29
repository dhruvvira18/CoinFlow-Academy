-- -----------------------------------------------------
-- DATA INSERTS for Tiered Vaults
-- NOTE: This script assumes the Courses and Course_Tiers tables exist and are empty.
-- -----------------------------------------------------
USE coinflow_academy_db;

-- -----------------------------------------------------
-- 1. CLEANUP (Safety first)
-- -----------------------------------------------------
-- Delete from child tables first to respect foreign key constraints
DELETE FROM Courses;
DELETE FROM Course_Tiers;

-- -----------------------------------------------------
-- 2. INSERT COURSE TIERS
-- Defines the unlock structure for the application logic.
-- -----------------------------------------------------
-- Tier 1 (Foundation) has no prerequisite
INSERT INTO Course_Tiers (tier_id, tier_name, prerequisite_tier_id) VALUES
(1, 'Foundation Vaults (Adept Mastery Focus)', NULL);

-- Tier 2 (Growth) requires Tier 1 completion
INSERT INTO Course_Tiers (tier_id, tier_name, prerequisite_tier_id) VALUES
(2, 'Growth Vaults (Maven Mastery Focus)', 1);

-- Tier 3 (Mastery) requires Tier 2 completion
INSERT INTO Course_Tiers (tier_id, tier_name, prerequisite_tier_id) VALUES
(3, 'Mastery Vaults (Mogul Mastery Focus)', 2);

-- Tier 4 (Grandmaster) requires Tier 3 completion
INSERT INTO Course_Tiers (tier_id, tier_name, prerequisite_tier_id) VALUES
(4, 'Grandmaster Vaults (Grandmaster Mastery Focus)', 3);


-- -----------------------------------------------------
-- 3. INSERT COURSES (VAULTS)
-- Cost set to 0 for Tier 1 (unlocked by default) and increases for subsequent tiers.
-- -----------------------------------------------------
INSERT INTO Courses (course_id, tier_id, course_name, core_topic, total_lessons, difficulty_level, skill_point_cost) VALUES
-- Tier 1: Foundation Vaults (Adept) - Cost 0 (Unlocked by default for new users)
(1, 1, 'The Budget Blueprint', 'Budgeting & Tracking: Creating and maintaining a basic budget (50/30/20 rule, zero-based).', 10, 'Adept', 0),
(2, 1, 'Cash Flow Command', 'Banking & Saving: Understanding checking/savings, managing cash flow, and building an emergency fund.', 10, 'Adept', 0),
(3, 1, 'Debt Defense 101', 'Basic Debt Management: Distinguishing good vs. bad debt, understanding credit card interest, and the concept of debt snowball/avalanche.', 10, 'Adept', 0),

-- Tier 2: Growth Vaults (Maven) - Cost 500 (Purchased after Tier 1 is completed)
(4, 2, 'Credit Score Catalyst', 'Credit Management: What makes up a credit score (FICO), reading a credit report, and building/improving credit responsibly.', 10, 'Maven', 500),
(5, 2, 'Investment Launchpad', 'Introduction to Investing: The basics of compounding, risk vs. return, asset classes (stocks, bonds, real estate), and diversification.', 10, 'Maven', 500),
(6, 2, 'Tax Taming Basics', 'Fundamental Taxation: Understanding income tax, basic tax deductions, and common retirement accounts (401k, IRA).', 10, 'Maven', 500),

-- Tier 3: Mastery Vaults (Mogul) - Cost 1000 (Purchased after Tier 2 is completed)
(7, 3, 'Portfolio Architect', 'Advanced Investing Strategy: Modern Portfolio Theory, passive vs. active investing, ETFs vs. Mutual Funds, and rebalancing.', 10, 'Mogul', 1000),
(8, 3, 'Real Estate Reality', 'Property & Home Ownership: Understanding mortgages, closing costs, rental property basics, and real estate as an investment.', 10, 'Mogul', 1000),
(9, 3, 'Future-Proofing Finance', 'Estate Planning & Insurance: Understanding different insurance types (life, disability), basic wills, trusts, and maximizing retirement savings.', 10, 'Mogul', 1000),

-- Tier 4: Grandmaster Vaults (Grandmaster) - Cost 2000 (Purchased after Tier 3 is completed)
(10, 4, 'Global Markets Gauntlet', 'International Investing & Economics: Understanding currency risk, global ETFs, and how macroeconomics (inflation, central banks) impacts personal wealth.', 10, 'Grandmaster', 2000),
(11, 4, 'Entrepreneurial Engine', 'Small Business Finance & Side Hustles: Basics of business valuation, funding, cash flow management for a small venture, and tax implications.', 10, 'Grandmaster', 2000),
(12, 4, 'Alternative Assets Ascent', 'Niche Investments: Exploring assets outside of traditional stocks and bonds (e.g., commodities, angel investing, art, crypto fundamentals).', 10, 'Grandmaster', 2000);
