<?php
if(isset($_REQUEST['download']))
{
    $requestedName = isset($_REQUEST['filename']) ? $_REQUEST['filename'] : '';
    $fileName = $requestedName !== '' ? basename($requestedName) : 'recording.mp3';
    $downloadToken = $_REQUEST['download'];

    if (filter_var($downloadToken, FILTER_VALIDATE_URL)) {
        header('Location: ' . $downloadToken);
        exit;
    }

    $basePath = rtrim(maindirectory, '/\\');
    $realBase = realpath($basePath);

    if ($realBase === false) {
        http_response_code(500);
        exit('Recording directory unavailable.');
    }

    $relative = str_replace('\\', '/', $downloadToken);
    $segments = array_filter(explode('/', $relative), 'strlen');
    $targetPath = $basePath;

    foreach ($segments as $segment) {
        if ($segment === '.' || $segment === '..') {
            continue;
        }

        $targetPath .= DIRECTORY_SEPARATOR . $segment;
    }

    $realPath = realpath($targetPath);

    if ($realPath === false || strpos($realPath, $realBase) !== 0 || !is_file($realPath)) {
        http_response_code(404);
        exit('Recording not found.');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Transfer-Encoding: Binary');
    header('Content-disposition: attachment; filename="'.$fileName.'"');
    readfile($realPath);
    exit;
}
?>
<?php include('includes/header.php'); ?>
<?php if(!isset($_SESSION['login'])){
        $model->redirect('login.php');
} ?>
<script src="jquery-1.10.2.js"></script>
<script src="ui/1.11.2/jquery-ui.js"></script>
        <script>
function DHTMLSound(surl,val) {
$('.dummyspan').hide();
$('#dummyspan_'+val).show();
  document.getElementById("dummyspan_"+val+"").innerHTML=
        "<audio controls><source src='"+surl+"' hidden='false' autostart='false' loop='false' ></audio>";

}
$(document).ready(function(){
        function getContainers(agentKey) {
                return {
                        detail: $('#detail_'+agentKey),
                        target: $('#show_'+agentKey)
                };
        }

        function fetchRecordings(agentKey, directory, scope, page, shouldScroll) {
                var containers = getContainers(agentKey);
                var $detailRow = containers.detail;
                var $targetContainer = containers.target;
                var scrollAfterLoad = (typeof shouldScroll === 'boolean') ? shouldScroll : false;

                if (!$detailRow.length || !$targetContainer.length) {
                        return;
                }

                $targetContainer
                        .addClass('showfull--visible')
                        .html('<div class="showfull__loading">Loading recordings...</div>');

                $.ajax({
                        type: 'POST',
                        url: 'process.php',
                        data: {
                                action: 'getdirectory',
                                user: agentKey,
                                directory: directory,
                                scope: scope || 'recent',
                                page: page || 1
                        },
                        success: function(response) {
                                $targetContainer.html(response).addClass('showfull--visible');
                                $detailRow.addClass('detail-row--visible');

                                if (scrollAfterLoad) {
                                        scrollToRecordingList($detailRow, $targetContainer);
                                }
                        },
                        error: function() {
                                $targetContainer.html('<div class="showfull__error">Unable to load recordings.</div>').addClass('showfull--visible');
                                $detailRow.addClass('detail-row--visible');

                                if (scrollAfterLoad) {
                                        scrollToRecordingList($detailRow, $targetContainer);
                                }
                        }
                });
        }

        function scrollToRecordingList($detailRow, $targetContainer) {
                var $scrollTarget = $detailRow.length ? $detailRow : $targetContainer;

                if (!$scrollTarget.length) {
                        return;
                }

                var offsetTop = $scrollTarget.offset().top;

                if (typeof offsetTop !== 'number' || isNaN(offsetTop)) {
                        return;
                }

                $('html, body').stop(true).animate({
                        scrollTop: Math.max(offsetTop - 120, 0)
                }, 300);
        }

        $('.click').on('click', function(event){
                event.preventDefault();

                var $trigger = $(this);
                var agentKey = $trigger.attr('rel');
                var directory = $trigger.attr('subd');

                if (!agentKey || !directory) {
                        return;
                }

                $('.table_row--agent').removeClass('table_row--agent-active');
                $trigger.closest('.table_row').addClass('table_row--agent-active');

                $('.detail-row').removeClass('detail-row--visible');
                $('.showfull').removeClass('showfull--visible').html('');

                fetchRecordings(agentKey, directory, 'recent', 1, true);
        });

        $('.app-main').on('click', '.recording-panel__toggle', function(event){
                event.preventDefault();

                var $button = $(this);
                var $panel = $button.closest('.recording-panel');
                var scope = $button.data('scope') || ($panel.length ? $panel.data('scope') : 'recent');
                var agentKey = $button.data('agent') || ($panel.length ? $panel.data('agent') : '');
                var directory = $button.data('directory') || ($panel.length ? $panel.data('directory') : '');

                if (!agentKey || !directory) {
                        return;
                }

                fetchRecordings(agentKey, directory, scope, 1, true);
        });

        $('.app-main').on('click', '.pagination__btn', function(event){
                event.preventDefault();

                var $button = $(this);
                var $panel = $button.closest('.recording-panel');
                var page = parseInt($button.data('page'), 10) || 1;
                var scope = $button.data('scope') || ($panel.length ? $panel.data('scope') : 'recent');
                var agentKey = $button.data('agent') || ($panel.length ? $panel.data('agent') : '');
                var directory = $button.data('directory') || ($panel.length ? $panel.data('directory') : '');

                if (!agentKey || !directory) {
                        return;
                }

                fetchRecordings(agentKey, directory, scope, page, true);
        });
});
        </script>

  	

<div class="outerlayer">
                      <div class="outerlayer1">
                             <div class="header_botm">
                                   <div class="header_btm_lft">
                                    <h2><a class="header-link" href="index.php"><span class="header-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="M16 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm0 2.5a4.5 4.5 0 0 0-4.5 4.5.75.75 0 0 0 .75.75h7.5a.75.75 0 0 0 .75-.75A4.5 4.5 0 0 0 8 13.5Zm8 0a4.49 4.49 0 0 0-2.73.9 5.72 5.72 0 0 1 1.98 3.6.75.75 0 0 0 .75.75h5a.75.75 0 0 0 .75-.75A4.5 4.5 0 0 0 16 13.5Z"/></svg></span><span>Show all Agents</span></a></h2>
                                   </div>
                                   <div class="header_btm_cntr">
                                  <h2><a class="header-link" href="search.php"><span class="header-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="m20.29 19.58-3.89-3.89a7.25 7.25 0 1 0-1.06 1.06l3.89 3.89a.75.75 0 1 0 1.06-1.06ZM6.75 11a4.25 4.25 0 1 1 4.25 4.25A4.25 4.25 0 0 1 6.75 11Z"/></svg></span><span>Search</span></a></h2>
                                   </div>
                                   <div class="header_btm_cntr">
                                  <h2 class="header-note"><span class="header-note__icon" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm0 4a1.25 1.25 0 1 1-1.25 1.25A1.25 1.25 0 0 1 12 6Zm1.75 10.25a.75.75 0 0 1-1.5 0V12a.75.75 0 0 0-1.5 0 2.25 2.25 0 0 0 0 4.5.75.75 0 0 1 0 1.5 3.75 3.75 0 0 1 0-7.5 2.25 2.25 0 0 1 2.25 2.25Z"/></svg></span>Select agent name to see recordings</h2>
                                   </div>
                                 </div>
                                 <div class="content">
<?php 
        $directory = rtrim(maindirectory, '/\\') . DIRECTORY_SEPARATOR;
        $selectedAgentFilter = (isset($_POST['agent']) && is_string($_POST['agent'])) ? $_POST['agent'] : '';
        $actionType = (isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';

        if($actionType === '')
	{
	$rosterEntries = $model->getAgentRoster();
?>
        <table class="record-table record-table--roster">
          <colgroup>
            <col class="record-col record-col--agent">
            <col class="record-col record-col--other">
            <col class="record-col record-col--datetime">
            <col class="record-col record-col--group">
            <col class="record-col record-col--call">
            <col class="record-col record-col--description">
          </colgroup>
          <thead>
                                           <tr class="table_top">
                                                        <th width="300">Agent Name</th>
                                                <th width="150">Other Parties</th>
                                                <th width="200">Date/Time</th>
                                                        <th>Service Group</th>
                                                        <th>Call ID</th>
                                                        <th>Description</th>
                                           </tr>
          </thead>
          <tbody>
<?php
        if (empty($rosterEntries)) {
?>
        <tr class="table_row table_row--empty">
          <td colspan="6" class="table_cell--empty">
            <div class="empty-roster">
              <p class="empty-roster__title">No agents found.</p>
              <p class="empty-roster__hint">Recordings will appear here once directories are available.</p>
            </div>
          </td>
        </tr>
<?php
        } else {
                foreach ($rosterEntries as $entry) {
                        $agentDomId = htmlspecialchars($entry['domId'], ENT_QUOTES, 'UTF-8');
                        $directoryAttr = htmlspecialchars($entry['directory'], ENT_QUOTES, 'UTF-8');
                        $agentLabelEsc = htmlspecialchars($entry['displayName'], ENT_QUOTES, 'UTF-8');
?>
        <tr class="table_row table_row--agent">
          <td class="table_cell--name">
            <a href="javascript:void(0)" class="click table-link" role="button" rel="<?php echo $agentDomId; ?>" subd="<?php echo $directoryAttr; ?>" data-agent="<?php echo $agentDomId; ?>" data-directory="<?php echo $directoryAttr; ?>" aria-controls="detail_<?php echo $agentDomId; ?>">
              <span class="table_content__primary">
                <span class="icon-chip icon-chip--chevron" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="m10.5 7.5 5 4.5-5 4.5a.75.75 0 0 1-1-.06.75.75 0 0 1 .06-1l3.63-3.27L9.56 8.56a.75.75 0 0 1 1-1.06Z"/></svg></span>
                <span class="icon-chip icon-chip--agent" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="M12 13.25a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 1.5c-3.51 0-6.5 1.92-6.5 4.5a.75.75 0 0 0 .75.75h11.5a.75.75 0 0 0 .75-.75c0-2.58-2.99-4.5-6.5-4.5Z"/></svg></span>
                <span class="table-link__text">
                  <span class="table-link__label"><?php echo $agentLabelEsc; ?></span>
                  <span class="table-link__hint">View recordings</span>
                </span>
              </span>
              <span class="table-link__chevron" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            </a>
          </td>
          <td class="table_cell--ghost" aria-hidden="true"></td>
          <td class="table_cell--ghost" aria-hidden="true"></td>
          <td class="table_cell--ghost" aria-hidden="true"></td>
          <td class="table_cell--ghost" aria-hidden="true"></td>
          <td class="table_cell--ghost" aria-hidden="true"></td>
        </tr>
        <tr class="detail-row" id="detail_<?php echo $agentDomId; ?>">
          <td colspan="6">
            <div id="show_<?php echo $agentDomId; ?>" class="showfull" aria-live="polite"></div>
          </td>
        </tr>
<?php
                }
        }
?>
          </tbody>
        </table>
<?php
}else{
	$i=0;
	$list_full = scandir($directory); 
	?>
    <table class="record-table">
					   <tr class="table_top">
					   <th width="300">Agent Name</th>
					        <th width="150">Other Parties</th>
					        <th width="200">Date/Time</th>
							<th>Service Group</th>
							<th>Call ID</th>
							<th>Description</th>
					   </tr>
	<?php
	foreach($list_full as $value_full)
	{
		if (!in_array($value_full,array(".",".."))) 
	 {
		 if ($selectedAgentFilter !== '' && $selectedAgentFilter !== $value_full) {
			 continue;
		 }
		 $select	=	"select first_name,last_name from dbo.cc_user where id='".ltrim($value_full,'0')."'";
		$query	=	sqlsrv_query(connect,$select);
		if($query==true){
		$result	=	sqlsrv_fetch_array($query,SQLSRV_FETCH_ASSOC);
	?>
                                               <tr class="table_row table_row--agent"><td colspan="6" class="table_content">
                                                 <span class="icon-chip icon-chip--chevron" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="m10.5 7.5 5 4.5-5 4.5a.75.75 0 0 1-1-.06.75.75 0 0 1 .06-1l3.63-3.27L9.56 8.56a.75.75 0 0 1 1-1.06Z"/></svg></span>
                                                 <span class="icon-chip icon-chip--agent" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="M12 13.25a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 1.5c-3.51 0-6.5 1.92-6.5 4.5a.75.75 0 0 0 .75.75h11.5a.75.75 0 0 0 .75-.75c0-2.58-2.99-4.5-6.5-4.5Z"/></svg></span>
                                                 <span class="table-link">
                                                   <span class="table-link__label"><?php echo $result['first_name'] ?> <?php echo $result['last_name']; ?></span>
                                                   <span class="table-link__hint">Filtered results</span>
                                                 </span>
                                                 <span class="table-link__chevron" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                               </td>
                                          </tr>
		<?php 
				$subdirectory	=	$directory.$value_full;
				if(is_dir($subdirectory))
				{
					 $list = $model->Sort_Directory_Files_By_Last_Modified($subdirectory);
					unset($new_array);
					$new_array	=	array();
					foreach($list[0] as $value)
					 {
						if (!in_array($value['file'],array(".",".."))) {
						$play	=	$directory.$value_full.DIRECTORY_SEPARATOR.$value['file'];
						if(is_dir($play))
						{
						unset($unew_array);
						$unew_array	=	array();
							$ulist = $model->Sort_Directory_Files_By_Last_Modified($play);
							
							foreach($ulist[0] as $uval)
							{
							if(is_file($play.DIRECTORY_SEPARATOR.$uval['file']))
						{
						$uexplode	=	explode('$',$uval['file']);
						$uservicegroup	=	$uexplode[0];
						$udatetime		=	$uexplode[1];
						$udescription	=	$uexplode[3];
						$uotherparty	=	$uexplode[2];
						$ucallid		=	$uexplode[4];
						$ucall			=	explode('.',$ucallid);
						if($_POST['name']!=''){
							if(in_array($_POST['name'],array($udescription))){ 
								$unew_array[]	=	$uval['file'];
							}
						}elseif($_POST['date']!='' && $_POST['enddate']!=''){
						$paymentDate = $udatetime;
						$paymentDate=date('Y-m-d', strtotime($paymentDate));;
						$contractDateBegin = $_POST['date'];
						$contractDateEnd = $_POST['enddate'];
						if ((strtotime($paymentDate) >= strtotime($contractDateBegin)) && (strtotime($paymentDate) <= strtotime($contractDateEnd)))
							{
								$unew_array[]	=	$uval['file'];
							}
						}elseif($_POST['other_party']!=''){
							if(in_array($_POST['other_party'],array($uotherparty))){
								$unew_array[]	=	$uval['file'];
							}
						}elseif($_POST['service_group']!=''){
							if(in_array($_POST['service_group'],array($uservicegroup))){
								$unew_array[]	=	$uval['file'];
							}
						}elseif($_POST['call_id']!=''){
							if(in_array($_POST['call_id'],array($ucall[0]))){
								$unew_array[]	=	$uval['file'];
						}
						}
					 }
							}
						
						if(is_array($unew_array))
						{
						
						foreach($unew_array as $uuval)
						{
						$i++;
						$uuplay	=	$directory.$value_full.DIRECTORY_SEPARATOR.$value['file'].DIRECTORY_SEPARATOR.$uuval;
						if(is_file($uuplay)){
						$uuexplode	=	explode('$',$uuval);
						$uuservicegroup	=	$uuexplode[0];
						$uudatetime		=	$uuexplode[1];
						$uudescription	=	$uuexplode[3];
						$uuotherparty	=	$uuexplode[2];
						$uucallid		=	$uuexplode[4];
						$uucall			=	explode('.',$uucallid);
                                                ?>
                                                <?php echo $model->renderRecordingRow(
                                                        $i,
                                                        array($value_full, $value['file'], $uuval),
                                                        $uuval,
                                                        $uuotherparty,
                                                        $uudatetime,
						        $uuservicegroup,
						        $uucall[0],
						        $uudescription
						); ?>
					  <?php }
						}
						}
						}
						?>
						<?php
						if(is_file($play))
						{
						$explode	=	explode('$',$value['file']);
						$servicegroup	=	$explode[0];
						$datetime		=	$explode[1];
						$description	=	$explode[3];
						$otherparty		=	$explode[2];
						$callid			=	$explode[4];
						$call			=	explode('.',$callid);
						if($_POST['name']!=''){
							if(in_array($_POST['name'],array($description))){ 
								$new_array[]	=	$value;
							}
						}elseif($_POST['date']!='' && $_POST['enddate']!=''){
						$paymentDate = $datetime;
						$paymentDate=date('Y-m-d', strtotime($paymentDate));;
						$contractDateBegin = $_POST['date'];
						$contractDateEnd = $_POST['enddate'];
						if ((strtotime($paymentDate) >= strtotime($contractDateBegin)) && (strtotime($paymentDate) <= strtotime($contractDateEnd)))
							{
								$new_array[]	=	$value['file'];
							}
						}elseif($_POST['other_party']!=''){
							if(in_array($_POST['other_party'],array($otherparty))){
								$new_array[]	=	$value['file'];
							}
						}elseif($_POST['service_group']!=''){
							if(in_array($_POST['service_group'],array($servicegroup))){
								$new_array[]	=	$value['file'];
							}
						}elseif($_POST['call_id']!=''){
							if(in_array($_POST['call_id'],array($call[0]))){
								$new_array[]	=	$value['file'];
						}
						}
					 }
					 }
					}
					if(is_array($new_array))
					{
					
						foreach($new_array as $val)
						{
						$i++;
						$play	=	$directory.$value_full.DIRECTORY_SEPARATOR.$val;
						$explode	=	explode('$',$val);
						$servicegroup	=	$explode[0];
						$datetime		=	$explode[1];
						$description	=	$explode[3];
						$otherparty		=	$explode[2];
						$callid			=	$explode[4];
						$call			=	explode('.',$callid);
                                                ?>
                                                <?php echo $model->renderRecordingRow(
                                                        $i,
                                                        array($value_full, $val),
                                                        $val,
                                                        $otherparty,
                                                        $datetime,
						        $servicegroup,
						        $call[0],
						        $description
						); ?>
					  <?php } } }
					   } 
					   ?>
					   
					   
					   <?php } }?>
					  
					  
					  
					   
					</table>
					
					<?php } ?>
					<div class="content_end"> 
					</div>
				 </div>
			  </div>
		   </div>
		 
<?php include('includes/footer.php'); ?> 
