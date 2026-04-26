<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

// Handle create/update/delete
$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? 'India');
    $notes = trim($_POST['notes'] ?? '');

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE customers SET name=?, phone=?, email=?, address=?, city=?, state=?, country=?, notes=? WHERE id=?");
        $stmt->bind_param('ssssssssi', $name, $phone, $email, $address, $city, $state, $country, $notes, $id);
        $stmt->execute();
        header('Location:customers.php?m=updated');
        exit;
    } else {
        $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, address, city, state, country, notes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssss', $name, $phone, $email, $address, $city, $state, $country, $notes);
        $stmt->execute();
        header('Location:customers.php?m=added');
        exit;
    }
}

if ($action === 'delete' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM customers WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location:customers.php?m=deleted');
    exit;
}

// Fetch customers
$rows = [];
$res = $conn->query("SELECT * FROM customers ORDER BY id DESC");
while ($r = $res->fetch_assoc()) $rows[] = $r;

$editRow = null;
if ($action === 'edit' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
}
?>
<?php $page_title = 'Customers'; include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/nav.php'; ?>
<div class="container mt-4">
  <div class="row g-3">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="mb-3"><?php echo $editRow ? 'Edit Customer' : 'Add Customer'; ?></h5>
          <form method="post">
            <input type="hidden" name="id" value="<?php echo (int)($editRow['id'] ?? 0); ?>">
            <div class="mb-2">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($editRow['name'] ?? ''); ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($editRow['phone'] ?? ''); ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editRow['email'] ?? ''); ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control"><?php echo htmlspecialchars($editRow['address'] ?? ''); ?></textarea>
            </div>
            <div class="row">
              <div class="col-md-4 mb-2">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($editRow['city'] ?? ''); ?>">
              </div>
              <div class="col-md-4 mb-2">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($editRow['state'] ?? ''); ?>">
              </div>
              <div class="col-md-4 mb-2">
                <label class="form-label">Country</label>
                <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($editRow['country'] ?? 'India'); ?>">
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control"><?php echo htmlspecialchars($editRow['notes'] ?? ''); ?></textarea>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-primary"><?php echo $editRow ? 'Update' : 'Add'; ?></button>
              <?php if ($editRow): ?>
              <a class="btn btn-secondary" href="customers.php">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="mb-3">All Customers</h5>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead><tr>
                <th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>City</th><th>Actions</th>
              </tr></thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo (int)$r['id']; ?></td>
                  <td><?php echo htmlspecialchars($r['name']); ?></td>
                  <td><?php echo htmlspecialchars($r['phone']); ?></td>
                  <td><?php echo htmlspecialchars($r['email']); ?></td>
                  <td><?php echo htmlspecialchars($r['city']); ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="customers.php?action=edit&id=<?php echo (int)$r['id']; ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="customers.php?action=delete&id=<?php echo (int)$r['id']; ?>" onclick="return confirm('Delete this customer?')">Delete</a>
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
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
