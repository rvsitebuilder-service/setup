<?php 

require 'vendor/autoload.php';
use GuzzleHttp\Client;
use splitbrain\PHPArchive\Tar;


header("Content-Type: text/html");

//if not file setupapiserver
if (! file_exists(dirname(__FILE__).'/install.html')) {
    
    if(ini_get('allow_url_fopen') != 1){
        echo "Error PHP INI config ,allow_url_fopen must be enabled ";
        exit;
    }
    $downloadurl = (check_getlatestversion()) ? 'http://files.mirror1.rvsitebuilder.com/download/rvsitebuilderinstaller/install/tier/latest'
                                              : 'http://files.mirror1.rvsitebuilder.com/download/rvsitebuilderinstaller/install' ;
    $downloadreal = do_download('GET' , $downloadurl , dirname(__FILE__).'/install.tar.gz');
    if(! $downloadreal){
        echo "Error Can not download RVsitebuilder Installer.";
        exit;
    }
    $extractreal  = do_extract(dirname(__FILE__).'/install.tar.gz',dirname(__FILE__).'/');
    if(! $extractreal) {
        echo "Error Can not download RVsitebuilder Installer.";
        exit;
    }
} 


//Go Go Go
header("Location: install.html");
die();




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

function check_getlatestversion (){
    if(file_exists(dirname(__FILE__).'/.getlatestversion')) {
        return true;
    }
    return false;
}


?>