<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';


require '../../vendor/autoload.php'; // PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\IOFactory;

// fetch countries
$countries = $conn->query("SELECT * FROM countries ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$msg = "";

/* ------- IMPORT EXCEL ------- */
if(isset($_POST['import_excel'])){
    if(isset($_FILES['excel']['tmp_name']) && $_FILES['excel']['tmp_name']!=""){
        $file = $_FILES['excel']['tmp_name'];

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null,true,true,true);

        $i=0; $ok=0; $err=0;

        foreach($rows as $r){
            $i++; if($i==1) continue;

            $category     = trim($r['A']);
            $food         = trim($r['B']);
            $restaurant   = trim($r['C']);
            $adult_price  = floatval($r['D']);
            $child_price  = floatval($r['E']);
            $no_bed_price = floatval($r['F']);
            $country_id   = intval($r['G']);
            $city_id      = intval($r['H']);

            if($category=="") { $err++; continue; }

            $stmt = $conn->prepare("
                INSERT INTO meals(category, food, restaurant, adult_price, child_price, no_bed_price, country_id, city_id)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param("sssddiii",
                $category, $food, $restaurant,
                $adult_price,$child_price,$no_bed_price,
                $country_id,$city_id
            );

            if($stmt->execute()) $ok++; else $err++;
        }
        $msg = "Imported: $ok success, $err failed";
    }
}

/* ------- SAVE MANUAL FORM ------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meal'])) {
    $category     = $_POST['category'];
    $food         = $_POST['food'];
    $restaurant   = $_POST['restaurant'];
    $adult_price  = $_POST['adult_price'];
    $child_price  = $_POST['child_price'];
    $no_bed_price = $_POST['no_bed_price'];
    $country_id   = $_POST['country_id'];
    $city_id      = $_POST['city_id'];

    $stmt = $conn->prepare("
        INSERT INTO meals(category, food, restaurant, adult_price, child_price, no_bed_price, country_id, city_id)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param("sssddiii",
        $category, $food, $restaurant,
        $adult_price,$child_price,$no_bed_price,
        $country_id,$city_id
    );
    $stmt->execute();

    header("Location: meal_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Add Meal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h3>Add Meal</h3>

<?php if($msg): ?>
<div class="alert alert-info"><?= $msg ?></div>
<?php endif; ?>

<!------------ Manual Form ----------->
<form method="post">
    <input type="hidden" name="save_meal" value="1">

    <div class="row mb-3">
        <div class="col-md-3">
            <label>Country</label>
            <select id="country" name="country_id" class="form-select" required>
                <option value="">-- Select Country --</option>
                <?php foreach ($countries as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label>City</label>
            <select id="city" name="city_id" class="form-select" required>
                <option value="">-- Select City --</option>
            </select>
        </div>

        <div class="col-md-3">
            <label>Category</label>
            <select name="category" class="form-select" required>
                <option value="">-- Select --</option>
                <option value="Breakfast">Breakfast</option>
                <option value="Lunch">Lunch</option>
                <option value="Dinner">Dinner</option>
                <option value="Lunch n Dinner">Lunch n Dinner</option>
            </select>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <label>Food</label>
            <input type="text" name="food" class="form-control" required>
        </div>

        <div class="col-md-3">
            <label>Restaurant Name</label>
            <input type="text" name="restaurant" class="form-control" required>
        </div>

        <div class="col-md-3">
            <label>Adult Price</label>
            <input type="number" step="0.01" name="adult_price" class="form-control" required>
        </div>

        <div class="col-md-3">
            <label>Child Price</label>
            <input type="number" step="0.01" name="child_price" class="form-control" required>
        </div>

        <div class="col-md-3 mt-2">
            <label>No Bed Child Price</label>
            <input type="number" step="0.01" name="no_bed_price" class="form-control" required>
        </div>
    </div>

    <button class="btn btn-success">Save</button>
</form>

<!------------ Excel Upload ----------->
<hr>
<h4>OR Upload Excel</h4>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="import_excel" value="1">

    <div class="mb-3">
        <label>Upload Excel</label>
        <input type="file" name="excel" accept=".xlsx,.xls,.csv" class="form-control" required>
    </div>

    <button class="btn btn-primary">Upload Excel</button>

    <a href="meal_template.php" class="btn btn-secondary btn-sm">⬇ Download Template</a>
</form>

<script>
document.getElementById('country').addEventListener('change', function(){
    let cid = this.value;
    let citySelect = document.getElementById('city');
    citySelect.innerHTML = "<option value=''>Loading...</option>";

    fetch('../fetch/get_cities.php?country_id=' + cid)
      .then(res=>res.text())
      .then(data=> citySelect.innerHTML=data)
      .catch(()=> citySelect.innerHTML="<option>Error</option>");
});
</script>

</body>
</html>
