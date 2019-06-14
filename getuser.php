<?php

//include_once "/var/www/lib/php/html/html_utils.php";
//include_once "/var/www/lib/php/db/progress_connector.php";
include_once 'C:\xampp\htdocs\hr\absence\lib-home\php\html\html_utils.php';
include_once 'C:\xampp\htdocs\hr\absence\lib-home\php\db\progress_connector.php';

//$qad_config_data = parse_ini_file("/var/www/config/qad_connect.ini");
//$db_config_data = parse_ini_file("/var/www/config/it_database.ini");
$qad_config_data = parse_ini_file('C:\xampp\htdocs\hr\absence\config\qad_connect.ini');
$db_config_data = parse_ini_file('C:\xampp\htdocs\hr\absence\config\it_database.ini');

// setup Progress QAD environment variables
$dsn = "Progress";
putenv("ODBCINI=" . $qad_config_data["ODBCINI"]);
putenv("ODBCINST=" . $qad_config_data["ODBCINST"]);
putenv("LD_LIBRARY_PATH=" . $qad_config_data["LD_LIBRARY_PATH"]);

$emp_id = "";
if (isset($_GET["emp_id"])) {
  $emp_id = $_GET["emp_id"];
}
else {
  var_dump(http_response_code(412));
  echo "no employee id given";
  exit();
}

// attempt to open a connection with QAD
if ($conn_id = odbc_connect("Progress", "mfg", "", SQL_CUR_USE_ODBC)) {
  $qad_sql = "" .
    "SELECT emp_fname, emp_lname " .
      "FROM PUB.emp_mstr " .
      "WHERE emp_addr = '" . $emp_id . "'";

  if ($qad_response = odbc_exec($conn_id, $qad_sql)) {
    $qad_userdata = parseProgressResult($qad_response)[0];

    //var_dump(http_response_code(200));
    echo $qad_userdata["emp_fname"] . " " . $qad_userdata["emp_lname"];
  }

  odbc_close($conn_id);
}
else {
  error_log("cannot execute '$sql' ");
  error_log(odbc_errormsg());

  var_dump(http_response_code(500));
  echo "QAD Connection Issue";
}

?>
