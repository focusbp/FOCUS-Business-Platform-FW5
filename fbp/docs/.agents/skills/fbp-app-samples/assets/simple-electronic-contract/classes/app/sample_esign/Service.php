<?php
require_once __DIR__.'/Guard.php';
/** Reusable two-person PDF approval; no customer app dependency. */
final class SampleESign {
    const TABLE='sample_esign_contracts';
    const TTL=864000;
    const DOWNLOAD_TTL=259200;
    private $mailer;
    function __construct(?callable $mailer=null){$this->mailer=$mailer;}
    static function prepare(Controller $c): void {
        $c->get_setting();$c->db('email_format','email_format');$c->db(self::TABLE);
    }
    static function identity(Controller $c): array {
        $c->db('user','user');self::prepare($c);SampleESignGuard::requireAdmin($c);
        $a=$c->db('user','user')->get((int)$c->get_login_user_id());
        if(!$a||(int)$a['status']!==0)throw new DomainException('有効な管理ユーザーでログインしてください。');
        return $a;
    }
    static function text(mixed $v,int $max=200): string {
        if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max||preg_match('/[\x00-\x1f]/',$v))throw new DomainException('入力内容を確認してください。');return trim($v);
    }
    static function email(mixed $v): string {
        $v=strtolower(self::text($v,254));if(!filter_var($v,FILTER_VALIDATE_EMAIL))throw new DomainException('有効なメールアドレスを入力してください。');return $v;
    }
    static function profile(array $a): array {try{$email=self::email($a['email']??'');}catch(DomainException $e){throw new DomainException('管理ユーザー（user）に有効なメールアドレスを登録してください。');}return ['name'=>self::text($a['name']??''),'email'=>$email];}
    static function state(array $r): array {return json_decode((string)$r['state_json'],true,512,JSON_THROW_ON_ERROR);}
    static function get(Controller $c,int $id): array {$r=$c->db(self::TABLE)->get($id);if(!$r)throw new DomainException('契約が見つかりません。');return $r;}
    static function own(Controller $c,int $id,array $actor,bool $creator=false): array {
        SampleESignGuard::requireAdmin($c);$r=self::get($c,$id);
        if($creator&&(int)$r['owner_user_id']!==(int)$actor['id'])throw new DomainException('この契約を操作できません。');
        return $r;
    }
    static function statusCode(array $r): int {
        $s=self::state($r);if($r['status']==='completed')return 3;
        if(!empty($s['parties'][0]['approved_at'])&&!empty($s['parties'][1]['approved_at']))return 2;
        if((int)$r['expires_at']<=time())return 4;
        return !empty($s['parties'][0]['approved_at'])||!empty($s['parties'][1]['approved_at'])?1:0;
    }
    static function refreshList(Controller $c): void {
        self::prepare($c);$db=$c->db(self::TABLE);
        foreach($db->getall() as $r){$code=self::statusCode($r);if((int)$r['status_code']!==$code){$r['status_code']=$code;$db->update($r);}}
    }
    private static function event(array &$s,string $event,int $party=-1): void {
        if(count($s['events']??[])>=200)throw new DomainException('操作回数の上限です。新しい契約を作成してください。');
        $e=['event'=>$event,'at'=>time(),'party'=>$party,'ip'=>mb_substr((string)($_SERVER['REMOTE_ADDR']??''),0,80),'agent'=>mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,300)];
        $e['previous']=$s['events']?end($s['events'])['hash']:'';$e['hash']=hash('sha256',json_encode($e,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$s['events'][]=$e;
    }
    private static function put(Controller $c,array $r,array $s,array $changes=[]): array {
        $json=json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(strlen($json)>89000)throw new DomainException('契約の保存可能な長さを超えています。');
        $r=array_replace($r,$changes,['state_json'=>$json,'updated_at'=>time()]);$r['status_code']=self::statusCode($r);$c->db(self::TABLE)->update($r);return self::get($c,(int)$r['id']);
    }
    private static function party(array $profile): array {return $profile+['nonce'=>bin2hex(random_bytes(24)),'approved_at'=>0,'verified_at'=>0,'code_hash'=>'','code_expires'=>0,'attempts'=>0,'sends'=>0,'last_send'=>0,'invite_sent'=>0,'complete_sent'=>0,'mail_tries'=>0,'last_mail'=>0];}
    // Input exists only in this authenticated session until the final send action.
    static function input(Controller $c,array $actor,int $id): array {
        $r=$c->get_session('sample_esign_input')??[];
        if((int)($r['id']??0)!==$id||(int)($r['owner_user_id']??0)!==(int)$actor['id']||(int)($r['updated_at']??0)<time()-3600)throw new DomainException('入力の有効期限が切れました。新規追加から開き直してください。');
        return $r;
    }
    private static function keepInput(Controller $c,array $r,array $s): array {
        $r['state_json']=json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$r['updated_at']=time();$c->set_session('sample_esign_input',$r);return $r;
    }
    static function clearInput(Controller $c): void {
        $r=$c->get_session('sample_esign_input')??[];
        if(($r['status']??'')==='input'&&!empty($r['original_pdf']))self::deleteInputPdf($c,$r['original_pdf']);
        $c->set_session('sample_esign_input',null);
    }
    function begin(Controller $c,array $actor): array {
        self::prepare($c);self::clearInput($c);$request=bin2hex(random_bytes(24));
        $a=self::profile($actor);if($a['name']==='')throw new DomainException('管理ユーザーの氏名を登録してください。');
        $s=['step'=>1,'parties'=>[self::party($a)],'events'=>[]];
        $r=['id'=>random_int(1000000000,PHP_INT_MAX),'owner_user_id'=>$actor['id'],'title'=>'','status'=>'input','created_at'=>time(),'updated_at'=>time(),'expires_at'=>0,'completed_at'=>0,'download_expires_at'=>0,'request_key'=>$request,'original_pdf'=>'','original_hash'=>'','signed_pdf'=>'','evidence_zip'=>''];
        return self::keepInput($c,$r,$s);
    }
    function step(Controller $c,array $actor,int $id,int $step,array $input,?array $upload=null): array {
        self::prepare($c);$r=self::input($c,$actor,$id);$s=self::state($r);$newFile=null;$oldFile=null;
        if($r['status']!=='input'||(int)$s['step']!==$step)throw new DomainException('画面を開き直してください。');
        if($step===1){
            $p=['name'=>self::text($input['recipient_name']??''),'email'=>self::email($input['recipient_email']??'')];
            if($p['name']===''||$p['email']===$s['parties'][0]['email'])throw new DomainException('自分と異なる相手の氏名・メールアドレスを指定してください。');
            $s['parties'][1]=self::party($p);
        }elseif($step===2){
            if($upload){$name=self::text($upload['name'],200);$path=$upload['path'];self::validatePdf($c,$path,$name);$stored=self::saveInputPdf($c,file_get_contents($path));$newFile=$stored;$oldFile=$r['original_pdf'];$r['original_pdf']=$stored;$r['original_hash']=hash_file('sha256',$path);$s['filename']=$name;$r['title']=mb_strcut(mb_substr(pathinfo($name,PATHINFO_FILENAME),0,200),0,600,'UTF-8');}
            if(!$r['original_pdf'])throw new DomainException('契約PDFを添付してください。');
        }else throw new DomainException('画面を開き直してください。');
        $s['step']=$step+1;try{$result=self::keepInput($c,$r,$s);}catch(Throwable $e){if($newFile)self::deleteInputPdf($c,$newFile);throw $e;}if($oldFile)self::deleteInputPdf($c,$oldFile);return $result;
    }
    function back(Controller $c,array $actor,int $id): array {self::prepare($c);$r=self::input($c,$actor,$id);if($r['status']!=='input')throw new DomainException('送信済みです。');$s=self::state($r);$s['step']=max(1,(int)$s['step']-1);return self::keepInput($c,$r,$s);}
    private static function work(Controller $c): string {$p=$c->get_temp_dir().'/aes_'.bin2hex(random_bytes(16));if(!mkdir($p,0700))throw new RuntimeException('作業領域を作成できません。');return $p;}
    private static function clean(string $p): void {foreach(glob($p.'/*')?:[] as $f)if(is_file($f))unlink($f);rmdir($p);}
    private static function command(array $args): string {$pipes=[];$p=proc_open(array_merge(['/usr/bin/timeout','45'], $args),[1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('PDFを処理できません。');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new DomainException('PDFを読み取れません。暗号化・破損のないPDFを指定してください。');return $out;}
    static function validatePdf(Controller $c,string $p,string $name): void {
        if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='pdf'||!is_file($p)||filesize($p)>20*1024*1024||filesize($p)<5||file_get_contents($p,false,null,0,5)!=='%PDF-')throw new DomainException('20MB以下のPDFを指定してください。');
        $out=self::command(['/usr/bin/pdfinfo',$p]);if(preg_match('/Encrypted:\s+yes/i',$out)||!preg_match('/Pages:\s+[1-9][0-9]*/',$out))throw new DomainException('暗号化されていないPDFを指定してください。');
    }
    private static function inputDir(Controller $c): string {
        $dir=$c->get_temp_dir().'/aes_input_'.substr(hash('sha256',(string)$c->get_setting()['secret']),0,24);
        if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('一時領域を作成できません。');
        return $dir;
    }
    private static function saveInputPdf(Controller $c,string $bytes): string {
        $dir=self::inputDir($c);foreach(glob($dir.'/aes_input_*.pdf')?:[] as $path)if(filemtime($path)<time()-7200)unlink($path);
        $name='aes_input_'.bin2hex(random_bytes(32)).'.pdf';$path=$dir.'/'.$name;
        if(file_put_contents($path,$bytes)!==strlen($bytes))throw new RuntimeException('PDFを一時保持できません。');chmod($path,0600);return $name;
    }
    private static function deleteInputPdf(Controller $c,string $name): void {
        if(preg_match('/^aes_input_[a-f0-9]{64}\.pdf$/D',$name)){$p=self::inputDir($c).'/'.$name;if(is_file($p))unlink($p);}
    }
    private static function saveBytes(Controller $c,string $bytes,string $ext): string {$name='aes_'.bin2hex(random_bytes(32)).'.'.$ext;$c->save_file($name,$bytes);if(!$c->is_saved_file($name)||filesize($c->get_saved_filepath($name))!==strlen($bytes))throw new RuntimeException('ファイルを保存できません。');return $name;}
    static function storedPath(Controller $c,string $name): string {if(preg_match('/^aes_input_[a-f0-9]{64}\.pdf$/D',$name)){$p=self::inputDir($c).'/'.$name;if(!is_file($p))throw new DomainException('PDFを添付し直してください。');return $p;}if(!preg_match('/^aes_[a-f0-9]{64}\.(pdf|zip)$/D',$name)||!$c->is_saved_file($name))throw new DomainException('ファイルが見つかりません。');return $c->get_saved_filepath($name);}
    private static function secret(Controller $c): string {$s=$c->get_setting();if(empty($s['secret']))throw new RuntimeException('設定を確認してください。');return hash_hmac('sha256','sample_esign_v1',(string)$s['secret']);}
    static function token(Controller $c,array $r,int $party,string $purpose): string {$s=self::state($r);$base=$r['id'].'.'.$party.'.'.$purpose;return $base.'.'.hash_hmac('sha256',$base.'|'.$s['parties'][$party]['nonce'].'|'.$r['expires_at'].'|'.($purpose==='download'?$r['completed_at']:''),self::secret($c));}
    static function resolve(Controller $c,string $token): array {
        self::prepare($c);if(!preg_match('/^([1-9][0-9]*)\.([01])\.(invite|download)\.([a-f0-9]{64})$/D',$token,$m))throw new DomainException('URLが無効です。');
        $r=self::get($c,(int)$m[1]);$p=(int)$m[2];$s=self::state($r);if(!isset($s['parties'][$p])||!hash_equals(self::token($c,$r,$p,$m[3]),$token)||$r['status']==='input')throw new DomainException('URLが無効です。');
        if($m[3]==='download'){if($r['status']!=='completed'||(int)$r['download_expires_at']<=time())throw new DomainException('ダウンロードURLの有効期限が切れています。');}
        elseif((int)$r['expires_at']<=time())throw new DomainException('契約URLの有効期限が切れています。');
        return [$r,$p,$m[3]];
    }
    static function url(Controller $c,array $r,int $party,string $purpose): string {return $c->get_APP_URL('sample_esign_public','page',['t'=>self::token($c,$r,$party,$purpose)]);}
    private function mail(Controller $c,string $to,string $key,array $v): void {
        if($this->mailer){($this->mailer)($c,$to,$key,$v);return;}
        if($c->testserver())throw new RuntimeException('テスト環境の実メール送信は停止しています。');
        foreach($v as $k=>$value)$c->assign('aes_'.$k,$value);
        $c->send_mail_prepared_format($to,'sample_esign_'.$key,null,'',null,['throw_on_error'=>true,'sensitive'=>true]);
    }
    function dispatch(Controller $c,array $actor,int $id,string $title): array {
        self::prepare($c);$r=self::input($c,$actor,$id);
        if($r['status']!=='input')return $this->retry($c,$actor,(int)$r['registered_id']);
        $s=self::state($r);$title=self::text($title);
        if((int)$s['step']!==3||!isset($s['parties'][1])||!$r['original_pdf']||$title===''||strlen($title)>600)throw new DomainException('契約名と入力内容を確認してください。');
        self::validatePdf($c,self::storedPath($c,$r['original_pdf']),$s['filename']);
        if(!hash_equals($r['original_hash'],hash_file('sha256',self::storedPath($c,$r['original_pdf']))))throw new DomainException('PDFを添付し直してください。');
        $db=$c->db(self::TABLE);$existing=$db->select(['owner_user_id','request_key'],[$actor['id'],$r['request_key']],true,'AND')[0]??null;
        if(!$existing){
            $count=0;foreach($db->select('owner_user_id',$actor['id'],true) as $row)if((int)$row['created_at']>time()-3600)$count++;
            if($count>=20)throw new DomainException('直近1時間の作成回数の上限です。時間をおいてお試しください。');
            self::event($s,'invitations_created');$existing=$r;unset($existing['id']);$existing=array_replace($existing,['title'=>$title,'status'=>'pending','created_at'=>time(),'updated_at'=>time(),'expires_at'=>time()+self::TTL,'state_json'=>json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$saved=self::saveBytes($c,file_get_contents(self::storedPath($c,$r['original_pdf'])),'pdf');$existing['original_pdf']=$saved;$existing['recipient_name']=$s['parties'][1]['name'];$existing['recipient_email']=$s['parties'][1]['email'];$existing['sender_name']=$s['parties'][0]['name'];$existing['sender_email']=$s['parties'][0]['email'];$existing['status_code']=self::statusCode($existing);try{$db->insert($existing);}catch(Throwable $e){$c->delete_saved_file($saved);throw $e;}
        }
        self::deleteInputPdf($c,$r['original_pdf']);
        // Retain only a replay receipt; the registered record now owns the PDF.
        $c->set_session('sample_esign_input',['id'=>$id,'owner_user_id'=>$actor['id'],'updated_at'=>time(),'status'=>'registered','registered_id'=>$existing['id']]);
        return $this->retry($c,$actor,(int)$existing['id']);
    }
    function retry(Controller $c,array $actor,int $id): array {
        self::prepare($c);$r=self::own($c,$id,$actor);$s=self::state($r);if($r['status']==='input')return $r;
        if($s['parties'][0]['approved_at']&&$s['parties'][1]['approved_at']&&$r['status']!=='completed')$r=$this->finish($c,$r);
        if($r['status']!=='completed'&&(int)$r['expires_at']<=time())throw new DomainException('契約期限が切れています。');
        return $this->sendNotices($c,$r);
    }
    private function sendNotices(Controller $c,array $r): array {
        $key=$r['status']==='completed'?'complete':'invite';$flag=$key.'_sent';$s=self::state($r);
        if($key==='complete'&&(int)$r['download_expires_at']<=time())return $r;
        foreach([0,1] as $i){
            if($s['parties'][$i][$flag])continue;$p=&$s['parties'][$i];
            if((int)$p['mail_tries']>=20||time()-(int)$p['last_mail']<30)continue;
            $p['mail_tries']++;$p['last_mail']=time();$r=self::put($c,$r,$s);unset($p);
            try{$this->mail($c,$s['parties'][$i]['email'],$key,['title'=>$r['title'],'name'=>$s['parties'][$i]['name'],'url'=>self::url($c,$r,$i,$key==='complete'?'download':'invite'),'expires'=>$c->create_ValueFormatter()->format_datetime($key==='complete'?$r['download_expires_at']:$r['expires_at'])]);$s['parties'][$i][$flag]=time();self::event($s,$key.'_mail_sent',$i);$s['parties'][$i]['mail_error']=false;}
            catch(Throwable $e){$s['parties'][$i]['mail_error']=true;}
            $r=self::put($c,$r,$s);
        }return $r;
    }
    function sendCode(Controller $c,int $id,int $party): array {
        self::prepare($c);$r=self::get($c,$id);$s=self::state($r);self::canApprove($r,$s,$party);$p=&$s['parties'][$party];
        if(time()-(int)$p['last_send']<60||(int)$p['sends']>=30)throw new DomainException('確認コードの送信回数制限です。時間をおいてお試しください。');
        $code=str_pad((string)random_int(0,9999),4,'0',STR_PAD_LEFT);$p['sends']++;$p['last_send']=time();$p['code_hash']='';$p['code_expires']=0;$p['attempts']=0;$p['challenge']=bin2hex(random_bytes(16));unset($p);$r=self::put($c,$r,$s);
        $this->mail($c,$s['parties'][$party]['email'],'code',['title'=>$r['title'],'code'=>$code]);$s['parties'][$party]['code_hash']=password_hash($code,PASSWORD_DEFAULT);$s['parties'][$party]['code_expires']=time()+600;self::event($s,'code_sent',$party);return self::put($c,$r,$s);
    }
    private static function canApprove(array $r,array $s,int $p): void {if($r['status']!=='pending'||!isset($s['parties'][$p])||$s['parties'][$p]['approved_at']||(int)$r['expires_at']<=time())throw new DomainException('契約は承認済み、または期限切れです。');}
    function verify(Controller $c,int $id,int $party,mixed $code): array {
        self::prepare($c);$r=self::get($c,$id);$s=self::state($r);self::canApprove($r,$s,$party);$p=&$s['parties'][$party];
        if(empty($p['code_hash'])||(int)$p['code_expires']<=time()||(int)$p['attempts']>=5)throw new DomainException('確認コードが期限切れ、または入力回数の上限です。メールのURLから開き直して再送してください。');
        $p['attempts']++;$valid=is_string($code)&&preg_match('/^[0-9]{4}$/D',$code)&&password_verify($code,$p['code_hash']);if($valid){$p['code_hash']='';$p['verified_at']=time();}unset($p);if($valid)self::event($s,'code_verified',$party);$r=self::put($c,$r,$s);if(!$valid)throw new DomainException('確認コードが一致しません。');return $r;
    }
    function approve(Controller $c,int $id,int $party,string $challenge): array {
        self::prepare($c);$r=self::get($c,$id);$s=self::state($r);
        if($r['status']==='completed')return $r;
        if(!empty($s['parties'][$party]['approved_at'])){if($s['parties'][0]['approved_at']&&$s['parties'][1]['approved_at'])return $this->sendNotices($c,$this->finish($c,$r));return $r;}
        self::canApprove($r,$s,$party);$p=$s['parties'][$party];
        if(empty($p['verified_at'])||time()-(int)$p['verified_at']>=600||!hash_equals((string)($p['challenge']??''),$challenge))throw new DomainException('メール認証からやり直してください。');
        if(!hash_equals($r['original_hash'],hash_file('sha256',self::storedPath($c,$r['original_pdf']))))throw new DomainException('原本PDFを確認できません。');
        $s['parties'][$party]['approved_at']=time();$s['parties'][$party]['approval']=['pdf_sha256'=>$r['original_hash'],'email'=>$p['email'],'name'=>$p['name'],'at'=>time(),'ip'=>(string)($_SERVER['REMOTE_ADDR']??''),'agent'=>mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,300)];self::event($s,'approved',$party);$r=self::put($c,$r,$s);
        if($s['parties'][0]['approved_at']&&$s['parties'][1]['approved_at'])$r=$this->sendNotices($c,$this->finish($c,$r));return $r;
    }
    private function finish(Controller $c,array $r): array {
        $s=self::state($r);if($r['status']==='completed')return $r;if(!$s['parties'][0]['approved_at']||!$s['parties'][1]['approved_at'])throw new DomainException('双方の承認が必要です。');
        $dir=self::work($c);$stored=[];
        try{
            $path=self::storedPath($c,$r['original_pdf']);if(!hash_equals($r['original_hash'],hash_file('sha256',$path)))throw new DomainException('原本PDFの内容が一致しません。');
            $now=time();$pdf=$c->create_pdfmaker();$pdf->setPageLayout(['pagesize'=>'A4','font'=>'gothic','fontsize'=>10,'lineheight'=>6,'pagenumber'=>'off']);$pdf->addPage();$text=function(string $value,array $layout=[])use($pdf){$pdf->addText($value,$layout+['align'=>'L']);};$text('簡易電子契約 承認記録',['fontsize'=>18]);$text('契約名：'.$r['title']);$text('締結完了：'.date('Y-m-d H:i:s',$now));
            foreach($s['parties'] as $i=>$p){$text('承認者'.($i+1).'：'.$p['name']);$text('認証メール：'.$p['email']);$text('承認日時：'.date('Y-m-d H:i:s',$p['approved_at']));}
            $text('原本PDF SHA-256：'.$r['original_hash']);file_put_contents($dir.'/proof.pdf',$pdf->get_pdf_data());self::command(['/usr/bin/pdfunite',$dir.'/proof.pdf',$path,$dir.'/complete.pdf']);
            $e=['schema'=>'sample_esign_v1','contract_id'=>(int)$r['id'],'title'=>$r['title'],'completed_at'=>$now,'original_sha256'=>$r['original_hash'],'completed_sha256'=>hash_file('sha256',$dir.'/complete.pdf'),'approvals'=>array_column($s['parties'],'approval'),'events'=>$s['events']];$payload=json_encode($e,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$e['hmac_sha256']=hash_hmac('sha256',$payload,self::secret($c));$json=json_encode($e,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
            $z=new ZipArchive();if($z->open($dir.'/evidence.zip',ZipArchive::CREATE)!==true)throw new RuntimeException('ZIPを作成できません。');$z->addFile($dir.'/complete.pdf','signed.pdf');$z->addFromString('evidence.json',$json);$z->addFromString('evidence.txt','契約名：'.$r['title']."\n".'承認者：'.implode(' / ',array_column($s['parties'],'name'))."\n".$json);$z->close();
            $stored['signed_pdf']=self::saveBytes($c,file_get_contents($dir.'/complete.pdf'),'pdf');$stored['evidence_zip']=self::saveBytes($c,file_get_contents($dir.'/evidence.zip'),'zip');foreach($s['parties'] as &$p){$p['mail_tries']=0;$p['last_mail']=0;}unset($p);self::event($s,'completed');return self::put($c,$r,$s,$stored+['status'=>'completed','completed_at'=>$now,'download_expires_at'=>$now+self::DOWNLOAD_TTL]);
        }catch(Throwable $e){foreach($stored as $name)$c->delete_saved_file($name);throw $e;}finally{self::clean($dir);}
    }
    static function label(array $r): string {$s=self::state($r);if($r['status']==='input')return '下書き';if($r['status']==='completed')return '締結完了';if($s['parties'][0]['approved_at']&&$s['parties'][1]['approved_at'])return '完了ファイル作成待ち';if((int)$r['expires_at']<=time())return '期限切れ';return ($s['parties'][0]['approved_at']||$s['parties'][1]['approved_at'])?'一方が承認済み':'双方の承認待ち';}
}
