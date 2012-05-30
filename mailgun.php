<?php 

$arr = json_decode( file_get_contents("/home/ubuntu/pp-twitter-bot/gun.txt"), true );

if( strpos($arr["subject"], "Confirm your Twitter account") !== false ){
  parseConfirmationEmail( $arr );
}

function parseConfirmationEmail( $requestArray ){
  
  $userInfo = array( "email" => $requestArray["recipient"], "password" => "asdfasdf12" );
  preg_match( "/(https.+\/confirm_email\/.+)/", $requestArray["body-plain"], $matches );
  
  if( count($matches) != 2 ){
    exit(0);
  }
  
  $userInfo["action"] = "confirmEmail";
  $userInfo["link"] = trim($matches[1]);
  
  echo json_encode( $userInfo );
}