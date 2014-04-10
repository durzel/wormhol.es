<?
	ini_set('set_time_limit',120);
	ini_set('max_execution_time',120);
	ini_set('memory_limit','256M');

	class Killboard {
		private $isValidKB = false;
		private $uniqID = null;
		
		public $kill = array();
		
		// We keep track of various stats on each corporation that has been active in some capacity in the system.
		// We don't keep track (outside of the kill data) of individual pilot or alliance activity simply because
		// this information is not important to us for diagnosing intel.
		public $corp = array();
		
		// Values that keep track of various metrics that we use elsewhere
		public $res_metrics = array(
			"totalScore" => 0,
			"minScore" => null,
			"maxScore" => 0,
			"topCorp" => null,
			"bottomCorp" => null,
			"newestKill" => null,
			"oldestKill" => null,
			"battleCount" => 0,
			"alliance" => array()
		);
		
		public $ctx;
		
		// The constructor takes as input the JSON output of an Eve-Kill EPIC API scrape
		public function __construct($uniqID, $kbCacheID, $kbCacheTime, $kbURL, $useCache = CACHE_USE_CACHE_UPDATE_NEW_DATA, $doCalcRes = true) {
			$this->uniqID = $uniqID;
			// Stream context for file_get_contents
			$this->ctx = stream_context_create(array('http' => array('timeout' => EVEKILL_SOCKET_TIMEOUT_SECONDS, 'ignore_errors' => true)));
			
			$jsonEKData = self::scrapeEveKill($uniqID, $kbCacheID, $kbCacheTime, $kbURL, $useCache);
			// If this fails, try and forcibly use the cache
			if (!is_array($jsonEKData)) $jsonEKData = self::scrapeEveKill($uniqID, $kbCacheID, $kbCacheTime, $kbURL, CACHE_FORCE_USE_CACHE);
			if (is_array($jsonEKData)) {
				
				// We have a valid killboard
				$this->isValidKB = true;
				
				$cacheFile = sprintf("%s/%s_kbdata.dat", realpath(CACHE_DIRECTORY), str_replace(" ","_",strtoupper($uniqID)));
				if ($jsonEKData["used_cache"] && is_readable($cacheFile) && CACHE_KILLBOARD_DATA_CACHE) {
					dprintf("[Killboard] Re-initialising killboard with cached data from %s (created: %s)", $cacheFile, date("Y-m-d H:i:s", filemtime($cacheFile)));
					// Initialise this object with the data from the cache
					$cacheKB = unserialize(gzinflate(file_get_contents($cacheFile)));
					$this->kill = $cacheKB->kill;
					$this->corp = $cacheKB->corp;
					$this->res_metrics = $cacheKB->res_metrics;										
					unset($cacheKB);
				} else {
					dprintf("[Killboard] Killboard being created from scratch, cache not used or cache file does not exist.");
					foreach ($jsonEKData["ekdata"] as $aKill) {
						$this->kill[] = new Kill($aKill, $this);	
					}
					
					// -------------------------------------------------------------------------------------------
					// We now carry out some corp sub-database tidyup, since this is the database that we will use 
					// to provide intel
					
					// Populate the corp catalogue with missing IDs by polling the database cache, and if
					// that fails getting it from the EVE API.
					$aryMissingIDName = array();
					foreach ($this->corp as $aCorp) {
						// Sanitise corp/alliance name if it is invalid
						$this->fixBrokenEKData($aCorp);
						
						if (is_null($aCorp->corporationID) && !in_array($aCorp->corporationName,$aryMissingIDName)) $aryMissingIDName[] = $aCorp->corporationName;
						if (is_null($aCorp->allianceID) && !in_array($aCorp->allianceName,$aryMissingIDName)) $aryMissingIDName[] = $aCorp->allianceName;
					}
					if (sizeof($aryMissingIDName) > 0) {
						$idArray = $this->getIDOrNameFromEveAPI($aryMissingIDName);
						if (!is_null($idArray)) {
							// Update our corporation database with our newly found IDs
							
							// We have to create a temporary array to uppercase the entries because the database
							// writes the EVE API result as the names are in game, which is not necessarily what 
							// they will be on the kills (strange but true)
							$ucArray = $idArray;
							for ($n = 0; $n < sizeof($ucArray); $n++) { $ucArray[$n] = strtoupper($ucArray[$n]["name"]); };
							
							// Loop through the corps we have stored, and update the IDs as we find a match
							foreach ($this->corp as $aCorp) {
								if (($nKey = array_search(strtoupper($aCorp->corporationName), $ucArray)) !== false) $aCorp->corporationID = $idArray[$nKey]["id"];
								if (($nKey = array_search(strtoupper($aCorp->allianceName), $ucArray)) !== false) $aCorp->allianceID = $idArray[$nKey]["id"];
							}
							unset($ucArray);
						}
					}
					// -------------------------------------------------------------------------------------------	
					
					// Update metrics (these are required for calcResidency)
					$this->res_metrics["newestKill"]	= &$this->getSysNewestKill();
					$this->res_metrics["oldestKill"]	= &$this->getSysOldestKill();
					$this->res_metrics["battleCount"]	= $this->getTotalBattleCount();

					if ($doCalcRes) {
						$aTempWH = new Wormhole($this->uniqID);
						if ($aTempWH->isValidLocus() && $aTempWH->isWHLocus()) {
							// Calculate residency scores
							$this->calcResidency();
							
							// Update score metrics
							// Note: Total residency score is populated by calcResidency(), because it uses it
							$this->res_metrics["maxScore"] 		= $this->getHighestResidencyScore();
							$this->res_metrics["minScore"] 		= $this->getLowestResidencyScore();
							$this->res_metrics["topCorp"]		= &$this->getTopResident();
							$this->res_metrics["bottomCorp"]	= &$this->getBottomResident();
						}
						// Write killboard cache to disk
						if (file_exists($cacheFile)) unlink($cacheFile);
						file_put_contents($cacheFile, gzdeflate(serialize($this)));
					}
				}
			}
		}
		
		// This function scrapes Eve-kill until we have the last EVEKILL_KILL_COUNT_FOR_INTEL kills with which 
		// to do analysis.  Because this scraping is time and resource intensive, and at risk of causing 
		// blacklisting, we save the results to a cache file.  If this cache file exists and has not expired 
		// we use that instead of polling eve-kill at all.
		private function scrapeEveKill($locusID, $kbCacheID, $kbCacheTime, $kbURL, $cacheUse) {
			$ekData = null;
			
			// Check to see whether cache file exists
			$haveCache = false;
			$addedNewRecords = false;
			
			/* If we're not using EveKill, we force using the cache (if it doesn't exist we won't have intel) */
			if (DISABLE_EVEKILL_COMPLETELY) $cacheUse = CACHE_FORCE_USE_CACHE;
			
			dprintf('scrapeEveKill(): cache time: %d, cur time: %d, cacheUse: %d, kbURL: %s', $kbCacheTime, time(), $cacheUse, $kbURL);
			if ($cacheUse != CACHE_FORCE_FULL_REFRESH) {
				$cacheFile = sprintf("%s/%s_%s.json", realpath(CACHE_DIRECTORY), str_replace(" ","_",strtoupper($locusID)), strtolower($kbCacheID));
				
				// Get mail limit boundary (used in loops)
				$foundMailLimit = preg_match('/mailLimit:(\d+)/', $kbURL, $sMailLimit);
				$sMailLimit = ($foundMailLimit === 0) ? EVEKILL_KILL_COUNT_FOR_INTEL : $sMailLimit[1];
				
				if (is_readable($cacheFile)) {
					dprintf('scrapeEveKill(): cache file exists');
					$ekData = json_decode(gzinflate(file_get_contents($cacheFile, false, $this->ctx)));
					if (!is_array($ekData) || empty($ekData)) {
						// Cache is completely empty, so disregard it
						$cacheUse = CACHE_FORCE_FULL_REFRESH;	
					} elseif ($cacheUse == CACHE_FORCE_USE_CACHE || (strtotime("+".$kbCacheTime." seconds", filemtime($cacheFile)) - time()) > 0) {
						// Found a valid cache file
						$haveCache = true;
						dprintf('scrapeEveKill(): cache file is fresh (%d secs old) or force cache use on (%d?)', (time() - filemtime($cacheFile)), $cacheUse == CACHE_FORCE_USE_CACHE);
					} else {		
						dprintf('scrapeEveKill(): cache file is expired');		
						if ($cacheUse == CACHE_USE_CACHE_REFRESH_IF_EXPIRED) {
							dprintf('scrapeEveKill(): forcing complete refresh of cache (CACHE_USE_CACHE_REFRESH_IF_EXPIRED is on)');	
							unlink($cacheFile);
							$haveCache = false;
						} elseif ($cacheUse == CACHE_USE_CACHE_UPDATE_NEW_DATA) {
							// Get the most recent kill timestamp in the cache, and built up the data
							// beyond that to refresh it
							$firstKill = current($ekData);
							
							dprintf('scrapeEveKill(): doing partial refresh of cache, starting from %s', $firstKill->timestamp);		
							
							// Sanity check - only refresh if the last kill is actually older than the refrest time
							if (strtotime("+".$kbCacheTime." seconds", strtotime($firstKill->timestamp)) < time()) {
								// Replace the timestamps in the URL to begin at the oldest kill
								// NOTE: THIS WILL MEAN WE WILL GET AT LEAST ONE DUPE WE HAVE TO REMOVE
								
								$foundSDate = preg_match('/startDate:(\d{4}-\d{2}-\d{2}_\d{2}.\d{2}.\d{2})/', $kbURL, $sDate);
								$foundEDate = preg_match('/endDate:(\d{4}-\d{2}-\d{2}_\d{2}.\d{2}.\d{2})/', $kbURL, $eDate);
								if ($foundSDate !== 1 || $foundEDate !== 1) return false;
								$kbURL = str_ireplace('/startDate:' . $sDate[1], '/startDate:' . date("Y-m-d_H.i.s", strtotime($firstKill->timestamp)), $kbURL);
								$kbURL = str_ireplace('/endDate:' . $eDate[1], '/endDate:' . date("Y-m-t_23:59:59", strtotime($firstKill->timestamp)), $kbURL);
								
								// Update temporary cache
								set_error_handler("eve_api_warning_catcher", E_WARNING);
								try {
									$mChkCnt = 0;
									$ekNewData = array();
									dprintf('scrapeEveKill(): starting partial refresh loop');
									do {
										dprintf("[m: %d] EVE API URL: %s", $mChkCnt, $kbURL);
										$ncScrape = file_get_contents($kbURL, false, $this->ctx);
										// Note: we update in reverse order to the way we want to store
										if ($ncScrape !== false) {
											$ekNewData = array_merge(json_decode($ncScrape),$ekNewData);
										} else {
											// Eve-Kill API/website returned something we can't use, assume 
											// it's broken
											restore_error_handler();
											return null;	
										}
										
										// Increment failsafe loop breaker
										$mChkCnt++;
										
										$foundSDate = preg_match('/startDate:(\d{4}-\d{2}-\d{2}_\d{2}.\d{2}.\d{2})/', $kbURL, $sDate);
										$foundEDate = preg_match('/endDate:(\d{4}-\d{2}-\d{2}_\d{2}.\d{2}.\d{2})/', $kbURL, $eDate);
										if ($foundSDate !== 1 || $foundEDate !== 1) break;
											
										$kbURL = str_ireplace('/startDate:' . $sDate[1], '/startDate:' . date("Y-m-01_00.00.00", strtotime("+1 month", convEKD2Pts($sDate[1]))), $kbURL);
										$kbURL = str_ireplace('/endDate:' . $eDate[1], '/endDate:' . date("Y-m-t_23:59:59", strtotime("+1 month", convEKD2Pts($sDate[1]))), $kbURL);
									} while (count($ncScrape) > 0 && convEKD2Pts($eDate[1]) < time() && $mChkCnt <= EVEKILL_ANALYSIS_MAX_MONTH_HISTORY);
									
									// Re-order database by timestamp (since Eve-Kill seems to be useless at it)
									usort($ekNewData, 'tcmp');
									
									// At this point we will have a new temporary cache, and the original kill db cache.
									// Before we join the two we chop off any kills off the top that match the original 
									// database.
									$aCnt = 0;
									foreach($ekNewData as $newKill) {
										if ($newKill->timestamp === $firstKill->timestamp && $newKill->internalID === $firstKill->internalID) {
											array_splice($ekNewData, $aCnt, 1);
										}
										$aCnt++;
									}
									dprintf('scrapeEveKill(): completed partial refresh loop, we have %d new records to append.', sizeof($ekNewData));
									if (sizeof($ekNewData) > 0) {
										// If we still have data to merge, then merge the new data with the old data								
										$ekData = array_merge($ekNewData,$ekData);
										file_put_contents($cacheFile, gzdeflate(json_encode($ekData)), LOCK_EX);
										
										// If we added new records, we need to update the KB cache
										$addedNewRecords = true;
									} else {
										// If we have nothing to add, at least mark that we tried to stop constant cache refresh checks with no new data
										dprintf('scrapeEveKill(): nothing new to add, but touching file to current time.', sizeof($ekNewData));
										touch($cacheFile, time());
									}
									
									// Whether or not we had any new data to append, the cache we now have is valid
									$haveCache = true;	
								} catch (Exception $e) {
									// Problem occured querying Eve Kill, so bomb out
									printf('<p><span class="advisoryBad">Error: Unable to update kill data from EVE KILL - error reported %s.</span></p>', $e->getMessage());
									error_log("EVE API error: " . $e->getMessage(), 0);
								}
								restore_error_handler();
							}
						}
					}
				}
			}
			
			if (!$haveCache) {
				if ($cacheUse != CACHE_FORCE_USE_CACHE) {
					// We don't have a cache file, so we have to poll Eve-Kill then create one
					set_error_handler("eve_api_warning_catcher", E_WARNING);
					try {
						$mChkCnt = 0;
						$ekData = array();
						do {
							dprintf("[m: %d] EVE API URL: %s", $mChkCnt, $kbURL);
							
							//dprintf("scrapeEveKill(): Fetching from URL: %s", $kbURL);
							$ekScrape = file_get_contents($kbURL, false, $this->ctx);
							if ($ekScrape !== false) {
								$ekData = array_merge($ekData,json_decode($ekScrape));
							} else {
								// Eve-Kill API/website returned something we can't use, assume 
								// it's broken
								restore_error_handler();
								return null;	
							}
							
							// Increment failsafe loop breaker
							$mChkCnt++;
							
							$foundSDate = preg_match('/startDate:(\d{4}-\d{2}-\d{2}_\d{2}.\d{2}.\d{2})/', $kbURL, $sDate);
							$foundEDate = preg_match('/endDate:(\d{4}-\d{2}-\d{2}_\d{2}.\d{2}.\d{2})/', $kbURL, $eDate);
							if ($foundSDate !== 1 || $foundEDate !== 1) break;
								
							$kbURL = str_ireplace('/startDate:' . $sDate[1], '/startDate:' . date("Y-m-01_00.00.00", strtotime("-1 month", convEKD2Pts($sDate[1]))), $kbURL);
							$kbURL = str_ireplace('/endDate:' . $eDate[1], '/endDate:' . date("Y-m-t_23:59:59", strtotime("-1 month", convEKD2Pts($sDate[1]))), $kbURL);
						} while (sizeof($ekData) < intval($sMailLimit) && $mChkCnt <= EVEKILL_ANALYSIS_MAX_MONTH_HISTORY);
					} catch (Exception $e) {
						// Problem occured querying Eve Kill, so bomb out
						printf('<p><span class="advisoryBad">Error: Unable to update kill data from EVE KILL - error reported %s.</span></p>', $e->getMessage());
						error_log("EVE API error: " . $e->getMessage(), 0);
					}
					restore_error_handler();
					
					// Write cache file to disk for next run
					dprintf("scrapeEveKill(): Scraping finished, total kills: %d, total months checked: %d", sizeof($ekData), $mChkCnt);

					if (is_array($ekData) && !empty($ekData)) {
						// Re-order database by timestamp (since Eve-Kill seems to be useless at it)
						usort($ekData, 'tcmp');
						
						file_put_contents($cacheFile, gzdeflate(json_encode($ekData)), LOCK_EX);
					} else {
						// Whatever data we have.. is invalid
						return null;	
					}
				} else {
					// We are trying to force using the cache, but we don't have one!
					return null;
				}
			}
			dprintf('scrapeEveKill(): ALL DONE - Used cache? %s, kill count: %d', ($haveCache) ? "yes" : "no", sizeof($ekData));;
			//print_r($ekData);
			return array("ekdata" => $ekData, "used_cache" => ($addedNewRecords) ? false : $haveCache);	
		}
		
		public function getKillCount() { return sizeof($kill); }
		public function isValidKB() { return $this->isValidKB; }
		public function getUniqID() { return $this->uniqID; }
		
		// Return the most recent kill in the database
		// Note: We can't assume the killboard will be in date order, so we'll do it the hard way
		private function &getSysNewestKill() {
			$newTS = 0;
			$newKill = null;
			if (sizeof($this->kill) > 0) {
				foreach ($this->kill as $aKill) {
					if (strtotime($aKill->timestamp) >= $newTS) {
						$newTS = strtotime($aKill->timestamp);
						$newKill = $aKill;
					}
				}
			}
			return $newKill;
		}
		
		// Return the oldest kill in the database
		// Note: We can't assume the killboard will be in date order, so we'll do it the hard way
		private function &getSysOldestKill() {
			$oldTS = time();
			$oldKill = null;
			if (sizeof($this->kill) > 0) {
				foreach ($this->kill as $aKill) {
					if (strtotime($aKill->timestamp) <= $oldTS) {
						$oldTS = strtotime($aKill->timestamp);
						$oldKill = $aKill;
					}
				}
			}
			return $oldKill;
		}
		
		// This function returns a pointer to an instantiated corp for kill updating
		public function &getCorporation($corpName) {
			if (sizeof($this->corp) > 0) {	
				foreach($this->corp as $aCorp) {
					if (strcasecmp(trim($aCorp->corporationName), trim($corpName)) == 0) {
						return $aCorp;
					}
				}
			}
			// Return null pointer reference
			$null = null;
			return $null;
		}
		
		// Returns the total number of corps that are in the alliance that the specified corp is in.
		// Zero is always returned if the corp is not in an alliance.
		public function getAllianceCorpCount($aCorp) {
			if ($aCorp->allianceID == 0 || $aCorp->allianceID == NO_ALLIANCE_ALLIANCE_ID) return 0;
			else return is_null($aAlliance = &$this->getAllianceRM($aCorp->allianceName)) ? 0 : $aAlliance["corp_count"];	
		}
		
		// This function returns a pointer to an instantiated corp for kill updating
		private function &getAllianceRM($allianceName) {
			if (sizeof($this->res_metrics["alliance"]) > 0) {	
				foreach($this->res_metrics["alliance"] as &$aAlliance) {
					if (strcasecmp(trim($aAlliance["alliance_name"]), trim($allianceName)) == 0) {
						return $aAlliance;
					}
				}
			}
			// Return null pointer reference
			$null = null;
			return $null;
		}
		
		// This function returns true if the specified corp looks like they have been or are being
		// evicted (structure losses including a tower within the past X months)
		public function calcEviction($aCorp, $numMonthsToChk = EVEKILL_RECENT_EVICTION_IN_MONTHS) {
			if (sizeof($this->kill) == 0) return null;
			
			// Get the typeIDs for towers
			//$rsTower = mysql_query("SELECT it.typeID FROM ".EVEDB_NAME.".invTypes it WHERE groupID IN (SELECT groupID FROM ".EVEDB_NAME.".invGroups WHERE categoryID = 23)");
			$rsTower = mysql_query_cache("SELECT it.typeID FROM ".EVEDB_NAME.".invTypes it WHERE groupID = 365");
			if (is_array($rsTower)) {
				if (!empty($rsTower)) {
					// Build array of tower IDs
					$towerIDAry = array();
					for ($t = 0; $t < count($rsTower); $t++) {
						$towerIDAry[] = $rsTower[$t]["typeID"];
					}
					$posKill = array();
					$lastPosKill = null;
					foreach($this->kill as $aKill) {
						//dprintf("Checking for tower kill, victim %s = corp %s? (%d), kill TS: %s, is Tower? %d, is Old? %d (months to check: %d)", $aKill->victimCorpName, $aCorp->corporationName, (strcasecmp($aCorp->corporationName, $aKill->victimCorpName) == 0 || (($aCorp->allianceID != 0 && $aCorp->allianceID != NO_ALLIANCE_ALLIANCE_ID) && strcasecmp($aCorp->allianceName, $aKill->victimAllianceName) == 0)), $aKill->timestamp, in_array($aKill->victimShipID, $towerIDAry), (strtotime("+".$numMonthsToChk." months", strtotime($aKill->timestamp)) < time()), $numMonthsToChk);
						if (strtotime("+".($numMonthsToChk+1)." months", strtotime($aKill->timestamp)) < time()) continue;
						
						if (in_array($aKill->victimShipID, $towerIDAry) && (strcasecmp($aCorp->corporationName, $aKill->victimCorpName) == 0)) {
							$posKill[] = $aKill;
							if (is_null($lastPosKill) || strtotime($aKill->timestamp) > strtotime($lastPosKill->timestamp)) $lastPosKill = $aKill;					
						}
					}
					// At this point we know that $aCorp has lost a POS to someone else, but we don't know if it was spurious and they 
					// recovered or not.  To check this we carry on looking through the kills to see if $aCorp still features a lot, if they 
					// do chances are it was spurious.
					if (sizeof($posKill) > 0 && !is_null($lastPosKill)) {
						// We only consider activity outside of that which is probably related to the eviction, as 
						// defined by SCORE_RES_IGNORE_RELATED_ACTIVITY_PERIOD_DAYS
						if (strtotime("+".SCORE_RES_IGNORE_RELATED_ACTIVITY_PERIOD_DAYS." days", strtotime($lastPosKill->timestamp)) < time()) {
							$stillHereWeight = 0;
							$latestKill = $this->res_metrics["newestKill"];
							$sysKillTimeSpan = strtotime($latestKill->timestamp) - strtotime($lastPosKill->timestamp);
							
							// We need to exclude POS modules, since people cleaning those up after a corp has been evicted shouldn't qualify them for residency
							$towerAssetsAry = array();
							$rsTowerAssets = mysql_query_cache(sprintf("SELECT it.typeID FROM ".EVEDB_NAME.".invTypes it WHERE groupID IN (%s,%s)", implode(unserialize(POS_MODULES_STRUCTURES_GROUPIDS),","), implode(unserialize(POS_GUNS_GROUPIDS),",")));
							if (is_array($rsTowerAssets)) {
								if (!empty($rsTowerAssets)) {
									// Build array of tower asset IDs
									$towerAssetsAry = array();
									for ($t = 0; $t < count($rsTower); $t++) {
										$towerAssetsAry[] = $rsTowerAssets[$t]["typeID"]; 
									}
								}
							}
							
							// We use the same exponential decay calculation to apportion weight to a battle that involves this corp.  The more 
							// recent it is (further away from the POS loss) the more weight it has.
							foreach ($aCorp->battle as $aBattle) {
								// Ignore any kill that occurs before the POS died, or for a period (SCORE_RES_IGNORE_RELATED_ACTIVITY_PERIOD_DAYS) afterwards
								if ($aBattle->timestamp <= $lastPosKill->timestamp || strtotime($aBattle->timestamp) <= strtotime("+".SCORE_RES_IGNORE_RELATED_ACTIVITY_PERIOD_DAYS." days", strtotime($lastPosKill->timestamp))) continue;
								dprintf("eviction check: pos kill on: %s, battle on: %s, is POS module and victim? %d, would add score: %f", $lastPosKill->timestamp, $aBattle->timestamp, (strcasecmp($aCorp->corporationName, $aBattle->victimCorpName) == 0 && in_array($aBattle->victimShipID, $towerAssetsAry)), $this->ed($sysKillTimeSpan, SCORE_EXPONENTIAL_DECAY_RATE, (strtotime($latestKill->timestamp) - strtotime($aBattle->timestamp)), SCORE_RES_STILL_RES_BATTLE_SCORE));
								if (!(strcasecmp($aCorp->corporationName, $aBattle->victimCorpName) == 0 && in_array($aBattle->victimShipID, $towerAssetsAry))) {
									$stillHereWeight += $this->ed($sysKillTimeSpan, SCORE_EXPONENTIAL_DECAY_RATE, (strtotime($latestKill->timestamp) - strtotime($aBattle->timestamp)), SCORE_RES_STILL_RES_BATTLE_SCORE);
								}
							}
						}
					}
					return sizeof($posKill) == 0 ? null : array("losses" => $posKill, "ppl_score" => $stillHereWeight); 	// ppl = post pos loss
				}
			}
		}
		
		// A simple function that returns an array of corporations 
		public function getInvolvedCorpsOnKill($aKill) {
			$aryKillers = null;
			$corpNameAry = array();
			foreach($aKill->involved as $aInvolved) {
				if (!in_array($aInvolved->corporationName, $corpNameAry)) {
					$aryKillers[] = &$this->getCorporation($aInvolved->corporationName);
					$corpNameAry[] = $aInvolved->corporationName;
				}
			}
			return $aryKillers;
		}
		
		// This function iterates over the kill database and returns an array of kills where the specified
		// corporation was involved (as involved party or victim)
		public function getKillsInvolvingCorporation($aCorp) {
			$corpName = $aCorp->corporationName;
			if (sizeof($this->kill) == 0) return null;
			return $aCorp->killDB;
		}
		
		// This function returns an array of activity (kills and losses) that involved capital ships
		// Optionally if corporationID is supplied it will return the kills that this corporation has 
		// used capitals on
		public function getActivityInvolvingCaps($corporationName = null, $includeFighters = true) {
			if (sizeof($this->kill) == 0) return null;
			
			//if (!is_null($corporationID)) $corpName = $this->getNameFromEVEID($corporationID, GET_CORPORATION_ID);
			
			$capKillDB = array();
			foreach($this->kill as $aKill) {
				// Check to see whether or not the victim was a capital
				// If corp is specified, we verify that it was theirs
				if (in_array($aKill->victimShipID, unserialize(CAPITAL_SHIP_TYPEIDS)) && (!is_null($corporationName) ? strcasecmp($corporationName, $aKill->victimName) == 0 : in_array($aKill->victimShipID, unserialize(CAPITAL_SHIP_TYPEIDS)))) {
					$capKillDB[] = array("kill" => $aKill, "typeID" => $aKill->victimShipID, "isShip" => true);		
				}
				
				foreach($aKill->involved as $aInvolved) {
					if (!is_null($corporationName)) {
						if (strcasecmp($aInvolved->corporationName, $corporationName) == 0) {
							if (in_array($aInvolved->shipTypeID, unserialize(CAPITAL_SHIP_TYPEIDS))) {
								$capKillDB[] = array("kill" => $aKill, "typeID" => $aInvolved->shipTypeID, "isShip" => true);
							} elseif ($includeFighters && in_array($aInvolved->weaponTypeID, unserialize(FIGHTER_WEAPON_TYPEIDS))) {
								$capKillDB[] = array("kill" => $aKill, "typeID" => $aInvolved->weaponTypeID, "isShip" => false);	
							}
						}
					} else {
						if (in_array($aInvolved->shipTypeID, unserialize(CAPITAL_SHIP_TYPEIDS))) {
							$capKillDB[] = array("kill" => $aKill, "typeID" => $aInvolved->shipTypeID, "isShip" => true);
						} elseif ($includeFighters && in_array($aInvolved->weaponTypeID, unserialize(FIGHTER_WEAPON_TYPEIDS))) {
							$capKillDB[] = array("kill" => $aKill, "typeID" => $aInvolved->weaponTypeID, "isShip" => false);	
						}
					}
				}
			}
			return $capKillDB;
		}
		
		public function getShipNameForTypeID($typeID) {
			$rsShipType = mysql_query_cache(sprintf("SELECT it.typeName FROM ".EVEDB_NAME.".invTypes it LEFT JOIN ".EVEDB_NAME.".invGroups ig USING (groupID) WHERE it.typeID = '%d'", $typeID));	
			if (is_array($rsShipType)) {
				if (!empty($rsShipType)) {
					return $rsShipType[0]["typeName"];		
				}
			}
			return null;
		}
		
		private function getTotalBattleCount() {
			$battleCount = 0;
			foreach ($this->corp as $aCorp) {
				$battleCount += sizeof($aCorp->battle);
			}
			return $battleCount;
		}
		
		// This function returns an array of kills where the specified corp has killed an InterBus C.O
		public function getInterBusCOKills($aCorp) {
			if (!$aCorp) return array();
			
			$IBKillAry = array();
			
			$corpKills = $this->getKillsInvolvingCorporation($aCorp);
			if (sizeof($corpKills) > 0) {
				foreach ($corpKills as $aCorpKill) {
					if ($aCorpKill["kill"]->victimShipID == INTERBUS_CUSTOMS_OFFICE_TYPEID && $aCorpKill["isVictim"] == false) {
						$IBKillAry[] = $aCorpKill["kill"];	
					}
				}
			}
			return $IBKillAry;
		}
		
		// This function returns an array of POS kills where the specified corp was involved
		public function getPOSCombatActivity($aCorp) {
			if (!$aCorp) return array();

			$posKillAry = array();
			
			// If corp has lost POS or 
			$rsTower = mysql_query_cache(sprintf("SELECT it.typeID FROM ".EVEDB_NAME.".invTypes it WHERE groupID IN (%s)", implode(unserialize(POS_TOWER_GROUPID),",")));
			$rsTowerGuns = mysql_query_cache(sprintf("SELECT it.typeID FROM ".EVEDB_NAME.".invTypes it WHERE groupID IN (%s)", implode(unserialize(POS_GUNS_GROUPIDS),",")));
			if (!empty($rsTower) && !empty($rsTowerGuns)) {
				// Build array of tower IDs
				$towerIDAry = array();
				$towerGunsAry = array();
				for ($t = 0; $t < count($rsTower); $t++) { $towerIDAry[] = $rsTower[$t]["typeID"]; }
				for ($t = 0; $t < count($rsTower); $t++) { $towerGunsAry[] = $rsTower[$t]["typeID"]; }
				
				// Note: Although a character cannot wield a POS, a POS can shoot people (dur!).  If a POS is 
				// involved in killing someone then we can be certain that someone is resident.  Equally if the
				// "victim" is a POS, they were definitely at one point resident.
				foreach($this->kill as $aKill) {
					if (strcasecmp($aKill->victimCorpName, $aCorp->corporationName) == 0 && in_array($aKill->victimShipID, $towerIDAry)) {
						$posKillAry[] = array("kill" => $aKill, "isVictim" => 1);	
					} else {
						// It is impossible for the victim to be a POS and the involved party to be a POS as well
						foreach($aKill->involved as $aInvolved) {
							if (strcasecmp($aCorp->corporationName, $aInvolved->corporationName) == 0 && (in_array($aInvolved->shipTypeID, $towerIDAry) || in_array($aInvolved->weaponTypeID,$towerGunsAry))) {
								// aCorp owned POS killed someone (or was involved in it)
								$posKillAry[] = array("kill" => $aKill, "isVictim" => 0);						
							}
						}
					}
				}
			}
			return $posKillAry;
		}
		
		// This function returns true if the specific corporation ID is within the array
		public function isNPCCorporation($corporationID) { return (($corporationID >= NPC_CORPORATION_START_ID && $corporationID <= NPC_CORPORATION_END_ID) || $corporationID == SLEEPER_CORPORATION_ID); }
		
		// This function returns TRUE if the specified corp is in a player alliance
		public function isCorpInAlliance($aCorp) { return (strtolower($aCorp->allianceName) == 'none' || $aCorp->allianceID == NO_ALLIANCE_ALLIANCE_ID || $aCorp->allianceID == 0) ? false : true; }
		
		public function getNameFromEVEID($intID, $queryType = null) {
			global $whConn;
			
			// If a Kill object is supplied then we can scan through the involved parties to see 
			// if we can get the ID that way, instead of doing a costly EVE API lookup.						
			if (sizeof($this->kill) > 0) {
				switch ($queryType) {
					case GET_CHARACTER_ID : 
						foreach($this->kill as $aKill) {
							if ($intID == $aKill->victimExternalID) {
								return $aKill->victimName;
							}
							foreach($aKill->involved as $aInvolved) {
								if ($intID == $aInvolved->characterID) {
									return $aInvolved->characterName;
								}
							}
						}
						break;
					case GET_CORPORATION_ID : 
						foreach($this->kill as $aKill) {
							foreach($aKill->involved as $aInvolved) {
								if ($intID == $aInvolved->corporationID) {
									return $aInvolved->corporationName;
								}
							}
						}
						break;
					case GET_ALLIANCE_ID :
						foreach($this->kill as $aKill) {
							foreach($aKill->involved as $aInvolved) {
								if ($intID == $aInvolved->allianceID) {
									return $aInvolved->allianceName;
								}
							}
						}
						break;
				}
			}
			
			// Try and get the result from the database
			$nameArray = self::getIDOrNameFromEveAPI($intID, EVE_API_NAME_SEARCH);
			if (is_array($nameArray)) { return $nameArray[0]["name"]; }
			
			return null;
		}
		
		// Gets the name corresponding to a typeID
		public function getDBNameFromEVEID($iID) {
			if (!is_numeric($iID)) return '';
			$iID = trim(mysql_real_escape_string($iID));
			$rsItem = mysql_query_cache(sprintf("SELECT it.typeName FROM ".EVEDB_NAME.".invTypes it WHERE	it.typeID = '%d'", $iID));
			return (!empty($rsItem)) ? $rsItem[0]["typeName"] : '';
		}
		
		// Gets the ID corresponding to a typeName
		public function getDBIDFromEVEName($sName) {
			if (strlen($sName) < 1) return -1;
			$sName = trim(mysql_real_escape_string($sName));
			$rsItem = mysql_query_cache(sprintf("SELECT it.typeID FROM ".EVEDB_NAME.".invTypes it WHERE it.typeName = '%s'", $sName));
			return (!empty($rsItem)) ? $rsItem[0]["typeID"] : '';
		}
		
		public function getEVEIDFromName($strName, $queryType = null) {
			global $whConn;
			
			// If a Kill object is supplied then we can scan through the involved parties to see 
			// if we can get the ID that way, instead of doing a costly EVE API lookup.						
			if (sizeof($this->kill) > 0) {
				switch ($queryType) {
					case GET_CHARACTER_ID : 
						foreach($this->kill as $aKill) {
							if (strcasecmp(trim($strName), trim($aKill->victimName)) == 0) {
								return $aKill->victimExternalID;
							}
							foreach($aKill->involved as $aInvolved) {
								if (strcasecmp(trim($strName), trim($aInvolved->characterName)) == 0) {
									return $aInvolved->characterID;
								}
							}
						}
						break;
					case GET_CORPORATION_ID : 
						foreach($this->kill as $aKill) {
							foreach($aKill->involved as $aInvolved) {
								if (strcasecmp(trim($strName), trim($aInvolved->corporationName)) == 0) {
									return $aInvolved->corporationID;
								}
							}
						}
						break;
					case GET_ALLIANCE_ID :
						foreach($this->kill as $aKill) {
							foreach($aKill->involved as $aInvolved) {
								if (strcasecmp(trim($strName), trim($aInvolved->allianceName)) == 0) {
									return $aInvolved->allianceID;
								}
							}
						}
						break;
				}
			}
			
			// Try and get the result from the database
			$idArray = self::getIDOrNameFromEveAPI($strName, EVE_API_ID_SEARCH);
			if (is_array($idArray)) { return $idArray[0]["id"]; }
			
			return -1;
		}
		
		// Sometimes Eve-Kill throws us some spurious data in the corporation/alliance names, which if we send to the API wlil bomb it out.
		// This function addresses this by sanitising the corp names.  It uses the EVE API to get the current corp/alliance name - which may
		// not have been the one at the time of the kill - and replaces the information with that instead.
		public function fixBrokenEKData($aCorp) {
			//dprintf("fixBrokenEKData() called on (%d) '%s', (%d) '%s'", $aCorp->corporationID, $aCorp->corporationName, $aCorp->allianceID, $aCorp->allianceName);
			if (preg_match(NAMING_POLICY_REGEXP, $aCorp->corporationName) === 0 
				|| preg_match(NAMING_POLICY_REGEXP, $aCorp->allianceName) === 0) {
				//dprintf("fixBrokenEKData() - found a bad name (corp bad? %d, alliance bad? %d), trying to sanitise.", (preg_match(NAMING_POLICY_REGEXP, $aCorp->corporationName) === 0), (preg_match(NAMING_POLICY_REGEXP, $aCorp->allianceName) === 0), $aCorp->allianceName);	
				if (is_null($aCorp->corporationID) && preg_match(NAMING_POLICY_REGEXP, $aCorp->corporationName) === 1) {
					//dprintf("fixBrokenEKData() - corporation ID is missing, but corporation name is valid - getting ID via getIDOrNameFromEveAPI()");
					// If we don't have a valid corporationID then we can't get any of the rest of the data, since the EVE API for 
					// getting an alliance ID/name requires the corporationID as input.
					$idArray = $this->getIDOrNameFromEveAPI($aCorp->corporationName);
					if (is_null($idArray)) {
						// If we still haven't got a corporationID by this point, we're screwed - so bomb out.
						//printf("<p>Argh! We haven't got a corporationID even now! Exiting</p>");
						return false;
					}
					$aCorp->corporationID = $idArray[0]["id"];
				}
				
				if (($aCorp->allianceID == 0 || $aCorp->allianceID == NO_ALLIANCE_ALLIANCE_ID) && strlen(trim($aCorp->allianceName)) == 0) {
					// If we have an alliance ID of 0 or one that corresponds to "None", we don't need to 
					// query the EVE API.  Technically we could - but this would get their current alliance, not
					// the one they were in at that point in time.
					//dprintf("Alliance name missing, ID is 0, assuming 'None'");
					$aCorp->allianceName = "None";
				} else {
					// Get alliance info from the API
					//dprintf("<strong>Querying EVE API URL: %s</strong>", sprintf(EVE_API_CORP_LOOKUP_URL, $aCorp->corporationID));
					set_error_handler("eve_api_warning_catcher", E_WARNING);
					try {
						libxml_use_internal_errors(true);
						$apiXml = simplexml_load_file(sprintf(EVE_API_CORP_LOOKUP_URL, $aCorp->corporationID));
					
						// If the allianceID is not already set, set it with the data we now have
						//dprintf("apiXml->allianceID = %d", $apiXml->result->allianceID);
						if (isset($apiXml->result->allianceID)) {
							$aCorp->allianceID = (int)$apiXml->result->allianceID;
							
							// If alliance name is invalid, get the name from the newly found ID
							if ($aCorp->allianceID == 0) {
								// If alliance ID returned is 0 we already know they have no alliance, so just set that
								$aCorp->allianceName = "None";
							} elseif (preg_match(NAMING_POLICY_REGEXP, $aCorp->allianceName) === 0) {
								//dprintf("fixBrokenEKData() - alliance name is invalid, fixing using allianceID %d", $aCorp->allianceID);
								//dprintf("<strong>Querying EVE API URL: %s</strong>", sprintf(EVE_API_NAME_LOOKUP_URL, $aCorp->allianceID));
								libxml_use_internal_errors(true);
								$apiXml = simplexml_load_file(sprintf(EVE_API_NAME_LOOKUP_URL, $aCorp->allianceID));
								
								if (isset($apiXml->result->rowset->row)) { 
									foreach($apiXml->result->rowset->row as $aChar) {
										// Add this ID to the database cache
										$idLookupSQL = sprintf("INSERT IGNORE INTO ".WHDB_NAME.".idMap (eve_id, eve_name) VALUES (%d, '%s')", 
											$aChar["characterID"], mysql_real_escape_string($aChar["name"]));
										mysql_query($idLookupSQL);
										
										// Update alliance name in corp table now that we have it
										$aCorp->allianceName = (string)$aChar["name"];
									}
								}
							}
						}
					} catch (Exception $e) {
						printf('<p><span class="advisoryBad">Error: Unable to update kill data from EVE KILL - error reported %s.</span></p>', $e->getMessage());
						error_log("EVE API error: " . $e->getMessage(), 0);		
					}
					restore_error_handler();
				}
			}
		}
		
		private function getIDOrNameFromEveAPI($nameArray, $searchType = EVE_API_ID_SEARCH) {
			global $whConn;
			
			// Convert nameArray to array if it is a string
			if (!is_array($nameArray)) {
				$tmpAry = array();
				$tmpAry[] = $nameArray;
				$nameArray = $tmpAry;
				unset($tmpAry);	
			}
			
			$idArray = array();
			
			// First interrogate the database cache, any entries we find in there we can exclude from the API run
			$dbNameArray = $nameArray;
			for ($n = 0; $n < sizeof($dbNameArray); $n++) { $dbNameArray[$n] = strtoupper(mysql_real_escape_string($dbNameArray[$n])); };
			if ($searchType == EVE_API_ID_SEARCH) {
				$idLookupSQL = sprintf("SELECT eve_id, eve_name FROM ".WHDB_NAME.".idMap WHERE UPPER(eve_name) IN ('%s')", implode($dbNameArray,"','"));
			} else {
				$idLookupSQL = sprintf("SELECT eve_id, eve_name FROM ".WHDB_NAME.".idMap WHERE eve_id IN (%s)", implode($dbNameArray,","));	
			}
			//dprintf("getIDOrNameFromEveAPI(): SQL query: %s", $idLookupSQL);
			$rsLookup = mysql_query_cache($idLookupSQL,$whConn);
			if (is_array($rsLookup)) {
				if (!empty($rsLookup)) {
					//printf("<p>Found %d/%d results in cache.", mysql_num_rows($rsLookup), sizeof($nameArray));
					// We have some cached IDs in the database, so add them to the "final" array, and remove them 
					// from the list of names we have to poll the EVE API for.
					
					// We have to create a temporary array to uppercase the entries because the database writes 
					// the EVE API result as the names are in game, which is not necessarily what they will be on 
					// the kills (strange but true)
					$ucArray = $nameArray;
					for ($n = 0; $n < sizeof($ucArray); $n++) { $ucArray[$n] = strtoupper($ucArray[$n]); };
					
					// For each database row we have, we remove the result from the nameArray table
					for ($s = 0; $s < count($rsLookup); $s++) {
						//dprintf("result: %d, %s", $sRow->eve_id, $sRow->eve_name);
						$idArray[] = array("id" => $rsLookup[$s]["eve_id"], "name" => $rsLookup[$s]["eve_name"]);
						if (($nKey = array_search(strtoupper(($searchType == EVE_API_ID_SEARCH) ? $rsLookup[$s]["eve_name"] : $rsLookup[$s]["eve_id"]), $ucArray)) !== false) { unset($nameArray[$nKey]); }
					}
					unset($ucArray);
					
					// Re-index the nameArray
					$nameArray = array_values($nameArray);
				}
			}
			unset($dbNameArray);
			//print_r($nameArray);
			
			// We might not have any names left to search for..
			if (sizeof($nameArray) > 0) {
				//dprintf("%d results were missing from cache, need to query EVE API to complete.", sizeof($nameArray));
				// We urlencode the individual names, but not the whole query
				for ($n = 0; $n < sizeof($nameArray); $n++) { $nameArray[$n] = urlencode($nameArray[$n]); };
				
				set_error_handler("eve_api_warning_catcher", E_WARNING);
				try {
					libxml_use_internal_errors(true);
					$apiXml = simplexml_load_file(sprintf(($searchType == EVE_API_ID_SEARCH) ? EVE_API_ID_LOOKUP_URL : EVE_API_NAME_LOOKUP_URL, implode($nameArray,",")));
					//dprintf("API: %s", sprintf(($searchType == EVE_API_ID_SEARCH) ? EVE_API_ID_LOOKUP_URL : EVE_API_NAME_LOOKUP_URL, implode($nameArray,",")));
					if (isset($apiXml->result->rowset->row)) { 
						foreach($apiXml->result->rowset->row as $aChar) {
							// Add this ID to the database cache
							$idLookupSQL = sprintf("INSERT IGNORE INTO ".WHDB_NAME.".idMap (eve_id, eve_name) VALUES (%d, '%s')", 
								$aChar["characterID"], mysql_real_escape_string($aChar["name"]));
							//dprintf("SQL insert: %s", $idLookupSQL);
							mysql_query($idLookupSQL);
							
							$idArray[] = array("id" => intval($aChar["characterID"]), "name" => strval($aChar["name"]));
						}
					}
				} catch (Exception $e) {
					printf('<p><span class="advisoryBad">Error: Unable to update kill data from EVE KILL - error reported %s.</span></p>', $e->getMessage());
					error_log("EVE API error: " . $e->getMessage(), 0);
				}
				restore_error_handler();
			}
			
			// Returns the populated array to the calling function, or null if we have no IDs
			return sizeof($idArray) > 0 ? $idArray : null;
		}
		
		// Exponential decay
		private function ed($ti, $k, $t, $N0) {
			$k = log($k) / $ti;
			$k = -$k * $t;
			$N = pow(2.718281828, -$k) * $N0;
			return $N;
		}
		
		// This function attempts to work out who is resident in a given system based on the corp database.  
		// Note: This should only be called once the corp database has been properly sanitised.
		private function calcResidency() {
			$oldestKill = $this->res_metrics["oldestKill"];
			$latestKill = $this->res_metrics["newestKill"];
			$rsAry = array();
			
			if ($oldestKill && $latestKill && sizeof($this->corp) > 0) {
				$sysKillTimeSpan = strtotime($latestKill->timestamp) - strtotime($oldestKill->timestamp);
				
				// Create new wormhole object, for checking capital use in lower class
				$aTempWH = new Wormhole($this->uniqID);
				
				foreach ($this->corp as $aCorp) {
					// NPC corporations can't be resident (obviously Sleepers are :) )
					if ($aCorp->isNPCCorporation()) continue;
					
					// Create/update alliance object
					if (!($aCorp->allianceID == 0 || $aCorp->allianceID == NO_ALLIANCE_ALLIANCE_ID)) {
						if (is_null($aAlliance = &$this->getAllianceRM($aCorp->allianceName))) {
							$this->res_metrics["alliance"][] = array(
								"alliance_name" => &$aCorp->allianceName,
								"corp_count" => 1
							);
						} else { $aAlliance["corp_count"]++; }
					}

					// Weigh each battle according to how long ago it occured
					$battleCount = 0;
					foreach ($aCorp->battle as $aBattle) {
						//dprintf("[%s] battle time: %s, <i>it</i>: %d, <i>k</i>: %f, <i>t</i>: %d, <i>N<sub>0</sub></i>: %d, expo. decay: %f", $aCorp->corporationName, $aBattle->timestamp, $sysKillTimeSpan, SCORE_EXPONENTIAL_DECAY_RATE, (strtotime($latestKill->timestamp) - strtotime($aBattle->timestamp)), SCORE_MAX_SCORE_FOR_BATTLE, $this->ed($sysKillTimeSpan, SCORE_EXPONENTIAL_DECAY_RATE, (strtotime($latestKill->timestamp) - strtotime($aBattle->timestamp)), SCORE_MAX_SCORE_FOR_BATTLE));
						
						$aCorp->residency["score"] += $this->ed($sysKillTimeSpan, SCORE_EXPONENTIAL_DECAY_RATE, (strtotime($latestKill->timestamp) - strtotime($aBattle->timestamp)), (SCORE_MAX_SCORE_FOR_BATTLE + (++$battleCount * SCORE_MULTIPLE_BATTLE_INCREMENT)));							
					}
					
					// Add weight to residency score for kills where corp's POS was either a killer or a victim
					$posKills = $this->getPOSCombatActivity($aCorp);
					if (sizeof($posKills) > 0) {
						foreach($posKills as $aPOSKill) {
							dprintf('[%s] POS was involved in a %s on %s - <a href="%s" target="_blank">here</a>', $aCorp->corporationName, ($aPOSKill["isVictim"] == 1 ? "LOSS" : "KILL"), $aPOSKill["kill"]->timestamp, $aPOSKill["kill"]->url);
							$aCorp->residency["score"] += $this->ed($sysKillTimeSpan, SCORE_EXPONENTIAL_DECAY_RATE, (strtotime($latestKill->timestamp) - strtotime($aPOSKill["kill"]->timestamp)), SCORE_SKEW_POS_COMBAT_ACTIVITY);
							$aCorp->residency["posactvy"][] = $aPOSKill;
						}
					}
					
					// If wormhole is class 4 or lower, and this corp has used a capital, it's a good 
					// assumption that they are or have been resident at some point.
					if ($aTempWH->isValidLocus() && $aTempWH->isWHLocus()) {
						if ($aTempWH->getSysClass() <= 4) {
							$capUsageDB = $this->getActivityInvolvingCaps($aCorp->corporationName, true);
							if (sizeof($capUsageDB) > 0) {
								dprintf("[%s] Corp has used a cap %d times in a <= C4 wormhole", $aCorp->corporationName, sizeof($capUsageDB));
								foreach($capUsageDB as $aKillWithCap) {
									$aCorp->residency["score"] += $this->ed($sysKillTimeSpan, SCORE_EXPONENTIAL_DECAY_RATE, (strtotime($latestKill->timestamp) - strtotime($aKillWithCap["kill"]->timestamp)), SCORE_SKEW_CAPITAL_USE_IN_LOWCLASS_WH);
									$aCorp->residency["caplcuse"][] = $aKillWithCap["kill"];
								}
							}
						}
						
						// Skew for InterBus Customs Office activity
						$IBCOKillDB = $this->getInterBusCOKills($aCorp);
						if (sizeof($IBCOKillDB) > 0) {
							dprintf("[%s] Corp has killed %d InterBus C.Os in this system", $aCorp->corporationName, sizeof($IBCOKillDB));
							foreach($IBCOKillDB as $aIBCOKill) {
								$aCorp->residency["score"] += $this->ed($sysKillTimeSpan, SCORE_EXPONENTIAL_DECAY_RATE, (strtotime($latestKill->timestamp) - strtotime($aIBCOKill->timestamp)), SCORE_SKEW_INTERBUS_CO_KILL);	
								$aCorp->residency["ibcokills"][] = $aIBCOKill;
							}
						}
					}
				
					// Check eviction status for each corp
					if (!is_null($aCorp->residency["evicted"] = $this->calcEviction($aCorp, EVEKILL_ANALYSIS_MAX_MONTH_HISTORY))) {
						if ($aCorp->residency["evicted"]["ppl_score"] >= SCORE_RES_STILL_RES_THRESHOLD_SCORE) {
							dprintf("[%s] Corp lost tower(s), but appeared to rally and remain resident? (still res weight: %f)", $aCorp->corporationName, $aCorp->residency["evicted"]["ppl_score"]);
							
							// Include this corps score in array for stddev calc
							$rsAry[] = $aCorp->residency["score"];
						} else {
							// If evicted zero score to eliminate them from standard deviation checks to allow us to find the current resident
							dprintf("[%s] Corp is evicted. (%f)", $aCorp->corporationName, $aCorp->residency["evicted"]["ppl_score"]);
							//$aCorp->residency["score"] = 0;
						}
					} else {
						// Include this corps score in array for stddev calc
						$rsAry[] = $aCorp->residency["score"];	
					}
				}
				
				// Populate total residency score metric in killboard
				$this->res_metrics["totalScore"] = $this->getTotalResidencyScore();
				
				// Calculate stddev, z-score and percentile
				$resStdDev = sd($rsAry);
				foreach ($this->corp as $aCorp) {
					$aCorp->residency["stddev"] = $resStdDev;
					$aCorp->residency["z-score"] = ($aCorp->residency["score"] - ($this->res_metrics["totalScore"] / sizeof($this->corp))) / $aCorp->residency["stddev"];
					$aCorp->residency["z-perc"] = cdf($aCorp->residency["z-score"]) * 100;	
				}
				
				// Sort the corp table by residency score descending order
				usort($this->corp, 'rcmp');
			}
		}
		
		public function getTimezoneActivity($corpArray) {
			if (!is_array($corpArray)) return false;
			
			$tzActivityArray = array();
			foreach($corpArray as $aCorp) {
				// Add this corps TZ activity.  Note: Kills and losses are treated the same
				// - we just want to know when they are active
				if (is_array($corpActivityDB = $this->getKillsInvolvingCorporation($aCorp))) {
					foreach($corpActivityDB as $aCorpA) {
						$tzActivityArray[] = $aCorpA["kill"]->timestamp;	
					}
				}	
			}
				
			// Calculate TZ spread.  Note: This is based on people working during the day, and
			// is weighted towards the mid to upper end (prime time).  Note also that EVE 
			// timestamps are constant - adjustments have to be made for timezones.  The
			// calculations are not binary, a kill at midnight EVE time would be prime time 
			// US, and still within EU.					
			$tzArray = array("eu" => array("name" => "Euro", "score" => 0, "z-score" => 0, "r-perc" => 0, "z-perc" => 0, "shift" => 1),
							 "us" => array("name" => "US", "score" => 0, "z-score" => 0, "r-perc" => 0, "z-perc" => 0, "shift" => -5),
							 "au_nz" => array("name" => "AU/NZ", "score" => 0, "z-score" => 0, "r-perc" => 0, "z-perc" => 0, "shift" => 10));
			$tzWeight = array(	0 	=> 0.35,
								1	=> 0.1,
								2 	=> 0.05,
								3 	=> 0,
								4 	=> 0,
								5 	=> 0,
								6 	=> 0,
								7 	=> 0,
								8 	=> 0,
								9 	=> 0,
								10 	=> 0,
								11	=> 0.05,
								12	=> 0.1,
								13 	=> 0.1,
								14 	=> 0.1,
								15	=> 0.1,
								16	=> 0.1,
								17 	=> 0.1,
								18 	=> 0.2, 
								19 	=> 0.45,
								20 	=> 0.7,
								21 	=> 0.9,
								22 	=> 1.0,
								23 	=> 0.7);
								
			foreach ($tzActivityArray as $aTime) {
				// 6pm to midnight is the spread for everyone
				//dprintf("kill time: %s, EU: %s, US: %s, AU/NZ: %s", $aTime, date("H:i:s", strtotime("+1 hour", strtotime($aTime))), date("H:i:s", strtotime("-5 hours", strtotime($aTime))), date("H:i:s", strtotime("+10 hours", strtotime($aTime))));
				$eTZhour = intval(date("G", strtotime("+1 hour", strtotime($aTime))));
				$eUShour = intval(date("G", strtotime("-5 hours", strtotime($aTime))));
				$eAUhour = intval(date("G", strtotime("+10 hours", strtotime($aTime))));
				
				$tzArray["eu"]["score"] += $tzWeight[$eTZhour] + (abs($tzWeight[(($eTZhour+1 > 23) ? 0 : $eTZhour+1)] - $tzWeight[$eTZhour]) * (intval(date("i", strtotime($aTime))) / 59 * 100));
				$tzArray["us"]["score"] += $tzWeight[$eUShour] + (abs($tzWeight[(($eUShour+1 > 23) ? 0 : $eUShour+1)] - $tzWeight[$eUShour]) * (intval(date("i", strtotime($aTime))) / 59 * 100));
				$tzArray["au_nz"]["score"] += $tzWeight[$eAUhour] + (abs($tzWeight[(($eAUhour+1 > 23) ? 0 : $eAUhour+1)] - $tzWeight[$eAUhour]) * (intval(date("i", strtotime($aTime))) / 59 * 100));
			}
			// Calculate TZ percentiles
			$tzZAry = array($tzArray["eu"]["score"], $tzArray["us"]["score"], $tzArray["au_nz"]["score"]);
			$tzStdDev = sd($tzZAry);
			
			$tzArray["eu"]["z-score"] = ($tzArray["eu"]["score"] - (array_sum($tzZAry) / 3)) / $tzStdDev;
			$tzArray["eu"]["z-perc"] = cdf($tzArray["eu"]["z-score"]) * 100;
			$tzArray["eu"]["r-perc"] = ($tzArray["eu"]["score"] / array_sum($tzZAry)) * 100;
			$tzArray["us"]["z-score"] = ($tzArray["us"]["score"] - (array_sum($tzZAry) / 3)) / $tzStdDev;
			$tzArray["us"]["z-perc"] = cdf($tzArray["us"]["z-score"]) * 100;
			$tzArray["us"]["r-perc"] = ($tzArray["us"]["score"] / array_sum($tzZAry)) * 100;
			$tzArray["au_nz"]["z-score"] = ($tzArray["au_nz"]["score"] - (array_sum($tzZAry) / 3)) / $tzStdDev;
			$tzArray["au_nz"]["z-perc"] = cdf($tzArray["au_nz"]["z-score"]) * 100;	
			$tzArray["au_nz"]["r-perc"] = ($tzArray["au_nz"]["score"] / array_sum($tzZAry)) * 100;
				
			// Sort array by score descending
			$aryScore = array();
			foreach($tzArray as $key => $row) {
				$aryScore[$key] = $row["score"];
			}
			array_multisort($aryScore, SORT_DESC, $tzArray);
			
			return $tzArray;
		}
		
		// This function returns a residency score for the specified allianceID
		// Note: Evicted residents in the same alliance are still considered, for presentation purposes
		public function totalAllianceResidencyScore($allianceID) {
			// It is meaningless to count up the score for corps not in an alliance, since "None" is
			// a special alliance ID that anyone who isn't in an alliance is put in.
			if ($allianceID == NO_ALLIANCE_ALLIANCE_ID) return 0;
			
			$cScore = 0;
			foreach ($this->corp as $aCorp) {
				if (!$this->isCorpInAlliance($aCorp) || $aCorp->isEvicted()) continue;
				if ($aCorp->allianceID == $allianceID) $cScore += $aCorp->residency["score"];
			}
			return $cScore;
		}
		
		// Returns the total combined residency score
		private function getTotalResidencyScore() {
			$cScore = 0;
			foreach ($this->corp as $aCorp) {
				if ($aCorp->isEvicted()) continue;
				$cScore += $aCorp->residency["score"];	
			}
			return $cScore;
		}
		
		// Returns the highest residency score in the database (evicted residents are excluded)
		private function getHighestResidencyScore() {
			$cScore = 0;
			foreach ($this->corp as $aCorp) { 
				if ($aCorp->isEvicted()) continue;
				if ($aCorp->residency["score"] > $cScore) { $cScore = $aCorp->residency["score"]; }		
			}
			return $cScore;
		}
		// Returns the highest residency score in the database
		private function getLowestResidencyScore() {
			$cScore = null;
			foreach ($this->corp as $aCorp) { 
				if (is_null($cScore) || $aCorp->residency["score"] < $cScore) { $cScore = $aCorp->residency["score"]; }		
			}
			return $cScore;
		}
		
		// Returns the highest resident in the database (evicted residents are excluded)
		private function &getTopResident() {
			$cCorp = null;
			$cScore = 0;
			foreach ($this->corp as $aCorp) { 
				if ($aCorp->isEvicted()) continue;
				if ($aCorp->residency["score"] > $cScore) { $cScore = $aCorp->residency["score"]; $cCorp = $aCorp; }		
			}
			return $cCorp;	
		}
		private function &getBottomResident() {
			$cCorp = null;
			$cScore = 9999;
			foreach ($this->corp as $aCorp) { 
				if ($aCorp->residency["score"] < $cScore) { $cScore = $aCorp->residency["score"]; $cCorp = $aCorp; }		
			}
			return $cCorp;	
		}
		
		// This function takes an array of Corporations and returns a unique list of them
		/*
		public function filterUniqueCorps($corpArray) {
			if (!is_array($corpArray)) return null;
			
			$uniqCorp = array();
			foreach($corpArray as $aCorp) { 
				if (array_search($aCorp, $uniqCorp) === false) $uniqCorp[] = $aCorp;	
			}
			return $uniqCorp;
		}
		*/
		
		// This function creates a new array that contains a list of the provided corps organised by 
		// their alliance.  It also removes all data that is extraneous to providing the output, and 
		// removes duplicates
		public function beautifyCorps($corpArray) {
			if (!is_array($corpArray)) return null;
			
			// PASS 1 - Get all of the alliances involved
			$allyAry = array();
			foreach($corpArray as $aCorp) {
				if (array_search($aCorp->allianceID, $allyAry) === false) {
					$allyAry[$aCorp->allianceID] = array("allianceID" => $aCorp->allianceID,
													     "allianceName" => $aCorp->allianceName,
														 "corps" => array());
				}
			}
			
			// PASS 2 - Add the corps to the respective alliances
			foreach($corpArray as $aCorp) {
				if (isset($allyAry[$aCorp->allianceID])) {	
					if (array_search($aCorp->corporationID, $allyAry[$aCorp->allianceID]["corps"]) === false) {
						$allyAry[$aCorp->allianceID]["corps"][$aCorp->corporationID] = 
							array("corporationID" => $aCorp->corporationID,
							      "corporationName" => $aCorp->corporationName);
					}
				}
			}
			
			// Make sure "Unknown" (no alliance) is at the end
			if (isset($allyAry[NO_ALLIANCE_ALLIANCE_ID])) {
				$na = $allyAry[NO_ALLIANCE_ALLIANCE_ID];
				unset($allyAry[NO_ALLIANCE_ALLIANCE_ID]);
				$allyAry[NO_ALLIANCE_ALLIANCE_ID] = $na;
			}
			
			return $allyAry;
		}
	}
	
	class Kill extends Killboard {
		public $url;
		public $timestamp;
		public $internalID;
		public $externalID;
		public $victimName;
		public $victimExternalID;
		public $victimCorpName;
		public $victimAllianceName;
		public $victimShipName;
		public $victimShipClass;
		public $victimShipID;
		public $FBPilotName;
		public $FBCorpName;
		public $FBAllianceName;
		public $involvedPartyCount;
		public $solarSystemName;
		public $solarSystemSecurity;
		
		public $involved = array();
		 
		public function __construct(&$aKill, &$cKillboard) {
			$this->url 					= $aKill->url;
			$this->timestamp 			= $aKill->timestamp;
			$this->internalID			= $aKill->internalID;
			$this->externalID			= $aKill->externalID;
			$this->victimName			= $aKill->victimName;
			$this->victimExternalID		= $aKill->victimExternalID;
			$this->victimCorpName		= $aKill->victimCorpName;
			$this->victimAllianceName	= $aKill->victimAllianceName;
			$this->victimShipName		= $aKill->victimShipName;
			$this->victimShipClass		= $aKill->victimShipClass;
			$this->victimShipID			= $aKill->victimShipID;
			$this->FBPilotName			= $aKill->FBPilotName;
			$this->FBCorpName			= $aKill->FBCorpName;
			$this->FBAllianceName		= $aKill->FBAllianceName;
			$this->involvedPartyCount	= $aKill->involvedPartyCount;
			$this->solarSystemName		= $aKill->solarSystemName;
			$this->solarSystemSecurity	= $aKill->solarSystemSecurity;
			
			// Add involved parties to the kill
			foreach ($aKill->involved as $aInvolved) {
				if (!$cKillboard->isNPCCorporation($aInvolved->corporationID)) {
					$this->involved[] = new Involved($aInvolved, $this, $cKillboard);
				}
			}
			
			// Add loss for victim corp
			$aCorp = &$cKillboard->getCorporation($this->victimCorpName);
			if (!is_object($aCorp)) {
				$cKillboard->corp[] = ($aCorp = new Corporation(SHIP_LOSS, null, $this->victimCorpName, null, $this->victimAllianceName, $this));
			} else {
				// Increment losscount
				$aCorp->lossCount++;
				
				// If EVEKILL_SECS_ELAPSED_ASSUME_NEW_BATTLE seconds have elapsed since the last loss by this corp, assume it 
				// is a new battle (and adds weight to the fact they are probably resident)
				if (is_null($aCorp->timeEarliestLoss) || (strtotime($this->timestamp) + EVEKILL_SECS_ELAPSED_ASSUME_NEW_BATTLE) <= $aCorp->timeEarliestLoss) {
					$aCorp->battle[] = &$this;
				}
				
				// Update loss times
				if (strtotime($this->timestamp) < $aCorp->timeEarliestLoss || is_null($aCorp->timeEarliestLoss)) $aCorp->timeEarliestLoss = strtotime($this->timestamp);
				if (strtotime($this->timestamp) > $aCorp->timeLatestLoss || is_null($aCorp->timeLatestLoss)) $aCorp->timeLatestLoss = strtotime($this->timestamp);
				
				// Add loss to this corp's kill database
				$aCorp->addKill($this, true);
			}
			// Add the victims ship to their "use" database
			$aCorp->addShip($this->victimShipID, SHIP_LOSS);
		}
		
		// This function quite simply returns TRUE if the specified corp name has already been recorded as an 
		// involved party in a kill.  This is required to avoid duplication of kills (i.e. we only credit a corp 
		// once for a kill)
		public function isCorpAlreadyInvolved($corpName) {
			if (sizeof($this->involved) == 0) return false;
			foreach ($this->involved as $aInvolved) {
				if (strcasecmp(trim($aInvolved->corporationName), trim($corpName)) == 0) {
					return true;
				}
			}
			// Couldn't find corp
			return false;
		}
		
		// Returns the involved party that laid the final blow
		public function &getFinalBlower() {
			foreach ($this->involved as $aInvolved) {
				if ($aInvolved->finalBlow == 1) return $aInvolved;	
			}
		}
	}
	
	class Involved extends Kill {
		public $characterID;
		public $characterName;
		public $corporationID;
		public $corporationName;
		public $allianceID;
		public $allianceName;
		public $factionID;
		public $factionName;
		public $securityStatus;
		public $damageDone;
		public $finalBlow;
		public $weaponTypeID;
		public $shipTypeID;
		
		public function __construct($aInvolved, &$cKill, &$cKillboard) {
			$this->characterID		= $aInvolved->characterID;
			$this->characterName	= $aInvolved->characterName;
			$this->corporationID	= $aInvolved->corporationID;
			$this->corporationName	= $aInvolved->corporationName;
			$this->allianceID		= $aInvolved->allianceID;
			$this->allianceName		= $aInvolved->allianceName;
			$this->factionID		= $aInvolved->factionID;
			$this->factionName		= $aInvolved->factionName;
			$this->securityStatus	= $aInvolved->securityStatus;
			$this->damageDone		= $aInvolved->damageDone;
			$this->finalBlow		= $aInvolved->finalBlow;
			$this->weaponTypeID		= $aInvolved->weaponTypeID;
			$this->shipTypeID		= $aInvolved->shipTypeID;
			
			$aCorp = &$cKillboard->getCorporation($this->corporationName);
			if (!is_object($aCorp)) {
				$cKillboard->corp[] = ($aCorp = new Corporation(SHIP_KILL, $this->corporationID, $this->corporationName, $this->allianceID, $this->allianceName, $cKill));	
			} else {
				if (!$cKill->isCorpAlreadyInvolved($this->corporationName)) {
					// Increment killcount
					$aCorp->killCount++;
 	
					// If EVEKILL_SECS_ELAPSED_ASSUME_NEW_BATTLE seconds have elapsed since the last kill by this corp, assume it 
					// is a new battle (and adds weight to the fact they are probably resident)
					if (is_null($aCorp->timeEarliestKill) || (strtotime($cKill->timestamp) + EVEKILL_SECS_ELAPSED_ASSUME_NEW_BATTLE) <= $aCorp->timeEarliestKill) {
						$aCorp->battle[] = &$cKill;
					}
					
					// Update kill times
					if (strtotime($cKill->timestamp) < $aCorp->timeEarliestKill || is_null($aCorp->timeEarliestKill)) $aCorp->timeEarliestKill = strtotime($cKill->timestamp);
					if (strtotime($cKill->timestamp) > $aCorp->timeLatestKill || is_null($aCorp->timeLatestKill)) $aCorp->timeLatestKill = strtotime($cKill->timestamp);
					
					// Add kill to this corp's kill database
					$aCorp->addKill($cKill, false);
				}
				
				// Increment involved parties and ships
				$aCorp->addInvolved();
			}
			// Add the killers ship and weapon to their "use" database
			$aCorp->addShip($this->shipTypeID, SHIP_KILL);
			$aCorp->addWeapon($this->weaponTypeID);
		}
	}
	
	class Corporation {
		public $corporationID;
		public $corporationName;
		public $allianceID;
		public $allianceName;	/* We record alliance name for presentation, but we don't care if a corp has changed since the kill record */
		public $battle = array();
		public $lossCount;
		public $killCount;
		public $timeFirstLoss;
		public $timeLastLoss;
		public $timeFirstKill;
		public $timeLastKill;
		public $involvedCount;
		public $residency = array("score" => 0, 
								  "stddev" => 0, 
								  "z-score" => 0,
								  "z-perc" => 0,
								  "caplcuse" => array(),
								  "ibcokills" => array(),
								  "posactvy" => array(),
								  "evicted" => null);
		public $shipTypes = array();
		public $weaponTypes = array();
		public $killDB = array();
		
		public function __construct($initialState, $corporationID, $corporationName, $allianceID, $allianceName, &$cKill) {
			//printf("<p>Adding new corporation: %s, alliance: %s, initial state: %d, state time: %s</p>", $corporationName, $allianceName, $initialState, $timestamp);
			$this->corporationID		= $corporationID;
			$this->corporationName 		= $corporationName;
			$this->allianceID			= $allianceID;
			$this->allianceName			= $allianceName;
			$this->battle[] 			= $cKill;
			$this->killDB[]				= array("kill" => $cKill, "isVictim" => (strcasecmp(trim($cKill->victimCorpName), trim($corporationName)) == 0) ? true : false);
			$this->involvedCount		= 1;
			$this->lossCount			= ($initialState == SHIP_LOSS) ? 1 : 0;
			$this->killCount 			= ($initialState == SHIP_KILL) ? 1 : 0;
			$this->timeEarliestLoss		= ($initialState == SHIP_LOSS) ? strtotime($cKill->timestamp) : null;
			$this->timeLatestLoss		= ($initialState == SHIP_LOSS) ? strtotime($cKill->timestamp) : null;
			$this->timeEarliestKill		= ($initialState == SHIP_KILL) ? strtotime($cKill->timestamp) : null;
			$this->timeLatestKill		= ($initialState == SHIP_KILL) ? strtotime($cKill->timestamp) : null;
			$this->residencyScore		= 0;
			$this->residencyStdDev		= 0;
			$this->residencyZScore		= 0;
			$this->residencyPercentile 	= 0;
		}
		
		public function addKill(&$cKill, $isVictim) { $this->killDB[] = array("kill" => $cKill, "isVictim" => $isVictim); }
		public function addInvolved() { $this->involvedCount++; }
		public function addShip($shipTypeID, $killType) { 
			$this->shipTypes[$shipTypeID] = isset($this->shipTypes[$shipTypeID]) ? $this->shipTypes[$shipTypeID]++ : 1; 
		}
		public function addWeapon($weaponTypeID) {
			$this->weaponTypes[$weaponTypeID] = isset($this->weaponTypes[$weaponTypeID]) ? $this->weaponTypes[$weaponTypeID]++ : 1; 
		}
		public function isEvicted() { return !is_null($this->residency["evicted"]) ? ($this->residency["evicted"]["ppl_score"] < SCORE_RES_STILL_RES_THRESHOLD_SCORE ? true : false) : false; }
		public function isNPCCorporation() { return (($this->corporationID >= NPC_CORPORATION_START_ID && $this->corporationID <= NPC_CORPORATION_END_ID) || $this->corporationID == SLEEPER_CORPORATION_ID); }
		
		// Return the oldest POS kill
		// Note: We can't assume the pos kill database will be in date order, so we'll do it the hard way
		public function getOldestEvictionPOSKill() {
			if (is_null($this->residency["evicted"])) return null;
			
			$oldTS = time();
			$oldKill = null;
			if (sizeof($this->residency["evicted"]["losses"]) > 0) {
				foreach ($this->residency["evicted"]["losses"] as $aPOSKill) {
					if (strtotime($aPOSKill->timestamp) <= $oldTS) {
						$oldTS = strtotime($aPOSKill->timestamp);
						$oldKill = $aPOSKill;
					}
				}
				return $oldKill;
			} else { return null; }
		}
		
		// Return the newest POS kill
		// Note: We can't assume the pos kill database will be in date order, so we'll do it the hard way
		public function getNewestEvictionPOSKill() {
			if (is_null($this->residency["evicted"])) return null;
			
			$newTS = 0;
			$newKill = null;
			if (sizeof($this->residency["evicted"]["losses"]) > 0) {
				foreach ($this->residency["evicted"]["losses"] as $aPOSKill) {
					if (strtotime($aPOSKill->timestamp) >= $newTS) {
						$newTS = strtotime($aPOSKill->timestamp);
						$newKill = $aPOSKill;
					}
				}
				return $newKill;
			} else { return null; }
		}
	}
	
	// This function is used to order the corp database by residencyScore
	function rcmp($a, $b) { return $b->residency["score"] - $a->residency["score"]; }
	function kcmp($a, $b) { return $b->killCount - $a->killCount; }
	function tcmp($a, $b) { return strtotime($b->timestamp) - strtotime($a->timestamp); }
	
	// Function to calculate square of value - mean
	function sd_square($x, $mean) { return pow($x - $mean,2); }
