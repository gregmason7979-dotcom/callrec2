<?php
include('includes/config.php');

if(isset($_POST['action']) && $_POST['action']=='Login')
{
	$model->admin_login();
}
if(isset($_POST['action']) && $_POST['action']=='getdirectory')
{
	$model->get_directories($_POST['user'],$_POST['directory']);
}
?>