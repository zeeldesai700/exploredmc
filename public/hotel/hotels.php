<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
/* ---------------- DELETE HOTEL ---------------- */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->query("DELETE FROM hotels WHERE id=$id");
    $conn->query("DELETE FROM hotel_rooms WHERE hotel_id=$id");
    $msg = "🗑️ Hotel deleted successfully!";
}

/* ---------------- FETCH ALL HOTELS (NO SEARCH HERE) ---------------- */
$result = $conn->query("
    SELECT h.*, c.name AS country_name, ci.name AS city_name
    FROM hotels h
    LEFT JOIN countries c ON h.country_id = c.id
    LEFT JOIN cities ci ON h.city_id = ci.id
    ORDER BY h.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hotel List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">

<div class="container mt-4">

    <?php if (!empty($msg)) { ?>
        <div class="alert alert-info"><?= $msg ?></div>
    <?php } ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added') { ?>
        <div class="alert alert-success">✅ Hotel added successfully!</div>
    <?php } ?>

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Hotels</h3>
        <a href="hotel_add.php" class="btn btn-primary">➕ Add Hotel</a>
    </div>

    <!-- LIVE SEARCH INPUT -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text"
                   id="hotelSearch"
                   class="form-control"
                   placeholder="🔍 Search hotel by name...">
        </div>
    </div>

    <!-- TABLE -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Address</th>
                            <th>Country</th>
                            <th>City</th>
                            <th>Room Categories</th>
                            <th>Added On</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <!-- IMPORTANT ID FOR AJAX -->
                    <tbody id="hotelTableBody">
                        <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= htmlspecialchars($row['address']) ?></td>
                            <td><?= htmlspecialchars($row['country_name']) ?></td>
                            <td><?= htmlspecialchars($row['city_name']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-info"
                                        data-bs-toggle="modal"
                                        data-bs-target="#roomsModal"
                                        data-hotel-id="<?= $row['id'] ?>"
                                        data-hotel-name="<?= htmlspecialchars($row['name']) ?>">
                                    🏨 View Rooms
                                </button>
                            </td>
                            <td><?= $row['created_at'] ?></td>
                            <td class="text-center">
                                <a href="hotel_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">✏️ Edit</a>
                                <a href="hotels.php?delete=<?= $row['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this hotel?');">
                                   🗑️ Delete
                                </a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>

                </table>
            </div>
        </div>
    </div>
</div>

<!-- ROOMS MODAL -->
<div class="modal fade" id="roomsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Room Categories - <span id="hotelName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="roomsContent">Loading...</div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    var roomsModal = document.getElementById('roomsModal');
    roomsModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('hotelName').textContent =
            button.getAttribute('data-hotel-name');

        fetch("hotel_rooms_list.php?hotel_id=" + button.getAttribute('data-hotel-id'))
            .then(res => res.text())
            .then(html => {
                document.getElementById('roomsContent').innerHTML = html;
            });
    });
});
</script>

<!-- LIVE SEARCH AJAX -->
<script>
$(document).ready(function () {

    let timer = null;

    $('#hotelSearch').on('keyup', function () {
        clearTimeout(timer);
        let keyword = $(this).val();

        timer = setTimeout(function () {
            $.ajax({
                url: 'ajax_hotel_search.php',
                method: 'GET',
                data: { q: keyword },
                success: function (data) {
                    $('#hotelTableBody').html(data);
                }
            });
        }, 300);
    });

});
</script>

</body>
</html>
