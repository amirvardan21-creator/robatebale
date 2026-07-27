<?php
// Safe defaults
$stats = $stats ?? [];
$recentUsers = $recentUsers ?? [];
$recentReports = $recentReports ?? [];
$topReferrers = $topReferrers ?? [];
$chartLabels = $chartLabels ?? [];
$chartUsers = $chartUsers ?? [];
$chartSecret = $chartSecret ?? [];

// Build simple arrays for charts - safe
$labels = [];
$usersData = [];
$secretData = [];
if (!empty($chartLabels) && isset($chartLabels[0]['label'])) {
    foreach ($chartLabels as $c) {
        $labels[] = $c['label'] ?? '';
        $usersData[] = (int)($c['users'] ?? 0);
        $secretData[] = (int)($c['secret'] ?? 0);
    }
} else {
    // fallback 7 days
    for ($i=6;$i>=0;$i--) {
        $labels[] = date('m/d', strtotime("-$i days"));
    }
    $usersData = $chartUsers ?: array_fill(0,7,0);
    $secretData = $chartSecret ?: array_fill(0,7,0);
}
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?= number_format((int)($stats['total_users'] ?? 0)) ?></div><div class="stat-label">کل خانواده</div></div>
    <div class="stat-card green"><div class="stat-icon">🟢</div><div class="stat-value"><?= number_format((int)($stats['online_users'] ?? 0)) ?></div><div class="stat-label">آنلاین</div></div>
    <div class="stat-card"><div class="stat-icon">🆕</div><div class="stat-value"><?= number_format((int)($stats['today_users'] ?? 0)) ?></div><div class="stat-label">امروز</div></div>
    <div class="stat-card gold"><div class="stat-icon">⭐</div><div class="stat-value"><?= number_format((int)($stats['vip_users'] ?? 0)) ?></div><div class="stat-label">VIP</div></div>
    <div class="stat-card"><div class="stat-icon">💘</div><div class="stat-value"><?= number_format((int)($stats['matches'] ?? 0)) ?></div><div class="stat-label">مچ</div></div>
    <div class="stat-card blue"><div class="stat-icon">🕶</div><div class="stat-value"><?= number_format((int)($stats['secret_active'] ?? 0)) ?></div><div class="stat-label">چت مخفی فعال</div></div>
    <div class="stat-card gold"><div class="stat-icon">🪙</div><div class="stat-value"><?= number_format((int)($stats['total_coins'] ?? 0)) ?></div><div class="stat-label">سکه</div></div>
    <div class="stat-card"><div class="stat-icon">🚨</div><div class="stat-value"><?= number_format((int)($stats['pending_reports'] ?? 0)) ?></div><div class="stat-label">گزارش</div></div>
    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value"><?= number_format((int)($stats['secret_queue'] ?? 0)) ?></div><div class="stat-label">صف</div></div>
    <div class="stat-card"><div class="stat-icon">🚫</div><div class="stat-value"><?= number_format((int)($stats['blocked_users'] ?? 0)) ?></div><div class="stat-label">مسدود</div></div>
    <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-value"><?= number_format((int)($stats['active_users'] ?? 0)) ?></div><div class="stat-label">فعال</div></div>
    <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?= number_format((int)($stats['referrals'] ?? 0)) ?></div><div class="stat-label">دعوت</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">📈</span> رشد 7 روز</div>
        <div class="chart-wrap"><canvas id="growthChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-title"><span class="icon">🕶</span> چت مخفیانه 7 روز</div>
        <div class="chart-wrap"><canvas id="secretChart"></canvas></div>
    </div>
</div>

<script>
const chartLabels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
const chartUsers = <?= json_encode($usersData) ?>;
const chartSecret = <?= json_encode($secretData) ?>;

