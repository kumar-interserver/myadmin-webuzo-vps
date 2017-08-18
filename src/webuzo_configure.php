<?php

/**
 * @param $id
 * @return false|null
 */
function webuzo_configure($id) {
		include_once INCLUDE_ROOT.'/../vendor/softaculous/webuzo_sdk/webuzo_sdk.php';
		if(isset($GLOBALS['tf']->variables->request['vps_id'])) {
			$id = $GLOBALS['tf']->variables->request['vps_id'];
		}
		$service = get_service($id, 'vps');
		if(!$id){
			myadmin_log('vps', 'info', 'VPS ID is not provided!', __LINE__, __FILE__);
			return FALSE;
		}
		function_requirements('webuzo_update_logo');
		$logo_update_resp = webuzo_update_logo($service['vps_ip']);
		$msg = (!empty($logo_update_resp)) ? 'Logo and text change is completed successfully!' : 'Logo and text change is not completed failed!';
		myadmin_log('vps', 'info', $msg,__LINE__,__FILE__);
		$email = $GLOBALS['tf']->accounts->cross_reference($service['vps_custid']);
		$ns1 = 'cdns1.interserver.net';
		$ns2 = 'cdns2.interserver.net';

		//Webuzo license
		$license_key = NULL;
		$noc = new \Detain\MyAdminSoftaculous\SoftaculousNOC(SOFTACULOUS_USERNAME, SOFTACULOUS_PASSWORD);
		$license_details = $noc->webuzoLicenses('', $service['vps_ip']);
		if ($license_details['num_results'] > 0) {
			foreach ($license_details['licenses'] as $license_detail) {
				if($service['vps_ip'] == $license_detail['ip']) {
					myadmin_log('vps', 'info', "Webuzo License found for {$service['vps_ip']} details as follows ".json_encode($license_detail),__LINE__,__FILE__);
					$license_key = $license_detail['license'];
				}
			}
		} else {
			myadmin_log('vps', 'info', "Webuzo License not found for $email for {$service['vps_ip']}",__LINE__,__FILE__);
		}

		$db = get_module_db('vps');
		$GLOBALS['tf']->history->set_db_module('vps');
		$GLOBALS['tf']->accounts->set_db_module('vps');
		$db->query("select * from history_log where history_owner = {$service['vps_custid']} and history_old_value = 'Webuzo Details' limit 1");
		$user = 'admin';
		function_requirements('webuzo_randomPassword');
		$pass = webuzo_randomPassword();

		$new = new Webuzo_API($user, $pass, $service['vps_ip']);
		$res = $new->webuzo_configure($service['vps_ip'],  $user, $email, $pass, $service['vps_hostname'], $ns1, $ns2, $license_key);
		myadmin_log('vps', 'info', "webuzo_configure({$service['vps_ip']},  $user, $email, $pass, {$service['vps_hostname']}, $ns1, $ns2, $license_key)",__LINE__,__FILE__);
		$res = myadmin_unstringify($res);
		if(isset($res['done'])) {
			if($db->num_rows() == 0) {
				$GLOBALS['tf']->history->add('vps', 'webuzo_pass', $pass,'Webuzo Details');
				myadmin_log('vps', 'info', "Webuzo password added to history_log successfully! for $email for vps id {$service['vps_ip']}",__LINE__,__FILE__);
			} else {
				$data['history_new_value'] = $pass;
				$db->next_record(MYSQL_ASSOC);
				$history_id = $db->Record['history_id'];
				$GLOBALS['tf']->history->update($history_id, $data);
				myadmin_log('vps', 'info', "Webuzo password updated to history_log id - $history_id successfully! for $email for vps id {$service['vps_ip']}",__LINE__,__FILE__);
			}
			$url = 'http://my.interserver.net/index.php?choice=none.view_vps3&id='.$id;
			$body = 'Welcome! '.EMAIL_NEWLINE;
			$body .= 'Your VPS has been created successfully. Here are some details for you to get started.'.EMAIL_NEWLINE;
			$body .= 'You can manage your VPS through '.$url . EMAIL_NEWLINE;
			$body .= 'Bread Basket Control Panel '.EMAIL_NEWLINE;
			$body .= 'Url: http://'.$service['vps_ip'].':2002/'.EMAIL_NEWLINE;
			$body .= 'Username: admin'.EMAIL_NEWLINE;
			$body .= 'Password: '.$pass . EMAIL_NEWLINE;
			$body .= 'Documentation: https://www.interserver.net/tips/knb-category/breadbasket/'.EMAIL_NEWLINE;
			$body .= 'Thank You, '.EMAIL_NEWLINE;
			$body .= 'Regards, '.EMAIL_NEWLINE;
			$body .= 'Interserver Team';
			$subject = 'InterServer Bread Basket Details';
			$headers = '';
			$headers .= 'MIME-Version: 1.0'.EMAIL_NEWLINE;
			$headers .= 'Content-Type: text/plain; charset=UTF-8'.EMAIL_NEWLINE;
			$headers .= 'From: admin@interserver.net'.EMAIL_NEWLINE;
			$headers .= 'To: '.$email . EMAIL_NEWLINE;
			mail($email, $subject, $body, $headers);
			myadmin_log('vps', 'info', "Webuzo configuration email has been sent to $email",__LINE__,__FILE__);
			myadmin_log('vps', 'info', "Webuzo configured successfully! for $email for vps id {$service['vps_ip']}",__LINE__,__FILE__);
		} else {
			myadmin_log('vps', 'info', "Error while configuring webuzo! for $email for vps id {$service['vps_ip']}",__LINE__,__FILE__);
			myadmin_log('vps', 'info', 'Error details : '.json_encode($res), __LINE__, __FILE__);
		}
	}
