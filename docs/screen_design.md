# FarmPlan 画面設計書

| 項目 | 内容 |
|------|------|
| アプリ名 | FarmPlan（作付けプランナー） |
| バージョン | 1.0 |
| 開発者 | aya-k-o |
| 公開URL | https://ayakomochi.xsrv.jp/farmplan/ |
| GitHub | https://github.com/aya-k-o/farmplan |
| 開発期間 | 約1ヶ月（総制作時間：約50時間） |

## 概要

畑の区画をグリッドで可視化し、野菜の配置・連作障害チェック・来季のシミュレーションを支援するWebアプリです。農業経験から「作付け計画が経験と勘に依存している」という課題を感じ、データとロジックで解決することを目的に開発しました。

---

## 画面一覧

| No | 画面名 | URL | 概要 |
|----|--------|-----|------|
| 1 | ログイン | login.php | ログインID・パスワードで認証 |
| 2 | 新規登録 | register.php | ユーザーアカウントを新規作成 |
| 3 | ホーム | index.php | ダッシュボード・タスク管理 |
| 4 | 畑マップ一覧 | field.php | 管理している畑の一覧表示 |
| 5 | 畑グリッド | field.php?id=N | 区画グリッドと栽培状況表示 |
| 6 | シミュレーション | plan.php | 来季の作付け計画を立てる |
| 7 | 栽培記録 | history.php | 過去の栽培履歴を絞り込み確認 |
| 8 | 設定 | settings.php | プロフィール・野菜マスタ管理 |

---

## 1. ログイン画面（login.php）

| 項目 | 内容 |
|------|------|
| URL | login.php |
| アクセス条件 | 未ログイン状態。ログイン済みはindex.phpへリダイレクト |
| 遷移先 | 成功→index.php ／ 登録リンク→register.php |

### 入力項目

| 項目名 | 種別 | バリデーション | 備考 |
|--------|------|----------------|------|
| ログインID | テキスト | 必須 | emailカラムに対応 |
| パスワード | パスワード | 必須 | |

### セキュリティ

| 対策 | 内容 |
|------|------|
| パスワード検証 | `password_verify()`でハッシュ照合 |
| セッション固定攻撃対策 | ログイン後に`session_regenerate_id(true)`を実行 |
| ユーザー列挙攻撃対策 | ID不一致・PW不一致を同一メッセージで返す |

---

## 2. 新規登録画面（register.php）

| 項目 | 内容 |
|------|------|
| URL | register.php |
| アクセス条件 | なし（未ログインで閲覧可） |
| 遷移先 | 登録成功→login.php?registered=1 |

### 入力項目

| 項目名 | 種別 | バリデーション | 備考 |
|--------|------|----------------|------|
| ユーザー名 | テキスト | 必須 | nameカラムに保存 |
| ログインID | テキスト | 必須・重複不可 | emailカラム（UNIQUE制約） |
| パスワード | パスワード | 必須・8文字以上 | `password_hash()`でハッシュ化 |
| パスワード（確認） | パスワード | 必須・一致すること | |

### バリデーション順序

① 空欄チェック → ② 8文字以上 → ③ パスワード一致確認 → ④ DB重複チェック

エラーがあれば以降の処理をスキップ。不要なDBアクセスを防ぐ設計。

---

## 3. ホーム画面（index.php）

| 項目 | 内容 |
|------|------|
| URL | index.php |
| アクセス条件 | ログイン必須。未ログインはlogin.phpへリダイレクト |

### 表示コンテンツ

| コンテンツ | 取得条件 | 備考 |
|-----------|---------|------|
| 栽培中の区画数 | `status="growing" AND mode="actual"` | 統計カード |
| 計画済みの区画数 | `status="planned" AND mode="plan"` | 統計カード |
| 今季の収穫回数 | `YEAR(harvested_at)=YEAR(NOW())` | 統計カード |
| 未完了タスク一覧 | `done=0`、期限あり優先ソート | 期限切れは赤表示 |
| 昨年の今頃の完了タスク | ±14日の範囲でフィルタ | 農作業の参考情報 |

### タスク操作

| 操作 | 処理先 | 内容 |
|------|--------|------|
| タスク追加 | task_action.php (action=add) | タイトル必須・期限は任意 |
| タスク完了 | task_action.php (action=complete) | `done=1`、`done_at=NOW()`を記録 |

---

## 4. 畑マップ一覧（field.php）

| 項目 | 内容 |
|------|------|
| URL | field.php（?idパラメータなし） |
| アクセス条件 | ログイン必須 |
| 遷移先 | 畑クリック→field.php?id=N ／ 畑を追加→field_create.php |

### 表示・操作

| 要素 | 内容 |
|------|------|
| 畑カード | 畑名・グリッドサイズ・区画数を表示。クリックでグリッド画面へ |
| 削除ボタン | confirmダイアログ後、field_delete.phpへPOST。ON DELETE CASCADEで関連データ全削除 |
| 畑を追加 | field_create.phpへ遷移 |

---

## 5. 畑グリッド画面（field.php?id=N）

| 項目 | 内容 |
|------|------|
| URL | field.php?id={field_id} |
| アクセス条件 | ログイン必須。他ユーザーのfield_idはアクセス不可（所有権確認） |
| 遷移先 | 各操作後→field.php?id=N にリダイレクト |

### グリッド表示

