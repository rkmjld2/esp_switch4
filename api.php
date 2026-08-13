<?php
require_once "db.php";

header("Content-Type: application/json; charset=UTF-8");

$action = $_GET["action"] ?? "";
$controller_id = trim($_GET["controller_id"] ?? "");
$device_token = trim($_GET["device_token"] ?? "");

if ($controller_id === "") {
    echo json_encode(["success"=>false,"message"=>"controller_id is missing"]);
    exit;
}

if ($device_token === "") {
    echo json_encode(["success"=>false,"message"=>"device_token is missing"]);
    exit;
}

/* Verify Controller ID and Device Token */
$stmt = $conn->prepare(
    "SELECT controller_id, device_token, active
     FROM controllers
     WHERE controller_id = ?
     LIMIT 1"
);
$stmt->bind_param("s", $controller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(["success"=>false,"message"=>"Unknown controller_id"]);
    exit;
}

$controller = $result->fetch_assoc();
$stmt->close();

if (!hash_equals(
    (string)$controller["device_token"],
    (string)$device_token
)) {
    echo json_encode(["success"=>false,"message"=>"Invalid device token"]);
    exit;
}

if ((int)$controller["active"] !== 1) {
    echo json_encode([
        "success"=>false,
        "message"=>"Controller is inactive",
        "controller_id"=>$controller_id
    ]);
    exit;
}

/*
 * IMPORTANT:
 * The new esp_switch3 project does NOT use controller_status.
 * Last communication time is stored in controllers.last_seen.
 */
$stmt = $conn->prepare(
    "UPDATE controllers
     SET last_seen = CURRENT_TIMESTAMP
     WHERE controller_id = ?"
);
$stmt->bind_param("s", $controller_id);
$stmt->execute();
$stmt->close();

/* ESP8266 requests D1-D8 */
if ($action === "get") {

    $stmt = $conn->prepare(
        "SELECT D1,D2,D3,D4,D5,D6,D7,D8
         FROM esp_control
         WHERE controller_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("s", $controller_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode([
            "success"=>false,
            "message"=>"No esp_control row found",
            "controller_id"=>$controller_id
        ]);
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        "success"=>true,
        "controller_id"=>$controller_id,
        "D1"=>(int)$row["D1"],
        "D2"=>(int)$row["D2"],
        "D3"=>(int)$row["D3"],
        "D4"=>(int)$row["D4"],
        "D5"=>(int)$row["D5"],
        "D6"=>(int)$row["D6"],
        "D7"=>(int)$row["D7"],
        "D8"=>(int)$row["D8"]
    ]);
    exit;
}

/* Optional SET command */
if ($action === "set") {

    $pin = strtoupper(trim($_GET["pin"] ?? ""));
    $value = isset($_GET["value"]) ? (int)$_GET["value"] : -1;

    $allowed = ["D1","D2","D3","D4","D5","D6","D7","D8"];

    if (!in_array($pin, $allowed, true)) {
        echo json_encode(["success"=>false,"message"=>"Invalid pin"]);
        exit;
    }

    if ($value !== 0 && $value !== 1) {
        echo json_encode(["success"=>false,"message"=>"Invalid value"]);
        exit;
    }

    $sql = "UPDATE esp_control SET `$pin`=? WHERE controller_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $value, $controller_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        "success"=>true,
        "controller_id"=>$controller_id,
        "pin"=>$pin,
        "value"=>$value
    ]);
    exit;
}

echo json_encode([
    "success"=>false,
    "message"=>"Invalid action"
]);
exit;
?>
