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
    <h3>Create Quotation</h3>

    <form id="quotationForm" method="POST" action="save_quotation.php" autocomplete="off" class="mb-4">
        <!-- Header -->
        <div class="card p-3 mb-3">
            <h6 class="section-title">Header</h6>
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small">Quotation No</label>
                    <input type="text" name="quotation_no" value="Will be generated after save" class="form-control form-control-sm" readonly>
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
  <select
      name="car_id"
      id="defaultCarId"
      class="form-select form-select-sm travel-car">
      
      <option value="">Select Car</option>
      <?php foreach ($cars as $car): ?>
          <option value="<?= (int)$car['id'] ?>">
              <?= htmlspecialchars($car['car_name']) ?> (<?= (int)$car['seater'] ?> seater)
          </option>
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
        <div class="col-4 mb-1">
            <input type="number" id="adults" name="adults"
                class="form-control form-control-sm"
                placeholder="Adults" min="1" required>
        </div>

        <div class="col-4 mb-1">
            <input type="number" id="extra_adults" name="extra_adults"
                class="form-control form-control-sm"
                placeholder="Extra Adult" min="0">
        </div>

        <div class="col-4 mb-1">
            <input type="number" id="children" name="children"
                class="form-control form-control-sm"
                placeholder="Child" min="0">
        </div>

        <!-- NEW: Infant -->
        <div class="col-4 mb-1">
            <input type="number" id="infants" name="infants"
                class="form-control form-control-sm"
                placeholder="Infant" min="0">
        </div>

        <div class="col-4 mb-1">
            <input type="number" id="no_bed" name="no_bed_child"
                class="form-control form-control-sm"
                placeholder="No Bed" min="0">
        </div>

        <div class="col-4">
            <input type="number" id="rooms" name="rooms"
                class="form-control form-control-sm"
                placeholder="Rooms" min="1" required>
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

  <h6 class="section-title d-flex justify-content-between align-items-center">
    Hotel Details
    <button type="button"
      class="btn btn-outline-success btn-sm"
      id="addHotelOption">
      + Add Hotel Option
    </button>
  </h6>

  <!-- HOTEL OPTIONS CONTAINER -->
  <div id="hotelOptionsWrapper">

    <!-- ================= HOTEL OPTION 1 ================= -->
    <div class="hotel-option card p-2 mb-3" data-option="1">

      <h6 class="text-primary mb-2">Hotel Option 1</h6>

      <table class="table table-bordered table-sm small align-middle text-center">
        <thead class="table-light">
          <tr>
            <th style="width:14%">City</th>
            <th style="width:14%">Hotel Category</th>
            <th style="width:46%">Hotel & Room Category</th>
            <th style="width:8%">Nights</th>
            <th style="width:10%">Price</th>
            <th style="width:8%">Action</th>
          </tr>
        </thead>

        <tbody class="hotelBody">
          <tr class="hotel-row">

            <!-- CITY -->
            <td>
              <select name="hotel[1][city_id][]"
                class="form-select form-select-sm city">
                <option value="">Select City</option>
              </select>
            </td>

            <!-- CATEGORY -->
            <td>
              <select name="hotel[1][category][]"
                class="form-select form-select-sm category">
                <option value="">Select Category</option>
              </select>
            </td>

            <!-- HOTEL + ROOM -->
            <td>
              <button type="button"
                class="btn btn-outline-primary btn-sm openHotelRoomPopup w-100">
                Select Hotel & Room
              </button>

              <input type="hidden"
                name="hotel[1][hotel_id][]"
                class="hotel-id">

              <input type="hidden"
                name="hotel[1][room_category_id][]"
                class="room-id">
            </td>
             
             <!-- 🔥 REQUIRED FOR PRICE SAVE -->
              <input type="hidden" name="hotel[1][base_price][]" class="base_price">
              <input type="hidden" name="hotel[1][extra_adult_price][]" class="extra_adult_price">
              <input type="hidden" name="hotel[1][child_price][]" class="child_price">
              <input type="hidden" name="hotel[1][nobed_price][]" class="nobed_price">
            </td>
            <!-- NIGHTS -->
            <td>
              <input type="number"
                name="hotel[1][stay_nights][]"
                class="form-control form-control-sm stay"
                min="1"
                value="1">
            </td>

            <!-- PRICE -->
            <td>
              <input type="text"
                name="hotel[1][price][]"
                class="form-control form-control-sm price"
                readonly>
            </td>

            <!-- ACTION -->
            <td>
              <button type="button"
                class="btn btn-danger btn-sm removeHotel">
                X
              </button>
            </td>

          </tr>
        </tbody>
      </table>

      <div class="d-flex gap-2">
        <button type="button"
          class="btn btn-outline-secondary btn-sm addHotelRow">
          + Add More Hotel
        </button>
      </div>

    </div>
    <!-- ================= END HOTEL OPTION 1 ================= -->

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
 <script>
