<?php 

require 'vendor/autoload.php';
use GuzzleHttp\Client;


$headers                = apache_request_headers();
$action                 = (isset($_GET['action'])) ? $_GET['action'] : '';
$responsetype           = (isset($headers['Accept'])) ? $headers['Accept'] : 'application/json';
$rvsb_installing_token  = (isset($headers['Rvsb-Installing-Token'])) ? $headers['Rvsb-Installing-Token'] : '';
$homeuser               = (isset($_GET['homeuser'])) ? $_GET['homeuser'] : '';
$domainname             = (isset($_GET['domainname'])) ? $_GET['domainname'] : '';
$publicpath             = (isset($_GET['public_path'])) ? $_GET['public_path'] : '';

/*
 * MAIN
 */
//first request - generator token for first request
if($rvsb_installing_token == '' && ! file_exists(dirname(__FILE__).'/.Rvsb-Installing-Token')) {
    $rvsb_installing_token = genTokenAndSaveFile();
    $firstreg = true;
}

//if not file setupapiserver
//TODO exists and sign version with version1.rvsitebuilder.com
if (! file_exists(dirname(__FILE__).'/setupapiserver.php')) {
    //TODO
    //download setupapiserver 
    //extrack
    //chmod chperm
} 

//set session
session_start();
$_SESSION['responsetype'] = $responsetype;
$_SESSION['action'] = $action;
$_SESSION['rvsb_installing_token'] = $rvsb_installing_token;
$_SESSION['homeuser'] = $homeuser;
$_SESSION['domainname'] = $domainname;
$_SESSION['docroot'] = $docroot;
$_SESSION['public_path'] = $publicpath;
if($firstreg){
    $_SESSION['firstreg'] = true;
}

include('setupapiserver.php');



/*
 *SUBROUTINE
 */
function genTokenAndSaveFile() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $length = 128;
    $randstring = '';
    for ($i = 0; $i < $length; $i++) {
        $randstring .= $characters[rand(0, $charactersLength - 1)];
    }
    file_put_contents(dirname(__FILE__).'/.Rvsb-Installing-Token', $randstring);
    return $randstring;
}


?>