document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('[data-report-column-picker]');
    const table = document.querySelector('[data-report-columns-table]');
    if (!picker || !table) {
        return;
    }

    const toggleButton = picker.querySelector('[data-report-picker-toggle]');
    const panel = picker.querySelector('[data-report-picker-panel]');
    const backdrop = picker.querySelector('[data-report-picker-backdrop]');
    const checkboxes = Array.from(picker.querySelectorAll('[data-report-column-toggle]'));
    if (!toggleButton || !panel || !backdrop || checkboxes.length === 0) {
        return;
    }

    const visibleCountElement = picker.querySelector('[data-report-visible-count]');
    const totalCountElement = picker.querySelector('[data-report-total-count]');
    const teamId = picker.dataset.teamId || '0';
    const storageKey = `report_columns_v1_team_${teamId}`;

    const allColumns = checkboxes.map((input) => input.value);
    const defaultColumns = [...allColumns];
    const cellsByColumn = Object.fromEntries(
        allColumns.map((column) => [column, table.querySelectorAll(`[data-col-id="${column}"]`)])
    );

    function selectedColumns() {
        return checkboxes.filter((input) => input.checked).map((input) => input.value);
    }

    function normalizeColumns(candidate) {
        if (!Array.isArray(candidate)) {
            return [...defaultColumns];
        }

        const normalized = [];
        candidate.forEach((column) => {
            if (!allColumns.includes(column) || normalized.includes(column)) {
                return;
            }
            normalized.push(column);
        });

        return normalized.length > 0 ? normalized : [...defaultColumns];
    }

    function setOpen(isOpen) {
        picker.classList.toggle('is-open', isOpen);
        panel.hidden = !isOpen;
        backdrop.hidden = !isOpen;
        document.body.classList.toggle('report-column-picker-open', isOpen);
        toggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function saveColumns(columns) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(columns));
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }
    }

    function loadColumns() {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) {
                return [...defaultColumns];
            }
            return normalizeColumns(JSON.parse(raw));
        } catch (error) {
            return [...defaultColumns];
        }
    }

    function updateCountDisplay(visibleCount) {
        if (visibleCountElement) {
            visibleCountElement.textContent = String(visibleCount);
        }
        if (totalCountElement) {
            totalCountElement.textContent = String(allColumns.length);
        }
    }

    function applyColumns(columns, persist = true) {
        const visibleColumns = normalizeColumns(columns);
        const visibleSet = new Set(visibleColumns);

        allColumns.forEach((column) => {
            const isVisible = visibleSet.has(column);
            cellsByColumn[column].forEach((cell) => {
                cell.hidden = !isVisible;
            });
        });

        checkboxes.forEach((input) => {
            input.checked = visibleSet.has(input.value);
            input.disabled = visibleSet.size === 1 && input.checked;
            input.closest('.report-column-picker-option')?.classList.toggle('is-disabled', input.disabled);
        });

        updateCountDisplay(visibleSet.size);

        if (persist) {
            saveColumns(visibleColumns);
        }
    }

    picker.addEventListener('click', (event) => {
        const presetButton = event.target.closest('[data-report-preset]');
        if (presetButton) {
            applyColumns(defaultColumns);
            return;
        }

        if (event.target.closest('[data-report-picker-toggle]')) {
            setOpen(!picker.classList.contains('is-open'));
            return;
        }

        if (event.target.closest('[data-report-picker-close]')) {
            setOpen(false);
        }
    });

    picker.addEventListener('change', (event) => {
        const input = event.target.closest('[data-report-column-toggle]');
        if (!input) {
            return;
        }

        const visible = selectedColumns();
        if (visible.length === 0) {
            input.checked = true;
            return;
        }

        applyColumns(visible);
    });

    backdrop.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && picker.classList.contains('is-open')) {
            setOpen(false);
        }
    });

    applyColumns(loadColumns(), false);
});
