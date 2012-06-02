<?php 

error_reporting(E_ALL);
require_once 'TwitterBot.class.php';
include 'config.php';

call_user_func( $argv[1] );

function getLists(){

  $listIds = array();
  $res = mysql_query("SELECT * FROM twitter_bot WHERE 1 ORDER BY id ASC");
  while( $row = mysql_fetch_assoc($res) ){  
    
    if( !strlen($row["ghost_lists"]) ){
      continue;
    }
    
    $ids = explode(",", $row["ghost_lists"]);
    $listIds = array_merge( $listIds, $ids );
  }
  
  $res = mysql_query("SELECT * FROM twitter_bot WHERE is_banned = 0 AND is_setup = 1 ORDER BY id ASC");
  while( $row = mysql_fetch_assoc($res) ){  
    
    if( strlen($row["ghost_lists"]) ){
      continue;
    }
    
    $list = $listIds[ rand(0, count($listIds)-1) ];
    mysql_query("UPDATE twitter_bot SET ghost_lists = '" . $list . "' WHERE id = " . $row["id"]);    
  }
  
  file_put_contents(dirname(__FILE__) . "/lists.txt", join("\n", $listIds));
  
}

function initializeProfile(){
  
  $names = json_decode( file_get_contents( dirname(__FILE__) . "/names.txt"), true );
  
  $res = mysql_query("SELECT * FROM twitter_bot WHERE is_banned = 0 AND is_setup = 0 ORDER BY id ASC");
  while( $row = mysql_fetch_assoc($res) ){  
  
      $name = $names[ rand(0, count($names)-1) ];
      $tb = new TwitterBot( $row["oauth_token"], $row["oauth_secret"], $row["id"] );      
      if( $tb->isBanned ){
        echo $row["username"] . " is banned!\n";
        mysql_query("UPDATE twitter_bot SET is_banned = 1 WHERE id = " . $row["id"]);
        continue;
      }
    
      echo "Updating " . $row["username"] . "\n";
      
      $tb->updateProfile( array(
        "name" => $name,
        "description" => ""
      ));
      
      mysql_query("UPDATE twitter_bot SET is_setup = 1 WHERE id = " . $row["id"]);
  }
  
}

function sendTweets(){
  
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
      
      if( $tb->isBanned ){
        mysql_query("UPDATE twitter_bot SET is_banned = 1 WHERE id = " . $bt["id"]);
        die();
      }
      
      $tb->sendTweet( array("lists" => $lists), array("msg_templates" => $PIN_REPLACEMENTS) );
      
      die();
    }
    
  }
  
  pcntl_wait($status);
  
}