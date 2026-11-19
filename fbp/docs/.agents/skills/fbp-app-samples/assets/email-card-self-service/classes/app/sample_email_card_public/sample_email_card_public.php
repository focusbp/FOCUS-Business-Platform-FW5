<?php
require_once dirname(__DIR__).'/sample_email_card/Profile.php';
trait sample_email_card_pages {
    protected function emailCardService(): SampleEmailCardProfile {return new SampleEmailCardProfile();}
    private function emailCardAssign(Controller $ctl,bool $authorized=false): void {
        [$state,$row]=$this->emailCardService()->context($ctl,$authorized,false);
        $ctl->assign('sample_email_card_csrf',$state['csrf']);
        $ctl->assign('sample_email_card_authorized',$authorized);
        $ctl->assign('sample_email_card_email_missing',($row['email']??'')==='');
        foreach(['email'=>($row['email']??'')===''?'メールアドレス':'新しいメールアドレス','code'=>'確認コード'] as $name=>$title){
            $ctl->assign('sample_email_card_'.$name.'_fields',[['parameter_name'=>$name,'parameter_title'=>$title,'parameter_description'=>'','type'=>'text','validation'=>1,'options'=>[]]]);
        }
        if($authorized)$ctl->assign('sample_email_card_row',array_intersect_key($row,array_flip(['name','email','billing_description','monthly_fee']))+['has_card'=>!empty($row['square_card']),'initial_charge'=>$this->emailCardService()->willChargeInitial($ctl,$row)]);
    }
    function page(Controller $ctl): void {
        try{
            $this->emailCardService()->open($ctl,$ctl->GET('token'));
            $this->emailCardAssign($ctl);
            $ctl->assign('sample_email_card_error','');
        }catch(Throwable $e){$ctl->assign('sample_email_card_error',$e instanceof DomainException?$e->getMessage():'URLを確認できませんでした。');}
        header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');
        $ctl->show_public_pages(__DIR__.'/Templates/page.tpl',__DIR__.'/Templates/head.tpl',null,null,['css_mode'=>'minimal']);
    }
    function sample_email_card_send(Controller $ctl): void {
        $ctl->clear_error_message();
        try{
            $this->emailCardService()->sendIdentity($ctl);$this->emailCardAssign($ctl);
            $ctl->assign('sample_email_card_verify_function','sample_email_card_verify');
            $ctl->assign('sample_email_card_verify_help','メールアドレスに確認コードを送信しました。届いた4桁の数字を入力してください。');
            $ctl->show_multi_dialog('sample_email_card_verify',__DIR__.'/Templates/verify.tpl','メールで本人確認',520);
        }catch(Throwable $e){$ctl->res_error_message('form',$e instanceof DomainException?$e->getMessage():'確認コードを送信できませんでした。');}
    }
    function sample_email_card_verify(Controller $ctl): void {
        $ctl->clear_error_message();
        try{
            $this->emailCardService()->verifyIdentity($ctl);$this->emailCardAssign($ctl,true);
            $ctl->close_multi_dialog('sample_email_card_verify');$ctl->reload_area('#sample-email-card-content',__DIR__.'/Templates/content.tpl');
        }catch(Throwable $e){$ctl->res_error_message('code',$e instanceof DomainException?$e->getMessage():'本人確認を完了できませんでした。');}
    }
    function sample_email_card_email(Controller $ctl): void {
        try{
            $this->emailCardService()->context($ctl);$this->emailCardAssign($ctl,true);
            $ctl->show_multi_dialog('sample_email_card_email',__DIR__.'/Templates/email.tpl','メールアドレス変更',520);
        }catch(Throwable $e){$ctl->show_notification_text($e instanceof DomainException?$e->getMessage():'変更画面を表示できませんでした。');}
    }
    function sample_email_card_email_send(Controller $ctl): void {
        $ctl->clear_error_message();
        try{
            $email=$this->emailCardService()->sendEmail($ctl);$this->emailCardAssign($ctl,true);
            $ctl->assign('sample_email_card_verify_function','sample_email_card_email_verify');
            $ctl->assign('sample_email_card_verify_help',$email.' に確認コードを送信しました。届いた4桁の数字を入力してください。');
            $ctl->show_multi_dialog('sample_email_card_email',__DIR__.'/Templates/verify.tpl','新しいメールアドレスの確認',520);
        }catch(Throwable $e){$ctl->res_error_message('email',$e instanceof DomainException?$e->getMessage():'確認コードを送信できませんでした。');}
    }
    function sample_email_card_email_verify(Controller $ctl): void {
        $ctl->clear_error_message();
        try{
            $this->emailCardService()->verifyEmail($ctl);$this->emailCardAssign($ctl,true);
            $ctl->close_multi_dialog('sample_email_card_email');$ctl->reload_area('#sample-email-card-content',__DIR__.'/Templates/content.tpl');
            $ctl->show_notification_text('メールアドレスを変更しました。');
        }catch(Throwable $e){$ctl->res_error_message('code',$e instanceof DomainException?$e->getMessage():'メールアドレスを変更できませんでした。');}
    }
    function sample_email_card_card(Controller $ctl): void {
        try{$params=$this->emailCardService()->beginCard($ctl);$ctl->show_square_dialog('sample_email_card_public','sample_email_card_card_save',$params);}
        catch(Throwable $e){$ctl->show_notification_text($e instanceof DomainException?$e->getMessage():'カード変更画面を表示できませんでした。');}
    }
    function sample_email_card_card_save(Controller $ctl): void {
        $params=$ctl->get_square_callback_parameter_array()??[];
        try{
            $result=$this->emailCardService()->saveCard($ctl,$params);$ctl->close_square_dialog();$this->emailCardAssign($ctl,true);
            $ctl->reload_area('#sample-email-card-content',__DIR__.'/Templates/content.tpl');
            $message='カード情報を更新しました。';
            if($result!==null)$message=match($result['state']??'unknown'){
                'success'=>'カードを登録し、初回料金を決済しました。',
                'failed'=>'カードを登録しましたが、初回決済に失敗しました。管理者へお問い合わせください。',
                default=>'カードを登録しました。初回決済の成否を確認中です。再決済せず、管理者へお問い合わせください。'
            };
            $ctl->show_notification_text($message,8);
        }catch(Throwable $e){$ctl->show_square_dialog('sample_email_card_public','sample_email_card_card_save',$params,$e instanceof DomainException?$e->getMessage():'カード登録を完了できませんでした。再操作の前に管理者へ確認してください。');}
    }
}

class sample_email_card_public {
    use sample_email_card_pages;
    function __construct(Controller $ctl){$ctl->set_check_login(false);}
}
