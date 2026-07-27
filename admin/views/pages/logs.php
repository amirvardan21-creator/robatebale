<div class="grid-2">
    <div class="card">
        <div class="card-title"><span class="icon">📜</span> فایل‌های لاگ - <?= count($logFiles) ?> فایل</div>
        <div style="max-height:400px;overflow-y:auto">
            <?php if (empty($logFiles)): ?><p class="empty-cell">لاگی وجود ندارد</p>
            <?php else: foreach ($logFiles as $lf): 
                $name = basename($lf);
                $size = file_exists($lf) ? round(filesize($lf)/1024,1) . ' KB' : '0';
                $active = $selectedLog === $lf ? 'active' : '';
                $isToday = strpos($name, date('Y-m-d')) !== false;
            ?>
                <a href="index.php?page=logs&file=<?= urlencode($lf) ?>" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-radius:8px;margin-bottom:6px;text-decoration:none;<?= $active ? 'background:rgba(225,6,0,0.1);border:1px solid var(--border-red);color:var(--red-light)' : 'background:var(--bg);border:1px solid var(--border);color:var(--text2)' ?>">
                    <span style="display:flex;align-items:center;gap:8px"><span><?= $isToday ? '🔥' : '📄' ?></span> <?= h($name) ?> <?= $isToday ? '<span class="badge badge-blocked" style="font-size:10px">امروز</span>' : '' ?></span>
                    <span style="font-size:11px"><?= $size ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
        <form method="POST" style="margin-top:16px"><?= csrfField() ?><input type="hidden" name="page" value="logs"><input type="hidden" name="action" value="cleanup_logs"><button class="btn btn-ghost btn-sm" onclick="return confirm('لاگ‌های قدیمی‌تر از 1 روز حذف شوند؟')">🗑 پاکسازی لاگ‌های قدیمی</button></form>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">⚙️</span> اطلاعات سیستم</div>
        <div class="info-grid">
            <div class="info-item"><div class="lbl">مسیر لاگ</div><div class="val" style="font-size:11px;direction:ltr"><?= h(LOG_PATH) ?></div></div>
            <div class="info-item"><div class="lbl">فضای اشغال</div><div class="val"><?= is_dir(LOG_PATH) ? round(array_sum(array_map(fn($f)=>@filesize($f)?:0, glob(LOG_PATH.'*.log') ?: [])) / 1024, 1) . ' KB' : '—' ?></div></div>
            <div class="info-item"><div class="lbl">PHP Version</div><div class="val"><?= PHP_VERSION ?></div></div>
            <div class="info-item"><div class="lbl">تعداد فایل لاگ</div><div class="val"><?= count($logFiles) ?> فایل</div></div>
            <div class="info-item"><div class="lbl">حافظه مصرفی</div><div class="val"><?= round(memory_get_usage()/1024/1024,2) ?> MB</div></div>
            <div class="info-item"><div class="lbl">زمان سرور</div><div class="val"><?= date('H:i:s') ?></div></div>
        </div>
        <div class="divider"></div>
        <h4 style="font-size:13px;margin-bottom:10px">💡 نکات:</h4>
        <ul class="tips-list">
            <li>لاگ‌های ERROR قرمز هستند</li>
            <li>هر روز یک فایل جدید ساخته می‌شود</li>
            <li>فایل‌های بیشتر از 7 روز خودکار حذف می‌شوند</li>
            <li>برای دیباگ ارور cb_ این فایل‌ها را چک کن</li>
        </ul>
    </div>
</div>

<?php if ($selectedLog): ?>
<div class="card">
    <div class="card-title"><span class="icon">📄</span> محتوای لاگ: <?= h(basename($selectedLog)) ?> <span class="card-subtitle">آخرین 10,000 کاراکتر</span></div>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;max-height:500px;overflow:auto;direction:ltr;text-align:left">
        <?php
        $lines = explode("\n", $logContent);
        $lines = array_filter($lines);
        $lines = array_slice(array_reverse($lines), 0, 200); // last 200 lines
        foreach ($lines as $line):
            $class = '';
            if (stripos($line, 'ERROR') !== false || stripos($line, 'error') !== false) $class = 'error';
            elseif (stripos($line, 'WARNING') !== false || stripos($line, 'SECURITY') !== false) $class = 'warning';
        ?>
            <div class="log-line <?= $class ?>"><?= h($line) ?></div>
        <?php endforeach; ?>
        <?php if (empty($lines)): ?><div class="log-line">لاگ خالی است</div><?php endif; ?>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px">
        <a href="index.php?page=logs&file=<?= urlencode($selectedLog) ?>" class="btn btn-ghost btn-sm">🔄 رفرش</a>
        <a href="index.php?page=logs" class="btn btn-ghost btn-sm">📜 همه فایل‌ها</a>
    </div>
</div>
<?php endif; ?>
