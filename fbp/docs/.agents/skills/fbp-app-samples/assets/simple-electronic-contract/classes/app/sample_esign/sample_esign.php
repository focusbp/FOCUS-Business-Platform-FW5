<?php
require_once __DIR__.'/Service.php';
/** Standard Screen additional actions. This class retains normal management login. */
class sample_esign {
    protected function service(): SampleESign {return new SampleESign();}
    private function actor(Controller $c,bool $write=false): array {if($write)SampleESignGuard::check($c);return SampleESign::identity($c);}
    private function id(Controller $c,mixed $value): int {$id=$c->decrypt($value);if(!is_scalar($id)||!preg_match('/^[1-9][0-9]*$/D',(string)$id))throw new DomainException('対象を選択してください。');return (int)$id;}
    private function show(Controller $c,array $r): void {
        $c->assign('esign_row',$r);$c->assign('esign_state',SampleESign::state($r));$c->assign('esign_id',$c->encrypt((string)$r['id']));$c->assign('esign_csrf',SampleESignGuard::nonce($c));
        $c->assign('esign_pdf_url',$c->get_APP_URL('sample_esign','preview',['id'=>$c->encrypt((string)$r['id'])]));
        $c->show_multi_dialog('sample-esign',__DIR__.'/Templates/wizard.tpl','簡易電子契約を追加',680);
    }
    function run(Controller $c): void {
        try{$a=$this->actor($c);$r=$this->service()->begin($c,$a);$this->show($c,$r);}catch(Throwable $e){$c->show_notification_text($e instanceof DomainException?$e->getMessage():'入力画面を開けませんでした。');}
    }
    function next(Controller $c): void {
        $c->clear_error_message();try{
            $a=$this->actor($c,true);$r=SampleESign::input($c,$a,$this->id($c,$c->POST('id')));$s=SampleESign::state($r);$upload=null;
            if((int)$s['step']===2){
                $files=array_filter($_FILES,static fn($f)=>($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE);
                if(count($files)>1||is_array($_FILES['contract_pdf']['name']??null)||is_array($_FILES['contract_pdf']['error']??null))throw new DomainException('PDFは1ファイルのみ添付してください。');
                if(($_FILES['contract_pdf']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){if(!$c->is_posted_file('contract_pdf'))throw new DomainException('PDFを添付し直してください。');$upload=['path'=>$c->get_posted_filepath('contract_pdf'),'name'=>$c->get_posted_filename('contract_pdf')];}
            }
            // Keep field errors beside each input and leave entered values untouched.
            if((int)$s['step']===1){$errors=false;try{if(SampleESign::text($c->POST('recipient_name')??'')==='')throw new DomainException('氏名を入力してください。');}catch(DomainException $e){$c->res_error_message('recipient_name',$e->getMessage());$errors=true;}
                try{$email=SampleESign::email($c->POST('recipient_email')??'');if($email===$s['parties'][0]['email'])throw new DomainException('自分と異なるメールアドレスを入力してください。');}catch(DomainException $e){$c->res_error_message('recipient_email',$e->getMessage());$errors=true;}if($errors){$c->res_error_message('form','入力内容を確認してください。');return;}}
            $r=$this->service()->step($c,$a,(int)$r['id'],(int)$s['step'],$c->POST(),$upload);$this->show($c,$r);
        }catch(Throwable $e){$c->res_error_message('form',$e instanceof DomainException?$e->getMessage():'入力を確認できませんでした。');}
    }
    function back(Controller $c): void {$c->clear_error_message();try{$a=$this->actor($c,true);$r=$this->service()->back($c,$a,$this->id($c,$c->POST('id')));$this->show($c,$r);}catch(Throwable $e){$c->res_error_message('form',$e instanceof DomainException?$e->getMessage():'画面を開き直してください。');}}
    function send(Controller $c): void {
        $c->clear_error_message();try{$a=$this->actor($c,true);$r=$this->service()->dispatch($c,$a,$this->id($c,$c->POST('id')),(string)$c->POST('title'));$this->show($c,$r);$c->reload_work_area();}
        catch(Throwable $e){$c->res_error_message('form',$e instanceof DomainException?$e->getMessage():'送信を完了できませんでした。一覧の詳細から再試行してください。');}
    }
    function detail(Controller $c): void {
        try{$a=$this->actor($c);$r=SampleESign::own($c,$this->id($c,$c->POST('id')),$a);$this->showDetail($c,$r);}catch(Throwable $e){$c->show_notification_text($e instanceof DomainException?$e->getMessage():'契約を取得できません。');}
    }
    private function showDetail(Controller $c,array $r): void {
        $c->assign('esign_row',$r);$c->assign('esign_state',SampleESign::state($r));$c->assign('esign_status',SampleESign::label($r));$c->assign('esign_id',$c->encrypt((string)$r['id']));$c->assign('esign_csrf',SampleESignGuard::nonce($c));
        $c->assign('esign_zip_url',$c->get_APP_URL('sample_esign','download',['id'=>$c->encrypt((string)$r['id'])]));
        $c->show_multi_dialog('sample-esign-detail',__DIR__.'/Templates/detail.tpl','簡易電子契約：'.$r['title'],680);
    }
    function retry(Controller $c): void {$c->clear_error_message();try{$a=$this->actor($c,true);$r=$this->service()->retry($c,$a,$this->id($c,$c->POST('id')));$this->showDetail($c,$r);$c->reload_work_area();}catch(Throwable $e){$c->res_error_message('form',$e instanceof DomainException?$e->getMessage():'再試行できませんでした。');}}
    function close(Controller $c): void {$this->actor($c);SampleESign::clearInput($c);$c->close_multi_dialog('sample-esign');}
    private function fileError(Controller $c,Throwable $e): void {http_response_code(403);$c->assign('esign_error',$e instanceof DomainException?$e->getMessage():'ファイルを取得できません。');$c->show_public_pages(dirname(__DIR__).'/sample_esign_public/Templates/error.tpl',null,null,null,['css_mode'=>'minimal']);}
    function preview(Controller $c): void {
        header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');try{$a=$this->actor($c);$r=SampleESign::input($c,$a,$this->id($c,$c->GET('id')));if($r['status']!=='input'||empty($r['original_pdf']))throw new DomainException('PDFを添付してください。');$p=SampleESign::storedPath($c,$r['original_pdf']);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="contract.pdf"');header('X-Content-Type-Options: nosniff');readfile($p);exit;}catch(Throwable $e){$this->fileError($c,$e);}
    }
    function download(Controller $c): void {
        header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');try{$a=$this->actor($c);$r=SampleESign::own($c,$this->id($c,$c->GET('id')),$a);if($r['status']!=='completed')throw new DomainException('締結が完了していません。');SampleESign::storedPath($c,$r['evidence_zip']);$c->res_saved_file($r['evidence_zip'],'contract_evidence.zip');}catch(Throwable $e){$this->fileError($c,$e);}
    }
}
