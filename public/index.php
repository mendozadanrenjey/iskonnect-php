<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

const DEPARTMENTS = [
    'College of Information and Computing Sciences',
    'Business Administration and Entrepreneurship',
    'Teachers Education',
    'Industrial Technology',
    'Engineering',
];

const ORG_OFFICER_POSITIONS = [
    'President',
    'Vice President',
    'Secretary',
    'Treasurer',
    'Public Relations Officer',
];

const PROGRAMS_BY_DEPARTMENT = [
    'College of Information and Computing Sciences' => ['BS Information Technology'],
    'Business Administration and Entrepreneurship' => [
        'BS Business Administration',
        'BS Entrepreneurship',
        'BS Business Administration major in Financial Management',
        'BS Business Administration major in Marketing Management',
    ],
    'Teachers Education' => [
        'Bachelor of Elementary Education',
        'Bachelor of Secondary Education major in English minor in Mandarin',
        'Bachelor of Secondary Education major in Science',
        'Bachelor of Secondary Education major in Mathematics',
        'Bachelor of Secondary Education major in Social Studies',
        'Bachelor of Physical Education',
    ],
    'Industrial Technology' => [
        'Bachelor of Industrial Technology major in Computer Technology',
        'Bachelor of Industrial Technology major in Culinary and Food Processing Technology',
        'Bachelor of Industrial Technology major in Electrical Technology',
        'Bachelor of Industrial Technology major in Automotive Technology',
        'Bachelor of Industrial Technology major in Architectural and Drafting Technology',
    ],
    'Engineering' => ['BS Computer Engineering', 'BS Industrial Engineering'],
];

function department_organization_names(string $department): array {
    return match ($department) {
        'College of Information and Computing Sciences', 'College of Computing Sciences', 'Information Technology' => ['College of Information and Computing Sciences', 'College of Computing Sciences', 'Information Technology'],
        'Teachers Education', 'Teacher Education' => ['Teachers Education', 'Teacher Education'],
        default => [$department],
    };
}

function programs_for_registration(string $department, string $year): array {
    $programs = PROGRAMS_BY_DEPARTMENT[$department] ?? [];
    if ($department !== 'Business Administration and Entrepreneurship') return $programs;
    return array_values(array_filter($programs, static function (string $program) use ($year): bool {
        if ($program === 'BS Business Administration') return in_array($year, ['1st year', '2nd year'], true);
        if (str_starts_with($program, 'BS Business Administration major in ')) return $year === '3rd year';
        return true;
    }));
}

function account_first_name(array $account): string {
    $firstName = trim((string)($account['first_name'] ?? ''));
    if ($firstName !== '') return $firstName;
    $nameParts = preg_split('/\s+/', trim((string)($account['name'] ?? ''))) ?: [];
    return $nameParts[0] ?? '';
}

function valid_facebook_url(string $url): bool {
    if ($url === '' || strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || !in_array(strtolower((string)($parts['host'] ?? '')), ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'fb.me'], true)
        || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
        return false;
    }
    return true;
}

$user = current_user();
$pdo = null;
$accountPage = isset($_GET['page']) && $_GET['page'] === 'account';
$error = '';
$orgs = [];
$myApps = [];
$officerApps = [];
$announcements = [];
$memberAnnouncements = [];
$memberOrganizations = [];
$memberAnnouncementsByOrganization = [];
$memberFilesByOrganization = [];
$memberTicketsByOrganization = [];
$memberTicketMessages = [];
$memberOfficersByOrganization = [];
$officerOrganizations = [];
$officerFilesByOrganization = [];
$officerTicketsByOrganization = [];
$officerTicketMessages = [];
$officerProfilesByOrganization = [];
$publicOrgs = [];
$officerRequests = [];
$usedApplicationGroups = [];
$appliedOrganizationIds = [];
try {
    $pdo = db();
} catch (Throwable $e) {
    error_log('ISKOnnect database connection failed: ' . $e->getMessage());
    http_response_code(503);
    $error = 'The site could not connect to its database. Check the hosting database settings and PHP error log.';
}

if (isset($_GET['organization_officer_photo']) && $pdo) {
    $organizationId = trim((string)($_GET['organization_id'] ?? ''));
    $position = (string)($_GET['position'] ?? '');
    if (!$user || $organizationId === '' || !in_array($position, ORG_OFFICER_POSITIONS, true)) {
        http_response_code(404);
        exit('Photo not found.');
    }
    if ($user['role'] === 'student') {
        $photoQuery = $pdo->prepare('SELECT l.profile_photo,l.photo_mime_type FROM organization_leadership l JOIN organization_members m ON m.organization_id=l.organization_id AND m.student_id=? WHERE l.organization_id=? AND l.position=?');
    } elseif ($user['role'] === 'officer') {
        $photoQuery = $pdo->prepare('SELECT l.profile_photo,l.photo_mime_type FROM organization_leadership l JOIN officer_organizations oo ON oo.organization_id=l.organization_id AND oo.officer_id=? WHERE l.organization_id=? AND l.position=?');
    } else {
        http_response_code(404);
        exit('Photo not found.');
    }
    $photoQuery->execute([$user['id'], $organizationId, $position]);
    $photo = $photoQuery->fetch();
    $photoTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!$photo || !preg_match('/^[a-f0-9]{32}$/D', (string)$photo['profile_photo'])
        || !in_array($photo['photo_mime_type'], $photoTypes, true)) {
        http_response_code(404);
        exit('Photo not found.');
    }
    $storageDirectory = realpath(dirname(__DIR__) . '/storage/organization-officers');
    $photoPath = $storageDirectory === false ? false : realpath($storageDirectory . DIRECTORY_SEPARATOR . $photo['profile_photo']);
    if ($photoPath === false || !str_starts_with($photoPath, $storageDirectory . DIRECTORY_SEPARATOR) || !is_file($photoPath)) {
        http_response_code(404);
        exit('Photo not found.');
    }
    header('Content-Type: ' . $photo['photo_mime_type']);
    header('Content-Length: ' . (string)filesize($photoPath));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($photoPath);
    exit;
}

