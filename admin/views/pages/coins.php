<?php if (!tableExists('coins')): ?>
<div class="alert alert-danger">🪙 جدول سکه نصب نشده. <code>install.php</code> را اجرا کن.</div>
<?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="stat-card gold"><div class="stat-icon">🪙</div><div class="stat-value"><?= number_format($totalCoins) ?></div><div class="stat-label">کل سکه در گردش - خزانه مرکزی</div></div>
    <div class="stat-card"><div class="stat-icon">👤</div><div class="stat-value"><?= number_format($coinHolders) ?></div><div class="stat-label">دارنده سکه - اعضای ثروتمند</div></div>
    <div class="stat-card green"><div class="stat-icon">📜</div><div class="stat-value"><?= number_format($totalTransactions) ?></div><div class="stat-label">کل تراکنش‌ها - گردش مالی</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">🏆</span> ثروتمندترین اعضای خانواده - تاپ 15</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>رتبه</th><th>نام</th><th>بله</th><th>سکه</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php if (empty($topUsers)): ?><tr><td colspan="5" class="empty-cell">داده‌ای نیست ♠️</td></tr>
                <?php else: foreach ($topUsers as $i => $u): $rank = $i+1; ?>
                    <tr style="<?= $rank<=3 ? 'background:linear-gradient(90deg, rgba(201,162,39,0.06), transparent)' : '' ?>">
                        <td><?php if ($rank==1) echo '👑'; elseif ($rank==2) echo '🥈'; elseif ($rank==3) echo '🥉'; else echo $rank; ?></td>
                        <td><strong><?= h($u['name'] ?? '—') ?></strong> <?= $rank==1 ? '👑 پدرخوانده' : '' ?></td>
                        <td><code style="font-size:11px"><?= h($u['bale_id']) ?></code></td>
                        <td><span class="coin-inline">🪙 <?= number_format($u['balance']) ?></span></td>
                        <td><a href="index.php?page=user_detail&uid=<?= (int)$u['user_id'] ?>" class="btn btn-xs btn-gold">💰 مدیریت</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">⚡</span> عملیات خزانه - واریز سریع</div>
        <p class="card-desc">♠️ خزانه‌دار خانواده: اینجا می‌توانی به اعضا سکه واریز یا کسر کنی. تمام تراکنش‌ها لاگ می‌شود.</p>
        <form method="GET" class="search-bar">
            <input type="hidden" name="page" value="user_detail">
            <input type="number" name="uid" placeholder="شناسه داخلی عضو (ID) مثلا 1" required min="1">
            <button type="submit" class="btn btn-primary btn-sm">🎩 رفتن به پرونده</button>
        </form>
        <hr class="divider">
        <div class="card-title" style="margin-top:0"><span class="icon">🔍</span> جستجوی بله آیدی</div>
        <form method="GET" class="search-bar">
            <input type="hidden" name="page" value="users">
            <input type="text" name="q" placeholder="مثلا 123456789">
            <button type="submit" class="btn btn-ghost btn-sm">جستجو</button>
        </form>
        <hr class="divider">
        <h4 style="font-size:13px;margin-bottom:10px">💡 نرخ‌های پیشنهادی مافیا:</h4>
        <div class="info-grid">
            <div class="info-item"><div class="lbl">پاداش عضو جدید</div><div class="val">10 سکه</div></div>
            <div class="info-item"><div class="lbl">جایزه روزانه</div><div class="val">5 سکه</div></div>
            <div class="info-item"><div class="lbl">هر دعوت</div><div class="val">20 سکه</div></div>
            <div class="info-item"><div class="lbl">VIP ماهانه</div><div class="val">100 سکه</div></div>
        </div>
    </div>
</div>

<?php if (!empty($recentTransactions)): ?>
<div class="card">
    <div class="card-title"><span class="icon">📜</span> دفتر حسابداری - آخرین تراکنش‌ها</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>عضو</th><th>نوع</th><th>مقدار</th><th>توضیح</th><th>تاریخ</th></tr></thead>
            <tbody>
                <?php foreach ($recentTransactions as $tx): ?>
                <tr>
                    <td><strong><?= h($tx['name'] ?? '—') ?></strong><br><code style="font-size:11px"><?= h($tx['bale_id'] ?? '') ?></code></td>
                    <td><?php if ($tx['type']==='add'): ?><span class="badge badge-active">➕ واریز</span><?php elseif ($tx['type']==='deduct'): ?><span class="badge badge-blocked">➖ برداشت</span><?php else: ?><span class="badge badge-vip"><?= h($tx['type']) ?></span><?php endif; ?></td>
                    <td><strong style="color:<?= $tx['type']==='add' ? 'var(--success)' : 'var(--red)' ?>"><?= $tx['type']==='deduct' ? '-' : '+' ?><?= number_format(abs((int)$tx['amount'])) ?></strong></td>
                    <td><span style="font-size:12px"><?= h($tx['description'] ?? '—') ?></span></td>
                    <td><span class="text-muted"><?= formatRelativeTime($tx['created_at']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
