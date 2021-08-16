<?php
if(empty($_POST))die(); //Don't allow people to be funny

require_once("config.php");
require_once("../regapi/llsd.php");
require_once("../regapi/regapi.php");
$ra = new RegAPI($caps);

function doError($msg){
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Uh oh!</title>
    </head>
    <body>
        <h1>An error occurred!</h1>
        <?php echo $msg; ?><br/>
        <form method="get" action="/example/index.php">
            <input type="submit" value="Restart!" />
        </form>
    </body>
</html>
<?php
    die();
}

//First parse the username
$username = $_POST["username"];
if(!isset($_POST["username"]))
    doError("No username specified!");

//And now the avatar
$avatar = $_POST["avatar"];
if(!isset($_POST["avatar"]))
    doError("No avatar specified!");

$avatar = new UUID($avatar);

//Now get if they opt into marketing
$marketing = false;
if(isset($_POST["markerting"])){
    if($_POST["markerting"] == "on")
        $marketing = true;
    else
        $marketing = false;
}

//And finally if check if they are an adult
$maturity = "General";
if(isset($_POST["maturity"]) && in_array($_POST["maturity"], array("General", "Moderate", "Adult"))){
    $maturity = $_POST["maturity"];
}

//Make sure they chose a valid avatar
if(!array_key_exists($_POST["avatar"], $ra->getAvatars()))
    doError("Sorry, that avatar isn't available!");

//Now see if the username is free
try{
    if($ra->checkName($username) === false)
        doError("Sorry, that username isn't available isn't available!");
}catch(RegAPIError $e){
    //No? Print out why
    doError($e);
}

//Ok now we can begin the actual request!
try{
    $host = $_SERVER['SERVER_NAME'];
    $port = $_SERVER['SERVER_PORT'];
    $res = $ra->createUser($username,
        null,
        $config["estate"],
        $config["region"],
        $config["location"],
        $config["lookat"],
        $marketing,
        "http://$host:$port/example/success.php?username=".urlencode($username),
        "http://$host:$port/example/error.php",
        $maturity
    );
    header("location: ".$res["complete_reg_url"]);
    try{
        if(!$config["experience"]->is_null())
            $ra->setUserExperience($res["agent_id"], $config["experience"]);
    }catch(RegAPIError $e){
        //Client is gone by now, don't do anything!
    }
    try{
        if($config["group"] != "")
            $ra->addToGroup($username, $config["group"]);
    }catch(RegAPIError $e){
        //Client is gone by now, don't do anything!
    }
}catch(RegAPIError $e){
    //No? Print out why
    doError($e);
}