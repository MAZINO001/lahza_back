<?php

namespace App\Helpers;

class FormatHelper
{
    public static function descriptionToBullets($text)
    {
        if (empty($text)) {
            return '';
        }

        $items = preg_split('/[,\n]/', $text);
        $items = array_map('trim', $items);
        $items = array_filter($items);

        if (empty($items)) {
            return '';
        }
        $html = '<ul style="margin: 0; list-style-type: none; font-size: 12px; line-height: 1.5; color: #555;">';

        foreach ($items as $item) {
            $html .= '<li style="margin-bottom: 3px; position: relative; padding-left: 12px;">';
            $html .= '<span style="position: absolute; left: 0; color: #051630; font-weight: 600;">-</span>';
            $html .= htmlspecialchars($item);
            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
?>
