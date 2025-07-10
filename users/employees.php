<?php
	require_once '../auth.php';
	require_once 'db.php';
	requireAdmin();
	
	//PHP Goes Here!
	$errors=[];
	$successes=[];
	$userId = getCurrentUser()['id'];
	$y = date("d-M-Y");
	
	// Get staff data using PDO with error handling
	try {
		$stmt = $pdo->query("SELECT * FROM staff");
		$cp = $stmt->fetchAll();
	} catch (Exception $e) {
		$cp = [];
		error_log("Database error in employees.php: " . $e->getMessage());
	}
	
	function confirm($servNo,$rank,$sname,$fname,$nrc,$dob,$gender,$marital,$addr,$prvnc,$dist,$attdate,$unit)
	{
		include 'db.php';

		$y = date("d-M-Y");
		$sql = "INSERT INTO staff (svcno,rank, sname, fname, nrc, dob, gender, mstatus, addr, prvnc, dist, attdate, unit) VALUES ('$servNo','$rank', '$sname', '$fname','$nrc', '$dob', '$gender', '$marital', '$addr', '$prvnc', '$dist', '$attdate', '$unit')";

		if ($conn->query($sql) === TRUE) 
		{
		    setMessage("Employee added successfully!");
		    header("Location: employees.php");
		} 
		else 
		{
		    setMessage("Error: " . $conn->error, 'error');
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
		$fname1=$row['fname'];
		$nrc1=$row['nrc'];
		$dob1=$row['dob'];
		$gender1=$row['gender'];
		$marital1=$row['marital'];
		$addr1=$row['addr'];
		$prvnc1=$row['prvnc'];
		$dist1=$row['dist'];
		$attdate1=$row['attdate'];
		$unit1=$row['unit'];

		if($servno1==$servNo)
		{
			return false;
		}
		else if($sname1==$sname)
		{
			return false;
		}
		else if($fname1==$fname)
		{
			return false;
		}
		else if($nrc1==$nrc)
		{
			return false;
		}
		else if($dob1==$dob)
		{
			return false;
		}
		else if($gender1==$gender)
		{
			return false;
		}
		else if($marital1==$marital)
		{
			return false;
		}
		else if($addr1==$addr)
		{
			return false;
		}
		else if($prvnc1==$prvnc)
		{
			return false;
		}
		else if($dist1==$dist)
		{
			return false;
		}
		else if($attdate1==$attdate)
		{
			return false;
		}
		else if($unit1==$unit)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	if(isset($_POST['employees']))
	{
		// Check CSRF token
		if (!isset($_POST['csrf_token']) || !CSRFToken::validate($_POST['csrf_token'])) {
			setMessage('Invalid security token', 'error');
		} else {
			$servNo = test_input($_POST['svcno']);
			$rank = test_input($_POST['rank']);
			$sname = test_input($_POST['sname']);
			$fname = test_input($_POST['fname']);
			$nrc = test_input($_POST['nrc']);
			$dob = test_input($_POST['dob']);
			$gender = test_input($_POST['gender']);
			$marital = test_input($_POST['marital']);
			$addr = test_input($_POST['addr']);
			$prvnc = test_input($_POST['prvnc']);
			$dist = test_input($_POST['dist']);
			$attdate = test_input($_POST['attdate']);
			$unit = test_input($_POST['unit']);

			if(check($servNo,$rank,$sname,$fname,$nrc,$dob,$gender,$marital,$addr,$prvnc,$dist,$attdate,$unit))
			{
				confirm($servNo,$rank,$sname,$fname,$nrc,$dob,$gender,$marital,$addr,$prvnc,$dist,$attdate,$unit);
			}
			else
			{
				$errors[] = "Employee Already Exists";
			}
		}
	}

	function test_input($data) 
	{
		$data = trim($data??'');
	  	$data = stripslashes($data??'');
	  	$data = htmlspecialchars($data??'');
	  	return $data;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Employee Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/armis_custom.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <div class="container-fluid">
                <h1><i class="fas fa-users"></i> Employee Management</h1>
                <p class="mb-0">Add and manage employee records in the system</p>
            </div>
        </div>

        <div class="container-fluid">
            <?php 
                displayMessages(); 
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                        echo htmlspecialchars($error);
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                        echo '</div>';
                    }
                }
            ?>
            
            <div class="dashboard-card">
                <h3><i class="fas fa-user-plus"></i> Add New Employee</h3>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
                    <?php echo CSRFToken::getField(); ?>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="svcno" class="form-label">Service Number</label>
                            <input type="text" class="form-control" id="svcno" name="svcno" placeholder="Enter Service Number" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="rank" class="form-label">Rank</label>
                            <input type="text" class="form-control" id="rank" name="rank" placeholder="Enter Rank" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sname" class="form-label">Surname</label>
                            <input type="text" class="form-control" id="sname" name="sname" placeholder="Enter Surname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="fname" class="form-label">Forename</label>
                            <input type="text" class="form-control" id="fname" name="fname" placeholder="Enter Forename" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nrc" class="form-label">National Registration Number</label>
                            <input type="text" class="form-control" id="nrc" name="nrc" placeholder="Enter NRC Number" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="dob" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="gender" id="male" value="Male" required>
                                <label class="form-check-label" for="male">Male</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="gender" id="female" value="Female" required>
                                <label class="form-check-label" for="female">Female</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="marital" class="form-label">Marital Status</label>
                            <select class="form-select" id="marital" name="marital" required>
                                <option value="">Select Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="addr" class="form-label">Residential Address</label>
                            <input type="text" class="form-control" id="addr" name="addr" placeholder="Enter Address" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prvnc" class="form-label">Province</label>
                            <select class="form-select" id="prvnc" name="prvnc" required>
                                <option value="">Select Province</option>
                                <option value="Central">Central</option>
                                <option value="Copperbelt">Copperbelt</option>
                                <option value="Eastern">Eastern</option>
                                <option value="Luapula">Luapula</option>
                                <option value="Lusaka">Lusaka</option>
                                <option value="Muchinga">Muchinga</option>
                                <option value="North Western">North Western</option>
                                <option value="Northern">Northern</option>
                                <option value="Southern">Southern</option>
                                <option value="Western">Western</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="dist" class="form-label">District</label>
                            <input type="text" class="form-control" id="dist" name="dist" placeholder="Enter District" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="attdate" class="form-label">Attestation Date</label>
                            <input type="date" class="form-control" id="attdate" name="attdate" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="unit" class="form-label">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit" placeholder="Enter Unit" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" name="employees" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Employee
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>