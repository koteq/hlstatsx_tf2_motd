<?php
/*
 * MOTD for TF2 servers
 * Reflex - 28.09.11
 */

error_reporting(0);
ini_set('display_errors', 0);

define('STEAM_API_KEY', '0123456789ABCDEF0123456789ABCDEF');  // get one at http://steamcommunity.com/dev/apikey

connect_to_db() or die_blank();

if (isset($_GET['avatar']) && !preg_match('[^0-9,]', $_GET['avatar'])) {
	$avatar = get_avatar($_GET['avatar'], STEAM_API_KEY);
	header("Location: $avatar");
	exit;
}

$client = get_player_id($_SERVER['REMOTE_ADDR']);
$players = get_neighbour_players($client);
$players = get_players_data($players);
$content = format_players_table($client, $players);
require('index.tpl.php');
exit;

function connect_to_db() {
	if (!mysql_connect('localhost', 'user', 'pass')) return false;
	if (!mysql_query('set names utf8')) return false;
	if (!mysql_select_db('hlstatsx')) return false;
	
	return true;
}

function die_blank() {
	$content = "<div id=error>Статистика временно недоступна</div>";
	require('index.tpl.php');
	die('TODO: nosql page here');
}

function die_404() {
	header("HTTP/1.0 404 Not Found");
	die();
}

function get_player_id($ip) {
	$ip = mysql_real_escape_string($ip);
	$sql = "
		SELECT DISTINCT
		  conn.playerId,
		  IF (
			entr.eventTime IS NOT NULL,
			entr.eventTime,
			conn.eventTime) as eventTime
		FROM hlstats_Events_Connects conn
		LEFT JOIN hlstats_Events_Entries entr
		  ON (conn.playerId = entr.playerId)
		WHERE ipAddress = '$ip'
		ORDER BY eventTime DESC
		LIMIT 1";
	$res = mysql_query($sql);
	if (!$res || !mysql_num_rows($res)) {
		return false;
	}
	return mysql_result($res, 0);
}

function get_players_data($players) {
	$players_str = implode(', ', $players);
	$sql = "
		SELECT
			p.playerId,
			p.lastName,
			p.skill,
			p.kills,
			p.deaths,
			ROUND(p.kills/(IF(p.deaths=0,1,p.deaths)), 2) AS kpd,
			CAST(LEFT(pu.uniqueId,1) AS unsigned) + CAST('76561197960265728' AS unsigned) + CAST(MID(pu.uniqueId, 3,10)*2 AS unsigned) AS communityId,
			pu.avatar
		FROM
			hlstats_Players p
		JOIN
			hlstats_PlayerUniqueIds pu ON (pu.playerId = p.playerId)
		WHERE
			p.game = 'tf'
			AND p.playerId IN ($players_str)";
		
	$res = mysql_query($sql);
	
	$result = array();
	
	while ($row = mysql_fetch_assoc($res)) {
		$players[array_search($row['playerId'], $players)] = $row;
	}

	return $players;
}

