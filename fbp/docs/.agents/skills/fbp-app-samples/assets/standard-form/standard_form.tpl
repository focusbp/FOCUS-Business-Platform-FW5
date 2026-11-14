{* Required: standard_form_id/action_class/save_function/csrf/fields.
   Each field: name,title,type,value,options. DB fields may supply definition + standard_form_row.
   Keep public-form/public-field/error_* together; framework CSS hides empty errors. *}
{include file="./standard_form_style.tpl"}
<form id="{$standard_form_id|escape}" class="public-form fbp-standard-form" onsubmit="return false;">
<input type="hidden" name="csrf" value="{$standard_form_csrf|escape}">
{foreach $standard_form_fields as $field}
<div class="public-field">
{if isset($field.definition)}
{fields_form_direct field_group=[$field.definition] data=$standard_form_row item_margin_top="0px"}
{else}
{fields_form_original name=$field.name type=$field.type title=$field.title value=$field.value|default:''|escape options_arr=$field.options|default:[] item_margin_top="0px"}
{/if}
<p class="error_message error_{$field.name|escape}" role="alert"></p>
</div>
{/foreach}
<p class="error_message error_form" role="alert"></p>
<div class="public-form-actions">
<button type="button" class="ajax-link button_link" data-class="{$standard_form_action_class|escape}" data-function="{$standard_form_save_function|escape}" data-form="{$standard_form_id|escape}">{$standard_form_save_label|default:'保存'|escape}</button>
</div>
</form>
