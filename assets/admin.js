/**
 * Tangible Populator — Admin UI
 *
 * Communicates with the REST API to start/cancel seeding processes and
 * displays a live progress bar + log tail.
 */
(function () {
    'use strict';

    const { restUrl, nonce, activeProcess: initialActiveProcess, defaultPassword } = window.tangiblePopulater || {};
    const PROCESS_STORAGE_KEY = 'tangiblePopulater.processId';

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

    const rememberProcessId = (processId) => {
        try {
            sessionStorage.setItem(PROCESS_STORAGE_KEY, processId);
        } catch (err) {
            // Ignore storage failures (private mode, quota, etc.).
        }
    };

    const forgetProcessId = () => {
        try {
            sessionStorage.removeItem(PROCESS_STORAGE_KEY);
        } catch (err) {
            // Ignore storage failures.
        }
    };

    const isActiveStatus = (status) => ['pending', 'running'].includes(status);

    const initPasswordField = () => {
        const input = el('tp-user-password');
        const copyBtn = el('tp-password-copy');

        if (!input || input.dataset.tpInitialized === '1') {
            return;
        }

        input.dataset.tpInitialized = '1';

        if (!input.value.trim()) {
            input.value = defaultPassword || '';
        }

        if (copyBtn && copyBtn.dataset.tpBound !== '1') {
            copyBtn.dataset.tpBound = '1';
            copyBtn.addEventListener('click', async () => {
                const password = input.value;

                try {
                    if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(password);
                    } else {
                        input.select();
                        document.execCommand('copy');
                    }

                    copyBtn.textContent = 'Copied!';
                    setTimeout(() => {
                        copyBtn.textContent = 'Copy';
                    }, 1500);
                } catch (err) {
                    alert('Could not copy password.');
                }
            });
        }
    };

    // -------------------------------------------------------------------------
    // Polling
    // -------------------------------------------------------------------------

    let lastLogCount = 0;

    const startPolling = (processId) => {
        if (pollTimer) {
            clearInterval(pollTimer);
        }

        currentProcessId = processId;
        rememberProcessId(processId);
        setRunning(true);
        pollTimer = setInterval(poll, 1500);
        poll();
    };

    const stopPolling = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        currentProcessId = null;
        lastLogCount = 0;
        forgetProcessId();
        setRunning(false);
    };

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
                stopPolling();

                if (status.status === 'failed') {
                    showStatusMessage(status.error || 'Seeding failed. See log for details.', true);
                } else if (status.status === 'cancelled') {
                    showStatusMessage('Seeding was cancelled.');
                } else {
                    showStatusMessage('Seeding completed successfully.');
                }
            }
        } catch (err) {
            stopPolling();
            showStatusMessage(err.message || 'Failed to fetch status.', true);
        }
    };

    const resolveActiveProcess = async () => {
        if (initialActiveProcess?.id && isActiveStatus(initialActiveProcess.status)) {
            return initialActiveProcess;
        }

        let storedId = null;
        try {
            storedId = sessionStorage.getItem(PROCESS_STORAGE_KEY);
        } catch (err) {
            storedId = null;
        }

        if (storedId) {
            try {
                const status = await apiFetch(`/seed/${storedId}/status`);
                if (isActiveStatus(status.status)) {
                    return status;
                }
                forgetProcessId();
            } catch (err) {
                forgetProcessId();
            }
        }

        try {
            const data = await apiFetch('/seed/active');
            if (data.process?.id && isActiveStatus(data.process.status)) {
                return data.process;
            }
        } catch (err) {
            // Fall through silently; page still works for starting new seeds.
        }

        return null;
    };

    const resumeActiveProcess = async () => {
        const active = await resolveActiveProcess();

        if (!active?.id) {
            return;
        }

        setProgress(active.processed, active.total);
        startPolling(active.id);
    };

    // -------------------------------------------------------------------------
    // Event listeners
    // -------------------------------------------------------------------------

    const init = () => {
        const startBtn  = el('tp-start-btn');
        const cancelBtn = el('tp-cancel-btn');
        const resetBtn  = el('tp-reset-btn');

        if (startBtn) {
            startBtn.addEventListener('click', async () => {
                const plugin  = el('tp-plugin').value;
                const courses = parseInt(el('tp-courses').value, 10);
                const lessons = parseInt(el('tp-lessons').value, 10);
                const quizzes = parseInt(el('tp-quizzes').value, 10);
                const questions = parseInt(el('tp-questions').value, 10);
                const users   = parseInt(el('tp-users').value, 10);
                const groups  = parseInt(el('tp-groups').value, 10);
                const userPassword = el('tp-user-password')?.value || '';

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
                            quizzes_per_section: quizzes,
                            questions_per_quiz: questions,
                            users,
                            groups,
                            user_password: userPassword,
                        }),
                    });

                    if (data.process_id) {
                        startPolling(data.process_id);
                    } else {
                        stopPolling();
                        showStatusMessage(data.message || 'Failed to start seeding.', true);
                    }
                } catch (err) {
                    stopPolling();
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
                    stopPolling();
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                const confirmed = window.confirm(
                    'WARNING: This will delete all posts, non-admin users, plugin/LMS data, custom site roles, and non-core options.\n\nAdministrator accounts, active plugins, and the active theme will be kept.\n\nContinue?'
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

        initPasswordField();
        resumeActiveProcess();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
