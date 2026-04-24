<?php
// =============================================
// history.php - 栽培記録・収穫履歴
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';
require 'functions.php';

$user_id = $_SESSION['user_id'];

// ---- 絞り込み条件を取得 ----
$filter_year   = isset($_GET['year'])   ? (int)$_GET['year']             : (int)date('Y');
$filter_status = isset($_GET['status']) ? $_GET['status']                : '';
$filter_family = isset($_GET['family']) ? trim($_GET['family'])          : '';

// ---- 栽培記録を取得 ----
$sql = '
    SELECT
        ps.id          AS season_id,
        ps.status,
        ps.year,
        ps.planted_at,
        ps.harvested_at,
        ps.quantity,
        ps.memo,
        v.name         AS veg_name,
        v.variety,
        v.family,
        f.name         AS field_name,
        p.row_num,
        p.col_num
    FROM plot_seasons ps
    JOIN plots p       ON p.id = ps.plot_id
    JOIN fields f      ON f.id = p.field_id
    JOIN vegetables v  ON v.id = ps.vegetable_id
    WHERE f.user_id = ?
      AND ps.mode = "actual"
      AND ps.year = ?
';

$params = [$user_id, $filter_year];

if ($filter_status !== '') {
    $sql .= ' AND ps.status = ?';
    $params[] = $filter_status;
}

if ($filter_family !== '') {
    $sql .= ' AND v.family = ?';
    $params[] = $filter_family;
}

