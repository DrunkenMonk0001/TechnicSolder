<?php
error_reporting(E_ALL);
session_start();
$config = require("./config.php");
require("dbconnect.php");
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("Unauthorized request or login session has expired!");
}
if (empty($_POST['db-pass'])) {
    die("error");
}
if (empty($_POST['db-name'])) {
    die("error");
}
if (empty($_POST['db-user'])) {
    die("error");
}
if (empty($_POST['db-host'])) {
    die("error");
}
if (empty($_POST['solder-orig'])) {
    die("error");
}
if (!$_SESSION['user']||$_SESSION['user']=="") {
    die("error");
}
$oemsolder = mysqli_connect($_POST['db-host'],$_POST['db-user'],$_POST['db-pass'],$_POST['db-name']);
if (!$oemsolder) {
    die("error");
}
mysqli_query($conn, "TRUNCATE `modpacks`");
mysqli_query($conn, "TRUNCATE `builds`");
mysqli_query($conn, "TRUNCATE `clients`");
mysqli_query($conn, "TRUNCATE `mods`");
// ----- MODPACKS ----- \\
$res = mysqli_query($oemsolder, "SELECT `name`,`slug`,`private`,`latest`,`recommended` FROM `modpacks`");
while($row = mysqli_fetch_array($res)) {
    $latest = mysqli_fetch_array(mysqli_query($oemsolder,"select `version` FROM `builds` WHERE `id` = '".$row['latest']."'"))['version'];
    $recommended = mysqli_fetch_array(mysqli_query($oemsolder,"select `version` FROM `builds` WHERE `id` = '".$row['recommended']."'"))['version'];
    if ($row['private'] == 0) {
        $public = 1;
    } else {
        $public = 0;
    }
    mysqli_query($conn, "INSERT INTO `modpacks` (`display_name`,`name`,`public`,`latest`,`recommended`,`icon`) VALUES ('".$row['name']."','".$row['slug']."',".$public.",'".$latest."','".$recommended."','http://demo.solder.cf/TechnicSolder/resources/default/icon.png')");
}
// ----- BUILDS ----- \\
$res = mysqli_query($oemsolder, "SELECT `modpack_id`,`version`,`minecraft`,`private`,`min_java`,`min_memory` FROM `builds`");
while($row = mysqli_fetch_array($res)) {
    if ($row['private'] == "0") {
        $public = 1;
    } else {
        $public = 0;
    }
    mysqli_query($conn, "INSERT INTO `builds` (`modpack`,`name`,`public`,`minecraft`,`java`,`memory`) VALUES ('".$row['modpack_id']."','".$row['version']."',".$public.",'".$row['minecraft']."','".$row['min_java']."','".$row['min_memory']."')");
}
// ----- CLIENTS ----- \\
$res = mysqli_query($oemsolder, "SELECT `name`,`uuid` FROM `clients`");
while($row = mysqli_fetch_array($res)) {
    mysqli_query($conn,"INSERT INTO `clients` (`name`,`UUID`) VALUES ('".$row['name']."','".$row['uuid']."')");
}
// ----- MODS ----- \\
$res = mysqli_query($oemsolder, "SELECT * FROM `modversions`");
while($row = mysqli_fetch_array($res)) {
    $url = "http://".$config['host'].$config['dir']."mods/".end(explode("/",$row['path']));
    $packageres = mysqli_query($oemsolder, "SELECT * FROM `mods` WHERE `id` = '".$row['mod_id']."'");
    $package = mysqli_fetch_array($packageres);
    mysqli_query($conn,"INSERT INTO `mods` (`type`,`url`,`version`,`md5`,`filename`,`name`,`pretty_name`,`author`,`link`,`donlink`,`description`) VALUES ('mod','".$url."','".$row['version']."','".$row['md5']."','".$package['name']."/".$package['name']."-".$row['version'].".zip','".$package['name']."','".$package['name']."','".$package['author']."','".$package['link']."','".$package['link']."','".$package['description']."')");
    copy($_POST['solder-orig'].$package['name']."/".$package['name']."-".$row['version'].".zip", dirname(dirname(__FILE__))."/mods/".end(explode("/",$package['name']."/".$package['name']."-".$row['version'].".zip")));
}
// ----- BUILD_RELEASE ----- \\
$res = mysqli_query($oemsolder, "SELECT * FROM `builds`");
while($row = mysqli_fetch_array($res)) {
    $mods = [];
    $mres = mysqli_query($conn, "SELECT `modversion_id` FROM `builds_modversion` WHERE `build_id` = '".$row['id']."'");
    $ma = mysqli_fetch_array($mres);
    $ml = explode(',', $ma['mods']);
    if (count($ml)>0) {
        array_push($mods, implode(',',$ml));
    }
    array_push($mods, $row['release_id']);
    mysqli_query($conn, "UPDATE `builds` SET `mods` = '". implode(',',$mods)."' WHERE `id` = '".$row['id']."'");
}
