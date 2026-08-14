<?php
/*
 * ESP-SWITCH4 - Stage 1 index.php
 *
 * - Shows all registered controllers in a selection list.
 * - User selects ONE controller at a time.
 * - Shows selected controller's customer details.
 * - Shows ONLINE/OFFLINE and last_seen.
 * - Controls only the selected controller's D1-D8.
 *
 * Requires:
 *   db.php
 */

require_once "db.php";

date_default_timezone_set("Asia/Kolkata");

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}


/* =========================================================
   1. GET CONTROLLER ID FROM WEBPAGE
   ========================================================= */

$controller_id = trim($_GET["controller_id"] ?? "");


/* =========================================================
   2. GET ALL REGISTERED CONTROLLERS
   ========================================================= */

$controllers = [];

$sql = "
    SELECT
        id,
        controller_id,
        customer_id,
        customer_name,
        active,
        last_seen
    FROM controllers
    ORDER BY id
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $controllers[] = $row;
}


/*
 * If no controller has been selected yet,
 * display the first controller initially.
 */
if ($controller_id === "" && count($controllers) > 0) {
    $controller_id = $controllers[0]["controller_id"];
}


/* =========================================================
   3. GET THE SELECTED CONTROLLER
   ========================================================= */

$selected = null;

if ($controller_id !== "") {

    $stmt = $conn->prepare("
        SELECT
            id,
            controller_id,
            customer_id,
            customer_name,
            active,
            last_seen
        FROM controllers
        WHERE controller_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $controller_id);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $selected = $result->fetch_assoc();
    }

    $stmt->close();
}


/* =========================================================
   4. DETERMINE ONLINE / OFFLINE
   ========================================================= */

$online = false;

/*
 * Controller is considered online if last_seen
 * is within the previous 15 seconds.
 *
 * api.php should save last_seen in IST.
 */

if ($selected && !empty($selected["last_seen"])) {

    $last_seen_timestamp = strtotime($selected["last_seen"]);
    $current_timestamp = time();

    if (
        $last_seen_timestamp !== false &&
        ($current_timestamp - $last_seen_timestamp) <= 15 &&
        ($current_timestamp - $last_seen_timestamp) >= 0
    ) {
        $online = true;
    }
}


/* =========================================================
   5. DEFAULT D1-D8 VALUES
   ========================================================= */

$d = [
    "D1" => 0,
    "D2" => 0,
    "D3" => 0,
    "D4" => 0,
    "D5" => 0,
    "D6" => 0,
    "D7" => 0,
    "D8" => 0
];


/* =========================================================
   6. GET D1-D8 FOR SELECTED CONTROLLER
   ========================================================= */

if ($selected) {

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

    $stmt->bind_param("s", $selected["controller_id"]);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $d = array_merge($d, $result->fetch_assoc());
    }

    $stmt->close();
}


/* =========================================================
   7. HANDLE ON/OFF BUTTON
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && $selected) {

    $pin = strtoupper(trim($_POST["pin"] ?? ""));

    $value = isset($_POST["value"])
        ? (int)$_POST["value"]
        : -1;

    $allowed = [
        "D1",
        "D2",
        "D3",
        "D4",
        "D5",
        "D6",
        "D7",
        "D8"
    ];

    if (
        in_array($pin, $allowed, true) &&
        ($value === 0 || $value === 1)
    ) {

        /*
         * IMPORTANT:
         * Only the selected controller is updated.
         */

        $sql = "
            UPDATE esp_control
            SET `$pin` = ?
            WHERE controller_id = ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "is",
            $value,
            $selected["controller_id"]
        );

        $stmt->execute();

        $stmt->close();
    }

    /*
     * Return to the same selected controller.
     */

    header(
        "Location: index.php?controller_id=" .
        urlencode($selected["controller_id"])
    );

    exit;
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<!-- Refresh page every 5 seconds -->

<meta http-equiv="refresh"
      content="5">

<title>ESP-SWITCH4</title>


<style>

