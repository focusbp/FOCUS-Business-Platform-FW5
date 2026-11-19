<?php
/** Isolated real FFM; all mail and Square calls are injected. Run from a test runtime. */
if(PHP_SAPI!=='cli')exit(1);
$web=realpath($argv[1]??'');$parent=realpath($argv[2]??'');
if(!$web||!is_file($web.'/fbp/lib/fixed_file_manager/fixed_file_manager.php')||!$parent||!is_dir($parent))throw new RuntimeException('Usage: test_email_card_self_service.php <test-runtime> <existing-temporary-parent>');
$root=$parent.'/email-card-'.bin2hex(random_bytes(5));mkdir($root.'/data',0700,true);mkdir($root.'/fmt',0700);
$assets=dirname(__DIR__).'/assets/email-card-self-service';
class Controller_class {static function getInstance(){return null;}}
putenv('FBP_FFM_LOG_DISABLE=1');require $web.'/fbp/lib/fixed_file_manager/fixed_file_manager.php';require $web.'/fbp/lib/Mcrypt.php';
require $assets.'/classes/app/sample_email_card/Profile.php';
file_put_contents($root.'/fmt/sample_email_card_member.fmt',"id,24,N\nname,255,T\nemail,254,T\nbilling_description,1000,T\nmonthly_fee,24,N\nstatus,1,N\nsquare_customer,255,T\nsquare_card,255,T\ncard_registered_at,24,N\n");
copy($assets.'/classes/app/sample_email_card/fmt/rate.fmt',$root.'/fmt/rate.fmt');
file_put_contents($root.'/fmt/email_format.fmt',"id,24,N\nkey,255,T\nsubject,255,T\nbody,4000,T\n");
class Controller {
    public array $post=[],$session=[],$dbs=[];public string $card='fake-card';public int $cardCalls=0,$dialogMailCalls=0;public array $vars=[],$dialogs=[];
    function __construct(private string $root){}
    function db($name,...$unused){return $this->dbs[$name]??=new fixed_file_manager($name,$this->root.'/data',$this->root.'/fmt');}
    function get_setting(){return ['secret'=>'isolated-sample-test-secret','iv'=>'sample-test-iv','timezone'=>'UTC'];}
    function get_session($key){return $this->session[$key]??null;}
    function set_session($key,$value){$this->session[$key]=$value;}
    function POST($key){return $this->post[$key]??null;}
    function encrypt($value){$s=$this->get_setting();return (new Mcrypt($s['secret'],$s['iv']))->encrypt($value);}
    function decrypt($value){$s=$this->get_setting();return @((new Mcrypt($s['secret'],$s['iv']))->decrypt($value));}
    function get_APP_URL($class,$function,$params){return 'https://example.invalid/'.$class.'*'.$function.'?'.http_build_query($params);}
    function close_all_db(){foreach($this->dbs as $db)$db->close();$this->dbs=[];}
    function assign($key,$value){$this->vars[$key]=$value;}
    function show_multi_dialog($name,$template,$title,$width){$this->dialogs[]=[$name,$template];}
    function send_mail_text(...$args){$this->dialogMailCalls++;}
    function square_regist_customer(...$args){return 'fake-customer';}
    function square_regist_card($customer){$this->cardCalls++;return $this->card;}
}
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REMOTE_ADDR']='192.0.2.1';$checks=0;
function check($value,$label){global $checks;$checks++;if(!$value)throw new RuntimeException($label);}
function reject(callable $fn,$label){try{$fn();}catch(DomainException $e){check(true,$label);return;}throw new RuntimeException('Unexpected accept: '.$label);}
$c=new Controller($root);$mail=[];$verification=new SampleEmailCardVerification(function($ctl,$to,$code)use(&$mail){$mail[]=[$to,$code];});$service=new SampleEmailCardProfile($verification);
function member($c,array $extra=[]){$r=array_replace(['name'=>'Sample','email'=>'','status'=>1,'monthly_fee'=>12345,'square_card'=>'','square_customer'=>''],$extra);$c->db('sample_email_card_member')->insert($r);return $c->db('sample_email_card_member')->get($r['id']);}
function token($c,$r){parse_str(parse_url(SampleEmailCardProfile::url($c,$r),PHP_URL_QUERY),$q);return $q['token'];}
function openMember($c,$s,$r){$s->open($c,token($c,$r));$c->post=['csrf'=>$c->session[SampleEmailCardProfile::STATE]['csrf']];}
function authorize($c,$s,$r){global $mail;foreach($c->db('rate','sample_email_card')->getall() as $v){$v['count']=0;$v['last_at']=0;$c->db('rate','sample_email_card')->update($v);}openMember($c,$s,$r);$c->post['email']='initial-'.($r['id']).'@example.invalid';$s->sendIdentity($c);$c->post['code']=end($mail)[1];$s->verifyIdentity($c);}
$r=member($c);$old=token($c,$r);openMember($c,$service,$r);
reject(fn()=>$service->beginCard($c),'card requires identity');$c->post['email']='initial@example.invalid';$service->sendIdentity($c);
$before=$c->session[SampleEmailCardProfile::STATE]['identity'];$sent=count($mail);$service->sendIdentity($c);check(count($mail)===$sent&&$c->session[SampleEmailCardProfile::STATE]['identity']===$before,'duplicate send keeps code and expiry');
$c->post['code']='bad';reject(fn()=>$service->verifyIdentity($c),'wrong code');$service->sendIdentity($c);check($c->session[SampleEmailCardProfile::STATE]['identity']['attempts']===1,'duplicate send preserves attempts');
check($c->db('sample_email_card_member')->get($r['id'])['email']==='','not saved before verification');
$c->post['code']=end($mail)[1];$service->verifyIdentity($c);check($c->db('sample_email_card_member')->get($r['id'])['email']==='initial@example.invalid','initial email verified');
$params=$service->beginCard($c);check($service->saveCard($c,$params)===null,'initial card does not bill by default');$r=$c->db('sample_email_card_member')->get($r['id']);check($r['square_card']==='fake-card'&&(int)$r['card_registered_at']>0,'card and initial timestamp saved');reject(fn()=>$service->saveCard($c,$params),'callback replay');
$c->post['email']='new@example.invalid';$service->sendEmail($c);$pending=$c->session[SampleEmailCardProfile::STATE]['pending'];$sent=count($mail);$service->sendEmail($c);check(count($mail)===$sent&&$pending===$c->session[SampleEmailCardProfile::STATE]['pending'],'duplicate email change keeps code');
$c->post['code']=end($mail)[1];$service->verifyEmail($c);check($c->db('sample_email_card_member')->get($r['id'])['email']==='new@example.invalid','email changed');reject(fn()=>$service->open($c,$old),'old link invalidated');
reject(fn()=>$service->open($c,$c->encrypt((string)$r['id'])),'encrypted ID is not a grant');
$r=member($c,['email'=>'stored@example.invalid']);authorize($c,$service,$r);check(end($mail)[0]==='stored@example.invalid','stored email overrides posted email');
class SampleFullInitialPayment extends SampleEmailCardPayment {
    public int $calls=0;public array $seen=[];
    function initialEnabled(): bool{return true;}
    function ready(): bool{return true;}
    function chargeInitial(Controller $ctl,array $member,string $month,string $operation): array{
        $key=$member['id'].':'.$month;
        if(isset($this->seen[$key]))return $this->seen[$key];
        $this->calls++;return $this->seen[$key]=['state'=>'success','amount'=>(int)$member['monthly_fee']];
    }
}
$payment=new SampleFullInitialPayment();$full=new SampleEmailCardProfile($verification,$payment);$r=member($c);authorize($c,$full,$r);$params=$full->beginCard($c);check(str_contains($params['square_button_label'],'12,345'),'full price shown');$result=$full->saveCard($c,$params);check($result['amount']===12345&&$payment->calls===1,'full initial payment adapter receives configured amount');
$params=$full->beginCard($c);$full->saveCard($c,$params);check($payment->calls===1,'replacement makes no extra charge');
$u=['id'=>$r['id'],'square_card'=>''];$c->db('sample_email_card_member')->update($u);$params=$full->beginCard($c);$full->saveCard($c,$params);check($payment->calls===1,'clearing a card does not reset first registration');
class SampleUnconnectedInitialPayment extends SampleEmailCardPayment {function initialEnabled(): bool{return true;}}
$unconnected=new SampleEmailCardProfile($verification,new SampleUnconnectedInitialPayment());$r=member($c);authorize($c,$unconnected,$r);$calls=$c->cardCalls;reject(fn()=>$unconnected->beginCard($c),'unconnected charge blocked before Square');check($c->cardCalls===$calls,'unconnected charge has no external side effect');
$r=member($c);authorize($c,$service,$r);$params=$service->beginCard($c);$u=['id'=>$r['id'],'monthly_fee'=>999];$c->db('sample_email_card_member')->update($u);reject(fn()=>$service->saveCard($c,$params),'amount changed after opening callback');
$r=member($c);authorize($c,$service,$r);$params=$service->beginCard($c);$c->card='';reject(fn()=>$service->saveCard($c,$params),'empty Square card');check(!$c->db('sample_email_card_member')->get($r['id'])['square_card'],'empty result not saved');
$r=member($c,['status'=>0]);reject(fn()=>SampleEmailCardProfile::url($c,$r),'inactive member');
$r=member($c);$urlToken=token($c,$r);$decoded=$c->decrypt($urlToken);[$sig,$payload]=explode("\n",$decoded,2);$data=json_decode($payload,true);$data['expires']=time()-1;$payload=json_encode($data);$expired=$c->encrypt(hash_hmac('sha256',$payload,$c->get_setting()['secret'])."\n".$payload);
reject(fn()=>$service->open($c,$expired),'expired URL');[$originalSignature,$originalPayload]=explode("\n",$c->decrypt($urlToken),2);$changed=json_decode($originalPayload,true);$changed['id']++;$tampered=$c->encrypt($originalSignature."\n".json_encode($changed));reject(fn()=>$service->open($c,$tampered),'tampered URL target');
openMember($c,$service,$r);$c->post['csrf']='invalid';reject(fn()=>$service->sendIdentity($c),'wrong CSRF');
$r=member($c);authorize($c,$service,$r);$c->session[SampleEmailCardProfile::STATE]['authorized_until']=time()-1;reject(fn()=>$service->beginCard($c),'expired identity');
$r=member($c);openMember($c,$service,$r);$c->post['email']='attempts@example.invalid';$service->sendIdentity($c);$code=end($mail)[1];$c->post['code']='wrong';for($i=0;$i<5;$i++)reject(fn()=>$service->verifyIdentity($c),'wrong attempt '.$i);$c->post['code']=$code;reject(fn()=>$service->verifyIdentity($c),'attempt cap');
$r=member($c);openMember($c,$service,$r);$c->post['email']='expired-code@example.invalid';$service->sendIdentity($c);$c->session[SampleEmailCardProfile::STATE]['identity']['expires']=time()-1;$c->post['code']=end($mail)[1];reject(fn()=>$service->verifyIdentity($c),'expired code');
require dirname(__DIR__,2).'/fbp-email-confirm-dialog/assets/email-confirm-dialog/class-methods.php';
class EmailDialogSampleHarness {
    use fbp_email_confirm_dialog_sample;
    private function get_target_row(Controller $ctl,int $id): array{return $ctl->db('sample_email_card_member')->get($id);}
}
$dialog=new EmailDialogSampleHarness();$r=member($c);$c->post=['id'=>$r['id'],'email'=>'dialog-template@example.invalid'];$dialog->email_confirm_send_code($c);$saved=$c->session['YOUR_DIALOG_NAME_email_confirm_'.$r['id']];$sent=$c->dialogMailCalls;$dialog->email_confirm_send_code($c);
check($c->dialogMailCalls===$sent&&$saved===$c->session['YOUR_DIALOG_NAME_email_confirm_'.$r['id']],'standalone dialog asset reuses code without resending');
$c->close_all_db();echo json_encode(['ok'=>true,'checks'=>$checks,'real_mail'=>false,'real_payment'=>false,'payment_adapter'=>'simulated; host durable billing must be tested separately'])."\n";
