
(function ($) {
  "use strict";

  // ---------- Utilities ----------
  const toNumber = v => Number(v || 0);
  const fix2 = v => Number(v || 0).toFixed(2);

  function debounce(fn, wait = 120) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  // header / persons helpers (read from inputs)
  const getAdults = () => toNumber($("#adults").val());
  const getExtraAdults = () => toNumber($("#extra_adults").val());
  const getTotalAdults = () => getAdults() + getExtraAdults();
  const getChildren = () => toNumber($("#children").val());
  const getNoBed = () => toNumber($("#no_bed").val());
  const getRooms = () => Math.max(1, toNumber($("#rooms").val()));

  
  
  const recalcAllRowCosts = debounce(function () {

   $(".hotel-option").each(function () {
  $(this).find(".hotel-row").each(function () {
    calcHotelCost($(this));
  });
});
    updateFinalCostPanel();
  }, 120);

  
 // ---------- calcHotelCost(row) ----------
function calcHotelCost($row) {

  try {

    const prices = $row.data("prices") || [];
    const eaArr  = $row.data("extra_adults") || [];
    const ecArr  = $row.data("extra_children") || [];
    const nbArr  = $row.data("extra_nobeds") || [];

    if (!prices.length) {
      $row.find(".price").val("0.00");
      $row.find(".base_price").val("0.00");
      return;
    }

    const rooms = Math.max(1, Number($("#rooms").val()) || 1);

    const adults      = Number($("#adults").val()) || 0;
    const extraAdults = Number($("#extra_adults").val()) || 0;
    const children    = Number($("#children").val()) || 0;
    const nobed       = Number($("#no_bed").val()) || 0;

    let total = 0;

    let adultCost = 0;   // 🔥 TOTAL BASE COST (ALL NIGHTS)
    let extraCost = 0;
    let childCost = 0;
    let nobedCost = 0;

    for (let i = 0; i < prices.length; i++) {

      const base = Number(prices[i]) || 0;

      const ea = Number(eaArr[i] ?? eaArr[0] ?? 0);
      const ec = Number(ecArr[i] ?? ecArr[0] ?? 0);
      const nb = Number(nbArr[i] ?? nbArr[0] ?? 0);

      // 🔒 BUSINESS LOGIC
      const baseNight  = base * rooms;
      const extraNight = extraAdults * ea;
      const childNight = children * ec;
      const nobedNight = nobed * nb;

      adultCost += baseNight;
      extraCost += extraNight;
      childCost += childNight;
      nobedCost += nobedNight;

      total += baseNight + extraNight + childNight + nobedNight;
    }

    /* ============================
       🔥 PER ADULT BASE PRICE
    ============================ */
    const perAdultBase =
      adults > 0
        ? (adultCost / adults)
        : 0;

    /* ============================
       STORE FOR SUMMARY + SAVE
    ============================ */
    $row.data("hotelBaseAdultCost", adultCost);
    $row.data("hotelExtraAdultCost", extraCost);
    $row.data("hotelChildWithBedCost", childCost);
    $row.data("hotelChildNoBedCost", nobedCost);

    // 🔥 THIS is what goes to DB
    $row.find(".base_price").val(perAdultBase.toFixed(2));

    // UI total
    $row.find(".price").val(total.toFixed(2));

  } catch (e) {
    console.error("calcHotelCost error:", e);
    $row.find(".price").val("0.00");
    $row.find(".base_price").val("0.00");
  }
}

function updateFinalCostPanel() {

  // ✅ REQUIRED PERSON COUNTS
  const baseAdults  = Number($("#adults").val()) || 0;
  const extraAdults = Number($("#extra_adults").val()) || 0;
  const children    = Number($("#children").val()) || 0;
  const nobed       = Number($("#no_bed").val()) || 0;

  let hotelA = 0, hotelEA = 0, hotelC = 0, hotelNB = 0;

  $(".hotel-option").each(function () {
    $(this).find(".hotel-row").each(function () {
      const r = $(this);

      hotelA  += Number(r.data("hotelBaseAdultCost")) || 0;
      hotelEA += Number(r.data("hotelExtraAdultCost")) || 0;
      hotelC  += Number(r.data("hotelChildWithBedCost")) || 0;
      hotelNB += Number(r.data("hotelChildNoBedCost")) || 0;
    });
  });

    // Activities: travel plan rows store activity-data as JSON in .activity-data & activity-price readonly input
    let actA = 0, actEA = 0, actC = 0, actNB = 0;
    $("#travelPlan tbody tr").each(function () {
      const r = $(this);
      const js = r.find(".activity-data").val();
      if (!js) return;
      let list = [];
      try { list = JSON.parse(js); } catch { list = []; }
      list.forEach(a => {
        // in their logic: adult price applies to base + extra separately
        const adultPrice = toNumber(a.adult);
        const childPrice = toNumber(a.child);

        actA += adultPrice * baseAdults;
        actEA += adultPrice * extraAdults;
        actC += childPrice * children;
        actNB += childPrice * nobed; // they used same price for nobed child
      });
    });

    // Meal
    let mealA = 0, mealEA = 0, mealC = 0, mealNB = 0;
    $("#travelPlan tbody tr").each(function () {
      const opt = $(this).find(".meal option:selected");
      if (!opt.length) return;
      const a = toNumber(opt.data("adult"));
      const c = toNumber(opt.data("child"));
      const nb = toNumber(opt.data("nobed"));
      mealA += a * baseAdults;
      mealEA += a * extraAdults;
      mealC += c * children;
      mealNB += nb * nobed;
    });

    // Transport: distribute each travel-row extra-car-price across persons present
    let trA = 0, trEA = 0, trC = 0, trNB = 0;
    $("#travelPlan tbody tr").each(function () {
      const r = $(this);
      const extra = toNumber(r.find(".extra-car-price").val());
      if (extra <= 0) return;
      const personTransportCount = baseAdults + extraAdults + children;
      if (personTransportCount <= 0) return;
      const perHead = extra / personTransportCount;
      trA += perHead * baseAdults;
      trEA += perHead * extraAdults;
      trC += perHead * children;
      // trNB remains 0 by previous logic
    });

    // Guide: distribute guide price per row
    let gdA = 0, gdEA = 0, gdC = 0, gdNB = 0;
    $("#travelPlan tbody tr").each(function () {
      const r = $(this);
      const totalGuideRow = toNumber(r.find(".guide-price").val());
      if (totalGuideRow <= 0) return;
      const personGuideCount = baseAdults + extraAdults + children;
      if (personGuideCount <= 0) return;
      const perHeadG = totalGuideRow / personGuideCount;
      gdA += perHeadG * baseAdults;
      gdEA += perHeadG * extraAdults;
      gdC += perHeadG * children;
    });

    
    // Totals per category
    const hotelTotal = hotelA + hotelEA + hotelC + hotelNB;
    const activityTotal = actA + actEA + actC + actNB;
    const mealTotal = mealA + mealEA + mealC + mealNB;
    const transportTotal = trA + trEA + trC + trNB;
    const guideTotal = gdA + gdEA + gdC + gdNB;

  function calcRowTotal(pa, pea, pc, pnb) {
  const a  = Number($("#adults").val()) || 0;
  const ea = Number($("#extra_adults").val()) || 0;
  const c  = Number($("#children").val()) || 0;
  const nb = Number($("#no_bed").val()) || 0;

  return (pa * a) + (pea * ea) + (pc * c) + (pnb * nb);
}


  const vt = getVisaTipBreakup();

const visaTotal =
  vt.visa.adult +
  vt.visa.extraAdult +
  vt.visa.child +
  vt.visa.nobed;

const tipTotal =
  vt.tip.adult +
  vt.tip.extraAdult +
  vt.tip.child +
  vt.tip.nobed;


// base (hotel + activity + meal + transport + guide)
const GA =
  hotelA + actA + mealA + trA + gdA +
  vt.visa.adult + vt.tip.adult;

const GEA =
  hotelEA + actEA + mealEA + trEA + gdEA +
  vt.visa.extraAdult + vt.tip.extraAdult;

const GC =
  hotelC + actC + mealC + trC + gdC +
  vt.visa.child + vt.tip.child;

const GNB =
  hotelNB + actNB + mealNB + trNB + gdNB +
  vt.visa.nobed + vt.tip.nobed;


  
    // Update UI (summary panel text and hidden fields)
    $("#hotel_total_ui").text(fix2(hotelTotal));
    $("#hotel_per_adult_ui").text(baseAdults > 0 ? fix2(hotelA / baseAdults) : "0.00");
    $("#hotel_per_extra_adult_ui").text(extraAdults > 0 ? fix2(hotelEA / extraAdults) : "0.00");
    $("#hotel_per_child_ui").text(children > 0 ? fix2(hotelC / children) : "0.00");
    $("#hotel_per_child_no_bed_ui").text(nobed > 0 ? fix2(hotelNB / nobed) : "0.00");

    $("#activity_total_ui").text(fix2(activityTotal));
    $("#activity_per_adult_ui").text(baseAdults > 0 ? fix2(actA / baseAdults) : "0.00");
    $("#activity_per_extra_adult_ui").text(extraAdults > 0 ? fix2(actEA / extraAdults) : "0.00");
    $("#activity_per_child_ui").text(children > 0 ? fix2(actC / children) : "0.00");
    $("#activity_per_child_no_bed_ui").text(nobed > 0 ? fix2(actNB / nobed) : "0.00");

    $("#meal_total_ui").text(fix2(mealTotal));
    $("#meal_per_adult").text(baseAdults > 0 ? fix2(mealA / baseAdults) : "0.00");
    $("#meal_per_extra_adult_ui").text(extraAdults > 0 ? fix2(mealEA / extraAdults) : "0.00");
    $("#meal_per_child").text(children > 0 ? fix2(mealC / children) : "0.00");
    $("#meal_per_child_no_bed_ui").text(nobed > 0 ? fix2(mealNB / nobed) : "0.00");

    $("#transport_total_ui").text(fix2(transportTotal));
    $("#transport_per_adult").text(baseAdults > 0 ? fix2(trA / baseAdults) : "0.00");
    $("#transport_per_extra_adult_ui").text(extraAdults > 0 ? fix2(trEA / extraAdults) : "0.00");
    $("#transport_per_child").text(children > 0 ? fix2(trC / children) : "0.00");
    $("#transport_per_child_no_bed_ui").text("0.00");

    $("#guide_total_ui").text(fix2(guideTotal));
    $("#guide_per_adult").text(baseAdults > 0 ? fix2(gdA / baseAdults) : "0.00");
    $("#guide_per_extra_adult_ui").text(extraAdults > 0 ? fix2(gdEA / extraAdults) : "0.00");
    $("#guide_per_child").text(children > 0 ? fix2(gdC / children) : "0.00");
    $("#guide_per_child_no_bed_ui").text("0.00");

   // VISA
$("#visa_total_ui").text(fix2(visaTotal));

$("#visa_per_adult_ui").text(
  baseAdults > 0 ? fix2(vt.visa.adult / baseAdults) : "0.00"
);
$("#visa_per_extra_adult_ui").text(
  extraAdults > 0 ? fix2(vt.visa.extraAdult / extraAdults) : "0.00"
);
$("#visa_per_child_ui").text(
  children > 0 ? fix2(vt.visa.child / children) : "0.00"
);
$("#visa_per_child_no_bed_ui").text(
  nobed > 0 ? fix2(vt.visa.nobed / nobed) : "0.00"
);

// TIP
$("#tip_total_ui").text(fix2(tipTotal));

$("#tip_per_adult_ui").text(
  baseAdults > 0 ? fix2(vt.tip.adult / baseAdults) : "0.00"
);
$("#tip_per_extra_adult_ui").text(
  extraAdults > 0 ? fix2(vt.tip.extraAdult / extraAdults) : "0.00"
);
$("#tip_per_child_ui").text(
  children > 0 ? fix2(vt.tip.child / children) : "0.00"
);
$("#tip_per_child_no_bed_ui").text(
  nobed > 0 ? fix2(vt.tip.nobed / nobed) : "0.00"
);


 const GRAND_TOTAL =
  hotelTotal +
  activityTotal +
  mealTotal +
  transportTotal +
  guideTotal +
  visaTotal +
  tipTotal;

$("#grand_total_ui").text(fix2(GRAND_TOTAL));
$("#grand_total").val(fix2(GRAND_TOTAL));

$("#grand_per_adult_ui").text(
  baseAdults > 0 ? fix2(GA / baseAdults) : "0.00"
);
$("#grand_per_extra_adult_ui").text(
  extraAdults > 0 ? fix2(GEA / extraAdults) : "0.00"
);
$("#grand_per_child_ui").text(
  children > 0 ? fix2(GC / children) : "0.00"
);
$("#grand_per_child_no_bed_ui").text(
  nobed > 0 ? fix2(GNB / nobed) : "0.00"
);


    // Update hidden fields for form submission (keep names used in original PHP)
    $("#hotel_total").val(fix2(hotelTotal));
    $("#activity_total").val(fix2(activityTotal));
    $("#meal_total").val(fix2(mealTotal));
    $("#transport_total").val(fix2(transportTotal));
    $("#guide_total").val(fix2(guideTotal));
    $("#visa_total").val(fix2(visaTotal));
    $("#tip_total").val(fix2(tipTotal));

    // also set breakdown hidden fields used by payload
    $("#hotel_adult_base").val(fix2(hotelA));
    $("#hotel_extra_adult").val(fix2(hotelEA));
    $("#hotel_child_with_bed").val(fix2(hotelC));
    $("#hotel_child_no_bed").val(fix2(hotelNB));

    $("#activity_adult_base").val(fix2(actA));
    $("#activity_extra_adult").val(fix2(actEA));
    $("#activity_child_with_bed").val(fix2(actC));
    $("#activity_child_no_bed").val(fix2(actNB));

    $("#meal_adult_base").val(fix2(mealA));
    $("#meal_extra_adult").val(fix2(mealEA));
    $("#meal_child_with_bed").val(fix2(mealC));
    $("#meal_child_no_bed").val(fix2(mealNB));

    $("#transport_adult_base").val(fix2(trA));
    $("#transport_extra_adult").val(fix2(trEA));
    $("#transport_child_with_bed").val(fix2(trC));
    $("#transport_child_no_bed").val("0.00");

    $("#guide_adult_base").val(fix2(gdA));
    $("#guide_extra_adult").val(fix2(gdEA));
    $("#guide_child_with_bed").val(fix2(gdC));
    $("#guide_child_no_bed").val("0.00");


    // Optionally update net_total or extra_total logic elsewhere (kept in other file)
  }

  function getVisaTipBreakup() {
    const adults      = Number($("#adults").val()) || 0;
    const extraAdults = Number($("#extra_adults").val()) || 0;
    const children    = Number($("#children").val()) || 0;
    const nobed       = Number($("#no_bed").val()) || 0;

    const visaPP = Number($("#visa_fee").val()) || 0;
    const tipPP  = Number($("#tip_amount").val()) || 0;

    return {
        visa: {
            adult: visaPP * adults,
            extraAdult: visaPP * extraAdults,
            child: visaPP * children,
            nobed: visaPP * nobed
        },
        tip: {
            adult: tipPP * adults,
            extraAdult: tipPP * extraAdults,
            child: tipPP * children,
            nobed: tipPP * nobed
        }
    };
}

/* =====================================================
   CITY CHANGE → LOAD CATEGORY (OPTION SAFE)
===================================================== */
$(document).on('change', '.city', function () {

  const cityId = $(this).val();
  const $row = $(this).closest('.hotel-row');
  const $category = $row.find('.category');

  if (!cityId) {
    $category.html('<option value="">Select Category</option>');
    return;
  }

  $category.html('<option>Loading...</option>');

  $.post('../fetch/fetch_categories.php', { city_id: cityId }, function (html) {
    $category.html(html);
  });
});


/* =====================================================
   HOTEL & ROOM POPUP (FROM–TO DATE FIXED)
===================================================== */
$(document).on('click', '.openHotelRoomPopup', function () {

  const $row = $(this).closest('tr.hotel-row');

  const city     = $row.find('.city').val();
  const category = $row.find('.category').val();
  const fromDate = $row.find('.hotel-from-date').val();
  const toDate   = $row.find('.hotel-to-date').val();

  if (!city || !category || !fromDate || !toDate) {
    alert('Select City, Category & Hotel From–To Date first');
    return;
  }

  const d1 = new Date(fromDate + 'T00:00:00');
  const d2 = new Date(toDate   + 'T00:00:00');
  const stayNights = Math.round((d2 - d1) / 86400000);

  if (stayNights <= 0) {
    alert('Invalid hotel date range');
    return;
  }

  $('tr.hotel-row').removeClass('active-hotel');
  $row.addClass('active-hotel');

  const $popup = $('#hotelRoomPopup');
  $popup.data('activeRow', $row);
  $popup.data('selectedHotel', $row.data('selectedHotel') || null);

  $('#hotelRoomList').html('Loading...');
  $popup.modal('show');

  $.get('../fetch/fetch_hotel_rooms_popup.php', {
    city_id: city,
    category: category,
    from_date: fromDate,
    to_date: toDate
  }, function (html) {
    $('#hotelRoomList').html(html);
    setTimeout(() => {
  highlightSelectedHotelRow();
}, 60);
  });
});


function highlightSelectedHotelRow() {

  const $popup = $('#hotelRoomPopup');
  const selected = $popup.data('selectedHotel');
  if (!selected) return;

  const selHotel = String(selected.hotel);
  const selRoom  = String(selected.room);

  $('#hotelRoomList tr.hotel-room-row').each(function () {

    const $r = $(this);
    const h  = String($r.attr('data-hotel-id'));
    const rm = String($r.attr('data-room-id'));

    if (h === selHotel && rm === selRoom) {

      $('#hotelRoomList tr.hotel-room-row')
        .removeClass('active-room');

      $r.addClass('active-room');

      setTimeout(() => {
        $r[0].scrollIntoView({ block: "center", behavior: "smooth" });
      }, 50);
    }
  });
}

/* =====================================================
   SELECT HOTEL ROOM (FINAL – CORRECT & SAFE)
===================================================== */
$(document).on("click", ".selectHotelRoom", function () {

  const $popup = $("#hotelRoomPopup");
  const $row   = $popup.data("activeRow");
  if (!$row) return;

  const btn = $(this);

  /* =====================================================
     1️⃣ SET HOTEL & ROOM IDs (CRITICAL)
  ===================================================== */
  const hotelId = btn.data("hotel-id");
  const roomId  = btn.data("room-id");

  $row.find(".hotel-id").val(hotelId);
  $row.find(".room-id").val(roomId);

  /* =====================================================
     2️⃣ GET PRICE ARRAYS (DATE-WISE)
  ===================================================== */
  const prices = btn.data("prices") || [];
  const eaArr  = btn.data("extra_adults") || [];
  const ecArr  = btn.data("extra_children") || [];
  const nbArr  = btn.data("extra_nobeds") || [];

  // store on row for calculations
  $row.data("prices", prices);
  $row.data("extra_adults", eaArr);
  $row.data("extra_children", ecArr);
  $row.data("extra_nobeds", nbArr);

  const nights = prices.length || 1;

  /* =====================================================
     3️⃣ PERSON COUNTS
  ===================================================== */
  const adults      = Number($("#adults").val() || 0);
  const extraAdults = Number($("#extra_adults").val() || 0);
  const children    = Number($("#children").val() || 0);
  const noBed       = Number($("#no_bed").val() || 0);

  /* =====================================================
     4️⃣ BASE PRICE → PER NIGHT AVERAGE
     Example: (150 + 200) / 2 = 175
  ===================================================== */
  const baseTotal = prices.reduce(
    (sum, v) => sum + Number(v || 0),
    0
  );

  const basePerNight = baseTotal / nights;

  $row.find(".base_price").val(
    basePerNight.toFixed(2)
  );

  /* =====================================================
     5️⃣ EXTRA PRICES → ONLY IF PERSON COUNT > 0
  ===================================================== */
  const eaTotal = eaArr.reduce((s, v) => s + Number(v || 0), 0);
  const ecTotal = ecArr.reduce((s, v) => s + Number(v || 0), 0);
  const nbTotal = nbArr.reduce((s, v) => s + Number(v || 0), 0);

  $row.find(".extra_adult_price").val(
    extraAdults > 0 ? (eaTotal / nights).toFixed(2) : 0
  );

  $row.find(".child_price").val(
    children > 0 ? (ecTotal / nights).toFixed(2) : 0
  );

  $row.find(".nobed_price").val(
    noBed > 0 ? (nbTotal / nights).toFixed(2) : 0
  );

  /* =====================================================
     6️⃣ UI HIGHLIGHT
  ===================================================== */
  $popup.find(".hotel-room-row").removeClass("active-room");
  btn.closest(".hotel-room-row").addClass("active-room");

  /* =====================================================
     7️⃣ RECALCULATE TOTAL PRICE (FULL SUM)
  ===================================================== */
  calcHotelCost($row);
  recalcAllRowCosts();

  /* =====================================================
     8️⃣ SAVE SELECTED STATE (REOPEN SUPPORT)
  ===================================================== */
  $row.data("selectedHotel", {
    hotel: hotelId,
    room: roomId
  });

  console.log("✅ HOTEL SELECTED", {
    hotelId,
    roomId,
    basePerNight,
    nights
  });

  $popup.modal("hide");
});



/* =====================================================
   RESET PRICE ON CATEGORY CHANGE
===================================================== */
$(document).on('change', '.category', function () {
  const $row = $(this).closest('.hotel-row');
  $row.find('.price').val('0.00');
  recalcAllRowCosts();
});


/* =====================================================
   NIGHT CHANGE → RECALCULATE
===================================================== */
$(document).on('input change', '.stay', function () {
  const $row = $(this).closest('.hotel-row');
  calcHotelCost($row);
  recalcAllRowCosts();
});


/* =====================================================
   REMOVE HOTEL ROW
===================================================== */
$(document).on('click', '.removeHotel', function () {

  const $row = $(this).closest('.hotel-row');
  const $tbody = $row.closest('.hotelBody');

  if ($tbody.find('.hotel-row').length > 1) {
    $row.remove();
  } else {
    $row.find('select,input').val('');
    $row.find('.price').val('0.00');
    $row.removeData();
  }

  recalcAllRowCosts();
});


/* =====================================================
   ADD MORE HOTEL (SAME OPTION)
===================================================== */
$(document).on('click', '.addHotelRow', function () {

  const $option = $(this).closest('.hotel-option');
  const $tbody  = $option.find('.hotelBody');

  const $baseRow = $tbody.find('.hotel-row:first');
  const $newRow  = $baseRow.clone(false);

  $newRow.find('select,input').val('');
  $newRow.find('.price').val('0.00');
  $newRow.find('.hotel-id,.room-id').val('');
  $newRow.removeData();

  $tbody.append($newRow);
});


/* =====================================================
   ADD HOTEL OPTION (GLOBAL)
===================================================== */
let hotelOptionCount = $('.hotel-option').length || 1;

$('#addHotelOption').on('click', function () {

  hotelOptionCount++;

  const $baseOption = $('.hotel-option:first');
  const $newOption  = $baseOption.clone(false);

  $newOption
    .attr('data-option', hotelOptionCount)
    .find('h6')
    .text('Hotel Option ' + hotelOptionCount)
    .removeClass('text-primary')
    .addClass('text-success');

  // update input names → hotel[2][...]
  $newOption.find('[name]').each(function () {
    const name = $(this).attr('name');
    $(this).attr(
      'name',
      name.replace(/hotel\[\d+]/, 'hotel[' + hotelOptionCount + ']')
    );
  });

  // reset values
  $newOption.find('select,input').val('');
  $newOption.find('.price').val('0.00');
  $newOption.find('.hotel-id,.room-id').val('');
  $newOption.find('.hotel-row').removeData();

  $('#hotelOptionsWrapper').append($newOption);
});


/* =====================================================
   AUTO TRIGGER CATEGORY ON PAGE LOAD (EDIT MODE)
===================================================== */
$(window).on('load', function () {
  $('.hotel-row').each(function () {
    const $row = $(this);
    if ($row.find('.city').val()) {
      $row.find('.city').trigger('change');
    }
  });
});

  // Initialize travel-plan row handlers (activity popup, car popup, meal & guide & price changes)
  function initTravelRow($row) {
    $row.off(".travelRow");

    /* -------------------------------
       CITY CHANGE
    --------------------------------*/
    $row.on("change.travelRow", ".travel-city", function () {
    const r  = $(this).closest("tr");
    const id = $(this).val();

    r.find(".pickup-point").html('<option>Loading...</option>');
    r.find(".sightseeing").html('<option>Loading...</option>');
    r.find(".meal").html('<option>Loading...</option>');

    if (!id) {
        r.find(".pickup-point").html('<option value="">Select Pickup</option>');
        r.find(".sightseeing").html('<option value="">Select Sightseeing</option>');
        r.find(".meal").html('<option value="">Select Meal</option>');
        return;
    }

    // Load Pickup Points
$.post("../fetch/fetch_pickup_points.php", { city_id: id }, function (html) {
    const $p = r.find(".pickup-point");
    $p.html(html);
});


    // Load Sightseeing (city only initially)
    $.post("../fetch/fetch_sightseeing.php", { city_id: id }, function (html) {
        r.find(".sightseeing").html(html);
    });

    // Load Meals
    $.post("../fetch/fetch_meals.php", { city_id: id }, function (html) {
        r.find(".meal").html(html);
    });
});


    /* -------------------------------
       PICKUP POINT CHANGE (NEW)
    --------------------------------*/
    $row.on("change.travelRow", ".pickup-point", function () {
        const r = $(this).closest("tr");

        const city_id   = r.find(".travel-city").val();
        const pickup_id = $(this).val();

        r.find(".sightseeing").html('<option>Loading...</option>');

        if (!city_id) {
            r.find(".sightseeing").html('<option value="">Select Sightseeing</option>');
            return;
        }

        // Load Sightseeing based on City + Pickup Point
        $.post(
            "../fetch/fetch_sightseeing.php",
            {
                city_id: city_id,
                pickup_point_id: pickup_id
            },
            function (html) {
                r.find(".sightseeing").html(html);
            }
        ).fail(function () {
            r.find(".sightseeing").html('<option value="">Select Sightseeing</option>');
        });
    });

    function autoApplyDefaultCarForRow(row) {
  const defaultCar = $("#defaultCarId").val();
  if (!defaultCar) return;

  const payingPersons = getAdults() + getExtraAdults() + getChildren();
  if (payingPersons <= 0) return;

  const sightseeing = row.find(".sightseeing").val();
  if (!sightseeing) return;

  $.post("../fetch/fetch_car_popup.php", { sightseeing }, function (list) {
    const car = list.find(c => c.id == defaultCar);
    if (!car) return;

    const fullRate = Number(car.full_day || 0);
    const perPerson = fullRate / payingPersons;
    const finalAmount = fullRate; // total remains full amount

    const arr = [{ id: defaultCar, mode: "full-day", price: fullRate }];
    row.find(".extra-car-values").val(JSON.stringify(arr));
    row.find(".car-rent-price").val(finalAmount.toFixed(2));
    row.find(".extra-car-price").val(finalAmount.toFixed(2));

    recalcAllRowCosts();
  }, "json");
}

function autoSelectFirstActivityForRow(r) {
  const sight_id = r.find(".sightseeing").val();
  if (!sight_id) return;

  $.post("../fetch/fetch_activities.php", { sightseeing_id: sight_id }, function (list) {

    if (!Array.isArray(list) || !list.length) return;

    const a = list[0]; // ✅ FIRST ACTIVITY

    const adultsTotal = getTotalAdults();
    const children = getChildren();
    const noBed = getNoBed();

    const selectedData = [{
      id: a.id,
      adult: Number(a.adult || 0),
      child: Number(a.child || 0)
    }];

    const total =
      (a.adult * adultsTotal) +
      (a.child * (children + noBed));

    // 🔥 THIS IS THE KEY
    r.find(".activity-values").val(a.id);
    r.find(".activity-data").val(JSON.stringify(selectedData));
    r.find(".activity-price").val(total.toFixed(2));

    recalcAllRowCosts();

    console.log("✅ Auto activity applied:", selectedData, total);

  }, "json");
}

    // sightseeing change -> load car rent options and auto pick default
   $row.on("change.travelRow", ".sightseeing", function () {
  const r = $(this).closest("tr");
  const sight_id = $(this).val();
  let car_id = r.find(".day-transport").val();
  if (!car_id) car_id = $("select[name='car_id']").val();

  // reset activity if empty
  if (!sight_id) {
    r.find(".activity-values").val("");
    r.find(".activity-data").val("");
    r.find(".activity-price").val("0.00");
    r.find(".car-rent").html('<option value="">Select Rent</option>');
    r.find(".car-rent-price").val("0.00");
    recalcAllRowCosts();
    return;
  }

  // 🟢 AUTO SELECT FIRST ACTIVITY (THIS WAS MISSING)
  autoSelectFirstActivityForRow(r);

  // existing car rent logic (unchanged)
  $.post("../fetch/fetch_car_rent.php", { sightseeing_id: sight_id, car_id }, function (html) {
    r.find(".car-rent").html(html);
    autoApplyDefaultCarForRow(r);

    const fullOpt = r.find(".car-rent option[value='full-day']");
    if (fullOpt.length) {
      r.find(".car-rent").val("full-day").trigger("change");
    }
  });
});

    // car-rent change -> compute car rent price
    $row.on("change.travelRow", ".car-rent", function () {
      const r = $(this).closest("tr");
      const opt = $(this).find("option:selected");
      const full = toNumber(opt.data("full-day"));
      const half = toNumber(opt.data("half-day"));
      const type = $(this).val();
      const totalAdults = getTotalAdults();
      let price = 0;
      if (type === "full-day") price = full * totalAdults;
      if (type === "half-day") price = half * totalAdults;
      r.find(".car-rent-price").val(fix2(price));
      recalcAllRowCosts();
    });

    // open car popup -> handled elsewhere; "saveCarSelection" will set .extra-car-price and .car-rent-price
    // here we ensure that when extra-car-price input changes, totals recalc
    $row.on("input.travelRow", ".extra-car-price", function () {
      recalcAllRowCosts();
    });

    // meal change -> calculate meal price for the row
    $row.on("change.travelRow", ".meal", function () {
      const r = $(this).closest("tr");
      const opt = $(this).find("option:selected");
      const adultP = toNumber(opt.data("adult"));
      const childP = toNumber(opt.data("child"));
      const nobedP = toNumber(opt.data("nobed"));
      const adults = getTotalAdults();
      const childs = getChildren();
      const nob = getNoBed();
      const total = (adultP * adults) + (childP * childs) + (nobedP * nob);
      r.find(".meal-price").val(fix2(total));
      recalcAllRowCosts();
    });

    // guide required -> calculate guide price for row
    $row.on("change.travelRow", ".guide-required", function () {
      const r = $(this).closest("tr");
      const need = $(this).val();
      const guide = toNumber(r.find(".sightseeing option:selected").data("guide"));
      r.find(".guide-price").val(need === "Yes" ? fix2(guide) : "0.00");
      recalcAllRowCosts();
    });

    // activity popup saving handled globally: when saveActivitySelection clicked, update active row
    // duplication and removal of travel rows
   $row.on("click.travelRow", ".duplicateTravelRow", function (e) {
  e.preventDefault();

  const $orig  = $(this).closest("tr");
  const $clone = $orig.clone(true);

  // ---------- RESET VALUES ----------
  $clone.find("select").not(".day-date").val("");
  $clone.find("input").not(".day-date").val("");

  $clone.find(".activity-values").val("");
  $clone.find(".activity-data").val("");
  $clone.find(".activity-price").val("0.00");

  // 🔥 IMPORTANT: EMPTY means "no selection yet"
  $clone.find(".extra-car-values").val("");
  $clone.find(".extra-car-price").val("0.00");
  $clone.find(".car-rent-price").val("0.00");

  // 🔥 reset manual flag
  $clone.removeData("manualCarSelected");

  $orig.after($clone);
  initTravelRow($clone);

  // 🔥 FORCE default car logic
  setTimeout(() => {
    autoSaveDefaultCar($clone);
    recalcAllRowCosts();
  }, 150);
});

    $row.on("click.travelRow", ".removeTravelRow", function (e) {
      e.preventDefault();
      if ($("#travelPlan tbody tr").length > 1) {
        $(this).closest("tr").remove();
        recalcAllRowCosts();
      }
    });

    // small on-init trigger to populate dependent selects if values exist
    setTimeout(() => {
      try {
        if ($row.find(".travel-city").val()) $row.find(".travel-city").trigger("change");
      } catch (e) { /* ignore */ }
    }, 40);
  }

  
  // ===========================
// AUTO GENERATE TRAVEL PLAN
// ===========================

// When Travel Date OR Nights change
$(document).on("change input", "#travel_date, #nights", function () {

    const travel = $("#travel_date").val();
    const nights = parseInt($("#nights").val() || 0);

    if (!travel || nights < 0) return;

    // 1) Calculate Departure Date
    let d = new Date(travel);
    d.setDate(d.getDate() + nights);
    $("#departure_date").val(d.toISOString().slice(0, 10));

    // 2) Auto-calc Days = Nights + 1 (only if user has NOT manually edited)
    if (!$("#days").data("user-edited")) {
        $("#days").val(nights + 1);
    }

    // 3) Generate Travel Plan
    generateTravelPlan(nights + 1, travel);
});


// If user manually enters Days, mark as edited + regenerate plan
$(document).on("change input", "#days", function () {

    $("#days").data("user-edited", true);

    const days = parseInt($("#days").val() || 0);
    const start = $("#travel_date").val();

    if (!start || days <= 0) return;

    generateTravelPlan(days, start);
});
  // ---------- Travel plan generator ----------
  function generateTravelPlan(days, startDate) {
    const tbody = $("#travelPlan tbody");
    tbody.empty();
    const template = document.querySelector("#travelRowTemplate");
    if (!template) return;

    const sDate = new Date(startDate);
    for (let i = 0; i < days; i++) {
      const d = new Date(sDate);
      d.setDate(d.getDate() + i);
      const clone = $(template.content.cloneNode(true));
      clone.find(".day-title").text(`DAY-${i + 1}`);
      clone.find(".day-date").val(d.toISOString().split("T")[0]);
      tbody.append(clone);
    }

    // load cities for travel plan if country selected
    const country_id = $("#country_id").val();
    if (country_id) {
      $.post("../fetch/fetch_cities.php", { country_id }, function (html) {
        $("#travelPlan .travel-city").html(html);
      });
    }

    // initialize handlers on travel rows
    $("#travelPlan tbody tr").each(function () {
      initTravelRow($(this));
    });

    // recalc after small delay
    setTimeout(() => recalcAllRowCosts(), 200);
  }

  // ---------- Add Hotel button (robust) ----------
  $("#addHotel").off("click.addHotel").on("click.addHotel", function () {
    const $first = $("#hotelBody tr:first");
    const $row = $first.clone(true);

    // reset user-editable inputs
    $row.find("select").val("");
    $row.find("input").not('.day-date').val("");
    $row.find(".price").val("0.00");

    // ensure remove button present
    if ($row.find(".removeHotel").length === 0) {
      $row.find("td:last").html('<button class="btn btn-danger btn-sm removeHotel">X</button>');
    }

    // append and initialize
    $("#hotelBody").append($row);
    initHotelRow($row);

    const id = $("#country_id").val();
    if (id) {
      $.post("../fetch/fetch_cities.php", { country_id: id }, function (html) {
        $row.find(".city").html(html);
      }).fail(function () {
        $row.find(".city").html('<option value="">Select City</option>');
      });
    }

    // small delay then trigger change to start chain (if default values should be loaded)
    setTimeout(() => {
      $row.find(".city").trigger("change");
      recalcAllRowCosts();
    }, 150);
  });

  function getTotalPersons() {
    return (
        (Number($("#adults").val()) || 0) +
        (Number($("#extra_adults").val()) || 0) +
        (Number($("#children").val()) || 0) +
        (Number($("#no_bed").val()) || 0)
    );
}
  // ---------- Remove Hotel (delegated handled by initHotelRow) ----------
  // (kept intentionally empty; removal handled in per-row initializer)

  // ---------- Initialize existing hotel rows on document ready ----------
  $(document).ready(function () {
    $("#hotelBody tr").each(function () {
      initHotelRow($(this));
    });

    // initialize travel plan rows (if any present on load)
    $("#travelPlan tbody tr").each(function () {
      initTravelRow($(this));
    });

    // Trigger recalc when header values that affect calculations change
    $(document).on("input change", "#adults,#extra_adults,#children,#no_bed,#rooms,#travel_date,#nights,#days", function () {
      // If travel_date or nights changed we may need to update departure date and travel plan
      if ($(this).is("#travel_date,#nights")) {
        const travel = $("#travel_date").val();
        const nights = parseInt($("#nights").val() || 0);
        if (travel && isFinite(nights)) {
          const d = new Date(travel);
          d.setDate(d.getDate() + nights);
          $("#departure_date").val(d.toISOString().slice(0, 10));
          const daysFromNight = nights + 1;
          if (!$("#days").data("user-edited")) $("#days").val(daysFromNight);
        }
      }
      // Recompute fully
      recalcAllRowCosts();
    });

    // When days is changed by user to generate travel plan
    $(document).on("change input", "#days", function () {
      $(this).data("user-edited", true);
      const days = parseInt($(this).val() || 0);
      const start = $("#travel_date").val();
      if (start && days > 0) {
        generateTravelPlan(days, start);
      }
    });

    // country change -> refresh city lists for hotel rows and travel plan
    $(document).on("change", "#country_id", function () {
      const id = $(this).val();
      if (!id) return;
      // refresh hotel row city selects
      $.post("../fetch/fetch_cities.php", { country_id: id }, function (html) {
        $(".city").each(function () {
          // If this select is empty, fill; if user already selected keep it
          const current = $(this).val();
          $(this).html(html);
          if (current) $(this).val(current);
        });
        recalcAllRowCosts();
      });
      // refresh travel plan travel-city selects
      $.post("../fetch/fetch_cities.php", { country_id: id }, function (html) {
        $(".travel-city").html(html);
      });
    });

    $(document).on("input change",
    "#visa_fee,#tip_amount,#adults,#extra_adults,#children,#no_bed",
    function () {
        recalcAllRowCosts();
    }
);

    // Activity popup wiring (open & save)
    let activeActivityRow = null;

    $(document).on("click", ".openActivityPopup", function () {
      activeActivityRow = $(this).closest("tr");
      const sight_id = activeActivityRow.find(".sightseeing").val();
      if (!sight_id) {
        $("#activityList").html(`<div class="p-2 text-center text-muted">Select sightseeing first.</div>`);
        $("#activityPopup").modal("show");
        return;
      }
      $("#activityList").html('<div class="p-2 text-center text-muted">Loading...</div>');
      $("#activityPopup").modal("show");

      $.post("../fetch/fetch_activities.php", { sightseeing_id: sight_id }, function (data) {
        if (!Array.isArray(data) || data.length === 0) {
          $("#activityList").html('<div class="p-2 text-center text-muted">No activities found.</div>');
          return;
        }
        const selected = (activeActivityRow.find(".activity-values").val() || "").split(",");
        let html = "";
        for (const a of data) {
          const chk = selected.includes(String(a.id)) ? "checked" : "";
          html += `
            <div class="activity-popup-item">
              <label>
                <input type="checkbox" class="activity-check" value="${a.id}" data-adult="${a.adult}" data-child="${a.child}" ${chk}>
                <strong>${a.name}</strong> — Adult ₹${a.adult}, Child ₹${a.child}
              </label>
            </div>`;
        }
        $("#activityList").html(html);
      }, "json").fail(function () {
        $("#activityList").html('<div class="p-2 text-center text-muted">Error loading activities.</div>');
      });
    });

    $("#saveActivitySelection").on("click", function () {
      if (!activeActivityRow) return;
      const ids = [];
      const selectedData = [];
      let total = 0;
      const adultsTotal = getTotalAdults();
      const children = getChildren();
      const noBed = getNoBed();

      $("#activityList .activity-check:checked").each(function () {
        const id = $(this).val();
        const a = toNumber($(this).data("adult"));
        const c = toNumber($(this).data("child"));
        ids.push(id);
        selectedData.push({ id, adult: a, child: c });
        const adultCost = a * adultsTotal;
        const childCost = c * (children + noBed);
        total += adultCost + childCost;
      });

      activeActivityRow.find(".activity-values").val(ids.join(","));
      activeActivityRow.find(".activity-data").val(JSON.stringify(selectedData));
      activeActivityRow.find(".activity-price").val(fix2(total));

      $("#activityPopup").modal("hide");
      recalcAllRowCosts();
    });

    let currentCarRow = null;

/* ================= OPEN POPUP ================= */
$(document).on("click", ".openCarPopup", function () {
  currentCarRow = $(this).closest("tr");
  loadCarOptionsForPopup(currentCarRow);
  $("#carPopup").modal("show");
});

/* ================= LOAD CARS ================= */
function loadCarOptionsForPopup(row) {

  const isManual = row.data("manualCarSelected") === true;
  const sightseeing = row.find(".sightseeing").val();
  const travelDate  = row.find(".day-date").val();
  const raw = row.find(".extra-car-values").val();
  const headerCar = $("#defaultCarId").val();

  let autoSelected = false;

  let already = [];
  if (raw && raw !== "__NO_CAR__") {
    try { already = JSON.parse(raw); } catch { already = []; }
  }

  if (!sightseeing || !travelDate) {
    $("#carList").html(
      '<div class="p-2 text-center text-muted">Select sightseeing & date first</div>'
    );
    return;
  }

  $.post("../fetch/fetch_car_popup.php", {
    sightseeing,
    travel_date: travelDate
  }, function (list) {

    $("#carList").empty();

    if (!Array.isArray(list) || !list.length) {
      $("#carList").html(
        '<div class="p-2 text-center text-muted">No car rates found</div>'
      );
      return;
    }

    list.forEach(car => {

  let checked = "";
  let qty = 0;
  let mode = "full-day";

  const matches = already.filter(x => x.id == car.id);

if (matches.length) {
  checked = "checked";
  qty = matches.length;          // ✅ COUNT QTY
  mode = matches[0].mode || "full-day";
  autoSelected = true;
  } else if (!isManual && headerCar == car.id) {
  checked = "checked";
  qty = 1;
  autoSelected = true;
}

  if (!autoSelected && !already.length && !headerCar && !isManual) {
  checked = "checked";
  qty = 1;
  mode = "full-day";
  autoSelected = true;
}


      $("#carList").append(`
<div class="border rounded p-2 mb-2 car-item" data-id="${car.id}">
  <div class="d-flex justify-content-between align-items-center">
    <label>
      <input type="checkbox" class="pickCar" ${checked}>
      ${car.car_name} (${car.seater})
    </label>

    <div class="d-flex align-items-center gap-1">
      <button class="btn btn-outline-secondary btn-sm removeSameCar">−</button>
      <button class="btn btn-outline-primary btn-sm addSameCar">+</button>
      <span class="badge bg-success carQty" style="display:none">0</span>
    </div>
  </div>

  <div class="ms-4 mt-2">
    <label class="me-3">
      <input type="radio" name="mode_${car.id}" value="full-day" ${mode === "full-day" ? "checked" : ""}>
      Full-Day ₹
      <input type="number" class="rateInput rate-full" value="${car.full_day}" style="width:90px;">
    </label>

    <label>
      <input type="radio" name="mode_${car.id}" value="half-day" ${mode === "half-day" ? "checked" : ""}>
      Half-Day ₹
      <input type="number" class="rateInput rate-half" value="${car.half_day}" style="width:90px;">
    </label>
  </div>
</div>`);

      const box = $("#carList .car-item").last();
      const addBtn = box.find(".addSameCar");

      addBtn.data("count", qty);

      if (qty > 0) {
        box.find(".carQty").text(qty).show();
      }
    });

  }, "json");
}

/* ================= + BUTTON ================= */
$(document).on("click", ".addSameCar", function () {

  const box = $(this).closest(".car-item");
  const btn = box.find(".addSameCar");

  let qty = Number(btn.data("count")) || 0;
  qty++;

  btn.data("count", qty);
  box.find(".carQty").text(qty).show();
  box.find(".pickCar").prop("checked", true);
});

/* ================= - BUTTON ================= */
$(document).on("click", ".removeSameCar", function () {

  const box = $(this).closest(".car-item");
  const btn = box.find(".addSameCar");

  let qty = Number(btn.data("count")) || 0;
  qty--;

  if (qty <= 0) {
    btn.data("count", 0);
    box.find(".carQty").hide();
    box.find(".pickCar").prop("checked", false);
  } else {
    btn.data("count", qty);
    box.find(".carQty").text(qty);
  }
});

/* ================= AUTO CHECK ================= */
$(document).on("input", ".rateInput", function () {
  $(this).closest(".car-item").find(".pickCar").prop("checked", true);
});


function autoSaveDefaultCar(row) {

  // ❌ STOP if user manually selected
  if (row.data("manualCarSelected")) return;

  const sightseeing = row.find(".sightseeing").val();
  const travelDate  = row.find(".day-date").val();
  const defaultCarId = $("#defaultCarId").val();

  if (!sightseeing || !travelDate || !defaultCarId) return;

  const raw = row.find(".extra-car-values").val();
 if (raw && raw !== "" && raw !== "__NO_CAR__") return;

  $.post("../fetch/fetch_car_popup.php", {
    sightseeing,
    travel_date: travelDate
  }, function (list) {

    if (!Array.isArray(list) || !list.length) return;

    const car = list.find(c => String(c.id) === String(defaultCarId));
    if (!car) return;

    const arr = [{
      id: car.id,
      mode: "full-day",
      rate: car.full_day
    }];

    row.find(".extra-car-values").val(JSON.stringify(arr));
    row.find(".extra-car-price").val(Number(car.full_day).toFixed(2));
    row.find(".car-rent-price").val(Number(car.full_day).toFixed(2));

    recalcAllRowCosts?.();
  }, "json");
}

$(document).on("change", ".sightseeing, .day-date", function () {

  const row = $(this).closest("tr");

  // ⏳ thoda delay taaki date + sightseeing dono set ho jaye
  setTimeout(() => {
    autoSaveDefaultCar(row);
  }, 200);
});

$('#defaultCarId').on('change', function () {

  const carId = $(this).val();
  if (!carId) return;

  // 🔥 APPLY ONLY AFTER USER SELECTS
  applyHeaderCarToAllTravelRows();
});

function applyHeaderCarToAllTravelRows() {

  const carId = $('#defaultCarId').val();
  if (!carId) return;

  $('#travelPlan tbody tr').each(function () {

    const row = $(this);

    // already has car → skip
    const existing = row.find('.extra-car-values').val();
    if (existing && existing !== '' && existing !== '__NO_CAR__') return;

    const sightseeing = row.find('.sightseeing').val();
    const travelDate  = row.find('.day-date').val();

    if (!sightseeing || !travelDate) return;

    $.post(
      "../fetch/fetch_car_popup.php",
      { sightseeing, travel_date: travelDate },
      function (list) {

        if (!Array.isArray(list) || !list.length) return;

        const car = list.find(c => String(c.id) === String(carId));
        if (!car) return;

        const price = Number(car.full_day || 0);

        const payload = [{
          id: car.id,
          mode: "full-day",
          price: price
        }];

        row.find('.extra-car-values').val(JSON.stringify(payload));
        row.find('.extra-car-price').val(price.toFixed(2));
        row.find('.car-rent-price').val(price.toFixed(2));

        // 🔥 recalc
        updateTransportTotals?.();
        recalcAllRowCosts?.();

      },
      "json"
    );
  });
}


/* ================= SAVE ================= */
$("#saveCarSelection").on("click", function () {

  if (!currentCarRow) return;

  // 🔥 MARK MANUAL SELECTION
  currentCarRow.data("manualCarSelected", true);

  const selected = [];
  let total = 0;

  $("#carList .car-item").each(function () {
    const box = $(this);
    if (!box.find(".pickCar").is(":checked")) return;

    const id = box.data("id");
    const qty = Math.max(1, Number(box.find(".addSameCar").data("count")) || 1);
    const mode = box.find("input[type=radio]:checked").val();
    const rate = mode === "half-day"
      ? Number(box.find(".rate-half").val())
      : Number(box.find(".rate-full").val());

    for (let i = 0; i < qty; i++) {
      selected.push({ id, mode, rate });
      total += rate;
    }
  });

  currentCarRow.find(".extra-car-values")
    .val(selected.length ? JSON.stringify(selected) : "__NO_CAR__");

  currentCarRow.find(".extra-car-price").val(total.toFixed(2));
  currentCarRow.find(".car-rent-price").val(total.toFixed(2));

  $("#carPopup").modal("hide");
  recalcAllRowCosts?.();
});

    // Extra charge handlers (delegated)
    $(document).on("click", "#addExtraCharge", function () {
      $("#extraChargeBody").append(`
        <tr>
          <td><input type="text" name="extra_charge_name[]" class="form-control form-control-sm"></td>
          <td><input type="number" name="extra_charge_amount[]" class="form-control form-control-sm extra-charge" step="0.01" min="0"></td>
          <td><button type="button" class="btn btn-danger btn-sm removeExtra">X</button></td>
        </tr>
      `);
      setTimeout(() => recalcAllRowCosts(), 60);
    });

    $(document).on("click", ".removeExtra", function () {
      $(this).closest("tr").remove();
      recalcAllRowCosts();
    });

    // when extra-charge inputs change, update extra total and grand recalculation hooks in the page's other script
    $(document).on("input", ".extra-charge", function () {
      // other inline script in page calculates extra_total & updates grand_total_ui — to keep consistent we call recalcAllRowCosts
      recalcAllRowCosts();
    });
  }); // end document ready

  // ---------- MutationObserver safety-net for hotelBody (ensure new rows wired) ----------
  try {
    const hotelBody = document.getElementById("hotelBody");
    if (hotelBody) {
      const mo = new MutationObserver(mutations => {
        mutations.forEach(m => {
          if (m.addedNodes && m.addedNodes.length) {
            m.addedNodes.forEach(node => {
              if (node.nodeType === 1 && $(node).is("tr")) {
                initHotelRow($(node));
              }
            });
          }
        });
      });
      mo.observe(hotelBody, { childList: true });
    }
  } catch (e) {
    // ignore if MutationObserver not available
  }

  // =====================================================
// BUILD FINAL TOTALS OBJECT (SOURCE OF TRUTH = UI)
// =====================================================
function buildFinalTotalsObject() {
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
}


$("#quotation_json").val(JSON.stringify({
  totals: buildFinalTotalsObject(),
  // keep your existing payload parts (hotels, travel_plan, etc.)
}));

// ---------- Public helper: collectQuotationPayload (keeps same shape as original) ----------
  // If you use the existing collectQuotationPayload function elsewhere, replace it or keep as-is.
  window.collectQuotationPayload = function () {
    const payload = {
      country_id: $("#country_id").val(),
      travel_date: $("#travel_date").val(),
      nights: Number($("#nights").val() || 0),
      departure_date: $("#departure_date").val(),
      adults: Number($("#adults").val() || 0),
      extra_adults: Number($("#extra_adults").val() || 0),
      children: Number($("#children").val() || 0),
      no_bed: Number($("#no_bed").val() || 0),
      rooms: Number($("#rooms").val() || 0),
      car_id: $("#defaultCarId").val(),
      hotels: [],
      travel_plan: [],
      totals: {
        hotel_total: Number($("#hotel_total").val() || 0),
        activity_total: Number($("#activity_total").val() || 0),
        meal_total: Number($("#meal_total").val() || 0),
        transport_total: Number($("#transport_total").val() || 0),
        guide_total: Number($("#guide_total").val() || 0),
        grand_total: Number($("#grand_total").val() || 0),
      }
    };

    $("#hotelBody tr").each(function () {
  const r = $(this);

  payload.hotels.push({
    // IDs
    city_id: r.find(".city").val(),
    category: r.find(".category").val(),
    hotel_id: r.find(".hotel").val(),
    room_category_id: r.find(".room").val(),
    stay_nights: Number(r.find(".stay").val() || 0),

    // counts
    rooms: Number($("#rooms").val() || 0),

    // breakdown (for future / audit)
    base_price: Number(r.data("room_price") || 0),
    extra_adult_price: Number(r.data("extra_adult") || 0),
    child_price: Number(r.data("extra_child") || 0),
    nobed_price: Number(r.data("extra_nobed") || 0),

    // ✅ FINAL hotel cost (IMPORTANT)
    price: Number(r.find(".price").val() || 0)
  });
});


    $("#travelPlan tbody tr").each(function () {
      const r = $(this);
      let carsJSON = r.find(".extra-car-values").val() || "[]";
      let cars = [];
      try { cars = JSON.parse(carsJSON); } catch { cars = []; }
      payload.travel_plan.push({
        day: r.find(".day-title").text(),
        date: r.find(".day-date").val(),
        city: r.find(".travel-city").val(),
        pickup_time: r.find(".pickup-time").val(),
        pickup_point: r.find(".pickup-point").val(),
        sightseeing: r.find(".sightseeing").val(),
        activities: r.find(".activity-values").val(),
        activity_price: Number(r.find(".activity-price").val() || 0),
        extra_cars: cars,
        extra_car_price: Number(r.find(".extra-car-price").val() || 0),
        meal: r.find(".meal").val(),
        meal_price: Number(r.find(".meal-price").val() || 0),
        guide_required: r.find(".guide-required").val(),
        guide_price: Number(r.find(".guide-price").val() || 0)
      });
    });

    return payload;
  };

  // expose recalcAllRowCosts for manual triggering if needed
  window.recalcAllRowCosts = recalcAllRowCosts;
  window.calcHotelCost = calcHotelCost;
  window.updateFinalCostPanel = updateFinalCostPanel;

})(jQuery);

