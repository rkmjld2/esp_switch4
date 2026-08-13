<?php
/*
 * ESP_SWITCH3 - FINAL index.php
 *
 * FINAL DESIGN:
 * The browser does NOT ask the customer for Controller ID or Token.
 *
 * IMPORTANT ARCHITECTURE:
 * app.ino identifies the physical ESP8266 to api.php using:
 *     controller_id + device_token
 *
 * api.php updates controllers.last_seen and reads/writes esp_control.
 *
 * The browser cannot directly receive the ID from app.ino.
 * Therefore this page uses the server's last_seen information.
 *
 * If exactly ONE controller is online, that controller is opened
 * automatically.
 *
 * If several controllers are online, the browser cannot safely
 * determine which physical controller belongs to the customer
 * without a browser/customer association. In that situation,
 * this page deliberately shows the controller list rather than
 * controlling the wrong device.
 *
 * Last Seen is stored in UTC and displayed in India Standard Time.
 */

session_start();
require_once "db.php";

/* ------------------------------------------------------------
   SETTINGS
   ------------------------------------------------------------ */

$ONLINE_SECONDS = 15;

/* ------------------------------------------------------------
   HELPERS
   ------------------------------------------------------------ */

function valid_controller_id($id)
{
    return is_string($id) &&
           preg_match('/^[A-Za-z0-9_-]{1,50}$/', $id);
}

function is_controller_online($last_seen, $online_seconds)
{
    if (empty($last_seen)) {
        return false;
    }

    $t = strtotime($last_seen);

    if ($t === false) {
        return false;
    }

    return (time() - $t) <= $online_seconds;
}

function india_time($utc_time)
{
    if (empty($utc_time)) {
        return "Not available";
    }

    try {
        $dt = new DateTime(
            $utc_time,
            new DateTimeZone("UTC")
        );

        $dt->setTimezone(
            new DateTimeZone("Asia/Kolkata")
        );

        return $dt->format("Y-m-d H:i:s");
    }
    catch (Exception $e) {
        return $utc_time;
    }
}

/* ------------------------------------------------------------
   LOGOUT / CLEAR CURRENT BROWSER SELECTION
   ------------------------------------------------------------ */

if (isset($_GET["logout"])) {

    $_SESSION = [];
    session_destroy();

    header("Location: index.php");
    exit;
}

/*
 * Back to controller list deliberately clears the browser's
 * current selection.
 */
if (isset($_GET["back"])) {

    unset($_SESSION["controller_id"]);

    header("Location: index.php");
    exit;
}

/* ------------------------------------------------------------
   READ ALL REGISTERED CONTROLLERS
   ------------------------------------------------------------ */

$controllers = [];

$sql = "
    SELECT
        controller_id,
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

/* ------------------------------------------------------------
   DETERMINE CURRENT CONTROLLER
   ------------------------------------------------------------ */

$controller_id = $_SESSION["controller_id"] ?? "";

/*
 * IMPORTANT:
 * We intentionally do NOT accept controller_id from a normal
 * browser URL as the automatic identification mechanism.
 *
 * app.ino -> api.php is the controller identification path.
 *
 * If the browser has no previous selection, find controllers
 * that have recently contacted api.php.
 */

if ($controller_id === "") {

    $online_ids = [];

    foreach ($controllers as $row) {

        if ((int)$row["active"] !== 1) {
            continue;
        }

        if (is_controller_online(
            $row["last_seen"],
            $ONLINE_SECONDS
        )) {
            $online_ids[] = $row["controller_id"];
        }
    }

    /*
     * With one active/online ESP8266, automatically open it.
     */
    if (count($online_ids) === 1) {

        $controller_id = $online_ids[0];

        $_SESSION["controller_id"] = $controller_id;
    }
}

/* ------------------------------------------------------------
   MANUAL SELECTION IS KEPT ONLY AS A FALLBACK / TEST FEATURE
   ------------------------------------------------------------ */

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["select_controller_id"])) {

    $id = trim($_POST["select_controller_id"]);

    if (valid_controller_id($id)) {

        $_SESSION["controller_id"] = $id;

        header("Location: index.php");
        exit;
    }
}

/*
 * If a manual selection already exists in the session, use it.
 */
$controller_id = $_SESSION["controller_id"] ?? "";

/* ------------------------------------------------------------
   NO UNIQUE ONLINE CONTROLLER
   ------------------------------------------------------------ */

