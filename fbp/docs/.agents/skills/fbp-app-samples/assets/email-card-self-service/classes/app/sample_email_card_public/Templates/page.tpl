<main class="sample-email-card">
<h1>メール・カード変更</h1>
{if $sample_email_card_error|default:''}
<p role="alert">{$sample_email_card_error|escape}</p>
{else}
<div id="sample-email-card-content">{include file="./content.tpl"}</div>
{/if}
</main>
