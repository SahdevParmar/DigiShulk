<?php
function generate_pagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) {
        return '';
    }

    $html = '<div class="pagination">';

    // Previous button
    if ($current_page > 1) {
        $html .= '<a href="' . $base_url . '&page=' . ($current_page - 1) . '">« Prev</a>';
    } else {
        $html .= '<span class="disabled">« Prev</span>';
    }

    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            $html .= '<span class="active">' . $i . '</span>';
        } else {
            // Show only a range of pages to avoid a huge list
            if ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
                $html .= '<a href="' . $base_url . '&page=' . $i . '">' . $i . '</a>';
            } elseif ($i == $current_page - 3 || $i == $current_page + 3) {
                 $html .= '<span class="dots">...</span>';
            }
        }
    }

    // Next button
    if ($current_page < $total_pages) {
        $html .= '<a href="' . $base_url . '&page=' . ($current_page + 1) . '">Next »</a>';
    } else {
        $html .= '<span class="disabled">Next »</span>';
    }

    $html .= '</div>';
    return $html;
}
?>
