<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="referrer" content="same-origin"><title>ログイン処理中</title></head><body>
<p>管理画面へログインしています。</p>
<form id="managed-login" method="post" action="{$managed_action|escape}">
<input type="hidden" name="ticket" value="{$managed_form.ticket|escape}"><input type="hidden" name="csrf" value="{$managed_form.csrf|escape}"><input type="hidden" name="complete" value="1"><input type="hidden" name="_windowcode" value="{$managed_window|escape}">
<noscript><button type="submit">ログインする</button></noscript>
</form>
<script data-window="{$managed_window|escape}" data-path="{$managed_cookie_path|escape}">{literal}
(()=>{const script=document.currentScript;try{sessionStorage.setItem('windowID',script.dataset.window);}catch(e){}document.cookie='windowID='+encodeURIComponent(script.dataset.window)+'; Path='+script.dataset.path+'; Secure; SameSite=Lax';window.opener=null;document.getElementById('managed-login').submit();})();
{/literal}</script></body></html>
