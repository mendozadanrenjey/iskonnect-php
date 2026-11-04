CREATE DATABASE IF NOT EXISTS iskonnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iskonnect;

CREATE TABLE IF NOT EXISTS organizations (
  id VARCHAR(32) PRIMARY KEY,
  name VARCHAR(160) NOT NULL UNIQUE,
  category VARCHAR(120) NOT NULL,
  department VARCHAR(160) NOT NULL DEFAULT 'Campus-wide',
  application_group ENUM('program','overall','legacy') NOT NULL DEFAULT 'legacy',
  description TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_programs (
  organization_id VARCHAR(32) NOT NULL,
  program_name VARCHAR(160) NOT NULL,
  PRIMARY KEY (organization_id, program_name),
  CONSTRAINT fk_org_program_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  first_name VARCHAR(160) NULL,
  surname VARCHAR(160) NULL,
  student_number VARCHAR(40) NULL UNIQUE,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','officer','council') NOT NULL DEFAULT 'student',
  course VARCHAR(160) NULL,
  department VARCHAR(160) NULL,
  year_level VARCHAR(24) NULL,
  bio VARCHAR(500) NULL,
  profile_photo VARCHAR(255) NULL,
  facebook_url VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS officer_organizations (
  officer_id BIGINT UNSIGNED NOT NULL,
  organization_id VARCHAR(32) NOT NULL,
  PRIMARY KEY (officer_id, organization_id),
  CONSTRAINT fk_oo_user FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_oo_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  organization_id VARCHAR(32) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_announcement_created (created_at),
  KEY idx_announcement_organization (organization_id, created_at),
  CONSTRAINT fk_announcement_author FOREIGN KEY (author_id) REFERENCES users(id),
  CONSTRAINT fk_announcement_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS officer_registration_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  first_name VARCHAR(160) NULL,
  surname VARCHAR(160) NULL,
  student_number VARCHAR(10) NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  organization_id VARCHAR(32) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_officer_request_email (email),
  UNIQUE KEY uq_officer_request_student_number (student_number),
  KEY idx_officer_request_status (status, created_at),
  CONSTRAINT fk_officer_request_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_officer_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  organization_id VARCHAR(32) NOT NULL,
  preferred_role VARCHAR(120) NULL,
  motivation TEXT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  officer_note VARCHAR(1000) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_org (student_id, organization_id),
  KEY idx_org_status (organization_id, status, created_at),
  CONSTRAINT fk_app_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_app_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_members (
  organization_id VARCHAR(32) NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  application_id BIGINT UNSIGNED NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_id, student_id),
  UNIQUE KEY uq_member_application (application_id),
  CONSTRAINT fk_member_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id VARCHAR(32) NOT NULL,
  uploader_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name CHAR(32) NOT NULL UNIQUE,
  mime_type VARCHAR(127) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org_file_created (organization_id, created_at),
  CONSTRAINT fk_org_file_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_org_file_uploader FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id VARCHAR(32) NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(180) NOT NULL,
  status ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_org_ticket_org_status (organization_id, status, updated_at),
  KEY idx_org_ticket_student (student_id, updated_at),
  CONSTRAINT fk_org_ticket_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_org_ticket_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_ticket_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org_ticket_message (ticket_id, created_at),
  CONSTRAINT fk_org_ticket_message_ticket FOREIGN KEY (ticket_id) REFERENCES organization_tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_org_ticket_message_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_leadership (
  organization_id VARCHAR(32) NOT NULL,
  position VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL DEFAULT '',
  profile TEXT NULL,
  contact_email VARCHAR(254) NULL,
  facebook_url VARCHAR(500) NULL,
  profile_photo CHAR(32) NULL,
  photo_mime_type VARCHAR(32) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_id, position),
  CONSTRAINT fk_org_leadership_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO organizations (id,name,category,department,application_group,description) VALUES
('ssc','Supreme Student Council','Student Council','Campus-wide','legacy','The central student government representing students across the campus.'),
('pub','Campus Publication','Publication','Campus-wide','legacy','Student writers, photographers, and artists sharing campus stories.'),
('arts','Performing Arts Guild','Socio-Cultural','Campus-wide','legacy','Dance, theater, and music groups showcasing student talent.'),
('cs','Computer Society','IT Department','College of Information and Computing Sciences','legacy','Workshops, tech talks, and activities for aspiring IT professionals.'),
('bsa','Business Students Association','Business','Business Administration and Entrepreneurship','legacy','Seminars, entrepreneurship activities, and career development.'),
('edu','Education Majors Society','Education','Teachers Education','legacy','Peer learning and activities for future teachers.'),
('ind','Industrial Technology Society','Technology','Industrial Technology','legacy','Hands-on workshops, skills competitions, and industry activities.'),
('eng','Engineering Society','Engineering','Engineering','legacy','Design challenges, seminars, and projects for future engineers.'),
('cov','Community Outreach Volunteers','Socio-Cultural','Campus-wide','legacy','Community service and outreach activities.'),
('min','Campus Ministry','Socio-Cultural','Campus-wide','legacy','Fellowship and values-based activities.'),
('spt','Sports & Recreation Club','Socio-Cultural','Campus-wide','legacy','Intramurals, training, and student wellness activities.');

INSERT IGNORE INTO organizations (id,name,category,department,application_group,description) VALUES
('asics','Alliance of Students in Information and Computing Sciences (ASICS)','Academic','College of Information and Computing Sciences','program','A program organization for students in the College of Information and Computing Sciences.'),
('insight','Innovative Network of Students in Information & Global Analytics for Holistic Transformation (INSIGHT)','Academic','College of Information and Computing Sciences','program','A program organization for students in the College of Information and Computing Sciences.'),
('life','League of Impeccable Future Entrepreneurs (LIFE)','Business','Business Administration and Entrepreneurship','program','An organization for students in the BS Entrepreneurship program.'),
('mmso','Marketing Management Student Organization (MMSO)','Business','Business Administration and Entrepreneurship','program','A student organization for Business Administration students.'),
('fmso','Financial Management Student Organization (FMSO)','Business','Business Administration and Entrepreneurship','program','A student organization for Business Administration students.'),
('jobs','Junior Organization of Business Student (JOBS)','Business','Business Administration and Entrepreneurship','program','A student organization for Business Administration students.'),
('sfed','Society of Future Educators (SFED)','Education','Teachers Education','program','A department-wide organization for Teachers Education students.'),
('glee','Guild of Leading Elementary Educators (GLEE)','Education','Teachers Education','program','An organization for Bachelor of Elementary Education students.'),
('scire','Science Club in Real Essence (SCIRE)','Education','Teachers Education','program','An organization for Secondary Education Science students.'),
('omg','Organization of Math Geniuses (OMG)','Education','Teachers Education','program','An organization for Secondary Education Mathematics students.'),
('plumap','Progresibong Lupon ng mga Mag-Aaral sa Araling Panlipunan (PLUMAP)','Education','Teachers Education','program','An organization for Secondary Education Social Studies students.'),
('lfc','Lingua Franca Club (LFC)','Education','Teachers Education','program','An organization for English and Mandarin students.'),
('somaphed','Society of Modified Artistic Physical Educators (SoMAPhEd)','Education','Teachers Education','program','An organization for Bachelor of Physical Education students.'),
('aies','Association of Industrial Engineering Students (AIES)','Engineering','Engineering','program','An organization for BS Industrial Engineering students.'),
('icpep','Institute of Computer Engineers of the Philippines Student Edition – BulSU Bustos Chapter (ICpEP.se – BulSU Bustos)','Engineering','Engineering','program','An organization for BS Computer Engineering students.'),
('asset','Association of Skilled Students for Excellence in Technology (ASSET)','Technology','Industrial Technology','program','A department-wide organization for Industrial Technology students.'),
('tfr','The Forerunner Student Publication (TFR)','Publication','Campus-wide','overall','A campus-wide student publication open to students from every program.'),
('samot-sari','Samo’t Sari','Socio-Cultural','Campus-wide','overall','A campus-wide student organization open to students from every program.'),
('dbo','Dance Beat Official (DBO)','Socio-Cultural','Campus-wide','overall','A campus-wide dance organization open to students from every program.'),
('bulsuan-chorale','BulSUan Bustos Chorale','Socio-Cultural','Campus-wide','overall','A campus-wide chorale open to students from every program.'),
('b1','BulSUONE (B1)','Socio-Cultural','Campus-wide','overall','A campus-wide student organization open to students from every program.'),
('ksm','Katipunan Student Movement (KSM)','Socio-Cultural','Campus-wide','overall','A campus-wide student organization open to students from every program.');

INSERT IGNORE INTO organization_leadership (organization_id, position) SELECT id, 'President' FROM organizations;
INSERT IGNORE INTO organization_leadership (organization_id, position) SELECT id, 'Vice President' FROM organizations;
INSERT IGNORE INTO organization_leadership (organization_id, position) SELECT id, 'Secretary' FROM organizations;
INSERT IGNORE INTO organization_leadership (organization_id, position) SELECT id, 'Treasurer' FROM organizations;
INSERT IGNORE INTO organization_leadership (organization_id, position) SELECT id, 'Public Relations Officer' FROM organizations;

INSERT IGNORE INTO organization_programs (organization_id,program_name) VALUES
('life','BS Entrepreneurship'),
('mmso','BS Business Administration'),
('mmso','BS Business Administration major in Financial Management'),
('mmso','BS Business Administration major in Marketing Management'),
('fmso','BS Business Administration'),
('fmso','BS Business Administration major in Financial Management'),
('fmso','BS Business Administration major in Marketing Management'),
('jobs','BS Business Administration'),
('jobs','BS Business Administration major in Financial Management'),
('jobs','BS Business Administration major in Marketing Management'),
('glee','Bachelor of Elementary Education'),
('scire','Bachelor of Secondary Education major in Science'),
('omg','Bachelor of Secondary Education major in Mathematics'),
('plumap','Bachelor of Secondary Education major in Social Studies'),
('lfc','Bachelor of Secondary Education major in English minor in Mandarin'),
('somaphed','Bachelor of Physical Education'),
('aies','BS Industrial Engineering'),
('icpep','BS Computer Engineering');
