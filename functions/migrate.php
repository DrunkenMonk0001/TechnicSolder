<?php
error_reporting(E_ALL);
session_start();
$config = require("./config.php");
require("dbconnect.php");
include('toml.php');
global $fileName;
global $fileNameZip;
global $fileNameShort;
global $fileJarInFolderLocation;
global $fileZipLocation;
global $conn;
global $warn;
global $fileInfo;
$legacy=false;
$mcmod=array();
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
	function slugify($text) {
		$text = preg_replace('~[^\pL\d]+~u', '-', $text);
		$text = preg_replace('~[^-\w]+~', '', $text);
		$text = trim($text, '-');
		$text = preg_replace('~-+~', '-', $text);
		$text = strtolower($text);
	if (empty($text)) {
		return 'n-a';
	}
	return $text;
	}
mysqli_query($conn, "TRUNCATE `modpacks`");
mysqli_query($conn, "TRUNCATE `builds`");
mysqli_query($conn, "TRUNCATE `clients`");
mysqli_query($conn, "TRUNCATE `mods`");
// ----- MODPACKS ----- \\
$res = mysqli_query($oemsolder, "SELECT `id`, `name`,`slug`,`private`,`latest`,`recommended` FROM `modpacks`");
while($row = mysqli_fetch_array($res)) {
    $latest = mysqli_fetch_array(mysqli_query($oemsolder,"select `version` FROM `builds` WHERE `id` = '".$row['latest']."'"))['version'];
    $recommended = mysqli_fetch_array(mysqli_query($oemsolder,"select `version` FROM `builds` WHERE `id` = '".$row['recommended']."'"))['version'];
    if ($row['private'] == 0) {
        $public = 1;
    } else {
        $public = 0;
    }
    mysqli_query($conn, "INSERT INTO `modpacks` (`id`,`display_name`,`name`,`public`,`latest`,`recommended`,`icon`) VALUES ('".$row['id']."','".$row['name']."','".$row['slug']."',".$public.",'".$latest."','".$recommended."','http://demo.solder.cf/TechnicSolder/resources/default/icon.png')");
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
	copy($_POST['solder-orig'].$package['name']."/".$package['name']."-".$row['version'].".zip", dirname(dirname(__FILE__))."/mods/".end(explode("/",$package['name']."/".$package['name']."-".$row['version'].".zip")));
$fileNameShort=$package['name']."-".$row['version'];
$fileNameZip=$package['name']."-".$row['version'].".zip";
$fileName="../mods/mods/".$package['name']."-".$row['version'].".jar";
$fileJarInFolderLocation="../mods/mods-".$fileNameShort."/".$fileName;
$fileZipLocation="../mods/".$fileNameZip;
$fileInfo=array();
$zip = new ZipArchive;
if ($zip->open($fileZipLocation) === TRUE) {
    $zip->extractTo('../mods/');
    $zip->close();
    echo 'ok';
} else {
    echo 'failed';
}
    $result = @file_get_contents("zip://".$fileName."#META-INF/mods.toml");
    if (!$result) {
        # fail 1.14+ or fabric mod check
        $result = file_get_contents("zip://".$fileName."#mcmod.info");
        if (!$result) {
            # fail legacy mod check
            $warn['b'] = true;
            $warn['level'] = "warn";
            $warn['message'] = "File does not contain mod info. Manual configuration required.";
        } elseif (file_get_contents("zip://".realpath("../mods/mods-".$fileName."/".$fileName)."#fabric.mod.json")) {
            # is a fabric mod
            $result = file_get_contents("zip://" . realpath("../mods/mods-" . $fileName . "/" . $fileName) . "#fabric.mod.json");
            $q = json_decode(preg_replace('/\r|\n/', '', trim($result)), true);
            $mcmod = $q;
            $mcmod["modid"] = $mcmod["id"];
            $mcmod["url"] = $mcmod["contact"]["sources"];
            if (!$mcmod['modid'] || !$mcmod['name'] || !$mcmod['description'] || !$mcmod['version'] || !$mcmod['mcversion'] || !$mcmod['url'] || !$mcmod['authorList']) {
                $warn['b'] = true;
                $warn['level'] = "info";
                $warn['message'] = "There is some information missing in fabric.mod.json.";
            }
        } else {
            # is legacy mod
            $legacy=true;
            $mcmod = json_decode(preg_replace('/\r|\n/','',trim($result)),true)[0];
            if (!$mcmod['modid']||!$mcmod['name']||!$mcmod['description']||!$mcmod['version']||!$mcmod['mcversion']||!$mcmod['url']||!$mcmod['authorList']) {
                $warn['b'] = true;
                $warn['level'] = "info";
                $warn['message'] = "There is some information missing in mcmod.info.";
            }
        }
    } else { # is 1.14+ mod
        $legacy=false;
        $mcmod = $mcmod = Toml::parse($result);
        //error_log(json_encode($mcmod, JSON_PRETTY_PRINT));
        if (!$mcmod['mods']['modId']||!$mcmod['mods']['displayName']||!$mcmod['mods']['description']||!$mcmod['mods']['version']||!$mcmod['mods']['displayURL']||!($mcmod['mods']['author'] && $mcmod['mods']['authors'])) {
            $warn['b'] = true;
            $warn['level'] = "info";
            $warn['message'] = "There is some information missing in mcmod.info.";
        }
    }
    if ($zipExists) { // while we could put a file check here, it'd be redundant (it's checked before).
        // cached zip
    } else {
        $zip = new ZipArchive();
        if ($zip->open($fileZipLocation, ZIPARCHIVE::CREATE) !== TRUE) {
            echo '{"status":"error","message":"Could not open archive"}';
            exit();
        }
        $zip->addEmptyDir('mods');
        if (is_file($fileJarInFolderLocation)) {
            $zip->addFile($fileJarInFolderLocation, "mods/".$fileName) or die ('{"status":"error","message":"Could not add file $key"}');
        }
        $zip->close();
    }
    if ($legacy) {
        if (!$mcmod['name']) {
            $pretty_name = mysqli_real_escape_string($conn, $fileNameShort);
        } else {
            $pretty_name = mysqli_real_escape_string($conn, $mcmod['name']);
        }
        if (!$mcmod['modid']) {
            $name = slugify($pretty_name);
        } else {
            if (@preg_match("^[a-z0-9]+(?:-[a-z0-9]+)*$", $mcmod['modid'])) {
                $name = $mcmod['modid'];
            } else {
                $name = slugify($mcmod['modid']);
            }
        }
        $link = $mcmod['url'];
        $author = mysqli_real_escape_string($conn, implode(', ', $mcmod['authorList']));
        $description = mysqli_real_escape_string($conn, $mcmod['description']);
        $version = $mcmod['version'];
        $mcversion = $mcmod['mcversion'];
    } else {
        if (!$mcmod['mods'][0]['displayName']) {
            $pretty_name = mysqli_real_escape_string($conn, $fileNameShort);
        } else {
            $pretty_name = mysqli_real_escape_string($conn, $mcmod['mods'][0]['displayName']);
        }
        if (!$mcmod['mods'][0]['modId']) {
            $name = slugify($pretty_name);
        } else {
            if (preg_match("^[a-z0-9]+(?:-[a-z0-9]+)*$", $mcmod['mods']['modId'])) {
                $name = $mcmod['mods'][0]['modId'];
            } else {
                $name = slugify($mcmod['mods'][0]['modId']);
            }
        }
        $link = empty($mcmod['mods']['displayURL'])? $mcmod[0]['displayURL'] : $mcmod['mods']['displayURL'];
        $authorRoot=empty($mcmod['authors'])? $mcmod['author'] : $mcmod['authors'];
        $authorMods=empty($mcmod['mods'][0]['authors'])? $mcmod['mods'][0]['author'] : $mcmod['mods'][0]['authors'];
        $author = mysqli_real_escape_string($conn, empty($authorRoot)? $authorMods : $authorRoot);
        $description = mysqli_real_escape_string($conn, $mcmod['mods'][0]['description']);
		$i = 0;
		//check for the minecraft dependency in the toml, if it's not found, use the first version range (forge)
		while($mcmod['dependencies'][$mcmod['mods'][0]['modId']][$i]['modId'] != 'minecraft') {
		$i++;
		if ($mcmod['dependencies'][$mcmod['mods'][0]['modId']][$i]['modId'] == null) break;
		}
		if ($mcmod['dependencies'][$mcmod['mods'][0]['modId']][$i]['modId'] == null) {
		$tmpver = array();
		preg_match('/(^.*?)(1[.|-][1-9]+)/', $fileName, $tmpver);
		$mcversion = str_replace("-", ".", $tmpver[2]);
		} else {
		$mcversion = $mcmod['dependencies'][$mcmod['mods'][0]['modId']][$i]['versionRange'];
		}
		//file_put_contents('filename.txt', print_r($mcmod, true));
        // let the user fill in if not absolutely certain.
        /* if (empty($mcversion)) { //if there is no dependency specified, get from filename
            // THIS SHOULD NEVER BE NECESSARY, BUT SOME MODS (OptiFine) DON'T HAVE A MINECRAFT DEPENDENCY LISTED
            $divideDash=explode('-', $fileNameShort);
            $mcversion=$divideDash[1].'.'.$divideDash[2]; // we get modname-1-16-5-1-1-1.jar. we don't know if it is 1-16 or 1-16-5, so it's safer to assume 1-16
        } */
        $version = $mcmod['mods'][0]['version'];
        if ($version == "\${file.jarVersion}" ) {
            $tmpFilename=explode('-', $fileNameShort);
            array_shift($tmpFilename);
            $tmpFilename = implode('.', $tmpFilename);
            $version=$tmpFilename;
        }
        // let the user fill in if not absolutely certain. (except for above if)
        /* if (empty($version)) { //if there is no dependency specified, get from filename
            // THIS SHOULD NEVER BE NECESSARY, BUT SOME MODS (OptiFine) DON'T HAVE A MINECRAFT DEPENDENCY LISTED
            $divideDash=explode('-', $fileNameShort);
            $version=end($divideDash); // we get modname-1-16-5-1-1-1.jar. just take the last - as we don't know.
        } */
    }
    mysqli_query($conn,"INSERT INTO `mods` (`type`,`url`,`version`,`md5`,`filename`,`name`,`pretty_name`,`author`,`link`,`donlink`,`description`,`mcversion`) VALUES ('mod','".$url."','".$row['version']."','".$row['md5']."','".$package['name']."-".$row['version'].".zip','".$name."','".$pretty_name."','".$author."','".$link."','','".$description."','".$mcversion."')");
}
// ----- BUILD_RELEASE ----- \\
$res = mysqli_query($oemsolder, "SELECT * FROM `builds`");
while($row = mysqli_fetch_array($res)) {
    $mods = [];
    $mres = mysqli_query($oemsolder, "SELECT * FROM `build_modversion` WHERE `build_id` = '".$row['id']."'");
    $ma = mysqli_fetch_array($mres);
    $ml = explode(',', $ma['modversion_id']);
    if (count($ml)>0) {
        array_push($mods, implode(',',$ml));
    }
    array_push($mods, $row['release_id']);
    mysqli_query($conn, "UPDATE `builds` SET `mods` = '". implode(',',$mods)."' WHERE `modpack` = '".$row['id']."'");
}

function removeDirectory($path) {

	$files = glob($path . '/*');
	foreach ($files as $file) {
		is_dir($file) ? removeDirectory($file) : unlink($file);
	}
	rmdir($path);

	return;
}

removeDirectory('../mods/mods');
removeDirectory('../mods/config');
removeDirectory('../mods/bin');