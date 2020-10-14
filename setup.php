<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use splitbrain\PHPArchive\Tar;

//get installer config
$installerconfig = getInstallerConfig();
//get provision config
$provisionconfig = getProvisionConfig();

//get header
$headers = (function_exists('apache_request_headers') || is_callable('apache_request_headers'))  ? apache_request_headers() : rv_apache_request_headers();
$headers = array_change_key_case($headers, CASE_UPPER);

//set installtype
$installtype =  'nocp';
if (isset($headers['INSTALLTYPE']) && isset($headers['INSTALLTYPE']) != '') $installtype = $headers['INSTALLTYPE'];
if (isset($provisionconfig['provisioning'])) $installtype = 'provision';

print_debug_log($installerconfig['debug_log'], 'Install type ' . $installtype);
$rvlicensecode = $headers['RV-LICENSE-CODE'] ?? '';
print_debug_log($installerconfig['debug_log'], 'RV License Code ' . $rvlicensecode);

//chk extension json load
if (!extension_loaded('json')) {
    header('Content-Type: text/html');
    echo 'Can not load Extension JSON';
    print_install_log($installerconfig['install_log'], 'Can not load Extension JSON');
    exit;
}

//json header
header('Content-type: application/json');


//if not file setupapiserver or install.html
if (!file_exists(dirname(__FILE__) . '/install.html') || !file_exists(dirname(__FILE__) . '/setupapiserver.php')) {
    //set download real-setup url
    $mirror = $installerconfig['mirror'] ?? 'http://files.mirror1.rvsitebuilder.com';

    $getversionurl = 'https://getversion.rvsitebuilder.com/getversion';
    if ($installerconfig['installer']['getversion'] == 'latest') {
        $downloadurl = $mirror . '/download/rvsitebuilderinstaller/install/tier/latest';
        $getversionurl .= '/rvsitebuilderinstaller/install/tier/latest';
    } elseif (preg_match('/[0-9]+\.[0-9]+\.[0-9]+/', $installerconfig['installer']['getversion'])) {
        $downloadurl = $mirror . '/download/rvsitebuilderinstaller/install/version/' . $installerconfig['installer']['getversion'];
        $getversionurl .= '/rvsitebuilderinstaller/install/version/' . $installerconfig['installer']['getversion'];
    } else {
        $downloadurl = $mirror . '/download/rvsitebuilderinstaller/install';
        $getversionurl .= '/rvsitebuilderinstaller/install';
    }
    print_debug_log($installerconfig['debug_log'], 'Download installer url ' . $downloadurl);
    //download
    $downloadreal = doDownload('GET', $downloadurl, dirname(__FILE__) . '/install.tar.gz', $rvlicensecode, $installerconfig['debug_log'], $getversionurl);
    if ($downloadreal['success'] == false) {
        echo json_encode(['status' => false, 'message' => $downloadreal['message']]);
        print_install_log($installerconfig['install_log'], $downloadreal['message']);
        exit;
    }
    //extract
    $extractreal  = doExtract(dirname(__FILE__) . '/install.tar.gz', dirname(__FILE__) . '/', $installerconfig['debug_log']);
    if ($extractreal['success'] = false) {
        echo json_encode(['status' => false, 'message' => 'Can not extract rvsitebuilder installer.']);
        print_install_log($installerconfig['install_log'], 'Can not extract rvsitebuilder installer.');
        exit;
    }
}

//Go Go Go
//nocp
if ($installtype == 'nocp') {
    print_install_log($installerconfig['install_log'], 'Redirect to real installer INSTALL.HTML');
    print_debug_log($installerconfig['debug_log'], 'Redirect to installer INSTALL.HTML');
    header("Location: install.html");
}
//provision
elseif ($installtype == 'provision') {
    print_install_log($installerconfig['install_log'], 'Redirect to real installer PROVISION.HTML');
    print_debug_log($installerconfig['debug_log'], 'Redirect to installer PROVISION.HTML');
    header("Location: provision.html");
}
//cpanel
else {
    print_install_log($installerconfig['install_log'], 'Redirect to real installer SETUPAPISERVER.PHP');
    print_debug_log($installerconfig['debug_log'], 'Redirect to real installer SETUPAPISERVER.PHP');
    header("Location: setupapiserver.php");
}
die();


