<?php
require_once __DIR__ . '/../../config/db.php';

if (isset($_POST['city_id'])) {
    $city_id = (int)$_POST['city_id'];

    $sql = "
        SELECT id, category, food, restaurant,
               adult_price, child_price, no_bed_price
        FROM meals
        WHERE city_id = $city_id
        ORDER BY category ASC, food ASC
    ";

    $res = $conn->query($sql);

    echo '<option value="">Select Meal</option>';

    while ($row = $res->fetch_assoc()) {

        $id     = (int)$row['id'];
        $cat    = htmlspecialchars($row['category']);
        $food   = htmlspecialchars($row['food']);
        $resto  = $row['restaurant'] ? 
                  ' ('.htmlspecialchars($row['restaurant']).')' : '';

        echo "<option value=\"$id\"
                data-adult=\"{$row['adult_price']}\"
                data-child=\"{$row['child_price']}\"
                data-nobed=\"{$row['no_bed_price']}\"
                data-category=\"$cat\">

                $cat - $food$resto
                (A ₹{$row['adult_price']}, C ₹{$row['child_price']},
                 NB ₹{$row['no_bed_price']})

            </option>";
    }
}
