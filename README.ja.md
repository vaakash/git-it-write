# Git it Write

![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-21759B?style=flat-square&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![Markdown](https://img.shields.io/badge/Markdown-000000?style=flat-square&logo=markdown&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-Integration-181717?style=flat-square&logo=github&logoColor=white)
![License](https://img.shields.io/badge/License-GPL%20v2-blue?style=flat-square)

[English](readme.md) | 日本語

_Git it Write_ は、GitHubリポジトリに存在するMarkdownファイルをWordPressサイトに公開できるWordPressプラグインです。このプラグインを使用すると、リポジトリ内のファイルが追加または更新されるたびに、WordPressの投稿が自動的に追加または更新されます。

このプラグインは、`Jekyll`、`Next.js`、`Gatsby.js`などの静的サイトジェネレーターでMarkdownを使用してコンテンツを作成する方法にインスパイアされています。GitHubからMarkdownデータを解析してWordPressの投稿として公開するという、WordPress向けの同様のアイデアです。

👓 **実例:** [ソースGitHubリポジトリ](https://github.com/vaakash/aakash-web)（`/docs/`フォルダ）から[公開された投稿](https://www.aakashweb.com/docs/)

📦 **ダウンロード/インストール:** WordPressプラグインリポジトリの[Git it write](https://wordpress.org/plugins/git-it-write/)

⚡ **はじめに:** _Git it Write_の[スタートガイド](https://www.aakashweb.com/docs/git-it-write/getting-started/)。`.md`ファイルの書き方とWordPressへの投稿方法を学びます。

これにより、GitHubで投稿に対してコラボレーション、編集、提案の共有が可能になり、プルされると自動的にWordPressの投稿が更新されます。

### 🖥️ 例

リポジトリに以下の構造でファイルがある場合、以下の投稿が作成されます（パーマリンクが設定されており、投稿タイプが「階層」をサポートしている場合、つまり、以下の例の `/pages` のようにレベルごとに投稿を作成できる場合）。

**GitHubリポジトリのファイル構造:**

    docs/
        guide/
            introduction.md
            getting-started.md
    help/
        faq.md

**生成されるWebサイト:**

    https://example.com/docs/guide/introduction/
    https://example.com/docs/guide/getting-started/
    https://example.com/help/faq/

### 🎲 このプラグインの用途は？

* GitHubリポジトリ内のファイルを使用して投稿を公開します。
* Markdown形式で投稿を作成します。
* デスクトップアプリケーション（Notepad++、Sublime Text、Visual Studio Code）で投稿を作成します。
* GitHub上のファイルでコラボレーションし、コミュニティを巻き込み、WordPressで公開します。
* Gitとそのバージョン管理システムのすべての利点を活用します。

### 🚀 いくつかのユースケース

* ドキュメント投稿、FAQ、Wikiなどに使用できます。
* ブログ投稿を書きます。
* コミュニティの関与が必要な記事。

### ✨ 機能

* Markdownが処理され、投稿がHTMLとして公開されます。
* ソースファイルで使用されている画像がWordPressにアップロードされます。
* 相対リンクがサポートされています。
* 投稿のステータス、タイトル、順序、カテゴリ、タグなどの投稿プロパティをソースファイル自体で設定できます。
* Webhookサポート（リポジトリが変更されるたびに、最新の変更をプルして投稿を公開するようにプラグインが更新されます）
* 複数のリポジトリを追加できます。
* 任意の投稿タイプに公開できます。
* フォルダー内にあるファイルは階層的に投稿されます。例：ファイル`dir1/hello.md`は、投稿タイプが階層をサポートしている場合、WordPressで`dir1/hello/`として投稿されます。
* タグ、カテゴリ、カスタムフィールドの設定などの投稿メタデータのサポート。

### ℹ 注意事項

* 現時点では、Markdownファイルのみがプルされて公開されます
* GitHub上でソースファイルが削除されても、投稿は削除されません。
* パーマリンク構造を持つことが推奨されます。
* 階層をサポートする投稿タイプを選択することが推奨されます。
* 画像はリポジトリルートの`_images`フォルダーにのみ存在する必要があります。Markdownファイルは、ファイル内で相対的に使用する必要があります。

### 🥗 推奨事項

WordPressサイトでパーマリンク構造を有効にすることをお勧めします。そうすると、`docs/reference/my-post.md`の下にファイルがある場合、`https://example.com/docs/reference/my-post/`のように投稿が公開されます。これは、投稿タイプが階層サポートを持っている場合の結果です。リポジトリ内のすべてのフォルダーについて、レベルごとに投稿されます。フォルダーの投稿は、そのフォルダーの下に存在する場合は`index.md`ファイルから取得されます。

### 🏃‍♂️ プラグインの使用方法

1. すべてのソースファイル（Markdownファイル）が管理されているGitHubリポジトリを用意します（必要に応じてフォルダーに整理された正確な構造）
2. プラグインの設定ページで、新しいリポジトリを追加をクリックします。
3. 投稿をプルするリポジトリの詳細と、どの投稿タイプに公開するかを入力します。
4. 設定を保存します
5. 「投稿をプル」をクリックし、次に「プルのみ」の変更をクリックします。これにより、すべてのMarkdownファイルの投稿が公開されます。
6. リポジトリが更新されるたびに投稿を自動的に更新するには、設定ページに記載されているようにWebhookを設定します。

## リンク

* [ドキュメント](https://www.aakashweb.com/docs/git-it-write/)
* [サポートフォーラム/バグ報告](https://www.aakashweb.com/forum/)
* [寄付](https://www.paypal.me/vaakash/)

## インストール

1. 圧縮ファイルを解凍し、`git-it-write`フォルダーを`/wp-content/plugins/`ディレクトリにアップロードします。
2. WordPressの`プラグイン`メニューからプラグインを有効化します。
3. 設定メニューの下の「Git it Write」リンクから管理ページを開きます。

## よくある質問

FAQの完全なリストについては、[プラグインドキュメントページ](https://www.aakashweb.com/docs/git-it-write/faq/)をご覧ください。

### WordPressで投稿を編集すると、GitHubリポジトリのファイルが更新されますか？

いいえ。このプラグインは投稿コンテンツを同期しません。これは一方向の更新です。GitHubリポジトリに加えられた変更のみが投稿を更新し、その逆はありません。

### リポジトリ内のどのファイルが公開されますか？

すべてのMarkdownファイルが投稿として公開されます。

### 公開されないものは何ですか？

`_`（アンダースコア）および`.`（ドット）で始まるフォルダー/ファイルは、公開の対象とはみなされません。

### リポジトリの特定のブランチから投稿をプルできますか？

はい、リポジトリのブランチから投稿をプルする場合は、プラグインのリポジトリ設定ページで指定できます。

### リポジトリの特定のフォルダーから投稿をプルできますか？

はい、リポジトリのフォルダーから投稿をプルする場合は、プラグインのリポジトリ設定ページで指定できます。たとえば、リポジトリに`website/main/docs`というフォルダーがあり、docsフォルダーからのみプルする場合は、プラグイン設定で`website/main/docs`を指定できます。
