{include file="image_annotation_style.tpl"}
<section class="fbp-image-annotation" aria-label="画像の赤字編集">
<div data-ia="error" class="ia-error error_message error_form" role="alert" hidden></div>
<div data-ia="stage" class="ia-stage">
<img data-ia="image" class="ia-image" alt="赤字を入れる画像">
<svg data-ia="overlay" class="ia-overlay" xmlns="http://www.w3.org/2000/svg" aria-label="赤字を描く領域"></svg>
<div class="ia-tools" role="toolbar" aria-label="画像編集ツール" aria-orientation="vertical">
<button type="button" data-ia-tool="move" title="移動：赤字をドラッグ" aria-label="移動" aria-pressed="false"><svg viewBox="0 -960 960 960" aria-hidden="true"><path d="M480-80 310-250l57-57 73 73v-166h80v165l72-73 58 58L480-80ZM250-310 80-480l169-169 57 57-72 72h166v80H235l73 72-58 58Zm460 0-57-57 73-73H560v-80h165l-73-72 58-58 170 170-170 170ZM440-560v-166l-73 73-57-57 170-170 170 170-57 57-73-73v166h-80Z"/></svg></button>
<button type="button" data-ia-tool="arrow" title="矢印" aria-label="矢印" aria-pressed="true"><svg viewBox="0 -960 960 960" aria-hidden="true"><path d="m216-160-56-56 464-464H360v-80h400v400h-80v-264L216-160Z"/></svg></button>
<button type="button" data-ia-tool="rect" title="赤枠" aria-label="赤枠" aria-pressed="false"><svg viewBox="0 -960 960 960" aria-hidden="true"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0 0v-560 560Z"/></svg></button>
<button type="button" data-ia-tool="text" title="文字：画像上をクリックして入力・編集" aria-label="文字" aria-pressed="false"><svg viewBox="0 -960 960 960" aria-hidden="true"><path d="M420-160v-520H200v-120h560v120H540v520H420Z"/></svg></button>
<button type="button" data-ia="undo" title="1つ戻す" aria-label="1つ戻す"><svg viewBox="0 -960 960 960" aria-hidden="true"><path d="M280-200v-80h284q63 0 109.5-40T720-420q0-60-46.5-100T564-560H312l104 104-56 56-200-200 200-200 56 56-104 104h252q97 0 166.5 63T800-420q0 94-69.5 157T564-200H280Z"/></svg></button>
<button type="button" data-ia="delete" title="選択した赤字を削除" aria-label="選択した赤字を削除"><svg viewBox="0 -960 960 960" aria-hidden="true"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg></button>
</div>
<div data-ia="inline" class="ia-inline" contenteditable="plaintext-only" role="textbox" aria-label="画像上の文字" aria-multiline="false" hidden></div>
</div>
<p class="ia-help">文字は画像上をクリックして入力・編集。移動ツールで赤字をドラッグできます。</p>
<div class="ia-actions"><button data-ia="submit" type="button">編集結果を取得</button></div>
<p data-ia="status" class="ia-status" role="status"></p>
</section>
{include file="image_annotation_script.tpl"}
