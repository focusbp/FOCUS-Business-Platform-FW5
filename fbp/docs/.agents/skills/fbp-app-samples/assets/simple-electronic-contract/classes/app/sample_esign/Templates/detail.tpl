{include file="./standard_form_style.tpl"}
<form id="sample_esign_detail" class="public-form fbp-standard-form" onsubmit="return false;">
<input type="hidden" name="id" value="{$esign_id|escape}"><input type="hidden" name="csrf" value="{$esign_csrf|escape}">
<p>状態：{$esign_status|escape}</p>
{foreach $esign_state.parties as $p}<p>{$p.name|escape}（{$p.email|escape}）：{if $p.approved_at}承認済み{else}承認待ち{/if} ／ {if $esign_row.status == 'completed'}{if $p.complete_sent}完了メール送信済み{else}完了メール未送信{/if}{else}{if $p.invite_sent}案内メール送信済み{else}案内メール未送信{/if}{/if}</p>{/foreach}
<p class="error_message error_form" role="alert"></p>
<div class="public-form-actions"><button type="button" class="ajax-link" data-class="sample_esign" data-function="retry" data-form="sample_esign_detail">未送信分・完了処理を再試行</button>{if $esign_row.status == 'completed'}<a class="button_link" href="{$esign_zip_url|escape}" referrerpolicy="no-referrer">証跡ZIP</a>{/if}</div>
</form>
