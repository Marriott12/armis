<?php
	require_once '../auth.php';
	require_once 'db.php';
	requireAdmin();
	
	//PHP Goes Here!
	$errors=[];
	$successes=[];
	$userId = getCurrentUser()['id'];
	$y = date("d-M-Y");
	
	// Get staff data using PDO
	$stmt = $pdo->query("SELECT * FROM staff");
	$cp = $stmt->fetchAll();
	
	function confirm($servNo,$rank,$sname,$fname,$nrc,$dob,$gender,$marital,$addr,$prvnc,$dist,$attdate,$unit)
	{
		include 'db.php';

		$y = date("d-M-Y");
		$sql = "INSERT INTO staff (svcno,rank, sname, fname, nrc, dob, gender, mstatus, addr, prvnc, dist, attdate, unit) VALUES ('$servNo','$rank', '$sname', '$fname','$nrc', '$dob', '$gender', '$marital', '$addr', '$prvnc', '$dist', '$attdate', '$unit')";

		if ($conn->query($sql) === TRUE) 
		{
		    echo "Recorded Added Successfully";
		    header("Location: employees.php");
		} 
		else 
		{
		    echo "Error: " . $sql . "<br>" . $conn->error;
		}

		$conn->close();

	}
	function check($servNo,$rank,$sname,$fname,$nrc,$dob,$gender,$marital,$addr,$prvnc,$dist,$attdate,$unit)
	{
		include 'db.php';
		$sql = "SELECT * FROM staff";
		$result=$conn->query($sql);
		$row=$result->fetch_assoc();

		$servno1=$row['svcno'];
		$sname1=$row['sname'];

		if($servno1 == $servNo && $sname1 == $sname)
		{
			echo "Employee already exists";
		}
		else
		{
			confirm($servNo,$rank,$sname,$fname,$nrc,$dob,$gender,$marital,$addr,$prvnc,$dist,$attdate,$unit);
		}
	}
	if ($_SERVER["REQUEST_METHOD"] == "POST")
	{
		$servNo = test_input($_POST["svcno"]);
		$rank = test_input($_POST["rank"]);
		$sname = test_input($_POST["sname"]);
		$fname = test_input($_POST["fname"]);
		$nrc = test_input($_POST["nrc"]);
        $dob = test_input($_POST["dob"]);
		$gender = test_input($_POST["gender"]);
		$marital = test_input($_POST["marital"]);
		$addr = test_input($_POST["addr"]);
		$prvnc = test_input($_POST["prvnc"]);
		$dist = test_input($_POST["dist"]);
		$attdate = test_input($_POST["attdate"]);
		$unit = test_input($_POST["unit"]);

		
		check($servNo,$rank,$sname,$fname,$nrc,$dob,$gender,$marital,$addr,$prvnc,$dist,$attdate,$unit);
        

		/* Check if $uploadOk is set to 0 by an error
		if ($uploadOk == 0) 
		{
			echo "Sorry, employee record could not be added";
		// if everything is ok, try to upload file
		} 
		else 
		{
			if (move_uploaded_file($_FILES["fimage"]["tmp_name"], $target_file)) 
			{
		    	$fimage = $target_file;
		    	//basename( $_FILES["fimage"]["name"]);
			} 
			else 
			{
			    echo "Sorry, there was an error adding the employee.";
			}
		}*/
	}
	function test_input($data) 
	{
		$data = trim($data??'');
	  	$data = stripslashes($data??'');
	  	$data = htmlspecialchars($data??'');
	  	return $data;
	}
?>

