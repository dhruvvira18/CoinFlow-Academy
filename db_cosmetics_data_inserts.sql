-- Bundle Cosmetics Data Inserts

-- Christmas Bundle
INSERT INTO bundle_cosmetics
    (name, type, cost_star_points, image_url)
VALUES
    ('Christmas Avatar', 'Avatar', 1500, '../images/bundle_christmas_avatar.png'),
    ('Christmas Frame', 'Frame', 1500, '../images/bundle_christmas_frame.png'),
    ('Christmas Badge', 'Badge', 1500, '../images/bundle_christmas_badge.png');

-- Halloween Bundle
INSERT INTO bundle_cosmetics
    (name, type, cost_star_points, image_url)
VALUES
    ('Halloween Avatar', 'Avatar', 1500, '../images/bundle_halloween_avatar.png'),
    ('Halloween Frame', 'Frame', 1500, '../images/bundle_halloween_frame.png'),
    ('Halloween Badge', 'Badge', 1500, '../images/bundle_halloween_badge.png');


INSERT INTO featured_bundles
    (set_name, description, bundle_cost_sp, start_date, end_date)
VALUES
    -- 1. Halloween Bundle (Repeats Annually: Oct 1st to Nov 1st)
    ('Halloween Spooktacular', 
     'Includes the Spider Web Frame, Haunted Hero Badge, and Spooky Ghost Avatar.', 
     4000, -- Total cost less than individual items if bought together
     '0000-10-01', 
     '0000-11-01'),
     
    -- 2. Christmas Bundle (Repeats Annually: Dec 1st to Jan 5th)
    ('Festive Wonderland Set', 
     'Get all the Christmas items at a special discounted price for the holidays!', 
     3500, 
     '0000-12-01', 
     '0000-01-05');

-- === 1. INSERT CHRISTMAS ITEMS INTO THE CHRISTMAS BUNDLE ===
INSERT INTO `bundle_items` (set_id, cosmetic_id)
VALUES
-- Link 'Christmas Avatar' to 'Festive Wonderland Set'
((SELECT set_id FROM featured_bundles WHERE set_name = 'Festive Wonderland Set' LIMIT 1),
 (SELECT cosmetic_id FROM bundle_cosmetics WHERE name = 'Christmas Avatar' LIMIT 1)),

-- Link 'Christmas Frame' to 'Festive Wonderland Set'
((SELECT set_id FROM featured_bundles WHERE set_name = 'Festive Wonderland Set' LIMIT 1),
 (SELECT cosmetic_id FROM bundle_cosmetics WHERE name = 'Christmas Frame' LIMIT 1)),

-- Link 'Christmas Badge' to 'Festive Wonderland Set'
((SELECT set_id FROM featured_bundles WHERE set_name = 'Festive Wonderland Set' LIMIT 1),
 (SELECT cosmetic_id FROM bundle_cosmetics WHERE name = 'Christmas Badge' LIMIT 1));


-- === 2. INSERT HALLOWEEN ITEMS INTO THE HALLOWEEN BUNDLE ===
INSERT INTO `bundle_items` (set_id, cosmetic_id)
VALUES
-- Link 'Halloween Avatar' to 'Halloween Spooktacular'
((SELECT set_id FROM featured_bundles WHERE set_name = 'Halloween Spooktacular' LIMIT 1),
 (SELECT cosmetic_id FROM bundle_cosmetics WHERE name = 'Halloween Avatar' LIMIT 1)),

-- Link 'Halloween Frame' to 'Halloween Spooktacular'
((SELECT set_id FROM featured_bundles WHERE set_name = 'Halloween Spooktacular' LIMIT 1),
 (SELECT cosmetic_id FROM bundle_cosmetics WHERE name = 'Halloween Frame' LIMIT 1)),

-- Link 'Halloween Badge' to 'Halloween Spooktacular'
((SELECT set_id FROM featured_bundles WHERE set_name = 'Halloween Spooktacular' LIMIT 1),
 (SELECT cosmetic_id FROM bundle_cosmetics WHERE name = 'Halloween Badge' LIMIT 1));


-- Weekly Cosmetics Data Inserts
INSERT INTO weekly_cosmetics
    (name, type, cost_star_points, image_url)
VALUES
    ('Cyberpunk Avatar', 'Avatar', 1200, '../images/cyberpunk_avatar.png'),
    ('Cyberpunk Frame', 'Frame', 1000, '../images/cyberpunk_frame.png'),
    ('Cyberpunk Badge', 'Badge', 800, '../images/cyberpunk_badge.png'),

    ('Forest Guardian Avatar', 'Avatar', 1800, '../images/forest_guardian_avatar.png'),
    ('Forest Guardian Frame', 'Frame', 1100, '../images/forest_guardian_frame.png'),
    ('Forest Guardian Badge', 'Badge', 900, '../images/forest_guardian_badge.png'),

    ('Ninja Avatar', 'Avatar', 1600, '../images/ninja_avatar.png'),
    ('Ninja Frame', 'Frame', 1300, '../images/ninja_frame.png'),
    ('Ninja Badge', 'Badge', 700, '../images/ninja_badge.png'),

    ('Steampunk Avatar', 'Avatar', 1100, '../images/steampunk_avatar.png'),
    ('Steampunk Frame', 'Frame', 1200, '../images/steampunk_frame.png'),
    ('Steampunk Badge', 'Badge', 1000, '../images/steampunk_badge.png');