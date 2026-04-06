<div class="app-bar">
    <div class="app-bar-start">
        <a href="/" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Mijn Account</h1>
    </div>
</div>

<div class="tb-card" id="ai-usage-card">
    <h2>AI Verbruik</h2>
    <p class="text-muted" id="ai-usage-loading">Laden...</p>
    <div id="ai-usage-summary" class="tb-account-ai-summary"></div>
    <div id="ai-usage-history-wrap" class="tb-account-ai-history">
        <h3 class="tb-account-ai-history-title">Recente calls</h3>
        <div class="tb-table-wrap">
            <table class="tb-table">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th>Tokens</th>
                        <th>Kosten (EUR)</th>
                    </tr>
                </thead>
                <tbody id="ai-usage-history-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
    const loadingEl = document.getElementById('ai-usage-loading');
    const summaryEl = document.getElementById('ai-usage-summary');
    const historyWrapEl = document.getElementById('ai-usage-history-wrap');
    const historyBodyEl = document.getElementById('ai-usage-history-body');

    const setError = function (message) {
        loadingEl.textContent = message;
        loadingEl.classList.add('tb-account-ai-error');
    };

    try {
        const [summaryRes, historyRes] = await Promise.all([
            fetch('/ai/usage/summary'),
            fetch('/ai/usage/history?per_page=10')
        ]);

        const summaryJson = await summaryRes.json();
        const historyJson = await historyRes.json();

        if (!summaryRes.ok || summaryJson.ok === false) {
            throw new Error(summaryJson.error || 'Kon AI samenvatting niet laden.');
        }
        if (!historyRes.ok || historyJson.ok === false) {
            throw new Error(historyJson.error || 'Kon AI historie niet laden.');
        }

        const summary = summaryJson.summary;
        const formatMoney = function (value) {
            if (value === null || value === undefined) return 'onbeperkt';
            return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(value));
        };

        const summaryLines = [];
        summaryLines.push('Periode: ' + summary.period_start.substring(0, 10) + ' t/m ' + summary.period_end.substring(0, 10));
        summaryLines.push('Tokens: ' + Number(summary.total_tokens || 0).toLocaleString('nl-NL'));
        if (summary.billing_enabled) {
            summaryLines.push('Kosten: ' + formatMoney(summary.billable_cost_eur || 0));
            if (summary.budget_eur !== null) {
                summaryLines.push('Budget: ' + formatMoney(summary.budget_eur));
                summaryLines.push('Resterend: ' + formatMoney(summary.remaining_budget_eur));
            } else {
                summaryLines.push('Budget: onbeperkt');
            }
        }

        summaryEl.innerHTML = summaryLines.map(function (line) {
            return '<div>' + line + '</div>';
        }).join('');

        const rows = historyJson.events || [];
        const escapeHtml = function (value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };
        if (rows.length === 0) {
            historyBodyEl.innerHTML = '<tr><td colspan=\"5\" class=\"tb-table-empty\">Nog geen AI usage events.</td></tr>';
        } else {
            historyBodyEl.innerHTML = rows.map(function (row) {
                const status = row.status || '-';
                const tokenCount = Number(row.total_tokens || 0).toLocaleString('nl-NL');
                const billable = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(row.billable_cost_eur || 0));
                return '<tr>' +
                    '<td>' + escapeHtml(String(row.created_at || '').substring(0, 16)) + '</td>' +
                    '<td><code>' + escapeHtml(row.model_id || '-') + '</code></td>' +
                    '<td>' + escapeHtml(status) + '</td>' +
                    '<td>' + tokenCount + '</td>' +
                    '<td>' + billable + '</td>' +
                    '</tr>';
            }).join('');
        }

        loadingEl.style.display = 'none';
        summaryEl.style.display = '';
        historyWrapEl.style.display = '';
    } catch (error) {
        setError(error.message || 'Kon AI verbruik niet laden.');
    }
});
</script>

<?php if (!empty($success)): ?>
    <div class="tb-alert tb-alert--success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="tb-alert tb-alert--danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Blok 1: Profiel -->
    <div class="tb-card">
        <h2>Profiel</h2>
        <form action="/account/update-profile" method="POST">
            <?= Csrf::renderInput() ?>
            
            <div class="form-group">
                <label>Gebruikersnaam</label>
                <input type="text" value="<?= e($user['username']) ?>" disabled class="form-control tb-account-disabled-input">
                <small class="text-muted">Je gebruikersnaam kan niet gewijzigd worden.</small>
            </div>

            <div class="form-group">
                <label for="name">Naam</label>
                <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Mijn Rollen</label>
                <ul class="list-group">
                    <?php foreach ($teams as $team): ?>
                        <?php
                            $roleParts = [];
                            if (!empty($team['is_coach'])) $roleParts[] = 'Coach';
                            if (!empty($team['is_trainer'])) $roleParts[] = 'Trainer';
                            $roleString = implode(' & ', $roleParts);
                        ?>
                        <li class="list-group-item">
                            <strong><?= e($team['name']) ?></strong>: 
                            <span class="badge"><?= e($roleString) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($teams)): ?>
                        <li class="list-group-item text-muted">Je bent nog geen lid van een team.</li>
                    <?php endif; ?>
                </ul>
                <small class="text-muted">Rollen kunnen alleen door een team-beheerder gewijzigd worden.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="tb-button tb-button--primary">Opslaan</button>
            </div>
        </form>
    </div>

    <!-- Blok 2: Beveiliging -->
    <div class="tb-card">
        <h2>Wachtwoord Wijzigen</h2>
        <form action="/account/update-password" method="POST">
            <?= Csrf::renderInput() ?>
            
            <div class="form-group">
                <label for="current_password">Huidig wachtwoord</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="new_password">Nieuw wachtwoord</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                <small class="text-muted">Minimaal 8 tekens.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Bevestig nieuw wachtwoord</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
            </div>

            <div class="form-actions">
                <button type="submit" class="tb-button tb-button--primary">Wachtwoord Wijzigen</button>
            </div>
        </form>
    </div>
</div>
