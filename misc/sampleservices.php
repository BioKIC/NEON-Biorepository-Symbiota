<?php
include_once('../config/symbini.php');
header('Content-Type: text/html; charset=' . $CHARSET);
?>
<html>
	<head>
		<title>Sample Services</title>
		<?php
		include_once($SERVER_ROOT.'/includes/head.php');
		?>
	</head>
	<body>
		<!-- This is inner text! -->
		<div id="innertext">
			<h1>Sample Services Landing Page</h1>
			<p>
				Take Types of Sample Requests, Considerations for Approval, and Accessing Samples from /samples/find-samples
			</p>
			<p>
				<a href="<?php echo $CLIENT_ROOT; ?>/misc/samplerequest.php">Sample Request</a>
			</p>
			<p>
				<a href="<?php echo $CLIENT_ROOT; ?>/misc/samplearchiverequest.php">Sample Archival Request</a>
			</p>
		</div>
	</body>
</html>