if (isset($_GET['organization_file']) && $pdo) {
    $fileId = filter_var($_GET['organization_file'], FILTER_VALIDATE_INT);
    if (!$user || !$fileId || $fileId < 1) {
        http_response_code(404);
        exit('File not found.');
    }
    if ($user['role'] === 'student') {
        $fileQuery = $pdo->prepare('SELECT f.original_name,f.stored_name,f.mime_type,f.file_size FROM organization_files f JOIN organization_members m ON m.organization_id=f.organization_id AND m.student_id=? WHERE f.id=?');
    } elseif ($user['role'] === 'officer') {
        $fileQuery = $pdo->prepare('SELECT f.original_name,f.stored_name,f.mime_type,f.file_size FROM organization_files f JOIN officer_organizations oo ON oo.organization_id=f.organization_id AND oo.officer_id=? WHERE f.id=?');
    } else {
        http_response_code(404);
        exit('File not found.');
    }
    $fileQuery->execute([$user['id'], $fileId]);
    $file = $fileQuery->fetch();
    if (!$file || !preg_match('/^[a-f0-9]{32}$/D', $file['stored_name'])) {
        http_response_code(404);
        exit('File not found.');
    }
    $storageDirectory = realpath(dirname(__DIR__) . '/storage/organization-files');
    $filePath = $storageDirectory === false ? false : realpath($storageDirectory . DIRECTORY_SEPARATOR . $file['stored_name']);
    if ($filePath === false || !str_starts_with($filePath, $storageDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
        http_response_code(404);
        exit('File not found.');
    }
    $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)$file['original_name']) ?: 'organization-file';
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . (string)filesize($filePath));
    header('Content-Disposition: attachment; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode((string)$file['original_name']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($filePath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'register') {
            if (($_POST['application_terms'] ?? '') !== '1' || ($_POST['privacy_consent'] ?? '') !== '1') {
                throw new RuntimeException('Both checkboxes are required: confirm your details and agree to the Data Privacy Act privacy notice.');
            }
            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $surname = trim((string)($_POST['surname'] ?? ''));
            $name = trim($firstName . ' ' . $surname);
            $sid = trim((string)($_POST['student_number'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $course = trim((string)($_POST['course'] ?? ''));
            $department = trim((string)($_POST['department'] ?? ''));
            $year = trim((string)($_POST['year_level'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if ($firstName === '' || $surname === '' || strlen($firstName) > 80 || strlen($surname) > 80 || strlen($name) > 160
                || !preg_match('/^\d{10}$/D', $sid) || $course === '' || $year === ''
                || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || !in_array($department, DEPARTMENTS, true)
                || !in_array($year, ['1st year','2nd year','3rd year','4th year','5th year+'], true)
                || !in_array($course, programs_for_registration($department, $year), true)
                || strlen($password) < 8) {
                throw new RuntimeException('Enter your 10-digit student number, complete all fields, choose your department, use a valid email, and enter a password of at least 8 characters.');
            }
            $q = $pdo->prepare('SELECT id FROM officer_registration_requests WHERE student_number=?');
            $q->execute([$sid]);
            if ($q->fetch()) throw new RuntimeException('That student number already has an officer registration request.');
            $q = $pdo->prepare("INSERT INTO users(name,first_name,surname,student_number,email,password_hash,role,course,department,year_level) VALUES(?,?,?,?,?,?,'student',?,?,?)");
            $q->execute([$name, $firstName, $surname, $sid, $email, password_hash($password, PASSWORD_DEFAULT), $course, $department, $year]);
            login_user(['id'=>(int)$pdo->lastInsertId(),'name'=>$name,'first_name'=>$firstName,'email'=>$email,'role'=>'student','department'=>$department,'course'=>$course,'year_level'=>$year]);
            flash('success', 'Your account is ready. You can now register with an organization.');
            redirect();
        }
        if ($action === 'login') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $q = $pdo->prepare('SELECT * FROM users WHERE email=?');
            $q->execute([$email]);
            $account = $q->fetch();
            if (!$account || !password_verify((string)($_POST['password'] ?? ''), $account['password_hash'])) {
                throw new RuntimeException('Email or password was not recognized.');
            }
            login_user($account);
            redirect();
        }
        if ($action === 'update_profile') {
            $account = require_role((string)(current_user()['role'] ?? ''));
            $bio = trim((string)($_POST['bio'] ?? ''));
            $facebookUrl = trim((string)($_POST['facebook_url'] ?? ''));
            preg_match_all('/./us', $bio, $bioCharacters);
            if (count($bioCharacters[0]) > 500) throw new RuntimeException('Your bio must be 500 characters or fewer.');
            if ($facebookUrl !== '' && !valid_facebook_url($facebookUrl)) {
                throw new RuntimeException('Enter a valid HTTPS link to a Facebook profile or page.');
            }
            if ($facebookUrl === '') $facebookUrl = null;

            $profilePhoto = null;
            $uploadedPhoto = $_FILES['profile_photo'] ?? null;
            if (is_array($uploadedPhoto) && $uploadedPhoto['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($uploadedPhoto['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('The photo upload failed. Choose an image smaller than 3 MB and try again.');
                }
                if ($uploadedPhoto['size'] > 3 * 1024 * 1024) {
                    throw new RuntimeException('Profile photos must be 3 MB or smaller.');
                }
                $imageInfo = getimagesize($uploadedPhoto['tmp_name']);
                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $fileInfo->file($uploadedPhoto['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!$imageInfo || !isset($extensions[$mime]) || $imageInfo['mime'] !== $mime
                    || $imageInfo[0] > 5000 || $imageInfo[1] > 5000) {
                    throw new RuntimeException('Upload a valid JPG, PNG, or WebP image no larger than 5000 × 5000 pixels.');
                }

                $uploadDirectory = __DIR__ . '/uploads/profiles';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Profile photo storage is unavailable. Please try again later.');
                }
                $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                if (!move_uploaded_file($uploadedPhoto['tmp_name'], $uploadDirectory . '/' . $filename)) {
                    throw new RuntimeException('Could not save your profile photo. Please try again.');
                }
                $profilePhoto = '/uploads/profiles/' . $filename;
            }

            $oldPhoto = '';
            if ($profilePhoto !== null) {
                $photoQuery = $pdo->prepare('SELECT profile_photo FROM users WHERE id=?');
                $photoQuery->execute([$account['id']]);
                $oldPhoto = (string)($photoQuery->fetchColumn() ?: '');
            }

            try {
                if ($profilePhoto !== null) {
                    $pdo->prepare('UPDATE users SET bio=?,facebook_url=?,profile_photo=? WHERE id=?')
                        ->execute([$bio, $facebookUrl, $profilePhoto, $account['id']]);
                    if (preg_match('~^/uploads/profiles/[a-f0-9]{32}\.(?:jpg|png|webp)$~', $oldPhoto)) {
                        $oldPath = __DIR__ . $oldPhoto;
                        if (is_file($oldPath)) unlink($oldPath);
                    }
                } else {
                    $pdo->prepare('UPDATE users SET bio=?,facebook_url=? WHERE id=?')
                        ->execute([$bio, $facebookUrl, $account['id']]);
                }
            } catch (Throwable $e) {
                if ($profilePhoto !== null && is_file(__DIR__ . $profilePhoto)) unlink(__DIR__ . $profilePhoto);
                throw $e;
            }
            flash('success', 'Your profile has been updated.');
            header('Location: /');
            exit;
        }
        if ($action === 'officer_register') {
            if (($_POST['application_terms'] ?? '') !== '1' || ($_POST['privacy_consent'] ?? '') !== '1') {
                throw new RuntimeException('Both checkboxes are required: confirm your details and agree to the Data Privacy Act privacy notice.');
            }
            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $surname = trim((string)($_POST['surname'] ?? ''));
            $name = trim($firstName . ' ' . $surname);
            $sid = trim((string)($_POST['student_number'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $organizationId = trim((string)($_POST['organization_id'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if ($firstName === '' || $surname === '' || strlen($firstName) > 80 || strlen($surname) > 80 || strlen($name) > 160
                || !preg_match('/^\d{10}$/D', $sid) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $organizationId === '' || strlen($password) < 8) {
                throw new RuntimeException('Enter your 10-digit student number, complete all fields, choose an organization, use a valid email, and enter a password of at least 8 characters.');
            }
            $q = $pdo->prepare('SELECT id FROM organizations WHERE id=?');
            $q->execute([$organizationId]);
            if (!$q->fetch()) throw new RuntimeException('Choose an organization from the list.');
            $q = $pdo->prepare('SELECT id FROM users WHERE email=? UNION SELECT id FROM officer_registration_requests WHERE email=?');
            $q->execute([$email, $email]);
            if ($q->fetch()) throw new RuntimeException('That email already has an account or officer registration request.');
            $q = $pdo->prepare('SELECT student_number FROM users WHERE student_number=? UNION SELECT student_number FROM officer_registration_requests WHERE student_number=?');
            $q->execute([$sid, $sid]);
            if ($q->fetch()) throw new RuntimeException('That student number already has an account or officer registration request.');
            $q = $pdo->prepare("INSERT INTO officer_registration_requests(name,first_name,surname,student_number,email,password_hash,organization_id) VALUES(?,?,?,?,?,?,?)");
            $q->execute([$name, $firstName, $surname, $sid, $email, password_hash($password, PASSWORD_DEFAULT), $organizationId]);
            flash('success', 'Officer registration submitted. The local student council must approve it before the account can sign in.');
            redirect();
        }
        if ($action === 'publish_announcement') {
            $council = require_role('council');
            $title = trim((string)($_POST['title'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            if ($title === '' || strlen($title) > 180 || $body === '' || strlen($body) > 10000) {
                throw new RuntimeException('Enter an announcement title (up to 180 characters) and message (up to 10,000 characters).');
            }
            $pdo->prepare('INSERT INTO announcements(title,body,author_id) VALUES(?,?,?)')
                ->execute([$title, $body, $council['id']]);
            flash('success', 'Announcement published to the public hub.');
            redirect();
        }
        if ($action === 'update_org_about') {
            $officer = require_role('officer');
            $organizationId = trim((string)($_POST['organization_id'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            if ($description === '' || strlen($description) > 5000) {
                throw new RuntimeException('Enter an organization About description of up to 5,000 characters.');
            }
            $organizationQuery = $pdo->prepare('SELECT o.id FROM organizations o JOIN officer_organizations oo ON oo.organization_id=o.id WHERE o.id=? AND oo.officer_id=?');
            $organizationQuery->execute([$organizationId, $officer['id']]);
            if (!$organizationQuery->fetch()) throw new RuntimeException('You can only edit organizations assigned to your officer account.');
            $pdo->prepare('UPDATE organizations SET description=? WHERE id=?')->execute([$description, $organizationId]);
            flash('success', 'Organization information updated.');
            redirect();
        }
        if ($action === 'update_org_officer_profile') {
            $officer = require_role('officer');
            $organizationId = trim((string)($_POST['organization_id'] ?? ''));
            $position = (string)($_POST['position'] ?? '');
            $name = trim((string)($_POST['name'] ?? ''));
            $profileText = trim((string)($_POST['profile'] ?? ''));
            $contactEmail = strtolower(trim((string)($_POST['contact_email'] ?? '')));
            $facebookUrl = trim((string)($_POST['facebook_url'] ?? ''));
            if (!in_array($position, ORG_OFFICER_POSITIONS, true)) {
                throw new RuntimeException('Choose a valid organization officer position.');
            }
            if (strlen($name) > 160 || strlen($profileText) > 1000
                || ($contactEmail !== '' && (strlen($contactEmail) > 254 || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)))) {
                throw new RuntimeException('Check the name, profile (up to 1,000 characters), and contact email.');
            }
            if ($facebookUrl !== '' && !valid_facebook_url($facebookUrl)) {
                throw new RuntimeException('Enter a valid HTTPS link to a Facebook profile or page.');
            }
            $organizationQuery = $pdo->prepare('SELECT o.id FROM organizations o JOIN officer_organizations oo ON oo.organization_id=o.id WHERE o.id=? AND oo.officer_id=?');
            $organizationQuery->execute([$organizationId, $officer['id']]);
            if (!$organizationQuery->fetch()) throw new RuntimeException('You can only edit officer profiles for organizations assigned to your account.');

            $oldProfileQuery = $pdo->prepare('SELECT profile_photo FROM organization_leadership WHERE organization_id=? AND position=?');
            $oldProfileQuery->execute([$organizationId, $position]);
            $oldPhoto = (string)($oldProfileQuery->fetchColumn() ?: '');
            $photoName = $oldPhoto !== '' ? $oldPhoto : null;
            $photoMimeType = null;
            $removePhoto = ($_POST['remove_photo'] ?? '') === '1';
            $upload = $_FILES['profile_photo'] ?? null;
            $newPhotoPath = null;
            if (is_array($upload) && isset($upload['error']) && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
                    throw new RuntimeException('The profile photo exceeds the PHP upload limit.');
                }
                if ($upload['error'] !== UPLOAD_ERR_OK || !isset($upload['tmp_name'], $upload['size'])
                    || !is_string($upload['tmp_name']) || !is_int($upload['size']) || !is_uploaded_file($upload['tmp_name'])) {
                    throw new RuntimeException('The profile photo upload failed. Choose an image smaller than 3 MB and try again.');
                }
                if ($upload['size'] < 1 || $upload['size'] > 3 * 1024 * 1024) {
                    throw new RuntimeException('Officer profile photos must be 3 MB or smaller.');
                }
                $imageInfo = getimagesize($upload['tmp_name']);
                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $fileInfo->file($upload['tmp_name']);
                $imageExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!$imageInfo || !isset($imageExtensions[$mime]) || $imageInfo['mime'] !== $mime
                    || $imageInfo[0] > 5000 || $imageInfo[1] > 5000) {
                    throw new RuntimeException('Upload a valid JPG, PNG, or WebP image no larger than 5000 × 5000 pixels.');
                }
                $storageDirectory = dirname(__DIR__) . '/storage/organization-officers';
                if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
                    throw new RuntimeException('Officer photo storage is unavailable. Please try again later.');
                }
                $photoName = bin2hex(random_bytes(16));
                $newPhotoPath = $storageDirectory . DIRECTORY_SEPARATOR . $photoName;
                if (!move_uploaded_file($upload['tmp_name'], $newPhotoPath)) {
                    throw new RuntimeException('Could not save the officer profile photo. Please try again.');
                }
                $photoMimeType = $mime;
                $removePhoto = false;
            } elseif ($removePhoto) {
                $photoName = null;
            } elseif ($oldPhoto !== '') {
                $oldMimeQuery = $pdo->prepare('SELECT photo_mime_type FROM organization_leadership WHERE organization_id=? AND position=?');
                $oldMimeQuery->execute([$organizationId, $position]);
                $photoMimeType = $oldMimeQuery->fetchColumn() ?: null;
            }

            try {
                $pdo->prepare('INSERT INTO organization_leadership(organization_id,position,name,profile,contact_email,facebook_url,profile_photo,photo_mime_type) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),profile=VALUES(profile),contact_email=VALUES(contact_email),facebook_url=VALUES(facebook_url),profile_photo=VALUES(profile_photo),photo_mime_type=VALUES(photo_mime_type)')
                    ->execute([$organizationId, $position, $name, $profileText ?: null, $contactEmail ?: null, $facebookUrl ?: null, $photoName, $photoMimeType]);
            } catch (Throwable $e) {
                if ($newPhotoPath !== null && is_file($newPhotoPath)) unlink($newPhotoPath);
                throw $e;
            }
            if (($removePhoto || $newPhotoPath !== null) && preg_match('/^[a-f0-9]{32}$/D', $oldPhoto)) {
                $oldPath = dirname(__DIR__) . '/storage/organization-officers/' . $oldPhoto;
                if (is_file($oldPath)) unlink($oldPath);
            }
            flash('success', $position . ' profile updated for the organization.');
            redirect();
        }
        if ($action === 'publish_org_announcement') {
            $officer = require_role('officer');
            $organizationId = trim((string)($_POST['organization_id'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            if ($title === '' || strlen($title) > 180 || $body === '' || strlen($body) > 10000) {
                throw new RuntimeException('Enter an announcement title (up to 180 characters) and message (up to 10,000 characters).');
            }
            $q = $pdo->prepare('SELECT o.id FROM organizations o JOIN officer_organizations oo ON oo.organization_id=o.id WHERE o.id=? AND oo.officer_id=?');
            $q->execute([$organizationId, $officer['id']]);
            if (!$q->fetch()) throw new RuntimeException('You can only publish announcements for your assigned organizations.');
            $pdo->prepare('INSERT INTO announcements(title,body,author_id,organization_id) VALUES(?,?,?,?)')
                ->execute([$title, $body, $officer['id'], $organizationId]);
            flash('success', 'Organization announcement published to admitted members.');
            redirect();
        }
        if ($action === 'upload_org_file') {
            $officer = require_role('officer');
            $organizationId = trim((string)($_POST['organization_id'] ?? ''));
            $organizationQuery = $pdo->prepare('SELECT o.id FROM organizations o JOIN officer_organizations oo ON oo.organization_id=o.id WHERE o.id=? AND oo.officer_id=?');
            $organizationQuery->execute([$organizationId, $officer['id']]);
            if (!$organizationQuery->fetch()) throw new RuntimeException('You can only upload files for organizations assigned to your officer account.');

            $upload = $_FILES['organization_file'] ?? null;
            if (!is_array($upload) || !isset($upload['error'], $upload['name'], $upload['size'], $upload['tmp_name'])
                || !is_int($upload['error']) || !is_string($upload['name']) || !is_int($upload['size'])
                || !is_string($upload['tmp_name']) || $upload['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Choose a file to upload.');
            }
            if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
                throw new RuntimeException('The file exceeds the PHP upload limit. Choose a smaller file or increase the server upload limit.');
            }
            if ($upload['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('The file upload failed. Please try again.');
            }
            if ($upload['size'] < 1 || $upload['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('Organization files must be between 1 byte and 10 MB.');
            }
            if (!is_uploaded_file($upload['tmp_name'])) {
                throw new RuntimeException('The uploaded file could not be verified. Please choose it again.');
            }
            $allowedFiles = [
                'pdf' => ['application/pdf'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
                'txt' => ['text/plain'],
                'png' => ['image/png'],
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'webp' => ['image/webp'],
            ];
            $originalName = basename(str_replace('\\', '/', (string)$upload['name']));
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($upload['tmp_name']);
            if (!isset($allowedFiles[$extension]) || !in_array($mimeType, $allowedFiles[$extension], true)) {
                throw new RuntimeException('Upload a PDF, DOCX, XLSX, PPTX, TXT, PNG, JPG, or WebP file.');
            }
            $originalName = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?: '');
            if (preg_match('/^.{0,255}/us', $originalName, $nameMatch)) {
                $originalName = $nameMatch[0];
            }
            if ($originalName === '') throw new RuntimeException('The uploaded file must have a valid name.');

            $storageDirectory = dirname(__DIR__) . '/storage/organization-files';
            if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
                throw new RuntimeException('Organization file storage is unavailable. Please try again later.');
            }
            $storedName = bin2hex(random_bytes(16));
            $filePath = $storageDirectory . DIRECTORY_SEPARATOR . $storedName;
            if (!move_uploaded_file($upload['tmp_name'], $filePath)) {
                throw new RuntimeException('Could not save the organization file. Please try again.');
            }
            try {
                $pdo->prepare('INSERT INTO organization_files(organization_id,uploader_id,original_name,stored_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')
                    ->execute([$organizationId, $officer['id'], $originalName, $storedName, $mimeType, $upload['size']]);
            } catch (Throwable $e) {
                if (is_file($filePath)) unlink($filePath);
                throw $e;
            }
            flash('success', 'File uploaded for organization members.');
            redirect();
        }
        if ($action === 'submit_org_ticket') {
            $student = require_role('student');
            $organizationId = trim((string)($_POST['organization_id'] ?? ''));
            $subject = trim((string)($_POST['subject'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            if ($subject === '' || strlen($subject) > 180 || $message === '' || strlen($message) > 5000) {
                throw new RuntimeException('Enter a ticket subject (up to 180 characters) and message (up to 5,000 characters).');
            }
            $pdo->beginTransaction();
            $membershipQuery = $pdo->prepare('SELECT organization_id FROM organization_members WHERE organization_id=? AND student_id=? FOR UPDATE');
            $membershipQuery->execute([$organizationId, $student['id']]);
            if (!$membershipQuery->fetch()) throw new RuntimeException('You can submit a ticket only to an organization you belong to.');
            $pdo->prepare('INSERT INTO organization_tickets(organization_id,student_id,subject) VALUES(?,?,?)')
                ->execute([$organizationId, $student['id'], $subject]);
            $ticketId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO organization_ticket_messages(ticket_id,author_id,body) VALUES(?,?,?)')
                ->execute([$ticketId, $student['id'], $message]);
            $pdo->commit();
            flash('success', 'Your ticket was sent to the organization officers.');
            redirect();
        }
        if ($action === 'reply_org_ticket') {
            $officer = require_role('officer');
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $status = (string)($_POST['status'] ?? '');
            $message = trim((string)($_POST['message'] ?? ''));
            if (!in_array($status, ['open', 'in_progress', 'resolved'], true) || $message === '' || strlen($message) > 5000) {
                throw new RuntimeException('Enter a reply of up to 5,000 characters and choose a valid ticket status.');
            }
            $pdo->beginTransaction();
            $ticketQuery = $pdo->prepare('SELECT t.id FROM organization_tickets t JOIN officer_organizations oo ON oo.organization_id=t.organization_id AND oo.officer_id=? WHERE t.id=? FOR UPDATE');
            $ticketQuery->execute([$officer['id'], $ticketId]);
            if (!$ticketQuery->fetch()) throw new RuntimeException('That ticket is outside your organization access.');
            $pdo->prepare('INSERT INTO organization_ticket_messages(ticket_id,author_id,body) VALUES(?,?,?)')
                ->execute([$ticketId, $officer['id'], $message]);
            $pdo->prepare('UPDATE organization_tickets SET status=? WHERE id=?')->execute([$status, $ticketId]);
            $pdo->commit();
            flash('success', 'Ticket reply sent and status updated.');
            redirect();
        }
        if ($action === 'delete_announcement') {
            $council = require_role('council');
            $pdo->prepare('DELETE FROM announcements WHERE id=? AND organization_id IS NULL')->execute([(int)($_POST['announcement_id'] ?? 0)]);
            flash('success', 'Announcement removed from the public hub.');
            redirect();
        }
        if ($action === 'review_officer_request') {
            $council = require_role('council');
            $requestId = (int)($_POST['request_id'] ?? 0);
            $decision = (string)($_POST['decision'] ?? '');
            if (!in_array($decision, ['approved', 'rejected'], true)) throw new RuntimeException('Choose approve or reject.');
            $q = $pdo->prepare("SELECT * FROM officer_registration_requests WHERE id=? AND status='pending'");
            $q->execute([$requestId]);
            $request = $q->fetch();
            if (!$request) throw new RuntimeException('That officer request is no longer pending.');
            $pdo->beginTransaction();
            if ($decision === 'approved') {
                $q = $pdo->prepare('SELECT id FROM users WHERE email=?');
                $q->execute([$request['email']]);
                if ($q->fetch()) throw new RuntimeException('That email now belongs to an account; the request cannot be approved.');
                $q = $pdo->prepare('SELECT id FROM users WHERE student_number=?');
                $q->execute([$request['student_number']]);
                if ($q->fetch()) throw new RuntimeException('That student number now belongs to an account; the request cannot be approved.');
                $pdo->prepare("INSERT INTO users(name,first_name,surname,student_number,email,password_hash,role) VALUES(?,?,?,?,?,?,'officer')")
                    ->execute([$request['name'], $request['first_name'], $request['surname'], $request['student_number'], $request['email'], $request['password_hash']]);
                $officerId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO officer_organizations(officer_id,organization_id) VALUES(?,?)')
                    ->execute([$officerId, $request['organization_id']]);
            }
            $pdo->prepare('UPDATE officer_registration_requests SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?')
                ->execute([$decision, $council['id'], $requestId]);
            $pdo->commit();
            flash('success', 'Officer registration ' . $decision . '.');
            redirect();
        }
        if ($action === 'logout') {
            if (!current_user()) { http_response_code(403); exit('Sign in first.'); }
            $_SESSION = [];
            session_regenerate_id(true);
            session_destroy();
            header('Location: /');
            exit;
        }
        if ($action === 'set_department') {
            $student = require_role('student');
            $department = trim((string)($_POST['department'] ?? ''));
            if (!in_array($department, DEPARTMENTS, true)) throw new RuntimeException('Choose your department from the list.');
            $pdo->prepare("UPDATE users SET department=? WHERE id=?")->execute([$department, $student['id']]);
            $_SESSION['user']['department'] = $department;
            flash('success', 'Your department is saved. You can now register with an organization.');
            redirect();
        }
        if ($action === 'set_student_profile') {
            $student = require_role('student');
            $department = trim((string)($_POST['department'] ?? ''));
            $course = trim((string)($_POST['course'] ?? ''));
            $year = trim((string)($_POST['year_level'] ?? ''));
            if (!in_array($department, DEPARTMENTS, true)
                || !in_array($year, ['1st year','2nd year','3rd year','4th year','5th year+'], true)
                || !in_array($course, programs_for_registration($department, $year), true)) {
                throw new RuntimeException('Choose a valid department, program, and year level.');
            }
            $pdo->prepare('UPDATE users SET department=?,course=?,year_level=? WHERE id=?')
                ->execute([$department,$course,$year,$student['id']]);
            $_SESSION['user']['department'] = $department;
            $_SESSION['user']['course'] = $course;
            $_SESSION['user']['year_level'] = $year;
            flash('success', 'Your student profile is saved. You can now apply to eligible organizations.');
            redirect();
        }
        if ($action === 'apply') {
            $student = require_role('student');
            $orgId = (string)($_POST['organization_id'] ?? '');
            $motivation = trim((string)($_POST['motivation'] ?? ''));
            $role = trim((string)($_POST['preferred_role'] ?? ''));
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT course,year_level,department FROM users WHERE id=? FOR UPDATE');
            $q->execute([$student['id']]);
            $studentProfile = $q->fetch();
            if (!$studentProfile || empty($studentProfile['department']) || empty($studentProfile['course']) || empty($studentProfile['year_level'])) {
                throw new RuntimeException('Complete your department, registered program, and year level before applying.');
            }
            if ($motivation === '') throw new RuntimeException('Tell us why you would like to join.');
            $organizationQuery = $pdo->prepare('SELECT id,application_group FROM organizations WHERE id=?');
            $organizationQuery->execute([$orgId]);
            $organization = $organizationQuery->fetch();
            $departmentNames = department_organization_names((string)$studentProfile['department']);
            $eligible = false;
            if ($organization && $organization['application_group'] === 'overall') {
                $eligible = true;
            } elseif ($organization && $organization['application_group'] === 'program') {
                $departmentPlaceholders = implode(',', array_fill(0, count($departmentNames), '?'));
                $q = $pdo->prepare("SELECT o.id FROM organizations o WHERE o.id=? AND o.department IN ($departmentPlaceholders) AND (NOT EXISTS (SELECT 1 FROM organization_programs op WHERE op.organization_id=o.id) OR EXISTS (SELECT 1 FROM organization_programs op WHERE op.organization_id=o.id AND op.program_name=?))");
                $q->execute(array_merge([$orgId], $departmentNames, [(string)$studentProfile['course']]));
                $eligible = (bool)$q->fetch();
            }
            if (!$eligible) throw new RuntimeException('That organization is not available to your department and program.');
            $q = $pdo->prepare('SELECT id FROM applications WHERE student_id=? AND organization_id=?');
            $q->execute([$student['id'], $orgId]);
            if ($q->fetch()) throw new RuntimeException('You already have an application for that organization.');
            $q = $pdo->prepare("SELECT a.id FROM applications a JOIN organizations o ON o.id=a.organization_id WHERE a.student_id=? AND o.application_group=? AND a.status IN ('pending','approved') FOR UPDATE");
            $q->execute([$student['id'], $organization['application_group']]);
            if ($q->fetch()) {
                $category = $organization['application_group'] === 'overall' ? 'overall' : 'program';
                throw new RuntimeException("You can have only one pending or accepted {$category} organization application at a time.");
            }
            $pdo->prepare('INSERT INTO applications(student_id,organization_id,preferred_role,motivation) VALUES(?,?,?,?)')
                ->execute([$student['id'],$orgId,$role,$motivation]);
            $pdo->commit();
            flash('success', 'Application submitted. Its status will appear in your account after officer review.');
            redirect();
        }
        if ($action === 'review') {
            $officer = require_role('officer');
            $appId = (int)($_POST['application_id'] ?? 0);
            $status = (string)($_POST['status'] ?? '');
            $note = trim((string)($_POST['officer_note'] ?? ''));
            if (!in_array($status, ['pending','approved','rejected'], true)) throw new RuntimeException('Choose a valid application status.');
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT a.id,a.student_id,a.organization_id FROM applications a JOIN officer_organizations oo ON oo.organization_id=a.organization_id AND oo.officer_id=? WHERE a.id=?');
            $q->execute([$officer['id'],$appId]);
            $app = $q->fetch();
            if (!$app) throw new RuntimeException('That application is outside your organization access.');
            $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE')->execute([$app['student_id']]);
            $q = $pdo->prepare('SELECT a.id,a.student_id,a.organization_id,a.status FROM applications a JOIN officer_organizations oo ON oo.organization_id=a.organization_id AND oo.officer_id=? WHERE a.id=? FOR UPDATE');
            $q->execute([$officer['id'],$appId]);
            $app = $q->fetch();
            if (!$app) throw new RuntimeException('That application is outside your organization access.');
            $q = $pdo->prepare('SELECT application_group FROM organizations WHERE id=?');
            $q->execute([$app['organization_id']]);
            $applicationGroup = $q->fetchColumn();
            if (in_array($status, ['pending', 'approved'], true) && in_array($applicationGroup, ['program', 'overall'], true)) {
                $q = $pdo->prepare("SELECT a.id FROM applications a JOIN organizations o ON o.id=a.organization_id WHERE a.student_id=? AND o.application_group=? AND a.status IN ('pending','approved') AND a.id<>? FOR UPDATE");
                $q->execute([$app['student_id'], $applicationGroup, $appId]);
                if ($q->fetch()) throw new RuntimeException('The student already has a pending or accepted application in this organization group.');
            }
            $updated = $pdo->prepare('UPDATE applications SET status=?,officer_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?');
            $updated->execute([$status,$note?:null,$officer['id'],$appId]);
            if ($status === 'approved') {
                $pdo->prepare('INSERT INTO organization_members(organization_id,student_id,application_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE application_id=VALUES(application_id)')
                    ->execute([$app['organization_id'],$app['student_id'],$appId]);
            } else {
                $pdo->prepare('DELETE FROM organization_members WHERE organization_id=? AND student_id=?')
                    ->execute([$app['organization_id'],$app['student_id']]);
            }
            $pdo->commit();
            flash('success', 'Application updated to ' . ['pending'=>'Awaiting review','approved'=>'Accepted','rejected'=>'Rejected'][$status] . '.');
            redirect();
        }
        if ($action === 'delete_application') {
            $officer = require_role('officer');
            $appId = (int)($_POST['application_id'] ?? 0);
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT a.id FROM applications a JOIN officer_organizations oo ON oo.organization_id=a.organization_id AND oo.officer_id=? WHERE a.id=? FOR UPDATE');
            $q->execute([$officer['id'], $appId]);
            if (!$q->fetch()) throw new RuntimeException('That application is outside your organization access or has already been removed.');
            $pdo->prepare('DELETE FROM applications WHERE id=?')->execute([$appId]);
            $pdo->commit();
            flash('success', 'Application deleted.');
            redirect();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getCode() === '23000'
            ? 'That email, student number, or organization application is already registered.'
            : (getenv('APP_DEBUG') === '1' ? $e->getMessage() : 'Could not save that request. Please try again.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$user = current_user();
$profile = null;
if ($pdo && $user) {
    $profileQuery = $pdo->prepare('SELECT bio,profile_photo,facebook_url FROM users WHERE id=?');
    $profileQuery->execute([$user['id']]);
    $profile = $profileQuery->fetch() ?: ['bio' => '', 'profile_photo' => null, 'facebook_url' => null];
}
$publicOrgs = $pdo ? $pdo->query("SELECT id,name,category,department,description FROM organizations WHERE application_group IN ('program','overall') ORDER BY name")->fetchAll() : [];
if ($pdo) {
    $announcements = $pdo->query("SELECT a.id,a.title,a.body,a.created_at,u.name author_name FROM announcements a JOIN users u ON u.id=a.author_id WHERE a.organization_id IS NULL AND u.role='council' ORDER BY a.created_at DESC,a.id DESC")->fetchAll();
}
if ($pdo && $user && !$accountPage && $user['role'] === 'student') {
    $q = $pdo->prepare('SELECT a.*,o.name organization_name,o.application_group FROM applications a JOIN organizations o ON o.id=a.organization_id WHERE a.student_id=? ORDER BY a.created_at DESC');
    $q->execute([$user['id']]);
    $myApps = $q->fetchAll();
    foreach ($myApps as $application) {
        $appliedOrganizationIds[] = $application['organization_id'];
        if (in_array($application['status'], ['pending', 'approved'], true) && in_array($application['application_group'], ['program', 'overall'], true)) {
            $usedApplicationGroups[] = $application['application_group'];
        }
    }
    $usedApplicationGroups = array_unique($usedApplicationGroups);
    if (!empty($user['department']) && !empty($user['course'])) {
        $departmentNames = department_organization_names((string)$user['department']);
        $departmentPlaceholders = implode(',', array_fill(0, count($departmentNames), '?'));
        $q = $pdo->prepare("SELECT o.id,o.name,o.category,o.department,o.description,o.application_group FROM organizations o WHERE o.application_group='overall' OR (o.application_group='program' AND o.department IN ($departmentPlaceholders) AND (NOT EXISTS (SELECT 1 FROM organization_programs op WHERE op.organization_id=o.id) OR EXISTS (SELECT 1 FROM organization_programs op WHERE op.organization_id=o.id AND op.program_name=?))) ORDER BY o.application_group,o.name");
        $q->execute(array_merge($departmentNames, [(string)$user['course']]));
        foreach ($q->fetchAll() as $organization) {
            if (!in_array($organization['application_group'], $usedApplicationGroups, true)
                && !in_array($organization['id'], $appliedOrganizationIds, true)) {
                $orgs[] = $organization;
            }
        }
    }
    $q = $pdo->prepare('SELECT o.id,o.name,o.description FROM organizations o JOIN organization_members m ON m.organization_id=o.id WHERE m.student_id=? ORDER BY o.name');
    $q->execute([$user['id']]);
    $memberOrganizations = $q->fetchAll();
    foreach ($memberOrganizations as $organization) {
        $memberAnnouncementsByOrganization[$organization['id']] = [];
        $memberFilesByOrganization[$organization['id']] = [];
        $memberTicketsByOrganization[$organization['id']] = [];
        $memberOfficersByOrganization[$organization['id']] = [];
    }
    $q = $pdo->prepare('SELECT l.organization_id,l.position,l.name,l.profile,l.contact_email,l.facebook_url,l.profile_photo FROM organization_leadership l JOIN organization_members m ON m.organization_id=l.organization_id AND m.student_id=? ORDER BY l.organization_id,FIELD(l.position,\'President\',\'Vice President\',\'Secretary\',\'Treasurer\',\'Public Relations Officer\')');
    $q->execute([$user['id']]);
    foreach ($q->fetchAll() as $organizationOfficer) {
        $memberOfficersByOrganization[$organizationOfficer['organization_id']][$organizationOfficer['position']] = $organizationOfficer;
    }
    $q = $pdo->prepare('SELECT a.id,a.title,a.body,a.created_at,u.name author_name,o.id organization_id,o.name organization_name FROM announcements a JOIN organization_members m ON m.organization_id=a.organization_id AND m.student_id=? JOIN organizations o ON o.id=a.organization_id JOIN users u ON u.id=a.author_id AND u.role=\'officer\' ORDER BY o.name,a.created_at DESC,a.id DESC');
    $q->execute([$user['id']]);
    $memberAnnouncements = $q->fetchAll();
    foreach ($memberAnnouncements as $announcement) {
        $memberAnnouncementsByOrganization[$announcement['organization_id']][] = $announcement;
    }
    $q = $pdo->prepare('SELECT f.id,f.organization_id,f.original_name,f.file_size,f.created_at,u.name uploader_name FROM organization_files f JOIN organization_members m ON m.organization_id=f.organization_id AND m.student_id=? JOIN users u ON u.id=f.uploader_id ORDER BY f.created_at DESC');
    $q->execute([$user['id']]);
    foreach ($q->fetchAll() as $file) {
        $memberFilesByOrganization[$file['organization_id']][] = $file;
    }
    $q = $pdo->prepare('SELECT t.id,t.organization_id,t.subject,t.status,t.created_at,t.updated_at,o.name organization_name FROM organization_tickets t JOIN organization_members m ON m.organization_id=t.organization_id AND m.student_id=? JOIN organizations o ON o.id=t.organization_id WHERE t.student_id=? ORDER BY t.updated_at DESC,t.id DESC');
    $q->execute([$user['id'], $user['id']]);
    $memberTickets = $q->fetchAll();
    $memberTicketIds = [];
    foreach ($memberTickets as $ticket) {
        $memberTicketsByOrganization[$ticket['organization_id']][] = $ticket;
        $memberTicketIds[] = (int)$ticket['id'];
    }
    if ($memberTicketIds) {
        $ticketPlaceholders = implode(',', array_fill(0, count($memberTicketIds), '?'));
        $q = $pdo->prepare("SELECT m.ticket_id,m.author_id,m.body,m.created_at,u.name author_name,u.role author_role FROM organization_ticket_messages m JOIN users u ON u.id=m.author_id WHERE m.ticket_id IN ($ticketPlaceholders) ORDER BY m.created_at,m.id");
        $q->execute($memberTicketIds);
        foreach ($q->fetchAll() as $message) {
            $memberTicketMessages[$message['ticket_id']][] = $message;
        }
    }
} elseif ($pdo && $user && !$accountPage && $user['role'] === 'officer') {
    $q = $pdo->prepare('SELECT o.id,o.name,o.description FROM organizations o JOIN officer_organizations oo ON oo.organization_id=o.id WHERE oo.officer_id=? ORDER BY o.name');
    $q->execute([$user['id']]);
    $officerOrganizations = $q->fetchAll();
    foreach ($officerOrganizations as $organization) {
        $officerFilesByOrganization[$organization['id']] = [];
        $officerTicketsByOrganization[$organization['id']] = [];
        $officerProfilesByOrganization[$organization['id']] = [];
    }
    $q = $pdo->prepare('SELECT l.* FROM organization_leadership l JOIN officer_organizations oo ON oo.organization_id=l.organization_id AND oo.officer_id=? ORDER BY l.organization_id,FIELD(l.position,\'President\',\'Vice President\',\'Secretary\',\'Treasurer\',\'Public Relations Officer\')');
    $q->execute([$user['id']]);
    foreach ($q->fetchAll() as $organizationOfficer) {
        $officerProfilesByOrganization[$organizationOfficer['organization_id']][$organizationOfficer['position']] = $organizationOfficer;
    }
    $q = $pdo->prepare('SELECT f.id,f.organization_id,f.original_name,f.file_size,f.created_at FROM organization_files f JOIN officer_organizations oo ON oo.organization_id=f.organization_id AND oo.officer_id=? ORDER BY f.created_at DESC');
    $q->execute([$user['id']]);
    foreach ($q->fetchAll() as $file) {
        $officerFilesByOrganization[$file['organization_id']][] = $file;
    }
    $q = $pdo->prepare('SELECT t.id,t.organization_id,t.student_id,t.subject,t.status,t.created_at,t.updated_at,o.name organization_name,s.name student_name FROM organization_tickets t JOIN officer_organizations oo ON oo.organization_id=t.organization_id AND oo.officer_id=? JOIN organizations o ON o.id=t.organization_id JOIN users s ON s.id=t.student_id ORDER BY (t.status="open") DESC,t.updated_at DESC,t.id DESC');
    $q->execute([$user['id']]);
    $officerTickets = $q->fetchAll();
    $officerTicketIds = [];
    foreach ($officerTickets as $ticket) {
        $officerTicketsByOrganization[$ticket['organization_id']][] = $ticket;
        $officerTicketIds[] = (int)$ticket['id'];
    }
    if ($officerTicketIds) {
        $ticketPlaceholders = implode(',', array_fill(0, count($officerTicketIds), '?'));
        $q = $pdo->prepare("SELECT m.ticket_id,m.author_id,m.body,m.created_at,u.name author_name,u.role author_role FROM organization_ticket_messages m JOIN users u ON u.id=m.author_id WHERE m.ticket_id IN ($ticketPlaceholders) ORDER BY m.created_at,m.id");
        $q->execute($officerTicketIds);
        foreach ($q->fetchAll() as $message) {
            $officerTicketMessages[$message['ticket_id']][] = $message;
        }
    }
    $q = $pdo->prepare('SELECT a.*,o.name organization_name,s.name student_name,s.student_number,s.email,s.course,s.year_level FROM applications a JOIN organizations o ON o.id=a.organization_id JOIN users s ON s.id=a.student_id JOIN officer_organizations oo ON oo.organization_id=a.organization_id AND oo.officer_id=? ORDER BY (a.status="pending") DESC,a.created_at ASC');
    $q->execute([$user['id']]);
    $officerApps = $q->fetchAll();
    $officerAppsByStatus = [
        'pending' => array_filter($officerApps, fn($app) => $app['status'] === 'pending'),
        'approved' => array_filter($officerApps, fn($app) => $app['status'] === 'approved'),
        'rejected' => array_filter($officerApps, fn($app) => $app['status'] === 'rejected'),
    ];
} elseif ($pdo && $user && !$accountPage && $user['role'] === 'council') {
    $officerRequests = $pdo->query('SELECT r.id,r.name,r.student_number,r.email,r.status,r.created_at,r.reviewed_at,o.name organization_name FROM officer_registration_requests r JOIN organizations o ON o.id=r.organization_id ORDER BY (r.status="pending") DESC,r.created_at ASC')->fetchAll();
    $officerRequestsByStatus = [
        'pending' => array_filter($officerRequests, fn($request) => $request['status'] === 'pending'),
        'approved' => array_filter($officerRequests, fn($request) => $request['status'] === 'approved'),
        'rejected' => array_filter($officerRequests, fn($request) => $request['status'] === 'rejected'),
    ];
}
$notice = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#176b3a"><title>ISKOnnect | BulSU Bustos Campus Organizations Hub</title>
<link rel="icon" type="image/svg+xml" href="/assets/iskonnect-icon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/site.css?v=20261028-4"></head><body class="<?= $user ? 'signed-in' : 'public-site' ?>">
<header class="top"><div class="wrap nav-wrap"><a class="brand" href="<?=$accountPage?'/':'#top'?>"><img class="brand-mark" src="/assets/iskonnect-icon.svg" alt=""><span>ISK<span>O</span><span class="brand-accent">nnect</span><small>BulSU Bustos Campus</small></span></a>
<nav class="site-nav"><?php if ($user): ?><div class="nav-links" id="nav-links"><?php if ($accountPage): ?><a href="/">Home</a><?php elseif ($user['role'] === 'student'): ?><a href="#announcements">Council announcements</a><?php if ($memberOrganizations): ?><a href="#organization-announcements">My Organization</a><?php endif; ?><a href="#apply">Registration</a><a href="#status">Application status</a><?php elseif ($user['role'] === 'officer'): ?><a href="#council-announcements">Council announcements</a><a href="#applications">Student applications</a><a href="#organization-posts">Organization announcements/files</a><a href="#organization-roster">Organization details and officers</a><a href="#organization-tickets">Organization tickets</a><?php else: ?><a href="#council-announcements">Council announcements</a><a href="#publish-announcement">Publish announcement</a><a href="#officer-registrations">Officer registrations</a><?php endif; ?></div><?php endif; ?>
<button type="button" class="icon-button theme-button" id="theme-toggle" aria-label="Toggle color theme">◐</button>
<?php if ($user): ?>
<details class="profile-menu"><summary aria-label="Profile menu"><?php if (!empty($profile['profile_photo'])): ?><img src="<?=h($profile['profile_photo'])?>" alt="" class="profile-avatar"><?php else: ?><span class="profile-avatar profile-avatar-fallback" aria-hidden="true"><?=h(strtoupper(substr(account_first_name($user), 0, 1)))?></span><?php endif; ?><span>Profile</span></summary><div class="profile-dropdown"><b><?=h($user['name'])?></b><a href="/?page=account">My Account</a><form method="post" class="profile-signout"><?=csrf_field()?><input type="hidden" name="action" value="logout"><button type="submit">Sign out</button></form></div></details>
<button type="button" class="icon-button menu-button" id="menu-toggle" aria-label="Open menu" aria-expanded="false">☰</button><?php endif; ?></nav></div>
</header>
<?php if ($user): ?><div class="ambient-particles signed-in-particles" aria-hidden="true"><svg class="connection-network" viewBox="0 0 1200 800" preserveAspectRatio="none"><g class="network-lines"><path d="M40 150 150 230 270 180 360 290 500 240 610 340 740 260 850 330 1010 210 1170 270M80 590 210 500 350 600 470 490 620 560 760 450 900 550 1040 440 1180 530M150 230 210 500M360 290 350 600M610 340 620 560M850 330 900 550M1010 210 1040 440M500 240 470 490M740 260 760 450"/></g><g class="network-nodes"><circle cx="150" cy="230" r="4"/><circle cx="270" cy="180" r="3"/><circle cx="360" cy="290" r="4"/><circle cx="500" cy="240" r="3"/><circle cx="610" cy="340" r="4"/><circle cx="740" cy="260" r="3"/><circle cx="850" cy="330" r="4"/><circle cx="1010" cy="210" r="3"/><circle cx="210" cy="500" r="3"/><circle cx="350" cy="600" r="4"/><circle cx="470" cy="490" r="3"/><circle cx="620" cy="560" r="4"/><circle cx="760" cy="450" r="3"/><circle cx="900" cy="550" r="4"/><circle cx="1040" cy="440" r="3"/></g></svg><span></span><span></span><span></span><span></span><span></span><span></span></div><?php endif; ?>
<?php if ($user): ?><div class="signed-in-feedback"><div class="signed-in-greeting wrap">Welcome, <?=h(account_first_name($user))?>.</div><?php endif; ?>
<?php if ($error): ?><div class="notice error wrap"><?=h($error)?></div><?php endif; ?>
<?php if ($notice): ?><div class="notice <?=h($notice['kind'])?> wrap"><?=h($notice['message'])?></div><?php endif; ?>
<?php if ($user): ?></div><?php endif; ?>
<main id="top">
<?php if ($user && $accountPage): ?>
<section class="account-settings"><div class="wrap"><div class="section-head"><span class="eyebrow">Your profile</span><h1>My Account</h1><p>Update your bio, Facebook link, and profile photo. Your account email and role are shown for reference.</p></div>
<div class="account-settings-grid"><div class="account-card account-profile-summary"><?php if (!empty($profile['profile_photo'])): ?><img src="<?=h($profile['profile_photo'])?>" alt="<?=h($user['name'])?> profile photo" class="account-profile-photo"><?php else: ?><div class="account-profile-photo account-profile-placeholder" aria-hidden="true"><?=h(strtoupper(substr(account_first_name($user), 0, 1)))?></div><?php endif; ?><h2><?=h($user['name'])?></h2><p><?=h($user['email'])?></p><span class="category"><?=h(ucfirst($user['role']))?></span><?php if (!empty($profile['facebook_url']) && valid_facebook_url((string)$profile['facebook_url'])): ?><a class="facebook-profile-link" href="<?=h($profile['facebook_url'])?>" target="_blank" rel="noopener noreferrer">Visit Facebook profile</a><?php endif; ?></div>
<div class="account-card"><h2>Edit profile</h2><form method="post" action="/?page=account" enctype="multipart/form-data" class="account-form photo-crop-form" id="profile-form"><?=csrf_field()?><input type="hidden" name="action" value="update_profile"><label>Bio <span class="hint">Optional · up to 500 characters</span><textarea name="bio" rows="6" maxlength="500" placeholder="Tell people a little about yourself"><?=h((string)($profile['bio'] ?? ''))?></textarea></label><label>Facebook link <span class="hint">Optional · link to your Facebook profile or page</span><input type="url" name="facebook_url" maxlength="500" placeholder="https://www.facebook.com/your.profile" value="<?=h((string)($profile['facebook_url'] ?? ''))?>"></label><label>Profile photo <span class="hint">JPG, PNG, or WebP · maximum 3 MB</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-photo-input></label><div class="photo-crop-editor hidden" data-crop-editor><p class="hint">Drag the image to reposition it. Adjust the zoom or rotate it until the square preview looks right.</p><canvas data-crop-canvas width="320" height="320" role="img" aria-label="Profile photo crop preview"></canvas><div class="crop-controls"><label>Zoom <input data-crop-zoom type="range" min="1" max="3" step="0.01" value="1"></label><div class="crop-actions"><button type="button" class="button button-small button-outline crop-rotate" data-crop-rotate="-90">Rotate left</button><button type="button" class="button button-small button-outline crop-rotate" data-crop-rotate="90">Rotate right</button><button type="button" class="button button-small button-outline" data-crop-reset>Reset</button></div></div><p class="crop-error hidden" data-crop-error role="alert"></p></div><button class="button full" type="submit">Save changes</button></form></div></div>
<a class="text-link account-back-link" href="/">← Back to dashboard</a></div></section>
<?php elseif ($user && !$accountPage && $user['role'] === 'council'): ?>
<section class="officer-council-news" id="council-announcements"><div class="wrap"><div class="section-head"><span class="eyebrow">Campus-wide updates</span><h2>Local Student Council announcements</h2></div><?php if (!$announcements): ?><div class="announcement-empty"><span class="empty-mark" aria-hidden="true">I</span><div><b>No council announcements yet.</b><p>Publish an update to share it with the campus community.</p></div></div><?php else: ?><div class="announcement-list"><?php foreach ($announcements as $announcement): ?><article class="announcement-card"><div class="announcement-meta"><span class="announcement-label">Local Student Council</span><time datetime="<?=h(date(DATE_ATOM, strtotime($announcement['created_at'])))?>"><?=h(date('M j, Y', strtotime($announcement['created_at'])))?></time></div><h3><?=h($announcement['title'])?></h3><p><?=nl2br(h($announcement['body']))?></p></article><?php endforeach; ?></div><?php endif; ?></div></section>
<?php endif; ?>
<?php if (!$user): ?>
<section class="auth-screen" id="sign-in"><div class="ambient-particles" aria-hidden="true"><svg class="connection-network" viewBox="0 0 1200 800" preserveAspectRatio="none"><g class="network-lines"><path d="M40 150 150 230 270 180 360 290 500 240 610 340 740 260 850 330 1010 210 1170 270M80 590 210 500 350 600 470 490 620 560 760 450 900 550 1040 440 1180 530M150 230 210 500M360 290 350 600M610 340 620 560M850 330 900 550M1010 210 1040 440M500 240 470 490M740 260 760 450"/></g><g class="network-nodes"><circle cx="150" cy="230" r="4"/><circle cx="270" cy="180" r="3"/><circle cx="360" cy="290" r="4"/><circle cx="500" cy="240" r="3"/><circle cx="610" cy="340" r="4"/><circle cx="740" cy="260" r="3"/><circle cx="850" cy="330" r="4"/><circle cx="1010" cy="210" r="3"/><circle cx="210" cy="500" r="3"/><circle cx="350" cy="600" r="4"/><circle cx="470" cy="490" r="3"/><circle cx="620" cy="560" r="4"/><circle cx="760" cy="450" r="3"/><circle cx="900" cy="550" r="4"/><circle cx="1040" cy="440" r="3"/></g></svg><span></span><span></span><span></span><span></span><span></span><span></span></div><div class="auth-wrap">
  <div class="auth-intro"><span class="auth-pill"><span class="live-dot"></span> Bulacan State University - Bustos Campus</span><h1>Welcome to<br><em><span class="auth-wordmark">ISK<span class="brand-o">O</span><span class="brand-accent">nnect</span>.</span></em></h1><p>Sign in or create a student account to get connected with your campus community.</p>
    <ol class="auth-steps" aria-label="How ISKOnnect works">
      <li><span aria-hidden="true">01</span><div><b>Discover organizations</b><small>Find campus groups that fit your program and interests.</small></div></li>
      <li><span aria-hidden="true">02</span><div><b>Apply to join</b><small>Send an application and follow its status in your account.</small></div></li>
      <li><span aria-hidden="true">03</span><div><b>Stay in the loop</b><small>Catch council and organization announcements.</small></div></li>
    </ol>
    <a class="auth-register-link" href="#sign-in" data-panel-link data-panel-target="register">New to ISKOnnect? <b>Create a student account <span aria-hidden="true">→</span></b></a>
  </div>
  <div class="account-card auth-card"><div class="tab-row"><button type="button" class="tab active" data-panel="login">Sign in</button><button type="button" class="tab" data-panel="register">Student registration</button><button type="button" class="tab" data-panel="officer-register">Officer registration</button></div>
    <form method="post" class="account-form" id="login"><?=csrf_field()?><input type="hidden" name="action" value="login">
      <label>Email address<input type="email" name="email" autocomplete="email" placeholder="Enter your email address" required></label>
      <div class="password-form-field"><label for="login-password">Password</label><div class="password-field"><input id="login-password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required><button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg class="password-eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="password-eye-off hidden" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.3 0 2.5-.3 3.5-.7"></path></svg></button></div></div>
      <button class="button full">Sign in</button>
    </form>
    <form method="post" class="account-form hidden" id="register"><?=csrf_field()?><input type="hidden" name="action" value="register">
      <div class="form-row"><label>First name<input name="first_name" autocomplete="given-name" maxlength="80" placeholder="Enter your First Name" required></label><label>Surname<input name="surname" autocomplete="family-name" maxlength="80" placeholder="Enter your Surname" required></label></div>
      <div class="form-row"><label>Student number<input name="student_number" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" title="Enter exactly 10 digits." placeholder="Enter Student Number" required></label><label>Email address<input type="email" name="email" autocomplete="email" maxlength="254" placeholder="Enter your Email Address" required></label></div>
      <label>Department<select name="department" id="registration-department" required><option value="">Choose your department</option><?php foreach (DEPARTMENTS as $department): ?><option><?=h($department)?></option><?php endforeach; ?></select></label>
      <label>Year level<select name="year_level" id="registration-year" required><option value="">Select year level</option><?php foreach (['1st year','2nd year','3rd year','4th year','5th year+'] as $year): ?><option><?=h($year)?></option><?php endforeach; ?></select></label>
      <label>Course / program<select name="course" id="registration-course" required><option value="">Choose your department and year level first</option></select></label>
      <div class="password-form-field"><label for="student-register-password">Password</label><span class="hint">At least 8 characters</span><div class="password-field"><input id="student-register-password" type="password" name="password" autocomplete="new-password" minlength="8" placeholder="Enter your password" required><button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg class="password-eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="password-eye-off hidden" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.3 0 2.5-.3 3.5-.7"></path></svg></button></div></div>
      <label class="check"><input type="checkbox" name="application_terms" value="1" required><span>I confirm these details are accurate and agree to the campus organization application process.</span></label>
      <div class="privacy-consent"><input id="student-privacy-consent" type="checkbox" name="privacy_consent" value="1" required><span><label for="student-privacy-consent">I have read the privacy notice and consent to the collection and use of my personal data, in accordance with the</label> <button class="privacy-link" type="button" data-privacy-open>Data Privacy Act of 2012</button> <span>of the Philippines.</span></span></div>
      <button class="button full">Create student account</button>
    </form>
    <form method="post" class="account-form hidden" id="officer-register"><?=csrf_field()?><input type="hidden" name="action" value="officer_register">
      <p class="hint">Officer access is enabled only after local student council approval.</p>
      <div class="form-row"><label>First name<input name="first_name" autocomplete="given-name" maxlength="80" placeholder="Enter your First Name" required></label><label>Surname<input name="surname" autocomplete="family-name" maxlength="80" placeholder="Enter your Surname" required></label></div>
      <label>Student number<input name="student_number" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" title="Enter exactly 10 digits." placeholder="Enter Student Number" required></label>
      <label>Email address<input type="email" name="email" autocomplete="email" maxlength="254" placeholder="Enter your Email Address" required></label>
      <label>Organization<select name="organization_id" required><option value="">Choose your organization</option><?php foreach ($publicOrgs as $org): ?><option value="<?=h($org['id'])?>"><?=h($org['name'])?></option><?php endforeach; ?></select></label>
      <div class="password-form-field"><label for="officer-register-password">Password</label><span class="hint">At least 8 characters</span><div class="password-field"><input id="officer-register-password" type="password" name="password" autocomplete="new-password" minlength="8" placeholder="Enter your password" required><button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg class="password-eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="password-eye-off hidden" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.3 0 2.5-.3 3.5-.7"></path></svg></button></div></div>
      <label class="check"><input type="checkbox" name="application_terms" value="1" required><span>I confirm these details are accurate and agree to the campus organization application process.</span></label>
      <div class="privacy-consent"><input id="officer-privacy-consent" type="checkbox" name="privacy_consent" value="1" required><span><label for="officer-privacy-consent">I have read the privacy notice and consent to the collection and use of my personal data, in accordance with the</label> <button class="privacy-link" type="button" data-privacy-open>Data Privacy Act of 2012</button> <span>of the Philippines.</span></span></div>
      <button class="button full">Submit officer registration</button>
    </form>
    <p class="auth-footnote">Officer registrations are reviewed by the local student council before access is granted.</p>
  </div>
</div></section>
<dialog class="privacy-dialog" id="privacy-notice" aria-labelledby="privacy-title">
  <div class="privacy-dialog-content">
    <span class="eyebrow">Republic Act No. 10173</span>
    <h2 id="privacy-title">Data Privacy Act of 2012</h2>
    <p>The Data Privacy Act of 2012 protects personal information and requires it to be handled transparently, for a legitimate purpose, and only to the extent necessary.</p>
    <h3>How registration information is used</h3>
    <p>ISKOnnect uses the information you provide to create or review your account, verify student and organization eligibility, manage organization applications, and respond to member support tickets. Authorized Local Student Council reviewers handle officer registrations. Organization officers can see applicant details and ticket conversations for organizations assigned to them. Organization files are shared only with that organization's admitted members and assigned officers.</p>
    <p>Your information should be kept secure and used only for these campus organization processes. You may request access to or correction of your personal information, or raise a privacy concern, through official campus channels.</p>
    <p class="privacy-source">Read <a href="https://www.officialgazette.gov.ph/2012/08/15/republic-act-no-10173/" target="_blank" rel="noopener noreferrer">Republic Act No. 10173 on the Official Gazette</a>.</p>
    <form method="dialog"><button class="button full">Close</button></form>
  </div>
</dialog>
<?php elseif ($accountPage): ?>
<?php elseif ($user['role'] === 'student'): ?>
<section class="student-announcements" id="announcements"><div class="wrap"><div class="section-head"><span class="eyebrow">For every student</span><h2>Local Student Council announcements</h2><p>Official campus updates available to all students.</p></div>
<?php if (!$announcements): ?><div class="announcement-empty"><span class="empty-mark" aria-hidden="true">I</span><div><b>You're all caught up.</b><p>There are no council announcements right now.</p></div></div><?php else: ?><div class="announcement-list"><?php foreach ($announcements as $announcement): ?><article class="announcement-card"><div class="announcement-meta"><span class="announcement-label">Local Student Council</span><time datetime="<?=h(date(DATE_ATOM, strtotime($announcement['created_at'])))?>"><?=h(date('M j, Y', strtotime($announcement['created_at'])))?></time></div><h3><?=h($announcement['title'])?></h3><p><?=nl2br(h($announcement['body']))?></p></article><?php endforeach; ?></div><?php endif; ?>
</div></section>
<section class="student-portal" id="portal"><div class="wrap"><div class="section-head" id="apply"><span class="eyebrow">Join an organization</span><h2><?= $myApps ? 'Your organization applications' : 'Registration' ?></h2><p><?= $myApps ? 'Review your applications or apply to another eligible organization.' : 'Submit one application. The organization officer will review it and update your private status below.' ?></p></div>
<div class="portal-grid"><?php if (empty($user['department'])): ?><div class="account-card"><h3>Choose your department</h3><p class="hint">We use this to show the organizations you may apply to.</p><form method="post" class="account-form"><?=csrf_field()?><input type="hidden" name="action" value="set_department"><label>Department<select name="department" required><option value="">Choose your department</option><?php foreach (DEPARTMENTS as $department): ?><option><?=h($department)?></option><?php endforeach; ?></select></label><button class="button full">Continue</button></form></div><?php elseif (empty($user['course']) || empty($user['year_level']) || !in_array((string)$user['course'], programs_for_registration((string)$user['department'], (string)$user['year_level']), true)): ?><div class="account-card"><h3>Complete your student profile</h3><p class="hint">Choose your registered department, program, and year level before applying.</p><form method="post" class="account-form"><?=csrf_field()?><input type="hidden" name="action" value="set_student_profile"><label>Department<select name="department" id="profile-department" required><option value="">Choose your department</option><?php foreach (DEPARTMENTS as $department): ?><option <?=($user['department']??'')===$department?'selected':''?>><?=h($department)?></option><?php endforeach; ?></select></label><label>Year level<select name="year_level" id="profile-year" required><option value="">Select year level</option><?php foreach (['1st year','2nd year','3rd year','4th year','5th year+'] as $year): ?><option <?=($user['year_level']??'')===$year?'selected':''?>><?=h($year)?></option><?php endforeach; ?></select></label><label>Course / program<select name="course" id="profile-course" required><option value="<?=h($user['course']??'')?>"><?=h($user['course']??'Choose your department and year level first')?></option></select></label><button class="button full">Save profile</button></form></div><?php else: ?>
<?php if (!$orgs): ?><div class="account-card"><h3><?= $myApps ? 'No other eligible organizations' : 'No organizations available' ?></h3><p><?= $myApps ? 'You have applied to all organizations currently available for your program and application slots.' : 'No eligible organizations are available for your program right now.' ?></p></div><?php else: ?>
<div class="account-card registration-card"><?php if ($myApps): ?><details class="compact-registration"><summary class="text-link">Apply to another eligible organization</summary><p class="hint">Your available application choices are based on your program and current application status.</p><?php else: ?><h3>Register for an organization</h3><p class="hint">You may have one program organization and one overall organization application at a time. Rejections free that application slot.</p><?php endif; ?>
<form method="post" class="account-form"><?=csrf_field()?><input type="hidden" name="action" value="apply">
<label>Organization<select name="organization_id" id="organization" required><option value="">Choose an organization</option><?php $programOrganizations=array_values(array_filter($orgs,fn($org)=>$org['application_group']==='program'));$overallOrganizations=array_values(array_filter($orgs,fn($org)=>$org['application_group']==='overall'));if($programOrganizations): ?><optgroup label="Program organizations"><?php foreach ($programOrganizations as $org): ?><option value="<?=h($org['id'])?>"><?=h($org['name'])?></option><?php endforeach; ?></optgroup><?php endif; if($overallOrganizations): ?><optgroup label="Overall organizations"><?php foreach ($overallOrganizations as $org): ?><option value="<?=h($org['id'])?>"><?=h($org['name'])?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label>
<div class="form-row"><label>Course / program<input value="<?=h($user['course']??'')?>" readonly></label><label>Year level<input value="<?=h($user['year_level']??'')?>" readonly></label></div>
<label>Preferred committee / role <span class="hint">Optional</span><input name="preferred_role" maxlength="120" placeholder="e.g. Publicity, Events, Finance"></label>
<label>Why would you like to join?<textarea name="motivation" rows="5" maxlength="5000" required></textarea></label><button class="button full">Submit application</button>
</form><?php if ($myApps): ?></details><?php endif; ?></div><?php endif; ?>
<?php endif; ?>
<div class="account-card" id="status"><h3>Application status</h3><?php if (!$myApps): ?><p>You haven’t registered with an organization yet. Your application status will appear here after you submit the form.</p><?php else: foreach ($myApps as $app): ?><article class="application"><div class="app-top"><div><span class="category"><?=h($app['organization_name'])?></span><h4><?=h(ucfirst($app['status']))?></h4></div><span class="status <?=h($app['status'])?>"><?=h(ucfirst($app['status']))?></span></div><p class="hint">Submitted <?=h(date('M j, Y',strtotime($app['created_at'])))?><?= $app['preferred_role']?' · '.h($app['preferred_role']):'' ?></p><?php if ($app['officer_note']): ?><div class="officer-note"><b>Note from the organization</b><p><?=h($app['officer_note'])?></p></div><?php endif; ?></article><?php endforeach; endif; ?></div></div>
</div></section>
<?php if ($memberOrganizations): ?><section class="member-announcements" id="organization-announcements"><div class="wrap"><div class="section-head" id="my-organizations"><span class="eyebrow">Your admitted organizations</span><h2>My Organization</h2><p>Organization information, files, support tickets, and member-only announcements.</p></div><div class="member-announcement-groups"><?php foreach ($memberOrganizations as $organization): $organizationId=(string)$organization['id']; $workspaceId='organization-workspace-'.preg_replace('/[^a-zA-Z0-9_-]/','-',$organizationId); ?><section class="member-announcement-group organization-workspace" aria-labelledby="<?=$workspaceId?>-title"><div class="section-head"><span class="eyebrow">Member workspace</span><h3 id="<?=$workspaceId?>-title"><?=h($organization['name'])?></h3></div><div class="tab-row organization-workspace-tabs" role="tablist" aria-label="<?=h($organization['name'])?> sections">
<button class="tab active" id="<?=$workspaceId?>-tab-information" type="button" role="tab" aria-controls="<?=$workspaceId?>-panel-information" aria-selected="true" data-organization-workspace-tab>Information</button><button class="tab" id="<?=$workspaceId?>-tab-members" type="button" role="tab" aria-controls="<?=$workspaceId?>-panel-members" aria-selected="false" data-organization-workspace-tab>Officers</button><button class="tab" id="<?=$workspaceId?>-tab-files" type="button" role="tab" aria-controls="<?=$workspaceId?>-panel-files" aria-selected="false" data-organization-workspace-tab>Files</button><button class="tab" id="<?=$workspaceId?>-tab-ticket" type="button" role="tab" aria-controls="<?=$workspaceId?>-panel-ticket" aria-selected="false" data-organization-workspace-tab>Submit a Ticket</button></div>
<div class="organization-workspace-panel" id="<?=$workspaceId?>-panel-information" role="tabpanel" aria-labelledby="<?=$workspaceId?>-tab-information"><h4>About</h4><p class="organization-about"><?=nl2br(h($organization['description']))?></p><h4>Announcements</h4><?php if (!$memberAnnouncementsByOrganization[$organizationId]): ?><div class="announcement-empty"><span class="empty-mark" aria-hidden="true">I</span><div><b>No announcements yet.</b><p>Organization updates will appear here when an officer publishes one.</p></div></div><?php else: ?><div class="announcement-list"><?php foreach ($memberAnnouncementsByOrganization[$organizationId] as $announcement): ?><article class="announcement-card organization-announcement"><div class="announcement-meta"><span class="announcement-label"><?=h($announcement['organization_name'])?></span><time datetime="<?=h(date(DATE_ATOM, strtotime($announcement['created_at'])))?>"><?=h(date('M j, Y', strtotime($announcement['created_at'])))?></time></div><h3><?=h($announcement['title'])?></h3><p><?=nl2br(h($announcement['body']))?></p><small>Posted by <?=h($announcement['author_name'])?></small></article><?php endforeach; ?></div><?php endif; ?></div>
<div class="organization-workspace-panel hidden" id="<?=$workspaceId?>-panel-members" role="tabpanel" aria-labelledby="<?=$workspaceId?>-tab-members"><div class="organization-officers-grid"><?php foreach (ORG_OFFICER_POSITIONS as $position): $organizationOfficer=$memberOfficersByOrganization[$organizationId][$position]??null; ?><article class="organization-officer-card"><?php if (!empty($organizationOfficer['profile_photo'])): ?><img class="organization-officer-photo" src="/?organization_officer_photo=1&amp;organization_id=<?=rawurlencode($organizationId)?>&amp;position=<?=rawurlencode($position)?>" alt="<?=h($position)?> profile photo"><?php else: ?><div class="organization-officer-photo organization-officer-placeholder" aria-hidden="true"><?=h(strtoupper(substr($position,0,1)))?></div><?php endif; ?><div><span class="eyebrow"><?=h($position)?></span><?php if (!empty($organizationOfficer['name'])): ?><h4><?=h($organizationOfficer['name'])?></h4><?php else: ?><h4 class="hint">Not listed yet</h4><?php endif; ?><?php if (!empty($organizationOfficer['profile'])): ?><p><?=nl2br(h($organizationOfficer['profile']))?></p><?php endif; ?><?php if (!empty($organizationOfficer['contact_email'])): ?><a href="mailto:<?=h($organizationOfficer['contact_email'])?>"><?=h($organizationOfficer['contact_email'])?></a><?php endif; ?><?php if (!empty($organizationOfficer['facebook_url']) && valid_facebook_url((string)$organizationOfficer['facebook_url'])): ?><a href="<?=h($organizationOfficer['facebook_url'])?>" target="_blank" rel="noopener noreferrer">Facebook profile</a><?php endif; ?></div></article><?php endforeach; ?></div></div>
<div class="organization-workspace-panel hidden" id="<?=$workspaceId?>-panel-files" role="tabpanel" aria-labelledby="<?=$workspaceId?>-tab-files"><h4>Organization files</h4><?php if (!$memberFilesByOrganization[$organizationId]): ?><p class="hint">No files have been shared by this organization yet.</p><?php else: ?><ul class="organization-file-list"><?php foreach ($memberFilesByOrganization[$organizationId] as $file): ?><li><a href="/?organization_file=<?=$file['id']?>"><?=h($file['original_name'])?></a><span><?=number_format((int)$file['file_size'] / 1024, 1)?> KB · Uploaded by <?=h($file['uploader_name'])?> · <?=h(date('M j, Y', strtotime($file['created_at'])))?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
<div class="organization-workspace-panel hidden" id="<?=$workspaceId?>-panel-ticket" role="tabpanel" aria-labelledby="<?=$workspaceId?>-tab-ticket"><h4>Submit a Ticket</h4><p class="hint">Contact this organization's officers and follow replies and status updates here.</p><form method="post" class="account-form ticket-submit-form"><?=csrf_field()?><input type="hidden" name="action" value="submit_org_ticket"><input type="hidden" name="organization_id" value="<?=h($organizationId)?>"><label>Subject<input name="subject" maxlength="180" required></label><label>Message<textarea name="message" rows="4" maxlength="5000" required></textarea></label><button class="button">Send ticket</button></form>
<?php if ($memberTicketsByOrganization[$organizationId]): ?><h4>Your tickets</h4><?php foreach ($memberTicketsByOrganization[$organizationId] as $ticket): ?><article class="organization-ticket"><div class="app-top"><b><?=h($ticket['subject'])?></b><span class="ticket-status <?=h($ticket['status'])?>"><?=['open'=>'Open','in_progress'=>'In progress','resolved'=>'Resolved'][$ticket['status']]?></span></div><p class="hint">Updated <?=h(date('M j, Y g:i a', strtotime($ticket['updated_at'])))?></p><div class="ticket-messages"><?php foreach ($memberTicketMessages[$ticket['id']] ?? [] as $message): ?><div class="ticket-message"><b><?=h($message['author_name'])?> <span><?= $message['author_role']==='officer'?'Organization officer':'You' ?></span></b><p><?=nl2br(h($message['body']))?></p><small><?=h(date('M j, Y g:i a', strtotime($message['created_at'])))?></small></div><?php endforeach; ?></div></article><?php endforeach; ?><?php else: ?><p class="hint">You have not submitted a ticket to this organization.</p><?php endif; ?></div></section><?php endforeach; ?></div></div></section><?php endif; ?>
<?php elseif ($user['role'] === 'officer'): ?>
<section class="officer-council-news" id="council-announcements"><div class="wrap"><div class="section-head"><span class="eyebrow">Campus-wide updates</span><h2>Local Student Council announcements</h2><p>Official announcements shared by the Local Student Council with the campus community.</p></div><?php if (!$announcements): ?><div class="announcement-empty"><span class="empty-mark" aria-hidden="true">I</span><div><b>No council announcements yet.</b><p>Check back here for campus-wide updates.</p></div></div><?php else: ?><div class="announcement-list"><?php foreach ($announcements as $announcement): ?><article class="announcement-card"><div class="announcement-meta"><span class="announcement-label">Local Student Council</span><time datetime="<?=h(date(DATE_ATOM, strtotime($announcement['created_at'])))?>"><?=h(date('M j, Y', strtotime($announcement['created_at'])))?></time></div><h3><?=h($announcement['title'])?></h3><p><?=nl2br(h($announcement['body']))?></p></article><?php endforeach; ?></div><?php endif; ?></div></section>
<section class="officer-portal officer-publishing" id="organization-posts"><div class="wrap"><div class="section-head"><span class="eyebrow">Your assigned organizations</span><h2>Organization announcements and files</h2><p>Each organization has a separate member-only feed and file area. You can publish and upload only to organizations assigned to your officer account.</p></div><?php if (!$officerOrganizations): ?><div class="announcement-empty"><span class="empty-mark" aria-hidden="true">I</span><div><b>No organizations are assigned to your account.</b><p>Contact the Local Student Council if you need access to publish.</p></div></div><?php else: ?><div class="portal-grid officer-announcement-tools"><?php foreach ($officerOrganizations as $organization): ?><div class="account-card"><h3><?=h($organization['name'])?></h3><form method="post" class="account-form"><?=csrf_field()?><input type="hidden" name="action" value="publish_org_announcement"><input type="hidden" name="organization_id" value="<?=h($organization['id'])?>"><label>Title<input name="title" maxlength="180" required></label><label>Message<textarea name="body" rows="5" maxlength="10000" required></textarea></label><button class="button full">Publish to <?=h($organization['name'])?> members</button></form><h4 class="subheading">Share a file with members</h4><form method="post" enctype="multipart/form-data" class="account-form"><?=csrf_field()?><input type="hidden" name="action" value="upload_org_file"><input type="hidden" name="organization_id" value="<?=h($organization['id'])?>"><label>File <span class="hint">PDF, DOCX, XLSX, PPTX, TXT, PNG, JPG, or WebP · up to 10 MB</span><input type="file" name="organization_file" accept=".pdf,.docx,.xlsx,.pptx,.txt,.png,.jpg,.jpeg,.webp" required></label><button class="button">Upload for members</button></form><?php if ($officerFilesByOrganization[$organization['id']]): ?><ul class="organization-file-list officer-file-list"><?php foreach ($officerFilesByOrganization[$organization['id']] as $file): ?><li><a href="/?organization_file=<?=$file['id']?>"><?=h($file['original_name'])?></a><span><?=number_format((int)$file['file_size'] / 1024, 1)?> KB · <?=h(date('M j, Y', strtotime($file['created_at'])))?></span></li><?php endforeach; ?></ul><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
<aside class="account-card announcement-audience"><span class="feature-icon" aria-hidden="true">◎</span><h3>Member-only updates</h3><p>Announcements are visible to students after their applications are approved and they become members of the selected organization.</p><p class="hint">Local Student Council announcements remain available to all officers.</p></aside></div></div></section>
<section class="officer-portal officer-applications" id="organization-roster"><div class="wrap"><div class="section-head"><span class="eyebrow">Organization details</span><h2>Edit organization information and officers</h2><p>Update the About section and officer profiles for organizations assigned to your account. Members see these details in their organization's workspace.</p></div><?php if (!$officerOrganizations): ?><p class="hint">No organizations are assigned to your officer account.</p><?php else: ?><div class="member-announcement-groups"><?php foreach ($officerOrganizations as $organization): $organizationId=(string)$organization['id']; ?><section class="account-card organization-editor"><h3><?=h($organization['name'])?></h3><form method="post" class="account-form organization-about-form"><?=csrf_field()?><input type="hidden" name="action" value="update_org_about"><input type="hidden" name="organization_id" value="<?=h($organizationId)?>"><label>About this organization<textarea name="description" rows="4" maxlength="5000" required><?=h($organization['description'])?></textarea></label><button class="button">Save About</button></form><h4>Officer profiles</h4><div class="organization-officer-editor-grid"><?php foreach (ORG_OFFICER_POSITIONS as $position): $organizationOfficer=$officerProfilesByOrganization[$organizationId][$position]??[]; ?><form method="post" enctype="multipart/form-data" class="account-form organization-officer-editor photo-crop-form"><?=csrf_field()?><input type="hidden" name="action" value="update_org_officer_profile"><input type="hidden" name="organization_id" value="<?=h($organizationId)?>"><input type="hidden" name="position" value="<?=h($position)?>"><h5><?=h($position)?></h5><?php if (!empty($organizationOfficer['profile_photo'])): ?><img class="organization-officer-photo editor-officer-photo" src="/?organization_officer_photo=1&amp;organization_id=<?=rawurlencode($organizationId)?>&amp;position=<?=rawurlencode($position)?>" alt="<?=h($position)?> profile photo"><?php else: ?><span class="organization-officer-photo organization-officer-placeholder editor-officer-photo" aria-hidden="true"><?=h(strtoupper(substr($position, 0, 1)))?></span><?php endif; ?><label>Name<input name="name" maxlength="160" value="<?=h((string)($organizationOfficer['name']??''))?>" placeholder="Officer name"></label><label>Profile / bio <span class="hint">Up to 1,000 characters</span><textarea name="profile" rows="3" maxlength="1000" placeholder="Share a short introduction"><?=h((string)($organizationOfficer['profile']??''))?></textarea></label><label>Contact email <span class="hint">Optional</span><input type="email" name="contact_email" maxlength="254" value="<?=h((string)($organizationOfficer['contact_email']??''))?>" placeholder="name@example.com"></label><label>Facebook profile <span class="hint">Optional</span><input type="url" name="facebook_url" maxlength="500" placeholder="https://www.facebook.com/your.profile" value="<?=h((string)($organizationOfficer['facebook_url']??''))?>"></label><label>Profile photo <span class="hint">JPG, PNG, or WebP · maximum 3 MB</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-photo-input></label><div class="photo-crop-editor officer-photo-crop-editor hidden" data-crop-editor><p class="hint">Drag to reposition. Zoom or rotate until the square preview looks right.</p><canvas data-crop-canvas width="320" height="320" role="img" aria-label="<?=h($position)?> photo crop preview"></canvas><div class="crop-controls"><label>Zoom <input data-crop-zoom type="range" min="1" max="3" step="0.01" value="1"></label><div class="crop-actions"><button type="button" class="button button-small button-outline" data-crop-rotate="-90">Rotate left</button><button type="button" class="button button-small button-outline" data-crop-rotate="90">Rotate right</button><button type="button" class="button button-small button-outline" data-crop-reset>Reset</button></div></div><p class="crop-error hidden" data-crop-error role="alert"></p></div><?php if (!empty($organizationOfficer['profile_photo'])): ?><label class="check"><input type="checkbox" name="remove_photo" value="1"><span>Remove current photo</span></label><?php endif; ?><button class="button">Save <?=h($position)?> profile</button></form><?php endforeach; ?></div></section><?php endforeach; ?></div><?php endif; ?></div></section>
<section class="officer-portal officer-applications" id="organization-tickets"><div class="wrap"><div class="section-head"><span class="eyebrow">Member support</span><h2>Organization tickets</h2><p>Review and reply to tickets submitted by members of organizations assigned to your officer account.</p></div><?php if (!$officerOrganizations): ?><p class="hint">No organizations are assigned to your officer account.</p><?php else: ?><div class="member-announcement-groups"><?php foreach ($officerOrganizations as $organization): ?><div class="account-card"><h3><?=h($organization['name'])?></h3><?php if (!$officerTicketsByOrganization[$organization['id']]): ?><p class="hint">There are no member tickets for this organization.</p><?php else: foreach ($officerTicketsByOrganization[$organization['id']] as $ticket): ?><article class="organization-ticket"><div class="app-top"><div><b><?=h($ticket['subject'])?></b><p class="hint">From <?=h($ticket['student_name'])?> · Updated <?=h(date('M j, Y g:i a', strtotime($ticket['updated_at'])))?></p></div><span class="ticket-status <?=h($ticket['status'])?>"><?=['open'=>'Open','in_progress'=>'In progress','resolved'=>'Resolved'][$ticket['status']]?></span></div><div class="ticket-messages"><?php foreach ($officerTicketMessages[$ticket['id']] ?? [] as $message): ?><div class="ticket-message"><b><?=h($message['author_name'])?> <span><?= $message['author_role']==='officer'?'Officer':'Member' ?></span></b><p><?=nl2br(h($message['body']))?></p><small><?=h(date('M j, Y g:i a', strtotime($message['created_at'])))?></small></div><?php endforeach; ?></div><form method="post" class="account-form ticket-reply-form"><?=csrf_field()?><input type="hidden" name="action" value="reply_org_ticket"><input type="hidden" name="ticket_id" value="<?=$ticket['id']?>"><label>Reply<textarea name="message" rows="3" maxlength="5000" required></textarea></label><label>Status<select name="status" required><option value="open" <?=$ticket['status']==='open'?'selected':''?>>Open</option><option value="in_progress" <?=$ticket['status']==='in_progress'?'selected':''?>>In progress</option><option value="resolved" <?=$ticket['status']==='resolved'?'selected':''?>>Resolved</option></select></label><button class="button">Send reply and update</button></form></article><?php endforeach; endif; ?></div><?php endforeach; ?></div><?php endif; ?></div></section>
<section class="officer-portal officer-applications" id="applications"><div class="wrap"><div class="section-head"><span class="eyebrow">Application review</span><h2>Student applications</h2><p>Review and update applications only for organizations assigned to your officer account.</p></div>
<div class="account-card"><div class="table-heading"><h3>Applications assigned to you</h3><span><?=count($officerApps)?> total</span></div><?php if (!$officerApps): ?><p>No applications are assigned to your officer account yet.</p><?php else: ?>
<div class="tab-row application-tabs" role="tablist" aria-label="Application status"><?php foreach (['pending'=>'Awaiting review','approved'=>'Accepted','rejected'=>'Rejected'] as $statusKey=>$statusLabel): ?><button class="tab application-tab<?=$statusKey==='pending'?' active':''?>" id="application-tab-<?=$statusKey?>" type="button" role="tab" aria-controls="application-panel-<?=$statusKey?>" aria-selected="<?=$statusKey==='pending'?'true':'false'?>" data-application-tab="<?=$statusKey?>"><?=$statusLabel?> <span>(<?=count($officerAppsByStatus[$statusKey])?>)</span></button><?php endforeach; ?></div>
<?php foreach (['pending'=>'Awaiting review','approved'=>'Accepted','rejected'=>'Rejected'] as $statusKey=>$statusLabel): ?><div class="application-panel<?=$statusKey==='pending'?'':' hidden'?>" id="application-panel-<?=$statusKey?>" role="tabpanel" aria-labelledby="application-tab-<?=$statusKey?>" tabindex="0">
<?php if (!$officerAppsByStatus[$statusKey]): ?><p>No applications <?= $statusKey==='pending'?'are awaiting review.':($statusKey==='approved'?'have been accepted.':'have been rejected.') ?></p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Applicant</th><th>Organization</th><th>Application</th><th>Decision and note</th></tr></thead><tbody><?php foreach ($officerAppsByStatus[$statusKey] as $app): ?><tr><td><b><?=h($app['student_name'])?></b><small><?=h($app['student_number'])?> · <?=h($app['email'])?></small><small><?=h($app['course'])?> · <?=h($app['year_level'])?></small></td><td><?=h($app['organization_name'])?></td><td><?= $app['preferred_role']?'<b>'.h($app['preferred_role']).'</b>':'' ?><p><?=nl2br(h($app['motivation']))?></p><small>Received <?=h(date('M j, Y',strtotime($app['created_at'])))?></small></td><td><form method="post" class="review-form"><?=csrf_field()?><input type="hidden" name="action" value="review"><input type="hidden" name="application_id" value="<?=$app['id']?>"><label>Decision<select name="status" required><option value="pending" <?=$app['status']==='pending'?'selected':''?>>Awaiting review</option><option value="approved" <?=$app['status']==='approved'?'selected':''?>>Accepted</option><option value="rejected" <?=$app['status']==='rejected'?'selected':''?>>Rejected</option></select></label><label>Decision note<textarea name="officer_note" rows="2" maxlength="1000"><?=h($app['officer_note'])?></textarea></label><button class="button small">Save changes</button></form><form method="post" class="application-delete-form" onsubmit="return confirm('Delete this application? This cannot be undone.')"><?=csrf_field()?><input type="hidden" name="action" value="delete_application"><input type="hidden" name="application_id" value="<?=$app['id']?>"><button class="button small danger">Delete application</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><?php endforeach; ?><?php endif; ?></div>
</div></section>
<?php else: ?>
<section class="officer-portal officer-applications" id="council-controls"><div class="wrap"><div class="portal-grid"><div class="account-card" id="publish-announcement"><h3>Publish Local Student Council announcement</h3><form method="post" class="account-form"><?=csrf_field()?><input type="hidden" name="action" value="publish_announcement"><label>Title<input name="title" maxlength="180" required></label><label>Message<textarea name="body" rows="6" maxlength="10000" required></textarea></label><button class="button full">Publish announcement</button></form>
<?php if ($announcements): ?><h3 class="subheading">Manage announcements</h3><?php foreach ($announcements as $announcement): ?><article class="announcement-card compact"><div class="app-top"><b><?=h($announcement['title'])?></b><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="announcement_id" value="<?=$announcement['id']?>"><button class="button small danger">Remove</button></form></div></article><?php endforeach; endif; ?></div>
<div class="account-card" id="officer-registrations"><div class="table-heading"><h3>Organization officer registrations</h3><span><?=count($officerRequests)?> total</span></div>
<div class="tab-row application-tabs" role="tablist" aria-label="Officer registration status"><?php foreach (['pending'=>'Awaiting review','approved'=>'Accepted','rejected'=>'Rejected'] as $statusKey=>$statusLabel): ?><button class="tab application-tab<?=$statusKey==='pending'?' active':''?>" id="officer-request-tab-<?=$statusKey?>" type="button" role="tab" aria-controls="officer-request-panel-<?=$statusKey?>" aria-selected="<?=$statusKey==='pending'?'true':'false'?>" data-application-tab="<?=$statusKey?>"><?=$statusLabel?> <span>(<?=count($officerRequestsByStatus[$statusKey])?>)</span></button><?php endforeach; ?></div>
<?php foreach (['pending'=>'Awaiting review','approved'=>'Accepted','rejected'=>'Rejected'] as $statusKey=>$statusLabel): ?><div class="application-panel<?=$statusKey==='pending'?'':' hidden'?>" id="officer-request-panel-<?=$statusKey?>" role="tabpanel" aria-labelledby="officer-request-tab-<?=$statusKey?>" tabindex="0"><?php if (!$officerRequestsByStatus[$statusKey]): ?><p>No officer registrations <?= $statusKey==='pending'?'are awaiting review.':($statusKey==='approved'?'have been accepted.':'have been rejected.') ?></p><?php else: foreach ($officerRequestsByStatus[$statusKey] as $request): ?><article class="application"><div class="app-top"><b><?=h($request['name'])?></b><span class="category"><?=h($request['organization_name'])?></span></div><p class="hint"><?=h($request['student_number'])?> · <?=h($request['email'])?> · Submitted <?=h(date('M j, Y', strtotime($request['created_at'])))?></p><?php if ($statusKey==='pending'): ?><div class="actions"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="review_officer_request"><input type="hidden" name="request_id" value="<?=$request['id']?>"><input type="hidden" name="decision" value="approved"><button class="button small">Accept registration</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="review_officer_request"><input type="hidden" name="request_id" value="<?=$request['id']?>"><input type="hidden" name="decision" value="rejected"><button class="button small danger">Reject registration</button></form></div><?php else: ?><span class="status <?=h($request['status'])?>"><?= $statusKey==='approved'?'Accepted':'Rejected' ?></span><?php if ($request['reviewed_at']): ?><small class="hint">Reviewed <?=h(date('M j, Y', strtotime($request['reviewed_at'])))?></small><?php endif; ?><?php endif; ?></article><?php endforeach; endif; ?></div><?php endforeach; ?></div></div>
</div></section>
<?php endif; ?>
</main>
<footer class="site-footer"><div class="wrap footer-grid"><div class="footer-brand"><a class="brand" href="<?=$accountPage?'/':'#top'?>"><img class="brand-mark" src="/assets/iskonnect-icon.svg" alt=""><span>ISK<span>O</span><span class="brand-accent">nnect</span><small>BulSU Bustos Campus</small></span></a><p>The central hub for discovering campus organizations and reading public student council announcements.</p></div><div><h3><?= $accountPage ? 'My Account' : ($user ? ($user['role']==='officer'?'Officer workspace':($user['role']==='council'?'Council workspace':'Explore')) : 'Explore') ?></h3><?php if ($accountPage): ?><a href="/">Back to dashboard</a><?php elseif ($user && $user['role']==='officer'): ?><a href="#council-announcements">Council announcements</a><a href="#applications">Student applications</a><a href="#organization-posts">Organization announcements</a><a href="#organization-tickets">Organization tickets</a><?php elseif ($user && $user['role']==='council'): ?><a href="#council-announcements">Council announcements</a><a href="#publish-announcement">Publish announcement</a><a href="#officer-registrations">Officer registrations</a><?php elseif ($user && $user['role']==='student'): ?><a href="#announcements">Council announcements</a><?php if ($memberOrganizations): ?><a href="#organization-announcements">My Organization</a><?php endif; ?><a href="#apply">Registration</a><?php else: ?><a href="#sign-in" data-panel-link data-panel-target="login">Sign in</a><a href="#sign-in" data-panel-link data-panel-target="register">Student registration</a><a href="#sign-in" data-panel-link data-panel-target="officer-register">Officer registration</a><?php endif; ?></div><div><h3><?= $user ? 'Your account' : 'Get started' ?></h3><?php if ($accountPage || ($user && $user['role']!=='student')): ?><a href="/">Back to dashboard</a><?php elseif ($user): ?><a href="#portal">My applications</a><?php else: ?><a href="#sign-in">Sign in or register</a><?php endif; ?></div></div><div class="wrap footer-bottom"><span>© <?=date('Y')?> ISKOnnect · BulSU Bustos Campus</span><span>BulSU Bustos campus community</span></div></footer>
<script>
const registrationProgramCatalog=<?=json_encode(PROGRAMS_BY_DEPARTMENT, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
function bindProgramSelectors(departmentId,yearId,courseId){const department=document.getElementById(departmentId),year=document.getElementById(yearId),course=document.getElementById(courseId);if(!department||!year||!course)return;function update(){let programs=registrationProgramCatalog[department.value]||[];if(department.value==='Business Administration and Entrepreneurship'){programs=programs.filter(program=>program==='BS Entrepreneurship'||(program==='BS Business Administration'&&['1st year','2nd year'].includes(year.value))||(program.startsWith('BS Business Administration major in ')&&year.value==='3rd year'))}const selected=course.value,placeholder=document.createElement('option');placeholder.value='';placeholder.textContent=programs.length?'Choose your course / program':'Choose department and year level first';course.replaceChildren(placeholder);for(const program of programs){const option=document.createElement('option');option.value=program;option.textContent=program;course.append(option)}if(programs.includes(selected))course.value=selected;else if(programs.length===1)course.value=programs[0]}department.addEventListener('change',update);year.addEventListener('change',update);update()}
bindProgramSelectors('registration-department','registration-year','registration-course');
bindProgramSelectors('profile-department','profile-year','profile-course');
document.querySelectorAll('[data-panel]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-panel]').forEach(item=>item.classList.toggle('active',item===button));document.querySelectorAll('.account-form[id]').forEach(form=>form.classList.toggle('hidden',form.id!==button.dataset.panel))}));
document.querySelectorAll('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{const input=button.parentElement.querySelector('input');const visible=input.type==='password';input.type=visible?'text':'password';button.setAttribute('aria-label',visible?'Hide password':'Show password');button.setAttribute('aria-pressed',String(visible));button.querySelector('.password-eye')?.classList.toggle('hidden',visible);button.querySelector('.password-eye-off')?.classList.toggle('hidden',!visible)}));
const privacyDialog=document.getElementById('privacy-notice');
document.querySelectorAll('[data-privacy-open]').forEach(button=>button.addEventListener('click',()=>privacyDialog?.showModal()));
privacyDialog?.addEventListener('click',event=>{if(event.target===privacyDialog)privacyDialog.close()});
document.querySelectorAll('[data-org]').forEach(link=>link.addEventListener('click',()=>{const select=document.getElementById('organization');if(select)select.value=link.dataset.org}));
const search=document.getElementById('organization-search');
const cards=[...document.querySelectorAll('.organization-card')];
let activeFilter='all';
function filterOrganizations(){const term=(search?.value||'').trim().toLowerCase();let shown=0;cards.forEach(card=>{const matchesText=card.dataset.name.includes(term);const matchesCategory=activeFilter==='all'||card.dataset.category===activeFilter;card.classList.toggle('hidden',!matchesText||!matchesCategory);if(matchesText&&matchesCategory)shown++});document.getElementById('organization-no-results')?.classList.toggle('hidden',shown!==0)}
search?.addEventListener('input',filterOrganizations);
document.querySelectorAll('.filter-chip').forEach(button=>button.addEventListener('click',()=>{activeFilter=button.dataset.filter;document.querySelectorAll('.filter-chip').forEach(chip=>chip.classList.toggle('active',chip===button));filterOrganizations()}));
document.querySelectorAll('[data-application-tab]').forEach(button=>button.addEventListener('click',()=>{const tablist=button.closest('[role="tablist"]');tablist?.querySelectorAll('[data-application-tab]').forEach(tab=>{const active=tab===button;tab.classList.toggle('active',active);tab.setAttribute('aria-selected',String(active));document.getElementById(tab.getAttribute('aria-controls'))?.classList.toggle('hidden',!active)});document.getElementById(button.getAttribute('aria-controls'))?.focus()}));
document.querySelectorAll('[data-organization-workspace-tab]').forEach(button=>button.addEventListener('click',()=>{const workspace=button.closest('.organization-workspace');if(!workspace)return;workspace.querySelectorAll('[data-organization-workspace-tab]').forEach(tab=>{const active=tab===button;tab.classList.toggle('active',active);tab.setAttribute('aria-selected',String(active));document.getElementById(tab.getAttribute('aria-controls'))?.classList.toggle('hidden',!active)});document.getElementById(button.getAttribute('aria-controls'))?.focus()}));
const root=document.documentElement,themeButton=document.getElementById('theme-toggle');
const savedTheme=localStorage.getItem('iskonnect-theme');
root.dataset.theme=savedTheme==='dark'?'dark':'light';
themeButton?.addEventListener('click',()=>{root.dataset.theme=root.dataset.theme==='dark'?'light':'dark';localStorage.setItem('iskonnect-theme',root.dataset.theme)});
const menuButton=document.getElementById('menu-toggle'),navLinks=document.getElementById('nav-links');
menuButton?.addEventListener('click',()=>{const open=navLinks.classList.toggle('open');menuButton.setAttribute('aria-expanded',String(open))});
navLinks?.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{navLinks.classList.remove('open');menuButton?.setAttribute('aria-expanded','false')}));
document.querySelectorAll('[data-panel-link]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();const target=link.dataset.panelTarget||'register';const button=document.querySelector(`[data-panel="${target}"]`);button?.click();document.querySelector('.auth-card')?.scrollIntoView({behavior:'smooth',block:'center'});}));
function bindPhotoCrop(form){
  const photoInput=form.querySelector('[data-photo-input]'),cropEditor=form.querySelector('[data-crop-editor]'),cropCanvas=form.querySelector('[data-crop-canvas]'),cropZoom=form.querySelector('[data-crop-zoom]'),cropError=form.querySelector('[data-crop-error]');
  if(!photoInput||!cropEditor||!cropCanvas||!cropZoom||!cropError)return;
  const cropContext=cropCanvas.getContext('2d');
  let cropImage=null,cropRotation=0,cropScale=1,cropPanX=0,cropPanY=0,dragStart=null,cropSubmitting=false;
  const showCropError=message=>{cropError.textContent=message;cropError.classList.toggle('hidden',!message)};
  function drawProfileCrop(context,size,pixelRatio,panMultiplier){
    if(!cropImage)return;
    const sideways=Math.abs(cropRotation%180)===90;
    const rotatedWidth=sideways?cropImage.naturalHeight:cropImage.naturalWidth;
    const rotatedHeight=sideways?cropImage.naturalWidth:cropImage.naturalHeight;
    const baseScale=Math.max(size/rotatedWidth,size/rotatedHeight);
    const renderedWidth=rotatedWidth*baseScale*cropScale;
    const renderedHeight=rotatedHeight*baseScale*cropScale;
    let panX=cropPanX*panMultiplier,panY=cropPanY*panMultiplier;
    panX=Math.max(-(renderedWidth-size)/2,Math.min((renderedWidth-size)/2,panX));
    panY=Math.max(-(renderedHeight-size)/2,Math.min((renderedHeight-size)/2,panY));
    if(panMultiplier===1){cropPanX=panX;cropPanY=panY}
    context.fillStyle='#fff';
    context.fillRect(0,0,size*pixelRatio,size*pixelRatio);
    context.save();
    context.translate((size/2+panX)*pixelRatio,(size/2+panY)*pixelRatio);
    context.rotate(cropRotation*Math.PI/180);
    context.scale(baseScale*cropScale*pixelRatio,baseScale*cropScale*pixelRatio);
    context.drawImage(cropImage,-cropImage.naturalWidth/2,-cropImage.naturalHeight/2);
    context.restore();
  }
  function drawCropPreview(){
    if(!cropImage||!cropContext)return;
    const size=cropCanvas.clientWidth;
    if(!size)return;
    const pixelRatio=window.devicePixelRatio||1;
    cropCanvas.width=Math.round(size*pixelRatio);
    cropCanvas.height=Math.round(size*pixelRatio);
    cropContext.setTransform(1,0,0,1,0,0);
    drawProfileCrop(cropContext,size,pixelRatio,1);
  }
  photoInput.addEventListener('change',()=>{
    showCropError('');
    cropImage=null;
    cropEditor.classList.add('hidden');
    const selectedFile=photoInput.files?.[0];
    if(!selectedFile)return;
    if(!['image/jpeg','image/png','image/webp'].includes(selectedFile.type)){showCropError('Choose a JPG, PNG, or WebP image.');photoInput.value='';return}
    if(selectedFile.size>3*1024*1024){showCropError('Profile photos must be 3 MB or smaller.');photoInput.value='';return}
    const imageUrl=URL.createObjectURL(selectedFile);
    const image=new Image();
    image.onload=()=>{URL.revokeObjectURL(imageUrl);if(image.naturalWidth>5000||image.naturalHeight>5000){showCropError('Choose an image no larger than 5000 × 5000 pixels.');photoInput.value='';return}cropImage=image;cropRotation=0;cropScale=1;cropPanX=0;cropPanY=0;cropZoom.value='1';cropEditor.classList.remove('hidden');drawCropPreview()};
    image.onerror=()=>{URL.revokeObjectURL(imageUrl);showCropError('This image could not be opened. Choose a valid JPG, PNG, or WebP image.');photoInput.value=''};
    image.src=imageUrl;
  });
  cropZoom.addEventListener('input',()=>{cropScale=Number(cropZoom.value);drawCropPreview()});
  cropEditor.querySelectorAll('[data-crop-rotate]').forEach(button=>button.addEventListener('click',()=>{cropRotation=(cropRotation+Number(button.dataset.cropRotate)+360)%360;drawCropPreview()}));
  cropEditor.querySelector('[data-crop-reset]')?.addEventListener('click',()=>{cropRotation=0;cropScale=1;cropPanX=0;cropPanY=0;cropZoom.value='1';drawCropPreview()});
  cropCanvas.addEventListener('pointerdown',event=>{if(!cropImage)return;dragStart={x:event.clientX,y:event.clientY,panX:cropPanX,panY:cropPanY};cropCanvas.setPointerCapture(event.pointerId)});
  cropCanvas.addEventListener('pointermove',event=>{if(!dragStart)return;cropPanX=dragStart.panX+event.clientX-dragStart.x;cropPanY=dragStart.panY+event.clientY-dragStart.y;drawCropPreview()});
  cropCanvas.addEventListener('pointerup',()=>{dragStart=null});
  cropCanvas.addEventListener('pointercancel',()=>{dragStart=null});
  window.addEventListener('resize',drawCropPreview);
  form.addEventListener('submit',async event=>{
    if(form.dataset.cropReady==='true'){delete form.dataset.cropReady;return}
    if(cropSubmitting||!photoInput.files?.length)return;
    event.preventDefault();
    if(!cropImage){showCropError('Wait for the crop preview to load, or choose the image again.');return}
    if(typeof DataTransfer==='undefined'){showCropError('This browser cannot prepare the cropped photo. Try a current version of Chrome, Edge, or Firefox.');return}
    cropSubmitting=true;
    showCropError('');
    try{
      const size=512,previewSize=cropCanvas.clientWidth,output=document.createElement('canvas');
      if(!previewSize)throw new Error('The crop preview is not ready. Choose the image again and try once more.');
      output.width=size;output.height=size;
      const outputContext=output.getContext('2d');
      if(!outputContext)throw new Error('Photo cropping is not supported in this browser.');
      drawProfileCrop(outputContext,size,1,size/previewSize);
      const croppedBlob=await new Promise(resolve=>output.toBlob(resolve,'image/jpeg',0.92));
      if(!croppedBlob)throw new Error('Could not prepare the cropped photo. Please try another image.');
      const transfer=new DataTransfer();
      transfer.items.add(new File([croppedBlob],'profile-photo.jpg',{type:'image/jpeg',lastModified:Date.now()}));
      photoInput.files=transfer.files;
      form.dataset.cropReady='true';
      form.requestSubmit(event.submitter||undefined);
    }catch(error){
      showCropError(error instanceof Error?error.message:'Could not prepare the cropped photo. Please try again.');
    }finally{
      cropSubmitting=false;
    }
  });
}
document.querySelectorAll('.photo-crop-form').forEach(bindPhotoCrop);
</script></body></html>
