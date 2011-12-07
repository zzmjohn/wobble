<?php
	error_reporting(E_ALL);
	
	function die_result($id, $result = NULL) {
		the_result(array(
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => $result
		));
	}
	function die_error($id, $code, $message, $data = NULL) {
		$error = array (
			'code' => $code,
			'message' => $message
		);
		if ( $data != NULL ) {
			$error['data'] = $data;
		}
		the_result(array(
			'jsonrpc' => '2.0',
			'id' => $id, 
			'error' => $error
		));
	}
	function the_result($result_object) {
		header('Content-Type: application/json');
		die(json_encode($result_object));
	}
	
	require_once 'context.php';
	
	$exportedMethods = array (
		array('file' => 'fun_topics.php', 'method' => 'topics_list'),
		array('file' => 'fun_topics.php', 'method' => 'topics_create'),
		
		array('file' => 'fun_topic.php', 'method' => 'topic_get_details'),
		array('file' => 'fun_topic.php', 'method' => 'topic_add_user'),
		array('file' => 'fun_topic.php', 'method' => 'post_create'),
		array('file' => 'fun_topic.php', 'method' => 'post_edit'),
		array('file' => 'fun_topic.php', 'method' => 'post_delete'),
		
		array('file' => 'fun_user.php', 'method' => 'user_get'),
		array('file' => 'fun_user.php', 'method' => 'user_get_id'),
		array('file' => 'fun_user.php', 'method' => 'user_register'),
		array('file' => 'fun_user.php', 'method' => 'user_change_name'),
		array('file' => 'fun_user.php', 'method' => 'user_login'),
		array('file' => 'fun_user.php', 'method' => 'user_signout'),
		array('file' => 'fun_user.php', 'method' => 'get_notifications'),
		
		// Contact list
		array('file' => 'fun_user.php', 'method' => 'user_get_contacts'),
		array('file' => 'fun_user.php', 'method' => 'user_add_contact'),
		array('file' => 'fun_user.php', 'method' => 'user_remove_contact'),
		
		array('file' => 'fun_test.php', 'method' => 'testecho')
	);
	
	# DEV MODE: Sleep randomly 500ms - 1.500ms
	usleep(1000 * 500); // rand(100, 3000));
	
	
	session_start();
	$requestBody = file_get_contents('php://input');
	$request = json_decode($requestBody, TRUE);
	if ($request === NULL) {
		die_error(NULL, -32700, "Parse error");
	}
	if ( !isset ( $request['method']))  {
		die_error($request['id'], -32602, 'No method given.');
	}
	foreach($exportedMethods AS $export) {
		if ( $export['method'] === $request['method']) {
			require_once($export['file']);
			try {
				$response = call_user_func($request['method'], $request['params']);
			}catch(Exception $e) {
				die_error($request['id'], -32603, $e->getMessage());
			}
			
			if (isset($request['id'])) 
				die_result($request['id'], $response);
			else
				die(); # die silently for a notification
		}
	}
	die_error(NULL, -32602, 'Unknown method: '. $request['method']);
