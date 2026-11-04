# ISKOnnect PHP + MySQL starter

A deployable foundation for the BulSU Bustos Campus organizations hub. It replaces the source page's hosted `claude` data calls with PHP, PDO, and MySQL/MariaDB. Local development uses XAMPP and VS Code; Docker is not required.

## Included

- Signed-out visitors see only the sign-in and registration experience.
- Responsive interface with a saved Light/Dark theme toggle.
- Signed-in users can update a 500-character bio, add a Facebook profile/page link, and upload a JPG, PNG, or WebP profile photo from My Account, where they can reposition, zoom, and rotate the square crop before saving.
- ISKOnnect SVG logo and campus photo are served locally from `public/assets/`.
- Local student council accounts alone can publish/remove announcements and review officer registrations.
- Prospective organization officers can register publicly; council approval is required before their account is created.
- Students see local student council announcements, their organization registration form, and private application status.
- Students see a separate member-only workspace for each organization they have joined, with an editable-by-officers About section, announcements and files, five officer profiles, and private support tickets.
- Assigned organization officers can update their organization's About section and the names, bios, contact details, Facebook links, and cropped profile photos for the President, Vice President, Secretary, Treasurer, and Public Relations Officer.
- Organization officers have a focused dashboard for Local Student Council announcements, assigned student applications, a separate publishing and file-upload form per assigned organization, and private member-ticket replies and status updates.
- Student registration creates a student account; organization officer registration requires council approval.
- One application per student, with private application status and officer notes.
- Officer review limited to organizations assigned to that officer. Approvals add students to the organization's membership table.
- Organization files are stored in a private project-level directory and can be downloaded only by members or assigned officers of that organization.
- Organization leadership photos are stored privately and shown only to the organization's admitted members and assigned officers.
- CSRF protection, password hashing, prepared SQL statements, session ID rotation, output escaping, and scoped review checks.
- VS Code tasks for starting the PHP site, creating officers, and checking PHP syntax.

## Quick start

1. Start Apache and MySQL in XAMPP Control Panel.
2. Run **Tasks: Run Task → ISKOnnect: Initialize database** for a fresh database. For an existing earlier database, apply each outstanding migration in order from `database/migrations/`.
3. Check `.env` for your local MySQL credentials.
4. In VS Code choose **Tasks: Run Task → ISKOnnect: Start PHP site**.
5. Visit [http://localhost:8080](http://localhost:8080). Register with the student's department to apply to an organization.
6. Create the local student council account with **Tasks: Run Task → ISKOnnect: Create local student council account**. Create other organization officers from the public officer registration form; council approval enables their account.

See `SETUP.md` for detailed steps.

## Railway deployment

This PHP/MySQL application cannot run on GitHub Pages. Use GitHub to host the source code and Railway to run the PHP backend and MySQL database.

1. Push this project to a GitHub repository. The `.env`, uploaded profile photos, and private organization files are excluded; keep the repository free of credentials and real user data.
2. In Railway, create a MySQL service and deploy the GitHub repository as a service. Railway will build the included `Dockerfile`.
3. Add a Railway Volume to the PHP service mounted at `/data`. Profile photos, organization files, and leadership photos are stored there so they survive redeploys.
4. In the PHP service variables, set `APP_ENV=production`, `APP_DEBUG=0`, and `APP_URL` to the public HTTPS URL. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` using the matching private variables from the Railway MySQL service.
5. After the database service is ready, run `php bin/init_db.php` once in the PHP service environment to create the tables. For a database that already has data, apply only the outstanding migrations in `database/migrations/`.
6. Generate a Railway domain and open it over HTTPS. The `/healthz.php` endpoint checks that the web service can connect to MySQL.

Use the platform's private variable references for database credentials; never put them in GitHub or commit `.env`. The included Docker configuration listens on Railway's assigned port and raises PHP upload limits to support the app's 10 MB organization-file limit.

Before inviting students, add an approved privacy notice and retention policy, decide how enrollment is verified, and configure email verification and password recovery. This starter does not send email or recover forgotten passwords. Review university privacy requirements before collecting student data.

The reference HTML's sample events, officer names, contact details, and achievements are not presented as verified information. Events, files, and the rest of the original sections still need migration if wanted.
