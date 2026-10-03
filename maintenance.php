<?php
http_response_code(503);

$state = ['enabled' => false, 'expires_at' => null];
$state_file = __DIR__ . '/config/maintenance_state.php';
if (is_file($state_file)) {
    $saved_state = include $state_file;
    if (is_array($saved_state)) {
        $state['enabled'] = !empty($saved_state['enabled']);
        $state['expires_at'] = isset($saved_state['expires_at']) && is_numeric($saved_state['expires_at'])
            ? (int) $saved_state['expires_at']
            : null;
    }
}

$expires_at = $state['expires_at'];
$remaining = $expires_at !== null ? max(0, $expires_at - time()) : 0;

$base_url = '/';
$config_file = __DIR__ . '/config/config.php';
$config_contents = @file_get_contents($config_file);
if ($config_contents !== false && preg_match("~define\\(\\s*['\"]BASE_URL['\"]\\s*,\\s*['\"]([^'\"]+)['\"]~", $config_contents, $match)) {
    $base_url = rtrim($match[1], '/') . '/';
}
$logo_url = $base_url . 'assets/images/logo/logo.png';
$title_logo_url = $base_url . 'assets/images/logo/matrimony_title.png';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Smart Matrimony | Maintenance</title>
    <style>
        :root{
            --teal:#0d8179;
            --teal-dark:#075b57;
            --ink:#123c3b;
            --muted:#718987;
            --line:#dcece9;
            --bg:#f2faf8;
            --white:#fff;
        }
        *{box-sizing:border-box}
        html,body{min-height:100%;margin:0}
        body{
            min-height:100vh;display:grid;place-items:center;padding:24px 16px;color:var(--ink);
            background:
                radial-gradient(circle at 12% 12%,rgba(13,129,121,.12),transparent 27%),
                radial-gradient(circle at 88% 88%,rgba(7,91,87,.10),transparent 30%),
                linear-gradient(145deg,#f8fcfb 0%,var(--bg) 55%,#edf7f5 100%);
            font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        }
        .shell{width:min(700px,100%);text-align:center}
        .brand{display:flex;align-items:center;justify-content:center;gap:12px;margin:0 auto 26px;min-height:62px}
        .brand-mark-wrap{width:62px;height:62px;padding:8px;display:grid;place-items:center;background:#fff;border:1px solid #dcece9;border-radius:18px;box-shadow:0 12px 28px rgba(15,76,73,.10)}
        .brand-mark{width:46px;height:46px;object-fit:contain;display:block}
        .brand-title-wrap{min-height:54px;max-width:min(235px,58vw);padding:9px 18px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#075b57 0%,#0d8179 100%);border:1px solid rgba(255,255,255,.22);border-radius:17px;box-shadow:0 12px 28px rgba(7,91,87,.16)}
        .brand-title{width:min(205px,52vw);height:auto;max-height:36px;object-fit:contain;display:block}
        .card{
            position:relative;overflow:hidden;padding:clamp(34px,7vw,58px) clamp(20px,7vw,64px);
            background:rgba(255,255,255,.96);border:1px solid var(--line);border-radius:30px;
            box-shadow:0 28px 80px rgba(15,76,73,.12);
        }
        .card:before{content:"";position:absolute;left:50%;top:-125px;width:280px;height:280px;transform:translateX(-50%);border-radius:50%;background:rgba(13,129,121,.055)}
        .icon{
            position:relative;width:74px;height:74px;margin:0 auto 23px;border-radius:23px;display:grid;place-items:center;
            background:#e8f7f4;color:var(--teal);border:1px solid #cceae6;font-size:31px;
            box-shadow:0 12px 28px rgba(13,129,121,.10)
        }
        .icon:after{content:"";position:absolute;inset:7px;border:1px dashed #91cbc5;border-radius:17px}
        .eyebrow{display:block;margin-bottom:8px;color:var(--teal);font-size:10px;font-weight:900;letter-spacing:2.4px}
        h1{margin:0;color:#084f4c;font-size:clamp(29px,6vw,44px);line-height:1.08;letter-spacing:-1.2px}
        .countdown{position:relative;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;max-width:440px;margin:28px auto 0}
        .time-box{padding:16px 8px 13px;border:1px solid #d7ebe8;border-radius:16px;background:linear-gradient(180deg,#fbfefd,#f3faf8);box-shadow:0 8px 22px rgba(15,76,73,.055)}
        .time-value{display:block;color:#075b57;font-size:clamp(25px,6vw,36px);font-weight:900;line-height:1;font-variant-numeric:tabular-nums;letter-spacing:-1px}
        .time-label{display:block;margin-top:8px;color:#78908d;font-size:9px;font-weight:850;letter-spacing:1.5px;text-transform:uppercase}
        .done{display:none;margin-top:28px;color:#6f8582;font-size:13px;font-weight:700}
        @media(max-width:520px){
            .brand{margin-bottom:20px;gap:8px}.brand-mark-wrap{width:54px;height:54px;padding:7px;border-radius:16px}.brand-mark{width:40px;height:40px}.brand-title-wrap{min-height:48px;padding:8px 13px;border-radius:15px}.brand-title{width:175px;max-height:31px}
            .card{border-radius:24px;padding:31px 18px}.icon{width:68px;height:68px;border-radius:21px;font-size:28px}
            .countdown{gap:7px;margin-top:24px}.time-box{padding:14px 6px 12px;border-radius:14px}
        }
    </style>
</head>
<body>
<main class="shell">
    <div class="brand" aria-label="Smart Matrimony">
        <div class="brand-mark-wrap">
            <img class="brand-mark" src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="">
        </div>
        <div class="brand-title-wrap">
            <img class="brand-title" src="<?= htmlspecialchars($title_logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="Smart Matrimony">
        </div>
    </div>

    <section class="card" aria-labelledby="maintenance-title">
        <div class="icon" aria-hidden="true">⚙</div>
        <span class="eyebrow">MAINTENANCE MODE</span>
        <h1 id="maintenance-title">We’ll be back soon.</h1>

        <?php if ($expires_at !== null): ?>
            <div class="countdown" id="maintenance-countdown" data-expires-at="<?= (int) $expires_at ?>" aria-label="Maintenance countdown">
                <div class="time-box"><span class="time-value" data-hours>00</span><span class="time-label">Hours</span></div>
                <div class="time-box"><span class="time-value" data-minutes>00</span><span class="time-label">Minutes</span></div>
                <div class="time-box"><span class="time-value" data-seconds>00</span><span class="time-label">Seconds</span></div>
            </div>
            <div class="done" id="maintenance-done">The site is coming back online.</div>
        <?php endif; ?>
    </section>
</main>
<script>
(function(){
    var timer = document.getElementById('maintenance-countdown');
    if (!timer) return;
    var end = Number(timer.getAttribute('data-expires-at')) * 1000;
    var hours = timer.querySelector('[data-hours]');
    var minutes = timer.querySelector('[data-minutes]');
    var seconds = timer.querySelector('[data-seconds]');
    var done = document.getElementById('maintenance-done');
    var tick = function(){
        var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
        hours.textContent = String(Math.floor(left / 3600)).padStart(2,'0');
        minutes.textContent = String(Math.floor((left % 3600) / 60)).padStart(2,'0');
        seconds.textContent = String(left % 60).padStart(2,'0');
        if (left <= 0) {
            timer.style.display = 'none';
            if (done) done.style.display = 'block';
            window.setTimeout(function(){ window.location.reload(); }, 1200);
        }
    };
    tick();
    window.setInterval(tick,1000);
})();
</script>
</body>
</html>
