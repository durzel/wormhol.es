<div id="footerBar">wormhol.es is &copy; <a href="javascript:authorInfo()">Durzel</a> <?=date("Y");?>&nbsp;&ndash;&nbsp;All Eve related materials are the property of <a href="http://www.ccpgames.com/">CCP hf</a>. 1997-<?=date("Y");?>&nbsp;&nbsp;&nbsp;&mdash;&nbsp;&nbsp;&nbsp;<a href="/more_info.php">more info</a></div>
<!-- Google Analytics -->
<script>
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
	
	ga('create', 'UA-40883469-1', 'wormhol.es');
	ga('send', 'pageview');
	
	$(document).on('ajaxComplete', function (event, request, settings) { 
		ga('send', 'pageview', settings.url);
	});
</script>