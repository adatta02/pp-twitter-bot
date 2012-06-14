#!/usr/bin/php
<?php 

require_once 'config.php'; 
require_once('MimeMailParser/MimeMailParser.class.php');

$total = "";
$stdin = fopen('php://stdin', 'r');
while (($buffer = fgets($stdin)) !== false) {
	$total .= $buffer;
}
$fname = tempnam(sys_get_temp_dir(), 'hpv');
file_put_contents( $fname, $total );

$Parser = new MimeMailParser();
$Parser->setPath( $fname );

$msgInfo = array(
  "to" => $Parser->getHeader('to'),
  "from" => $Parser->getHeader('from'),
  "subject" => $Parser->getHeader('subject'),
  "plain_body" => $Parser->getMessageBody('text'),
  "html_body" => $Parser->getMessageBody('html') 
);

$userInfo = parseConfirmationEmail ( $msgInfo ); 

$request = json_encode( $msgInfo );

if (is_null ( $userInfo )) {
  mysql_query ( "INSERT INTO email (request) VALUES ('$request')" );
} else {
  mysql_query ( "INSERT INTO email (request, to_email, link)
      				VALUES ('$request', '" . mysql_real_escape_string ( $userInfo ["email"] ) 
                            . " ', '" . mysql_real_escape_string ( $userInfo ["link"] ) . "')" );
}

function parseConfirmationEmail( $requestArray ){
  
  $userInfo = array( "email" => $requestArray["to"], "password" => "asdfasdf12" );
  preg_match( "/(https.+\/confirm_email\/.+)/", $requestArray["plain_body"], $matches );
    
  if( count($matches) != 2 ){
    return null;
  }
  
  $userInfo["action"] = "confirmEmail";
  $userInfo["link"] = trim($matches[1]);
  
  return $userInfo;
}
