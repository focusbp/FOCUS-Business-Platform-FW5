{include file="./standard_form_style.tpl"}
<form id="sample_esign_wizard" class="public-form fbp-standard-form" onsubmit="return false;">
<input type="hidden" name="id" value="{$esign_id|escape}"><input type="hidden" name="csrf" value="{$esign_csrf|escape}">
{if $esign_row.status != 'input'}
<h3>送信結果</h3>
{if $esign_state.parties[0].invite_sent && $esign_state.parties[1].invite_sent}<p>双方にメールを送信しました。それぞれのメールから認証・承認してください。</p>{else}<p>未送信の宛先があります。一覧の「詳細」から30秒以上あけて再試行してください。</p>{/if}
{foreach $esign_state.parties as $p}<p>{$p.name|escape}（{$p.email|escape}）：{if $p.invite_sent}送信済み{else}未送信{/if}</p>{/foreach}
<div class="public-form-actions"><button type="button" class="ajax-link" data-class="sample_esign" data-function="close">閉じる</button></div>
{else}
<p>ステップ {$esign_state.step} / 3</p>
{if $esign_state.step == 1}
<div class="public-field">{fields_form_original name="recipient_name" type="text" title="相手の氏名" value=$esign_state.parties[1].name|default:''|escape item_margin_top="0px"}<p class="error_message error_recipient_name" role="alert"></p></div>
<div class="public-field">{fields_form_original name="recipient_email" type="text" title="相手のメールアドレス" value=$esign_state.parties[1].email|default:''|escape item_margin_top="0px"}<p class="error_message error_recipient_email" role="alert"></p></div>
<p>自分：{$esign_state.parties[0].name|escape}（{$esign_state.parties[0].email|escape}）</p>
{elseif $esign_state.step == 2}
<div class="public-field">{fields_form_original name="contract_pdf" type="file" title="契約PDF（1ファイル・20MB以下）" item_margin_top="0px"}<p class="error_message error_contract_pdf" role="alert"></p></div>
{if $esign_row.original_pdf}<p>添付済み：{$esign_state.filename|escape}</p>{/if}
{elseif $esign_state.step == 3}
<div class="public-field">{fields_form_original name="title" type="text" title="契約名" value=$esign_row.title|escape item_margin_top="0px"}<p class="error_message error_title" role="alert"></p></div>
{foreach $esign_state.parties as $p}<p>{$p.name|escape}（{$p.email|escape}）</p>{/foreach}
<p>添付：{$esign_state.filename|escape}　<a href="{$esign_pdf_url|escape}" target="_blank" rel="noopener noreferrer">PDFを確認</a></p>
<p>双方に個別のURLを送ります。送信確定後の契約URLは10日間有効です。作成者もメール認証・承認が必要です。</p>
{/if}
<p class="error_message error_form" role="alert"></p>
<div class="public-form-actions">{if $esign_state.step > 1}<button type="button" class="ajax-link" data-class="sample_esign" data-function="back" data-form="sample_esign_wizard">戻る</button>{/if}<button type="button" class="ajax-link" data-class="sample_esign" data-function="{if $esign_state.step == 3}send{else}next{/if}" data-form="sample_esign_wizard">{if $esign_state.step == 3}双方にメールを送信{else}次へ{/if}</button></div>
{/if}
</form>
{literal}<script>(function(){const f=document.getElementById('sample_esign_wizard');if(!f)return;const setup=()=>f.querySelectorAll('input[type=file][name=contract_pdf]').forEach(i=>{i.accept='application/pdf,.pdf';i.multiple=false;i.dataset.multiple='false'});setup();new MutationObserver(setup).observe(f,{childList:true,subtree:true});})();</script>
<style>#sample_esign_wizard input[type=file].hidden{display:none!important}.multi_dialog:has(#sample_esign_wizard){max-width:calc(100vw - 24px);box-sizing:border-box}</style>{/literal}