$sql .= ' ORDER BY ps.year DESC, ps.planted_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// ---- 年の選択肢（記録がある年一覧） ----
$stmt = $pdo->prepare('
    SELECT DISTINCT ps.year
    FROM plot_seasons ps
    JOIN plots p  ON p.id = ps.plot_id
    JOIN fields f ON f.id = p.field_id
    WHERE f.user_id = ? AND ps.mode = "actual"
    ORDER BY ps.year DESC
');
$stmt->execute([$user_id]);
$years = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ---- 科の選択肢 ----
$families = ['ナス科', 'ウリ科', '根菜', '葉野菜', 'イモ類', 'マメ科'];

// ---- ステータスラベル ----
$status_labels = [
    'planned'   => '計画済み',
    'growing'   => '栽培中',
    'harvested' => '収穫済み',
    'failed'    => '失敗',
];

$page_title = '栽培記録';
$active_nav = 'history';
require 'header.php';
?>

<main class="main-content">

  <div class="page-header">
    <h1 class="page-title">栽培記録</h1>
    <p class="page-subtitle">過去の栽培履歴を確認できます</p>
  </div>

  <!-- 絞り込みフォーム -->
  <form method="get" action="history.php" class="filter-form">
    <div class="filter-row">

      <div class="filter-group">
        <label class="form-label">年</label>
        <select class="form-input" name="year">
          <?php if (empty($years)): ?>
            <option value="<?= date('Y') ?>"><?= date('Y') ?>年</option>
          <?php else: ?>
            <?php foreach ($years as $y): ?>
              <option value="<?= $y ?>" <?= $y == $filter_year ? 'selected' : '' ?>>
                <?= $y ?>年
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>

      <div class="filter-group">
        <label class="form-label">ステータス</label>
        <select class="form-input" name="status">
          <option value="">すべて</option>
          <?php foreach ($status_labels as $val => $label): ?>
            <option value="<?= $val ?>" <?= $val === $filter_status ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label class="form-label">科</label>
        <select class="form-input" name="family">
          <option value="">すべて</option>
          <?php foreach ($families as $fam): ?>
            <option value="<?= htmlspecialchars($fam, ENT_QUOTES, 'UTF-8') ?>"
              <?= $fam === $filter_family ? 'selected' : '' ?>>
              <?= htmlspecialchars($fam, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button class="btn-filter" type="submit">絞り込む</button>
    </div>
  </form>

  <!-- 記録一覧 -->
  <div class="section">
    <?php if (empty($records)): ?>
      <div class="empty-state">
        <p>該当する栽培記録がありません。</p>
        <a href="field.php" class="btn-link">畑マップから記録を追加する</a>
      </div>
    <?php else: ?>
      <form method="post" action="history_delete.php" onsubmit="return confirmDelete()">
        <!-- 絞り込み条件を引き継ぐ -->
        <input type="hidden" name="year"   value="<?= htmlspecialchars($filter_year,   ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="family" value="<?= htmlspecialchars($filter_family, ENT_QUOTES, 'UTF-8') ?>">

        <div class="bulk-action-bar">
          <p class="record-count"><?= count($records) ?>件の記録</p>
          <button class="btn-bulk-delete" type="submit">選択したものを削除</button>
        </div>

        <div class="history-table-wrap">
          <table class="history-table">
            <thead>
              <tr>
                <th><input type="checkbox" id="checkAll" title="すべて選択"></th>
                <th>野菜</th>
                <th>科</th>
                <th>株数</th>
                <th>畑・区画</th>
                <th>植え付け日</th>
                <th>収穫日</th>
                <th>メモ</th>
                <th>ステータス</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $rec): ?>
                <tr>
                  <td><input type="checkbox" name="season_ids[]" value="<?= $rec['season_id'] ?>"></td>
                  <td class="td-veg">
                    <?= htmlspecialchars($rec['veg_name'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($rec['variety'])): ?>
                      <span class="td-variety">（<?= htmlspecialchars($rec['variety'], ENT_QUOTES, 'UTF-8') ?>）</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="family-tag family-<?= familyClass($rec['family']) ?>">
                      <?= htmlspecialchars($rec['family'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                  <td><?= (int)$rec['quantity'] ?>株</td>
                  <td class="td-plot">
                    <?= htmlspecialchars($rec['field_name'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="td-plot-pos"><?= $rec['row_num'] ?>行<?= $rec['col_num'] ?>列</span>
                  </td>
                  <td><?= $rec['planted_at'] ?? '―' ?></td>
                  <td><?= $rec['harvested_at'] ?? '―' ?></td>
                  <td class="td-memo">
                    <input
                      class="memo-input"
                      type="text"
                      data-season-id="<?= $rec['season_id'] ?>"
                      value="<?= htmlspecialchars($rec['memo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                      placeholder="メモを入力..."
                    >
                  </td>
                  <td>
                    <span class="status-badge status-<?= htmlspecialchars($rec['status'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($status_labels[$rec['status']] ?? $rec['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>

      <script>
      // 全選択チェックボックス
      document.getElementById('checkAll').addEventListener('change', function() {
        document.querySelectorAll('input[name="season_ids[]"]').forEach(cb => {
          cb.checked = this.checked;
        });
      });

      // 削除前確認
      function confirmDelete() {
        const checked = document.querySelectorAll('input[name="season_ids[]"]:checked').length;
        if (checked === 0) {
          alert('削除する記録を選択してください。');
          return false;
        }
        return confirm(checked + '件の記録を削除しますか？');
      }

      // メモ：フォーカスを外したとき自動保存
      document.querySelectorAll('.memo-input').forEach(input => {
        const original = input.value;
        input.dataset.original = original;

        input.addEventListener('blur', function() {
          if (this.value === this.dataset.original) return; // 変更なし
          const seasonId = this.dataset.seasonId;
          const memo     = this.value;
          const el       = this;

          const fd = new FormData();
          fd.append('action',    'update_memo');
          fd.append('season_id', seasonId);
          fd.append('memo',      memo);

          fetch('plot_action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
              if (data.ok) {
                el.dataset.original = memo;
                el.style.borderColor = '#8ab870';
                setTimeout(() => { el.style.borderColor = ''; }, 1000);
              }
            })
            .catch(() => {});
        });
      });
      </script>
    <?php endif; ?>
  </div>

</main>

<?php require 'footer.php'; ?>