/* Function */
function getInstallerConfig(): array
{

    //defaultconfig
    $defconfig = [];
    $defconfigpath = dirname(__FILE__) . '/rvsitebuilderinstallerconfig_dist/config.ini';
    if (file_exists($defconfigpath)) {
        $defconfig = parse_ini_file($defconfigpath, true);
    }

    //overwrite installer config by root config
    $rootconfig = [];
    $rootconfigpath = dirname(__FILE__) . '/../.rvsitebuilderinstallerconfig/root_config.ini';
    if (file_exists($rootconfigpath)) {
        $rootconfig = parse_ini_file($rootconfigpath, true);
    }
    $installerconfig1 = array_merge($defconfig, $rootconfig);

    //overwrite installer config by user config
    $userconfig = [];
    $userconfigpath = dirname(__FILE__) . '/../.rvsitebuilderinstallerconfig/config.ini';
    if (file_exists($userconfigpath)) {
        $userconfig = parse_ini_file($userconfigpath, true);
    }
    $installerconfig2 = array_merge($installerconfig1, $userconfig);

    return $installerconfig2;
}

function getProvisionConfig(): array
{
    $provisionConfig = [];
    $userpathinfo = get_user_path_info();
    $domainname = get_current_domain();
    $configfile = $userpathinfo['homepath'] . '/rvsitebuildercms/' . $domainname . '/provisioning.ini';
    if (file_exists($configfile)) {
        $provisionConfig = parse_ini_file($configfile, true);
    }

    if (
        !isset($provisionConfig['provisioning']['db_host']) || !isset($provisionConfig['provisioning']['db_name']) ||
        !isset($provisionConfig['provisioning']['db_user']) || !isset($provisionConfig['provisioning']['db_pass']) ||
        !isset($provisionConfig['provisioning']['admin_email']) || !isset($provisionConfig['provisioning']['admin_password'])
    ) {
        return [];
    }

    return $provisionConfig;
}

function get_current_domain(): string
{
    $domainname = '';
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] != '') {
        $domainname = $_SERVER['HTTP_HOST'];
    }
    if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] != '') {
        $domainname = $_SERVER['SERVER_NAME'];
    }
    $domainname = str_replace("www.", "", $domainname);
    return $domainname;
}

function get_user_path_info(): array
{
    $userpathinfo = [];
    $user_path = '';
    $document_root = $_SERVER['DOCUMENT_ROOT'];
    $user_paths = [];
    $open_basedir_paths = get_open_basedir_paths();
    if (count($open_basedir_paths)) {
        $user_paths = $open_basedir_paths;
    } else {
        if (isset($_SERVER['HOME'])) {
            if (!in_array($_SERVER['HOME'], $user_paths)) {
                array_push($user_paths, $_SERVER['HOME']);
            }
        }
        $posix_user_path = '';
        if (function_exists('posix_getuid')) {
            $webuid = posix_getuid();
            $userinfo = posix_getpwuid($webuid);
            if (is_dir($userinfo['dir'])) {
                if (!in_array($userinfo['dir'], $user_paths)) {
                    array_push($user_paths, $userinfo['dir']);
                }
            }
        }
        // case  have posix_getpwuid get uid by owner dir
        if (function_exists('posix_getpwuid')) {
            $stat = stat($document_root);
            $userinfo = posix_getpwuid($stat['uid']);
            if (is_dir($userinfo['dir'])) {
                if (!in_array($userinfo['dir'], $user_paths)) {
                    array_push($user_paths, $userinfo['dir']);
                }
            }
            $userinfo = posix_getpwuid($stat['gid']);
            if (is_dir($userinfo['dir'])) {
                if (!in_array($userinfo['dir'], $user_paths)) {
                    array_push($user_paths, $userinfo['dir']);
                }
            }
        }
        // case  find home path from document_root ( /home/amarin/public_html => /home/amarin )
        if ($posix_user_path == '') {
            $paths = preg_split("/\//", $document_root);
            $loop_dim = count($paths);
            for ($i = 0; $i < $loop_dim; $i++) {
                $test_path = join('/', $paths);
                if (is_dir($test_path)) {
                    if (!in_array($test_path, $user_paths)) {
                        array_push($user_paths, $test_path);
                    }
                }
                array_pop($paths);
            }
        }
    }

    foreach ($user_paths as $user_path_var) {
        if (is_file($user_path_var)) {
            continue;
        }
        if ($user_path_var == $document_root) {
            continue;
        }
        if (preg_match("/(^\/tmp)|(\/tmp$)/", $user_path_var)) {
            continue;
        }
        if (is_writable($user_path_var)) {
            $user_path = $user_path_var;
            break;
        }
    }

    $userpathinfo['homepath'] = $user_path;
    $userpathinfo['publicpath'] = $document_root;

    return $userpathinfo;
}

