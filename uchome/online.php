﻿<?php
include_once('common.php');
if(empty($space))exit();
$name = nick($space);
require 'http_client.php';
$platform = gp('platform');

$stranger_ids = ids_except($space['uid'], ids_array(gp("stranger_ids")));//陌生人
$friend_ids = ids_array($space['friends']); //好友
$buddy_ids = ids_array(gp("buddy_ids"));//正在聊天的联系人

$new_messages = find_new_message();//查找离线消息
for($i=0;$i<count($new_messages);$i++){
        $msg_uid = $new_messages[$i]["from"];
        array_push($buddy_ids, $msg_uid);
        array_push($stranger_ids, $msg_uid);
}
//Login webim server.
$nick = to_utf8($name);
if($platform == 'uchome'){
	$setting = setting();
}

$block_list = is_array($setting->block_list) ? $setting->block_list : array();
switch($platform){
case 'uchome':
	$rooms = find_room();
	$room_ids = array();
	break;
case 'discuz':
	$rooms = find_room(gp("room_ids"));
	$room_ids = ids_array($room);
	break;
default:
	break;
}
foreach($rooms as $key => $value){
	if(in_array($key, $block_list)){
		$rooms[$key]['blocked'] = true;
	}else
		array_push($room_ids, $key);
}

switch($platform){
case 'uchome':
$data = array ('rooms'=> join(',', $room_ids),'buddies'=>join(',', array_unique(array_merge($friend_ids, $buddy_ids, $stranger_ids))), 'domain' => $_IMC['domain'], 'apikey' => $_IMC['apikey'], 'endpoint'=> $space['uid'], 'nick'=>to_unicode($nick));
break;
case 'discuz':
$data = array ('rooms'=> join(',', $room_ids),'buddies'=>join(',', array_unique(array_merge($friend_ids, $stranger_ids))), 'domain' => $_IMC['domain'], 'apikey' => $_IMC['apikey'], 'endpoint'=> $space['uid'], 'nick'=>to_unicode($nick));
break;
default:
break;
}
$client = new HttpClient($_IMC['imsvr'], $_IMC['impost']);
$client->post('/presences/online', $data);
$pageContents = $client->getContent();
//TODO: handle errors!
$pageData  = json_decode($pageContents);
if($client->status !="200"||empty($pageData->ticket)){
        $ticket ="";
}else
        $ticket = $pageData->ticket;
if(empty($ticket)){
        //登录失败
        echo '{status: "'.$client->status.'", "errorMsg":"'.$pageContents.'"}';
        exit();
}
$buddy_online_ids = ids_array($pageData->buddies);//在线好友列表ids
$clientnum = $pageData->clientnum;
$rooms_num = $pageData->roominfo;
if(is_object($rooms_num)){
	foreach($rooms_num as $key => $value){
		$rooms[$key]['count'] = $value;
	}
}

$output = array();
$output['buddy_online_ids'] = join(",", $buddy_online_ids);
$output['clientnum'] = $clientnum;
$output['server_time'] = microtime(true)*1000;

$output['user']=array('id'=>$space['uid'], 'name'=>to_utf8($name), 'pic_url'=>avatar($space['uid'],'small',true), 'status'=>'', 'presence' => 'online', 'status_time'=>'', 'url'=>'space.php?uid='.$space['uid']);//用户信息

$imserver = 'http://'.$_IMC['imsvr'].':'.$_IMC['impoll'];
$output['connection'] = array('domain' => $_IMC['domain'], 'ticket'=>$ticket, 'server'=>$imserver);//服务器连接

$output['new_messages'] = $new_messages;
$output['buddies'] = find_buddy($buddy_ids);
$output['rooms'] = $rooms;
$output['histories'] = find_history($buddy_ids);

new_message_to_histroy(); //新消息转到历史记录

echo json_encode($output);
?>