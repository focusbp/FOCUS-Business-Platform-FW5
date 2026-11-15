<?php
require_once __DIR__.'/OnetimecodeService.php';
require_once __DIR__.'/../../lib/ManagedLogin.php';
final class ManagedMasterLoginService {
    const TTL=60;const SESSION_TTL=1800;
    private static ?array $grant=null;
    private $tickets;private PasskeyRepository $repo;private array $cfg;
    function __construct(private Controller $ctl,private $transport=null){
        OnetimecodeService::requireStandardLogin($ctl);$this->cfg=ManagedLoginConfig::load($ctl);
        $this->repo=new PasskeyRepository($ctl);$this->tickets=$ctl->db('master_ticket','login');$this->tickets->set_strict_field_length(true);
    }
    static function code(mixed $value): string {if(!is_string($value)||!preg_match('/^[A-Za-z0-9_-]{43}$/D',$value))throw new RuntimeException('Invalid capability.');return $value;}
    private function master(): array {
        $rows=$this->repo->users->getall('id',SORT_ASC);$u=$rows?array_values($rows)[0]:[];
        if(!$u||(int)$u['type']!==0||(int)$u['status']!==0)throw new RuntimeException('Master unavailable.');return $u;
    }
    function prepare(array $p): array {
        if(array_diff(array_keys($p),['grant','target','environment'])||($p['target']??'')!==$this->cfg['base_url']||($p['environment']??'')!==$this->cfg['environment'])throw new RuntimeException('Wrong target.');
        $code=self::code($p['grant']??null);$setting=$this->ctl->get_setting();
        $snapshot=ManagedLoginScope::run($this->ctl,function()use($code){
            $this->repo->rate('managed:issue',30,60);if($this->tickets->select('grant_hash',hash('sha256',$code),true))throw new RuntimeException('Replayed grant.');
            $u=$this->master();$i=$this->repo->identity((int)$u['id']);return [$u,$i];
        });
        $this->ctl->close_all_db();
        $call=$this->transport??[ManagedLoginHttp::class,'request'];
        $result=$call($this->cfg['issuer_base_url'],'sftp_master_login_api','consume',['grant'=>$code,'target'=>$this->cfg['base_url'],'environment'=>$this->cfg['environment']],$setting['api_key']??'',$setting['api_secret']??'',$this->cfg['http_basic']??[]);
        if(($result['grant_hash']??'')!==hash('sha256',$code)||($result['target']??'')!==$this->cfg['base_url']||($result['environment']??'')!==$this->cfg['environment']||!is_int($result['expires_at']??null)||$result['expires_at']<=time()||$result['expires_at']>time()+self::TTL)throw new RuntimeException('Issuer denied.');
        $this->repo=new PasskeyRepository($this->ctl);$this->tickets=$this->ctl->db('master_ticket','login');
        return ManagedLoginScope::run($this->ctl,function()use($code,$snapshot,$result){
            $u=$this->master();$i=$this->repo->identity((int)$u['id']);
            if($u['id']!==$snapshot[0]['id']||!hash_equals($snapshot[1]['epoch'],$i['epoch'])||!hash_equals($snapshot[1]['user_handle'],$i['user_handle'])||$this->tickets->select('grant_hash',hash('sha256',$code),true))throw new RuntimeException('Identity changed.');
            foreach($this->tickets->getall() as $old)if((int)$old['created_at']<time()-600)$this->tickets->delete($old['id']);
            $ticket=PasskeyService::encode(random_bytes(32));$row=['user_id'=>$u['id'],'ticket_hash'=>hash('sha256',$ticket),'grant_hash'=>hash('sha256',$code),'origin'=>$this->cfg['base_url'],'epoch'=>$i['epoch'],'user_handle'=>$i['user_handle'],'issuer_actor'=>(int)($result['actor_id']??0),'created_at'=>time(),'expires_at'=>$result['expires_at'],'used_at'=>0];$this->tickets->insert($row);
            error_log('managed_master: issued record='.$row['id'].' user='.$u['id']);
            return ['ok'=>true,'ticket'=>$ticket,'expires_at'=>$row['expires_at'],'login_url'=>preg_replace('/^http:/','https:',$this->ctl->get_APP_URL('login','managed_master'))];
        });
    }
    function bridge(string $ticket): array {
        self::code($ticket);
        return ManagedLoginScope::run($this->ctl,function()use($ticket){
            $rows=$this->tickets->select('ticket_hash',hash('sha256',$ticket),true);$row=$rows[0]??[];
            if(!$row||(int)$row['used_at']!==0||(int)$row['expires_at']<=time())throw new RuntimeException('Ticket denied.');
            $csrf=PasskeyService::encode(random_bytes(32));$this->ctl->set_session('managed_master_form',['csrf'=>$csrf,'ticket_hash'=>hash('sha256',$ticket),'expires'=>$row['expires_at']]);return ['csrf'=>$csrf,'ticket'=>$ticket];
        });
    }
    function consume(string $ticket,string $csrf,callable $login): void {
        $form=$this->ctl->get_session('managed_master_form');$this->ctl->set_session('managed_master_form',null);
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||($_SERVER['HTTP_ORIGIN']??'')!=='https://'.parse_url($this->cfg['base_url'],PHP_URL_HOST)||($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site'||!is_array($form)||($form['expires']??0)<=time()||!hash_equals((string)$form['csrf'],$csrf)||!hash_equals($form['ticket_hash'],hash('sha256',self::code($ticket))))throw new RuntimeException('Invalid form.');
        ManagedLoginScope::run($this->ctl,function()use($ticket,$login){
            $rows=$this->tickets->select('ticket_hash',hash('sha256',$ticket),true);$row=$rows[0]??[];$u=$this->master();$i=$this->repo->identity((int)$u['id']);
            if(!$row||(int)$row['used_at']!==0||(int)$row['expires_at']<=time()||(int)$row['user_id']!==(int)$u['id']||$row['origin']!==$this->cfg['base_url']||!hash_equals($row['epoch'],$i['epoch'])||!hash_equals($row['user_handle'],$i['user_handle']))throw new RuntimeException('Ticket revoked.');
            $row['used_at']=time();$this->tickets->update($row);self::$grant=['ctl'=>$this->ctl,'user_id'=>(int)$u['id']];
            error_log('managed_master: consumed record='.$row['id'].' user='.$u['id']);
            try{$login($u);}finally{self::$grant=null;}
        });
    }
    static function hasGrant(Controller $ctl,int $id): bool {return self::$grant!==null&&self::$grant['ctl']===$ctl&&self::$grant['user_id']===$id;}
}
