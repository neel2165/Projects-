<?php
/*
 * Lecture Notes Portal — Single File App (All-in-one)
 * PHP 8+ / MySQL (Laragon/XAMPP)
 */

session_start();
host ='sql102.infinityfree.com'
$dsn = 'if0_39804808_lecture_portal	';
$dbUser = 'if0_39804808';
$dbPass = 'neelpatel12';
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];
$pdo = new PDO($host, $dsn, $dbUser, $dbPass, $options);


define('TEACHER_SECRET', 'teach1234'); // change for production
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
           . "://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";
$UPLOAD_DIR = __DIR__ . '/uploads';
if (!is_dir($UPLOAD_DIR)) { mkdir($UPLOAD_DIR, 0777, true); }
$MAX_FILE_BYTES = 50 * 1024 * 1024; // 50 MB

// *** SCHEMA ***
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','teacher') NOT NULL,
  classroom_code VARCHAR(50) UNIQUE,
  class_title VARCHAR(120),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS lecture_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  subject VARCHAR(120) NOT NULL,
  description TEXT,
  file_path VARCHAR(255) NOT NULL,         -- filesystem path
  original_name VARCHAR(255) NOT NULL,     -- original filename
  mime_type VARCHAR(150) DEFAULT NULL,
  file_size BIGINT DEFAULT NULL,
  uploaded_by INT NOT NULL,
  owner_teacher_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (owner_teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS classroom_students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  teacher_id INT NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pair (student_id, teacher_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

try { $pdo->query("SELECT classroom_code FROM users LIMIT 1"); } catch (Throwable $e) { $pdo->exec("ALTER TABLE users ADD classroom_code VARCHAR(50) UNIQUE"); }
try { $pdo->query("SELECT class_title FROM users LIMIT 1"); } catch (Throwable $e) { $pdo->exec("ALTER TABLE users ADD class_title VARCHAR(120)"); }
try { $pdo->query("SELECT original_name FROM lecture_notes LIMIT 1"); } catch (Throwable $e) { $pdo->exec("ALTER TABLE lecture_notes ADD original_name VARCHAR(255) NOT NULL DEFAULT 'file'"); }
try { $pdo->query("SELECT mime_type FROM lecture_notes LIMIT 1"); } catch (Throwable $e) { $pdo->exec("ALTER TABLE lecture_notes ADD mime_type VARCHAR(150) NULL"); }
try { $pdo->query("SELECT file_size FROM lecture_notes LIMIT 1"); } catch (Throwable $e) { $pdo->exec("ALTER TABLE lecture_notes ADD file_size BIGINT NULL"); }
try { $pdo->query("SELECT owner_teacher_id FROM lecture_notes LIMIT 1"); } catch (Throwable $e) {
  $pdo->exec("ALTER TABLE lecture_notes ADD owner_teacher_id INT NOT NULL DEFAULT 0");
  $pdo->exec("UPDATE lecture_notes ln JOIN users u ON u.id=ln.uploaded_by SET ln.owner_teacher_id = u.id WHERE ln.owner_teacher_id=0 AND u.role='teacher'");
}

// *** HELPERS ***
function post($k, $d=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function getv($k, $d=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function require_login(){ if (empty($_SESSION['user_id'])) { header("Location: ?action=login"); exit; } }
function is_student_of($pdo, $studentId, $teacherId){
  $st = $pdo->prepare("SELECT 1 FROM classroom_students WHERE student_id=? AND teacher_id=?");
  $st->execute([$studentId, $teacherId]);
  return (bool)$st->fetchColumn();
}
function safe($val, $default=''){ return htmlspecialchars((string)($val ?? $default), ENT_QUOTES, 'UTF-8'); }
function rawbasename($n){ return str_replace(['"',"\r","\n"], ['%22','',''], $n); }

// *** REGISTER ***
if (isset($_POST['register'])) {
  $fullname = post('fullname');
  $username = post('username');
  $email = post('email');
  $passwordPlain = post('password');
  $role = post('role', 'student');
  $secret = post('secret_code');

  if ($role === 'teacher' && $secret !== TEACHER_SECRET) {
    $error = "Invalid teacher secret code.";
  }

  if (empty($error)) {
    $st = $pdo->prepare("SELECT 'u' AS t FROM users WHERE username=? UNION SELECT 'e' FROM users WHERE email=? LIMIT 1");
    $st->execute([$username, $email]);
    $found = $st->fetchColumn();
    if ($found === 'u') $error = "Username already exists. Please choose another.";
    elseif ($found === 'e') $error = "Email already registered. Try logging in.";
  }

  if (empty($error)) {
    $classroom_code = ($role === 'teacher') ? bin2hex(random_bytes(4)) : null;
    $password = password_hash($passwordPlain, PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role, classroom_code, class_title) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->execute([$fullname, $username, $email, $password, $role, $classroom_code, $role==='teacher' ? 'My Class' : null]);
    $_SESSION['message'] = "Registration successful! Please login.";
    header("Location: ?action=login"); exit;
  }
}

// *** LOGIN ***
if (isset($_POST['login'])) {
  $identifier = post('identifier');
  $password = post('password');

  $st = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=? LIMIT 1");
  $st->execute([$identifier, $identifier]);
  $user = $st->fetch();

  if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['classroom_code'] = $user['classroom_code'];
    $_SESSION['class_title'] = $user['class_title'];
    header("Location: ?action=dashboard"); exit;
  } else {
    $error = "Invalid username/email or password.";
  }
}

// *** LOGOUT ***
if (getv('action') === 'logout') {
  session_destroy();
  header("Location: ?action=login"); exit;
}

// *** JOIN CLASS (student) ***
if (getv('action') === 'join_classroom' && !empty($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
  $code = getv('code');
  if ($code !== '') {
    $st = $pdo->prepare("SELECT id FROM users WHERE role='teacher' AND classroom_code=? LIMIT 1");
    $st->execute([$code]);
    if ($t = $st->fetch()) {
      $ins = $pdo->prepare("INSERT IGNORE INTO classroom_students (student_id, teacher_id) VALUES (?, ?)");
      $ins->execute([$_SESSION['user_id'], $t['id']]);
      $message = "Joined the classroom!";
    } else { $error = "Invalid classroom code."; }
  }
}

// *** TEACHER actions: update title / regenerate code ***
if (isset($_POST['save_class_title']) && !empty($_SESSION['user_id']) && $_SESSION['role'] === 'teacher') {
  $title = post('class_title', 'My Class');
  $st = $pdo->prepare("UPDATE users SET class_title=? WHERE id=?");
  $st->execute([$title, $_SESSION['user_id']]);
  $_SESSION['class_title'] = $title;
  $message = "Class title updated.";
}
if (isset($_POST['regenerate_code']) && !empty($_SESSION['user_id']) && $_SESSION['role'] === 'teacher') {
  $new = bin2hex(random_bytes(4));
  $st = $pdo->prepare("UPDATE users SET classroom_code=? WHERE id=?");
  $st->execute([$new, $_SESSION['user_id']]);
  $_SESSION['classroom_code'] = $new;
  $message = "Class code regenerated.";
}

// *** UPLOAD (to class) — allowed only for teacher or student who is member ***
// NOTE: UI only shows upload form for teachers, but server-side still validates membership.
if (isset($_POST['upload_to_class']) && !empty($_SESSION['user_id'])) {
  $targetTeacherId = (int)post('teacher_id');
  $allowed = false;
  if ($_SESSION['role'] === 'teacher' && $_SESSION['user_id'] === $targetTeacherId) $allowed = true;
  if ($_SESSION['role'] === 'student' && is_student_of($pdo, $_SESSION['user_id'], $targetTeacherId)) $allowed = true;

  if (!$allowed) {
    $error = "You are not allowed to upload to this class.";
  } else {
    $title = post('title');
    $subject = post('subject','General');
    $description = post('description');

    if (!isset($_FILES['note_file']) || $_FILES['note_file']['error'] !== UPLOAD_ERR_OK) {
      $error = "Please choose a file to upload.";
    } elseif ($_FILES['note_file']['size'] > $MAX_FILE_BYTES) {
      $error = "File is too large. Max 50 MB.";
    } else {
      $orig = $_FILES['note_file']['name'];
      $safeOrig = preg_replace('/[^\w\-. ]+/', '_', $orig);
      $randName = bin2hex(random_bytes(8)) . '_' . $safeOrig;
      $dest = $UPLOAD_DIR . '/' . $randName;

      if (move_uploaded_file($_FILES['note_file']['tmp_name'], $dest)) {
        $mime = $_FILES['note_file']['type'] ?? null;
        $size = (int)$_FILES['note_file']['size'];

        $st = $pdo->prepare("
          INSERT INTO lecture_notes (title, subject, description, file_path, original_name, mime_type, file_size, uploaded_by, owner_teacher_id)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([$title, $subject, $description, $dest, $safeOrig, $mime, $size, $_SESSION['user_id'], $targetTeacherId]);
        $message = "File uploaded to class.";
        header("Location: ?action=class&teacher_id=".(int)$targetTeacherId);
        exit;
      } else { $error = "Upload failed."; }
    }
  }
}

// *** EDIT FILE (teacher owner only) ***
if (isset($_POST['edit_file']) && !empty($_SESSION['user_id'])) {
  $noteId = (int)post('note_id');

  $st = $pdo->prepare("SELECT * FROM lecture_notes WHERE id=? LIMIT 1");
  $st->execute([$noteId]);
  $note = $st->fetch();
  if (!$note) { $error = "File not found."; }
  else {
    $teacherOwnerId = (int)$note['owner_teacher_id'];

    if (!($_SESSION['role'] === 'teacher' && $_SESSION['user_id'] === $teacherOwnerId)) {
      $error = "You are not allowed to edit this file.";
    } else {
      $title = post('title');
      $subject = post('subject','General');
      $description = post('description');

      $updatePath = $note['file_path'];
      $updateOrig = $note['original_name'];
      $updateMime = $note['mime_type'];
      $updateSize = $note['file_size'];

      // Optional replacement
      if (isset($_FILES['replace_file']) && $_FILES['replace_file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['replace_file']['size'] > $MAX_FILE_BYTES) {
          $error = "Replacement file too large.";
        } else {
          $orig2 = $_FILES['replace_file']['name'];
          $safeOrig2 = preg_replace('/[^\w\-. ]+/', '_', $orig2);
          $randName2 = bin2hex(random_bytes(8)) . '_' . $safeOrig2;
          $dest2 = $UPLOAD_DIR . '/' . $randName2;
          if (move_uploaded_file($_FILES['replace_file']['tmp_name'], $dest2)) {
            if (is_file($note['file_path'])) @unlink($note['file_path']);
            $updatePath = $dest2;
            $updateOrig = $safeOrig2;
            $updateMime = $_FILES['replace_file']['type'] ?? null;
            $updateSize = (int)$_FILES['replace_file']['size'];
          } else { $error = "Replacement upload failed."; }
        }
      }

      if (empty($error)) {
        $st2 = $pdo->prepare("UPDATE lecture_notes SET title=?, subject=?, description=?, file_path=?, original_name=?, mime_type=?, file_size=? WHERE id=?");
        $st2->execute([$title, $subject, $description, $updatePath, $updateOrig, $updateMime, $updateSize, $noteId]);
        $message = "File updated.";
        header("Location: ?action=class&teacher_id=" . $teacherOwnerId);
        exit;
      }
    }
  }
}

// *** DELETE FILE (teacher owner only) ***
if (isset($_POST['delete_file']) && !empty($_SESSION['user_id'])) {
  $noteId = (int)post('note_id');
  $st = $pdo->prepare("SELECT * FROM lecture_notes WHERE id=? LIMIT 1");
  $st->execute([$noteId]);
  $note = $st->fetch();
  if (!$note) { $error = "File not found."; }
  else {
    $teacherOwnerId = (int)$note['owner_teacher_id'];
    if (!($_SESSION['role'] === 'teacher' && $_SESSION['user_id'] === $teacherOwnerId)) {
      $error = "You are not allowed to delete this file.";
    } else {
      $st2 = $pdo->prepare("DELETE FROM lecture_notes WHERE id=?");
      $st2->execute([$noteId]);
      if (is_file($note['file_path'])) @unlink($note['file_path']);
      $message = "File deleted.";
      header("Location: ?action=class&teacher_id=" . $teacherOwnerId);
      exit;
    }
  }
}

// *** DOWNLOAD ***
if (getv('action') === 'download') {
  require_login();
  $id = (int)getv('id', 0);

  $st = $pdo->prepare("SELECT * FROM lecture_notes WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); exit('Not found'); }

  $teacherOwnerId = (int)$row['owner_teacher_id'];
  $allowed = false;
  if ($_SESSION['role'] === 'teacher' && $_SESSION['user_id'] === $teacherOwnerId) $allowed = true;
  if ($_SESSION['role'] === 'student' && is_student_of($pdo, $_SESSION['user_id'], $teacherOwnerId)) $allowed = true;
  if (!$allowed) { http_response_code(403); exit('Forbidden'); }

  $path = $row['file_path'];
  if (!is_file($path)) { http_response_code(404); exit('File missing'); }
  $mime = $row['mime_type'] ?: 'application/octet-stream';
  $name = $row['original_name'] ?: basename($path);

  header('Content-Description: File Transfer');
  header('Content-Type: ' . $mime);
  header('Content-Disposition: attachment; filename="' . rawbasename($name) . '"');
  header('Content-Length: ' . filesize($path));
  header('Cache-Control: private');
  readfile($path);
  exit;
}

// *** QUERIES FOR DASHBOARD/CLASS VIEWS ***
function totalClasses($pdo) {
  $st = $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND classroom_code IS NOT NULL");
  return (int)$st->fetchColumn();
}
function teacherCardData($pdo, $teacherId) {
  $st = $pdo->prepare("
    SELECT u.id, u.fullname, u.username, u.class_title, u.classroom_code,
           (SELECT COUNT(*) FROM lecture_notes ln WHERE ln.owner_teacher_id = u.id) AS notes_count
    FROM users u WHERE u.id=? AND u.role='teacher' LIMIT 1
  ");
  $st->execute([$teacherId]);
  return $st->fetch();
}
function studentClassCards($pdo, $studentId) {
  $st = $pdo->prepare("
    SELECT u.id, u.fullname, u.username, u.class_title, u.classroom_code,
           (SELECT COUNT(*) FROM lecture_notes ln WHERE ln.owner_teacher_id = u.id) AS notes_count
    FROM classroom_students cs
    JOIN users u ON u.id = cs.teacher_id
    WHERE cs.student_id = ?
    ORDER BY u.fullname
  ");
  $st->execute([$studentId]);
  return $st->fetchAll();
}
function classMembers($pdo, $teacherId) {
  $out = ['teacher'=>null,'students'=>[]];
  $st = $pdo->prepare("SELECT id, fullname, username, class_title, classroom_code FROM users WHERE id=? AND role='teacher' LIMIT 1");
  $st->execute([$teacherId]);
  $out['teacher'] = $st->fetch();
  $st = $pdo->prepare("
    SELECT u.id, u.fullname, u.username
    FROM classroom_students cs JOIN users u ON u.id=cs.student_id
    WHERE cs.teacher_id=? ORDER BY u.fullname
  ");
  $st->execute([$teacherId]);
  $out['students'] = $st->fetchAll();
  return $out;
}
function classFiles($pdo, $teacherId) {
  $st = $pdo->prepare("
    SELECT ln.*, u.fullname AS uploader_name, u.username AS uploader_username
    FROM lecture_notes ln
    JOIN users u ON u.id = ln.uploaded_by
    WHERE ln.owner_teacher_id = ?
    ORDER BY ln.created_at DESC
  ");
  $st->execute([$teacherId]);
  return $st->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Lecture Notes Portal</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f6f8fb}
.navbar-brand{font-weight:700}
.card-class{
  color:#fff; border:0; border-radius:16px;
  background: linear-gradient(135deg, var(--c1), var(--c2));
  box-shadow:0 10px 20px rgba(0,0,0,.08);
}
.card-class .class-avatar{
  width:44px; height:44px; border-radius:50%; background:#fff2; display:flex; align-items:center; justify-content:center; font-weight:700;
}
.card-class .class-actions a{ color:#fff; opacity:.95; text-decoration:none; margin-right:12px; }
.badge-pill{border-radius:999px; padding:.5rem .8rem;}
.table-sm td, .table-sm th { padding:.4rem .5rem; }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark navbar-expand">
  <div class="container">
    <a class="navbar-brand" href="?action=dashboard">Lecture Notes</a>
    <div class="ms-auto">
      <ul class="navbar-nav align-items-center">
        <?php if(!empty($_SESSION['user_id'])): ?>
        <li class="nav-item me-3 text-white">Welcome, <strong><?php echo safe($_SESSION['fullname']); ?></strong></li>
        <li class="nav-item"><a class="nav-link" href="?action=dashboard">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="?action=profile">Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="?action=logout">Logout</a></li>
        <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="?action=login">Login</a></li>
        <li class="nav-item"><a class="nav-link" href="?action=register">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4">
  <?php if(!empty($message)): ?><div class="alert alert-success"><?php echo safe($message); ?></div><?php endif; ?>
  <?php if(!empty($error)):   ?><div class="alert alert-danger"><?php echo safe($error); ?></div><?php endif; ?>

  <?php
  $action = getv('action', (empty($_SESSION['user_id']) ? 'login' : 'dashboard'));

  // *** REGISTER VIEW ***
  if ($action === 'register'): ?>
    <div class="row justify-content-center">
      <div class="col-md-6">
        <h3 class="mb-3">Create Account</h3>
        <form method="post" class="card card-body shadow-sm">
          <div class="mb-2"><label class="form-label">Full Name</label><input class="form-control" name="fullname" required></div>
          <div class="mb-2"><label class="form-label">Username</label><input class="form-control" name="username" required></div>
          <div class="mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
          <div class="mb-2"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
          <div class="mb-2">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" id="roleSelect"
                    onchange="document.getElementById('secretWrap').style.display=this.value==='teacher'?'block':'none'">
              <option value="teacher">Teacher</option>
              <option value="student" selected>Student</option>
            </select>
          </div>
          <div id="secretWrap" class="mb-2" style="display:none">
            <label class="form-label">Teacher Secret Code</label>
            <input class="form-control" name="secret_code" placeholder="Enter teacher secret">
          </div>
          <button class="btn btn-primary" name="register">Register</button>
        </form>
        <div class="text-muted mt-2">Already have an account? <a href="?action=login">Login</a></div>
      </div>
    </div>

  <?php /* ---------- LOGIN ---------- */ elseif ($action === 'login'): ?>
    <div class="row justify-content-center">
      <div class="col-md-6">
        <h3 class="mb-3">Login</h3>
        <?php if(!empty($_SESSION['message'])){ echo '<div class="alert alert-info">'.safe($_SESSION['message']).'</div>'; unset($_SESSION['message']); } ?>
        <form method="post" class="card card-body shadow-sm">
          <div class="mb-2"><label class="form-label">Username or Email</label><input class="form-control" name="identifier" required></div>
          <div class="mb-2"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
          <button class="btn btn-success" name="login">Login</button>
        </form>
        <div class="text-muted mt-2">No account? <a href="?action=register">Register</a></div>
      </div>
    </div>

  <?php /* ---------- PROFILE ---------- */ elseif ($action === 'profile'): require_login(); ?>
    <h3 class="mb-3">Profile</h3>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <p><strong>Name:</strong> <?php echo safe($_SESSION['fullname']); ?></p>
            <p><strong>Username:</strong> <?php echo safe($_SESSION['username']); ?></p>
            <p><strong>Role:</strong> <?php echo safe($_SESSION['role']); ?></p>
            <?php if($_SESSION['role']==='teacher'):
              $code = $_SESSION['classroom_code'] ?? '';
              $codeText = $code !== '' ? $code : 'Not set yet';
              $joinLink = $baseUrl . '?action=join_classroom&code=' . urlencode($code);
            ?>
              <p><strong>Class Code:</strong> <code><?php echo safe($codeText); ?></code></p>
              <p><strong>Join Link:</strong>
                <?php if ($code !== ''): ?>
                  <a target="_blank" href="<?php echo safe($joinLink); ?>"><?php echo safe($joinLink); ?></a>
                <?php else: ?>
                  <?php echo safe('Not available until code is generated.'); ?>
                <?php endif; ?>
              </p>

              <form method="post" class="mt-3 d-flex gap-2">
                <input class="form-control" name="class_title" value="<?php echo safe($_SESSION['class_title'] ?? 'My Class'); ?>">
                <button class="btn btn-primary" name="save_class_title">Save Title</button>
                <button class="btn btn-outline-secondary" name="regenerate_code" onclick="return confirm('Regenerate the class code? existing links will stop working.');">Regenerate Code</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  <?php /* ---------- CLASS VIEW ---------- */ elseif ($action === 'class'): require_login();
    $teacherId = (int)getv('teacher_id', 0);
    if (!$teacherId) { echo '<div class="alert alert-danger">Invalid class.</div>'; }
    else {
      $canView = false;
      if ($_SESSION['role'] === 'teacher' && $_SESSION['user_id'] === $teacherId) $canView = true;
      if ($_SESSION['role'] === 'student' && is_student_of($pdo, $_SESSION['user_id'], $teacherId)) $canView = true;

      if (!$canView) {
        echo '<div class="alert alert-danger">You are not a member of this class.</div>';
      } else {
        $members = classMembers($pdo, $teacherId);
        if (!$members['teacher']) {
          echo '<div class="alert alert-danger">Class not found.</div>';
        } else {
          $t = $members['teacher'];
          $files = classFiles($pdo, $teacherId);
  ?>
    <div class="d-flex align-items-center mb-3">
      <h3 class="me-3 mb-0"><?php echo safe($t['class_title'] ?: 'Class'); ?></h3>
      <span class="badge bg-primary badge-pill">Code: <?php echo safe($t['classroom_code'] ?? 'Not set yet'); ?></span>
    </div>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h6 class="mb-3">Members</h6>
            <div class="mb-2"><strong>Teacher:</strong> <?php echo safe($t['fullname']); ?> (@<?php echo safe($t['username']); ?>)</div>
            <div class="small text-muted mb-2"><?php echo count($members['students']); ?> students</div>
            <?php if ($members['students']): ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($members['students'] as $s): ?>
                  <li class="list-group-item py-2">
                    <?php echo safe($s['fullname']); ?> <span class="text-muted">@<?php echo safe($s['username']); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="alert alert-light border small mb-0">No students joined yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <?php if ($_SESSION['role'] === 'teacher' && $_SESSION['user_id'] === (int)$t['id']): ?>
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h6 class="mb-3">Upload a File to This Class</h6>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="teacher_id" value="<?php echo (int)$teacherId; ?>">
              <div class="row g-2">
                <div class="col-md-4"><input class="form-control" name="title" placeholder="Title" required></div>
                <div class="col-md-3"><input class="form-control" name="subject" placeholder="Subject" required></div>
                <div class="col-md-5"><input class="form-control" type="file" name="note_file" required></div>
                <div class="col-12"><textarea class="form-control" name="description" placeholder="Description (optional)"></textarea></div>
              </div>
              <div class="mt-2">
                <button class="btn btn-primary" name="upload_to_class">Upload</button>
                <span class="text-muted small ms-2">Max 50 MB. Any file type.</span>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-3">Class Files</h6>
            <?php if ($files): ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead><tr><th>Title</th><th>Subject</th><th>Uploaded by</th><th>Size</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($files as $n): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?php echo safe($n['title']); ?></div>
                          <div class="small text-muted"><?php echo safe($n['original_name']); ?></div>
                        </td>
                        <td class="small"><?php echo safe($n['subject']); ?></td>
                        <td class="small">
                          <?php echo safe($n['uploader_name']); ?> <span class="text-muted">@<?php echo safe($n['uploader_username']); ?></span>
                          <div class="text-muted small"><?php echo safe(date('Y-m-d H:i', strtotime($n['created_at']))); ?></div>
                        </td>
                        <td class="small">
                          <?php if(!empty($n['file_size'])) echo number_format($n['file_size']/1024, 1) . ' KB'; ?>
                        </td>
                        <td class="text-end">
                          <a class="btn btn-outline-primary btn-sm" href="<?php echo $baseUrl.'?action=download&id='.(int)$n['id']; ?>">Download</a>
                          <?php if($_SESSION['role'] === 'teacher' && $_SESSION['user_id'] === (int)$t['id']): ?>
                            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo (int)$n['id']; ?>">Edit</button>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this file?');">
                              <input type="hidden" name="note_id" value="<?php echo (int)$n['id']; ?>">
                              <button class="btn btn-outline-danger btn-sm" name="delete_file">Delete</button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>

                      <?php if($_SESSION['role'] === 'teacher' && $_SESSION['user_id'] === (int)$t['id']): ?>
                      <div class="modal fade" id="editModal<?php echo (int)$n['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                          <div class="modal-content">
                            <form method="post" enctype="multipart/form-data" class="modal-form">
                              <div class="modal-header">
                                <h5 class="modal-title">Edit file</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body">
                                <input type="hidden" name="note_id" value="<?php echo (int)$n['id']; ?>">
                                <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" value="<?php echo safe($n['title']); ?>" required></div>
                                <div class="mb-2"><label class="form-label">Subject</label><input class="form-control" name="subject" value="<?php echo safe($n['subject']); ?>" required></div>
                                <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description"><?php echo safe($n['description']); ?></textarea></div>
                                <div class="mb-2"><label class="form-label">Replace file (optional)</label><input type="file" class="form-control" name="replace_file"></div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button class="btn btn-primary" name="edit_file">Save changes</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                      <?php endif; ?>

                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light border mb-0">No files uploaded yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php
        }
      }
    }
  ?>

  <?php /* ---------- DASHBOARD ---------- */
  elseif ($action === 'dashboard'): require_login();
    $role = $_SESSION['role'];
    $total = totalClasses($pdo); ?>

    <div class="d-flex align-items-center mb-3">
      <h3 class="me-3 mb-0">Dashboard</h3>
      <span class="badge bg-primary badge-pill">Total Classes: <?php echo (int)$total; ?></span>
    </div>

    <?php if ($role === 'teacher'):
      $card = teacherCardData($pdo, $_SESSION['user_id']);
      $colors = ['--c1:#e31b63;--c2:#ff7a59','--c1:#2a9d8f;--c2:#20b2aa','--c1:#6a5acd;--c2:#8a2be2','--c1:#0ea5e9;--c2:#22c55e'];
      $style = $colors[$_SESSION['user_id'] % count($colors)];
      ?>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="card card-class p-3" style="<?php echo safe($style); ?>">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fs-5 fw-bold"><?php echo safe($card['class_title'] ?: 'My Class'); ?></div>
                <div class="small"><?php echo safe($card['fullname']); ?> • @<?php echo safe($card['username']); ?></div>
              </div>
              <div class="class-avatar"><?php echo safe(strtoupper(substr($card['fullname'],0,1))); ?></div>
            </div>
            <div class="mt-3">
              <div class="small">Code: <code><?php echo safe($card['classroom_code'] ?? 'Not set yet'); ?></code></div>
              <div class="small">Files: <?php echo (int)$card['notes_count']; ?></div>
              <div class="class-actions mt-2">
                <a href="<?php echo $baseUrl.'?action=class&teacher_id='.(int)$card['id']; ?>">Open class</a>
                <a href="<?php echo $baseUrl.'?action=profile'; ?>">Class settings</a>
              </div>
            </div>
          </div>
        </div>
      </div>

    <?php else:
      $cards = studentClassCards($pdo, $_SESSION['user_id']); ?>
      <div class="row g-3 align-items-stretch">
        <?php
        $pal = [
          ['#e31b63','#ff7a59'],['#2a9d8f','#20b2aa'],['#6a5acd','#8a2be2'],
          ['#0ea5e9','#22c55e'],['#f59e0b','#ef4444']
        ];
        if ($cards) {
          $i=0;
          foreach ($cards as $c) {
            $p = $pal[$i % count($pal)]; $i++;
            echo '<div class="col-sm-6 col-lg-4">'
              .'<div class="card card-class p-3 h-100" style="--c1:'.$p[0].';--c2:'.$p[1].'">'
                .'<div class="d-flex justify-content-between align-items-start">'
                  .'<div>'
                    .'<div class="fs-5 fw-bold">'.safe($c['class_title']?:'Class').'</div>'
                    .'<div class="small">'.safe($c['fullname']).' • @'.safe($c['username']).'</div>'
                  .'</div>'
                  .'<div class="class-avatar">'.safe(strtoupper(substr($c['fullname'],0,1))).'</div>'
                .'</div>'
                .'<div class="mt-3 small">Files: '.(int)$c['notes_count'].'</div>'
                .'<div class="class-actions mt-2">'
                  .'<a href="'.$baseUrl.'?action=class&teacher_id='.(int)$c['id'].'">Open class</a>'
                .'</div>'
              .'</div>'
            .'</div>';
          }
        } else {
          echo '<div class="col-12"><div class="alert alert-info">You have not joined any classes yet.</div></div>';
        }
        ?>
      </div>

      <h5 class="mt-4">Join a Classroom</h5>
      <form method="get" class="card card-body shadow-sm mb-4">
        <input type="hidden" name="action" value="join_classroom">
        <div class="row g-2 align-items-center">
          <div class="col-md-6"><input class="form-control" name="code" placeholder="Enter classroom code" required></div>
          <div class="col-md-3"><button class="btn btn-warning w-100">Join</button></div>
        </div>
      </form>
    <?php endif; ?>

  <?php  else: echo '<div class="alert alert-secondary">Unknown action.</div>'; endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
