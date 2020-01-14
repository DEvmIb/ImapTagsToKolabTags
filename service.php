<?php
/* include("imap.class"); */
$imap_h="localhost.localdomain"; //kolab host name
$imap_u="hans"; // imap username
$imap_p="wurst"; // imap password
$imap_po=143; // imap port
$rc_u="hans"; //roundcube username
$rc_p="wurst"; //roundcube password
$rc_host="http://localhost.localdomain"; //roundcube hostname
$rc_host_path="/roundcubemail/"; // roundcube path on server
$rc_cookie="/tmp/curl.cookie"; // curl cookie file path
$tag_messages_processed="tag_to_tagging"; // set this tag to processed mails



/////////////////////////////////////////////////////////////////////////////////
//error_reporting(0);
$rc_newurl="";
$rc_id="";
$kolab_tags=[];
$sock="";
$messages=[];

if (file_exists($rc_cookie)) {unlink($rc_cookie);}
IMAP_login($imap_h,$imap_po,$imap_u,$imap_p);
IMAP_idl();

function work() {
	global $messages, $kolab_tags, $tag_messages_processed;
	logging("Starte Worker");
	foreach($messages as $key => $message) {
		logging("Starte Worker 1");
		if (count($messages[$key]["tags"]) ) {
			echo "Bearbeite Nachricht nummer '$key'\n";
			foreach ($messages[$key]["tags"] as $tag) {
				if (isset($kolab_tags[$tag])) {
					logging("\t".$tag." OK!");
					set_mail_tag($messages[$key]["uid"],$kolab_tags[$tag]["uid"]);
				} else {
					logging( "\t'".$tag."' ignored. not found in kolab.");
				}
			}
			IMAP_send("STORE $key +FLAGS ($tag_messages_processed)\r\n");
		}
	}
}
function logging($text) {
	echo date("Y-m-d h:i:sa")." $text\n";
}
function IMAP_logout() {
	global $sock;
	$response=IMAP_send("LOGOUT\r\n");
	fclose($sock);
	logging("IMAP Logged OUT"); 
}
function IMAP_idl() {
	global $imap_h,$imap_po,$imap_u,$imap_p, $rc_cookie;
	$c=0;
	while ( true ) {
		$response=IMAP_send("SELECT INBOX\r\n");
		if(!preg_match("/\*\s(.*)\sEXISTS\r\n/",$response,$match))	{
			logging("IMAP Error");
			IMAP_logout();
			IMAP_login($imap_h,$imap_po,$imap_u,$imap_p);
		} else {
			if ( $match[1] !== $c ) {
				logging("new Mails: Old: $c New: ".$match[1]);
				$c=$match[1];
				if (file_exists($rc_cookie)) {unlink($rc_cookie);}
				RoundCube_login();
				cache_kolab_tags();
				IMAP_fetch_tags();
				work();
			}
		}
			
		sleep(10);
	}
}

function in_array_r($needle, $haystack, $strict = false) {
    foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
            return true;
        }
    }

    return false;
}

function set_mail_tag($message_uid,$tag_uid) {
	global $imap_u,$imap_p,$rc_u,$rc_p,$rc_host,$rc_host_path,$rc_cookie,$rc_newurl,$rc_id,$kolab_tags;
	$post_array=[];
	$post_array["_uid"]=$message_uid;
	$post_array["_mbox"]="INBOX";
	$post_array["_tag"]=$tag_uid;
	$post_array["_act"]="add";
	$post_array["_remote"]="1";
	$post_array["_unlock"]="true";
	$curl = curl_init();
	curl_setopt ($curl, CURLOPT_URL, $rc_host.$rc_host_path."/".$rc_id."/?_task=mail&_action=plugin.kolab_tags");
	curl_setopt ($curl, CURLOPT_POST, count($post_array));
	curl_setopt ($curl, CURLOPT_POSTFIELDS, $post_array);
	curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($curl, CURLOPT_COOKIEJAR, $rc_cookie);
	curl_setopt ($curl, CURLOPT_COOKIEFILE, $rc_cookie);
	curl_setopt ($curl, CURLOPT_HEADER, 0);
	$result = curl_exec($curl);
	//echo "\t".$result."\n";

	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	curl_close($curl);
	$curl="";
	$headers = substr($result, 0, $header_size);
	$body = substr($result, $header_size);	
}

function cache_kolab_tags() {
			global $imap_u,$imap_p,$rc_u,$rc_p,$rc_host,$rc_host_path,$rc_cookie,$rc_newurl,$rc_id,$kolab_tags;
			$kolab_tags=[];
			$curl = curl_init();
			curl_setopt ($curl, CURLOPT_URL, $rc_newurl);
			curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt ($curl, CURLOPT_COOKIEJAR, $rc_cookie);
			curl_setopt ($curl, CURLOPT_COOKIEFILE, $rc_cookie);
			curl_setopt ($curl, CURLOPT_HEADER, 1);
			$result = curl_exec($curl);
			$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
			curl_close($curl);
			unset($curl);
			$headers = substr($result, 0, $header_size);
			$body = substr($result, $header_size);
			preg_match('/\"tags\"\:(.*)\}\]/', $body, $matches);
			foreach (json_decode($matches[1]."}]", true) as $kolab_tag) {
				$kolab_tags[$kolab_tag["name"]]=[];
				$kolab_tags[$kolab_tag["name"]]["uid"]=[];
				$kolab_tags[$kolab_tag["name"]]["uid"]=$kolab_tag["uid"];	
			}
	
}

