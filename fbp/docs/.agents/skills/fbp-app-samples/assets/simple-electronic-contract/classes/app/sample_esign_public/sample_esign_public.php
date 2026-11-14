<?php
require_once dirname(__DIR__).'/sample_esign/Service.php';
class sample_esign_public {
    function __construct(Controller $c){$c->set_check_login(false);}
    protected function service(): SampleESign {return new SampleESign();}
    private function privateHeaders(): void {header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');}
    private function failure(Controller $c,Throwable $e): void {$this->privateHeaders();http_response_code(403);$c->assign('esign_error',$e instanceof DomainException?$e->getMessage():'処理を完了できませんでした。時間をおいてお試しください。');$c->show_public_pages(__DIR__.'/Templates/error.tpl',__DIR__.'/Templates/head.tpl',null,null,['css_mode'=>'minimal']);}
    function page(Controller $c): void {
        $this->privateHeaders();try{$token=(string)$c->GET('t');[$r,$party,$purpose]=SampleESign::resolve($c,$token);$flows=$c->get_session('sample_esign_flows')??[];
            foreach($flows as $k=>$v)if(($v['expires']??0)<time())unset($flows[$k]);
            $key=bin2hex(random_bytes(24));$flows[$key]=['token'=>$token,'party'=>$party,'id'=>(int)$r['id'],'purpose'=>$purpose,'csrf'=>bin2hex(random_bytes(24)),'expires'=>time()+3600,'verified_until'=>0,'challenge'=>''];$c->set_session('sample_esign_flows',array_slice($flows,-32,null,true));$this->show($c,$key,$r,$party,$purpose);
        }catch(Throwable $e){$this->failure($c,$e);}
    }
    private function flow(Controller $c): array {
        $this->privateHeaders();SampleESign::prepare($c);$key=(string)$c->POST('flow');$f=($c->get_session('sample_esign_flows')??[])[$key]??[];
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!$f||$f['expires']<=time()||!is_string($c->POST('csrf'))||!hash_equals($f['csrf'],$c->POST('csrf'))||$f['purpose']!=='invite')throw new DomainException('メールのURLから開き直してください。');
        [$r,$p,$purpose]=SampleESign::resolve($c,$f['token']);if((int)$r['id']!==$f['id']||$p!==$f['party'])throw new DomainException('画面が無効です。');return [$key,$f,$r,$p];
    }
    private function show(Controller $c,string $key,array $r,int $party,string $purpose,string $error='',?string $stage=null): void {
        $s=SampleESign::state($r);$f=($c->get_session('sample_esign_flows')??[])[$key];
        $mode=$purpose==='download'?'download':($r['status']==='completed'?'completed':($s['parties'][$party]['approved_at']?'waiting':($stage??($f['verified_until']>time()?'approve':'confirm'))));
        $c->assign('aes_mode',$mode);$c->assign('aes_row',$r);$c->assign('aes_party',$s['parties'][$party]);$c->assign('aes_names',array_column($s['parties'],'name'));$c->assign('aes_flow',$key);$c->assign('aes_csrf',$f['csrf']);$c->assign('aes_error',$error);$c->assign('aes_status',SampleESign::label($r));$c->assign('aes_expires',date('Y-m-d H:i',(int)($purpose==='download'?$r['download_expires_at']:$r['expires_at'])));
        $c->assign('aes_original_url',$c->get_APP_URL('sample_esign_public','file',['t'=>$f['token'],'kind'=>'original']));
        foreach(['pdf','zip'] as $kind)$c->assign('aes_'.$kind.'_url',$c->get_APP_URL('sample_esign_public','file',['t'=>$f['token'],'kind'=>$kind]));
        $c->show_public_pages(__DIR__.'/Templates/page.tpl',__DIR__.'/Templates/head.tpl',null,null,['css_mode'=>'minimal']);
    }
    function send_code(Controller $c): void {
        try{[$k,$f,$r,$p]=$this->flow($c);$r=$this->service()->sendCode($c,(int)$r['id'],$p);$flows=$c->get_session('sample_esign_flows');$flows[$k]['verified_until']=0;$flows[$k]['challenge']='';$c->set_session('sample_esign_flows',$flows);$this->show($c,$k,$r,$p,'invite','','code');}
        catch(Throwable $e){if(isset($k))$this->show($c,$k,SampleESign::get($c,(int)$r['id']),$p,'invite',$e instanceof DomainException?$e->getMessage():'確認メールを送信できませんでした。時間をおいてお試しください。','confirm');else $this->failure($c,$e);}
    }
    function verify_code(Controller $c): void {
        try{[$k,$f,$r,$p]=$this->flow($c);$r=$this->service()->verify($c,(int)$r['id'],$p,$c->POST('code'));$flows=$c->get_session('sample_esign_flows');$flows[$k]['verified_until']=time()+600;$flows[$k]['challenge']=SampleESign::state($r)['parties'][$p]['challenge'];$c->set_session('sample_esign_flows',$flows);$this->show($c,$k,$r,$p,'invite');}
        catch(Throwable $e){if(isset($k))$this->show($c,$k,SampleESign::get($c,(int)$r['id']),$p,'invite',$e instanceof DomainException?$e->getMessage():'照合できませんでした。','code');else $this->failure($c,$e);}
    }
    function approve(Controller $c): void {
        try{[$k,$f,$r,$p]=$this->flow($c);if($f['verified_until']<=time()||!in_array('1',(array)$c->POST('agree'),true))throw new DomainException('メール認証と同意のチェックを確認してください。');$r=$this->service()->approve($c,(int)$r['id'],$p,$f['challenge']);$this->show($c,$k,$r,$p,'invite');}
        catch(Throwable $e){if(isset($k))$this->show($c,$k,SampleESign::get($c,(int)$r['id']),$p,'invite',$e instanceof DomainException?$e->getMessage():'承認後の完了処理を終えられませんでした。管理画面から再試行してください。');else $this->failure($c,$e);}
    }
    function file(Controller $c): void {
        $this->privateHeaders();try{[$r,$p,$purpose]=SampleESign::resolve($c,(string)$c->GET('t'));$kind=(string)$c->GET('kind');
            if($kind==='original'){if($purpose!=='invite'||$r['status']==='completed'||(int)$r['expires_at']<=time())throw new DomainException('原本の閲覧期限が切れています。');$field='original_pdf';$name='contract.pdf';}
            else{if($purpose!=='download'||$r['status']!=='completed')throw new DomainException('完了メールのURLから開いてください。');$field=['pdf'=>'signed_pdf','zip'=>'evidence_zip'][$kind]??null;if(!$field)throw new DomainException('取得できません。');$name=$kind==='pdf'?'signed.pdf':'contract_evidence.zip';}
            SampleESign::storedPath($c,$r[$field]);$c->res_saved_file($r[$field],$name);
        }catch(Throwable $e){$this->failure($c,$e);}
    }
}
