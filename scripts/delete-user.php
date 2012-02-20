#!/usr/bin/env php
<?php
require_once dirname(__FILE__) . '/../web/api/context.php';

if (count($argv) <= 1) {
  die('Usage: ' . $argv[0] . ' <email>');
}

$email = $argv[1];
$user = UserRepository::getUserByEmail($email);
if ($user === NULL) {
  die('ERROR: User not found');
}

print "Deleting user {$user['name']} with id {$user['id']}\n";
UserRepository::delete($user['id']);