$(document).ready(function () {

  // Delay is IMPORTANT because create-page JS auto-builds rows
  setTimeout(function () {

    if (!window.initQuotation) return;
    const q = window.initQuotation;

    /* ================= HEADER ================= */
    $('#customer_id').val(q.customer_id);
    $('#country_id').val(q.country_id);
    $('#travel_date').val(q.travel_date);
    $('#departure_date').val(q.departure_date);
    $('#defaultCarId').val(q.car_id);

    $('#adults').val(q.adults);
    $('#extra_adults').val(q.extra_adults);
    $('#children').val(q.children);
    $('#infants').val(q.infants);
    $('#no_bed').val(q.no_bed_child);
    $('#rooms').val(q.rooms);
    $('#nights').val(q.nights);
    $('#days').val(q.days);

    /* ================= HOTELS ================= */
    if (Array.isArray(q.hotels) && q.hotels.length) {

      const hotelTemplate = $('.hotel-row:first').clone(true);
      $('#hotelBody').empty();

      q.hotels.forEach(h => {

        const row = hotelTemplate.clone(true);

        // ---- CITY ----
        $.post('../fetch/fetch_cities.php', { country_id: q.country_id }, function (html) {
          row.find('.city').html(html).val(h.city_id);

          // ---- CATEGORY (depends on city) ----
          $.post('../fetch/fetch_categories.php', { city_id: h.city_id }, function (html2) {
            row.find('.category').html(html2).val(h.category_id);
          });
        });

        row.find('.hotel-id').val(h.hotel_id);
        row.find('.room-id').val(h.room_category_id);
        row.find('.stay').val(h.stay_nights);
        row.find('.price').val(h.price);

        // REQUIRED for hotel calculation
        row.data('room_price', Number(h.price) || 0);
        row.data('extra_adult', Number(h.extra_adult) || 0);
        row.data('extra_child', Number(h.extra_child) || 0);
        row.data('extra_nobed', Number(h.extra_nobed) || 0);

        $('#hotelBody').append(row);
        calcHotelCost(row);
      });
    }

    /* ================= TRAVEL ================= */
    if (Array.isArray(q.travels) && q.travels.length) {

      $('#travelBody').empty();

      q.travels.forEach((t, i) => {

        const $row = $($('#travelRowTemplate').html());

        $row.find('.day-title').text('Day ' + (i + 1));
        $row.find('.day-date').val(t.day_date || '');
        $row.find('.pickup-time').val(t.pickup_time || '');

        // ---- CITY ----
        $.post('../fetch/fetch_cities.php', { country_id: q.country_id }, function (html) {
          $row.find('.travel-city').html(html).val(t.city_id);

          // ---- PICKUP POINT ----
          $.post('../fetch/fetch_pickup_points.php', { city_id: t.city_id }, function (html2) {
            $row.find('.pickup-point').html(html2).val(t.pickup_point_id);
          });

          // ---- SIGHTSEEING ----
          $.post('../fetch/fetch_sightseeing.php', { city_id: t.city_id }, function (html3) {
            $row.find('.sightseeing').html(html3).val(t.sightseeing_id);
          });

          // ---- MEAL ----
          $.post('../fetch/fetch_meals.php', { city_id: t.city_id }, function (html4) {
            $row.find('.meal').html(html4).val(t.meal_id);
          });
        });

        /* ===== ACTIVITY ===== */
        const actIds = (t.activity_ids || []).map(String);
        $row.find('.activity-values').val(actIds.join(','));
        $row.find('.activity-data').val(JSON.stringify(
          actIds.map(id => ({
            id: id,
            adult: t.activity_adult_price || 0,
            child: t.activity_child_price || 0
          }))
        ));
        $row.find('.activity-price').val(t.activity_price || 0);

        /* ===== CAR ===== */
        $row.find('.car-id').val(t.car_id || '');
        $row.find('.car-rent-type').val(t.car_rent_type || '');
        $row.find('.car-rent-price').val(t.car_rent_price || 0);

        $('#travelBody').append($row);
      });
    }

    // Final recalculation
    if (typeof recalcAllRowCosts === 'function') {
      recalcAllRowCosts();
    }

    console.log('✅ Edit quotation data loaded with proper names');

  }, 400);

});

$(document).on('change', '.travel-city', function () {
  const row = $(this).closest('tr');
  const cityId = $(this).val();

  if (!cityId) return;

  // 1) Reload Pickup
  $.post('../fetch/fetch_pickup_points.php', { city_id: cityId }, function (p) {
    const $pickup = row.find('.pickup-point');
    $pickup.html(p);
  });

  // 2) Reload Transfer
  $.post('../fetch/fetch_sightseeing.php', { city_id: cityId }, function (s) {
    const $transfer = row.find('.sightseeing');
    $transfer.html(s).val('');
  });

  // 3) Reset activities
  row.find('.activity-values').val('');
  row.find('.activity-data').val('');
  row.find('.openActivityPopup').removeClass('btn-success');

});

$(document).on('change', '.pickup-point', function () {
  const row = $(this).closest('tr');
  const cityId = row.find('.travel-city').val();
  const pickupId = $(this).val();

  if (!cityId || !pickupId) return;

  // Reload Transfer based on pickup
  $.post('../fetch/fetch_sightseeing.php', {
    city_id: cityId,
    pickup_point_id: pickupId
  }, function (s) {
    const $transfer = row.find('.sightseeing');
    $transfer.html(s).val('');
  });

});
