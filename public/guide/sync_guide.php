<?php
require_once __DIR__.'/../../config/db.php';

/* =========================
   STEP 1: INSERT MISSING
========================= */

$conn->query("
INSERT INTO confirmation_guide (
    confirmation_id,
    confirmation_no,
    agent_name,
    user_name,
    city_name,
    guide_date,
    car,
    guide,
    action_status,
    car_status
)

SELECT 
    ct.confirmation_id,

    CONCAT('EV-', LPAD(ct.confirmation_id,3,'0')),

    IFNULL(a.agent_name, 'System'),
    IFNULL(a.created_by, 'System'),

    ct.city_name,
    ct.travel_date,

    LOWER(IFNULL(ct.car,'no')),
    LOWER(IFNULL(ct.guide,'no')),

    'no',
    'no'

FROM confirmations_travels ct

LEFT JOIN agent_accounts a
ON a.confirmation_no = CONCAT('EV-', LPAD(ct.confirmation_id,3,'0'))

LEFT JOIN confirmation_guide cg
ON cg.confirmation_id = ct.confirmation_id
AND cg.city_name = ct.city_name
AND cg.guide_date = ct.travel_date

WHERE cg.id IS NULL
");

/* =========================
   STEP 2: UPDATE SAFE DATA
========================= */

$conn->query("
UPDATE confirmation_guide cg

JOIN confirmations_travels ct
ON cg.confirmation_id = ct.confirmation_id
AND cg.city_name = ct.city_name
AND cg.guide_date = ct.travel_date

LEFT JOIN agent_accounts a
ON a.confirmation_no = cg.confirmation_no

SET 
    cg.car   = LOWER(IFNULL(ct.car,'no')),
    cg.guide = LOWER(IFNULL(ct.guide,'no')),
    cg.agent_name = IFNULL(a.agent_name, cg.agent_name),
    cg.user_name  = IFNULL(a.created_by, cg.user_name)

WHERE 
    (cg.car_status IS NULL OR cg.car_status != 'yes')
AND (cg.action_status IS NULL OR cg.action_status != 'yes')
");

echo "done";