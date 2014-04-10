<?
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	ini_set('display_errors', 1);
	ini_set('set_time_limit', 60);
	date_default_timezone_set("Europe/London");
	
	// Close the session to ensure asynchronous behaviour in AJAX calls
	session_write_close();
	
	require_once("dbconn.php");
	require_once("../includes/settings.php");
	require_once("../includes/funcs.php");
	require_once("../classes/class_wormhole.php");
	require_once("../classes/class_killboard.php");

	db_open();
	
	$action = trim($_REQUEST["func"]);
	
	// ========================================================================
	if ($action == "no-trust") {
	// ========================================================================
		// If the user does not want to trust this website we set a cookie on their computer 
		// that expires in +30 days, and let them know that they won't be bothered again for that time.
		setcookie("WHSE_NO_TRUST",true,time()+60*60*24*30);
		
		printf('<div id="t_trustHdr">Fair enough, be like that!</div>');
		printf('<div id="t_trustDesc">');
		printf('<p><strong>So you don\'t want to trust us?  Fine, it\'s your call.</strong></p>');
		printf('<p>We\'ll leave you alone for 30 days to think about it.  If in the meantime you decide you would like to trust us then simply add <strong>http://' . $_SERVER['SERVER_NAME'] . '</strong> to your trusted sites, or click the padlock at the top of the screen.</p>'); 
		printf('<p style="color: #666666; font-style: italic;">This window will automatically close in a few seconds.</p>');
		printf('</div>');
	// ========================================================================
	} elseif ($action == "chk-trust") {
	// ========================================================================
		// $trustWnd is a flag which determines (best guess) whether the "A website is requesting your trust" 
		// window was displayed or not.  Broadly this is done by error-checking the call to open it.  Out of game
		// there is no CCPEVE javascript object, so any call to it will error out, but ingame it will work.

		$trustWnd = ($_POST["twnd"] == "true") ? true : false;
		
		if ($trustWnd) {
			// Trust window *should* be visible
			printf('<div id="t_trustHdr">Good! We\'re nearly done..</div>');
			printf('<div id="t_trustDesc">');
			printf('<p><strong>Now we\'re getting somewhere.</strong>  A dialog should have appeared ' 
				 . 'on-screen asking you to confirm the trust settings for this website...</p>'); 
			printf('<p><img src="/images/trust_example.jpg" width="225" height="120" alt="Trust website window" border="0"/></p>');
			printf('<p>Just click on <strong>TRUST WEBSITE</strong> and you\'re done!</p>');
			printf('<p style="color: #666666; font-style: italic;">Cmon! Do it!  I\'m here c\'mon kill me!  Do it now!</p>');
			printf('</div>');
		} else {
			// No trust window, we're out of game or something else went wrong.
			printf('<div id="t_trustHdr">Something went wrong..</div>');
			printf('<div id="t_trustDesc">');
			printf('<p><strong>It seems like you\'re not in the game.</strong></p>');
			printf('<p>Trusting websites in Eve actually involves... being in Eve.  I know it\'s a bit unorthodox but please try again when you\'re <i>actually playing the game</i>.</p>');
			printf('<p>If you\'re seeing this message in-game then something else went wrong, and I look like a bit of a fool.  Try refreshing the page and if it happens again let me know.</p>'); 
			printf('<p style="color: #666666; font-style: italic;">This window will automatically close in a few seconds.</p>');
			printf('</div>');
		}
	// ========================================================================
	} elseif ($action == "getloc") {
	// ========================================================================
		printf(isset($_SERVER["HTTP_EVE_SOLARSYSTEMNAME"]) ? $_SERVER["HTTP_EVE_SOLARSYSTEMNAME"] : "");
	// ========================================================================
	} elseif ($action == "locusov") {
	// ========================================================================
		$n_LocusID = strtoupper(trim(mysql_real_escape_string($_REQUEST["lid"])));

		$aTempWH = new Wormhole($n_LocusID);
		if ($aTempWH->isValidLocus()) {
			if ($aTempWH->isWHLocus()) {
        		$whTxt = sprintf('<div class="iH sW"><a href="javascript:void(0)" rel="whInfo" title="System info">%s</a></div> is a <div class="iH %s">%s%s</div> system with ', $aTempWH->getSysName(), $aTempWH->getClassCSS(), $aTempWH->getClassDesc(), ($aTempWH->hasAnomaly() ? sprintf(' <a href="javascript:void(0)" rel="whAnom" title="Anomaly info">%s</a>', $aTempWH->getAnomalyName()) : ''));
				
				$staticInfoSQL = sprintf("SELECT it.typeName, COALESCE(whClass.valueFloat,whClass.valueInt) AS wormholeType FROM ".EVEDB_NAME.".invTypes it " 
							. "LEFT JOIN ".EVEDB_NAME.".dgmTypeAttributes whClass USING(typeID) "
							. "WHERE it.typeID IN (SELECT typeID FROM ".WHDB_NAME.".staticMap WHERE constellationID = '%d') "
							. "AND whClass.attributeID = 1381 "
							. "ORDER BY FIND_IN_SET(wormholeType,'7,8,9,1,2,3,4,5,6')", $aTempWH->getConstellationID());
				$rsStatics = mysql_query_cache($staticInfoSQL,$whConn);
				$whStatic = array();
				if (is_array($rsStatics)) {
					if (count($rsStatics) > 1) {
						for ($s = 0; $s < count($rsStatics); $s++) { 
							$whStatic[] = sprintf('<div class="iH sS %s"><a href="javascript:void(0)" rel="whStatic" title="Static info">%s</a></div> <div class="iH %s">(%s)</div>',
								getClassCSS($rsStatics[$s]["wormholeType"]), preg_replace("/Wormhole /i","",$rsStatics[$s]["typeName"]), getClassCSS($rsStatics[$s]["wormholeType"]), getClassDesc($rsStatics[$s]["wormholeType"],true));
						}
						$whTxt .= implode(", ",$whStatic) . sprintf(" static%s.", sizeof($whStatic) > 1 ? "s" : "");
						unset($whStatic);
					} elseif (count($rsStatics) == 1) {
						$whTxt .= sprintf('<div class="iH sS %s"><a href="javascript:void(0)" rel="whStatic" title="Static info">%s</a></div> <div class="iH %s">(%s)</div> static.', 
							getClassCSS($rsStatics[0]["wormholeType"]), preg_replace("/Wormhole /i","",$rsStatics[0]["typeName"]), getClassCSS($rsStatics[0]["wormholeType"]), getClassDesc($rsStatics[0]["wormholeType"],true));	
					}
				}
			} else {
				// Not a wormhole
				$whTxt = sprintf('<div class="iH sW"><a href="javascript:void(0)" rel="whInfo" title="System info">%s</a></div> is a <div class="iH %s">%s</div> system.', $aTempWH->getSysName(), $aTempWH->getClassCSS(), $aTempWH->getClassDesc());	
			}
		} else {
			// Not a valid locus
			$whTxt = sprintf('<div class="iH sW">%s</div> is not a valid locus ID.', $n_LocusID);		
		}
		echo $whTxt;
	// ========================================================================
	} elseif ($action == "getlocii") {
	// ========================================================================
		// Handler for the jQuery UI autocomplete code
		$locusID = strtoupper(trim(mysql_real_escape_string(isset($_REQUEST["term"]) ? $_REQUEST["term"] : '')));
		
		$lociiSQL = sprintf("SELECT solarSystemName FROM ".EVEDB_NAME.".mapSolarSystems WHERE UPPER(solarSystemName) LIKE UPPER('%s%%') ORDER BY solarSystemName", $locusID);
		$rsLocii = mysql_query_cache($lociiSQL, $whConn, MEMCACHE_STATICDATA_TIMEOUT, true);
		if (is_array($rsLocii)) {
			if (!empty($rsLocii)) {
				$locusIDs = array();
				//while ($lRow = mysql_fetch_object($rsLocii)) {
				for ($l = 0; $l < count($rsLocii); $l++) {
					array_push($locusIDs, $rsLocii[$l]["solarSystemName"]);
				}
				echo json_encode($locusIDs);
			}
		}
	// ========================================================================
	} elseif ($action == "dotlandata") {
	// ========================================================================
		$n_LocusID = strtoupper(trim(mysql_real_escape_string($_REQUEST["lid"])));
		
		$aTempWH = new Wormhole($n_LocusID);
		if ($aTempWH->isValidLocus()) {

			// Try and get Dotlan data from cache if it is available and fresh
			$haveCache = false;
			$cacheFile = sprintf("%s/%s_dotlan.dat", realpath(CACHE_DIRECTORY), str_replace(" ","_",strtoupper($n_LocusID)));
			if (is_readable($cacheFile)) {
				if ((strtotime("+".DOTLAN_CACHE_LIFETIME." seconds", filemtime($cacheFile)) - time()) > 0) {
					// Found a valid cache file
					$haveCache = true;
					$pageHTML = unserialize(gzinflate(file_get_contents($cacheFile)));
				} else {
					// Found a cache file, but it's too old - delete and recreate it
					unlink($cacheFile);
					$haveCache = false;
				}
			}

			if (!$haveCache) {
				$dotlanURL = "http://evemaps.dotlan.net/system/" . str_replace(" ","_",strtoupper($n_LocusID));
				try { 
					$pageHTML = file_get_contents($dotlanURL);
				} catch (Exception $e) {
					printf('<div class="iHdr" rel="%s"><a href="http://evemaps.dotlan.net/system/%s" target="_blank" alt="Visit Dotlan for more information about %s">Dotlan</a></div><span class="h4">&mdash;</span><div class="iData"><p>Unable to gather Dotlan data at this time - error returned was: %s', 
						$n_LocusID, $n_LocusID, str_replace("_"," ",$n_LocusID), $e->getMessage());
						exit(1);
				}
				file_put_contents($cacheFile, gzdeflate(serialize($pageHTML)));
			}
			
			if ($pageHTML) {
				$dom = new DOMDocument();
				$dom->preserveWhiteSpace = false;
				libxml_use_internal_errors(true);
				$dom->loadHTML($pageHTML);
				
				// First, get the jumps in last hour/24 hours
				$dlData = array('JumpGraphSrc' => '',
								'NPCKillsGraphSrc' => '',
								'ShipKillsGraphSrc' => '',
								'PodKillsGraphSrc' => '',
								'Jumps_1hr' => -1, 
								'Jumps_24hr' => -1,
								'ShipKills_1hr' => -1,
								'ShipKills_24hr' => -1,
								'NPCKills_1hr' => -1,
								'NPCKills_24hr' => -1,
								'PodKills_1hr' => -1,
								'PodKills_24hr' => -1);			
				
				$nodes = $dom->getElementsByTagName('td');
				for ($i = 0; $i < $nodes->length; $i++) {
					$td = $nodes->item($i);	
					if (stristr($td->nodeValue,"Jumps 1h/24h")) {
						// Node - Jumps 1h/24h
						$dlDataAry["Jumps_1hr"] = is_numeric($nodes->item($i+1)->nodeValue) ? (int)$nodes->item($i+1)->nodeValue : -1;
						$dlDataAry["Jumps_24hr"] = is_numeric($nodes->item($i+2)->nodeValue) ? (int)$nodes->item($i+2)->nodeValue : -1;
					} elseif (stristr($td->nodeValue,"Ship Kills")) {
						// Node - Ship Kills 1h/24h
						$dlDataAry["ShipKills_1hr"] = is_numeric($nodes->item($i+1)->nodeValue) ? (int)$nodes->item($i+1)->nodeValue : -1;
						$dlDataAry["ShipKills_24hr"] = is_numeric($nodes->item($i+2)->nodeValue) ? (int)$nodes->item($i+2)->nodeValue : -1;	
					} elseif (stristr($td->nodeValue,"NPC Kills")) {
						// Node - NPC Kills 1h/24h
						$dlDataAry["NPCKills_1hr"] = is_numeric($nodes->item($i+1)->nodeValue) ? (int)$nodes->item($i+1)->nodeValue : -1;
						$dlDataAry["NPCKills_24hr"] = is_numeric($nodes->item($i+2)->nodeValue) ? (int)$nodes->item($i+2)->nodeValue : -1;		
					} elseif (stristr($td->nodeValue,"Pod Kills")) {
						// Node - Pod Kills 1h/24h
						$dlDataAry["PodKills_1hr"] = is_numeric($nodes->item($i+1)->nodeValue) ? (int)$nodes->item($i+1)->nodeValue : -1;
						$dlDataAry["PodKills_24hr"] = is_numeric($nodes->item($i+2)->nodeValue) ? (int)$nodes->item($i+2)->nodeValue : -1;		
					}
				}
				
				$nodes = $dom->getElementsByTagName('img');
				foreach ($nodes as $img) {
					if (stristr($img->getAttribute('src'),"chart.googleapis.com")) {
						if (stristr($img->getAttribute('alt'),"Jumps")) {
							$dlGraphSrc = $img->getAttribute('src');
							$dlGraphSrc = str_ireplace('D5DF3D','CC9966',$dlGraphSrc);
							$dlGraphSrc = str_ireplace('chs=230x160','chs='.EVEMAPS_GRAPH_WIDTH.'x'.EVEMAPS_GRAPH_HEIGHT,$dlGraphSrc);
							$dlGraphSrc .= "&chxs=1,c2c2c2,10|0,c2c2c2,10";
							$dlDataAry["JumpGraphSrc"] = $dlGraphSrc;
		
							//printf('<div class="dotLanGraph"><img src="%s" width="%s" height="%s"></div>', $dlDataAry["JumpGraphSrc"], 240, 160);  
							
						} elseif (stristr($img->getAttribute('alt'),"NPC Kills")) {
							// Note: The Jumps graph is the only one we actually display, the others we store in 
							// a single graph which is displayed by another function.  This is a little sloppy but
							// it preserves the layout of the page.
							$dlGraphSrc = $img->getAttribute('src');
							$dlGraphSrc = str_ireplace('D5DF3D','82f882',$dlGraphSrc);
							$dlGraphSrc = str_ireplace('chs=230x160','chs='.EVEMAPS_GRAPH_WIDTH.'x'.EVEMAPS_GRAPH_HEIGHT,$dlGraphSrc);
							$dlGraphSrc .= "&chxs=1,c2c2c2,10|0,c2c2c2,10";
							$dlDataAry["NPCKillsGraphSrc"] = $dlGraphSrc;
							
						} elseif (stristr($img->getAttribute('alt'),"Ship Kills")) {
							// Note: The Jumps graph is the only one we actually display, the others we store in 
							// a single graph which is displayed by another function.  This is a little sloppy but
							// it preserves the layout of the page.
							$dlGraphSrc = $img->getAttribute('src');
							$dlGraphSrc = str_ireplace('D5DF3D','82f7f8',$dlGraphSrc);
							$dlGraphSrc = str_ireplace('chs=230x160','chs='.EVEMAPS_GRAPH_WIDTH.'x'.EVEMAPS_GRAPH_HEIGHT,$dlGraphSrc);
							$dlGraphSrc .= "&chxs=1,c2c2c2,10|0,c2c2c2,10";
							$dlDataAry["ShipKillsGraphSrc"] = $dlGraphSrc;
							
						} elseif (stristr($img->getAttribute('alt'),"Pod Kills")) {
							// Note: The Jumps graph is the only one we actually display, the others we store in 
							// a single graph which is displayed by another function.  This is a little sloppy but
							// it preserves the layout of the page.
							$dlGraphSrc = $img->getAttribute('src');
							$dlGraphSrc = str_ireplace('D5DF3D','dd60e8',$dlGraphSrc);
							$dlGraphSrc = str_ireplace('chs=230x160','chs='.EVEMAPS_GRAPH_WIDTH.'x'.EVEMAPS_GRAPH_HEIGHT,$dlGraphSrc);
							$dlGraphSrc .= "&chxs=1,c2c2c2,10|0,c2c2c2,10";
							$dlDataAry["PodKillsGraphSrc"] = $dlGraphSrc;
						}
					}
				}
				
				printf('<div class="iHdr" rel="%s"><a href="http://evemaps.dotlan.net/system/%s" target="_blank" alt="Visit Dotlan for more information about %s">Dotlan</a></div><span class="h4">&mdash;</span><div class="iData"><p>', $n_LocusID, $n_LocusID, str_replace("_"," ",$n_LocusID));
				
				if (!$aTempWH->isWHLocus()) {
					// only show jumps for non-wormholes - CCP removed jumps from WH systems
					printf('<a href="javascript:void(0)" rel="dlGraph;%s" title="Jumps">Jumps</a>: <span class="h3">%d</span> / <span class="h3">%d</span><span class="h4">&ndash;</span>', urlencode($dlDataAry["JumpGraphSrc"]), $dlDataAry["Jumps_1hr"], $dlDataAry["Jumps_24hr"]);
				}
				printf('<a href="javascript:void(0)" rel="dlGraph;%s" title="NPC Kills">NPC Kills</a>: <span class="h3%s">%d</span> / <span class="h3">%d</span><span class="h4">&ndash;</span><a href="javascript:void(0)" rel="dlGraph;%s" title="Ship Kills">Ship Kills</a>: <span class="h3%s">%d</span> / <span class="h3">%d</span><span class="h4">&ndash;</span><a href="javascript:void(0)" rel="dlGraph;%s" title="Pod Kills">Pod Kills</a>: <span class="h3%s">%d</span> / <span class="h3">%d</span><span class="h4">&mdash;</span>(last 1 / 24 hours)', 
					urlencode($dlDataAry["NPCKillsGraphSrc"]),
					($dlDataAry["NPCKills_1hr"] > 0) ? ' dlB dlAtv' : '',
					$dlDataAry["NPCKills_1hr"], 
					$dlDataAry["NPCKills_24hr"],
					urlencode($dlDataAry["ShipKillsGraphSrc"]),
					($dlDataAry["ShipKills_1hr"] > 0) ? ' dlB dlAtv' : '',
					$dlDataAry["ShipKills_1hr"],
					$dlDataAry["ShipKills_24hr"],
					urlencode($dlDataAry["PodKillsGraphSrc"]),
					($dlDataAry["PodKills_1hr"] > 0) ? ' dlB' : '',
					$dlDataAry["PodKills_1hr"],
					$dlDataAry["PodKills_24hr"]);
					
				printf('</p></div>');
			}
		}
	// ========================================================================
	} elseif ($action == "intel_staticinfo") {
	// ========================================================================
		// Get static info
		$staticType = strtoupper(trim(mysql_real_escape_string($_REQUEST["s"])));
		
		$staticInfoSQL = sprintf("SELECT it.typeName, COALESCE(whClass.valueFloat,whClass.valueInt) AS wormholeType, COALESCE(whStableTime.valueFloat,whStableTime.valueInt) AS maxStableTime, COALESCE(whStableMass.valueFloat,whStableMass.valueInt) AS maxStableMass, COALESCE(whMassRegen.valueFloat,whMassRegen.valueInt) AS massRegeneration, COALESCE(whJumpMass.valueFloat,whJumpMass.valueInt) AS maxJumpMass, sm.sigSize AS signatureSize "		
			. "FROM ".EVEDB_NAME.".invTypes it " 
			. "LEFT JOIN ".EVEDB_NAME.".dgmTypeAttributes whClass USING(typeID) "
			. "LEFT JOIN ".EVEDB_NAME.".dgmTypeAttributes whStableTime USING(typeID) " 
			. "LEFT JOIN ".EVEDB_NAME.".dgmTypeAttributes whStableMass USING(typeID) "
			. "LEFT JOIN ".EVEDB_NAME.".dgmTypeAttributes whMassRegen USING(typeID) "
			. "LEFT JOIN ".EVEDB_NAME.".dgmTypeAttributes whJumpMass USING(typeID) "
			. "LEFT JOIN ".WHDB_NAME.".sigDataMap sm USING(typeID) "
			. "WHERE UPPER(it.typeName) LIKE '%%%s' "
			. "AND whClass.attributeID = 1381 "
			. "AND whStableTime.attributeID = 1382 "
			. "AND whStableMass.attributeID = 1383 "
			. "AND whMassRegen.attributeID = 1384 "
			. "AND whJumpMass.attributeID = 1385 " 
			. "ORDER BY FIND_IN_SET(wormholeType,'7,8,9,1,2,3,4,5,6')", $staticType);
		//printf("<p>%s</p>", $staticInfoSQL);
		
		$rsStaticInfo = mysql_query_cache($staticInfoSQL,$whConn);
		if (!empty($rsStaticInfo)) {
			printf('<table class="iTbl">' 
				. '<tr><td class="hdr">Lifetime:</td><td class="data">%s hours</td></tr>'
				. '<tr><td class="hdr">Max. stable mass:</td><td class="data">%s kg (%s b)</td></tr>'
				. '<tr><td class="hdr">Mass variance (&plusmn;10%%):</td><td class="data">%s kg</td></tr>'
				. '<tr><td class="hdr">Max. jump mass:</td><td class="data">%s kg</td></tr>'
				. '<tr><td class="hdr">Signature size:</td><td class="data">%s</td></tr>'
				. '</table>', 
				number_format($rsStaticInfo[0]["maxStableTime"]/60,0),
				number_format($rsStaticInfo[0]["maxStableMass"],0,'.',','),
				number_format($rsStaticInfo[0]["maxStableMass"]/1000/1000/1000,1,'.',','),
				number_format($rsStaticInfo[0]["maxStableMass"]*0.1,0,'.',','),
				number_format($rsStaticInfo[0]["maxJumpMass"],0,'.',','),
				is_null($rsStaticInfo[0]["signatureSize"]) ? "?" : $rsStaticInfo[0]["signatureSize"]);
		} else {
			printf("Could not find information for wormhole type <strong>%s</strong>", $staticType);	
		}
	// ========================================================================
	} elseif ($action == "intel_whinfo") {
	// ========================================================================
		$n_LocusID = str_replace("_"," ",strtoupper(trim(mysql_real_escape_string($_REQUEST["lid"]))));
	
		$aTempWH = new Wormhole($n_LocusID);
		if ($aTempWH->isValidLocus()) {
			printf('<table class="iTbl">' 
				. '<tr><td class="hdr">Region:</td><td class="data">%s</td></tr>'
				. '<tr><td class="hdr">Constellation:</td><td class="data">%s</td></tr>'
				. '</table>',
				$aTempWH->getRegion(),
				$aTempWH->getConstellation());
			
			printf('<table class="sTbl" style="margin-top: 10px;">');
			printf('<tr class="hdr"><td>Planet</td><td>Type</td><td>Moons</td></tr>');
			foreach ($aTempWH->cCelestial as $aCelestial) {
				if ($aCelestial->isPlanet()) {
					printf('<tr><td class="hdr">%s</td><td class="data pt_%d">%s</td>', 
						$aCelestial->Name(), $aCelestial->typeID(), $aCelestial->planetType(), $aCelestial->typeID());
						
						$aMoons = $aTempWH->getCelestialChildren($aCelestial->itemID());
						$moonCount = 0;
						foreach ($aMoons as $cCelestial) {
							$moonCount += ($cCelestial->isMoon()) ? 1 : 0;
						}
						printf('<td class="data_r">%d</td>',$moonCount);
					
					printf('</tr>');
				}
			}
			printf('<tr class="ftr"><td class="data_r">%d</td><td></td><td class="data_r">%d</td></tr>', $aTempWH->getPlanetCount(), $aTempWH->getMoonCount());
			printf('</table>');
		} else {
			printf("<p><b>%s</b> is not a valid locus ID.</p>", $n_LocusID);	
		}
	// ========================================================================
	} elseif ($action == "intel_whanom") {
	// ========================================================================
		$n_LocusID = strtoupper(trim(mysql_real_escape_string($_REQUEST["lid"])));
	
		$aTempWH = new Wormhole($n_LocusID);
		if ($aTempWH->isValidLocus() && $aTempWH->hasAnomaly()) { // Technically we should check isWHLocus() here, but maybe CCP will make non-wormholes have anoms?
			$anomalySQL = sprintf("SELECT dgmTypeAttributes.*, dgmAttributeTypes.attributeName, dgmAttributeTypes.displayName "
				. "FROM ".EVEDB_NAME.".dgmTypeAttributes INNER JOIN ".EVEDB_NAME.".dgmAttributeTypes USING(attributeID) "
				. "WHERE typeID = '%s'", $aTempWH->getAnomalyTypeID());
			//print($anomalySQL);
			$rsAnomaly = mysql_query_cache($anomalySQL,$whConn);
			if (!empty($rsAnomaly)) {
				printf('<table class="sTbl">');
				printf('<tr class="hdr"><td>Effect</td><td>Strength</td></tr>');
				
				for ($a = 0; $a < count($rsAnomaly); $a++) {
					printf('<tr><td class="data">%s</td><td class="hdr">%s</td></tr>', 
						$rsAnomaly[$a]["displayName"], $aTempWH->getAnomEffect($rsAnomaly[$a]["attributeID"], $rsAnomaly[$a]["valueFloat"]));
				}

				printf('</table>');
			}
		}
	// ========================================================================
	} elseif ($action == "intel_dlgraph") {
	// ========================================================================
		$n_GraphSrc = urldecode(trim($_REQUEST["gsrc"]));
		if (isset($n_GraphSrc)) {
			printf('		<div class="dlChart">');
			printf('			<img src="%s" width="%d" height="%d" border="0"/>', $n_GraphSrc, EVEMAPS_GRAPH_WIDTH, EVEMAPS_GRAPH_HEIGHT);
			printf('		</div>');
		}
	// ========================================================================
	} elseif ($action == "intel_evekill_last") {
	// ========================================================================
		$n_LocusID = strtoupper(trim(mysql_real_escape_string($_REQUEST["lid"])));
		
		$aTempWH = new Wormhole($n_LocusID);
		if ($aTempWH->isValidLocus()) {
			$sDate = strtotime(sprintf("%d-%d-01", date("Y"), date("m")));
			
			printf('<div class="iHdr" rel="%s"><a href="http://eve-kill.net/?a=system_detail&sys_name=%s" target="_blank" alt="Visit Eve-Kill for more information about %s">Eve-Kill</a></div><span class="h4">&mdash;</span><div class="iData">', 
				$n_LocusID,
				str_replace("_"," ",$n_LocusID),
				str_replace("_"," ",$n_LocusID));
	
			$aKillboard = new Killboard($n_LocusID, "lastkill", EVEKILL_CACHE_LIFETIME_LASTKILL, sprintf(EVEKILL_LASTKILL_URL, urlencode($n_LocusID), EVEKILL_EPIC_MASK, sprintf("%s_23.59.59", date("Y-m-t")), sprintf("%s_00.00.00", date("Y-m-d", $sDate))), CACHE_USE_CACHE_REFRESH_IF_EXPIRED, false);
			
			if ($aKillboard->isValidKB()) {
				$lastKill = $aKillboard->res_metrics["newestKill"];
				if (!is_null($lastKill)) {
					printf('<p>Most recent <a href="%s" target="_blank">kill</a> recorded on <span class="h5">%s</span><span class="h4">&ndash;</span><span class="h5"><a href="javascript:void(0)" class="em" rel="gPilot;%d" title="Victim info">%s</a></span> (<span class="h5"><a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a></span>)%s lost a <span class="h5">%s</span> to <span class="h5"><a href="javascript:void(0)" class="em" rel="gPilot;%d" title="Final blow info">%s</a></span> (<span class="h5"><a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a></span>)%s in a <span class="h5">%s</span>%s.</p>',
						urldecode($lastKill->url),
						date("d M Y H:i", strtotime($lastKill->timestamp)),
						$lastKill->victimExternalID,
						$lastKill->victimName,
						$aKillboard->getEVEIDFromName($lastKill->victimCorpName, GET_CORPORATION_ID),
						$lastKill->victimCorpName,
						(strlen($lastKill->victimAllianceName) > 0 && $aKillboard->getEVEIDFromName($lastKill->victimAllianceName, GET_ALLIANCE_ID) != 0 && $aKillboard->getEVEIDFromName($lastKill->victimAllianceName, GET_ALLIANCE_ID) != NO_ALLIANCE_ALLIANCE_ID) ? sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $aKillboard->getEVEIDFromName($lastKill->victimAllianceName, GET_ALLIANCE_ID), $lastKill->victimAllianceName) : "",
						$lastKill->victimShipName,
						$aKillboard->getEVEIDFromName($lastKill->FBPilotName, GET_CHARACTER_ID),
						$lastKill->FBPilotName,
						$aKillboard->getEVEIDFromName($lastKill->FBCorpName, GET_CORPORATION_ID),
						$lastKill->FBCorpName,
						(strlen($lastKill->FBAllianceName) > 0 && $aKillboard->getEVEIDFromName($lastKill->FBAllianceName, GET_ALLIANCE_ID) != 0 && $aKillboard->getEVEIDFromName($lastKill->FBAllianceName, GET_ALLIANCE_ID) != NO_ALLIANCE_ALLIANCE_ID) ? sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $aKillboard->getEVEIDFromName($lastKill->FBAllianceName, GET_ALLIANCE_ID), $lastKill->FBAllianceName) : "",
						$aKillboard->getDBNameFromEVEID($lastKill->getFinalBlower()->shipTypeID),
						sizeof($lastKill->involved) > 1 ? 
							sprintf(' and <a href="javascript:void(0)" rel="gKillers;%d" title="Killers info">%d associate%s</a>', 
								$lastKill->internalID,
								sizeof($lastKill->involved)-1,	
								sizeof($lastKill->involved)-1 > 1 ? "s" : "") 
							: ""						
						);
				} else {
					// No kills found in the database (this should only occur when the killboard is empty)
					printf("<p>No kills recorded in this system in the past %d months.</p>", EVEKILL_ANALYSIS_MAX_MONTH_HISTORY);
				}
			} else {
				// Killboard was invalid, probably a problem querying evekill
				printf('<p class="fatalerror">Unable to query Eve-Kill data at this time - please try again later.</p>');
			}
			printf("</div>");
		}
	// ========================================================================
	} elseif ($action == "intel_evekill_analysis") {
	// ========================================================================
		$n_LocusID = strtoupper(trim(mysql_real_escape_string($_REQUEST["lid"])));
		
		$aTempWH = new Wormhole($n_LocusID);
		if ($aTempWH->isValidLocus()) {
			$sDate = strtotime(sprintf("%d-%d-01", date("Y"), date("m")));
			
			printf('<div class="iHdr" rel="%s">Intel</div><span class="h4">&mdash;</span><div class="iData">', $n_LocusID);
					
			$aKillboard = new Killboard($n_LocusID, "analysis", EVEKILL_CACHE_LIFETIME_ANALYSIS, sprintf(EVEKILL_ANALYSIS_URL, urlencode($n_LocusID), EVEKILL_EPIC_MASK, EVEKILL_KILL_COUNT_FOR_INTEL, sprintf("%s_23.59.59", date("Y-m-t")), sprintf("%s_00.00.00", date("Y-m-d", $sDate))), CACHE_USE_CACHE_UPDATE_NEW_DATA, true);
			
			if ($aKillboard->isValidKB()) {
				if ($aTempWH->isWHLocus()) {		
					
					if (false && ($_SERVER["REMOTE_ADDR"] == "94.169.97.53" || $_SERVER["REMOTE_ADDR"] = "80.45.72.211")) {
						printf('<p><span style="font-weight: bold; font-size: 14px;">DEBUG TABLE:</span><br/><table style="border-collapse: separate; border-spacing: 2px; font-size: 10px;">');
						printf('<tr style="font-weight: bold;"><td>CORP</td><td>ALLIANCE</td><td>KILLS</td><td>LOSSES</td><td>BATTLES</td><td>SCORE</td><td>timeEarliestKill</td><td>timeLatestKill</td><td>timeEarliestLoss</td><td>timeLatestLoss</td><td>involvedCount</td></tr>');
						
						foreach ($aKillboard->corp as $aCorp) {
							printf('<tr%s><td><strong>%s</strong></td><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td><strong>%0.2f</strong> (a: %0.1f%%) (t: %0.1f%%) (h: %0.1f%%) (as: %0.1f%%) (p: %f%%)</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td></tr>',
								(!is_null($aCorp->residency["evicted"])) ? ' style="color: #FF3030;"' : ((sizeof($aCorp->residency["posactvy"]) > 0) ? ' style="color: #30FF30"' : ((sizeof($aCorp->residency["caplcuse"]) > 0) ? ' style="color: #FFFF30"' : "")),
								$aCorp->corporationName . " (" . $aCorp->corporationID . ")",
								$aCorp->allianceName . " (" . $aCorp->allianceID . ")",
								$aCorp->killCount, 
								$aCorp->lossCount, 
								sizeof($aCorp->battle),
								$aCorp->residency["score"],	
						/* a */	($aKillboard->totalAllianceResidencyScore($aCorp->allianceID) != 0) ? ($aCorp->residency["score"] / $aKillboard->totalAllianceResidencyScore($aCorp->allianceID)) * 100 : 0,	
						/* t */	($aKillboard->res_metrics["totalScore"] != 0) ? ($aCorp->residency["score"] / $aKillboard->res_metrics["totalScore"]) * 100 : 0,
						/* h */	($aCorp->residency["score"] / $aKillboard->res_metrics["maxScore"]) * 100,
						/* as */($aKillboard->totalAllianceResidencyScore($aCorp->allianceID) != 0) ? ($aKillboard->totalAllianceResidencyScore($aCorp->allianceID) / $aKillboard->res_metrics["totalScore"]) * 100 : 0,
						/* p */	$aCorp->residency["z-perc"],
								!is_null($aCorp->timeEarliestKill) ? date("Y-m-d H:i:s", $aCorp->timeEarliestKill) : '-', 
								!is_null($aCorp->timeLatestKill) ? date("Y-m-d H:i:s", $aCorp->timeLatestKill) : '-',
								!is_null($aCorp->timeEarliestLoss) ? date("Y-m-d H:i:s", $aCorp->timeEarliestLoss) : '-', 
								!is_null($aCorp->timeLatestLoss) ? date("Y-m-d H:i:s", $aCorp->timeLatestLoss) : '-',
								$aCorp->involvedCount);
						}
						printf('</table>');
						printf('</p>');
					}
					

					$aryResident 				= array();
					$aryEvictee					= array();
					$residentCorps 				= array();
					$residentCorpsInAlliance 	= 0;
					$occupiedAlliance 			= null;
					$tzArray 					= array();
					$allianceOccupied 			= false;
					 
					if ($aKillboard->corp[0]->residency["stddev"] >= SCORE_RES_STDDEV_TOO_LOW_THRESHOLD && $aKillboard->res_metrics["battleCount"] >= SCORE_BATTLE_COUNT_TOO_LOW_THRESHOLD) {
						dprintf('residency: std dev for system: %f (threshold: %f)', $aKillboard->corp[0]->residency["stddev"], SCORE_RES_STDDEV_TOO_LOW_THRESHOLD);
	
						foreach ($aKillboard->corp as $aCorp) {
							if (!$aCorp->isEvicted() && (sizeof($aCorp->battle) >= SCORE_MINIMUM_BATTLE_COUNT_FOR_RES && (($aCorp->residency["score"] / $aKillboard->res_metrics["maxScore"]) * 100) >= SCORE_RESIDENCY_THRESHOLD_PERC) || (($aKillboard->totalAllianceResidencyScore($aCorp->allianceID) != 0) ? ($aKillboard->totalAllianceResidencyScore($aCorp->allianceID) / $aKillboard->res_metrics["totalScore"]) * 100 : 0) >= SCORE_RES_ASSUME_ALLIANCE_OCCUPANCY) {
								$aryResident[] = $aCorp;
								
								// The current corp is assumed to be a resident - their residency score as a
								// percentage of the total residency score is sufficiently high enough.
								
								// Check to see whether or not we should show the alliance, or the corp.
								// Sometimes an alliance will have a dominant presence in more than one corp							
								if ($aCorp->allianceID == (!is_null($occupiedAlliance) ? $occupiedAlliance->allianceID : -1) || (($aKillboard->totalAllianceResidencyScore($aCorp->allianceID) != 0) ? ($aKillboard->totalAllianceResidencyScore($aCorp->allianceID) / $aKillboard->res_metrics["totalScore"]) * 100 : 0) >= SCORE_RES_ASSUME_ALLIANCE_OCCUPANCY && $aKillboard->getAllianceCorpCount($aCorp) > 1) {
									$allianceOccupied = true;
									$residentCorpsInAlliance++;
									$occupiedAlliance = $aCorp;
									
									$residentCorps[] = sprintf('<span class="h5"><a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a></span>%s', 
										$aCorp->corporationID, 
										$aCorp->corporationName, 
										(!$aCorp->isEvicted() ? sprintf(' (%s%0.1f%%)', 
											(sizeof($residentCorps) == 0) ? "confidence: " : "", 
											$aCorp->residency["z-perc"]) : ''));
								} else {
									// Not alliance controlled, individual corps are active though
									$residentCorps[] = sprintf('<span class="h5"><a href="javascript:void(0)" class="em3" rel="gCorp;%d" title="Corporation info">%s</a></span>%s (%d kills, %d losses in %d battles)',
										$aCorp->corporationID, 
										$aCorp->corporationName, 
										($aKillboard->isCorpInAlliance($aCorp) ? sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $aCorp->allianceID, $aCorp->allianceName) : ''), 
										$aCorp->killCount,
										$aCorp->lossCount,
										sizeof($aCorp->battle));
								}
							}
						}
						
						if (sizeof($aryResident) > 0) {
							printf('<p><div class="ss">%s%s by ', ICON_RESIDENT_IMAGE, ((($aKillboard->res_metrics["totalScore"] != 0) ? ($aKillboard->res_metrics["topCorp"]->residency["score"] / $aKillboard->res_metrics["totalScore"]) * 100 : 0) >= SCORE_CERTIFIED_RESIDENCY_PERC) || ((($aKillboard->totalAllianceResidencyScore($aKillboard->res_metrics["topCorp"]->allianceID) != 0) ? ($aKillboard->totalAllianceResidencyScore($aKillboard->res_metrics["topCorp"]->allianceID) / $aKillboard->res_metrics["totalScore"]) * 100 : 0) >= SCORE_RES_ASSUME_ALLIANCE_OCCUPANCY) ? "Occupied" : "Probably occupied"); 
							
							// ============================================================================== 			
							if ($allianceOccupied && $residentCorpsInAlliance > 1) {
							// ============================================================================== 
								printf('the <span class="h5"><a href="javascript:void(0)" class="em2" rel="gAlliance;%d" title="Alliance info">%s</a></span> alliance<span class="h4">&ndash;</span></div>%s</p>', $occupiedAlliance->allianceID, $occupiedAlliance->allianceName, fimplode(', ',$residentCorps));	
								$topCorp = &$aKillboard->res_metrics["topCorp"];
								if (!is_null($topCorp)) {
									// If top corp doesn't have any kills, they won't have any involved information, so show an alternate loss printout instead
									if ($topCorp->killCount == 0) {
										printf('<p>%sMost active are <span class="h5"><a href="javascript:void(0)" class="em" rel="gCorp;%d" title="Corporation info">%s</a></span>, with %d losses over %d battles.  ',
											ICON_ACTIVITY_IMAGE,
											$topCorp->corporationID,
											$topCorp->corporationName,
											$topCorp->lossCount,
											sizeof($topCorp->battle));
									} else {
										printf('<p>%sMost active are <span class="h5"><a href="javascript:void(0)" class="em" rel="gCorp;%d" title="Corporation info">%s</a></span>, with <span class="h5">%d</span> kills and <span class="h5">%d</span> losses, involving an average of <span class="h5">%d</span> ship%s. ',
											ICON_ACTIVITY_IMAGE,
											$topCorp->corporationID,
											$topCorp->corporationName,
											$topCorp->killCount,
											$topCorp->lossCount,
											floor($topCorp->involvedCount/$topCorp->killCount),
											floor($topCorp->involvedCount/$topCorp->killCount) > 1 ? "s" : "");
									}
									
									// Get the top corp in alliance ship types
									$topCorpShips = $topCorp->shipTypes;
									if (is_array($topCorpShips)) {
										arsort($topCorpShips, SORT_NUMERIC);
										
										// $bigKillShips contains a list of ship typeIDs together with the number of 
										// times they were used.  In order to get the total count of ship CLASSES we 
										// have to build a convoluted SQL query using UNIONs in order to add a column
										// containing the counts-per-ship, and then aggregating them into a 
										// total-per-class, which is what we display.
															
										$bigShipJoinSQL = "LEFT JOIN (";
										foreach($topCorpShips as $sName => $sUses) {
											$bigShipJoinSQL .= sprintf("SELECT %d AS typeID, %d AS useCnt%s",
												$sName, $sUses, ($sName != end(array_keys($topCorpShips)) ? " UNION " : ""));
										}
										$bigShipJoinSQL .= ") sK USING(typeID)";
										
										$shipClassSQL = sprintf("SELECT ig.groupName, SUM(sK.useCnt) AS timesUsed FROM ".EVEDB_NAME.".invTypes it LEFT JOIN ".EVEDB_NAME.".invGroups ig USING(groupID) %s WHERE (it.typeID IN (%s) AND NOT it.typeID = 0) AND ig.anchorable = 0 GROUP BY ig.groupName ORDER BY timesUsed DESC", $bigShipJoinSQL, implode(",",array_keys($topCorpShips)));
										$rsShipClass = mysql_query_cache($shipClassSQL);
										if (is_array($rsShipClass)) {
											if (!empty($rsShipClass)) {			
												$classCnt = 1;
												$classCount = count($rsShipClass);
												
												//$bigKillShipID = key($topCorpShips);
												
												printf('Their favourite ship class%s %s <span class="h5">%s</span> (%0.1f%% of total)', ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1 ? "es" : ""), ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1 ? "include" : "is"), $rsShipClass[0]["groupName"], (($rsShipClass[0]["timesUsed"]/$classCount)*100));
												if ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1) {
													$clAry = array();
													for ($s = 1; $s < count($rsShipClass) && ($classCnt++ < EVEKILL_MAX_SHIP_CLASSES_TO_SHOW); $s++) {
														$clAry[] = sprintf('%s (%0.1f%%)', $rsShipClass[$s]["groupName"], (($rsShipClass[$s]["timesUsed"]/$classCount)*100));		
													}
													printf(", %s.  ", (isset($clAry) ? implode(", ",$clAry) : ""));
												} else {
													printf(".  ");	
												}
											}
										}
									}
									printf("</p>");
								}
							// ============================================================================== 
							} else {
							// ============================================================================== 
								// Occupied by one or more corps that are distinct from a single alliance
								printf("</div>%s.  ", fimplode(', ',$residentCorps));
								
								// If we only have one resident, get their ships
								if (sizeof($residentCorps) == 1) {
									$topCorp = &$aKillboard->res_metrics["topCorp"];
									if (!is_null($topCorp)) {
										$topCorpShips = $topCorp->shipTypes;
										if (is_array($topCorpShips) && $topCorp->killCount > 0) {
											arsort($topCorpShips, SORT_NUMERIC);
											
											// $bigKillShips contains a list of ship typeIDs together with the number of 
											// times they were used.  In order to get the total count of ship CLASSES we 
											// have to build a convoluted SQL query using UNIONs in order to add a column
											// containing the counts-per-ship, and then aggregating them into a 
											// total-per-class, which is what we display.
																
											$bigShipJoinSQL = "LEFT JOIN (";
											foreach($topCorpShips as $sName => $sKills) {
												$bigShipJoinSQL .= sprintf("SELECT %d AS typeID, %d AS useCnt%s",
													$sName, $sKills, ($sName != end(array_keys($topCorpShips)) ? " UNION " : ""));
											}
											$bigShipJoinSQL .= ") sK USING(typeID)";
											
											$shipClassSQL = sprintf("SELECT ig.groupName, SUM(sK.useCnt) AS timesUsed FROM ".EVEDB_NAME.".invTypes it LEFT JOIN ".EVEDB_NAME.".invGroups ig USING(groupID) %s WHERE (it.typeID IN (%s) AND NOT it.typeID = 0) AND ig.anchorable = 0 GROUP BY ig.groupName ORDER BY timesUsed DESC", $bigShipJoinSQL, implode(",",array_keys($topCorpShips)));
											$rsShipClass = mysql_query_cache($shipClassSQL);
											if (is_array($rsShipClass)) {
												if (!empty($rsShipClass)) {			
													$classCnt = 1;
													$classCount = count($rsShipClass);
												
													//$bigKillShipID = key($topCorpShips);
												
													printf('Their favourite ship class%s %s <span class="h5">%s</span> (%0.1f%% of total)', ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1 ? "es" : ""), ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1 ? "include" : "is"), $rsShipClass[0]["groupName"], (($rsShipClass[0]["timesUsed"]/$classCount)*100));
													if ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1) {
														$clAry = array();
														for ($s = 1; $s < count($rsShipClass) && ($classCnt++ < EVEKILL_MAX_SHIP_CLASSES_TO_SHOW); $s++) {
															$clAry[] = sprintf('%s (%0.1f%%)', $rsShipClass[$s]["groupName"], (($rsShipClass[$s]["timesUsed"]/$classCount)*100));
														}
														printf(", %s.  ", (isset($clAry) ? implode(", ",$clAry) : ""));
													} else {
														printf(".  ");	
													}
												}
											}
										}
										
										// Eviction/inactivity check
										if ($aTempWH->isWHLocus() && (strtotime("+".EVEKILL_EVICTION_INACTIVE_MONTHS." months", strtotime($topCorp->battle[0]->timestamp))) - time() < 0) {
											printf('<br/><span class="advisory">NOTE: Their last battle here was over %d months ago, on %s - possibly inactive or have moved out?</span>', EVEKILL_EVICTION_INACTIVE_MONTHS, date("d M Y H:i", strtotime($topCorp->battle[0]->timestamp))); 
										}
									}
								}
								printf('</p>');	
							// ============================================================================== 
							}
							// ============================================================================== 
						}
	
						$tzArray = $aKillboard->getTimezoneActivity($aryResident);
						$addTZ = array();
						if (is_array($tzArray)) {
							$topTZ = current($tzArray);
							printf('<p>%s%s are most active in system during the <span class="h6">%s</span> timezone (EVE%s) with <span class="h6">%0.2f%%</span> of all activity. ', ICON_TIMEZONE_IMAGE, ($allianceOccupied || sizeof($aryResident) > 1) ? "Collectively they" : "They", $topTZ["name"], sprintf("%s%d", ($topTZ["shift"] >= 0 ? "+" : ""), $topTZ["shift"]), $topTZ["r-perc"]);
							foreach($tzArray as $aTimezone) {
								if ($aTimezone == $topTZ) continue;
								$addTZ[] = sprintf('%s - %0.2f%%', $aTimezone["name"], $aTimezone["r-perc"]);
							}
							printf("(%s).</p>", implode(", ",$addTZ));
						}
						
						// Work out whether the top corp has used capital ships in this system
						$capKills = $aKillboard->getActivityInvolvingCaps();
						if (sizeof($capKills) > 0) {
							// Itemise capital ships, and link to eve-kill
							$capStr = array();
							$killDB = array();
							$lastKillID = 0;
							$cpCnt = 0;
							$capUseCount = 0;
							foreach($capKills as $aCap) {
								if (!in_array(sprintf("%s%s", $aKillboard->getShipNameForTypeID($aCap["typeID"]), (!$aCap["isShip"]) ? " <sup>(delegated)</sup>" : ""), $capStr)) {
									$capStr[] = sprintf('%s%s', $aKillboard->getShipNameForTypeID($aCap["typeID"]), (!$aCap["isShip"]) ? " <sup>(delegated)</sup>" : "");
								}
								if (!strcasecmp($aCap["kill"]->url, $lastKillID) == 0) {
									if (++$cpCnt <= SHOW_MAX_CAPITAL_KILLMAILS) {
										$killDB[] = sprintf('<a href="%s" target="_blank">%d</a>', $aCap["kill"]->url, $cpCnt);
									}
									
									// We keep a running update of the current kill ID so as not to duplicate
									// capital sightings
									$lastKillID = $aCap["kill"]->url;
								}
							}
							
							printf('<p>%s<span class="capusage">Capital ship%s (%s) %s been used in combat in this wormhole on %d occasion%s - %s%s</span></p>', ICON_CAPUSE_IMAGE, (sizeof($capStr) > 1) ? "s" : "", implode(', ',$capStr), (sizeof($capStr) > 1) ? "have" : "has", $cpCnt, ($cpCnt > 1) ? "s" : "",  (($cpCnt > SHOW_MAX_CAPITAL_KILLMAILS) ? sprintf('last %d: ', SHOW_MAX_CAPITAL_KILLMAILS) : ''), implode(', ',$killDB));
						}
					} else {
						// We have no residents, assume unoccupied
						dprintf('residency: std dev for system: %f (threshold: %f)', $aKillboard->corp[0]->residency["stddev"], SCORE_RES_STDDEV_TOO_LOW_THRESHOLD);
						printf('<p>%s<span class="advisory2">System may be unoccupied or contested<span class="h4">&ndash;</span>%d seperate corporations have had %d isolated battles over a period of %d days.</span></p>', ICON_VACATED_IMAGE, sizeof($aKillboard->corp), $aKillboard->res_metrics["battleCount"], ((strtotime($aKillboard->res_metrics["newestKill"]->timestamp) - strtotime($aKillboard->res_metrics["oldestKill"]->timestamp)) / 60 / 60 / 24));
					}
					
					$evicteeDB = array();
					// Show instances where a corp recovered from a POS loss
					foreach ($aKillboard->corp as $eCorp) {
						if ($eCorp->isNPCCorporation()) continue;
						
						// Max evicters to show, changes depending on how many POSes have been lost
						$evicterMaxCnt = (sizeof($eCorp->residency["evicted"]["losses"] > 1) ? EVEKILL_MAX_EVICTERS_FOR_MULTIPOS_LOSS : EVEKILL_MAX_EVICTERS_TO_REPORT);
						
						if (!is_null($eCorp->residency["evicted"]) && ($eCorp->residency["evicted"]["ppl_score"] >= SCORE_RES_STILL_RES_THRESHOLD_SCORE || strtotime("+".EVEKILL_RECENT_EVICTION_IN_DAYS." days", strtotime($eCorp->getNewestEvictionPOSKill()->timestamp)) - time() >= 0)) {
							$aryKillers = array();
							$killDB = array();
							$pkCnt = 0;
							// Corp suffered a POS loss, but they recovered
							foreach($eCorp->residency["evicted"]["losses"] as $aPOSKill) {
								$aryKillers = array_merge($aryKillers, $aKillboard->getInvolvedCorpsOnKill($aPOSKill));
							}
							// Remove duplicate corps from combined evicter array
							//$aryKillers = $aKillboard->filterUniqueCorps($aryKillers);
							$aryKillers = $aKillboard->beautifyCorps($aryKillers);			
							
							$aryKillAry = array();
							if (sizeof($aryKillers) > 0) {
								foreach($aryKillers as $allianceID => $allyData) {
									$evtCnt = 0;
									if ($allianceID !== NO_ALLIANCE_ALLIANCE_ID) {
										if (sizeof($allyData["corps"]) == 1) {
											// Only one corp recorded in the alliance, so just show them normally
											$corpData = current($allyData["corps"]);
											$aryKillAry[] = sprintf('<a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a>%s', 
												$corpData["corporationID"], 
												$corpData["corporationName"],
												sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $allyData["allianceID"], $allyData["allianceName"]));
										} else {
											$corpKillAry = array();
											foreach($allyData["corps"] as $corporationID => $corpData) {
												if (++$evtCnt > EVEKILL_MAX_EVICTERS_TO_REPORT) continue;
												$corpKillAry[] = sprintf('<a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a>', $corpData["corporationID"], $corpData["corporationName"]);
											}
											$aryKillAry[] = sprintf('%s<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>%s (%s%s)',
												substr_compare($allyData["allianceName"], "the", 0, 3, true) === 0 ? "" : "the ",
												$allyData["allianceID"],
												$allyData["allianceName"],
												substr_compare($allyData["allianceName"], "alliance", strlen($allyData["allianceName"])-8, 8, true) === 0 ? "" : " alliance",
												($evtCnt <= EVEKILL_MAX_EVICTERS_TO_REPORT) ? fimplode(", ", $corpKillAry) : implode(", ", $corpKillAry),
												($evtCnt > EVEKILL_MAX_EVICTERS_TO_REPORT) ? sprintf(" and %d other%s", ($evtCnt - EVEKILL_MAX_EVICTERS_TO_REPORT), ($evtCnt - EVEKILL_MAX_EVICTERS_TO_REPORT > 1 ? "s" : "")) : "");
										}
									} else {
										// Individual unallied corps
										$corpKillAry = array();
										foreach($allyData["corps"] as $corporationID => $corpData) {
											$aryKillAry[] = sprintf('<a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a>', $corpData["corporationID"], $corpData["corporationName"]);
										}
									}
								}
							/*$aryKillAry = array();
							if (sizeof($aryKillers) > 0) {
								$evtCnt = 0;
								foreach($aryKillers as $aKiller) {
									if (++$evtCnt <= $evicterMaxCnt && !in_array($aKiller->corporationName, $aryKillAry) && $eCorp !== $aKiller) {
										$aryKillAry[] = sprintf('<a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a>%s', 
											$aKiller->corporationID, 
											$aKiller->corporationName,
											(strlen($aKiller->allianceName) > 0 && ($aKillboard->getEVEIDFromName($aKiller->allianceName, GET_ALLIANCE_ID) != 0 && $aKillboard->getEVEIDFromName($aKiller->allianceName, GET_ALLIANCE_ID) != NO_ALLIANCE_ALLIANCE_ID)) ? sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $aKillboard->getEVEIDFromName($aKiller->allianceName, GET_ALLIANCE_ID), $aKiller->allianceName) : "");
									}
								}
							*/

								$oldPOSKillDT = new DateTime($eCorp->getOldestEvictionPOSKill()->timestamp);
								$newPOSKillDT = new DateTime($eCorp->getNewestEvictionPOSKill()->timestamp);
								if ($eCorp->residency["evicted"]["ppl_score"] >= SCORE_RES_STILL_RES_THRESHOLD_SCORE) { 
									// EVICTION RECOVERY
									printf('<p>%s<span class="eviction"><span class="h5">%s</span> %s &mdash; to %s%s - but they appear to have recovered.</span></p>',
										ICON_COMBAT_IMAGE,
										$eCorp->corporationName,
										(sizeof($eCorp->residency["evicted"]["losses"]) == 1) ? sprintf('<a href="%s" target="_blank">lost a POS</a> on <span class="h5">%s</span>', $eCorp->getOldestEvictionPOSKill()->url, date("d M Y H:i", strtotime($eCorp->getOldestEvictionPOSKill()->timestamp))) : sprintf('lost <span class="h5">%d</span> POS between %s and %s', sizeof($eCorp->residency["evicted"]["losses"]), date(($oldPOSKillDT->diff($newPOSKillDT)->days === 0 ? "d M Y H:i" : "d M Y"), strtotime($eCorp->getOldestEvictionPOSKill()->timestamp)), date(($oldPOSKillDT->diff($newPOSKillDT)->days === 0 ? "d M Y H:i" : "d M Y"), strtotime($eCorp->getNewestEvictionPOSKill()->timestamp))), 
										($evtCnt <= $evicterMaxCnt) ? fimplode(', ',$aryKillAry) : implode(', ',$aryKillAry),
										($evtCnt > $evicterMaxCnt) ? sprintf(" and %d other%s ", ($evtCnt - $evicterMaxCnt), ($evtCnt - $evicterMaxCnt > 1 ? "s" : "")) : "");
								} elseif (strtotime("+".EVEKILL_RECENT_EVICTION_IN_DAYS." days", strtotime($eCorp->getNewestEvictionPOSKill()->timestamp)) - time() >= 0) {
									// RECENT EVICTION
									printf('<p>%s<span class="eviction"><span class="h5">RECENT EVICTION ACTIVITY</span> &mdash; <span class="h5"><a href="javascript:void(0)" class="em" rel="gCorp;%d" title="Corporation info">%s</a></span>%s %s &mdash; to %s%s.</span></p>',
										ICON_EVICTING_IMAGE,
										$eCorp->corporationID, 
										$eCorp->corporationName,
										(strlen($eCorp->allianceName) > 0 && ($aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID) != 0 && $aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID) != NO_ALLIANCE_ALLIANCE_ID)) ? sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID), $eCorp->allianceName) : "",
										(sizeof($eCorp->residency["evicted"]["losses"]) == 1) ? sprintf('have <a href="%s" target="_blank">lost a POS</a> on <span class="h5">%s</span>', $eCorp->getOldestEvictionPOSKill()->url, date("d M Y H:i", strtotime($eCorp->getOldestEvictionPOSKill()->timestamp))) : sprintf('have lost <span class="h5">%d</span> POS between %s and %s', sizeof($eCorp->residency["evicted"]["losses"]), date(($oldPOSKillDT->diff($newPOSKillDT)->days === 0 ? "d M Y H:i" : "d M Y"), strtotime($eCorp->getOldestEvictionPOSKill()->timestamp)), date(($oldPOSKillDT->diff($newPOSKillDT)->days === 0 ? "d M Y H:i" : "d M Y"), strtotime($eCorp->getNewestEvictionPOSKill()->timestamp))), 
										($evtCnt <= $evicterMaxCnt) ? fimplode(', ',$aryKillAry) : implode(', ',$aryKillAry),
										($evtCnt > $evicterMaxCnt) ? sprintf(" and %d other%s", ($evtCnt - $evicterMaxCnt), ($evtCnt - $evicterMaxCnt > 1 ? "s" : "")) : "");
								}
							}	
						} elseif ($eCorp->isEvicted()) ($evicteeDB[] = $eCorp);
					}
					
					if (sizeof($evicteeDB) > 0) {
						foreach($evicteeDB as $eCorp) {
							
							// Max evicters to show, changes depending on how many POSes have been lost
							$evicterMaxCnt = (sizeof($eCorp->residency["evicted"]["losses"] > 1) ? EVEKILL_MAX_EVICTERS_FOR_MULTIPOS_LOSS : EVEKILL_MAX_EVICTERS_TO_REPORT);
							
							$aryKillers = array();
							$killDB = array();
							$pkCnt = 0;
							// Corp suffered a POS loss, but they recovered
							foreach($eCorp->residency["evicted"]["losses"] as $aPOSKill) {
								$aryKillers = array_merge($aryKillers, $aKillboard->getInvolvedCorpsOnKill($aPOSKill));
							}
							// Remove duplicate corps from combined evicter array
							//$aryKillers = $aKillboard->filterUniqueCorps($aryKillers);
							$aryKillers = $aKillboard->beautifyCorps($aryKillers);	
							
							$aryKillAry = array();
							if (sizeof($aryKillers) > 0) {
								foreach($aryKillers as $allianceID => $allyData) {
									$evtCnt = 0;
									if ($allianceID !== NO_ALLIANCE_ALLIANCE_ID) {
										if (sizeof($allyData["corps"]) == 1) {
											// Only one corp recorded in the alliance, so just show them normally
											$corpData = current($allyData["corps"]);
											$aryKillAry[] = sprintf('<a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a>%s', 
												$corpData["corporationID"], 
												$corpData["corporationName"],
												sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $allyData["allianceID"], $allyData["allianceName"]));
										} else {
											$corpKillAry = array();
											foreach($allyData["corps"] as $corporationID => $corpData) {
												if (++$evtCnt > EVEKILL_MAX_EVICTERS_TO_REPORT) continue;
												$corpKillAry[] = sprintf('<a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a>', $corpData["corporationID"], $corpData["corporationName"]);
											}
											$aryKillAry[] = sprintf('%s<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>%s (%s%s)',
												substr_compare($allyData["allianceName"], "the", 0, 3, true) === 0 ? "" : "the ",
												$allyData["allianceID"],
												$allyData["allianceName"],
												substr_compare($allyData["allianceName"], "alliance", strlen($allyData["allianceName"])-8, 8, true) === 0 ? "" : " alliance",
												($evtCnt <= EVEKILL_MAX_EVICTERS_TO_REPORT) ? fimplode(", ", $corpKillAry) : implode(", ", $corpKillAry),
												($evtCnt > EVEKILL_MAX_EVICTERS_TO_REPORT) ? sprintf(" and %d other%s", ($evtCnt - EVEKILL_MAX_EVICTERS_TO_REPORT), ($evtCnt - EVEKILL_MAX_EVICTERS_TO_REPORT > 1 ? "s" : "")) : "");	
										}
									} else {
										// Individual unallied corps
										$corpKillAry = array();
										foreach($allyData["corps"] as $corporationID => $corpData) {
											$aryKillAry[] = sprintf('<a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a>', $corpData["corporationID"], $corpData["corporationName"]);
										}
									}
								}
								
								$aryEvictee[] = sprintf('<span class="h5"><a href="javascript:void(0)" class="em" rel="gCorp;%d" title="Corporation info">%s</a></span>%s%s', 
									$eCorp->corporationID, 
									$eCorp->corporationName,
									(strlen($eCorp->allianceName) > 0 && ($aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID) != 0 && $aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID) != NO_ALLIANCE_ALLIANCE_ID)) ? sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID), $eCorp->allianceName) : "",
									(sizeof($evicteeDB) == 1) ? sprintf(' &ndash; %s on %s, by %s%s', 
										sprintf('<a href="%s" target="_blank">evicted</a>', $eCorp->getNewestEvictionPOSKill()->url),
										date("d M Y H:i", strtotime($eCorp->getNewestEvictionPOSKill()->timestamp)), 
										($evtCnt <= $evicterMaxCnt) ? fimplode(', ',$aryKillAry) : implode(', ',$aryKillAry),
										($evtCnt > $evicterMaxCnt) ? sprintf(" and %d other%s ", ($evtCnt - $evicterMaxCnt), ($evtCnt - $evicterMaxCnt > 1 ? "s" : "")) : "") : sprintf(' &ndash; %s on %s', 
											sprintf('<a href="%s" target="_blank">evicted</a>', $eCorp->getNewestEvictionPOSKill()->url),
											date("d M Y H:i", strtotime($eCorp->getNewestEvictionPOSKill()->timestamp))));
							}
							
							/*
							$aryKillAry = array();
							if (sizeof($aryKillers) > 0) {
								$evtCnt = 0;
								foreach($aryKillers as $aKiller) {
									if (++$evtCnt <= $evicterMaxCnt && !in_array($aKiller->corporationName, $aryKillAry) && $eCorp !== $aKiller) {
										$aryKillAry[] = sprintf('<a href="javascript:void(0)" rel="gCorp;%d" title="Corporation info">%s</a>%s', 
											$aKiller->corporationID, 
											$aKiller->corporationName,
											(strlen($aKiller->allianceName) > 0 && ($aKillboard->getEVEIDFromName($aKiller->allianceName, GET_ALLIANCE_ID) != 0 && $aKillboard->getEVEIDFromName($aKiller->allianceName, GET_ALLIANCE_ID) != NO_ALLIANCE_ALLIANCE_ID)) ? sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $aKillboard->getEVEIDFromName($aKiller->allianceName, GET_ALLIANCE_ID), $aKiller->allianceName) : "");
									}
								}
							
								$aryEvictee[] = sprintf('<span class="h5"><a href="javascript:void(0)" class="em" rel="gCorp;%d" title="Corporation info">%s</a></span>%s%s', 
												$eCorp->corporationID, 
												$eCorp->corporationName,
												(strlen($eCorp->allianceName) > 0 && ($aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID) != 0 && $aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID) != NO_ALLIANCE_ALLIANCE_ID)) ? sprintf(' [<span class="h5"><a href="javascript:void(0)" rel="gAlliance;%d" title="Alliance info">%s</a></span>]', $aKillboard->getEVEIDFromName($eCorp->allianceName, GET_ALLIANCE_ID), $eCorp->allianceName) : "",
												(sizeof($evicteeDB) == 1) ? sprintf(' (%s on %s, by %s%s)', 
													sprintf('<a href="%s" target="_blank">evicted</a>', $eCorp->getNewestEvictionPOSKill()->url),
													date("d M Y H:i", strtotime($eCorp->getNewestEvictionPOSKill()->timestamp)), 
													($evtCnt <= $evicterMaxCnt) ? fimplode(', ',$aryKillAry) : implode(', ',$aryKillAry),
													($evtCnt > $evicterMaxCnt) ? sprintf(" and %d other%s ", ($evtCnt - $evicterMaxCnt), ($evtCnt - $evicterMaxCnt > 1 ? "s" : "")) : "") : sprintf(' (%s on %s)', 
														sprintf('<a href="%s" target="_blank">evicted</a>', $eCorp->getNewestEvictionPOSKill()->url),
														date("d M Y H:i", strtotime($eCorp->getNewestEvictionPOSKill()->timestamp))));
							}
							*/
						}
						if (sizeof($aryEvictee) > 0) {
							printf('<p>%s<span class="evicted">Previous residents include: %s</span></p>', ICON_EVICTED_IMAGE, fimplode(", ",$aryEvictee));	
						}
					}
				} else {
					// Not a wormhole locus, just show the most active corp
					
					// Re-order corp table by kills descending
					usort($aKillboard->corp, 'kcmp');
					
					$topCorp = current($aKillboard->corp);
					if (is_object($topCorp)) {
						printf('<p>Most active are <span class="h5"><a href="javascript:void(0)" class="em" rel="gCorp;%d" title="Corporation info">%s</a></span>, with <span class="h5">%d</span> kills and <span class="h5">%d</span> losses, involving an average of <span class="h5">%d</span> ships. ',
							$topCorp->corporationID,
							$topCorp->corporationName,
							$topCorp->killCount,
							$topCorp->lossCount,
							floor($topCorp->involvedCount/$topCorp->killCount));
							
						// Get the top corp in alliance ship types
						$topCorpShips = $topCorp->shipTypes;
						if (is_array($topCorpShips)) {
							arsort($topCorpShips, SORT_NUMERIC);
							
							// $bigKillShips contains a list of ship typeIDs together with the number of 
							// times they were used.  In order to get the total count of ship CLASSES we 
							// have to build a convoluted SQL query using UNIONs in order to add a column
							// containing the counts-per-ship, and then aggregating them into a 
							// total-per-class, which is what we display.
												
							$bigShipJoinSQL = "LEFT JOIN (";
							foreach($topCorpShips as $sName => $sKills) {
								$bigShipJoinSQL .= sprintf("SELECT %d AS typeID, %d AS useCnt%s",
									$sName, $sKills, ($sName != end(array_keys($topCorpShips)) ? " UNION " : ""));
							}
							$bigShipJoinSQL .= ") sK USING(typeID)";
							
							$shipClassSQL = sprintf("SELECT ig.groupName, SUM(sK.useCnt) AS timesUsed FROM ".EVEDB_NAME.".invTypes it LEFT JOIN ".EVEDB_NAME.".invGroups ig USING(groupID) %s WHERE (it.typeID IN (%s) AND NOT it.typeID = 0) AND ig.anchorable = 0 GROUP BY ig.groupName ORDER BY timesUsed DESC", $bigShipJoinSQL, implode(",",array_keys($topCorpShips)));
							
							$rsShipClass = mysql_query_cache($shipClassSQL);
							if (is_array($rsShipClass)) {
								if (!empty($rsShipClass)) {			
									$classCnt = 1;
									$classCount = count($rsShipClass);
											
									//$bigKillShipID = key($topCorpShips);
											
									printf('Their favourite ship class%s %s <span class="h5">%s</span> (%0.1f%% of total)', ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1 ? "es" : ""), ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1 ? "include" : "is"), $rsShipClass[0]["groupName"], (($rsShipClass[0]["timesUsed"]/$classCount)*100));
									if ($classCount > 1 && EVEKILL_MAX_SHIP_CLASSES_TO_SHOW > 1) {
										$clAry = array();
										for ($s = 1; $s < count($rsShipClass) && ($classCnt++ < EVEKILL_MAX_SHIP_CLASSES_TO_SHOW); $s++) {
											$clAry[] = sprintf('%s (%0.1f%%)', $rsShipClass[$s]["groupName"], (($rsShipClass[$s]["timesUsed"]/$classCount)*100));
										}
										printf(", %s.  ", (isset($clAry) ? implode(", ",$clAry) : ""));
									} else {
										printf(".  ");	
									}
								}
							}
						}
					} else {
						// No top corp, so not enough data to do anything!
						printf("<p>No kills recorded in this system in the past %d months.  No intel analysis is possible.</p>", EVEKILL_ANALYSIS_MAX_MONTH_HISTORY);	
					}
				}
			} else {
				// Killboard was invalid, probably a problem querying evekill
				printf('<p class="fatalerror">Unable to query Eve-Kill data at this time - please try again later.</p>');
			}
			dprintf("intel_evekill_analysis - Finished.");
			printf("</div>");
		}
	// ========================================================================
	} elseif ($action == "intel_pilotinfo") {
	// ========================================================================
		$n_PilotID 		= trim(mysql_real_escape_string($_REQUEST["id"]));
		$n_PilotName 	= urldecode(trim($_REQUEST["name"]));
		
		// Note: The double-DIV is required because the AJAX re-AJAXer won't get the "rel" otherwise
		printf('<div class="gBox">');
		printf('<div class="gImg" rel="%s"><img src="/images/gimg_placeholder.jpg" width="%d" height="%d" border="0"/></div>', sprintf(EVE_API_PILOT_IMAGE_LOOKUP_URL, $n_PilotID, EVE_API_IMAGE_XY), EVE_API_IMAGE_XY, EVE_API_IMAGE_XY);
		printf('<div class="gData">');
		printf('<div class="gName">%s</div>', $n_PilotName);
		printf('<p class="igLinks">Contact List:<br/><a href="javascript:void(0)" onClick="CCPEVE.showInfo(1377, %d)">Show Info</a> &ndash; <a href="javascript:void(0)" onClick="javascript:CCPEVE.addContact(%d)">Add</a> &ndash; <a href="javascript:void(0)" onClick="javascript:CCPEVE.addCorpContact(%d)">Add Corp</a></p>', $n_PilotID, $n_PilotID, $n_PilotID);
		printf('<p><a href="%s" target="_blank" alt="Eve-Kill details for %s">Eve-Kill</a></p>', sprintf(EVEKILL_PILOT_URL, $n_PilotID), $n_PilotName);
		printf('<p><a href="%s" target="_blank" alt="BattleClinic details for %s">BattleClinic</a></p>', sprintf(BATTLECLINIC_PILOT_URL, str_replace(" ","+",$n_PilotName)), $n_PilotName);
		printf('<p><a href="%s" target="_blank" alt="EveWho details for %s">EveWho</a></p>', sprintf(EVEWHO_PILOT_URL, str_replace(" ","+",$n_PilotName)), $n_PilotName);
		printf('<p><a href="%s" target="_blank" alt="Eve Gate page for %s">Eve-Gate</a></p>', sprintf(EVEGATE_PILOT_URL, $n_PilotName), str_replace(" ","+",$n_PilotName));
		printf('</div>');
		printf('</div>');
	// ========================================================================
	} elseif ($action == "intel_corpinfo") {
	// ========================================================================
		$n_CorpID 		= trim(mysql_real_escape_string($_REQUEST["id"]));
		$n_CorpName		= urldecode(trim($_REQUEST["name"]));
		
		// Note: The double-DIV is required because the AJAX re-AJAXer won't get the "rel" otherwise
		printf('<div class="gBox">');
		printf('<div class="gImg" rel="%s"><img src="/images/gimg_placeholder.jpg" width="%d" height="%d" border="0"/></div>', sprintf(EVE_API_CORP_IMAGE_LOOKUP_URL, $n_CorpID, EVE_API_IMAGE_XY), EVE_API_IMAGE_XY, EVE_API_IMAGE_XY);
		printf('<div class="gData">');
		printf('<div class="gName">%s</div>', $n_CorpName);
		printf('<p class="igLinks">Contact List:<br/><a href="javascript:void(0)" onClick="CCPEVE.showInfo(2, %d)">Show Info</a> &ndash; <a href="javascript:void(0)" onClick="javascript:CCPEVE.addContact(%d)">Add</a> &ndash; <a href="javascript:void(0)" onClick="javascript:CCPEVE.addCorpContact(%d)">Add Corp</a></p>', $n_CorpID, $n_CorpID, $n_CorpID);
		printf('<p><a href="%s" target="_blank" alt="Eve-Kill details for %s">Eve-Kill</a></p>', sprintf(EVEKILL_CORP_URL, $n_CorpID), $n_CorpName);
		printf('<p><a href="%s" target="_blank" alt="BattleClinic details for %s">BattleClinic</a></p>', sprintf(BATTLECLINIC_CORP_URL, str_replace(" ","+",$n_CorpName)), $n_CorpName);
		printf('<p><a href="%s" target="_blank" alt="EveWho details for %s">EveWho</a></p>', sprintf(EVEWHO_CORP_URL, str_replace(" ","+",$n_CorpName)), $n_CorpName);
		printf('<p><a href="%s" target="_blank" alt="Dotlan details for %s">Dotlan</a></p>', sprintf(DOTLAN_CORP_URL, str_replace(" ","_",$n_CorpName)), $n_CorpName);
		printf('</div>');
		printf('</div>');
	// ========================================================================
	} elseif ($action == "intel_allianceinfo") {
	// ========================================================================
		$n_AllianceID 	= trim(mysql_real_escape_string($_REQUEST["id"]));
		$n_AllianceName	= urldecode(trim($_REQUEST["name"]));
		
		// Note: The double-DIV is required because the AJAX re-AJAXer won't get the "rel" otherwise
		printf('<div class="gBox">');
		printf('<div class="gImg" rel="%s"><img src="/images/gimg_placeholder.jpg" width="%d" height="%d" border="0"/></div>', sprintf(EVE_API_ALLIANCE_IMAGE_LOOKUP_URL, $n_AllianceID, EVE_API_IMAGE_XY), EVE_API_IMAGE_XY, EVE_API_IMAGE_XY);
		printf('<div class="gData">');
		printf('<div class="gName">%s</div>', $n_AllianceName);
		printf('<p class="igLinks">Contact List:<br/><a href="javascript:void(0)" onClick="CCPEVE.showInfo(16159, %d)">Show Info</a> &ndash; <a href="javascript:void(0)" onClick="javascript:CCPEVE.addContact(%d)">Add</a> &ndash; <a href="javascript:void(0)" onClick="javascript:CCPEVE.addCorpContact(%d)">Add Corp</a></p>', $n_AllianceID, $n_AllianceID, $n_AllianceID);
		printf('<p><a href="%s" target="_blank" alt="Eve-Kill details for %s">Eve-Kill</a></p>', sprintf(EVEKILL_ALLIANCE_URL, $n_AllianceID), $n_AllianceName);
		printf('<p><a href="%s" target="_blank" alt="BattleClinic details for %s">BattleClinic</a></p>', sprintf(BATTLECLINIC_ALLIANCE_URL, str_replace(" ","+",$n_AllianceName)), $n_AllianceName);
		printf('<p><a href="%s" target="_blank" alt="EveWho details for %s">EveWho</a></p>', sprintf(EVEWHO_ALLIANCE_URL, str_replace(" ","+",$n_AllianceName)), $n_AllianceName);
		printf('<p><a href="%s" target="_blank" alt="Dotlan details for %s">Dotlan</a></p>', sprintf(DOTLAN_ALLIANCE_URL, str_replace(" ","_",$n_AllianceName)), $n_AllianceName);
		printf('</div>');
		printf('</div>');
	// ========================================================================
	} elseif ($action == "intel_iteminfo") {
	// ========================================================================
		$n_ID			= trim(mysql_real_escape_string($_REQUEST["id"]));
		
		printf('<img src="%s" width="%d" height="%d" border="0"/>', sprintf(EVE_API_ITEM_IMAGE_LOOKUP_URL, $n_ID, EVE_API_IMAGE_XY), EVE_API_IMAGE_XY, EVE_API_IMAGE_XY);
	// ========================================================================
	} elseif ($action == "get_image") {
	// ========================================================================
		$n_ImageURL 	= trim(mysql_real_escape_string($_REQUEST["url"]));
		printf('<img src="%s" border="0"/>', $n_ImageURL);
	// ========================================================================
	}
	// ========================================================================
	
	// This is used with the sort function to 
	function kbcmp($a, $b) { return $b['killCount'] - $a['killCount']; }
	function lbcmp($a, $b) { return $b['lossCount'] - $a['lossCount']; }
	
	function getClassCSS($sysClass) {
		switch ($sysClass) {
			case 1:
			case 2:
			case 3:
			case 4:
			case 5:
			case 6:
				return sprintf("wSec_WH%d", $sysClass);
			case 7:
				return sprintf("wSec_H");
			case 8:
				return sprintf("wSec_L");
			case 9:
				return sprintf("wSec_N");
		}
	}
	
	function getClassDesc($sysClass,$shortHand) {
		switch ($sysClass) {
			case 1:
			case 2:
			case 3:
			case 4:
			case 5:
			case 6:
				return sprintf((($shortHand) ? "C%d" : "Wormhole Class %d"), $sysClass);
			case 7:
				return sprintf(($shortHand) ? "Highsec" : "Highsec");
			case 8:
				return sprintf(($shortHand) ? "Lowsec" : "Lowsec");
			case 9:
				return sprintf(($shortHand) ? "Null" : "Nullsec");
		}
	}
	
	// Close database connection(s)
	db_close();
?>
