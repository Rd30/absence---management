<?php

require "/var/www/lib/php/PHPMailer/PHPMailerAutoload.php";
include_once "/var/www/lib/php/spg_utils.php";


$db_config_data = parse_ini_file("/var/www/config/it_database.ini");
$app_config_data = parse_ini_file("/var/www/config/absence.ini");



$names_conn = new mysqli($db_config_data["m_host"], $db_config_data["m_user"], $db_config_data["m_password"], $db_config_data["m_database"]);

if ($names_conn->connect_errno) {
  echo "Failed to connect to MySQL: (" . $names_conn->connect_errno . ") " . $names_conn->connect_error;
  exit();
}

$absence_conn = new mysqli($db_config_data["m_host_absence"], $db_config_data["m_user_absence"], $db_config_data["m_password_absence"], $db_config_data["m_database_absence"]);

if ($absence_conn->connect_errno) {
  echo "Failed to connect to MySQL: (" . $names_conn->connect_errno . ") " . $names_conn->connect_error;
  exit();
}

if (isset($_POST['validate'])) {
  // handle employee name validation request
  $first_last_name = explode(" ", $_POST['validate']);

  $get_emp_manager_query = "" .
    "SELECT Supervisor " .
      "FROM names " .
      "WHERE NameFirst = '" . $first_last_name[0] . "' " .
        "AND NameLast = '" . $first_last_name[1] . "'";

  $parsed_result = parseQueryResponse(sendQuery($names_conn, $get_emp_manager_query));

  if (sizeof($parsed_result) > 0 && isset($parsed_result[0])) {
    if ($manager = $parsed_result[0]['Supervisor']) {
      echo $manager;
      quit($names_conn);
    }
  }

  echo "Error: " . $app_config_data['INVALID_NAME'] . "\n";
  quit($names_conn);
}


if (isset($_GET['manage'])) {

  $get_absence_info_query = "" .
    "SELECT * " .
      "FROM requests " .
      "WHERE id = '" . $_GET['manage'] . "';";

  $absence_info_response = parseQueryResponse(sendQuery($absence_conn, $get_absence_info_query));

  echo json_encode($absence_info_response[0]);

  mysqli_close($names_conn);
  quit($absence_conn);
}

if (isset($_POST['approve']) && isset($_POST['user'])) {
  $approve_absence_request_query = "" .
    "UPDATE requests " .
      "SET approved = 'Approved', " .
        "response_date = NOW(), " .
        "response_name = '" .  $_POST['user'] . "' " .
      "WHERE id = '" . $_POST['approve'] . "';";

  sendQuery($absence_conn, $approve_absence_request_query);

  $get_update_query = "" .
    "SELECT * " .
      "FROM requests " .
      "WHERE id = '" . $_POST['approve'] . "';";

  $absence_data = parseQueryResponse(sendQuery($absence_conn, $get_update_query))[0];

  echo json_encode($absence_data);

  $get_user_email_query = "" .
    "SELECT Email " .
      "FROM names " .
      "WHERE User = '" . $absence_data["emp_name"] . "'";

  $user_data = parseQueryResponse(sendQuery($names_conn, $get_user_email_query))[0];

  $subject = sprintf($app_config_data["EMAIL_APPROVED_SUBJECT"]);
  $body = sprintf($app_config_data["EMAIL_APPROVED"], $absence_data["emp_name"], $absence_data["response_name"]);
  $altbody = sprintf($app_config_data["ALT_EMAIL_APPROVED"], $absence_data["emp_name"], $absence_data["response_name"]);

  $absence_info_html = "" .
    "<p>".
    "Absence Info:"."<br>"."<br>".
    "    Start Time: " . $absence_data["date_start"] . " - " . $absence_data["start_time"] . "<br>" .
    "    End Date: " . $absence_data["date_end"] . "<br>" .
    "    Reason: " . $absence_data["reason"] . "<br>" .
    "    Hours: " . $absence_data["hours"] . "<br>" .
    "    Comment: " . $absence_data["comment"] . "<br>".
    "</p>";

  $absence_info_plain = "" .
    "\n\n" .
    "    Start Time: " . $absence_data["date_start"] . " - " . $absence_data["start_time"] . "\n" .
    "    End Date: " . $absence_data["date_end"] . "\n" .
    "    Reason: " . $absence_data["reason"] . "\n" .
    "    Hours: " . $absence_data["hours"] . "\n" .
    "    Comment: " . $absence_data["comment"];

  $body .= $absence_info_html;
  $altbody .= $absence_info_plain;

  //error_log("****** sending approval email!");
  absenceMailer($user_data["Email"], $subject, $body, $altbody, $app_config_data["HR_EMAIL"]);

  quit($absence_conn);
}

