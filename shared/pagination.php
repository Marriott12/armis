<?php

if (!function_exists('getPagination')) {
    function getPagination(int $total, int $perPage = 50): array
    {
        $perPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $page = min(max(1, $page), $totalPages);

        return [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'offset' => ($page - 1) * $perPage,
        ];
    }
}

if (!function_exists('renderPagination')) {
    function renderPagination(array $pagination): string
    {
        if ($pagination['totalPages'] <= 1) {
            return '';
        }

        $query = $_GET;
        unset($query['page']);
        $urlForPage = static function (int $page) use ($query): string {
            return '?' . http_build_query($query + ['page' => $page]);
        };

        $html = '<nav aria-label="Results pages"><ul class="pagination pagination-sm justify-content-end mb-0">';
        $previousDisabled = $pagination['page'] === 1 ? ' disabled' : '';
        $html .= '<li class="page-item' . $previousDisabled . '"><a class="page-link" href="' . htmlspecialchars($urlForPage(max(1, $pagination['page'] - 1)), ENT_QUOTES) . '" aria-label="Previous page">Previous</a></li>';

        for ($page = max(1, $pagination['page'] - 2); $page <= min($pagination['totalPages'], $pagination['page'] + 2); $page++) {
            $active = $page === $pagination['page'] ? ' active' : '';
            $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . htmlspecialchars($urlForPage($page), ENT_QUOTES) . '">' . $page . '</a></li>';
        }

        $nextDisabled = $pagination['page'] === $pagination['totalPages'] ? ' disabled' : '';
        $html .= '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . htmlspecialchars($urlForPage(min($pagination['totalPages'], $pagination['page'] + 1)), ENT_QUOTES) . '" aria-label="Next page">Next</a></li>';
        return $html . '</ul></nav>';
    }
}