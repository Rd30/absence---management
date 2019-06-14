<!DOCTYPE html>
<html lang="en-us">
  <head></head>
  <body><h1>Hello</h1>
<?php

$ical_location = 'C:\xampp\htdocs\hr\absence\icals';
define('DATE_ICAL', 'Ymd\THis');

include_once 'C:\xampp\htdocs\hr\absence\lib-home\php\spg_utils.php';

$db_config_data = parse_ini_file('C:\xampp\htdocs\hr\absence\config\it_database.ini');

// set up mysql connections
$conn = new mysqli($db_config_data["m_host_absence"], $db_config_data["m_user_absence"], $db_config_data["m_password_absence"], $db_config_data["m_database_absence"]);
if ($conn->connect_errno) {
  error_log("Failed to connect to MySQL: (" . json_encode($conn->connect_errno) . ") " . json_encode($conn->connect_error));
  exit();
}

// *** STEP 1 ***
// get users to create calendars for
$sql = "" .
  "SELECT * " .
    "FROM it.names " .
    "WHERE NOT Supervisor = ''";

$cal_users = parseQueryResponse(sendQuery($conn, $sql));

$hr_users = array();
foreach ($cal_users as $user) {
  $hr_users[] = $user["id"];
}

$hr_user = array(
  "id" => "hr12345",
  "username" => "hr",
  "User" => "Human Resources",
  "Email" => "spg.hr@saes-group.com",
  "userlist" => $hr_users
);

$cal_users[] = $hr_user;

// *** STEP 2 ***
// step through the list of calendar users
foreach ($cal_users as $cal_user) {

  // create array of users that will be included in the calendar
  $user_list = array();

  // by default this user is included.

  // *** STEP 3 ***
  // check to see if user is a manager
    // get the users being managed by this user

  if ($cal_user["id"] === "hr12345") {
    $user_list = $cal_user["userlist"];
  }
  else {
    array_push($user_list, $cal_user["id"]);
    $user_list = getManagees($cal_user, $user_list);
  }

  // debug output
  file_put_contents($ical_location . $cal_user["username"] . ".txt", json_encode($user_list));

  // *** STEP 4 ***
  // create the absence calendar for this user
  $output = "" .
    "BEGIN:VCALENDAR\r\n" .
    "VERSION:2.0\r\n" .
    "CALSCALE:GREGORIAN\r\n" .
    "METHOD:PUBLISH\r\n" .
    "PRODID:-//Entegris//" . $cal_user["username"] . " Absence Calendar//EN\r\n" .
    "X-WR-CALNAME:" . $cal_user["User"] . " Absence Calendar\r\n" .
    "X-WR-CALDESC:Calendar containing " . $cal_user["username"] . "'s absences\r\n" .
    " in addition to managees if applicable\r\n" .
    "X-PUBLISHED-TTL:PT10M\r\n" .
    "X-WR-TIMEZON:America/Los_angeles\r\n" .
    "BEGIN:VTIMEZONE\r\n" .
    "TZID:America/Los_Angeles\r\n" .
    "X-LIC-LOCATION:America/Los_Angeles\r\n" .
    "BEGIN:DAYLIGHT\r\n" .
    "TZOFFSETFROM:-0800\r\n" .
    "TZOFFSETTO:-0700\r\n" .
    "TZNAME:PDT\r\n" .
    "DTSTART:19700308T020000\r\n" .
    "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU\r\n" .
    "END:DAYLIGHT\r\n" .
    "BEGIN:STANDARD\r\n" .
    "TZOFFSETFROM:-0700\r\n" .
    "TZOFFSETTO:-0800\r\n" .
    "TZNAME:PST\r\n" .
    "DTSTART:19701101T020000\r\n" .
    "RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU\r\n" .
    "END:STANDARD\r\n" .
    "END:VTIMEZONE\r\n";

  // go through events for each user in list
  foreach ($user_list as $user) {
    // get *approved* absences for user
    $sql = "" .
      "SELECT * " .
        "FROM absence.requests " .
        "WHERE emp_id = '" . $user . "' " .
          "AND approved = 'Approved'";

    $absences = parseQueryResponse(sendQuery($conn, $sql));

    //echo json_encode($absences) . "\n";
    //echo $sql . "\n";

    // go through each absence request and add an event to the calendar
    foreach ($absences as $absence) {

      $date_start = $absence["date_start"];
      $start_time = $absence["start_time"];
      $date_end = new DateTime($absence["date_end"]);
      $request_date = $absence["request_date"];



      $output .= "" .
        "BEGIN:VEVENT\r\n" .
          "SUMMARY:" . $absence["hours"] . " hour absence: " . $absence["emp_name"] . "\r\n" .
          "UID:" . $absence["id"] . "@saes-group.com\r\n" .
          "ORGANIZER;CN=Entegris.relay@entegris.com:mailto:Entegris.relay@entegris.com\r\n" .
          "STATUS:CONFIRMED\r\n" .
          "DTSTAMP:" . date(DATE_ICAL, strtotime($request_date)) . "\r\n" .
          "DTSTART:" . date(DATE_ICAL, strtotime("$date_start $start_time")) . "\r\n" .
          //"DTEND:" . date(DATE_ICAL, date_time_set(DateTime(strtotime($date_end)),17,0)) . "\r\n" .
          "DTEND:" . date(DATE_ICAL, date_timestamp_get(date_time_set($date_end, 17, 0))) . "\r\n" .
          "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;CN=\r\n" .
            " " . $absence["emp_name"] . ";X-NUM-GUESTS=0:mailto:" . $cal_user["Email"] . "\r\n" .
          "CREATED:" . date(DATE_ICAL, time()) . "\r\n" .
          "LAST-MODIFIED:" . date(DATE_ICAL, time()) . "\r\n" .
          "DESCRIPTION:" .
            "Requested By: " . $absence["emp_name"] . "\\n\\n\r\n" .
            " Reason: " . $absence["reason"] . "\\n\r\n" .
            " Total Hours: " . $absence["hours"] . "\\n\r\n" .
            " Comment: " . $absence["comment"] . "\\n\r\n" .
            " Manager Comment: " . "coming soon" . "\\n\\n\r\n" .
            " Approved By: " . $absence["response_name"] . " at " . $absence["response_date"] . "\\n\r\n" .
        "END:VEVENT\r\n";
    }
  }

  $output .= "END:VCALENDAR";

  // *** STEP 5 ***
  // write calendar to user's calendar file
  file_put_contents($ical_location . $cal_user["username"] . ".ics", $output);

}

// recursive function to find manager/managee hierarchy
function getManagees($manager, $user_list) {

  global $conn;

  $sql = "" .
    "SELECT * " .
      "FROM it.names " .
      "WHERE Supervisor = '" . $manager["NameFirst"] . " " . $manager["NameLast"] . "'";
  $managees = parseQueryResponse(sendQuery($conn, $sql));

  // which of these users are managers?
  foreach ($managees as $managee) {
    if (!in_array($managee["id"], $user_list)) {
      array_push($user_list, $managee["id"]);

      if ($managee["IsSupervisor"] == "Yes") {
        // recurse
        $user_list = getManagees($managee, $user_list);
      }
    }
  }
  return $user_list;
}
?>
</body>
</html>
