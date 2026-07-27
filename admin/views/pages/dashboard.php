<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
        <div class="stat-label">کل خانواده</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">🟢</div>
        <div class="stat-value"><?= number_format($stats['online_users']) ?></div>
        <div class="stat-label">آنلاین (5 دقیقه)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🆕</div>
        <div class="stat-value"><?= number_format($stats['today_users']) ?></div>
        <div class="stat-label">عضو جدید امروز</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-icon">⭐</div>
        <div class="stat-value"><?= number_format($stats['vip_users']) ?></div>
        <div class="stat-label">VIP فعال</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💘</div>
        <div class="stat-value"><?= number_format($stats['matches']) ?></div>
        <div class="stat-label">مچ فعال</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🕶</div>
        <div class="stat-value"><?= number_format($stats['secret_active']) ?></div>
        <div class="stat-label">چت مخفیانه فعال</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-icon">🪙</div>
        <div class="stat-value"><?= number_format($stats['total_coins']) ?></div>
        <div class="stat-label">سکه در گردش</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🚨</div>
        <div class="stat-value"><?= number_format($stats['pending_reports']) ?></div>
        <div class="stat-label">گزارش امنیتی</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= number_format($stats['secret_queue']) ?></div>
        <div class="stat-label">در صف ملاقات</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🚫</div>
        <div class="stat-value"><?= number_format($stats['blocked_users']) ?></div>
        <div class="stat-label">مسدود</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= number_format($stats['active_users']) ?></div>
        <div class="stat-label">فعال</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= number_format($stats['referrals']) ?></div>
        <div class="stat-label">دعوت‌ها</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">📈</span> رشد 7 روز اخیر</div>
        <div class="chart-wrap"><canvas id="growthChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-title"><span class="icon">🕶</span> چت مخفیانه 7 روز</div>
        <div class="chart-wrap"><canvas id="secretChart"></canvas></div>
    </div>
</div>

<script>
const labels = <?= json_encode(array_column($chartLabels ? $chartLabels : array_map(fn($d)=>['label'=>date('m/d')], range(0,6)), 'label') ?: ['-6','-5','-4','-3','-2','-1','امروز'])) ?>;
<?php
// fallback if chartLabels not defined - use from $chartLabels var if exists else generate
if (isset($chartLabels)) {
    echo "const growthLabels = " . json_encode(array_column($chartLabels, 'label')) . ";\n";
    echo "const growthUsers = " . json_encode(array_column($chartLabels, 'users')) . ";\n";
    echo "const growthSecret = " . json_encode(array_column($chartLabels, 'secret') ?? []) . ";\n";
} else {
    // use variables from dashboard
    echo "const growthLabels = " . json_encode($chartLabels ?? ['-6','-5','-4','-3','-2','-1','امروز']) . ";\n";
    echo "const growthUsers = " . json_encode($chartUsers ?? [0,0,0,0,0,0,0]) . ";\n";
    echo "const growthSecret = " . json_encode($chartSecret ?? [0,0,0,0,0,0,0]) . ";\n";
}
?>
// If variables are defined above, use them else fallback
const finalLabels = typeof growthLabels !== 'undefined' ? growthLabels : labels;
const finalUsers = typeof growthUsers !== 'undefined' ? growthUsers : <?= json_encode($chartUsers ?? []) ?>;
const finalSecret = typeof growthSecret !== 'undefined' && growthSecret.length ? growthSecret : <?= json_encode($chartSecret ?? []) ?>;

