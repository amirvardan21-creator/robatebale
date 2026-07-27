<div class="stats-grid">
    <div class="stat-card gold"><div class="stat-icon">👥</div><div class="stat-value"><?= number_format($totalReferrals) ?></div><div class="stat-label">کل دعوت‌ها</div></div>
    <div class="stat-card"><div class="stat-icon">🏆</div><div class="stat-value"><?= isset($topReferrers[0]) ? number_format($topReferrers[0]['cnt']) : 0 ?></div><div class="stat-label">رکورد دعوت</div></div>
    <div class="stat-card green"><div class="stat-icon">🪙</div><div class="stat-value"><?= number_format(array_sum(array_column($recentReferrals, 'bonus'))) ?></div><div class="stat-label">سکه پاداش دعوت</div></div>
    <div class="stat-card"><div class="stat-icon">📈</div><div class="stat-value"><?= count($topReferrers) ?></div><div class="stat-label">دعوت‌کننده فعال</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">👑</span> برترین دعوت‌کنندگان - سرداران خانواده</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>رتبه</th><th>نام</th><th>بله آیدی</th><th>تعداد دعوت</th><th>سکه دریافتی</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($topReferrers)): ?><tr><td colspan="6" class="empty-cell">هنوز دعوتی ثبت نشده</td></tr>
                <?php else: $rank=1; foreach ($topReferrers as $tr): ?>
                    <tr style="<?= $rank===1 ? 'background:linear-gradient(90deg, rgba(201,162,39,0.08), transparent)' : '' ?>">
                        <td><?php if ($rank===1) echo '🥇'; elseif ($rank===2) echo '🥈'; elseif ($rank===3) echo '🥉'; else echo $rank; ?></td>
                        <td><strong><?= h($tr['name'] ?? '—') ?></strong> <?= $rank===1 ? '👑' : '' ?></td>
                        <td><code><?= h($tr['bale_id'] ?? '') ?></code></td>
                        <td><span class="badge badge-vip"><?= (int)$tr['cnt'] ?> نفر</span></td>
                        <td><span class="coin-inline"><?= (int)($tr['total_bonus'] ?? 0) ?> سکه</span></td>
                        <td><a href="index.php?page=user_detail&uid=<?= (int)$tr['id'] ?>" class="btn btn-xs btn-ghost">پرونده</a></td>
                    </tr>
                <?php $rank++; endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">🎁</span> جوایز دعوت</div>
        <div style="padding:10px 0">
            <div class="info-grid">
                <div class="info-item"><div class="lbl">پاداش هر دعوت (دعوت‌کننده)</div><div class="val"><?= REFERRAL_BONUS ?> سکه</div></div>
                <div class="info-item"><div class="lbl">پاداش دوست دعوت‌شده</div><div class="val"><?= REFERRAL_BONUS ?> سکه</div></div>
                <div class="info-item"><div class="lbl">لینک دعوت</div><div class="val" style="font-size:12px">https://ble.ir/BOT?start=ref_ID</div></div>
                <div class="info-item"><div class="lbl">وضعیت سیستم</div><div class="val" style="color:var(--success)">فعال ✅</div></div>
            </div>
            <div class="divider"></div>
            <p style="font-size:13px;color:var(--text2);line-height:1.8">
                ♠️ <strong>قدرت خانواده در اتحاد است:</strong><br>
                هر کاربر با دعوت دوستانش هم سکه می‌گیرد هم خانواده را بزرگ‌تر می‌کند.<br>
                برترین دعوت‌کنندگان را تشویق کن تا شبکه‌ات رشد کند.
            </p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title"><span class="icon">🆕</span> آخرین دعوت‌ها - 20 مورد اخیر</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>دعوت‌کننده</th><th>دعوت‌شده</th><th>پاداش</th><th>تاریخ</th></tr></thead>
            <tbody>
            <?php if (empty($recentReferrals)): ?><tr><td colspan="4" class="empty-cell">دعوتی ثبت نشده</td></tr>
            <?php else: foreach ($recentReferrals as $r): ?>
                <tr>
                    <td><?= h($r['referrer_name'] ?? 'کاربر '.$r['referrer_id']) ?> <small style="color:var(--text3)">#<?= (int)$r['referrer_id'] ?></small></td>
                    <td><?= h($r['referred_name'] ?? 'کاربر '.$r['referred_id']) ?> <small style="color:var(--text3)">#<?= (int)$r['referred_id'] ?></small></td>
                    <td><span class="coin-inline"><?= (int)$r['bonus'] ?> سکه</span></td>
                    <td><?= formatRelativeTime($r['created_at']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
