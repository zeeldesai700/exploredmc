
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/auth.php';
require_login();
if (!is_admin()) {
    http_response_code(403);
    die('Admins only');
}

require_once __DIR__ . '/../../config/db.php';
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

/* =========================
   UTIL: GENERATE PASSWORD
========================= */
function generatePassword($len = 8) {
    return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, $len);
}

/* =========================
   FETCH USER FOR EDIT
========================= */
$editUser = null;
if (($_GET['action'] ?? '') === 'edit' && !empty($_GET['id'])) {
    $eid = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id,name,email,role FROM users WHERE id=?");
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
}

/* =========================
   RESET PASSWORD
========================= */
$resetMsg = '';
if (($_GET['action'] ?? '') === 'reset' && !empty($_GET['id'])) {
    $uid = (int)$_GET['id'];

    $pwd = generatePassword();
    $hash = password_hash($pwd, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param('si', $hash, $uid);
    $stmt->execute();

    $resetMsg = "New password for User ID {$uid}: <b>{$pwd}</b>";
}

/* =========================
   ADD / UPDATE USER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'employee';

    if (!$name || !$email || ($id === 0 && !$password)) {
        die('Required fields missing');
    }

    // check duplicate email
    $chk = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
    $chk->bind_param('si', $email, $id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        die('Email already exists');
    }

    if ($id > 0) {
        // LOCK ADMIN ROLE
        $r = $conn->query("SELECT role FROM users WHERE id=$id")->fetch_assoc();
        if ($r && $r['role'] === 'admin') {
            $role = 'admin';
        }

        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("
                UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?
            ");
            $stmt->bind_param('ssssi', $name, $email, $role, $hash, $id);
        } else {
            $stmt = $conn->prepare("
                UPDATE users SET name=?, email=?, role=? WHERE id=?
            ");
            $stmt->bind_param('sssi', $name, $email, $role, $id);
        }

        $stmt->execute();
        header('Location: users.php?m=updated');
        exit;

    } else {
        // ADD USER
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            INSERT INTO users (name,email,password,role,created_at)
            VALUES (?,?,?,?,NOW())
        ");
        $stmt->bind_param('ssss', $name, $email, $hash, $role);
        $stmt->execute();
        header('Location: users.php?m=added');
        exit;
    }
}

/* =========================
   DELETE USER
========================= */
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    $did = (int)$_GET['id'];
    if ($did === (int)$_SESSION['user_id']) {
        die('Cannot delete yourself');
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param('i', $did);
    $stmt->execute();
    header('Location: users.php?m=deleted');
    exit;
}

/* =========================
   FETCH USERS
========================= */
$rows = [];
$res = $conn->query("SELECT id,name,email,role,created_at FROM users ORDER BY id DESC");
while ($r = $res->fetch_assoc()) $rows[] = $r;

$page_title = 'Users';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>

<div class="container mt-4">

<?php if ($resetMsg): ?>
  <div class="alert alert-warning"><?= $resetMsg ?></div>
<?php endif; ?>

<div class="row g-3">

<!-- ADD / EDIT -->
<div class="col-md-5">
<div class="card shadow-sm">
<div class="card-body">

<h5><?= $editUser ? 'Edit User' : 'Add User' ?></h5>

<form method="post">
<?php if ($editUser): ?>
<input type="hidden" name="id" value="<?= $editUser['id'] ?>">
<?php endif; ?>

<div class="mb-2">
<label>Name</label>
<input class="form-control" name="name" required value="<?= htmlspecialchars($editUser['name'] ?? '') ?>">
</div>

<div class="mb-2">
<label>Email</label>
<input class="form-control" name="email" type="email" required value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
</div>

<div class="mb-2">
<label>Password <?= $editUser ? '(optional)' : '' ?></label>
<input class="form-control" name="password" type="password" <?= $editUser?'':'required' ?>>
</div>

<div class="mb-2">
<label>Role</label>
<select class="form-select" name="role" <?= ($editUser && $editUser['role']=='admin')?'disabled':'' ?>>
  <option value="employee" <?= ($editUser['role']??'')=='employee'?'selected':'' ?>>Employee</option>
  <option value="admin" <?= ($editUser['role']??'')=='admin'?'selected':'' ?>>Admin</option>
</select>
<?php if ($editUser && $editUser['role']=='admin'): ?>
<input type="hidden" name="role" value="admin">
<?php endif; ?>
</div>

<button class="btn btn-primary"><?= $editUser?'Update':'Add' ?></button>
<?php if ($editUser): ?>
<a href="users.php" class="btn btn-secondary ms-2">Cancel</a>
<?php endif; ?>
</form>

</div>
</div>
</div>

<!-- USER LIST -->
<div class="col-md-7">
<div class="card shadow-sm">
<div class="card-body">

<h5>Team</h5>

<table class="table table-sm table-striped">
<thead>
<tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['name']) ?></td>
<td><?= htmlspecialchars($r['email']) ?></td>
<td><?= $r['role'] ?></td>
<td>

<a class="btn btn-sm btn-outline-primary"
   href="users.php?action=edit&id=<?= $r['id'] ?>">Edit</a>

<a class="btn btn-sm btn-outline-warning"
   href="users.php?action=reset&id=<?= $r['id'] ?>"
   onclick="return confirm('Reset password?')">Reset</a>

<?php if ($r['id'] != $_SESSION['user_id']): ?>
<a class="btn btn-sm btn-outline-danger"
   href="users.php?action=delete&id=<?= $r['id'] ?>"
   onclick="return confirm('Delete user?')">Delete</a>
<?php endif; ?>

</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
</div>
</div>

</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
