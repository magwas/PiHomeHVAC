<?php
/*
   _____    _   _    _
  |  __ \  (_) | |  | |
  | |__) |  _  | |__| |   ___    _ __ ___     ___
  |  ___/  | | |  __  |  / _ \  | |_  \_ \   / _ \
  | |      | | | |  | | | (_) | | | | | | | |  __/
  |_|      |_| |_|  |_|  \___/  |_| |_| |_|  \___|

     S M A R T   H E A T I N G   C O N T R O L

*************************************************************************"
* PiHome is Raspberry Pi based Central Heating Control systems. It runs *"
* from web interface and it comes with ABSOLUTELY NO WARRANTY, to the   *"
* extent permitted by applicable law. I take no responsibility for any  *"
* loss or damage to you or your property.                               *"
* DO NOT MAKE ANY CHANGES TO YOUR HEATING SYSTEM UNTILL UNLESS YOU KNOW *"
* WHAT YOU ARE DOING                                                    *"
*************************************************************************"
*/

require_once(__DIR__.'/st_inc/session.php');
confirm_logged_in();
require_once(__DIR__.'/st_inc/connection.php');
require_once(__DIR__.'/st_inc/functions.php');
?>
<div class="panel panel-primary">
        <div class="panel-heading">
                <div class="Light"><i class="fa fa-home fa-fw"></i> <?php echo $lang['home']; ?>
                        <div class="pull-right">
                                <div class="btn-group"><?php echo date("H:i"); ?>
                                </div>
                        </div>
                </div>
        </div>
        <!-- /.panel-heading -->
        <div class="panel-body">
                <a style="color: #777; cursor: pointer; text-decoration: none;" data-toggle="collapse" data-parent="#accordion" href="#collapseone">
                <button class="btn btn-default btn-circle btn-xxl mainbtn animated fadeIn">
                <h3><small><?php echo $lang['one_touch']; ?></small></h3>
                <h3 class="degre" style="margin-top:0px;"><i class="fa fa-bullseye fa-2x"></i></h3>
                <h3 class="status"></h3>
                </button></a>
                <?php

		//following two variable set to 0 on start for array index.
		$boost_index = '0';
		$override_index = '0';

		//following variable set to current day of the week.
		$dow = idate('w');

		//Mode 0 is EU Boiler Mode, Mode 1 is US HVAC Mode
		$system_controller_mode = settings($conn, 'mode');

		//GET BOILER DATA AND FAIL ZONES IF BOILER COMMS TIMEOUT
		//query to get last boiler operation time and hysteresis time
		$query = "SELECT * FROM system_controller LIMIT 1";
		$result = $conn->query($query);
		$row = mysqli_fetch_array($result);
		$bcount=$result->num_rows;
		$fired_status = $row['fired_status'];
		$system_controller_name = $row['name'];
		$system_controller_max_operation_time = $row['max_operation_time'];
		$system_controller_hysteresis_time = $row['hysteresis_time'];
		$sc_mode  = $row['sc_mode'];

		//Get data from nodes table
		$query = "SELECT * FROM nodes WHERE id = {$row['node_id']} AND status IS NOT NULL LIMIT 1";
		$result = $conn->query($query);
		$system_controller_node = mysqli_fetch_array($result);
		$system_controller_id = $system_controller_node['node_id'];
		$system_controller_seen = $system_controller_node['last_seen'];
		$system_controller_notice = $system_controller_node['notice_interval'];

		//Check Boiler Fault
		$system_controller_fault = 0;
		if($system_controller_notice > 0){
			$now=strtotime(date('Y-m-d H:i:s'));
		  	$system_controller_seen_time = strtotime($system_controller_seen);
		  	if ($system_controller_seen_time  < ($now - ($system_controller_notice*60))){
    				$system_controller_fault = 1;
  			}
		}

                //if in HVAC mode display the mode selector
                if ($system_controller_mode == 1) {
                        switch ($sc_mode) {
                                case 0:
                                        $current_sc_mode = $lang['mode_off'];
                                        break;
                                case 1:
                                        $current_sc_mode = $lang['mode_timer'];
                                        break;
                                case 2:
                                        $current_sc_mode = $lang['mode_auto'];
                                        break;
                                case 3:
                                        $current_sc_mode = $lang['mode_fan'];
                                        break;
                                case 4:
                                        $current_sc_mode = $lang['mode_heat'];
                                        break;
                                case 5:
                                        $current_sc_mode = $lang['mode_cool'];
                                        break;
                                default:
                                        $current_sc_mode = $lang['mode_off'];
			}
           	} else {
                        switch ($sc_mode) {
                                case 0:
                                        $current_sc_mode = $lang['mode_off'];
                                        break;
                                case 1:
                                        $current_sc_mode = $lang['mode_timer'];
                                        break;
                                case 2:
                                        $current_sc_mode = $lang['mode_ce'];
                                        break;
                                case 3:
                                        $current_sc_mode = $lang['mode_hw'];
                                        break;
                                case 4:
                                        $current_sc_mode = $lang['mode_both'];
                                        break;
                                default:
                                        $current_sc_mode = $lang['mode_off'];
			}

                }
	        echo '<a href="javascript:active_sc_mode();">
                <button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
                <h3 class="buttontop"><small>'.$lang['mode'].'</small></h3>
                <h3 class="degre" >'.$current_sc_mode.'</h3>
                <h3 class="status"></small></h3>
            	</button></a>';

		//loop through zones
		$query = "SELECT zone.*, zone_type.type, zone_type.category FROM `zone`, `zone_type`  WHERE (`zone_type`.id = `type_id`) AND `zone`.`purge` = 0 AND `category` <> 2 order by index_id asc;";
		$results = $conn->query($query);
		while ($row = mysqli_fetch_assoc($results)) {
			$zone_id=$row['id'];
			$zone_name=$row['name'];
			$zone_type=$row['type'];
                        $zone_category=$row['category'];
			$zone_controller_type=$row['controller_type'];

                        //query to get the zone controller info
			if ($zone_category <> 3) {
	                        $query = "SELECT controller_relays.controler_id, controller_relays.controler_child_id FROM zone_controllers, controller_relays WHERE (zone_controllers.controller_relay_id = controller_relays.id) AND zone_id = '{$zone_id}' LIMIT 1;";
        	                $result = $conn->query($query);
                	        $zone_controllers = mysqli_fetch_array($result);
                        	$zone_controler_id=$zone_controllers['controler_id'];
	                        $zone_controler_child_id=$zone_controllers['controler_child_id'];
			}

			//query to get zone current state
			$query = "SELECT * FROM zone_current_state WHERE zone_id = '{$zone_id}' LIMIT 1;";
			$result = $conn->query($query);
			$zone_current_state = mysqli_fetch_array($result);
			$zone_mode = $zone_current_state['mode'];
			$zone_temp_reading = $zone_current_state['temp_reading'];
			$zone_temp_target = $zone_current_state['temp_target'];
			$zone_temp_cut_in = $zone_current_state['temp_cut_in'];
			$zone_temp_cut_out = $zone_current_state['temp_cut_out'];
			$zone_ctr_fault = $zone_current_state['controler_fault'];
			$controler_seen = $zone_current_state['controler_seen_time'];
			$zone_sensor_fault = $zone_current_state['sensor_fault'];
			$sensor_seen = $zone_current_state['sensor_seen_time'];
			$temp_reading_time= $zone_current_state['sensor_reading_time'];
			$overrun= $zone_current_state['overrun'];

			//get the sensor id
	                $query = "SELECT * FROM temperature_sensors WHERE zone_id = '{$zone_id}' LIMIT 1;";
        	        $result = $conn->query($query);
                	$sensor = mysqli_fetch_array($result);
	                $temperature_sensor_id=$sensor['sensor_id'];
                	$temperature_sensor_child_id=$sensor['sensor_child_id'];

			//get the node id
                	$query = "SELECT node_id FROM nodes WHERE id = '{$temperature_sensor_id}' LIMIT 1;";
                	$result = $conn->query($query);
                	$nodes = mysqli_fetch_array($result);
                	$zone_node_id=$nodes['node_id'];

			//query to get temperature from messages_in_view_24h table view
                        $query = "SELECT * FROM messages_in WHERE node_id = '{$zone_node_id}' AND child_id = '{$temperature_sensor_child_id}' ORDER BY id desc LIMIT 1;";
			$result = $conn->query($query);
			$sensor = mysqli_fetch_array($result);
			$zone_c = $sensor['payload'];
			//Zone Main Mode
		/*	0 - idle
			10 - fault
			20 - frost
			30 - overtemperature
			40 - holiday
			50 - nightclimate
			60 - boost
			70 - override
			80 - sheduled
			90 - away
			100 - hysteresis
			110 - Add-On 
			120 - HVAC*/

			$zone_mode_main=floor($zone_mode/10)*10;
			$zone_mode_sub=floor($zone_mode%10);

			//Zone sub mode - running/ stopped different types
		/*	0 - stopped (above cut out setpoint or not running in this mode)
			1 - heating running
			2 - stopped (within deadband)
			3 - stopped (coop start waiting for boiler)
			4 - manual operation ON
			5 - manual operation OFF 
                        6 - cooling running 
			7 - fan running*/

   			echo '<button class="btn btn-default btn-circle btn-xxl mainbtn animated fadeIn" data-href="#" data-toggle="modal" data-target="#'.$zone_type.''.$zone_id.'" data-backdrop="static" data-keyboard="false">
			<h3><small>'.$zone_name.'</small></h3>
			<h3 class="degre">'.number_format(DispTemp($conn,$zone_c),1).'&deg;</h3>
			<h3 class="status">';

                        $rval=getIndicators($conn, $zone_mode, $zone_temp_target);
                        //Left small circular icon/color status
                        echo '<small class="statuscircle"><i class="fa fa-circle fa-fw ' . $rval['status'] . '"></i></small>';
                        //Middle target temp
                        echo '<small class="statusdegree">' . $rval['target'] .'</small>';
                        //Right icon for what/why
                        echo '<small class="statuszoon"><i class="fa ' . $rval['shactive'] . ' ' . $rval['shcolor'] . ' fa-fw"></i></small>';
                        //Overrun Icon
                        if($overrun == 1) {
                            echo '<small class="statuszoon"><i class="fa ion-ios-play-outline orange fa-fw"></i></small>';
                        }
                        echo '</h3></button>';      //close out status and button

			//Zone Schedule listing model
			echo '<div class="modal fade" id="'.$zone_type.''.$zone_id.'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
							<h5 class="modal-title">'.$zone_name.'</h5>
						</div>
						<div class="modal-body">';
  							if ($system_controller_fault == '1') {
								$date_time = date('Y-m-d H:i:s');
								$datetime1 = strtotime("$date_time");
								$datetime2 = strtotime("$system_controller_seen");
								$interval  = abs($datetime2 - $datetime1);
								$ctr_minutes   = round($interval / 60);
								echo '
								<ul class="chat">
									<li class="left clearfix">
										<div class="header">
											<strong class="primary-font red">Boiler Fault!!!</strong>
											<small class="pull-right text-muted">
											<i class="fa fa-clock-o fa-fw"></i> '.secondsToWords(($ctr_minutes)*60).' ago
											</small>
											<br><br>
											<p>Node ID '.$system_controller_id.' last seen at '.$system_controller_seen.' </p>
											<p class="text-info">Heating system will resume its normal operation once this issue is fixed. </p>
										</div>
									</li>
								</ul>';

  							}elseif ($zone_ctr_fault == '1') {
								$date_time = date('Y-m-d H:i:s');
								$datetime1 = strtotime("$date_time");
								$datetime2 = strtotime("$controler_seen");
								$interval  = abs($datetime2 - $datetime1);
								$ctr_minutes   = round($interval / 60);
								echo '
								<ul class="chat">
									<li class="left clearfix">
										<div class="header">
											<strong class="primary-font red">Controller Fault!!!</strong>
											<small class="pull-right text-muted">
											<i class="fa fa-clock-o fa-fw"></i> '.secondsToWords(($ctr_minutes)*60).' ago
											</small>
											<br><br>
											<p>Controller ID '.$zone_controler_id.' last seen at '.$controler_seen.' </p>
											<p class="text-info">Heating system will resume its normal operation once this issue is fixed. </p>
										</div>
									</li>
								</ul>';
							//echo $zone_senros_txt;
							}elseif ($zone_sensor_fault == '1'){
								$date_time = date('Y-m-d H:i:s');
								$datetime1 = strtotime("$date_time");
								$datetime2 = strtotime("$sensor_seen");
								$interval  = abs($datetime2 - $datetime1);
								$sensor_minutes   = round($interval / 60);
								echo '
								<ul class="chat">
									<li class="left clearfix">
										<div class="header">
											<strong class="primary-font red">Sensor Fault!!!</strong>
											<small class="pull-right text-muted">
											<i class="fa fa-clock-o fa-fw"></i> '.secondsToWords(($sensor_minutes)*60).' ago
											</small>
											<br><br>
											<p>Sensor ID '.$zone_node_id.' last seen at '.$sensor_seen.' <br>Last Temperature reading received at '.$temp_reading_time.' </p>
											<p class="text-info"> Heating system will resume for this zone its normal operation once this issue is fixed. </p>
										</div>
									</li>
								</ul>';
							}else{
								//if temperature control active display cut in and cut out levels
								if (($zone_category <= 1) && (($zone_mode_main == 20 ) || ($zone_mode_main == 50 ) || ($zone_mode_main == 60 ) || ($zone_mode_main == 70 )||($zone_mode_main == 80 ))){
									echo '<p>Cut In Temperature : '.$zone_temp_cut_in.'&degC</p>
									<p>Cut Out Temperature : ' .$zone_temp_cut_out.'&degC</p>';
								}
								//display coop start info
								if($zone_mode_sub == 3){
									echo '<p>Coop Start Schedule - Waiting for boiler start.</p>';
								}
								$squery = "SELECT * FROM schedule_daily_time_zone_view where zone_id ='{$zone_id}' AND tz_status = 1 AND time_status = '1' AND (WeekDays & (1 << {$dow})) > 0 ORDER BY start asc";
								$sresults = $conn->query($squery);
								if (mysqli_num_rows($sresults) == 0){
									echo '<div class=\"list-group\">
									<a href="#" class="list-group-item"><i class="fa fa-exclamation-triangle red"></i>&nbsp;&nbsp;'.$lang['schedule_active_today'].' '.$zone_name.'!!! </a>
							</div>';
							} else {
								//echo '<h4>'.mysqli_num_rows($sresults).' Schedule Records found.</h4>';
								echo '<p>'.$lang['schedule_disble'].'</p>
								<br>
								<div class=\"list-group\">' ;
									while ($srow = mysqli_fetch_assoc($sresults)) {
										$shactive="orangesch_list";
										$time = strtotime(date("G:i:s"));
										$start_time = strtotime($srow['start']);
										$end_time = strtotime($srow['end']);
										if ($time >$start_time && $time <$end_time){$shactive="redsch_list";}
											//this line to pass unique argument  "?w=schedule_list&o=active&wid=" href="javascript:delete_schedule('.$srow["id"].');"
											echo '<a href="javascript:schedule_zone('.$srow['tz_id'].');" class="list-group-item">
											<div class="circle_list '. $shactive.'"> <p class="schdegree">'.number_format(DispTemp($conn,$srow['temperature']),0).'&deg;</p></div>
											<span class="label label-info sch_name"> '.$srow['sch_name'].'</span>
											<span class="pull-right text-muted sch_list"><em>'. $srow['start'].' - ' .$srow['end'].'</em></span></a>';
									}
								echo '</div>';
							}
						}
						echo '
						</div>
						<!-- /.modal-body -->
						<div class="modal-footer"><button type="button" class="btn btn-default btn-sm" data-dismiss="modal">'.$lang['close'].'</button>
						</div>
						<!-- /.modal-footer -->
					</div>
					<!-- /.modal-content -->
				</div>
				<!-- /.modal-dialog -->
			</div>
			<!-- /.modal fade -->
			';
		} // end of zones while loop

                // Temperature Sensors Pre System Controller
                $query = "SELECT temperature_sensors.name, temperature_sensors.sensor_child_id, nodes.node_id, nodes.last_seen, nodes.notice_interval FROM temperature_sensors, nodes WHERE (nodes.id = temperature_sensors.sensor_id) AND temperature_sensors.zone_id = 0 AND temperature_sensors.show_it = 1 AND temperature_sensors.pre_post = 1 order by index_id asc;";
                $results = $conn->query($query);
                while ($row = mysqli_fetch_assoc($results)) {
                        $sensor_name = $row['name'];
                        $sensor_child_id = $row['sensor_child_id'];
                        $node_id = $row['node_id'];
                        $node_seen = $row['last_seen'];
                        $node_notice = $row['notice_interval'];
                        $shcolor = "green";
                        if($node_notice > 0){
                                $now=strtotime(date('Y-m-d H:i:s'));
                                $node_seen_time = strtotime($node_seen);
                                if ($node_seen_time  < ($now - ($node_notice*60))) { $shcolor = "red"; }
                        }
                        //query to get temperature from messages_in_view_24h table view
                        $query = "SELECT * FROM messages_in WHERE node_id = '{$node_id}' AND child_id = '{$sensor_child_id}' ORDER BY id desc LIMIT 1;";
                        $result = $conn->query($query);
                        $sensor = mysqli_fetch_array($result);
                        $sensor_c = $sensor['payload'];
                        echo '<button class="btn btn-default btn-circle btn-xxl mainbtn animated fadeIn" data-backdrop="static" data-keyboard="false">
                        <h3><small>'.$sensor_name.'</small></h3>
                        <h3 class="degre">'.number_format(DispTemp($conn,$sensor_c),1).'&deg;</h3>
                        <h3 class="status">
                        <small class="statuscircle"><i class="fa fa-circle fa-fw '.$shcolor.'"></i></small>
                        </h3></button>';      //close out status and button
                }

		//BOILER BUTTON
		if ($bcount != 0) {
			//query to get last boiler statues change time
			$query = "SELECT * FROM system_controller_logs ORDER BY id desc LIMIT 1 ";
			$result = $conn->query($query);
			$system_controller_onoff = mysqli_fetch_array($result);
			$system_controller_last_off = $system_controller_onoff['stop_datetime'];

			//check if hysteresis is passed its time or not
			$hysteresis='0';
			if ($system_controller_mode == 0 && isset($system_controller_last_off)){
				$system_controller_last_off = strtotime( $system_controller_last_off );
				$system_controller_hysteresis_time = $system_controller_last_off + ($system_controller_hysteresis_time * 60);
				$now=strtotime(date('Y-m-d H:i:s'));
				if ($system_controller_hysteresis_time > $now){$hysteresis='1';}
			} else {
				$hysteresis='0';
			}

			if ($fired_status=='1'){$system_controller_colour="red";} elseif ($fired_status=='0'){$system_controller_colour="blue";}
			echo '<button class="btn btn-default btn-circle btn-xxl mainbtn animated fadeIn" data-toggle="modal" href="#boiler" data-backdrop="static" data-keyboard="false">
			<h3 class="text-info"><small>'.$system_controller_name.'</small></h3>';
			if($zone_mode == 127 || $zone_mode == 87 || $zone_mode == 67){
				echo '<h3 class="degre" ><img src="images/hvac_fan_30.png" border="0"></h3>';
			} else {
				echo '<h3 class="degre" ><i class="'.$rval['scactive'].' fa-1x '.$rval['sccolor'].'"></i></h3>';
			}
			if($system_controller_fault=='1') {echo'<h3 class="status"><small class="statusdegree"></small><small style="margin-left: 70px;" class="statuszoon"><i class="fa ion-android-cancel fa-1x red"></i> </small>';}
			elseif($hysteresis=='1') {echo'<h3 class="status"><small class="statusdegree"></small><small style="margin-left: 70px;" class="statuszoon"><i class="fa fa-hourglass fa-1x orange"></i> </small>';}
			else { echo'<h3 class="status"><small class="statusdegree"></small><small style="margin-left: 48px;" class="statuszoon"></small>';}
			echo '</h3></button>';

			//Boiler Last 5 Status Logs listing model
			echo '<div class="modal fade" id="boiler" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
							<h5 class="modal-title">'.$system_controller_name.' - '.$lang['boiler_recent_logs'].'</h5>
						</div>
						<div class="modal-body">';
  							if ($system_controller_fault == '1') {
								$date_time = date('Y-m-d H:i:s');
								$datetime1 = strtotime("$date_time");
								$datetime2 = strtotime("$system_controller_seen");
								$interval  = abs($datetime2 - $datetime1);
								$ctr_minutes   = round($interval / 60);
								echo '
								<ul class="chat">
									<li class="left clearfix">
										<div class="header">
											<strong class="primary-font red">Boiler Fault!!!</strong>
											<small class="pull-right text-muted">
											<i class="fa fa-clock-o fa-fw"></i> '.secondsToWords(($ctr_minutes)*60).' ago
											</small>
											<br><br>
											<p>Node ID '.$system_controller_id.' last seen at '.$system_controller_seen.' </p>
											<p class="text-info">Heating system will resume its normal operation once this issue is fixed. </p>
										</div>
									</li>
								</ul>';
  							}
							$bquery = "select DATE_FORMAT(start_datetime, '%H:%i') as start_datetime, DATE_FORMAT(stop_datetime, '%H:%i') as stop_datetime , DATE_FORMAT(expected_end_date_time, '%H:%i') as expected_end_date_time, TIMESTAMPDIFF(MINUTE, start_datetime, stop_datetime) as on_minuts
							from system_controller_logs order by id desc limit 5";
							$bresults = $conn->query($bquery);
							if (mysqli_num_rows($bresults) == 0){
								echo '<div class=\"list-group\">
									<a href="#" class="list-group-item"><i class="fa fa-exclamation-triangle red"></i>&nbsp;&nbsp;'.$lang['boiler_no_log'].'</a>
								</div>';
							} else {
								echo '<p class="text-muted">'. mysqli_num_rows($bresults) .' '.$lang['boiler_last_records'].'</p>
								<div class=\"list-group\">' ;
									echo '<a href="#" class="list-group-item"> <i class="ionicons ion-flame fa-1x red"></i> Start &nbsp; - &nbsp;End <span class="pull-right text-muted"><em> '.$lang['boiler_on_minuts'].' </em></span></a>';
									while ($brow = mysqli_fetch_assoc($bresults)) {
										echo '<a href="#" class="list-group-item"> <i class="ionicons ion-flame fa-1x red"></i> '. $brow['start_datetime'].' - ' .$brow['stop_datetime'].' <span class="pull-right text-muted"><em> '.$brow['on_minuts'].'&nbsp;</em></span></a>';
									}
								 echo '</div>';
							}
						echo '</div>
						<div class="modal-footer"><button type="button" class="btn btn-default btn-sm" data-dismiss="modal">'.$lang['close'].'</button>
						</div>
						<!-- /.modal-footer -->
					</div>
					<!-- /.modal-content -->
				</div>
				<!-- /.modal-dialog -->
			</div>
			<!-- /.modal fade -->
			';
		}	// end if boiler button

		// Temperature Sensors Post System Controller
		$query = "SELECT temperature_sensors.name, temperature_sensors.sensor_child_id, nodes.node_id, nodes.last_seen, nodes.notice_interval FROM temperature_sensors, nodes WHERE (nodes.id = temperature_sensors.sensor_id) AND temperature_sensors.zone_id = 0 AND temperature_sensors.show_it = 1 AND temperature_sensors.pre_post = 0 order by index_id asc;";
                $results = $conn->query($query);
                while ($row = mysqli_fetch_assoc($results)) {
			$sensor_name = $row['name'];
                        $sensor_child_id = $row['sensor_child_id'];
			$node_id = $row['node_id'];
                        $node_seen = $row['last_seen'];
                        $node_notice = $row['notice_interval'];
			$shcolor = "green";
	                if($node_notice > 0){
        	                $now=strtotime(date('Y-m-d H:i:s'));
                	        $node_seen_time = strtotime($node_seen);
                        	if ($node_seen_time  < ($now - ($node_notice*60))) { $shcolor = "red"; }
        	        }
                        //query to get temperature from messages_in_view_24h table view
                        $query = "SELECT * FROM messages_in WHERE node_id = '{$node_id}' AND child_id = '{$sensor_child_id}' ORDER BY id desc LIMIT 1;";
                        $result = $conn->query($query);
                        $sensor = mysqli_fetch_array($result);
                        $sensor_c = $sensor['payload'];
   			echo '<button class="btn btn-default btn-circle btn-xxl mainbtn animated fadeIn" data-backdrop="static" data-keyboard="false">
			<h3><small>'.$sensor_name.'</small></h3>
			<h3 class="degre">'.number_format(DispTemp($conn,$sensor_c),1).'&deg;</h3>
			<h3 class="status">
                        <small class="statuscircle"><i class="fa fa-circle fa-fw '.$shcolor.'"></i></small>
                        </h3></button>';      //close out status and button
 		}

                // Add-On buttons
                $query = "SELECT zone.*, zone_type.category FROM zone, zone_type WHERE zone.type_id = zone_type.id AND zone.purge = 0 AND category = 2 ORDER BY index_id asc;";
                $results = $conn->query($query);
                while ($row = mysqli_fetch_assoc($results)) {
                        //get the schedule status for this zone
			$zone_id = $row['id'];
                        $query = "SELECT schedule_daily_time.start, schedule_daily_time_zone.sunset, schedule_daily_time_zone.sunset_offset FROM schedule_daily_time, schedule_daily_time_zone WHERE (schedule_daily_time_zone.schedule_daily_time_id = schedule_daily_time.id) AND zone_id = {$zone_id} LIMIT 1;";
                        $result = $conn->query($query);
                        $sch_row = mysqli_fetch_array($result);
                        $sunset = $sch_row['sunset'];
                        $start_time = $sch_row['start'];
                        $sunset_offset = $sch_row['sunset_offset'];
                        if ($sunset == 1) {
                                $query = "SELECT * FROM weather WHERE last_update > DATE_SUB( NOW(), INTERVAL 24 HOUR);";
                                $result = $conn->query($query);
                                $rowcount=mysqli_num_rows($result);
                                if ($rowcount > 0) {
                                        $wrow = mysqli_fetch_array($result);
                                        if (date('H:i:s', $wrow['sunset']) < $start_time) {
                                                $sunset_time = date('H:i:s', $wrow['sunset']);
                                                $start_time = strtotime($sunset_time);
                                                $start_time = $start_time + ($sunset_offset * 60); //set to start $sunset_offset minutes before sunset
                                                $start_time = date('H:i:s', $start_time);
                                         }
                                }
                        }
	                if($holidays_status == 0) {
				$query = "SELECT * FROM schedule_daily_time_zone_view WHERE ((`end`> CAST('{$start_time}' AS time) AND CURTIME() between CAST('{$start_time}' AS time) AND `end`) OR (`end` < CAST('{$start_time}' AS time) AND CURTIME() < `end`) OR (`end` < CAST('{$start_time}' AS time) AND CURTIME() > CAST('{$start_time}' AS time))) AND zone_id = {$zone_id} AND time_status = '1' AND (WeekDays & (1 << {$dow})) > 0 AND holidays_id = 0 LIMIT 1;";
               		}else{
                       		$query = "SELECT * FROM schedule_daily_time_zone_view WHERE ((`end`> CAST('{$start_time}' AS time) AND CURTIME() between CAST('{$start_time}' AS time) AND `end`) OR (`end` < CAST('{$start_time}' AS time) AND CURTIME() < `end`) OR (`end` < CAST('{$start_time}' AS time) AND CURTIME() > CAST('{$start_time}' AS time))) AND zone_id = {$zone_id} AND time_status = '1' AND (WeekDays & (1 << {$dow})) > 0 AND holidays_id > 0 LIMIT 1;";
                	}                        $result = $conn->query($query);
                        if(mysqli_num_rows($result)<=0){
                                $sch_status=0;
                        }else{
                                $schedule = mysqli_fetch_array($result);
                                $sch_status = $schedule['tz_status'];
                        }

                        //set current zone state
                        $add_on_active = $row['zone_state'];

                        //query to get zone current state
                        $query = "SELECT * FROM zone_current_state WHERE zone_id =  '{$row['id']}' LIMIT 1;";
                        $result = $conn->query($query);
                        $zone_current_state = mysqli_fetch_array($result);
                        $add_on_mode = $zone_current_state['mode'];

                        if ($add_on_active=='1'){$add_on_colour="orange";} elseif ($add_on_active=='0'){$add_on_colour="black";}
                        echo '<a href="javascript:update_add_on('.$row['id'].');">
                        <button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
                        <h3 class="buttontop"><small>'.$row['name'].'</small></h3>
                        <h3 class="degre" ><i class="fa fa-lightbulb-o fa-1x '.$add_on_colour.'"></i></h3>
                        <h3 class="status">';

                        if ($sch_status =='1' && $add_on_active == 0) {
                                $add_on_mode = 74;
                        } elseif ($sch_status =='1' && $add_on_active == 1) {
                                $add_on_mode = 114;
                        } elseif ($sch_status =='0' && $add_on_active == 0) {
                                $add_on_mode = 0;
                        } elseif ($sch_status =='0' && $add_on_active == 1) {
                                $add_on_mode = 111;
                        }
                        $rval=getIndicators($conn, $add_on_mode, $zone_temp_target);
                        //Left small circular icon/color status
                        echo '<small class="statuscircle"><i class="fa fa-circle fa-fw ' . $rval['status'] . '"></i></small>';
                        //Middle target temp
                        echo '<small class="statusdegree">' . $rval['target'] .'</small>';
                        //Right icon for what/why
                        echo '<small class="statuszoon"><i class="fa ' . $rval['shactive'] . ' ' . $rval['shcolor'] . ' fa-fw"></i></small>';
                        echo '</h3></button>';      //close out status and button

                }
                echo '<input type="hidden" id="sch_active" name="sch_active" value="'.$sch_status.'"/>';
                ?>
		<!-- One touch buttons -->
		<div id="collapseone" class="panel-collapse collapse animated fadeIn">
			<?php
                        //query to check live temperature status
                        $query = "SELECT status FROM live_temperature WHERE status = '1' LIMIT 1";
                        $result = $conn->query($query);
                        $lt_status=mysqli_num_rows($result);
                        if ($lt_status==1) {$lt_status='red';}else{$lt_status='blue';}
                        echo '<button class="btn btn-default btn-circle btn-xxl mainbtn animated fadeIn" data-toggle="modal" href="#livetemperature" data-backdrop="static" data-keyboard="false">
                        <h3 class="text-info"><small>'.$lang['live_temp'].'</small></h3>
			<h3 class="degre" ><img src="images/hvac_temp_30.png" border="0"></h3>
                        <h3 class="status"><small class="statuscircle"><i class="fa fa-circle fa-fw '.$lt_status.'"></i></small></h3>
                        </button>';

			//query to check override status
			$query = "SELECT status FROM override WHERE status = '1' LIMIT 1";
			$result = $conn->query($query);
			$override_status=mysqli_num_rows($result);
			if ($override_status==1) {$override_status='red';}else{$override_status='blue';}
			echo '<a style="color: #777; cursor: pointer; text-decoration: none;" href="override.php">
			<button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
			<h3 class="buttontop"><small>'.$lang['override'].'</small></h3>
			<h3 class="degre" ><i class="fa fa-refresh fa-1x"></i></h3>
			<h3 class="status"><small class="statuscircle"><i class="fa fa-circle fa-fw '.$override_status.'"></i></small>
			</h3></button></a>';

			//query to check boost status
			$query = "SELECT status FROM boost WHERE status = '1' LIMIT 1";
			$result = $conn->query($query);
			$boost_status=mysqli_num_rows($result);
			if ($boost_status ==1) {$boost_status='red';}else{$boost_status='blue';}
			echo '<a style="color: #777; cursor: pointer; text-decoration: none;" href="boost.php">
			<button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
			<h3 class="buttontop"><small>'.$lang['boost'].'</small></h3>
			<h3 class="degre" ><i class="fa fa-rocket fa-1x"></i></h3>
			<h3 class="status"><small class="statuscircle"><i class="fa fa-circle fa-fw '.$boost_status.'"></i></small>
			</h3></button></a>';

			//query to check night climate
			$query = "SELECT * FROM schedule_night_climate_time WHERE id = 1";
			$results = $conn->query($query);
			$row = mysqli_fetch_assoc($results);
			if ($row['status'] == 1) {$night_status='red';}else{$night_status='blue';}
			echo '<a style="color: #777; cursor: pointer; text-decoration: none;" href="scheduling.php?nid=0">
			<button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
			<h3 class="buttontop"><small>'.$lang['night_climate'].'</small></h3>
			<h3 class="degre" ><i class="fa fa-bed fa-1x"></i></h3>
			<h3 class="status"><small class="statuscircle"><i class="fa fa-circle fa-fw '.$night_status.'"></i></small>
			</h3></button>';

			//query to check away status
			$query = "SELECT * FROM away LIMIT 1";
			$result = $conn->query($query);
			$away = mysqli_fetch_array($result);
			if ($away['status']=='1'){$awaystatus="red";}elseif ($away['status']=='0'){$awaystatus="blue";}
			echo '<a href="javascript:active_away();">
			<button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
			<h3 class="buttontop"><small>'.$lang['away'].'</small></h3>
			<h3 class="degre" ><i class="fa fa-sign-out fa-1x"></i></h3>
			<h3 class="status"><small class="statuscircle"><i class="fa fa-circle fa-fw '.$awaystatus.'"></i></small>
			</h3></button></a>';

			//query to check holidays status
			$query = "SELECT status FROM holidays WHERE NOW() between start_date_time AND end_date_time AND status = '1' LIMIT 1";
			$result = $conn->query($query);
			$holidays_status=mysqli_num_rows($result);
			if ($holidays_status=='1'){$holidaystatus="red";}elseif ($holidays_status=='0'){$holidaystatus="blue";}
			?>
			<a style="color: #777; cursor: pointer; text-decoration: none;" href="holidays.php">
			<button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
			<h3 class="buttontop"><small><?php echo $lang['holidays']; ?></small></h3>
			<h3 class="degre" ><i class="fa fa-paper-plane fa-1x"></i></h3>
			<h3 class="status"><small class="statuscircle" style="color:#048afd;"><i class="fa fa-circle fa-fw <?php echo $holidaystatus; ?>"></i></small>
			</h3></button></a>

                        <a style="color: #777; cursor: pointer; text-decoration: none;" href="relay.php">
                        <button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
                        <h3 class="buttontop"><small><?php echo $lang['relay_add']; ?></small></h3>
                        <h3 class="degre" ><i class="fa fa-plus fa-1x blue"></i></h3>
                        <h3 class="status"><small class="statuscircle" style="color:#048afd;"><i class="fa fa-fw"></i></small>
                        </h3></button></a>

                        <a style="color: #777; cursor: pointer; text-decoration: none;" href="sensor.php">
                        <button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
                        <h3 class="buttontop"><small><?php echo $lang['sensor_add']; ?></small></h3>
                        <h3 class="degre" ><i class="fa fa-plus fa-1x green"></i></h3>
                        <h3 class="status"><small class="statuscircle" style="color:#048afd;"><i class="fa fa-fw"></i></small>
                        </h3></button></a>

			<a style="color: #777; cursor: pointer; text-decoration: none;" href="zone.php">
			<button type="button" class="btn btn-default btn-circle btn-xxl mainbtn">
			<h3 class="buttontop"><small><?php echo $lang['zone_add']; ?></small></h3>
			<h3 class="degre" ><i class="fa fa-plus fa-1x"></i></h3>
			<h3 class="status"><small class="statuscircle" style="color:#048afd;"><i class="fa fa-fw"></i></small>
			</h3></button></a>
			</div>
			<?php
			// live temperature modal
			if ($system_controller_mode == 1) {
                        	$query = "SELECT id, default_c FROM zone_view WHERE type = 'HVAC' LIMIT 1";
			} else {
                                $query = "SELECT id, default_c FROM zone_view WHERE type = 'Heating' LIMIT 1";
			}
                        $result = $conn->query($query);
                        $row = mysqli_fetch_array($result);
                        echo '<input type="hidden" id="zone_id" name="zone_id" value="'.$row['id'].'"/>
			<div class="modal fade" id="livetemperature" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                        <div class="modal-content">
                                                <div class="modal-header">
                                                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
                                                        <h5 class="modal-title">'.$lang['live_temperature'].'</h5>
                                                </div>
                                                <div class="modal-body">
                                                        <table class="table">
                                                                <tr>
                                                                        <th class="col-xs-1"><small>'.$lang['live_temperature'].'</small></th>
									<th class="col-xs-11"></small></th>
                                                                </tr>
                                                                <tr>
                                                                        <td><div class="slider-wrapper">
                                                                                <input type="range" min="0" max="100" step="0.5" value="'.DispTemp($conn, $row['default_c']).'" id="default_c" name="live_temp" oninput=update_slider(this.value,"live_temp")>
                                                                        </div></td>
									<td><h4><br><br><br><span id="live_val" style="display: inline-flex !important; font-size:18px !important;"><output name="show_min_temp_val" id="live_temp" style="padding-top:0px !important; font-size:18px !important;">'.DispTemp($conn, $row['default_c']).'</output></span>&deg;</h4><br></td>
                                                                </tr>
                                                        </table>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-default btn-sm" data-dismiss="modal">'.$lang['cancel'].'</button>
                				<input type="button" name="submit" value="'.$lang['save'].'" class="btn btn-default login btn-sm" onclick="update_defaut_c()">
                                                </div>
                                                <!-- /.modal-footer -->
                                        </div>
                                        <!-- /.modal-content -->
                                </div>
                                <!-- /.modal-dialog -->
                        </div>
                        <!-- /.modal fade -->
                        '; ?>
		</div>
                <!-- /.panel-body -->
		<div class="panel-footer">
			<?php
			ShowWeather($conn);
			?>

                       	<div class="pull-right">
                        	<div class="btn-group">
					<?php
					$query="select date(start_datetime) as date,
					sum(TIMESTAMPDIFF(MINUTE, start_datetime, expected_end_date_time)) as total_minuts,
					sum(TIMESTAMPDIFF(MINUTE, start_datetime, stop_datetime)) as on_minuts,
					(sum(TIMESTAMPDIFF(MINUTE, start_datetime, expected_end_date_time)) - sum(TIMESTAMPDIFF(MINUTE, start_datetime, stop_datetime))) as save_minuts
					from system_controller_logs WHERE date(start_datetime) = CURDATE() GROUP BY date(start_datetime) asc";
					$result = $conn->query($query);
					$system_controller_time = mysqli_fetch_array($result);
					$system_controller_time_total = $system_controller_time['total_minuts'];
					$system_controller_time_on = $system_controller_time['on_minuts'];
					$system_controller_time_save = $system_controller_time['save_minuts'];
					if($system_controller_time_on >0){	echo ' <i class="ionicons ion-ios-clock-outline"></i> '.secondsToWords(($system_controller_time_on)*60);}
					?>
                        	</div>
                 	</div>
		</div>
		<!-- /.panel-footer -->
	</div>
	<!-- /.panel-primary -->
<?php if(isset($conn)) { $conn->close();} ?>

<script language="javascript" type="text/javascript">
function update_slider(value, id)
{
 var valuetext = value;
 var idtext = id;
 document.getElementById(id).innerTex = parseFloat(value);
 document.getElementById(id).value = parseFloat(value);
}
</script>

