---
name: fbp-webhook
description: Implement and operate webhook-driven integrations in FBP, including rule registration, LINE reply/push URL formatting, and end-to-end verification.
---

# fbp-webhook

## trigger conditions
- 外部イベント受信（Webhook）を実装する
- `webhook_rule` の登録/更新が必要
- LINE連携などWebhook起点処理を扱う

## workflow
1. 受信要件と署名要件を整理。
2. 受信クラス/処理を実装。
3. `webhook_rule` を登録。
4. CLIまたは検証リクエストで受信から処理完了まで確認。

## line patterns
- LINE Bot の `keyword -> action_class` 型を作る場合は `references/line_bot_member_link.md` を読む。
- LINE user_id と会員DBを連結する場合、まず `webhook_line` 標準の `getting_member` 解決を前提にする。
- `getting_member` は LINE の生イベントではなく、`webhook_line` が前処理として呼ぶ内部解決処理。
- 既存アプリ互換のため、`[getting_member]` / `match_type=data_type` の `webhook_rule` がある場合はそれを優先できる。
- 会員連携済みのキーワード処理は、`line_webhook_context.line_member` を前提に `public_pages` URL を返す形を基本とする。
- `match_type=unmatch` は LINE `message/text` 専用のフォールバックとして扱う。
- `unmatch` は通常の `exact / contains / regex` ルールを全件評価したあと、1件も一致しなかった場合だけ実行される。
- 同一 channel では `unmatch` は 1件だけ登録する前提で扱う。
- Wizard 運用では、会員連携の標準構成を `line_member / userid / line_name / name` とする。
- `LINE用会員データベース作製` は固定テンプレートとして扱い、上記のテーブル名・フィールド名で実装する。新規案件では `getting_member` 用 `webhook_rule` / `action_class` は通常不要。
- `Line Bot処理追加` / `Line Bot処理変更` で会員連携を前提にする場合も、特別な指示がない限り `line_member / userid / line_name / name` を標準前提として扱う。
- `webhook_rule` のクラスと公開画面のクラスは分離を基本とし、同一クラスにまとめない。
- 代わりに、`webhook_rule class` と `public_pages function` を対応ペアとして設計・命名する。
- 例: `line_webhook_rule_event -> public_pages::event_list`, `line_webhook_rule_member_search -> public_pages::member_search`
- 問い合わせや申込フォームのように入力・保存がある導線は、`webhook_rule action_class` 側でフォームを持たず、`public_pages` 側で実装する。
- `webhook_rule action_class` 側は、原則として `public_pages` のURLを返す役割にとどめる。
- Flex Messageを返信する action_class は、`["reply_flex" => ["alt_text" => "...", "contents" => <bubble|carousel>], "handled" => true]` を返す。公開URLはFlex Message内のURI actionへ設定する。

## public_pages への引継ぎ
- LINE webhook から問い合わせや申込ページへ誘導する場合は、`webhook_rule class -> public_pages URL返信 -> public_pages でフォーム表示/保存/完了表示` の3段構成を基本にする。
- action_class は `line_webhook_context` の会員情報から入口用の暗号化識別子を作り、公開URLを返す。フォーム表示・入力内容の保存は `public_pages` 側で行う。
- 公開画面のUI・ダイアログ・通常リンク・会員文脈の引継ぎは [fbp-public-pages](../fbp-public-pages/SKILL.md) を正本とする。公開画面を実装・変更するときに参照する。
- LINEへ送るURLの生成・URI action／プレーンテキストの選択・受信パラメータの検証は [LINE message URLs](../fbp-public-pages/SKILL.md#line-message-urls) に従う。
- 会員文脈は [public member context](../fbp-public-pages/SKILL.md#public-member-context)、実装例は [LINE起点の公開フォーム](../fbp-public-pages/references/line_webhook_public_form_sample.md) を参照する。

## unmatch action_class rules
- `unmatch` の action_class では `$ctl->get_session("line_webhook_context")` から入力本文を取得する。
- 取得キーは `text` を使う。`message/text` の未一致本文がそのまま入る。
- `line_webhook_context` には通常 `event`, `text`, `userid`, `displayname`, `line_member`, `rule`, `data_type` が入る前提で扱う。
- `unmatch` action_class は `run(Controller $ctl)` を実装する。
- 文字列を返すと、その文字列をLINEへ返信して処理終了になる。
- `["reply_text" => "..."]` を返しても返信できる。
- `["handled" => true]` を返すと処理済み扱いになり、後続の管理者転送へ進まない。
- `["handled" => false]` を返すと、`unmatch` action_class 実行後も処理継続となり、設定次第で管理者転送へ進む。
- `null` を返すと現状の `webhook_line` ではその場で処理終了寄りになるため、管理者転送と共存したい場合は使わず `["handled" => false]` を返す。
- `unmatch` と管理者転送を共存させたい場合は、「DB保存や通知文整形だけ action_class で行い、戻り値は `["handled" => false]`」を基本にする。
- `unmatch` で返信だけして終了したい場合は、文字列または `["reply_text" => "...", "handled" => true]` を返す。

### sample code
```php
<?php

class line_webhook_rule_unmatch_sample {

	function run(Controller $ctl) {
		$context = $ctl->get_session("line_webhook_context");
		$text = trim((string) ($context["text"] ?? ""));
		$line_member = is_array($context["line_member"] ?? null) ? $context["line_member"] : [];
		$userid = trim((string) ($context["userid"] ?? ""));

		if ($text === "") {
			return [
				"handled" => false,
			];
		}

		// 例: ログ保存やDB記録をここで行う
		$ctl->log("[line unmatch] userid=" . $userid . " text=" . $text);

		// 1. 返信して終了したい場合
		// return "お問い合わせありがとうございます。担当者が確認します。";

		// 2. 返信しつつ終了したい場合
		// return [
		// 	"reply_text" => "内容を受け付けました。",
		// 	"handled" => true,
		// ];

		// 3. 管理者転送と共存したい場合
		return [
			"handled" => false,
		];
	}
}
```

## constraints
- 機密情報（シークレット、トークン）をコード直書きしない。
- LINE 連携では `webhook_rule.channel=0` を使う。
- `webhook_rule` は `channel + keyword` 重複を避ける。
- `match_type=unmatch` は同一 channel に複数登録しない。
- 問い合わせ・申込フォームの保存処理は `public_pages` 側に置き、`webhook_rule action_class` にフォーム処理を混在させない。
- `getting_member` を実装する場合は、会員検索キー・表示名保存先・未登録時の新規作成方針を明示する。
