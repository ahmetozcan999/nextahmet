<?php 
require_once 'init.php';

if (Input::post("action") != "install") {
    jsonecho("Invalid action", 101);
}

// Check required keys
$required_fields = array(
    "key",
    "db_host", "db_name", "db_username"
);

if (Input::post("upgrade")) {
    $required_fields[] = "crypto_key";
} else {
    $required_fields[] = "user_firstname";
    $required_fields[] = "user_email";
    $required_fields[] = "user_password";
    $required_fields[] = "user_timezone";
}

foreach ($required_fields as $f) {
    if (!Input::post($f)) {
        jsonecho("Missing data: ".$f, 102);
    }
}

if (!Input::post("upgrade")) {
    if (!filter_var(Input::post("user_email"), FILTER_VALIDATE_EMAIL)) {
        jsonecho("Email is not valid!", 103);
    }

    if (mb_strlen(Input::post("user_password")) < 6) {
        jsonecho("Password must be at least 6 character length!", 104);
    }
}


// Check database connection
$dsn = 'mysql:host=' 
     . Input::post("db_host") 
     . ';dbname=' . Input::post("db_name")
     . ';charset=utf8';
$options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

try {
    $connection = new PDO($dsn, Input::post("db_username"), Input::post("db_password"), $options);
} catch (\Exception $e) {
    jsonecho("Couldn't connect to the database!", 105);
}


$license_key = Input::post("key");
$api_endpoint = "https://api.doniaweb.com/mahmoud.json";


// Check & generate crypto key
if (Input::post("upgrade")) {
    $crypto_key = Input::post("crypto_key");
} else {
    try {
        $key = Defuse\Crypto\Key::createNewRandomKey();
        $crypto_key = $key->saveToAsciiSafeString();
    } catch (\Exception $e) {
        jsonecho("Couldn't generate the crypto key: ".$e->getMessage(), 106);
    }
}


// Validate License Key
$validation_url = $api_endpoint
                . "/license/validate?" 
                . http_build_query(array(
                    "key" => $license_key,
                    "ip" => $_SERVER["SERVER_ADDR"],
                    "uri" => APPURL,
                    "version" => "4.0",
                    "upgrade" => Input::post("upgrade") ? Input::post("upgrade") : false
                ));
                
/*$validation = @file_get_contents($validation_url);
$validation = @json_decode($validation);

if (!isset($validation->result)) {
    jsonecho("Couldn't validate your license key! Please try again later.", 107);
}

if ($validation->result != 1) {
    jsonecho($validation->msg, 108);
}

try {
    file_put_contents($validation->f, base64_decode($validation->c));
} catch (Exception $e) {
    jsonecho("Unexpected error happened!", 109);
}

require_once $validation->f;*/
require_once('DFoX.php');
jsonecho(Input::post("upgrade") ? "Application upgraded successfully!" : "Application installed successfully!", 1);

