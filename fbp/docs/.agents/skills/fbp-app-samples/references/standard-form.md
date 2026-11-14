# 標準フォームの基本形

入力部品だけでなく、親フォーム・項目wrapper・エラー・操作行まで一組として再利用する。公開側minimalフォームと、管理側Original Screen／ダイアログの独自フォームが対象。Standard Screenの自動CRUDを置き換えるためのサンプルではない。DB不要。

## 配置

`assets/standard-form/standard_form.tpl` を対象クラスの `Templates` へコピーし、必要な範囲を適応する。

同梱の`standard_form_style.tpl`も一組で配置する。`fbp-standard-form`配下の配置・空エラー・ボタン位置だけを補い、入力部品の色・フォント等は読み込み中のframework標準を使う。管理画面へpublicsite_minimal.css全体を読み込む必要はない。

```sh
php docs/.agents/skills/fbp-app-samples/scripts/install_standard_form.php <app-source-root> <class-name>
```

対象クラスは既存であること。同名テンプレートを上書きしない。DB・認証・公開ルート・保存処理は作成しない。既存ページからincludeするか、通常のダイアログの本文に使う。フォームを別のformで囲まない。includeを複数置く場合は一意のフォームIDを割り当てる。

## 呼出し側

Controllerで次の変数を割り当てる。action_class/save_functionは実装済みの保存先をサーバー側で指定する。

```php
$ctl->assign('standard_form_id', 'record_form');
$ctl->assign('standard_form_action_class', 'my_editor');
$ctl->assign('standard_form_save_function', 'save');
$ctl->assign('standard_form_save_label', '保存');
$ctl->assign('standard_form_csrf', $csrf); // アプリの既存CSRF方式で発行・検証
$ctl->assign('standard_form_fields', [
    ['name'=>'title','title'=>'名称','type'=>'text','value'=>'','options'=>[]],
    ['name'=>'amount','title'=>'金額','type'=>'number','value'=>'0','options'=>[]],
    ['name'=>'target_date','title'=>'日付','type'=>'date','value'=>'','options'=>[]],
    ['name'=>'memo','title'=>'備考','type'=>'textarea','value'=>'','options'=>[]],
]);
```

- 項目名はサーバー側の定義（英字で始まる英数字・アンダースコア）から作り、リクエストの任意キーを部品定義や関数名にしない。
- DB項目は`definition`へ既存の項目定義を渡し、`standard_form_row`へ行データを割り当てて`fields_form_direct`を使う。行データのエスケープは通常のdirect helperの取扱いに従う。
- DB定義のない項目は`fields_form_original`を使う。text/number/date等を手書きinputで代替しない。`date`の初期値はtimestampまたは空文字、POSTは標準部品のtimestampを正規化する。
- 必須・形式・長さ・認可は業務要件に応じて保存側で検証する。各`res_error_message(name,...)`に対応する`error_name`を残し、全体エラーは`error_form`へ出す。エラー時はフォームを再描画せず、入力を保持する。
- 保存ハンドラの応答では`$ctl->clear_error_message()`を使って前回の表示をクリアし、今回のエラーを追加する。成功通知だけを返すと古いエラーが残るため、成功時もクリア応答を返す。
- readonly値・ファイル・checkbox等、専用の扱いが必要な項目は該当Skillに従って適応する。サンプルをそのまま全種類の入力へ適用しない。

## 余白の責任範囲

`public-form`が項目間、`public-field`が部品とエラー表示を管理する。部品側の追加余白は`item_margin_top="0px"`として、親と部品の余白を重ねない。保存ボタンは`public-form-actions`で下部右寄せにする。

公開側minimalの標準CSSでは、空のエラー表示を`.public-form .error_message:empty`で非表示にする。`public-form`を外すと、空のpの高さ・margin・grid gapが残り、標準入力部品でも間隔が広がる。管理側でもクラスとエラー要素の組を保ち、その画面の共通CSSが適用されていることを確認する。CSS全文や場当たり的な余白上書きを各フォームへ複製しない。

## 検証

- 通常時：親のclass、一意のID、標準入力、空エラーの非表示、ボタン右寄せ、狭い幅でのはみ出しを確認。
- エラー時：複数項目と全体のエラーを表示し、入力値が残ることを確認。成功時にエラーが消えることも確認する。
- 部品チェックは`local-template-check`、操作・computed styleの確認は`fbp-playwright`を使う。標準部品であることだけでレイアウト確認を省略しない。
- このサンプル追加だけで既存アプリを一括変更・本番反映しない。

検証済み：実FBPの4項目レンダリング、空エラー非表示、下部右寄せ、複数エラーと入力保持、成功時のエラー消去、狭い幅の配置、公開側CSSなしでのスコープ付き空エラー制御。配置スクリプトの実行と既存ファイルの上書き拒否、PHP/JSON/Skill形式、手書き入力チェックも確認済み。

共通対応範囲リストには、公開・独自管理画面のフォーム基本形として記載する。既存の標準UI対応範囲内の開発補助サンプルであり、認証や業務処理を省略するものではない。