// Function to calculate standard deviation (uses sd_square)    
	function sd($array) {
   		// square root of sum of squares devided by N-1
    	return sqrt(array_sum(array_map("sd_square", $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) );
	}
	
	function erf($x) {
        $pi = 3.1415927;
        $a = (8*($pi - 3))/(3*$pi*(4 - $pi));
        $x2 = $x * $x;

        $ax2 = $a * $x2;
        $num = (4/$pi) + $ax2;
        $denom = 1 + $ax2;

        $inner = (-$x2)*$num/$denom;
        $erf2 = 1 - exp($inner);

        return sqrt($erf2);
	}

	function cdf($n) {
        if($n < 0)
        {
                return (1 - erf($n / sqrt(2)))/2;
        }
        else
        {
                return (1 + erf($n / sqrt(2)))/2;
        }
	}
	
	function convEKD2Pts($dateStr) {
		// Convert Eve-Kill date to PHP date - takes a date formatted as "Y-m-d_H.i.s" and converts it to 
		// timestamp
		$dAry = date_parse_from_format("Y-m-d_H.i.s", $dateStr);
		return is_array($dAry) ? mktime($dAry["hour"], $dAry["minute"], $dAry["second"], $dAry["month"], $dAry["day"], $dAry["year"]) : false;
	}
	
	function eve_api_warning_catcher($errno, $errstr) {
		printf('<p><span class="advisoryBad">Error: The EVE API appears to be down - data may be inaccurate or outdated.</span></p>', $errstr);	
		error_log("FATAL EVE API error: " . $errstr, 0);
		return true;
	}
?>
