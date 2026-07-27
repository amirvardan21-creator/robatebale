<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Profile.php';
require_once __DIR__ . '/../classes/MatchModel.php';
require_once __DIR__ . '/../classes/BlockReport.php';
require_once __DIR__ . '/../classes/Bale.php';
require_once __DIR__ . '/../classes/Vip.php';
require_once __DIR__ . '/../classes/Coin.php';
require_once __DIR__ . '/../classes/SecretMeetingRoom.php';
require_once __DIR__ . '/../classes/SecretMeetingQueue.php';
require_once __DIR__ . '/includes/helpers.php';

function adminAuth(): void { if (empty($_SESSION['admin_logged_in'])) { header('Location: index.php'); exit; } }

$error = '';
$page  = $_GET['page'] ?? $_POST['page'] ?? 'login';
$msg   = '';

$protectedPages = ['dashboard','users','user_detail','reports','broadcast','settings','coins','secret','referrals','vip','analytics','logs'];

if (isset($_SESSION['admin_logged_in']) && $page === 'login') { header('Location: index.php?page=dashboard'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $admin = Database::fetch('SELECT * FROM admins WHERE username = ?', [$username]);
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $admin['username'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        Database::execute('UPDATE admins SET last_login = NOW() WHERE id = ?', [$admin['id']]);
        header('Location: index.php?page=dashboard'); exit;
    }
    $error = 'نام کاربری یا رمز عبور اشتباه است.';
}

if ($page === 'logout') { session_destroy(); header('Location: index.php'); exit; }

if (isset($_SESSION['admin_logged_in']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    adminAuth();
    if (!csrfVerify()) {
        $_SESSION['flash'] = '❌ توکن امنیتی نامعتبر';
        $rp = in_array($page, $protectedPages, true) ? $page : 'dashboard';
        $extra = isset($_GET['uid']) ? '&uid=' . (int) $_GET['uid'] : '';
        header('Location: index.php?page=' . $rp . $extra); exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'block_user') { User::block((int) $_POST['user_id'], 'blocked by admin'); $msg = 'کاربر مسدود شد.'; }
    elseif ($action === 'unblock_user') { User::unblock((int) $_POST['user_id']); $msg = 'آنبلاک شد.'; }
    elseif ($action === 'delete_user') {
        User::delete((int) $_POST['user_id']); $msg = 'کاربر حذف شد.';
        header('Location: index.php?page=users'); $_SESSION['flash'] = $msg; exit;
    }
    elseif ($action === 'grant_vip') { Vip::activate((int) $_POST['user_id'], $_POST['plan'] ?? 'monthly'); $msg = 'VIP فعال شد.'; }
    elseif ($action === 'revoke_vip') { Database::execute('UPDATE vip_users SET is_active = 0 WHERE user_id = ?', [(int) $_POST['user_id']]); $msg = 'VIP لغو شد.'; }
    elseif ($action === 'add_coins') {
        $uid = (int) $_POST['user_id']; $amount = (int) $_POST['amount']; $desc = trim($_POST['desc'] ?? 'ادمین');
        if ($amount > 0 && tableExists('coins')) { Coin::add($uid, $amount, $desc); $msg = "{$amount} سکه اضافه شد."; }
    }
    elseif ($action === 'deduct_coins') {
        $uid = (int) $_POST['user_id']; $amount = (int) $_POST['amount']; $desc = trim($_POST['desc'] ?? 'کسر ادمین');
        if ($amount > 0 && tableExists('coins')) { Coin::deduct($uid, $amount, $desc); $msg = "{$amount} سکه کسر شد."; }
    }
    elseif ($action === 'set_coins') {
        $uid = (int) $_POST['user_id']; $amount = (int) $_POST['amount'];
        if (tableExists('coins')) { Coin::set($uid, $amount); $msg = "موجودی {$amount} شد."; }
    }
    elseif ($action === 'resolve_report') { BlockReport::updateReportStatus((int) $_POST['report_id'], 'resolved', $_POST['note'] ?? 'حل شد'); $msg = 'گزارش حل شد.'; }
    elseif ($action === 'block_from_report') {
        $uid = (int) $_POST['reported_user_id']; User::block($uid, 'blocked from report'); BlockReport::updateReportStatus((int) $_POST['report_id'], 'resolved', 'مسدود شد'); $msg = 'مسدود و بسته شد.';
    }
    elseif ($action === 'dismiss_report') { BlockReport::updateReportStatus((int) $_POST['report_id'], 'dismissed', $_POST['note'] ?? 'رد شد'); $msg = 'گزارش رد شد.'; }
    elseif ($action === 'update_setting') { Database::execute('UPDATE settings SET value = ? WHERE `key` = ?', [$_POST['value'], $_POST['key']]); $msg = 'ذخیره شد.'; }
    elseif ($action === 'broadcast') {
        $bale = new Bale(); $text = trim($_POST['message'] ?? '');
        $filter = $_POST['filter'] ?? 'all';
        if ($text) {
            $sql = 'SELECT bale_id FROM users WHERE is_active = 1 AND is_blocked = 0';
            $params = [];
            if ($filter === 'vip') { $sql .= ' AND id IN (SELECT user_id FROM vip_users WHERE is_active=1 AND expires_at > NOW())'; }
            elseif ($filter === 'city_tehran') { $sql .= ' AND id IN (SELECT user_id FROM profiles WHERE city="تهران")'; }
            $users = Database::fetchAll($sql, $params);
            $sent = 0;
            foreach ($users as $u) { $bale->sendMessage((int) $u['bale_id'], $text); usleep(80000); $sent++; }
            $msg = "پیام برای {$sent} نفر ارسال شد.";
        }
    }
    elseif ($action === 'send_dm') {
        $uid = (int) $_POST['user_id']; $text = trim($_POST['message'] ?? '');
        $user = User::findById($uid);
        if ($user && $text) { $bale = new Bale(); $bale->sendMessage((int) $user['bale_id'], "🎩 پیام مدیریت:\n\n" . $text); $msg = 'پیام ارسال شد.'; }
    }
    elseif ($action === 'clear_queue') { Database::execute('DELETE FROM secret_meeting_queue'); $msg = 'صف پاک شد.'; }
    elseif ($action === 'end_all_rooms') { Database::execute('UPDATE secret_meeting_rooms SET status="aborted", ended_at=NOW() WHERE status="active"'); Database::execute('UPDATE users SET current_status="idle", current_secret_meeting_room_id=NULL WHERE current_status!="idle"'); $msg = 'تمام چت‌های مخفیانه بسته شد.'; }
    elseif ($action === 'cleanup_logs') {
        $files = glob(LOG_PATH . '*.log');
        $deleted = 0;
        foreach ($files as $f) { if (is_file($f) && filemtime($f) < time() - 86400) { @unlink($f); $deleted++; } }
        $msg = "{$deleted} لاگ قدیمی حذف شد.";
    }

    $userActions = ['block_user','unblock_user','grant_vip','revoke_vip','add_coins','deduct_coins','set_coins','send_dm'];
    if (in_array($action, $userActions, true) && !empty($_POST['user_id'])) { $rp = 'user_detail'; $extra = '&uid=' . (int) $_POST['user_id']; }
    else { $rp = in_array($page, $protectedPages, true) ? $page : 'dashboard'; $extra = isset($_GET['uid']) ? '&uid=' . (int) $_GET['uid'] : ''; }
    if ($msg) $_SESSION['flash'] = $msg;
    header('Location: index.php?page=' . $rp . $extra); exit;
}

if (isset($_SESSION['flash'])) { $msg = $_SESSION['flash']; unset($_SESSION['flash']); }
if (in_array($page, $protectedPages, true)) adminAuth();

$pendingReports = 0;
$secretQueueCount = 0;
try { $row = Database::fetch('SELECT COUNT(*) AS cnt FROM reports WHERE status = "pending"'); $pendingReports = (int) ($row['cnt'] ?? 0); } catch (Throwable) {}
try { $row = Database::fetch('SELECT COUNT(*) AS cnt FROM secret_meeting_queue'); $secretQueueCount = (int) ($row['cnt'] ?? 0); } catch (Throwable) {}

switch ($page) {
    case 'dashboard':
        $stats = [
            'total_users'     => User::count(),
            'active_users'    => User::countActive(),
            'online_users'    => User::countOnline(5),
            'today_users'     => (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM users WHERE DATE(created_at) = CURDATE()')['cnt'] ?? 0),
            'vip_users'       => (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM vip_users WHERE is_active = 1 AND expires_at > NOW()')['cnt'] ?? 0),
            'matches'         => (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM matches WHERE is_active = 1')['cnt'] ?? 0),
            'total_coins'     => safeTotalCoins(),
            'pending_reports' => $pendingReports,
            'blocked_users'   => (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM users WHERE is_blocked = 1')['cnt'] ?? 0),
            'secret_active'   => (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM secret_meeting_rooms WHERE status="active"')['cnt'] ?? 0),
            'secret_today'    => (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM secret_meeting_rooms WHERE DATE(created_at)=CURDATE()')['cnt'] ?? 0),
            'secret_queue'    => $secretQueueCount,
            'referrals'       => (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM referrals')['cnt'] ?? 0),
        ];
        $recentUsers = Database::fetchAll('SELECT u.*, p.name, p.city, p.age, p.gender FROM users u LEFT JOIN profiles p ON u.id = p.user_id ORDER BY u.created_at DESC LIMIT 8');
        $recentReports = Database::fetchAll('SELECT r.*, p1.name AS reporter_name, p2.name AS reported_name FROM reports r LEFT JOIN profiles p1 ON p1.user_id = r.reporter_id LEFT JOIN profiles p2 ON p2.user_id = r.reported_id ORDER BY r.created_at DESC LIMIT 6');
        $topReferrers = [];
        try { $topReferrers = Database::fetchAll('SELECT u.id, p.name, COUNT(r.id) as cnt FROM referrals r JOIN users u ON r.referrer_id=u.id LEFT JOIN profiles p ON p.user_id=u.id GROUP BY r.referrer_id ORDER BY cnt DESC LIMIT 5'); } catch (Throwable) {}
        // chart data last 7 days
        $chartLabels = []; $chartUsers = []; $chartSecret = [];
        for ($i=6;$i>=0;$i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chartLabels[] = date('m/d', strtotime($date));
            try { $c = Database::fetch('SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at)=?', [$date]); $chartUsers[] = (int)($c['cnt'] ?? 0); } catch (Throwable) { $chartUsers[] = 0; }
            try { $c = Database::fetch('SELECT COUNT(*) as cnt FROM secret_meeting_rooms WHERE DATE(created_at)=?', [$date]); $chartSecret[] = (int)($c['cnt'] ?? 0); } catch (Throwable) { $chartSecret[] = 0; }
        }
        break;

    case 'users':
        $searchQuery = trim($_GET['q'] ?? '');
        $filter = $_GET['filter'] ?? 'all';
        $currentPage = max(1, (int) ($_GET['p'] ?? 1));
        $perPage = 20; $offset = ($currentPage - 1) * $perPage;
        $users = []; $totalUsers = 0;
        try {
            if ($searchQuery) {
                $users = User::search($searchQuery);
                $totalUsers = count($users);
                $users = array_slice($users, $offset, $perPage);
            } else {
                if ($filter === 'online') {
                    $users = Database::fetchAll('SELECT u.*, p.name, p.age, p.city FROM users u LEFT JOIN profiles p ON u.id=p.user_id WHERE u.last_seen_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND u.is_blocked=0 ORDER BY u.last_seen_at DESC LIMIT ? OFFSET ?', [$perPage, $offset]);
                    $totalUsers = (int) (Database::fetch('SELECT COUNT(*) as cnt FROM users WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)')['cnt'] ?? 0);
                } elseif ($filter === 'vip') {
                    $users = Database::fetchAll('SELECT u.*, p.name, p.age, p.city FROM users u JOIN vip_users v ON v.user_id=u.id LEFT JOIN profiles p ON p.user_id=u.id WHERE v.is_active=1 AND v.expires_at > NOW() ORDER BY v.expires_at DESC LIMIT ? OFFSET ?', [$perPage, $offset]);
                    $totalUsers = (int) (Database::fetch('SELECT COUNT(*) as cnt FROM vip_users WHERE is_active=1 AND expires_at > NOW()')['cnt'] ?? 0);
                } elseif ($filter === 'blocked') {
                    $users = Database::fetchAll('SELECT u.*, p.name, p.age, p.city FROM users u LEFT JOIN profiles p ON u.id=p.user_id WHERE u.is_blocked=1 ORDER BY u.created_at DESC LIMIT ? OFFSET ?', [$perPage, $offset]);
                    $totalUsers = (int) (Database::fetch('SELECT COUNT(*) as cnt FROM users WHERE is_blocked=1')['cnt'] ?? 0);
                } else {
                    $totalUsers = User::count();
                    $users = User::getAll($perPage, $offset);
                }
            }
        } catch (Throwable $e) { $users = []; }
        foreach ($users as &$u) { $u['coins'] = safeCoinBalance((int) $u['id']); $u['is_vip'] = Vip::isVip((int) $u['id']); $u['is_online'] = isUserOnline($u['last_seen_at'] ?? null); }
        unset($u);
        $totalPages = max(1, (int) ceil($totalUsers / $perPage));
        break;

    case 'user_detail':
        $uid = (int) ($_GET['uid'] ?? 0);
        $userDetail = Database::fetch('SELECT u.*, p.id AS profile_id, p.name, p.age, p.gender, p.city, p.bio, p.interests, p.looking_for, p.is_visible, p.photo_file_id, p.total_views, p.total_likes, (SELECT COUNT(*) FROM matches m WHERE (m.user1_id = u.id OR m.user2_id = u.id) AND m.is_active = 1) AS match_count FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?', [$uid]);
        $userTransactions = []; $userReferrals = []; $userSecretRooms = [];
        if ($userDetail) {
            try { $userTransactions = Database::fetchAll('SELECT * FROM coin_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 10', [$uid]); } catch (Throwable) {}
            try { $userReferrals = Database::fetchAll('SELECT r.*, p.name FROM referrals r LEFT JOIN profiles p ON p.user_id=r.referred_id WHERE r.referrer_id=? ORDER BY r.created_at DESC LIMIT 10', [$uid]); } catch (Throwable) {}
            try { $userSecretRooms = Database::fetchAll('SELECT * FROM secret_meeting_rooms WHERE user1_id=? OR user2_id=? ORDER BY created_at DESC LIMIT 5', [$uid,$uid]); } catch (Throwable) {}
        }
        break;

    case 'reports':
        $reportStatus = $_GET['status'] ?? 'pending';
        try {
            if ($reportStatus === 'all') {
                $reports = Database::fetchAll('SELECT r.*, p1.name AS reporter_name, p2.name AS reported_name, u1.bale_id AS reporter_bale_id, u2.bale_id AS reported_bale_id FROM reports r JOIN users u1 ON r.reporter_id = u1.id JOIN users u2 ON r.reported_id = u2.id LEFT JOIN profiles p1 ON p1.user_id = r.reporter_id LEFT JOIN profiles p2 ON p2.user_id = r.reported_id ORDER BY r.created_at DESC LIMIT 50');
            } else {
                $reports = Database::fetchAll('SELECT r.*, p1.name AS reporter_name, p2.name AS reported_name, u1.bale_id AS reporter_bale_id, u2.bale_id AS reported_bale_id FROM reports r JOIN users u1 ON r.reporter_id = u1.id JOIN users u2 ON r.reported_id = u2.id LEFT JOIN profiles p1 ON p1.user_id = r.reporter_id LEFT JOIN profiles p2 ON p2.user_id = r.reported_id WHERE r.status = ? ORDER BY r.created_at DESC LIMIT 50', [$reportStatus]);
            }
        } catch (Throwable) { $reports = []; }
        break;

    case 'broadcast': $broadcastCount = (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM users WHERE is_active = 1 AND is_blocked = 0')['cnt'] ?? 0); break;

    case 'settings':
        $settings = Database::fetchAll('SELECT * FROM settings ORDER BY id ASC');
        $settingLabels = ['bot_active'=>'وضعیت ربات','maintenance_message'=>'پیام تعمیر','free_daily_views'=>'محدودیت مشاهده روزانه','free_daily_likes'=>'محدودیت لایک روزانه','free_daily_secret'=>'محدودیت چت مخفیانه روزانه','vip_monthly_price'=>'قیمت VIP','welcome_message'=>'پیام خوش‌آمد',];
        break;

    case 'coins':
        $totalCoins = safeTotalCoins();
        $topUsers = safeTopCoinUsers(15);
        $coinHolders = 0; $totalTransactions = 0; $recentTransactions = [];
        if (tableExists('coins')) { $coinHolders = (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM coins WHERE balance > 0')['cnt'] ?? 0); }
        if (tableExists('coin_transactions')) {
            $totalTransactions = (int) (Database::fetch('SELECT COUNT(*) AS cnt FROM coin_transactions')['cnt'] ?? 0);
            $recentTransactions = Database::fetchAll('SELECT ct.*, p.name, u.bale_id FROM coin_transactions ct JOIN users u ON ct.user_id = u.id LEFT JOIN profiles p ON p.user_id = u.id ORDER BY ct.created_at DESC LIMIT 20');
        }
        break;

    case 'secret':
        $secretStats = [
            'active_rooms' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM secret_meeting_rooms WHERE status="active"')['cnt'] ?? 0),
            'today_rooms' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM secret_meeting_rooms WHERE DATE(created_at)=CURDATE()')['cnt'] ?? 0),
            'total_rooms' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM secret_meeting_rooms')['cnt'] ?? 0),
            'queue' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM secret_meeting_queue')['cnt'] ?? 0),
        ];
        $activeRooms = Database::fetchAll('SELECT r.*, p1.name as user1_name, p2.name as user2_name FROM secret_meeting_rooms r LEFT JOIN profiles p1 ON p1.user_id=r.user1_id LEFT JOIN profiles p2 ON p2.user_id=r.user2_id WHERE r.status="active" ORDER BY r.created_at DESC LIMIT 20');
        $queueUsers = Database::fetchAll('SELECT q.*, p.name, p.city, u.bale_id, u.last_seen_at FROM secret_meeting_queue q JOIN users u ON q.user_id=u.id LEFT JOIN profiles p ON p.user_id=q.user_id ORDER BY q.vip_priority DESC, q.queued_at ASC LIMIT 30');
        break;

    case 'referrals':
        $totalReferrals = (int) (Database::fetch('SELECT COUNT(*) as cnt FROM referrals')['cnt'] ?? 0);
        $topReferrers = Database::fetchAll('SELECT u.id, p.name, u.bale_id, COUNT(r.id) as cnt, SUM(r.bonus) as total_bonus FROM referrals r JOIN users u ON r.referrer_id=u.id LEFT JOIN profiles p ON p.user_id=u.id GROUP BY r.referrer_id ORDER BY cnt DESC LIMIT 20');
        $recentReferrals = Database::fetchAll('SELECT r.*, p1.name as referrer_name, p2.name as referred_name FROM referrals r LEFT JOIN profiles p1 ON p1.user_id=r.referrer_id LEFT JOIN profiles p2 ON p2.user_id=r.referred_id ORDER BY r.created_at DESC LIMIT 20');
        break;

    case 'vip':
        $vipStats = [
            'active' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM vip_users WHERE is_active=1 AND expires_at > NOW()')['cnt'] ?? 0),
            'expired' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM vip_users WHERE expires_at < NOW()')['cnt'] ?? 0),
            'today' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM vip_users WHERE DATE(started_at)=CURDATE()')['cnt'] ?? 0),
        ];
        $vipUsers = Database::fetchAll('SELECT v.*, p.name, p.city, u.bale_id FROM vip_users v JOIN users u ON v.user_id=u.id LEFT JOIN profiles p ON p.user_id=v.user_id WHERE v.is_active=1 AND v.expires_at > NOW() ORDER BY v.expires_at ASC LIMIT 30');
        break;

    case 'analytics':
        $analytics = [
            'users_by_city' => Database::fetchAll('SELECT city, COUNT(*) as cnt FROM profiles GROUP BY city ORDER BY cnt DESC LIMIT 10'),
            'users_by_gender' => Database::fetchAll('SELECT gender, COUNT(*) as cnt FROM profiles GROUP BY gender'),
            'users_by_age' => Database::fetchAll('SELECT CASE WHEN age < 20 THEN "18-19" WHEN age < 25 THEN "20-24" WHEN age < 30 THEN "25-29" WHEN age < 35 THEN "30-34" ELSE "35+" END as range_age, COUNT(*) as cnt FROM profiles GROUP BY range_age ORDER BY range_age'),
        ];
        $dailyStats = [];
        for ($i=6;$i>=0;$i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dailyStats[] = [
                'date' => $date,
                'label' => date('m/d', strtotime($date)),
                'users' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at)=?', [$date])['cnt'] ?? 0),
                'secret' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM secret_meeting_rooms WHERE DATE(created_at)=?', [$date])['cnt'] ?? 0),
                'matches' => (int) (Database::fetch('SELECT COUNT(*) as cnt FROM matches WHERE DATE(created_at)=?', [$date])['cnt'] ?? 0),
            ];
        }
        break;

    case 'logs':
        $logFiles = glob(LOG_PATH . '*.log');
        rsort($logFiles);
        $logFiles = array_slice($logFiles, 0, 20);
        $selectedLog = $_GET['file'] ?? ($logFiles[0] ?? null);
        $logContent = '';
        if ($selectedLog && file_exists($selectedLog) && strpos(realpath($selectedLog), realpath(LOG_PATH)) === 0) {
            $logContent = file_get_contents($selectedLog);
            $logContent = mb_substr($logContent, -10000); // last 10k chars
        }
        break;
}

if ($page === 'login') { include __DIR__ . '/views/login.php'; exit; }
if (!in_array($page, $protectedPages, true)) { header('Location: index.php?page=dashboard'); exit; }
include __DIR__ . '/views/layout.php';
