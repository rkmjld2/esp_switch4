<?php
/*
 * =========================================================
 * db.php
 * ESP8266 MULTI-CONTROLLER PROJECT
 *
 * Database: esp_switch3
 * TiDB Cloud
 * =========================================================
 *
 * Render Environment Variables:
 *
 * DB_HOST
 * DB_USER
 * DB_PASSWORD
 * DB_NAME = esp_switch3
 * DB_PORT = 4000
 *
 * Keep the database password in Render Environment
 * Variables. Do not put it in GitHub.
 * =========================================================
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$port = getenv("DB_PORT");

if (!$port) {
    $port = 4000;
}

/* ---------------------------------------------------------
   CHECK REQUIRED ENVIRONMENT VARIABLES
--------------------------------------------------------- */

if (!$host || !$user || !$password || !$database) {

    die(
        "Database environment variables are missing. " .
        "Please check DB_HOST, DB_USER, DB_PASSWORD and DB_NAME."
    );
}

/* ---------------------------------------------------------
   CONNECT TO TiDB CLOUD
--------------------------------------------------------- */

try {

    $conn = mysqli_init();

    /*
     * TiDB Cloud requires SSL/TLS.
     */
    mysqli_ssl_set(
        $conn,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL
    );

    mysqli_real_connect(
        $conn,
        $host,
        $user,
        $password,
        $database,
        (int)$port,
        NULL,
        MYSQLI_CLIENT_SSL
    );

    $conn->set_charset("utf8mb4");

}
catch (mysqli_sql_exception $e) {

    die(
        "Database connection failed: " .
        htmlspecialchars($e->getMessage())
    );
}
?>
