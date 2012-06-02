<?php 

$base = "pinkpanther";
echo "email, username\n";

for($i=1000; $i<=1100; $i++){
  echo $base.$i."@hypervipr.mailgun.org," . $base.$i . "\n"; 
}