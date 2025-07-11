<?php
header("Content-type:application/json");
//header("Content-type:text/plain");

define("SRC_VERSION", "1.0.7");

include_once("lib/db_mysql.php");
include_once("lib/params.php");
include_once("lib/db_params.php");
include_once("land-flags.php");

function cmp( $a, $b ){
    if( $a["public"] == $b["public"] ) return 0;
    return $a["public"] > $b["public"] ? -1 : 1;
}

$p = new parameters();
$result = array( "error" => "OK",
                 "version" => SRC_VERSION,
                 "date-time" => date("Y-m-d H:i:s") ); // set defaults

if( $p->pw != SECRET ){
    $result["error"] = "naughty access";
    echo json_encode( $result, JSON_PRETTY_PRINT );
    exit(0);
}


$db = new DB_Sql();
$db = setDBParameters( $db );

$db2 = new DB_Sql();
$db2 = setDBParameters( $db2 );

$db->connect();
$db2->connect();

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
$result["landareakm"] = 0;


$result["regionlist"] = array();
$sql = "select r.uuid, r.regionName, r.locX, r.locY, r.sizeX, l.regionUUID, l.LandFlags as flags from regions as r, land as l where r.uuid=l.regionUUID group by r.uuid order by regionName;";
$publicAccess = UseAccessGroup | UseAccessList | UsePassList;
$db->query($sql);
while( $r = $db->next_rec_as_obj() ){
    $xreg = array();
    $xreg["name"] = $r->regionName;
    $xreg["coordx"] = $r->locX / 256;
    $xreg["coordy"] = $r->locY / 256;
    $xreg["size"]   = $r->sizeX / 256;
    $result["landarea"] += $r->sizeX;
    //$xreg["public"] = $r->musicURL != "";
    $pub = $r->flags & $publicAccess;
    $xreg["public"] =  $pub == 0;
    $xreg["users"] = array();

    $sql = "select p.UserID, p.RegionID, g.UserID from Presence as p, GridUser as g where p.RegionID='$r->uuid' and p.UserID=g.UserID and g.UserID like '%;http%'";
    $db2->query($sql);
    $xreg["users"]["grid"] = $db2->num_rows();

    $sql = "select p.UserID, p.RegionID, g.UserID from Presence as p, GridUser as g where p.RegionID='$r->uuid' and p.UserID=g.UserID and g.UserID not like '%;http%'";
    $db2->query($sql);
    $xreg["users"]["local"] = $db2->num_rows();
    
    if( $p->regionlist != "no" ){
        $result["regionlist"][] = $xreg;
    }
}

$result["landareakm"] = $result["landarea"] / 1000;
$result["landarea"] = $result["landarea"] * $result["landarea"];
// put public regions at the top of the list
// they're already sorted by region name, so region name will act as a sub-sort
usort( $result["regionlist"], "cmp" );


$jr = json_encode( $result, JSON_PRETTY_PRINT );
echo $jr;
?>
