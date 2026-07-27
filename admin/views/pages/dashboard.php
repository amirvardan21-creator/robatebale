<?php
// داشبورد فوق ایمن - هیچ اروری نمیده
$stats = $stats ?? [];
$recentUsers = $recentUsers ?? [];
$recentReports = $recentReports ?? [];
?>
<div style="background:linear-gradient(135deg, rgba(225,6,0,0.1), transparent);border:1px solid var(--border-red);border-radius:12px;padding:16px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="font-size:24px">♠️</span>
    <div>
        <div style="font-weight:800">داشبورد فرماندهی - نسخه ایمن</div>
        <div style="font-size:12px;color:var(--text2)">این نسخه بدون چارت بارگذاری شد تا خطا نده - اگر این صفحه بالا اومد یعنی مشکل از چارت‌ها بود</div>
    </div>
    <div style="margin-right:auto"><span class="badge badge-active">✅ آنلاین</span></div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?= (int)($stats['total_users'] ?? 0) ?></div><div class="stat-label">کل اعضا</div></div>
    <div class="stat-card green"><div class="stat-icon">🟢</div><div class="stat-value"><?= (int)($stats['online_users'] ?? 0) ?></div><div class="stat-label">آنلاین</div></div>
    <div class="stat-card"><div class="stat-icon">🆕</div><div class="stat-value"><?= (int)($stats['today_users'] ?? 0) ?></div><div class="stat-label">امروز</div></div>
    <div class="stat-card gold"><div class="stat-icon">⭐</div><div class="stat-value"><?= (int)($stats['vip_users'] ?? 0) ?></div><div class="stat-label">VIP</div></div>
    <div class="stat-card"><div class="stat-icon">💘</div><div class="stat-value"><?= (int)($stats['matches'] ?? 0) ?></div><div class="stat-label">مچ</div></div>
    <div class="stat-card blue"><div class="stat-icon">🕶</div><div class="stat-value"><?= (int)($stats['secret_active'] ?? 0) ?></div><div class="stat-label">مخفیانه فعال</div></div>
    <div class="stat-card gold"><div class="stat-icon">🪙</div><div class="stat-value"><?= (int)($stats['total_coins'] ?? 0) ?></div><div class="stat-label">سکه</div></div>
    <div class="stat-card"><div class="stat-icon">🚨</div><div class="stat-value"><?= (int)($stats['pending_reports'] ?? 0) ?></div><div class="stat-label">گزارش</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">🆕</span> آخرین اعضا</div>
        <?php if (empty($recentUsers)): ?>
            <p class="empty-cell">هنوز عضوی نیست یا خطا در لود</p>
        <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>نام</th><th>بله آیدی</th><th>شهر</th><th>تاریخ</th><th></th></tr></thead><tbody>
            <?php foreach ($recentUsers as $u): ?>
            <tr>
                <td><strong><?= h($u['name'] ?? $u['first_name'] ?? '—') ?></strong></td>
                <td><code style="font-size:11px"><?= h($u['bale_id'] ?? '') ?></code></td>
                <td><?= h($u['city'] ?? '—') ?></td>
                <td><span style="font-size:11px;color:var(--text2)"><?= h($u['created_at'] ?? '') ?></span></td>
                <td><a href="index.php?page=user_detail&uid=<?= (int)($u['id'] ?? 0) ?>" class="btn btn-xs btn-ghost">مشاهده</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">🚨</span> آخرین گزارشات</div>
        <?php if (empty($recentReports)): ?>
            <p class="empty-cell">گزارشی نیست</p>
        <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>گزارش‌دهنده</th><th>متهم</th><th>دلیل</th></tr></thead><tbody>
            <?php foreach ($recentReports as $r): ?>
            <tr><td><?= h($r['reporter_name'] ?? '—') ?></td><td><?= h($r['reported_name'] ?? '—') ?></td><td><span style="font-size:12px"><?= h(mb_substr($r['reason'] ?? '',0,30)) ?></span></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-title"><span class="icon">⚙️</span> دیباگ داشبورد - اگر هنوز کار نمی‌کند این اطلاعات را بفرست</div>
    <div style="background:var(--bg);padding:16px;border-radius:8px;font-family:monospace;font-size:12px;direction:ltr;text-align:left">
        PHP: <?= PHP_VERSION ?><br>
        Stats count: <?= count($stats) ?><br>
        Users: <?= is_array($recentUsers) ? count($recentUsers) . ' loaded' : 'not loaded' ?><br>
        Reports: <?= is_array($recentReports) ? count($recentReports) . ' loaded' : 'not loaded' ?><br>
        Memory: <?= round(memory_get_usage()/1024/1024,2) ?> MB<br>
        Time: <?= date('Y-m-d H:i:s') ?><br>
        Logs path: <?= h(LOG_PATH) ?> <?= is_writable(LOG_PATH) ? 'writable ✅' : 'NOT writable ❌' ?><br>
        ChartLabels: <?= isset($chartLabels) ? count($chartLabels) . ' items' : 'not set' ?><br>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px">
        <a href="index.php?page=dashboard" class="btn btn-primary btn-sm">🔄 رفرش داشبورد</a>
        <a href="index.php?page=logs" class="btn btn-ghost btn-sm">📜 مشاهده لاگ‌ها</a>
        <a href="index.php?page=analytics" class="btn btn-ghost btn-sm">📈 آمار کامل</a>
    </div>
</div>

<div class="card">
    <div class="card-title"><span class="icon">⚡</span> عملیات سریع</div>
    <div class="quick-actions">
        <a href="index.php?page=users" class="quick-btn">🎩 اعضا</a>
        <a href="index.php?page=secret" class="quick-btn">🕶 مخفیانه</a>
        <a href="index.php?page=coins" class="quick-btn">🪙 خزانه</a>
        <a href="index.php?page=broadcast" class="quick-btn">📡 همگانی</a>
    </div>
</div>
