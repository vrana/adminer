# PR作成コマンド

以下の手順でPull Request(PR)を作成してください。

## 1. ブランチ準備
- 現在のブランチがmainの場合: 新しいfeatureブランチを作成
- それ以外の場合: 現在のブランチをそのまま使用

## 2. 変更のコミット
- `git status`で変更ファイルを確認
- `git add`で全ての変更をステージング
- `git commit`で変更をコミット（適切な日本語メッセージ）

## 3. リモートプッシュ
- `git push -u origin <branch-name>`でリモートにプッシュ

## 4. PR作成
- `mcp__github__create_pull_request`を使用してPRを作成
- タイトル: 日本語で変更内容を要約
- body: Summary、Test planを含む定型フォーマット

## 命名規則
- ブランチ名: 英語（例: update-config, validation-error）
  - github flowに従う(feature/などのprefixは不要)
- コミットメッセージ: 日本語
- PRタイトル・説明: 日本語
