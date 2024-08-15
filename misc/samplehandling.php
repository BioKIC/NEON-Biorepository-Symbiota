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
			<div class="page__default--body">
				<p>The primary NEON Biorepository is curated by Arizona State University and encompasses the long-term storage and curation of most NEON samples and specimens which include voucher specimens, whole organisms, tissues, and samples that are collected and processed for chemistry, disease and genetics. Explore our&nbsp;<a href="/node/6223">general catalog of samples</a>&nbsp;to learn more about the types of samples that we collect and archive.</p>
				<h2 id="discover-neon-samples">Discover&nbsp;NEON Samples</h2>
				<ul>
					<li>Samples and data are collected together. <a href="https://data.neonscience.org/data-products/explore">NEON data products</a> that include sampling have sample Identifiers in their data tables. Within each data product download, an&nbsp;accompanying table that describes the products' variables will help you identify&nbsp;the sample identifier variable names&nbsp;and the data table(s) in which they appear.&nbsp;</li>
					<li>Sample identifiers can be entered into the&nbsp;<a href="http://data.neonscience.org/static/samples.html" target="_blank">NEON Sample Explorer</a>&nbsp;to look up the current location of a particular sample.&nbsp;</li>
				</ul>
				<div class="table-wrapper">
					<table>
						<thead>
							<tr>
								<th scope="col">Physical samples</th>
								<th scope="col">Data Product Name/ID</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>All</td>
								<td>Aquatic plant bryophyte macroalgae clip harvest (DP1.20066.001)</td>
							</tr>
							<tr>
								<td>Partial</td>
								<td>Aquatic plant, bryophyte, lichen, and macroalgae point counts in wadeable streams NEON.DP1.20072</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Benthic microbe community composition (DP1.20086.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Benthic microbe group abundances (DP1.20277.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Benthic microbe marker gene sequences (DP1.20280.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Benthic microbe metagenome sequences (DP1.20279.001)</td>
							</tr>
							<tr>
								<td>None</td>
								<td>Breeding landbird point counts (DP1.10003.001)</td>
							</tr>
							<tr>
								<td>Partial</td>
								<td>Fish electrofishing, gill netting, and fyke netting counts (DP1.20107.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Ground beetles sampled from pitfall traps (DP1.10022.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Macroinvertebrate collection (DP1.20120.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Mosquito-borne pathogen status (DP1.10041.001)</td>
							</tr>
							<tr>
								<td>Partial</td>
								<td>Mosquitoes sampled from CO2 traps (DP1.10043.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Periphyton, seston, and phytoplankton collection (DP1.20166.001)</td>
							</tr>
							<tr>
								<td>None</td>
								<td>Plant phenology observations (DP1.10055.001)</td>
							</tr>
							<tr>
								<td>Vouchers only</td>
								<td>Plant presence and percent cover (DP1.10058.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Rodent-borne pathogen status (DP1.10064.001)</td>
							</tr>
							<tr>
								<td>Partial</td>
								<td>Small mammal box trapping (DP1.10072.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Soil microbe community composition(DP1.10081.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Soil microbe marker gene sequences (DP1.10108.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Soil microbe metagenome sequences (DP1.10107.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Surface water microbe community composition (DP1.20141.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Surface water microbe group abundances (DP1.20278.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Surface water microbe marker gene sequences (DP1.20282.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Surface water microbe metagenome sequences DP1.20281.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Tick-borne pathogen status (DP1.10092.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Ticks sampled using drag cloths (DP1.10093.001)</td>
							</tr>
							<tr>
								<td>Tissue samples only</td>
								<td>Woody plant vegetation structure (DP1.10098.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Zooplankton collection (DP1.20219.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Zooplankton DNA barcode (DP1.20221.001)</td>
							</tr>
							<tr>
								<td>All</td>
								<td>Macroinvertebrate DNA barcode (DP1.20126.001)</td>
							</tr>
						</tbody>
					</table>
				</div>
				<h2 id="samples-hosted-at-other-institutions">Samples Hosted at Other Institutions</h2>
				<p>Some NEON samples have already been archived at other facilities. Requests for these samples should be made directly to the hosting organization. See our <a href="/data-samples/samples/sample-repositories">Sample Repositories</a> page to learn more.</p>
			</div>
		</div>
	</body>
</html>


