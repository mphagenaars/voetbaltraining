<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin/ai/settings" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">AI Usage Rapport</h1>
    </div>
</div>

<div class="ai-admin-stats-grid ai-admin-form-spaced">
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value"><?= number_format((int)$summary['total_calls']) ?></span>
        <span class="ai-admin-stat-label">Totaal calls</span>
    </div>
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value"><?= number_format((int)$summary['total_tokens']) ?></span>
        <span class="ai-admin-stat-label">Totaal tokens</span>
    </div>
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value">$<?= number_format((float)$summary['total_supplier_cost_usd'], 4) ?></span>
        <span class="ai-admin-stat-label">Supplier cost (USD)</span>
    </div>
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value">€<?= number_format((float)$summary['total_billable_cost_eur'], 4) ?></span>
        <span class="ai-admin-stat-label">Billable (EUR)</span>
    </div>
</div>

<div class="ai-admin-stats-grid ai-admin-form-spaced">
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value"><?= number_format((int)($qualitySummary['total_events'] ?? 0)) ?></span>
        <span class="ai-admin-stat-label">Quality events</span>
    </div>
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value"><?= number_format((int)($qualitySummary['blocker_events'] ?? 0)) ?></span>
        <span class="ai-admin-stat-label">Blockers</span>
    </div>
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value"><?= number_format((int)($qualitySummary['recovery_offered'] ?? 0)) ?></span>
        <span class="ai-admin-stat-label">Recovery aangeboden</span>
    </div>
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value"><?= number_format((int)($qualitySummary['recovery_selected'] ?? 0)) ?></span>
        <span class="ai-admin-stat-label">Recovery gekozen</span>
    </div>
    <div class="ai-admin-stat-card">
        <span class="ai-admin-stat-value"><?= number_format((int)($qualitySummary['cookie_recoveries'] ?? 0)) ?></span>
        <span class="ai-admin-stat-label">Cookie recoveries</span>
    </div>
</div>

<div class="tb-card">
    <h2 class="ai-admin-card-title">Per model</h2>
    <div class="ai-admin-table-wrap">
        <table class="ai-admin-table">
            <thead>
                <tr>
                    <th>Model</th>
                    <th>Calls</th>
                    <th>Tokens</th>
                    <th>Supplier USD</th>
                    <th>Billable EUR</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usagePerModel)): ?>
                    <tr><td colspan="5" class="ai-admin-table-empty">Nog geen usage events.</td></tr>
                <?php else: ?>
                    <?php foreach ($usagePerModel as $row): ?>
                        <tr>
                            <td><code><?= e($row['model_id']) ?></code></td>
                            <td><?= number_format((int)$row['calls']) ?></td>
                            <td><?= number_format((int)$row['total_tokens']) ?></td>
                            <td>$<?= number_format((float)$row['supplier_cost_usd'], 4) ?></td>
                            <td>€<?= number_format((float)$row['billable_cost_eur'], 4) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="tb-card">
    <h2 class="ai-admin-card-title">Per gebruiker</h2>
    <div class="ai-admin-table-wrap">
        <table class="ai-admin-table">
            <thead>
                <tr>
                    <th>Gebruiker</th>
                    <th>Calls</th>
                    <th>Tokens</th>
                    <th>Billable EUR</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usagePerUser)): ?>
                    <tr><td colspan="4" class="ai-admin-table-empty">Nog geen usage events.</td></tr>
                <?php else: ?>
                    <?php foreach ($usagePerUser as $row): ?>
                        <tr>
                            <td><?= e((string)$row['user_name']) ?> <small class="ai-admin-meta-muted">(#<?= (int)$row['user_id'] ?>)</small></td>
                            <td><?= number_format((int)$row['calls']) ?></td>
                            <td><?= number_format((int)$row['total_tokens']) ?></td>
                            <td>€<?= number_format((float)$row['billable_cost_eur'], 4) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="tb-card">
    <h2 class="ai-admin-card-title">Quality events per type</h2>
    <div class="ai-admin-table-wrap">
        <table class="ai-admin-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Aantal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($qualityPerType)): ?>
                    <tr><td colspan="3" class="ai-admin-table-empty">Nog geen quality events.</td></tr>
                <?php else: ?>
                    <?php foreach ($qualityPerType as $row): ?>
                        <tr>
                            <td><code><?= e((string)$row['event_type']) ?></code></td>
                            <td><?= e((string)$row['status']) ?></td>
                            <td><?= number_format((int)$row['event_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="tb-card">
    <h2 class="ai-admin-card-title">Recente quality events</h2>
    <div class="ai-admin-table-wrap">
        <table class="ai-admin-table">
            <thead>
                <tr>
                    <th>Moment</th>
                    <th>Gebruiker</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Video</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentQualityEvents)): ?>
                    <tr><td colspan="6" class="ai-admin-table-empty">Nog geen quality events.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentQualityEvents as $row): ?>
                        <tr>
                            <td><?= e((string)$row['created_at']) ?></td>
                            <td><?= e((string)$row['user_name']) ?><?php if (!empty($row['user_id'])): ?> <small class="ai-admin-meta-muted">(#<?= (int)$row['user_id'] ?>)</small><?php endif; ?></td>
                            <td><code><?= e((string)$row['event_type']) ?></code></td>
                            <td><?= e((string)$row['status']) ?></td>
                            <td><?= e((string)($row['external_id'] ?? '')) ?></td>
                            <td><?= e((string)($row['payload_summary'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
