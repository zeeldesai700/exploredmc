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
guide
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
top:0;
font-weight:bold;
}

.city-header th{
top:40px;
font-weight:bold;
}

.guide-date{
font-size:12px;
color:#555;
margin-bottom:4px;
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

<div class="table-container">
<table class="table operations-table">

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

<th colspan="<?= count($cityList) ?>">TRANSPORT BOOK</th>
<th colspan="<?= count($cityList) ?>">GUIDE BOOK</th>

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

<!-- ================= TRANSPORT BOOK ================= -->

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

/* BOOKED STATUS */
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

<button
class="btn btn-success btn-sm"
onclick="showCar('<?= htmlspecialchars($driverName) ?>','<?= htmlspecialchars($driverMobile) ?>')">
Booked
</button>

<?php if($driverName || $driverMobile){ ?>
<div class="guide-info">
<?= htmlspecialchars($driverName) ?><br>
<?= htmlspecialchars($driverMobile) ?>
</div>
<?php } ?>

<?php } else { ?>

<button
class="btn btn-danger btn-sm"
onclick="openCarModal(<?= $carRowId ?>)">
Book
</button>

<?php } ?>

</td>

<?php } ?>


<!-- ================= GUIDE BOOK ================= -->

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

<button
class="btn btn-success btn-sm"
onclick="showGuide('<?= htmlspecialchars($guideName) ?>','<?= htmlspecialchars($guideMobile) ?>')">
Booked
</button>

<div class="guide-info">
<?= htmlspecialchars($guideName) ?><br>
<?= htmlspecialchars($guideMobile) ?>
</div>

<?php } else { ?>

<button
class="btn btn-danger btn-sm"
onclick="openModal(<?= $guideRowId ?>)">
Book
</button>

<?php } ?>

</td>

<?php } ?>

</tr>

<?php } ?>

</tbody>

</table>


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


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>

function openModal(id){
document.getElementById("guide_id").value=id;
var modal=new bootstrap.Modal(document.getElementById('guideModal'));
modal.show();
}

function showGuide(name,mobile){
document.getElementById("view_guide_name").value=name;
document.getElementById("view_guide_mobile").value=mobile;
}

function openCarModal(id){
document.getElementById("car_id").value=id;
var modal=new bootstrap.Modal(document.getElementById('carModal'));
modal.show();
}

function showCar(name,mobile){
alert("Driver: "+name+"\nMobile: "+mobile);
}

</script>

</body>
</html>