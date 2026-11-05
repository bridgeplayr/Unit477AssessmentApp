<?php
define('APP_VERSION', '1.1');

date_default_timezone_set('America/Los_Angeles'); // PDT/PST

error_log("CONFIG LOADED");
error_log("HOST: " . $_SERVER['HTTP_HOST']);

if ($_SERVER['HTTP_HOST'] === 'localhost:8000') {
    error_log("LOCAL DB BLOCK EXECUTED");

    $servername = "127.0.0.1";
    $username   = "root";
    $password   = "Rick.Garvin!77";
    $dbname     = "bridge_local";
} else {
    error_log("PRODUCTION DB BLOCK EXECUTED");

    $servername = "localhost";
    $username   = "uydcdcyfwllsh";
    $password   = "qddigrzbntsg";
    $dbname     = "db4krxl4bpud17";
}

define('LOG_FILE', 'logfile3.txt');
