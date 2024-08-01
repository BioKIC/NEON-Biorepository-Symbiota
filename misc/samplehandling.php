<?php
include_once('../config/symbini.php');
header('Content-Type: text/html; charset=' . $CHARSET);
?>
<html>
	<head>
		<title>Sample Handling</title>
		<?php
		include_once($SERVER_ROOT.'/includes/head.php');
		?>
	</head>
	<body>
		<!-- This is inner text! -->
		<div id="innertext">
			<h1>Sample Handling Landing Page</h1>
			<p>
				Add Discover NEON Samples and Samples Hosted at Other Institutions from /samples/find-samples
			</p>
			<p>
				<a href="<?php echo $CLIENT_ROOT; ?>/collections/misc/collprofiles.php">Sample Collection Profiles</a>
			</p>
			<p>
				<a href="https://www.neonscience.org/samples/sample-quality">Sample Quality</a>
			</p>
			<p>
				<a href="https://www.neonscience.org/samples/sample-processing">Sample Processing</a>
			</p>
			<p>
				<a href="https://www.neonscience.org/samples/sample-repositories">Sample Repositories</a>
			</p>
			<p>
				<a href="https://www.neonscience.org/samples/sample-types">Sample Types</a>
			</p>
			<p>
				<a href="https://www.neonscience.org/samples/soil-archive">Soil Archives</a>
			</p>
		</div>
	</body>
</html>