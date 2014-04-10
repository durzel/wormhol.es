<?
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	ini_set('display_errors',1);
	ini_set('set_time_limit',60);
	ini_set('max_execution_time',60);
	date_default_timezone_set("Europe/London");
	
	session_start();
	
	require_once("includes/dbconn.php");
	db_open();
	
	/* GET TRUST STATUS
	 * If user has already expressed a preference (cookie stored), then we don't ask them again if they 
	 * want to trust us.  In future, they have to explicitly request to start the trust process (or manually trust)
	 */
	$userNoTrust = false;
	if (isset($_COOKIE["WHSE_NO_TRUST"]))
		$userNoTrust = true;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="keywords" content="wormholes, wspace, eve online, eve, pvp, scanning, intel, anomalies"/>
<meta name="description" content="wormhol.es is designed to provide the capsuleers of Eve Online with a comprehensive resource to quickly identify activity and historical events in wormholes they venture into."/>
<meta name="author" content="Durzel"/>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>wormhol.es</title>
<link rel="stylesheet" type="text/css" media="screen" href="/css/reset.css"/>
<link rel="stylesheet" type="text/css" media="screen" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css"/>
<link rel="stylesheet" type="text/css" media="screen" href="/js/qTip2/dist/jquery.qtip.min.css"/>
<link rel="stylesheet" type="text/css" media="screen" href="/css/base.css?<?=filemtime('css/base.css')?>"/>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.min.js" type="text/javascript"></script>
<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js" type="text/javascript"></script>
<script src="/js/jquery.activity-indicator-1.0.0.min.js?<?=filemtime('js/jquery.activity-indicator-1.0.0.min.js')?>" type="text/javascript"></script>
<script src="/js/main.js?<?=filemtime('js/main.js');?>" type="text/javascript"></script>
<script src="/js/ajax_funcs.js?<?=filemtime('js/ajax_funcs.js');?>" type="text/javascript"></script>
<script language="Javascript">// <![CDATA[									  
	var isInGame = false;
	var isTrusted = false;

	$(document).ready(function(){
		// Check to see whether the CCPEVE object is available
		if (typeof CCPEVE != 'undefined') {
			isInGame = true;	
		}
<?
	$_SESSION["isTrusted"] 	= false;
	$_SESSION["isInGame"] 	= false;

	if (isset($_SERVER["HTTP_EVE_TRUSTED"])) {
		if ($_SERVER["HTTP_EVE_TRUSTED"] == 'No' && !$userNoTrust) {		
			// Website is not currently trusted by the user, so ask..
			$_SESSION["isInGame"] = true;
		} elseif ($_SERVER["HTTP_EVE_TRUSTED"] == 'Yes') {
			$userNoTrust = false;	// Override whether cookie exists or not
			$_SESSION["isInGame"] = true;
			$_SESSION["isTrusted"] = true;
			
			printf("isTrusted = true;");
		}
	}
	printf('$("INPUT#searchFor").focus();');
?>		
	});
// ]]>
</script>
</head>
<?
	$autoSearch = false;
	$n_SearchFor = '';
	if (isset($_REQUEST["s"])) { 
		$n_SearchFor = trim(mysql_real_escape_string($_REQUEST["s"]));
		$autoSearch = true;
	}
	//if (strlen(trim($n_SearchFor)) < 1 && $isTrusted) {
		// If website is trusted, and user hasn't entered a locus to search, populate it
		//$n_SearchFor = trim(mysql_real_escape_string($_SERVER["HTTP_EVE_SOLARSYSTEMNAME"]));	
	//}
?>
<body>
<div id="siteContent">
	<div id="headerBar">
    	<div id="siteLogo"><a href="/"><img src="/images/sitelogo.png?<?=filemtime('images/sitelogo.png')?>" width="140" height="55" alt="wormhol.es" border="0"/></a></div>
        <form id="frmSearch" name="frmSearch" method="post">
        <div id="searchPanel">
        	<div id="refreshLoc"><a id="refreshLoc" href="javascript:void(0)"><img src="/images/reload_icon.png" width="16" height="16" alt="Use your current location" rel="Use your current location" border="0"/></a></div>
        	<div id="searchBox"><input id="searchFor" name="searchFor" value="<?=$n_SearchFor?>" alt="enter search text and hit return" type="text" class="auto-hint" autofocus/><div id="loadAnim"></div></div>
        </div>
        </form>
        <div id="sysDistances">
        	<div id="sysDScan"><img src="/images/dscan.png" width="16" height="16" alt="Dscan" border="0"/></div>
            <div id="sysDists">
                <div id="sysDistAU">0.00 AU</div>
                <div id="sysDistKM">0 KM</div>
            </div>        
            <div id="sys-dist"></div>
        </div>
    </div>
    <div id="newsBox">
<!--
<div class="news"><img src="/images/icon_info.png" width="12" height="12" border="0"/>&nbsp;&nbsp;Fixed lookup bug introduced in latest versions of Chrome.  Thanks for the bug reports.<div class="newsNav"><div class="iClose"><a class="btnClose" href="javascript:void(0)" title="Click to close">X</a></div></div></div>
        <div class="news"><img src="/images/icon_info.png" width="12" height="12" border="0"/>&nbsp;&nbsp;A bug with new kills not being factored into intel has been fixed, cache files have been reset so site will be slower for a bit.<div class="newsNav"><div class="iClose"><a class="btnClose" href="javascript:void(0)" title="Click to close">X</a></div></div></div>
//-->
    </div>
    <div id="intelBox"></div>
<? require_once("includes/footer.php"); ?>
</div>
<script src="/js/qTip2/dist/jquery.qtip.min.js?<?=filemtime('js/qTip2/dist/jquery.qtip.min.js');?>" type="text/javascript"></script>
</body>
</html>
<?
	// Close database connection(s)
	db_close();
?>
