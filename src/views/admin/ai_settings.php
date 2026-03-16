<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">AI Module</h1>
    </div>
    <div class="app-bar-actions">
        <a class="btn btn-outline btn-sm btn-inline-icon" href="/admin/ai/usage">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
            Usage rapport
        </a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if (empty($hasEncryptionKey)): ?>
    <div class="alert alert-danger">
        Encryptiesleutel ontbreekt. Voeg <code>data/config.php</code> toe met een geldige <code>encryption_key</code>.
    </div>
<?php endif; ?>

<?php
$accessMode = (string)($settings['ai_access_mode'] ?? 'off');
$billingEnabled = (string)($settings['ai_billing_enabled'] ?? '1') === '1';
$budgetMode = (string)($settings['ai_budget_mode'] ?? 'monthly_per_user');
$monthlyBudget = $settings['ai_monthly_user_budget_eur'] ?? null;
$budgetResetDay = (int)($settings['ai_budget_reset_day'] ?? 1);
$rateLimitPerMinute = (int)($settings['ai_rate_limit_per_minute'] ?? 10);
$maxSessionsPerUser = (int)($settings['ai_max_sessions_per_user'] ?? 50);
$retrievalEnabled = (string)($settings['ai_retrieval_enabled'] ?? '1') === '1';
$retrievalYoutubeEnabled = (string)($settings['ai_retrieval_youtube_enabled'] ?? '1') === '1';
$retrievalMaxCandidates = (int)($settings['ai_retrieval_max_candidates'] ?? 10);
$retrievalMinYoutubeSources = (int)($settings['ai_retrieval_min_youtube_sources'] ?? 2);
$retrievalInternalLimit = (int)($settings['ai_retrieval_internal_limit'] ?? 2);
$ytDlpCookiesPath = (string)($settings['ai_ytdlp_cookies_path'] ?? '');
?>

