<div class="app-bar">
    <div class="app-bar-start">
        <a href="/matches" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Rapportage</h1>
    </div>
</div>

<div class="card">
    <h3 class="report-section-title">Teamoverzicht</h3>
    <div class="report-summary-grid">
        <div class="report-summary-item">
            <div class="report-summary-label">Wedstrijden</div>
            <div class="report-summary-value"><?= (int)($summary['total_matches'] ?? 0) ?></div>
        </div>
        <div class="report-summary-item">
            <div class="report-summary-label">Doelpunten</div>
            <div class="report-summary-value"><?= (int)($summary['total_goals'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <?php
        $columnPickerLabels = [
            'matches' => 'Wedstrijden',
            'absent' => 'Afwezig',
            'starts' => 'Basis',
            'goals' => 'Goals',
            'goal_matches' => 'Wedstr. gescoord',
            'keepers' => 'Keeper',
        ];
        $columnCount = count($columnPickerLabels);
    ?>
    <div class="report-card-header">
        <h3>Spelerstatistieken</h3>
        <?php if (!empty($playerStats)): ?>
            <div class="report-column-picker" data-report-column-picker data-team-id="<?= (int)(Session::get('current_team')['id'] ?? 0) ?>">
                <button
                    type="button"
                    class="btn btn-sm btn-outline report-column-picker-toggle"
                    data-report-picker-toggle
                    aria-haspopup="dialog"
                    aria-expanded="false"
                    aria-controls="report-column-picker-panel"
                >
                    <span>Kolommen</span>
                    <span><span data-report-visible-count><?= (int)$columnCount ?></span>/<span data-report-total-count><?= (int)$columnCount ?></span></span>
                    <svg class="report-column-picker-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <div
                    id="report-column-picker-panel"
                    class="report-column-picker-panel"
                    data-report-picker-panel
                    role="dialog"
                    aria-label="Kolommen kiezen"
                    hidden
                >
                    <div class="report-column-picker-actions">
                        <button type="button" class="btn btn-sm btn-outline" data-report-preset="reset">Reset</button>
                    </div>
                    <div class="report-column-picker-options">
                        <?php foreach ($columnPickerLabels as $columnId => $label): ?>
                            <label class="report-column-picker-option">
                                <input type="checkbox" value="<?= e($columnId) ?>" data-report-column-toggle checked>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="report-column-picker-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-report-picker-close>Sluiten</button>
                    </div>
                </div>
                <div class="report-column-picker-backdrop" data-report-picker-backdrop hidden></div>
            </div>
        <?php endif; ?>
    </div>

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
                'goal_matches' => 'desc',
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
        <div class="table-responsive">
            <table class="report-table" data-report-columns-table>
                <colgroup>
                    <col data-col-id="name" class="report-col-name">
                    <col data-col-id="matches" class="report-col-compact">
                    <col data-col-id="absent" class="report-col-compact">
                    <col data-col-id="starts" class="report-col-compact">
                    <col data-col-id="goals" class="report-col-compact">
                    <col data-col-id="goal_matches" class="report-col-compact">
                    <col data-col-id="keepers" class="report-col-compact">
                </colgroup>
                <thead>
                    <tr class="report-table-head-row">
                        <th data-col-id="name" class="report-th">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('name')) ?>" title="Speler">
                                <span>Speler</span><?= $sortIndicator('name') ?>
                            </a>
                        </th>
                        <th data-col-id="matches" class="report-th report-th-right">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('matches')) ?>" title="Wedstrijden">
                                <span class="report-label-full">Wedstrijden</span><span class="report-label-short">Weds.</span><?= $sortIndicator('matches') ?>
                            </a>
                        </th>
                        <th data-col-id="absent" class="report-th report-th-right">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('absent')) ?>" title="Afwezig">
                                <span class="report-label-full">Afwezig</span><span class="report-label-short">Afw.</span><?= $sortIndicator('absent') ?>
                            </a>
                        </th>
                        <th data-col-id="starts" class="report-th report-th-right">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('starts')) ?>" title="Basis">
                                <span class="report-label-full">Basis</span><span class="report-label-short">Bas.</span><?= $sortIndicator('starts') ?>
                            </a>
                        </th>
                        <th data-col-id="goals" class="report-th report-th-right">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('goals')) ?>" title="Goals">
                                <span class="report-label-full">Goals</span><span class="report-label-short">Gls.</span><?= $sortIndicator('goals') ?>
                            </a>
                        </th>
                        <th data-col-id="goal_matches" class="report-th report-th-right">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('goal_matches')) ?>" title="Unieke wedstrijden met minimaal 1 doelpunt">
                                <span class="report-label-full">Wedstr. gescoord</span><span class="report-label-short">W+G</span><?= $sortIndicator('goal_matches') ?>
                            </a>
                        </th>
                        <th data-col-id="keepers" class="report-th report-th-right">
                            <a class="report-sort-link" href="<?= e($buildSortUrl('keepers')) ?>" title="Aangewezen keeper">
                                <span class="report-label-full">Keeper</span><span class="report-label-short">gk</span><?= $sortIndicator('keepers') ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($playerStats as $stat): ?>
                        <tr class="report-table-row">
                            <td data-col-id="name" class="report-td">
                                <strong><?= e($stat['name']) ?></strong>
                            </td>
                            <td data-col-id="matches" class="report-td report-td-right"><?= (int)$stat['matches_played'] ?></td>
                            <td data-col-id="absent" class="report-td report-td-right"><?= (int)$stat['absent_matches'] ?></td>
                            <td data-col-id="starts" class="report-td report-td-right"><?= (int)$stat['starts'] ?></td>
                            <td data-col-id="goals" class="report-td report-td-right"><?= (int)$stat['goals'] ?></td>
                            <td data-col-id="goal_matches" class="report-td report-td-right"><?= (int)$stat['goal_matches'] ?></td>
                            <td data-col-id="keepers" class="report-td report-td-right"><?= (int)$stat['keeper_selections'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="/js/reports-columns.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/reports-columns.js') ?>"></script>
