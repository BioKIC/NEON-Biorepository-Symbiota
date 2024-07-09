<?php
include_once('../config/symbini.php');
header("Content-Type: text/html; charset=".$CHARSET);
?>
<html>
	<head>
		<title>Sample Use Request</title>
		<?php
		$activateJQuery = false;
		include_once($SERVER_ROOT.'/includes/head.php');
		?>
		
	</head>
	<body>
		<div id="biorepo-page"></div>
		<!-- This is inner text! -->
		<div id="innertext" style="text-align: center;">
			<h1>Sample Use Request</h1>
			<iframe src="https://asu.co1.qualtrics.com/jfe/form/SV_bfPgKtTfHTyzffg" width="790" height="1000px" frameborder="0" marginheight="0" marginwidth="0" style="margin-top: 2rem">Loading…</iframe></iframe>
		</div>
	</body>
</html>
