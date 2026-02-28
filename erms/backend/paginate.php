<?php
function paginate(int $total, int $per_page, int $current_page): array {
    $total_pages  = max(1, (int)ceil($total / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset       = ($current_page - 1) * $per_page;
    return [
        'total'       => $total,
        'per_page'    => $per_page,
        'current'     => $current_page,
        'total_pages' => $total_pages,
        'offset'      => $offset,
        'has_prev'    => $current_page > 1,
        'has_next'    => $current_page < $total_pages,
        'from'        => $total === 0 ? 0 : $offset + 1,
        'to'          => min($offset + $per_page, $total),
    ];
}

function render_pagination(array $pg, array $extra = []): void {
    if ($pg['total_pages'] <= 1) return;
    $base = array_filter($extra, fn($v) => $v !== '' && $v !== null && $v !== false);
    $link = fn(int $p): string => '?' . http_build_query(array_merge($base, ['page' => $p]));
    $current = $pg['current'];
    $total   = $pg['total_pages'];

    $pages = array_unique(array_filter([
        1, $current-2, $current-1, $current, $current+1, $current+2, $total
    ], fn($p) => $p >= 1 && $p <= $total));
    sort($pages);

    echo '<div class="pagination">';
    echo '<span class="pg-info">Showing ' . $pg['from'] . '–' . $pg['to'] . ' of ' . number_format($pg['total']) . '</span>';
    echo '<div class="pg-controls">';
    echo $pg['has_prev']
        ? '<a href="' . $link($current-1) . '" class="pg-btn">‹</a>'
        : '<span class="pg-btn disabled">‹</span>';

    $prev = null;
    foreach ($pages as $p) {
        if ($prev !== null && $p - $prev > 1) echo '<span class="pg-dots">…</span>';
        echo $p === $current
            ? '<span class="pg-btn active">' . $p . '</span>'
            : '<a href="' . $link($p) . '" class="pg-btn">' . $p . '</a>';
        $prev = $p;
    }

    echo $pg['has_next']
        ? '<a href="' . $link($current+1) . '" class="pg-btn">›</a>'
        : '<span class="pg-btn disabled">›</span>';
    echo '</div></div>';
}