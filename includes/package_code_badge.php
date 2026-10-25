<?php
/** Render a consistent, clearly labeled package-ID badge above a package title. */
if (!function_exists('sm_package_id_badge')) {
    function sm_package_id_badge($code): string
    {
        $code = trim((string)($code ?? ''));
        if ($code === '') return '';
        $safe = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        return '<div class="sm-package-id-badge" style="display:inline-flex;flex-direction:column;align-items:flex-start;gap:2px;margin:0 0 7px;padding:5px 9px;border:1px solid #c9e8e4;border-radius:9px;background:linear-gradient(135deg,#f0fbf9 0%,#e5f5f2 100%);box-shadow:0 1px 2px rgba(8,95,91,.06);line-height:1.1;max-width:max-content">'
            . '<span style="font-size:9px;font-weight:800;letter-spacing:.11em;color:#5b817f">PACKAGE ID</span>'
            . '<strong style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;font-weight:800;letter-spacing:.045em;color:#087f79">' . $safe . '</strong>'
            . '</div>';
    }
}
