<?php
include_once('../config/symbini.php');
header('Content-Type: text/html; charset=' . $CHARSET);
?>
<html>
	<head>
		<title>Sample Guidelines & Policies</title>
		<?php
		include_once($SERVER_ROOT.'/includes/head.php');
		?>
	</head>
	<body>
		<!-- This is inner text! -->
		<div id="innertext">
			<h1>Landing Page for Links to Guidelines & Policies</h1>
			<p>
				<a href="<?php echo $CLIENT_ROOT; ?>/misc/cite.php">Acknowledging and Citing</a>
			</p>
			<p>
				<a href="<?php echo $CLIENT_ROOT; ?>/misc/samplepolicy.php">Sample Use Policy</a>
			</p>
			<p>
				<a href="<?php echo $CLIENT_ROOT; ?>/misc/datasetpublishing.php">Dataset Publishing</a>
			</p>
		</div>
	</body>
</html>