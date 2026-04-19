<?php
// =============================================
// functions.php - 共通関数
// =============================================

/**
 * 科名を CSS クラス名に変換する
 *
 * @param  string $family  科名（例: 'ナス科'）
 * @return string          CSSクラス名（例: 'nasuka'）
 */
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
