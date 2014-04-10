<?
	$whConn = null;
	
	// This is the name of the MySQL EVE database dump.  The user MYSQLUSER will need SELECT permissions to view it
	const EVEDB_NAME = 'evedb_inf11';
	const WHDB_NAME = 'whdata';
	
	function db_open() {
		global $whConn;
		// Note: Apache presents $_ENV as $_SERVER instead (annoying)
		$whConn = mysql_connect("localhost", $_SERVER["MYSQLUSER"], $_SERVER["MYSQLPASS"]);
	}
	
	function db_close() {
		global $whConn;
		
		if (!empty($whConn))
			mysql_close($whConn);
	}
?>