if (isset($_POST['deny']) && isset($_POST['user'])) {
  $deny_absence_request_query = "" .
    "UPDATE requests " .
      "SET approved = 'Denied', " .
        "response_date = NOW(), " .
        "response_name = '" . $_POST['user'] . "' " .
      "WHERE id = '" . $_POST['deny'] . "';";

  sendQuery($absence_conn, $deny_absence_request_query);

  $get_update_query = "" .
    "SELECT * " .
      "FROM requests " .
      "WHERE id = '" . $_POST['deny'] . "';";

  $absence_data = parseQueryResponse(sendQuery($absence_conn, $get_update_query))[0];

  echo json_encode($absence_data);

  $get_user_email_query = "" .
    "SELECT Email " .
      "FROM names " .
      "WHERE User = '" . $absence_data["emp_name"] . "'";

  $user_data = parseQueryResponse(sendQuery($names_conn, $get_user_email_query))[0];

  $subject = sprintf($app_config_data["EMAIL_DENIED_SUBJECT"]);
  $body = sprintf($app_config_data["EMAIL_DENIED"], $absence_data["emp_name"],$absence_data["response_name"]);
  $altbody = sprintf($app_config_data["ALT_EMAIL_DENIED"], $absence_data["emp_name"], $absence_data["response_name"]);

  $absence_info_html = "" .
    "<p>".
    "Absence Info:"."<br>"."<br>".
    "    Start Time: " . $absence_data["date_start"] . " - " . $absence_data["start_time"] . "<br>" .
    "    End Date: " . $absence_data["date_end"] . "<br>" .
    "    Reason: " . $absence_data["reason"] . "<br>" .
    "    Hours: " . $absence_data["hours"] . "<br>" .
    "    Comment: " . $absence_data["comment"] . "<br>".
    "</p>";


  $absence_info_plain = "" .
    "\n\n" .
    "    Start Time: " . $absence_data["date_start"] . " - " . $absence_data["start_time"] . "\n" .
    "    End Date: " . $absence_data["date_end"] . "\n" .
    "    Reason: " . $absence_data["reason"] . "\n" .
    "    Hours: " . $absence_data["hours"] . "\n" .
    "    Comment: " . $absence_data["comment"];

  $body .= $absence_info_html;
  $altbody .= $absence_info_plain;

  absenceMailer($user_data["Email"], $subject, $body, $altbody);

  quit($absence_conn);
}

$absenceInfo = array();

if (isset($_POST[$app_config_data['REQUESTED_BY']])) {

  $sql = "" .
    "SELECT * " .
      "FROM names " .
      "WHERE user = '" . $_POST[$app_config_data['REQUESTED_BY']] . "'";

  if ($check_result = sendQuery($names_conn, $sql)) {
    $absenceInfo[$app_config_data['REQUESTED_BY']] = $_POST[$app_config_data['REQUESTED_BY']];
    $parsed_result = parseQueryResponse($check_result);
    $absenceInfo["emp_id"] = $parsed_result[0]["id"];
  }
  else {
    echo "Employee: " . $first_name . " " . $last_name . " is not in the system.\n";
    quit($absence_conn);
  }
}
else {
  echo "Error: " . $app_config_data['INVALID_NAME'] . "\n";
  quit($absence_conn);
}

