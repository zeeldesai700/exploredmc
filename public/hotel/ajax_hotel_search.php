<?php
require_once __DIR__ . '/../../config/db.php';

$q = trim($_GET['q'] ?? '');

$where = '';
if ($q !== '') {
    $safe = $conn->real_escape_string($q);
    $where = "WHERE h.name LIKE '%$safe%'";
}

$sql = "
SELECT h.*, c.name AS country_name, ci.name AS city_name
FROM hotels h
LEFT JOIN countries c ON h.country_id = c.id
LEFT JOIN cities ci ON h.city_id = ci.id
$where
ORDER BY h.id DESC
";

$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo '<tr><td colspan="9" class="text-center text-muted">No hotels found</td></tr>';
    exit;
}

while ($row = $result->fetch_assoc()) {
?>
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
           onclick="return confirm('Are you sure?');">
           🗑️ Delete
        </a>
    </td>
</tr>
<?php } ?>
