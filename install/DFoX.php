<?php 
// Data Source Name
$dsn = 'mysql:host=' 
     . Input::post("db_host") 
     . ';dbname=' . Input::post("db_name")
     . ';charset=utf8';
$options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

try {
    $connection = new PDO($dsn, Input::post("db_username"), Input::post("db_password"), $options);
} catch (\Exception $e) {
    jsonecho("Couldn't connect to the database!", 107);
}


$dbconfig_file_path = "../app/config/db.config.php";
$config_file_path = "../app/config/config.php";
$sql_file_path = "app/inc/db.sql";
$index_file_path = "../index.php";
$upgrade_sqls = array(
    "1.0" => "app/inc/upgrade-1.0.sql",
    "2.0" => "app/inc/upgrade-2.0.sql",
);


$SQL = "";
if (Input::post("upgrade")) {
    foreach ($upgrade_sqls as $version => $file) {
        if ($version >= Input::post("upgrade")) {
            if (!is_file($file)) {
                jsonecho("Some of SQL files didn't not found in install folder!", 108);
            } 

            $SQL .= file_get_contents($file);
        }
    }
} else {
    if (!is_file($sql_file_path)) {
        jsonecho("Some of SQL files didn't not found in install folder!", 109);
    }

    $SQL .= file_get_contents($sql_file_path);
}


require_once $dbconfig_file_path;
if (DB_HOST != "NP_DB_HOST") {
    jsonecho("Something went wrong! It seems that application is already installed!", 110);
}


$tzlist = getTimezones();
$timezone = Input::post("user_timezone");
if (!isset($tzlist[$timezone])) {
    $timezone = "UTC";
}

# Install DB
$SQL = str_replace(
    array(
        "TABLE_ACCOUNTS",
        "TABLE_CAPTIONS",
        "TABLE_FILES",
        "TABLE_GENERAL_DATA",
        "TABLE_ORDERS",
        "TABLE_PACKAGES",
        "TABLE_PLUGINS",
        "TABLE_POSTS",
		"TABLE_OPTIONS",
        "TABLE_PROXIES",
        "TABLE_USERS",

        "'ADMIN_EMAIL'",
        "'ADMIN_PASSWORD'",
        "'ADMIN_FIRSTNAME'",
        "'ADMIN_LASTNAME'",
        "ADMIN_TIMEZONE",
        "'ADMIN_DATE'",
    ), 
    array(
        Input::post("db_table_prefix") . TABLE_ACCOUNTS,
        Input::post("db_table_prefix") . TABLE_CAPTIONS,
        Input::post("db_table_prefix") . TABLE_FILES,
        Input::post("db_table_prefix") . TABLE_GENERAL_DATA,
        Input::post("db_table_prefix") . TABLE_ORDERS,
        Input::post("db_table_prefix") . TABLE_PACKAGES,
        Input::post("db_table_prefix") . TABLE_PLUGINS,
        Input::post("db_table_prefix") . TABLE_POSTS,
		Input::post("db_table_prefix") . TABLE_OPTIONS,
        Input::post("db_table_prefix") . TABLE_PROXIES,
        Input::post("db_table_prefix") . TABLE_USERS,

        ":ADMIN_EMAIL",
        ":ADMIN_PASSWORD",
        ":ADMIN_FIRSTNAME",
        ":ADMIN_LASTNAME", 
        $timezone,
        ":ADMIN_DATE"
    ), 
    $SQL
);
$smtp = $connection->prepare($SQL);

if (Input::post("upgrade")) {
	if($SQL != "")
    	$smtp->execute();
} else {
    $smtp->execute(array(
        ":ADMIN_EMAIL" => Input::post("user_email"),
        ":ADMIN_PASSWORD" => password_hash(Input::post("user_password"), PASSWORD_DEFAULT),
        ":ADMIN_FIRSTNAME" => Input::post("user_firstname"),
        ":ADMIN_LASTNAME" => Input::post("user_lastname"),
        ":ADMIN_DATE" => date("Y-m-d H:i:s")
    ));
}

# Update DB Configuration file
$dbconfig = file_get_contents($dbconfig_file_path);
$dbconfig = str_replace(
    array(
        "NP_DB_HOST",
        "NP_DB_NAME",
        "NP_DB_USER",
        "NP_DB_PASS",
        "NP_TABLE_PREFIX",
    ),
    array(
        Input::post("db_host"),
        Input::post("db_name"),
        Input::post("db_username"),
        Input::post("db_password"),
        Input::post("db_table_prefix"),
    ),
    $dbconfig
);
file_put_contents($dbconfig_file_path, $dbconfig);

# Update main configuation file
if (Input::post("upgrade")) {
    $crypto_key = Input::post("crypto_key");
} else {
    $key = Defuse\Crypto\Key::createNewRandomKey();
    $crypto_key = $key->saveToAsciiSafeString();
}

$config = file_get_contents($config_file_path);
$config = str_replace(array("NP_CRYPTO_KEY", "NP_RANDOM_SALT"), 
                      array($crypto_key, generate_token(16)), 
                      $config);
file_put_contents($config_file_path, $config);

# Update index
$index = file_get_contents($index_file_path);
$index = preg_replace('/installation/', 'production', $index, 1);
file_put_contents($index_file_path, $index); 

# Save license key,
# This is super important
# Don't delete or edit this file
# It's a proof that you have a valid license to use the app.
@file_put_contents(ROOTPATH."/app/inc/license", $license_key);
@unlink(__FILE__);