if (isset($_POST[$app_config_data['ABSENCE_DATE_START']])){
  if (empty($_POST[$app_config_data['ABSENCE_DATE_START']])){
    echo "Error: " . $app_config_data['START_DATE_REQUIRED'] . "\n";
    quit($absence_conn);
  }else{
    $absenceInfo[$app_config_data['ABSENCE_DATE_START']] = $_POST[$app_config_data['ABSENCE_DATE_START']];
  }
}else {
  echo "Error: " . $app_config_data['INVALID_DATES'] . "\n";
  quit($absence_conn);
}

if (isset($_POST[$app_config_data['ABSENCE_TIME_START']])) {
  $absenceInfo[$app_config_data['ABSENCE_TIME_START']] = $_POST[$app_config_data['ABSENCE_TIME_START']];
}

if (isset($_POST[$app_config_data['ABSENCE_DATE_END']])){
  if (empty($_POST[$app_config_data['ABSENCE_DATE_END']])){
    echo "Error: " . $app_config_data['END_DATE_REQUIRED'] . "\n";
    quit($absence_conn);
  }else{
    $absenceInfo[$app_config_data['ABSENCE_DATE_END']] = $_POST[$app_config_data['ABSENCE_DATE_END']];
  }
}else {
  echo "Error: " . $app_config_data['INVALID_DATES'] . "\n";
  quit($absence_conn);
}

if (isset($_POST[$app_config_data['REASON_FOR_ABSENCE']])) {
  $absenceInfo[$app_config_data['REASON_FOR_ABSENCE']] = $_POST[$app_config_data['REASON_FOR_ABSENCE']];
}
else {
  echo "Error: " . $app_config_data['INVALID_REASON'] . "\n";
  quit($absence_conn);
}

if (isset($_POST[$app_config_data['TIME_HOURS']])) {
  if ((isInteger($_POST[$app_config_data['TIME_HOURS']])) && (int)($_POST[$app_config_data['TIME_HOURS']]) > 0) {
    $absenceInfo[$app_config_data['TIME_HOURS']] = $_POST[$app_config_data['TIME_HOURS']];
  }else{
    echo "Error: " . $app_config_data['TOTAL_HOURS'] . "\n";
    quit($absence_conn);
  }
}else {
  echo "Error: " . $app_config_data['INVALID_TIME'] . "\n";
  quit($absence_conn);
}

if (isset($_POST[$app_config_data['COMMENTS']])) {
  $absenceInfo[$app_config_data['COMMENTS']] = $_POST[$app_config_data['COMMENTS']];
}

if (isset($_POST[$app_config_data['MANAGER']])) {
  $absenceInfo[$app_config_data['MANAGER']] = $_POST[$app_config_data['MANAGER']];
}
else {
  echo "Error: " . $app_config_data['INVALID_MANAGER'] . "\n";
  quit($absence_conn);
}

/* check to see if we have everything we need to make an absence request report */
$absence_keys = array_keys($absenceInfo);
for ($i = 0; $i < sizeof($absence_keys); $i++) {
  if (!isset($absenceInfo[$absence_keys[$i]])) {
    echo "Error: " . $app_config_data['GENERIC_ISSUE'] . "\n";
    quit($absence_conn);
  }
}

//$mysqli->autocommit(FALSE);
// Turn off autocommit
mysqli_autocommit($absence_conn, FALSE);

/*Allow special characters in the comments*/
$comments = mysqli_real_escape_string($absence_conn, $absenceInfo[$app_config_data["COMMENTS"]]);

