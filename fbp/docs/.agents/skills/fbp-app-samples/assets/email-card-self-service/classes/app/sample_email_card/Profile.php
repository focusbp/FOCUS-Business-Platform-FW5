<?php
require_once __DIR__.'/Verification.php';
require_once __DIR__.'/Payment.php';

/** Reusable verified email and Square card settings. Initial charging is opt-in. */
class SampleEmailCardProfile {
    const STATE = 'sample_email_card_profile';
    function __construct(private ?SampleEmailCardVerification $verification = null, private ?SampleEmailCardPayment $billing = null) {}
    private function verifier(): SampleEmailCardVerification { return $this->verification ?? new SampleEmailCardVerification(); }
    private function payment(): SampleEmailCardPayment {return $this->billing??new SampleEmailCardPayment();}
    static function prepare(Controller $ctl): void {SampleEmailCardVerification::prepare($ctl);}
    static function fingerprint(array $row): string {
        return hash('sha256',json_encode([(int)$row['id'],(string)($row['email']??'')],JSON_UNESCAPED_UNICODE));
    }
    static function version(array $row): string {
        return hash('sha256',json_encode(array_map(fn($k)=>(string)($row[$k]??''),['id','email','status','card_registered_at','monthly_fee','billing_description','square_customer','square_card'])));
    }
    static function row(Controller $ctl,int $id): array {
        $row=$id>0?$ctl->db('sample_email_card_member')->get($id):[];
        if(!$row || (int)($row['status']??0)!==1)throw new DomainException('有効な登録情報を確認できません。管理者へお問い合わせください。');
        if(($row['email']??'')!==''&&!filter_var($row['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('登録済みメールアドレスを管理者に確認してください。');
        return $row;
    }
    static function url(Controller $ctl,array $row): string {
        $row=self::row($ctl,(int)$row['id']);
        $payload=json_encode(['purpose'=>'sample-email-card-v1','id'=>(int)$row['id'],'expires'=>time()+30*86400,'fingerprint'=>self::fingerprint($row)],JSON_UNESCAPED_SLASHES);
        $signature=hash_hmac('sha256',$payload,(string)$ctl->get_setting()['secret']);
        return $ctl->get_APP_URL('sample_email_card_public','page',['token'=>$ctl->encrypt($signature."\n".$payload)]);
    }
    function open(Controller $ctl,$token): array {
        // Discard a prior account, pending email and Square operation even if this URL is invalid.
        $ctl->set_session(self::STATE,[]);
        if(!is_string($token)||strlen($token)>4096)throw new DomainException('URLが無効です。管理者から新しいURLを取得してください。');
        $decoded=$ctl->decrypt($token);
        [$signature,$payload]=is_string($decoded)?array_pad(explode("\n",$decoded,2),2,''):['',''];
        if(!hash_equals(hash_hmac('sha256',$payload,(string)$ctl->get_setting()['secret']),$signature))throw new DomainException('URLが無効です。管理者から新しいURLを取得してください。');
        $data=json_decode($payload,true);
        if(!is_array($data)||($data['purpose']??'')!=='sample-email-card-v1'||(int)($data['expires']??0)<time())throw new DomainException('URLの有効期限が切れています。管理者から新しいURLを取得してください。');
        self::prepare($ctl);$row=self::row($ctl,(int)($data['id']??0));
        if(!hash_equals(self::fingerprint($row),(string)($data['fingerprint']??'')))throw new DomainException('登録情報が変更されています。管理者から新しいURLを取得してください。');
        $state=['id'=>(int)$row['id'],'fingerprint'=>self::fingerprint($row),'expires'=>min((int)$data['expires'],time()+1800),'csrf'=>bin2hex(random_bytes(24))];
        $ctl->set_session(self::STATE,$state);return $state;
    }
    function context(Controller $ctl,bool $authorized=true,bool $post=true): array {
        self::prepare($ctl);$state=$ctl->get_session(self::STATE)??[];
        if(!$state||(int)($state['expires']??0)<time())throw new DomainException('画面の有効期限が切れました。案内されたURLから開き直してください。');
        if($post && (($_SERVER['REQUEST_METHOD']??'')!=='POST'||!is_string($ctl->POST('csrf'))||!hash_equals($state['csrf'],$ctl->POST('csrf'))))throw new DomainException('画面を開き直してお試しください。');
        $row=self::row($ctl,(int)$state['id']);
        if(!hash_equals($state['fingerprint'],self::fingerprint($row)))throw new DomainException('登録情報が変更されています。案内されたURLから開き直してください。');
        if($authorized&&(int)($state['authorized_until']??0)<time())throw new DomainException('メールの本人確認からやり直してください。');
        return [$state,$row];
    }
    function sendIdentity(Controller $ctl): void {
        [$state,$row]=$this->context($ctl,false);
        $email=(string)($row['email']??'');
        $initial=$email==='';
        if($initial){
            $email=SampleEmailCardVerification::email($ctl->POST('email'));
            if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('メールアドレスを正しく入力してください。');
        }
        $existing=$state['identity']??[];
        $existingEmail=$existing['target_email']??(($existing['initial_email']??'')?:($row['email']??''));
        if($this->reusable($existing)&&$existingEmail===$email)return;
        // Do not erase a delivered code when a resend is throttled or fails.
        $challenge=$this->verifier()->issueChallenge($ctl,$email,['initial_email'=>$initial?$email:'','target_email'=>$email]);
        // Re-read after all dependencies have been prepared by the verifier.
        $this->context($ctl,false);unset($state['authorized_until'],$state['pending'],$state['operation']);$state['identity']=$challenge;$ctl->set_session(self::STATE,$state);
    }
    private function reusable(array $challenge): bool {
        return !empty($challenge['hash'])&&(int)($challenge['expires']??0)>=time()&&(int)($challenge['attempts']??0)<5;
    }
    private function verify(array &$challenge,$code): void {
        if(empty($challenge['hash'])||(int)($challenge['expires']??0)<time()||(int)($challenge['attempts']??0)>=5)throw new DomainException('確認コードが期限切れ、または入力回数の上限です。画面を閉じてやり直してください。');
        $challenge['attempts']=(int)($challenge['attempts']??0)+1;
        if(!is_string($code)||!preg_match('/^[0-9]{4}$/D',$code)||!password_verify($code,$challenge['hash']))throw new DomainException('確認コードが一致しません。');
    }
    function verifyIdentity(Controller $ctl): void {
        [$state]=$this->context($ctl,false);$challenge=$state['identity']??[];
        try{$this->verify($challenge,$ctl->POST('code'));}
        finally{$state['identity']=$challenge;$ctl->set_session(self::STATE,$state);}
        if(!empty($challenge['initial_email'])){
            // The signed, admin-issued URL grants initial setup for this exact blank-email row.
            // context() rechecks its persisted fingerprint before any update.
            [, $row]=$this->context($ctl,false);
            if(($row['email']??'')!=='')throw new DomainException('登録情報が変更されています。URLから開き直してください。');
            $update=['id'=>$row['id'],'email'=>$challenge['initial_email']];$ctl->db('sample_email_card_member')->update($update);
            $row['email']=$challenge['initial_email'];$state['fingerprint']=self::fingerprint($row);
        }
        unset($state['identity']);$state['authorized_until']=time()+600;$ctl->set_session(self::STATE,$state);
    }
    function sendEmail(Controller $ctl): string {
        [$state,$row]=$this->context($ctl);$email=SampleEmailCardVerification::email($ctl->POST('email'));
        if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('メールアドレスを正しく入力してください。');
        if($email===SampleEmailCardVerification::email($row['email']))throw new DomainException('現在と異なるメールアドレスを入力してください。');
        $existing=$state['pending']??[];
        if($this->reusable($existing)&&($existing['email']??'')===$email)return $email;
        $challenge=$this->verifier()->issueChallenge($ctl,$email,[]);
        $this->context($ctl);unset($state['operation']);$state['pending']=$challenge+['email'=>$email];$ctl->set_session(self::STATE,$state);return $email;
    }
    function verifyEmail(Controller $ctl): void {
        [$state,$row]=$this->context($ctl);$challenge=$state['pending']??[];
        try{$this->verify($challenge,$ctl->POST('code'));}
        finally{$state['pending']=$challenge;$ctl->set_session(self::STATE,$state);}
        $update=['id'=>$row['id'],'email'=>$challenge['email']];$ctl->db('sample_email_card_member')->update($update);
        $row['email']=$challenge['email'];unset($state['pending'],$state['operation']);
        $state['fingerprint']=self::fingerprint($row);$ctl->set_session(self::STATE,$state);
    }
    static function firstCard(Controller $ctl,array $row): bool {
        return empty($row['square_card'])&&empty($row['card_registered_at']);
    }
    function willChargeInitial(Controller $ctl,array $row): bool {
        return self::firstCard($ctl,$row)&&$this->payment()->initialEnabled();
    }
    function beginCard(Controller $ctl): array {
        [$state,$row]=$this->context($ctl);
        $first=$this->willChargeInitial($ctl,$row);
        if($first&&!$this->payment()->ready())throw new DomainException('初回決済の連携設定を管理者に確認してください。');
        if($first&&(int)($row['monthly_fee']??0)<=0)throw new DomainException('初回決済金額を管理者に確認してください。');
        $state['operation']=['key'=>bin2hex(random_bytes(24)),'version'=>self::version($row),'expires'=>time()+600,'first'=>$first,'month'=>(new DateTimeImmutable('now',new DateTimeZone((string)($ctl->get_setting()['timezone']??date_default_timezone_get()))))->format('Y-m')];
        $ctl->set_session(self::STATE,$state);
        return ['operation'=>$state['operation']['key'],'square_button_label'=>$first?number_format((int)$row['monthly_fee']).'円を決済する':(empty($row['square_card'])?'カードを登録する':'カードを変更する')];
    }
    function saveCard(Controller $ctl,array $params): ?array {
        [$state,$row]=$this->context($ctl,true,false);$op=$state['operation']??[];
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||empty($op['key'])||(int)$op['expires']<time()||!is_string($params['operation']??null)||!hash_equals($op['key'],$params['operation'])||!hash_equals($op['version'],self::version($row)))throw new DomainException('カード変更画面を開き直してください。');
        if(empty($op['saved'])){
            $ctl->close_all_db();
            $customer=(string)($row['square_customer']??'');
            if($customer==='')$customer=(string)$ctl->square_regist_customer($row['name'],$row['email'],'');
            if($customer==='')throw new DomainException('カードの登録先を作成できませんでした。もう一度お試しください。');
            $card=(string)$ctl->square_regist_card($customer);
            if($card==='')throw new DomainException('カードを登録できませんでした。カード情報を確認してもう一度お試しください。');
            [$current,$latest]=$this->context($ctl,true,false);
            if(($current['operation']['key']??'')!==$op['key']||!hash_equals($op['version'],self::version($latest)))throw new DomainException('登録情報が変更されています。カード変更画面を開き直してください。');
            $update=['id'=>$row['id'],'square_customer'=>$customer,'square_card'=>$card,'card_registered_at'=>(int)($latest['card_registered_at']??0)?:time()];$ctl->db('sample_email_card_member')->update($update);
            $op['saved']=true;$op['version']=self::version(array_replace($latest,$update));
            $current['operation']=$op;$ctl->set_session(self::STATE,$current);$state=$current;
        }
        // The host payment adapter must use its durable reservation and provider idempotency.
        $result=!empty($op['first'])?$this->payment()->chargeInitial($ctl,self::row($ctl,(int)$row['id']),$op['month'],$op['key']):null;
        unset($state['operation']);$ctl->set_session(self::STATE,$state);return $result;
    }
}
