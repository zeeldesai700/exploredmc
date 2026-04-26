<?php
// index.php - Login page + handler
if (session_status() === PHP_SESSION_NONE) session_start();

// correct path to DB
require_once __DIR__ . '/config/db.php';

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($user = $res->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {

            // SET SESSION CORRECTLY
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];  // important fix
            $_SESSION['role'] = $user['role'];

            // redirect to dashboard
            header('Location: public/dashboard.php');
            exit;
        }
    }

    $err = 'Invalid email or password';
}

$page_title = 'Login';
include __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="mb-3 text-center">Login</h4>

          <?php if (!empty($err)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>

            <button class="btn btn-primary w-100" type="submit">Login</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
