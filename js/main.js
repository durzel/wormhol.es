$(document).ready(function(){
	// Auto-hint control
	$('INPUT.auto-hint, TEXTAREA.auto-hint').focus(function(){
		if($(this).val() == $(this).attr('alt')){
			$(this).val('');
			$(this).removeClass('auto-hint');
		}
	});
	$('INPUT.auto-hint, TEXTAREA.auto-hint').blur(function(){
		if($(this).val() == '' && $(this).attr('alt') != ''){
		   $(this).val($(this).attr('alt'));
		   $(this).addClass('auto-hint');
		   $("#searchBox #loadAnim").activity(false);
		}
	});
	$('INPUT.auto-hint, TEXTAREA.auto-hint').each(function(){
		if($(this).attr('alt') == ''){ return; }
		if($(this).val() == ''){ $(this).val($(this).attr('alt')); }
		else { $(this).removeClass('auto-hint'); }
	});
	
	var scrollPane = $(".scroll-pane").css('overflow','auto'), scrollContent = $(".scroll-content");
	$("DIV#sys-dist").slider({
		value: 0,
		min: 0,
		max: 14.3550291,
		step: 1,
		slide: function(event, ui) {
			$("#sysDistAU").html(ui.value.toFixed(2)+" AU");
			$("#sysDistKM").html(parseFloat(ui.value * 149597870.700).toFixed(0)+" KM");	
		}
	});
	
	// Fix up locusID if it's a wormhole and they missed off the J-tag
	$("INPUT#searchFor").keyup(function() {
		if ($(this).val().match(/^((1|2)[0-9]{5})$/)) {
			$(this).val("J"+$(this).val());	
		}
	});
	
	// Handles submission of Locus ID search form
	$("#frmSearch").submit(function() { 
		//$("INPUT#searchFor").autocomplete('disable');
		//$("INPUT#searchFor").autocomplete('close');
		
		getLocusInfo($("FORM#frmSearch INPUT#searchFor").val().match('^[\w ]+$'));
		return false;
	});
	
	// Restrict locus search box to allowed characters
	$("INPUT#searchFor").bind("keypress", function(event) {
		if (event.charCode!=0) {
			var regex = new RegExp("^[a-zA-Z0-9 \-]+$");
			var key = String.fromCharCode(!event.charCode ? event.which : event.charCode);
			if (!regex.test(key)) {
				event.preventDefault();
				return false;
			}
		}
	});

	$.getJSON("/js/eve_solar_systems.json", function(data) {			   
		$("INPUT#searchFor").autocomplete({ 
			delay: 150,
			minLength: 2,
			autoFocus: true,
			selectFirst: false,
			source: data,
			max: 50,
			
			search: function() { $("#searchBox #loadAnim").activity({align: 'right', segments: 12, steps: 3, width:2, space: 1, length: 3, color: '#909090', speed: 2}); },
			open: function(e,ui) {	$("#searchBox #loadAnim").activity(false); },
			select: function(event, ui) { getLocusInfo(ui.item.value); return; }
		});
	});
	// Addresses jQuery 1.8.16 bug 7555: http://bugs.jqueryui.com/ticket/7555
	$('.ui-autocomplete-input').each(function (idx, elem) {
	  var autocomplete = $(elem).data('autocomplete');
	  if ('undefined' !== typeof autocomplete) {
		  var blur = autocomplete.menu.options.blur;
		  autocomplete.menu.options.blur = function (evt, ui) {
			  if (autocomplete.pending === 0) {
				  blur.apply(this,  arguments);
			  }
		  };
	  }
	});
	
	// Auto-query the specific system if we have one
	if ($.trim($("FORM#frmSearch INPUT#searchFor").val()).length > 0 && $("FORM#frmSearch INPUT#searchFor").val() != $("FORM#frmSearch INPUT#searchFor").attr('alt')) { getLocusInfo($("FORM#frmSearch INPUT#searchFor").val()); };
	
	$(".pupClose").click(function(){
		showTrust(0);
		return false;
	});
	
	$("A#refreshLoc").click(function() { refreshLocation(); });
	
	/* News items */
	$("#siteContent #newsBox DIV.news A.btnClose").click(function() { $(this).closest("DIV.news").fadeOut(300); });
	$("#siteContent #newsBox DIV > A.btnClose[title]").qtip({
		position: {
			at: 'top left', 		// Position the tooltip above the link
			my: 'bottom right',
			adjust: {
				x: 8,
				y: 2
			}
		},
		show: { solo: true },
		hide: { fixed: true }, 
		style: {
			classes: 'ui-tooltip-dark ui-tooltip-shadow ui-tooltip-wh'
		}
	});
	
	// Handler for AJAX requests, it adds any missing minimise DIVs to the intel	
	$(document).ajaxStop(function() {
		$("#intelBox DIV.intelItem").each(function() {
			if ($("DIV.intelNav",this).length == 0) {
				var locusID = $(this).attr('id').substr(3);
				$(this).prepend('<div id="in_'+locusID+'" class="intelNav"><div class="iClose"><a href="#" title="Click to minimise">&ndash;</a></div><div class="iCopy"><a href="javascript:void(0)" rel="syslink"><img src="/images/clipboard.png" width="25" height="25" border="0" alt="Copy link"/></a></div></div>');
				$("DIV#in_"+locusID+" DIV > A[title]").qtip({
					position: {
						at: 'top left', 		// Position the tooltip above the link
						my: 'bottom right',
						adjust: {
							x: 8,
							y: 2
						}
					},
					show: { solo: true },
					hide: { fixed: true }, 
					style: {
						classes: 'ui-tooltip-dark ui-tooltip-shadow ui-tooltip-wh'
					}
				});
				$("DIV#in_"+locusID+" DIV > A[rel=syslink]").qtip({
					content: { text: '<p style="margin-bottom: 3px">Copy link below to share:</p><p><a href="http://wormhol.es/'+locusID+'">http://wormhol.es/'+locusID+'</a></p>' },
					position: {
						at: 'top left', 		// Position the tooltip above the link
						my: 'bottom right',
						adjust: {
							x: 4,
							y: 2
						}
					},
					show: { solo: true },
					hide: { fixed: true, delay: 200 }, 
					style: {
						classes: 'ui-tooltip-dark ui-tooltip-shadow ui-tooltip-wh ui-tooltip-copylink'
					}
				});
				$("DIV#in_"+locusID+" DIV.iClose A").click(function() {
					$("DIV.iED",$(this).closest("DIV.intelItem")).each(function(index){
						// Dim the intel if it's not the only one available
						if (!($("DIV#ii_"+locusID).attr('id') == $("DIV.intelItem:first").attr('id')) && index == $("DIV.iED",$(this).closest("DIV.intelItem")).length -1) {
							$("DIV#ii_"+locusID).delay(($(this).is(":visible")) ? 500 : 0).fadeTo(300, ($(this).is(":visible")) ? 0.6 : 1);	
						}
						if ($(this).is(":visible")) {
							$(this).animate({ opacity: 0 }, 200).slideToggle(200, 'easeInCirc');	
						} else {
							$(this).slideToggle(200, 'easeOutCirc', function() { $(this).animate({ opacity: 1 }, 200); });	
						}
					});
					$("DIV#in_"+locusID+" DIV.iClose A:eq(0)").attr('title', ($(this).text() == "+") ? "Click to minimise" : "Click to maximise");
					$(this).text(($(this).text() == "+" ? "–" : "+"));
					return false;
				});
			}
		});
	});
});

