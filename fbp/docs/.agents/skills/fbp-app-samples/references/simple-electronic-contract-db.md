# 簡易電子契約のノート・メール

正本は`assets/simple-electronic-contract/simple-electronic-contract.json`。導入スクリプトは新規名だけをテスト環境へ登録する。業務契約・userレコード・SMTP設定を自動投入しない。

## Standard Screen：sample_esign_contracts

- メニュー「簡易電子契約」、一覧幅1000px・ダイアログ680px。標準の追加・編集・削除定義は置かず、上部「新規追加」と行「詳細」だけを接続する。
- `title`、`sender_name/email`、`recipient_name/email`、状態`status_code`、登録日時`created_at`、承認期限`expires_at`、締結日時`completed_at`を一覧へ表示する。
- 内部項目：`owner_user_id`、`status`、`updated_at`、`download_expires_at`、`request_key`、`original_pdf/hash`、`signed_pdf`、`evidence_zip`、`state_json`。
- `state_json`に当事者、コードのハッシュ・期限・試行数・認証世代、承認情報、通知状態、ハッシュ連鎖した履歴をまとめる。同じレコードの排他で更新する。
- ファイル項目は汎用のメディアIDではなく、アプリが生成するランダムな管理保存名を保持し、専用の取得関数で認証・権限・期限を確認する。

`sample_esign_status`は0=双方の承認待ち、1=一方が承認済み、2=完了ファイル作成待ち、3=締結完了、4=期限切れ。標準選択肢の色設定を使う。状態更新時と標準一覧の読取り時に派生値を同期し、期限切れ表示を古いまま残さない。

標準画面検査のadd/edit/delete/list_on_sideの空定義警告は意図したもの。独自の状態更新は標準編集を迂回する操作ではなく、`sample_esign`の認証・CSRF・業務確認を通す。

## user

既存のframework管理ユーザーを使い、アプリ独自の管理ユーザーノートは作らない。`get_login_user_id()`で特定した有効なレコードの`name`と`email`を使う。ログインIDやSMTP送信元を氏名・メールの代わりにしない。

## email_format

独立した3キー：`sample_esign_invite`、`sample_esign_code`、`sample_esign_complete`。本文はmanifestに持ち、テスト設定登録で初期投入する。導入済みの同名メールを上書きしない。

置換変数は`aes_title`・`aes_name`・`aes_url`・`aes_expires`・`aes_code`。メールはプレーンテキストで`nofilter`を指定し、URLの`&`をHTML実体へ変換しない。日時はValueFormatter、送信は`send_mail_prepared_format`を使う。

## 公開入口

`sample_esign_public/page`（署名URL）、`send_code`・`verify_code`・`approve`（POSTとフローCSRF）、`file`（用途別の専用URL検証）。管理クラス`sample_esign`のログインは外さない。メール本文のURL生成は`get_APP_URL()`を使い、導入先のドメインを埋め込まない。