/* =====================================================
   GLOBAL – FINAL TOTALS BUILDER (MUST BE GLOBAL)
===================================================== */
window.buildFinalTotalsObject = function () {
  return {
    hotel: {
      total: $("#hotel_total_ui").text(),
      per_adult: $("#hotel_per_adult_ui").text(),
      per_extra_adult: $("#hotel_per_extra_adult_ui").text(),
      per_child: $("#hotel_per_child_ui").text(),
      per_child_no_bed: $("#hotel_per_child_no_bed_ui").text()
    },
    activity: {
      total: $("#activity_total_ui").text(),
      per_adult: $("#activity_per_adult_ui").text(),
      per_extra_adult: $("#activity_per_extra_adult_ui").text(),
      per_child: $("#activity_per_child_ui").text(),
      per_child_no_bed: $("#activity_per_child_no_bed_ui").text()
    },
    meal: {
      total: $("#meal_total_ui").text(),
      per_adult: $("#meal_per_adult").text(),
      per_extra_adult: $("#meal_per_extra_adult_ui").text(),
      per_child: $("#meal_per_child").text(),
      per_child_no_bed: $("#meal_per_child_no_bed_ui").text()
    },
    transport: {
      total: $("#transport_total_ui").text(),
      per_adult: $("#transport_per_adult").text(),
      per_extra_adult: $("#transport_per_extra_adult_ui").text(),
      per_child: $("#transport_per_child").text(),
      per_child_no_bed: $("#transport_per_child_no_bed_ui").text()
    },
    guide: {
      total: $("#guide_total_ui").text(),
      per_adult: $("#guide_per_adult").text(),
      per_extra_adult: $("#guide_per_extra_adult_ui").text(),
      per_child: $("#guide_per_child").text(),
      per_child_no_bed: $("#guide_per_child_no_bed_ui").text()
    },
    visa: {
      total: $("#visa_total_ui").text(),
      per_adult: $("#visa_per_adult_ui").text(),
      per_extra_adult: $("#visa_per_extra_adult_ui").text(),
      per_child: $("#visa_per_child_ui").text(),
      per_child_no_bed: $("#visa_per_child_no_bed_ui").text()
    },
    tip: {
      total: $("#tip_total_ui").text(),
      per_adult: $("#tip_per_adult_ui").text(),
      per_extra_adult: $("#tip_per_extra_adult_ui").text(),
      per_child: $("#tip_per_child_ui").text(),
      per_child_no_bed: $("#tip_per_child_no_bed_ui").text()
    },
    grand: {
      total: $("#grand_total_ui").text(),
      per_adult: $("#grand_per_adult_ui").text(),
      per_extra_adult: $("#grand_per_extra_adult_ui").text(),
      per_child: $("#grand_per_child_ui").text(),
      per_child_no_bed: $("#grand_per_child_no_bed_ui").text()
    }
  };
};

</script>

