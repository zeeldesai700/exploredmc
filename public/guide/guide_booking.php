<?php
require_once __DIR__.'/../../config/db.php';

/* ================= CITIES ================= */

$cities = $conn->query("
SELECT DISTINCT city_name
FROM confirmation_guide
ORDER BY city_name
");

/* ================= CONFIRMATIONS ================= */

$confirmations = $conn->query("
SELECT DISTINCT confirmation_no
FROM confirmation_guide
ORDER BY confirmation_no DESC
");

/* ================= MAIN DATA ================= */

$data = [];

$res = $conn->query("
SELECT 
id,
confirmation_no,
city_name,
guide_date,
agent_name,
user_name,
guide_name,
guide_mobile,
car,
car_driver_name,
car_driver_mobile,
car_status,
action_status,
guide,
passport_status,
passport_name,
passport_no
FROM confirmation_guide
ORDER BY confirmation_no, city_name, guide_date
");

while($r = $res->fetch_assoc()){
    $data[$r['confirmation_no']][$r['city_name']][] = $r;
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>

<!DOCTYPE html>
<html>
<head>

<title>Operations Booking</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.table-container{
max-height:650px;
overflow:auto;
border:1px solid #ccc;
}
table{
border-collapse:collapse;
width:100%;
}
table th, table td{
border:2px solid #444;
text-align:center;
vertical-align:middle;
font-size:13px;
padding:6px;
}
thead th{
position:sticky;
top:0;
z-index:5;
background:#fff;
}
.section-header th{
font-weight:bold;
}
.city-header th{
top:40px;
font-weight:bold;
}
.guide-info{
font-size:12px;
margin-top:5px;
}
.na-box{
color:red;
font-weight:bold;
background:#80d0f8;
padding:8px 14px;
border-radius:4px;
}
</style>

</head>

<body class="p-4">

<h3 class="mb-4">Operations Booking</h3>

<button class="btn btn-primary" onclick="syncData()">
🔄 Sync Data
</button>


<div class="table-container">
<table class="table">

<thead>

<tr class="section-header">

<th rowspan="2">Confirmation</th>
<th rowspan="2">Agent</th>
<th rowspan="2">User</th>

<?php
$cityList=[];
while($c=$cities->fetch_assoc()){
    $cityList[]=$c['city_name'];
}
?>

<th colspan="<?= count($cityList) ?>">TRANSPORT</th>
<th colspan="<?= count($cityList) ?>">GUIDE</th>
<th rowspan="2">PASSPORT (HALONG BAY)</th>

</tr>

<tr class="city-header">

<?php
foreach($cityList as $city){
    echo "<th>$city</th>";
}
foreach($cityList as $city){
    echo "<th>$city</th>";
}
?>

</tr>

</thead>

<tbody>

<?php while($row=$confirmations->fetch_assoc()){ 

$cn=$row['confirmation_no'];

?>

<tr>

<td><b><?= $cn ?></b></td>

<?php
$agent='-';
$user='-';

if(isset($data[$cn])){
    $firstCityRows = reset($data[$cn]);
    $firstRow = $firstCityRows[0] ?? [];

    $agent = $firstRow['agent_name'] ?? '-';
    $user  = $firstRow['user_name'] ?? '-';
}
?>

<td><?= $agent ?></td>
<td><?= $user ?></td>

<!-- ================= TRANSPORT ================= -->

<?php foreach($cityList as $city){

$cells = $data[$cn][$city] ?? [];

$carRequired=false;
$carBooked=false;
$carRowId=null;
$driverName='';
$driverMobile='';

foreach($cells as $cell){

if(($cell['car'] ?? '')=='yes'){
$carRequired=true;
$carRowId=$cell['id'];
}

if(($cell['car_status'] ?? '')=='yes'){
$carBooked=true;
$driverName=$cell['car_driver_name'] ?? '';
$driverMobile=$cell['car_driver_mobile'] ?? '';
}

}
?>

<td>

<?php if(!$carRequired){ ?>

<span class="na-box">N/A</span>

<?php } elseif($carBooked){ ?>

<button class="btn btn-success btn-sm">Booked</button>

<div class="guide-info">
<?= htmlspecialchars($driverName) ?><br>
<?= htmlspecialchars($driverMobile) ?>
</div>

<?php } else { ?>

<button class="btn btn-danger btn-sm"
onclick="openCarModal(<?= $carRowId ?>)">
Book
</button>

<?php } ?>

</td>

<?php } ?>

<!-- ================= GUIDE ================= -->

<?php foreach($cityList as $city){

$cells = $data[$cn][$city] ?? [];

$guideRequired=false;
$guideBooked=false;
$guideRowId=null;
$guideName='';
$guideMobile='';

foreach($cells as $cell){

if(($cell['guide'] ?? '')=='yes'){
$guideRequired=true;
$guideRowId=$cell['id'];
}

if(($cell['action_status'] ?? '')=='yes'){
$guideBooked=true;
$guideName=$cell['guide_name'];
$guideMobile=$cell['guide_mobile'];
}

}
?>

<td>

<?php if(!$guideRequired){ ?>

<span class="na-box">N/A</span>

<?php } elseif($guideBooked){ ?>

<button class="btn btn-success btn-sm">Booked</button>

<div class="guide-info">
<?= htmlspecialchars($guideName) ?><br>
<?= htmlspecialchars($guideMobile) ?>
</div>

<?php } else { ?>

<button class="btn btn-danger btn-sm"
onclick="openModal(<?= $guideRowId ?>)">
Book
</button>

<?php } ?>

</td>

<?php } ?>

<!-- ================= PASSPORT (HALONG BAY ONLY) ================= -->

<?php

$passportRequired = false;
$passportBooked   = false;
$passportRowId    = null;
$passportName     = '';
$passportNo       = '';

if(isset($data[$cn])){
foreach($data[$cn] as $cityName => $cells){

if(strtoupper(trim($cityName)) == 'HALONG BAY'){

$passportRequired = true;

foreach($cells as $cell){

// get first row id
if(!$passportRowId){
    $passportRowId = $cell['id'];
}

/* ✅ FIX: STOP LOOP WHEN FOUND */
if(
    !empty($cell['passport_name']) &&
    !empty($cell['passport_no'])
){
    $passportBooked = true;
    $passportName = $cell['passport_name'];
    $passportNo   = $cell['passport_no'];

    break; // 🔥 VERY IMPORTANT
}

}

break; // 🔥 stop city loop also

}

}
}
?>

<td>

<?php if(!$passportRequired){ ?>

<span class="na-box">N/A</span>

<?php } elseif($passportBooked){ ?>

<button class="btn btn-success btn-sm">Booked</button>

<?php } else { ?>

<button 
class="btn btn-danger btn-sm passport-btn"
data-id="<?= $passportRowId ?>"
onclick="openPassportModal(<?= $passportRowId ?>)">
Book
</button>

<?php } ?>

</td>
</tr>

<?php } ?>

</tbody>

</table>
</div>

<!-- GUIDE MODAL -->
<div class="modal fade" id="guideModal">
<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Guide Booking</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form method="post" action="save_guide.php">

<div class="modal-body">
<input type="hidden" name="guide_id" id="guide_id">

<div class="mb-3">
<label>Guide Name</label>
<input type="text" name="guide_name" class="form-control" required>
</div>

<div class="mb-3">
<label>Guide Mobile</label>
<input type="text" name="guide_mobile" class="form-control" required>
</div>
</div>

<div class="modal-footer">
<button class="btn btn-success">Save</button>
</div>

</form>

</div>
</div>
</div>


<!-- CAR MODAL -->
<div class="modal fade" id="carModal">
<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Transport Booking</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form method="post" action="save_car.php">

<div class="modal-body">
<input type="hidden" name="car_id" id="car_id">

<div class="mb-3">
<label>Driver Name</label>
<input type="text" name="car_driver_name" class="form-control">
</div>

<div class="mb-3">
<label>Driver Mobile</label>
<input type="text" name="car_driver_mobile" class="form-control">
</div>
</div>

<div class="modal-footer">
<button class="btn btn-success">Save</button>
</div>

</form>

</div>
</div>
</div>


<!-- PASSPORT MODAL -->
<div class="modal fade" id="passportModal">
<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Passport Booking</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form id="passportForm">

<div class="modal-body">
<input type="hidden" name="passport_id" id="passport_id">

<div class="mb-3">
<label>Passenger Name</label>
<input type="text" name="passport_name" class="form-control">
</div>

<div class="mb-3">
<label>Passport No</label>
<input type="text" name="passport_no" class="form-control">
</div>
</div>

<div class="modal-footer">
<button class="btn btn-success">Save</button>
</div>

</form>

</div>
</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>

function openModal(id){
document.getElementById("guide_id").value=id;
new bootstrap.Modal(document.getElementById('guideModal')).show();
}

function openCarModal(id){
document.getElementById("car_id").value=id;
new bootstrap.Modal(document.getElementById('carModal')).show();
}

function openPassportModal(id){
document.getElementById("passport_id").value=id;
new bootstrap.Modal(document.getElementById('passportModal')).show();
}

function showCar(name,mobile){
alert("Driver: "+name+"\nMobile: "+mobile);
}

document.getElementById("passportForm").addEventListener("submit", function(e){
e.preventDefault();

let form = this;
let formData = new FormData(form);
let id = document.getElementById("passport_id").value;

fetch("save_passport.php", {
    method: "POST",
    body: formData
})
.then(res => res.text())
.then(() => {

    let btn = document.querySelector(`.passport-btn[data-id='${id}']`);

    if(btn){
        btn.classList.remove("btn-danger");
        btn.classList.add("btn-success");
        btn.innerText = "Booked";
        btn.removeAttribute("onclick");

        // show details instantly
        let cell = btn.closest("td");
        let name = formData.get("passport_name");
        let no   = formData.get("passport_no");

        if(name || no){
            let div = document.createElement("div");
            div.className = "guide-info";
            div.innerHTML = (name || '') + "<br>" + (no || '');
            cell.appendChild(div);
        }
    }

    // close modal
    bootstrap.Modal.getInstance(document.getElementById('passportModal')).hide();

    form.reset();
});
});
function syncData(){

if(!confirm("Are you sure to sync data?")) return;

fetch("sync_guide.php")
.then(res => res.text())
.then(res => {
    alert("Sync Completed ✅");
    location.reload();
})
.catch(() => {
    alert("Error in sync ❌");
});

}
</script>

</body>
</html>