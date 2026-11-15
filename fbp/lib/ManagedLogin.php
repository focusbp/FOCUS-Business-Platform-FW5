<?php
/** Transport compatible with db_api HMAC; this API also binds the POST body. */
final class ManagedLoginHttp {
    static function base(string $url): string {
        $parts=parse_url($url);
        if(!$parts||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||isset($parts['query'])||isset($parts['fragment'])||preg_match('~[\\\\\x00-\x20]~',$url))throw new RuntimeException('Invalid endpoint.');
        $path=$parts['path']??'';
        if(str_contains($path,'..')||str_contains($path,'%'))throw new RuntimeException('Invalid endpoint.');
        return rtrim($url,'/');
    }
    static function request(string $base,string $class,string $function,array $payload,string $key,string $secret,array $httpBasic=[]): array {
        $base=self::base($base);$body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if($key===''||$secret===''||strlen($body)>16384)throw new RuntimeException('API configuration required.');
        $query=http_build_query(['class'=>$class,'function'=>$function,'body_sha256'=>hash('sha256',$body)],'','&',PHP_QUERY_RFC3986);
        $url=$base.'/fbp/app.php?'.$query;$ts=(string)time();$path=parse_url($url,PHP_URL_PATH);
        $sign=hash_hmac('sha256',"POST\n".$path."\n".$query."\n".$ts,$secret);
        $out='';$curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-API-KEY: '.$key,'X-API-TS: '.$ts,'X-API-SIGN: '.$sign],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_WRITEFUNCTION=>static function($ch,$part)use(&$out){if(strlen($out)+strlen($part)>32768)return 0;$out.=$part;return strlen($part);}]);
        $basic=$httpBasic[parse_url($base,PHP_URL_HOST)]??null;
        if(is_array($basic)&&is_string($basic['username']??null)&&is_string($basic['password']??null))curl_setopt_array($curl,[CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$basic['username'].':'.$basic['password']]);
        try{$ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);}finally{curl_close($curl);}
        if($ok===false||$status!==200)throw new RuntimeException('Management login communication failed.');
        $result=json_decode($out,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($result)||($result['ok']??false)!==true)throw new RuntimeException('Management login denied.');return $result;
    }
    static function verify(string $key,string $secret,string $body): array {
        $ts=$_SERVER['HTTP_X_API_TS']??'';$signature=$_SERVER['HTTP_X_API_SIGN']??'';
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||$key===''||$secret===''||!is_string($ts)||!ctype_digit($ts)||abs(time()-(int)$ts)>60||strlen($body)>16384||!hash_equals($key,(string)($_SERVER['HTTP_X_API_KEY']??'')))throw new RuntimeException('API denied.');
        $query=(string)($_SERVER['QUERY_STRING']??'');parse_str($query,$args);
        if(!isset($args['body_sha256'])||!is_string($args['body_sha256'])||!hash_equals(hash('sha256',$body),$args['body_sha256']))throw new RuntimeException('Body denied.');
        $path=(string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
        $expected=hash_hmac('sha256',"POST\n".$path."\n".$query."\n".$ts,$secret);
        if(!is_string($signature)||!hash_equals($expected,strtolower($signature)))throw new RuntimeException('Signature denied.');
        $data=json_decode($body,true,16,JSON_THROW_ON_ERROR);if(!is_array($data)||array_is_list($data))throw new RuntimeException('Invalid payload.');return $data;
    }
}
final class ManagedLoginConfig {
    static function load(Controller $ctl): array {
        $file=$ctl->dirs->datadir.'/_common/managed_login.php';
        if(!is_file($file))throw new RuntimeException('管理アプリからのログインが未設定です。');
        $cfg=require $file;
        if(!is_array($cfg)||($cfg['enabled']??false)!==true||!in_array($cfg['environment']??null,['test','production'],true))throw new RuntimeException('管理アプリからのログインが未設定です。');
        $cfg['base_url']=ManagedLoginHttp::base($cfg['base_url']??'');$cfg['issuer_base_url']=ManagedLoginHttp::base($cfg['issuer_base_url']??'');
        $pk=PasskeyConfig::load($ctl);$current=rtrim(preg_replace('/^http:/','https:',$ctl->get_APP_URL()),'/');
        $allowed=[$cfg['base_url']];
        if(($cfg['issuer']??false)===true)foreach($cfg['allowed_base_urls']??[] as $alias)$allowed[]=ManagedLoginHttp::base($alias);
        if(!in_array($current,$allowed,true)||$pk['origin']!=='https://'.parse_url($current,PHP_URL_HOST))throw new RuntimeException('ログイン先の設定が一致しません。');
        $cfg['request_base_url']=$current;return $cfg;
    }
}
/** Process-local access, never activated by request or session parameters. */
final class ManagedLoginScope {
    private static array $depth=[];
    static function active(Controller $ctl): bool {return (self::$depth[spl_object_id($ctl)]??0)>0;}
    static function run(Controller $ctl,callable $fn): mixed {
        $id=spl_object_id($ctl);self::$depth[$id]=(self::$depth[$id]??0)+1;
        try{return $fn();}finally{if(--self::$depth[$id]===0)unset(self::$depth[$id]);}
    }
}
