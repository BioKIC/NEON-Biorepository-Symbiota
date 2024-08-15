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
			<article>
				<h1>Sample Analysis</h1>
					<a href="https://data.neonscience.org/data-api/">Modified from NEON Data API</a>
				<h2><strong>NEON Biorepository Sample API</strong></h2>
				<p>The NEON Biorepository Sample API (Application Programming Interface) can be used to quickly access data as well as information about our <s>data products, samples, and sampling locations</s>. This API provides a simple means of constructing URLs or cURL statements that return information in a common machine-readable format, <a href="https://json.org/json-en.html">JSON (JavaScript Object Notation)</a>. </p> <p>The API provides numerous endpoints, some of which provide the option to enter values for specific parameters that allow you to refine your search. To learn more about each endpoint and to try each endpoint out, open the <a href="<?php echo $CLIENT_ROOT; ?>/api/v2/documentation">REST API Explorer</a>.
				</p>
				<p>Add something about the regular NEON API</p>
				<h2><strong>Sample Explorer</strong></h2>
				<p>Sample identifiers can be entered into the&nbsp;<a href="https://data.neonscience.org/sample-explorer">NEON Sample Explorer</a>&nbsp;to look up the current history or related data for a particular sample.&nbsp;</p>
			</article>
		</div>
	</body>
</html>