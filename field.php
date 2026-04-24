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
require 'functions.php';

$user_id  = $_SESSION['user_id'];
$field_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

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
            ps.quantity,
            ps.memo,
            v.id         AS vegetable_id,
            v.name       AS veg_name,
            v.variety,
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
    $stmt = $pdo->prepare('SELECT id, name, family, variety FROM vegetables ORDER BY family, name');
    $stmt->execute();
    $vegetables = $stmt->fetchAll();

    $mode = 'grid';
}

$page_title = '畑マップ';
$active_nav = 'field';
require 'header.php';
?>

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
        <form method="post" action="field_delete.php"
              onsubmit="return confirm('「<?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>」を削除しますか？\n栽培記録もすべて削除されます。')">
          <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
          <button class="btn-delete-field" type="submit">削除</button>
        </form>
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
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <form method="post" action="plot_clear.php"
            onsubmit="return confirm('今年の栽培記録をすべてリセットしますか？')">
        <input type="hidden" name="field_id" value="<?= $field_id ?>">
        <input type="hidden" name="target"   value="season">
        <button class="btn-clear" type="submit">今年をリセット</button>
      </form>
      <form method="post" action="plot_clear.php"
            onsubmit="return confirm('この畑の全栽培記録を削除しますか？\nこの操作は取り消せません。')">
        <input type="hidden" name="field_id" value="<?= $field_id ?>">
        <input type="hidden" name="target"   value="all">
        <button class="btn-clear" type="submit">全記録を削除</button>
      </form>
      <a href="field.php" class="btn-back">← 畑一覧</a>
    </div>
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
            $variety      = $plot['variety'] ?? '';
            $season_id    = $plot['season_id'] ?? '';
            $status       = $plot['status'] ?? '';
            $planted_at   = $plot['planted_at'] ?? '';
            $quantity     = $plot['quantity'] ?? 1;
            $memo         = $plot['memo'] ?? '';
            $plot_id      = $plot['plot_id'] ?? '';
          ?>
          <div
            class="plot <?= $family_class ?> <?= $status === 'planned' ? 'planned' : '' ?>"
            data-plot-id="<?= htmlspecialchars($plot_id, ENT_QUOTES, 'UTF-8') ?>"
            data-season-id="<?= htmlspecialchars($season_id, ENT_QUOTES, 'UTF-8') ?>"
            data-veg-name="<?= htmlspecialchars($veg_name, ENT_QUOTES, 'UTF-8') ?>"
            data-variety="<?= htmlspecialchars($variety, ENT_QUOTES, 'UTF-8') ?>"
            data-family="<?= htmlspecialchars($plot['family'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
            data-planted-at="<?= htmlspecialchars($planted_at, ENT_QUOTES, 'UTF-8') ?>"
            data-quantity="<?= (int)$quantity ?>"
            data-memo="<?= htmlspecialchars($memo, ENT_QUOTES, 'UTF-8') ?>"
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
        <div class="modal-variety" id="modalVariety"></div>
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
              <option value="<?= $v['id'] ?>">
                <?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($v['variety'])): ?>
                  （<?= htmlspecialchars($v['variety'], ENT_QUOTES, 'UTF-8') ?>）
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
            <?php if ($current_family !== '') echo '</optgroup>'; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">株数</label>
          <input class="form-input" type="number" name="quantity" value="1" min="1" max="99">
        </div>

        <div class="form-group">
          <label class="form-label">植え付け日</label>
          <input class="form-input" type="date" name="planted_at" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">メモ（任意）</label>
          <textarea class="form-input" name="memo" rows="2" placeholder="例：接木苗使用、日当たり良好など"></textarea>
        </div>

        <button class="btn-primary" type="submit">植え付ける</button>
      </form>
    </div>

    <!-- 栽培中区画：情報 + 操作ボタン -->
    <div id="modalOccupied" style="display:none;">
      <div class="modal-info-row"><span class="modal-info-label">科</span><span id="modalFamily"></span></div>

      <div id="modalRotationWarning" class="alert alert-error" style="display:none; margin:12px 0;">
        ▲ 連作障害の恐れあり — 過去3年以内に同じ科を栽培しています
      </div>

      <!-- 編集フォーム -->
      <form method="post" action="plot_action.php" style="margin-top:12px;">
        <input type="hidden" name="action"    value="update">
        <input type="hidden" name="field_id"  value="<?= $field_id ?>">
        <input type="hidden" name="season_id" id="inputSeasonId">
        <div class="form-group">
          <label class="form-label">株数</label>
          <input class="form-input" type="number" name="quantity" id="editQuantity" min="1" max="99">
        </div>
        <div class="form-group">
          <label class="form-label">植え付け日</label>
          <input class="form-input" type="date" name="planted_at" id="editPlantedAt">
        </div>
        <div class="form-group">
          <label class="form-label">メモ（任意）</label>
          <textarea class="form-input" name="memo" id="editMemo" rows="2" placeholder="例：接木苗使用、日当たり良好など"></textarea>
        </div>
        <button class="btn-primary" type="submit" style="width:100%; margin-bottom:12px;">変更を保存する</button>
      </form>

      <!-- 収穫・失敗フォーム -->
      <form method="post" action="plot_action.php">
        <input type="hidden" name="field_id"  value="<?= $field_id ?>">
        <input type="hidden" name="season_id" id="inputSeasonId2">
        <div class="btn-row">
          <button class="btn btn-harvest" type="submit" name="action" value="harvest">収穫した</button>
          <button class="btn btn-fail"    type="submit" name="action" value="fail">失敗した</button>
        </div>
      </form>

      <!-- 取り消しフォーム -->
      <form method="post" action="plot_action.php" style="margin-top:8px;"
            onsubmit="return confirm('この区画の植え付け記録を取り消しますか？')">
        <input type="hidden" name="field_id"  value="<?= $field_id ?>">
        <input type="hidden" name="season_id" id="inputSeasonId3">
        <button class="btn btn-cancel" type="submit" name="action" value="cancel" style="width:100%;">植え付けを取り消す</button>
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
  const variety   = el.dataset.variety;
  const family    = el.dataset.family;
  const status    = el.dataset.status;
  const plantedAt = el.dataset.plantedAt;
  const quantity  = el.dataset.quantity;
  const memo      = el.dataset.memo;
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
    document.getElementById('modalVariety').textContent = variety ? '（' + variety + '）' : '';
    document.getElementById('modalFamily').textContent = family;
    document.getElementById('editQuantity').value   = quantity;
    document.getElementById('editPlantedAt').value  = plantedAt;
    document.getElementById('editMemo').value       = memo;
    document.getElementById('inputSeasonId').value  = seasonId;
    document.getElementById('inputSeasonId2').value = seasonId;
    document.getElementById('inputSeasonId3').value = seasonId;
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

<?php require 'footer.php'; ?>
