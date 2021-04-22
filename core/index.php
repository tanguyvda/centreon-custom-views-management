<?php 

$file = fopen("/var/opt/rh/rh-php72/log/php-fpm/ccvm", "a") or die ("Unable to open file!");
// fwrite($file, print_r($centreon,true));

$path = $centreon_path . 'www/modules/centreon-custom-views-management/core/';
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, './', $centreon_path);
$template->display('index.ihtml');

if (!isset($centreon)) {
  exit();
}

if (!$centreon->user->admin) {
  echo "<pre>you're not admin</pre>";
} else {
  echo "<pre>you're admin</pre>";
}


fclose($file);