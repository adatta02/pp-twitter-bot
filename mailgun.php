<?php 
require_once 'config.php';

if( $argv[1] == "saved" ){
  
  $res = mysql_query("SELECT * FROM email WHERE is_processed = 0 ORDER BY id ASC");
  while( $row = mysql_fetch_assoc( $res ) ){

   echo date("r") . "\n";
    
    $msg = json_decode( $row["request"], true );
    
    if( array_key_exists("Subject", $msg)
      && strpos($msg["Subject"], "Confirm your Twitter account") !== false ){
      parseConfirmationEmail( $msg );
    }
    
    mysql_query("UPDATE email SET is_processed = 1 WHERE id = " . $row["id"]);
    
  }
  
}else{

  if( count(array_keys($_REQUEST)) ){
    $request = mysql_real_escape_string( json_encode($_REQUEST) );
    mysql_query("INSERT INTO email (request) VALUES ('$request')");
  }

}

function parseConfirmationEmail( $requestArray ){
  
  $userInfo = array( "email" => $requestArray["recipient"], "password" => "asdfasdf12" );
  preg_match( "/(https.+\/confirm_email\/.+)/", $requestArray["body-plain"], $matches );
    
  if( count($matches) != 2 ){
    exit(0);
  }
  
  $userInfo["action"] = "confirmEmail";
  $userInfo["link"] = trim($matches[1]);
  
  $jsOutput = " var phantomConfig = " .  json_encode( $userInfo ) . ";";  
  file_put_contents( dirname(__FILE__) . "/test.js", $jsOutput );
  
  chdir( dirname(__FILE__) );
  $path = dirname(__FILE__);
  
  echo $requestArray["recipient"] . "\n";
  exec( "/usr/local/bin/phantomjs $path/phantomTwitter.js $path/test.js", $output );
  echo join("\n", $output);
}
