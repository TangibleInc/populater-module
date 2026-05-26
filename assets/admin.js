/**
 * Tangible Populator — Admin UI
 *
 * Communicates with the REST API to start/cancel seeding processes and
 * displays a live progress bar + log tail.
 */
(function () {
    'use strict';

    const { restUrl, nonce } = window.tangiblePopulater || {};

    const apiFetch = (path, options = {}) =>
        fetch(`${restUrl}${path}`, {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            ...options,
        }).then((r) => r.json());

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    let currentProcessId = null;
    let pollTimer = null;

    // -------------------------------------------------------------------------
    // DOM helpers
    // -------------------------------------------------------------------------

    const el = (id) => document.getElementById(id);

    const setProgress = (processed, total) => {
        const pct = total > 0 ? Math.round((processed / total) * 100) : 0;
        el('tp-progress-bar').value = pct;
        el('tp-progress-text').textContent = `${processed} / ${total} (${pct}%)`;
    };

    const appendLog = (entries) => {
        const pre = el('tp-log-output');
        entries.forEach(({ timestamp, level, message }) => {
            const date = new Date(timestamp * 1000).toLocaleTimeString();
            pre.textContent += `[${date}] [${level.toUpperCase()}] ${message}\n`;
        });
        pre.scrollTop = pre.scrollHeight;
    };

    const setRunning = (running) => {
        el('tp-start-btn').style.display = running ? 'none' : '';
        el('tp-cancel-btn').style.display = running ? '' : 'none';
        el('tp-progress-wrap').style.display = running ? '' : 'none';
        el('tp-log-card').style.display = running ? '' : 'none';
    };

    // -------------------------------------------------------------------------
    // Polling
    // -------------------------------------------------------------------------

    let lastLogCount = 0;

    const poll = () => {
        if (!currentProcessId) return;

        Promise.all([
            apiFetch(`/seed/${currentProcessId}/status`),
            apiFetch(`/seed/${currentProcessId}/logs`),
        ]).then(([status, logsData]) => {
            setProgress(status.processed, status.total);

            const newLogs = (logsData.logs || []).slice(lastLogCount);
            if (newLogs.length) {
                appendLog(newLogs);
                lastLogCount += newLogs.length;
            }

            const terminal = ['completed', 'cancelled', 'failed'];
            if (terminal.includes(status.status)) {
                clearInterval(pollTimer);
                setRunning(false);
                currentProcessId = null;
                lastLogCount = 0;
            }
        });
    };

    // -------------------------------------------------------------------------
    // Event listeners
    // -------------------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', () => {
        const startBtn  = el('tp-start-btn');
        const cancelBtn = el('tp-cancel-btn');
        const resetBtn  = el('tp-reset-btn');

        if (startBtn) {
            startBtn.addEventListener('click', () => {
                const plugin  = el('tp-plugin').value;
                const courses = parseInt(el('tp-courses').value, 10);
                const lessons = parseInt(el('tp-lessons').value, 10);
                const quizzes = parseInt(el('tp-quizzes').value, 10);
                const users   = parseInt(el('tp-users').value, 10);

                el('tp-log-output').textContent = '';
                lastLogCount = 0;
                setRunning(true);

                apiFetch('/seed', {
                    method: 'POST',
                    body: JSON.stringify({
                        plugin,
                        courses,
                        lessons_per_course: lessons,
                        quizzes_per_lesson: quizzes,
                        users,
                    }),
                }).then((data) => {
                    if (data.process_id) {
                        currentProcessId = data.process_id;
                        pollTimer = setInterval(poll, 1500);
                    } else {
                        setRunning(false);
                        alert(data.message || 'Failed to start seeding.');
                    }
                });
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                if (!currentProcessId) return;
                apiFetch(`/seed/${currentProcessId}/cancel`, { method: 'POST' }).then(() => {
                    clearInterval(pollTimer);
                    setRunning(false);
                    currentProcessId = null;
                });
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                const confirmed = window.confirm(
                    'WARNING: This will permanently delete ALL database tables and re-install WordPress.\n\nAre you absolutely sure?'
                );
                if (!confirmed) return;

                resetBtn.disabled = true;
                resetBtn.textContent = 'Resetting…';

                apiFetch('/reset', {
                    method: 'POST',
                    body: JSON.stringify({ confirmed: true }),
                }).then((data) => {
                    alert(data.message || 'Done.');
                    resetBtn.disabled = false;
                    resetBtn.textContent = 'Reset Database';
                });
            });
        }
    });
})();
