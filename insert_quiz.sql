-- -----------------------------------------------------
-- File: insert_quiz.sql
-- Description: Migrates the 5 final quiz questions/options from the packed strings 
--              in Course_Lessons (Lesson Index 10) into the new Quiz_Questions table.
-- Assumptions: Lesson IDs are assumed to be sequential: Course 1 L10 = 10, Course 2 L10 = 20, etc.
-- -----------------------------------------------------
USE coinflow_academy_db;

-- Delete existing data to prevent primary key conflicts if run multiple times
DELETE FROM Quiz_Questions;

-- =======================================================
-- COURSE 1: The Budget Blueprint (Lesson ID 10)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(10, 'What does a budget help you do?', 'Increase spending', 'Control and plan expenses', 'Confuse finances', 'Hide money', 'B'),
(10, 'How does zero-based budgeting treat leftover money?', 'Leave it unused', 'Assign every dollar a purpose', 'Spend on wants', 'Save nothing', 'B'),
(10, 'What is the purpose of a sinking fund?', 'Handle irregular expenses', 'Increase debt', 'Track daily spending', 'Reduce income', 'A'),
(10, 'Which rule divides income into 50/30/20?', 'The golden rule', 'The 50/30/20 rule', 'The zero-based plan', 'The cash flow method', 'B'),
(10, 'Why should budgets be flexible?', 'Because income and needs change', 'To stay fixed forever', 'To avoid review', 'To confuse records', 'A');


-- =======================================================
-- COURSE 2: Cash Flow Command (Lesson ID 20)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(20, 'What indicates positive cash flow?', 'Outflows > inflows', 'Inflows > outflows', 'No transactions', 'Only investment gains are counted', 'B'),
(20, 'What is a buffer used for?', 'Increase spending', 'Cover unexpected shortfalls without borrowing', 'Invest aggressively', 'Pay taxes early', 'B'),
(20, 'Which action helps when income is seasonal?', 'Spend all peak income', 'Save part of peak income for lean months', 'Borrow during peak months', 'Ignore seasonality', 'B'),
(20, 'What is a benefit of automation?', 'More manual work', 'Payments on time and consistent savings', 'Less clarity', 'Missed bills', 'B'),
(20, 'What baseline should irregular earners use?', 'Highest monthly income', 'Lowest expected monthly income', 'Average of last 2 months', 'Random month', 'B');


-- =======================================================
-- COURSE 3: Debt Defense 101 (Lesson ID 30)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(30, 'What is an example of good debt?', 'Shopping debt', 'Home loan', 'Lottery ticket', 'Payday loan', 'B'),
(30, 'Which strategy targets high-interest debts first?', 'Snowball', 'Avalanche', 'Random', 'Round-robin', 'B'),
(30, 'What should your credit utilization ideally stay under?', '10%', '30%', '60%', '90%', 'B'),
(30, 'How can you avoid high-interest traps?', 'Take every loan available', 'Avoid payday loans and compare rates', 'Delay payments', 'Ignore terms', 'B'),
(30, 'What improves credit health?', 'Paying on time', 'Maxing cards', 'Missing due dates', 'Ignoring reports', 'A');


-- =======================================================
-- COURSE 4: Credit Score Catalyst (Lesson ID 40)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(40, 'What is a credit score used for?', 'Predicting income', 'Measuring creditworthiness', 'Calculating taxes', 'Estimating savings', 'B'),
(40, 'Which action builds credit?', 'Missing payments', 'Making payments on time', 'Borrowing more', 'Ignoring bills', 'B'),
(40, 'What should you do if you find an error on your credit report?', 'File a dispute', 'Do nothing', 'Take a new loan', 'Cancel account', 'A'),
(40, 'What is a good credit utilization ratio?', 'Below 30%', 'Above 80%', '100%', '50%', 'A'),
(40, 'Which of the following is a credit myth?', 'Checking your score lowers it', 'Paying bills on time helps', 'Keeping low balance helps', 'Reviewing reports helps', 'A');


-- =======================================================
-- COURSE 5: Investment Launchpad (Lesson ID 50)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(50, 'What does diversification help with?', 'Increases risk', 'Reduces risk', 'Guarantees profit', 'Avoids taxes', 'B'),
(50, 'What is compounding?', 'Simple interest', 'Earning on earnings', 'Spending', 'Borrowing', 'B'),
(50, 'Which mindset benefits long-term investors?', 'Chasing hype', 'Staying patient', 'Reacting emotionally', 'Ignoring goals', 'B'),
(50, 'What are investment vehicles?', 'Tools to invest collectively', 'Insurance plans', 'Tax refunds', 'Loans', 'A'),
(50, 'Which action is a common investing mistake?', 'Emotional investing', 'Research', 'Goal setting', 'Patience', 'A');


-- =======================================================
-- COURSE 6: Tax Taming Basics (Lesson ID 60)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(60, 'What is the main goal of taxes?', 'Fund public services', 'Reduce salaries', 'Increase debt', 'Avoid payments', 'A'),
(60, 'What is an example of a direct tax?', 'Income Tax', 'GST', 'Import Tax', 'Sales Tax', 'A'),
(60, 'What is the main purpose of deductions?', 'Reduce taxable income', 'Increase tax owed', 'Avoid audits', 'Delay payments', 'A'),
(60, 'What is tax planning?', 'Legal way to reduce tax', 'Evading tax', 'Spending more', 'Filing late', 'A'),
(60, 'Why keep tax documents organized?', 'To simplify returns and handle audits', 'To hide details', 'To pay extra', 'To confuse the system', 'A');