/* If we got here, all is well and we can insert a request to the database */
$insert_absence_request_sql = "" .
  "INSERT INTO requests " .
    "(id, request_date, " .
      $app_config_data["REQUESTED_BY"] . ", " .
      $app_config_data["ABSENCE_DATE_START"] . ", " .
      $app_config_data["ABSENCE_TIME_START"] . ", " .
      $app_config_data["ABSENCE_DATE_END"] . ", " .
      $app_config_data["REASON_FOR_ABSENCE"] . ", " .
      $app_config_data["TIME_HOURS"] . ", " .
      $app_config_data["COMMENTS"] . ", " .
      $app_config_data["MANAGER"] . ", " .
      $app_config_data["ID"] .
    ") " .
    "VALUES(MD5(UUID()), NOW(), '" .
      $absenceInfo[$app_config_data["REQUESTED_BY"]] . "', '" .
      $absenceInfo[$app_config_data["ABSENCE_DATE_START"]] . "', '" .
      $absenceInfo[$app_config_data["ABSENCE_TIME_START"]] . "', '" .
      $absenceInfo[$app_config_data["ABSENCE_DATE_END"]] . "', '" .
      $absenceInfo[$app_config_data["REASON_FOR_ABSENCE"]] . "', '" .
      $absenceInfo[$app_config_data["TIME_HOURS"]] . "', '" .
       $comments. "', '" .
      $absenceInfo[$app_config_data["MANAGER"]] . "', '" .
      $absenceInfo[$app_config_data["ID"]] . "'" .
    ")";

sendQuery($absence_conn, $insert_absence_request_sql);

// get created id
$get_request_id_sql = "" .
  "SELECT id " .
    "FROM requests " .
    "ORDER BY request_date DESC " .
    "LIMIT 1;";

$request_id_response = parseQueryResponse(sendQuery($absence_conn, $get_request_id_sql));
$request_id = $request_id_response[0]["id"];

/* Request is stored in DB... time to send an email to the supervisor */
$super_first_last_name = explode(" ", $absenceInfo[$app_config_data['MANAGER']]);
$manager_email_query = "" .
  "SELECT Email, User " .
    "FROM names " .
    "WHERE NameFirst = '" . $super_first_last_name[0] . "' " .
      "AND NameLast = '" . $super_first_last_name[1] . "'";

$manager_info_result = parseQueryResponse(sendQuery($names_conn, $manager_email_query));
$managerEmail = $manager_info_result[0]["Email"];

$employee_email_query = "" .
  "SELECT Email " .
    "FROM names " .
    "WHERE id = '" . $absenceInfo[$app_config_data["ID"]] . "'";

$employee_email_result = parseQueryResponse(sendQuery($names_conn, $employee_email_query));
$employee_email = $employee_email_result[0]['Email'];

$id = $request_id;
$subject = $app_config_data["EMAIL_REQUEST_SUBJECT"];

// Commit the transaction
mysqli_commit($absence_conn);

// construct email body to send to manager
$body = sprintf(
  "<html><body><p>%s has submitted an absence request.</p><p>Please click <b><a style=\"color:green\" target=\"_blank\" href=\"%s%s\">here</a></b> to respond.</p>" .
    "<p>Absence Info:</p>" .
      "<p>    Start Time: %s - %s<br>" .
         "    End Date: %s<br>" .
         "    Reason: %s<br>" .
         "    Hours: %s<br>" .
      "</p>" .
      "<strong><p style=\"color:red\">If you are offsite only then use the links below to respond : </p></strong>" .
    "<a target=\"_blank\" href=\"http://nd-force.entegris.com/gp-slo_hr/absence/handle-absence-approval.php?id=%s&approval=%s&user=%s&email=%s\">Approve</a><br>" .
    "<a target=\"_blank\" href=\"http://nd-force.entegris.com/gp-slo_hr/absence/handle-absence-approval.php?id=%s&approval=%s&user=%s&email=%s\">Deny</a><br></body></html>",
  $absenceInfo[$app_config_data["REQUESTED_BY"]], $app_config_data["MANAGEMENT_URL"], $id,
  $absenceInfo["date_start"], $absenceInfo["start_time"], $absenceInfo["date_end"], $absenceInfo["reason"], $absenceInfo["hours"],
  $id, "Approved", $manager_info_result[0]["User"], $employee_email,
  $id, "Denied", $manager_info_result[0]["User"], $employee_email
);

