/**
 * Tangible Populator — Admin UI
 *
 * Communicates with the REST API to start/cancel seeding processes and
 * displays a live progress bar + log tail.
 */
(function () {
    'use strict';

    const { restUrl, nonce } = window.tangiblePopulater || {};

    const apiFetch = async (path, options = {}) => {
        const response = await fetch(`${restUrl}${path}`, {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            ...options,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = data.message || data.error || `Request failed (${response.status})`;
            const err = new Error(message);
            err.data = data;
            err.status = response.status;
            throw err;
        }

        return data;
    };

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

    const showStatusMessage = (message, isError = false) => {
        const statusEl = el('tp-status-message');
        if (!statusEl) {
            if (isError) {
                alert(message);
            }
            return;
        }
        statusEl.textContent = message;
        statusEl.className = isError ? 'notice notice-error' : 'notice notice-info';
        statusEl.style.display = message ? '' : 'none';
    };

    // -------------------------------------------------------------------------
    // Polling
    // -------------------------------------------------------------------------

    let lastLogCount = 0;

    const poll = async () => {
        if (!currentProcessId) return;

        try {
            const [status, logsData] = await Promise.all([
                apiFetch(`/seed/${currentProcessId}/status`),
                apiFetch(`/seed/${currentProcessId}/logs`),
            ]);

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

                if (status.status === 'failed') {
                    showStatusMessage(status.error || 'Seeding failed. See log for details.', true);
                } else if (status.status === 'cancelled') {
                    showStatusMessage('Seeding was cancelled.');
                } else {
                    showStatusMessage('Seeding completed successfully.');
                }
            }
        } catch (err) {
            clearInterval(pollTimer);
            setRunning(false);
            showStatusMessage(err.message || 'Failed to fetch status.', true);
        }
    };

    // -------------------------------------------------------------------------
    // Event listeners
    // -------------------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', () => {
        const startBtn  = el('tp-start-btn');
        const cancelBtn = el('tp-cancel-btn');
        const resetBtn  = el('tp-reset-btn');

        if (startBtn) {
            startBtn.addEventListener('click', async () => {
                const plugin  = el('tp-plugin').value;
                const courses = parseInt(el('tp-courses').value, 10);
                const lessons = parseInt(el('tp-lessons').value, 10);
                const quizzes = parseInt(el('tp-quizzes').value, 10);
                const users   = parseInt(el('tp-users').value, 10);

                el('tp-log-output').textContent = '';
                lastLogCount = 0;
                showStatusMessage('');
                setRunning(true);

                try {
                    const data = await apiFetch('/seed', {
                        method: 'POST',
                        body: JSON.stringify({
                            plugin,
                            courses,
                            lessons_per_course: lessons,
                            quizzes_per_lesson: quizzes,
                            users,
                        }),
                    });

                    if (data.process_id) {
                        currentProcessId = data.process_id;
                        pollTimer = setInterval(poll, 1500);
                    } else {
                        setRunning(false);
                        showStatusMessage(data.message || 'Failed to start seeding.', true);
                    }
                } catch (err) {
                    setRunning(false);
                    showStatusMessage(err.message || 'Failed to start seeding.', true);
                }
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', async () => {
                if (!currentProcessId) return;
                try {
                    await apiFetch(`/seed/${currentProcessId}/cancel`, { method: 'POST' });
                } catch (err) {
                    showStatusMessage(err.message || 'Failed to cancel.', true);
                } finally {
                    clearInterval(pollTimer);
                    setRunning(false);
                    currentProcessId = null;
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                const confirmed = window.confirm(
                    'WARNING: This will delete all posts, non-admin users, plugin/LMS data, and non-core options.\n\nAdministrator accounts, active plugins, and the active theme will be kept.\n\nContinue?'
                );
                if (!confirmed) return;

                resetBtn.disabled = true;
                resetBtn.textContent = 'Resetting…';

                apiFetch('/reset', {
                    method: 'POST',
                    body: JSON.stringify({ confirmed: true }),
                })
                    .then((data) => {
                        alert(data.message || 'Done.');
                    })
                    .catch((err) => {
                        alert(err.message || 'Reset failed.');
                    })
                    .finally(() => {
                        resetBtn.disabled = false;
                        resetBtn.textContent = 'Reset Database';
                    });
            });
        }
    });
})();
