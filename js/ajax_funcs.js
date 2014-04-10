var aryLocus = new Array();
var blinkTimer = null;

// Array search prototype (doesn't exist in IE)
if(!Array.prototype.indexOf) {
    Array.prototype.indexOf = function(needle) {
        for(var i = 0; i < this.length; i++) {
            if(this[i] === needle) {
                return i;
            }
        }
        return -1;
    };
}

function ajaxError(errorType, errorThrown) {
	alert("A "+errorType+" error occured when attempting to call an AJAX routine.\n"+
			"The error reported was: "+errorThrown+"\n"+
			"Please report this to daz@superficial.net");	
}

function setTrust(intTrust) {
	if (isNaN(intTrust)) return false;
	
	if (parseInt(intTrust) == 0) {
		// Set "I don't trust you" cookie
		$.ajax({
			url: "/includes/ajax_funcs.php",
			type: 'post',
			data: 'func=no-trust',
			async: true,
			beforeSend: function(jqXHR, settings) { $('#t_trustOverview').fadeOut(100); },
			success: function(data, textStatus, jqXHR) { $("#t_trustOverview").html(data); },
			complete: function(jqXHR, textStatus) { $('#t_trustOverview').fadeIn(150).delay(5000).fadeIn(0, function() { showTrust(0) }); },
			error: function(jqXHR, textStatus, errorThrown) { ajaxError(textStatus, errorThrown); }
		});
	} else if (parseInt(intTrust) == 1) {
		var tWndShown = ccpShowTrustWindow();

		$.ajax({
			url: "/includes/ajax_funcs.php",
			type: 'post',
			data: 'func=chk-trust&twnd='+tWndShown,
			async: true,
			beforeSend: function(jqXHR, settings) { $('#t_trustOverview').fadeOut(100); },
			success: function(data, textStatus, jqXHR) { $("#t_trustOverview").html(data); },
			complete: function(jqXHR, textStatus) { $('#t_trustOverview').fadeIn(150, function () { if (!"+tWndShown+") { $('#t_trustOverview').delay(5000).fadeIn(0, function() { showTrust(0) }); }; }); },
			error: function(jqXHR, textStatus, errorThrown) { ajaxError(textStatus, errorThrown); }
		});
	}
}

function ccpShowTrustWindow() {
	// Technically this is probably unnecessary since this function should never be 
	// called outside of the IGB, but for the sake of completeness we do a sanity
	// check on the call.
	try {
		CCPEVE.requestTrust('http://wormhol.es/');
		return true;
	} catch (err) {
		return false;
	}
}

function getUpdatedLocation() {
	// Performs an AJAX call to get the updated location variable
	$.ajax({
		url: "/includes/ajax_funcs.php",
		type: 'post',
		data: 'func=getloc',
		async: true,
		beforeSend: function(jqXHR, settings) {  },
		success: function(data, textStatus, jqXHR) { $('INPUT.auto-hint, TEXTAREA.auto-hint').removeClass('auto-hint'); $("INPUT#searchFor").val(data); },
		complete: function(jqXHR, textStatus) { if ($.trim($("INPUT#searchFor").val()).length > 0) { getLocusOverview($("INPUT#searchFor").val()); }  },
		error: function(jqXHR, textStatus, errorThrown) { ajaxError(textStatus, errorThrown); }
	});
}


function getLocusOverview(locusID) {
	// Gets basic details about the locus ID from the database
	if (!locusID || $.trim(locusID).length < 1) return false;
	locusID = $.trim(locusID).replace(/ /g,"_").toUpperCase().toString();
	
	if (aryLocus[locusID] == undefined) {
		aryLocus[locusID] = 0;
		addIntel(locusID, "func=locusov&lid="+locusID);
		
		// Minimise other systems
		$("#intelBox DIV.intelItem").each(function() {
			if ($("DIV.intelNav",this).length != 0) {
				// If intel item is open, collapse it
				if ($("DIV.intelNav DIV.iClose A:eq(0)",this).attr('title') == 'Click to minimise') {
					$("DIV.intelNav DIV.iClose A:eq(0)",this).trigger('click');
				} else { fadeOutIntel(this); }
			};
		});
	}
}

function gatherIntel(locusID) {
	// This function gathers all the intel for the specified locus, adding it as it goes
	locusID = $.trim(locusID).replace(/ /g,"_").toUpperCase().toString();
	addIntel(locusID, "func=dotlandata&lid="+locusID);
	addIntel(locusID, "func=intel_evekill_analysis&lid="+locusID);
	addIntel(locusID, "func=intel_evekill_last&lid="+locusID);
}

