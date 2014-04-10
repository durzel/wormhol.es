wormhol.es
==========

Here is the full source code for http://wormhol.es

<strong>Note: This is provided "as is" without any support or warranty of any kind.  I am unfortunately on hiatus from Eve Online, so am unable to maintain this to a standard that I would like.  This, coupled with numerous requests for bugfixes, functionality improvements and API stuff means it is easier for me to just release this for everyone to use in their own projects.</strong>

Some things you'll need to be aware of:

<ol><li>CCP removed wormhole data from their DB dumps a long time ago so unless things have changed you will need to use an old Inferno 1.1 database (with the usual caveats - it won't be useful for much else than wormhole stuff as it's so old).  You will need the following for the site to work properly:
<ul><li>https://www.fuzzwork.co.uk/dump/Inferno/mysql55-inferno11-extended.sql.bz2 (Inferno 1.1 data dump)</li>
<li>http://zofu.no-ip.de/dbz84244/dbz84244-mysql5-v3.sql.bz2 (can't remember what this is for)</li><li>Apply the SQL "patches" in the "sql" directory, in the order listed in IMPORT_ORDER_README</li></ul></li><br/>
<li>The site additionally uses its own internal database - "whdata", the structure and content as of the commit is included in file - whdata.sql.tgz</li><br/>
<li>You'll need to configure Apache to set the MySQL database credentials in the VirtualHost (or .htaccess) - or modify includes/dbconn.php to hardcode it in.  I did this as follows:
<ul><li>SetEnv MYSQLUSER "wormholes"</li>
<li>SetEnv MYSQLPASS "password"</li>
<li>SetEnv MYSQLDB "whdata"</li></ul></li><br/>
<li>The weights of various actions - e.g. ship kills, losses, POS kills, etc is all configured in includes/settings.php.  The settings provided in there are what http://wormhol.es uses currently.  These settings aren't based on any kind of science or extensive analytics - they were "suck it and see" values that seemed to work most of the time.  To see a "league table" of the scores that each entity has, enable debugging in the settings file.</li><br/>
<li>The website relies upon the original (pre-Zkillboard) API that Eve-Kill provides.  If/when this gets depreciated the site will probably stop working completely.  Incidentally the Eve-Kill API in my experience hasn't always produced consistent data, which has caused no end of problems (some mitigated, some not).  The site also scrapes Dotlan for last NPC/ship/pod kills etc - I realise this isn't the right way to do this (and rather rude) but back when the site was originally created and intended as a private resource it was a shorthand.  Both Eve-Kill and Dotlan data is cached (in _cache) to prevent heavy usage.</li><br/>
<li>As said at the top - this is provided "as is", I can't provide coding or installation support.  If I could, I'd be developing it right now instead of (or as well as) releasing it into the public domain, so please don't ask.  Please also be aware that the code is quite messy and I'm sure the performance could be improved in a number of areas.</li></ol>

Feel free to integrate it in any way you see fit without restriction, I hope it gets put to good use.  Shoot me some ISK if you feel like it, but don't feel obliged to.

Durzel


<sub>EVE Online, the EVE logo, EVE and all associated logos and designs are the intellectual property of CCP hf. All artwork, screenshots, characters, vehicles, storylines, world facts or other recognizable features of the intellectual property relating to these trademarks are likewise the intellectual property of CCP hf. EVE Online and the EVE logo are the registered trademarks of CCP hf. All rights are reserved worldwide. All other trademarks are the property of their respective owners. CCP hf. has granted permission to [insert your name or site name] to use EVE Online and all associated logos and designs for promotional and information purposes on its website but does not endorse, and is not in any way affiliated with, [insert name or site name]. CCP is in no way responsible for the content on or functioning of this website, nor can it be liable for any damage arising from the use of this website.</sub>