-- =======================================================
-- COURSE 7: Portfolio Architect (Lesson ID 70)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(70, 'What does Modern Portfolio Theory promote?', 'Diversification for balanced risk', 'One-asset focus', 'Avoiding markets', 'Gambling', 'A'),
(70, 'What is the purpose of asset allocation?', 'To balance risk/reward', 'To increase taxes', 'To gamble', 'To save cash only', 'A'),
(70, 'What’s a key difference between ETFs and mutual funds?', 'ETFs trade throughout the day', 'Mutual funds are tax-free', 'ETFs have no fees', 'Mutual funds never diversify', 'A'),
(70, 'What are behavioral biases?', 'Emotional mistakes in investing', 'Legal contracts', 'Tax deductions', 'Market indicators', 'A'),
(70, 'Why is long-term planning important?', 'It uses compounding to grow wealth', 'It avoids saving', 'It shortens time horizon', 'It ensures daily profit', 'A');


-- =======================================================
-- COURSE 8: Real Estate Reality (Lesson ID 80)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(80, 'What is a mortgage?', 'A loan secured by property', 'A type of rent', 'A government tax', 'A property deed', 'A'),
(80, 'What is a benefit of buying property?', 'Building equity', 'Avoiding all costs', 'Zero maintenance', 'Temporary ownership', 'A'),
(80, 'Why is diversification essential?', 'To reduce risk', 'To avoid investing', 'To limit growth', 'To gain rent only', 'A'),
(80, 'What costs should homeowners plan for?', 'Taxes, insurance, and maintenance', 'Food and travel', 'Only loan interest', 'Nothing', 'A'),
(80, 'What does leverage mean in real estate?', 'Using borrowed funds to invest', 'Selling property for cash', 'Ignoring expenses', 'Paying with coins only', 'A');


-- =======================================================
-- COURSE 9: Future-Proofing Finance (Lesson ID 90)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(90, 'What does insurance primarily do?', 'Protects from financial loss', 'Guarantees profit', 'Avoids inflation', 'Creates new loans', 'A'),
(90, 'Why is estate planning important?', 'Ensures smooth wealth transfer', 'Increases debt', 'Avoids insurance', 'Prevents savings', 'A'),
(90, 'What is the key purpose of retirement planning?', 'Provides post-retirement income', 'Eliminates expenses', 'Increases job opportunities', 'Avoids planning', 'A'),
(90, 'How can taxes on investments be minimized?', 'Use tax-advantaged accounts', 'Trade daily', 'Ignore capital gains', 'Spend all income', 'A'),
(90, 'What defines a financial legacy?', 'Passing wealth and wisdom', 'Avoiding heirs', 'Short-term profits', 'Quick spending', 'A');


-- =======================================================
-- COURSE 10: Global Markets Gauntlet (Lesson ID 100)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(100, 'What best defines global markets?', 'Interconnected trade systems', 'Domestic trade only', 'Currency printing', 'Stock dividends', 'A'),
(100, 'What primarily affects currency exchange rates?', 'Inflation and interest rates', 'Population growth', 'Climate patterns', 'Random chance', 'A'),
(100, 'What is a central bank’s main role?', 'Managing national monetary policy', 'Setting property prices', 'Running businesses', 'Hiring employees', 'A'),
(100, 'Why do investors use global ETFs?', 'Diversify across markets', 'Reduce profit margins', 'Avoid taxes', 'Remove liquidity', 'A'),
(100, 'What are common international risks?', 'Currency and political instability', 'Local traffic laws', 'Seasonal shopping', 'Domestic inflation only', 'A');


-- =======================================================
-- COURSE 11: Entrepreneurial Engine (Lesson ID 110)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(110, 'What defines entrepreneurship?', 'Starting and managing a business', 'Avoiding taxes', 'Working as an employee', 'Selling old assets', 'A'),
(110, 'What does a business model describe?', 'How a company creates and captures value', 'Employee hierarchy', 'Product lifespan', 'Factory setup', 'A'),
(110, 'What is the goal of cash flow management?', 'Ensuring income exceeds expenses', 'Avoiding legal fees', 'Paying fewer taxes', 'Eliminating salaries', 'A'),
(110, 'What is scaling in business?', 'Efficiently expanding capacity', 'Hiring fewer staff', 'Buying more debt', 'Raising prices only', 'A'),
(110, 'Why is valuation essential?', 'To determine company worth', 'To set employee salaries', 'To measure product quality', 'To increase taxes', 'A');


-- =======================================================
-- COURSE 12: Alternative Assets Ascent (Lesson ID 120)
-- =======================================================
INSERT INTO Quiz_Questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(120, 'What defines alternative investments?', 'Non-traditional assets', 'Only cash deposits', 'Government bonds', 'Office furniture', 'A'),
(120, 'Why do investors buy commodities?', 'Hedge against inflation', 'Reduce taxes', 'Avoid income', 'Save cash', 'A'),
(120, 'What is private equity?', 'Investment in private companies', 'Buying public shares', 'Government pension', 'Fixed deposits', 'A'),
(120, 'What is cryptocurrency?', 'Digital decentralized currency', 'Paper notes', 'Stock index', 'Credit card', 'A'),
(120, 'Why is diversification important?', 'Reduces risk and increases stability', 'Guarantees losses', 'Removes taxes', 'Focuses on one stock', 'A');