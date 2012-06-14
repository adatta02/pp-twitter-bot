<?php 

$base = "pinkpoodles";
echo "email, link\n";

for($i=6000; $i<=6100; $i++){
  $email = $base . $i . "@hypervipr.mailgun.org";
  $link = "http://pinkpinterest.com/twitter/confirm.php?email=" . $email;
  echo $email . "," . $link . "\n"; 
}