// set up alternate email body in case email client does not support html bodies
$altbody = sprintf(
  "%s has submitted an absence request.\n\nGo to %s%s in your browser to respond to it.\n" .
    "\nAbsence Info:\n" .
      "\n    Start Time: %s - %s\n" .
         "    End Date: %s\n" .
         "    Reason: %s\n" .
         "    Hours: %s\n" .
      "\n" .
      "\nIf offsite: use the following links to respond:\n" .
    "Go to this link to Approve: http://nd-force.entegris.com/gp-slo_hr/absence/handle-absence-approval.php?id=%s&approval=%s&user=%s&email=%s\n" .
    "Go to this link to Deny: http://nd-force.entegris.com/gp-slo_hr/absence/handle-absence-approval.php?id=%s&approval=%s&user=%s&email=%s\n",
  $absenceInfo[$app_config_data["REQUESTED_BY"]], $app_config_data["MANAGEMENT_URL"], $id,
  $absenceInfo["date_start"], $absenceInfo["start_time"], $absenceInfo["date_end"], $absenceInfo["reason"], $absenceInfo["hours"],
  $id, "Approved", $manager_info_result[0]["User"], $employee_email,
  $id, "Denied", $manager_info_result[0]["User"], $employee_email
);
absenceMailer($managerEmail, $subject, $body, $altbody); // E-mail to Manager


// construct email body to send an acknowledgement e-mail to the Employee
$body = sprintf(
  "<html><body><p>You have submitted an absence request to your Manager.</p>" .
    "<p>Absence Info:</p>" .
      "<p>    Start Time: %s - %s<br>" .
         "    End Date: %s<br>" .
         "    Reason: %s<br>" .
         "    Hours: %s<br>" .
      "</p>"."</body></html>",
  $absenceInfo["date_start"], $absenceInfo["start_time"], $absenceInfo["date_end"], $absenceInfo["reason"], $absenceInfo["hours"]
);

// set up alternate email body in case email client does not support html bodies -- email body to send an acknowledgement e-mail to the Employee
$altbody = sprintf(
  "You have submitted an absence request to your Manager." .
    "\nAbsence Info:\n" .
      "\n    Start Time: %s - %s\n" .
         "    End Date: %s\n" .
         "    Reason: %s\n" .
         "    Hours: %s\n" .
      "\n",
  $absenceInfo["date_start"], $absenceInfo["start_time"], $absenceInfo["date_end"], $absenceInfo["reason"], $absenceInfo["hours"]
);

absenceMailer($employee_email, $subject, $body, $altbody); // Acknowledgement E-mail to the Employee

echo $id;

//mysqli_commit($absence_conn);
mysqli_close($names_conn);
mysqli_close($absence_conn);

exit(0);


  function absenceMailer($to, $subject, $body, $altbody, $cc=""){

	  $mail = new PHPMailer;
	  $mail->isSMTP();
	  $mail->Host = "relay-west.entegris.com";
	  $mail->SMTPOptions= array(
			'ssl'=> array(
				'verify_peer'=>false,
				'verify_peer_name'=>false,
				'allow_self_signed'=>true
			)
	  );
	  $mail->SMTPSecure = "tls";
	  $mail->Port = 25;
	  $mail->SetFrom("donotreply@entegris.com", "Absence Request");
	  $mail->addAddress($to);
	  $mail->Subject = $subject;
	  $mail->Body = $body;
	  $mail->AltBody = $altbody;

	  if(isset($cc)){
		$mail->addCC($cc);
	  }

	  if(!$mail->send()){
		error_log('Absence Request E-mail Error: ' . $mail->ErrorInfo);
	  }
  }

?>
