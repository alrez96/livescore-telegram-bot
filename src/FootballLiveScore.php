<?php

/**************************
 * @author     alrez96    *
 * @date  	   Jan 2017   *
 * @version    0.1        *
 **************************/

// --------------- start initialization ---------------

define('token', '-');

// --------------- end initialization ---------------

// --------------- start functions ---------------

function request($method, $data) {
    $url = 'https://api.telegram.org/bot' . token . '/' . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $result = curl_exec($ch);
	curl_close($ch);
	return json_decode($result);
}

function sendMessage(
					$chat_id,
					$text,
					$parse_mode,
					$disable_web_page_preview,
					$disable_notification,
					$reply_to_message_id,
					$reply_markup
					) {
    $ret = request('sendMessage', [
						'chat_id' => $chat_id,
						'text' => $text,
						'parse_mode' => $parse_mode,
						'disable_web_page_preview' => $disable_web_page_preview,
						'disable_notification' => $disable_notification,
						'reply_to_message_id' => $reply_to_message_id,
						'reply_markup' => $reply_markup
					]
			);
	return $ret;
}

// ------------------------------

function getPage($pageURL) {
	$ch = curl_init($pageURL);
	curl_setopt($ch, CURLOPT_ENCODING, "");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$content = curl_exec($ch);
	curl_close($ch);
	return $content;
}

function extractString($str, $start, $end) {
	$str = " " . $str;
	$ini = strpos($str, $start);
	if ($ini == 0)
		return "";
	$ini += strlen($start);
	$len = strpos($str, $end, $ini) - $ini;
	return substr($str, $ini, $len);
}

function groupNameEmoji($group) {
	if($group == 'England')
		return "\xf0\x9f\x8f\xb4\xf3\xa0\x81\xa7\xf3\xa0\x81\xa2\xf3\xa0\x81\xa5\xf3\xa0\x81\xae\xf3\xa0\x81\xa7\xf3\xa0\x81\xbf";
	elseif($group == 'spain')
		return "\xf0\x9f\x87\xaa\xf0\x9f\x87\xb8";
	elseif($group == 'italy')
		return "\xf0\x9f\x87\xae\xf0\x9f\x87\xb9";
	elseif($group == 'WORLD' || $group == 'INTERNATIONAL')
		return "\xf0\x9f\x8c\x90";
	elseif($group == 'France')
		return "\xf0\x9f\x87\xab\xf0\x9f\x87\xb7";
	else
		return "\xe2\x9d\x93";
}

function setLiveScore() {
	$html = getPage("http://www.varzesh3.com/livescore/feed");
	$parsed = extractString($html, 'var livescoreMatches = ', ';');
	$livescore_json = json_decode($parsed);
	for($i = 0; $i < sizeof($livescore_json); $i++) {
		if($livescore_json[$i]->sportId != 0)
			array_splice($livescore_json, $i, 1);
	}
	return $livescore_json;
}

function getLiveScore($livescore) {
	$groupLabel = $livescore[0]->groupLabel;
	$str = groupNameEmoji($livescore[0]->groupName) . " " . $groupLabel;
	for($i = 0; $i < sizeof($livescore); $i++) {
		if($livescore[$i]->groupLabel != $groupLabel) {
			$groupLabel = $livescore[$i]->groupLabel;
			$str .= "\n\n" . groupNameEmoji($livescore[$i]->groupName) . " " . $groupLabel;
		}
		if($livescore[$i]->time->state == 0) {
			$str .= "\n\xe2\x96\xab\xef\xb8\x8f " . $livescore[$i]->teams->host->name
			     . " *?* - *?* "
			             . $livescore[$i]->teams->guest->name . " | _" . $livescore[$i]->time->start . "_";
		} elseif($livescore[$i]->time->state == 1 || ($livescore[$i]->time->state == 3 && $livescore[$i]->time->current != null)) {
			$str .= "\n\xe2\x96\xaa\xef\xb8\x8f " . $livescore[$i]->teams->host->name
			     . " *" . $livescore[$i]->teams->host->score[0] . "* - *"
				       . $livescore[$i]->teams->guest->score[0]
				 . "* "
			             . $livescore[$i]->teams->guest->name . " | _'" . $livescore[$i]->time->current
				 . "_";
		} elseif($livescore[$i]->time->state == 3) {
			$str .= "\n\xf0\x9f\x94\xbb " . $livescore[$i]->teams->host->name
			     . " *" . $livescore[$i]->teams->host->score[0] . "* - *"
				       . $livescore[$i]->teams->guest->score[0]
				 . "* "
			             . $livescore[$i]->teams->guest->name
				 . "\n     \xf0\x9f\x94\xbd _جزئیات_ /" . $livescore[$i]->id;
		} else {
			$str .= "\nاتفاق دیگری افتاده!";
		}
	}
	return $str;
}

