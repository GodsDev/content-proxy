<?php

/**
 * global variables for overall behavior control
 * 
 */

/** set to true when in the test environment, set to false otherwise */
$test_env = false;

/** for CLI usage (used in Comp::file_content) */
//$static_project_path = 'D:\prace\gods\repo1\ms\knihy\src';

//$allow_google_analytics = false;

//if set to true, redirect (aka production) urls will be used
//$allow_redirects = false;

//$production_home = 'absolute url';

/* include server's document root (example: /project) */
//$project_deployment_location = 'relative url';

/* content proxy timeout */
$curlTimeout = 10; //[sec]

/* targetBaseURL including trailing slash - target MUST by a DIRECTORY (not a web page) */
$targetBaseURL = 'http://free.gods.cz/textovky/';
