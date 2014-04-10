<?
	class Celestial {
		const GROUPID_STAR = 6;
		const GROUPID_PLANET = 7;
		const GROUPID_MOON = 8;
		
		private $iItemID;
		private $sItemName;
		private $sTypeName;
		private $iTypeID;
		private $dX;
		private $dY;
		private $dZ;
		private $iOrbitID;
		private $iGroupID;
		
		public function __construct($id) {
			global $whConn;
			
			$celestialSQL = sprintf("SELECT x, y, z, itemID, itemName, orbitID, mdn.typeID, mdn.groupID, typeName FROM ".EVEDB_NAME.".mapDenormalize mdn LEFT JOIN ".EVEDB_NAME.".invTypes it USING(typeID) WHERE mdn.itemID = '%d'", $id);
			//printf("<p>%s</p>",$celestialSQL);
			
			$rsCelestial = mysql_query($celestialSQL,$whConn);
			if ($rsCelestial) {
				if (mysql_num_rows($rsCelestial) == 1) {
					$sRow = mysql_fetch_object($rsCelestial);
					if ($sRow) {
						$this->iItemID 		= $sRow->itemID;
						$this->sItemName	= $sRow->itemName;
						$this->iTypeID		= $sRow->typeID;
						$this->sTypeName	= $sRow->typeName;
						$this->dX 			= $sRow->x;
						$this->dY 			= $sRow->y;
						$this->dZ 			= $sRow->z;
						$this->iOrbitID		= $sRow->orbitID;
						$this->iGroupID		= $sRow->groupID;
					}
				}
				mysql_free_result($rsCelestial);
			}	
		}
	
		public function X() { return $this->dX; }
		public function Y() { return $this->dY; }
		public function Z() { return $this->dZ; }	
		public function itemID() { return $this->iItemID; }
		public function Name() { return $this->sItemName; }
		public function typeID() { return $this->iTypeID; }
		public function TypeOf() { return $this->sTypeName; }
		public function isPlanet() { return $this->iGroupID == self::GROUPID_PLANET; }
		public function isMoon() { return $this->iGroupID == self::GROUPID_MOON; }
		public function orbitID() { return $this->iOrbitID; } 
		
		public function planetType() {
			preg_match_all('/(.*)\((.*)\)/', $this->sTypeName, $matches);
			return ($this->isPlanet() && is_array($matches)) ? $matches[2][0] : false;
		}
	}
?>