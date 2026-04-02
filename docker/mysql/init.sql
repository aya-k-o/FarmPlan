-- FarmPlan データベース初期化
-- MySQLコンテナ初回起動時に自動実行される

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS farmplan
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE farmplan;

-- ユーザー管理
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)     NOT NULL,
  email         VARCHAR(255)     NOT NULL UNIQUE,
  password_hash VARCHAR(255)     NOT NULL,
  created_at    DATETIME         DEFAULT CURRENT_TIMESTAMP
);

-- 畑の管理
CREATE TABLE IF NOT EXISTS fields (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED  NOT NULL,
  name       VARCHAR(100)  NOT NULL,
  grid_rows  TINYINT       UNSIGNED NOT NULL DEFAULT 6,
  grid_cols  TINYINT       UNSIGNED NOT NULL DEFAULT 8,
  created_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 区画（1m²単位）
CREATE TABLE IF NOT EXISTS plots (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  field_id   INT UNSIGNED  NOT NULL,
  row_num    TINYINT       UNSIGNED NOT NULL,
  col_num    TINYINT       UNSIGNED NOT NULL,
  created_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE,
  UNIQUE KEY uq_plot (field_id, row_num, col_num)
);

-- 野菜マスタ
CREATE TABLE IF NOT EXISTS vegetables (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100)  NOT NULL,
  variety    VARCHAR(100),
  family     VARCHAR(100)  NOT NULL COMMENT '科（例：ナス科、ウリ科）',
  icon       VARCHAR(10),
  created_at DATETIME      DEFAULT CURRENT_TIMESTAMP
);

-- 栽培履歴（連作チェックの核心テーブル）
CREATE TABLE IF NOT EXISTS plot_seasons (
  id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  plot_id      INT UNSIGNED  NOT NULL,
  vegetable_id INT UNSIGNED  NOT NULL,
  year         YEAR          NOT NULL,
  mode         ENUM('plan','actual')                              NOT NULL DEFAULT 'actual',
  status       ENUM('planned','growing','harvested','failed')    NOT NULL DEFAULT 'planned',
  planted_at   DATE,
  harvested_at DATE,
  memo         TEXT,
  created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plot_id)      REFERENCES plots(id)      ON DELETE CASCADE,
  FOREIGN KEY (vegetable_id) REFERENCES vegetables(id)
);

-- コンパニオンプランツルール
CREATE TABLE IF NOT EXISTS companion_rules (
  id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  vegetable_id INT UNSIGNED  NOT NULL,
  target_id    INT UNSIGNED  NOT NULL,
  type         ENUM('good','bad') NOT NULL,
  FOREIGN KEY (vegetable_id) REFERENCES vegetables(id),
  FOREIGN KEY (target_id)    REFERENCES vegetables(id)
);

-- 収穫記録
CREATE TABLE IF NOT EXISTS harvests (
  id             INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  plot_season_id INT UNSIGNED   NOT NULL,
  harvested_at   DATE           NOT NULL,
  weight         DECIMAL(6,2)   COMMENT 'kg単位（任意入力）',
  memo           TEXT,
  created_at     DATETIME       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plot_season_id) REFERENCES plot_seasons(id) ON DELETE CASCADE
);

-- =============================================
-- サンプルデータ（野菜マスタ）
-- =============================================
INSERT INTO vegetables (name, variety, family) VALUES
  ('トマト',       NULL, 'ナス科'),
  ('ナス',         NULL, 'ナス科'),
  ('ピーマン',     NULL, 'ナス科'),
  ('キュウリ',     NULL, 'ウリ科'),
  ('ズッキーニ',   NULL, 'ウリ科'),
  ('ニンジン',     NULL, '根菜'),
  ('タマネギ',     NULL, '根菜'),
  ('レタス',       NULL, '葉野菜'),
  ('シソ',         NULL, '葉野菜'),
  ('ブロッコリー', NULL, '葉野菜'),
  ('ジャガイモ',   NULL, 'イモ類'),
  ('サツマイモ',   NULL, 'イモ類'),
  ('トウモロコシ', NULL, 'マメ科'),
  ('エダマメ',     NULL, 'マメ科');

-- コンパニオンプランツルール（サンプル）
INSERT INTO companion_rules (vegetable_id, target_id, type) VALUES
  (1,  8,  'good'),  -- トマト × レタス    ：相性良
  (1,  9,  'good'),  -- トマト × シソ      ：相性良
  (4,  13, 'good'),  -- キュウリ × トウモロコシ：相性良
  (11, 14, 'good'),  -- ジャガイモ × エダマメ：相性良
  (1,  2,  'bad'),   -- トマト × ナス      ：同科NG
  (11, 1,  'bad');   -- ジャガイモ × トマト：同科NG
