---
name: fbp-cli
description: Execute and verify FBP features through cli.php commands including app_call/app_check and schema/data inspection.
---

# fbp-cli

## trigger conditions
- 実装前後のCLI確認が必要
- `app_call` / `app_check` で画面導線を検証したい
- `db_*` / `data_*` / `cron_list` 等の状態確認をしたい

## 標準画面の設定確認

標準機能だけのノート作成・表示項目変更の完了条件は `fbp-standard-screen` の「標準画面の設定確認と完了条件」を正本とする。ビルダーのチェッカー結果を依頼と照合し、本Skillの通常の初動・作成後CLI確認を重ねない。独自処理・明示された操作確認・本番リリースの判断にはそれぞれの確認を適用する。

## workflow
1. 既存ノートが特定できる実装探索は、環境にノートからクラス・関数へ辿る参照専用ツールがあれば先に使う。ノート不明で文言やキーワードから探す場合は、環境に定義・設定・コードの横断検索があれば先に使う。対象の設定・入口が得られた場合、初動の全件取得や広範囲検索を重ねない。それらで解決できない範囲だけ `db_schema`, `db_tables_list`, `db_additionals_list` を確認。
2. 必要なら `cron_list`, `webhook_rule_list`, `embed_app_list` を確認。
3. 一括投入は「1コマンド1JSON」で実行し、各ステップの必須キーを事前検証してから流す。
4. 実装後は変更した結果を確認する最小の方法を選ぶ。`app_call` / `app_check` / 関連する既存テストのすべてを機械的に実行しない。ブラウザ等で同じ期待値を確認済みなら重ねない。
5. 更新結果が応答や既存検証だけでは分からない場合に `data_get` / `data_list` で必要な項目を確認する。画面操作を変えない内部処理にスクリーンショットを要求しない。

## quick commands
- ヘルパー関数単位の入出力確認が必要なら、一時PHPや `php -r` の依存読込みを自作する前に `method_call` を使う。追加検証自体が不要なら実行しない。詳細は [関数単位の確認](references/method-call.md)。
- private/protected/staticを含む関数の戻り値と期待値:
  `php <app-root>/fbp/cli.php method_call --json='{"class":"helper","function":"format","args":[3661],"expect":"1.01.01"}'`
- ノート探索・キーワード横断検索で解決できない範囲は、対象アプリの `cli.php` で必要な `db_schema` / `db_tables_list` / `db_additionals_list` を確認する。
- 画面の生レスポンス確認:
  `php <app-root>/fbp/cli.php app_call --json='{"class":"setting","function":"page"}'`
- 期待値検証:
  `php <app-root>/fbp/cli.php app_check --json='{"class":"public_form","function":"index","get":{"key":"abc"}}'`
- 更新結果確認:
  `php <app-root>/fbp/cli.php data_get --json='{"table":"customers","id":1}'`
  `php <app-root>/fbp/cli.php data_list --json='{"table":"customers","max":100}'`
- HMAC API 経由のデータ更新は、環境固有のクライアント設定に従う。
- 定期処理設定の確認:
  `php <app-root>/fbp/cli.php cron_list --json='{"id":1}'`

## Excel風ノートの開発時検証

Developer Modeでも、web側CLIの `db_tables_add`／`db_tables_edit` でExcel風（list_type=4）の作成・画面パターン変更ができる。列設定はExcel画面の正規処理を使い、db_fieldsへ二重登録しない。Codexの通常作業として扱い、モード変更や特例の許可は不要。

表示だけ検証する場合は `excel_preview_set --json='{"tb_name":"example","session_id":"ExcelCheck","windowcode":"ExcelCheck"}'` を使い、同じsession_id・windowcodeのapp_callへ引き継ぐ。`excel_preview_reset` でExcel風へ戻せる。

本番→テストの自動同期は廃止。ノート追加CLIは本番定義を取り込まない。差分移行はサポートタスクで明示された範囲だけ扱う。定義を含むリリースはテスト→本番であり、修正したテスト定義を反映する。専用取り込みコマンドの検証とHTTP／汎用app_callからの取込み拒否は維持する。

