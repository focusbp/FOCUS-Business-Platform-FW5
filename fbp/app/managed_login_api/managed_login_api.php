<?php
require_once __DIR__.'/../login/ManagedMasterLoginService.php';
class managed_login_api {
    function __construct(Controller $ctl){$ctl->set_check_login(false);}
    function capability(Controller $ctl): void {
        header('Cache-Control: no-store');header('Content-Type: application/json');$ctl->stop_res=true;
        try{ManagedLoginConfig::load($ctl);ManagedMasterLoginService::requireSupportedLogin($ctl);echo json_encode(['ok'=>true,'managed_master'=>1]);}catch(Throwable $e){echo json_encode(['ok'=>true,'managed_master'=>0]);}
    }
    function prepare(Controller $ctl): void {
        header('Cache-Control: no-store');header('Content-Type: application/json');$ctl->stop_res=true;
        try{
            $raw=file_get_contents('php://input',false,null,0,16385);$s=$ctl->get_setting();$p=ManagedLoginHttp::verify($s['api_key']??'',$s['api_secret']??'',$raw);
            $ctl->set_dsp_system_subject(['kind'=>'api','verified'=>true]);
            echo json_encode((new ManagedMasterLoginService($ctl))->prepare($p),JSON_THROW_ON_ERROR);
        }catch(Throwable $e){http_response_code(403);echo '{"ok":false,"error":"managed_login_denied"}';}
    }
}