function RoundCube_login() {
	global $imap_u,$imap_p,$rc_u,$rc_p,$rc_host,$rc_host_path,$rc_cookie,$rc_newurl,$rc_id;			
	$curl = curl_init();
	curl_setopt($curl,CURLOPT_URL, $rc_host.$rc_host_path);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($curl, CURLOPT_COOKIEJAR, $rc_cookie);
	curl_setopt ($curl, CURLOPT_COOKIEFILE, $rc_cookie);
	curl_setopt ($curl, CURLOPT_HEADER, 1);
	$result = curl_exec($curl);
	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	curl_close($curl);
	unset($curl);
	$headers = substr($result, 0, $header_size);
	$body = substr($result, $header_size);
	$doc = new DOMDocument();
	$doc->validateOnParse = true;
	$doc->loadHTML($body);
	$result=$doc->getElementsByTagName('input');
	foreach ($result as $input) {
		$post_array[$input->getAttribute('name')] = $input->getAttribute('value');
	}
	$post_array["_user"]=$rc_u;
	$post_array["_pass"]=$rc_p;
	$curl = curl_init();
	curl_setopt ($curl, CURLOPT_URL, $rc_host.$rc_host_path);
	curl_setopt ($curl, CURLOPT_POST, count($post_array));
	curl_setopt ($curl, CURLOPT_POSTFIELDS, $post_array);
	curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($curl, CURLOPT_COOKIEJAR, $rc_cookie);
	curl_setopt ($curl, CURLOPT_COOKIEFILE, $rc_cookie);
	curl_setopt ($curl, CURLOPT_HEADER, 1);
	$result = curl_exec($curl);
	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	curl_close($curl);
	unset($curl);
	$headers = substr($result, 0, $header_size);
	$body = substr($result, $header_size);
	preg_match('/roundcubemail\/(.*)\/\?/', $headers, $matches);
	$rc_id=$matches[1];
	preg_match('/Location\: (.*)\r\n/', $headers, $matches);
	$rc_newurl=$rc_host.$matches[1];
}
function IMAP_send($datasend) {
	global $sock;
	$data="";
	fwrite($sock,". ".$datasend);
	while (($buffer = fgets($sock)) !== false) {
		$data=$data.$buffer;
		if (substr($buffer,0,1) == ".") { break; }
	}
	return $data;
}
function IMAP_read() {
	global $sock;
	$data="";
	while (($buffer = fgets($sock)) !== false) {
		$data=$data.$buffer;
		if (substr($buffer,0,1) == ".") { break; }
	}
	return $data;
}

function IMAP_login($host,$port,$user, $password) {
	global $sock, $tag_messages_processed;
	$buffer="";
	if (!$sock = fsockopen($host, $port, $errno, $errstr)) { die("IMAP Connect error.\n"); }
	$response=fgets($sock);
	if (substr($response,0,4) !== '* OK') { die ("IMAP login failed\n");} 
	$response=IMAP_send("LOGIN ".$user." ".$password."\n");
	if (substr($response,0,4) !== '. OK') { die ("IMAP login failed\n");}
}

function IMAP_fetch_tags() {
	global $sock, $tag_messages_processed, $messages;
	$messages=[];
	$response=IMAP_send("SELECT INBOX\n");
	$response=IMAP_send("SEARCH NOT KEYWORD ".$tag_messages_processed."\n");
	if(!preg_match("/\*\sSEARCH\s(.*)\r\n/",$response,$match)) {return;}
	foreach (explode(" ",$match[1]) as $mid) {
		$response=IMAP_send("FETCH ".$mid." (UID)\n");
		if (!preg_match("/\sFETCH\s\(UID\s(.*)\)/",$response,$match1)) { echo "IMAP retuned no UID\n";continue;}
		$msg_uid=$match1[1];
		$messages[$mid]=[];
		$messages[$mid]["tags"]=[];
		$messages[$mid]["uid"]=[];
		$messages[$mid]["uid"]=$match1[1];
		$response=IMAP_send("FETCH ".$mid." (FLAGS)\n");
		if (!preg_match("/\sFETCH\s\(FLAGS\s\((.*)\)\)\r\n/",$response,$match1)) { echo "no FLAGS\n";continue;}
		foreach (explode(" ",$match1[1]) as $tag) {
			if (substr($tag,0,1) !== "\\" and $tag !== $tag_messages_processed and $tag !== "") {
			array_push($messages[$mid]["tags"],$tag);
			}
		}
		if (count($messages[$mid]["tags"]) == 0) { unset($messages[$mid]); }
	}
}
?>
