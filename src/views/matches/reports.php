<div class="app-bar">
    <div class="app-bar-start">
        <a href="/matches" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Rapportage</h1>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom: 1rem;">Teamoverzicht</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem;">
        <div style="padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius); background: #fafafa;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">Wedstrijden</div>
            <div style="font-size: 1.7rem; font-weight: 700; line-height: 1.2;"><?= (int)($summary['total_matches'] ?? 0) ?></div>
        </div>
        <div style="padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius); background: #fafafa;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">Doelpunten</div>
            <div style="font-size: 1.7rem; font-weight: 700; line-height: 1.2;"><?= (int)($summary['total_goals'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom: 1rem;">Spelerstatistieken</h3>

    <?php if (empty($playerStats)): ?>
        <p>Er zijn nog geen spelers beschikbaar voor rapportage.</p>
    <?php else: ?>
        <?php
            $currentSort = $currentSort ?? 'matches';
            $currentDir = $currentDir ?? 'desc';
            $defaultDirs = [
                'name' => 'asc',
                'matches' => 'desc',
                'absent' => 'desc',
                'starts' => 'desc',
                'goals' => 'desc',
                'keepers' => 'desc',
            ];

            $buildSortUrl = static function(string $column) use ($currentSort, $currentDir, $defaultDirs): string {
                $nextDir = ($currentSort === $column)
                    ? ($currentDir === 'asc' ? 'desc' : 'asc')
                    : ($defaultDirs[$column] ?? 'desc');
                return '/matches/reports?sort=' . urlencode($column) . '&dir=' . urlencode($nextDir);
            };

            $sortIndicator = static function(string $column) use ($currentSort, $currentDir): string {
                if ($currentSort !== $column) {
                    return '';
                }
                return $currentDir === 'asc' ? ' ↑' : ' ↓';
            };
        ?>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.75rem;">
            Wedstrijden per speler = totaal teamwedstrijden - aantal keer afwezig.
        </p>
        <div class="table-responsive">
            <table class="report-table" style="width: 100%; border-collapse: collapse; font-size: 0.95rem; table-layout: fixed;">
                <colgroup>
                    <col style="width: 40%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                </colgroup>
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 0.6rem 0.4rem;">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('name')) ?>" title="Speler" style="color: inherit; text-decoration: none;">
                                <span>Speler</span><?= $sortIndicator('name') ?>
                            </a>
                        </th>
                        <th style="padding: 0.6rem 0.4rem; text-align: right;">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('matches')) ?>" title="Wedstrijden" style="color: inherit; text-decoration: none;">
                                <span class="report-label-full">Wedstrijden</span><span class="report-label-short">Weds.</span><?= $sortIndicator('matches') ?>
                            </a>
                        </th>
                        <th style="padding: 0.6rem 0.4rem; text-align: right;">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('absent')) ?>" title="Afwezig" style="color: inherit; text-decoration: none;">
                                <span class="report-label-full">Afwezig</span><span class="report-label-short">Afw.</span><?= $sortIndicator('absent') ?>
                            </a>
                        </th>
                        <th style="padding: 0.6rem 0.4rem; text-align: right;">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('starts')) ?>" title="Basis" style="color: inherit; text-decoration: none;">
                                <span class="report-label-full">Basis</span><span class="report-label-short">Bas.</span><?= $sortIndicator('starts') ?>
                            </a>
                        </th>
                        <th style="padding: 0.6rem 0.4rem; text-align: right;">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('goals')) ?>" title="Goals" style="color: inherit; text-decoration: none;">
                                <span class="report-label-full">Goals</span><span class="report-label-short">Gls.</span><?= $sortIndicator('goals') ?>
                            </a>
                        </th>
                        <th style="padding: 0.6rem 0.4rem; text-align: right;">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('keepers')) ?>" title="Aangewezen keeper" style="color: inherit; text-decoration: none;">
                                <span class="report-label-full">Keeper</span><span class="report-label-short">gk</span><?= $sortIndicator('keepers') ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($playerStats as $stat): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 0.55rem 0.4rem;">
                                <strong><?= e($stat['name']) ?></strong>
                            </td>
                            <td style="padding: 0.55rem 0.4rem; text-align: right; font-weight: 600;"><?= (int)$stat['matches_played'] ?></td>
                            <td style="padding: 0.55rem 0.4rem; text-align: right; font-weight: 600;"><?= (int)$stat['absent_matches'] ?></td>
                            <td style="padding: 0.55rem 0.4rem; text-align: right; font-weight: 600;"><?= (int)$stat['starts'] ?></td>
                            <td style="padding: 0.55rem 0.4rem; text-align: right; font-weight: 600;"><?= (int)$stat['goals'] ?></td>
                            <td style="padding: 0.55rem 0.4rem; text-align: right; font-weight: 600;"><?= (int)$stat['keeper_selections'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
