<!DOCTYPE html>
<html lang="en_us">
  <head>
    <?php include("http://nd-wind.entegris.com/gp-slo/common/gp-sloHead.html"); ?>	
  </head>
  <body>
    <?php
      require_once "/var/www/lib/php/spg_utils.php";
      $db_config_data = parse_ini_file("/var/www/config/it_database.ini");
      $user = getSessionUser();
    ?>
	
    <!-- Dark overlay element -->
    <div class="overlay" id="overlay"></div>

    <!--NavBar/Header-->
    <div class="all-gp-sloHeader" id="absenceReqHeader"><?php include("http://nd-wind.entegris.com/gp-slo/common/gp-sloHeader.html"); ?></div>

    <!--Sidebar-->
    <?php include("http://nd-wind.entegris.com/gp-slo/common/gp-sloSidebar.html"); ?>

    <div class="container gp-slo-container" id="absenceMainContainer">

      <div class="" id="absenceContent">

        <div class="container" id="absenceFormContainer">
          <div id="msgBox" class="abs-req-msgBox"></div><!--msg box to display success or error msgs-->

          <form class="absence-req-form col-md-6" name="absenceForm" id="absenceForm" style="padding-bottom:2%;">
            <div class="form-group">
              <label for="emp_name_input">Employee Name</label>
              <input type="text" name="emp_name" class="form-control" id="emp_name_input">
            </div>

            <div class="form-group">
              <label for="manager_input">Manager Name</label>
              <input required type="text" name="manager" class="form-control" id="manager_input">
            </div>

            <div class="form-group row">
              <div class="col-md-8">
                <label for="input_incident_date_start">Start Date</label>
                <div class="input-group date">
                  <input type="text" id="date_input_start" name="date_start" class="form-control date" value="<?php echo isset($_POST['date_start']) ? $_POST['date_start'] : '' ?>" <?php echo isset($_POST["date_start"]) ? "disabled" : "";?> />
                </div>
              </div>

              <div class="col-md-4">
                <label for="start_time_input">Start Time</label>
                <input required type="time" value="08:00" class="form-control" name="start_time" id="start_time_input"></input>
              </div>
            </div>

            <div class="form-group">
              <label for="input_incident_date_end">End Date</label>
              <div class="input-group date">
                <input required="" type="text" name="date_end" id="date_input_end" class="form-control">
              </div>
            </div>

            <div class="form-group ">
              <label for="hours_input">Total Hours</label>
              <input required type="number" min="0" name="hours" class="form-control xs-field" id="hours_input"></input>
            </div>

            <div class="form-group" id="absenceReason">
              <label for="absence_reason">Reason for Absence</label>
              <div class="">
                <div class="radio">
                  <label>
                    <input required type="radio" name="reason" id="ptoRadio" value="pto/openleave">
                    PTO/Open Leave
                  </label>
                </div>
                <div class="radio">
                  <label>
                    <input  type="radio" name="reason" id="sickRadio" value="sick">
                    Sick
                  </label>
                </div>
                <div class="radio">
                  <label>
                    <input  type="radio" name="reason" id="juryRadio" value="jury">
                    Jury Duty
                  </label>
                </div>
                <div class="radio">
                  <label>
                    <input  type="radio" name="reason" id="bereavementRadio" value="bereavement">
                    Bereavement
                  </label>
                </div>
                <div class="radio">
                  <label>
                    <input  type="radio" name="reason" id="nopayRadio" value="nopay">
                    Personal / No Pay
                  </label>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="comment_input">Comment</label>
              <textarea class="form-control" id="comment_input" rows="3" placeholder="Additional description about the absence..."></textarea>
            </div>

            <?php
              if (isset($_GET['manage'])){
                $conn = new mysqli($db_config_data["m_host_absence"], $db_config_data["m_user_absence"], $db_config_data["m_password_absence"], $db_config_data["m_database_absence"]);
                if ($conn->connect_errno) {
                  echo "Failed to connect to MySQL: (" . $conn->connect_errno . ") " . $conn->connect_error;
                  exit();
                }

                $sql = "" .
                  "SELECT * " .
                    "FROM requests " .
                    "WHERE id = '" . $_GET['manage'] . "'";
                $absence_info = parseQueryResponse(sendQuery($conn, $sql));

                if ($absence_info[0]["manager"] === $user["User"]) {
            ?>

                <div class="form-group">
                  <div id="manager_response" class="alert" role="alert" hidden></div>
                </div>

                <div class="">
                  <button type="button" id="approveButton" class="btn btn-primary abs-req-approve" style="width:49%">Approve</button>
                  <button type="button" id="denyButton" class="btn btn-primary abs-req-deny" style="width:49%">Deny</button>
                </div>
            <?php
              }
            }
            else if (!isset($_GET['view'])) {
            ?>
              <div class="col-md-12">
                <button type="submit" id="submitButton" class="btn btn-primary abs-req-submit" style="width:100%;">Submit</button>
              </div>
            <?php
            }
            ?>
          </form>
        </div>
      </div>
    </div>    
    <script type="text/javascript">

        var userdata;
        var absenceInfo = {
          emp_name: null,
          date_start: null,
          date_end: null,
          start_time: null,
          reason: null,
          hours: null,
          comment: null,
          manager: null,
          approved: "Pending"
        };

        $(document).ready(function () {
          $('#pageTitleDiv').html("");
          $('#pageTitleDiv').html("<h5>Absence Request</h5>");
          $('#shortPageTitleDiv').html("");
          $('#shortPageTitleDiv').html("<h5>Absnc Req.</h5>");

          // set default dates
          var start = new Date();
          // set end date to max one year period:
          var end = new Date(new Date().setYear(start.getFullYear()+1));

          $('#startDateAddOn').click(function(event){
            $('#date_input_start').data('datepicker').show();
          });

          //startDateGroup, date_input_start, startDateAddOn
          $('#date_input_start').datepicker({
              format : "mm/dd/yyyy",
              startDate: '-1m',
              endDate: '+6m',
              orientation: "bottom left",
              autoclose: true,
            // update "toDate" defaults whenever "fromDate" changes
          }).on('changeDate', function(){
              // set the "toDate" start to not be later than "fromDate" ends:
              $('#date_input_end').datepicker('setStartDate', new Date($(this).val()));
          });


          $('#date_input_end').datepicker({
              format : "mm/dd/yyyy",
              startDate : start,
              endDate   : end,
              orientation: "bottom left",
              autoclose: true,
            // update "fromDate" defaults whenever "toDate" changes
          }).on('changeDate', function(){
              // set the "fromDate" end to not be later than "toDate" starts:
              $('#date_input_start').datepicker('setEndDate', new Date($(this).val()));
          });

          $('#endDateAddOn').click(function(event){
            $('#date_input_end').data('datepicker').show();
            //$('#date_input_end').data('datepicker').
          });

          /* figure out what state the page should be in */
          var url_param_manage = getUrlParameter("manage");
          var url_param_view = getUrlParameter("view");

          $("#manager_select").hide();

          userdata = <?php echo json_encode($user); ?>;

          if (url_param_manage) {
            $("#page_title").text("Manage Absence Request");
            console.log("managing " + url_param_manage);
            absenceInfo['request_id'] = url_param_manage;

            var inputs = document.getElementsByTagName("input");
            for (var i = 0; i < inputs.length; i++) {
              if (inputs[i].id != "manager_comment_input") {
                inputs[i].disabled = true;
              }
            }

            $("#approveButton").click(function(e) {
              if (absenceInfo.approved !== "Approved") {
                $.ajax({
                  url: "absence.php",
                  method: "POST",
                  data: {
                    approve: url_param_manage,
                    user: userdata.NameFirst + " " + userdata.NameLast
                  },
                  success: function(result) {
                    $("#msgBox").addClass('alert alert-info text-center').css('font-weight','bold');
                    $("#msgBox").text('Absence requested approved');
                    //loadAbsenceData(absenceInfo['request_id']);
                    absenceInfo.approved = "Approved";
                    console.log(result);
                    var info = JSON.parse(result);
                    managerInput(info);

                  }
                });
              }
            });

            $("#denyButton").click(function(e) {
              if (absenceInfo.approved !== "Denied") {
                $.ajax({
                  url: "absence.php",
                  data: {
                    deny: url_param_manage,
                    user: userdata.NameFirst + " " + userdata.NameLast
                  },
                  method: "POST",
                  success: function(result) {
                    $("#msgBox").addClass('alert alert-info text-center').css('font-weight','bold');
                    $("#msgBox").text('Absence requested denied');
                    //loadAbsenceData(absenceInfo['request_id']);
                    absenceInfo.approved = "Denied";
                    console.log(result);
                    var info = JSON.parse(result);
                    managerInput(info);
                  }
                });
              }
            });

            loadAbsenceData(absenceInfo['request_id']);
          }else if (url_param_view) {
            $("#page_title").text("View Absence Request");
            absenceInfo['request_id'] = url_param_view;
            var inputs = document.getElementsByTagName("input");

            for (var i = 0; i < inputs.length; i++) {
              inputs[i].disabled = true;
            }

            loadAbsenceData(absenceInfo['request_id']);
          }else {
            $("#emp_name_input").val(userdata.NameFirst + " " + userdata.NameLast);
            $("#emp_name_input").prop("disabled", true);
            $("#manager_input").val(userdata.Supervisor);
            $("#manager_input").prop("disabled", true);
          }

          $("#submitButton").on("click", function(e) {
            e.preventDefault();
            absenceInfo.emp_name = $("#emp_name_input").val();
            absenceInfo.date_start = $("#date_input_start").val();
            absenceInfo.start_time = $("#start_time_input").val();
            absenceInfo.date_end = $("#date_input_end").val();
            absenceInfo.reason = getRadioState();
            absenceInfo.hours = $("#hours_input").val();
            absenceInfo.comment = $("#comment_input").val();
            absenceInfo.manager = $("#manager_input").val();
            sendAbsenceRequest();
          });

          $("#validate_emp_btn").click(function(e) {
            if ($("#emp_name_input").val() !== "") {
              empValidationRequest($("#emp_name_input").val());
            }
          });
        });

        function sendAbsenceRequest() {
          $.ajax({
            url: "absence.php",
            data: absenceInfo,
            method: "POST",
            success: function(result){
              if (result.indexOf("Error") != -1) {
                $("#msgBox").addClass('alert alert-danger text-center').css('font-weight','bold');
                $("#msgBox").text(result);
              }
              else {
                $("#msgBox").addClass('alert alert-success text-center').css('font-weight','bold');
                $("#msgBox").text('Absence successfully requested');
                $("#date_input_start").prop('disabled', true);
                $("#start_time_input").prop('disabled', true);
                $("#date_input_end").prop('disabled', true);
                $("input[name=reason]:checked").prop('disabled', true);
                $("#hours_input").prop('disabled', true);
                $("#comment_input").prop('disabled', true);
                $("#submitButton").prop('hidden', true);
              }
            },
            failure: function(result) {
              console.log("failed to send the absence request");
              console.log(result);
            }
          });
        }

        function loadAbsenceData(id) {
          $.ajax({
            url: "absence.php",
            data: {
              manage: id
            },
            method: "GET",
            success: function(result){
              if (result.indexOf("Error") != -1){
                $("#msgBox").addClass('alert alert-danger text-center').css('font-weight','bold');
                $("#msgBox").text(result);
              }else{
                var info = JSON.parse(result);
                console.log("checking manager");
                console.log(info);
                console.log("userdata = "+userdata);
                if (info && (info.manager === userdata.User || info.emp_name === userdata.User)){
                  absenceInfo = info;
                  absenceInfo.emp_name = $("#emp_name_input").val(info.emp_name);
                  absenceInfo.date_start = $("#date_input_start").val(info.date_start);
                  absenceInfo.start_time = $("#start_time_input").val(info.start_time);
                  absenceInfo.date_end = $("#date_input_end").val(info.date_end);
                  absenceInfo.hours = $("#hours_input").val(info.hours);
                  absenceInfo.comment = $("#comment_input").val(info.comment);
                  absenceInfo.manager = $("#manager_input").val(info.manager);

                  checkRadio(info.reason);
                  managerInput(info);
                }else {
                  // not user's manager. disable buttons
                  $("#approveButton").hide();
                  $("#denyButton").hide();
                  $("#msgBox").addClass('alert alert-danger text-center').css('font-weight','bold');
                  $("#msgBox").text("This absence can only be managed by the user's supervisor");
                }
              }
            },
            failure: function(result) {
              console.log("failed to load the absence data");
              console.log(result);
            }
          });
        }

        function managerInput(info) {
          $("#manager_response").removeClass("alert-warning").removeClass("alert-success").removeClass("alert-danger");
          console.log(info.approved);
          if (info.approved === "Pending") {
            $("#manager_response").text("Request pending manager input").addClass("alert-warning");
          }
          else if (info.approved === "Approved") {
            $("#manager_response").text("Request approved by " + info.response_name + " at " + info.response_date).addClass("alert-success");
          }
          else if (info.approved === "Denied") {
            $("#manager_response").text("Request denied by " + info.response_name + " at " + info.response_date).addClass("alert-danger");
          }
          $("#manager_response").show();
        }

        function getRadioState(){
          return $('input[name=reason]:checked').val();
        }

        function checkRadio(radioValue) {
          var inputs = document.getElementsByTagName("input");
          for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].type == "radio" && inputs[i].value == radioValue) {
              inputs[i].checked = true;
            }
            else if (inputs[i].type == "radio") {
              inputs[i].checked = false;
            }
          }
        }
    </script>
    <script type="text/javascript" src="http://nd-wind.entegris.com/gp-slo/gp-slo.js"></script>	
	<script src="../../lib/spg_utils.js"></script>
  </body>
</html>
