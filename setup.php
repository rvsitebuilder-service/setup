<?php 

require 'vendor/autoload.php';
use GuzzleHttp\Client;
use splitbrain\PHPArchive\Tar;

$headers                = apache_request_headers();
$action                 = (isset($_GET['action'])) ? $_GET['action'] : '';
$responsetype           = (isset($headers['Accept'])) ? $headers['Accept'] : 'application/json';
$rvsb_installing_token  = (isset($headers['Rvsb-Installing-Token'])) ? $headers['Rvsb-Installing-Token'] : '';
$homeuser               = (isset($_GET['homeuser'])) ? $_GET['homeuser'] : '';
$domainname             = (isset($_GET['domainname'])) ? $_GET['domainname'] : '';
$publicpath             = (isset($_GET['public_path'])) ? $_GET['public_path'] : '';
$dbhost             = (isset($_GET['dbhost'])) ? $_GET['dbhost'] : '';
$dbname            = (isset($_GET['dbname'])) ? $_GET['dbname'] : '';
$dbuser             = (isset($_GET['dbuser'])) ? $_GET['dbuser'] : '';
$dbpassword             = (isset($_GET['dbpassword'])) ? $_GET['dbpassword'] : '';
$ftpaccount             = (isset($_GET['ftpaccount'])) ? $_GET['ftpaccount'] : '';
$ftppassword             = (isset($_GET['ftppassword'])) ? $_GET['ftppassword'] : '';
$ftpserver             = (isset($_GET['ftpserver'])) ? $_GET['ftpserver'] : '';
$ftpport             = (isset($_GET['ftpport'])) ? $_GET['ftpport'] : '';
$appname                = (isset($_GET['appname'])) ? $_GET['appname'] : 'RVsitebuilder';


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
    
    if(ini_get('allow_url_fopen') != 1){
        header('Content-type: application/json');
        echo json_encode( ['status' => false , 'message' => 'Error php.ini, Must set allow_url_fopen=ON'] );
        exit;
    }
    $downloadurl = (check_getlatestversion()) ? 'http://files.mirror1.rvsitebuilder.com/download/rvsitebuilderinstaller/install/tier/latest'
                                              : 'http://files.mirror1.rvsitebuilder.com/download/rvsitebuilderinstaller/install' ;
    $downloadreal = do_download('GET' , $downloadurl , dirname(__FILE__).'/install.tar.gz');
    if(! $downloadreal){
        header('Content-type: application/json');
        echo json_encode( ['status' => false , 'message' => 'Can not download rvsitebuilder installer.'] );
        exit;
    }
    $extractreal  = do_extract(dirname(__FILE__).'/install.tar.gz',dirname(__FILE__).'/');
    if(! $extractreal) {
        header('Content-type: application/json');
        echo json_encode( ['status' => false , 'message' => 'Can not extract rvsitebuilder installer.'] );
        exit;
    }
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
$_SESSION['dbhost'] = $dbhost;
$_SESSION['dbname'] = $dbname;
$_SESSION['dbuser'] = $dbuser;
$_SESSION['dbpassword'] = $dbpassword;
$_SESSION['ftpaccount'] = $ftpaccount;
$_SESSION['ftppassword'] = $ftppassword;
$_SESSION['ftpserver'] = $ftpserver;
$_SESSION['ftpport'] = $ftpport;
$_SESSION['appname'] = $appname;
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

function do_download($type, $url, $sink) {
    $client = new Client([
        'curl'            => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false],
        'allow_redirects' => false,
        'cookies'         => true,
        'verify'          => false
    ]);
    $client->request($type, $url, ['sink' => $sink]);
    if(file_exists($sink)) {
        return true;
    }
    return false;
}

function do_extract($file,$path) {
    $tar = new Tar();
    $tar->open($file);
    $tar->extract($path);
    return true;
}

function check_getlatestversion(){
    if(file_exists(dirname(__FILE__).'/.getlatestversion')) {
        return true;
    }
    return false;
}


?>