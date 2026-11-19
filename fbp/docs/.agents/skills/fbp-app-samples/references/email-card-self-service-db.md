# 公開メール・カード設定のDB接続

manifestは`assets/email-card-self-service/email-card-self-service.json`。サンプルには以下を含む。

- Standard Screenノート`sample_email_card_member`：氏名、メール（空欄可）、請求内容、月額料金、状態（1=有効、0=停止）、Square customer/card参照、初回カード登録日時。管理者共有の一覧と行ボタン。生カード番号・CVVは保存しない。
- 固定形式`sample_email_card/rate`：メールアドレスとIPのハッシュ別の送信回数。初期値はメール5回／時・60秒間隔、IP10回／時。業務ノートへ昇格させない。
- メール定型文`sample_email_card_code`：`confirmation_code`を使う4桁確認コード。SMTPは導入先の既存設定。テストは依存注入で捕捉し、実送信を既定で拒否する。
- 公開入口は独立クラス`sample_email_card_public::page`。有効なtokenが必須なので、固定の一般向けメニューURLにはしない。URLは管理一覧の「メール・カード変更URL」で案内する。

`public_pages`への転送入口を設ける環境では、そちらの関数を公開ページ台帳へ`link_requires_params=1`で登録する。サンプルの独立クラスを、引数なしの`public_pages`関数として台帳へ誤登録しない。

既存ノートへ適応するときは`Profile.php`の対象とバージョン項目、`Verification::prepare`の依存DB、管理URLクラス・visibility filter・定型文・manifestの画面配置を揃える。組織や所有者の権限をサンプル名から推測しない。サンプル既定はアプリ管理者間の全件共有であり、独立会員ログインや代理権限の実装ではない。

初回課金の取引台帳・予約・照合・月次処理は導入先の責任範囲。既存の課金DBへPaymentアダプターで接続する。単にcallback内で` square_payment()`を呼んで初回課金済みフラグを後付けする方法では、途中停止や並行処理の二重請求を防げない。

インストーラーはコード配置と開発定義登録だけを行い、会員データ、送信、カード登録、決済、cron設定、本番リリースは実行しない。
