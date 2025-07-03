<?php
header("Content-type:application/json");
//header("Content-type:text/plain");

define("SRC_VERSION", "1.0.4");

include_once("lib/db_mysql.php");
include_once("lib/params.php");
include_once("lib/db_params.php");
include_once("land-flags.php");

$p = new parameters();
$result = array();

$result["error"] = "OK";

if( $p->pw != SECRET ){
    $result["error"] = "naughty access";
    echo json_encode( $result, JSON_PRETTY_PRINT );
    exit(0);
}

$db = new DB_Sql();

$db->Host = "localhost";
$db->Database = "opensim";
$db->User = "opensimuser";
$db->Password = "0hmymossgrid";

$db->connect( $Host, $Database, $User, $Pass );

$db->execute("select count(*) as c from regions");
$r = $db->as_obj();
$result["regions"] = (int) $r->c;

$db->execute("select count(*) as c from UserAccounts where active=1 and (Firstname='GRID' or LastName='GOD' or UserLevel>199);");
$r = $db->as_obj();
$result["gods"] = (int) $r->c;

$sql = "select p.UserID, u.PrincipalID, u.UserLevel from Presence as p, UserAccounts as u where u.PrincipalID=p.UserID and u.UserLevel>199";
$db->query( $sql );
$result["godsonline"] = $db->num_rows();

$db->execute("select count(*) as c from UserAccounts where UserLevel>-1 and not (Firstname='GRID' or LastName='GOD' or UserLevel>199);");
$r = $db->as_obj();
$result["users"] = (int) $r->c;

$db->execute("select count(*) as c from Presence where RegionID!='00000000-0000-0000-0000-000000000000'");
$r = $db->as_obj();
$result["usersonline"] = $r->c - $result["godsonline"];

$result["totalonline"] = $result["godsonline"] + $result["usersonline"];

$db->execute("select count(*) as c from GridUser WHERE `Login`>`Logout` and UserID like '%;http%'");
$r = $db->as_obj();
$result["grid"] = (int) $r->c;

$result["gstatus"] = $result["regions"] > 0;

$result["landarea"] = 0;

$result["regionlist"] = array();
$sql = "select r.uuid, r.regionName, r.locX, r.locY, r.sizeX, l.regionUUID, l.musicURL, l.LandFlags as flags from regions as r, land as l where l.regionUUID=r.uuid group by r.uuid order by musicURL desc, regionName;";
//$db->query("select r.regionName, r.locX, r.locY, l.musicURL from regions as r left join land as l on r.uuid=l.regionUUID order by musicURL desc, regionName;");
$publicAccess = UseAccessGroup | UseAccessList | UsePassList;
$db->query($sql);
while( $r = $db->next_rec_as_obj() ){
    $xreg = array();
    $xreg["name"] = $r->regionName;
    $xreg["coordx"] = $r->locX / 256;
    $xreg["coordy"] = $r->locY / 256;
    $xreg["size"]   = $r->sizeX / 256;
    $result["landarea"] += ($r->sizeX * $r->sizeX);
    //$xreg["public"] = $r->musicURL != "";
    $pub = $r->flags & $publicAccess;
    $xreg["public"] =  $pub == 0;
    if( $p->regionlist != "no" ){
        $result["regionlist"][] = $xreg;
    }
}

$jr = json_encode( $result, JSON_PRETTY_PRINT );
echo $jr;
?>
