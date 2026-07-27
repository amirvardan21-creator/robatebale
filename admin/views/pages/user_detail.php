<?php if (!$userDetail): ?>
    <div class="alert alert-danger">♠️ پرونده یافت نشد - این عضو وجود ندارد یا حذف شده.</div>
    <a href="index.php?page=users" class="btn btn-ghost">← بازگشت به خانواده</a>
<?php else: ?>
<?php
    $u = $userDetail;
    $initial = mb_substr($u['name'] ?? $u['first_name'] ?? 'U', 0, 1);
    $isVip = Vip::isVip($u['id']);
    $vipInfo = $isVip ? Vip::getInfo($u['id']) : null;
    $coins = safeCoinBalance($u['id']);
    $transactions = safeCoinTransactions($u['id']);
    $isOnline = isUserOnline($u['last_seen_at'] ?? null);
?>

<a href="index.php?page=users" class="btn btn-ghost btn-sm" style="margin-bottom:20px">← بازگشت به لیست اعضا</a>

<div class="user-header">
    <div class="user-avatar-lg"><?= h($initial) ?></div>
    <div class="user-meta">
        <h2><?= h($u['name'] ?? $u['first_name']) ?> <?= $isVip ? '👑' : '' ?> <?= $isOnline ? '<span class="badge badge-online" style="font-size:11px">🟢 آنلاین</span>' : '' ?></h2>
        <p>
            🆔 <code><?= h($u['bale_id']) ?></code>
            <?php if ($u['username']): ?> | @<?= h($u['username']) ?><?php endif; ?>
            | ثبت: <?= formatDate($u['created_at']) ?>
            <?php if ($isOnline): ?> | <span style="color:var(--success)">آنلاین الان</span><?php else: ?> | آخرین بازدید: <?= formatRelativeTime($u['last_seen_at']) ?><?php endif; ?>
        </p>
        <div class="user-badges">
            <?php if ($u['is_blocked']): ?><span class="badge badge-blocked">🚫 مسدود - اخراج از خانواده</span>
            <?php else: ?><span class="badge badge-active">✅ عضو فعال خانواده</span><?php endif; ?>
            <?php if ($isVip): ?><span class="badge badge-vip">⭐ VIP <?= h($vipInfo['plan'] ?? '') ?> تا <?= formatDate($vipInfo['expires_at'] ?? null) ?></span><?php endif; ?>
            <?php if (!$u['profile_id']): ?><span class="badge badge-pending">⚠️ پرونده ناقص - بدون پروفایل</span><?php endif; ?>
            <?php if ($u['is_visible'] ?? true): ?><span class="badge badge-active">👁 قابل مشاهده</span><?php else: ?><span class="badge badge-pending">🙈 مخفی</span><?php endif; ?>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card gold"><div class="stat-icon">🪙</div><div class="stat-value"><?= number_format($coins) ?></div><div class="stat-label">سکه</div></div>
    <div class="stat-card"><div class="stat-icon">💘</div><div class="stat-value"><?= (int)($u['match_count'] ?? 0) ?></div><div class="stat-label">مچ</div></div>
    <div class="stat-card blue"><div class="stat-icon">👁</div><div class="stat-value"><?= (int)($u['total_views'] ?? 0) ?></div><div class="stat-label">بازدید پروفایل</div></div>
    <div class="stat-card"><div class="stat-icon">❤️</div><div class="stat-value"><?= (int)($u['total_likes'] ?? 0) ?></div><div class="stat-label">لایک دریافتی</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">📋</span> پرونده شناسایی - مشخصات</div>
        <div class="info-grid">
            <div class="info-item"><div class="lbl">نام کامل</div><div class="val"><?= h($u['name'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">سن</div><div class="val"><?= h($u['age'] ?? '—') ?> ساله</div></div>
            <div class="info-item"><div class="lbl">جنسیت</div><div class="val"><?= genderLabel($u['gender'] ?? null) ?></div></div>
            <div class="info-item"><div class="lbl">شهر / استان</div><div class="val"><?= h($u['city'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">علایق</div><div class="val"><?= h($u['interests'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">به دنبال</div><div class="val"><?= h($u['looking_for'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">وضعیت نمایش</div><div class="val"><?= ($u['is_visible'] ?? 1) ? '👁 نمایش' : '🙈 مخفی' ?></div></div>
            <div class="info-item"><div class="lbl">تاریخ عضویت</div><div class="val"><?= formatDate($u['created_at']) ?></div></div>
        </div>
        <?php if (!empty($u['bio'])): ?><hr class="divider"><div class="info-item" style="grid-column:1/-1"><div class="lbl">📝 بیوگرافی / اعترافات</div><div class="val" style="font-weight:400;margin-top:8px;line-height:1.9;background:var(--bg);padding:12px;border-radius:8px;border-left:3px solid var(--red)"><?= nl2br(h($u['bio'])) ?></div></div><?php endif; ?>
    </div>

    <div>
        <div class="coin-balance">
            <div class="amount">🪙 <?= number_format($coins) ?></div>
            <div class="label">موجودی خزانه شخصی</div>
            <div style="margin-top:10px;font-size:11px;color:var(--text3)">سطح: <?= User::getLevel($u['id'])['title'] ?? 'تازه‌وارد' ?> <?= User::getLevel($u['id'])['icon'] ?? '' ?> | استریک: <?= $u['daily_streak'] ?? 0 ?> روز</div>
        </div>

        <?php if ($isVip): ?>
        <div class="vip-box">
            <div class="crown">👑</div>
            <h3>عضو اشراف - VIP فعال</h3>
            <p>انقضا: <?= formatDate($vipInfo['expires_at'] ?? null) ?></p>
            <p>پلن: <?= h($vipInfo['plan'] ?? 'monthly') ?> | شروع: <?= formatDate($vipInfo['started_at'] ?? null) ?></p>
        </div>
        <?php else: ?>
        <div class="card" style="text-align:center;padding:20px">
            <div style="font-size:28px">💎</div>
            <h3 style="font-size:14px;margin-top:8px">عضو عادی</h3>
            <p style="font-size:12px;color:var(--text2);margin-top:4px">این عضو VIP نیست - با VIP اولویت می‌گیرد</p>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title"><span class="icon">🪙</span> مدیریت خزانه</div>
            <form method="POST" class="action-grid">
                <?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <div class="form-group"><label>➕ افزودن سکه</label><input type="number" name="amount" min="1" placeholder="مثلا 50" required></div>
                <div class="form-group"><label>دلیل</label><input type="text" name="desc" placeholder="جایزه از طرف پدرخوانده"></div>
                <button type="submit" name="action" value="add_coins" class="btn btn-gold btn-sm">➕ واریز به خزانه</button>
            </form>
            <hr class="divider">
            <form method="POST" class="action-grid">
                <?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <div class="form-group"><label>➖ کسر سکه</label><input type="number" name="amount" min="1" placeholder="مقدار" required></div>
                <div class="form-group"><label>دلیل</label><input type="text" name="desc" placeholder="جریمه"></div>
                <button type="submit" name="action" value="deduct_coins" class="btn btn-danger btn-sm">➖ کسر</button>
            </form>
            <hr class="divider">
            <form method="POST" style="display:flex;gap:10px;align-items:flex-end">
                <?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <div class="form-group" style="flex:1;margin:0"><label>تنظیم مستقیم</label><input type="number" name="amount" min="0" value="<?= $coins ?>" required></div>
                <button type="submit" name="action" value="set_coins" class="btn btn-ghost btn-sm">تنظیم</button>
            </form>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">⭐</span> اشرافیت - VIP</div>
        <?php if ($isVip): ?>
            <p style="margin-bottom:16px;color:var(--text2);font-size:13px">این عضو هم‌اکنون از اشراف خانواده است.</p>
            <form method="POST" onsubmit="return confirm('VIP لغو شود؟')"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button type="submit" name="action" value="revoke_vip" class="btn btn-danger btn-sm btn-block">❌ خلع اشرافیت - لغو VIP</button></form>
        <?php else: ?>
            <form method="POST">
                <?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <div class="form-group"><label>اعطای مقام اشراف (پلن)</label><select name="plan"><option value="daily">روزانه (1 روز) - تست</option><option value="weekly">هفتگی (7 روز)</option><option value="monthly" selected>ماهانه (30 روز) - محبوب</option><option value="quarterly">فصلی (90 روز)</option><option value="yearly">سالانه (365 روز)</option></select></div>
                <button type="submit" name="action" value="grant_vip" class="btn btn-gold btn-block">👑 اعطای تاج VIP</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">🛡</span> عدالت مافیایی - کنترل</div>
        <div class="action-grid">
            <?php if ($u['is_blocked']): ?>
                <form method="POST"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button type="submit" name="action" value="unblock_user" class="btn btn-success btn-sm btn-block">✅ بخشش - رفع مسدودیت</button></form>
            <?php else: ?>
                <form method="POST" onsubmit="return confirm('این عضو اخراج و مسدود شود؟')"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button type="submit" name="action" value="block_user" class="btn btn-danger btn-sm btn-block">🚫 اخراج از خانواده - مسدود</button></form>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('⚠️ تمام اطلاعات این عضو برای همیشه حذف شود؟')"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button type="submit" name="action" value="delete_user" class="btn btn-ghost btn-sm btn-block">🗑 حذف پرونده - اعدام</button></form>
        </div>
        <div style="margin-top:16px;padding:12px;background:rgba(225,6,0,0.05);border-radius:8px;border:1px solid var(--border-red)"><p style="font-size:12px;color:var(--text2)">💀 <strong>قانون مافیا:</strong> مسدود کردن = اخراج موقت، حذف = پاکسازی کامل پرونده، قابل بازگشت نیست.</p></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">💬</span> ارسال پیام مستقیم - نامه از پدرخوانده</div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <div class="form-group"><label>متن پیام (در بله ارسال می‌شود)</label><textarea name="message" placeholder="مثال: سلام، شما به دلیل فعالیت خوب 50 سکه جایزه گرفتید..." required></textarea></div>
            <button type="submit" name="action" value="send_dm" class="btn btn-primary">📨 ارسال نامه ✉️</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">📊</span> فعالیت‌های اخیر</div>
        <div class="info-grid">
            <div class="info-item"><div class="lbl">آخرین بازدید</div><div class="val"><?= $u['last_seen_at'] ? formatRelativeTime($u['last_seen_at']) : 'نامشخص' ?></div></div>
            <div class="info-item"><div class="lbl">وضعیت فعلی</div><div class="val"><?= h($u['current_status'] ?? 'idle') ?></div></div>
            <div class="info-item"><div class="lbl">استپ فعلی</div><div class="val"><code><?= h($u['step'] ?? 'idle') ?></code></div></div>
            <div class="info-item"><div class="lbl">ارجاع‌دهنده</div><div class="val"><?= $u['referred_by'] ? 'کاربر #' . (int)$u['referred_by'] : 'مستقیم (بدون دعوت)' ?></div></div>
        </div>
        <?php if (!empty($userSecretRooms)): ?>
            <hr class="divider">
            <h4 style="font-size:12px;color:var(--text2);margin-bottom:10px">🕶 آخرین چت‌های مخفیانه</h4>
            <?php foreach ($userSecretRooms as $room): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px"><span>اتاق #<?= (int)$room['id'] ?> - <?= h($room['status']) ?></span><span class="text-muted"><?= formatDate($room['created_at']) ?></span></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($userTransactions)): ?>
<div class="card">
    <div class="card-title"><span class="icon">📜</span> تاریخچه خزانه - تراکنش‌های سکه</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>نوع</th><th>مقدار</th><th>توضیح</th><th>تاریخ</th></tr></thead>
            <tbody>
                <?php foreach ($userTransactions as $tx): ?>
                <tr>
                    <td><?php if (in_array($tx['type'], ['add','gift_received'])): ?><span class="badge badge-active">➕ <?= h($tx['type']) ?></span><?php else: ?><span class="badge badge-blocked">➖ <?= h($tx['type']) ?></span><?php endif; ?></td>
                    <td><strong style="color:<?= in_array($tx['type'], ['add']) ? 'var(--success)' : 'var(--red)' ?>"><?= $tx['type']==='deduct' ? '-' : '+' ?><?= number_format(abs((int)$tx['amount'])) ?></strong></td>
                    <td><?= h($tx['description'] ?? '—') ?></td>
                    <td><span class="text-muted"><?= formatDate($tx['created_at']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($userReferrals)): ?>
<div class="card">
    <div class="card-title"><span class="icon">👥</span> شبکه دعوت - زیرمجموعه‌های این عضو (<?= count($userReferrals) ?> نفر)</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>دعوت‌شده</th><th>پاداش</th><th>تاریخ</th></tr></thead>
            <tbody>
                <?php foreach ($userReferrals as $ref): ?>
                <tr><td><?= h($ref['name'] ?? 'کاربر '.$ref['referred_id']) ?></td><td><span class="coin-inline"><?= (int)$ref['bonus'] ?> سکه</span></td><td><?= formatDate($ref['created_at']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