body {

    font-family: Arial, sans-serif;

    margin: 0;

    background: #f2f4f7;

    text-align: center;

}


.container {

    width: 92%;

    max-width: 750px;

    margin: 30px auto;

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow: 0 2px 12px rgba(0,0,0,.12);

}


h1 {

    margin-top: 0;

}


.selector {

    margin: 20px 0;

    padding: 15px;

    background: #eef3f8;

    border-radius: 8px;

}


select {

    width: 90%;

    max-width: 500px;

    padding: 10px;

    font-size: 16px;

}


.info {

    margin: 15px 0;

    padding: 15px;

    background: #f7f7f7;

    border-radius: 8px;

}


.status {

    font-weight: bold;

    font-size: 20px;

}


.online {

    color: green;

}


.offline {

    color: red;

}


table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 20px;

}


th,
td {

    border: 1px solid #ccc;

    padding: 10px;

}


th {

    background: #eeeeee;

}


button {

    min-width: 100px;

    padding: 8px 14px;

    border: 0;

    border-radius: 6px;

    cursor: pointer;

}


.on {

    background: #c8f7c5;

}


.off {

    background: #ffd2d2;

}

</style>

</head>


<body>


<div class="container">


<h1>ESP-SWITCH4</h1>


<!-- =====================================================
     CONTROLLER SELECTION
     ===================================================== -->

<div class="selector">


<form method="get">


<label for="controller_id">

<strong>Select Controller:</strong>

</label>


<br>
<br>


<select
    name="controller_id"
    id="controller_id"
    onchange="this.form.submit()"
>


<?php foreach ($controllers as $c): ?>


<option
    value="<?= h($c["controller_id"]) ?>"

    <?= (
        $selected &&
        $selected["controller_id"] ===
        $c["controller_id"]
    )
    ? "selected"
    : ""
    ?>
>


<?= h($c["controller_id"]) ?>

-

<?= h($c["customer_name"] ?? "Customer") ?>


</option>


<?php endforeach; ?>


</select>


</form>


</div>


<?php if ($selected): ?>


<!-- =====================================================
     SELECTED CONTROLLER INFORMATION
     ===================================================== -->

<div class="info">


<p>

<strong>Controller ID:</strong>

<?= h($selected["controller_id"]) ?>

</p>


<p>

<strong>Customer ID:</strong>

<?= h($selected["customer_id"] ?? "-") ?>

</p>


<p>

<strong>Customer:</strong>

<?= h($selected["customer_name"] ?? "-") ?>

</p>


<?php if ($online): ?>


<p class="status online">

CONNECTED / ONLINE

</p>


<?php else: ?>


<p class="status offline">

OFFLINE

</p>


<?php endif; ?>


<p>

<strong>Last Seen (India Time):</strong>

<?= h($selected["last_seen"] ?? "-") ?>

</p>


</div>


<!-- =====================================================
     D1-D8 CONTROL TABLE
     ===================================================== -->

<table>


<tr>

<th>Pin</th>

<th>Status</th>

<th>Control</th>

</tr>


<?php

for ($i = 1; $i <= 8; $i++):

    $pin = "D" . $i;

    $value = (int)$d[$pin];

?>


<tr>


<td>

<?= $pin ?>

</td>


<td class="<?= $value ? "on" : "off" ?>">


<?= $value ? "ON" : "OFF" ?>


</td>


<td>


<form method="post">


<input
    type="hidden"
    name="pin"
    value="<?= h($pin) ?>"
>


<input
    type="hidden"
    name="value"
    value="<?= $value ? 0 : 1 ?>"
>


<button type="submit">


<?= $value ? "Turn OFF" : "Turn ON" ?>


</button>


</form>


</td>


</tr>


<?php endfor; ?>


</table>


<?php else: ?>


<div class="info">


<p class="status offline">

NO CONTROLLER FOUND

</p>


<p>

Please add a controller to the
controllers table.

</p>


</div>


<?php endif; ?>


</div>


</body>

</html>