<div class="card">
    <h2 class="ai-admin-card-title">AI Toegangsmodus</h2>
    <form action="/admin/ai/access-mode" method="POST" class="admin-inline-form">
        <?= Csrf::renderInput() ?>
        <div class="admin-form-field-sm">
            <label for="ai_access_mode">Modus</label>
            <select id="ai_access_mode" name="ai_access_mode" required>
                <option value="off" <?= $accessMode === 'off' ? 'selected' : '' ?>>Globaal uit</option>
                <option value="selective" <?= $accessMode === 'selective' ? 'selected' : '' ?>>Selectief per gebruiker</option>
                <option value="on" <?= $accessMode === 'on' ? 'selected' : '' ?>>Globaal aan</option>
            </select>
        </div>
        <div class="admin-form-action">
            <button type="submit" class="btn-icon-square" title="Opslaan" aria-label="Opslaan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            </button>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="ai-admin-card-title">API Keys</h2>
    <p class="ai-admin-card-subtitle">Sleutel wordt versleuteld in de database opgeslagen.</p>

    <div class="admin-settings-grid">
        <div class="admin-settings-panel">
            <h3>Inference key</h3>
            <p class="ai-admin-key-status">
                Status: <strong><?= $apiKeyMasked ? 'Ingesteld' : 'Niet ingesteld' ?></strong>
                <?php if ($apiKeyMasked): ?>
                    <small class="ai-admin-key-mask"><?= e($apiKeyMasked) ?></small>
                <?php endif; ?>
            </p>
            <form action="/admin/ai/api-key" method="POST" class="admin-input-action-row">
                <?= Csrf::renderInput() ?>
                <input type="password" name="api_key" placeholder="sk-or-..." autocomplete="new-password" <?= empty($hasEncryptionKey) ? 'disabled' : '' ?> required>
                <button type="submit" class="btn-icon-square" title="Opslaan" aria-label="Opslaan" <?= empty($hasEncryptionKey) ? 'disabled' : '' ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                </button>
            </form>
            <?php if ($apiKeyMasked): ?>
                <form action="/admin/ai/api-key/delete" method="POST">
                    <?= Csrf::renderInput() ?>
                    <button type="submit" class="btn btn-outline-danger btn-inline-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Verwijderen
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="admin-settings-panel">
            <h3>YouTube Data API key</h3>
            <p class="ai-admin-key-status">
                Status: <strong><?= $youtubeKeyMasked ? 'Ingesteld' : 'Niet ingesteld' ?></strong>
                <?php if ($youtubeKeyMasked): ?>
                    <small class="ai-admin-key-mask"><?= e($youtubeKeyMasked) ?></small>
                <?php endif; ?>
            </p>
            <form action="/admin/ai/youtube-key" method="POST" class="admin-input-action-row">
                <?= Csrf::renderInput() ?>
                <input type="password" name="youtube_api_key" placeholder="AIza..." autocomplete="new-password" <?= empty($hasEncryptionKey) ? 'disabled' : '' ?> required>
                <button type="submit" class="btn-icon-square" title="Opslaan" aria-label="Opslaan" <?= empty($hasEncryptionKey) ? 'disabled' : '' ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                </button>
            </form>
            <?php if ($youtubeKeyMasked): ?>
                <form action="/admin/ai/youtube-key/delete" method="POST">
                    <?= Csrf::renderInput() ?>
                    <button type="submit" class="btn btn-outline-danger btn-inline-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Verwijderen
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <h2 class="ai-admin-card-title">Modelbeheer</h2>
    <p class="ai-admin-card-subtitle">Geen losse defaults of vision-vinkjes meer: het systeem gebruikt automatisch het eerste publiceerbare model. Beeldanalyse pakt automatisch het eerste model met beeldondersteuning.</p>

    <form action="/admin/ai/models/create" method="POST" class="admin-grid-form admin-grid-form-model-create ai-admin-form-spaced">
        <?= Csrf::renderInput() ?>
        <div>
            <label for="new_model_id">Model ID</label>
            <input id="new_model_id" type="text" name="model_id" placeholder="bijv. openai/gpt-4o-mini" required>
        </div>
        <div>
            <label for="new_model_label">Label</label>
            <input id="new_model_label" type="text" name="label" placeholder="bijv. GPT-4o Mini" required>
        </div>
        <button type="submit" class="btn-icon-square" title="Model toevoegen" aria-label="Model toevoegen">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </button>
    </form>

    <div class="ai-admin-table-wrap">
        <table class="ai-admin-table">
            <thead>
                <tr>
                    <th class="ai-admin-cell-handle" aria-label="Sorteren"></th>
                    <th>Model ID</th>
                    <th>Label</th>
                    <th>Status</th>
                    <th class="ai-admin-cell-right">Acties</th>
                </tr>
            </thead>
            <tbody data-ai-model-sortable-body>
                <?php if (empty($models)): ?>
                    <tr><td colspan="5" class="ai-admin-table-empty">Nog geen modellen toegevoegd.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $model): ?>
                        <?php $modelFormId = 'model-update-' . (int)$model['id']; ?>
                        <tr data-model-id="<?= (int)$model['id'] ?>">
                            <td class="ai-admin-cell-handle">
                                <span class="ai-model-drag-handle" draggable="true" title="Sleep om te sorteren" aria-label="Sleep om te sorteren">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                        <line x1="5" y1="7" x2="19" y2="7"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <line x1="5" y1="17" x2="19" y2="17"></line>
                                    </svg>
                                </span>
                            </td>
                            <td><code><?= e($model['model_id']) ?></code></td>
                            <td class="ai-admin-cell-label">
                                <input form="<?= e($modelFormId) ?>" type="text" name="label" value="<?= e($model['label']) ?>" required>
                            </td>
                            <td>
                                <?php if (!empty($model['is_publishable'])): ?>
                                    <span class="ai-admin-status-badge is-ok">Publiceerbaar</span>
                                <?php else: ?>
                                    <span class="ai-admin-status-badge is-warn">Onvolledig</span>
                                <?php endif; ?>
                                <?= !empty($model['supports_vision']) ? '<span class="ai-admin-status-badge is-ok">Beeldanalyse</span>' : '<span class="ai-admin-status-badge is-muted">Tekst-only</span>' ?>
                            </td>
                            <td class="ai-admin-cell-right">
                                <form id="<?= e($modelFormId) ?>" action="/admin/ai/models/update" method="POST" class="ai-admin-inline-form">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="id" value="<?= (int)$model['id'] ?>">
                                    <button type="submit" class="btn-icon-square" title="Opslaan" aria-label="Opslaan">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                    </button>
                                </form>
                                <form action="/admin/ai/models/delete" method="POST" class="ai-admin-inline-form" onsubmit="return confirm('Weet je zeker dat je dit model wilt verwijderen?');">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="id" value="<?= (int)$model['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-inline-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        Verwijderen
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2 class="ai-admin-card-title">Prijsbeheer (EUR)</h2>
    <div class="ai-admin-table-wrap">
        <table class="ai-admin-table">
            <thead>
                <tr>
                    <th>Model</th>
                    <th>Input / 1M</th>
                    <th>Output / 1M</th>
                    <th>Flat</th>
                    <th>Min</th>
                    <th class="ai-admin-cell-center">Actief</th>
                    <th class="ai-admin-cell-right">Actie</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="7" class="ai-admin-table-empty">Voeg eerst modellen toe.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $model): ?>
                        <?php $pricingFormId = 'pricing-update-' . (int)$model['id']; ?>
                        <tr>
                            <td><code><?= e($model['model_id']) ?></code></td>
                            <td><input form="<?= e($pricingFormId) ?>" type="number" step="0.000001" min="0" name="input_price_per_mtoken" value="<?= e((string)($model['input_price_per_mtoken'] ?? '0')) ?>" required></td>
                            <td><input form="<?= e($pricingFormId) ?>" type="number" step="0.000001" min="0" name="output_price_per_mtoken" value="<?= e((string)($model['output_price_per_mtoken'] ?? '0')) ?>" required></td>
                            <td><input form="<?= e($pricingFormId) ?>" type="number" step="0.000001" min="0" name="request_flat_price" value="<?= e((string)($model['request_flat_price'] ?? '0')) ?>" required></td>
                            <td><input form="<?= e($pricingFormId) ?>" type="number" step="0.000001" min="0" name="min_request_price" value="<?= e((string)($model['min_request_price'] ?? '0')) ?>" required></td>
                            <td class="ai-admin-cell-center">
                                <input form="<?= e($pricingFormId) ?>" type="hidden" name="is_active" value="0">
                                <input form="<?= e($pricingFormId) ?>" type="checkbox" name="is_active" value="1" <?= !empty($model['is_active']) ? 'checked' : '' ?>>
                            </td>
                            <td class="ai-admin-cell-right">
                                <form id="<?= e($pricingFormId) ?>" action="/admin/ai/pricing/update" method="POST" class="ai-admin-inline-form">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="model_id" value="<?= e($model['model_id']) ?>">
                                    <input type="hidden" name="currency" value="EUR">
                                    <button type="submit" class="btn-icon-square" title="Opslaan" aria-label="Opslaan">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.querySelector('[data-ai-model-sortable-body]');
    if (!tbody) {
        return;
    }

    let draggingRow = null;

    tbody.querySelectorAll('.ai-model-drag-handle').forEach(function (handle) {
        handle.addEventListener('dragstart', function (event) {
            const row = handle.closest('tr[data-model-id]');
            if (!row) {
                return;
            }

            draggingRow = row;
            row.classList.add('ai-model-row-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', row.dataset.modelId || '');
            }
        });

        handle.addEventListener('dragend', function () {
            if (!draggingRow) {
                return;
            }

            draggingRow.classList.remove('ai-model-row-dragging');
            draggingRow = null;
            saveModelOrder();
        });
    });

    tbody.addEventListener('dragover', function (event) {
        if (!draggingRow) {
            return;
        }

        event.preventDefault();
        const afterRow = getDragAfterRow(tbody, event.clientY);
        if (afterRow === null) {
            tbody.appendChild(draggingRow);
        } else if (afterRow !== draggingRow) {
            tbody.insertBefore(draggingRow, afterRow);
        }
    });

    function getDragAfterRow(container, y) {
        const rows = Array.from(container.querySelectorAll('tr[data-model-id]:not(.ai-model-row-dragging)'));
        return rows.reduce(function (closest, row) {
            const box = row.getBoundingClientRect();
            const offset = y - box.top - (box.height / 2);
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: row };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    function saveModelOrder() {
        const ids = Array.from(tbody.querySelectorAll('tr[data-model-id]')).map(function (row) {
            return row.dataset.modelId;
        }).filter(Boolean);

        const csrfInput = document.querySelector('form[action="/admin/ai/models/create"] input[name="csrf_token"]');
        if (!csrfInput || !ids.length) {
            return;
        }

        const formData = new FormData();
        ids.forEach(function (id) {
            formData.append('ids[]', id);
        });
        formData.append('csrf_token', csrfInput.value);

        fetch('/admin/ai/models/reorder', {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data || data.success !== true) {
                alert('Opslaan van de modelvolgorde is mislukt.');
            }
        })
        .catch(function () {
            alert('Opslaan van de modelvolgorde is mislukt.');
        });
    }
});
</script>