function get_open_basedir_paths(): array
{
    $open_basedir_paths = [];
    if (function_exists('ini_get')) {
        $open_basedir_str =  ini_get('open_basedir');
        $open_basedir_str = trim($open_basedir_str);
        if ($open_basedir_str != '') {
            $open_basedir_str = strtolower($open_basedir_str);
            $open_basedir_paths = explode(":", $open_basedir_str);
        }
    }
    return $open_basedir_paths;
}

function doDownload($type, $url, $sink, $rvlicensecode, $debug_log, $getversionurl)
{
    $response = [
        'message' => '',
        'success' => false
    ];

    $client = new Client([
        'curl'            => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false],
        'allow_redirects' => false,
        'cookies'         => true,
        'verify'          => false
    ]);

    $headers = [
        /// Domain user
        'RV-Referer' => get_current_domain(),
        /// บอกให้ทำ GATracking
        'Allow-GATracking' => 'true',
        /// RVGlobalsoft Product
        'RV-Product' => 'rvsitebuilder',
        /// ทำ License-Code ดูตาม function เลย
        'RV-License-Code' => $rvlicensecode,
        /// Browser ของ user
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36',
        /// ส่ง IP ของ user ให้ด้วย เพราะที่ server เราจะเห็นแค่ IP ของ server ไม่ใช่ IP ของผู้ใช้งานจริงๆ
        'RV-Forword-REMOTE-ADDR' => get_client_ip()
    ];

    print_debug_log($debug_log, 'Header request to server ' . json_encode($headers));

    $res = $client->request(
        $type,
        $url,
        [
            'headers'   => $headers,
            'sink'      => $sink
        ]
    );

    print_debug_log($debug_log, 'Server Response Status ' . $res->getStatusCode());
    print_debug_log($debug_log, 'Server Response Header ' . json_encode((array) $res->getHeaders()));

    if ($res->getHeaderLine('RV-DOWNLOAD-RESPONSE') != 'ok') {
        $response['message'] = $res->getHeaderLine('RV-DOWNLOAD-RESPONSE-MESSAGE');
    } else if (!file_exists($sink)) {
        $response['message'] = 'Download Error ,file ' . $sink . ' not exists';
    } else {
        $response['success'] = true;
    }

    //sha_512 verify
    if (isset($getversionurl)) {
        $arr_request = $client->request('GET', $getversionurl);
        $verify_arr = json_decode($arr_request->getBody(), true);
        $downloadurl = $verify_arr['rvsitebuilderinstaller/install']['sha512'];
        $file_sha512 = hash_file('sha512', $sink);
        if ($file_sha512 != $downloadurl) {
            $response['success'] = false;
            $response['message'] = 'Download error , File validation incorret.';
        }
    }

    return $response;
}

function get_client_ip(): string
{
    $ipaddress = '';
    $ipaddress = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_FORWARDED'] ?? $_SERVER['HTTP_FORWARDED'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    return $ipaddress;
}

function doExtract($file, $path, $debug_log)
{
    $response['success'] = false;
    $response['message'] = '';
    try {
        $tar = new Tar();
        $tar->open($file);
        $tar->extract($path);
        $response['success'] = true;
        return $response;
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        return $response;
    }
}

function print_debug_log($debug, $msg = '')
{
    if ($debug == true) {
        file_put_contents(
            dirname(__FILE__) . '/install_log.txt',
            'DEBUG LOG >> ' . $msg . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    return true;
}

function print_install_log($installlog, $msg = '')
{
    if ($installlog == true) {
        file_put_contents(
            dirname(__FILE__) . '/install_log.txt',
            'INSTALL LOG >> ' . $msg . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    return true;
}


function rv_apache_request_headers(): array
{
    $arh = array();
    $rx_http = '/\AHTTP_/';
    foreach ($_SERVER as $key => $val) {
        if (preg_match($rx_http, $key)) {
            $arh_key = preg_replace($rx_http, '', $key);
            $rx_matches = array();
            // do some nasty string manipulations to restore the original letter case
            // this should work in most cases
            $rx_matches = explode('_', $arh_key);
            if (count($rx_matches) > 0 and strlen($arh_key) > 2) {
                foreach ($rx_matches as $ak_key => $ak_val) {
                    $rx_matches[$ak_key] = ucfirst($ak_val);
                    $arh_key = implode('-', $rx_matches);
                }
            }
            $arh[$arh_key] = $val;
        }
    }
    return ($arh);
}
