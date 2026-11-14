<?php
$web=getenv('ESIGN_TEST_WEB');$root=getenv('ESIGN_TEST_ROOT');if(!$web||!$root||!is_file($root.'/.sample_esign_fixture'))exit(2);
putenv('FBP_FFM_LOG_DISABLE=1');
require_once $web.'/fbp/interface/Controller.php';require_once $web.'/fbp/lib/Dirs.php';require_once $web.'/fbp/lib/Controller_class.php';require_once $web.'/fbp/lib/fixed_file_manager/fixed_file_manager.php';require_once $web.'/fbp/lib/SmartyBootstrap.php';fbp_include_smarty();require_once $web.'/fbp/lib/ValueFormatter.php';require_once $web.'/fbp/lib/FrameworkTheme.php';require_once $web.'/fbp/lib/I18nSimple.php';require_once $web.'/fbp/lib/pdfmaker/pdfmaker_class.php';require_once $web.'/classes/app/sample_esign/sample_esign.php';require_once $web.'/classes/app/sample_esign_public/sample_esign_public.php';
function cookie_path(){return '/';}function startsWith($s,$v){return str_starts_with($s,$v);}function endsWith($s,$v){return str_ends_with($s,$v);}
function service(): SampleESign {return new SampleESign(function($c,$to,$key,$v){global $root;if(!str_ends_with($to,'@example.invalid'))throw new RuntimeException('Fixture only');if(file_exists($root.'/fail_mail')&&str_contains($to,'peer'))throw new RuntimeException('Simulated mail failure');file_put_contents($root.'/mails.jsonl',json_encode(['to'=>$to,'key'=>$key,'v'=>$v],JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND);});}
class ESController extends Controller_class {
    public array $vars=[],$out=[];
    function __construct(){global $root,$web;$s=new Smarty();$s->setCompileDir($root.'/compile');$s->escape_html=true;$s->error_reporting=E_ALL&~E_NOTICE&~E_WARNING;fbp_register_smarty_plugins($s,$web.'/fbp/lib/smarty_plugins_org/');$s->assign('base_template_dir',$web.'/fbp/Templates');$s->assign('lang','jp');$s->assign('class','public_pages');parent::__construct('sample_esign',$s,'public');$this->set_data_dir($root.'/data');$this->set_windowcode('esignFixture');$this->set_session('testserver',true);$s->assign('_ctl',$this);$s->assign('ctl',$this);}
    function get_setting(){return ['secret'=>'isolated-esign-test-key','dsp_disabled'=>0];}
    function encrypt($s){return 'fixture:'.$s;}
    function decrypt($s){return is_string($s)&&str_starts_with($s,'fixture:')?substr($s,8):null;}
    function get_temp_dir(){global $root;return $root;}
    function assign($k,$v){parent::assign($k,$v);$this->vars[$k]=$v;}
    function get_APP_URL($class=null,$function=null,$params=null,$options=[]){return 'http://127.0.0.1:18089/app?'.http_build_query(['class'=>$class,'function'=>$function]+($params??[]));}
    function show_multi_dialog($name,$template,$title='',$width=600,$bar=null,$options=[]){$this->out['dialog']=['name'=>$name,'html'=>$this->smarty()->fetch($template),'title'=>$title];}
    function close_multi_dialog($name,$class=null){$this->out['closed']=$name;}
    function reload_area($selector,$template){$this->out['reload'][$selector]=$this->smarty()->fetch($template);}
    function clear_error_message(){$this->out['errors']=[];}
    function res_error_message($k,$v){$this->out['errors'][$k]=$v;}
    function show_notification_text($t,$time=2,$background='#4B70FF',$color='#FFF',$fontsize=24,$width=600){$this->out['notice']=$t;}
    function show_public_pages($template,$head=null,$header=null,$footer=null,$options=[]){echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';if($head)echo $this->smarty()->fetch($head);echo $this->smarty()->fetch($template);}
    function reload_work_area(){$this->out['reload_work_area']=true;}
    function res_saved_file($name,$download_name=null){header('Content-Type: '.(str_ends_with($name,'.zip')?'application/zip':'application/pdf'));header('Content-Disposition: attachment; filename="'.($download_name??'file').'"');readfile($this->get_saved_filepath($name));exit;}
}
class ESAdmin extends sample_esign {protected function service(): SampleESign{return service();}}
class ESPublic extends sample_esign_public {protected function service(): SampleESign{return service();}}
function actor(ESController $c,int $id=1): array {$c->set_session('login',true);$c->set_session('app_admin',$id!==3);$c->set_session('user_id',$id);return $c->db('user','user')->get($id);}
function initRoot(): void {global $root,$web;if(file_exists($root.'/data'))return;mkdir($root.'/data/_common/fmt',0700,true);mkdir($root.'/compile',0700,true);mkdir($root.'/sessions',0700,true);foreach(glob($web.'/classes/data/_common/fmt/*.fmt') as $p)copy($p,$root.'/data/_common/fmt/'.basename($p));$c=new ESController();$c->db('user','user');SampleESign::prepare($c);foreach([1=>'作成管理者',2=>'別の管理者',3=>'一般ユーザー'] as $id=>$name){$r=['id'=>$id,'status'=>0,'type'=>$id===3?1:0,'name'=>$name,'email'=>($id===2?'peer':'user'.$id).'@example.invalid'];$c->db('user','user')->insert($r);}$pdf=$c->create_pdfmaker();$pdf->addPage();$pdf->addText('契約サンプルPDF',['align'=>'L']);file_put_contents($root.'/input.pdf',$pdf->get_pdf_data());$c->close_all_db();}
