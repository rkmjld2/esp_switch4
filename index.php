```php
<?php
/*
 * ESP-SWITCH4
 * Stage 1 - Controller Selection
 *
 * Database tables:
 *
 * controllers:
 *   id
 *   controller_id
 *   device_token
 *   customer_name
 *   active
 *   last_seen
 *
 * esp_control:
 *   controller_id
 *   D1 ... D8
 */

require_once "db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   HELPER
   ========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   ONLINE LIMIT
   =========================================================
   Controller is considered online when last_seen is within
   the last 60 seconds.
   ========================================================= */

$ONLINE_LIMIT = 60;


/* =========================================================
   SELECTED CONTROLLER
   ========================================================= */

$controller_id = trim(
    $_GET["controller_id"] ?? ""
);


/* =========================================================
   GET CONTROLLER LIST
   ========================================================= */

$controllers = [];

$sql = "
    SELECT
        id,
        controller_id,
        customer_name,
        active,
        last_seen
    FROM controllers
    ORDER BY id
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $controllers[] = $row;

    }

}


/* =========================================================
   IF NO CONTROLLER SELECTED, USE FIRST CONTROLLER
   ========================================================= */

if (
    $controller_id === "" &&
    count($controllers) > 0
) {

    $controller_id =
        $controllers[0]["controller_id"];

}


/* =========================================================
   GET SELECTED CONTROLLER
   ========================================================= */

$selected = null;

if ($controller_id !== "") {

    $stmt = $conn->prepare("
        SELECT
            id,
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

    $result =
        $stmt->get_result();

    if ($result->num_rows > 0) {

        $selected =
            $result->fetch_assoc();

    }

    $stmt->close();

}


/* =========================================================
   DEFAULT D1-D8
   ========================================================= */

$pins = [

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
   READ D1-D8 OF SELECTED CONTROLLER
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

    $stmt->bind_param(
        "s",
        $selected["controller_id"]
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows > 0) {

        $row =
            $result->fetch_assoc();

        for ($i = 1; $i <= 8; $i++) {

            $pin = "D" . $i;

            $pins[$pin] =
                (int)$row[$pin];

        }

    }

    $stmt->close();

}


/* =========================================================
   PROCESS BUTTONS
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $selected
) {

    $action =
        strtoupper(
            trim($_POST["action"] ?? "")
        );


    /* =====================================================
       ALL ON
       ===================================================== */

    if ($action === "ALL_ON") {

        $stmt = $conn->prepare("
            UPDATE esp_control
            SET
                D1 = 1,
                D2 = 1,
                D3 = 1,
                D4 = 1,
                D5 = 1,
                D6 = 1,
                D7 = 1,
                D8 = 1
            WHERE controller_id = ?
        ");

        $stmt->bind_param(
            "s",
            $selected["controller_id"]
        );

        $stmt->execute();

        $stmt->close();

    }


    /* =====================================================
       ALL OFF
       ===================================================== */

    elseif ($action === "ALL_OFF") {

        $stmt = $conn->prepare("
            UPDATE esp_control
            SET
                D1 = 0,
                D2 = 0,
                D3 = 0,
                D4 = 0,
                D5 = 0,
                D6 = 0,
                D7 = 0,
                D8 = 0
            WHERE controller_id = ?
        ");

        $stmt->bind_param(
            "s",
            $selected["controller_id"]
        );

        $stmt->execute();

        $stmt->close();

    }


    /* =====================================================
       INDIVIDUAL D1-D8
       ===================================================== */

    elseif (
        preg_match(
            '/^D[1-8]$/',
            $action
        )
    ) {

        $value =
            isset($_POST["value"])
            ? (int)$_POST["value"]
            : -1;


        if (
            $value === 0 ||
            $value === 1
        ) {

            /*
             * Pin name is validated by the regex above,
             * so it is safe to use as a column identifier.
             */

            $sql = "
                UPDATE esp_control
                SET `$action` = ?
                WHERE controller_id = ?
            ";

            $stmt =
                $conn->prepare($sql);

            $stmt->bind_param(
                "is",
                $value,
                $selected["controller_id"]
            );

            $stmt->execute();

            $stmt->close();

        }

    }


    /* =====================================================
       RETURN TO SAME CONTROLLER
       ===================================================== */

    header(
        "Location: index.php?controller_id=" .
        urlencode(
            $selected["controller_id"]
        )
    );

    exit;
}


/* =========================================================
   ONLINE / OFFLINE
   ========================================================= */

$is_online = false;

if (
    $selected &&
    !empty($selected["last_seen"])
) {

    $last_seen =
        strtotime(
            $selected["last_seen"]
        );

    if ($last_seen !== false) {

        $age =
            time() - $last_seen;

        if (
            $age >= 0 &&
            $age <= $ONLINE_LIMIT
        ) {

            $is_online = true;

        }

    }

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>ESP-SWITCH4 Controller</title>


<style>

body {

    font-family: Arial, sans-serif;

    margin: 0;

    background: #f1f3f6;

}


.container {

    width: 92%;

    max-width: 950px;

    margin: 30px auto;

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 2px 15px
        rgba(0,0,0,0.12);

}


h1 {

    text-align: center;

    margin-top: 0;

    color: #111;

}


.selector {

    text-align: center;

    background: #eef3f8;

    padding: 18px;

    border-radius: 8px;

    margin-bottom: 20px;

}


.selector select {

    padding: 10px;

    font-size: 16px;

    width: 280px;

    max-width: 90%;

}


.info {

    text-align: center;

    background: #f7f7f7;

    padding: 18px;

    border-radius: 8px;

}


.info p {

    margin: 9px;

}


.online {

    color: green;

    font-size: 20px;

    font-weight: bold;

}


.offline {

    color: red;

    font-size: 20px;

    font-weight: bold;

}


.main-buttons {

    text-align: center;

    margin: 20px 0;

}


.main-buttons button {

    padding: 12px 30px;

    margin: 5px;

    border: none;

    border-radius: 6px;

    color: white;

    font-weight: bold;

    cursor: pointer;

    font-size: 15px;

}


.all-on {

    background: #087f23;

}


.all-off {

    background: #e60000;

}


.pin-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-top: 20px;

}


.pin-card {

    border: 1px solid #ddd;

    border-radius: 10px;

    padding: 18px;

    text-align: center;

    background: #fafafa;

}


.pin-name {

    font-size: 20px;

    font-weight: bold;

    margin-bottom: 15px;

}


.pin-status {

    font-size: 17px;

    font-weight: bold;

    margin-bottom: 15px;

}


.pin-on {

    color: green;

}


.pin-off {

    color: red;

}


.pin-card button {

    padding: 9px 15px;

    margin: 3px;

    border: none;

    border-radius: 5px;

    color: white;

    cursor: pointer;

    font-weight: bold;

}


.btn-on {

    background: green;

}


.btn-off {

    background: red;

}


@media (max-width: 700px) {

    .pin-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 450px) {

    .pin-grid {

        grid-template-columns:
            1fr;

    }

}


</style>

</head>


<body>


<div class="container">


<h1>

ESP-SWITCH4 Controller

</h1>


<!-- =====================================================
     CONTROLLER SELECTION
     ===================================================== -->

<div class="selector">


<form
    method="get"
    id="controllerForm"
>


<label>

<strong>

Select Controller:

</strong>

</label>


<br><br>


<select
    name="controller_id"
    onchange="document.getElementById('controllerForm').submit();"
>


<?php foreach (
    $controllers as $c
): ?>


<option

    value="<?= h(
        $c["controller_id"]
    ) ?>"

    <?= (
        $selected &&
        $selected["controller_id"] ===
        $c["controller_id"]
    )
        ? "selected"
        : ""
    ?>

>


<?= h(
    $c["controller_id"]
) ?>

-
<?= h(
    $c["customer_name"]
) ?>


</option>


<?php endforeach; ?>


</select>


</form>


</div>


<?php if ($selected): ?>


<!-- =====================================================
     CONTROLLER INFORMATION
     ===================================================== -->

<div class="info">


<p>

<strong>

Controller ID:

</strong>

<?= h(
    $selected["controller_id"]
) ?>


</p>


<p>

<strong>

Customer:

</strong>

<?= h(
    $selected["customer_name"]
) ?>


</p>


<?php if ($is_online): ?>


<p class="online">

CONNECTED / ONLINE

</p>


<?php else: ?>


<p class="offline">

OFFLINE

</p>


<?php endif; ?>


<p>

<strong>

Last Seen (India Time):

</strong>

<?= h(
    $selected["last_seen"] ?? "-"
) ?>


</p>


</div>



<!-- =====================================================
     ALL ON / ALL OFF
     ===================================================== -->

<div class="main-buttons">


<form
    method="post"
    style="display:inline;"
>


<input
    type="hidden"
    name="action"
    value="ALL_ON"
>


<button
    type="submit"
    class="all-on"
>


ALL ON


</button>


</form>



<form
    method="post"
    style="display:inline;"
>


<input
    type="hidden"
    name="action"
    value="ALL_OFF"
>


<button
    type="submit"
    class="all-off"
>


ALL OFF


</button>


</form>


</div>



<!-- =====================================================
     D1-D8
     ===================================================== -->

<div class="pin-grid">


<?php for (
    $i = 1;
    $i <= 8;
    $i++
):

    $pin =
        "D" . $i;

    $value =
        (int)$pins[$pin];

?>


<div class="pin-card">


<div class="pin-name">

<?= $pin ?>

</div>


<div
    class="pin-status
    <?= $value
        ? "pin-on"
        : "pin-off"
    ?>"
>


<?= $value
    ? "ON"
    : "OFF"
?>


</div>


<!-- ON -->

<form
    method="post"
    style="display:inline;"
>


<input
    type="hidden"
    name="action"
    value="<?= $pin ?>"
>


<input
    type="hidden"
    name="value"
    value="1"
>


<button
    type="submit"
    class="btn-on"
>


ON


</button>


</form>


<!-- OFF -->

<form
    method="post"
    style="display:inline;"
>


<input
    type="hidden"
    name="action"
    value="<?= $pin ?>"
>


<input
    type="hidden"
    name="value"
    value="0"
>


<button
    type="submit"
    class="btn-off"
>


OFF


</button>


</form>


</div>


<?php endfor; ?>


</div>


<?php else: ?>


<div class="info">


<p class="offline">

No controller found.

</p>


</div>


<?php endif; ?>


</div>


</body>

</html>
```
