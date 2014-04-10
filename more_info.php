<?
	error_reporting(E_ALL ^ E_NOTICE);
	ini_set('display_errors',1);
	ini_set('set_time_limit',60);
	date_default_timezone_set("Europe/London");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="keywords" content="wormholes, wspace, eve online, eve, pvp, scanning, intel, anomalies" />
<meta name="description" content="wormhol.es is designed to provide the capsuleers of Eve Online with a comprehensive resource to quickly identify activity and historical events in wormholes they venture into. " />
<meta name="author" content="Darren Coleman" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>wormhol.es</title>
<link rel="stylesheet" type="text/css" media="screen" href="/css/reset.css"/>
<link rel="stylesheet" type="text/css" media="screen" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1/themes/base/jquery-ui.css"/>
<link rel="stylesheet" type="text/css" media="screen" href="/js/qTip2/dist/jquery.qtip.min.css"/>
<link rel="stylesheet" type="text/css" media="screen" href="/css/base.css?<?=filemtime('css/base.css')?>"/>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js" type="text/javascript"></script>
<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js" type="text/javascript"></script>
<script language="Javascript">// <![CDATA[									  
	var isInGame = false;
	var isTrusted = false;

	$(document).ready(function(){
		// Check to see whether the CCPEVE object is available
		if (typeof CCPEVE != 'undefined') {
			isInGame = true;	
		}
	});
	
	function authorInfo() { (isInGame) ? CCPEVE.showInfo(1377, 1821996928) : window.location.href = ["\x6D\x61\x69\x6C\x74\x6F\x3A\x64\x61\x7A\x40\x73\x75\x70\x65\x72\x66\x69\x63\x69\x61\x6C\x2E\x6E\x65\x74"]; }
// ]]>
</script>
</head>

<body>
<div id="siteContent">
	<div id="headerBar">
    	<div id="siteLogo"><a href="/"><img src="/images/whlogo2.png" width="162" height="42" alt="wormhol.es" border="0"/></a></div>
    </div>
    <div id="bodyBox">
    	<p class="hdr">What is wormhol.es?</p>
        <p><strong>wormhol.es</strong> has been designed to provide wormhole adventurers with a quick and easy way of finding out more information about what has gone on in whichever wormhole they have ventured into.  It analyses information from a variety of sources and attempts to identify significant activity that has occured, and using algorithms attempts to identify who is and has been resident.</p>
        <p>These algorithms are completely organic - the analysis and conclusions that are drawn are based entirely on the data available and are not skewed, gamed or manipulated in any way.  If you know where my corp lives, you will be able to confirm it :)</p>
        <p class="hdr">Why does it need trust?  I don't trust you!</p>
        <p><strong>The site functions the same whether you trust it or not.</strong>  The reason trust is required for the "refresh location" button is because it needs it in order to be able to query your current location.  If you choose not to trust the site it will still produce the same results, it'll just mean you will have to type in your location every time you jump into a new system.</p>
        <p class="hdr">If I trust the site, can't you record where I've been?</p>
        <p>Technically yes - this is effectively how sites like <strong>wormnav</strong>, <strong>eveeye</strong> etc function.  This website does not do any tracking of any kind - it simply uses the <strong>HTTP_EVE_SOLARSYSTEMNAME</strong> (your current location) variable provided by trust to auto-populate the search box and automatically submit it.  <i>That's it.</i>  No data about any individual (including what they search for) is ever stored anywhere.</p>
        <p>For more information about what trusting a website means for you, <a href="http://eve.grismar.net/wikka.php?wakka=TrustPage" target="_blank">click here</a>.</p>
        <p class="hdr">I saw an error, what should I do?</p>
        <p>This website is currently in a beta stage of development.  A lot of errors I've seen occur in the logs relate to timeouts in gathering data, so if this happens to you try searching again (you might have to refresh the page for it to let you do this), and if the error still happens I would be very grateful if you could report it to me (including the system you searched for) on <a href="javascript:authorInfo()">this character</a>.</p>
        <p class="hdr">Ha! I found a system which gives the wrong residency info!</p>
        <p>The algorithms use a number of variables that have mostly been "guesstimated" during development.  The system <i>is</i> fallible, and in certain cases - particularly where a resident corporation has avoided PvP in their own wormhole, or have only recently moved in - there is very little data available at all to determine that they live there.  Likewise a corp that just abandons their wormhole without a fight, or packs up and leaves without incident, may still be classed as a resident even if their tower(s) are offline or no longer there.</p>
        <p>I welcome all feedback about incorrect analysis as it enables me to fine-tune the algorithm, please <a href="javascript:authorInfo()">contact me</a> with the details.</p>
        <p class="hdr">Why not simply allow us to correct wrong residency info?</p>
        <p>Simply put because there would be no way short of visiting a wormhole myself to confirm whether or not a correction is genuine or someone just trying to remove themselves from the output.  Added to which that if the residency info is wrong the algorithm is what would need adjusting, not individual systems.  The next version of wormhol.es MAY allow contributions in some as-yet-undecided form to allow multiple sources providing the same adjustments to be reflected in the output.</p>
        <p class="hdr">How can I get my corporation/alliance removed?</p>
        <p><strong>You can't.</strong>  Because of how the algorithm works any manual interference would skew results across the whole system.  In any event it's not something I would ever consider doing under any circumstances, so don't ask.</p>
        <p class="hdr">Why doesn't it provide residency info for Empire (k-space) systems?</p>
        <p>The algorithms have been designed around the principals of w-space, which don't directly translate to occupancy in k-space.  In addition the algorithms have been designed and fine-tuned around detailed analysis of individual kills and corporation activity, and where there has been considerable activity across a significant number of corporations (as is often the case in k-space systems with known entrance & exits) it tends to take too long to finish its analysis.</p>
        <p>Residency of lowsec systems is something I am looking into, but it is not high priority.</p>
        <p class="hdr">The number of kills/losses for corporation X is wrong.</p>
        <p>The information about kills and losses is intended to give a flavour of a corp's competency.  The site gathers enough data to enable accurate residency analysis, not to give definitive statistics on corporation efficiency.</p>
        <p class="hdr">This site is amazing/crap - how can I give you all of my ISK?</p>
        <p>All donations are gratefully received on <a href="javascript:authorInfo()">this character</a>.</p>
        <p class="hdr">Obligatory copyright information</p>
        <p>EVE Online, the EVE logo, EVE and all associated logos and designs are the intellectual property of CCP hf. All artwork, screenshots, characters, vehicles, storylines, world facts or other recognizable features of the intellectual property relating to these trademarks are likewise the intellectual property of CCP hf. EVE Online and the EVE logo are the registered trademarks of CCP hf. All rights are reserved worldwide. All other trademarks are the property of their respective owners. CCP hf. has granted permission to wormhol.es to use EVE Online and all associated logos and designs for promotional and information purposes on its website but does not endorse, and is not in any way affiliated with, wormhol.es. CCP is in no way responsible for the content on or functioning of this website, nor can it be liable for any damage arising from the use of this website.</p>
    </div>
<? require_once("includes/footer.php"); ?>
</body>
</html>