<div class="card">
    <h2 class="ai-admin-card-title">Budgetinstellingen</h2>
    <form action="/admin/ai/budget" method="POST" class="admin-grid-form admin-grid-form-budget">
        <?= Csrf::renderInput() ?>

        <div class="admin-budget-toggle">
            <label class="admin-budget-toggle-label" for="ai_billing_enabled">
                <input type="hidden" name="ai_billing_enabled" value="0">
                <input id="ai_billing_enabled" type="checkbox" name="ai_billing_enabled" value="1" <?= $billingEnabled ? 'checked' : '' ?>>
                Billing inschakelen
            </label>
            <p class="admin-budget-toggle-help">Wanneer uit: alleen usage loggen, geen budgetblokkade.</p>
        </div>

        <div>
            <label for="ai_budget_mode">Budgetmodus</label>
            <select id="ai_budget_mode" name="ai_budget_mode">
                <option value="none" <?= $budgetMode === 'none' ? 'selected' : '' ?>>Geen budget</option>
                <option value="monthly_per_user" <?= $budgetMode === 'monthly_per_user' ? 'selected' : '' ?>>Maandelijks per gebruiker</option>
            </select>
        </div>

        <div>
            <label for="ai_monthly_user_budget_eur">Maandbudget per gebruiker (EUR)</label>
            <input id="ai_monthly_user_budget_eur" type="number" step="0.01" min="0" name="ai_monthly_user_budget_eur" value="<?= e((string)($monthlyBudget ?? '')) ?>" placeholder="Leeg = onbeperkt">
        </div>

        <div>
            <label for="ai_budget_reset_day">Resetdag (1-28)</label>
            <input id="ai_budget_reset_day" type="number" min="1" max="28" name="ai_budget_reset_day" value="<?= $budgetResetDay ?>" required>
        </div>

        <div>
            <label for="ai_rate_limit_per_minute">Rate limit per minuut</label>
            <input id="ai_rate_limit_per_minute" type="number" min="1" name="ai_rate_limit_per_minute" value="<?= $rateLimitPerMinute ?>" required>
        </div>

        <div>
            <label for="ai_max_sessions_per_user">Max chat sessies per gebruiker</label>
            <input id="ai_max_sessions_per_user" type="number" min="1" name="ai_max_sessions_per_user" value="<?= $maxSessionsPerUser ?>" required>
        </div>

        <div class="admin-form-actions-full">
            <button type="submit" class="btn-icon-square" title="Budgetinstellingen opslaan" aria-label="Budgetinstellingen opslaan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            </button>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="ai-admin-card-title">Retrievalinstellingen</h2>
    <form action="/admin/ai/retrieval" method="POST" class="admin-grid-form admin-grid-form-budget">
        <?= Csrf::renderInput() ?>

        <div class="admin-budget-toggle">
            <label class="admin-budget-toggle-label" for="ai_retrieval_enabled">
                <input type="hidden" name="ai_retrieval_enabled" value="0">
                <input id="ai_retrieval_enabled" type="checkbox" name="ai_retrieval_enabled" value="1" <?= $retrievalEnabled ? 'checked' : '' ?>>
                Retrieval inschakelen
            </label>
        </div>

        <div class="admin-budget-toggle">
            <label class="admin-budget-toggle-label" for="ai_retrieval_youtube_enabled">
                <input type="hidden" name="ai_retrieval_youtube_enabled" value="0">
                <input id="ai_retrieval_youtube_enabled" type="checkbox" name="ai_retrieval_youtube_enabled" value="1" <?= $retrievalYoutubeEnabled ? 'checked' : '' ?>>
                YouTube retrieval verplicht
            </label>
            <p class="admin-budget-toggle-help">Bij onvoldoende YouTube-bronnen wordt de AI-call geblokkeerd.</p>
        </div>

        <div>
            <label for="ai_retrieval_max_candidates">Max kandidaten per bron (1-20)</label>
            <input id="ai_retrieval_max_candidates" type="number" min="1" max="20" name="ai_retrieval_max_candidates" value="<?= $retrievalMaxCandidates ?>" required>
        </div>

        <div>
            <label for="ai_retrieval_min_youtube_sources">Min. YouTube bronnen in output (1-3)</label>
            <input id="ai_retrieval_min_youtube_sources" type="number" min="1" max="3" name="ai_retrieval_min_youtube_sources" value="<?= $retrievalMinYoutubeSources ?>" required>
        </div>

        <div>
            <label for="ai_retrieval_internal_limit">Max interne bronnen in output (0-3)</label>
            <input id="ai_retrieval_internal_limit" type="number" min="0" max="3" name="ai_retrieval_internal_limit" value="<?= $retrievalInternalLimit ?>" required>
        </div>

        <div class="admin-form-actions-full">
            <label for="ai_ytdlp_cookies_path">Optioneel `cookies.txt`-pad voor authenticated yt-dlp</label>
            <input id="ai_ytdlp_cookies_path" type="text" name="ai_ytdlp_cookies_path" value="<?= e($ytDlpCookiesPath) ?>" placeholder="/secure/yt-dlp/cookies.txt">
            <p class="admin-budget-toggle-help">Gebruik een expliciet beheerd serverpad. Dit wordt alleen gebruikt als anonieme yt-dlp-toegang faalt door authenticatie.</p>
        </div>

        <div class="admin-form-actions-full">
            <button type="submit" class="btn-icon-square" title="Retrievalinstellingen opslaan" aria-label="Retrievalinstellingen opslaan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            </button>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="ai-admin-card-title">Korte usage samenvatting</h2>
    <div class="ai-admin-stats-grid">
        <div class="ai-admin-stat-card">
            <span class="ai-admin-stat-value"><?= number_format((int)$usageSummary['total_calls']) ?></span>
            <span class="ai-admin-stat-label">Totaal calls</span>
        </div>
        <div class="ai-admin-stat-card">
            <span class="ai-admin-stat-value"><?= number_format((int)$usageSummary['total_tokens']) ?></span>
            <span class="ai-admin-stat-label">Totaal tokens</span>
        </div>
        <div class="ai-admin-stat-card">
            <span class="ai-admin-stat-value">$<?= number_format((float)$usageSummary['total_supplier_cost_usd'], 4) ?></span>
            <span class="ai-admin-stat-label">Supplier cost (USD)</span>
        </div>
        <div class="ai-admin-stat-card">
            <span class="ai-admin-stat-value">€<?= number_format((float)$usageSummary['total_billable_cost_eur'], 4) ?></span>
            <span class="ai-admin-stat-label">Billable (EUR)</span>
        </div>
    </div>
</div>
