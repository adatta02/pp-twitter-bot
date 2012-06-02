<?php 

error_reporting(E_ALL);
require_once 'twitteroauth/OAuth.php';
require_once 'twitteroauth/twitteroauth.php';

class TwitterBot {
  
  private static $MAX_AT_MSG = 12;
  
  private $token;
  private $secret;
  private $twitterUser;
  private $memcache;
  private $tweetsToday;
  private $botId;
  public $isBanned = false;
  public  $oauthClient;
  
  public function TwitterBot( $token, $secret, $botId ){
    
    include 'config.php';
    
    $this->botId = $botId;
    $this->token = $token;
    $this->secret = $secret;
    
    $this->oauthClient = new TwitterOAuth(OAUTH_KEY, OAUTH_SECRET, $token, $secret);
    $this->twitterUser = $this->oauthClient->get("account/verify_credentials");    
    
    if( array_key_exists("error", $this->twitterUser) && $this->twitterUser["error"] ){
      $this->isBanned = true;
      return false;
    }
    
    $this->memcache = new Memcache();
    $this->memcache->connect("localhost", 11211);
    
    $tweetsToday = $this->memcache->get( "twitter_info_" . $this->twitterUser["id"] );        
    if( !$tweetsToday ){ 
      $tweetsToday = array( ); 
    }
    
    $today = date("Y-m-d");
    if( !array_key_exists($today, $tweetsToday) ){
      $tweetsToday = array( );
      $tweetsToday[ $today ] = array( "at_msg" => 0, "ghost_msg" => 0, "last_at_index" => -1, "last_was_at" => false );
      $this->memcache->set("twitter_info_" . $this->twitterUser["id"], $tweetsToday );
    }
    
    $this->tweetsToday = $tweetsToday[ $today ];
    
    echo "Booting " . $this->twitterUser["screen_name"] . " at " . date("r") . "<br>\n";    
    $arr = print_r( $this->tweetsToday, true );
    echo nl2br( $arr );    
  }
  
  public function updateProfile( $params ){
    
    $imgData = base64_encode( file_get_contents( dirname(__FILE__) . "/pinkpinterest-logo.png") );
    $bgImageData = base64_encode( file_get_contents( dirname(__FILE__) . "/bg.gif") );
    
    $this->oauthClient->post("account/update_profile", array(
      "name" => $params["name"],
      "description" => "@pinterest in #pink! &lt;3 &lt;3",      
    ));
    
    $this->oauthClient->post("account/update_profile_image", array(
      "image" => $imgData
    ));
    
    $this->oauthClient->post("account/update_profile_background_image", array(
      "image" => $bgImageData,
      "tile" => 1
    ));
    
  }
  
  public function sendTweet( $ghostConfig = array(), $atMsgConfig = array() ){
    
    $sleep = rand(600, 900);
    echo "Sleeping " . $sleep . " seconds...<br>";
    sleep( $sleep );
    
    $isAtMsg = ( rand(0, 2) == 1 ) ? true : false;
    if( $isAtMsg && $this->tweetsToday["at_msg"] > self::$MAX_AT_MSG ){
      $isAtMsg = false;
    }
    
    if( $this->tweetsToday["last_was_at"] ){
        $isAtMsg = false;
    }
    
    if( $isAtMsg ){
      $c = 0;
      do{
        $res = $this->sendAtMessageTweet( $atMsgConfig );
        $c ++;
      }while( !$res && $c < 3 );
    }else{
      $this->sendGhostMessageTweet( $ghostConfig );
    }
    
  }
  
  public function sendGhostMessageTweet( $ghostConfig = array() ){
    
    $tweets = array();
    foreach( $ghostConfig["lists"] as $id ){
      $result = $this->oauthClient->get("lists/statuses", array("list_id" => $id, "include_entities" => true));
      $tweets = array_merge( $tweets, $result );
    }
    
    $targetTweet = null;
    foreach( $tweets as $tw ){
      
      if( count($tw["entities"]["urls"]) != 0 || count($tw["entities"]["user_mentions"]) != 0 ){
        continue;
      }
      
      if( !$this->getIsTweetGhosted($tw["id"]) ){
        $targetTweet = $tw;
        break;
      }
      
    }
    
    // couldn't find anything to tweet :(
    if( !$targetTweet ){
      return false;
    }
    
    echo $targetTweet["text"] . "<br>\n";
    
    $this->setTweetIsGhosted( $targetTweet["id"], $targetTweet["text"] );
    $this->tweetsToday["ghost_msg"] += 1;
    $this->tweetsToday["last_was_at"] = false;
    $this->serializeTweetStats();    
    
    $this->oauthClient->post('statuses/update', array('status' => $targetTweet["text"]));
  }
  
