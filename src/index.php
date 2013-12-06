<?php

/*
 * Content proxy pro http://free.gods.cz/textovky/
 * v.1.3, 131202, rejthar@gods.cz (fix slukova@mobile-partnership.cz )
 * 
 * Podporuje POST
 * Všechny původní headers předává jako X-orig-*
 * 
 * Nenásleduje redirekty, tak URL na cílovém serveru musí být relativní (nezačínat /) a úplné
 */

require_once 'conf/env_config.php';
//require_once 'lib/backyard/deploy/backyard/functions_http.php';

$sourceBaseUrl = dirname($_SERVER["PHP_SELF"]) . '/';

//O2 - header & footer - přidávat na straně, která generuje komplet stránky
//give UA & auth - get content body

$useragent = (isset($_SERVER["HTTP_USER_AGENT"]))?($_SERVER["HTTP_USER_AGENT"]):false;
//template předáván v GET parametru tw {xt=touch; xe=enhanced; xs=simple; ??=wml}//$template = 'touch'; //@TODO - zdynamičnit a POSTnout
$incomingURL = $_SERVER["REQUEST_URI"];
$url = str_replace($sourceBaseUrl, $targetBaseURL, $incomingURL);
if(isset($_GET['tbu']) && $_GET['tbu']) {
    $url=$targetBaseURL.$_GET['tbu'];
}

$headers = apache_request_headers();
$referer = (isset($_SERVER["HTTP_REFERER"]))?($_SERVER["HTTP_REFERER"]):false;
/*
$excludedHeaders = array (
    'Connection',
    'User-agent',
    'Accept-Encoding',
    'Host',
    'Accept',
);
*/
//list of headers that are directly output to the client from the proxied website
$directOutputHeaders = array (
    'Last-Modified',
    'ETag',
    'Accept-Ranges',
    'Content-Length',
    'Vary',
    'Content-Type',
);

//Let's retrieve the content
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
if($useragent)curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $curlTimeout);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

/* cannot be activated when in safe_mode or an open_basedir is set
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
 */

$headersToBeSent = array();
/*
//spoof Telefonica auth for testing purposes
if(!isset($headers['X_NOKIA_MSISDN'])){
    $headersToBeSent[]=("X_NOKIA_MSISDN: 420999999999");
} else {
    $headersToBeSent[]=("X_NOKIA_MSISDN: {$headers['X_NOKIA_MSISDN']}");
}
*/
foreach ($headers as $key => $value) {
    /*if(!in_array($key, $excludedHeaders)){
        $headersToBeSent[]=("{$key}: {$value}");
    } else {*/
        $headersToBeSent[]=("X-orig-{$key}: {$value}");
    //}
}

if($headersToBeSent){
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headersToBeSent);
}

if ($_POST) {
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST, '', '&'));
}

/*
 * TBD - cookies set
 * 
 * 3)Grab from the header cookies like this:
  preg_match_all('|Set-Cookie: (.*);|U', $content, $results);
  $cookies = implode(';', $results[1]);
  4)Set them using curl_setopt($ch, CURLOPT_COOKIE,  $cookies);
 * 
 * * TBD - cookies get
 * CURLOPT_COOKIE	 The contents of the "Cookie: " header to be used in the HTTP request. Note that multiple cookies are separated with a semicolon followed by a space (e.g., "fruit=apple; colour=red")
 */

if($referer) curl_setopt($ch, CURLOPT_REFERER, $referer);

curl_setopt($ch, CURLOPT_VERBOSE, 1);
curl_setopt($ch, CURLOPT_HEADER, 1);

$data = curl_exec($ch);
if (!$data) {
    error_log("Curl error: " . curl_error($ch) . " on {$url}");
}

//expects CURLOPT_RETURNTRANSFER, CURLOPT_VERBOSE, CURLOPT_HEADER and doesn't work through proxy  
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($data, 0, $header_size);
$body = substr($data, $header_size);

curl_close($ch);

$headerArray0 = explode("\n", $header);
$headerArray = array();
foreach ($headerArray0 as $key => $value) {
    $tempArray = explode(": ", $value, 2);
    if (trim($tempArray[0]) != '') {
        if(isset($tempArray[1])){
            $headerArray[$tempArray[0]] = is_null($tempArray[1]) ? $tempArray[0] : $tempArray[1];
        } else {
            $headerArray[$tempArray[0]] = $tempArray[0];
        }
    }
}

//Outputs the retrieved file
foreach ($directOutputHeaders as $key => $value) {
    outputHeaderIfSet($value, $headerArray);
}

die($body);

function outputHeaderIfSet($headerName, $headerArray) {
    if (isset($headerArray[$headerName])) {
        header("{$headerName}: {$headerArray[$headerName]}");
    }
}