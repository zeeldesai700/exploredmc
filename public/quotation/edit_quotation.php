<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

/* =====================================================
   GET QUOTATION ID (EDIT MODE ONLY)
===================================================== */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("<div class='alert alert-danger'>Quotation ID missing</div>");
}

/* =====================================================
   FETCH QUOTATION MASTER
===================================================== */
$stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quotation) {
    die("<div class='alert alert-danger'>Quotation not found</div>");
}

/* =====================================================
   FETCH DROPDOWNS
===================================================== */
$customers = $conn->query("SELECT id, name FROM customers ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$countries = $conn->query("SELECT id, name FROM countries ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$cars      = $conn->query("SELECT id, car_name, seater FROM cars ORDER BY car_name")->fetch_all(MYSQLI_ASSOC);

/* =====================================================
   FETCH HOTELS (JS + OPTION WISE COMPATIBLE)
===================================================== */
$hotel_rows = [];

$stmt = $conn->prepare("
    SELECT
        id,
        option_no,
        city_id,
        category AS category_id,
        hotel_id,
        room_category_id,
        stay_nights,
        base_price,
        extra_adult_price,
        child_price,
        nobed_price,
        price
    FROM quotation_hotels
    WHERE quotation_id = ?
    ORDER BY option_no, id
");

$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    $hotel_rows[] = $r;
}

$stmt->close();


/* =====================================================
   FETCH TRAVEL PLAN
===================================================== */
$travel_rows = [];
$stmt = $conn->prepare("
    SELECT *
    FROM quotation_travels
    WHERE quotation_id = ?
    ORDER BY day_no
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $travel_rows[] = $r;
}
$stmt->close();

/* =====================================================
   FETCH ACTIVITIES PER TRAVEL
===================================================== */
$activities_by_travel = [];
$stmt = $conn->prepare("
    SELECT quotation_travel_id, activity_id, activity_price
    FROM quotation_travel_activities
    WHERE quotation_travel_id IN (
        SELECT id FROM quotation_travels WHERE quotation_id = ?
    )
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $tid = (int)$r['quotation_travel_id'];
    $activities_by_travel[$tid]['ids'][] = (int)$r['activity_id'];
    $activities_by_travel[$tid]['total_price'] =
        ($activities_by_travel[$tid]['total_price'] ?? 0) + (float)$r['activity_price'];
}
$stmt->close();

/* =====================================================
   INIT OBJECT FOR JS (EDIT MODE)
===================================================== */
$init = [
    'quotation_no' => $quotation['quotation_no'],
    'customer_id' => (int)$quotation['customer_id'],
    'country_id' => (int)$quotation['country_id'],
    'travel_date' => $quotation['travel_date'],
    'departure_date' => $quotation['departure_date'],
    'car_id' => (int)$quotation['car_id'],
    'adults' => (int)$quotation['adults'],
    'extra_adults' => (int)$quotation['extra_adults'],
    'children' => (int)$quotation['children'],
    'infants' => (int)$quotation['infants'],
    'no_bed_child' => (int)$quotation['no_bed_child'],
    'rooms' => (int)$quotation['rooms'],
    'nights' => (int)$quotation['nights'],
    'days' => (int)$quotation['days'],
    'hotels' => $hotel_rows,
    'travels' => $travel_rows,

    // 🔥 MUST MATCH JS
    'travel_activities' => $activities_by_travel
];

?>
<script>
/* ===== INIT DATA FOR EDIT MODE ===== */
window.initQuotation = <?= json_encode($init, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit Quotation — <?= htmlspecialchars($quotation['quotation_no']) ?></title>

    <!-- Bootstrap (CSS only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- jQuery (required by quotation.js) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- ===== SAME CSS AS CREATE PAGE (UNCHANGED) ===== -->
   <style>
        /* ===== Classic Travel Portal Theme (clean & modern) ===== */
        body {
            background: #f3f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            color: #222;
            font-size: 14px;
        }
        .container { max-width: 1200px; }

        h3 {
            font-weight: 700;
            color: #0f2540;
            margin-bottom: 14px;
        }
        h6.section-title {
            font-weight: 700;
            color: #0f2540;
            border-left: 4px solid #2f7bed;
            padding-left: 10px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .card {
            border-radius: 10px;
            border: 1px solid #e6ebf3;
            box-shadow: 0 2px 10px rgba(18,38,63,0.04);
        }

        /* compact inputs */
        .form-control-sm, .form-select-sm {
            border-radius: 6px;
            padding: .35rem .5rem;
        }

        .controls-row .form-control { max-width: 160px; display: inline-block; }

        .table-sm th, .table-sm td { vertical-align: middle; font-size: 13px; }

        /* Buttons */
        .btn-theme {
            background: linear-gradient(180deg,#2f7bed,#2466d6);
            color: #fff;
            border: none;
            border-radius: 6px;
        }
        .btn-theme:focus, .btn-theme:active { box-shadow: 0 4px 14px rgba(47,123,237,0.2); }

        /* Activity popup items */
        .activity-popup-item {
            border: 1px solid #e6ebf3;
            padding: 10px;
            border-radius: 7px;
            margin-bottom: 8px;
        }
        .activity-popup-item:hover {
            background: #f7fbff;
            border-color: #2f7bed;
        }

        /* cost panel */
        .cost-panel {
            background: #fff;
            padding: 14px;
            border-radius: 10px;
            border: 1px solid #e6ebf3;
            box-shadow: 0 2px 8px rgba(18,38,63,0.03);
            max-width: 320px;
        }

        /* travel plan header style */
        #travelPlan thead th {
            background: #0f2540;
            color: #fff;
            font-weight: 400;
            font-size: 10px;
        }

        /* responsive tweaks */
        @media (max-width: 900px) {
            .controls-row .form-control { max-width: 100%; display: block; }
            .card { padding: 12px; }
        }
       
th:nth-child(11),
th:nth-child(12),
th:nth-child(13),
th:nth-child(14),
td:nth-child(11),
td:nth-child(12),
td:nth-child(13),
td:nth-child(14){
    display:none;
}

.lowest-room {
    background: #eaffea !important;
    border-left: 4px solid #28a745;
}
.lowest-badge {
    background: #28a745;
    color: #fff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 6px;
}
#hotelRoomList {
    max-height: 65vh;     /* visible height */
    overflow-y: auto;    /* enable vertical scroll */
    overflow-x: hidden;
}

#hotelRoomPopup tr.hotel-room-row.active-room > td {
  background-color: #d1e7dd !important;
}

#hotelRoomPopup tr.hotel-room-row.active-room {
  outline: 2px solid #198754;
  outline-offset: -2px;
}

    </style>
</head>

<body class="container py-4">

<h3>Edit Quotation</h3>

<form id="quotationForm"
      method="POST"
      action="update_quotation.php"
      autocomplete="off"
      class="mb-4">

<!-- ================= HEADER ================= -->
<div class="card p-3 mb-3">
    <h6 class="section-title">Header</h6>

    <div class="row g-2">

        <div class="col-md-3">
            <label class="form-label small">Quotation No</label>
            <input type="text"
                   name="quotation_no"
                   value="<?= htmlspecialchars($quotation['quotation_no']) ?>"
                   class="form-control form-control-sm"
                   readonly>
        </div>

        <div class="col-md-3">
            <label class="form-label small">Customer</label>
            <select name="customer_id" class="form-select form-select-sm" required>
                <option value="">Select Customer</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"
                        <?= $quotation['customer_id']==$c['id']?'selected':'' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label small">Country</label>
            <select name="country_id" id="country_id" class="form-select form-select-sm" required>
                <option value="">Select Country</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"
                        <?= $quotation['country_id']==$c['id']?'selected':'' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label small">Transport</label>
            <select name="car_id"
                    id="defaultCarId"
                    class="form-select form-select-sm travel-car">
                <option value="">Select Car</option>
                <?php foreach ($cars as $car): ?>
                    <option value="<?= (int)$car['id'] ?>"
                        <?= $quotation['car_id']==$car['id']?'selected':'' ?>>
                        <?= htmlspecialchars($car['car_name']) ?>
                        (<?= (int)$car['seater'] ?> seater)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Travel Date -->
        <div class="col-md-3">
            <label class="form-label small">Travel Date</label>
            <input type="date"
                   id="travel_date"
                   name="travel_date"
                   value="<?= htmlspecialchars($quotation['travel_date']) ?>"
                   class="form-control form-control-sm"
                   required>
        </div>

        <!-- Departure Date -->
        <div class="col-md-3">
            <label class="form-label small">Departure Date</label>
            <input type="date"
                   id="departure_date"
                   name="departure_date"
                   value="<?= htmlspecialchars($quotation['departure_date']) ?>"
                   class="form-control form-control-sm"
                   readonly>
        </div>

        <!-- Persons & Rooms -->
        <div class="col-md-3">
            <label class="form-label small">Persons & Rooms</label>
            <div class="row g-1">
                <div class="col-4 mb-1">
                    <input type="number" id="adults" name="adults"
                           class="form-control form-control-sm"
                           value="<?= (int)$quotation['adults'] ?>"
                           min="1" required>
                </div>
                <div class="col-4 mb-1">
                    <input type="number" id="extra_adults" name="extra_adults"
                           class="form-control form-control-sm"
                           value="<?= (int)$quotation['extra_adults'] ?>"
                           min="0">
                </div>
                <div class="col-4 mb-1">
                    <input type="number" id="children" name="children"
                           class="form-control form-control-sm"
                           value="<?= (int)$quotation['children'] ?>"
                           min="0">
                </div>
                <div class="col-4 mb-1">
                    <input type="number" id="infants" name="infants"
                           class="form-control form-control-sm"
                           value="<?= (int)$quotation['infants'] ?>"
                           min="0">
                </div>
                <div class="col-4 mb-1">
                    <input type="number" id="no_bed" name="no_bed_child"
                           class="form-control form-control-sm"
                           value="<?= (int)$quotation['no_bed_child'] ?>"
                           min="0">
                </div>
                <div class="col-4">
                    <input type="number" id="rooms" name="rooms"
                           class="form-control form-control-sm"
                           value="<?= (int)$quotation['rooms'] ?>"
                           min="1" required>
                </div>
            </div>
        </div>

        <!-- Days & Nights -->
        <div class="col-md-3">
            <label class="form-label small">Days & Nights</label>
            <div class="row g-1">
                <div class="col-6">
                    <input type="number" id="nights" name="nights"
                           class="form-control form-control-sm"
                           value="<?= (int)$quotation['nights'] ?>"
                           min="1" required>
                </div>
                <div class="col-6">
                    <input type="number" id="days" name="days"
                           class="form-control form-control-sm"
                           value="<?= (int)$quotation['days'] ?>"
                           min="1" required>
                </div>
            </div>
        </div>

    </div>
</div>
<!-- ===== END HEADER ===== -->
<!-- HOTEL DETAILS -->
<div class="card p-2 mb-3">

  <h6 class="section-title d-flex justify-content-between align-items-center">
    Hotel Details
    <button type="button"
      class="btn btn-outline-success btn-sm"
      id="addHotelOption">
      + Add Hotel Option
    </button>
  </h6>

  <div id="hotelOptionsWrapper"></div>

  <!-- 🔥 Hidden Template (DO NOT REMOVE) -->
  <div class="hotel-option card p-2 mb-3 d-none" id="hotelOptionTemplate" data-option="__X__">

    <h6 class="text-primary mb-2">Hotel Option</h6>

    <table class="table table-bordered table-sm small align-middle text-center">
      <thead class="table-light">
        <tr>
          <th>City</th>
          <th>Category</th>
          <th>Hotel & Room</th>
          <th>Nights</th>
          <th>Price</th>
          <th></th>
        </tr>
      </thead>

      <tbody class="hotelBody">
        <tr class="hotel-row">
          <td>
            <select name="hotel[1][city_id][]" class="form-select form-select-sm city"></select>
          </td>

          <td>
            <select name="hotel[1][category][]" class="form-select form-select-sm category"></select>
          </td>

          <td>
            <button type="button"
              class="btn btn-outline-primary btn-sm openHotelRoomPopup w-100">
              Select Hotel & Room
            </button>

            <input type="hidden" name="hotel[1][hotel_id][]" class="hotel-id">
            <input type="hidden" name="hotel[1][room_category_id][]" class="room-id">

            <input type="hidden" name="hotel[1][base_price][]" class="base_price">
            <input type="hidden" name="hotel[1][extra_adult_price][]" class="extra_adult_price">
            <input type="hidden" name="hotel[1][child_price][]" class="child_price">
            <input type="hidden" name="hotel[1][nobed_price][]" class="nobed_price">
          </td>

          <td>
            <input type="number"
              name="hotel[1][stay_nights][]"
              class="form-control form-control-sm stay" value="1">
          </td>

          <td>
            <input type="text"
              name="hotel[1][price][]"
              class="form-control form-control-sm price" readonly>
          </td>

          <td>
            <button type="button"
              class="btn btn-danger btn-sm removeHotel">X</button>
          </td>
        </tr>
      </tbody>
    </table>

    <button type="button"
      class="btn btn-outline-secondary btn-sm addHotelRow">
      + Add More Hotel
    </button>

  </div>
</div>
<!-- TRAVELING PLAN -->
            <h6 class="section-title mt-3">Traveling Plan</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm small text-center align-middle" id="travelPlan">
                    <thead>
                        <tr>
                            <th style="width:3%">Day</th>
    <th style="width:3%">Date</th>
    <th style="width:4%">City</th>
    <th style="width:7%">Pickup Point</th>
    <th style="width:25%">Transfer</th>
    <th style="width:6%">Sightseeing</th>
    <th style="width:4%">Car Rent</th>
    <th style="width:10%">Meal</th>
    <th style="width:3%">Guide</th>
    <th style="width:3%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- rows generated by quotation.js using template below -->
                    </tbody>
                </table>
            </div>

            <!-- VISA & TIP -->
<div class="card p-3 mt-3">
    <h6 class="section-title">Visa Fee & Tip</h6>

    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label small">Visa Fee (Per Person)</label>
            <input type="number" id="visa_fee" class="form-control form-control-sm" step="0.01">
        </div>

        <div class="col-md-6">
            <label class="form-label small">Tip Amount (Per Person)</label>
            <input type="number" id="tip_amount" class="form-control form-control-sm" step="0.01">
        </div>
    </div>
</div>
        <!-- EXTRA CHARGES -->
<div class="card p-3 mt-3">
    <h6 class="section-title">Extra Charges</h6>

    <table class="table table-bordered table-sm text-center" id="extraChargeTable">
        <thead class="table-light">
            <tr>
                <th style="width:40%">Charge Name</th>
                <th style="width:30%">Amount</th>
                <th style="width:10%">Action</th>
            </tr>
        </thead>
        <tbody id="extraChargeBody">
            <tr>
                <td><input type="text" name="extra_charge_name[]" class="form-control form-control-sm"></td>
                <td><input type="number" name="extra_charge_amount[]" class="form-control form-control-sm extra-charge" step="0.01"></td>
                <td><button type="button" class="btn btn-danger btn-sm removeExtra">X</button></td>
            </tr>
        </tbody>
    </table>

    <button type="button" class="btn btn-outline-secondary btn-sm" id="addExtraCharge">+ Add More Charge</button>
</div>

<input type="hidden" name="extra_total" id="extra_total" value="0">

        <!-- COST PANEL & Hidden totals (JS will maintain values) -->
        <div class="row align-items-start g-3">
            <div class="col-md-7">
                <!-- placeholder: any additional summary or notes -->
                <div class="card p-3">
                    <h6 class="section-title">Notes / Itinerary Summary</h6>
                    <textarea name="itinerary_notes" class="form-control form-control-sm" rows="4" placeholder="Add itinerary summary or special notes..."></textarea>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card p-3 mt-4" id="costSummaryPanel">
    <h6 class="section-title">Total Cost Summary</h6>

    <table class="table table-bordered table-sm text-center">
<thead class="table-dark text-white">
<tr>
    <th>Category</th>
    <th>Total</th>
    <th>Per Adult</th>
    <th>Per Extra Adult</th>
    <th>Per Child with Bed</th>
    <th>Per Child No Bed</th>
</tr>
        </thead>
            <tbody>
<tr>
    <td>Hotel</td>
    <td id="hotel_total_ui">0.00</td>
    <td id="hotel_per_adult_ui">0.00</td>
    <td id="hotel_per_extra_adult_ui">0.00</td>
    <td id="hotel_per_child_ui">0.00</td>
    <td id="hotel_per_child_no_bed_ui">0.00</td>
</tr>

<tr>
    <td>Activity</td>
    <td id="activity_total_ui">0.00</td>
    <td id="activity_per_adult_ui">0.00</td>
    <td id="activity_per_extra_adult_ui">0.00</td>
    <td id="activity_per_child_ui">0.00</td>
    <td id="activity_per_child_no_bed_ui">0.00</td>
</tr>

<tr>
    <td>Meal</td>
    <td id="meal_total_ui">0.00</td>
    <td id="meal_per_adult">0.00</td>
    <td id="meal_per_extra_adult_ui">0.00</td>
    <td id="meal_per_child">0.00</td>
    <td id="meal_per_child_no_bed_ui">0.00</td>
</tr>

<tr>
    <td>Transport</td>
    <td id="transport_total_ui">0.00</td>
    <td id="transport_per_adult">0.00</td>
    <td id="transport_per_extra_adult_ui">0.00</td>
    <td id="transport_per_child">0.00</td>
    <td id="transport_per_child_no_bed_ui">0.00</td>
</tr>

<tr>
    <td>Guide</td>
    <td id="guide_total_ui">0.00</td>
    <td id="guide_per_adult">0.00</td>
    <td id="guide_per_extra_adult_ui">0.00</td>
    <td id="guide_per_child">0.00</td>
    <td id="guide_per_child_no_bed_ui">0.00</td>
</tr>
</tbody>

<tr>
    <td>Visa Fee</td>
    <td id="visa_total_ui">0.00</td>
    <td id="visa_per_adult_ui">0.00</td>
    <td id="visa_per_extra_adult_ui">0.00</td>
    <td id="visa_per_child_ui">0.00</td>
    <td id="visa_per_child_no_bed_ui">0.00</td>
</tr>

<tr>
    <td>Tip Amount</td>
    <td id="tip_total_ui">0.00</td>
    <td id="tip_per_adult_ui">0.00</td>
    <td id="tip_per_extra_adult_ui">0.00</td>
    <td id="tip_per_child_ui">0.00</td>
    <td id="tip_per_child_no_bed_ui">0.00</td>
</tr>

<tfoot class="table-dark text-white">
<tr>
    <th>GRAND TOTAL</th>
    <th id="grand_total_ui">0.00</th>
    <th id="grand_per_adult_ui">0.00</th>
    <th id="grand_per_extra_adult_ui">0.00</th>
    <th id="grand_per_child_ui">0.00</th>
    <th id="grand_per_child_no_bed_ui">0.00</th>
</tr>

<tr id="extra_charge_summary_row"></tr>
</tfoot>

    </table>
</div>

        </div>

       <!-- MAIN TOTALS -->
<input type="hidden" name="hotel_total" id="hotel_total" value="0.00">
<input type="hidden" name="activity_total" id="activity_total" value="0.00">
<input type="hidden" name="meal_total" id="meal_total" value="0.00">
<input type="hidden" name="transport_total" id="transport_total" value="0.00">
<input type="hidden" name="guide_total" id="guide_total" value="0.00">
<input type="hidden" name="grand_total" id="grand_total" value="0.00">

<!-- HOTEL -->
<input type="hidden" id="hotel_adult_base"         name="hotel_adult_base">
<input type="hidden" id="hotel_extra_adult"        name="hotel_extra_adult">
<input type="hidden" id="hotel_child_with_bed"     name="hotel_child_with_bed">
<input type="hidden" id="hotel_child_no_bed"       name="hotel_child_no_bed">

<!-- ACTIVITY -->
<input type="hidden" id="activity_adult_base"      name="activity_adult_base">
<input type="hidden" id="activity_extra_adult"     name="activity_extra_adult">
<input type="hidden" id="activity_child_with_bed"  name="activity_child_with_bed">
<input type="hidden" id="activity_child_no_bed"    name="activity_child_no_bed">

<!-- MEAL -->
<input type="hidden" id="meal_adult_base"          name="meal_adult_base">
<input type="hidden" id="meal_extra_adult"         name="meal_extra_adult">
<input type="hidden" id="meal_child_with_bed"      name="meal_child_with_bed">
<input type="hidden" id="meal_child_no_bed"        name="meal_child_no_bed">

<!-- TRANSPORT -->
<input type="hidden" id="transport_adult_base"     name="transport_adult_base">
<input type="hidden" id="transport_extra_adult"    name="transport_extra_adult">
<input type="hidden" id="transport_child_with_bed" name="transport_child_with_bed">
<input type="hidden" id="transport_child_no_bed"   name="transport_child_no_bed">

<!-- GUIDE -->
<input type="hidden" id="guide_adult_base"         name="guide_adult_base">
<input type="hidden" id="guide_extra_adult"        name="guide_extra_adult">
<input type="hidden" id="guide_child_with_bed"     name="guide_child_with_bed">
<input type="hidden" id="guide_child_no_bed"       name="guide_child_no_bed">

<!-- VISA -->
<input type="hidden" id="visa_total"             name="visa_total">
<input type="hidden" id="visa_adult_base"        name="visa_adult_base">
<input type="hidden" id="visa_extra_adult"       name="visa_extra_adult">
<input type="hidden" id="visa_child_with_bed"    name="visa_child_with_bed">
<input type="hidden" id="visa_child_no_bed"      name="visa_child_no_bed">

<!-- TIP -->
<input type="hidden" id="tip_total"              name="tip_total">
<input type="hidden" id="tip_adult_base"         name="tip_adult_base">
<input type="hidden" id="tip_extra_adult"        name="tip_extra_adult">
<input type="hidden" id="tip_child_with_bed"     name="tip_child_with_bed">
<input type="hidden" id="tip_child_no_bed"       name="tip_child_no_bed">


<!-- GRAND -->
<input type="hidden" id="grand_adult_base"         name="grand_adult_base">
<input type="hidden" id="grand_extra_adult"        name="grand_extra_adult">
<input type="hidden" id="grand_child_with_bed"     name="grand_child_with_bed">
<input type="hidden" id="grand_child_no_bed"       name="grand_child_no_bed">


<input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
<input type="hidden" name="user_name" value="<?= $_SESSION['user_name'] ?>">
<input type="hidden" id="quotation_json" name="quotation_json">

        <div class="text-end mt-3 mb-5">
            <button type="button" class="btn btn-theme btn-sm" id="saveQuotation">💾 Save Quotation</button>
        </div>
    </form>

    <!-- Hidden Travel Row Template (used by JS) -->
    <template id="travelRowTemplate">
<tr class="travel-box">

  <td class="day-title">Day 1</td>
  
  <td><input type="date" class="form-control form-control-sm day-date" readonly></td>

  <td>
    <select name="plan_city[]" class="form-select form-select-sm travel-city">
      <option value="">Select City</option>
    </select>
  </td>
  <td>
    <select name="pickup_point[]" class="form-select form-select-sm pickup-point">
      <option value="">Select Pickup</option>
    </select>
  </td>

  <td>
    <select name="sightseeing[]" class="form-select form-select-sm sightseeing">
      <option value="">Select Transfer</option>
    </select>
  </td>

  <td>
    <button type="button" class="btn btn-outline-secondary btn-sm openActivityPopup">
      Select Sightseeing
    </button>
    <input type="hidden" name="activities[]" class="activity-values">
    <input type="hidden" class="activity-data">   
  </td>

  <!-- ******* CAR COLUMN ******* -->
  <td>
    <button type="button" class="btn btn-outline-secondary btn-sm openCarPopup">
      Select Car
    </button>

    <input type="hidden" name="extra_cars[]" class="extra-car-values">
    <input type="hidden" name="extra_car_price[]" class="extra-car-price">
    <input type="hidden" name="car-rent-type[]" class="car-rent-type" value="Full-Day">
  </td>

  <td>
    <select name="meal[]" class="form-select form-select-sm meal">
      <option value="">Select Meal</option>
    </select>
  </td>

  <td>
  <select name="guide_required[]" class="form-select form-select-sm guide-required">
    <option value="No">No</option>
    <option value="Yes">Yes</option>
  </select>

  <!-- 🔥 REQUIRED -->
  <input type="hidden" class="guide-price" value="0">
</td>

    </td>
    <td>
  <button type="button" class="btn btn-outline-secondary btn-sm duplicateTravelRow mt-1">+</button>
  <button type="button" class="btn btn-outline-danger btn-sm removeTravelRow mt-1">X</button>
</td>


</tr>
</template>

    <!-- Activity Selection Modal (markup only; logic in assets/js/quotation.js) -->
    <div class="modal fade" id="activityPopup" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Select Activities</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div id="activityList" style="max-height:420px; overflow:auto;">
                <!-- populated by JS via fetch_activities.php (checkbox list with data-price) -->
            </div>

            <div class="mt-3 text-end">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-theme btn-sm" id="saveActivitySelection">OK</button>

            </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="hotelRoomPopup" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content p-3">

      <div class="d-flex justify-content-between mb-2">
        <h5>Select Hotel & Room</h5>
        <button type="button"
          class="btn-close"
          data-bs-dismiss="modal">
        </button>
      </div>

      <!-- ✅ SCROLLABLE AREA -->
      <div id="hotelRoomList">
        Loading...
      </div>

      <div class="text-end mt-2">
        <button type="button"
          class="btn btn-secondary btn-sm"
          data-bs-dismiss="modal">
          Cancel
        </button>
      </div>

    </div>
  </div>
</div>


    <!-- CAR SELECTION MODAL -->
<div class="modal fade" id="carPopup" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content p-3">
      
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Select Cars</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div id="carList" style="max-height:420px; overflow:auto;">
        <!-- filled by JS -->
      </div>

      <div class="mt-3 text-end">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-theme btn-sm" id="saveCarSelection">OK</button>
      </div>
    </div>
  </div>
</div>


<!-- ================= SAVE BUTTON ================= -->
<div class="text-end mt-4 mb-5">
  <button type="button"
          id="saveQuotation"
          class="btn btn-theme btn-sm px-4">
    💾 Update Quotation
  </button>
</div>

</form>
<!-- ================= END FORM ================= -->


<!-- ================= JS FILES ================= -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- SAME JS AS CREATE PAGE (UNCHANGED) -->
<script src="assets/js/quotation.js?v=1.0"></script>

<script>
$(function(){

  // FORCE create Option-1 on Edit Page
  if ($(".hotel-option").length === 0) {
    $("#addHotelOption").trigger("click");
  }

});
</script>

<script>
function loadEditHotelsOptionWise() {

  if (!window.initQuotation || !Array.isArray(initQuotation.hotels)) return;

  if (typeof calcHotelCost !== "function") {
    setTimeout(loadEditHotelsOptionWise, 200);
    return;
  }

  const hotels = initQuotation.hotels;

  console.log("EDIT HOTELS:", hotels);

  const grouped = {};
  hotels.forEach(h => {
    const o = h.option_no || 1;
    if (!grouped[o]) grouped[o] = [];
    grouped[o].push(h);
  });

  $("#hotelOptionsWrapper").empty();

  Object.keys(grouped).forEach(optNo => {

    const $opt = $("#hotelOptionTemplate").clone(true);
    $opt.removeClass("d-none").removeAttr("id");
    $opt.attr("data-option", optNo);
    $opt.find("h6").text("Hotel Option " + optNo);

    // fix input names
    $opt.find("input,select").each(function () {
      const name = $(this).attr("name");
      if (name) $(this).attr("name", name.replace("[1]", "[" + optNo + "]"));
    });

    $("#hotelOptionsWrapper").append($opt);

    grouped[optNo].forEach((h, i) => {

      if (i > 0) $opt.find(".addHotelRow").trigger("click");

      const $row = $opt.find(".hotel-row:last");

      $.post("fetch_cities.php", { country_id: initQuotation.country_id }, function (html) {
        $row.find(".city").html(html).val(h.city_id);   // NO trigger

        $.post("fetch_categories.php", { city_id: h.city_id }, function (html2) {
          $row.find(".category").html(html2).val(h.category_id);
        });
      });

      $row.find(".hotel-id").val(h.hotel_id);
      $row.find(".room-id").val(h.room_category_id);
      $row.find(".stay").val(h.stay_nights);

      // 🔥 THIS MAKES POPUP SHOW GREEN ROW ON EDIT
      // 🔥 store per-row selection
$row.data('selectedHotel', {
  hotel: h.hotel_id,
  room:  h.room_category_id
});


      $row.data("room_price", Number(h.base_price) || 0);
      $row.data("extra_adult", Number(h.extra_adult_price) || 0);
      $row.data("extra_child", Number(h.child_price) || 0);
      $row.data("extra_nobed", Number(h.nobed_price) || 0);

      $row.find(".base_price").val(h.base_price);
      $row.find(".extra_adult_price").val(h.extra_adult_price);
      $row.find(".child_price").val(h.child_price);
      $row.find(".nobed_price").val(h.nobed_price);

      calcHotelCost($row);
    });
  });

  recalcAllRowCosts();
  console.log("✅ Hotels restored");
}

$(window).on("load", function () {
  setTimeout(loadEditHotelsOptionWise, 800);
});

</script>
<script>
function loadEditTravelPlan() {

  if (!window.initQuotation) return;

  const travels = initQuotation.travels || [];
  const actMap  = initQuotation.travel_activities || {};

  const tbody = $("#travelPlan tbody");
  tbody.empty();

  travels.forEach((t, index) => {

    const tpl = document.getElementById("travelRowTemplate").content.cloneNode(true);
    const $row = $(tpl).find("tr");

    $row.find(".day-title").text("DAY-" + (index + 1));
    $row.find(".day-date").val(t.day_date);

    tbody.append($row);

    /* ---------------- CITY ---------------- */
    $.post("fetch_cities.php", { country_id: initQuotation.country_id }, function (html) {

      const $city = $row.find(".travel-city");
      $city.html(html).val(t.city_id).trigger("change");

      /* ---------------- PICKUP ---------------- */
      setTimeout(() => {
        $.post("fetch_pickup_points.php", { city_id: t.city_id }, function (p) {

          const $pickup = $row.find(".pickup-point");
          $pickup.html(p).val(t.pickup_point_id).trigger("change");

          /* ---------------- SIGHTSEEING ---------------- */
          setTimeout(() => {
            $.post("fetch_sightseeing.php", {
              city_id: t.city_id,
              pickup_point_id: t.pickup_point_id
            }, function (s) {

              const $sight = $row.find(".sightseeing");
              $sight.html(s).val(t.sightseeing_id).trigger("change");

              /* ---------------- ACTIVITIES ---------------- */
              const acts = actMap[t.id] || { ids: [] };

              if (acts.ids.length) {

                $row.find(".activity-values").val(acts.ids.join(","));

                const activityData = acts.ids.map(id => ({
                  id: id,
                  adult: Number(t.activity_adult_price || 0),
                  child: Number(t.activity_child_price || 0)
                }));

                $row.find(".activity-data").val(JSON.stringify(activityData));
                $row.find(".openActivityPopup").addClass("btn-success");
              }

              /* ---------------- CAR ---------------- */
              if (t.car_id && Number(t.car_rent_price) > 0) {

                const cars = [{
                  id: t.car_id,
                  mode: t.car_rent_type || "full-day",
                  price: Number(t.car_rent_price)
                }];

                $row.find(".extra-car-values").val(JSON.stringify(cars));
                $row.find(".extra-car-price").val(t.car_rent_price);
                $row.find(".openCarPopup").addClass("btn-success");
              }

              /* ---------------- MEAL ---------------- */
              $.post("fetch_meals.php", { city_id: t.city_id }, function (m) {
                $row.find(".meal").html(m).val(t.meal_id);
              });

              /* ---------------- GUIDE ---------------- */
              $row.find(".guide-required").val(t.guide === "Yes" ? "Yes" : "No");
              $row.find(".guide-price").val(t.guide_price || 0);

            });
          }, 200);
        });
      }, 200);
    });
  });

  setTimeout(recalcAllRowCosts, 1500);
}
$(window).on("load", function () {
  setTimeout(loadEditTravelPlan, 800);
});

</script>

<script>
/* =====================================================
   AUTO SELECT FIRST ACTIVITY + AUTO CLICK OK
===================================================== */
$(document).on("change", ".sightseeing", function () {

  const row = $(this).closest("tr");
  const sightId = $(this).val();
  if (!sightId) return;

  // remember row globally (popup uses this)
  window.activeTravelRow = row;

  $.post(
    "fetch_activities.php",
    { sightseeing_id: sightId },
    function (list) {

      if (!Array.isArray(list) || !list.length) return;

      const first = list[0];

      // wait until popup content exists
      setTimeout(function () {

        // 1️⃣ CHECK FIRST ACTIVITY CHECKBOX
        $('#activityList input[type="checkbox"]').each(function () {
          if ($(this).val() == first.id) {
            $(this).prop('checked', true);
          } else {
            $(this).prop('checked', false);
          }
        });

        // 2️⃣ 🔥 TRIGGER SAME LOGIC AS USER CLICK
        $('#saveActivitySelection').trigger('click');

        console.log('✅ Auto activity selected + OK triggered');

      }, 200);

    },
    "json"
  );
});
</script>

<script>
function collectTravels() {
  const travels = [];

  // default car (header)
  const defaultCarId = $('#defaultCarId').val() || null;

  $('#travelPlan tbody tr').each(function () {
    const row = $(this);

    const dayText = row.find('.day-title').text();
    const dayNo = parseInt(dayText.replace(/\D/g, ''), 10);
    if (isNaN(dayNo)) return;

    /* ---------------- ACTIVITIES ---------------- */
    const actCsv = row.find('.activity-values').val();
    const activityIds = actCsv
      ? actCsv.split(',').map(v => parseInt(v, 10)).filter(Boolean)
      : [];

    /* ---------------- MULTI CAR SUPPORT ---------------- */
    let carsPayload = [];
    let totalCarRentPrice = 0;

    const carJson = row.find('.extra-car-values').val();

    if (carJson === '__NO_CAR__') {
      carsPayload = [];
      totalCarRentPrice = 0;
    }
    else if (carJson) {
      try {
        const cars = JSON.parse(carJson);

        if (Array.isArray(cars)) {
          carsPayload = cars.map(c => ({
            id: c.id || null,
            mode: c.mode || null,
            price: Number(c.price || 0)
          }));

          totalCarRentPrice = carsPayload.reduce(
            (sum, c) => sum + c.price,
            0
          );
        }
      } catch (e) {
        console.warn('Invalid car JSON:', carJson);
      }
    }
    else if (defaultCarId) {
      // fallback default car (single)
      carsPayload = [{
        id: defaultCarId,
        mode: null,
        price: 0
      }];
    }

    const pickupOption = row.find('.pickup-point option:selected');

    /* ---------------- PUSH DAY ---------------- */
    travels.push({
      day_no: dayNo,
      day_date: row.find('.day-date').val() || null,
      city_id: row.find('.travel-city').val() || null,

      pickup_time: row.find('.pickup-time').val() || null,
      pickup_point_id: row.find('.pickup-point').val() || null,
      pickup_category: pickupOption.data('category') || '',

      sightseeing_id: row.find('.sightseeing').val() || null,

      // 🔥 MULTI CAR DATA
      cars: carsPayload,
      car_rent_price: totalCarRentPrice,

      activity_ids: activityIds,
      activity_price: parseFloat(row.find('.activity-price').val()) || 0,

      meal_id: row.find('.meal').val() || null,
      meal_price: parseFloat(row.find('.meal-price').val()) || 0,

      guide: row.find('.guide-required').val() || 'No',
      guide_price: parseFloat(row.find('.guide-price').val()) || 0
    });
  });

  return travels;
}


function setActivitySelection(row, selectedIds, totalPrice) {

  row.find('.activity-data').val(selectedIds.join(',')); // 🔥 REQUIRED
  row.find('.activity-price').val(totalPrice.toFixed(2));

  console.log('ACTIVITY IDS SET:', selectedIds); // 🔥 DEBUG
}
</script>
<script>
function applyAutoActivity(row, activity) {

  // 1️⃣ set activity ids
  row.find('.activity-values').val(activity.id);
  row.find('.activity-data').val(activity.id);

  // 2️⃣ set price
  row.find('.activity-price').val(parseFloat(activity.price).toFixed(2));

  // 3️⃣ FORCE activity total recalculation
  if (typeof updateActivityTotals === 'function') {
    updateActivityTotals();
  }

  // 4️⃣ FORCE final summary rebuild
  if (typeof updateFinalCostPanel === 'function') {
    updateFinalCostPanel();
  }

  console.log('✅ Auto activity applied & counted:', activity);
}
</script>

</body>
</html>
