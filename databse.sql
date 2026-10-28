// --- SQL Table Creation (Run these commands once in your MySQL client) ---

CREATE TABLE youtubers (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subscribers BIGINT(20) UNSIGNED NOT NULL,
    niche VARCHAR(255) NOT NULL,
    engagement DECIMAL(5, 2) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sponsors (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    industry VARCHAR(255) NOT NULL,
    budget VARCHAR(50) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE collaborations (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    youtuber_id INT(11) UNSIGNED NOT NULL,
    sponsor_id INT(11) UNSIGNED NOT NULL,
    status ENUM('pending', 'accepted', 'completed', 'declined') DEFAULT 'pending',
    proposal_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (youtuber_id) REFERENCES youtubers(id) ON DELETE CASCADE,
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
);

-- NEW: User Table for Login Authentication
CREATE TABLE users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'youtuber', 'sponsor') NOT NULL,
    youtuber_id INT(11) UNSIGNED NULL,
    sponsor_id INT(11) UNSIGNED NULL,
    FOREIGN KEY (youtuber_id) REFERENCES youtubers(id) ON DELETE SET NULL,
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE SET NULL
);

-- Initial Data Inserts
INSERT INTO youtubers (id, name, subscribers, niche, engagement, image, bio) VALUES
(1, 'TechGuru', 5200000, 'Technology Reviews', 10.00, 'https://placehold.co/150x150/5B21B6/ffffff?text=TG', 'I create in-depth reviews of the latest gadgets and software.'),
(2, 'GamingKing', 12100000, 'Gaming & Streams', 8.50, 'https://placehold.co/150x150/4C0D9B/ffffff?text=GK', 'The #1 channel for daily gaming content, live streams, and esports commentary.'),
(3, 'FitnessFreak', 3500000, 'Fitness & Health', 15.00, 'https://placehold.co/150x150/7C3AED/ffffff?text=FF', 'Helping people achieve their fitness goals with workout routines, nutrition tips, and motivational content.'),
(4, 'CreativeCrafts', 1800000, 'DIY & Arts', 11.25, 'https://placehold.co/150x150/6D28D9/ffffff?text=CC', 'Bringing creative ideas to life through DIY projects, art tutorials, and home decor tips.');

INSERT INTO sponsors (id, name, industry, budget, logo, description) VALUES
(1, 'CodeForge Software', 'Tech', 'High', 'https://placehold.co/150x150/2563EB/ffffff?text=CF', 'A leading software company looking for tech YouTubers to promote our new code editor.'),
(2, 'GameOn Studios', 'Gaming', 'Medium', 'https://placehold.co/150x150/1D4ED8/ffffff?text=GO', 'We develop popular mobile games and want to collaborate with gaming influencers to reach a wider audience.'),
(3, 'BioWellness Inc.', 'Health', 'High', 'https://placehold.co/150x150/3B82F6/ffffff?text=BW', 'A health and wellness brand with a focus on organic supplements.');

INSERT INTO collaborations (youtuber_id, sponsor_id, status) VALUES (1, 1, 'accepted');

-- NEW: User data inserts (passwords are plain text for this demo)
INSERT INTO users (username, password_hash, role, youtuber_id, sponsor_id) VALUES
('admin', 'adminpass', 'admin', NULL, NULL),
('techguru', 'tgpass', 'youtuber', 1, NULL),
('gamingking', 'gkpass', 'youtuber', 2, NULL),
('codeforge', 'cfpass', 'sponsor', NULL, 1),
('gameon', 'gopass', 'sponsor', NULL, 2);