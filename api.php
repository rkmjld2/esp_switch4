<?php
/*
 * ESP-SWITCH4 - api.php
 *
 * Stage 1 API
 *
 * ESP8266 sends:
 *   controller_id
 *   device_token
 *
 * API:
 *   1. Verifies controller ID + device token
 *   2. Updates last_seen in IST
 *   3. Reads D1-D8 for that controller
 *   4. Returns D1-D8 values to ESP8266
 *
 * Compatible with the Stage-1 index.php:
 *   User selects one controller at a time.
 */

require_once "db.php";

date_default_timezone_set("Asia/Kolkata");

header("Content-Type: application/json; charset=UTF-8");


/* =========================================================
   1. GET REQUEST PARAMETERS
   ========================================================= */

$action = trim($_GET["action"] ?? "");

$controller_id = trim(
    $_GET["controller_id"] ?? ""
);

$device_token = trim(
    $_GET["device_token"] ?? ""
);


/* =========================================================
   2. BASIC VALIDATION
   ========================================================= */

if ($controller_id === "" || $device_token === "") {

    echo json_encode([
        "success" => false,
        "error" => "Missing controller_id or device_token"
    ]);

    exit;
}


/* =========================================================
   3. CHECK CONTROLLER ID + TOKEN
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        controller_id,
        customer_id,
        customer_name,
        device_token,
        active
    FROM controllers
    WHERE controller_id = ?
      AND device_token = ?
    LIMIT 1
");

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "error" => "Database prepare error"
    ]);

    exit;
}

$stmt->bind_param(
    "ss",
    $controller_id,
    $device_token
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "error" => "Invalid controller_id or device_token"
    ]);

    exit;
}

$controller = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   4. CHECK ACTIVE STATUS
   ========================================================= */

if ((int)$controller["active"] !== 1) {

    echo json_encode([
        "success" => false,
        "error" => "Controller is inactive",
        "controller_id" => $controller_id
    ]);

    exit;
}


/* =========================================================
   5. SAVE LAST_SEEN IN IST
   ========================================================= */

/*
 * We deliberately calculate the Indian time in PHP.
 *
 * Example:
 *
 * 2026-08-14 12:30:15
 *
 * This avoids the TiDB SQL syntax problem previously caused
 * by expressions such as:
 *
 * INTERVAL 30 MINUTE
 */

$india_time = date("Y-m-d H:i:s");


$stmt = $conn->prepare("
    UPDATE controllers
    SET
        last_seen = ?,
        active = 1
    WHERE controller_id = ?
      AND device_token = ?
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "error" => "Could not prepare last_seen update"
    ]);

    exit;
}


$stmt->bind_param(
    "sss",
    $india_time,
    $controller_id,
    $device_token
);


$stmt->execute();

$stmt->close();


/* =========================================================
   6. ACTION = GET
   ========================================================= */

if ($action === "get") {


    /*
     * Get D1-D8 ONLY for the controller that supplied
     * the correct controller_id + device_token.
     */

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
            "error" => "Could not prepare pin query"
        ]);

        exit;
    }


    $stmt->bind_param(
        "s",
        $controller_id
    );


    $stmt->execute();

    $result = $stmt->get_result();


    if ($result->num_rows === 0) {

        $stmt->close();

        echo json_encode([
            "success" => false,
            "error" =>
                "No esp_control record found for controller",
            "controller_id" => $controller_id
        ]);

        exit;
    }


    $pins = $result->fetch_assoc();

    $stmt->close();


    /* Convert values to integer 0/1. */

    $D1 = (int)$pins["D1"];
    $D2 = (int)$pins["D2"];
    $D3 = (int)$pins["D3"];
    $D4 = (int)$pins["D4"];
    $D5 = (int)$pins["D5"];
    $D6 = (int)$pins["D6"];
    $D7 = (int)$pins["D7"];
    $D8 = (int)$pins["D8"];


    /* =====================================================
       7. SEND RESPONSE TO ESP8266
       ===================================================== */

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


/* =========================================================
   8. UNKNOWN ACTION
   ========================================================= */

echo json_encode([

    "success" => false,

    "error" => "Invalid action"

]);

exit;

?>