if ($controller_id === "") {
?>
<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<meta http-equiv="refresh"
      content="5">

<title>ESP-SWITCH3</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #eeeeee;
    margin: 0;
    padding: 20px;
}

.box {
    max-width: 950px;
    margin: 35px auto;
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 3px 15px #aaaaaa;
    text-align: center;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    border: 1px solid #dddddd;
    padding: 12px;
}

th {
    background: #f3f3f3;
}

.online {
    color: green;
    font-weight: bold;
}

.offline {
    color: red;
    font-weight: bold;
}

button {
    padding: 9px 18px;
    border: 0;
    border-radius: 6px;
    background: #0066cc;
    color: white;
    cursor: pointer;
}

.note {
    background: #f7f7f7;
    padding: 12px;
    border-radius: 8px;
}

</style>

</head>

<body>

<div class="box">

<h1>ESP-SWITCH3</h1>

<h2>Waiting for Controller</h2>

<div class="note">

<p>
The browser is waiting for a controller to communicate
with the server.
</p>

<p>
No Controller ID or Device Token is required from the customer.
</p>

</div>

<h3>Registered Controllers</h3>

<table>

<tr>
<th>Controller ID</th>
<th>Customer</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php foreach ($controllers as $row): ?>

<?php

$online = is_controller_online(
    $row["last_seen"],
    $ONLINE_SECONDS
);

?>

<tr>

<td>
<?= htmlspecialchars($row["controller_id"]) ?>
</td>

<td>
<?= htmlspecialchars(
    $row["customer_name"] ?? ""
) ?>
</td>

<td>

<?php if ((int)$row["active"] !== 1): ?>

<span class="offline">
INACTIVE
</span>

<?php elseif ($online): ?>

<span class="online">
ONLINE
</span>

<?php else: ?>

<span class="offline">
OFFLINE
</span>

<?php endif; ?>

</td>

<td>

<?php if ($online): ?>

<form method="post">

<input
    type="hidden"
    name="select_controller_id"
    value="<?= htmlspecialchars(
        $row["controller_id"]
    ) ?>">

<button type="submit">
CONTROL
</button>

</form>

<?php else: ?>

-

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</body>
</html>

<?php

exit;

}

/* ------------------------------------------------------------
   VALIDATE SELECTED CONTROLLER
   ------------------------------------------------------------ */

if (!valid_controller_id($controller_id)) {

    unset($_SESSION["controller_id"]);

    header("Location: index.php");

    exit;
}

/* ------------------------------------------------------------
   GET SELECTED CONTROLLER
   ------------------------------------------------------------ */

$stmt = $conn->prepare("
    SELECT
        controller_id,
        customer_name,
        active,
        last_seen
    FROM controllers
    WHERE controller_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $controller_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    unset($_SESSION["controller_id"]);

    header("Location: index.php");

    exit;
}

$controller = $result->fetch_assoc();

$stmt->close();

/* ------------------------------------------------------------
   ACTIVE CHECK
   ------------------------------------------------------------ */

if ((int)$controller["active"] !== 1) {

    unset($_SESSION["controller_id"]);

    die(
        "<h2 style='text-align:center'>
         Controller is inactive.
         </h2>"
    );
}