function getGameEvents($game_id) {
	$livescore = setLiveScore();
	$find_game = false;
	for($i = 0; $i < sizeof($livescore); $i++) {
		if($livescore[$i]->id == $game_id) {
			$find_game = true;
			if($livescore[$i]->time->state == 1 || ($livescore[$i]->time->state == 3 && $livescore[$i]->time->current != null))
				$state_i = "\xe2\x96\xaa\xef\xb8\x8f";
			else
				$state_i = "\xf0\x9f\x94\xbb";
			if($livescore[$i]->time->state == 1)
				$state_des = "دقیقه '" . $livescore[$i]->time->current;
			elseif($livescore[$i]->time->state == 2)
				$state_des = "پایان نیمه اول";
			elseif($livescore[$i]->time->state == 3 && $livescore[$i]->time->current != null)
				$state_des = "دقیقه '" . $livescore[$i]->time->current;
			elseif($livescore[$i]->time->state == 3)
				$state_des = "خاتمه یافته";
			if(sizeof($livescore[$i]->events) == 0)
				$event = "تاکنون رخدادی ثبت نشده";
			else {
				$event = "";
				for($j = 0; $j < sizeof($livescore[$i]->events); $j++) {
					if($livescore[$i]->events[$j]->teamId == $livescore[$i]->teams->guest->id)
						$event .= "\n*G*: ";
					else
						$event .= "\n*H*: ";
					if($livescore[$i]->events[$j]->status == 1) {
						$event .= "\xe2\x9a\xbd\xef\xb8\x8f " . $livescore[$i]->events[$j]->playerName
						        . " | _'" . $livescore[$i]->events[$j]->time . "_";
					} elseif($livescore[$i]->events[$j]->status == 6) {
						$event .= "\xe2\x9a\xbd\xef\xb8\x8fP " . $livescore[$i]->events[$j]->playerName
						        . " | _'" . $livescore[$i]->events[$j]->time . "_";
					}
				}
			}
			$str = "\xf0\x9f\x94\xbd _جزئیات بازی شماره " . $game_id;
			$str .= "_\n\n" . groupNameEmoji($livescore[$i]->groupName) . " "
			     . $livescore[$i]->groupLabel
				 . "\n\xe2\x96\xab\xef\xb8\x8f " . $livescore[$i]->teams->host->name
			     . " *" . $livescore[$i]->teams->host->score[0] . "* - *"
				        . $livescore[$i]->teams->guest->score[0]
				 . "* "
			            . $livescore[$i]->teams->guest->name
				 . "\n\n\xf0\x9f\x93\x86 تاریخ برگزاری: " . $livescore[$i]->date
				 . "\n\xf0\x9f\x95\x92 زمان شروع: " . $livescore[$i]->time->start
				 . "\n" . $state_i . " وضعیت: " . $state_des
				 . "\n\n\xf0\x9f\x94\xb9 رخدادها:" . $event;
			break;
		}
	}
	if(!$find_game)
		$str = "";
	return $str;
}

// --------------- end functions ---------------

// --------------- start main ---------------

$update = json_decode(file_get_contents('php://input'));

if(isset($update->message)) {
	$message_id = $update->message->message_id;
	$from_id = $update->message->from->id;
	$date = $update->message->date;
	$chat_id = $update->message->chat->id;
	$chat_type = $update->message->chat->type;
} else {
	exit();
}

if($chat_type == 'private') {
	$text = $update->message->text;
	if($text == "/start") {
		$str = getLiveScore(setLiveScore());
		sendMessage($from_id, $str, "Markdown");
	} elseif(preg_match("/^\/[0-9]{5,6}$/", $text)) {
		$str = getGameEvents(substr($text, 1));
		sendMessage($from_id, $str, "Markdown");
	}
} else {
	exit();
}

// --------------- end main ---------------

?>