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

$statsArr = json_encode($stats->getBlueNeonStats());
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

<script>
// image resizings
	document.addEventListener('DOMContentLoaded', function() {
		function updateElementWidth() {	
			// blue div
			var neonPageContent = document.querySelector('div[data-selenium="neon-page.content"]');
			var neonPageContentWidth = neonPageContent.offsetWidth;

			var muiContainer = document.querySelector('div.MuiContainer-root');
			var muiContainerStyle = window.getComputedStyle(muiContainer);
			var muiContainerRightMargin = parseFloat(muiContainerStyle.marginRight);

			var neonPageContentStyle = window.getComputedStyle(neonPageContent);
			var neonPageContentpaddingLeft = parseFloat(neonPageContentStyle.paddingLeft);
			
			document.getElementById('blue-div').style.width = (neonPageContentWidth + muiContainerRightMargin) + 'px';
			document.getElementById('statistics-container').style.width = (neonPageContentWidth - (2* neonPageContentpaddingLeft)) + 'px'; 
		}
	
		// Update the width on initial load
		updateElementWidth();
	
		// Update the width on window resize
		window.addEventListener('resize', updateElementWidth);
	});
</script>

<script type="module">
	// countup animation
	import { CountUp } from './neon/js/countUp.min.js';
	
	window.onload = function() {
		var data = <?php echo $statsArr; ?>;
	
		// Array of target elements
		var targets = [
		  { id: 'speciesCount', value: data.noSpecies, suffix: ' species' },
		  { id: 'recordCount', value: data.noRecords, suffix: ' records' },
		  { id: 'imageCount', value: data.noImages, suffix: ' images' },
		  { id: 'yearCount', value: data.noYears, suffix: ' years' },
		  { id: 'sampleTypeCount', value: data.noSampleTypes, suffix: ' sample types' },
		  { id: 'siteCount', value: data.noSites, suffix: ' sites' },
		];
	
		// Iterate over the targets array and initialize CountUp for each element
		targets.forEach(function(target) {
		  var countUp = new CountUp(target.id, target.value, { enableScrollSpy: true, suffix: target.suffix, duration: 3 });
		  countUp.start();
		});
	};
</script>

<body class="home-page">
	<div id="biorepo-page"></div>
	<!-- This is inner text! -->
	<div id="innertext">
		<div id="biorepo-home-page-content"></div>
	</div>
</body>
</html>
