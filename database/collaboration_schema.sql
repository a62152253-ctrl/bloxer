-- =============================================
-- COLLABORATION SYSTEM DATABASE SCHEMA
-- =============================================

-- Teams Table
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    avatar_url VARCHAR(500),
    owner_id INT NOT NULL,
    company_name VARCHAR(255),
    website VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_owner_id (owner_id),
    INDEX idx_slug (slug)
);

-- Team Members Table
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'developer', 'designer', 'tester') DEFAULT 'developer',
    permissions JSON DEFAULT '{}',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    invited_by INT,
    status ENUM('active', 'pending', 'suspended') DEFAULT 'active',
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_team_user (team_id, user_id),
    INDEX idx_team_id (team_id),
    INDEX idx_user_id (user_id)
);

-- Team Invitations Table
CREATE TABLE IF NOT EXISTS team_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    invited_email VARCHAR(255) NOT NULL,
    invited_by INT NOT NULL,
    role ENUM('admin', 'developer', 'designer', 'tester') DEFAULT 'developer',
    token VARCHAR(255) NOT NULL UNIQUE,
    message TEXT,
    status ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT DATE_ADD(NOW(), INTERVAL 7 DAY),
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_team_id (team_id),
    INDEX idx_email (invited_email),
    INDEX idx_token (token),
    INDEX idx_status (status)
);

-- Code Reviews Table
CREATE TABLE IF NOT EXISTS code_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    project_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    author_id INT NOT NULL,
    reviewer_id INT,
    status ENUM('pending', 'in_review', 'approved', 'rejected', 'changes_requested') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    code_changes JSON DEFAULT '{}',
    review_comments JSON DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    due_date TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_team_id (team_id),
    INDEX idx_project_id (project_id),
    INDEX idx_author_id (author_id),
    INDEX idx_reviewer_id (reviewer_id),
    INDEX idx_status (status)
);

-- Deploy Queue Table
CREATE TABLE IF NOT EXISTS deploy_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    project_id INT NOT NULL,
    version VARCHAR(50) NOT NULL,
    environment ENUM('development', 'staging', 'production') DEFAULT 'staging',
    status ENUM('pending', 'building', 'testing', 'deploying', 'completed', 'failed', 'rolled_back') DEFAULT 'pending',
    build_config JSON DEFAULT '{}',
    test_results JSON DEFAULT '{}',
    deploy_log TEXT,
    initiated_by INT NOT NULL,
    approved_by INT,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_team_id (team_id),
    INDEX idx_project_id (project_id),
    INDEX idx_status (status),
    INDEX idx_environment (environment),
    INDEX idx_created_at (created_at)
);

-- Team Activity Log
CREATE TABLE IF NOT EXISTS team_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type ENUM('team', 'member', 'project', 'review', 'deployment') NOT NULL,
    entity_id INT,
    details JSON DEFAULT '{}',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_team_id (team_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Update existing projects table to support teams
ALTER TABLE projects ADD COLUMN team_id INT NULL AFTER user_id;
ALTER TABLE projects ADD COLUMN FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL;
ALTER TABLE projects ADD INDEX idx_team_id (team_id);

-- Insert sample data for testing
INSERT INTO teams (name, slug, description, owner_id, company_name) VALUES 
('Bloxer Team', 'bloxer-team', 'Main development team', 1, 'Bloxer Inc.'),
('Design Squad', 'design-squad', 'UI/UX design team', 1, 'Creative Agency');

INSERT INTO team_members (team_id, user_id, role, status) VALUES 
(1, 1, 'owner', 'active'),
(1, 2, 'developer', 'active'),
(2, 1, 'owner', 'active');