function buildIntelAjaxURL(iObj) {
	// This function returns a URL that is specific to the anchor tag that has already been created with 
	// a previous intel build.  We abuse the class property of an anchor tag to determine what data we need
	// to return if the user clicks on the button.
	var baseURL = '/includes/ajax_funcs.php';
	
	switch ($(iObj).attr('rel').split(';')[0]) {
		case "whStatic" : return baseURL + "?func=intel_staticinfo&s="+encodeURI($.trim($(iObj).html()).toUpperCase());
		case "whInfo" : return baseURL + "?func=intel_whinfo&lid="+encodeURI($.trim($(iObj).html()).toUpperCase());
		case "whAnom" : return baseURL + "?func=intel_whanom&lid="+encodeURI($.trim($(iObj).parent().parent().find("A[rel=whInfo]").html()).toUpperCase());
		case "dlGraph" : return baseURL + "?func=intel_dlgraph&lid="+encodeURI($.trim($(iObj).parent().parent().find("DIV.dlData").attr('rel')).toUpperCase()) + "&gsrc=" + encodeURI($(iObj).attr('rel').split(';')[1]);
		case "gPilot" : return baseURL + "?func=intel_pilotinfo&id=" + encodeURI($(iObj).attr('rel').split(';')[1]+"&name="+encodeURI($(iObj).text()));
		case "gCorp" : return baseURL + "?func=intel_corpinfo&id=" + encodeURI($(iObj).attr('rel').split(';')[1]+"&name="+encodeURI($(iObj).text()));
		case "gAlliance" : return baseURL + "?func=intel_allianceinfo&id=" + encodeURI($(iObj).attr('rel').split(';')[1]+"&name="+encodeURI($(iObj).text()));
		case "gItem" : return baseURL + "?func=intel_iteminfo&id=" + encodeURI($(iObj).attr('rel').split(';')[1]);
		default : return baseURL + "?func=nodata";
	}
}


function addIntel(intelID,ajaxParams) {
	var isNewIntel = false;
	var newIntel, intelCSS;
	var cIntelID = "ii_"+intelID;
	
	if ($("DIV#"+cIntelID).length == 0) {
		 //$("DIV.intelItem DIV.iED").each(function() { $(this).slideUp(500, 'swing'); });
		 newIntel = $("#intelBox").prepend('<div id="'+cIntelID+'" class="intelItem"></div>').find("DIV.intelItem:first");
		 isNewIntel = true;
		 intelCSS = "iD";
	} else { 
		newIntel = $("DIV#"+cIntelID);
		intelCSS = "iED";
	}
	
	
	$.ajax({
		url: "/includes/ajax_funcs.php",
		type: 'POST',
		data: ajaxParams,
		async: true,
		timeout: 90000,
		beforeSend: function(jqXHR, settings) { aryLocus[intelID]++; $(newIntel).fadeIn(300).activity({align: 'right', segments: 12, steps: 3, width: 2, space: 1, length: 4, color: '#909090', speed: 2.2}); },
		success: function(data, textStatus, jqXHR) { 
			$(newIntel).append('<div class="'+intelCSS+'">'+data+'</div>').find("DIV."+intelCSS+":last").fadeIn(300); 
			$(newIntel).find("A[rel]").each(function() {
				$(this).qtip({
					content: {
						text: '<div align="center"><img src="/images/ajax-loader.gif" width="16" height="11" alt="Loading, please wait.."/></div>',
						ajax: {
							url: buildIntelAjaxURL($(this)),
							type: 'GET',
							async: true,
							timeout: 10000,
							dataType: 'html',
							success: function(data, status) {
								// Set the content manually (required!)
								this.set('content.text', data);
								
								var jqObj = jQuery(data);
								var aQTip = this;
    							jqObj.find("DIV.gImg").each(function() {
									$.ajax({
										url: "/includes/ajax_funcs.php",
										type: 'POST',
										data: 'func=get_image&url='+encodeURI($(this).attr('rel')),
										async: true,
										timeout: 10000,
										dataType: 'html',
										beforeSend: $.proxy(function(jqXHR, settings) { $("DIV.gImg IMG").each(function() { $(this).show(); }); }, this), 
										success: $.proxy(function(data, textStatus, jqXHR) { 
											aQTip.set('content.text', 
												'<div class="gBox">' +
												'<div class="gImg">' + data + '</div>' +
												'<div class="gData">'+$(this).parent().find("DIV.gData").html()+'</div>' +
												'</div>');
											$("DIV.gImg IMG").each(function() { $(this).fadeIn(400); });
										}, this),
										complete: $.proxy(function(jqXHR, textStatus) { }, this),
									});
								});
							}
						},
						title: {
							text: $(this).attr('title'),
							button: true	
						}
					},
					position: {
						at: 'bottom right', // Position the tooltip above the link
						my: 'top left',
						viewport: $(window), // Keep the tooltip on-screen at all times
						effect: false // Disable positioning animation
					},
					show: {
						event: 'click',
						solo: true // Only show one tooltip at a time
					},
					hide: 'unfocus',
					style: {
						classes: 'ui-tooltip-dark ui-tooltip-shadow ui-tooltip-wh'
					}
				});
			})
			// Make sure it doesn't follow the link when we click it
			.click(function(event) { event.preventDefault(); });
		},
		complete: function(jqXHR, textStatus) { 
			if (--aryLocus[intelID] == 0) { $(newIntel).activity(false); }
			if (isNewIntel) gatherIntel(intelID);	
			//alert($(newIntel).html());	
		},
		error: function(jqXHR, textStatus, errorThrown) { 
			$(newIntel).append('<div class="'+intelCSS+'"><span style="color: #FF0000; font-size: 12px;">ERROR: AJAX call failed, error reported: '+errorThrown+' - sorry about that.</span></div>').find("DIV."+intelCSS+":last").fadeIn(300);
		}
	})
}