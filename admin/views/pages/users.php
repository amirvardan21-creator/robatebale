<div class="card">
    <div class="card-title"><span class="icon">🔍</span> جستجو و فیلتر - خانواده مافیا</div>
    <form method="GET" class="search-bar">
        <input type="hidden" name="page" value="users">
        <input type="text" name="q" value="<?= h($searchQuery ?? '') ?>" placeholder="🎩 نام، شناسه بله، یوزرنیم، شهر...">
        <button type="submit" class="btn btn-primary">🔍 جستجو</button>
        <?php if (!empty($searchQuery)): ?><a href="index.php?page=users" class="btn btn-ghost">✕ پاک</a><?php endif; ?>
    </form>
    <div class="filter-tabs">
        <a href="index.php?page=users" class="filter-tab <?= ($filter ?? 'all')==='all' && empty($searchQuery) ? 'active' : '' ?>">همه (<?= number_format($totalUsers ?? 0) ?>)</a>
        <a href="index.php?page=users&filter=online" class="filter-tab <?= ($filter ?? '')==='online' ? 'active' : '' ?>">🟢 آنلاین</a>
        <a href="index.php?page=users&filter=vip" class="filter-tab <?= ($filter ?? '')==='vip' ? 'active' : '' ?>">⭐ VIP</a>
        <a href="index.php?page=users&filter=blocked" class="filter-tab <?= ($filter ?? '')==='blocked' ? 'active' : '' ?>">🚫 مسدود</a>
    </div>
</div>

<div class="card">
    <div class="card-title">
        <span class="icon">👥</span> لیست اعضا - <?= number_format($totalUsers) ?> نفر
        <span class="card-subtitle">صفحه <?= $currentPage ?> از <?= $totalPages ?></span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>عضو</th>
                    <th>شناسه بله</th>
                    <th>شهر/سن</th>
                    <th>سکه</th>
                    <th>وضعیت</th>
                    <th>آنلاین</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?><tr><td colspan="8" class="empty-cell">عضوی یافت نشد ♠️</td></tr>
            <?php else: foreach ($users as $u): ?>
                <tr style="<?= $u['is_blocked'] ? 'opacity:0.6' : '' ?>">
                    <td><span style="color:var(--text3);font-size:11px">#<?= (int)$u['id'] ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:36px;height:36px;background:linear-gradient(135deg,<?= $u['is_vip'] ? 'var(--gold), #8a6d0a' : 'var(--red), var(--red-dark)' ?>);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;box-shadow:0 0 10px <?= $u['is_vip'] ? 'rgba(201,162,39,0.3)' : 'var(--red-glow)' ?>"><?= mb_substr(h($u['name'] ?? $u['first_name'] ?? 'U'),0,1) ?></div>
                            <div>
                                <strong><?= h($u['name'] ?? $u['first_name']) ?> <?= $u['is_vip'] ? '⭐' : '' ?></strong>
                                <div style="font-size:11px;color:var(--text3)"><?= genderLabel($u['gender'] ?? null) ?> • <?= h($u['age'] ?? '—') ?> ساله</div>
                            </div>
                        </div>
                    </td>
                    <td><code style="font-size:11px"><?= h($u['bale_id']) ?></code></td>
                    <td><span style="font-size:12px"><?= h($u['city'] ?? '—') ?></span></td>
                    <td><span class="coin-inline">🪙 <?= number_format($u['coins'] ?? 0) ?></span></td>
                    <td>
                        <?php if ($u['is_blocked']): ?><span class="badge badge-blocked">🚫 مسدود</span>
                        <?php elseif ($u['is_active']): ?><span class="badge badge-active">✅ فعال</span>
                        <?php else: ?><span class="badge badge-pending">غیرفعال</span><?php endif; ?>
                        <?php if ($u['is_vip']): ?><div style="margin-top:4px"><span class="badge badge-vip">⭐ VIP</span></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['is_online'] ?? false): ?><span class="badge badge-online">🟢 آنلاین</span>
                        <?php else: ?><span class="text-muted" style="font-size:11px"><?= isset($u['last_seen_at']) ? formatRelativeTime($u['last_seen_at']) : '—' ?></span><?php endif; ?>
                    </td>
                    <td><a href="index.php?page=user_detail&uid=<?= (int)$u['id'] ?>" class="btn btn-xs btn-primary">🎩 پرونده</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="index.php?page=users&p=<?= $i ?><?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= !empty($filter) && $filter!=='all' ? '&filter=' . $filter : '' ?>" class="page-btn <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