| 項目 | 内容 |
|------|------|
| 取得SQL | plotsをLEFT JOINで取得。空き区画もNULLとして表示（INNER JOINでは消えてしまう） |
| 2次元変換 | DBの1次元配列を`$grid[row][col]`の2次元配列に変換しネストループで描画 |
| 色分け | 科（family）ごとにCSSクラスを付与。`functions.php`の`familyClass()`で変換 |
| 凡例 | ナス科・ウリ科・根菜・葉野菜・イモ類・マメ科・空きを色で表示 |

### モーダル（区画クリック時）

| 状態 | 表示内容 | 操作 |
|------|---------|------|
| 空き区画 | 野菜選択・株数・植え付け日・メモ | plot_action.php (action=plant) |
| 栽培中区画 | 野菜名・科・株数編集・植え付け日・メモ | 変更保存 / 収穫した / 失敗した / 取り消す |

### 連作チェック（AJAX）

栽培中区画のモーダルを開いた際、`fetch API`で`check_rotation`リクエストを送信。過去3年以内に同じ科を栽培していた場合、`{"warning": true}`を返し警告を表示。科名（日本語）は`encodeURIComponent()`でURLエンコードして送信。

### plot_action.phpの処理一覧

| action値 | メソッド | 処理内容 |
|----------|---------|---------|
| plant | POST | 植え付け記録をINSERT（mode=actual, status=growing） |
| update | POST | 株数・植え付け日・メモをUPDATE |
| harvest | POST | status=harvested, harvested_at=CURDATE()に更新。harvestsにもINSERT |
| fail | POST | status=failedに更新 |
| cancel | POST | plot_seasonsからDELETE（空き区画に戻す） |
| update_memo | POST | メモのみAJAXで更新。JSONレスポンスを返す |
| check_rotation | GET | 過去3年の同科栽培を確認。{warning: bool}を返す |

---

## 6. シミュレーション画面（plan.php）

| 項目 | 内容 |
|------|------|
| URL | plan.php |
| アクセス条件 | ログイン必須 |

来季の作付け計画を立てるための画面。`plot_seasons`テーブルの`mode`カラムを`"plan"`として保存することで、実績データ（actual）と同一テーブルで管理しながら完全に分離して運用できる。連作障害の警告も同様にAJAXで確認できる。

---

## 7. 栽培記録画面（history.php）

| 項目 | 内容 |
|------|------|
| URL | history.php |
| アクセス条件 | ログイン必須 |
| 遷移先 | 削除→history_delete.php（絞り込み条件を引き継ぎ） |

### 絞り込み条件

| 条件 | 種別 | 初期値 | 備考 |
|------|------|--------|------|
| 年 | セレクト | 今年 | 記録がある年のみ選択肢に表示 |
| ステータス | セレクト | すべて | planned / growing / harvested / failed |
| 科 | セレクト | すべて | ナス科・ウリ科・根菜・葉野菜・イモ類・マメ科 |

### 機能

| 機能 | 内容 |
|------|------|
| 一覧表示 | 野菜名・科・株数・畑名・区画位置・植え付け日・収穫日・メモ・ステータスを表形式で表示 |
| メモインライン編集 | フォーカスを外したタイミングでAJAX自動保存（plot_action.php/update_memo） |
| 一括削除 | チェックボックスで複数選択→確認ダイアログ後、history_delete.phpへPOST |

---

## 8. 設定画面（settings.php）

| 項目 | 内容 |
|------|------|
| URL | settings.php |
| アクセス条件 | ログイン必須 |

### 機能一覧

| 機能 | 内容 |
|------|------|
| プロフィール変更 | ユーザー名の変更 |
| パスワード変更 | 現在のパスワード確認後、新しいパスワードをハッシュ化して更新 |
| 野菜マスタ管理 | 独自の野菜を追加・削除。削除時は外部キー制約でエラーハンドリング |

> ログアウトは全ページ共通のヘッダー右端に配置。野菜マスタが増えても常にアクセスできる。

---

## セキュリティ対策まとめ

| 対策 | 実装内容 | 該当箇所 |
|------|---------|---------|
| パスワード暗号化 | `password_hash()` / `password_verify()` | register.php / login.php |
| SQLインジェクション対策 | PDOプリペアドステートメント（prepare + execute） | 全PHPファイル |
| XSS対策 | `htmlspecialchars()`による出力エスケープ / WAF（XSS対策ON） | 全PHPファイル / Xserver |
| セッション固定攻撃対策 | ログイン後に`session_regenerate_id(true)`を実行 | login.php |
| 認証ガード | 全ページでセッション確認、未ログインはリダイレクト | 全ページ先頭 |
| 所有権確認 | `WHERE id=? AND user_id=?`でDBレベルで制限 | field.php / plot_action.php |
| 機密情報管理 | `.env`で管理、`.gitignore`でGit除外 | db_connect.php |
| URLエンコード | `encodeURIComponent()`でAJAXパラメータを安全に送信 | field.php（JS部分） |
| WAF | XSS・SQL・ファイル・コマンド・PHP対策をON | Xserver WAF設定 |
| トランザクション | 畑作成時のfields+plots連続INSERTをbeginTransaction/commit/rollBackで管理 | field_create.php |

---

## 技術スタック・開発環境

| 項目 | 技術・ツール |
|------|------------|
| バックエンド | PHP 8.2 |
| データベース | MySQL 8.0 |
| フロントエンド | HTML / CSS / JavaScript（fetch API） |
| 開発環境 | Docker / Docker Compose |
| バージョン管理 | Git / GitHub |
| フォント | Shippori Mincho / Zen Kaku Gothic New（Google Fonts） |
| 開発期間 | 約1ヶ月（総制作時間：約50時間） |
