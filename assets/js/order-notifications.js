/**
 * assets/js/order-notifications.js
 * -------------------------------------------------------------
 * Real-time new order listener with sound and desktop notifications.
 * Works even when the browser tab is minimized or in the background.
 *
 * KEY DESIGN: We NEVER auto-reload the page. Every page reload resets
 * the browser's audio autoplay lock, silencing future alerts. Instead,
 * we show a clickable banner so the user can manually refresh if needed.
 */
(function() {
    'use strict';

    let lastOrderId = null;
    let audioElement = null;
    let audioCtx = null;
    let isUnlocked = false;
    let pendingRefresh = false; // track if new orders arrived and UI needs refresh

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
                audioElement.src = '/assets/sounds/order_alert.wav?v=3';
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
    // Sound Player
    // -----------------------------------------------------------------------

    // Synthesised bell chime via Web Audio API (no external file dependency)
    function playSynthChime() {
        try {
            initAudioContext();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;
            const notes = [
                { f: 659.25, t: 0.00, dur: 0.35, vol: 0.5 },
                { f: 987.77, t: 0.15, dur: 0.45, vol: 0.55 },
                { f: 1318.51, t: 0.32, dur: 0.65, vol: 0.6 }
            ];

            // Play the chime twice with a short gap
            [0, 0.95].forEach(delay => {
                notes.forEach(c => {
                    const osc  = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();

                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(c.f, now + delay + c.t);

                    gain.gain.setValueAtTime(0.0001, now + delay + c.t);
                    gain.gain.linearRampToValueAtTime(c.vol, now + delay + c.t + 0.03);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + delay + c.t + c.dur);

                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.start(now + delay + c.t);
                    osc.stop(now + delay + c.t + c.dur + 0.05);
                });
            });
        } catch(e) {
            console.warn('[OrderAlerts] Synth chime error:', e);
        }
    }

    function playOrderSound() {
        setupAudioElement();

        // Always try synth chime first — it uses WebAudio and is more reliable
        playSynthChime();

        // Also try HTML5 audio (better quality if not blocked)
        if (audioElement) {
            audioElement.currentTime = 0;
            audioElement.muted = false;
            audioElement.play().catch(() => {
                // HTML5 audio blocked — synth already played above
            });
        }
    }

    // -----------------------------------------------------------------------
    // Desktop / OS Notifications
    // -----------------------------------------------------------------------

    function showDesktopNotification(title, body, orderId) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        try {
            const n = new Notification(title, {
                body: body,
                icon: '/assets/images/logo.png?v=2',
                tag: orderId ? ('order-' + orderId) : 'test-alert',
                requireInteraction: false
            });
            n.onclick = function() {
                window.focus();
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
    // UI: Top Banner
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
                'display:flex', 'align-items:center', 'justify-content:center', 'gap:15px'
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
            '<span>🔔 NEW ORDER: ' + (order.order_number || ('#' + order.id)) + amount + branch + '</span>' +
            '<a href="' + detailUrl + '" style="background:#fff;color:#059669;padding:5px 12px;border-radius:6px;text-decoration:none;font-size:0.85rem;font-weight:700;">View</a>' +
            '<button onclick="window.location.href=\'' + ordersUrl + '\'" style="background:rgba(255,255,255,0.25);color:#fff;border:1px solid rgba(255,255,255,0.5);padding:5px 12px;border-radius:6px;cursor:pointer;font-size:0.85rem;font-weight:700;">Refresh List</button>' +
            '<button onclick="document.getElementById(\'new-order-top-banner\').style.display=\'none\'" style="background:transparent;color:#fff;border:none;cursor:pointer;font-size:1.2rem;padding:0 5px;">&times;</button>';
    }

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
            bell.setAttribute('title', 'Alerts active! Click to test sound & notification.');
        } else {
            badge.style.background = '#f59e0b';
            bell.setAttribute('title', 'Click to allow notifications & sound alerts');
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
                        playOrderSound();
                        showDesktopNotification('🔔 Alerts Enabled!', 'Sound and desktop alerts are now active for new orders.', null);
                        showToast('Notifications & sound alerts enabled!');
                    } else {
                        showToast('Permission denied. Only in-page sound alerts will play.');
                    }
                });
                return;
            }

            // Already granted — play test
            playOrderSound();
            showDesktopNotification('🔔 Test Notification', 'Sound and desktop alerts are working correctly.', null);
            showToast('Test complete! Sound & desktop alerts are active.');
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
    // Core: Order Alert Processing
    // -----------------------------------------------------------------------

    function processOrderAlert(order) {
        const oid = parseInt(order.id, 10);
        const storedAlerted = parseInt(localStorage.getItem('sheera_last_alerted_order_id') || '0', 10);

        if (oid <= storedAlerted) {
            // Already alerted for this order (or an older one)
            return;
        }

        // Mark as alerted AFTER we've confirmed we'll fire (prevents double-alerts on same ID)
        try { localStorage.setItem('sheera_last_alerted_order_id', oid); } catch(e) {}

        console.log('[OrderAlerts] New order detected → ID:', oid, order.order_number);

        // 1. Sound
        playOrderSound();

        // 2. Desktop notification (works when tab is in background/minimized)
        const title = '🔔 New Order ' + (order.order_number || ('#' + order.id));
        const body  = [
            order.branch_name   ? 'Branch: '   + order.branch_name   : null,
            order.total_amount  ? 'Amount: SAR ' + parseFloat(order.total_amount).toFixed(2) : null,
            order.customer_name ? 'Customer: ' + order.customer_name : null
        ].filter(Boolean).join('\n');

        showDesktopNotification(title, body, order.id);

        // 3. In-page banner (no reload — banner has manual "Refresh List" button)
        showTopBanner(order);

        // 4. Toast
        showToast('New Order: ' + (order.order_number || ('#' + order.id)));

        // IMPORTANT: We do NOT auto-reload the page here.
        // Reloading would reset the browser's audio unlock state,
        // causing the NEXT order's sound to be silenced.
        // The user can click the "Refresh List" button in the banner.
        pendingRefresh = true;
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
                // Not logged in anymore — stop polling silently
                console.log('[OrderAlerts] Session expired, polling stopped.');
                return null;
            }
            return res.text(); // Use text() so we can strip BOM before parsing
        })
        .then(text => {
            if (!text) return null;
            // Strip UTF-8 BOM (\uFEFF) if the server accidentally outputs it
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

            // Update our local cursor
            if (data.latest_id && data.latest_id > 0) {
                // Only set lastOrderId from handshake (since_id=0) or when new orders arrive.
                // This prevents the cursor from jumping unexpectedly.
                if (lastOrderId === null) {
                    // Initial handshake: set cursor so we don't re-alert on next poll
                    lastOrderId = data.latest_id;
                    try { localStorage.setItem('sheera_last_seen_order_id', lastOrderId); } catch(e) {}
                } else if (data.has_new) {
                    lastOrderId = data.latest_id;
                    try { localStorage.setItem('sheera_last_seen_order_id', lastOrderId); } catch(e) {}
                }
            }

            // Process new orders from regular polls (NOT handshake)
            // On the handshake (since=0), we skip alerts to avoid false alarms
            // on page load/reload. Real detection starts from next poll.
            if (!data.initialized && data.has_new && Array.isArray(data.orders) && data.orders.length > 0) {
                data.orders.forEach(order => processOrderAlert(order));
            }
        })
        .catch(err => {
            // Network error — fail silently, will retry next interval
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

        // Request notification permission automatically if not yet decided
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(() => updateBellUI());
        }

        // First poll: handshake to get current max ID (no alerts fired on this one)
        pollNewOrders();

        // Then poll every 8 seconds for new orders
        setInterval(pollNewOrders, 8000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