/* ------------------------------------------------------------
   D1-D8 WEB CONTROL
   ------------------------------------------------------------ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
     * Individual pin
     */
    if (
        isset($_POST["pin"]) &&
        isset($_POST["value"])
    ) {

        $allowed = [
            "D1", "D2", "D3", "D4",
            "D5", "D6", "D7", "D8"
        ];

        $pin = strtoupper(
            trim($_POST["pin"])
        );

        $value = (int)$_POST["value"];

        if (
            in_array($pin, $allowed, true) &&
            ($value === 0 || $value === 1)
        ) {

            $sql = "
                UPDATE esp_control
                SET `$pin` = ?
                WHERE controller_id = ?
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "is",
                $value,
                $controller_id
            );

            $stmt->execute();

            $stmt->close();
        }
    }

    /*
     * ALL ON
     */
    if (isset($_POST["all_on"])) {

        $stmt = $conn->prepare("
            UPDATE esp_control
            SET
                D1=1,
                D2=1,
                D3=1,
                D4=1,
                D5=1,
                D6=1,
                D7=1,
                D8=1
            WHERE controller_id = ?
        ");

        $stmt->bind_param(
            "s",
            $controller_id
        );

        $stmt->execute();

        $stmt->close();
    }

    /*
     * ALL OFF
     */
    if (isset($_POST["all_off"])) {

        $stmt = $conn->prepare("
            UPDATE esp_control
            SET
                D1=0,
                D2=0,
                D3=0,
                D4=0,
                D5=0,
                D6=0,
                D7=0,
                D8=0
            WHERE controller_id = ?
        ");

        $stmt->bind_param(
            "s",
            $controller_id
        );

        $stmt->execute();

        $stmt->close();
    }

    header("Location: index.php");

    exit;
}

/* ------------------------------------------------------------
   READ D1-D8
   ------------------------------------------------------------ */

$stmt = $conn->prepare("
    SELECT
        D1,D2,D3,D4,
        D5,D6,D7,D8
    FROM esp_control
    WHERE controller_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $controller_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    die(
        "<h2 style='text-align:center'>
         No control row found for this controller.
         </h2>"
    );
}

$control = $result->fetch_assoc();

$stmt->close();

/* ------------------------------------------------------------
   ONLINE STATUS
   ------------------------------------------------------------ */

$online = is_controller_online(
    $controller["last_seen"],
    $ONLINE_SECONDS
);

/* ------------------------------------------------------------
   INDIA TIME
   ------------------------------------------------------------ */

$last_seen_ist = india_time(
    $controller["last_seen"]
);

?>
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<meta http-equiv="refresh"
      content="5">

<title>
ESP-SWITCH3 Controller
</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #eeeeee;
    margin: 0;
    padding: 20px;
}

.box {
    max-width: 900px;
    margin: auto;
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 3px 15px #aaaaaa;
    text-align: center;
}

.info {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 10px;
}

.online {
    color: green;
    font-weight: bold;
}

.offline {
    color: red;
    font-weight: bold;
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 25px;
}

.pin {
    padding: 18px;
    background: #f7f7f7;
    border: 1px solid #dddddd;
    border-radius: 12px;
}

.state_on {
    color: green;
    font-weight: bold;
}

.state_off {
    color: red;
    font-weight: bold;
}

button {
    padding: 10px 16px;
    margin: 4px;
    border: 0;
    border-radius: 7px;
    color: white;
    cursor: pointer;
}

.on_button {
    background: green;
}

.off_button {
    background: red;
}

.all_button {
    background: #0066cc;
    padding: 12px 25px;
}

.nav_button {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 20px;
    background: #555555;
    color: white;
    text-decoration: none;
    border-radius: 7px;
}

@media (max-width: 650px) {

    .grid {
        grid-template-columns: repeat(2, 1fr);
    }

}

</style>

</head>

<body>

<div class="box">

<h1>
ESP-SWITCH3 Controller
</h1>

<div class="info">

<p>
<b>Controller ID:</b>
<?= htmlspecialchars($controller_id) ?>
</p>

<p>
<b>Customer:</b>
<?= htmlspecialchars(
    $controller["customer_name"] ?? ""
) ?>
</p>

<p>

<b>Controller Status:</b>

<span class="<?= $online
    ? "online"
    : "offline"
?>">

<?= $online
    ? "ONLINE"
    : "OFFLINE"
?>

</span>

</p>

<p>

<b>Last Seen (India Time):</b>

<?= htmlspecialchars(
    $last_seen_ist
) ?>

</p>

</div>

<form method="post">

<button
    class="all_button"
    type="submit"
    name="all_on"
    value="1">

ALL ON

</button>

<button
    class="all_button"
    type="submit"
    name="all_off"
    value="1">

ALL OFF

</button>

</form>

<div class="grid">

<?php

for ($i = 1; $i <= 8; $i++):

    $pin = "D" . $i;

    $state = (int)$control[$pin];

?>

<div class="pin">

<h3>
<?= $pin ?>
</h3>

<p class="<?= $state
    ? "state_on"
    : "state_off"
?>">

<?= $state
    ? "ON"
    : "OFF"
?>

</p>

<form method="post">

<input
    type="hidden"
    name="pin"
    value="<?= $pin ?>">

<button
    class="on_button"
    type="submit"
    name="value"
    value="1">

ON

</button>

<button
    class="off_button"
    type="submit"
    name="value"
    value="0">

OFF

</button>

</form>

</div>

<?php endfor; ?>

</div>

<a
    class="nav_button"
    href="index.php?back=1">

BACK / WAIT FOR CONTROLLER

</a>

<a
    class="nav_button"
    href="index.php?logout=1">

CLEAR SESSION

</a>

</div>

</body>

</html>
