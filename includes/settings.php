<?
	define("SHOW_DEBUGGING",						false);
	
	define("DISABLE_EVEKILL_COMPLETELY",			false);		/* This is analogous to CACHE_FORCE_USE_CACHE */
	
	define("CACHE_KILLBOARD_DATA_CACHE",			true);		/* If set, killboard data will be serialized and 
																   saved to disk, and subsequent queries 
																   that use a Killboard object will use this cache.
																   Setting this to false will significantly
																   increase the time it takes to output intel.
																 */
	define("EVEMAPS_GRAPH_WIDTH",					230);
	define("EVEMAPS_GRAPH_HEIGHT",					160);
	
	define("SHIP_KILL", 							1);
	define("SHIP_LOSS",								2);
	
	// Search constants
	define("GET_CHARACTER_ID",						1);
	define("GET_CORPORATION_ID",					2);
	define("GET_ALLIANCE_ID",						4);
	
	// Cache usage
	
	define("CACHE_FORCE_FULL_REFRESH",				0);			/* Forces the system to reload the data even if the cache is fresh (effectively a "no cache" option) */
	define("CACHE_USE_CACHE_REFRESH_IF_EXPIRED",	1);			/* Forces a full refresh if the cache is expired (delete old cache, acquire new data from scratch) */
	define("CACHE_USE_CACHE_UPDATE_NEW_DATA",		2);			/* (default) Update the existing cache with new data if it has expired */
	define("CACHE_FORCE_USE_CACHE",					3);			/* Force using the cache if it exists even if it is expired (e.g. network issues) */
	
	define("NAMING_POLICY_REGEXP",					'/^([\-])?[A-Za-z0-9]+([\'\-\.\ ]+[A-Za-z0-9]+)*([\-])?\.?$/');
	
	define("NPC_CORPORATION_START_ID",				1000002);
	define("NPC_CORPORATION_END_ID",				1000182);
	define("INTERBUS_CORPORATION_ID",				1000148);
	define("SLEEPER_CORPORATION_ID",				146033117);
	define("NO_ALLIANCE_ALLIANCE_ID",				958929439);
	define("INTERBUS_CUSTOMS_OFFICE_TYPEID",		4318);
	
	// The ship typeIDs that can exist in a wormhole
	// Note: Freighters are excluded
	define("CAPITAL_SHIP_TYPEIDS",					serialize(array(19720, 19722, 19724, 19726, 23757, 
																	23911, 23915, 24483, 28352))); 
	// The carrier Fighter typeIDs
	define("FIGHTER_WEAPON_TYPEIDS", 				serialize(array(23055, 23057, 23059, 23061)));
	
	// POS battery groupIDs
	define("POS_GUNS_GROUPIDS",						serialize(array(417, 426, 430, 449)));
	define("POS_MODULES_STRUCTURES_GROUPIDS",		serialize(array(411, 363, 397, 404, 413, 438, 439, 440, 441, 443, 444, 471, 837, 1106)));
	define("POS_TOWER_GROUPID",						serialize(array(365)));
	
	define("SHOW_MAX_CAPITAL_KILLMAILS",			5);
	
	define("CACHE_DIRECTORY",						"../_cache");
	define("EVEKILL_CACHE_LIFETIME_LASTKILL",		1800);		/* Last kill is cached 30 minutes */
	define("EVEKILL_CACHE_LIFETIME_ANALYSIS",		7200);		/* Analysis is cached for 2 hours */
	define("DOTLAN_CACHE_LIFETIME",					900);		/* Dotlan cache */
	
	define("EVEKILL_SOCKET_TIMEOUT_SECONDS",		15);
	define("EVEKILL_KILL_COUNT_FOR_INTEL",			1000);
	define("EVEKILL_ANALYSIS_MAX_MONTH_HISTORY",	12);
	define("EVEKILL_ANALYSIS_MAX_KILLS_PER_MONTH",	100);		/* Default maximum kills recorded per month (for diversity).  This should never be lower than EVEKILL_KILL_COUNT_FOR_INTEL / EVEKILL_ANALYSIS_MAX_KILLS_PER_MONTH */
	define("EVEKILL_EPIC_MASK",						655359);
	
	define("EVEKILL_EVICTION_INACTIVE_MONTHS",		3);
	
	define("EVEKILL_SECS_ELAPSED_ASSUME_NEW_BATTLE",86400);		/* Number of seconds between kills that if exceeded assumes is a seperate & new battle */
	
	define("EVEKILL_MAX_SHIP_CLASSES_TO_SHOW"		,3);		/* Max number of ship classes to show */

	define("EVEKILL_RECENT_EVICTION_IN_DAYS"		,7);		/* An eviction in this many days is notable */
	define("EVEKILL_MAX_EVICTERS_TO_REPORT"			,3);
	define("EVEKILL_MAX_EVICTERS_FOR_MULTIPOS_LOSS"	,6);
	
	define("SCORE_EXPONENTIAL_DECAY_RATE",			0.1);		/* Decay rate for exponential decay formula */
	define("SCORE_MAX_SCORE_FOR_BATTLE",			1000);		/* Maximum score an individual battle can receive in residency computations */
	define("SCORE_MULTIPLE_BATTLE_INCREMENT",		0);			/* Max battle score modifier */
	define("SCORE_SKEW_CAPITAL_USE_IN_LOWCLASS_WH", 4000); 		/* A corp using a capital in a wormhole that can only support them being built there is almost certainly resident */
	define("SCORE_SKEW_POS_COMBAT_ACTIVITY",		5500);		/* A POS owned by corp was involved in a kill/loss */
	define("SCORE_SKEW_FOR_POS_ATTACK",				1500);		/* Credit for a corp being involved in killing a POS */
	define("SCORE_SKEW_INTERBUS_CO_KILL",			2000);		/* Someone killed an InterBus C.O - they probably lived there or wanted to */
	
	define("SCORE_RESIDENCY_THRESHOLD_PERC",		60);		/* Percentage of the highest residency score that a corp can be considered resident too */
	define("SCORE_CERTIFIED_RESIDENCY_PERC",		30);
	
	define("SCORE_RES_ASSUME_ALLIANCE_THRESHOLD_PERC", 30);		/* Percentage threshold of total system residency score that an alliance is assumed resident */
	define("SCORE_RES_ASSUME_ALLIANCE_CORP_DOMINANT_PERC", 25);	/* Percentage at which it is assumed a corporation is dominant in the alliance in the system */	
	define("SCORE_RES_ASSUME_ALLIANCE_OCCUPANCY",	40);		/* Percentage at which an alliance is assumed to occupy */
	define("SCORE_RES_STDDEV_TOO_LOW_THRESHOLD",	505);		/* If standard deviation of values is below this number, assume unoccupied */
	define("SCORE_BATTLE_COUNT_TOO_LOW_THRESHOLD",	5);			/* Minimum number of battles that have to have taken place to consider calculating residency */
	define("SCORE_RES_STILL_RES_BATTLE_SCORE", 		100);		/* The score to apply per kill for a "still resident" check */
	define("SCORE_RES_STILL_RES_THRESHOLD_SCORE",	175);		/* The threshold at which we assume they are probably still resident */
	define("SCORE_RES_IGNORE_RELATED_ACTIVITY_PERIOD_DAYS",	14);	/* The number of days after a POS loss that kill activity (losses and kills) should be ignored for residency calculations */
	define("SCORE_MINIMUM_BATTLE_COUNT_FOR_RES",	1);			/* Corporations will have to have had at least this many battles to qualify for residency */	
	
	define("ICON_RESIDENT_IMAGE",					'<div class="iImg"><img src="/images/icon_resident.png" width="16" height="16" alt="Resident" border="0"/></div>');
	define("ICON_ACTIVITY_IMAGE",					'<div class="iImg"><img src="/images/icon_activity.png" width="16" height="16" alt="Activity" border="0"/></div>');
	define("ICON_TIMEZONE_IMAGE",					'<div class="iImg"><img src="/images/icon_timezone.png" width="16" height="16" alt="Timezone" border="0"/></div>');
	define("ICON_CAPUSE_IMAGE",						'<div class="iImg"><img src="/images/icon_capital.png" width="16" height="16" alt="Capital usage" border="0"/></div>');
	define("ICON_COMBAT_IMAGE",						'<div class="iImg"><img src="/images/icon_combat.png" width="16" height="16" alt="Combat event" border="0"/></div>');
	define("ICON_EVICTED_IMAGE",					'<div class="iImg"><img src="/images/icon_evicted.png" width="16" height="16" alt="Evicted" border="0"/></div>');
	define("ICON_VACATED_IMAGE",					'<div class="iImg"><img src="/images/icon_vacated.png" width="16" height="16" alt="Vacated" border="0"/></div>');
	define("ICON_EVICTING_IMAGE",					'<div class="iImg"><img src="/images/icon_danger.gif" width="16" height="16" alt="Combat event" border="0"/></div>');
	
	define("EVE_API_ID_LOOKUP_URL",					"https://api.eveonline.com/eve/CharacterID.xml.aspx?names=%s");
	define("EVE_API_NAME_LOOKUP_URL",				"https://api.eveonline.com/eve/CharacterName.xml.aspx?ids=%s");
	define("EVE_API_CORP_LOOKUP_URL",				"https://api.eveonline.com/corp/CorporationSheet.xml.aspx?corporationID=%d");
	define("EVE_API_PILOT_IMAGE_LOOKUP_URL",		"http://image.eveonline.com/Character/%d_%d.jpg");
	define("EVE_API_CORP_IMAGE_LOOKUP_URL",			"http://image.eveonline.com/Corporation/%d_%d.png");
	define("EVE_API_ALLIANCE_IMAGE_LOOKUP_URL",		"http://image.eveonline.com/Alliance/%d_%d.png");
	define("EVE_API_ITEM_IMAGE_LOOKUP_URL",			"http://image.eveonline.com/Render/%d_%d.png");
	define("EVE_API_IMAGE_XY",						128);
	define("EVE_API_ID_SEARCH",						1);
	define("EVE_API_NAME_SEARCH",					2);
	
	define("EVEKILL_PILOT_URL",						"http://eve-kill.net/?a=pilot_detail&plt_ext_id=%d");
	define("BATTLECLINIC_PILOT_URL",				"http://eve.battleclinic.com/killboard/combat_record.php?type=player&name=%s");
	define("EVEWHO_PILOT_URL",						"http://evewho.com/pilot/%s");
	define("EVEGATE_PILOT_URL",						"https://gate.eveonline.com/Profile/%s");
	
	define("EVEKILL_CORP_URL",						"http://eve-kill.net/?a=corp_detail&crp_ext_id=%d");
	define("BATTLECLINIC_CORP_URL",					"http://eve.battleclinic.com/killboard/combat_record.php?type=corp&name=%s");
	define("EVEWHO_CORP_URL",						"http://evewho.com/corp/%s");
	define("DOTLAN_CORP_URL",						"http://evemaps.dotlan.net/corp/%s");
	
	define("EVEKILL_ALLIANCE_URL",					"http://eve-kill.net/?a=alliance_detail&all_ext_id=%d");
	define("BATTLECLINIC_ALLIANCE_URL",				"http://eve.battleclinic.com/killboard/combat_record.php?type=alliance&name=%s");
	define("EVEWHO_ALLIANCE_URL",					"http://evewho.com/alli/%s");
	define("DOTLAN_ALLIANCE_URL",					"http://evemaps.dotlan.net/alliance/%s");
	
	define("EVEKILL_LASTKILL_URL",					"http://eve-kill.net/epic/system:%s/mask:%s/mailLimit:10/endDate:%s/startDate:%s/orderBy:desc");
	define("EVEKILL_ANALYSIS_URL",					"http://eve-kill.net/epic/system:%s/mask:%s/mailLimit:%d/endDate:%s/startDate:%s/orderBy:desc");
	define("EVEKILL_INTERBUS_POCO_CHECK_URL",		"http://eve-kill.net/epic/system:%s/mask:%s/endDate:%s/startDate:%s/orderBy:desc/victimCorpExtID:1000148");
	define("EVEKILL_CORP_KILL_HISTORY",				"http://eve-kill.net/epic/mask:%d/mailLimit:%d/combinedCorp:%s");
	
	define("MEMCACHE_STATICDATA_TIMEOUT",			5184000); /* How long data that is assumed to be static will
															   * remain in the memcache before expiry
															   */															
?>