try {
    new Chart(document.getElementById('growthChart'), {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'کاربر جدید',
                data: chartUsers,
                borderColor: '#e10600',
                backgroundColor: 'rgba(225,6,0,0.12)',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                pointBackgroundColor: '#e10600',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b5f5f' } },
                x: { grid: { display: false }, ticks: { color: '#6b5f5f' } }
            }
        }
    });

    new Chart(document.getElementById('secretChart'), {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'چت',
                data: chartSecret,
                backgroundColor: 'rgba(225,6,0,0.7)',
                borderColor: '#e10600',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b5f5f' } },
                x: { grid: { display: false }, ticks: { color: '#6b5f5f' } }
            }
        }
    });
} catch(e) {
    console.error('Chart error', e);
}
</script>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">🆕</span> آخرین اعضا <span class="card-subtitle"><?= count($recentUsers) ?> نفر</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>نام</th><th>بله</th><th>شهر</th><th>تاریخ</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($recentUsers)): ?><tr><td colspan="5" class="empty-cell">عضوی نیست</td></tr>
                <?php else: foreach ($recentUsers as $u): ?>
                    <tr>
                        <td><div style="display:flex;align-items:center;gap:8px"><div style="width:32px;height:32px;background:linear-gradient(135deg,var(--red),var(--red-dark));border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px"><?= mb_substr(h($u['name'] ?? $u['first_name'] ?? 'U'),0,1) ?></div><strong><?= h($u['name'] ?? $u['first_name']) ?></strong></div></td>
                        <td><code style="font-size:11px"><?= h($u['bale_id']) ?></code></td>
                        <td><?= h($u['city'] ?? '—') ?></td>
                        <td><span class="text-muted" style="font-size:12px"><?= isset($u['created_at']) ? formatRelativeTime($u['created_at']) : '—' ?></span></td>
                        <td><a href="index.php?page=user_detail&uid=<?= (int)$u['id'] ?>" class="btn btn-xs btn-ghost">پرونده</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">🚨</span> گزارشات <span class="card-subtitle"><?= (int)($stats['pending_reports'] ?? 0) ?> در انتظار</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>گزارش‌دهنده</th><th>متهم</th><th>دلیل</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php if (empty($recentReports)): ?><tr><td colspan="4" class="empty-cell">گزارشی نیست</td></tr>
                <?php else: foreach ($recentReports as $r): ?>
                    <tr>
                        <td><?= h($r['reporter_name'] ?? '—') ?></td>
                        <td><?= h($r['reported_name'] ?? '—') ?></td>
                        <td><span style="font-size:12px"><?= h(mb_substr($r['reason'] ?? '',0,35)) ?></span></td>
                        <td><?php if (($r['status'] ?? '')==='pending'): ?><span class="badge badge-pending">انتظار</span><?php elseif (($r['status'] ?? '')==='resolved'): ?><span class="badge badge-active">حل</span><?php else: ?><span class="badge badge-blocked"><?= h($r['status'] ?? '') ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (($stats['pending_reports'] ?? 0) > 0): ?><div style="margin-top:16px"><a href="index.php?page=reports" class="btn btn-primary btn-sm">بررسی <?= (int)$stats['pending_reports'] ?> گزارش</a></div><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-title"><span class="icon">⚡</span> عملیات سریع</div>
    <div class="quick-actions">
        <a href="index.php?page=users" class="quick-btn">🎩 اعضا (<?= number_format((int)($stats['total_users'] ?? 0)) ?>)</a>
        <a href="index.php?page=secret" class="quick-btn">🕶 مخفیانه (<?= (int)($stats['secret_active'] ?? 0) ?>)</a>
        <a href="index.php?page=coins" class="quick-btn">🪙 خزانه</a>
        <a href="index.php?page=broadcast" class="quick-btn">📡 پیام همگانی</a>
        <a href="index.php?page=analytics" class="quick-btn">📈 تحلیل</a>
        <a href="index.php?page=referrals" class="quick-btn">👥 دعوت‌ها</a>
        <a href="index.php?page=vip" class="quick-btn">💎 VIP</a>
        <a href="index.php?page=logs" class="quick-btn">📜 لاگ‌ها</a>
    </div>
</div>
