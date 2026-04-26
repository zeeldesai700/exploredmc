<?php
session_start();

// CHECK LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// After verifying email & password
$user_id = $_SESSION['user_id'] ?? '';
$user_name = $_SESSION['user_name'] ?? '';  // <-- THIS WAS MISSING


require_once __DIR__ . '/../config/db.php';
$page_title = 'Customers';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/nav.php';

// --- Generate quotation no
$result = $conn->query("SELECT MAX(id) AS last_id FROM quotations");
$row = $result ? $result->fetch_assoc() : null;
$next_id = ($row && $row['last_id']) ? ((int)$row['last_id'] + 1) : 1001;
$quotation_no = "QTN-" . date("Ymd") . "-" . $next_id;

// --- Fetch customers, countries, cars
$customers = $conn->query("SELECT id, name FROM customers ORDER BY name ASC");
$customers = $customers ? $customers->fetch_all(MYSQLI_ASSOC) : [];

$countries = $conn->query("SELECT id, name FROM countries ORDER BY name ASC");
$countries = $countries ? $countries->fetch_all(MYSQLI_ASSOC) : [];

$cars = $conn->query("SELECT id, car_name, seater FROM cars ORDER BY car_name ASC");
$cars = $cars ? $cars->fetch_all(MYSQLI_ASSOC) : [];



/**
 * AJAX ROOM LOADER (seasonal)
 */
