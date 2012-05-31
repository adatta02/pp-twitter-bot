<?php 

file_put_contents( dirname(__FILE__) . "/gun.txt", json_encode($_REQUEST) );

if( array_key_exists("Subject", $_REQUEST) 
    && strpos($_REQUEST["Subject"], "Confirm your Twitter account") !== false ){
  parseConfirmationEmail( $_REQUEST );
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
}