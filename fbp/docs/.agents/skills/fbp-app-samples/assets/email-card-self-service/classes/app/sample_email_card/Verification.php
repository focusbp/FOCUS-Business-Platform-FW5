<?php
class SampleEmailCardVerification {
    function __construct(private $mailer=null) {}
    static function prepare(Controller $ctl): void {
        $ctl->get_setting();
        $ctl->db('rate','sample_email_card');$ctl->db('email_format','email_format');$ctl->db('sample_email_card_member');
        // Preopen any extra DBs used by a host DSP or billing adapter before reading rows.
    }
    static function email($value): string {return is_string($value)?strtolower(trim($value)):'';}
    private function throttle(Controller $ctl,string $email): void {
        $db=$ctl->db('rate','sample_email_card');$now=time();$updates=[];
        foreach([['email:'.hash('sha256',$email),5],['ip:'.hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown'),10]] as [$key,$limit]){
            $r=$db->select('key',$key,true)[0]??['key'=>$key,'window_at'=>$now,'count'=>0,'last_at'=>0];
            if($now-(int)$r['window_at']>=3600){$r['window_at']=$now;$r['count']=0;}
            if((int)$r['count']>=$limit||($limit===5&&$now-(int)$r['last_at']<60))throw new DomainException('送信回数の制限に達しました。時間をおいてお試しください。');
            $r['count']++;$r['last_at']=$now;$updates[]=$r;
        }
        foreach($updates as $r){if(empty($r['id']))$db->insert($r);else $db->update($r);}
    }
    function issueChallenge(Controller $ctl,string $email,array $context): array {
        self::prepare($ctl);$this->throttle($ctl,$email);
        $code=str_pad((string)random_int(0,9999),4,'0',STR_PAD_LEFT);
        if($this->mailer){($this->mailer)($ctl,$email,$code);}
        else{
            if($ctl->get_session('testserver'))throw new RuntimeException('テスト環境の実メール送信は停止しています。');
            $format=$ctl->db('email_format','email_format')->select('key','sample_email_card_code',true)[0]??[];
            if(!$format)throw new RuntimeException('メール設定を確認してください。');
            $ctl->assign('confirmation_code',$code);
            $ctl->send_mail_string(null,$email,$ctl->fetch_string($format['subject']),$ctl->fetch_string($format['body']),null,true,['sensitive'=>true]);
        }
        return array_merge($context,['hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+600,'attempts'=>0]);
    }
}
