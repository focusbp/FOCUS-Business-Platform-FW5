<?php
/** Management policy hook. Default: logged-in application administrators only. */
class SampleESignGuard {
    static function allowed(Controller $c): bool {return (bool)$c->get_session('login')&&$c->is_app_admin();}
    static function requireAdmin(Controller $c): void {if(!self::allowed($c))throw new DomainException('管理者としてログインしてください。');}
    static function nonce(Controller $c): string {$s=$c->get_session('sample_esign_csrf');if(!is_string($s)||!preg_match('/^[a-f0-9]{48}$/D',$s)){$s=bin2hex(random_bytes(24));$c->set_session('sample_esign_csrf',$s);}return $s;}
    static function check(Controller $c): void {self::requireAdmin($c);$v=$c->POST('csrf');if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!is_string($v)||!hash_equals(self::nonce($c),$v))throw new DomainException('画面を開き直してください。');}
}
