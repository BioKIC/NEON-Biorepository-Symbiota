<?php
include_once('../config/symbini.php');
header('Content-Type: text/html; charset=' . $CHARSET);
?>
<html>
	<head>
		<title>About Samples</title>
		<?php
		include_once($SERVER_ROOT.'/includes/head.php');
		?>
	</head>
	<body>
		<!-- This is inner text! -->
		<div id="innertext">
			<h1>About Samples</h1>
			</br>
			<img src="<?php echo $CLIENT_ROOT . '/images/card-images/2021_04_Photo_Biorepository-ASU-handling-samples-jpg_0.jpg'; ?>" style="position: relative; right: 75px;" alt="ASU biorepository" loading="lazy">
			<p>NEON has been collecting specimens as part of its sampling program since 2012. Certain samples are earmarked for immediate curation at an archival institution, while others are sent to analytical facilities for chemical, taxonomic or genetic analysis. Where possible, the downstream byproducts of these analyses are archived in lieu of the original sample (e.g., when ear tissue from small mammal collections are sent for genetic analysis, any surplus genomic extracts are preserved at an archive facility). In 2018, NEON began construction of the NEON Biorepository – a facility intended to archive most specimens curated by the NEON program - at the Arizona State University Biocollections in Tempe, AZ. The NEON Biorepository publishes occurrence records for every archived sample and specimen, as well as additional value-added sample-associated data not present in NEON data products in the sample portal and makes these samples available for loan.</p>
			<h2>Sample Processing</h2>
			<p>Providing high-quality, richly contextualized samples is a key component of NEON’s mission and allows for greater understanding of complex ecological processes at local, regional and continental scales. Each <a href="https://www.neonscience.org/data-collection/protocols-standardized-methods">protocol</a> describes field collection of samples. Some samples are immediately curated for archive at the NEON Biorepository and are not subject to direct analysis by NEON staff. Other samples are collected as a target for a particular analysis (e.g., chemical composition, taxonomic analysis, genetic analysis). Where possible, NEON strives to archive the downstream products of these analyses (e.g., the NEON Biorepository archives any remaining genomic DNA after small mammal ear tissue is processed for <a href="https://data.neonscience.org/data-products/DP1.10076.001">DNA sequence analysis</a>). Researchers interested in intermediate sample products or desiring additional sample collection can submit a NEON Research Support Services <a href="https://www.neonscience.org/resources/research-support">request</a>.</p>
			<h2>Sample Identification and Labeling</h2>
			<p>Every sample is identified in the NEON database with a unique primary identifier. All samples are designated a <b>Sample Tag (sampleID)</b> and/or <b>Barcode (sampleCode)</b> upon collection and a <b>Catalog Number (archiveGuid)</b> upon physical accession at the Biorepository. Each sample is also designated a <b>Sample Class (sampleClass)</b>, describing the type of sample and stored in a Collection within the Biorepository, which roughly equates to sample classes. <a href="https://data.neonscience.org/data-products/explore">NEON data products</a> that include sampling will often record sampleIDs in their data tables. Within each data product download, an accompanying table that describes the products' variables will help you identify the sampleID variable names and the data table(s) in which they appear. All samples in the NEON Biorepository can be searched by sampleID, catalog number or barcode through the <a href="https://biorepo.neonscience.org/portal/neon/search/index.php">Sample Search</a> page.</p>
			<p>A <b>Sample Tag (SampleID)</b> is the identifier for the sample and contains as much information as is required to differentiate specimens within the same sample class; these identifiers may contain information about the sample encoded in the label, including the site or plot of collection, the date or time of collection, the taxon or sex (as applicable), or a unique number (e.g., tag number on a small mammal). As the observatory has moved into operations, written labels have been supplemented and/or replaced by barcodes.</p>
			<p>Upon arrival and check in at the NEON Biorepository, they are assigned a <b>Catalog Number (archiveGUID)</b>. The Catalog Number is shorter, globally unique across all samples, and easier to manage in large collections. It ensures consistency, as sampleIDs can vary in structure and length based on sample type or collection details. Using a uniform catalog number streamlines sample tracking and referencing across systems, simplifying specimen management and ensuring accurate records as samples are accessed or loaned for research.</p>
			<p><b>Sample Classes</b> roughly equate to types of samples, such that carabid specimens mounted on pins (sample class: bet_IDandpinning_in.individualID) have a different class than mounted mosquito specimens (mos_identification_in.individualIDList). The first part of the sample class name identifies the data table that is input into NEON's database, e.g., bet = beetle, IDandpinning = identification and pinning activity, in = ingest. The second part of the sample class name identifies the specific field in the table that holds identifiers. New sample classes are often assigned as an originating field-collected sample goes through various processing steps, so not all sample classes correspond to samples archived at the Biorepository.</p>
			<button class="MuiButtonBase-root MuiButton-root MuiButton-outlined MuiButton-outlinedPrimary" tabindex="0" type="button" data-selenium="download-sample-classes-button">
				<span class="MuiButton-label" style="font-weight:600">Download current list of supported sample classes
					<svg class="MuiSvgIcon-root MuiSvgIcon-fontSizeSmall" focusable="false" viewBox="0 0 24 24" aria-hidden="true" style="margin-left: 8px;">
						<path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z"></path>
					</svg>
				</span>
				<span class="MuiTouchRipple-root"></span>
			</button>
			<p><b>Barcode (sampleCode)</b> labels were first used on NEON samples in 2017 on a select number of NEON specimens and expanded to most NEON sample types in subsequent years. NEON uses cryo-compatible barcode labels for specimens stored in -20 to -196 ˚C and non-cryo barcode labels for the remaining samples. The non-cryo labels are robust to exposure in solvents (particularly ethanol and water) and are optimized to hold up to field conditions. In the NEON Processed Data Repository (PDR), barcodes cannot be duplicated – as in, two separate/different samples cannot be affiliated with the same barcode. This enables NEON to leverage barcode tracking for duplicate checking in the database.</p>
			<h2>Sample Organization</h2>
			<div id="biorepo-aboutsamples-content"></div>
			<h2>Sample Explorer</h2>
			<p>NEON collects and archives up to 120,000 samples per year at the NEON Biorepository. Additional samples are collected for destructive analysis at the NEON Domain Support Facilities or external laboratories. To track samples moving between facilities, NEON generates shipment manifests and standardized receipt forms that enable NEON to track the location of a sample through its lifetime from generation in the field to final disposition. Because of the use of barcodes and autogenerated shipment manifests, NEON has greatly reduced the opportunity for transcription errors associated with labels and sample transfer. A sample's custody and location history, as well as relationships to other samples (parent, child, and siblings) can be explored using the <a href="https://data.neonscience.org/sample-explorer">Sample Explorer</a>. At the Biorepository, samples are curated following best museum practices to ensure findability within the collections.</p>
			<img src="<?php echo $CLIENT_ROOT . '/images/card-images/2020_10_SampleViewer_Table_Screenshot_0.jpg'; ?>" style="max-width:100%; max-height:100%; box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.4);" alt="Sample Explorer" loading="lazy">
			<figcaption style="background: #F5F6F7; font-size: 20px;">Sample search results for NEON.MAM.D01.L0253; this sample appears in the trapping data repeatedly reflecting that it was captured on multiple occasions.</figcaption>
			<p>The NEON Sample Explorer also allows users to explore the relationship between a sample from the field and all downstream subsamples and mixtures. For example, a female Peromyscus leucopus (NEON.MAM.D01.L0253) was captured at the HARV site in 2013 on multiple dates. Using this tool, a user can see all subsamples from this mammal – in this case 3 fecal samples, 2 blood samples, 2 hair samples, and 1 ear tissue collection.</p>
			<img src="<?php echo $CLIENT_ROOT . '/images/card-images/2020_10_SampleViewer_Graph_Screenshot_0.jpg'; ?>" style="max-width:100%; max-height:100%; box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.4);" alt="Sample Explorer" loading="lazy">
			<figcaption style="background: #F5F6F7; font-size: 20px;">Sample graph showing 8 subsamples (ear, hair, blood, feces) collected from small mammal NEON.MAM.D01.L0253.</figcaption>
			
		</div>
	</body>
</html>


