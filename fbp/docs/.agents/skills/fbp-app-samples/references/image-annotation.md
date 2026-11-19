# 画像の赤字編集（image-annotation）

PNG・JPEG・WebP画像へ、矢印・赤枠・文字を重ねる再利用テンプレート。画像左の縦アイコン、青い選択枠、画像上での文字入力／再編集、全種類のドラッグ移動、選択中だけの確認なし削除、100操作までの取消、狭幅表示を一組で同梱する。クローズは確認なし。画像本体と注釈を分離し、編集結果を取得するところまでが共通部品の責任範囲。

AI送信・3D・案件・保存先・名称・DB・公開ルート・業務処理は含まない。元画像取得の認証・閲覧権限、保存後にどう使うかは導入先で実装する。バナーや写真そのものをAI編集する制作ツールとは別。

## 配置と見た目

```sh
php docs/.agents/skills/fbp-app-samples/scripts/install_image_annotation.php <app-source-root> <existing-class>
```

既存クラスへ3つのtpl・アイコンライセンス・PHP検証ヘルパーを配置する。同名ファイル・symlinkは上書きしない。`image_annotation_editor.tpl`をincludeすれば、スコープ付きCSSとJSも一緒に読み込まれる。ツールアイコンはMaterial Symbols OutlinedのSVGを埋込み済みで、Google Fonts・CDN・外部画像を取得しない。ライセンスも一緒に配布する。

管理側のダイアログでは、認可済みの入口から通常の`show_multi_dialog('image-annotation', 'my_annotation_dialog.tpl', '画像の赤字編集', 1050)`で表示する。標準のクローズUIを使う。独立した画面へ埋込む場合も同じtplを使え、複数の編集領域は別インスタンスとして動く。

## 最小の接続

呼出し先tplで部品をincludeした後、描画されたrootへmountする。rootのIDを固定せず、ホスト領域の中から取得する。同じ画面に複数置くときはホストを分ける。

```smarty
<div id="my-annotation-host">
{include file="image_annotation_editor.tpl"}
</div>
```

```js
// imageBlobは、利用者が選んだFile、canvas.toBlob()、または認可済み画像のfetch().blob()。
const root = document.querySelector('#my-annotation-host .fbp-image-annotation');
const editor = await FBPImageAnnotation.mount(root, {
  image: imageBlob,
  annotations: savedDocument || [],
  onSubmit: async result => {
    // ここから先は各アプリの責任。自動保存・自動AI送信は行わない。
    await handleEditedImage(result);
  }
});
```

- `mount()`は画像decodeと注釈検証後に編集APIを返すPromise。画像は12MB・1600万画素以内。SVG・動画・PDFは渡さず、PDFページや3Dの撮影画像は導入先で画像Blobへ変換する。EXIF回転を持つ写真は導入先で表示方向を正規化し、サーバー側でも同じ向き・寸法の画像を使う。
- `annotations`は下記document、または既存の注釈配列。documentの場合は画像サイズも照合する。同じサイズの別画像の取り違えを防ぐID・版照合はアプリ側で行う。
- `onSubmit`は「編集結果を取得」クリック時に呼ぶ。Promise失敗時は画面と赤字を保持してエラーを表示し、再試行できる。クリック中は二重実行を抑止する。必要ならアプリ側でラベルを「保存」などに変更する。
- `onSubmit`を省略するとrootから`image-annotation:submit`イベントが発火し、`event.detail`に同じresultが入る。イベントに永続化の成功待ちはないため、失敗表示が必要な保存ではcallbackを使う。
- 別ボタンからは`await editor.getResult()`で同じ結果を取得できる。処理中の手動連打や複数の保存はアプリ側でも制御する。
- ページを差し替える前に`editor.destroy()`でlistener・Observer・Blob URLを解放する。標準ダイアログのクローズでは自動で破棄する。元Blobのバイト列は変更しない。

## 戻り値・JSON

`result`は次の4項目。BlobをJSON.stringifyしない。

| 項目 | 内容 |
| --- | --- |
| `originalImage` | mount時に渡した元画像Blob（同じバイト列） |
| `annotatedImage` | 元解像度の赤字合成PNG Blob。ツールバー・入力枠・青い選択枠は含めない |
| `document` | 下記のJSONオブジェクト |
| `json` | documentのJSON文字列 |

```json
{
  "schema_version": 1,
  "coordinate_space": "normalized-1000",
  "image": {"width": 960, "height": 600},
  "annotations": [
    {"type": "arrow", "x": 200, "y": 250, "x2": 500, "y2": 500, "text": ""},
    {"type": "text", "x": 600, "y": 250, "x2": 600, "y2": 250, "text": "ここを変更"}
  ]
}
```

座標はX／Yをそれぞれ画像の幅／高さで0〜1000に正規化した数値。表示幅を変えても同じ位置になる。文字のx/yはベースライン位置。typeはarrow/rect/text、最大60個・文字は1〜80文字。文字はHTMLとして扱わない。

## サーバーへ渡す入口

```js
const form = FBPImageAnnotation.toFormData(result);
// multipart: original_image、annotated_image、annotations_json
// URLはController::get_APP_URL()で生成して画面へ渡す。
form.append('record_id', authorizedRecordId);
form.append('version', expectedVersion);
form.append('csrf', existingCsrfToken);
window.FBPManagementCsrf?.addToFormData(receiveUrl, form);
const response = await fetch(receiveUrl, {method:'POST', body:form, credentials:'same-origin'});
if (!response.ok) throw Error('受信できませんでした。');
```

このサンプルは受信URLを作成しない。導入先で認証・所有者・CSRF・版競合・二重送信を検証し、アップロードのエラー・実ファイルのMIME／サイズ／画素数を独立して検査する。`require_once __DIR__.'/ImageAnnotationData.php';`で同梱ヘルパーを読み込み、`ImageAnnotationData::validate($json, $width, $height)`へサーバーで解読した**元画像**の寸法を渡すと、不要なキーを除いたdocumentを取得できる。クライアント由来の合成PNGがJSONと一致する保証はない。証跡等で一致が必要なら、導入先で元画像と検証済みJSONから再合成する。

永続化する場合は[fbp-media](../../fbp-media/SKILL.md)に従い、元画像・合成画像をDBのfile/image項目、JSONを別項目へ保存し、取得時の認可も維持する。表示用URL・保存名・ユーザーIDをクライアントから信頼しない。元画像が権限付きの場合は通常の認証済み取得を使い、CORS迂回や公開コピーを作らない。

## 検証

インストーラーを空の仮クラスへ実行し、2回目の拒否と既存ファイルの保持を確認する。JSONとPHP lintも確認する。実FBPの標準ダイアログで、元画像→赤字編集→callback／multipart受信までを確認する。画像のバイト列と寸法、JSON再読込、移動・削除・undo、選択枠なしのPNG、callback失敗時の保持、複数インスタンス、クローズ破棄、狭幅の縦アイコンを確認する。DB・AI・実メール・本番リリースはこのサンプルの検証に含めない。

検証済み：仮配置・上書き／symlink拒否、JSON検証15項目、実FBPでの赤字操作とmultipart受信、元画像SHA-256の一致、赤字PNGの寸法と画素・選択枠の除外、JSON再読込、2インスタンスの独立、callbackエラー時保持、イベントでの結果取得、JPEG／WebP、版・寸法不一致とSVG入力の拒否、再mount時のエラー解除、標準クローズ時の解放、PC／390px幅とSVGアイコン表示。外部AI呼出し・永続保存・本番配置は行っていない。
