<?php
require_once __DIR__ . '/../../config/db.php';

/* ================= INPUTS ================= */
$city_id   = (int)($_GET['city_id'] ?? 0);
$category  = $_GET['category'] ?? '';
$from_date = $_GET['from_date'] ?? null;
$to_date   = $_GET['to_date'] ?? null;

if (!$city_id || !$category || !$from_date || !$to_date) {
    echo "<div class='text-danger p-3'>Invalid request</div>";
    exit;
}

$categoryEsc = mysqli_real_escape_string($conn, $category);

/* ================= DATE LOGIC ================= */
$from = new DateTime($from_date);
$to   = new DateTime($to_date);

$nights = (int)$from->diff($to)->days;
if ($nights <= 0) {
    echo "<div class='text-danger p-3'>Invalid date range</div>";
    exit;
}

/* ================= ADD HOTEL BUTTON ================= */
echo "
<div class='d-flex justify-content-between align-items-center mb-2'>
  <div class='text-muted small'>Hotel not found?</div>
  <button type='button' class='btn btn-outline-primary btn-sm' id='addHotelBtn'>
    + Add New Hotel
  </button>
</div>
";

/* ================= ROOMS ================= */
$sql = "
SELECT
  h.id AS hotel_id,
  h.name AS hotel_name,
  r.id AS room_id,
  r.room_category
FROM hotels h
JOIN hotel_rooms r ON r.hotel_id = h.id
WHERE h.city_id = $city_id
  AND h.category = '$categoryEsc'
ORDER BY h.name, r.room_category
";

$res = $conn->query($sql);
if (!$res || !$res->num_rows) {
    echo "<div class='text-muted text-center p-3'>No hotels found</div>";
    exit;
}

$currentHotel = '';
$minPrice = null;

