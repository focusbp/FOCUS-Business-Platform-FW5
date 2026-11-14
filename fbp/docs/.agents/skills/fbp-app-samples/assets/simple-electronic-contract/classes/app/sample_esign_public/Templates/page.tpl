<main class="aes-page"><h1>{$aes_row.title|escape}</h1>
<p>当事者：{foreach $aes_names as $name}{$name|escape}{if !$name@last} ／ {/if}{/foreach}</p>
<p>あなた：{$aes_party.name|escape}（{$aes_party.email|escape}）</p>
<p class="error_message error_form" role="alert">{$aes_error|escape}</p>
{if $aes_mode == 'download'}
<h2>2名の承認が揃い、締結が完了しました。</h2><p>ダウンロードURLの有効期限：{$aes_expires|escape}</p>
<div class="aes-actions"><a class="button_link" href="{$aes_zip_url|escape}" referrerpolicy="no-referrer">証跡ZIPをダウンロード</a></div>
{elseif $aes_mode == 'completed'}<h2>締結済みです。</h2><p>双方に送られた締結完了メールのリンクから完了ファイルを取得してください。</p>
{elseif $aes_mode == 'waiting'}<h2>あなたの承認は完了しています。</h2><p>状態：{$aes_status|escape}</p><p>2名の承認と完了ファイルの生成が揃うと、双方に締結完了メールを送信します。</p>
{else}
<p>契約URLの有効期限：{$aes_expires|escape}</p><p><a class="button_link" href="{$aes_original_url|escape}" target="_blank" rel="noopener noreferrer">契約PDFを確認</a></p>
{assign var="aes_action" value="send_code"}{if $aes_mode == 'code'}{assign var="aes_action" value="verify_code"}{elseif $aes_mode == 'approve'}{assign var="aes_action" value="approve"}{/if}
<form method="post" action="{$ctl->get_APP_URL('sample_esign_public', $aes_action)|escape}" class="public-form">
<input type="hidden" name="flow" value="{$aes_flow|escape}"><input type="hidden" name="csrf" value="{$aes_csrf|escape}">
{if $aes_mode == 'code'}<p>上記のメールアドレスに4桁の確認コードを送信しました。有効期限は10分です。</p>{fields_form_original name="code" type="text" title="確認コード" value=""}<p class="error_message error_code" role="alert"></p><div class="aes-actions"><button type="submit" class="button_link">確認する</button></div>
{elseif $aes_mode == 'approve'}<p>メール認証が完了しました。PDFと当事者情報を確認し、同意する場合に承認してください。</p>{fields_form_original name="agree" type="checkbox" title="契約内容への同意" options_arr=['1'=>'PDFの内容に同意します'] value=[]}<p class="error_message error_agree" role="alert"></p><div class="aes-actions"><button type="submit" class="button_link">同意して承認</button></div>
{else}<p>PDFを確認後、あなたの指定メールアドレスへ確認コードを送信します。宛先は変更できません。</p><div class="aes-actions"><button type="submit" class="button_link">確認コードを送信</button></div>{/if}
</form>
{/if}
</main>
