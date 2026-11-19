# line_webhook_public_form_sample

LINE webhook から公開フォームへ誘導し、公開側で常に `line_member` を引けるようにする基本形。

## intent
- webhook の action_class は公開URLを返すだけにする
- `userid` は暗号化して公開URLへ渡す
- `public_pages` の初回入口で `userid` を復号してsessionへ保存し、各関数ではsessionから `line_member` を取得する
- `show_public_pages()` の `contents_header` / `contents_footer` 差し込みも併用できる形にする
- 完了画面も `show_public_pages()` にそろえる

## action_class sample

```php
<?php

class line_webhook_rule_inquiry_entry {

	function run(Controller $ctl) {
		$context = $ctl->get_session("line_webhook_context");
		$userid = trim((string) ($context["userid"] ?? ""));
		if ($userid === "") {
			return "ユーザー情報を取得できませんでした。";
		}

		return $ctl->get_APP_URL("public_pages", "line_inquiry", [
			"userid" => $ctl->encrypt($userid),
		]);
	}
}
```

## public_pages sample

```php
<?php

class public_pages {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function line_inquiry(Controller $ctl) {
		$userid_enc = trim((string) $ctl->GET("userid"));
		if ($userid_enc !== "") {
			$ctl->set_session("line_inquiry_userid", trim((string) $ctl->decrypt($userid_enc)));
		}
		$line_member = $this->require_line_member($ctl);
		if ($line_member === null) {
			return;
		}
		$ctl->assign("line_member", $line_member);
		$ctl->assign("row", [
			"name" => trim((string) ($line_member["name"] ?? "")),
			"message" => "",
		]);
		$ctl->assign("page_title", "LINE お問い合わせ");
		$ctl->show_public_pages(
			"line_inquiry.tpl",
			null,
			"line_inquiry_header.tpl",
			"line_inquiry_footer.tpl"
		);
	}

	function line_inquiry_exe(Controller $ctl) {
		$line_member = $this->require_line_member($ctl);
		if ($line_member === null) {
			return;
		}
		$post = $ctl->POST();
		$row = [
			"name" => trim((string) ($post["name"] ?? "")),
			"message" => trim((string) ($post["message"] ?? "")),
		];

		$has_error = false;
		if ($row["name"] === "") {
			$ctl->res_error_message("name", "名前を入力してください。");
			$has_error = true;
		}
		if ($row["message"] === "") {
			$ctl->res_error_message("message", "内容を入力してください。");
			$has_error = true;
		}
		if ($has_error) {
			$ctl->res_error_message("form", "エラーがあります。修正してください。");
			return;
		}

		$ctl->db("contact_inquiry")->insert([
			"userid" => trim((string) ($line_member["userid"] ?? "")),
			"name" => $row["name"],
			"message" => $row["message"],
		]);

		$ctl->assign("line_member", $line_member);
		$ctl->assign("saved", $row);
		$ctl->assign("line_inquiry_url", $ctl->get_APP_URL("public_pages", "line_inquiry"));
		$ctl->assign("page_title", "送信ありがとうございました");
		$ctl->show_public_pages(
			"line_inquiry_done.tpl",
			null,
			"line_inquiry_header.tpl",
			"line_inquiry_footer.tpl"
		);
	}

	private function require_line_member(Controller $ctl): ?array {
		$userid = trim((string) ($ctl->get_session("line_inquiry_userid") ?? ""));
		if ($userid === "") {
			$ctl->assign("page_title", "LINE会員情報が見つかりません");
			$ctl->show_public_pages("line_inquiry_error.tpl");
			return null;
		}

		$list = $ctl->db("line_member")->select("userid", $userid);
		if (!is_array($list) || count($list) === 0) {
			$ctl->assign("page_title", "LINE会員情報が見つかりません");
			$ctl->show_public_pages("line_inquiry_error.tpl");
			return null;
		}
		return (array) $list[0];
	}
}
```

## template sample

```smarty
<form id="line_inquiry_form" onsubmit="return false;">
	<input type="text" name="name" value="{$row.name|escape}">
	<p class="error_message error_name"></p>

	<textarea name="message">{$row.message|escape}</textarea>
	<p class="error_message error_message"></p>

	<p class="error_message error_form"></p>
	<button class="ajax-link" data-class="{$class}" data-function="line_inquiry_exe" data-form="line_inquiry_form">送信する</button>
</form>
```

## notes
- 公開画面のUI・通常リンク・ダイアログ・会員文脈の正本は [fbp-public-pages](../SKILL.md)。新規UIには同Skillのminimalモード・標準フォーム基本形を適用する。この例のhead/footer差し込みは既存互換の構成例。
- `userid` は初回URLだけ暗号化して渡し、復号後はsessionに保持する。hiddenや内部URLで再送しない。
- 各関数で `require_line_member()` を呼び、sessionの `userid` から会員を再取得する。実際の閲覧・保存に必要な権限も確認する。
- `getone()` ではなく `select("userid", $userid)` の既存パターンに合わせる。
- 戻りURLは `get_APP_URL("public_pages", "line_inquiry")` とし、パラメータを付けない。
