<?php
error_reporting(E_ALL);
session_start();
/* ini_set('display_errors', '1'); */
date_default_timezone_set('America/New_York');
define('username','sa');
define('password','$olidus');
define('host','172.30.12.36');
define('dbname','nextccdb');
define('adminusername','Supervisor');
define('adminpassword','WAF1234');
define('maindirectory','e:\SecRecord\-1');

define('recording_base_url','http://172.30.12.36/SeCRecord');

include('functions.php');


$model	=	new model();

?>