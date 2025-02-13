<?php
include_once($SERVER_ROOT.'/classes/OccurrenceCollectionProfile.php');
include_once($SERVER_ROOT.'/classes/TaxonomyUtilities.php');
include_once($SERVER_ROOT.'/classes/TaxonomyHarvester.php');
include_once($SERVER_ROOT.'/classes/UuidFactory.php');
include_once($SERVER_ROOT.'/config/symbini.php');

class OccurrenceHarvester{

	private $conn;
	private $activeCollid = 0;
	private $collectionArr = array();
	private $fateLocationArr;
	private $taxonCodeArr = array();
	private $taxonArr = array();
	private $stateArr = array();
	private $personnelArr = array();
	private $timezone = 'America/Denver';
	private $sampleClassArr = array();
	private $domainSiteArr = array();
	private $replaceFieldValues = false;
	private $neonApiBaseUrl;
	private $neonApiKey;
	private $errorStr;
	private $errorLogArr = array();

	public function __construct(){
		$this->conn = MySQLiConnectionFactory::getCon('write');
		$this->neonApiBaseUrl = 'https://data.neonscience.org/api/v0';
		if(isset($GLOBALS['NEON_API_KEY'])) $this->neonApiKey = $GLOBALS['NEON_API_KEY'];
	}

	public function __destruct(){
		if($this->conn) $this->conn->close();
	}