while ($row = $res->fetch_assoc()) {

    $prices = $extraAdults = $extraChildren = $extraNoBeds = [];
    $seasonRows = [];

    /* ================= NIGHT LOOP ================= */
    for ($i = 0; $i < $nights; $i++) {

        $nightDate = (clone $from)->modify("+{$i} days")->format('Y-m-d');

        $seasonSql = "
            SELECT room_price, extra_adult, extra_child, no_bed_child, date_from, date_to
            FROM hotel_room_seasons
            WHERE room_id = {$row['room_id']}
              AND '$nightDate' BETWEEN date_from AND date_to
            ORDER BY date_from DESC
            LIMIT 1
        ";

        $seasonRes = $conn->query($seasonSql);
        if ($seasonRes && $seasonRes->num_rows) {
            $s = $seasonRes->fetch_assoc();

            // pricing arrays (for JS & total)
            $prices[]        = (float)$s['room_price'];
            $extraAdults[]   = (float)$s['extra_adult'];
            $extraChildren[] = (float)$s['extra_child'];
            $extraNoBeds[]   = (float)$s['no_bed_child'];

            // ✅ UNIQUE SEASON (date_from + date_to)
            $key = $s['date_from'] . '|' . $s['date_to'];
            if (!isset($seasonRows[$key])) {
                $seasonRows[$key] = $s;
            }
        }
    }

    if (!$prices) continue;

    $seasonRows = array_values($seasonRows); // re-index
    $sum = array_sum($prices);

    /* ================= HOTEL HEADER ================= */
    if ($currentHotel !== $row['hotel_name']) {

        if ($currentHotel !== '') echo "</tbody></table>";

        $currentHotel = $row['hotel_name'];
        $minPrice = null;

        echo "<h6 class='fw-bold mt-3'>" . htmlspecialchars($row['hotel_name']) . "</h6>";
        echo "
        <table class='table table-sm table-bordered'>
          <thead class='table-light'>
            <tr>
              <th>Room</th>
              <th class='text-end'>Price / Night</th>
              <th class='text-end'>Extra Adult</th>
              <th class='text-end'>Extra Child</th>
              <th class='text-end'>No Bed</th>
              <th>From</th>
              <th>To</th>
              <th class='text-center'>Select</th>
            </tr>
          </thead>
          <tbody>";
    }

    if ($minPrice === null || $sum < $minPrice) $minPrice = $sum;
    $badge = ($sum == $minPrice) ? "<span class='badge bg-success ms-1'>LOWEST</span>" : "";

    /* ================= SAFE JSON FOR JS ================= */
    $p  = htmlspecialchars(json_encode($prices), ENT_QUOTES);
    $ea = htmlspecialchars(json_encode($extraAdults), ENT_QUOTES);
    $ec = htmlspecialchars(json_encode($extraChildren), ENT_QUOTES);
    $nb = htmlspecialchars(json_encode($extraNoBeds), ENT_QUOTES);

    $rowspan = count($seasonRows);

    /* ================= SEASON ROWS ================= */
    foreach ($seasonRows as $k => $s) {

        echo "<tr class='hotel-room-row'
          data-hotel-id='{$row['hotel_id']}'
          data-room-id='{$row['room_id']}'>";


        // ROOM CELL (only once)
        if ($k === 0) {
            echo "
            <td rowspan='{$rowspan}'>
              <strong>" . htmlspecialchars($row['room_category']) . "</strong>
              $badge
              <div class='text-muted small'>
                Total {$nights} nights : $ " . number_format($sum, 2) . "
              </div>
            </td>";
        }

        echo "
        <td class='text-end'>$ " . number_format($s['room_price'], 2) . "</td>
        <td class='text-end'>$ " . number_format($s['extra_adult'], 2) . "</td>
        <td class='text-end'>$ " . number_format($s['extra_child'], 2) . "</td>
        <td class='text-end'>$ " . number_format($s['no_bed_child'], 2) . "</td>
        <td>" . date('d-m-Y', strtotime($s['date_from'])) . "</td>
        <td>" . date('d-m-Y', strtotime($s['date_to'])) . "</td>";

        // SELECT BUTTON (only once)
        if ($k === 0) {
            echo "
            <td rowspan='{$rowspan}' class='text-center align-middle'>
              <button type='button'
                class='btn btn-success btn-sm selectHotelRoom'
                data-hotel-id='{$row['hotel_id']}'
                data-room-id='{$row['room_id']}'
                data-prices='$p'
                data-extra_adults='$ea'
                data-extra_children='$ec'
                data-extra_nobeds='$nb'>
                Select
              </button>
            </td>";
        }

        echo "</tr>";
    }
}

echo "</tbody></table>";
?>
<!-- ADD HOTEL MODAL -->
<div class="modal fade" id="addHotelModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Add New Hotel</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="addHotelFrame" style="width:100%;height:80vh;border:0"></iframe>
      </div>
    </div>
  </div>
</div>

<script>
let addHotelModalInstance = null;

/* ================= OPEN ADD HOTEL POPUP ================= */
$(document).on("click", "#addHotelBtn", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const url =
        "../hotel/add_hotel.php" +
        "?city_id=<?= (int)$city_id ?>" +
        "&category=<?= urlencode($categoryEsc) ?>";

    $("#addHotelFrame").attr("src", url);

    const modalEl = document.getElementById("addHotelModal");

    // Bootstrap 5 modal instance
    addHotelModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl, {
        backdrop: "static",
        keyboard: false
    });

    addHotelModalInstance.show();
});


/* ================= HANDLE POPUP MESSAGES ================= */
window.addEventListener("message", function (e) {

    if (e.data === "HOTEL_SAVED") {
        if (addHotelModalInstance) {
            addHotelModalInstance.hide();
        }

        if (typeof loadHotels === "function") {
            loadHotels();
        }
    }

    if (e.data === "HOTEL_CLOSE") {
        if (addHotelModalInstance) {
            addHotelModalInstance.hide();
        }
    }

});


/* ================= BLOCK ❌ BUTTON BUBBLING ================= */
$(document).on("click", "#addHotelModal .btn-close", function (e) {
    e.preventDefault();
    e.stopPropagation();

    if (addHotelModalInstance) {
        addHotelModalInstance.hide();
    }
});
</script>
