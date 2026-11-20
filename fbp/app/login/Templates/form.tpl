
{if $flg_login_logo}
	<img src="app.php?class=login&function=logo">
{else}
	<img src="app.php?class=login&function=logo_default">
{/if}

{if $user}
	<div>
		<h5>{t key="login.account_created_help"}</h5>
		<p class="first_account">{t key="user.login_id"} : {$user.login_id}</p>
		<p class="first_account">{t key="login.password"} : {$user.password}</p>
	</div>
{/if}

<form id="login_form">
	<input type="hidden" name="class" value="login">
	<input type="hidden" name="function" value="check">
	<div class="form-wrap form-wrap-validation has-error">
		<p>{t key="user.login_id"}</p>
		<input type="text" name="login_id" value="{$login_id}" autocomplete="username">
		<p style="margin-top:10px;">{t key="login.password"}</p>
		<input type="password" name="password" value="{$password}" autocomplete="current-password">
		<p id="err_password" class="error">{$err_password}</p>
	</div>	

	<button class="ajax-link" data-form="login_form" style="float:right;margin-top:18px;">{t key="login.login_button"}</button>

</form>

{if $passkey_url}<div style="clear:both;padding-top:24px;"><p class="passkey-migration-notice">2026年11月末でパスキーに完全移行します。今月中にパスキー登録とログイン確認をお願いします。</p><form id="passkey_login_form">
<input type="hidden" name="class" value="login">
<input type="hidden" name="function" value="passkey_start">
<input type="hidden" name="_passkey_start_csrf" value="{$passkey_start_csrf|escape}">
<button type="button" class="ajax-link" data-form="passkey_login_form">パスキーでログイン</button>
</form></div>{/if}
