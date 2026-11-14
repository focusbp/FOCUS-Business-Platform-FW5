<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=isset($argv[1])?realpath($argv[1]):false;$class=$argv[2]??'';
if(!$root||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$class)||!is_dir($root.'/classes/app/'.$class)){
    fwrite(STDERR,"Usage: php install_standard_form.php <app-source-root> <existing-class>\n");exit(1);
}
$assets=dirname(__DIR__).'/assets/standard-form';
$manifest=json_decode(file_get_contents($assets.'/standard-form.json'),true,512,JSON_THROW_ON_ERROR);
$directory=$root.'/classes/app/'.$class.'/Templates';
foreach($manifest['files'] as $file)if(file_exists($directory.'/'.$file)||is_link($directory.'/'.$file)){fwrite(STDERR,"Template already exists; adapt it without overwriting.\n");exit(1);}
if(!is_dir($directory)&&!mkdir($directory,0775,true))throw new RuntimeException('Cannot create template directory');
foreach($manifest['files'] as $file)if(!copy($assets.'/'.$file,$directory.'/'.$file))throw new RuntimeException('Cannot copy template');
echo "Installed standard_form.tpl. Assign standard_form_* variables and connect your existing save handler.\n";