function get_neighbour_players($client, $top_count = 3, $total_count = 10) {
	$sql = "
		SELECT
			playerId
		FROM
			hlstats_Players
		WHERE
			game = 'tf'
			AND hideranking = 0
		ORDER BY
			skill DESC,
			lastName";
			
	$res = mysql_query($sql);
	
	$client_pos = 0;
	$num_rows = mysql_num_rows($res);
	
	if ($client) {	
		$result = array();

		while ($row = mysql_fetch_row($res)) {
			if ($client == $row[0]) {
				break;
			}
			$client_pos++;
		}
	}
	
	if ($num_rows <= $total_count) { // not enougth rows, show all rows
		mysql_data_seek($res, 0);
		while ($row = mysql_fetch_row($res)) {
			$result[] = $row[0];
		}
	} elseif ($client_pos < $top_count + floor($total_count / 2) - 1) { // too close to begin, show rows from begin
		mysql_data_seek($res, 0);
		$iterator = 0;
		while ($row = mysql_fetch_row($res)) {
			$result[++$iterator] = $row[0];
			if ($iterator >= $total_count) break;
		}
	} elseif ($client_pos >= $num_rows - floor($total_count / 2)) { // too close to end, show rows from end
		$pos = 0;
		mysql_data_seek($res, $pos);
		while ($row = mysql_fetch_row($res)) {
			$result[++$pos] = $row[0];
			if ($pos >= $top_count) break;
		}
		
		$pos = $num_rows - ($total_count - $top_count);
		mysql_data_seek($res, $pos);
		while ($row = mysql_fetch_row($res)) {
			$result[++$pos] = $row[0];
		}
	} else { // somethere in the middle
		$pos = 0;
		mysql_data_seek($res, $pos);
		while ($row = mysql_fetch_row($res)) {
			$result[++$pos] = $row[0];
			if ($pos >= $top_count) break;
		}
		
		$pos = $client_pos - floor(($total_count - $top_count) / 2);
		$start = $pos;
		mysql_data_seek($res, $pos);
		while ($row = mysql_fetch_row($res)) {
			$result[++$pos] = $row[0];
			if ($pos >= $start + $total_count - $top_count) break;
		}
	}
	
	return $result;
}

function format_players_table($client, $players) {
	ob_start();
	print <<<EOT
	<table class="players" cellspacing="0" cellpadding="0">
	<tr class="headline">
		<td>Место</td>
		<td colspan="3">Очков опыта</td>
		<td class="kills">Убийств</td>
		<td class="deaths">Смертей</td>
		<td class="kpd">K:D</td>
	</tr>
EOT;
	foreach ($players as $rank => $player) {
		if ($player['playerId'] == $client) {
			$highlight = ' class="highlight"';
		} else {
			$highlight = '';
		}

		if ($player['avatar']) {
			$avatar = '<img class="avatar" src="'.$player['avatar'].'" width="32" height="32" alt="" />';
		} else {
			$avatar = '<img class="avatar" src="index.php?avatar='.$player['communityId'].'" width="32" height="32" alt="" />';
		}
		
		switch ($rank) {
			case 1:
				$rank = '<img src="img/gold.png" alt="1" />';
				break;
			case 2:
				$rank = '<img src="img/silver.png" alt="2" />';
				break;
			case 3:
				$rank = '<img src="img/bronze.png" alt="3" />';
				break;
		}
		$name = htmlspecialchars($player['lastName']);
		$kpd = sprintf('%0.2f', $player['kpd']);
		print <<<EOT
	<tr$highlight>
		<td class="rank">$rank</td>
		<td class="avatar">$avatar</td>
		<td class="name">$name</td>
		<td class="skill">$player[skill]</td>
		<td class="kills">$player[kills]</td>
		<td class="deaths">$player[deaths]</td>
		<td class="kpd">$kpd</td>
	</tr>
EOT;
	}
	print "</table>";
	$content = ob_get_contents();
	ob_end_clean();
	
	return $content;
}

function get_avatar($q, $api_key) {
	ob_start();

	$url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$api_key&steamids=$q";
	if (!$content = file_get_contents($url)) die_404();
	$content = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $content);
	$data = json_decode($content, true);
	
	$friend_id = $data['response']['players'][0]['steamid'];
	$avatar        = $data['response']['players'][0]['avatar'];
	$avatarmediuml = $data['response']['players'][0]['avatarmediuml'];
	$avatarfull    = $data['response']['players'][0]['avatarfull'];
		
	$avatar        = mysql_real_escape_string($avatar);
	$avatarmediuml = mysql_real_escape_string($avatarmediuml);
	$avatarfull    = mysql_real_escape_string($avatarfull);
	
	$server = (substr($friend_id, -1) % 2 == 0) ? 0 : 1;
	$stem_id = $server.':'.(substr($friend_id, -13) - 1197960265728 - $server) / 2;
	$sql = "UPDATE hlstats_PlayerUniqueIds SET avatar = '$avatar', avatarmedium = '$avatarmedium', avatarfull = '$avatarfull' WHERE uniqueId = '$stem_id' AND game = 'tf'";
	mysql_query($sql);

	ob_end_clean();
	
	return $avatar;
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