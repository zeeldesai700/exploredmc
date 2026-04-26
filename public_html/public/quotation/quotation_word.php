   <?php
   error_reporting(0);
   ini_set('display_errors', 0);

   /* ================= CLEAN OUTPUT ================= */
   while (ob_get_level()) {
      ob_end_clean();
   }

   require_once __DIR__ . '/../../config/db.php';
   require_once __DIR__ . '/../../vendor/autoload.php';

   use PhpOffice\PhpWord\PhpWord;
   use PhpOffice\PhpWord\IOFactory;
   use PhpOffice\PhpWord\SimpleType\Jc;

   /* ================= SAFE TEXT ================= */
   function clean($text) {
    if ($text === null) return '';
    $text = strip_tags($text);
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
   /* ================= SECTION TITLE BAR ================= */
   function sectionTitle($section, $text) {

      $section->addTextBreak();

      $tbl = $section->addTable('sectionTitleTbl');

      $tbl->addRow();

      $cell = $tbl->addCell(10000, [
         'bgColor' => 'F3F1CF'
      ]);

      $cell->addText(
         $text,
         ['bold' => true],
         ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
      );
   }

   /* ================= GET ID ================= */
   $id = (int)($_GET['id'] ?? 0);
   if ($id <= 0) exit;

   /* ================= FETCH QUOTATION ================= */
   $q = $conn->query("
   SELECT q.*, c.name customer_name, co.name country_name
   FROM quotations q
   LEFT JOIN customers c ON q.customer_id = c.id
   LEFT JOIN countries co ON q.country_id = co.id
   WHERE q.id = $id
   ")->fetch_assoc();

   if (!$q) exit;

   /* ================= FETCH HOTELS ================= */
   $hotelsRes = $conn->query("
   SELECT qh.*, ci.name city_name, ht.name hotel_name,
         ht.category hotel_star, rc.room_category room_name
   FROM quotation_hotels qh
   LEFT JOIN cities ci ON qh.city_id = ci.id
   LEFT JOIN hotels ht ON qh.hotel_id = ht.id
   LEFT JOIN hotel_rooms rc ON qh.room_category_id = rc.id
   WHERE qh.quotation_id = $id
   ORDER BY qh.option_no, qh.id
   ");

   $hotelGroups = [];
   while ($r = $hotelsRes->fetch_assoc()) {
      $hotelGroups[(int)$r['option_no']][] = $r;
   }

   function get_travel_activities(mysqli $conn, int $travel_id): string {
      $res = $conn->query("
         SELECT sa.activity_name
         FROM quotation_travel_activities qta
         JOIN sightseeing_activities sa ON sa.id = qta.activity_id
         WHERE qta.quotation_travel_id = $travel_id
         ORDER BY sa.activity_name
      ");
      if (!$res || $res->num_rows === 0) return '';
      $arr = [];
      while ($r = $res->fetch_assoc()) {
         $arr[] = $r['activity_name'];
      }
      return implode(', ', $arr);
   }

   function get_travel_cars_pdf(mysqli $conn, int $travel_id): string {
      $res = $conn->query("
         SELECT c.car_name, c.seater
         FROM quotation_travel_cars tc
         JOIN cars c ON c.id = tc.car_id
         WHERE tc.quotation_travel_id = $travel_id
      ");
      if (!$res || $res->num_rows === 0) return '—';
      $cars = [];
      while ($r = $res->fetch_assoc()) {
         $cars[] = $r['car_name'].' ('.$r['seater'].' Seater)';
      }
      return implode(' + ', $cars);
   }

   function get_travel_car_count(mysqli $conn, int $travel_id): int {
      $res = $conn->query("
         SELECT COUNT(*) cnt
         FROM quotation_travel_cars
         WHERE quotation_travel_id = $travel_id
      ");
      $row = $res ? $res->fetch_assoc() : ['cnt'=>0];
      return (int)$row['cnt'];
   }

   /* ================= FETCH TRAVELS ================= */
   $travelsRes = $conn->query("
   SELECT 
      qt.*,
      sp.name AS sightseeing_name,
      ml.category AS meal_name
   FROM quotation_travels qt
   LEFT JOIN sightseeings sp ON qt.sightseeing_id = sp.id
   LEFT JOIN meals ml ON qt.meal_id = ml.id
   WHERE qt.quotation_id = $id
   ORDER BY qt.day_no
   ");


   $travels = [];
   while ($row = $travelsRes->fetch_assoc()) {
      $travels[] = $row;
   }

   /* ================= COST ================= */
   $otherPerAdult =
      $q['activity_per_adult'] +
      $q['meal_per_adult'] +
      $q['transport_per_adult'] +
      $q['guide_per_adult'] +
      $q['visa_per_adult'] +
      $q['tip_per_adult'];

   $otherCostTotal =
      $q['activity_total'] +
      $q['meal_total'] +
      $q['transport_total'] +
      $q['guide_total'] +
      $q['visa_total'] +
      $q['tip_total'] +
      $q['extra_total'];

   /* ================= INIT PHPWORD ================= */
$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(10);

/* ================= SECTION ================= */
$section = $phpWord->addSection([
    'marginTop'    => 700,
    'marginBottom' => 900,
    'marginLeft'   => 700,
    'marginRight'  => 700,
]);

/* ================= EMPTY HEADER (NO LOGO) ================= */
$header = $section->addHeader();
$header->firstPage();

/* ================= TITLE ================= */
$section->addText(
    'Quotation',
    ['bold' => true, 'size' => 16],
    ['alignment' => Jc::CENTER]
);

$section->addText(
    "{$q['nights']} Night {$q['days']} Days {$q['country_name']} Package",
    ['bold' => true],
    ['alignment' => Jc::CENTER]
);

/* ================= LOGO (CENTERED IN BODY) ================= */

$section->addImage(__DIR__ . '/../assets/logo.png', [
    'width'     => 140,
    'alignment' => Jc::CENTER
]);



/* ================= TABLE STYLES ================= */
$phpWord->addTableStyle('tbl', [
    'borderSize'  => 4,
    'borderColor' => '999999',
    'cellMargin'  => 70
]);

$phpWord->addTableStyle('paxTbl', [
    'borderSize'  => 4,
    'borderColor' => '999999',
    'cellMargin'  => 70
]);

$phpWord->addTableStyle('noteTbl', [
    'borderSize' => 0,
    'cellMargin' => 70
]);

/* ================= CUSTOMER INFO ================= */
$t = $section->addTable('tbl');
$t->addRow();

$t->addCell(5000)->addText(
    clean($q['customer_name']),
    ['bold' => true]
);

$right = $t->addCell(5000);
$right->addText("Quotation No: {$q['quotation_no']}", null, ['alignment'=>Jc::RIGHT]);
$right->addText("Quotation Date: ".date('d M Y',strtotime($q['created_at'])), null, ['alignment'=>Jc::RIGHT]);
$right->addText("Destination: {$q['country_name']}", null, ['alignment'=>Jc::RIGHT]);

/* ================= PAX TABLE ================= */
$pax = $section->addTable('paxTbl');

/* HEADER */
$pax->addRow();
$pax->addCell(4000, ['bgColor'=>'E6E6E6'])->addText('Travel Date', ['bold'=>true]);
$pax->addCell(2000, ['bgColor'=>'E6E6E6'])->addText('Adult', ['bold'=>true]);
$pax->addCell(2000, ['bgColor'=>'E6E6E6'])->addText('Child', ['bold'=>true]);
$pax->addCell(2000, ['bgColor'=>'E6E6E6'])->addText('Infant', ['bold'=>true]);

/* DATA */
$pax->addRow();
$pax->addCell()->addText(
    date('d M Y',strtotime($q['travel_date'])) .
    ' To ' .
    date('d M Y',strtotime($q['departure_date']))
);
$pax->addCell()->addText($q['adults'] + $q['extra_adults']);
$pax->addCell()->addText($q['children'] + $q['no_bed_child']);
$pax->addCell()->addText($q['infants'] ?? 0);

/* ================= BLUE NOTE BAR ================= */
$note = $section->addTable('noteTbl');
$note->addRow();

$cell = $note->addCell(10000, [
    'bgColor' => '4F79A7'
]);

$cell->addText(
    'THE COSTS PROVIDED ARE BASED ON A PER PERSON BASIS IN [USD]',
    ['bold'=>true, 'color'=>'FFFFFF'],
    ['alignment'=>Jc::CENTER]
);
$section->addTextBreak(1);

/* ================= VISA / TIP (PDF LOGIC MATCHED) ================= */

$visaText = '';
$tipText  = '';

if (!empty($q['visa_total']) && (float)$q['visa_total'] > 0) {
    $visaText = 'VISA: ' . strtoupper($q['country_name']);
}

if (!empty($q['tip_total']) && (float)$q['tip_total'] > 0) {
    $tipText = 'TIP INCLUDED';
}

if ($visaText || $tipText) {

    $vt = $section->addTable([
        'borderSize'  => 6,
        'borderColor' => '999999',
        'cellMargin'  => 120,
        'width'       => 100 * 50
    ]);

    $vt->addRow();

    // LEFT CELL – VISA
    $vt->addCell(5000)->addText(
        $visaText ?: ' ',
        ['bold' => true],
        ['alignment' => Jc::LEFT]
    );

    // RIGHT CELL – TIP
    $vt->addCell(5000)->addText(
        $tipText ?: ' ',
        ['bold' => true],
        ['alignment' => Jc::LEFT]
    );
}

 $otherPerAdult =
      $q['activity_per_adult'] +
      $q['meal_per_adult'] +
      $q['transport_per_adult'] +
      $q['guide_per_adult'] +
      $q['visa_per_adult'] +
      $q['tip_per_adult'];

  $otherPerExtraAdult =
      $q['activity_per_extra_adult'] +
      $q['meal_per_extra_adult'] +
      $q['transport_per_extra_adult'] +
      $q['guide_per_extra_adult'] +
      $q['visa_per_extra_adult'] +
      $q['tip_per_extra_adult'];

  $otherPerChild =
      $q['activity_per_child'] +
      $q['meal_per_child'] +
      $q['transport_per_child'] +
      $q['guide_per_child'] +
      $q['visa_per_child'] +
      $q['tip_per_child'];

  $otherPerNoBed =
      $q['activity_per_child_no_bed'] +
      $q['meal_per_child_no_bed'] +
      $q['transport_per_child_no_bed'] +
      $q['guide_per_child_no_bed'] +
      $q['visa_per_child_no_bed'] +
      $q['tip_per_child_no_bed'];

  $otherCostTotal = (
      $q['activity_total'] +
      $q['meal_total'] +
      $q['transport_total'] +
      $q['guide_total'] +
      $q['visa_total'] +
      $q['tip_total'] +
      $q['extra_total']
  );

/* ================= HOTELS (WORD – FINAL & MATCHED) ================= */


$adults      = (int)$q['adults'];
$extraAdults = (int)$q['extra_adults'];
$children    = (int)$q['children'];
$noBed       = (int)$q['no_bed_child'];

foreach ($hotelGroups as $opt => $hotels) {

    if (empty($hotels)) continue;

    /* ================= SECTION TITLE ================= */
    sectionTitle($section, "HOTEL OPTION $opt");

    /* ================= HOTEL TABLE ================= */
    $ht = $section->addTable('tbl');

    $ht->addRow();
    $ht->addCell(2500)->addText('HOTEL NAME', ['bold'=>true]);
    $ht->addCell(1500)->addText('DESTINATION', ['bold'=>true]);
    $ht->addCell(2000)->addText('ROOM TYPE', ['bold'=>true]);
    $ht->addCell(900)->addText('MEAL PLAN', ['bold'=>true]);
    $ht->addCell(1100)->addText('ROOMS', ['bold'=>true]);
    $ht->addCell(2000)->addText('STAY', ['bold'=>true]);

    $startDate = strtotime($q['travel_date']);

    /* ================= DISPLAY HOTEL ROWS (NO CALC) ================= */
    foreach ($hotels as $h) {

        $nights = (int)$h['stay_nights'];

        $from = date('d M Y', $startDate);
        $to   = date('d M Y', strtotime("+{$nights} days", $startDate));
        $startDate = strtotime($to);

        $roomsText = "DBL : {$q['rooms']}";
        if ($extraAdults > 0) $roomsText .= "\nAdult Extra Bed: {$extraAdults}";
        if ($children > 0) $roomsText .= "\nCWB : {$children}";
        if ($noBed > 0)    $roomsText .= "\nCNB : {$noBed}";

        $ht->addRow();
        $ht->addCell()->addText(
            clean($h['hotel_name']) . "\n" . $h['hotel_star']
        );
        $ht->addCell()->addText(
            clean($h['city_name']) . "\n{$nights} Night"
        );
        $ht->addCell()->addText(clean($h['room_name']));
        $ht->addCell()->addText('BB', null, ['alignment'=>Jc::CENTER]);
        $ht->addCell()->addText($roomsText);
        $ht->addCell()->addText("$from To $to");
    }

   /* ================= COST SUMMARY (FINAL – SAME AS VIEW & PDF) ================= */

sectionTitle($section, 'COST SUMMARY');
$cost = $section->addTable('tbl');

/* ================= INIT TOTALS ================= */
$hotelOptionTotal   = 0;

$perAdultHotelSum   = 0;
$perExtraAdultSum   = 0;
$perChildHotelSum   = 0;
$perNoBedHotelSum   = 0;

/* ================= CALCULATE ALL HOTELS ================= */
foreach ($hotels as $h) {

    $nights = (int)$h['stay_nights'];

    // ✅ OPTION-WISE HOTEL TOTAL
    $hotelOptionTotal += (float)$h['price'];

    // ✅ OPTION-WISE PER PERSON (NO /2, NO adults multiplication)
    $perAdultHotelSum += (float)$h['base_price'];

    $perExtraAdultSum += (float)$h['extra_adult_price'] * $nights;
    $perChildHotelSum += (float)$h['child_price'] * $nights;
    $perNoBedHotelSum += (float)$h['nobed_price'] * $nights;
}

/* ================= FINAL PER PERSON ================= */
$perAdult      = $otherPerAdult      + $perAdultHotelSum;
$perExtraAdult = $otherPerExtraAdult + $perExtraAdultSum;
$perChild      = $otherPerChild      + $perChildHotelSum;
$perNoBed      = $otherPerNoBed      + $perNoBedHotelSum;

/* ================= FINAL TOTAL ================= */
$finalTotal = $otherCostTotal + $hotelOptionTotal;

 
/* ================= ROWS ================= */

if ($perAdult > 0) {
    $cost->addRow();
    $cost->addCell(3000)->addText('Per Adult');
    $cost->addCell(1300)->addText('USD ' . number_format($perAdult, 2));
}

if ($perExtraAdult > 0) {
    $cost->addRow();
    $cost->addCell(3000)->addText('Per Extra Adult');
    $cost->addCell(1300)->addText('USD ' . number_format($perExtraAdult, 2));
}

if ($perChild > 0) {
    $cost->addRow();
    $cost->addCell(3000)->addText('Per Child With Bed');
    $cost->addCell(1300)->addText('USD ' . number_format($perChild, 2));
}

if ($perNoBed > 0) {
    $cost->addRow();
    $cost->addCell(3000)->addText('Per Child No Bed');
    $cost->addCell(1300)->addText('USD ' . number_format($perNoBed, 2));
}

/* ================= GRAND TOTAL ================= */

$cost->addRow();
$cost->addCell(3000)->addText('TOTAL PACKAGE COST', ['bold' => true]);
$cost->addCell(1300)->addText(
    'USD ' . number_format($finalTotal, 0),
    ['bold' => true]
);
}

/* =======================================================
   COST SUMMARY (NO HOTEL CASE – WORD)
======================================================= */

if (empty($hotelGroups)) {

    sectionTitle($section, 'COST SUMMARY');
    $cost = $section->addTable('tbl');

    // ✅ TOTAL = ONLY OTHER COSTS (same as HTML)
    $finalTotal = $otherCostTotal;

    if ($otherPerAdult > 0) {
        $cost->addRow();
        $cost->addCell(3000)->addText('Per Adult');
        $cost->addCell(1300)->addText(
            'USD ' . number_format($otherPerAdult, 2)
        );
    }

    if ($otherPerExtraAdult > 0) {
        $cost->addRow();
        $cost->addCell(3000)->addText('Per Extra Adult');
        $cost->addCell(1300)->addText(
            'USD ' . number_format($otherPerExtraAdult, 2)
        );
    }

    if ($otherPerChild > 0) {
        $cost->addRow();
        $cost->addCell(3000)->addText('Per Child With Bed');
        $cost->addCell(1300)->addText(
            'USD ' . number_format($otherPerChild, 2)
        );
    }

    if ($otherPerNoBed > 0) {
        $cost->addRow();
        $cost->addCell(3000)->addText('Per Child No Bed');
        $cost->addCell(1300)->addText(
            'USD ' . number_format($otherPerNoBed, 2)
        );
    }

    // ✅ TOTAL (always shown)
    $cost->addRow();
    $cost->addCell(3000)->addText(
        'TOTAL PACKAGE COST',
        ['bold' => true]
    );
    $cost->addCell(1300)->addText(
        'USD ' . number_format($finalTotal, 0),
        ['bold' => true]
    );
}

   /* ================= SIGHTSEEING / ACTIVITIES ================= */
   sectionTitle($section, 'SIGHTSEEING / ACTIVITIES');

   $sg = $section->addTable('tbl');

   /* HEADER */
   $sg->addRow();
   $sg->addCell(2500)->addText('ACTIVITY',['bold'=>true]);
   $sg->addCell(1000)->addText('TYPE',['bold'=>true]);
   $sg->addCell(3000)->addText('VEHICLE',['bold'=>true]);
   $sg->addCell(1200)->addText('NO. OF VEHICLE',['bold'=>true]);
   $sg->addCell(1400)->addText('DATE',['bold'=>true]);
   $sg->addCell(1200)->addText('GUIDE COST',['bold'=>true]);

   foreach ($travels as $t) {

      $activityNames = trim(get_travel_activities($conn, (int)$t['id']));
      if ($activityNames === '') continue;

      $vehicle      = get_travel_cars_pdf($conn, (int)$t['id']);
      $vehicleCount = get_travel_car_count($conn, (int)$t['id']);

      $sg->addRow();
      $sg->addCell()->addText(clean($activityNames));
      $sg->addCell()->addText('PVT',['alignment'=>Jc::CENTER]);
      $sg->addCell()->addText($vehicle ?: '—');
      $sg->addCell()->addText($vehicleCount ?: '—',['alignment'=>Jc::CENTER]);
      $sg->addCell()->addText(
         !empty($t['day_date'])
               ? date('d M Y', strtotime($t['day_date']))
               : '—',
         null,
         ['alignment'=>Jc::CENTER]
      );
      $sg->addCell()->addText(
         ($t['guide'] === 'Yes' ? 'Yes' : 'No'),
         null,
         ['alignment'=>Jc::CENTER]
      );
   }


   /* ================= TRANSFER ================= */
   sectionTitle($section, 'TRANSFER');

   $tr = $section->addTable('tbl');

   /* HEADER */
   $tr->addRow();
   $tr->addCell(3500)->addText('TRANSFER',['bold'=>true]);
   $tr->addCell(1000)->addText('TYPE',['bold'=>true]);
   $tr->addCell(3000)->addText('VEHICLE',['bold'=>true]);
   $tr->addCell(1200)->addText('NO. OF VEHICLE',['bold'=>true]);
   $tr->addCell(2000)->addText('MEAL',['bold'=>true]);

   foreach ($travels as $t) {

      if (empty($t['sightseeing_name']) && empty($t['car_id'])) {
         continue;
      }

      $vehicle      = get_travel_cars_pdf($conn, (int)$t['id']);
      $vehicleCount = get_travel_car_count($conn, (int)$t['id']);

      $mealText = (!empty($t['meal_id']) && !empty($t['meal_name']))
         ? 'Yes ('.$t['meal_name'].')'
         : 'No';

      $tr->addRow();
      $tr->addCell()->addText(clean($t['sightseeing_name'] ?: '—'));
      $tr->addCell()->addText('PVT',['alignment'=>Jc::CENTER]);
      $tr->addCell()->addText($vehicle ?: '—');
      $tr->addCell()->addText($vehicleCount ?: '—',['alignment'=>Jc::CENTER]);
      $tr->addCell()->addText($mealText,['alignment'=>Jc::CENTER]);
   }



 /* ================= DAY WISE ITINERARY ================= */
sectionTitle($section, 'DAY WISE ITINERARY');

foreach ($travels as $t) {

    // Create bordered box (table)
    $dayTbl = $section->addTable([
        'borderSize'  => 6,
        'borderColor' => '999999',
        'cellMargin'  => 150,
        'width'       => 100 * 50
    ]);

    // 🔒 VERY IMPORTANT: prevent page break inside this row
    $dayTbl->addRow(null, [
        'cantSplit' => true
    ]);

    $cell = $dayTbl->addCell(10000, [
        'vAlign' => 'top'
    ]);

    /* ---------- ROUTE / DAY TITLE (BOLD) ---------- */
    if (!empty($t['route_text'])) {
        $cell->addText(
            clean($t['route_text']),
            ['bold' => true],
            ['spaceAfter' => 200]
        );
    }

    /* ---------- ITINERARY DESCRIPTION ---------- */
    if (!empty($t['itinerary_text'])) {

        // Preserve line breaks like PDF
        $lines = preg_split("/\r\n|\n|\r/", $t['itinerary_text']);

        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            $cell->addText(
                clean($line),
                [],
                ['spaceAfter' => 120]
            );
        }
    }

    // Space AFTER the box (not inside)
    $section->addTextBreak(1);
}


  /* ================= INCLUSION ================= */
sectionTitle($section, 'INCLUSION');

$section->addListItem("Transportation with air-conditioned as program PVT.");
$section->addListItem("Tour arrival and departure airport transfers.");
$section->addListItem("Accommodation base on a twin-share basis.");
$section->addListItem("Breakfast (check-in time: 14:00 / Check-out time: 12:00).");
$section->addListItem("English Speaking Guide as per mentioned above itinerary.");
$section->addListItem("All entrance fee, meals as mentioned in program (B - Breakfast, L - Lunch, D - Dinner).");
$section->addListItem("Mineral water: 2 bottles per person per day.");

/* ================= EXCLUSION ================= */
sectionTitle($section, 'EXCLUSION');

$section->addListItem(
    "Guide available where mentioned; extra guide charges apply for additional days."
);

$section->addListItem(
    "International airfare to and from Vietnam, and internal domestic airfare."
);

$section->addListItem(
    "Beverages and other meals not indicated in the program."
);

$section->addListItem(
    "Early check-in and late check-out at all hotels."
);

$section->addListItem(
    "Travel insurance of all kinds and personal expenses (laundry, telephone, shopping, etc.)."
);

$section->addListItem(
    "Gratuities (approximately USD 3 per person per day)."
);

$section->addListItem(
    "Any additional expenses caused by circumstances beyond our control, such as natural calamities (typhoons, floods), flight delays, rescheduling or cancellations, accidents, medical evacuations, riots, strikes, etc."
);

$section->addListItem(
    "Surcharge for peak seasons: New Year (01–05 Jan), Lunar New Year (13 Feb–22 Feb), Southern Liberation Day (29 Apr–03 May), National Day (30 Aug–03 Sep), Christmas (20–31 Dec)."
);


/* ================= IMPORTANT NOTES ================= */
sectionTitle($section, 'IMPORTANT NOTES');

$section->addListItem(
    "Price based on twin or triple sharing room per person. Hotel availability subject to confirmation."
);

$section->addListItem(
    "Prices quoted in USD and subject to change without prior notice."
);

$section->addListItem(
    "This quotation does not imply or constitute availability."
);

$section->addListItem(
    "If listed hotel is unavailable, a similar category hotel will be provided."
);

$section->addListItem(
    "Compulsory Gala Dinner applicable on Christmas Eve and New Year Eve."
);

$section->addListItem(
    "Airfare is the same for children (2 years and above) and adults."
);

$section->addListItem(
    "Tip is compulsory for driver and guide: USD 3 per pax per day."
);

$section->addListItem(
    "In any airport pickup /drop 1 bag of 20Kg luggage bag per person and 1 backpack 7 kg per person allowed in car, if more bags extra charges pay by guest."
);

$section->addListItem(
    "Child below 100 cm complimentary."
);

$section->addTextBreak(1);

$section->addText(
    "You are advised to book hotels in the following areas to avoid additional transfer charges. Recommended areas by city are:"
);

$section->addTextBreak(1);

$section->addListItem("Hanoi: Old Quarter Area");
$section->addListItem("Sapa: Sapa Town");
$section->addListItem("Halong Bay: Tuan Chau Marina");
$section->addListItem("Danang: My Khe Beach");
$section->addListItem("Ho Chi Minh City: District 1 and District 3");
$section->addListItem("Phu Quoc: Duong Dong Town");

$section->addTextBreak(1);

$section->addText(
    "Booking hotels within these areas will help minimize transfer costs. Hotels booked outside these areas may incur additional transfer charges."
);

$section->addTextBreak(1);

$section->addText(
    "Phu Quoc: Kiss of the Sea Show is closed on Tuesdays.",
    ['bold' => true]
);

/* ================= CANCELLATION POLICY ================= */
sectionTitle($section, 'CANCELLATION POLICY');

$section->addListItem("All cancellation must be made in writing.");
$section->addListItem("Any cancellation after confirmation of booking: 10 percent charge.");
$section->addListItem("Any cancellation between 29 Days - 15 Days prior to arrival date: 75 percent of tour fare charge.");
$section->addListItem("Any cancellation less than 14 Days: 100 percent of tour fare charge.");


   /* ================= FOOTER ================= */
   $footer = $section->addFooter();
   $footer->addPreserveText(
      'Page {PAGE} of {NUMPAGES}',
      null,
      ['alignment'=>Jc::CENTER]
   );

   /* ================= SAVE & DOWNLOAD ================= */
   $tmp = sys_get_temp_dir()."/Quotation_{$q['quotation_no']}.docx";
   $writer = IOFactory::createWriter($phpWord,'Word2007');
   $writer->save($tmp);

   while (ob_get_level()) ob_end_clean();

   header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
   header('Content-Disposition: attachment; filename="Quotation_'.$q['quotation_no'].'.docx"');
   header('Content-Length: '.filesize($tmp));
   readfile($tmp);
   unlink($tmp);
   exit;
