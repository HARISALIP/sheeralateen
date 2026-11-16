/**
 * assets/js/order-notifications.js
 * -------------------------------------------------------------
 * Real-time new order listener with 30-second continuous sound,
 * unaccepted order reminders, snooze support, and desktop alerts.
 * Works even when the browser tab is minimized or in the background.
 *
 * KEY DESIGN: We NEVER auto-reload the page. Every page reload resets
 * the browser's audio autoplay lock, silencing future alerts. Instead,
 * we show an interactive banner so the user can manually refresh if needed.
 */
(function() {
    'use strict';

    let lastOrderId = null;
    let audioElement = null;
    let audioCtx = null;
    let isUnlocked = false;
    let pendingRefresh = false;

    // Continuous sound state
    let isAlertRinging = false;
    let soundLoopInterval = null;
    let soundStopTimeout = null;

    // -----------------------------------------------------------------------
    // Audio Setup
    // -----------------------------------------------------------------------

    function setupAudioElement() {
        if (!audioElement) {
            audioElement = document.getElementById('sheera-order-audio');
            if (!audioElement) {
                audioElement = document.createElement('audio');
                audioElement.id = 'sheera-order-audio';
                audioElement.preload = 'auto';
                audioElement.src = '/assets/sounds/order_alert.wav?v=4';
                document.body.appendChild(audioElement);
            }
        }
    }

    function initAudioContext() {
        if (!audioCtx) {
            const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
            if (AudioCtxClass) {
                try { audioCtx = new AudioCtxClass(); } catch(e) {}
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }
    }

    // Unlock audio on any user interaction (click, touch, key)
    function unlockAudio() {
        setupAudioElement();
        initAudioContext();

        if (audioElement && !isUnlocked) {
            audioElement.muted = true;
            const p = audioElement.play();
            if (p !== undefined) {
                p.then(() => {
                    audioElement.pause();
                    audioElement.currentTime = 0;
                    audioElement.muted = false;
                    isUnlocked = true;
                    console.log('[OrderAlerts] Audio unlocked');
                }).catch(() => {});
            }
        }
    }

    ['click', 'touchstart', 'keydown', 'scroll'].forEach(evt => {
        window.addEventListener(evt, unlockAudio, { once: false, passive: true });
    });

    // -----------------------------------------------------------------------
    // Sound Engine: 30-Second Continuous Alerting
    // -----------------------------------------------------------------------

    // Synthesised bell chime via Web Audio API (completely standalone fallback)
    function playSynthChimePulse(isReminder = false) {
        try {
            initAudioContext();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;
            // Slightly urgent pitch for reminder, warm pleasant pitch for new order
            const baseFreq = isReminder ? 784.0 : 659.25; 
            const notes = [
                { f: baseFreq,        t: 0.00, dur: 0.30, vol: 0.50 },
                { f: baseFreq * 1.5,  t: 0.14, dur: 0.40, vol: 0.55 },
                { f: baseFreq * 2.0,  t: 0.28, dur: 0.60, vol: 0.60 }
            ];

            notes.forEach(c => {
                const osc  = audioCtx.createOscillator();
                const gain = audioCtx.createGain();

                osc.type = isReminder ? 'sawtooth' : 'triangle';
                osc.frequency.setValueAtTime(c.f, now + c.t);

                gain.gain.setValueAtTime(0.0001, now + c.t);
                gain.gain.linearRampToValueAtTime(c.vol, now + c.t + 0.03);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + c.t + c.dur);

                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(now + c.t);
                osc.stop(now + c.t + c.dur + 0.05);
            });
        } catch(e) {
            console.warn('[OrderAlerts] Synth chime error:', e);
        }
    }

    /**
     * Start continuous alert ringing for up to 30 seconds.
     * Can be silenced at any time by calling stopContinuousAlert().
     */
    function startContinuousAlert(durationSeconds = 30, isReminder = false) {
        stopContinuousAlert(); // Clear any ongoing sound
        isAlertRinging = true;

        setupAudioElement();
        initAudioContext();

        // 1. HTML5 audio looping
        if (audioElement) {
            audioElement.currentTime = 0;
            audioElement.loop = true;
            audioElement.muted = false;
            const p = audioElement.play();
            if (p !== undefined) {
                p.catch(() => {
                    // Handled by WebAudio fallback below
                });
            }
        }

        // 2. Play WebAudio chime pulse immediately and repeat every 1.8 seconds
        playSynthChimePulse(isReminder);
        soundLoopInterval = setInterval(() => {
            if (!isAlertRinging) {
                clearInterval(soundLoopInterval);
                return;
            }
            playSynthChimePulse(isReminder);
            if (audioElement && audioElement.paused && isUnlocked) {
                audioElement.play().catch(() => {});
            }
        }, 1800);

        // 3. Automatically stop ringing after durationSeconds (default 30s)
        soundStopTimeout = setTimeout(() => {
            stopContinuousAlert();
            console.log('[OrderAlerts] Sound alert auto-stopped after ' + durationSeconds + 's');
        }, durationSeconds * 1000);

        updateSilenceButtonsUI(true);
    }

    /**
     * Immediately silence any ringing alert sound.
     */
    function stopContinuousAlert() {
        isAlertRinging = false;
        if (soundLoopInterval) {
            clearInterval(soundLoopInterval);
            soundLoopInterval = null;
        }
        if (soundStopTimeout) {
            clearTimeout(soundStopTimeout);
            soundStopTimeout = null;
        }
        if (audioElement) {
            try {
                audioElement.pause();
                audioElement.currentTime = 0;
                audioElement.loop = false;
            } catch(e) {}
        }
        updateSilenceButtonsUI(false);
    }

    function updateSilenceButtonsUI(isPlaying) {
        const btns = document.querySelectorAll('.btn-silence-alert');
        btns.forEach(btn => {
            if (isPlaying) {
                btn.innerHTML = '<i class="fa-solid fa-volume-high"></i> Silence Sound';
                btn.style.display = 'inline-flex';
                btn.style.opacity = '1';
            } else {
                btn.innerHTML = '<i class="fa-solid fa-volume-xmark"></i> Silenced';
                btn.style.opacity = '0.75';
            }
        });
    }

    // Expose stop function globally for inline onclick handlers
    window.stopOrderAlertSound = stopContinuousAlert;

    // -----------------------------------------------------------------------
    // Desktop / OS Notifications
    // -----------------------------------------------------------------------

    function showDesktopNotification(title, body, orderId) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        try {
            const n = new Notification(title, {
                body: body,
                icon: '/assets/images/favicon.png',
                tag: orderId ? ('order-' + orderId) : 'test-alert',
                requireInteraction: false
            });
            n.onclick = function() {
                window.focus();
                stopContinuousAlert();
                if (orderId) {
                    const base = window.location.pathname.includes('/manager/') ? '/manager/' : '/admin/';
                    window.location.href = base + 'order_details.php?id=' + orderId;
                }
                n.close();
            };
        } catch(e) {
            console.warn('[OrderAlerts] Desktop notification error:', e);
        }
    }

    // -----------------------------------------------------------------------
    // UI: New Order Banner (Green)
    // -----------------------------------------------------------------------

    function showTopBanner(order) {
        let banner = document.getElementById('new-order-top-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'new-order-top-banner';
            banner.style.cssText = [
                'position:fixed', 'top:0', 'left:0', 'right:0',
                'background:linear-gradient(90deg,#059669,#10b981)',
                'color:#fff', 'padding:12px 20px', 'text-align:center',
                'font-size:0.95rem', 'font-weight:600', 'z-index:9999999',
                'box-shadow:0 4px 20px rgba(0,0,0,0.3)',
                'display:flex', 'align-items:center', 'justify-content:center', 'gap:12px',
                'flex-wrap:wrap'
            ].join(';');
            document.body.appendChild(banner);
        }

        const isManager = window.location.pathname.includes('/manager/');
        const base = isManager ? '/manager/' : '/admin/';
        const detailUrl = base + 'order_details.php?id=' + order.id;
        const ordersUrl = base + 'orders.php';
        const amount = order.total_amount ? ' &bull; SAR ' + parseFloat(order.total_amount).toFixed(2) : '';
        const branch = order.branch_name ? ' (' + order.branch_name + ')' : '';

        banner.innerHTML =
            '<span>🔔 <strong>NEW ORDER:</strong> ' + (order.order_number || ('#' + order.id)) + amount + branch + '</span>' +
            '<a href="' + detailUrl + '" onclick="window.stopOrderAlertSound()" style="background:#fff;color:#059669;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:0.85rem;font-weight:700;">View</a>' +
            '<button onclick="window.stopOrderAlertSound(); window.location.href=\'' + ordersUrl + '\'" style="background:rgba(255,255,255,0.25);color:#fff;border:1px solid rgba(255,255,255,0.5);padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.85rem;font-weight:700;">Refresh List</button>' +
            '<button class="btn-silence-alert" onclick="window.stopOrderAlertSound()" style="background:#dc2626;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:0.85rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;"><i class="fa-solid fa-volume-high"></i> Silence Sound (30s)</button>' +
            '<button onclick="window.stopOrderAlertSound(); document.getElementById(\'new-order-top-banner\').style.display=\'none\'" style="background:transparent;color:#fff;border:none;cursor:pointer;font-size:1.3rem;padding:0 8px;">&times;</button>';
        
        banner.style.display = 'flex';
    }

    // -----------------------------------------------------------------------
    // UI: Unaccepted Order Reminder Banner (Amber / Orange)
    // -----------------------------------------------------------------------

    function showReminderBanner(order) {
        let banner = document.getElementById('pending-order-reminder-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'pending-order-reminder-banner';
            banner.style.cssText = [
                'position:fixed', 'top:0', 'left:0', 'right:0',
                'background:linear-gradient(90deg,#d97706,#f59e0b)',
                'color:#fff', 'padding:12px 20px', 'text-align:center',
                'font-size:0.95rem', 'font-weight:600', 'z-index:9999998',
                'box-shadow:0 6px 22px rgba(217,119,6,0.4)',
                'display:flex', 'align-items:center', 'justify-content:center', 'gap:12px',
                'flex-wrap:wrap', 'animation:reminderPulse 2s infinite'
            ].join(';');

            // Inject animation style if not already present
            if (!document.getElementById('sheera-reminder-style')) {
                const style = document.createElement('style');
                style.id = 'sheera-reminder-style';
                style.textContent = `
                    @keyframes reminderPulse {
                        0%, 100% { filter: brightness(1); }
                        50% { filter: brightness(1.15); }
                    }
                `;
                document.head.appendChild(style);
            }
            document.body.appendChild(banner);
        }

        const isManager = window.location.pathname.includes('/manager/');
        const base = isManager ? '/manager/' : '/admin/';
        const detailUrl = base + 'order_details.php?id=' + order.id;
        const branch = order.branch_name ? ' (' + order.branch_name + ')' : '';
        const age = order.age_minutes ? order.age_minutes + ' min ago' : 'waiting';

        banner.innerHTML =
            '<span>⚠️ <strong>REMINDER:</strong> Order ' + (order.order_number || ('#' + order.id)) + branch + ' is still <strong>New & Unaccepted</strong> (' + age + ')!</span>' +
            '<a href="' + detailUrl + '" onclick="window.stopOrderAlertSound()" style="background:#fff;color:#d97706;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:0.85rem;font-weight:700;">View & Accept</a>' +
            '<button onclick="window.snoozeOrderReminder(' + order.id + ', \'' + (order.order_number || ('#' + order.id)) + '\')" style="background:rgba(255,255,255,0.25);color:#fff;border:1px solid rgba(255,255,255,0.6);padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.85rem;font-weight:700;">💤 Snooze 2m</button>' +
            '<button class="btn-silence-alert" onclick="window.stopOrderAlertSound()" style="background:#b45309;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:0.85rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;"><i class="fa-solid fa-volume-high"></i> Silence Sound</button>' +
            '<button onclick="window.stopOrderAlertSound(); document.getElementById(\'pending-order-reminder-banner\').style.display=\'none\'" style="background:transparent;color:#fff;border:none;cursor:pointer;font-size:1.3rem;padding:0 8px;">&times;</button>';
        
        banner.style.display = 'flex';
    }

    // Snooze reminder for 2 minutes
    window.snoozeOrderReminder = function(orderId, orderNumber) {
        stopContinuousAlert();
        const snoozeUntil = Date.now() + (2 * 60 * 1000); // 2 minutes from now
        try {
            localStorage.setItem('sheera_snooze_' + orderId, snoozeUntil.toString());
        } catch(e) {}

        const banner = document.getElementById('pending-order-reminder-banner');
        if (banner) {
            banner.style.display = 'none';
        }

        showToast('💤 Reminder for ' + orderNumber + ' snoozed for 2 minutes.');
        console.log('[OrderAlerts] Order ' + orderId + ' snoozed until ' + new Date(snoozeUntil).toLocaleTimeString());
    };

    // -----------------------------------------------------------------------
    // UI: Bell Button
    // -----------------------------------------------------------------------

    function updateBellUI() {
        const bell = document.getElementById('btn-notifications');
        if (!bell) return;

        let badge = bell.querySelector('.notify-pulse-dot');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'notify-pulse-dot';
            badge.style.cssText = 'position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;';
            bell.style.position = 'relative';
            bell.appendChild(badge);
        }

        if (window.Notification && Notification.permission === 'granted') {
            badge.style.background = '#10b981';
            bell.setAttribute('title', 'Alerts active! Click to test 30s sound & desktop notification.');
        } else {
            badge.style.background = '#f59e0b';
            bell.setAttribute('title', 'Click to allow notifications & 30s sound alerts');
        }
    }

    function setupBellButton() {
        const bell = document.getElementById('btn-notifications');
        if (!bell) return;

        bell.style.cursor = 'pointer';
        bell.addEventListener('click', function(e) {
            e.preventDefault();
            unlockAudio();

            if ('Notification' in window && Notification.permission !== 'granted') {
                Notification.requestPermission().then(perm => {
                    updateBellUI();
                    if (perm === 'granted') {
                        startContinuousAlert(10); // 10s test ring
                        showDesktopNotification('🔔 Alerts Enabled!', '30-second sound and desktop alerts are active for new orders & reminders.', null);
                        showToast('Notifications & 30s sound alerts enabled!');
                    } else {
                        showToast('Permission denied. Only in-page 30s sound alerts will play.');
                    }
                });
                return;
            }

            // Already granted — run test
            if (isAlertRinging) {
                stopContinuousAlert();
                showToast('Sound alert silenced.');
            } else {
                startContinuousAlert(10); // 10s test ring
                showDesktopNotification('🔔 Test Notification', '30-second sound and desktop alerts are working correctly.', null);
                showToast('Test ringing! Click the bell again to stop.');
            }
        });
    }

    // -----------------------------------------------------------------------
    // UI: Toast
    // -----------------------------------------------------------------------

    function showToast(msg) {
        let toast = document.getElementById('order-notify-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'order-notify-toast';
            toast.style.cssText = [
                'position:fixed', 'bottom:24px', 'right:24px',
                'background:#1e293b', 'color:#fff',
                'padding:12px 20px', 'border-radius:8px', 'font-size:0.9rem',
                'box-shadow:0 10px 25px rgba(0,0,0,0.25)', 'z-index:999999',
                'transition:opacity 0.3s,transform 0.3s',
                'display:flex', 'align-items:center', 'gap:10px'
            ].join(';');
            document.body.appendChild(toast);
        }
        toast.innerHTML = '<i class="fa-solid fa-bell" style="color:#10b981;"></i> <span>' + msg + '</span>';
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
        }, 5000);
    }

    // -----------------------------------------------------------------------
    // Core: Order Alert Processing (New Orders)
    // -----------------------------------------------------------------------

    function processOrderAlert(order) {
        const oid = parseInt(order.id, 10);
        const storedAlerted = parseInt(localStorage.getItem('sheera_last_alerted_order_id') || '0', 10);

        if (oid <= storedAlerted) {
            return;
        }

        try { localStorage.setItem('sheera_last_alerted_order_id', oid.toString()); } catch(e) {}

        console.log('[OrderAlerts] New order detected → ID:', oid, order.order_number);

        // 1. Start 30-second continuous sound alert
        startContinuousAlert(30, false);

        // 2. Desktop notification
        const title = '🔔 New Order ' + (order.order_number || ('#' + order.id));
        const body  = [
            order.branch_name   ? 'Branch: '   + order.branch_name   : null,
            order.total_amount  ? 'Amount: SAR ' + parseFloat(order.total_amount).toFixed(2) : null,
            order.customer_name ? 'Customer: ' + order.customer_name : null
        ].filter(Boolean).join('\n');

        showDesktopNotification(title, body, order.id);

        // 3. Top Banner with Silence button
        showTopBanner(order);

        // 4. Toast
        showToast('New Order: ' + (order.order_number || ('#' + order.id)));

        pendingRefresh = true;
    }

    // -----------------------------------------------------------------------
    // Core: Unaccepted Order Reminders
    // -----------------------------------------------------------------------

    function processReminders(remindersList) {
        if (!Array.isArray(remindersList) || remindersList.length === 0) {
            // No unaccepted orders pending — hide reminder banner if displayed
            const banner = document.getElementById('pending-order-reminder-banner');
            if (banner) banner.style.display = 'none';
            return;
        }

        const now = Date.now();
        // Check oldest unaccepted order first
        for (const order of remindersList) {
            const oid = order.id;

            // Check if snoozed
            const snoozeUntil = parseInt(localStorage.getItem('sheera_snooze_' + oid) || '0', 10);
            if (now < snoozeUntil) {
                continue; // Still snoozed
            }

            // Check if reminded recently (at least 2 minutes between reminder rings)
            const lastReminded = parseInt(localStorage.getItem('sheera_last_reminded_' + oid) || '0', 10);
            if (now - lastReminded < 120000) {
                // Already reminded in the last 2 minutes, keep banner visible if not hidden
                return;
            }

            // Fire reminder!
            try { localStorage.setItem('sheera_last_reminded_' + oid, now.toString()); } catch(e) {}

            console.log('[OrderAlerts] Reminder alert triggered for order:', order.order_number || oid);

            // 1. Start 30-second continuous sound alert
            startContinuousAlert(30, true);

            // 2. Desktop notification
            const title = '⚠️ REMINDER: Order ' + (order.order_number || ('#' + order.id)) + ' is still pending!';
            const body = (order.branch_name ? 'Branch: ' + order.branch_name + '\n' : '') +
                         'Waiting for ' + (order.age_minutes || 2) + ' minutes. Please accept and prepare!';
            showDesktopNotification(title, body, order.id);

            // 3. Reminder Banner
            showReminderBanner(order);

            // 4. Toast
            showToast('⚠️ Reminder: Order ' + (order.order_number || ('#' + order.id)) + ' still pending acceptance!');

            break; // Ring for the oldest pending order
        }
    }

    // -----------------------------------------------------------------------
    // UI: Session Expired Banner
    // -----------------------------------------------------------------------

    function showSessionExpiredBanner() {
        let banner = document.getElementById('session-expired-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'session-expired-banner';
            banner.style.cssText = [
                'position:fixed', 'top:0', 'left:0', 'right:0',
                'background:#dc2626', 'color:#fff', 'padding:12px 20px',
                'text-align:center', 'font-size:0.95rem', 'font-weight:600',
                'z-index:9999999', 'box-shadow:0 4px 20px rgba(0,0,0,0.3)',
                'display:flex', 'align-items:center', 'justify-content:center', 'gap:15px'
            ].join(';');
            banner.innerHTML =
                '<span>⚠️ Session expired. Order notifications are paused.</span>' +
                '<a href="/login.php" style="background:#fff;color:#dc2626;padding:5px 14px;border-radius:6px;text-decoration:none;font-size:0.85rem;font-weight:700;">Sign Back In</a>';
            document.body.appendChild(banner);
        }
    }

    function hideSessionExpiredBanner() {
        const banner = document.getElementById('session-expired-banner');
        if (banner) banner.remove();
    }

    // -----------------------------------------------------------------------
    // Core: Polling
    // -----------------------------------------------------------------------

    function pollNewOrders() {
        const since = (lastOrderId === null) ? 0 : lastOrderId;

        fetch('/api/check_new_orders.php?since_id=' + since + '&_t=' + Date.now(), {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => {
            if (res.status === 401) {
                console.warn('[OrderAlerts] Session expired, polling stopped.');
                showSessionExpiredBanner();
                return null;
            }
            hideSessionExpiredBanner();
            return res.text();
        })
        .then(text => {
            if (!text) return null;
            const clean = text.charCodeAt(0) === 0xFEFF ? text.slice(1) : text;
            try {
                return JSON.parse(clean);
            } catch(e) {
                console.warn('[OrderAlerts] Poll error:', e, '| Raw:', clean.substring(0, 80));
                return null;
            }
        })
        .then(data => {
            if (!data) return;

            // Update local order ID cursor
            if (data.latest_id && data.latest_id > 0) {
                if (lastOrderId === null) {
                    lastOrderId = data.latest_id;
                    try { localStorage.setItem('sheera_last_seen_order_id', lastOrderId); } catch(e) {}
                } else if (data.has_new) {
                    lastOrderId = data.latest_id;
                    try { localStorage.setItem('sheera_last_seen_order_id', lastOrderId); } catch(e) {}
                }
            }

            // 1. Process new incoming orders
            if (!data.initialized && data.has_new && Array.isArray(data.orders) && data.orders.length > 0) {
                data.orders.forEach(order => processOrderAlert(order));
            }

            // 2. Process unaccepted order reminders
            if (Array.isArray(data.reminders)) {
                processReminders(data.reminders);
            }
        })
        .catch(err => {
            console.warn('[OrderAlerts] Poll error:', err);
        });
    }

    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------

    function init() {
        setupAudioElement();
        setupBellButton();
        updateBellUI();

        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(() => updateBellUI());
        }

        // First poll: handshake to get current max ID and check existing reminders
        pollNewOrders();

        // Regular poll every 8 seconds
        setInterval(pollNewOrders, 8000);

        // Immediate poll when returning to tab or window focus
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                pollNewOrders();
            }
        });
        window.addEventListener('focus', function() {
            pollNewOrders();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