$(window).bind("resize", function(){
	$("#fadedBG").css("height", $(document).height());
});

function fadeOutIntel(iObj) { $(iObj).fadeTo(300, 0.60); }

function getLocusInfo(locusID) {
	// Close autocomplete dropdown
			
	if ($.trim(locusID).length > 0) {		
		getLocusOverview($.trim(locusID));
	}	
	return false;
}

function showTrust(intTrust) {
	if (isNaN(intTrust)) return false;
	if (parseInt(intTrust) == 1) {
		// Show trust box
		$("#fadedBG").css('filter','alpha(opacity=65)');
		$("#fadedBG").delay(500).fadeIn(100, function() { $("#trustBox").fadeIn(100); });	
	} else {
		// Hide trust box
		$("#trustBox").fadeOut(200, function () { $("#fadedBG").delay(100).fadeOut(0); });
		$("INPUT#searchFor").focus();
	}
}

function refreshLocation() {
	if (!isInGame || !isTrusted) {
		alert("You must be in game and have trusted\nthis website to use this function.");
	} else {
		getUpdatedLocation();
	}
}

function authorInfo() { (isInGame) ? CCPEVE.showInfo(1377, 1821996928) : window.location.href = ["\x6D\x61\x69\x6C\x74\x6F\x3A\x64\x61\x7A\x40\x73\x75\x70\x65\x72\x66\x69\x63\x69\x61\x6C\x2E\x6E\x65\x74"]; }