<?php
$searchQuery = $searchQuery ?? '';
$filter = $filter ?? 'all';
$users = $users ?? [];
$totalUsers = $totalUsers ?? 0;
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
?>
<div class="card">
    <div class="card-title"><span class="icon">🔍</span> جستجو و فیلتر خانواده</div>
    <form method="GET" class="search-bar">
        <input type="hidden" name="page" value="users">
        <input type="text" name="q" value="<?= h($searchQuery) ?>" placeholder="🎩 نام، بله آیدی، شهر، یوزرنیم...">
        <button type="submit" class="btn btn-primary">🔍 جستجو</button>
        <?php if (!empty($searchQuery)): ?><a href="index.php?page=users" class="btn btn-ghost">✕ پاک</a><?php endif; ?>
    </form>
    <div class="filter-tabs">
        <a href="index.php?page=users" class="filter-tab <?= $filter==='all' && empty($searchQuery) ? 'active' : '' ?>">همه (<?= (int)$totalUsers ?>)</a>
        <a href="index.php?page=users&filter=online" class="filter-tab <?= $filter==='online' ? 'active' : '' ?>">🟢 آنلاین</a>
        <a href="index.php?page=users&filter=vip" class="filter-tab <?= $filter==='vip' ? 'active' : '' ?>">⭐ VIP</a>
        <a href="index.php?page=users&filter=blocked" class="filter-tab <?= $filter==='blocked' ? 'active' : '' ?>">🚫 مسدود</a>
    </div>
</div>

<div class="card">
    <div class="card-title">
        <span class="icon">👥</span> لیست اعضا - <?= number_format((int)$totalUsers) ?> نفر
        <span class="card-subtitle">صفحه <?= (int)$currentPage ?> از <?= (int)$totalPages ?></span>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-cell" style="padding:40px;text-align:center">
            <div style="font-size:48px">🎩</div>
            <div style="margin-top:10px;color:var(--text2)">عضوی یافت نشد</div>
            <?php if (!empty($searchQuery)): ?><div style="margin-top:8px"><a href="index.php?page=users" class="btn btn-ghost btn-sm">نمایش همه</a></div><?php endif; ?>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>عضو</th><th>بله</th><th>شهر/سن</th><th>سکه</th><th>وضعیت</th><th>آنلاین</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): 
                $uid = (int)($u['id'] ?? 0);
                $name = $u['name'] ?? $u['first_name'] ?? '—';
                $baleId = $u['bale_id'] ?? '';
                $isBlocked = (int)($u['is_blocked'] ?? 0) === 1;
                $isVip = $u['is_vip'] ?? false;
                $coins = $u['coins'] ?? 0;
                $isOnline = $u['is_online'] ?? false;
                $city = $u['city'] ?? '—';
                $age = $u['age'] ?? '—';
                $gender = $u['gender'] ?? null;
            ?>
                <tr style="<?= $isBlocked ? 'opacity:0.6;background:rgba(225,6,0,0.03)' : '' ?>">
                    <td><span style="color:var(--text3);font-size:11px">#<?= $uid ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:36px;height:36px;background:linear-gradient(135deg,<?= $isVip ? 'var(--gold), #8a6d0a' : 'var(--red), var(--red-dark)' ?>);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;color:#fff"><?= h(mb_substr($name ?? 'U',0,1)) ?></div>
                            <div>
                                <strong><?= h($name) ?> <?= $isVip ? '⭐' : '' ?></strong>
                                <div style="font-size:11px;color:var(--text3)"><?= $gender ? ( $gender==='male' ? 'آقا' : 'خانم') : '' ?> <?= $age !== '—' ? $age.' ساله' : '' ?></div>
                            </div>
                        </div>
                    </td>
                    <td><code style="font-size:11px"><?= h($baleId) ?></code></td>
                    <td><span style="font-size:12px"><?= h($city) ?></span></td>
                    <td><span class="coin-inline" style="font-size:12px">🪙 <?= number_format((int)$coins) ?></span></td>
                    <td>
                        <?php if ($isBlocked): ?><span class="badge badge-blocked">🚫 مسدود</span>
                        <?php else: ?><span class="badge badge-active">✅ فعال</span><?php endif; ?>
                    </td>
                    <td><?php if ($isOnline): ?><span class="badge badge-online">🟢 آنلاین</span><?php else: ?><span style="font-size:11px;color:var(--text3)"><?= isset($u['last_seen_at']) && $u['last_seen_at'] ? formatRelativeTime($u['last_seen_at']) : '—' ?></span><?php endif; ?></td>
                    <td><a href="index.php?page=user_detail&uid=<?= $uid ?>" class="btn btn-xs btn-primary">🎩 پرونده</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:20px">
        <?php
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $start + 4);
        if ($currentPage > 1) echo '<a href="index.php?page=users&p='.($currentPage-1).(!empty($searchQuery)?'&q='.urlencode($searchQuery):'').(!empty($filter)&&$filter!=='all'?'&filter='.$filter:'').'" class="page-btn">‹</a>';
        for ($i=$start;$i<=$end;$i++) {
            $active = $i === $currentPage ? 'active' : '';
            echo '<a href="index.php?page=users&p='.$i.(!empty($searchQuery)?'&q='.urlencode($searchQuery):'').(!empty($filter)&&$filter!=='all'?'&filter='.$filter:'').'" class="page-btn '.$active.'">'.$i.'</a>';
        }
        if ($currentPage < $totalPages) echo '<a href="index.php?page=users&p='.($currentPage+1).(!empty($searchQuery)?'&q='.urlencode($searchQuery):'').(!empty($filter)&&$filter!=='all'?'&filter='.$filter:'').'" class="page-btn">›</a>';
        ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title"><span class="icon">💡</span> راهنما</div>
    <ul class="tips-list">
        <li>برای دیدن پرونده کامل روی <strong>🎩 پرونده</strong> بزن</li>
        <li>جستجو بر اساس نام، بله آیدی، شهر کار می‌کند</li>
        <li>فیلتر آنلاین فقط کاربرانی که در 10 دقیقه اخیر فعال بودند را نشان می‌دهد</li>
        <li>اعضای VIP با ⭐ مشخص شده‌اند</li>
    </ul>
</div>