	//Occurrence harvesting functions
	public function getHarvestReport($shipmentPK){
		$retArr = array();
		$sql = 'SELECT s.errorMessage AS errMsg, COUNT(s.samplePK) as sampleCnt, COUNT(o.occid) as occurrenceCnt '.
			'FROM NeonSample s LEFT JOIN omoccurrences o ON s.occid = o.occid '.
			'WHERE s.checkinuid IS NOT NULL AND s.sampleReceived = 1';
		if($shipmentPK) $sql .= 'AND s.shipmentPK = '.$shipmentPK;
		$sql .= ' GROUP BY errMsg';
		$rs= $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$errMsg = $r->errMsg;
			if(!$errMsg) $errMsg = 'null';
			$retArr[$errMsg]['s-cnt'] = $r->sampleCnt;
			$retArr[$errMsg]['o-cnt'] = $r->occurrenceCnt;
		}
		$rs->free();
		return $retArr;
	}

	public function batchHarvestOccid($postArr){
		//Set variables
		$status = false;
		if(isset($postArr['replaceFieldValues']) && $postArr['replaceFieldValues']) $this->setReplaceFieldValues(true);
		$sqlWhere = '';
		$sqlPrefix = '';
		if(isset($postArr['scbox'])){
			$sqlWhere = 'AND s.samplePK IN('.implode(',',$postArr['scbox']).')';
		}
		elseif($postArr['action'] == 'harvestOccurrences'){
			if(isset($postArr['nullOccurrencesOnly'])){
				$sqlWhere .= 'AND (s.occid IS NULL) ';
			}
			if($postArr['collid']){
				$sqlWhere .= 'AND (o.collid = '.$postArr['collid'].') ';
			}
			if($postArr['errorStr'] == 'nullError'){
				$sqlWhere .= 'AND (s.errorMessage IS NULL) ';
			}
			elseif($postArr['errorStr']){
				$sqlWhere .= 'AND (s.errorMessage = "'.$this->cleanInStr($postArr['errorStr']).'") ';
			}
			if($postArr['sessionid']){
				$sqlWhere .= 'AND (s.sessionID = "'.$this->cleanInStr($postArr['sessionid']).'") ';
			}
			if($postArr['harvestDate']){
				$sqlWhere .= 'AND (s.harvestTimestamp IS NULL OR s.harvestTimestamp < "'.$postArr['harvestDate'].'") ';
			}
			$sqlPrefix = 'ORDER BY s.shipmentPK ';
			if(isset($postArr['limit']) && is_numeric($postArr['limit'])) $sqlPrefix .= 'LIMIT '.$postArr['limit'];
			else $sqlPrefix .= 'LIMIT 1000 ';
		}
		if($sqlWhere){
			$sqlWhere = 'WHERE s.checkinuid IS NOT NULL AND s.acceptedForAnalysis = 1 '.$sqlWhere;
			$status = $this->batchHarvestOccurrences($sqlWhere.$sqlPrefix);
		}
		return $status;
	}

	private function batchHarvestOccurrences($sqlWhere){
		set_time_limit(3600);
		if($sqlWhere){
			$this->setStateArr();
			$this->setDomainSiteArr();
			//if(!$this->setSampleClassArr()) echo '<li>'.$this->errorStr.'</li>';
			echo '<li>Target record count: '.number_format($this->getTargetCount($sqlWhere)).'</li>';
			$collArr = array();
			$cnt = 1;
			$shipmentPK = '';
			$sql = 'SELECT s.samplePK, s.shipmentPK, s.sampleID, s.hashedSampleID, s.alternativeSampleID, s.sampleUuid, s.sampleCode, s.sampleClass, s.taxonID, '.
				's.individualCount, s.filterVolume, s.namedLocation, s.collectDate, s.symbiotaTarget, s.igsnPushedToNEON, s.occid '.
				'FROM NeonSample s LEFT JOIN omoccurrences o ON s.occid = o.occid '.
				$sqlWhere;
			$rs = $this->conn->query($sql);
			while($r = $rs->fetch_object()){
				$this->errorStr = '';
				if($shipmentPK != $r->shipmentPK){
					$shipmentPK = $r->shipmentPK;
					echo '<li><b>Processing shipment #'.$shipmentPK.'</b></li>';
				}
				echo '<li style="margin-left:15px">'.$cnt.': '.($r->occid?($this->replaceFieldValues?'Rebuilding':'Appending'):'Harvesting').' '.($r->sampleID?$r->sampleID:$r->sampleCode).' ('.date('Y-m-d H:i:s').')... </li>';
				$sampleArr = array();
				$sampleArr['samplePK'] = $r->samplePK;
				$sampleArr['sampleID'] = strtoupper($r->sampleID ?? '');
				$sampleArr['hashedSampleID'] = $r->hashedSampleID ?? '';
				$sampleArr['alternativeSampleID'] = strtoupper($r->alternativeSampleID ?? '');
				$sampleArr['sampleUuid'] = $r->sampleUuid ?? '';
				$sampleArr['sampleCode'] = $r->sampleCode ?? '';
				$sampleArr['sampleClass'] = $r->sampleClass ?? '';
				$sampleArr['taxonID'] = $r->taxonID ?? '';
				//$sampleArr['individualCount'] = $r->individualCount ?? '';
				$sampleArr['filterVolume'] = $r->filterVolume ?? '';
				$sampleArr['namedLocation'] = $r->namedLocation ?? '';
				$sampleArr['collectDate'] = $r->collectDate ?? '';
				$sampleArr['symbiotaTarget'] = $r->symbiotaTarget ?? '';
				$sampleArr['igsnPushedToNEON'] = $r->igsnPushedToNEON ?? 0;
				$sampleArr['occid'] = $r->occid ?? '';
				if($r->occid){
					if($occurrenceID = $this->getCurrentOccurrenceID($r->occid)){
						$sampleArr['occurrenceID'] = $occurrenceID;
					}
				}
				if($this->harvestNeonApi($sampleArr)){
					if($dwcArr = $this->getDarwinCoreArr($sampleArr)){
						$this->subSampleIdentifications($dwcArr, $r->occid);
						if($occid = $this->loadOccurrenceRecord($dwcArr, $r->occid, $r->samplePK)){
							if(!in_array($dwcArr['collid'],$collArr)) $collArr[] = $dwcArr['collid'];
							echo '<li style="margin-left:30px">Record successfully harvested: <a href="'.$GLOBALS['CLIENT_ROOT'].'/collections/individual/index.php?occid='.$occid.'" target="_blank">'.$occid.'</a></li>';
						}
						if($this->errorStr) echo '<li style="margin-left:30px">WARNING: '.$this->errorStr.'</li>';
					}
					else{
						echo '<li style="margin-left:30px">'.$this->errorStr.'</li>';
					}
				}
				else{
					echo '<li style="margin-left:30px">ABORT: '.trim($this->errorStr, ';, ').'</li>';
				}
				$cnt++;
				flush();
				ob_flush();
			}
			$rs->free();
			if($shipmentPK){
				$this->adjustTaxonomy();
				//Set recordID GUIDs
				echo '<li>Setting recordID UUIDs for all occurrence records...</li>';
				$uuidManager = new UuidFactory();
				$uuidManager->setSilent(1);
				$uuidManager->populateGuids();
				//Update stats for each collection affected
				if($collArr){
					echo '<li>Update stats and associations for each collection...</li>';
					if(in_array(7, $collArr)) {$collArr[] = 108;}
					if(in_array(8, $collArr)) {$collArr[] = 109;}
					if(in_array(9, $collArr)) { $collArr[] = 107;}
					if(in_array(22, $collArr)) {$collArr[] = 100;}
					if(in_array(45, $collArr)) {$collArr[] = 104;}
					//if(in_array(47, $collArr)) {$collArr[] = 110;}
					//if(in_array(49, $collArr)) {$collArr[] = 111;}
					if(in_array(50, $collArr)) {$collArr[] = 105;}
					if(in_array(52, $collArr)) {$collArr[] = 101;}
					if(in_array(53, $collArr)) {$collArr[] = 102;}
					if(in_array(57, $collArr)) {$collArr[] = 103;}
					if(in_array(73, $collArr)) {$collArr[] = 106;}
					$collManager = new OccurrenceCollectionProfile();
					foreach($collArr as $collID){
						echo '<li style="margin-left:15px">Stat update for collection <a href="'.$GLOBALS['CLIENT_ROOT'].'/collections/misc/collprofiles.php?collid='.$collID.'" target="_blank">#'.$collID.'</a>...</li>';
						$collManager->setCollid($collID);
						$collManager->updateStatistics(false);
						flush();
						ob_flush();
					}
				}
			}
			else echo '<li><b>No records processed. Note that records have to be checked in before occurrences can be harvested.</b></li>';
			//Log any notices, warnings, and errors
			if($this->errorLogArr){
				$logPath = $GLOBALS['SERVER_ROOT'].'/neon/content/logs/occurHarvest_error_'.date('Y-m-d').'.log';
				$logFH = fopen($logPath, 'a');
				fwrite($logFH,'Harvesting event: '.date('Y-m-d H:i:s')."\n");
				fwrite($logFH,'-------------------------'."\n");
				foreach($this->errorLogArr as $errStr){
					fwrite($logFH,$errStr."\n");
				}
				fwrite($logFH,"\n\n");
				fclose($logFH);
			}
		}
		return false;
	}

	private function getTargetCount($sqlWhere){
		$retCnt = 0;
		$sql = 'SELECT COUNT(s.samplePK) AS cnt FROM NeonSample s LEFT JOIN omoccurrences o ON s.occid = o.occid '.$sqlWhere;
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$retCnt = $r->cnt;
		}
		$rs->free();
		return $retCnt;
	}

	private function harvestNeonApi(&$sampleArr){
		//returning true/false changes the 'success' of the sample
		$this->setSampleErrorMessage($sampleArr['samplePK'], '');
		$sampleViewArr = array();

		// Define an array of URL configurations based on sample identifiers
		$urlConfigs = [
			['key' => 	'sampleCode', 	'param' => 	'barcode'],
			['key' => 	'sampleUuid', 	'param' => 	'sampleUuid'],
			['key' => 	'occurrenceID', 'param' => 	'archiveGuid'],
			['key' => 	'sampleID', 	'param' => 	'sampleTag',
			 'key2' => 	'sampleClass', 	'param2' => 'sampleClass']
		];

		$anyKeyExists = array_reduce($urlConfigs, function ($carry, $config) use ($sampleArr) {
			return $carry || isset($sampleArr[$config['key']]);
		}, false);

		if (!$anyKeyExists) {
			$this->errorStr = 'Sample identifiers incomplete';
			$this->setSampleErrorMessage($sampleArr['samplePK'], $this->errorStr);
			return false;
		}

		foreach ($urlConfigs as $config) {
			$url = '';
			if (!empty($sampleArr[$config['key']])) $url = $this->buildApiurl($config, $sampleArr);

			if (!empty($url)){
				//echo 'url: ' . $url . '<br/>';
				$sampleViewArr = $this->checkApiforData($url, $sampleArr);
				if ($sampleViewArr) {
					return $this->checkApiDataforErrors($sampleArr, $sampleViewArr);
				}
			}
		}
		return false;
	}

	private function buildApiurl($config, $sampleArr){
		$url = $this->neonApiBaseUrl . '/samples/view?' . $config['param'] . '=' . urlencode($sampleArr[$config['key']]);

		if ($config['key'] == 'sampleID'){
			if (!empty($sampleArr[$config['key2']])){
				$url .= '&' . $config['param2'] . '=' . urlencode($sampleArr['sampleClass']);
			} else {
				$this->errorStr = 'Searching by sampleID, but no sampleClass given';
				$this->setSampleErrorMessage($sampleArr['samplePK'], $this->errorStr);
				return;
			}
		}

		$url .= '&apiToken=' . $this->neonApiKey;
		return $url;
	}


	private function checkApiforData($url, $sampleArr){
		$this->errorLogArr = [];
		$this->errorStr = '';
		$sampleViewArr = $this->getNeonApiArr($url);

		if(!isset($sampleViewArr['sampleViews'])){
			$this->errorStr = 'NEON API failed to return sample data';
			//$this->errorStr .= ': (<a href="'.$url.'" target="_blank">'.$url.'</a>)';
			$this->updateSampleRecord(array('errorMessage'=>$this->errorStr),$sampleArr['samplePK']);
			return;
		}
		return $sampleViewArr;
	}

	private function checkApiDataforErrors(&$sampleArr, &$sampleViewArr){
		//true = successful, false = error
		$this->errorLogArr = [];
		$this->errorStr = '';

		if(count($sampleViewArr['sampleViews']) > 1){
			$this->errorStr = 'Harvest skipped: NEON API returned multiple sampleViews ';
			$this->updateSampleRecord(array('errorMessage'=>$this->errorStr),$sampleArr['samplePK']);
			return false;
		}
		$viewArr = current($sampleViewArr['sampleViews']);
		if(!$this->checkIdentifiers($viewArr, $sampleArr)) return false;
		//Get fateLocation and process parent samples
		unset($this->fateLocationArr);
		$this->fateLocationArr = array();
		$this->processViewArr($sampleArr, $sampleViewArr);
		if($this->fateLocationArr){
			ksort($this->fateLocationArr);
			$locArr = current($this->fateLocationArr);
			$sampleArr['fate_location'] = $locArr['loc'];
			if(!isset($sampleArr['collect_end_date'])) $sampleArr['collect_end_date'] = $locArr['date'];
		}
		return true;
	}

	private function checkIdentifiers($viewArr, &$sampleArr){
		$status = true;
		$neonSampleUpdate = array();
		if(isset($viewArr['sampleUuid']) && $viewArr['sampleUuid']){
			//Populate or verify/coordinate sampleUuid
			if(!$sampleArr['sampleUuid']){
				$sampleArr['sampleUuid'] = $viewArr['sampleUuid'];
				$neonSampleUpdate['sampleUuid'] = $viewArr['sampleUuid'];
			}
			elseif($sampleArr['sampleUuid'] != $viewArr['sampleUuid']){
				$this->errorLogArr[] = 'NOTICE: sampleUuid updated from '.$sampleArr['sampleUuid'].' to '.$viewArr['sampleUuid'];
				$this->errorStr .= '; DATA ISSUE: sampleUuid failing to match (old: '.$sampleArr['sampleUuid'].', new: '.$viewArr['sampleUuid'].')';
				$status = false;
			}
		}

		//missing a barcode, just record within NeonSample error field and then skip harvest of this record
		if(empty($sampleArr['sampleCode']) && isset($viewArr['barcode'])){
			$this->errorStr .= '; DATA ISSUE: Barcode missing in database records, but available in API ('.$viewArr['barcode'].')';
			$status = false;
		} elseif (!empty($sampleArr['sampleCode']) && !isset($viewArr['barcode'])){
			$this->errorStr .= '; DATA ISSUE: Barcode missing in API, but available in database records ('.$sampleArr['sampleCode'].')';
			$status = false;
		}

		if(!empty($sampleArr['sampleCode']) && isset($viewArr['barcode']) && $sampleArr['sampleCode'] != $viewArr['barcode']){
			//sampleCode/barcode are not equal; don't update, just record within NeonSample error field and then skip harvest of this record
			$this->errorStr .= '; DATA ISSUE: Barcode failing to match (old: '.$sampleArr['sampleCode'].', new: '.$viewArr['barcode'].')';
			$status = false;
		}

		if($sampleArr['sampleClass'] && isset($viewArr['sampleClass']) && $sampleArr['sampleClass'] != $viewArr['sampleClass']){
			//sampleClass are not equal; don't update, just record within NeonSample error field and then skip harvest of this record
			$this->errorStr .= '; DATA ISSUE: sampleClass failing to match (old: '.$sampleArr['sampleClass'].', new: '.$viewArr['sampleClass'].')';
			$status = false;
		}
		if(isset($viewArr['archiveGuid']) && $viewArr['archiveGuid']){
			$igsnMatch = array();
			if(preg_match('/(NEON[A-Z,0-9]{5})/', $viewArr['archiveGuid'], $igsnMatch)){
				if($sampleArr['occid']){
					//This is a reharvest event, check to make sure IGSNs match
					if(isset($sampleArr['occurrenceID']) && $sampleArr['occurrenceID']){
						if($sampleArr['occurrenceID'] == $igsnMatch[1]){
							$neonSampleUpdate['igsnPushedToNEON'] = 1;
						}
						else{
							$this->setSampleErrorMessage($sampleArr['samplePK'], 'DATA ISSUE: IGSN failing to match with API value');
							$neonSampleUpdate['igsnPushedToNEON'] = 2;
						}
					}
					else{
						if(!$this->igsnExists($igsnMatch[1],$sampleArr)){
							if(!$this->updateOccurrenceIgsn($igsnMatch[1], $sampleArr['occid'])){
								$this->setSampleErrorMessage($sampleArr['samplePK'], 'NOTICE: unable to update igsn: '.$this->conn->error);
								$neonSampleUpdate['igsnPushedToNEON'] = 3;
							}
						}
					}
				}
				else{
					//New record should use ISGN, if it is not already assigned to another record
					if(!$this->igsnExists($igsnMatch[1],$sampleArr)) $sampleArr['occurrenceID'] = $igsnMatch[1];
				}
			}
		}
		if($sampleArr['sampleID'] && isset($viewArr['sampleTag']) && $sampleArr['sampleID'] != $viewArr['sampleTag'] && $sampleArr['hashedSampleID'] != $viewArr['sampleTag']){
			//sampleIDs (sampleTags) are not equal; report error and abort harvest
			if(substr($viewArr['sampleTag'],-1) == '=' || !preg_match('/[_\.]+/',$viewArr['sampleTag'])){
				$neonSampleUpdate['hashedSampleID'] = $viewArr['sampleTag'];
				$sampleArr['hashedSampleID'] = $viewArr['sampleTag'];
			}
			else{
				$this->errorStr .= '; DATA ISSUE: sampleID failing to match';
				$status = false;
				/*
				 if($this->updateSampleID($viewArr['sampleTag'], $sampleArr['sampleID'], $sampleArr['samplePK'], $sampleArr['occid'])){
				 $this->errorLogArr[] = 'NOTICE: sampleID updated from '.$sampleArr['sampleID'].' to '.$viewArr['sampleTag'].' (samplePK: '.$sampleArr['samplePK'].', occid: '.$sampleArr['occid'].')';
				 }
				 else{
				 $errMsg = (isset($neonSampleUpdate['errorMessage'])?$neonSampleUpdate['errorMessage'].'; ':'');
				 $errMsg .= 'DATA ISSUE: failed to reset sampleID using changed API value';
				 $this->setSampleErrorMessage($sampleArr['samplePK'], $errMsg);
				 }
				 */
			}
		}
		if(!$status) $this->setSampleErrorMessage($sampleArr['samplePK'], trim($this->errorStr, '; '));
		$this->updateSampleRecord($neonSampleUpdate,$sampleArr['samplePK']);
		return $status;
	}

	private function updateSampleID($newSampleID, $oldSampleID, $samplePK, $occid){
		$status = true;
		$sql = 'UPDATE NeonSample SET sampleID = "'.$newSampleID.'", alternativeSampleID = CONCAT_WS(", ",alternativeSampleID,"'.$oldSampleID.'") WHERE samplePK = '.$samplePK;
		if(!$this->conn->query($sql)){
			$status = false;
		}
		if($occid){
			$sql = 'UPDATE omoccuridentifiers SET identifierValue = "'.$newSampleID.'" WHERE identifiername = "NEON sampleID" AND occid = '.$occid;
			if(!$this->conn->query($sql)){
				$status = false;
			}
		}
		return $status;
	}

	private function updateSampleRecord($neonSampleUpdate,$samplePK){
		if($neonSampleUpdate){
			$sqlInsert = '';
			foreach($neonSampleUpdate as $field => $value){
				$sqlInsert .= $field.' = "'.$this->cleanInStr($value).'", ';
			}
			$sql = 'UPDATE NeonSample SET '.trim($sqlInsert,', ').' WHERE (samplePK = '.$samplePK.')';
			if(!$this->conn->query($sql)){
				echo '</li><li style="margin-left:30px">ERROR updating NeonSample record: '.$this->conn->error.'</li>';
			}
		}
	}

	private function igsnExists($igsn, &$sampleArr){
		$occid = 0;
		$sql = 'SELECT occid FROM omoccurrences WHERE occurrenceid = "'.$igsn.'" ';
		$rs = $this->conn->query($sql);
		if($r = $rs->fetch_object()){
			$occid = $r->occid;
		}
		$rs->free();
		if($occid){
			//Another records exists within the portal with the same IGSN (not ideal)
			$sampleArr['occurrenceID'] = $igsn.'-dupe (data issue!)';
			$errMsg = 'DATA ISSUE: another record exists with duplicate IGSN registered within NEON API';
			$this->setSampleErrorMessage($sampleArr['samplePK'], $errMsg);
			return true;
		}
		return false;
	}

	private function updateOccurrenceIgsn($igsn, $occid){
		$status = false;
		$sql = 'UPDATE omoccurrences SET occurrenceID = "'.$igsn.'" WHERE occurrenceid IS NULL AND occid = '.$occid;
		if($this->conn->query($sql)) $status = true;
		return $status;
	}

	private function processViewArr(&$sampleArr, $viewArr, $sampleRank = 0){
		if(!isset($viewArr['sampleViews'])){
			$this->errorStr = 'sampleViews object failed to be returned from NEON API';
			return false;
		}
		$viewArr = current($viewArr['sampleViews']);
		//if(isset($viewArr['sampleClass']) && $viewArr['sampleClass'] == 'mam_pertrapnight_in.tagID') $this->createRelationship($viewArr['childSampleIdentifiers']);
		//parse Sample Event details
		$eventArr = $viewArr['sampleEvents'];
		$harvestIdentifications = true;
		if($sampleRank && isset($sampleArr['identifications'])) $harvestIdentifications = false;
		if($eventArr){
			foreach($eventArr as $eArr){
				$tableName = $eArr['ingestTableName'];
				if(strpos($tableName,'shipment')) continue;
				//if(strpos($tableName,'identification')) continue;
				//if(strpos($tableName,'sorting')) continue;
				if(strpos($tableName,'scs_archive')) continue;
				//if(strpos($tableName,'barcoding')) continue;
				if(strpos($tableName,'dnaStandardTaxon')) continue;
				if(strpos($tableName,'dnaExtraction')) continue;
				if(strpos($tableName,'markerGeneSequencing')) continue;
				if(strpos($tableName,'metagenomeSequencing')) continue;
				if(strpos($tableName,'metabarcodeTaxonomy')) continue;
				if(strpos($tableName,'pcrAmplification')) continue;
				//if(strpos($tableName,'perarchivesample')) continue;
				//if(strpos($tableName,'persample')) continue;
				//if(strpos($tableName,'pertaxon')) continue;
				if(strpos($tableName,'zoo_perVial_in')) continue;
				if($tableName == 'mpr_perpitprofile_in') continue;
				$fieldArr = $eArr['smsFieldEntries'];
				$fateLocation = ''; $fateDate = '';
				$readAssocTaxon = false;
				$identRemarks = array();
				$identArr = array(); $assocMedia = array(); $assocTaxa = array();
				$tableArr = array();
				foreach($fieldArr as $fArr){
					if($tableName == 'tck_pathogenresults_in'){
						if($fArr['smsKey'] == 'analysis_type' && $fArr['smsValue'] == 'Positive'){
							$readAssocTaxon = true;
						}
						if($fArr['smsKey'] == 'taxon' && $fArr['smsValue'] != 'HardTick DNA Quality' && $readAssocTaxon){
							$assocTaxa['verbatimSciname'] = $fArr['smsValue'];
							$assocTaxa['relationship'] = 'hostOf';
						}
					}
					else{
						if($fArr['smsKey'] == 'fate_location') $fateLocation = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'collection_location' && $fArr['smsValue']) $tableArr['collection_location'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'fate_date' && $fArr['smsValue']) $fateDate = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'event_id' && $fArr['smsValue']) $tableArr['event_id'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'collected_by' && $fArr['smsValue']) $tableArr['collected_by'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'collect_start_date' && $fArr['smsValue']) $tableArr['collect_start_date'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'collect_end_date' && $fArr['smsValue']) $tableArr['collect_end_date'] = $fArr['smsValue'];
						elseif (
							$fArr['smsKey'] == 'specimen_count' &&
							$fArr['smsValue'] &&
							!(
								strpos($tableName, 'bet_') !== false ||
								strpos($tableName, 'ptx_') !== false ||
								strpos($tableName, 'cfc_') !== false ||
								strpos($tableName, 'mic_') !== false ||
								strpos($tableName, 'sls_') !== false ||
								strpos($tableName, 'inv_') !== false ||
								strpos($tableName, 'metabarcode') !== false
							)
						) {
							$tableArr['specimen_count'] = $fArr['smsValue'];
						}
						elseif($fArr['smsKey'] == 'temperature' && $fArr['smsValue']) $tableArr['temperature'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'decimal_latitude' && $fArr['smsValue']) $tableArr['decimal_latitude'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'decimal_longitude' && $fArr['smsValue']) $tableArr['decimal_longitude'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'coordinate_uncertainty' && $fArr['smsValue']) $tableArr['coordinate_uncertainty'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'elevation' && $fArr['smsValue']) $tableArr['elevation'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'elevation_uncertainty' && $fArr['smsValue']) $tableArr['elevation_uncertainty'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'verbatim_depth' && $fArr['smsValue']) $tableArr['verbatim_depth'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'minimum_depth_in_meters' && $fArr['smsValue']) $tableArr['minimum_depth_in_meters'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'maximum_depth_in_meters' && $fArr['smsValue']) $tableArr['maximum_depth_in_meters'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'reproductive_condition' && $fArr['smsValue']) $tableArr['reproductive_condition'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'sex' && $fArr['smsValue']) $tableArr['sex'] = $fArr['smsValue'];
						elseif (
							$fArr['smsKey'] == 'life_stage' &&
							$fArr['smsValue'] &&
							!(
								strpos($tableName, 'bet_') !== false ||
								strpos($tableName, 'ptx_') !== false ||
								strpos($tableName, 'cfc_') !== false ||
								strpos($tableName, 'mic_') !== false ||
								strpos($tableName, 'sls_') !== false ||
								strpos($tableName, 'inv_') !== false ||
								strpos($tableName, 'metabarcode') !== false
							)
						) {
							$tableArr['life_stage'] = $fArr['smsValue'];
						}
						elseif($fArr['smsKey'] == 'associated_taxa' && $fArr['smsValue']) $tableArr['associated_taxa'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'remarks' && $fArr['smsValue'] && !in_array($tableName,array('ptx_taxonomy_in'))) $tableArr['remarks'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'preservative_concentration' && $fArr['smsValue']) $tableArr['preservative_concentration'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'preservative_volume' && $fArr['smsValue']) $tableArr['preservative_volume'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'preservative_type' && $fArr['smsValue']) $tableArr['preservative_type'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'sample_type' && $fArr['smsValue'] && !in_array($tableName,array('ptx_taxonomy_in'))) $tableArr['sample_type'] = $fArr['smsValue'];
						//elseif($fArr['smsKey'] == 'sample_condition' && $fArr['smsValue']) $tableArr['sample_condition'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'sample_mass' && $fArr['smsValue']) $tableArr['sample_mass'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'sample_volume' && $fArr['smsValue']) $tableArr['sample_volume'] = $fArr['smsValue'];
						elseif($fArr['smsKey'] == 'associated_media'){
							if(!strpos($fArr['smsValue'],'biorepo.neonscience.org/portal')) $assocMedia['url'] = $fArr['smsValue'];
						}
						elseif($fArr['smsKey'] == 'photographed_by') $assocMedia['photographer'] = $fArr['smsValue'];
						if($harvestIdentifications && !($tableName == 'inv_pervial_in' && in_array($sampleArr['sampleClass'],array('inv_fielddata_in.sampleID','inv_persample_in.mixedInvertVialID','inv_persample_in.chironomidVialID','inv_persample_in.oligochaeteVialID')))){
							if($fArr['smsKey'] == 'taxon' && $fArr['smsValue']){
								$identArr['sciname'] = $fArr['smsValue'];
								$identArr['taxon'] = $fArr['smsValue'];
							}
							elseif($fArr['smsKey'] == 'taxon_published' && $fArr['smsValue']){
								//Temporarly keep to support possibility of this field still being used for certain sampleClasses
								$identArr['taxonPublished'] = $fArr['smsValue'];
							}
							elseif($fArr['smsKey'] == 'taxon_published_processed_scientific_name' && $fArr['smsValue']){
								$identArr['taxonPublished'] = $fArr['smsValue'];
							}
							elseif($fArr['smsKey'] == 'taxon_published_raw_scientific_name' && $fArr['smsValue']){
								//We only want "raw" value if "processed" does not exists, thus we don't want to replace a set "processed" value with a "raw"
								if(empty($identArr['taxonPublished'])) $identArr['taxonPublished'] = $fArr['smsValue'];
							}
							elseif($fArr['smsKey'] == 'taxon_published_processed_code' && $fArr['smsValue']){
								$identArr['taxonPublishedCode'] = $fArr['smsValue'];
							}
							elseif($fArr['smsKey'] == 'taxon_published_raw_code' && $fArr['smsValue']){
								if(empty($identArr['taxonPublishedCode'])) $identArr['taxonPublishedCode'] = $fArr['smsValue'];
							}
							elseif($fArr['smsKey'] == 'identified_by' && $fArr['smsValue']) $identArr['identifiedBy'] = $this->translatePersonnel($fArr['smsValue']);
							elseif($fArr['smsKey'] == 'identified_date' && $fArr['smsValue']) $identArr['dateIdentified'] = $fArr['smsValue'];
							elseif(in_array($tableName,array('ptx_taxonomy_in'))){
								if($fArr['smsKey'] == 'sample_type' && $fArr['smsValue']){
									$identRemarks[] = $fArr['smsValue'];
								}
								if($fArr['smsKey'] == 'remarks' && $fArr['smsValue']){
									$identRemarks[] = $fArr['smsValue'];
								}
								if($fArr['smsKey'] == 'identification_remarks' && $fArr['smsValue']){
									$identRemarks[] = $fArr['smsValue'];
								}
								$identArr['identificationRemarks'] = implode('; ',$identRemarks);
							}
							elseif(!in_array($tableName,array('ptx_taxonomy_in')) && $fArr['smsKey'] == 'identification_remarks' && $fArr['smsValue']) {
									$identArr['identificationRemarks'] = $fArr['smsValue'];
								}
							elseif($fArr['smsKey'] == 'identification_references' && $fArr['smsValue']) $identArr['identificationReferences'] = $fArr['smsValue'];
							elseif($fArr['smsKey'] == 'identification_qualifier' && $fArr['smsValue']) $identArr['identificationQualifier'] = $fArr['smsValue'];
							elseif(in_array($tableName,array('zoo_perTaxon_in','inv_pertaxon_in')) && $fArr['smsKey'] == 'specimen_count' && $fArr['smsValue']) $identArr['subsampleIndividualCount'] = $fArr['smsValue'];
							elseif(in_array($tableName,array('inv_pertaxon_in')) && $fArr['smsKey'] == 'life_stage' && $fArr['smsValue']) $identArr['subsampleLifeStage'] = $fArr['smsValue'];
						}
					}
				}
				if($assocMedia && isset($assocMedia['url'])) $tableArr['assocMedia'][] = $assocMedia;
				if(empty($this->fateLocationArr[1]['loc'])){
					//locationID has not yet been harvested from collection_location field, thus looking for value set within parent
					if(!empty($tableArr['collection_location']) && !strpos($tableArr['collection_location'], ' ')){
						//collection_location is first choice/ranking
						$score = 1;
						$this->fateLocationArr[$score]['loc'] = $tableArr['collection_location'];
						$this->fateLocationArr[$score]['date'] = $fateDate;
					}
					elseif($fateDate && $fateLocation && !strpos($fateLocation, ' ')){
						//If collection_location is not defined, use fate dates (old method of harvesting locationID and date)
						$score = $sampleRank.':'.$fateDate;
						if(strpos($tableName,'fielddata')) $score = 2;
						$this->fateLocationArr[$score]['loc'] = $fateLocation;
						$this->fateLocationArr[$score]['date'] = $fateDate;
					}
				}
				$sampleArr = array_merge($tableArr, $sampleArr);
				if($identArr && isset($identArr['sciname']) && $identArr['sciname']){
					$identArr['taxonRemarks'] = 'Identification source: harvested from NEON API';
					if(empty($identArr['dateIdentified'])){
						if($fateDate) $identArr['dateIdentified'] = $fateDate;
						else $identArr['dateIdentified'] = 's.d.';
					}
					if(empty($identArr['identifiedBy'])){
						$identArr['identifiedBy'] = 'undefined';
					}
					$hash = hash('md5', str_replace(' ', '', $identArr['sciname'].$identArr['identifiedBy'].$identArr['dateIdentified']));
					// allow for unique records for different life stages
					if($tableName = 'inv_pertaxon_in'){
						if(isset($identArr['subsampleIndividualCount'])){
							$hash .= hash('md5', str_replace(' ', '', $identArr['subsampleIndividualCount']));
						}
						if(isset($identArr['subsampleLifeStage'])){
							$hash .= hash('md5', str_replace(' ', '', $identArr['subsampleLifeStage']));
						}
						if(isset($identArr['identificationRemarks'])){
							$hash .= hash('md5', str_replace(' ', '', $identArr['identificationRemarks']));
						}
					}
					$sampleArr['identifications'][$hash] = $identArr;
				}
				if($assocTaxa && isset($assocTaxa['verbatimSciname'])){
					$taxaArr = $this->getTaxonArr($assocTaxa['verbatimSciname']);
					if(isset($taxaArr['tidInterpreted'])) $assocTaxa['tid'] = $taxaArr['tidInterpreted'];
					$hash = hash('md5', str_replace(' ', '', $assocTaxa['verbatimSciname'].$assocTaxa['relationship']));
					$sampleArr['associations'][$hash] = $assocTaxa;
				}
			}
		}
		$sampleRank++;
		if(isset($viewArr['parentSampleIdentifiers'][0]['sampleUuid'])){
			//Get parent data
			$url = $this->neonApiBaseUrl.'/samples/view?sampleUuid='.$viewArr['parentSampleIdentifiers'][0]['sampleUuid'].'&apiToken='.$this->neonApiKey;
			$parentViewArr = $this->getNeonApiArr($url);
			$this->processViewArr($sampleArr, $parentViewArr, $sampleRank);
		}
	}

	private function getDarwinCoreArr($sampleArr){
		$dwcArr = array();
		if($sampleArr['samplePK']){
			if($this->setCollectionIdentifier($dwcArr,$sampleArr['sampleClass'])){
				//Get data that was provided within manifest
				$dwcArr['identifiers']['NEON sampleCode (barcode)'] = (isset($sampleArr['sampleCode'])?$sampleArr['sampleCode']:'');
				$dwcArr['identifiers']['NEON sampleID'] = (isset($sampleArr['sampleID'])?$sampleArr['sampleID']:'');
				$dwcArr['identifiers']['NEON sampleUUID'] = (isset($sampleArr['sampleUuid'])?$sampleArr['sampleUuid']:'');
				$dwcArr['identifiers']['NEON sampleID Hash'] = (isset($sampleArr['hashedSampleID'])?$sampleArr['hashedSampleID']:'');
				if(isset($sampleArr['event_id'])) $dwcArr['eventID'] = $sampleArr['event_id'];
				if(isset($sampleArr['specimen_count'])) $dwcArr['individualCount'] = $sampleArr['specimen_count'];
				elseif(isset($sampleArr['individualCount'])) $dwcArr['individualCount'] = $sampleArr['individualCount'];
				if(isset($sampleArr['reproductive_condition'])) $dwcArr['reproductiveCondition'] = $sampleArr['reproductive_condition'];
				if(isset($sampleArr['sex'])) $dwcArr['sex'] = $sampleArr['sex'];
				if(isset($sampleArr['life_stage'])) $dwcArr['lifeStage'] = $sampleArr['life_stage'];
				if(isset($sampleArr['associated_taxa'])) $dwcArr['associatedTaxa'] = $this->translateAssociatedTaxa($sampleArr['associated_taxa']);
				$occurRemarks = array();
				if(!in_array($dwcArr['collid'],array(46,73))){
					if(isset($sampleArr['remarks'])) $occurRemarks[] = $sampleArr['remarks'];
					if(isset($sampleArr['sample_type'])){
						$sampleType = $sampleArr['sample_type'];
						if($sampleType == 'M') $sampleType = 'mineral';
						elseif($sampleType == 'O') $sampleType = 'organic';
						$occurRemarks[] = 'sample type: '.$sampleType;
					}
					if($occurRemarks) $dwcArr['occurrenceRemarks'] = implode('; ',$occurRemarks);
				}
				if(isset($sampleArr['assocMedia'])) $dwcArr['assocMedia'] = $sampleArr['assocMedia'];
				if(isset($sampleArr['coordinate_uncertainty']) && $sampleArr['coordinate_uncertainty']) $dwcArr['coordinateUncertaintyInMeters'] = $sampleArr['coordinate_uncertainty'];
				if(isset($sampleArr['decimal_latitude'])) $dwcArr['decimalLatitude'] = $sampleArr['decimal_latitude'];
				if(isset($sampleArr['decimal_longitude'])) $dwcArr['decimalLongitude'] = $sampleArr['decimal_longitude'];
				if(isset($sampleArr['elevation'])){
					if(isset($sampleArr['elevation_uncertainty'])){
						$dwcArr['minimumElevationInMeters'] = round($sampleArr['elevation']-$sampleArr['elevation_uncertainty']);
						$dwcArr['maximumElevationInMeters'] = round($sampleArr['elevation']+$sampleArr['elevation_uncertainty']);
						$dwcArr['verbatimElevation'] = $sampleArr['elevation'].'m (+-'.$sampleArr['elevation_uncertainty'].'m)';
					}
					else{
						$dwcArr['minimumElevationInMeters'] = $sampleArr['elevation'];
						$dwcArr['maximumElevationInMeters'] = $sampleArr['elevation'];
					}
				}
				$prepArr = array();
					if(!in_array($dwcArr['collid'], array(7,8,9,19,28,42,46,17,64))){
						if(!in_array($dwcArr['collid'],array(31,50,73))){
							if(!empty($sampleArr['preservative_type'])) $prepArr[] = 'preservative type: '.$sampleArr['preservative_type'];
						}
						if(!empty($sampleArr['preservative_volume'])) $prepArr[] = 'preservative volume: '.$sampleArr['preservative_volume'];
						if(!empty($sampleArr['preservative_concentration'])) $prepArr[] = 'preservative concentration: '.$sampleArr['preservative_concentration'];
						if(!empty($sampleArr['sample_mass']) && strpos($sampleArr['symbiotaTarget'],'sample mass') === false) $prepArr[] = 'sample mass: '.$sampleArr['sample_mass'];
						if(!empty($sampleArr['sample_volume']) && strpos($sampleArr['symbiotaTarget'],'sample volume') === false) $prepArr[] = 'sample volume: '.$sampleArr['sample_volume'];
						if(!empty($sampleArr['sex'])){
							if($sampleArr['sex'] == 'M') $dwcArr['sex'] = 'Male';
							elseif($sampleArr['sex'] == 'F') $dwcArr['sex'] = 'Female';
							elseif($sampleArr['sex'] == 'U') $dwcArr['sex'] = 'Unknown';
						}
					}
				if($prepArr) $dwcArr['preparations'] = implode(', ',$prepArr);
				$dynProp = array();
				if(!empty($sampleArr['filterVolume'])) $dynProp[] = 'filterVolume: '.$sampleArr['filterVolume'];
				if(!empty($sampleArr['temperature'])) $dynProp[] = 'temperature: '.$sampleArr['temperature'];
				if(!empty($sampleArr['minimum_depth_in_meters'])) $dynProp[] = 'minimum depth: '.$sampleArr['minimum_depth_in_meters'].'m ';
				if(!empty($sampleArr['maximum_depth_in_meters'])) $dynProp[] = 'maximum depth: '.$sampleArr['maximum_depth_in_meters'].'m ';
				if(!empty($sampleArr['verbatim_depth'])) $dynProp[] = 'verbatim depth: '.$sampleArr['verbatim_depth'];
				//if(isset($sampleArr['sample_condition'])) $dynProp[] = 'sample condition: '.$sampleArr['sample_condition'];
				if($dynProp) $dwcArr['dynamicProperties'] = implode(', ',$dynProp);

				if(!empty($sampleArr['collected_by'])) $dwcArr['recordedBy'] = $this->translatePersonnel($sampleArr['collected_by']);
				if(!empty($sampleArr['collect_end_date'])){
					if(!empty($sampleArr['collect_start_date']) && $sampleArr['collect_start_date'] != $sampleArr['collect_end_date']){
						$dwcArr['eventDate'] = $sampleArr['collect_start_date'];
						$dwcArr['eventDate2'] = $sampleArr['collect_end_date'];
					}
					else $dwcArr['eventDate'] = $sampleArr['collect_end_date'];
				}
				elseif(!empty($sampleArr['collectDate']) && $sampleArr['collectDate'] != '0000-00-00') $dwcArr['eventDate'] = $sampleArr['collectDate'];
				elseif($sampleArr['sampleID']){
					if(preg_match('/\.(20\d{2})(\d{2})(\d{2})\./',$sampleArr['sampleID'],$m)){
						//Get date from sampleID
						$dwcArr['eventDate'] = $m[1].'-'.$m[2].'-'.$m[3];
					}
				}
				//Build proper location code
				$locationStr = '';
				if(!empty($sampleArr['fate_location'])) $locationStr = $sampleArr['fate_location'];
				elseif($sampleArr['namedLocation']) $locationStr = $sampleArr['namedLocation'];
				if($locationStr){
					if($this->setNeonLocationData($dwcArr, $locationStr)){
						if(isset($dwcArr['domainID'])){
							$locStr = $this->domainSiteArr[$dwcArr['domainID']].' ('.$dwcArr['domainID'].'), ';
							if(isset($dwcArr['siteID'])) $locStr .= $this->domainSiteArr[$dwcArr['siteID']].' ('.$dwcArr['siteID'].'), ';
							if(isset($dwcArr['locality'])) $locStr .= $dwcArr['locality'];
							$dwcArr['locality'] = trim($locStr,', ');
						}
						if(isset($dwcArr['plotDim'])){
							$dwcArr['locality'] .= $dwcArr['plotDim'];
							unset($dwcArr['plotDim']);
						}
						$dwcArr['locationID'] = $locationStr;
					}
					else{
						$dwcArr['locality'] = $sampleArr['namedLocation'];
						$this->errorStr = 'locality data failed to populate';
						$this->setSampleErrorMessage($sampleArr['samplePK'], $this->errorStr);
						//return false;
					}
					if(!empty($sampleArr['collection_location'])){
						if(!isset($dwcArr['locationID']) || $dwcArr['locationID'] != $sampleArr['collection_location']) $dwcArr['locality'] .= ', '.trim($sampleArr['collection_location'],' ,;.');
					}
					if(!empty($dwcArr['locality'])) $dwcArr['locality'] = trim($dwcArr['locality'],' ,;.');
				}

				//Taxonomic fields
				$skipTaxonomy = array(5,6,10,13,16,21,23,31,41,42,58,60,61,62,67,68,69,76,92);
				if(!in_array($dwcArr['collid'],$skipTaxonomy)){
					$identArr = array();
					$taxonCode = '';
					if(isset($sampleArr['identifications']) && !in_array($dwcArr['collid'], array(46,98))){
						$identArr = $sampleArr['identifications'];
					}
					if(!$identArr && $sampleArr['taxonID'] && !in_array($dwcArr['collid'], array(46,98))){
						$hash = hash('md5', str_replace(' ','',$sampleArr['taxonID'].'manifests.d.'));
						$identArr[$hash] = array('sciname' => $sampleArr['taxonID'], 'identifiedBy' => 'manifest', 'dateIdentified' => 's.d.', 'taxonRemarks' => 'Identification source: inferred from shipment manifest');
					}
					if(!$identArr){
						//Identifications not supplied via API nor manifest, thus try to grab from sampleID with collection specific format
						$taxonCode = '';
						$taxonRemarks = '';
						if(in_array($dwcArr['collid'], array(46,98))){
							if (preg_match('/\.\d{8}\.([a-zA-Z]{2,15})\./', $sampleArr['sampleID'], $m)) {
								$taxonCode = $m[1];
								$taxonRemarks = 'Identification source: parsed from NEON sampleID';
							}
						}
						// Should not ever need this for collid 30 anymore, but leaving in case it's useful
						// if($dwcArr['collid'] == 30){
						// 	$identArr[] = array('sciname' => $dwcArr['identifications'][0]['sciname'],
						// 					  'identifiedBy' => 'NEON Lab',
						// 					  'dateIdentified' => 's.d.');
						// }
						elseif($dwcArr['collid'] == 56){
							if(preg_match('/\.\d{4}\.\d{1,2}\.([A-Z]{2,15}\d{0,2})\./', $sampleArr['sampleID'], $m)){
								$taxonCode = $m[1];
								$taxonRemarks = 'Identification source: parsed from NEON sampleID';
							}
						}
						// elseif(!in_array($dwcArr['collid'], array(22,50,57))){
						// 	if(preg_match('/\.\d{8}\.([A-Z]{2,15}\d{0,2})\./',$sampleArr['sampleID'], $m)){
						// 		$taxonCode = $m[1];
						// 		$taxonRemarks = 'Identification source: parsed from NEON sampleID';
						// 	}
						// }
					}
					if($taxonCode){
							$hash = hash('md5', str_replace(' ','',$taxonCode.'sampleIDs.d.'));
							$identArr[$hash] = array('sciname' => $taxonCode, 'identifiedBy' => 'sampleID', 'dateIdentified' => 's.d.', 'taxonRemarks' => $taxonRemarks);
					}
					if($identArr){
						$isCurrentKey = 0;
						$bestDate = 0;
						foreach($identArr as $idKey => &$idArr){
							if(!isset($idArr['sciname'])) unset($identArr[$idKey]);
							//Translate NEON taxon codes or check/clean scientific name submitted
							if(preg_match('/^[A-Z0-9]+$/', $idArr['sciname'])){
								//Taxon is a NEON code that needs to be translated
								if($taxaArr = $this->translateTaxonCode($idArr['sciname'])){
									$idArr = array_merge($idArr, $taxaArr);
								}
							}
							else{
								if($taxaArr = $this->getTaxonArr($idArr['sciname'])){
									if(!empty($idArr['scientificNameAuthorship'])) unset($taxaArr['scientificNameAuthorship']);
									$idArr = array_merge($idArr, $taxaArr);
								}
							}
							//Evaluate if any incoming determinations should be tagged as isCurrent
							if(!$isCurrentKey) $isCurrentKey = $idKey;	//First determination is set as isCurrent as the default
							if(isset($idArr['dateIdentified']) && preg_match('/^\d{4}/', $idArr['dateIdentified']) && $idArr['dateIdentified'] > $bestDate){
								$bestDate = $idArr['dateIdentified'];
								$isCurrentKey = $idKey;
							}
						}
						if($isCurrentKey) $identArr[$isCurrentKey]['isCurrent'] = 1;
						$appendIdentArr = array();
						foreach($identArr as $idKey => &$idArr){
							//Check to see if any determination needs to be protected
							$protectTaxon = $this->protectTaxonomyTest($idArr);
							if($protectTaxon){
								$idArrClone = $idArr;
								if($idArr['taxonPublished']) $idArrClone['sciname'] = $idArr['taxonPublished'];
								else $idArrClone['sciname'] = $idArr['taxonPublishedCode'];
								unset($idArrClone['scientificNameAuthorship']);
								unset($idArrClone['family']);
								if(preg_match('/^[A-Z0-9]+$/', $idArrClone['sciname'])){
									//Taxon is a NEON code that needs to be translated
									if($taxaArr = $this->translateTaxonCode($idArrClone['sciname'])){
										$idArrClone = array_merge($idArrClone, $taxaArr);
									}
								}
								else{
									if($taxaArr = $this->getTaxonArr($idArrClone['sciname'])){
										$idArrClone = array_merge($idArrClone, $taxaArr);
									}
								}
								$appendIdentArr[] = $idArrClone;
								$idArr['securityStatus'] = 1;
								$idArr['securityStatusReason'] = 'Locked - NEON redaction list';
							}
							else{
								$idArr['securityStatus'] = 0;
								$idArr['securityStatusReason'] = '';
							}
							//Check to see if current taxon is the most current taxon
							if(!empty($idArr['isCurrent'])){
								if(isset($this->taxonArr[$idArr['sciname']]['accepted'])){
									if($idArr['sciname'] != $this->taxonArr[$idArr['sciname']]['accepted']){
										$idArr['scientificNameAuthorship'] = $this->taxonArr[$idArr['sciname']]['acceptedAuthor'];
										$idArr['tidInterpreted'] = $this->taxonArr[$idArr['sciname']]['acceptedTid'];
										$idArr['sciname'] = $this->taxonArr[$idArr['sciname']]['accepted'];
									}
								}
							}
						}
						if($appendIdentArr) $identArr = array_merge($identArr, $appendIdentArr);
						$dwcArr['identifications'] = $identArr;
					}
				}
				// Occurrence associations
				if(isset($sampleArr['associations'])){
					$dwcArr['associations'] = $sampleArr['associations'];
				}
				//Add DwC fields that were imported as part of the manifest file
				if($sampleArr['symbiotaTarget']){
					if($symbArr = json_decode($sampleArr['symbiotaTarget'],true)){
						foreach($symbArr as $symbField => $symbValue){
							if($symbValue !== '' && !isset($dwcArr[$symbField])) $dwcArr[$symbField] = $symbValue;
						}
					}
				}
			}
			else{
				$this->errorStr = 'ERROR: unable to retrieve collid using sampleClass: '.$sampleArr['sampleClass'];
				$this->setSampleErrorMessage($sampleArr['samplePK'], 'unable to retrieve collid using sampleClass');
				return false;
			}
		}
		if(isset($dwcArr['eventDate'])) $dwcArr['eventDate'] = $this->formatDate($dwcArr['eventDate']);
		if(isset($dwcArr['eventDate2'])){
			$dwcArr['eventDate2'] = $this->formatDate($dwcArr['eventDate2']);
			if($dwcArr['eventDate'] == $dwcArr['eventDate2']) unset($dwcArr['eventDate2']);
		}
		$this->applyCustomAdjustments($dwcArr);
		return $dwcArr;
	}

	private function setCollectionIdentifier(&$dwcArr,$sampleClass){
		$status = false;
		if($sampleClass){
			$sql = 'SELECT collid, datasetName FROM omcollections
				WHERE (datasetID = "'.$sampleClass.'") OR (datasetID LIKE "%,'.$sampleClass.',%") OR (datasetID LIKE "'.$sampleClass.',%") OR (datasetID LIKE "%,'.$sampleClass.'")';
			$rs = $this->conn->query($sql);
			if($rs->num_rows == 1){
				$r = $rs->fetch_object();
				$this->activeCollid = $r->collid;
				$dwcArr['collid'] = $r->collid;
				if($r->datasetName) $dwcArr['verbatimAttributes'] = $r->datasetName;
				else $dwcArr['verbatimAttributes'] = $sampleClass;
				$status = true;
			}
			$rs->free();
		}
		return $status;
	}

	private function setNeonLocationData(&$dwcArr, $locationName){
		$url = $this->neonApiBaseUrl.'/locations/'.urlencode($locationName).'?history=true&apiToken='.$this->neonApiKey;
		//echo 'loc url: '.$url.'<br/>';
		$resultArr = $this->getNeonApiArr($url);

		$eventDate = $dwcArr['eventDate'];
		$matchingIndex = null;

		if(!$resultArr) return false;

		foreach ($resultArr['locationHistory'] as $index => $location) {
    	
			$startDate = $location['locationStartDate'];
			$endDate = $location['locationEndDate'];

    	// Check if eventDate falls within the location history date range
    		if ($eventDate >= $startDate && (empty($endDate) || $eventDate <= $endDate)) {
        	$matchingIndex = $index;
        	break; 
   	 		}
		}

		if ($matchingIndex == null) $matchingIndex = 0;

		if(isset($resultArr['locationType']) && $resultArr['locationType']){
			if($resultArr['locationType'] == 'SITE') $dwcArr['siteID'] = $resultArr['locationName'];
			elseif($resultArr['locationType'] == 'DOMAIN') $dwcArr['domainID'] = $resultArr['locationName'];
		}
		if(isset($resultArr['locationDescription']) && $resultArr['locationDescription']){
			$parStr = str_replace(array('"',', RELOCATABLE',', CORE','Parent'),'',$resultArr['locationDescription']);
			$parStr = str_replace('re - Reach','Reach',$parStr);
			$parStr = preg_replace('/ at site [A-Z]+/', '', $parStr);
			$parStr = trim($parStr,' ,;');
			if($parStr){
				if($resultArr['locationType'] != 'SITE' && $resultArr['locationType'] != 'DOMAIN'){
					$localityStr = '';
					if(isset($dwcArr['locality'])) $localityStr = $dwcArr['locality'];
					$dwcArr['locality'] = $parStr.', '.$localityStr;
				}
			}
		}

		$resultArr_history = $resultArr['locationHistory'][$matchingIndex];

		if(!isset($dwcArr['decimalLatitude']) && isset($resultArr_history['locationDecimalLatitude']) && $resultArr_history['locationDecimalLatitude']){
			$dwcArr['decimalLatitude'] = $resultArr_history['locationDecimalLatitude'];
		}
		if(!isset($dwcArr['decimalLongitude']) && isset($resultArr_history['locationDecimalLongitude']) && $resultArr_history['locationDecimalLongitude']){
			$dwcArr['decimalLongitude'] = $resultArr_history['locationDecimalLongitude'];
		}
		if(!isset($dwcArr['verbatimCoordinates']) && isset($resultArr_history['locationUtmEasting']) && $resultArr_history['locationUtmEasting']){
			$dwcArr['verbatimCoordinates'] = trim($resultArr_history['locationUtmZone'].$resultArr_history['locationUtmHemisphere'].' '.$resultArr_history['locationUtmEasting'].'E '.$resultArr_history['locationUtmNorthing'].'N');
		}

		$locPropArr_history = $resultArr_history['locationProperties'];
		$locPropArr = $resultArr['locationProperties'];
		
		if ($locPropArr || $locPropArr_history) {
			$habitatArr = array();
			$elevMin = '';
			$elevMax = '';
			$elevUncertainty = '';
			$fullPropArr = array_merge($locPropArr_history, $locPropArr);
			
			foreach ($fullPropArr as $propArr) {
				$propName = $propArr['locationPropertyName'];
				$propValue = $propArr['locationPropertyValue'];
				
				if (!isset($dwcArr['georeferenceSources']) && $propName == 'Value for Coordinate source') {
					$dwcArr['georeferenceSources'] = $propValue;
				} elseif (!isset($dwcArr['coordinateUncertaintyInMeters']) && $propName == 'Value for Coordinate uncertainty') {
					$dwcArr['coordinateUncertaintyInMeters'] = $propValue;
				} elseif ($elevMin == '' && $propName == 'Value for Minimum elevation') {
					$elevMin = round($propValue);
				} elseif ($elevMax == '' && $propName == 'Value for Maximum elevation') {
					$elevMax = round($propValue);
				} elseif ($elevUncertainty == '' && $propName == 'Value for Elevation uncertainty') {
					$elevUncertainty = round($propValue);
				} elseif (!isset($dwcArr['country']) && $propName == 'Value for Country') {
					$countryValue = ($propValue == 'unitedStates' || $propValue == 'USA') ? 'United States' : $propValue;
					$dwcArr['country'] = $countryValue;
				} elseif (!isset($dwcArr['county']) && $propName == 'Value for County') {
					$dwcArr['county'] = $propValue;
				} elseif (!isset($dwcArr['geodeticDatum']) && $propName == 'Value for Geodetic datum') {
					$dwcArr['geodeticDatum'] = $propValue;
				} elseif (!isset($dwcArr['plotDim']) && $propName == 'Value for Plot dimensions') {
					$dwcArr['plotDim'] = ' (plot dimensions: ' . $propValue . ')';
				} elseif (!isset($habitatArr['landcover']) && strpos($propName, 'Value for National Land Cover Database') !== false) {
					$habitatArr['landcover'] = $propValue;
				} elseif (!isset($habitatArr['aspect']) && $propName == 'Value for Slope aspect') {
					$habitatArr['aspect'] = 'slope aspect: ' . $propValue;
				} elseif (!isset($habitatArr['gradient']) && $propName == 'Value for Slope gradient') {
					$habitatArr['gradient'] = 'slope gradient: ' . $propValue;
				} elseif (!isset($habitatArr['soil']) && $propName == 'Value for Soil type order') {
					if ($dwcArr['collid'] == 30 && !isset($dwcArr['identifications'])) {
						$dwcArr['identifications'][] = array('sciname' => $propValue);
					}
					$habitatArr['soil'] = 'soil type order: ' . $propValue;
				} elseif (!isset($dwcArr['stateProvince']) && $propName == 'Value for State province') {
					$stateStr = $propValue;
					if (array_key_exists($stateStr, $this->stateArr)) {
						$stateStr = $this->stateArr[$stateStr];
					}
					$this->setTimezone($stateStr);
					$dwcArr['stateProvince'] = $stateStr;
				}			
			}
			if ($habitatArr) {
				if (isset($habitatArr['landcover'])) {
					$landcover = $habitatArr['landcover'];
					unset($habitatArr['landcover']); 
					array_unshift($habitatArr, $landcover);
				}
				$dwcArr['habitat'] = implode('; ', $habitatArr);
			}
			if($elevMin === '' && !isset($dwcArr['minimumElevationInMeters'])) {
				$elevMin = round($resultArr_history['locationElevation']);
			}
			if ($elevMin !== '' && !isset($dwcArr['minimumElevationInMeters'])) {
				$dwcArr['minimumElevationInMeters'] = $elevMin;
			}
			
			if ($elevMax && $elevMax != $elevMin && !isset($dwcArr['maximumElevationInMeters'])) {
				$dwcArr['maximumElevationInMeters'] = $elevMax;
			}
			
			// new code if we wanted to use the verbatim elevation field in the future
			// if ($elevMin !== '' || $elevMax !== '' || $elevUncertainty !== '') {
			// 	$verbatimParts = [];
			// 	if ($elevMin !== '') {
			// 		$verbatimParts[] = $elevMin;
			// 	}			
			// 	if ($elevMax !== '' && $elevMax != $elevMin) {
			// 		$verbatimParts[] = $elevMax;
			// 	}
			// 	if (!empty($elevUncertainty)) {
			// 		$verbatimParts[] = "($elevUncertainty)";
			// 	}
			// 	$dwcArr['verbatimElevation'] = implode(' - ', $verbatimParts);
			// }
			
		}		

		if(isset($resultArr['locationParent']) && $resultArr['locationParent']){
			if($resultArr['locationParent'] != 'REALM'){
				$this->setNeonLocationData($dwcArr, $resultArr['locationParent']);
			}
		}
		return true;
	}

	private function applyCustomAdjustments(&$dwcArr){
		if($dwcArr['collid'] == 75){
			//Tick pathogen extracts
			$dwcArr['individualCount'] = 1;
			$dwcArr['preparations'] = '-80 degrees C.';
			$dwcArr['lifeStage'] = 'Nymph';
			$dwcArr['sex'] = '';
		}
		elseif(in_array($dwcArr['collid'], array(29,39,44,63,65,66,71,75,82,90,91,95))) {
			$dwcArr['individualCount'] = 1;
		}
	}

	private function protectTaxonomyTest($idArr){
		$protectTaxon = false;
		if(empty($idArr['taxonPublished']) && !empty($idArr['taxonPublishedCode'])){
			if($translatedTaxaArr = $this->translateTaxonCode($idArr['taxonPublishedCode'])){
				$idArr['taxonPublished'] = $translatedTaxaArr['sciname'];
			}
		}
		if(!empty($idArr['sciname'])){
			$taxaPublishedArr = array();
			if(!empty($idArr['taxonPublished'])){
				//Run taxonPublished by taxonomic thesaurus to ensure that taxonomic author is not embedded in name
				$taxaPublishedArr = $this->getTaxonArr($idArr['taxonPublished']);
				if(!empty($taxaPublishedArr['sciname'])) $idArr['taxonPublished'] = $taxaPublishedArr['sciname'];
				//Taxon published does not match base taxon, thus protect taxonomy
				if( $idArr['sciname'] != $idArr['taxonPublished']) $protectTaxon = true;
			}
			if($protectTaxon){
				if(!empty($idArr['taxonPublishedCode']) && $idArr['sciname'] == $idArr['taxonPublishedCode']){
					//But taxon does match the taxonCode, thus abort taxon protections
					//We should need this, but codes are not always translated successfully
					return false;
				}
				if($taxaPublishedArr && !empty($idArr['taxonPublished'])){
					//run secondary test to ensure that names are not synonyms
					$taxaArr = $this->getTaxonArr($idArr['sciname']);
					if(!empty($taxaArr['accepted']) && !empty($taxaPublishedArr['accepted'])){
						if($taxaArr['sciname'] == $taxaPublishedArr['accepted'] || $taxaArr['accepted'] == $taxaPublishedArr['sciname'] || $taxaArr['accepted'] == $taxaPublishedArr['accepted']){
							//both taxa are synonyms, thus abort protections
							return false;
						}
						if($taxaArr['rankid'] <= $taxaPublishedArr['rankid']){
							//protected taxon should always have a rankid greater than published taxon, thus abort
							return false;
						}
					}
				}
			}
		}
		return $protectTaxon;
	}

	private function subSampleIdentifications(&$dwcArr, $parentOccid){
		$collArr = array();
		$collArr[7] = array('targetCollid' => 108, 'lotId' => 'dynamic','defaultId' => 'Plantae');
		$collArr[8] = array('targetCollid' => 109, 'lotId' => 'dynamic','defaultId' => 'Plantae');
		$collArr[9] = array('targetCollid' => 107, 'lotId' => 'dynamic','defaultId' => 'Plantae');
		$collArr[22] = array('targetCollid' => 100, 'lotId' => 'dynamic','defaultId' => 'Chironomidae');
		$collArr[45] = array('targetCollid' => 104, 'defaultId' => 'Zooplankton');
		//$collArr[47] = array('targetCollid' => 110, 'lotId' => 'dynamic','defaultId' => 'ECO');
		//$collArr[49] = array('targetCollid' => 111, 'lotId' => 'dynamic','defaultId' => 'ECO');
		$collArr[50] = array('targetCollid' => 105, 'lotId' => 'dynamic','defaultId' => 'Plantae');
		$collArr[52] = array('targetCollid' => 101, 'lotId' => 'dynamic','defaultId' => 'Oligochaeta');
		$collArr[53] = array('targetCollid' => 102, 'lotId' => 'dynamic','defaultId' => 'Bulk Aquatic Macroinvertebrates');
		$collArr[57] = array('targetCollid' => 103, 'lotId' => 'dynamic','defaultId' => 'Bulk Aquatic Macroinvertebrates');
		$collArr[73] = array('targetCollid' => 106, 'lotId' => 'dynamic','defaultId' => 'Plantae');
		//Add option to parse ID from sampleID
		//Process identifications
		$sourceCollid = $dwcArr['collid'];
		if(array_key_exists($sourceCollid, $collArr)){
			$targetCollid = $collArr[$sourceCollid]['targetCollid'];
			$baseID = array('sciname' => 'undefined');
			if(!empty($dwcArr['identifications'])){
				if($dwcArr['identifications']){
					if(in_array($sourceCollid,array(22,52))){
						$validKeys = $this->subsetTaxonGroup($dwcArr['identifications'], $sourceCollid);
						$dwcArr['identifications'] = array_intersect_key($dwcArr['identifications'], array_flip($validKeys));
						if(empty($dwcArr['identifications'])){
							// delete existing subsamples if now no valid subsamples are found (e.g. no oligochaetes reported in parent of oligochaete sample)
							$sql = 'DELETE FROM omoccurrences
							WHERE occid IN (
								SELECT occidAssociate
								FROM omoccurassociations
								WHERE occid = ' . intval($parentOccid) . '
								AND createdUid = 50
							)';
							$this->conn->query($sql);

							// set identifications for lot sample
							$baseID['sciname'] = $collArr[$sourceCollid]['defaultId'];
						}
					}
					if($dwcArr['identifications']){
						//Evaluate identification cluster to determine which IDs should become subsamples
						$identificationsGrouped = array();
						foreach($dwcArr['identifications'] as $idKey => $idArr){
							//if(!empty($idArr['securityStatus'])) continue;
							if(empty($idArr['sciname'])) continue;
							$dateIdentified = 0;
							if(in_array($sourceCollid,array(22,52,53,57))){
								// use all ids for macroinverts
									$dateIdentified = 1;
							}
							elseif(!empty($idArr['dateIdentified'])){
								//If an actual date exists, extract to be used for grouping IDs
								if(preg_match('/^(\d{4}-\d{2}-\d{2}).*/', $idArr['dateIdentified'], $m)){
									$dateIdentified = $m[1];
								}
							}
							elseif(!$dateIdentified){
								//If date does not exist and identifier does exist, increase group by 1, thus making it a separate and preferred group for subsampling
								if(!empty($idArr['identifiedBy']) && $idArr['identifiedBy'] != 'undefined'){
									$dateIdentified = 1;
								}
							}
							$identificationsGrouped[$dateIdentified][] = $idKey;
						}
						$baseDateIdentified = key($identificationsGrouped);
						//Select group of identifications
						krsort($identificationsGrouped);
						// only most recent otherwise
						$targetIdentifications = current($identificationsGrouped);
						//Subsample records
							echo '<li style="margin-left:30px">Creating/updating ' . count($targetIdentifications) . ' subSample records ... </li>';
						$currentSubsampleArr = $this->getSubSamples($parentOccid);
						$allSubOccids = array_keys($currentSubsampleArr);
						$associationArr = array();
						$tidArr = array();
						foreach($targetIdentifications as $identificationKey){
							$identArr = $dwcArr['identifications'][$identificationKey];
							unset($dwcArr['identifications'][$identificationKey]);
							if(!empty($identArr['tidInterpreted'])){
								//TIDs are used to determine common taxonomic node
								$tidArr[$identArr['tidInterpreted']] = $identArr['tidInterpreted'];
							}
							$dwcArrClone = $dwcArr;
							$dwcArrClone['collid'] = $targetCollid;
							$identArr['isCurrent'] = 1;
							$dwcArrClone['identifications'] = array($identArr);
							if(!empty($identArr['subsampleIndividualCount'])){
								$dwcArrClone['individualCount']=$identArr['subsampleIndividualCount'];
							}
							if(!empty($identArr['subsampleLifeStage'])){
								$dwcArrClone['lifeStage']=$identArr['subsampleLifeStage'];
							}
							unset($dwcArrClone['associations']);
							unset($dwcArrClone['identifiers']);
							$existingOccid = 0;
							foreach($currentSubsampleArr as $subOccid => $subUnitArr){
								if(
									$identArr['sciname'] == $subUnitArr['sciname'] &&
									(!isset($identArr['subsampleIndividualCount']) || $identArr['subsampleIndividualCount'] == $subUnitArr['individualCount']) &&
									(!isset($identArr['subsampleLifeStage']) || $identArr['subsampleLifeStage'] == $subUnitArr['lifeStage'])
								){
									//Subsample exists, thus set occid so that subsample is updated rather than creating a new one
									$existingOccid = $subOccid;
									unset($currentSubsampleArr[$subOccid]);
									break;
								}
							}
							//Add parent identifiers as additional identifiers (aka otherCatalogNumbers)
							//Catalog numbers can't be transferred at this point because they are assigned well after the parent samples are created,
							//thus we'll add this to the Stored Procedure that runs at the end of harvesting (aka occurrence_harvesting_sql)
							if(!empty($dwcArr['identifiers']['NEON sampleCode (barcode)'])){
								$dwcArrClone['identifiers']['Originating NEON barcode'] = $dwcArr['identifiers']['NEON sampleCode (barcode)'];
							}
							if(!empty($dwcArr['identifiers']['NEON sampleID'])){
								$dwcArrClone['identifiers']['Originating NEON sampleID'] = $dwcArr['identifiers']['NEON sampleID'];
							}
							if(!empty($dwcArr['identifiers']['NEON sampleID Hash'])){
								$dwcArrClone['identifiers']['Originating NEON sampleID Hash'] = $dwcArr['identifiers']['NEON sampleID Hash'];
							}
							if($datasetName = $this->getDatasetName($targetCollid)){
								// use dataset name of destination collection, if it exists, otherwise it will just maintain the parent datasetName
								$dwcArrClone['verbatimAttributes'] = $datasetName;
							}
							//Load subsample into database
							$occid = $this->loadOccurrenceRecord($dwcArrClone, $existingOccid);
							if(!$existingOccid && $occid){
								//Add association to parent record
								$associationArr[] = array('relationship' => 'originatingSampleOf', 'occidAssociate' => $occid);
								$allSubOccids[] = $occid;
							}
						}

						//Add associations between subsamples
						if($allSubOccids) {
							$sharedAssocArr = $this->setSharedOriginAssoc($allSubOccids);
							foreach ($sharedAssocArr as $occid => $assocArr) {
								// Call the setAssociations function with the current occid and its associated array
								$this->setAssociations($occid, $assocArr);
							}
						}
						//Delete all subsamples that are not identified as a subsample import
						$this->deleteSubSamples($currentSubsampleArr);
						//Reset base sample (parent) with new identification unit containing lot ID
						if(!empty($collArr[$sourceCollid]['lotId'])){
							$lotId = $collArr[$sourceCollid]['lotId'];
							if($lotId == 'dynamic'){
								if($idArr){
									if($commonIdArr = $this->getCommonID($tidArr)){
										$baseID['tidInterpreted'] = key($commonIdArr);
										$baseID['sciname'] = current($commonIdArr);
										$baseID['identifiedBy']=$identArr['identifiedBy'];
									}
								}
							}
							else{
								$baseID['sciname'] = $lotId;
							}
						}

						// make the baseID the default lotID if one exists and the current sciname is undefined or empty
						if (($baseID['sciname'] === 'undefined' || empty($baseID['sciname'])) && !empty($collArr[$sourceCollid]['defaultId'])) {
							$baseID['sciname'] = $collArr[$sourceCollid]['defaultId'];
							$baseID['identifiedBy'] = $identArr['identifiedBy'];
							$baseID['tidInterptreted'] = $collArr[$sourceCollid]['defaultId'];
						}

						if($baseDateIdentified) $baseID['dateIdentified'] = $baseDateIdentified;
						if($baseID['dateIdentified'] == 1 || !$baseID['dateIdentified']){
							$baseID['dateIdentified'] = 's.d.';
						}

						//Append associations
						if(isset($dwcArr['associations'])) $associationArr = array_merge($dwcArr['associations'], $associationArr);
						$dwcArr['associations'] = $associationArr;

					}
					// set remaining baseID fields and add to dwcArr
					$baseID['isCurrent'] = 1;
					$baseID['taxonRemarks'] = 'Identification source: harvested from NEON API';
					$dwcArr['identifications'][] = $baseID;
				}
			}
		}
	}

	private function subsetTaxonGroup($dwcIDs, $sourceCollid) {
		$matchingKeys = array();

		if ($dwcIDs && !empty($dwcIDs)) {
			$sql = '';

			if ($sourceCollid == 52) { // oligochaete vials
				$sql = 'SELECT tid FROM taxaenumtree WHERE parenttid = 132169 OR tid = 132169';
			}
			elseif ($sourceCollid == 22) { // chironomid vials
				$sql = 'SELECT tid FROM taxaenumtree WHERE parenttid = 97420 OR tid = 97420';
			}
			// elseif ($sourceCollid == 53) { // slides
			// 	$sql = 'SELECT tid FROM taxaenumtree WHERE parenttid IN (132169,97420) OR tid IN (132169,97420)';
			// }

			if (!empty($sql)) {
				$rs = $this->conn->query($sql);
				$validTids = array();

				// Fetch all the valid taxa
				while ($row = $rs->fetch_assoc()) {
					$validTids[] = $row['tid'];
				}
				$rs->free();

				foreach ($dwcIDs as $key => $idArr) {
					if (!empty($idArr['tidInterpreted']) && in_array($idArr['tidInterpreted'], $validTids)) {
						$matchingKeys[] = $key;
					}
				}
			}
		}
		return $matchingKeys;
	}

	private function getSubSamples($parentOccid) {
		$retArr = array();
		if ($parentOccid) {
			// Return existing subsample occid, if it exists
			$sql = 'SELECT o.occid, o.sciname, o.individualCount, o.lifeStage
					FROM omoccurassociations a
					INNER JOIN omoccurrences o ON a.occidAssociate = o.occid
					WHERE a.occid = ' . intval($parentOccid);
			$rs = $this->conn->query($sql);
			while ($r = $rs->fetch_object()) {
				// Store the data in a structured array
				$retArr[$r->occid] = array(
					'sciname' => $r->sciname,
					'individualCount' => $r->individualCount,
					'lifeStage' => $r->lifeStage,
				);
			}
			$rs->free();
		}
		return $retArr;
	}

	private function setSharedOriginAssoc($allSubOccids) {
		$combinations = [];
		for ($i = 0; $i < count($allSubOccids); $i++) {
			for ($j = $i + 1; $j < count($allSubOccids); $j++) {
				// Only create one direction combination
				$occid1 = $allSubOccids[$i];
				$occid2 = $allSubOccids[$j];

				$combinations[$occid1][] = [
					'occidAssociate' => $occid2,
					'relationship' => 'sharesOriginatingSample'
				];
			}
		}

		return $combinations;
	}

	private function deleteSubSamples($subSampleArr){
		if($subSampleArr){
			$sql = 'DELETE FROM omoccurrences WHERE occid IN(' . implode(',', array_keys($subSampleArr)) . ')';
			$this->conn->query($sql);
		}
	}

	private function getCommonID($idArr){
		$retArr = array();
		if($idArr){
			$tid = 0;
			$sciname = '';
			$tidCnt = count($idArr);
			// Use input tid if there is only one
			if ($tidCnt == 1) {
				$sql = 'SELECT t.tid, t.sciname FROM taxa t WHERE t.tid = ' . key($idArr);
				$rs = $this->conn->query($sql);
				if ($r = $rs->fetch_object()) {
					$retArr[$r->tid] = $r->sciname;
				}
				$rs->free();
				return $retArr;
			}
			// find common id if there are multiple
			$sql = 'SELECT t.tid, t.sciname, t.rankid, e.parenttid, count(e.tid) as cnt
				FROM taxaenumtree e INNER JOIN taxa t ON e.parenttid = t.tid
				WHERE e.taxauthid = 1 AND e.tid IN(' . implode(',', $idArr) . ') AND t.rankid > 5
				GROUP BY e.parenttid
				ORDER BY t.rankid, cnt DESC';
			$rs = $this->conn->query($sql);
			while ($r = $rs->fetch_object()) {
				$adjustedcnt = $r->cnt;
				// Add one to the count if the current tid is in $idArr
				if (in_array($r->tid, $idArr)) {
					$adjustedcnt += 1;
				}
				// Always update $tid and $sciname if the condition is met
				if ($adjustedcnt >= $tidCnt) {
					$tid = $r->tid;
					$sciname = $r->sciname;
				}
			}
			$rs->free();
			if ($tid) {
				$retArr[$tid] = $sciname;
			}
		}
		return $retArr;
	}

	private function getDatasetName($collid){
		$datasetName = '';
		if(!empty($this->collectionArr[$collid]['datasetName'])){
			$datasetName = $this->collectionArr[$collid]['datasetName'];
		}
		else{
			$sql = 'SELECT datasetName FROM omcollections WHERE collID= ' .$collid;
			$rs = $this->conn->query($sql);
			if($r = $rs->fetch_object()){
				if($r->datasetName) {
					$datasetName = $r->datasetName;
					$this->collectionArr[$collid]['datasetName'] = $datasetName;
				}
			}
			$rs->free();
		}
		return $datasetName;
	}

	private function loadOccurrenceRecord($dwcArr, $occid = null, $samplePK = null){
		if($dwcArr){
			$domainID = (isset($dwcArr['domainID'])?$dwcArr['domainID']:0);
			$siteID = (isset($dwcArr['siteID'])?$dwcArr['siteID']:0);
			unset($dwcArr['domainID']);
			unset($dwcArr['siteID']);
			if(!isset($dwcArr['identifications'])){
				$sciname = '';
				$tid = '';
				if($dwcArr['collid'] == 5 || $dwcArr['collid'] == 67){
					$sciname = 'Benthic Microbe';
					$tid = 126842;
				}
				if($dwcArr['collid'] == 21 || $dwcArr['collid'] == 61){
					$sciname = 'Bulk Aquatic Macroinvertebrates';
					$tid = 126822;
				}
				if($dwcArr['collid'] == 23){
					$sciname = 'Terrestrial Plant Litterfall';
					$tid = 126842;
				}
				elseif($dwcArr['collid'] == 6 || $dwcArr['collid'] == 68){
					$sciname = 'Surface Water Microbe';
					$tid = 126843;
				}
				elseif($dwcArr['collid'] == 13 || $dwcArr['collid'] == 16 ){
					$sciname = 'Bulk Terrestrial Invertebrates';
					$tid = 126821;
				}
				elseif($dwcArr['collid'] == 18){
					$sciname = 'Bulk Canopy Foliage';
					$tid = 126850;
				}
				elseif($dwcArr['collid'] == 31 || $dwcArr['collid'] == 69){
					$sciname = 'Soil Microbe';
					$tid = 126874;
				}
				elseif($dwcArr['collid'] == 30){
					$sciname = 'Soil';
					$tid = 126845;
				}
				elseif($dwcArr['collid'] == 41){
					$sciname = 'Dry Deposition';
					$tid = 126848;
				}
				elseif($dwcArr['collid'] == 42){
					$sciname = 'Wet Deposition';
					$tid = 126847;
				}
				elseif(in_array($dwcArr['collid'],array(7,8,9,50,73))){
					$sciname = 'Plantae';
					$tid = 4;
				}
				elseif($dwcArr['collid'] == 10||$dwcArr['collid'] == 76){
					$sciname = 'Belowground Biomass';
					$tid = 126849;
				}
				elseif($dwcArr['collid']== 23){
					$sciname = "Terrestrial Plant Litterfall";
					$tid = 126851;
				}
				elseif($dwcArr['collid'] == 60|| $dwcArr['collid'] == 62){
					$sciname = 'Zooplankton';
					$tid = 126824;
				}
				elseif($dwcArr['collid'] == 92){
					$sciname = 'Aquatic Sediments';
					$tid = 131450;
				}
				if($sciname){
					$idDate = 's.d.';
					if(!empty($dwcArr['eventDate'])) $idDate = $dwcArr['eventDate'];
					$dwcArr['identifications'][] = array('sciname' => $sciname,'tidInterpreted'=>$tid, 'identifiedBy' => 'NEON Lab', 'dateIdentified' => $idDate, 'isCurrent' => 1);
				}
			}
			$numericFieldArr = array('collid','decimalLatitude','decimalLongitude','minimumElevationInMeters','maximumElevationInMeters');
			$sql = '';
			$skipFieldArr = array('occid','collid','identifiers','assocmedia','identifications','associations');
			if($occid){
				$currentOccurArr = $this->getCurrentOccurrenceArr($occid);
				if($this->replaceFieldValues){
					//Only replace values that have not yet been explicitly modified
					$skipFieldArr = array_merge($skipFieldArr, $this->getOccurrenceEdits($occid));
				}
				foreach($dwcArr as $fieldName => $fieldValue){
					if(in_array(strtolower($fieldName), $skipFieldArr)) continue;
					if($this->replaceFieldValues){
						if(in_array($fieldName, $numericFieldArr) && is_numeric($fieldValue)){
							$sql .= ', '.$fieldName.' = '.$this->cleanInStr($fieldValue).' ';
						}
						else{
							$sql .= ', '.$fieldName.' = "'.$this->cleanInStr($fieldValue).'" ';
						}
						if(array_key_exists($fieldName, $currentOccurArr) && $currentOccurArr[$fieldName] != $fieldValue){
							$this->versionEdit($occid, $fieldName, $currentOccurArr[$fieldName], $fieldValue);
						}
					}
					else{
						if(in_array($fieldName, $numericFieldArr) && is_numeric($fieldValue)){
							$sql .= ', '.$fieldName.' = IFNULL('.$fieldName.','.$this->cleanInStr($fieldValue).') ';
						}
						else{
							$sql .= ', '.$fieldName.' = IFNULL('.$fieldName.',"'.$this->cleanInStr($fieldValue).'") ';
						}
					}
				}
				if($sql) $sql = 'UPDATE omoccurrences SET '.substr($sql, 1).' WHERE (occid = '.$occid.')';
			}
			else{
				$sql1 = ''; $sql2 = '';
				foreach($dwcArr as $fieldName => $fieldValue){
					if(in_array(strtolower($fieldName),$skipFieldArr)) continue;
					$fieldValue = $this->cleanInStr($fieldValue);
					if(in_array($fieldName, $numericFieldArr) && is_numeric($fieldValue)){
						$sql1 .= $fieldName.',';
						$sql2 .= $fieldValue.',';
					}
					else{
						if($fieldValue){
							$sql1 .= $fieldName.',';
							$sql2 .= '"'.trim($fieldValue,',; ').'",';
						}
					}
				}
				$sql = 'INSERT INTO omoccurrences(collid,'.$sql1.'dateentered) VALUES('.$dwcArr['collid'].','.$sql2.'NOW())';
			}
			if($sql){
				if($this->conn->query($sql)){
					if(!$occid){
						$occid = $this->conn->insert_id;
						if($samplePK){
							$this->conn->query('UPDATE NeonSample SET occid = '.$occid.', occidOriginal = IFNULL(occidOriginal,'.$occid.') WHERE (occid IS NULL) AND (samplePK = '.$samplePK.')');
						}
					}
					if($samplePK) $this->conn->query('UPDATE NeonSample SET harvestTimestamp = now() WHERE (samplePK = '.$samplePK.')');
					if(isset($dwcArr['identifiers'])) $this->setOccurrenceIdentifiers($dwcArr['identifiers'], $occid);
					if(isset($dwcArr['assocMedia'])) $this->setAssociatedMedia($dwcArr['assocMedia'], $occid);
					if(isset($dwcArr['identifications'])) $this->setIdentifications($occid, $dwcArr['identifications'],$dwcArr['collid']);
					if(isset($dwcArr['associations'])) $this->setAssociations($occid, $dwcArr['associations']);
					$this->setDatasetIndexing($domainID,$occid);
					$this->setDatasetIndexing($siteID,$occid);
				}
				else{
					$this->errorStr = 'ERROR updating/creating new occurrence record: '.$this->conn->error;
					return false;
				}
			}
		}
		return $occid;
	}

	private function getOccurrenceEdits($occid){
		$retArr = array();
		$sql = 'SELECT DISTINCT fieldname FROM omoccuredits WHERE (uid != 50 OR appliedstatus = 0) AND occid = '.$occid;
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$retArr[] = strtolower($r->fieldname);
		}
		$rs->free();
		//Include identification edits
		$sql = 'SELECT sciname, identifiedBy, dateIdentified FROM omoccurdeterminations WHERE (createdUid IS NULL OR createdUid != 50) AND occid = '.$occid;
		$rs = $this->conn->query($sql);
		if($r = $rs->fetch_object()){
			$retArr[] = 'sciname';
			$retArr[] = 'scientificnameauthorship';
			$retArr[] = 'identifiedby';
			$retArr[] = 'dateidentified';
			$retArr[] = 'taxonremarks';
			$retArr[] = 'identificationremarks';
		}
		$rs->free();
		return $retArr;
	}

	private function versionEdit($occid, $fieldName, $oldValue, $newValue){
		if(strtolower(trim($oldValue ?? '')) != strtolower(trim($newValue ?? '')) && $fieldName != 'coordinateUncertaintyInMeters'){
			$sql = 'INSERT INTO omoccuredits(occid, fieldName, fieldValueOld, fieldValueNew, appliedStatus, uid)
				VALUES('.$occid.',"'.$fieldName.'","'.$this->cleanInStr($oldValue).'","'.$this->cleanInStr($newValue).'", 1, 50)';
			if(!$this->conn->query($sql)){
				$this->errorStr = 'ERROR versioning edit: '.$this->conn->error;
				echo $this->errorStr;
			}
		}
	}

	private function setOccurrenceIdentifiers($idArr, $occid){
		if($idArr && $occid){
			//Do not reset identifiers that were explicitly edited by someone
			$sql = 'SELECT fieldValueOld, fieldValueNew FROM omoccuredits WHERE fieldname IN("omoccuridentifier","omoccuridentifiers") AND uid != 50 AND occid = '.$occid;
			$rs = $this->conn->query($sql);
			while($r = $rs->fetch_object()){
				if($p = strpos($r->fieldValueOld, ': ')) unset($idArr[substr($r->fieldValueOld, 0, $p)]);
				if($p = strpos($r->fieldValueNew, ': ')) unset($idArr[substr($r->fieldValueNew, 0, $p)]);
			}
			$rs->free();
			//Reset identifiers
			$delSql = 'DELETE FROM omoccuridentifiers WHERE identifiername IN("'.implode('","',array_keys($idArr)).'") AND (occid = '.$occid.')';
			$this->conn->query($delSql);
			foreach($idArr as $idName => $idValue){
				if($idValue){
					$sortBy = 'NULL';
					if($idName=='NEON sampleID') $sortBy = 5;
					elseif($idName=='NEON sampleID Hash') $sortBy = 10;
					elseif($idName=='NEON sampleCode (barcode)') $sortBy = 15;
					elseif($idName=='NEON sampleUUID') $sortBy = 20;
					$sql = 'INSERT INTO omoccuridentifiers(occid, identifiername, identifierValue, sortBy)
						VALUES('.$occid.',"'.$this->cleanInStr($idName).'","'.$this->cleanInStr($idValue).'",'.$sortBy.')';
					if(!$this->conn->query($sql)){
						//$this->errorStr = 'ERROR loading occurrence identifiers: '.$this->conn->error;
					}
				}
			}
		}
	}

	private function setAssociatedMedia($assocMedia, $occid){
		if($assocMedia && $occid){
			foreach($assocMedia as $mediaArr){
				$loadMedia = true;
				$sqlTest = 'SELECT url, originalUrl FROM images WHERE occid = '.$occid;
				$rsTest = $this->conn->query($sqlTest);
				while($rTest = $rsTest->fetch_object()){
					if($rTest->originalUrl == $mediaArr['url'] || $rTest->url == $mediaArr['url']){
						$loadMedia = false;
						break;
					}
				}
				$rsTest->free();
				if($loadMedia){
					$sql = 'INSERT INTO images(occid, originalUrl, photographer) VALUES('.$occid.',"'.$mediaArr['url'].'",'.(isset($mediaArr['photographer'])?'"'.$mediaArr['photographer'].'"':'NULL').')';
					if(!$this->conn->query($sql)){
						//$this->errorStr = 'ERROR loading associatedMedia: '.$this->conn->error;
					}
				}
			}
		}
	}

	private function setIdentifications($occid, $identArr,$collID){
		if($occid){
			//Set default values
			foreach($identArr as $k => $v){
				if(empty($v['dateIdentified'])) $identArr[$k]['dateIdentified'] = 's.d.';
				if(empty($v['identifiedBy'])) $identArr[$k]['identifiedBy'] = 'undefined';
			}
			//Remove invalid identifications
			foreach($identArr as $k => $v){
				if(!isset($v['sciname'])) unset($identArr[$k]);
				elseif($v['identifiedBy'] == 'undefined' && $v['dateIdentified'] == 's.d.' && count($identArr) > 1){
					//unset($identArr[$k]);
				}
			}
			//Check to see if a current determination was explicitly set by a collection manager, which thus needs to be maintained as the central current determination
			$currentDetArr = $this->getCurrentDeterminationArr($occid);
			foreach($currentDetArr as $detObj){
				if($detObj['isCurrent'] && $detObj['createdUid'] && $detObj['createdUid'] != 50){
					foreach($identArr as $k => $v){
						if(!empty($v['isCurrent'])) $identArr[$k]['isCurrent'] = 0;
					}
					break;
				}
			}
			//Remove old annotations entered by the occurrence harvester that are not present within new harvest
			$oldID = '';
			$newID = '';
			if($currentDetArr){
				$incomingIsCurrentExists = false;
				foreach($currentDetArr as $detID => $cdArr){
					$deleteDet = true;
					if($cdArr['createdUid'] && $cdArr['createdUid'] != 50){
						$deleteDet = false;
					}
					if($deleteDet){
						foreach($identArr as $idKey => $idArr){
							if($cdArr['sciname'] == $idArr['sciname'] && $cdArr['identifiedBy'] == $idArr['identifiedBy'] && $cdArr['dateIdentified'] == $idArr['dateIdentified']){
								$identArr[$idKey]['updateDetID'] = $detID;
								$deleteDet = false;
							}
							if(!empty($idArr['isCurrent'])) $incomingIsCurrentExists = true;
						}
					}
					if($deleteDet) $this->deleteDetermination($detID);
					else{
						if(!empty($cdArr['isCurrent']) && $incomingIsCurrentExists) $this->updateDetermination(array('updateDetID' => $detID, 'isCurrent' => 0, 'securityStatus' => 0, 'securityStatusReason' => ''));
						elseif(!empty($cdArr['securityStatus'])) $this->updateDetermination(array('updateDetID' => $detID, 'securityStatus' => 0, 'securityStatusReason' => ''));
					}
					if($cdArr['isCurrent']){
						if(!$oldID || !empty($cdArr['securityStatus'])) $oldID = $cdArr['sciname'];
					}
				}
			}
			//Check old IDs against new IDs and unset existing isCurrent determinations
			foreach($identArr as $idArr){
				if(!$oldID){
					if($idArr['identifiedBy'] == 'manifest' || $idArr['identifiedBy'] == 'sampleID') $oldID = $idArr['sciname'];
				}
				if(!empty($idArr['isCurrent'])){
					if(!$newID || !empty($cdArr['securityStatus'])) $newID = $idArr['sciname'];
				}
			}

			if (in_array($collID, [7,8,9,11,12,14,15,17,18,19,20,28,29,39,48,52,53,54,55,70])) {
				if ($oldID && $newID && $oldID != $newID) {

					$sql_old = 'SELECT ts.tidaccepted FROM taxstatus ts
								LEFT JOIN taxa t ON ts.tid = t.tid
								WHERE sciname = "'.$oldID.'"';

					$sql_new = 'SELECT ts.tidaccepted FROM taxstatus ts
								LEFT JOIN taxa t ON ts.tid = t.tid
								WHERE sciname = "'.$newID.'"';

					$result_old = $this->conn->query($sql_old);
					$result_new = $this->conn->query($sql_new);

					$old_tid = $result_old ? $result_old->fetch_assoc() : null;
					$new_tid = $result_new ? $result_new->fetch_assoc() : null;

					if ($old_tid && $new_tid && $old_tid['tidaccepted'] != $new_tid['tidaccepted']) {
						$this->setSampleErrorMessage(
							'occid:'.$occid,
							'Curatorial Check: possible ID conflict (old ID: '.$oldID.'; new ID: '.$newID.')'
						);
					}
				}
			}
			foreach($identArr as $idArr){
				if(($idArr['identifiedBy'] != 'manifest' && $idArr['identifiedBy'] != 'sampleID') || !empty($idArr['isCurrent'])){
					if(empty($idArr['updateDetID'])) $this->insertDetermination($occid, $idArr);
					else $this->updateDetermination($idArr);
					//Following code needed until omoccurdeterminations is activated as central determination source
					if(isset($idArr['isCurrent']) && $idArr['isCurrent'] && (!isset($idArr['securityStatus']) || !$idArr['securityStatus'])){
						$this->updateOccurrence($occid, $idArr);
					}
				}
			}
		}
	}

	private function insertDetermination($occid, $idArr){
		$status = true;
		$scientificName = $idArr['sciname'];
		$tidInterpreted = null;
		if(isset($idArr['tidInterpreted']) && $idArr['tidInterpreted']) $tidInterpreted = $idArr['tidInterpreted'];
		$identifiedBy = $idArr['identifiedBy'];
		$dateIdentified = 's.d.';
		if(isset($idArr['dateIdentified']) && $idArr['dateIdentified']) $dateIdentified = $idArr['dateIdentified'];
		$scientificNameAuthorship = null;
		if(isset($idArr['scientificNameAuthorship']) && $idArr['scientificNameAuthorship']) $scientificNameAuthorship = $idArr['scientificNameAuthorship'];
		$family = null;
		if(isset($idArr['family']) && $idArr['family']) $family = $idArr['family'];
		$taxonRemarks = null;
		if(isset($idArr['taxonRemarks']) && $idArr['taxonRemarks']) $taxonRemarks = $idArr['taxonRemarks'];
		$identificationRemarks = null;
		if(isset($idArr['identificationRemarks']) && $idArr['identificationRemarks']) $identificationRemarks = $idArr['identificationRemarks'];
		$identificationReferences = null;
		if(isset($idArr['identificationReferences']) && $idArr['identificationReferences']) $identificationReferences = $idArr['identificationReferences'];
		$identificationQualifier = null;
		if(isset($idArr['identificationQualifier']) && $idArr['identificationQualifier']) $identificationQualifier = $idArr['identificationQualifier'];
		$securityStatus = 0;
		if(isset($idArr['securityStatus']) && $idArr['securityStatus']) $securityStatus = 1;
		$securityStatusReason = null;
		if(isset($idArr['securityStatusReason']) && $idArr['securityStatusReason']) $securityStatusReason = $idArr['securityStatusReason'];
		$isCurrent = 0;
		if(isset($idArr['isCurrent']) && $idArr['isCurrent']) $isCurrent = 1;
		$createdUid = 50;
		$sql = 'INSERT IGNORE INTO omoccurdeterminations(occid, sciname, tidInterpreted, identifiedBy, dateIdentified, scientificNameAuthorship, family, taxonRemarks,
			identificationRemarks, identificationReferences, identificationQualifier, securityStatus, securityStatusReason, isCurrent, createdUid)
			VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
		if($stmt = $this->conn->prepare($sql)) {
			$stmt->bind_param('isissssssssisii', $occid, $scientificName, $tidInterpreted, $identifiedBy, $dateIdentified, $scientificNameAuthorship, $family, $taxonRemarks,
				$identificationRemarks, $identificationReferences, $identificationQualifier, $securityStatus, $securityStatusReason, $isCurrent, $createdUid);
			$stmt->execute();
			if($stmt->error){
				echo '<li style="margin-left:30px">ERROR adding identification to omoccurdetermination: '.$stmt->error.'</li>';
				$status = false;
			}
			$stmt->close();
		}
		else{
			echo '<li style="margin-left:30px">ERROR preparing statement for adding identification to omoccurdetermination: '.$this->conn->error.'</li>';
			$status = false;
		}
		return $status;
	}

	private function updateDetermination($idArr){
		$status = true;
		$detID = $idArr['updateDetID'];
		unset($idArr['updateDetID']);
		if($detID){
			$sqlFrag = '';
			$typeStr = '';
			$paramArr = array();
			if(!empty($idArr['sciname'])){
				$sqlFrag .= 'sciname = ?, ';
				$typeStr .= 's';
				$paramArr[] = $idArr['sciname'];
			}
			if(isset($idArr['tidInterpreted'])){
				$sqlFrag .= 'tidInterpreted = ?, ';
				$typeStr .= 'i';
				if($idArr['tidInterpreted']) $paramArr[] = $idArr['tidInterpreted'];
				else $paramArr[] = null;
			}
			if(isset($idArr['identifiedBy'])){
				$sqlFrag .= 'identifiedBy = ?, ';
				$typeStr .= 's';
				$paramArr[] = $idArr['identifiedBy'];
			}
			if(isset($idArr['dateIdentified'])){
				$sqlFrag .= 'dateIdentified = ?, ';
				$typeStr .= 's';
				if($idArr['dateIdentified']) $paramArr[] = $idArr['dateIdentified'];
				else $paramArr[] = 's.d.';
			}
			if(isset($idArr['scientificNameAuthorship'])){
				$sqlFrag .= 'scientificNameAuthorship = ?, ';
				$typeStr .= 's';
				if($idArr['scientificNameAuthorship']) $paramArr[] = $idArr['scientificNameAuthorship'];
				else $paramArr[] = null;
			}
			if(isset($idArr['family'])){
				$sqlFrag .= 'family = ?, ';
				$typeStr .= 's';
				if($idArr['family']) $paramArr[] = $idArr['family'];
				else $paramArr[] = null;
			}
			if(isset($idArr['taxonRemarks'])){
				$sqlFrag .= 'taxonRemarks = ?,';
				$typeStr .= 's';
				if($idArr['taxonRemarks']) $paramArr[] = $idArr['taxonRemarks'];
				else $paramArr[] = null;
			}
			if(isset($idArr['identificationRemarks'])){
				$sqlFrag .= 'identificationRemarks = ?, ';
				$typeStr .= 's';
				if($idArr['identificationRemarks']) $paramArr[] = $idArr['identificationRemarks'];
				else $paramArr[] = null;
			}
			if(isset($idArr['identificationReferences'])){
				$sqlFrag .= 'identificationReferences = ?, ';
				$typeStr .= 's';
				if($idArr['identificationReferences']) $paramArr[] = $idArr['identificationReferences'];
				else $paramArr[] = null;
			}
			if(isset($idArr['identificationQualifier'])){
				$sqlFrag .= 'identificationQualifier = ?, ';
				$typeStr .= 's';
				if($idArr['identificationQualifier']) $paramArr[] = $idArr['identificationQualifier'];
				else $paramArr[] = null;
			}
			if(isset($idArr['securityStatus'])){
				$sqlFrag .= 'securityStatus = ?, ';
				$typeStr .= 'i';
				if($idArr['securityStatus']) $paramArr[] = 1;
				else $paramArr[] = 0;
			}
			if(isset($idArr['securityStatusReason'])){
				$sqlFrag .= 'securityStatusReason = ?, ';
				$typeStr .= 's';
				if($idArr['securityStatusReason']) $paramArr[] = $idArr['securityStatusReason'];
				else $paramArr[] = null;
			}
			if(isset($idArr['isCurrent'])){
				$sqlFrag .= 'isCurrent = ?, ';
				$typeStr .= 'i';
				if($idArr['isCurrent']) $paramArr[] = 1;
				else $paramArr[] = 0;
			}
			if($sqlFrag){
				$typeStr .= 'i';
				$paramArr[] = $detID;
				$sql = 'UPDATE omoccurdeterminations SET '.trim($sqlFrag,', ').' WHERE detID = ?';
				if($stmt = $this->conn->prepare($sql)) {
					$stmt->bind_param($typeStr, ...$paramArr);
					$stmt->execute();
					if($stmt->error){
						echo '<li style="margin-left:30px">ERROR updating identification within omoccurdetermination: '.$stmt->error.'</li>';
						$status = false;
					}
					$stmt->close();
				}
				else{
					echo '<li style="margin-left:30px">ERROR preparing statement for updating within omoccurdetermination: '.$this->conn->error.'</li>';
					$status = false;
				}
			}
		}
		return $status;
	}

	private function updateOccurrence($occid, $idArr){
		$status = true;
		$scientificName = $idArr['sciname'];
		$tidInterpreted = null;
		if(isset($idArr['tidInterpreted']) && $idArr['tidInterpreted']) $tidInterpreted = $idArr['tidInterpreted'];
		$identifiedBy = $idArr['identifiedBy'];
		$dateIdentified = 's.d.';
		if(isset($idArr['dateIdentified']) && $idArr['dateIdentified']) $dateIdentified = $idArr['dateIdentified'];
		$scientificNameAuthorship = null;
		if(isset($idArr['scientificNameAuthorship']) && $idArr['scientificNameAuthorship']) $scientificNameAuthorship = $idArr['scientificNameAuthorship'];
		$family = null;
		if(isset($idArr['family']) && $idArr['family']) $family = $idArr['family'];
		$taxonRemarks = null;
		if(isset($idArr['taxonRemarks']) && $idArr['taxonRemarks']) $taxonRemarks = $idArr['taxonRemarks'];
		$identificationRemarks = null;
		if(isset($idArr['identificationRemarks']) && $idArr['identificationRemarks']) $identificationRemarks = $idArr['identificationRemarks'];
		$identificationReferences = null;
		if(isset($idArr['identificationReferences']) && $idArr['identificationReferences']) $identificationReferences = $idArr['identificationReferences'];
		$identificationQualifier = null;
		if(isset($idArr['identificationQualifier']) && $idArr['identificationQualifier']) $identificationQualifier = $idArr['identificationQualifier'];
		$sql = 'UPDATE omoccurrences
			SET sciname = ?, tidInterpreted = ?, identifiedBy = ?, dateIdentified = ?, scientificNameAuthorship = ?, family = ?, taxonRemarks = ?,
			identificationRemarks = ?, identificationReferences = ?, identificationQualifier = ?
			WHERE occid = ?';
		if($stmt = $this->conn->prepare($sql)) {
			$stmt->bind_param('sissssssssi', $scientificName, $tidInterpreted, $identifiedBy, $dateIdentified, $scientificNameAuthorship, $family, $taxonRemarks,
				$identificationRemarks, $identificationReferences, $identificationQualifier, $occid);
			$stmt->execute();
			if($stmt->error){
				echo '<li style="margin-left:30px">ERROR updating current identification within omoccurrences table: '.$stmt->error.'</li>';
				$status = false;
			}
			$stmt->close();
		}
		else{
			echo '<li style="margin-left:30px">ERROR preparing statement for updating identification within omoccurrences: '.$this->conn->error.'</li>';
			$status = false;
		}
		return $status;
	}

	private function deleteDetermination($detid){
		if(is_numeric($detid)){
			$sql = 'DELETE FROM omoccurdeterminations WHERE detid = ?';
			if($stmt = $this->conn->prepare($sql)){
				$stmt->bind_param('i', $detid);
				$stmt->execute();
				if($stmt->error){
					$this->errorStr = 'ERROR deteling determination (#'.$detid.'):'.$this->conn->error;
				}
				$stmt->close();
			}
		}
	}

	private function setAssociations($occid, $assocArr){
		$status = true;
		foreach($assocArr as $assocUnit){
			$occidAssociate = null;
			if(!empty($assocUnit['occidAssociate'])) $occidAssociate = $assocUnit['occidAssociate'];
			$scientificName = null;
			if(!empty($assocUnit['verbatimSciname'])) $scientificName = $assocUnit['verbatimSciname'];
			$tid = null;
			if(!empty($assocUnit['tidInterpreted'])) $tid = $assocUnit['tidInterpreted'];
			$relationship = $assocUnit['relationship'];
			$sql = 'INSERT IGNORE INTO omoccurassociations(occid, occidAssociate, associationType, verbatimSciname, tid, relationship, createdUid) VALUES(?, ?, "internalOccurrence", ?, ?, ?, 50)';
			if($stmt = $this->conn->prepare($sql)) {
				$stmt->bind_param('iisis', $occid, $occidAssociate, $scientificName, $tid, $relationship);
				$stmt->execute();
				if($stmt->error){
					echo '<li style="margin-left:30px">ERROR inserting occurrence association: '.$stmt->error.'</li>';
					$status = false;
				}
				$stmt->close();
			}
			else{
				echo '<li style="margin-left:30px">ERROR preparing statement for inserting occurrence association: '.$this->conn->error.'</li>';
				$status = false;
			}
		}
		return $status;
	}

	private function setDatasetIndexing($datasetName, $occid) {
		if ($datasetName && $occid) {
			// get dataset id
			$datasetID = null;
			$selectDatasetID = 'SELECT datasetid FROM omoccurdatasets WHERE name = "'.$datasetName.'"';

			$result = $this->conn->query($selectDatasetID);
			if ($result && $row = $result->fetch_assoc()) {
				$datasetID = $row['datasetid'];
			}

			if (!$datasetID) {
				$this->errorStr = 'ERROR: Dataset "'.$datasetName.'" not found.';
				return;
			}

			if ($datasetID <= 20){
				// Delete existing entries for the given occid, if necesary
				$deleteSql = 'DELETE FROM omoccurdatasetlink
						  WHERE occid = '.$occid.'
						  AND datasetid != '.$datasetID.'
						  AND datasetid <=20';

				if (!$this->conn->query($deleteSql)) {
					$this->errorStr = 'ERROR deleting unmatched entries for occid '.$occid.': '.$this->conn->errno.' - '.$this->conn->error;
					return;
				}
			}

			if ($datasetID >= 33 AND $datasetID <=133){
				// Delete existing entries for the given occid, if necesary
				$deleteSql = 'DELETE FROM omoccurdatasetlink
						  WHERE occid = '.$occid.'
						  AND datasetid != '.$datasetID.'
						  AND datasetid >=33 AND datasetid <=133';

				if (!$this->conn->query($deleteSql)) {
					$this->errorStr = 'ERROR deleting unmatched entries for occid '.$occid.': '.$this->conn->errno.' - '.$this->conn->error;
					return; // Stop execution if there's an error with the DELETE
				}
			}

			// Insert the correct datasetID and occid if not already present
			$insertSql = 'INSERT IGNORE INTO omoccurdatasetlink (datasetid, occid)
						  VALUES ('.$datasetID.', '.$occid.')';

			if (!$this->conn->query($insertSql)) {
				if ($this->conn->errno != 1062) {
					$this->errorStr = 'ERROR assigning occurrence to '.$datasetName.' dataset: '.$this->conn->errno.' - '.$this->conn->error;
				}
			}
		}
	}

	private function getCurrentOccurrenceArr($occid){
		$retArr = array();
		if($occid){
			$sql = 'SELECT catalogNumber, occurrenceID, eventID, recordedBy, eventDate, eventDate2, individualCount,
				reproductiveCondition, sex, lifeStage, associatedTaxa, occurrenceRemarks, preparations, dynamicProperties, verbatimAttributes, habitat, country,
				stateProvince, county, locality, locationID, decimalLatitude, decimalLongitude, coordinateUncertaintyInMeters,
				verbatimCoordinates, georeferenceSources, minimumElevationInMeters, maximumElevationInMeters, verbatimElevation
				FROM omoccurrences WHERE occid = '.$occid;
			$rs = $this->conn->query($sql);
			$retArr = $rs->fetch_assoc();
			$rs->free();
		}
		return $retArr;
	}

	private function getCurrentOccurrenceID($occid){
		$occurrenceID = 0;
		if($occid){
			$sql = 'SELECT occurrenceID FROM omoccurrences WHERE occid = '.$occid;
			$rs = $this->conn->query($sql);
			if($r = $rs->fetch_object()){
				$occurrenceID = $r->occurrenceID;
			}
			$rs->free();
		}
		return $occurrenceID;
	}

	private function getCurrentDeterminationArr($occid){
		$retArr = array();
		if($occid){
			$sql = 'SELECT detid, sciname, scientificNameAuthorship, taxonRemarks, identifiedBy, dateIdentified,
				identificationRemarks, identificationReferences, identificationQualifier, isCurrent, createdUid
				FROM omoccurdeterminations WHERE occid = '.$occid;
			$rs = $this->conn->query($sql);
			while($r = $rs->fetch_assoc()){
				$retArr[$r['detid']] = $r;
			}
			$rs->free();
		}
		return $retArr;
	}

	private function getNeonApiArr($url){
		$retArr = array();
		//echo 'url: ' . $url . '(' . date('Y-m-d H:i:s') . ')<br/>';
		if($url){
			//Request URL example: https://data.neonscience.org/api/v0/locations/TOOL_073.mammalGrid.mam
			$json = @file_get_contents($url);
			//echo 'json1: '.$json; exit;

			/*
			//curl -X GET --header 'Accept: application/json' 'https://data.neonscience.org/api/v0/locations/TOOL_073.mammalGrid.mam'
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_PUT, 1);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Accept: application/json') );
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			$json = curl_exec($curl);
			*/
			if($json){
				$resultArr = json_decode($json,true);
				if(isset($resultArr['data'])){
					$retArr = $resultArr['data'];
				}
				elseif(isset($resultArr['error'])){
					$this->errorStr = 'ERROR thrown accessing NEON API: url ='.$url;
					if(isset($resultArr['error']['status'])) '; '.$this->errorStr .= $resultArr['error']['status'];
					if(isset($resultArr['error']['detail'])) '; '.$this->errorStr .= $resultArr['error']['detail'];
					$retArr = false;
				}
				else{
					$this->errorStr = 'ERROR retrieving NEON data: '.$url;
					$retArr = false;
				}
			}
			else{
				//$this->errorStr = 'ERROR: unable to access NEON API: '.$url;
				$retArr = false;
			}
			//curl_close($curl);
		}
		return $retArr;
	}

	private function translateAssociatedTaxa($inStr){
		$retStr = '';
		$taxaCodeArr = explode('|', $inStr);
		foreach($taxaCodeArr as $strFrag){
			$taxaArr = $this->translateTaxonCode($strFrag);
			if(!empty($taxaArr['sciname'])) $retStr .= ', ' . trim($taxaArr['sciname']);
			else $retStr .= ', ' . $strFrag;
		}
		return trim($retStr, ', ');
	}

	private function translateTaxonCode($taxonCode){
		$retArr = array();
		$taxonGroup = $this->getTaxonGroup($this->activeCollid);
		$taxonCode = trim($taxonCode);
		if($taxonCode && $taxonGroup){
			if(!isset($this->taxonCodeArr[$taxonGroup][$taxonCode])){
				$tid = 0;
				$sciname = '';
				$sql = 'SELECT t.tid, n.sciname, n.scientificNameAuthorship, n.family
					FROM neon_taxonomy n LEFT JOIN taxa t ON n.sciname = t.sciname
					WHERE n.taxonGroup = "'.$this->cleanInStr($taxonGroup).'" AND n.taxonCode = "'.$this->cleanInStr($taxonCode).'"';
				if($rs = $this->conn->query($sql)){
					while($r = $rs->fetch_object()){
						$tid = $r->tid;
						$sciname = $r->sciname;
						$this->taxonCodeArr[$taxonGroup][$taxonCode]['tid'] = $tid;
						$this->taxonCodeArr[$taxonGroup][$taxonCode]['sciname'] = $sciname;
						$this->taxonCodeArr[$taxonGroup][$taxonCode]['author'] = $r->scientificNameAuthorship;
						$this->taxonCodeArr[$taxonGroup][$taxonCode]['family'] = $r->family;
					}
					$rs->free();
				}
				else echo 'ERROR populating taxonomy codes: '.$sql;
				if(!$tid && $sciname){
					//Verify name via Catalog of Life and if valid, add to thesaurus
					$harvester = new TaxonomyHarvester();
					$harvester->setKingdomName($this->getKingdomName());
					$harvester->setTaxonomicResources(array('col'));
					if($newTid = $harvester->processSciname($sciname)){
						$this->taxonCodeArr[$taxonGroup][$taxonCode]['tid'] = $newTid;
					}
				}
			}
			if(isset($this->taxonCodeArr[$taxonGroup][$taxonCode])){
				$retArr['tidInterpreted'] = $this->taxonCodeArr[$taxonGroup][$taxonCode]['tid'];
				$retArr['sciname'] = $this->taxonCodeArr[$taxonGroup][$taxonCode]['sciname'];
				$retArr['scientificNameAuthorship'] = $this->taxonCodeArr[$taxonGroup][$taxonCode]['author'];
				$retArr['family'] = $this->taxonCodeArr[$taxonGroup][$taxonCode]['family'];
			}
		}
		return $retArr;
	}

	private function getTaxonGroup($collid){
		$taxonGroup = array( 46 => 'ALGAE', 47 => 'ALGAE', 49 => 'ALGAE', 50 => 'ALGAE',  73 => 'ALGAE',  98 => 'ALGAE', 105 => 'ALGAE',  106 => 'ALGAE', 110 => 'ALGAE',  111 => 'ALGAE',
			11 => 'BEETLE', 14 => 'BEETLE', 39 => 'BEETLE', 44 => 'BEETLE', 63 => 'BEETLE', 82 =>'BEETLE', 95 =>'BEETLE',
			20 => 'FISH', 66 => 'FISH',
			12 => 'HERPETOLOGY', 15 => 'HERPETOLOGY', 70 => 'HERPETOLOGY',
			21 => 'MACROINVERTEBRATE', 22 => 'MACROINVERTEBRATE', 45 => 'MACROINVERTEBRATE', 48 => 'MACROINVERTEBRATE', 52 => 'MACROINVERTEBRATE', 53 => 'MACROINVERTEBRATE', 55 => 'MACROINVERTEBRATE', 57 => 'MACROINVERTEBRATE', 60 => 'MACROINVERTEBRATE', 61 => 'MACROINVERTEBRATE', 62 => 'MACROINVERTEBRATE', 84 => 'MACROINVERTEBRATE', 100 => 'MACROINVERTEBRATE', 101 => 'MACROINVERTEBRATE', 102 => 'MACROINVERTEBRATE', 103 => 'MACROINVERTEBRATE',
			29 => 'MOSQUITO', 56 => 'MOSQUITO', 58 => 'MOSQUITO', 59 => 'MOSQUITO', 65 => 'MOSQUITO',
			7 => 'PLANT', 8 => 'PLANT', 9 => 'PLANT', 18 => 'PLANT', 40 => 'PLANT', 54 => 'PLANT', 107 => 'PLANT', 108 => 'PLANT', 109 => 'PLANT',
			17 => 'SMALL_MAMMAL', 19 => 'SMALL_MAMMAL', 24 => 'SMALL_MAMMAL', 25 => 'SMALL_MAMMAL', 26 => 'SMALL_MAMMAL', 27 => 'SMALL_MAMMAL', 28 => 'SMALL_MAMMAL', 64 => 'SMALL_MAMMAL', 71 => 'SMALL_MAMMAL', 74 => 'SMALL_MAMMAL', 90 => 'SMALL_MAMMAL', 91 => 'SMALL_MAMMAL',
			30 => 'SOIL', 79 => 'SOIL', 80 =>'SOIL',
			75 => 'TICK', 83 => 'TICK'
		);
		if(array_key_exists($collid, $taxonGroup)) return $taxonGroup[$collid];
		return false;
	}

	private function getKingdomName(){
		if(in_array($this->activeCollid, array(4,11,12,13,14,15,16,17,19,20,21,22,24,25,26,27,28,29,39,44,48,52,53,56,57,58,59,61,63,64,65,66,70,71,74,75,82,83,84,85,90,91,95,97,100,102,102,101,103 ))) return 'Animalia';
		elseif(in_array($this->activeCollid, array( 7,8,9,10,18,23,40,54,76,93,98,107,108,109 ))) return 'Plantae';
		//Let's use Plantae for algae group, which works for now
		elseif(in_array($this->activeCollid, array( 46,49,50,73,105,106 ))) return 'Plantae';
		elseif(in_array($this->activeCollid, array( 30,79,80 ))) return 'Soil';

		return '';
	}

	private function getTaxonArr($sciname){
		if(substr($sciname, -4) == ' sp.') $sciname = trim(substr($sciname, 0, strlen($sciname) - 4));
		elseif(substr($sciname, -4) == ' spp.') $sciname = trim(substr($sciname, 0, strlen($sciname) - 5));
		$retArr = $this->getTaxon($sciname);
		if(!$retArr){
			//Parse name in case author is inbedded within taxon
			$scinameArr = TaxonomyUtilities::parseScientificName($sciname, $this->conn);
			if(!empty($scinameArr['sciname'])){
				$sciname = $scinameArr['sciname'];
				if($retArr = $this->getTaxon($sciname)){
					if(!empty($scinameArr['author'])) $retArr['scientificNameAuthorship'] = $scinameArr['author'];
				}
			}
			if(!$retArr){
				//Verify name via Catalog of Life and if valid, add to thesaurus
				$harvester = new TaxonomyHarvester();
				$harvester->setKingdomName($this->getKingdomName());
				$harvester->setTaxonomicResources(array('col'));
				if($harvester->processSciname($sciname)){
					$retArr = $this->getTaxon($sciname);
				}
			}
		}

		return $retArr;
	}

	private function getTaxon($sciname){
		$retArr = array();
		$targetTaxon = '';
		$sciname2 = '';
		if(array_key_exists($sciname, $this->taxonArr)){
			$targetTaxon = $sciname;
		}
		elseif(substr($sciname,-1) == 's'){
			//Soil taxon needs to have s removed from end of word
			$sciname2 = substr($sciname,0,-1);
			if(array_key_exists($sciname2, $this->taxonArr)){
				$targetTaxon = $sciname2;
			}
		}
		if(!$targetTaxon){
			$taxonGroup = $this->getTaxonGroup($this->activeCollid);
			$sql = 'SELECT t.tid, t.sciname, t.author, t.rankid, ts.family, a.tid as acceptedTid, t.taxonGroup as `group`, a.sciname as accepted, a.author as acceptedAuthor
			FROM taxa t
			INNER JOIN taxstatus ts ON t.tid = ts.tid
			INNER JOIN taxa a ON ts.tidAccepted = a.tid
			WHERE ts.taxauthid = 1 AND t.sciname IN("' . $this->cleanInStr($sciname) . '"' . ($this->cleanInStr($sciname2) ? ',"' . $this->cleanInStr($sciname2) . '"' : '') . ')
			ORDER BY
				CASE
					WHEN t.taxonGroup = "' . $taxonGroup . '" THEN 1
					WHEN t.taxonGroup IS NULL THEN 2
					ELSE 3
				END
			LIMIT 1';
			$matchingGroupFound = false;
			$nullGroupFound = false;
			if($rs = $this->conn->query($sql)){
				while($r = $rs->fetch_object()){
					// preferentially choose those of the correct taxon group
					if ($r->group === $taxonGroup) {
						$this->taxonArr[$r->sciname]['tid'] = $r->tid;
						$this->taxonArr[$r->sciname]['author'] = $r->author;
						$this->taxonArr[$r->sciname]['rankid'] = $r->rankid;
						$this->taxonArr[$r->sciname]['family'] = $r->family;
						$this->taxonArr[$r->sciname]['accepted'] = $r->accepted;
						$this->taxonArr[$r->sciname]['acceptedAuthor'] = $r->acceptedAuthor;
						$this->taxonArr[$r->sciname]['acceptedTid'] = $r->acceptedTid;
						$targetTaxon = $r->sciname;
						$matchingGroupFound = true;
					}
					// if no matching taxa from the taxon group, try ones with a null taxon group
					elseif(!$matchingGroupFound && $r->group === null){
						$this->taxonArr[$r->sciname]['tid'] = $r->tid;
						$this->taxonArr[$r->sciname]['author'] = $r->author;
						$this->taxonArr[$r->sciname]['rankid'] = $r->rankid;
						$this->taxonArr[$r->sciname]['family'] = $r->family;
						$this->taxonArr[$r->sciname]['accepted'] = $r->accepted;
						$this->taxonArr[$r->sciname]['acceptedAuthor'] = $r->acceptedAuthor;
						$this->taxonArr[$r->sciname]['acceptedTid'] = $r->acceptedTid;
						$targetTaxon = $r->sciname;
					}
				}
			}
			$rs->free();
		}
		if($targetTaxon){
			$retArr['sciname'] = $targetTaxon;
			$retArr['tidInterpreted'] = $this->taxonArr[$targetTaxon]['tid'];
			$retArr['scientificNameAuthorship'] = $this->taxonArr[$targetTaxon]['author'];
			$retArr['rankid'] = $this->taxonArr[$targetTaxon]['rankid'];
			$retArr['family'] = $this->taxonArr[$targetTaxon]['family'];
			$retArr['accepted'] = $this->taxonArr[$targetTaxon]['accepted'];
		}
		return $retArr;
	}

	private function adjustTaxonomy(){
		// Update tidInterpreted index
		// These statements should no longer be needed since tids are explicitly set upon harvest, but we'll keep to ensure that nothing falls through the cracks
		$sql = 'UPDATE omoccurrences o INNER JOIN taxa t ON o.sciname = t.sciname
			INNER JOIN taxstatus ts ON t.tid = ts.tid
			SET o.tidInterpreted = t.tid
			WHERE o.tidInterpreted IS NULL AND o.family = ts.family';
		if(!$this->conn->query($sql)){
			echo 'ERROR updating occurrence tidInterpreted with family match: '.$sql;
		}
		$sql = 'UPDATE omoccurdeterminations d INNER JOIN taxa t ON d.sciname = t.sciname
			INNER JOIN omoccurrences o ON d.occid = o.occid
			INNER JOIN taxstatus ts ON t.tid = ts.tid
			SET d.tidInterpreted = t.tid
			WHERE d.tidInterpreted IS NULL AND o.family = ts.family';
		if(!$this->conn->query($sql)){
			echo 'ERROR updating determination tidInterpreted with family match: '.$sql;
		}

		$sql = 'UPDATE omoccurrences o INNER JOIN taxa t ON o.sciname = t.sciname SET o.tidinterpreted = t.tid WHERE (o.tidinterpreted IS NULL)';
		if(!$this->conn->query($sql)){
			echo 'ERROR updating tidInterpreted: '.$sql;
		}
		$sql = 'UPDATE omoccurdeterminations d INNER JOIN taxa t ON d.sciname = t.sciname SET d.tidinterpreted = t.tid WHERE (d.tidinterpreted IS NULL)';
		if(!$this->conn->query($sql)){
			echo 'ERROR updating tidInterpreted: '.$sql;
		}

		//Update Mosquito taxa details
		$sql = 'UPDATE omoccurrences o INNER JOIN NeonSample s ON o.occid = s.occid
			INNER JOIN taxa t ON o.sciname = t.sciname
			INNER JOIN taxstatus ts ON t.tid = ts.tid
			SET o.scientificNameAuthorship = t.author, o.tidinterpreted = t.tid, o.family = ts.family
			WHERE (o.collid = 29) AND (o.scientificNameAuthorship IS NULL) AND (o.family IS NULL) AND (ts.taxauthid = 1)';
		if(!$this->conn->query($sql)){
			echo 'ERROR updating occurrence taxonomy codes: '.$sql;
		}
		$sql = 'UPDATE omoccurrences o INNER JOIN omoccurdeterminations d ON o.occid = d.occid
			INNER JOIN NeonSample s ON d.occid = s.occid
			INNER JOIN taxa t ON d.sciname = t.sciname
			INNER JOIN taxstatus ts ON t.tid = ts.tid
			SET d.scientificNameAuthorship = t.author, d.tidinterpreted = t.tid, d.family = ts.family
			WHERE (o.collid = 29) AND (d.scientificNameAuthorship IS NULL) AND (d.family IS NULL) AND (ts.taxauthid = 1)';
		if(!$this->conn->query($sql)){
			echo 'ERROR updating determination taxonomy codes: '.$sql;
		}

		//Run custom stored procedure that performs some special assignment tasks
		if(!$this->conn->query('call occurrence_harvesting_sql()')){
			echo 'ERROR running stored procedure occurrence_harvesting_sql: '.$this->conn->error;
		}

		//Run stored procedure that protects rare and sensitive species
		/*
		if(!$this->conn->query('call sensitive_species_protection()')){
			echo 'ERROR running stored procedure sensitive_species_protection: '.$this->conn->error;
		}
		*/
	}

	private function translatePersonnel($persStr){
		$retStr = $persStr;
		if($persStr == '0000-0000-0000-0000') return '';
		if(array_key_exists($persStr, $this->personnelArr)){
			$retStr = $this->personnelArr[$persStr];
		}
		else{
			//Look to see if string can be translated via NeonPersonnel table
			$sql = "SELECT full_info FROM NeonPersonnel
			WHERE SUBSTRING_INDEX(neon_email, '@', 1) = SUBSTRING_INDEX(?, '@', 1) OR orcid = ?";
			if($stmt = $this->conn->prepare($sql)){
				$stmt->bind_param('ss', $persStr, $persStr);
				$stmt->execute();
				$stmt->bind_result($retStr);
				while($stmt->fetch()){
					$this->personnelArr[$persStr] = $retStr;
				}
				$stmt->close();
			}
		}
		return $retStr;
	}

	private function setStateArr(){
		$sql = 'SELECT DISTINCT s.abbrev, s.statename '.
			'FROM lkupstateprovince s INNER JOIN lkupcountry c ON s.countryId = c.countryId '.
			'WHERE c.iso = "us" ';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$this->stateArr[$r->abbrev] = $r->statename;
		}
		$rs->free();
	}

	private function setSampleClassArr(){
		$status = false;
		$result = $this->getNeonApiArr($this->neonApiBaseUrl.'/samples/supportedClasses?apiToken='.$this->neonApiKey);
		if(isset($result['entries'])){
			foreach($result['entries'] as $classArr){
				$this->sampleClassArr[$classArr['key']] = $classArr['value'];
			}
			$status = true;
		}
		return $status;
	}

	private function setDomainSiteArr(){
		$sql = 'SELECT domainNumber, domainName, siteID, siteName FROM neon_field_sites';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$this->domainSiteArr[$r->domainNumber] = $r->domainName;
			$this->domainSiteArr[$r->siteID] = $r->siteName;
		}
		$rs->free();
	}

	private function setTimezone($state){
		$tzArr = array();
		$tzArr['Alabama'] = 'America/Chicago';
		$tzArr['Alaska'] = 'America/Anchorage';
		$tzArr['Arizona'] = 'America/Phoenix';
		$tzArr['Arkansas'] = 'America/Chicago';
		$tzArr['California'] = 'America/Los_Angeles';
		$tzArr['Colorado'] = 'America/Denver';
		$tzArr['Connecticut'] = 'America/New_York';
		$tzArr['Delaware'] = 'America/New_York';
		$tzArr['District of Columbia'] = 'America/New_York';
		$tzArr['Florida'] = 'America/New_York';
		$tzArr['Georgia'] = 'America/New_York';
		$tzArr['Hawaii'] = 'Pacific/Honolulu';
		$tzArr['Idaho'] = 'America/Denver';
		$tzArr['Illinois'] = 'America/Chicago';
		$tzArr['Indiana'] = 'America/New_York';
		$tzArr['Iowa'] = 'America/Chicago';
		$tzArr['Kansas'] = 'America/Chicago';
		$tzArr['Kentucky'] = 'America/Chicago';
		$tzArr['Louisiana'] = 'America/Chicago';
		$tzArr['Maine'] = 'America/New_York';
		$tzArr['Maryland'] = 'America/New_York';
		$tzArr['Massachusetts'] = 'America/New_York';
		$tzArr['Michigan'] = 'America/New_York';
		$tzArr['Minnesota'] = 'America/Chicago';
		$tzArr['Mississippi'] = 'America/Chicago';
		$tzArr['Missouri'] = 'America/Chicago';
		$tzArr['Montana'] = 'America/Denver';
		$tzArr['Nebraska'] = 'America/Chicago';
		$tzArr['Nevada'] = 'America/Los_Angeles';
		$tzArr['New Hampshire'] = 'America/New_York';
		$tzArr['New Jersey'] = 'America/New_York';
		$tzArr['New Mexico'] = 'America/Denver';
		$tzArr['New York'] = 'America/New_York';
		$tzArr['North Carolina'] = 'America/New_York';
		$tzArr['North Dakota'] = 'America/Chicago';
		$tzArr['Ohio'] = 'America/New_York';
		$tzArr['Oklahoma'] = 'America/Chicago';
		$tzArr['Oregon'] = 'America/Los_Angeles';
		$tzArr['Pennsylvania'] = 'America/New_York';
		$tzArr['Puerto Rico '] = 'America/Puerto_Rico';
		$tzArr['Rhode Island'] = 'America/New_York';
		$tzArr['South Carolina'] = 'America/New_York';
		$tzArr['South Dakota'] = 'America/Chicago';
		$tzArr['Tennessee'] = 'America/Chicago';
		$tzArr['Texas'] = 'America/Chicago';
		$tzArr['Utah'] = 'America/Denver';
		$tzArr['Vermont'] = 'America/New_York';
		$tzArr['Virginia'] = 'America/New_York';
		$tzArr['Washington'] = 'America/Los_Angeles';
		$tzArr['West Virginia'] = 'America/New_York';
		$tzArr['Wisconsin'] = 'America/Chicago';
		$tzArr['Wyoming'] = 'America/Denver';
		if($state && !empty($tzArr[$state])) $this->timezone = $tzArr[$state];
	}

	private function setSampleErrorMessage($id, $msg){
		$sql = 'UPDATE NeonSample SET errorMessage = CONCAT_WS("; ", "'.$this->cleanInStr($msg).'", errorMessage) ';
		if(!$msg) $sql = 'UPDATE NeonSample SET errorMessage = NULL ';
		if(substr($id, 0, 6) == 'occid:') $sql .= 'WHERE (occid = '.substr($id, 6).') ';
		else $sql .= 'WHERE (samplePK = '.$id.') ';
		if(!$msg) $sql .= 'AND errorMessage NOT LIKE "Curatorial Check:%"';
		$this->conn->query($sql);
	}

	//General data return functions
	public function getTargetCollectionArr(){
		$retArr = array();
		$sql = 'SELECT DISTINCT c.collid, CONCAT(c.collectionName, " (",CONCAT_WS(":",c.institutionCode,c.collectionCode),")") as name
			FROM omcollections c INNER JOIN omoccurrences o ON c.collid = o.collid INNER JOIN NeonSample s ON o.occid = s.occid
			WHERE c.institutioncode = "NEON"';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$retArr[$r->collid] = $r->name;
		}
		$rs->free();
		return $retArr;
	}

	//Setters and getters
	public function setReplaceFieldValues($bool){
		if($bool) $this->replaceFieldValues = true;
	}

	public function getErrorStr(){
		return $this->errorStr;
	}

	//Misc functions
	private function formatDate($dateStr){
		if(preg_match('/^(20\d{2})-(\d{2})-(\d{2})T\d{2}/', $dateStr)){
			//UTC datetime
			$dt = new DateTime($dateStr, new DateTimeZone('UTC'));
			$dt->setTimezone(new DateTimeZone($this->timezone));
			$dateStr = $dt->format('Y-m-d');
		}
		elseif(preg_match('/^(20\d{2})-(\d{2})-(\d{2})\D*/', $dateStr, $m)) $dateStr = $m[1].'-'.$m[2].'-'.$m[3];
		elseif(preg_match('/^(20\d{2})(\d{2})(\d{2})\D+/', $dateStr, $m)) $dateStr = $m[1].'-'.$m[2].'-'.$m[3];
		elseif(preg_match('/^(\d{1,2})\/(\d{1,2})\/(20\d{2})/', $dateStr, $m)){
			$month = $m[1];
			if(strlen($month) == 1) $month = '0'.$month;
			$day = $m[2];
			if(strlen($day) == 1) $day = '0'.$day;
			$dateStr = $m[3].'-'.$month.'-'.$day;
		}
		return $dateStr;
	}

	private function cleanInStr($str){
		$newStr = trim($str ?? '');
		$newStr = preg_replace('/\s\s+/', ' ',$newStr);
		$newStr = $this->conn->real_escape_string($newStr);
		return $newStr;
	}
}
?>