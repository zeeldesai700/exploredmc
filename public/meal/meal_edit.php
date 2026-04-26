<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

// Fetch countries
$countries = $conn->query("SELECT id,name FROM countries ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM meals WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$meal = $stmt->get_result()->fetch_assoc();

if(!$meal){
    die("Meal not found");
}

// UPDATE
if($_SERVER['REQUEST_METHOD']==='POST'){
    $category   = $_POST['category'];
    $food       = $_POST['food'];
    $restaurant = $_POST['restaurant'];
    $adult      = $_POST['adult_price'];
    $child      = $_POST['child_price'];
    $nobed      = $_POST['no_bed_price'];
    $country_id = $_POST['country_id'];
    $city_id    = $_POST['city_id'];

    $stmt = $conn->prepare("
      UPDATE meals 
      SET category=?, food=?, restaurant=?, adult_price=?, child_price=?, no_bed_price=?, country_id=?, city_id=?
      WHERE id=?
    ");
    $stmt->bind_param("sssddiiii",
        $category,$food,$restaurant,$adult,$child,$nobed,$country_id,$city_id,$id
    );
    $stmt->execute();

    header("Location: meal_list.php"); exit;
}

// load cities for selected country
$cities = [];
if($meal['country_id']){
  $res = $conn->query("SELECT id,name FROM cities WHERE country_id=".(int)$meal['country_id']." ORDER BY name");
  $cities = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Meal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container py-4">

<h3>Edit Meal</h3>

<form method="post">

  <div class="row mb-3">
      <div class="col-md-3">
          <label>Category</label>
          <select name="category" class="form-select" required>
              <option value="Breakfast" <?=($meal['category']=='Breakfast')?'selected':''?>>Breakfast</option>
              <option value="Lunch" <?=($meal['category']=='Lunch')?'selected':''?>>Lunch</option>
              <option value="Dinner" <?=($meal['category']=='Dinner')?'selected':''?>>Dinner</option>
              <option value="Dinner" <?=($meal['category']=='Lunch n Dinner')?'selected':''?>>Lunch n Dinner</option>
          </select>
      </div>

      <div class="col-md-3">
          <label>Food</label>
          <input type="text" name="food" class="form-control"
                 value="<?=htmlspecialchars($meal['food'])?>" required>
      </div>

      <div class="col-md-3">
          <label>Restaurant</label>
          <input type="text" name="restaurant" class="form-control"
                 value="<?=htmlspecialchars($meal['restaurant'])?>" required>
      </div>
  </div>

  <div class="row mb-3">

      <div class="col-md-2">
        <label>Adult Price</label>
        <input type="number" step="0.01" name="adult_price" class="form-control"
               value="<?=$meal['adult_price']?>" required>
      </div>

      <div class="col-md-2">
        <label>Child Price</label>
        <input type="number" step="0.01" name="child_price" class="form-control"
               value="<?=$meal['child_price']?>" required>
      </div>

      <div class="col-md-2">
        <label>No-Bed Price</label>
        <input type="number" step="0.01" name="no_bed_price" class="form-control"
               value="<?=$meal['no_bed_price']?>" required>
      </div>
  </div>

  <div class="row mb-3">
      <div class="col-md-4">
          <label>Country</label>
          <select id="country" name="country_id" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach($countries as $c): ?>
                  <option value="<?=$c['id']?>" <?=($meal['country_id']==$c['id'])?'selected':''?>>
                    <?=$c['name']?>
                  </option>
              <?php endforeach;?>
          </select>
      </div>

      <div class="col-md-4">
          <label>City</label>
          <select id="city" name="city_id" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach($cities as $ci): ?>
                  <option value="<?=$ci['id']?>" <?=($meal['city_id']==$ci['id'])?'selected':''?>>
                    <?=$ci['name']?>
                  </option>
              <?php endforeach; ?>
          </select>
      </div>
  </div>

  <button class="btn btn-primary">Update</button>
  <a class="btn btn-secondary" href="meal_list.php">Cancel</a>

</form>

<script>
document.getElementById('country').addEventListener('change', function(){
    let cid=this.value;
    fetch('../fetch/get_cities.php?country_id='+cid)
      .then(r=>r.text())
      .then(html=> document.getElementById('city').innerHTML = html);
});
</script>

</body>
</html>
