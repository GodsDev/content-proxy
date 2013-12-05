<?php

/*
 * Content proxy pro http://free.gods.cz/textovky/
 * v.1.1, 131129, rejthar@gods.cz
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

$useragent = $_SERVER["HTTP_USER_AGENT"]; //@TODO - přidat ochranu proti spamování a dát default hodnotu
$template = 'touch'; //@TODO - zdynamičnit a POSTnout
$incomingURL = $_SERVER["REQUEST_URI"];
$url = str_replace($sourceBaseUrl, $targetBaseURL, $incomingURL);
$headers = apache_request_headers();
$referer = $_SERVER["HTTP_REFERER"];
/*
$excludedHeaders = array (
    'Connection',
    'User-agent',
    'Accept-Encoding',
    'Host',
    'Accept',
);
*/

//Let's retrieve the content
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
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
    /*if(!in_array($kex, $excludedHeaders)){
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
        $headerArray[$tempArray[0]] = is_null($tempArray[1]) ? $tempArray[0] : $tempArray[1];
    }
}

//Outputs the retrieved file
outputHeaderIfSet('Last-Modified', $headerArray);
outputHeaderIfSet('ETag', $headerArray);
outputHeaderIfSet('Accept-Ranges', $headerArray);
outputHeaderIfSet('Content-Length', $headerArray);
outputHeaderIfSet('Vary', $headerArray);
outputHeaderIfSet('Content-Type', $headerArray);

die($body);

function outputHeaderIfSet($headerName, $headerArray) {
    if (isset($headerArray[$headerName])) {
        header("{$headerName}: {$headerArray['$headerName']}");
    }
}
