

## Make Samples

### 画像の赤字編集

```text
再利用可能な FBP Codex Booster Make Sample「image-annotation」を作成・更新してください。
参照: 画像の赤字編集。fbp/docs/.agents/skills/fbp-app-samples/references/image-annotation.mdを入口にする。
画像左の縦アイコン、同梱SVG／CSS、矢印・赤枠・文字、画像上での文字編集、移動、
選択中だけの確認なし削除、undo、確認なしクローズ、PC／狭幅表示を一組で維持する。
元画像Blob・赤字付きPNG Blob・注釈JSONを取得してcallbackへ渡す入口までに限定し、
案件・3D・AI・保存先・名称・DB等のアプリ固有処理と実行時依存は除く。
assets、manifest、導入reference、既存クラス用installer、Skill導線とこのプロンプトを整合させる。
仮配置・上書き拒否、JSON／PHP／JS、実FBPで編集→結果取得／multipart受信、
元画像保持・JSON再読込・PNG・複数インスタンス・失敗時保持・狭幅を確認する。
本番配布や外部AI送信は行わない。
```

### 公開メール確認・カード変更

```text
再利用可能な FBP Codex Booster Make Sample を作成・更新してください。
Sample name: email-card-self-service
参照: メール・カード変更の公開セルフサービス。既存サンプルの
fbp/docs/.agents/skills/fbp-app-samples/references/email-card-self-service.md を入口にする。
維持: 管理URL、登録済みメールの本人確認、未登録時の初回確認、新メール確認、
重複送信時のコード保持、Squareカード登録／差替え、中央640pxのシンプルなボックス。
初回満額決済は選択式とし、指定時だけ導入先の永続予約・冪等性へ接続する。
除外: 顧客固有のプラン・紹介者・案件、認証情報、実データ、運用パス、特定アプリへの実行時依存。
assets/email-card-self-service、manifest、フローとDBのreferences、インストーラー、
fbp-app-samplesと関連Skillの導線、このMake Samplesプロンプトを整合させる。
PHP lint、JSON検証、仮配置と既存ファイル保護、隔離FFM・模擬メール／Squareの
成功・拒否・重複送信を検証し、導入先では別HTTP要求とPC／狭幅の表示も確認する。
実メール・実決済・本番リリースを自動で行わない。
```