// Growth Chart
new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: finalLabels,
        datasets: [{
            label: 'کاربر جدید',
            data: finalUsers,
            borderColor: '#e10600',
            backgroundColor: 'rgba(225,6,0,0.1)',
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

// Secret Chart
new Chart(document.getElementById('secretChart'), {
    type: 'bar',
    data: {
        labels: finalLabels,
        datasets: [{
            label: 'چت مخفیانه',
            data: finalSecret,
            backgroundColor: 'rgba(225,6,0,0.6)',
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
</script>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">🆕</span> آخرین اعضا <span class="card-subtitle">8 نفر اخیر</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>نام</th><th>شناسه</th><th>شهر</th><th>تاریخ</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($recentUsers)): ?><tr><td colspan="5" class="empty-cell">عضوی نیست ♠️</td></tr>
                <?php else: foreach ($recentUsers as $u): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="width:32px;height:32px;background:linear-gradient(135deg,var(--red),var(--red-dark));border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px"><?= mb_substr(h($u['name'] ?? $u['first_name'] ?? 'U'),0,1) ?></div>
                                <div><strong><?= h($u['name'] ?? $u['first_name']) ?></strong><div style="font-size:11px;color:var(--text3)"><?= h($u['gender'] === 'male' ? 'آقا' : ($u['gender']==='female'?'خانم':'-')) ?> <?= (int)($u['age'] ?? 0) ?> ساله</div></div>
                            </div>
                        </td>
                        <td><code><?= h($u['bale_id']) ?></code></td>
                        <td><?= h($u['city'] ?? '—') ?></td>
                        <td><span class="text-muted"><?= formatRelativeTime($u['created_at']) ?></span></td>
                        <td><a href="index.php?page=user_detail&uid=<?= (int)$u['id'] ?>" class="btn btn-xs btn-ghost">پرونده</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">🚨</span> گزارشات امنیتی <span class="card-subtitle"><?= (int)$stats['pending_reports'] ?> در انتظار</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>گزارش‌دهنده</th><th>متهم</th><th>دلیل</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php if (empty($recentReports)): ?><tr><td colspan="4" class="empty-cell">گزارشی نیست</td></tr>
                <?php else: foreach ($recentReports as $r): ?>
                    <tr>
                        <td><?= h($r['reporter_name'] ?? '—') ?></td>
                        <td><?= h($r['reported_name'] ?? '—') ?></td>
                        <td><span style="font-size:12px"><?= h(mb_substr($r['reason'],0,35)) ?>...</span></td>
                        <td><?php if ($r['status']==='pending'): ?><span class="badge badge-pending">در انتظار</span><?php elseif ($r['status']==='resolved'): ?><span class="badge badge-active">حل شده</span><?php else: ?><span class="badge badge-blocked"><?= h($r['status']) ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($stats['pending_reports'] > 0): ?><div style="margin-top:16px"><a href="index.php?page=reports" class="btn btn-primary btn-sm">بررسی <?= (int)$stats['pending_reports'] ?> گزارش 🚨</a></div><?php endif; ?>
    </div>
</div>

<?php if (!empty($topReferrers)): ?>
<div class="card">
    <div class="card-title"><span class="icon">👥</span> برترین دعوت‌کنندگان</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>کاربر</th><th>تعداد دعوت</th><th>شناسه بله</th></tr></thead>
            <tbody>
            <?php foreach ($topReferrers as $tr): ?>
                <tr><td><?= h($tr['name'] ?? '—') ?></td><td><span class="badge badge-vip"><?= (int)$tr['cnt'] ?> نفر</span></td><td><code><?= h($tr['bale_id'] ?? '') ?></code></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title"><span class="icon">⚡</span> عملیات سریع خانواده</div>
    <div class="quick-actions">
        <a href="index.php?page=users" class="quick-btn">🎩 اعضا (<?= number_format($stats['total_users']) ?>)</a>
        <a href="index.php?page=secret" class="quick-btn">🕶 مخفیانه (<?= (int)$stats['secret_active'] ?> فعال)</a>
        <a href="index.php?page=coins" class="quick-btn">🪙 خزانه (<?= number_format($stats['total_coins']) ?>)</a>
        <a href="index.php?page=broadcast" class="quick-btn">📡 پیام همگانی</a>
        <a href="index.php?page=analytics" class="quick-btn">📈 تحلیل</a>
        <a href="index.php?page=referrals" class="quick-btn">👥 دعوت‌ها</a>
        <a href="index.php?page=vip" class="quick-btn">💎 VIP (<?= (int)$stats['vip_users'] ?>)</a>
        <a href="index.php?page=logs" class="quick-btn">📜 لاگ‌ها</a>
    </div>
</div>
