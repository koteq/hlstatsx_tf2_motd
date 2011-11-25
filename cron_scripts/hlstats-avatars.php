#!/usr/bin/php
<?php
/*
 * Avatar updater
 * Reflex - 28.09.11
 */

require('config.php');
$threading = function_exists('pcntl_fork');

$main_conn = connect_to_db();
$sql = 'SELECT uniqueId FROM hlstats_PlayerUniqueIds WHERE game = "tf" ORDER BY playerId';
$res = mysql_query($sql, $main_conn);

$buffer = array();
$pid_arr = array();
$i = 0;
while ($row = mysql_fetch_row($res)) {
	$i++;
	$friend = steam2friend($row[0]);
	if ($friend === false) continue;
	
	$buffer[] = $friend;
	echo "FLUSH $i\n";
	if (count($buffer) >= 100) {
		if ($threading) {
			$pid = pcntl_fork();
			if ($pid) {
				$pid_arr[] = $pid;
				unset($buffer);
				$buffer = array();
			} else {
				get_avatars($buffer, STEAM_API_KEY);
				exit;
			}
			wait_for_childs(5);
		} else {
			get_avatars($buffer, STEAM_API_KEY);
		}
	}
}
if (count($buffer)) {
	echo "FLUSH $i\n";
	get_avatars($buffer, STEAM_API_KEY);
}
if ($threading) {
	wait_for_childs();
}

function wait_for_childs($thread_limit = 0) {
    global $pid_arr;

	while (count($pid_arr) > $thread_limit) {
		$died_pid = pcntl_wait($status);
		if (($key = array_search($died_pid, $pid_arr)) !== false) {
			unset($pid_arr[$key]);
		}
	}
}

function connect_to_db() {
	$conn = mysql_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, true) or die();
	mysql_select_db(DB_DATABASE, $conn) or die();
	return $conn;
}

function get_avatars($array, $api_key) {
	$query = implode(',', $array);
	
	$url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$api_key&steamids=$query";
	if (!$content = file_get_contents($url)) continue;
	$content = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $content);
	$data = json_decode($content, true);
	
	$slave_conn = connect_to_db();
	foreach($data['response']['players'] as $player) {
		$avatar       = mysql_real_escape_string($player['avatar'], $slave_conn);
		$avatarmedium = mysql_real_escape_string($player['avatarmedium'], $slave_conn);
		$avatarfull   = mysql_real_escape_string($player['avatarfull'], $slave_conn);
		$uid = friend2steam($player['steamid']);
		$sql = "UPDATE hlstats_PlayerUniqueIds SET avatar = '$avatar', avatarmedium = '$avatarmedium', avatarfull = '$avatarfull' WHERE game = 'tf' AND uniqueId = '$uid'";
		mysql_query($sql, $slave_conn);
	}
	mysql_close($slave_conn);
}

function steam2friend($steam) {
	$data = explode(':', $steam);
	if (count($data) != 2) return false;
	$friendid = ($data[1] * 2) + $data[0] + 1197960265728;
	return '7656' . $friendid;
}

function friend2steam($friend) {
	$server = (substr($friend, -1) % 2 == 0) ? 0 : 1;
	$auth = (substr($friend, -13) - 1197960265728 - $server) / 2;
	return $server.':'.$auth;
}
