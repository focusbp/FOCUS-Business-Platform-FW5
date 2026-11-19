<form id="sample_email_card_verify_form" class="public-form" onsubmit="return false;">
<input type="hidden" name="csrf" value="{$sample_email_card_csrf|escape}">
<p>{$sample_email_card_verify_help|escape} 有効期限は10分です。</p>
<div class="public-field">{fields_form_direct field_group=$sample_email_card_code_fields data=["code"=>""] item_margin_top="0px"}<p class="error_message error_code" role="alert"></p></div>
<p class="error_message error_form" role="alert"></p>
<div class="public-form-actions"><button type="button" class="ajax-link" data-class="sample_email_card_public" data-function="{$sample_email_card_verify_function|escape}" data-form="sample_email_card_verify_form">確認して完了</button></div>
</form>
