<?
	// Timing
	$mtime = microtime();
   	$mtime = explode(" ",$mtime);
   	$mtime = $mtime[1] + $mtime[0];
   	$starttime = $mtime;
	
	// Memcache
	$memCache = null;

	// Miscellaneous site-wide functions
	function fimplode($delim, $ary) {
		if (sizeof($ary) == 0) return "";
		elseif (sizeof($ary) == 1) return $ary[0];
		$endStr = array_pop($ary);
		return sprintf("%s and %s", implode($delim, $ary), $endStr);	
	}
	
	function dprintf() {
		if (($_SERVER["REMOTE_ADDR"] == "94.169.97.53" || $_SERVER["REMOTE_ADDR"] == "80.45.72.211") && SHOW_DEBUGGING) {
			$argv = func_get_args();
    		$format = array_shift($argv);
			printf('<p class="debug">[%4.3f] ', curScriptTime());
    		vprintf($format, $argv );
			printf('</p>');
		}
	}
	
	function curScriptTime() {
		global $starttime;
		
		$mtime = microtime();
		$mtime = explode(" ",$mtime);
		$mtime = $mtime[1] + $mtime[0];
		$endtime = $mtime;
		$totaltime = ($endtime - $starttime);
		return $totaltime;
	}
	
	function mc_open() {
		global $memCache;
		$memCache = new Memcache;
		$memCache->connect('localhost', 11211);
	}
	
	function mc_close() {
		global $memCache;
		if (!is_null($memCache)) {
			$memCache->close();	
		}
	}
	
	// -------------------------------------------------------------------------------------------------
	// Code copied from http://pureform.wordpress.com/2008/05/21/using-memcache-with-mysql-and-php/
	
	// Gets key / value pair into memcache ... called by mysql_query_cache()
    function getCache($key) {
        global $memCache;
		//dprintf("Looking for key %s, memCache available? %d, result: %s", $key, is_null($memCache), $memCache->get($key)); 
        return ($memCache) ? $memCache->get($key) : false;
    }

    // Puts key / value pair into memcache ... called by mysql_query_cache()
    function setCache($key, $object, $timeout = MEMCACHE_STATICDATA_TIMEOUT) {
        global $memCache;
		//dprintf("Setting key %s to cache", $key); 
        return ($memCache) ? $memCache->set($key, $object, MEMCACHE_COMPRESSED, ($timeout > 0) ? time()+$timeout : 0) : false;
    }

    // Caching version of mysql_query()
    function mysql_query_cache($sql, $linkIdentifier = false, $timeout = MEMCACHE_STATICDATA_TIMEOUT, $goSilent = true) {
		global $memCache;
		if (is_null($memCache)) mc_open();
		
        if (($cache = getCache(md5("mysql_query" . $sql))) === false) {
			if (!$goSilent) dprintf("mysql_query_cache(): using MySQL data.", $sql);
            $cache = false;
            $r = ($linkIdentifier !== false) ? mysql_query($sql, $linkIdentifier) : mysql_query($sql);
            if (is_resource($r) && (($rows = mysql_num_rows($r)) !== 0)) {
				while ($row = mysql_fetch_assoc($r)) {
					$results[] = $row;	
				}
				$cache = serialize($results);
                if (!setCache(md5("mysql_query" . $sql), $cache, $timeout)) {
                    # If we get here, there isn't a memcache daemon running or responding
					if (!$goSilent) dprintf("mysql_query_cache(): failed to update memcached");
                }
				mysql_free_result($r);
            }
        } else { if (!$goSilent) dprintf("mysql_query_cache(): using memcached data."); }
        return unserialize($cache);
    }
	// -------------------------------------------------------------------------------------------------
?>
