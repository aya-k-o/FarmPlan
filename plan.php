<?php
// =============================================
// plan.php - 来季シミュレーション
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id  = $_SESSION['user_id'];
$field_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$year     = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y') + 1;

// 科名 → CSSクラス名
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

// ---- 畑一覧モード ----
if ($field_id === null) {
    $stmt = $pdo->prepare('SELECT * FROM fields WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user_id]);
    $fields = $stmt->fetchAll();
    $mode = 'list';

// ---- シミュレーションモード ----
} else {
    // 自分の畑か確認
    $stmt = $pdo->prepare('SELECT * FROM fields WHERE id = ? AND user_id = ?');
    $stmt->execute([$field_id, $user_id]);
    $field = $stmt->fetch();
    if (!$field) {
        header('Location: plan.php');
        exit;
    }

    // 計画中の区画データを取得
    $stmt = $pdo->prepare('
        SELECT
            p.id        AS plot_id,
            p.row_num,
            p.col_num,
            ps.id       AS season_id,
            ps.status,
            ps.quantity,
            v.id        AS vegetable_id,
            v.name      AS veg_name,
            v.family
        FROM plots p
        LEFT JOIN plot_seasons ps
            ON  ps.plot_id = p.id
            AND ps.mode    = "plan"
            AND ps.status  = "planned"
            AND ps.year    = ?
        LEFT JOIN vegetables v ON v.id = ps.vegetable_id
        WHERE p.field_id = ?
        ORDER BY p.row_num, p.col_num
    ');
    $stmt->execute([$year, $field_id]);
    $plots_raw = $stmt->fetchAll();

    // 2次元配列に整理
    $grid = [];
    foreach ($plots_raw as $plot) {
        $grid[$plot['row_num']][$plot['col_num']] = $plot;
    }

    // 連作チェック用：各plotの直近3年の実績を取得
    $stmt = $pdo->prepare('
        SELECT ps.plot_id, v.family, ps.year
        FROM plot_seasons ps
        JOIN plots p      ON p.id = ps.plot_id
        JOIN vegetables v ON v.id = ps.vegetable_id
        WHERE p.field_id = ?
          AND ps.mode    = "actual"
          AND ps.year    >= ? - 3
          AND ps.year    <  ?
        ORDER BY ps.plot_id, ps.year DESC
    ');
    $stmt->execute([$field_id, $year, $year]);
    $history_raw = $stmt->fetchAll();

    // [plot_id] => [family1, family2, ...] の形に整理
    $history = [];
    foreach ($history_raw as $h) {
        $history[$h['plot_id']][] = $h['family'];
    }

    // 野菜リスト
    $stmt = $pdo->prepare('SELECT id, name, family, variety FROM vegetables ORDER BY family, name');
    $stmt->execute();
    $vegetables = $stmt->fetchAll();

    // 年の選択肢（今年〜3年後）
    $current_year = (int)date('Y');
    $year_options = [$current_year, $current_year + 1, $current_year + 2];

    $mode = 'grid';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>シミュレーション - FarmPlan</title>
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
    <a href="field.php">畑マップ</a>
    <a href="plan.php" class="active">シミュレーション</a>
    <a href="history.php">栽培記録</a>
    <a href="settings.php">設定</a>
  </nav>
</header>

<main class="main-content">

<?php if ($mode === 'list'): ?>
  <!-- 畑一覧 -->
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
    <div>
      <h1 class="page-title">シミュレーション</h1>
      <p class="page-subtitle">来季の作付け計画を立てましょう</p>
    </div>
  </div>

  <?php if (empty($fields)): ?>
    <div class="empty-state">
      <p>まだ畑が登録されていません。</p>
      <a href="field_create.php" class="btn-link">畑を作成する</a>
    </div>
  <?php else: ?>
    <div class="field-list">
      <?php foreach ($fields as $f): ?>
        <a href="plan.php?id=<?= $f['id'] ?>" class="field-card">
          <div class="field-card-name"><?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?></div>
          <div class="field-card-size"><?= $f['grid_rows'] ?>m × <?= $f['grid_cols'] ?>m</div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <!-- シミュレーショングリッド -->
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
    <div>
      <h1 class="page-title"><?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?> — 計画</h1>
      <p class="page-subtitle">区画をクリックして来季の野菜を配置してください</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
      <!-- 年切替 -->
      <form method="get" action="plan.php" style="display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="id" value="<?= $field_id ?>">
        <select class="form-input" name="year" onchange="this.form.submit()" style="width:auto;">
          <?php foreach ($year_options as $y): ?>
            <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?>年</option>
          <?php endforeach; ?>
        </select>
      </form>
      <a href="plan.php" class="btn-back">← 畑一覧</a>
    </div>
  </div>

  <div class="plan-notice">
    <span class="plan-notice-icon">📋</span>
    点線枠が計画済み区画です。連作の恐れがある区画には <strong>⚠</strong> が表示されます。
  </div>

  <!-- グリッド -->
  <div class="grid-wrapper">
    <div class="farm-grid" style="grid-template-columns: 28px repeat(<?= $field['grid_cols'] ?>, 1fr);">

      <div></div>
      <?php for ($c = 1; $c <= $field['grid_cols']; $c++): ?>
        <div class="grid-label"><?= $c ?>m</div>
      <?php endfor; ?>

      <?php for ($r = 1; $r <= $field['grid_rows']; $r++): ?>
        <div class="grid-label"><?= $r ?>m</div>
        <?php for ($c = 1; $c <= $field['grid_cols']; $c++): ?>
          <?php
            $plot        = $grid[$r][$c] ?? null;
            $family_class = $plot && $plot['family'] ? familyClass($plot['family']) : 'empty';
            $veg_name    = $plot['veg_name']    ?? '';
            $season_id   = $plot['season_id']   ?? '';
            $plot_id     = $plot['plot_id']      ?? '';
            $family      = $plot['family']       ?? '';
            $quantity    = $plot['quantity']     ?? 1;

            // 連作チェック
            $has_warning = false;
            if ($family && isset($history[$plot_id])) {
                $has_warning = in_array($family, $history[$plot_id]);
            }
          ?>
          <div
            class="plot <?= $family_class ?> planned <?= $has_warning ? 'rotation-warning' : '' ?>"
            data-plot-id="<?= htmlspecialchars($plot_id, ENT_QUOTES, 'UTF-8') ?>"
            data-season-id="<?= htmlspecialchars($season_id, ENT_QUOTES, 'UTF-8') ?>"
            data-veg-name="<?= htmlspecialchars($veg_name, ENT_QUOTES, 'UTF-8') ?>"
            data-family="<?= htmlspecialchars($family, ENT_QUOTES, 'UTF-8') ?>"
            data-quantity="<?= (int)$quantity ?>"
            data-row="<?= $r ?>"
            data-col="<?= $c ?>"
            data-warning="<?= $has_warning ? '1' : '0' ?>"
            onclick="openPlanModal(this)"
          ><?= htmlspecialchars($veg_name, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endfor; ?>
      <?php endfor; ?>

    </div>

    <!-- 凡例 -->
    <div class="legend">
      <div class="legend-item"><div class="legend-dot nasuka"></div>ナス科</div>
      <div class="legend-item"><div class="legend-dot urka"></div>ウリ科</div>
      <div class="legend-item"><div class="legend-dot konka"></div>根菜</div>
      <div class="legend-item"><div class="legend-dot hagasai"></div>葉野菜</div>
      <div class="legend-item"><div class="legend-dot imoka"></div>イモ類</div>
      <div class="legend-item"><div class="legend-dot mameka"></div>マメ科</div>
      <div class="legend-item"><div class="legend-dot empty"></div>空き</div>
      <div class="legend-item"><div class="legend-dot" style="background:#fff3e0;border:2px solid #e67e22;"></div>連作警告</div>
    </div>
  </div>

  <!-- モーダル -->
  <div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>
  <div class="modal" id="modal">
    <div class="modal-header">
      <div>
        <div class="modal-plot-label" id="modalPlotLabel"></div>
        <div class="modal-veg-name" id="modalVegName"></div>
      </div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <div id="modalRotationWarning" class="alert alert-error" style="display:none; margin-bottom:14px;">
      ▲ 連作障害の恐れあり — 過去3年以内に同じ科を栽培しています
    </div>

    <!-- 空き：野菜を計画 -->
    <div id="modalEmpty">
      <form method="post" action="plan_action.php">
        <input type="hidden" name="action"   value="plan">
        <input type="hidden" name="field_id" value="<?= $field_id ?>">
        <input type="hidden" name="year"     value="<?= $year ?>">
        <input type="hidden" name="plot_id"  id="inputPlotId">

        <div class="form-group">
          <label class="form-label">野菜を選択</label>
          <select class="form-input" name="vegetable_id" required>
            <option value="">-- 選択してください --</option>
            <?php
            $cur_family = '';
            foreach ($vegetables as $v):
                if ($v['family'] !== $cur_family):
                    if ($cur_family !== '') echo '</optgroup>';
                    echo '<optgroup label="' . htmlspecialchars($v['family'], ENT_QUOTES, 'UTF-8') . '">';
                    $cur_family = $v['family'];
                endif;
            ?>
              <option value="<?= $v['id'] ?>">
                <?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($v['variety'])): ?>
                  （<?= htmlspecialchars($v['variety'], ENT_QUOTES, 'UTF-8') ?>）
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
            <?php if ($cur_family !== '') echo '</optgroup>'; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">株数</label>
          <input class="form-input" type="number" name="quantity" value="1" min="1" max="99">
        </div>
        <button class="btn-primary" type="submit">この区画に配置する</button>
      </form>
    </div>

    <!-- 計画済み：削除ボタン -->
    <div id="modalPlanned" style="display:none;">
      <div class="modal-info-row"><span class="modal-info-label">科</span><span id="modalFamily"></span></div>
      <div class="modal-info-row"><span class="modal-info-label">株数</span><span id="modalQuantity"></span></div>
      <form method="post" action="plan_action.php" style="margin-top:16px;">
        <input type="hidden" name="action"    value="remove">
        <input type="hidden" name="field_id"  value="<?= $field_id ?>">
        <input type="hidden" name="year"      value="<?= $year ?>">
        <input type="hidden" name="season_id" id="inputSeasonId">
        <button class="btn btn-fail" type="submit" style="width:100%;">この区画の計画を削除する</button>
      </form>
    </div>
  </div>

<?php endif; ?>
</main>

<script>
function openPlanModal(el) {
  const plotId   = el.dataset.plotId;
  const seasonId = el.dataset.seasonId;
  const vegName  = el.dataset.vegName;
  const family   = el.dataset.family;
  const quantity = el.dataset.quantity;
  const row      = el.dataset.row;
  const col      = el.dataset.col;
  const warning  = el.dataset.warning === '1';

  document.getElementById('modalPlotLabel').textContent = row + '行 ' + col + '列';
  document.getElementById('modalRotationWarning').style.display = warning ? 'block' : 'none';
  document.getElementById('modalOverlay').style.display = 'block';
  document.getElementById('modal').style.display = 'block';

  if (!vegName) {
    document.getElementById('modalVegName').textContent = '空き区画';
    document.getElementById('inputPlotId').value = plotId;
    document.getElementById('modalEmpty').style.display = 'block';
    document.getElementById('modalPlanned').style.display = 'none';
  } else {
    document.getElementById('modalVegName').textContent = vegName;
    document.getElementById('modalFamily').textContent = family;
    document.getElementById('modalQuantity').textContent = quantity + '株';
    document.getElementById('inputSeasonId').value = seasonId;
    document.getElementById('modalEmpty').style.display = 'none';
    document.getElementById('modalPlanned').style.display = 'block';
  }
}

function closeModal() {
  document.getElementById('modalOverlay').style.display = 'none';
  document.getElementById('modal').style.display = 'none';
}
</script>

</body>
</html>