  public function sendAtMessageTweet( $atMsgConfig = array() ){
    
    $result = $this->oauthClient->get("search", array("q" => '"via @pinterest"', "include_entities" => true));
    
    $tweetToParams = array ();
    $userIdTweets = array ();
    
    foreach ( $result ["results"] as $rs ) {
      
      if (count ( $rs ["entities"] ["urls"] ) == 0) {
        continue;
      }
      
      if ( $this->getIsUserMessaged($rs ["from_user_id"]) ) {
        continue;
      }
      
      foreach ( $rs ["entities"] ["urls"] as $url ) {
        
        // pop out the Pinterest URL
        if (strpos ( $url ["expanded_url"], "http://pinterest.com/pin/" ) !== false) {
          $url ["expanded_url"] = rtrim ( $url ["expanded_url"], "/" );
          $pinId = basename ( $url ["expanded_url"] );
          break;
        }
      
      }
      
      $userIdTweets [$rs ["from_user_id"]] = array ("pin" => $pinId, "id" => $rs ["id"], "status" => $rs ["text"] );
    }
    
    $targetUsername = null;
    $targetUserId = null;    
    
    // grab profile info for the users
    $userProfileData = $this->oauthClient->post ( "users/lookup", 
                                            array ("user_id" => join ( ", ", array_keys ( $userIdTweets ) ) ) );
        
    foreach ( $userProfileData as $user ) {      
      if ($user ["followers_count"] > 200  
            && ($user ["followers_count"] / $user ["friends_count"] > .65)) {
        $targetUserId = $user ["id"];
        $targetUsername = $user ["screen_name"];
        break;
      }
    }
    
    // couldn't find anyone we like so bail.
    if( is_null($targetUserId) ){
      return false;
    }
    
    $pinIndex = null;
    do{
        $pinIndex = rand(0, count($atMsgConfig["msg_templates"]) - 1);
    }while( $pinIndex == $this->tweetsToday["last_at_index"] );
    
    $targetUrl = "http://pinkpinterest.com/pin/" . $userIdTweets[$targetUserId]["pin"];
    $targetUrl = $this->getShortUrl( $targetUrl );    
    if( strlen($targetUrl) == 0 ){
      return false;
    }
    
    $status = $atMsgConfig["msg_templates"][ $pinIndex ];
    $status = str_replace("%user%", "@" . $targetUsername, $status);
    $status = str_replace("%url%", $targetUrl, $status);
        
    $this->setUserIsMessaged( $targetUserId, $status );
    $this->tweetsToday["at_msg"] += 1;
    $this->tweetsToday["last_at_index"] = $pinIndex;
    $this->tweetsToday["last_was_at"] = true;
    $this->serializeTweetStats();    
    
    echo $status . "<br>\n";
    
    $this->oauthClient->post('statuses/update', array('status' => $status, 
    						 'in_reply_to_status_id' => $userIdTweets[$targetUserId]["id"]));
    
    return true;
  }
  
  public function getShortUrl( $url ){
    
    $url = urlencode( $url );
        
    $ch = curl_init ();
    curl_setopt ( $ch, CURLOPT_URL, "http://api.bit.ly/shorten?version=2.0.1&login=pinkpinterest&apiKey=R_f13a48e20df89ac0f8c18b38d649b4f5&longUrl=" . $url );
    curl_setopt ( $ch, CURLOPT_FAILONERROR, false );
    curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt ( $ch, CURLOPT_TIMEOUT, 300 );
    $result = json_decode(curl_exec ( $ch ), true);
    curl_close ( $ch );
    
    $keys = array_keys( $result["results"] );
    return $result["results"][ $keys[0] ]["shortUrl"];
  }
  
  private function serializeTweetStats(){
    $today = date("Y-m-d");
    $arr = array( $today => $this->tweetsToday );
    $this->memcache->set("twitter_info_" . $this->twitterUser["id"], $arr);
  }
  
  public function getIsTweetGhosted( $id ){
    $res = mysql_fetch_assoc( mysql_query("SELECT COUNT(*) AS c FROM sent_tweet WHERE twitter_id = '". $id . "'") );
    return $res["c"] > 0 ? true : false;
  }
  
  public function setTweetIsGhosted( $id, $msg ){    
    mysql_query("INSERT INTO sent_tweet (twitter_id, tweet, twitter_bot_id) 
    				VALUES ('" . $id . "', '" . $msg . "', '" . $this->botId . "')");    
  }
  
  public function setUserIsMessaged( $userId, $msg ){    
    mysql_query("INSERT INTO sent_tweet (at_user_id, tweet, twitter_bot_id) 
    				VALUES ('" . $userId . "', '" . $msg . "', '" . $this->botId . "')");    
  }
  
  public function getIsUserMessaged( $userId ){
    $res = mysql_fetch_assoc( mysql_query("SELECT COUNT(*) AS c FROM sent_tweet WHERE at_user_id = '". $userId . "'") );
    return $res["c"] > 0 ? true : false;
  }
  
}