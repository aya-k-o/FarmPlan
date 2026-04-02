<?php
// =============================================
// field.php - 畑マップ
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id  = $_SESSION['user_id'];
$field_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// 科名 → CSSクラス名の変換
function familyClass(string $family): string {
    $map = [
        'ナス科' => 'nasuka',
        'ウリ科' => 'urka',
        '根菜'   => 'konka',
        '葉野菜' => 'hagasai',
        'イモ類' => 'imoka',
        'マメ科' => 'mameka',
    ];
    return $map[$family] ?? 'empty';
}

// ---- 畑一覧モード（?idなし） ----
if ($field_id === null) {
    $stmt = $pdo->prepare('SELECT * FROM fields WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user_id]);
    $fields = $stmt->fetchAll();
    $mode = 'list';

// ---- グリッドモード（?id=X） ----
} else {
    // 自分の畑か確認
    $stmt = $pdo->prepare('SELECT * FROM fields WHERE id = ? AND user_id = ?');
    $stmt->execute([$field_id, $user_id]);
    $field = $stmt->fetch();

    if (!$field) {
        header('Location: field.php');
        exit;
    }

    // 全区画と現在の栽培情報を取得
    $stmt = $pdo->prepare('
        SELECT
            p.id         AS plot_id,
            p.row_num,
            p.col_num,
            ps.id        AS season_id,
            ps.status,
            ps.planted_at,
            v.id         AS vegetable_id,
            v.name       AS veg_name,
            v.family
        FROM plots p
        LEFT JOIN plot_seasons ps
            ON  ps.plot_id = p.id
            AND ps.mode    = "actual"
            AND ps.status  IN ("growing", "planned")
            AND ps.year    = YEAR(NOW())
        LEFT JOIN vegetables v ON v.id = ps.vegetable_id
        WHERE p.field_id = ?
        ORDER BY p.row_num, p.col_num
    ');
    $stmt->execute([$field_id]);
    $plots_raw = $stmt->fetchAll();

    // [row][col] の2次元配列に整理
    $grid = [];
    foreach ($plots_raw as $plot) {
        $grid[$plot['row_num']][$plot['col_num']] = $plot;
    }

    // 野菜リスト（モーダルのセレクトボックス用）
    $stmt = $pdo->prepare('SELECT id, name, family FROM vegetables ORDER BY family, name');
    $stmt->execute();
    $vegetables = $stmt->fetchAll();

    $mode = 'grid';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>畑マップ - FarmPlan</title>
  <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&family=Shippori+Mincho:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <a href="index.php" class="logo">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
      <path d="M12 22V12" stroke="#b8d89a" stroke-width="2" stroke-linecap="round"/>
      <path d="M12 12C12 12 7 10 5 5C9 4 13 6 14 10" fill="#5a8a45"/>
      <path d="M12 12C12 12 17 10 19 5C15 4 11 6 10 10" fill="#8ab870"/>
      <path d="M12 16C12 16 9 14 8 11C10 10.5 12.5 12 12 16Z" fill="#3a5a2e"/>
    </svg>
    Farm<span>Plan</span>
  </a>
  <nav>
    <a href="index.php">ホーム</a>
    <a href="field.php" class="active">畑マップ</a>
    <a href="plan.php">シミュレーション</a>
    <a href="history.php">栽培記録</a>
    <a href="settings.php">設定</a>
  </nav>
</header>

<main class="main-content">

<?php if ($mode === 'list'): ?>
  <!-- ===== 畑一覧 ===== -->
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
    <div>
      <h1 class="page-title">畑マップ</h1>
      <p class="page-subtitle">管理している畑を選択してください</p>
    </div>
    <a href="field_create.php" class="btn-create">＋ 畑を追加</a>
  </div>

  <?php if (empty($fields)): ?>
    <div class="empty-state">
      <p>まだ畑が登録されていません。</p>
      <a href="field_create.php" class="btn-link">畑を作成する</a>
    </div>
  <?php else: ?>
    <div class="field-list">
      <?php foreach ($fields as $f): ?>
        <a href="field.php?id=<?= $f['id'] ?>" class="field-card">
          <div class="field-card-name"><?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?></div>
          <div class="field-card-size"><?= $f['grid_rows'] ?>m × <?= $f['grid_cols'] ?>m（<?= $f['grid_rows'] * $f['grid_cols'] ?>区画）</div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <!-- ===== グリッド表示 ===== -->
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
    <div>
      <h1 class="page-title"><?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="page-subtitle"><?= $field['grid_rows'] ?>m × <?= $field['grid_cols'] ?>m</p>
    </div>
    <a href="field.php" class="btn-back">← 畑一覧に戻る</a>
  </div>

  <!-- グリッド -->
  <div class="grid-wrapper">
    <div class="farm-grid" style="grid-template-columns: 28px repeat(<?= $field['grid_cols'] ?>, 1fr);">

      <!-- 1行目：列ラベル -->
      <div></div>
      <?php for ($c = 1; $c <= $field['grid_cols']; $c++): ?>
        <div class="grid-label"><?= $c ?>m</div>
      <?php endfor; ?>

      <!-- 2行目以降：行ラベル + 区画 -->
      <?php for ($r = 1; $r <= $field['grid_rows']; $r++): ?>
        <div class="grid-label"><?= $r ?>m</div>
        <?php for ($c = 1; $c <= $field['grid_cols']; $c++): ?>
          <?php $plot = $grid[$r][$c] ?? null; ?>
          <?php
            $family_class = $plot && $plot['family'] ? familyClass($plot['family']) : 'empty';
            $veg_name     = $plot['veg_name'] ?? '';
            $season_id    = $plot['season_id'] ?? '';
            $status       = $plot['status'] ?? '';
            $planted_at   = $plot['planted_at'] ?? '';
            $plot_id      = $plot['plot_id'] ?? '';
          ?>
          <div
            class="plot <?= $family_class ?> <?= $status === 'planned' ? 'planned' : '' ?>"
            data-plot-id="<?= htmlspecialchars($plot_id, ENT_QUOTES, 'UTF-8') ?>"
            data-season-id="<?= htmlspecialchars($season_id, ENT_QUOTES, 'UTF-8') ?>"
            data-veg-name="<?= htmlspecialchars($veg_name, ENT_QUOTES, 'UTF-8') ?>"
            data-family="<?= htmlspecialchars($plot['family'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
            data-planted-at="<?= htmlspecialchars($planted_at, ENT_QUOTES, 'UTF-8') ?>"
            data-row="<?= $r ?>"
            data-col="<?= $c ?>"
            onclick="openModal(this)"
          ><?= htmlspecialchars($veg_name, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endfor; ?>
      <?php endfor; ?>

    </div><!-- /farm-grid -->

    <!-- 凡例 -->
    <div class="legend">
      <div class="legend-item"><div class="legend-dot nasuka"></div>ナス科</div>
      <div class="legend-item"><div class="legend-dot urka"></div>ウリ科</div>
      <div class="legend-item"><div class="legend-dot konka"></div>根菜</div>
      <div class="legend-item"><div class="legend-dot hagasai"></div>葉野菜</div>
      <div class="legend-item"><div class="legend-dot imoka"></div>イモ類</div>
      <div class="legend-item"><div class="legend-dot mameka"></div>マメ科</div>
      <div class="legend-item"><div class="legend-dot empty"></div>空き</div>
    </div>
  </div><!-- /grid-wrapper -->

  <!-- ===== モーダル ===== -->
  <div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>
  <div class="modal" id="modal">
    <div class="modal-header">
      <div>
        <div class="modal-plot-label" id="modalPlotLabel"></div>
        <div class="modal-veg-name" id="modalVegName"></div>
      </div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <!-- 空き区画：野菜を植えるフォーム -->
    <div id="modalEmpty">
      <form method="post" action="plot_action.php">
        <input type="hidden" name="action"   value="plant">
        <input type="hidden" name="field_id" value="<?= $field_id ?>">
        <input type="hidden" name="plot_id"  id="inputPlotId">

        <div class="form-group">
          <label class="form-label">野菜を選択</label>
          <select class="form-input" name="vegetable_id" required>
            <option value="">-- 選択してください --</option>
            <?php
            $current_family = '';
            foreach ($vegetables as $v):
                if ($v['family'] !== $current_family):
                    if ($current_family !== '') echo '</optgroup>';
                    echo '<optgroup label="' . htmlspecialchars($v['family'], ENT_QUOTES, 'UTF-8') . '">';
                    $current_family = $v['family'];
                endif;
            ?>
              <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
            <?php if ($current_family !== '') echo '</optgroup>'; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">植え付け日</label>
          <input class="form-input" type="date" name="planted_at" value="<?= date('Y-m-d') ?>">
        </div>

        <button class="btn-primary" type="submit">植え付ける</button>
      </form>
    </div>

    <!-- 栽培中区画：情報 + 操作ボタン -->
    <div id="modalOccupied" style="display:none;">
      <div class="modal-info-row"><span class="modal-info-label">科</span><span id="modalFamily"></span></div>
      <div class="modal-info-row"><span class="modal-info-label">植え付け日</span><span id="modalPlantedAt"></span></div>

      <div id="modalRotationWarning" class="alert alert-error" style="display:none; margin:12px 0;">
        ▲ 連作障害の恐れあり — 過去3年以内に同じ科を栽培しています
      </div>

      <form method="post" action="plot_action.php" style="margin-top:16px;">
        <input type="hidden" name="field_id"  value="<?= $field_id ?>">
        <input type="hidden" name="season_id" id="inputSeasonId">
        <div class="btn-row">
          <button class="btn btn-harvest" type="submit" name="action" value="harvest">収穫した</button>
          <button class="btn btn-fail"    type="submit" name="action" value="fail">失敗した</button>
        </div>
      </form>
    </div>
  </div><!-- /modal -->

<?php endif; ?>
</main>

<script>
function openModal(el) {
  const plotId    = el.dataset.plotId;
  const seasonId  = el.dataset.seasonId;
  const vegName   = el.dataset.vegName;
  const family    = el.dataset.family;
  const status    = el.dataset.status;
  const plantedAt = el.dataset.plantedAt;
  const row       = el.dataset.row;
  const col       = el.dataset.col;

  document.getElementById('modalPlotLabel').textContent = row + '行 ' + col + '列';
  document.getElementById('modalOverlay').style.display = 'block';
  document.getElementById('modal').style.display = 'block';

  if (!status) {
    // 空き区画
    document.getElementById('modalVegName').textContent = '空き区画';
    document.getElementById('inputPlotId').value = plotId;
    document.getElementById('modalEmpty').style.display = 'block';
    document.getElementById('modalOccupied').style.display = 'none';

    // 連作チェック（AJAX）
    document.getElementById('modalRotationWarning').style.display = 'none';
  } else {
    // 栽培中 or 計画済み
    document.getElementById('modalVegName').textContent = vegName;
    document.getElementById('modalFamily').textContent = family;
    document.getElementById('modalPlantedAt').textContent = plantedAt || '未登録';
    document.getElementById('inputSeasonId').value = seasonId;
    document.getElementById('modalEmpty').style.display = 'none';
    document.getElementById('modalOccupied').style.display = 'block';

    // 連作チェック（AJAX）
    fetch('plot_action.php?action=check_rotation&plot_id=' + plotId + '&family=' + encodeURIComponent(family))
      .then(r => r.json())
      .then(data => {
        document.getElementById('modalRotationWarning').style.display = data.warning ? 'block' : 'none';
      });
  }
}

function closeModal() {
  document.getElementById('modalOverlay').style.display = 'none';
  document.getElementById('modal').style.display = 'none';
}
</script>

</body>
</html>
