<?php
//error_reporting(E_ALL);
//ini_set('display_errors', '1');
include_once('config/symbini.php');
include_once('content/lang/index.' . $LANG_TAG . '.php');
include_once($SERVER_ROOT . '/neon/classes/PortalStatistics.php');
header('Cache-Control: no-cache');
header('Pragma: no-cache');
header("Content-Type: text/html; charset=" . $CHARSET);

$stats = new PortalStatistics();
$totalSamples = $stats->getTotalNeonSamples();
$totalTaxa = $stats->getTotalNeonTaxa();
$sampleArr = $stats->getNeonSamplesByTax();
$taxaArr = $stats->getNeonTaxa();
?>
<html>

<head>
	<title><?php echo $DEFAULT_TITLE; ?> Home</title>
	<meta http-equiv="Expires" content="Tue, 01 Jan 1995 12:12:12 GMT">
	<meta http-equiv="Pragma" content="no-cache">
	<!-- UNIVERSAL CSS –––––––––––––––––––––––––––––––––––––––––––––––––– -->
	<link rel="stylesheet" href="css/normalize.css">
	<link rel="stylesheet" href="css/skeleton.css">
	<?php
	$activateJQuery = true;
	include_once($SERVER_ROOT . '/includes/head.php');
	include_once($SERVER_ROOT . '/includes/googleanalytics.php');
	?>
	<script type="text/javascript" src="<?php echo $CLIENT_ROOT . '/neon/js/d3.min.js'; ?>"></script>
</head>

<body class="home-page">
	<style>
		.bar:hover {
			opacity: 0.5;
		}

		.bar-label {
			font-size: 7px !important;
			color: #0073cf !important;
		}

		.bar-label a {
			color: #0073cf !important;
			text-decoration: underline;
		}
	</style>
	<div id="biorepo-page"></div>
	<!-- This is inner text! -->
	<div id="innertext" class="container" style="margin-top: 2rem">
		<section style="margin-bottom: 3em;">
			<div style="border-bottom-width:2px;border-color:#F0AB00;border-left-width:20px;border-right-width:2px;border-style:solid;border-top-width:2px;padding:10px;">
				<p>A new <a href="https://www.nsf.gov/pubs/2024/nsf24069/nsf24069.jsp?WT.mc_ev=click&amp;WT.mc_id=&amp;utm_medium=email&amp;utm_source=govdelivery">NSF DCL presents an opportunity to leverage the NEON Biorepository collections</a>. Please <a href="mailto:biorepo@asu.edu">contact us</a> with any questions or for information needed for potential innovative use! We are here to support you.</p>
			</div>
		</section>
		<div id="biorepo-home-page-content"></div>
	</div>
</body>
</html>