## bulk execution safety
- 作業用スクリプト・入力JSON・一時生成物は環境が指定する作業領域へ置く。Task間の共有範囲、実行記録の保護、環境変数の初期化は運用ルールに従う。作業場所のためだけにTaskを新規作成しない。
- 長い複合コマンドを `bash -lc '...'` に多重クォートして実行しない（特に `php -r` / ヒアドキュメント混在を禁止）。
- 一括処理は環境の作業領域にスクリプトを作り、`set -euo pipefail`付きで `bash <work-dir>/<script>.sh` を実行する。CLIの実行ディレクトリは対象の実行環境とし、スクリプトの保管先と区別する。
- JSON は各操作ごとに個別で組み立てる（`db_tables_add`, `db_fields_add`, `screen_fields_add`, `data_add` を混在させた巨大1発JSONを作らない）。
- 失敗時はその場で停止し、成功済みの確認コマンド（`*_list` / `data_list`）で再開位置を特定してから再実行する。

## purpose templates
- 初動確認:
  `php <app-root>/fbp/cli.php db_schema`
- 画面1枚の確認:
  `php <app-root>/fbp/cli.php app_call --json='{"class":"<class>","function":"<function>"}'`
- POST付き更新確認:
  `php <app-root>/fbp/cli.php app_call --json='{"class":"<class>","function":"<function>","post":{"id":1}}'`
  直後に `data_get` で対象レコードを確認する。
- 一覧反映確認:
  `php <app-root>/fbp/cli.php data_list --json='{"table":"<table>","max":100}'`
- 公開導線確認:
  `php <app-root>/fbp/cli.php app_check --json='{"class":"<class>","function":"<function>","get":{"key":"abc"}}'`
- screen_fields の確認:
  `php <app-root>/fbp/cli.php screen_fields_list --json='{"tb_name":"<tb_name>","screen_name":"<screen_name>"}'`
- 標準画面チェッカー:
  環境が提供する読取り専用の標準画面チェッカーを使う。
  詳細・完了判断は `fbp-standard-screen` を参照する。
- 生の CLI でしか表せない場合:
  `php <app-root>/fbp/cli.php <command> --json='{}'`

## required key checks
- `db_fields_add`: `db_id`, `parameter_name` は必須。
- `screen_fields_add`: `tb_name`, `screen_name`, `parameter_name` は必須。
- `db_tables_edit` / `db_fields_edit` / `screen_fields_edit`: `id` は必須。
- 一括投入前に、必須キー不足があるJSONを投入しない。

## known pitfalls
- `data_list` は `table` だけでなく `max` も要求される環境がある。`{"table":"x","max":100}` 形式で呼ぶ。
- API更新系は read-only 専用ではない。調査・承認前は参照系だけに限定する。
- `db_fields_list` の結果に `tb_name` が含まれない環境がある。`db_id` と `db_tables_list.id` を対応させてテーブル名を特定する。
- `db()->insert()` / `update()` は参照渡し実装のため、配列リテラルを直接渡さず変数に入れてから渡す。
- `app_call` の戻りには `request.post` / `request.get` / `console_log` が含まれる。送信値の不整合確認はまずここを見る。
- アプリ受信後のPOST全体を確認したい場合は、対象関数に一時的に `$ctl->console_log($ctl->POST());` を入れると CLI の `console_log` に出る。
- shell 直打ち時の `--json='...'` クォート崩れが多い場合は、JSONファイルを作って `--json_file` 相当の入力に寄せる。

## constraints
- OSローカルの正規 `cli.php` の `data_list` / `data_get` / `data_add` / `data_update` / `data_delete` は保守用操作としてアプリDSPを適用しない。HTTP、別PHP、`app_call` / `app_check` / `method_call` はこの例外に含めない。共通パスキー・確認コードなどframework固定の資格情報ポリシーは維持する。操作対象と更新・削除の許可は通常どおり確認する。
- 実行ディレクトリは対象環境ルールに従う（環境固有の運用ルールを参照）。
