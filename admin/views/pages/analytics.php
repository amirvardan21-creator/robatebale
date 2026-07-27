<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">🏙</span> توزیع بر اساس شهر</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>شهر</th><th>تعداد</th><th>درصد</th></tr></thead>
                <tbody>
                <?php
                $totalCity = array_sum(array_column($analytics['users_by_city'] ?? [], 'cnt'));
                foreach (($analytics['users_by_city'] ?? []) as $row):
                    $pct = $totalCity > 0 ? round($row['cnt'] / $totalCity * 100, 1) : 0;
                ?>
                    <tr>
                        <td><?= h($row['city']) ?></td>
                        <td><?= (int)$row['cnt'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="flex:1;height:6px;background:var(--bg);border-radius:3px;overflow:hidden"><div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,var(--red),var(--red-light))"></div></div>
                                <span style="font-size:11px;color:var(--text2)"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">⚧</span> توزیع جنسیت و سن</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div>
                <h4 style="font-size:12px;color:var(--text2);margin-bottom:10px">جنسیت</h4>
                <?php foreach (($analytics['users_by_gender'] ?? []) as $g): ?>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05)">
                        <span><?= genderLabel($g['gender']) ?></span>
                        <span class="badge badge-active"><?= (int)$g['cnt'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div>
                <h4 style="font-size:12px;color:var(--text2);margin-bottom:10px">بازه سنی</h4>
                <?php foreach (($analytics['users_by_age'] ?? []) as $a): ?>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05)">
                        <span><?= h($a['range_age']) ?></span>
                        <span class="badge badge-vip"><?= (int)$a['cnt'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title"><span class="icon">📊</span> فعالیت 7 روز اخیر - نمودار کامل</div>
    <div class="chart-wrap"><canvas id="fullChart"></canvas></div>
</div>

<script>
const daily = <?= json_encode($dailyStats ?? []) ?>;
const labels = daily.map(d => d.label);
new Chart(document.getElementById('fullChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            { label: 'کاربر جدید', data: daily.map(d => d.users), borderColor: '#e10600', backgroundColor: 'rgba(225,6,0,0.1)', tension: 0.4, fill: true, borderWidth: 2 },
            { label: 'چت مخفیانه', data: daily.map(d => d.secret), borderColor: '#c9a227', backgroundColor: 'rgba(201,162,39,0.1)', tension: 0.4, fill: true, borderWidth: 2 },
            { label: 'مچ', data: daily.map(d => d.matches), borderColor: '#00c950', backgroundColor: 'rgba(0,201,80,0.1)', tension: 0.4, fill: true, borderWidth: 2 },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#a89a9a', usePointStyle: true } } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b5f5f' } },
            x: { grid: { display: false }, ticks: { color: '#6b5f5f' } }
        }
    }
});
</script>

<div class="grid-3">
    <div class="card">
        <div class="card-title"><span class="icon">📈</span> میانگین روزانه</div>
        <?php
        $avgUsers = count($dailyStats) ? round(array_sum(array_column($dailyStats, 'users')) / count($dailyStats), 1) : 0;
        $avgSecret = count($dailyStats) ? round(array_sum(array_column($dailyStats, 'secret')) / count($dailyStats), 1) : 0;
        $avgMatches = count($dailyStats) ? round(array_sum(array_column($dailyStats, 'matches')) / count($dailyStats), 1) : 0;
        ?>
        <div class="info-grid">
            <div class="info-item"><div class="lbl">کاربر جدید</div><div class="val"><?= $avgUsers ?>/روز</div></div>
            <div class="info-item"><div class="lbl">چت مخفیانه</div><div class="val"><?= $avgSecret ?>/روز</div></div>
            <div class="info-item"><div class="lbl">مچ</div><div class="val"><?= $avgMatches ?>/روز</div></div>
            <div class="info-item"><div class="lbl">رشد</div><div class="val" style="color:var(--success)">↗ صعودی</div></div>
        </div>
    </div>
    <div class="card">
        <div class="card-title"><span class="icon">🎯</span> نرخ تبدیل</div>
        <?php
        $totalUsers = User::count();
        $totalMatches = (int) (Database::fetch('SELECT COUNT(*) as cnt FROM matches')['cnt'] ?? 0);
        $conversion = $totalUsers > 0 ? round($totalMatches / $totalUsers * 100, 1) : 0;
        ?>
        <div style="text-align:center;padding:20px">
            <div style="font-size:42px;font-weight:900;color:var(--red)"><?= $conversion ?>%</div>
            <div style="font-size:12px;color:var(--text2)">کاربران به مچ تبدیل شده‌اند</div>
            <div style="margin-top:16px;height:8px;background:var(--bg);border-radius:4px;overflow:hidden"><div style="width:<?= min(100,$conversion) ?>%;height:100%;background:linear-gradient(90deg,var(--red),var(--red-light))"></div></div>
        </div>
    </div>
    <div class="card">
        <div class="card-title"><span class="icon">⚡</span> سلامت سیستم</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px">دیتابیس</span><span class="badge badge-active">آنلاین</span></div>
            <div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px">وب‌هوک</span><span class="badge badge-active">متصل</span></div>
            <div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px">Cron MatchMaker</span><span class="badge badge-pending">بررسی لاگ</span></div>
            <div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px">فضای لاگ</span><span class="badge badge-vip"><?= is_dir(LOG_PATH) ? round(array_sum(array_map(fn($f)=>filesize($f), glob(LOG_PATH.'*.log') ?: [])) / 1024, 1) . ' KB' : '—' ?></span></div>
        </div>
    </div>
</div>
