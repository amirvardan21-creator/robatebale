<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(adminPageTitle($page)) ?> | مافیا مچ</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <span class="skull">♠️</span>
            <h2>مافیا <span style="color:var(--red)">مچ</span></h2>
            <span>MAFIA FAMILY • خانواده مافیا</span>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">🏛 فرماندهی کل</div>
            <a href="index.php?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>">
                <span class="icon">📊</span> داشبورد
            </a>
            <a href="index.php?page=analytics" class="nav-item <?= $page === 'analytics' ? 'active' : '' ?>">
                <span class="icon">📈</span> آمار و تحلیل
            </a>

            <div class="nav-section">👥 مدیریت اعضا</div>
            <a href="index.php?page=users" class="nav-item <?= in_array($page, ['users', 'user_detail']) ? 'active' : '' ?>">
                <span class="icon">🎩</span> اعضا
            </a>
            <a href="index.php?page=secret" class="nav-item <?= $page === 'secret' ? 'active' : '' ?>">
                <span class="icon">🕶</span> ملاقات مخفیانه
                <?php if (($secretQueueCount ?? 0) > 0): ?><span class="nav-badge"><?= (int)$secretQueueCount ?></span><?php endif; ?>
            </a>
            <a href="index.php?page=referrals" class="nav-item <?= $page === 'referrals' ? 'active' : '' ?>">
                <span class="icon">👥</span> دعوت‌ها
            </a>

            <div class="nav-section">💰 اقتصاد خانواده</div>
            <a href="index.php?page=coins" class="nav-item <?= $page === 'coins' ? 'active' : '' ?>">
                <span class="icon">🪙</span> خزانه سکه
            </a>
            <a href="index.php?page=vip" class="nav-item <?= $page === 'vip' ? 'active' : '' ?>">
                <span class="icon">💎</span> اشتراک VIP
            </a>

            <div class="nav-section">🛡 امنیت</div>
            <a href="index.php?page=reports" class="nav-item <?= $page === 'reports' ? 'active' : '' ?>">
                <span class="icon">🚨</span> گزارشات
                <?php if (($pendingReports ?? 0) > 0): ?><span class="nav-badge"><?= (int)$pendingReports ?></span><?php endif; ?>
            </a>
            <a href="index.php?page=logs" class="nav-item <?= $page === 'logs' ? 'active' : '' ?>">
                <span class="icon">📜</span> لاگ‌ها
            </a>

            <div class="nav-section">📢 عملیات</div>
            <a href="index.php?page=broadcast" class="nav-item <?= $page === 'broadcast' ? 'active' : '' ?>">
                <span class="icon">📡</span> پیام همگانی
            </a>
            <a href="index.php?page=settings" class="nav-item <?= $page === 'settings' ? 'active' : '' ?>">
                <span class="icon">⚙️</span> تنظیمات
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-info">
                <div class="admin-avatar">♠️</div>
                <div>
                    <div class="admin-name"><?= h($_SESSION['admin_username'] ?? 'رئیس') ?></div>
                    <div class="admin-role">DON • پدرخوانده</div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <a href="index.php?page=logout" class="btn btn-ghost btn-sm">خروج</a>
                <a href="../webhook/setup.php?secret=<?= defined('WEBHOOK_SECRET') ? h(WEBHOOK_SECRET) : '' ?>&action=info" target="_blank" class="btn btn-ghost btn-sm">وب‌هوک</a>
            </div>
            <div style="text-align:center;margin-top:12px;font-size:10px;color:var(--text3);letter-spacing:1px">v2.0 MAFIA EDITION</div>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="topbar-title">
                <?= h(adminPageTitle($page)) ?>
                <span>♠️ خانواده مافیا</span>
            </div>
            <div class="topbar-meta">
                <span class="live">زنده</span>
                <span><?= date('Y/m/d H:i') ?></span>
                <span style="opacity:0.5">|</span>
                <span><?= h($_SERVER['SERVER_NAME'] ?? 'localhost') ?></span>
            </div>
        </header>

        <div class="content">
            <?php if ($msg): ?>
                <div class="alert alert-success">✅ <?= h($msg) ?></div>
            <?php endif; ?>
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger">❌ <?= h($error) ?></div>
            <?php endif; ?>

            <?php
            $pageFile = __DIR__ . '/pages/' . $page . '.php';
            if (file_exists($pageFile)) {
                include $pageFile;
            } else {
                echo '<div class="card"><div class="card-title">🚧 در حال ساخت</div><p style="color:var(--text2)">این بخش به زودی اضافه می‌شود.</p></div>';
            }
            ?>
        </div>

        <footer style="text-align:center;padding:20px;color:var(--text3);font-size:11px;letter-spacing:1px;border-top:1px solid var(--border)">
            ♠️ MAFIA MATCH • ساخته شده با خون و خیانت • <?= date('Y') ?> • <span style="color:var(--red)">قدرت در سایه است</span>
        </footer>
    </div>
</div>

<script>
// موبایل منو (اختیاری)
document.addEventListener('DOMContentLoaded', function() {
    // انیمیشن کارت‌ها
    const cards = document.querySelectorAll('.stat-card, .card');
    cards.forEach((card, i) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, i * 80);
    });
});
</script>
</body>
</html>