<div id="page-wrapper">
	<div class="container">
  <div class="row">
			<div class="col-sm-12">
				<br/>
				<h3 align="center">Employee Records</h3>
				
		


		      	
			  <div class="row">
			  <div class="col-md-12">
			  <h4>Add New Employee</h4>
			    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" enctype="multipart/form-data">
				<div class="form-row">
				<div class="input-group col-md-4 mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Service Number</span>
					</div>
					<input type="text" class="form-control mb-2 mr-sm-2" aria-describedby="ip-label" name="svcno" placeholder="Enter Service Number" required>
				</div>
				<div class="input-group col-md-4 mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Rank</span>
					</div>
					<input type="text" class="form-control" aria-describedby="ip-label" name="rank" placeholder="Enter Rank" required>
				</div>
				<div class="input-group col-md-4 mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Surname</span>
					</div>
					<input type="text" class="form-control" aria-describedby="ip-label" name="sname" placeholder="Enter Surname" required>
				</div>
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Forname</span>
					</div>
					<input type="text" class="form-control" aria-describedby="ip-label" name="fname" placeholder="Enter Forename" required>
				</div>
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">National Registration Number</span>
					</div>
					<input type="text" class="form-control" aria-describedby="ip-label" name="nrc" placeholder="Enter NRC Number" required>
				</div>
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Date of Birth</span>
					</div>
					<input type="date" class="form-control" aria-describedby="ip-label" name="dob" placeholder="Enter Date of Birth" required>
				</div>
				<div class="custom-control custom-radio custom-control-inline">
					<input type="radio" id="gender1" name="gender" class="custom-control-input">
					<label class="custom-control-label" for="gender1" value="Male">Male</label>
					<input type="radio" id="gender2" name="gender" class="custom-control-input">
					<label class="custom-control-label" for="gender2" value="Female">Female</label>
					
				</div>
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Marital Status</span>
					</div>
					<select type="text" class="form-control" aria-describedby="ip-label" name="marital"required>
						<option value="Single">Single</option>
						<option value="Married">Married</option>
						<option value="Divorced">Divorced</option>
						<option value="Widowed">Widowed</option>
					</select>
				</div>
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Resdential Address</span>
					</div>
					<input type="text" class="form-control" aria-describedby="ip-label" name="addr" placeholder="Enter Address" required>
				</div>
				
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Province</span>
					</div>
					<select type="text" class="form-control" aria-describedby="ip-label" name="prvnc"required>
						<option value="Central">Central</option>
						<option value="Copperbelt">Copperbelt</option>
						<option value="Eastern">Eastern</option>
						<option value="Luapula">Luapula</option>
						<option value="Lusaka">Lusaka</option>
						<option value="Muchinga">Muchinga</option>
						<option value="North Western">North Western</option>
						<option value="Northen">Northern</option>
						<option value="Southern">Southern</option>
						<option value="Westen">Western</option>
					</select>
				</div>
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">District</span>
					</div>
					<input type="text" class="form-control" aria-describedby="ip-label" name="dist" placeholder="Enter District" required>
				</div>
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Attestation Date</span>
					</div>
					<input type="date" class="form-control" aria-describedby="ip-label" name="attdate" placeholder="Enter Date of Attestation" required>
				</div>
				<div class="input-group col-md-4 mb-4">
					<div class="input-group-prepend">
						<span class="input-group-text" id="ip-label">Unit</span>
					</div>
					<input type="text" class="form-control" aria-describedby="ip-label" name="unit" placeholder="Enter Unit" required>
				</div>
				  <input type="submit" name="employees" value="Submit" class="btn btn-danger form-control col-md-1  mb-3">
				  </div>
			      </form>
				  <hr />
				  </div>
			    </div>


			</div>
		</div>
	</div>
</div>

  <script type="text/javascript" src="js/pagination/datatables.min.js"></script>
  <script type="text/javascript" src="js/pagination/buttons.print.min.js"></script>
  <script type="text/javascript" src="js/pagination/dataTables.fixedHeader.min.js"></script>
  <script type="text/javascript" src="js/pagination/dataTables.keyTable.min.js"></script>
  <script type="text/javascript" src="js/pagination/buttons.flash.min.js"></script>
  <script type="text/javascript" src="js/pagination/buttons.html5.min.js"></script>
  <script type="text/javascript" src="js/pagination/buttons.bootstrap.min.js"></script>
  <script type="text/javascript" src="js/pagination/dataTables.buttons.min.js"></script>

<script>
	$(document).ready( function () 
	{
		$('.lisTed').DataTable({
			scrollY:        480,
			scrollCollapse: true,
			paging:         true,
		});
	} );
	$('a[data-toggle="tab"]').on( 'shown.bs.tab', function (e) 
	{
		$.fn.dataTable.tables( {visible: true, api: true} ).columns.adjust();
	});
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
