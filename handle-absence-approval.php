<?php
error_log("*** check 2 ***");
//require_once "/var/www/lib/PHPMailer/PHPMailerAutoload.php";
//require_once "/var/www/lib/utils.php";
require 'C:\xampp\htdocs\hr\absence\lib-home\php\PHPMailer\PHPMailerAutoload.php';
include_once 'C:\xampp\htdocs\hr\absence\lib-home\php\spg_utils.php';

$db_config_data = parse_ini_file('C:\xampp\htdocs\hr\absence\config\seep_connector.ini');

error_log(json_encode($_GET));

echo "" .
  "<html>\r\n" .
    "<head>\r\n" .
      "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n" .
      "<meta charset=\"utf-8\">\r\n" .
      "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\r\n" .
      "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=Edge\" />\r\n" .
      "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=9\"/>\r\n" .
      "<title>Absence Response</title>" .
    "</head>\r\n" .
    "<body>\r\n";



if (isset($_GET["id"]) && isset($_GET["approval"]) && isset($_GET["user"]) && isset($_GET["email"])) {

  $conn = new mysqli($db_config_data["m_host"], $db_config_data["m_user_absence"], "", $db_config_data["m_database_names"]);
  if ($conn->connect_errno) {
    error_log("Failed to connect to MySQL: (" . json_encode($conn->connect_errno) . ") " . json_encode($conn->connect_error));

    echo "<h3>Error: Could not connect to SPG Intranet MariaDB - nd-seep: " . json_encode($conn->connect_error) . "</h3>\r\n" .
      "</body></html>\r\n";
    exit();
  }

  $sql = "" .
    "SELECT * " .
      "FROM absence.requests " .
      "WHERE id = '" . $_GET["id"] . "'";

  $absence_data = parseQueryResponse(sendQuery($conn, $sql))[0];

  if ($_GET["user"] !== $absence_data["manager"]) {
    echo "<h3>Sorry, You are not authorized to manage this absence created by " . $absence_data["emp_name"] . ". Only his/her direct manager has this access</h3>\r\n";
    echo "</body></html>\r\n";
    exit(0);
  }

  $sql = "" .
    "UPDATE absence.requests " .
      "SET approved = '" . $_GET["approval"] . "', " .
        "response_date = NOW(), " .
        "response_name = '" . $_GET["user"] . "' " .
      "WHERE id = '" . $_GET["id"] . "'";

  sendQuery($conn, $sql);

  echo "<h1>Absence System</h1>\r\n" .
    "<h2>Welcome " . $_GET["user"] . "</h2>\r\n" .
    "<h3>You have " . $_GET["approval"];

  $sql = "" .
    "SELECT * " .
      "FROM absence.requests " .
      "WHERE id = '" . $_GET["id"] . "'";

  $absence_data = parseQueryResponse(sendQuery($conn, $sql))[0];

  echo " " . $absence_data["emp_name"] . "'s absence</h3>\r\n";

  //error_log(json_encode($absence_data));

  mysqli_close($conn);

  // create mail for IT
  $mail = new PHPMailer;
  $mail->isSMTP();  // Set mailer to use SMTP
  $mail->Host = '127.0.0.1'; // Specify main and backup SMTP servers
  //$mail->SMTPSecure = "tls";
  $mail->Port = 465;     // TCP port to connect to
  $mail->SMTPAuth = false;  // Enable SMTP authentication
  //$mail->Username = 'Entegris.relay@entegris.com';
  //$mail->Password = 'pr8Tr8#t';
  $mail->setFrom('Entegris.relay@entegris.com', 'Entegris');
  $mail->addAddress($_GET["email"]);
  $mail->Subject = "Absence Request " . $_GET["approval"];
  $mail->isHTML(true);
  $mail->Body = "" .
    "<p>Dear " . $absence_data["emp_name"] . ",</p>" .
      "<p>Your absence request has been " . $_GET["approval"] . " by " . $_GET["user"] . "</p>" .
      "<p>Absence Info:</p>" .
      " <p>   Start Time: " . $absence_data["date_start"] . " - " . $absence_data["start_time"] . "<br>" .
      "    End Date: " . $absence_data["date_end"] . "<br>" .
      "    Reason: " . $absence_data["reason"] . "<br>" .
      "    Hours: " . $absence_data["hours"]."<br>" .
      "</p>";

      if($_GET["approval"] === "Approved"){
        $mail->addCC('Teg_Butler@saes-group.com');
      }

      if(!$mail->send()) {
          echo 'mail send error :'.$mail->ErrorInfo;
          error_log('Message could not be sent.\n');
          error_log('Mailer Error: ' . $mail->ErrorInfo . "\n");
      } else {
          error_log('Message has been sent now\n');
      }
}

echo "</body></html>\r\n";

?>
