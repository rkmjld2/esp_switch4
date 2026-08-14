<?php
/*
 * ============================================================
 * ESP-SWITCH4 - api.php
 * ============================================================
 *
 * Used by ESP8266 and index.php
 *
 * Database:
 *
 * controllers
 *   id
 *   controller_id
 *   device_token
 *   customer_name
 *   active
 *   last_seen
 *
 * esp_control
 *   controller_id
 *   D1
 *   D2
 *   D3
 *   D4
 *   D5
 *   D6
 *   D7
 *   D8
 *
 * ============================================================
 */

require_once "db.php";


/* ============================================================
   INDIA STANDARD TIME
   ============================================================ */

date_default_timezone_set("Asia/Kolkata");


/* ============================================================
   RETURN JSON
   ============================================================ */

header("Content-Type: application/json; charset=UTF-8");


/* ============================================================
   GET PARAMETERS
   ============================================================ */

$action = trim($_GET["action"] ?? "");

$controller_id = trim(
    $_GET["controller_id"] ?? ""
);

$device_token = trim(
    $_GET["device_token"] ?? ""
);


/* ============================================================
   BASIC VALIDATION
   ============================================================ */

if ($controller_id === "") {

    echo json_encode([
        "status" => "error",
        "message" => "controller_id missing"
    ]);

    exit;
}


if ($device_token === "") {

    echo json_encode([
        "status" => "error",
        "message" => "device_token missing"
    ]);

    exit;
}


/* ============================================================
   CHECK CONTROLLER + TOKEN
   ============================================================ */

$stmt = $conn->prepare("
    SELECT
        id,
        controller_id,
        device_token,
        customer_name,
        active
    FROM controllers
    WHERE controller_id = ?
      AND device_token = ?
    LIMIT 1
");

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Controller query prepare failed"
    ]);

    exit;
}


$stmt->bind_param(
    "ss",
    $controller_id,
    $device_token
);


if (!$stmt->execute()) {

    echo json_encode([
        "status" => "error",
        "message" => "Controller query failed"
    ]);

    $stmt->close();

    exit;
}


$result = $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid controller_id or device_token"
    ]);

    $stmt->close();

    exit;
}


$controller = $result->fetch_assoc();

$stmt->close();


/* ============================================================
   CHECK ACTIVE
   ============================================================ */

if ((int)$controller["active"] !== 1) {

    echo json_encode([
        "status" => "error",
        "message" => "Controller is inactive"
    ]);

    exit;
}


/* ============================================================
   UPDATE LAST_SEEN
   ============================================================
 *
 * IMPORTANT:
 *
 * We do NOT use:
 *
 * NOW()
 *
 * or
 *
 * DATE_ADD(...)
 *
 * or
 *
 * INTERVAL ...
 *
 * because TiDB/MySQL server timezone can differ.
 *
 * PHP explicitly creates the current India time.
 *
 * Example:
 * 2026-08-14 10:15:25
 *
 * ============================================================
 */

$indiaTime = new DateTime(
    "now",
    new DateTimeZone("Asia/Kolkata")
);

$last_seen = $indiaTime->format(
    "Y-m-d H:i:s"
);


/* ============================================================
   UPDATE ONLY THE REQUESTING CONTROLLER
   ============================================================ */

$stmt = $conn->prepare("
    UPDATE controllers
    SET last_seen = ?
    WHERE controller_id = ?
      AND device_token = ?
");


if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "last_seen prepare failed"
    ]);

    exit;
}


$stmt->bind_param(
    "sss",
    $last_seen,
    $controller_id,
    $device_token
);


if (!$stmt->execute()) {

    echo json_encode([
        "status" => "error",
        "message" => "Could not update last_seen"
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/* ============================================================
   ACTION = GET
   ============================================================
 *
 * ESP8266 sends:
 *
 * api.php?action=get
 * &controller_id=ESP0001
 * &device_token=ESP0001-TOKEN-2026-A7K9X2
 *
 * ============================================================
 */

if ($action === "get") {


    /* ========================================================
       GET D1-D8 FOR THIS CONTROLLER
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
            "status" => "error",
            "message" => "esp_control prepare failed"
        ]);

        exit;
    }


    $stmt->bind_param(
        "s",
        $controller_id
    );


    if (!$stmt->execute()) {

        echo json_encode([
            "status" => "error",
            "message" => "esp_control query failed"
        ]);

        $stmt->close();

        exit;
    }


    $result = $stmt->get_result();


    if ($result->num_rows === 0) {

        echo json_encode([
            "status" => "error",
            "message" => "No esp_control record found"
        ]);

        $stmt->close();

        exit;
    }


    $row = $result->fetch_assoc();

    $stmt->close();


    /* ========================================================
       RETURN D1-D8
       ======================================================== */

    echo json_encode([

        "status" => "ok",

        "controller_id" =>
            $controller_id,

        "D1" => (int)$row["D1"],
        "D2" => (int)$row["D2"],
        "D3" => (int)$row["D3"],
        "D4" => (int)$row["D4"],
        "D5" => (int)$row["D5"],
        "D6" => (int)$row["D6"],
        "D7" => (int)$row["D7"],
        "D8" => (int)$row["D8"],

        "last_seen" =>
            $last_seen

    ]);

    exit;
}


/* ============================================================
   ACTION = SET
   ============================================================
 *
 * Example:
 *
 * api.php?action=set
 * &controller_id=ESP0001
 * &device_token=ESP0001-TOKEN-2026-A7K9X2
 * &pin=D1
 * &value=1
 *
 * ============================================================
 */

if ($action === "set") {


    $pin = strtoupper(
        trim($_GET["pin"] ?? "")
    );


    $value = isset($_GET["value"])
        ? (int)$_GET["value"]
        : -1;


    /* ========================================================
       CHECK PIN
       ======================================================== */

    if (!preg_match(
        '/^D[1-8]$/',
        $pin
    )) {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid pin"
        ]);

        exit;
    }


    /* ========================================================
       CHECK VALUE
       ======================================================== */

    if (
        $value !== 0 &&
        $value !== 1
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid value"
        ]);

        exit;
    }


    /* ========================================================
       UPDATE PIN
       ======================================================== */

    $sql = "
        UPDATE esp_control
        SET `$pin` = ?
        WHERE controller_id = ?
    ";


    $stmt = $conn->prepare($sql);


    if (!$stmt) {

        echo json_encode([
            "status" => "error",
            "message" => "Pin update prepare failed"
        ]);

        exit;
    }


    $stmt->bind_param(
        "is",
        $value,
        $controller_id
    );


    if (!$stmt->execute()) {

        echo json_encode([
            "status" => "error",
            "message" => "Pin update failed"
        ]);

        $stmt->close();

        exit;
    }


    $stmt->close();


    echo json_encode([

        "status" => "ok",

        "controller_id" =>
            $controller_id,

        "pin" => $pin,

        "value" => $value,

        "last_seen" =>
            $last_seen

    ]);

    exit;
}


/* ============================================================
   UNKNOWN ACTION
   ============================================================ */

echo json_encode([

    "status" => "error",

    "message" =>
        "Unknown action. Use action=get or action=set"

]);

exit;

?>
