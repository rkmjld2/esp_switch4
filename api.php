<?php
/*
 * ============================================================
 * ESP-SWITCH4 - api.php
 * ============================================================
 *
 * Stage 1
 *
 * Supports:
 *   ESP0001
 *   ESP0002
 *   ESP0003
 *   etc.
 *
 * Controllers table:
 *
 *   id
 *   controller_id
 *   device_token
 *   customer_name
 *   active
 *   last_seen
 *
 * esp_control table:
 *
 *   controller_id
 *   D1 D2 D3 D4 D5 D6 D7 D8
 *
 * ============================================================
 */

require_once "db.php";


/* ============================================================
   TIME ZONE
   ============================================================ */

date_default_timezone_set("Asia/Kolkata");


/* ============================================================
   JSON RESPONSE
   ============================================================ */

header("Content-Type: application/json; charset=UTF-8");


/* ============================================================
   GET PARAMETERS
   ============================================================ */

$action = trim(
    $_GET["action"] ?? ""
);

$controller_id = trim(
    $_GET["controller_id"] ?? ""
);

$device_token = trim(
    $_GET["device_token"] ?? ""
);


/* ============================================================
   CHECK REQUIRED PARAMETERS
   ============================================================ */

if (
    $controller_id === "" ||
    $device_token === ""
) {

    echo json_encode([
        "success" => false,
        "error" => "Missing controller_id or device_token"
    ]);

    exit;
}


/* ============================================================
   FIND EXACT CONTROLLER
   ============================================================
   
   IMPORTANT:
   We search using BOTH controller_id and device_token.

   Therefore:

   ESP0001 + ESP0001 token
          -> ESP0001 record

   ESP0002 + ESP0002 token
          -> ESP0002 record

   ESP0003 + ESP0003 token
          -> ESP0003 record

   ============================================================ */

$stmt = $conn->prepare("
    SELECT
        id,
        controller_id,
        device_token,
        customer_name,
        active,
        last_seen
    FROM controllers
    WHERE controller_id = ?
      AND device_token = ?
    LIMIT 1
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "error" => "Controller query prepare failed"
    ]);

    exit;
}


$stmt->bind_param(
    "ss",
    $controller_id,
    $device_token
);


if (!$stmt->execute()) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "error" => "Controller query failed"
    ]);

    exit;
}


$result =
    $stmt->get_result();


/* ============================================================
   CONTROLLER NOT FOUND
   ============================================================ */

if (
    $result->num_rows === 0
) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "error" =>
            "Invalid controller_id or device_token",
        "controller_id" =>
            $controller_id
    ]);

    exit;
}


$controller =
    $result->fetch_assoc();


$stmt->close();


/* ============================================================
   CHECK ACTIVE
   ============================================================
   
   active = 1 means controller is permitted to operate.

   ============================================================ */

if (
    (int)$controller["active"] !== 1
) {

    echo json_encode([
        "success" => false,
        "error" => "Controller is inactive",
        "controller_id" =>
            $controller_id
    ]);

    exit;
}


/* ============================================================
   UPDATE LAST_SEEN
   ============================================================
   
   IMPORTANT:
   The UPDATE contains BOTH controller_id AND device_token.

   Thus ESP0002 can NEVER update ESP0001's record.

   ============================================================ */

$india_time =
    date("Y-m-d H:i:s");


$stmt = $conn->prepare("
    UPDATE controllers
    SET last_seen = ?
    WHERE controller_id = ?
      AND device_token = ?
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "error" =>
            "last_seen update prepare failed"
    ]);

    exit;
}


$stmt->bind_param(
    "sss",
    $india_time,
    $controller_id,
    $device_token
);


if (!$stmt->execute()) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "error" =>
            "last_seen update failed"
    ]);

    exit;
}


$stmt->close();


/* ============================================================
   ACTION = GET
   ============================================================ */

if (
    strtolower($action) === "get"
) {


    /* ========================================================
       GET D1-D8 FOR EXACT CONTROLLER
       ======================================================== */

    $stmt = $conn->prepare("
        SELECT
            D1,
            D2,
            D3,
            D4,
            D5,
            D6,
            D7,
            D8
        FROM esp_control
        WHERE controller_id = ?
        LIMIT 1
    ");


    if (!$stmt) {

        echo json_encode([
            "success" => false,
            "error" =>
                "Pin query prepare failed",
            "controller_id" =>
                $controller_id
        ]);

        exit;
    }


    $stmt->bind_param(
        "s",
        $controller_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        echo json_encode([
            "success" => false,
            "error" =>
                "Pin query failed",
            "controller_id" =>
                $controller_id
        ]);

        exit;
    }


    $result =
        $stmt->get_result();


    /* ========================================================
       NO ESP_CONTROL RECORD
       ======================================================== */

    if (
        $result->num_rows === 0
    ) {

        $stmt->close();

        echo json_encode([
            "success" => false,
            "error" =>
                "No esp_control record found",
            "controller_id" =>
                $controller_id
        ]);

        exit;
    }


    $pins =
        $result->fetch_assoc();


    $stmt->close();


    /* ========================================================
       CONVERT D1-D8 TO 0 OR 1
       ======================================================== */

    $D1 = (int)$pins["D1"];
    $D2 = (int)$pins["D2"];
    $D3 = (int)$pins["D3"];
    $D4 = (int)$pins["D4"];
    $D5 = (int)$pins["D5"];
    $D6 = (int)$pins["D6"];
    $D7 = (int)$pins["D7"];
    $D8 = (int)$pins["D8"];


    /* ========================================================
       SEND RESPONSE TO ESP8266
       ======================================================== */

    echo json_encode([

        "success" => true,

        "controller_id" =>
            $controller_id,

        "D1" => $D1,
        "D2" => $D2,
        "D3" => $D3,
        "D4" => $D4,
        "D5" => $D5,
        "D6" => $D6,
        "D7" => $D7,
        "D8" => $D8

    ]);

    exit;
}


/* ============================================================
   INVALID ACTION
   ============================================================ */

echo json_encode([

    "success" => false,

    "error" =>
        "Invalid action"

]);

exit;

?>