<script>
function collectHotels() {
  const hotels = [];

  const adults      = Number($('#adults').val() || 0);
  const extraAdults = Number($('#extra_adults').val() || 0);
  const children    = Number($('#children').val() || 0);
  const noBed       = Number($('#no_bed').val() || 0);
  const nights      = Number($('#nights').val() || 1);
  const rooms       = Number($('#rooms').val() || 1);

  $('.hotel-option').each(function () {

    const $option = $(this);

    $option.find('.hotelBody tr').each(function () {

      const row = $(this);

      const hotelId = row.find('.hotel-id').val();
      const roomId  = row.find('.room-id').val();

      if (!hotelId || !roomId) return;

      // 🔹 UNIT RATES (from popup)
      const baseUnit  = Number(row.data('room_price'))  || 0;
      const eaUnit    = Number(row.data('extra_adult')) || 0;
      const childUnit = Number(row.data('extra_child')) || 0;
      const nbUnit    = Number(row.data('extra_nobed')) || 0;

      // 🔹 APPLY PERSON LOGIC
      const basePriceApplied  = adults > 0 ? baseUnit  : 0;
      const eaPriceApplied    = extraAdults > 0 ? eaUnit : 0;
      const childPriceApplied = children > 0 ? childUnit : 0;
      const nbPriceApplied    = noBed > 0 ? nbUnit : 0;

      const totalPrice = Number(row.find('.price').val() || 0);
      if (totalPrice <= 0) return;

      hotels.push({
        city_id: row.find('.city').val() || null,
        category: row.find('.category').val() || null,
        hotel_id: hotelId,
        room_category_id: roomId,
        stay_nights: nights,
        rooms: rooms,

        base_price: basePriceApplied,
        extra_adult_price: eaPriceApplied,
        child_price: childPriceApplied,
        nobed_price: nbPriceApplied,

        price: totalPrice
      });

    });
  });

  console.log('✅ FINAL HOTEL PAYLOAD', hotels);
  return hotels;
}


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

 <script src="assets/js/quotation.js?v=1.0"></script>
    <!-- Bootstrap JS bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

    <!-- Minimal inline helpers (only UI helpers that don't conflict with quotation.js) -->
    <script>
 (function($){
   function syncSummaryToHidden(){
     // categories
     $('#hotel_adult_total').val( $('#hotel_adult_total_ui').text() || '0' );
     $('#hotel_child_total').val( $('#hotel_child_total_ui').text() || '0' );
     $('#hotel_per_adult_input').val( $('#hotel_per_adult').text() );
     $('#hotel_per_child_input').val( $('#hotel_per_child').text() );

     $('#activity_adult_total').val( $('#activity_adult_total_ui').text() || '0' );
     $('#activity_child_total').val( $('#activity_child_total_ui').text() || '0' );
     $('#activity_per_adult_input').val( $('#activity_per_adult').text() );
     $('#activity_per_child_input').val( $('#activity_per_child').text() );

     $('#meal_adult_total').val( $('#meal_adult_total_ui').text() || '0' );
     $('#meal_child_total').val( $('#meal_child_total_ui').text() || '0' );
     $('#meal_per_adult_input').val( $('#meal_per_adult').text() );
     $('#meal_per_child_input').val( $('#meal_per_child').text() );


     $('#transport_adult_total').val( $('#transport_adult_total_ui').text() || '0' );
     $('#transport_child_total').val( $('#transport_child_total_ui').text() || '0' );
     $('#transport_per_adult_input').val( $('#transport_per_adult').text() );
     $('#transport_per_child_input').val( $('#transport_per_child').text() );

     $('#guide_adult_total').val( $('#guide_adult_total_ui').text() || '0' );
     $('#guide_child_total').val( $('#guide_child_total_ui').text() || '0' );
     $('#guide_per_adult_input').val( $('#guide_per_adult').text() );
     $('#guide_per_child_input').val( $('#guide_per_child').text() );

    /* ================= VISA FEE ================= */
    $('#visa_total').val( $('#visa_total_ui').text() || '0' );
    $('#visa_adult_base').val( $('#visa_per_adult_ui').text() || '0' );
    $('#visa_extra_adult').val( $('#visa_per_extra_adult_ui').text() || '0' );
    $('#visa_child_with_bed').val( $('#visa_per_child_ui').text() || '0' );
    $('#visa_child_no_bed').val( $('#visa_per_child_no_bed_ui').text() || '0' );

     /* ================= TIP AMOUNT ================= */
    $('#tip_total').val( $('#tip_total_ui').text() || '0' );
    $('#tip_adult_base').val( $('#tip_per_adult_ui').text() || '0' );
    $('#tip_extra_adult').val( $('#tip_per_extra_adult_ui').text() || '0' );
    $('#tip_child_with_bed').val( $('#tip_per_child_ui').text() || '0' );
    $('#tip_child_no_bed').val( $('#tip_per_child_no_bed_ui').text() || '0' );

     $('#grand_adult_total').val( $('#grand_adult_total_ui').text() || '0' );
     $('#grand_child_total').val( $('#grand_child_total_ui').text() || '0' );
     $('#grand_per_adult_input').val( $('#grand_per_adult_ui').text() );
     $('#grand_per_child_input').val( $('#grand_per_child_ui').text() );

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
 }
)

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
  const visa = parseUiNumber('#visa_total_ui');
const tip  = parseUiNumber('#tip_total_ui');

return hotel + activity + meal + transport + guide + visa + tip;

}

/* ================= TRANSPORT TOTAL ================= */
function updateTransportTotals() {
  let total = 0;

  $('#travelPlan tbody tr').each(function () {
    const v = parseFloat($(this).find('.car-rent-price').val());
    if (!isNaN(v)) {
      total += v;
    }
  });

  // UI
  $('#transport_total_ui').text(total.toFixed(2));

  // hidden field (for save)
  $('#transport_total').val(total.toFixed(2));
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
}

// ---------- render extra-charge rows into summary ----------
function updateExtraChargeSummary() {

  // remove old extra rows
  $(".extra-summary-row").remove();

  let totalExtra = 0;

  $("#extraChargeBody tr").each(function () {

    const name = $(this).find("input[name='extra_charge_name[]']").val().trim();
    const amount = parseFloat(
      $(this).find("input[name='extra_charge_amount[]']").val()
    ) || 0;

    if (name !== "" && amount > 0) {

      const rowHtml = `
        <tr class="extra-summary-row">
          <td>${$('<div>').text(name).html()}</td>
          <td>${amount.toFixed(2)}</td>
          <td>0</td>
          <td>0</td>
          <td>0</td>
          <td>0</td>
        </tr>
      `;

      // 🔥 INSERT JUST BEFORE GRAND TOTAL ROW
      $("#costSummaryPanel table tfoot tr:first").before(rowHtml);

      totalExtra += amount;
    }
  });

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

$(function () {

 $('#saveQuotation').off('click').on('click', function (e) {
  e.preventDefault();

  try {
    if (typeof updateFinalCostPanel === 'function') {
      updateFinalCostPanel();
    }

    if (typeof buildFinalTotalsObject !== 'function') {
      throw new Error('buildFinalTotalsObject not available');
    }

    // ✅ BUILD PAYLOAD ONCE
    const payload = {};
    payload.totals  = buildFinalTotalsObject();
    payload.hotels  = collectHotels();
    payload.travels = collectTravels(); // 🔥 REQUIRED

    // 🔎 DEBUG (VERY IMPORTANT)
    console.log('TRAVELS =>', payload.travels);

    if (!payload.travels.length) {
      alert('No travel data found');
      return;
    }

    // 🔎 CHECK ACTIVITY IDS
    payload.travels.forEach(t => {
      console.log(
        'DAY', t.day_no,
        'ACTIVITIES', t.activity_ids
      );
    });

    $('#quotation_json').val(JSON.stringify(payload));
    $('#quotationForm').submit();

  } catch (err) {
    console.error(err);
    alert('Save failed. Please check console');
  }
});
});


</script>
 <!-- External site JS (keep your current file - Option A) -->
   
</body>
</html>
