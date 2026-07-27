<div class="stats-grid">
    <div class="stat-card gold"><div class="stat-icon">💎</div><div class="stat-value"><?= (int)($vipStats['active'] ?? 0) ?></div><div class="stat-label">VIP فعال</div></div>
    <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-value"><?= (int)($vipStats['today'] ?? 0) ?></div><div class="stat-label">خرید امروز</div></div>
    <div class="stat-card"><div class="stat-icon">⏰</div><div class="stat-value"><?= (int)($vipStats['expired'] ?? 0) ?></div><div class="stat-label">منقضی شده</div></div>
    <div class="stat-card green"><div class="stat-icon">💰</div><div class="stat-value"><?= (int)($vipStats['active'] ?? 0) * 100 ?></div><div class="stat-label">درآمد تخمینی (سکه)</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">👑</span> اعضای VIP فعال - اشراف خانواده</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>کاربر</th><th>پلن</th><th>شروع</th><th>انقضا</th><th>مانده</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($vipUsers)): ?><tr><td colspan="6" class="empty-cell">VIP فعالی نیست</td></tr>
                <?php else: foreach ($vipUsers as $v): 
                    $expires = strtotime($v['expires_at']);
                    $remaining = ceil(($expires - time()) / 86400);
                    $planLabel = ['daily'=>'روزانه','weekly'=>'هفتگی','monthly'=>'ماهانه','quarterly'=>'فصلی','yearly'=>'سالانه'][$v['plan']] ?? $v['plan'];
                ?>
                    <tr>
                        <td><strong><?= h($v['name'] ?? '—') ?></strong><div style="font-size:11px;color:var(--text3)"><?= h($v['city'] ?? '') ?> • <code><?= h($v['bale_id']) ?></code></div></td>
                        <td><span class="badge badge-vip"><?= h($planLabel) ?></span></td>
                        <td><span class="text-muted"><?= formatDate($v['started_at']) ?></span></td>
                        <td><?= formatDate($v['expires_at']) ?></td>
                        <td>
                            <?php if ($remaining <= 3): ?><span class="badge badge-blocked"><?= $remaining ?> روز</span>
                            <?php elseif ($remaining <= 7): ?><span class="badge badge-pending"><?= $remaining ?> روز</span>
                            <?php else: ?><span class="badge badge-active"><?= $remaining ?> روز</span><?php endif; ?>
                        </td>
                        <td><a href="index.php?page=user_detail&uid=<?= (int)$v['user_id'] ?>" class="btn btn-xs btn-ghost">پرونده</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">🎩</span> مدیریت VIP</div>
        <p style="font-size:13px;color:var(--text2);line-height:1.8;margin-bottom:16px">
            ♠️ اعضای VIP ستون‌های خانواده هستند. آن‌ها با سکه VIP می‌خرند و درآمد اصلی ربات از آن‌هاست.
        </p>
        <div class="info-grid">
            <?php foreach (Vip::getPlans() as $key=>$plan): ?>
                <div class="info-item" style="border-left-color: <?= !empty($plan['popular']) ? 'var(--gold)' : 'var(--border)' ?>">
                    <div class="lbl"><?= $plan['icon'] ?> <?= h($plan['title']) ?> <?= !empty($plan['popular']) ? '🔥 محبوب' : '' ?></div>
                    <div class="val"><?= (int)$plan['price_coins'] ?> سکه - <?= (int)$plan['days'] ?> روز <?= !empty($plan['discount']) ? '(' . h($plan['discount']) . ')' : '' ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="divider"></div>
        <h4 style="font-size:13px;margin-bottom:10px">اعطای دستی VIP</h4>
        <form method="POST" class="setting-row">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="vip">
            <input type="hidden" name="action" value="grant_vip">
            <div class="form-group" style="flex:1;margin:0"><label>آیدی کاربر</label><input type="number" name="user_id" placeholder="ID کاربر" required></div>
            <div class="form-group" style="flex:1;margin:0"><label>پلن</label><select name="plan"><option value="daily">روزانه</option><option value="weekly">هفتگی</option><option value="monthly" selected>ماهانه</option><option value="quarterly">فصلی</option><option value="yearly">سالانه</option></select></div>
            <button class="btn btn-gold">اعطا</button>
        </form>
    </div>
</div>
