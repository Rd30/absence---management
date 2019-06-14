<!DOCTYPE html>
<html lang="en-us">
  <head>
  </head>
  <body>Hola
<?php
// get mode
$mode = "user";
$user = $_SERVER["PHP_AUTH_USER"];

if (isset($_GET["for"]) && $_GET["for"] === "hr") {
  $mode = "hr";
}
else {
  if (isset($_GET["user"])) {
    $user = $_GET["user"];
  }
}
// $user = "nobpat";
// get SQL information
//require_once "/var/www/lib/php/spg_utils.php";
include_once 'C:\xampp\htdocs\hr\absence\lib-home\php\spg_utils.php';
//$db_config_data = parse_ini_file("/var/www/config/it_database.ini");
$db_config_data = parse_ini_file('C:\xampp\htdocs\hr\absence\config\it_database.ini');

// connect to MariaDB SQL database for IT names and such
$conn = new mysqli($db_config_data["m_host"], $db_config_data["m_user"], $db_config_data["m_password"], $db_config_data["m_database"]);
if ($conn->connect_errno) {
  error_log("Failed to connect to MySQL: (" . json_encode($conn->connect_errno) . ") " . json_encode($conn->connect_error));
  exit();
}


// build query
$user_sql = "" .
  "SELECT id, NameFirst, NameLast, User, username, Supervisor, IsSupervisor " .
    "FROM names " .
    "WHERE username = '" . $user . "'";
$userdata = parseQueryResponse(sendQuery($conn, $user_sql))[0];

// is the user a manager or a normal user? (exceptions?)
$mode = "regular";
if ($userdata["IsSupervisor"] === "Yes") {
  $mode = "manager";
}

//error_log($userdata["username"] . " is a $mode user");

mysqli_close($conn);

// connect to MariaDB SQL database for absence requests
$conn = new mysqli($db_config_data["m_host"], $db_config_data["m_user"], $db_config_data["m_password"], $db_config_data["m_database_absence"]);
if ($conn->connect_errno) {
  error_log("Failed to connect to MySQL: (" . $conn->connect_errno . ") " . $conn->connect_error);
  exit();
}

$absence_sql = "" .
  "SELECT * " .
    "FROM requests " .
    "WHERE approved = 'Approved' ";

if ($mode !== "manager") {
  $absence_sql .= "" .
      "AND emp_id = '" . $userdata["id"] . "'";
}

$absences = parseQueryResponse(sendQuery($conn, $absence_sql));

// the iCal date format. Note the Z on the end indicates a UTC timestamp.
define('DATE_ICAL', 'Ymd\THis');

// max line length is 75 chars. New line is \\n

$output = "BEGIN:VCALENDAR\n" .
  "METHOD:PUBLISH\n" .
  "VERSION:2.0\n" .
  "PRODID:-//Entegris//Absence Calendar//EN\n" .
  "X-WR-CALNAME:" . $userdata["User"] . " Absence Calendar\n" .
  "X-WR-CALDESC:A single user absence calendar\n" .
  "X-PUBLISHED-TTL:PT1M\n";

// loop over eventss
foreach ($absences as $absence) {
  // validate that the absence has been approved
  // might need to parse the date time a bit

  $date_start = $absence["date_start"];
  $date_end = $absence["date_end"];

  $output .= "" .
    "BEGIN:VEVENT\n" .
    "SUMMARY:" . $absence["hours"] . " hour absence: " . $absence["emp_name"] . "\n" .
    "UID:" . $absence["id"] . "\n" .
    "ORGANIZER;CN=spg_admin@saes-group.com\n" .
    "STATUS:CONFIRMED\n" .
    "DTSTAMP:" . date(DATE_ICAL, time()) . "Z\n" .
    "DTSTART;TZID=America/Los_Angeles:" . date(DATE_ICAL, strtotime($date_start)) . "\n" .
    "DTEND;TZID=America/Los_Angeles:" . date(DATE_ICAL, strtotime($date_end)) . "\n" .
    "DESCRIPTION:";

  $output .= "" .
    "Requested By: " . $absence["emp_name"] . "\\n\\n" .
    "Reason: " . $absence["reason"] . "\\n" .
    "Total Hours: " . $absence["hours"] . "\\n" .
    "Comment: " . $absence["comment"] . "\\n" .
    "Manager Comment: " . "coming soon" . "\\n" .
    "\n";

  $output .= "END:VEVENT\n";

  //}
}

// close calendar
$output .= "END:VCALENDAR";

echo $output;

?>
</body>
</html>