if (isset($_POST['hotel_id']) && isset($_POST['travel_date'])) {

    $hotel_id = (int)$_POST['hotel_id'];
    $travel_date = $_POST['travel_date'];

    $res = $conn->query("
        SELECT id, room_category 
        FROM hotel_rooms
        WHERE hotel_id = $hotel_id
        ORDER BY room_category ASC
    ");

    echo '<option value="">Select Room</option>';

    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['id'];
        $cat = htmlspecialchars($r['room_category']);

        echo "<option value=\"$id\">$cat</option>";
    }
    exit;

    $res = $conn->query($sql);

    echo '<option value="">Select Room</option>';
    while ($r = $res->fetch_assoc()) {
        $id    = (int)$r['id'];
        $label = htmlspecialchars($r['room_category']);
        $price = (float)$r['room_price'];

        echo "<option value=\"$id\" 
            data-price=\"$price\" 
            data-extra_adult=\"{$r['extra_adult_charge']}\" 
            data-extra_child=\"{$r['extra_child_charge']}\" 
            data-extra_nobed=\"{$r['child_no_bed_charge']}\">";
        echo $label;
        echo "</option>";
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create Quotation — <?= htmlspecialchars($quotation_no) ?></title>

    <!-- Bootstrap (CSS only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- jQuery (required by existing / external quotation.js) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Theme & small polish CSS -->
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
    </style>
</head>
<body class="container py-4">
    <h3>Create Quotation</h3>

    <form id="quotationForm" method="POST" action="save_quotation.php" autocomplete="off" class="mb-4">
        <!-- Header -->
        <div class="card p-3 mb-3">
            <h6 class="section-title">Header</h6>
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small">Quotation No</label>
                    <input type="text" name="quotation_no" value="<?= htmlspecialchars($quotation_no) ?>" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Customer</label>
                    <select name="customer_id" class="form-select form-select-sm" required>
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Country</label>
                    <select name="country_id" id="country_id" class="form-select form-select-sm" required>
                        <option value="">Select Country</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Transport</label>
                    <select name="car_id" class="form-select form-select-sm">
                        <option value="">Select Car</option>
                        <?php foreach ($cars as $car): ?>
                            <option value="<?= (int)$car['id'] ?>"><?= htmlspecialchars($car['car_name']) ?> (<?= (int)$car['seater'] ?> seater)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- second row (inline Persons & Days) -->
                <div class="col-md-3">
                    <label class="form-label small">Travel Date</label>
                    <input type="date" id="travel_date" name="travel_date" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Departure Date</label>
                    <input type="date" id="departure_date" name="departure_date" class="form-control form-control-sm" readonly>
                </div>

                <!-- inline Persons & Rooms -->
                <div class="col-md-3">
                    <label class="form-label small">Persons & Rooms</label>
                    <div class="row g-1">
                        <div class="col-6 mb-1">
                            <input type="number" id="adults" name="adults" class="form-control form-control-sm" placeholder="Adults" min="1" required>
                        </div>
                        <div class="col-6 mb-1">
                            <input type="number" id="children" name="children" class="form-control form-control-sm" placeholder="Child" min="0">
                        </div>
                        <div class="col-6">
                            <input type="number" id="no_bed" name="no_bed_child" class="form-control form-control-sm" placeholder="No Bed" min="0">
                        </div>
                        <div class="col-6">
                            <input type="number" id="rooms" name="rooms" class="form-control form-control-sm" placeholder="Rooms" min="1" required>
                        </div>
                    </div>
                </div>

                <!-- inline Days & Nights -->
                <div class="col-md-3">
                    <label class="form-label small">Days & Nights</label>
                    <div class="row g-1">
                        <div class="col-6">
                            <input type="number" id="nights" name="nights" class="form-control form-control-sm" placeholder="Nights" min="1" required>
                        </div>
                        <div class="col-6">
                            <input type="number" id="days" name="days" class="form-control form-control-sm" placeholder="Days" min="1" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HOTEL DETAILS -->
        <div class="card p-2 mb-3">
            <h6 class="section-title">Hotel Details</h6>

            <table class="table table-bordered table-sm small align-middle text-center" id="hotelTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:14%">City</th>
                        <th style="width:14%">Hotel Category</th>
                        <th style="width:34%">Hotel</th>
                        <th style="width:12%">Room Category</th>
                        <th style="width:5%">Nights</th>
                        <th style="width:5%">Price</th>
                        <th style="width:6%">Action</th>
                    </tr>
                </thead>
                <tbody id="hotelBody">
                    <tr class="hotel-row">
                        <td>
                            <select name="hotel_city_id[]" class="form-select form-select-sm city">
                                <option value="">Select City</option>
                                <!-- populated by JS -->
                            </select>
                        </td>
                        <td>
                            <select name="hotel_category[]" class="form-select form-select-sm category">
                                <option value="">Select Category</option>
                                <!-- populated by JS -->
                            </select>
                        </td>
                        <td>
                            <select name="hotel_id[]" class="form-select form-select-sm hotel">
                                <option value="">Select Hotel</option>
                                <!-- populated by JS -->
                            </select>
                        </td>
                        <td>
                            <select name="room_category_id[]" class="form-select form-select-sm room">
                                <option value="">Select Room</option>
                                <!-- populated via AJAX from hotel_id -->
                            </select>
                        </td>
                        <td>
                            <input type="number" name="stay_nights[]" class="form-control form-control-sm stay" min="1" value="1">
                        </td>
                        <td>
                            <input type="text" name="hotel_price[]" class="form-control form-control-sm price" readonly>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm removeHotel">X</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="addHotel">+ Add More Hotel</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="refreshHotelCities">Refresh Cities</button>
            </div>
        </div>

        <!-- TRAVELING PLAN -->
        <h6 class="section-title mt-3">Traveling Plan</h6>
        <div class="table-responsive mb-3">
            <table class="table table-bordered table-sm small text-center align-middle" id="travelPlan">
                <thead>
                    <tr>
                        <th style="width:3%">Day</th>
<th style="width:7%">Date</th>
<th style="width:7%">City</th>
<th style="width:6%">Pickup Time</th>
<th style="width:12%">Pickup Point</th>
<th style="width:16%">Sightseeing</th>
<th style="width:8%">Activity</th>
<th style="width:7%">Car Rent</th>
<th style="width:7%">Meal</th>
<th style="width:3%">Guide</th>
<th style="width:4%">Guide Price</th>
<th style="width:4%">Activity Price</th>
<th style="width:4%">Car Rent Price</th>
<th style="width:4%">Meal Price</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- rows generated by quotation.js using template below -->
                </tbody>
            </table>
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
        <thead class="table-light">
            <tr>
                <th>Category</th>
                <th>Total</th>
                <th>Adult Total</th>
                <th>Child Total</th>
                <th>Per Adult</th>
                <th>Per Child</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Hotel</td>
                <td id="hotel_total_ui">0</td>
                <td id="hotel_adult_total_ui">0</td>
                <td id="hotel_child_total_ui">0</td>
                <td id="hotel_per_adult">0</td>
                <td id="hotel_per_child">0</td>
            </tr>

            <tr>
                <td>Activity</td>
                <td id="activity_total_ui">0</td>
                <td id="activity_adult_total_ui">0</td>
                <td id="activity_child_total_ui">0</td>
                <td id="activity_per_adult">0</td>
                <td id="activity_per_child">0</td>
            </tr>

            <tr>
                <td>Meal</td>
                <td id="meal_total_ui">0</td>
                <td id="meal_adult_total_ui">0</td>
                <td id="meal_child_total_ui">0</td>
                <td id="meal_per_adult">0</td>
                <td id="meal_per_child">0</td>
            </tr>

            <tr>
                <td>Transport</td>
                <td id="transport_total_ui">0</td>
                <td id="transport_adult_total_ui">0</td>
                <td id="transport_child_total_ui">0</td>
                <td id="transport_per_adult">0</td>
                <td id="transport_per_child">0</td>
            </tr>

            <tr>
                <td>Guide</td>
                <td id="guide_total_ui">0</td>
                <td id="guide_adult_total_ui">0</td>
                <td id="guide_child_total_ui">0</td>
                <td id="guide_per_adult">0</td>
                <td id="guide_per_child">0</td>
            </tr>
        </tbody>

        <tfoot class="table-dark text-white">
            <tr>
                <th>GRAND TOTAL</th>
                <th id="grand_total_ui">0</th>
                <th id="grand_adult_total_ui">0</th>
                <th id="grand_child_total_ui">0</th>
                <th id="grand_per_adult_ui">0</th>
                <th id="grand_per_child_ui">0</th>
            </tr>
            <tr id="extra_charge_summary_row"></tr>
        </tfoot>
    </table>
</div>

        </div>

        <!-- Hidden fields that JS will update before submit -->
<input type="hidden" name="hotel_total" id="hotel_total" value="0.00">
<input type="hidden" name="activity_total" id="activity_total" value="0.00">
<input type="hidden" name="meal_total" id="meal_total" value="0.00">
<input type="hidden" name="transport_total" id="transport_total" value="0.00">
<input type="hidden" name="guide_total" id="guide_total" value="0.00">

<input type="hidden" name="net_total" id="net_total" value="0.00">
<input type="hidden" name="grand_total" id="grand_total" value="0.00">

<input type="hidden" name="quotation_json" id="quotation_json" value="">

<!-- HOTEL SUMMARY -->
<input type="hidden" name="hotel_adult_total" id="hotel_adult_total" value="0.00">
<input type="hidden" name="hotel_child_total" id="hotel_child_total" value="0.00">
<input type="hidden" name="hotel_per_adult" id="hotel_per_adult" value="0.00">
<input type="hidden" name="hotel_per_child" id="hotel_per_child" value="0.00">

<!-- ACTIVITY SUMMARY -->
<input type="hidden" name="activity_adult_total" id="activity_adult_total" value="0.00">
<input type="hidden" name="activity_child_total" id="activity_child_total" value="0.00">
<input type="hidden" name="activity_per_adult" id="activity_per_adult" value="0.00">
<input type="hidden" name="activity_per_child" id="activity_per_child" value="0.00">

<!-- MEAL SUMMARY -->
<input type="hidden" name="meal_adult_total" id="meal_adult_total" value="0.00">
<input type="hidden" name="meal_child_total" id="meal_child_total" value="0.00">
<input type="hidden" name="meal_per_adult" id="meal_per_adult" value="0.00">
<input type="hidden" name="meal_per_child" id="meal_per_child" value="0.00">

<!-- TRANSPORT SUMMARY -->
<input type="hidden" name="transport_adult_total" id="transport_adult_total" value="0.00">
<input type="hidden" name="transport_child_total" id="transport_child_total" value="0.00">
<input type="hidden" name="transport_per_adult" id="transport_per_adult" value="0.00">
<input type="hidden" name="transport_per_child" id="transport_per_child" value="0.00">

<!-- GUIDE SUMMARY -->
<input type="hidden" name="guide_adult_total" id="guide_adult_total" value="0.00">
<input type="hidden" name="guide_child_total" id="guide_child_total" value="0.00">
<input type="hidden" name="guide_per_adult" id="guide_per_adult" value="0.00">
<input type="hidden" name="guide_per_child" id="guide_per_child" value="0.00">

<!-- GRAND TOTAL SUMMARY -->
<input type="hidden" name="grand_adult_total" id="grand_adult_total" value="0.00">
<input type="hidden" name="grand_child_total" id="grand_child_total" value="0.00">
<input type="hidden" name="grand_per_adult" id="grand_per_adult" value="0.00">
<input type="hidden" name="grand_per_child" id="grand_per_child" value="0.00">

<input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
<input type="hidden" name="user_name" value="<?= $_SESSION['user_name'] ?>">


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
                <!-- populated by JS -->
            </select>
        </td>
        <td><input type="time" step="1" name="pickup_time[]" class="form-control form-control-sm pickup-time"></td>
        <td>
            <select name="pickup_point[]" class="form-select form-select-sm pickup-point">
                <option value="">Select Pickup</option>
            </select>
        </td>
        <td>
            <select name="sightseeing[]" class="form-select form-select-sm sightseeing">
                <option value="">Select Sightseeing</option>
            </select>
        </td>
        <td>
            <!-- button triggers activity modal; JS will copy selections into hidden input -->
            <button type="button" class="btn btn-outline-secondary btn-sm openActivityPopup">Select Activity</button>
            <input type="hidden" name="activities[]" class="activity-values">
        </td>
        <td>
            <select name="car_rent[]" class="form-select form-select-sm car-rent">
                <option value="">Select Rent</option>
            </select>
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
        </td>
        <td><input type="text" name="guide_price[]" class="form-control form-control-sm guide-price" readonly></td>
        <td><input type="text" name="activity_price[]" class="form-control form-control-sm activity-price" readonly></td>
        <td><input type="text" name="car_rent_price[]" class="form-control form-control-sm car-rent-price" readonly></td>
        <td><input type="text" name="meal_price[]" class="form-control form-control-sm meal-price" readonly></td>
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

    <!-- Bootstrap JS bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

   
    <!-- Minimal inline helpers (only UI helpers that don't conflict with quotation.js) -->
    <script>
 (function($){
   function syncSummaryToHidden(){
     // categories
     $('#hotel_adult_total').val( $('#hotel_adult_total_ui').text() || '0' );
     $('#hotel_child_total').val( $('#hotel_child_total_ui').text() || '0' );
     $('#hotel_per_adult').val( $('#hotel_per_adult').text() || '0' );
     $('#hotel_per_child').val( $('#hotel_per_child').text() || '0' );

     $('#activity_adult_total').val( $('#activity_adult_total_ui').text() || '0' );
     $('#activity_child_total').val( $('#activity_child_total_ui').text() || '0' );
     $('#activity_per_adult').val( $('#activity_per_adult').text() || '0' );
     $('#activity_per_child').val( $('#activity_per_child').text() || '0' );

     $('#meal_adult_total').val( $('#meal_adult_total_ui').text() || '0' );
     $('#meal_child_total').val( $('#meal_child_total_ui').text() || '0' );
     $('#meal_per_adult').val( $('#meal_per_adult').text() || '0' );
     $('#meal_per_child').val( $('#meal_per_child').text() || '0' );

     $('#transport_adult_total').val( $('#transport_adult_total_ui').text() || '0' );
     $('#transport_child_total').val( $('#transport_child_total_ui').text() || '0' );
     $('#transport_per_adult').val( $('#transport_per_adult').text() || '0' );
     $('#transport_per_child').val( $('#transport_per_child').text() || '0' );

     $('#guide_adult_total').val( $('#guide_adult_total_ui').text() || '0' );
     $('#guide_child_total').val( $('#guide_child_total_ui').text() || '0' );
     $('#guide_per_adult').val( $('#guide_per_adult').text() || '0' );
     $('#guide_per_child').val( $('#guide_per_child').text() || '0' );

     $('#grand_adult_total').val( $('#grand_adult_total_ui').text() || '0' );
     $('#grand_child_total').val( $('#grand_child_total_ui').text() || '0' );
     $('#grand_per_adult').val( $('#grand_per_adult_ui').text() || '0' );
     $('#grand_per_child').val( $('#grand_per_child_ui').text() || '0' );

     // Also sync top totals hidden (if not already)
     $('#hotel_total').val( $('#hotel_total_ui').text() || '0' );
     $('#activity_total').val( $('#activity_total_ui').text() || '0' );
     $('#meal_total').val( $('#meal_total_ui').text() || '0' );
     $('#transport_total').val( $('#transport_total_ui').text() || '0' );
     $('#guide_total').val( $('#guide_total_ui').text() || '0' );
     $('#net_total').val( $('#totalCost').text() || '0' );
     $('#grand_total').val( $('#grand_total_ui').text() || '0' );
     $('#extra_total').val($('#extra_total').val() || '0');

   }

function updateExtraChargeSummary() {
    let rows = "";
    let totalExtra = 0;

    $("#extra_charge_summary_row").empty(); // clear old rows

    $("#extraChargeBody tr").each(function () {
        let name = $(this).find("input[name='extra_charge_name[]']").val();
        let amount = parseFloat($(this).find("input[name='extra_charge_amount[]']").val()) || 0;

        if (name.trim() !== "" || amount > 0) {
            rows += `
                <tr class="extra-summary-row">
                    <td>${name}</td>
                    <td>${amount.toFixed(2)}</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            `;
            totalExtra += amount;
        }
    });

    $("#extra_charge_summary_row").before(rows);

    // update hidden field
    $("#extra_total").val(totalExtra.toFixed(2));
}

// When extra charge changes, update summary
$(document).on("input", ".extra-charge", function () {
    calculateExtraCharges(); 
    updateExtraChargeSummary();
});

$(document).on("click", "#addExtraCharge", function () {
    setTimeout(function () {
        updateExtraChargeSummary();
    }, 100);
});

$(document).on("click", ".removeExtra", function () {
    updateExtraChargeSummary();
});

// ---------- helpers ----------
function parseUiNumber(selector) {
  const txt = $(selector).text();
  const v = parseFloat((txt || "").toString().replace(/[, ]+/g, "")); // remove commas/spaces
  return isFinite(v) ? v : 0;
}

function getBaseGrandFromUi() {
  // base = sum of category totals shown in UI (hotel, activity, meal, transport, guide)
  const hotel = parseUiNumber('#hotel_total_ui');
  const activity = parseUiNumber('#activity_total_ui');
  const meal = parseUiNumber('#meal_total_ui');
  const transport = parseUiNumber('#transport_total_ui');
  const guide = parseUiNumber('#guide_total_ui');
  return hotel + activity + meal + transport + guide;
}

// ---------- calculate & render ----------
function calculateExtraCharges() {
  let sum = 0;
  // sum up values from inputs
  $(".extra-charge").each(function () {
    let v = parseFloat($(this).val());
    if (!isFinite(v)) v = 0;
    sum += v;
  });

  // update hidden field for form submission
  $("#extra_total").val(sum.toFixed(2));

  // update extra rows in the summary (one row per extra charge)
  updateExtraChargeSummary();

  // recompute grand from base UI totals + extra (so we never double-add)
  const baseGrand = getBaseGrandFromUi();
  const newGrand = baseGrand + sum;

  // update UI and hidden grand_total/net_total if you maintain them
  $("#grand_total_ui").text(newGrand.toFixed(2));
  $("#grand_total").val(newGrand.toFixed(2));
  // if you keep a separate net_total, set it here (adjust as your logic requires)
  $("#net_total").val(newGrand.toFixed(2));
}

// ---------- render extra-charge rows into summary ----------
function updateExtraChargeSummary() {
  // remove any existing extra-summary rows
  $(".extra-summary-row").remove();

  let totalExtra = 0;
  // iterate through the extra charge inputs and insert rows before the grand total row
  $("#extraChargeBody tr").each(function () {
    const name = $(this).find("input[name='extra_charge_name[]']").val() || '';
    const amount = parseFloat($(this).find("input[name='extra_charge_amount[]']").val()) || 0;
    if (name.trim() !== "" || amount !== 0) {
      // create a row and insert before the grand total footer (we'll insert before the footer by using the tfoot)
      const row = $(`<tr class="extra-summary-row">
          <td>${$('<div>').text(name).html()}</td>
          <td>${amount.toFixed(2)}</td>
          <td>0</td>
          <td>0</td>
          <td>0</td>
          <td>0</td>
        </tr>`);
      // place the row just before tfoot (append to tbody)
      $("#costSummaryPanel table tbody").append(row);
      totalExtra += amount;
    }
  });

  // ensure #extra_total matches computed
  $("#extra_total").val(totalExtra.toFixed(2));
}

// ---------- event handlers ----------
$(document).on("click", "#addExtraCharge", function () {
  $("#extraChargeBody").append(`
    <tr>
      <td><input type="text" name="extra_charge_name[]" class="form-control form-control-sm"></td>
      <td><input type="number" name="extra_charge_amount[]" class="form-control form-control-sm extra-charge" step="0.01" min="0"></td>
      <td><button type="button" class="btn btn-danger btn-sm removeExtra">X</button></td>
    </tr>
  `);

  // recalc after adding (small delay ensures element exists)
  setTimeout(function () {
    calculateExtraCharges();
    // focus new amount input for convenience
    $("#extraChargeBody tr:last").find("input.extra-charge").focus();
  }, 10);
});

$(document).on("click", ".removeExtra", function () {
  $(this).closest("tr").remove();
  calculateExtraCharges();
});

// when any extra amount changes
$(document).on("input", ".extra-charge, input[name='extra_charge_name[]']", function () {
  calculateExtraCharges();
});

// Also recalc when any category total UI changes externally
// If your existing code updates category UI values via a function, call calculateExtraCharges() there.
// As a fallback, observe the five totals and recalc when they change (mutation observer)
(function watchUiTotals(){
  const targets = ['#hotel_total_ui','#activity_total_ui','#meal_total_ui','#transport_total_ui','#guide_total_ui'];
  targets.forEach(selector => {
    const el = document.querySelector(selector);
    if (!el) return;
    const mo = new MutationObserver(function(){ calculateExtraCharges(); });
    mo.observe(el, { childList: true, characterData: true, subtree: true });
  });
})();


   // when user clicks Save — sync then submit
   $('#saveQuotation').on('click', function(e){
     e.preventDefault();
     // recalc (in case)
     if (typeof window.updateFinalCostPanel === 'function') {
       window.updateFinalCostPanel();
     }
     syncSummaryToHidden();

     // ensure your JS collects travel rows into quotation_json
     if (typeof window.collectQuotationPayload === 'function') {
       const payload = window.collectQuotationPayload();
       $('#quotation_json').val(JSON.stringify(payload));
     }
     // finally submit
     $('#quotationForm').submit();
   });
 })(jQuery);
</script>
 <!-- External site JS (keep your current file - Option A) -->
    <script src="assets/js/quotation.js"></script>
</body>
</html>
