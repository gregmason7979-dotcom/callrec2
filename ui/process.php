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
if (isset($_POST['action']) && $_POST['action'] === 'sync_index')
{
        header('Content-Type: application/json');

        if (!isset($_SESSION['login'])) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'message' => 'Not authorised'));
                exit;
        }

        $stats = $model->runRecordingIndexer();

        echo json_encode(array('success' => true, 'stats' => $stats));
        exit;
}
?>