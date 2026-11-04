ALTER TABLE organizations
  ADD COLUMN application_group ENUM('program','overall','legacy') NOT NULL DEFAULT 'legacy' AFTER department;

CREATE TABLE organization_programs (
  organization_id VARCHAR(32) NOT NULL,
  program_name VARCHAR(160) NOT NULL,
  PRIMARY KEY (organization_id, program_name),
  CONSTRAINT fk_org_program_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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
