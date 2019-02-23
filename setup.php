<?php

require 'vendor/autoload.php';
use GuzzleHttp\Client;
use splitbrain\PHPArchive\Tar;

//get installer config
$installerconfig = getInstallerConfig();

$headers = apache_request_headers();
$installtype  = (isset($headers['Installtype'])) ? $headers['Installtype'] : 'nocp';
print_debug_log($installerconfig['debug_log'],'Install type '.$installtype);

//chk extension json load
if (!extension_loaded('json')) {
    header('Content-Type: text/html');
    echo 'Can not load Extension JSON';
    print_install_log($installerconfig['install_log'] , 'Can not load Extension JSON');
    exit;
}

//json header
header('Content-type: application/json');

//common validation
if(ini_get('allow_url_fopen') != 1){
    echo json_encode( ['status' => false , 'message' => 'Error php.ini, Must set allow_url_fopen=ON'] );
    print_install_log($installerconfig['install_log'] , 'Error php.ini, Must set allow_url_fopen=ON');
    exit;
}

//if not file setupapiserver or install.html
if (! file_exists(dirname(__FILE__).'/install.html') || ! file_exists(dirname(__FILE__).'/setupapiserver.php')) {
    //set download real-setup url
    $mirror =  (isset($installerconfig['mirror'])) ? $installerconfig['mirror'] : 'http://files.mirror1.rvsitebuilder.com';
    if($installerconfig['installer']['getversion'] == 'latest') {
        $downloadurl = $mirror.'/download/rvsitebuilderinstaller/install/tier/latest';
    }
    elseif(preg_match('/[0-9]+\.[0-9]+\.[0-9]+/',$installerconfig['installer']['getversion'])) {
        $downloadurl = $mirror.'/download/rvsitebuilderinstaller/install/version/'.$installerconfig['installer']['getversion'];
    }
    else{
        $downloadurl = $mirror.'/download/rvsitebuilderinstaller/install';
    }
    print_debug_log($installerconfig['debug_log'],'Download installer url '.$downloadurl);
    //download
    $downloadreal = doDownload('GET' , $downloadurl , dirname(__FILE__).'/install.tar.gz');
    if(! $downloadreal){
        echo json_encode( ['status' => false , 'message' => 'Can not download rvsitebuilder installer.'] );
        print_install_log($installerconfig['install_log'] , 'Can not download rvsitebuilder installer.');
        exit;
    }
    //extract
    $extractreal  = doExtract(dirname(__FILE__).'/install.tar.gz',dirname(__FILE__).'/');
    if(! $extractreal) {
        echo json_encode( ['status' => false , 'message' => 'Can not extract rvsitebuilder installer.'] );
        print_install_log($installerconfig['install_log'] , 'Can not extract rvsitebuilder installer.');
        exit;
    }
}

//Go Go Go
if($installtype == 'nocp') {
    print_install_log($installerconfig['install_log'] , 'Redirect to real installer INSTALL.HTML');
    print_debug_log($installerconfig['debug_log'],'Redirect to real installer INSTALL.HTML');
    header("Location: install.html");
}
else {
    print_install_log($installerconfig['install_log'] , 'Redirect to real installer SETUPAPISERVER.PHP');
    print_debug_log($installerconfig['debug_log'],'Redirect to real installer SETUPAPISERVER.PHP');
    header("Location: setupapiserver.php");
}
die();


/* Function */
function getInstallerConfig() {
    //defaultconfig
    $defconfig = parse_ini_file(dirname(__FILE__).'/rvsitebuilderinstallerconfig_dist/config.ini',true);
    
    //overwrite installer config by user
    $userconfig = [];
    if(file_exists(__DIR__.'/../.rvsitebuilderinstallerconfig/config.ini')) {
        $userconfig = parse_ini_file(__DIR__.'/../.rvsitebuilderinstallerconfig/config.ini',true);
    }
    return array_merge($defconfig,$userconfig);
}
function doDownload($type, $url, $sink) {
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
function doExtract($file,$path) {
    $tar = new Tar();
    $tar->open($file);
    $tar->extract($path);
    return true;
}

function print_debug_log($debug , $msg = '') {
    if($debug == true){
        file_put_contents(
            dirname(__FILE__).'/install_log.txt',
            'DEBUG LOG >> ' .$msg.PHP_EOL ,
            FILE_APPEND | LOCK_EX
            );
    }
    return true;
}

function print_install_log($installlog , $msg = '') {
    if($installlog == true){
        file_put_contents(
            dirname(__FILE__).'/install_log.txt',
            'INSTALL LOG >> ' .$msg.PHP_EOL ,
            FILE_APPEND | LOCK_EX
            );
    }
    return true;
}
?>