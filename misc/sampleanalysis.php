<?php
include_once('../config/symbini.php');
header('Content-Type: text/html; charset=' . $CHARSET);
?>
<html>
	<head>
		<title>Sample Analysis</title>
		<?php
		include_once($SERVER_ROOT.'/includes/head.php');
		?>
	</head>
	<body>
		<!-- This is inner text! -->
		<div id="innertext">
			<h1>Sample Analysis Landing Page</h1>
			<p>
				<a href="<?php echo $CLIENT_ROOT; ?>/api/v2/documentation">API</a>
			</p>
			<p>
				<a href="https://data.neonscience.org/sample-explorer">Sample Explorer</a>
			</p>
		</div>
	</body>
</html>