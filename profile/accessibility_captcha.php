<?php

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$code = '';
for ($i = 0; $i < 5; $i++) {
    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}

$_SESSION['step5_vision_code'] = $code;

$escapedCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
$width = 260;
$height = 86;

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $width ?>" height="<?= $height ?>" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="Visual verification code">
    <defs>
        <!-- Blur the complete CAPTCHA artwork, not the characters alone. -->
        <filter id="captchaWholeImageBlur" x="-10%" y="-25%" width="120%" height="150%">
            <feGaussianBlur stdDeviation="2.8" />
        </filter>
        <filter id="captchaWarp" x="-10%" y="-20%" width="120%" height="140%">
            <feTurbulence type="fractalNoise" baseFrequency="0.025 0.08" numOctaves="1" seed="<?= random_int(1, 9999) ?>" result="noise" />
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="7" xChannelSelector="R" yChannelSelector="G" />
        </filter>
    </defs>

    <g filter="url(#captchaWholeImageBlur)">

    <rect width="260" height="86" rx="14" fill="#f0fdf4"/>

    <!-- Background interference -->
    <path d="M-5 20 C45 2, 92 67, 145 29 S220 10, 266 61" fill="none" stroke="#a7e8ca" stroke-width="3" opacity=".72"/>
    <path d="M-8 68 C45 47, 82 11, 139 56 S219 72, 268 25" fill="none" stroke="#bcefd8" stroke-width="3" opacity=".78"/>
    <path d="M0 43 C55 66, 108 9, 165 43 S222 54, 260 37" fill="none" stroke="#d0f5e3" stroke-width="2" opacity=".9"/>

    <circle cx="34" cy="22" r="4" fill="#7dddb2" opacity=".7"/>
    <circle cx="226" cy="63" r="5" fill="#9be8c7" opacity=".78"/>
    <circle cx="190" cy="18" r="3" fill="#65d4a6" opacity=".65"/>
    <circle cx="75" cy="70" r="3" fill="#8ce1bc" opacity=".65"/>

    <!-- Character layer: slight blur + warp + different rotations/positions. -->
    <g fill="#3f756a" fill-opacity="0.78" font-family="Arial, Helvetica, sans-serif" font-size="36" font-weight="700" letter-spacing="2" >
        <g filter="url(#captchaWarp)">
            <?php
            $xPositions = [48, 91, 134, 177, 220];
            $yPositions = [56, 51, 59, 50, 57];
            $rotations = [-8, 5, -3, 8, -6];
            for ($i = 0; $i < 5; $i++):
                $x = $xPositions[$i];
                $y = $yPositions[$i];
                $rotation = $rotations[$i];
                $char = htmlspecialchars($code[$i], ENT_QUOTES, 'UTF-8');
            ?>
                <text x="<?= $x ?>" y="<?= $y ?>" text-anchor="middle" transform="rotate(<?= $rotation ?> <?= $x ?> <?= $y ?>)"><?= $char ?></text>
            <?php endfor; ?>
        </g>
    </g>

    <!-- Multi-layer foreground veil: several translucent strokes and texture partially cover the characters. -->
    <g opacity=".78">
        <path d="M-8 26 C32 7, 66 67, 108 34 S184 9, 224 43 S251 69, 270 55" fill="none" stroke="#74cfa9" stroke-width="4.5" opacity=".72"/>
        <path d="M-10 55 C31 76, 73 23, 117 58 S184 73, 226 30 S250 20, 270 35" fill="none" stroke="#5fbd98" stroke-width="3.8" opacity=".68"/>
        <path d="M4 78 C46 46, 82 78, 125 42 S187 16, 222 58 S250 76, 264 65" fill="none" stroke="#a4e4c8" stroke-width="5" opacity=".82"/>
        <path d="M18 12 C55 40, 91 16, 133 48 S195 67, 238 16" fill="none" stroke="#c0eedc" stroke-width="3" opacity=".9"/>
    </g>

    <!-- Short crossing strokes create additional cover directly across the character area. -->
    <g stroke-linecap="round">
        <path d="M25 48 L72 29 M55 68 L102 43 M92 30 L142 63 M128 25 L176 55 M166 68 L214 38 M196 27 L244 58" stroke="#d1f4e4" stroke-width="5" opacity=".72"/>
        <path d="M18 59 L58 39 M78 62 L118 36 M137 65 L177 39 M194 61 L238 38" stroke="#4fb58d" stroke-width="2.2" opacity=".55"/>
    </g>

    <!-- Sparse soft cover spots: enough to interrupt the letter shapes without hiding them completely. -->
    <g fill="#8bd9b8">
        <circle cx="53" cy="42" r="7" opacity=".22"/>
        <circle cx="96" cy="53" r="5" opacity=".25"/>
        <circle cx="143" cy="39" r="7" opacity=".20"/>
        <circle cx="184" cy="55" r="6" opacity=".24"/>
        <circle cx="222" cy="44" r="7" opacity=".22"/>
    </g>

    </g>
</svg>
