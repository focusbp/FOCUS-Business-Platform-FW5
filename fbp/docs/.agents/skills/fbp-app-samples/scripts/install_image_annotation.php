<?php
/** Copy into an existing app class; no routes, DB, settings, media or external calls. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    $root=isset($argv[1])?realpath($argv[1]):false;$class=$argv[2]??'';
    if(count($argv)!==3||!$root||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$class))throw new RuntimeException('Usage: php install_image_annotation.php <app-source-root> <existing-class>');
    $destination=$root.'/classes/app/'.$class;
    if(!is_dir($destination)||is_link($destination)||is_link($root.'/classes')||is_link($root.'/classes/app')||is_link($destination.'/Templates'))throw new RuntimeException('An existing, non-symlink app class is required.');
    $assets=dirname(__DIR__).'/assets/image-annotation';
    $manifest=json_decode(file_get_contents($assets.'/image-annotation.json'),true,512,JSON_THROW_ON_ERROR);
    foreach($manifest['files'] as $file=>$target){
        if(!preg_match('~^[A-Za-z0-9_.]+$~D',$file)||!preg_match('~^(Templates/)?[A-Za-z0-9_.]+$~D',$target)||str_contains($file,'..')||str_contains($target,'..')||!is_file($assets.'/'.$file))throw new RuntimeException('Invalid/missing manifest asset.');
        if(file_exists($destination.'/'.$target)||is_link($destination.'/'.$target))throw new RuntimeException('Refusing to overwrite: '.$target);
    }
    if(!is_dir($destination.'/Templates')&&!mkdir($destination.'/Templates',0775,true))throw new RuntimeException('Cannot create Templates.');
    foreach($manifest['files'] as $file=>$target)if(!copy($assets.'/'.$file,$destination.'/'.$target))throw new RuntimeException('Cannot copy: '.$file);
    echo 'Installed image annotation ('.count($manifest['files'])." files). Include image_annotation_editor.tpl and connect onSubmit. No DB or storage configured.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
