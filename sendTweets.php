<?php 

error_reporting(E_ALL);
require_once 'TwitterBot.class.php';

$con = mysql_connect("localhost", "pink", "pink");
mysql_select_db("pink");

$PIN_REPLACEMENTS = array();
$res = mysql_query("SELECT * FROM twitter_message WHERE 1 ORDER BY id ASC");
while( $row = mysql_fetch_assoc($res) ){
  $PIN_REPLACEMENTS[] = $row["message"];
}

$bots = array();
$res = mysql_query("SELECT * FROM twitter_bot WHERE is_banned = 0 AND is_setup = 1");
while( $row = mysql_fetch_assoc($res) ){  
  $bots[] = $row;
}

mysql_close( $con );
foreach( $bots as $bt ){

  $pid = pcntl_fork();
  if ($pid == -1) {
       die('could not fork');
  } else if ($pid) {
       
  } else {
    
    $lists = explode(",", $bt["ghost_lists"]);
    for($i=0; $i<count($lists); $i++){
      $lists[ $i ] = trim($lists[ $i ]);
    }
    
    $tb = new TwitterBot( $bt["oauth_token"], $bt["oauth_secret"], $bt["id"] );
    $tb->sendTweet( array("lists" => $lists), array("msg_templates" => $PIN_REPLACEMENTS) );
    
    die();
  }
  
}

pcntl_wait($status);