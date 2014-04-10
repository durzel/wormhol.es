<?
	require_once("class_celestial.php");

	class Wormhole {
		private $iItemID;
		private $iSysID;
		private $sSysName;
		private $iSysClass;
		private $dSysSecurity;
		private $iRegionID;
		private $sRegionName;
		private $iConstellationID;
		private $sConstellationName;
		private $iAnomalyTypeID;
		private $iAnomalyTypeName;
		
		public $cCelestial = array();
		
		private $isWormhole = false;
		private $isValidLocus = false;
		
		public function __construct($locusID) {		
			global $whConn;
			
			// Sanitise input
			$locusID = str_replace("_", " ", $locusID);
		
			$sysInfoSQL = "SELECT mss.solarSystemName, mr.regionName, mc.constellationName, "
				. "mlwc.wormholeClassID, mss.security AS trueSec, it.typeName, it.typeID, mdn.* "
				. "FROM ".EVEDB_NAME.".mapDenormalize mdn "
				. "LEFT JOIN ".EVEDB_NAME.".mapRegions mr USING(regionID) "
				. "LEFT JOIN ".EVEDB_NAME.".mapConstellations mc USING(constellationID) "
				. "LEFT JOIN ".EVEDB_NAME.".mapLocationWormholeClasses mlwc ON mlwc.locationID = mdn.regionID "
				. "LEFT JOIN ".EVEDB_NAME.".mapSolarSystems mss USING(solarSystemID) "
				. "LEFT JOIN ".EVEDB_NAME.".invTypes it USING(typeID) "
				. "WHERE mdn.solarSystemID = (SELECT itemID FROM ".EVEDB_NAME.".mapDenormalize WHERE itemName = '" . $locusID . "') ";
			//printf("<p>%s</p>",$sysInfoSQL);
			$rsSysInfo = mysql_query_cache($sysInfoSQL, $whConn);
			if (is_array($rsSysInfo)) {
				if (!empty($rsSysInfo)) {
					$this->isValidLocus 		= true;
					
					$this->iItemID 				= $rsSysInfo[0]["itemID"];
					$this->iSysID 				= $rsSysInfo[0]["solarSystemID"];
					$this->sSysName 			= $rsSysInfo[0]["solarSystemName"];
					$this->iSysClass 			= $rsSysInfo[0]["wormholeClassID"];
					$this->dSysSecurity 		= $rsSysInfo[0]["trueSec"];
					$this->isWormhole 			= ($this->iSysClass >= 1 && $this->iSysClass <= 6) ? true : false;
					$this->sRegionName 			= $rsSysInfo[0]["regionName"];
					$this->sConstellationName 	= $rsSysInfo[0]["constellationName"];
					$this->iRegionID 			= $rsSysInfo[0]["regionID"];
					$this->iConstellationID 	= $rsSysInfo[0]["constellationID"];
					// Add up stars, planets, moons and anomolie(s)
					for ($i = 1; $i < count($rsSysInfo); $i++) {
						switch ($rsSysInfo[$i]["groupID"]) {
							case 6: 
							case 7: 
							case 8: 
								array_push($this->cCelestial, new Celestial($rsSysInfo[$i]["itemID"]));
								break;
							case 995:
								// Wormhole anomoly
								$this->iAnomalyTypeName = preg_replace("/Wolf-Rayet Star/i","Wolf Rayet", $rsSysInfo[$i]["typeName"]);	
								$this->iAnomalyTypeID 	= self::getRealAnomTypeID($this->iAnomalyTypeName, $this->iSysClass);						
								break;	
						}
					}
				}
			}
		}
		
		private function getRealAnomTypeID($typeName, $whClassID) {
			global $whConn;
			
			$anomSQL = sprintf("SELECT dgmTypeAttributes.typeID FROM ".EVEDB_NAME.".dgmTypeAttributes INNER JOIN ".EVEDB_NAME.".dgmAttributeTypes USING(attributeID) WHERE typeID = (SELECT invTypes.typeID FROM ".EVEDB_NAME.".invTypes WHERE UPPER(typeName) LIKE '%%%s%% CLASS %d%%') LIMIT 1", strtoupper($typeName), $whClassID);
			$rsAnomaly = mysql_query_cache($anomSQL, $whConn);
			if (is_array($rsAnomaly)) { 
				if (!empty($rsAnomaly)) {
					return $rsAnomaly[0]["typeID"];
				}
			}
			return null;
		}
		
		public function getAnomEffect($attributeID,$valueFloat) {
			$valueFloat = floatval($valueFloat);
			//printf("attributeID = '%d', valueFloat = '%f'<br/>", $attributeID, $valueFloat);
			switch (intval($attributeID)) {
				case 146: 	/* Shield HP bonus */
				case 169:	/* Inertia modifier */
				case 237: 	/* Targeting range multiplier */
				case 243: 	/* Max range modifier */
				case 244: 	/* Tracking speed multiplier */
				case 517: 	/* Falloff Modifier */
				case 652: 	/* Signature penalty */
				case 1469: 	/* Missile velocity multiplier */
				case 1470:	/* Maximum velocity multiplier */
				case 1472: 	/* Control range multiplier */	
				case 1482: 	/* Damage multiplier multiplier */
				case 1483: 	/* AOE velocity multiplier */
				case 1484: 	/* Drone velocity multiplier */
				case 1485: 	/* Heat damage multiplier */
				case 1486: 	/* Overload bonus multiplier */
				case 1487: 	/* Smart bomb range multiplier */
				case 1488: 	/* Smart bomb damage multiplier */
				case 1495: 	/* Repair amount multiplier */
				case 1496:	/* Shield transfer amount multiplier */
				case 1497:  /* Shield repair multiplier */
				case 1498:	/* Remote repair amount multiplier */
				case 1499:	/* Capacitor capacity multiplier */
				case 1500:  /* Capacitor recharge multiplier */
					return sprintf("%s%s%%", ($valueFloat < 1) ? (($valueFloat > 0) ? "-" : "") : "+", number_format(($valueFloat < 1) ? (1-$valueFloat)*100 : ($valueFloat-1)*100,0));
					
				case 1465:	/* Armor EM resistance bonus */
				case 1466:	/* Armor kinetic resistance bonus */
				case 1467:	/* Armor thermal resistance bonus */
				case 1468: 	/* Armor explosive resistance bonus */
				case 1489:	/* Shield EM resistance */
				case 1490:	/* Shield kinetic resistance */
				case 1491:	/* Shield thermal resistance */
				case 1492:	/* Shield explosive resistance */
					/* For some reason the database has the inverse of the actual effects for shield & armour 
					 * buffs/debuffs - so we reverse it for presentation 
					 */
					$valueFloat = -($valueFloat);
					return sprintf("%s%s%%", ($valueFloat < 1) ? (($valueFloat > 0) ? "-" : "") : "+", number_format($valueFloat,0));	
					
				case 1493:	/* Small weapon damage multiplier */
					return sprintf("%sx", number_format($valueFloat,2));
					
				default: 
					return sprintf("%s%s%%", ($valueFloat < 1) ? (($valueFloat > 0) ? "-" : "") : "+", number_format($valueFloat,0));
			}
		}
		
		public function isValidLocus() { return $this->isValidLocus; }
		public function hasAnomaly() { return isset($this->iAnomalyTypeID); }
		public function getAnomalyName() { return isset($this->iAnomalyTypeName) ? $this->iAnomalyTypeName : ""; }
		public function getAnomalyTypeID() { return $this->hasAnomaly() ? $this->iAnomalyTypeID : -1; }
		public function isWHLocus() { return $this->isWormhole; }
		public function getSysID() { return $this->iSysID; }
		public function getConstellationID() { return $this->iConstellationID; }
		public function getSysName() { return $this->sSysName; }
		public function getSysClass() { return $this->iSysClass; }
		public function getPlanetCount() { 
			$planetCount = 0;
			foreach ($this->cCelestial as $aCelestial) {
				if ($aCelestial->isPlanet()) $planetCount++;
			}
			return $planetCount;
		}
		public function getMoonCount() { 
			$moonCount = 0;
			foreach ($this->cCelestial as $aCelestial) {
				if ($aCelestial->isMoon()) $moonCount++;
			}
			return $moonCount;
		}
		
		public function getCelestialChildren($itemID) {
			// This function returns an array of Celestials that are the direct descendants of the 
			// specified parent (itemID) or an empty array otherwise on none.
			$aChildren = array();
				
			foreach ($this->cCelestial as $aCelestial) {
				if ($aCelestial->orbitID() == $itemID) {
					array_push($aChildren, $aCelestial);	
				}
			}
			return $aChildren;
		}
		
		public function getSysXYZExtents($svgX,$svgY,$svgZ) {
			// This function calculates the minimum and maximum boundaries of the system, and therefore
			// where each celestial sits within the relative map
			$minX = $minY = $minZ = $maxX = $maxY = $maxZ = 0;
			
			foreach ($this->cCelestial as $aCel) {
				$minX = ($aCel->X() < $minX) ? $aCel->X() : $minX;
				$minY = ($aCel->Y() < $minY) ? $aCel->Y() : $minY;
				$minZ = ($aCel->Z() < $minZ) ? $aCel->Z() : $minZ;
				$maxX = ($aCel->X() > $maxX) ? $aCel->X() : $maxX;
				$maxY = ($aCel->Y() > $maxY) ? $aCel->Y() : $maxY;
				$maxZ = ($aCel->Z() > $maxZ) ? $aCel->Z() : $maxZ;
			}
			printf("<strong>MIN:</strong><li>X: %f</li><li>Y: %f</li><li>Z: %f</li>" 
				. "<strong>MAX:</strong><li>X: %f</li><li>Y: %f</li><li>Z: %f</li>", 
					$minX, $minY, $minZ, $maxX, $maxY, $maxZ);
					
			// Work out the relative boundaries
			$xRange = $maxX - $minX;
			$yRange = $maxY - $minY;
			$zRange = $maxZ - $minZ;
			
			foreach ($this->cCelestial as $aCel) {
				if ($aCel->isPlanet()) {
					$cRadius = sqrt(pow(((($aCel->X() / $xRange) * $svgX)-(0)),2)+pow(((($aCel->Y() / $yRange) * $svgY)-(0)),2));
					printf("<br/><p>'%s'<li><strong>X:</strong> %f</li><li><strong>Y:</strong> %f</li><li><strong>Z:</strong> %f</li>Relative: <li><strong>X:</strong> %f</li><li><strong>Y:</strong> %f</li><li><strong>Z:</strong> %f</li><li><strong>RADIUS:</strong> %f</li><br/></p>",
						$aCel->Name(), $aCel->X(), $aCel->Y(), $aCel->Z(), (($aCel->X() / $xRange) * $svgX), (($aCel->Y() / $yRange) * $svgY), (($aCel->Z() / $zRange) * $svgZ), $cRadius);
				}
			}
		}
		
		public function getRegion() {
			return ($this->isWormhole) ? sprintf("%s (R%03d)", $this->sRegionName, substr($this->iRegionID,strlen($this->iRegionID)-3)) : $this->sRegionName;
		}
		
		public function getConstellation() {
			return ($this->isWormhole) ? sprintf("%s (C%03d)", $this->sConstellationName, substr($this->iConstellationID,strlen($this->iConstellationID)-3)) : $this->sConstellationName;
		}
		
		public function getClassCSS() {
			// Returns a CSS class name to modify the output colour
			return ($this->isWormhole) ? "sSec_WH" . $this->iSysClass : "sSec_" . (($this->dSysSecurity < 0) ? "00" : str_replace(".","",sprintf("%0.1f",$this->dSysSecurity)));	
		}
		public function getClassDesc() {
			switch ($this->iSysClass) {
				case 1:
				case 2:
				case 3:
				case 4:
				case 5:
				case 6:
					return sprintf("Wormhole Class %d", $this->iSysClass);
				case 7:
				case 8:
				case 9:
					// The static data dump can't be totally relied upon for this data
					// (e.g. Rancer has a class of 7, when it should be 8)
					if (round($this->dSysSecurity,1) >= 0.5)
						return sprintf("Highsec (%0.1f)", $this->dSysSecurity);
					elseif (round($this->dSysSecurity,1) >= 0.1)
						return sprintf("Lowsec (%0.1f)", $this->dSysSecurity);
					else 
						return sprintf("Nullsec (%0.2f)", $this->dSysSecurity);
				default:
					return "&lt;unknown&gt;";
			}
		}
	}
?>