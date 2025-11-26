<?php
$serverName = "gnt-sec"; //serverName\instanceName
$connectionInfo = array( "Database"=>"nextccdb", "UID"=>"sa", "PWD"=>'$olidus');
$conn = sqlsrv_connect( $serverName, $connectionInfo);

if( $conn ) {
     echo "Connection established.<br />";
}else{
     echo "Connection could not be established.<br />";
     die( print_r( sqlsrv_errors(), true));
}
?>