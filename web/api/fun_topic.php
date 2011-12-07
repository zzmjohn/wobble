<?php
	require_once 'fun_user.php';
	
	function _topic_has_access($pdo, $topic_id) {
		$stmt = $pdo->prepare('SELECT COUNT(*) cnt FROM topic_readers r WHERE r.user_id = ? AND r.topic_id = ?');
		$stmt->execute(array(user_get_id(), $topic_id));
		$result = $stmt->fetchAll();
		return $result[0]['cnt'] > 0;
	}
	
	function topic_get_details($params) {
		/*global $USERS;
		return array(
			'id' => $param['id'],
			'users' => $USERS,
			'posts' => array(
				array('id' => '1', 'content' =>  '<b>Hello World</b><br />Hi there! This is topic with id=' . $params['id'], 'users' => array('1')),
				array('id' => '2', 'content' =>'Moar!', 'users' => array('2')), /* first reply, no indentation *
				array('id' => '3', 'parent' => '1', 'content' => 'Intended Comment!', 'users' => array('1', '2'))
			)
		);
		*/
		$self_user_id = user_get_id();
		$topic_id = $params['id'];
		
		ValidationService::validate_not_empty($topic_id);
		
		$pdo = ctx_getpdo();
		
		$users = TopicRepository::getReaders($topic_id);
				
		$stmt = $pdo->prepare('SELECT p.post_id id, p.content, p.revision_no revision_no, p.parent_post_id parent FROM posts p WHERE p.topic_id = ? ORDER BY created_at');
		$stmt->execute(array($topic_id));
		$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$stmt = $pdo->prepare('SELECT e.user_id id FROM post_editors e WHERE topic_id = ? AND post_id = ?');
		foreach($posts AS $i => $post) {
			$posts[$i]['users'] = array();
			
			$stmt->execute(array($topic_id, $post['id']));
			foreach($stmt->fetchAll() AS $post_user) {
				$posts[$i]['users'][] = intval($post_user['id']);
			}
		}
		
		return array (
			'id' => $topic_id,
			'users' => $users,
			'posts' => $posts
		);
	}
	
	
	function topic_add_user($params) {
		$topic_id = $params['topic_id'];
		$user_id = $params['contact_id'];
		
		ValidationService::validate_not_empty($topic_id);
		ValidationService::validate_not_empty($user_id);
		
		$pdo = ctx_getpdo();
		if ( _topic_has_access($pdo, $topic_id) ) {
			$pdo->prepare('REPLACE topic_readers (topic_id, user_id) VALUES (?,?)')->execute(array($topic_id, $user_id));
			
			foreach(TopicRepository::getReaders($topic_id) as $user) {
				NotificationRepository::push($user['id'], array(
					'type' => 'topic_changed',
					'topic_id' => $topic_id
				));
			}
			
			return TRUE;
		}
		else {
			throw new Exception('Illegal Access!');
		}
	}
	
	function post_create($params) {
		$self_user_id = user_get_id();
		$topic_id = $params['topic_id'];
		$post_id = $params['post_id'];
		$parent_post_id = $params['parent_post_id'];
		
		ValidationService::validate_not_empty($topic_id);
		ValidationService::validate_not_empty($post_id);
		ValidationService::validate_not_empty($parent_post_id);
		
		$pdo = ctx_getpdo();
		
		if ( _topic_has_access($pdo, $topic_id) ) {
			// Create empty root post
			$stmt = $pdo->prepare('INSERT INTO posts (topic_id, post_id, content, parent_post_id, created_at)  VALUES (?,?, "",?, unix_timestamp())');
			$stmt->execute(array($topic_id, $post_id, $parent_post_id));
			
			// Assoc first post with current user
			$stmt = $pdo->prepare('INSERT INTO post_editors (topic_id, post_id, user_id) VALUES (?,?,?)');
			$stmt->bindValue(1, $topic_id);
			$stmt->bindValue(2, $post_id);
			$stmt->bindValue(3, $self_user_id);
			$stmt->execute();
			
			foreach(TopicRepository::getReaders($topic_id) as $user) {
				NotificationRepository::push($user['id'], array(
					'type' => 'post_changed',
					'topic_id' => $topic_id,
					'post_id' => $post_id
				));
			}
			
			
			return TRUE;
		}
		else {
			throw new Exception('Illegal Access!');
		}
	}
	
	function post_edit($params) {
		$self_user_id = user_get_id();
		$topic_id = $params['topic_id'];
		$post_id = $params['post_id'];
		$content = $params['content'];
		$revision = $params['revision_no'];
		
		ValidationService::validate_not_empty($topic_id);
		ValidationService::validate_not_empty($post_id);
		ValidationService::validate_not_empty($revision);
		
		$pdo = ctx_getpdo();
		
		if ( _topic_has_access($pdo, $topic_id) ) {
			$stmt = $pdo->prepare('SELECT revision_no FROM posts WHERE topic_id = ? AND post_id = ?');
			$stmt->execute(array($topic_id, $post_id));
			$posts = $stmt->fetchAll();
			
			if ($posts[0]['revision_no'] != $revision) {
				throw new Exception('RevisionNo is not correct. Somebody else changed the post already. (Value: ' . $posts[0][0] . ')');
			}
			$pdo->prepare('UPDATE posts SET content = ?, revision_no = revision_no + 1 WHERE post_id = ? AND topic_id = ?')->execute(array($content, $post_id, $topic_id));
			$pdo->prepare('REPLACE post_editors (topic_id, post_id, user_id) VALUES (?,?,?)')->execute(array($topic_id, $post_id, $self_user_id));
			
			foreach(TopicRepository::getReaders($topic_id) as $user) {
				NotificationRepository::push($user['id'], array(
					'type' => 'post_changed',
					'topic_id' => $topic_id,
					'post_id' => $post_id
				));
			}
			
			return array (
				'revision_no' => ($revision + 1)
			);
		}
		else {
			throw new Exception('Illegal Access!');
		}
	}
	
	function post_delete($params) {
		$self_user_id = user_get_id();
		$topic_id = $params['topic_id'];
		$post_id = $params['post_id'];
		
		ValidationService::validate_not_empty($topic_id);
		ValidationService::validate_not_empty($post_id);
		
		$pdo = ctx_getpdo();
		
		if ( _topic_has_access($pdo, $topic_id) ) {
			$stmt = $pdo->prepare('DELETE FROM post_editors WHERE topic_id = ? AND post_id = ?');
			$stmt->execute(array($topic_id, $post_id));
			
			$pdo->prepare('DELETE FROM posts WHERE topic_id = ? AND post_id = ?')->execute(array($topic_id, $post_id));
			
			foreach(TopicRepository::getReaders($topic_id) as $user) {
				NotificationRepository::push($user['id'], array(
					'type' => 'post_deleted',
					'topic_id' => $topic_id,
					'post_id' => $post_id
				));
			}
			return TRUE;
		} else {
			throw new Exception('Illegal Access!');
		}
	}
	
	