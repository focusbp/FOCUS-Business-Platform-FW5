<?php
/** Run isolated real-FFM tests. Never writes business rows into the supplied runtime. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$web=realpath($argv[1]??'');$parent=realpath($argv[2]??'');
if(!$web||!is_file($web.'/fbp/cli.php')||!$parent||!is_dir($parent)){fwrite(STDERR,"Usage: php test_simple_electronic_contract.php <configured-test-runtime> <temporary-parent-directory>\n");exit(2);}
$root=$parent.'/fbp_esign_test_'.bin2hex(random_bytes(12));mkdir($root,0700);file_put_contents($root.'/.sample_esign_fixture','isolated');
$env=getenv();$env['ESIGN_TEST_WEB']=$web;$env['ESIGN_TEST_ROOT']=$root;
$p=proc_open([PHP_BINARY,dirname(__DIR__).'/assets/simple-electronic-contract/tests/domain.php'],[1=>STDOUT,2=>STDERR],$pipes,$web,$env);$exit=is_resource($p)?proc_close($p):1;
$remove=static function(string $path)use(&$remove):void{if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};$remove($root);exit($exit);
