# ISKOnnect setup guide

This project is designed for a local PHP + MySQL development setup using XAMPP.

For a public PHP/MySQL deployment on Railway, follow the **Railway deployment** section in `README.md`. GitHub Pages cannot run this application's backend.

## 1) Start the local services

- Open XAMPP Control Panel.
- Start Apache.
- Start MySQL.

## 2) Initialize the database

For a fresh database, open the project in VS Code and run **Tasks: Run Task → ISKOnnect: Initialize database**. Or, from the project root, run:

```bash
"C:\xampp\php\php.exe" bin/init_db.php
```

This script creates the `iskonnect` database if needed and imports `database/schema.sql`.

If you already created a database from an earlier ISKOnnect starter schema, do not reinitialize it. In phpMyAdmin select `iskonnect`, choose **Import**, and apply each outstanding migration in `database/migrations/` in numeric order. Existing sample organizations remain available to historical applications and assigned officers but are hidden from new student applications.

Department choices use the names **College of Information and Computing Sciences** and **Teachers Education**. Migration `005_computing_sciences_department_name.sql` updates existing student and organization records to the new computing-college name.

Migration `007_program_organization_catalog.sql` adds the current program and campus-wide organization catalog plus eligibility mappings. Existing applications and officer assignments for legacy organizations are preserved.

Migration `008_registration_first_name_surname.sql` adds separate first-name and surname fields for student and officer registrations. Existing account and registration-request names are retained and split for first-name greetings.

Migration `009_user_profile_bio_photo.sql` adds optional bios and profile photo paths to user accounts. Apply it to existing databases before using My Account. On the account page, users can drag, zoom, and rotate a square profile-photo crop before it is saved.

Migration `010_user_facebook_url.sql` adds an optional Facebook profile/page link to user accounts. Apply it to existing databases before using the Facebook link field in My Account.

Migration `011_organization_member_workspace.sql` adds private organization file sharing and member support tickets. Apply it to existing databases before using the Files or Submit a Ticket tabs. Officers can share PDF, DOCX, XLSX, PPTX, TXT, PNG, JPG, and WebP files up to 10 MB. Files are stored in the project-level `storage/organization-files/` directory and downloaded only through the membership-checked application route. Ensure PHP can create and write to that directory and the web server blocks direct requests to `storage/`. Organization ticket replies and statuses (Open, In progress, Resolved) are visible to the submitting member and officers assigned to that organization.

Migration `012_organization_leadership_profiles.sql` creates the five organization officer positions for every organization and adds editable About and officer profiles. Apply it to existing databases before using the organization About and Officers tabs. Assigned officers can update officer names, bios, contact emails, Facebook links, and optional JPG, PNG, or WebP profile photos. Officer photos are stored in the private `storage/organization-officers/` directory and served only to organization members and assigned officers.

## 3) Check the environment file

The project reads from `.env` in the root folder. Use the example provided in `.env.example` as your starting point.

```env
APP_ENV=local
APP_DEBUG=1
APP_URL=http://localhost:8080
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=iskonnect
DB_USER=root
DB_PASSWORD=
```

## 4) Start the PHP development server

In VS Code, run the task:

- `ISKOnnect: Start PHP site`

Then open:

- http://localhost:8080

## 5) Create a local student council account

In VS Code, choose **Tasks: Run Task → ISKOnnect: Create local student council account**. This account alone can publish or remove announcements in the public hub and approve/reject organization officer registrations.

## 6) Register an organization officer

Anyone can submit the public **Officer registration** form from the sign-in page. Enter a 10-digit student number, choose the organization, and set a password of at least 8 characters. The account cannot sign in until the local student council approves the request.

Student and officer registrations both require a first name, surname, unique 10-digit student number, a password of at least 8 characters, and both the campus application-process confirmation and Data Privacy Act privacy consent checkboxes. After sign-in, the greeting uses the account's first name.

## 7) Create an officer account directly (optional)

Administrators can still create an organization officer directly with the task **ISKOnnect: Create officer account**. When prompted, enter:

- officer email
- officer full name
- password (12+ characters)
- comma-separated organization IDs (for example: `ssc,pub`)

## 8) Log in

The app will allow:

- campus announcements are public, including for signed-out visitors
- registration asks the student to choose a department
- student registration offers department-specific programs; College of Information and Computing Sciences automatically selects BS Information Technology
- Business Administration and Entrepreneurship offers BS Business Administration for first- and second-year students, BS Entrepreneurship, and BSBA Financial Management/Marketing Management majors for third-year students
- students can submit one pending or accepted application to an eligible program organization and one to an overall organization; a rejected application frees its group slot
- department and program eligibility determines program-organization choices, while overall organizations are available to all students
- student application forms prefill the registered program and year level from the account
- after registration, students see organizations for their department and campus-wide groups
- approved organization officers can manage applications for their assigned organization in Awaiting review, Accepted, and Rejected tabs; officers can change an application's status and decision note from any tab, or permanently delete an application after confirmation
- the local student council dashboard focuses on public council announcements and reviewing organization officer registrations
- organization officer registrations are grouped in Awaiting review, Accepted, and Rejected tabs on the council dashboard
- local student council sign-in can manage public announcements and officer registrations
- students see local student council announcements and their own registration/application status
- admitted organization members also see announcements posted for their organization

### Useful organization IDs

The starter seed data includes:

- `ssc`
- `pub`
- `arts`
- `cs`
- `bsa`
- `edu`
- `ind`
- `eng`
- `cov`
- `min`
- `spt`

If you want to use a custom database password, update `.env` and keep the value private in local-only development.
