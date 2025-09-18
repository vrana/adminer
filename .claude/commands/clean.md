# ブランチクリーンアップコマンド

リポジトリでmainにmerge済みかつmainより進んだcommitのないbranchをローカル/リモートとも削除してください。

## 実行内容
1. `git branch --merged main`でマージ済みブランチを確認
2. mainブランチ以外のマージ済みブランチを特定
3. ローカルブランチを削除: `git branch -d <branch-name>`
4. リモートブランチを削除: `git push origin --delete <branch-name>`

## 注意事項
- mainブランチは削除対象から除外
- 現在のブランチは削除対象から除外
- 削除前に確認を行う