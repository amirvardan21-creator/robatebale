<div class="card">
    <div class="card-title">
        <span class="icon">🚨</span> گزارشات کاربران
        <span class="card-subtitle"><?= count($reports) ?> گزارش</span>
    </div>

    <div class="filter-tabs">
        <a href="index.php?page=reports&status=pending" class="filter-tab <?= ($reportStatus ?? 'pending') === 'pending' ? 'active' : '' ?>">در انتظار</a>
        <a href="index.php?page=reports&status=reviewed" class="filter-tab <?= ($reportStatus ?? '') === 'reviewed' ? 'active' : '' ?>">بررسی شده</a>
        <a href="index.php?page=reports&status=resolved" class="filter-tab <?= ($reportStatus ?? '') === 'resolved' ? 'active' : '' ?>">حل شده</a>
        <a href="index.php?page=reports&status=all" class="filter-tab <?= ($reportStatus ?? '') === 'all' ? 'active' : '' ?>">همه</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>گزارش‌دهنده</th>
                    <th>متهم</th>
                    <th>دلیل</th>
                    <th>وضعیت</th>
                    <th>تاریخ</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="7" class="empty-cell">گزارشی یافت نشد</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td>
                        <strong><?= h($r['reporter_name'] ?? '—') ?></strong><br>
                        <code style="font-size:11px"><?= h($r['reporter_bale_id']) ?></code>
                    </td>
                    <td>
                        <strong><?= h($r['reported_name'] ?? '—') ?></strong><br>
                        <code style="font-size:11px"><?= h($r['reported_bale_id']) ?></code>
                    </td>
                    <td><?= h($r['reason']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <span class="badge badge-pending">⏳ در انتظار</span>
                        <?php elseif ($r['status'] === 'resolved'): ?>
                            <span class="badge badge-active">✅ حل شده</span>
                        <?php else: ?>
                            <span class="badge badge-blocked"><?= h($r['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatDate($r['created_at']) ?></td>
                    <td>
                        <div class="inline-actions">
                            <a href="index.php?page=user_detail&uid=<?= (int)$r['reported_id'] ?>" class="btn btn-xs btn-ghost">مشاهده</a>
                            <?php if ($r['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="page" value="reports">
                                <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="reported_user_id" value="<?= (int)$r['reported_id'] ?>">
                                <button type="submit" name="action" value="block_from_report" class="btn btn-xs btn-danger" onclick="return confirm('متهم مسدود شود؟')">🚫 مسدود</button>
                            </form>
                            <form method="POST" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="page" value="reports">
                                <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" name="action" value="resolve_report" class="btn btn-xs btn-success">✅ حل شد</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php if (!empty($r['admin_note'])): ?>
                <tr class="note-row">
                    <td></td>
                    <td colspan="6"><small>یادداشت ادمین: <?= h($r['admin_note']) ?></small></td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
