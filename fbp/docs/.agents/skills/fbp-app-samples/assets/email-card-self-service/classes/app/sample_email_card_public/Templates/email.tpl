<form id="sample_email_card_email_form" class="public-form" onsubmit="return false;">
<input type="hidden" name="csrf" value="{$sample_email_card_csrf|escape}">
<div class="public-field">{fields_form_direct field_group=$sample_email_card_email_fields data=["email"=>""] item_margin_top="0px"}<p class="error_message error_email" role="alert"></p></div>
<p class="error_message error_form" role="alert"></p>
<div class="public-form-actions"><button type="button" class="ajax-link" data-class="sample_email_card_public" data-function="sample_email_card_email_send" data-form="sample_email_card_email_form">確認コードを送信する</button></div>
</form>
