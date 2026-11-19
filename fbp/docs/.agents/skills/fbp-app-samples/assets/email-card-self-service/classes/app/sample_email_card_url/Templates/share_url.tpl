<style>{literal}
/* Application URL sharing dialog */
.multi_dialog:has(.fbp-share-url-share) .multi_dialog_fixed_bar{display:none}
.multi_dialog:has(.fbp-share-url-share) .multi_dialog_contents::after{display:none}
.multi_dialog:has(.fbp-share-url-share) .multi_dialog_scroll{overflow-y:auto}
.fbp-share-url-share{padding:10px 4px 12px}
.fbp-share-url-help{margin:0 0 16px;color:#64748b;font-size:14px;line-height:1.7}
.fbp-share-url-control{display:flex;align-items:stretch;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;overflow:hidden}
.fbp-share-url-text{flex:1;min-width:0;white-space:normal;overflow-wrap:anywhere;padding:14px 12px;font-size:14px;line-height:22px;color:#334155;text-decoration:none;display:block}
.fbp-share-url-control .fbp-share-url-copy{display:flex;align-items:center;justify-content:center;gap:6px;flex:none;width:48px;min-width:48px;margin:0;padding:0 14px;border:0;border-left:1px solid #cbd5e1;border-radius:0;background:var(--fbp-framework-primary-color, #4BA3FF);color:var(--fbp-framework-primary-text-color, #FFF);font-size:14px;cursor:pointer}
.fbp-share-url-control .fbp-share-url-copy:focus-visible,.fbp-share-url-text:focus-visible{outline:2px solid var(--fbp-framework-primary-color, #4BA3FF);outline-offset:-3px}
.fbp-share-url-share .material-symbols-outlined{font-size:18px}
.fbp-share-url-text:hover{text-decoration:underline;color:var(--fbp-framework-primary-color, #4BA3FF)}
.fbp-share-url-status{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:480px){.fbp-share-url-control .fbp-share-url-copy{width:48px;min-width:48px;padding:0 10px}.fbp-share-url-help{font-size:13px}}
@media(max-width:700px){.multi_dialog:has(.fbp-share-url-share){max-width:calc(100vw - 20px);left:10px!important}}
{/literal}</style>
<script>{literal}
$(document).off('click.fbpShareUrlCopy', '.fbp-share-url-copy').on('click.fbpShareUrlCopy', '.fbp-share-url-copy', async function () {
    const button = $(this);
    const share = button.closest('.fbp-share-url-share');
    const status = share.find('.fbp-share-url-status');
    try {
        await navigator.clipboard.writeText(button.attr('data-copy'));
        button.find('.material-symbols-outlined').text('check');
        button.attr('title', 'コピーしました').attr('aria-label', 'コピーしました');
        status.text('コピーしました');
    } catch (e) {
        const value = share.find('.fbp-share-url-text')[0];
        value.focus();
        const range = document.createRange();
        range.selectNodeContents(value);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        button.find('.material-symbols-outlined').text('content_copy');
        button.attr('title', 'URLを選択しました。手動でコピーしてください。').attr('aria-label', 'URLを選択しました。手動でコピーしてください。');
        status.text('URLを選択しました。手動でコピーしてください。');
    }
});
{/literal}</script>
<div class="fbp-share-url-share">
<p class="fbp-share-url-help">{$share_url_help|escape}</p>
<div class="fbp-share-url-control">
<a class="fbp-share-url-text" href="{$share_url|escape}" target="_blank" rel="noopener" title="{$share_url_open_label|escape}" aria-label="{$share_url_open_label|escape}（新しいタブで開く）">{$share_url|escape}</a>
<button type="button" class="fbp-share-url-copy" data-copy="{$share_url|escape}" aria-label="URLをコピー" title="URLをコピー"><span class="material-symbols-outlined" aria-hidden="true">content_copy</span><span class="fbp-share-url-status" role="status" aria-live="polite"></span></button>
</div>
</div>
