<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon">🟢</div><div class="stat-value"><?= (int)($secretStats['active_rooms'] ?? 0) ?></div><div class="stat-label">چت فعال الان</div></div>
    <div class="stat-card blue"><div class="stat-icon">📅</div><div class="stat-value"><?= (int)($secretStats['today_rooms'] ?? 0) ?></div><div class="stat-label">چت امروز</div></div>
    <div class="stat-card gold"><div class="stat-icon">📊</div><div class="stat-value"><?= (int)($secretStats['total_rooms'] ?? 0) ?></div><div class="stat-label">کل چت‌ها</div></div>
    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value"><?= (int)($secretStats['queue'] ?? 0) ?></div><div class="stat-label">در صف انتظار</div></div>
</div>

<div class="card">
    <div class="card-title"><span class="icon">⚙️</span> عملیات فوری
        <span class="card-subtitle">خانواده مافیا همیشه آماده</span>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="page" value="secret"><input type="hidden" name="action" value="clear_queue"><button class="btn btn-ghost btn-sm">🗑 پاکسازی صف</button></form>
        <form method="POST" style="display:inline" onsubmit="return confirm('همه چت‌های فعال بسته شوند؟')"><?= csrfField() ?><input type="hidden" name="page" value="secret"><input type="hidden" name="action" value="end_all_rooms"><button class="btn btn-danger btn-sm">❌ بستن همه چت‌ها</button></form>
        <a href="index.php?page=logs" class="btn btn-ghost btn-sm">📜 لاگ cron</a>
        <a href="../cli/cli_match_maker.php" target="_blank" class="btn btn-ghost btn-sm" onclick="return false" style="opacity:0.5">⚠️ اجرای دستی (CLI)</a>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">💬</span> چت‌های فعال (<?= count($activeRooms) ?>)</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>اتاق</th><th>کاربر 1</th><th>کاربر 2</th><th>شروع</th><th>نوع جستجو</th></tr></thead>
                <tbody>
                <?php if (empty($activeRooms)): ?><tr><td colspan="5" class="empty-cell">چت فعالی نیست - خانواده در آرامش ♠️</td></tr>
                <?php else: foreach ($activeRooms as $r): ?>
                    <tr>
                        <td><code>#<?= (int)$r['id'] ?></code></td>
                        <td><?= h($r['user1_name'] ?? 'کاربر '.$r['user1_id']) ?> <small style="color:var(--text3)">(<?= (int)$r['user1_id'] ?>)</small></td>
                        <td><?= h($r['user2_name'] ?? 'کاربر '.$r['user2_id']) ?> <small style="color:var(--text3)">(<?= (int)$r['user2_id'] ?>)</small></td>
                        <td><span class="text-muted"><?= formatRelativeTime($r['created_at']) ?></span></td>
                        <td><span class="badge badge-pending"><?= h($r['search_type'] ?? '-') ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">⏳</span> صف انتظار (<?= count($queueUsers) ?> نفر)</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>کاربر</th><th>شهر</th><th>جستجو</th><th>VIP</th><th>از کی</th></tr></thead>
                <tbody>
                <?php if (empty($queueUsers)): ?><tr><td colspan="5" class="empty-cell">صف خالیه</td></tr>
                <?php else: foreach ($queueUsers as $q): ?>
                    <tr>
                        <td>
                            <strong><?= h($q['name'] ?? '—') ?></strong>
                            <div style="font-size:11px;color:var(--text3)"><code><?= h($q['bale_id'] ?? '') ?></code> <?= isUserOnline($q['last_seen_at'] ?? null) ? '<span class="badge badge-online" style="font-size:10px">آنلاین</span>' : '<span class="badge badge-offline" style="font-size:10px">آفلاین</span>' ?></div>
                        </td>
                        <td><?= h($q['city'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge-pending"><?= h($q['search_preference']) ?></span>
                            <?php if ($q['gender_preference']): ?><span class="badge badge-active"><?= $q['gender_preference']=='male'?'پسر':'دختر' ?></span><?php endif; ?>
                            <?php if ($q['province_preference']): ?><span style="font-size:11px"><?= h($q['province_preference']) ?></span><?php endif; ?>
                        </td>
                        <td><?= $q['vip_priority'] ? '<span class="badge badge-vip">VIP</span>' : '—' ?></td>
                        <td><span class="text-muted"><?= formatRelativeTime($q['queued_at']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;font-size:12px;color:var(--text3)">💡 VIPها اولویت دارند و سریع‌تر مچ می‌شوند. صف هر دقیقه توسط cron پردازش می‌شود.</div>
    </div>
</div>

<div class="card">
    <div class="card-title"><span class="icon">📜</span> تاریخچه چت‌ها</div>
    <?php
    $history = Database::fetchAll('SELECT r.*, p1.name as u1_name, p2.name as u2_name FROM secret_meeting_rooms r LEFT JOIN profiles p1 ON p1.user_id=r.user1_id LEFT JOIN profiles p2 ON p2.user_id=r.user2_id WHERE r.status!="active" ORDER BY r.created_at DESC LIMIT 20');
    ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>کاربر 1</th><th>کاربر 2</th><th>وضعیت</th><th>شروع</th><th>پایان</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><code>#<?= (int)$h['id'] ?></code></td>
                    <td><?= h($h['u1_name'] ?? $h['user1_id']) ?></td>
                    <td><?= h($h['u2_name'] ?? $h['user2_id']) ?></td>
                    <td>
                        <?php
                        $statusMap = ['ended_by_user1'=>'کاربر1 بست','ended_by_user2'=>'کاربر2 بست','aborted'=>'لغو','expired'=>'منقضی','ended_mutually'=>'توافقی'];
                        echo '<span class="badge badge-blocked">'.h($statusMap[$h['status']] ?? $h['status']).'</span>';
                        ?>
                    </td>
                    <td><?= formatDate($h['created_at']) ?></td>
                    <td><?= formatDate($h['ended_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
