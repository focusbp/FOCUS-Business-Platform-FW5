<?php
require_once dirname(__DIR__).'/sample_email_card/Profile.php';
class sample_email_card_url implements CodegenActionInterface {
    function run(Controller $ctl): void {
        if(!$ctl->is_app_admin()){$ctl->show_notification_text('管理者のみ操作できます。');return;}
        try{
            SampleEmailCardProfile::prepare($ctl);$value=$ctl->POST('id');$value=is_scalar($value)?(string)$value:'';
            $id=is_string($value)&&ctype_digit($value)?(int)$value:(int)$ctl->decrypt($value);
            $row=SampleEmailCardProfile::row($ctl,$id);
            $ctl->assign('share_url',SampleEmailCardProfile::url($ctl,$row));
            $ctl->assign('share_url_help','契約者へこのURLをご案内ください。メールの確認後、メールアドレス・カードを登録／変更できます。有効期限は30日です。メール変更後はURLを再発行してください。');
            $ctl->assign('share_url_open_label','メール・カード変更画面');
            $ctl->show_multi_dialog('sample_email_card_url','share_url.tpl','メール・カード変更URL',680);
        }catch(Throwable $e){$ctl->show_notification_text($e instanceof DomainException?$e->getMessage():'変更URLを取得できませんでした。');}
    }
}
