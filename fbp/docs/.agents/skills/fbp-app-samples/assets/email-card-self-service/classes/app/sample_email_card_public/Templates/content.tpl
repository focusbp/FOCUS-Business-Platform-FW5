{if !$sample_email_card_authorized}
<p class="sample-email-card-note">{if $sample_email_card_email_missing}メールアドレスを登録して確認すると、お支払いカードを登録・変更できます。{else}登録済みメールアドレスで本人確認をすると、メールアドレスとお支払いカードを変更できます。{/if}</p>
<form id="sample_email_card_identity" class="public-form" onsubmit="return false;">
<input type="hidden" name="csrf" value="{$sample_email_card_csrf|escape}">
{if $sample_email_card_email_missing}<div class="public-field">{fields_form_direct field_group=$sample_email_card_email_fields data=["email"=>""] item_margin_top="0px"}<p class="error_message error_email" role="alert"></p></div>{/if}
<p class="error_message error_form" role="alert"></p>
<div class="public-form-actions"><button type="button" class="ajax-link" data-class="sample_email_card_public" data-function="sample_email_card_send" data-form="sample_email_card_identity">確認コードを送信する</button></div>
</form>
{else}
<dl class="sample-email-card-details">
<dt>ご契約者</dt><dd>{$sample_email_card_row.name|escape}</dd>
<dt>メールアドレス</dt><dd>
<form id="sample_email_card_email_action" class="public-form sample-email-card-email" onsubmit="return false;">
<input type="hidden" name="csrf" value="{$sample_email_card_csrf|escape}">
<span class="sample-email-card-email-address">{$sample_email_card_row.email|escape}</span>
<button type="button" class="ajax-link" data-class="sample_email_card_public" data-function="sample_email_card_email" data-form="sample_email_card_email_action">メールアドレスを変更</button>
</form>
</dd>
<dt>請求内容</dt><dd>{$sample_email_card_row.billing_description|escape}</dd>
<dt>月額料金</dt><dd>{$sample_email_card_row.monthly_fee|number_format}円</dd>
</dl>
{if $sample_email_card_row.initial_charge}<p class="sample-email-card-note">初めてカードを登録すると、上記の月額料金を満額決済します。日割り計算は行いません。</p>{/if}
<form id="sample_email_card_actions" class="public-form" onsubmit="return false;">
<input type="hidden" name="csrf" value="{$sample_email_card_csrf|escape}">
<div class="public-actions"><div class="public-actions-main">
<button type="button" class="ajax-link" data-class="sample_email_card_public" data-function="sample_email_card_card" data-form="sample_email_card_actions">{if $sample_email_card_row.initial_charge}カードを登録・初回料金を決済{elseif $sample_email_card_row.has_card}カードを変更{else}カードを登録{/if}</button>
</div></div>
</form>
{/if}
