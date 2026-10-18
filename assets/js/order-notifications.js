/**
 * assets/js/order-notifications.js
 * -------------------------------------------------------------
 * Real-time new order listener with sound and desktop notifications.
 * Works even when the browser tab is minimized or in the background.
 */
(function() {
    'use strict';

    let lastOrderId = null;
    let audioCtx = null;
    let audioUnlocked = false;

    // Web Audio API Chime Synth
    function initAudioContext() {
        if (!audioCtx) {
            const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
            if (AudioCtxClass) {
                audioCtx = new AudioCtxClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    }

    // Unlock audio context on any user interaction
    function unlockAudio() {
        initAudioContext();
        if (audioCtx && audioCtx.state === 'running') {
            audioUnlocked = true;
            updateBellUI();
        }
    }
    ['click', 'touchstart', 'keydown'].forEach(evt => {
        window.addEventListener(evt, unlockAudio, { once: false, passive: true });
    });

    // Play a distinct, loud kitchen/order bell chime
    function playOrderSound() {
        try {
            initAudioContext();
            if (!audioCtx) return;

            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            // Bell chords: High pleasant chime (D5 -> A5 -> D6)
            const chords = [
                { f: 587.33, t: 0.00, dur: 0.35, vol: 0.35 },
                { f: 880.00, t: 0.16, dur: 0.45, vol: 0.40 },
                { f: 1174.66, t: 0.34, dur: 0.65, vol: 0.45 }
            ];

            // Play sequence twice: "Ding-Ding-Ding ... Ding-Ding-Ding"
            [0, 0.85].forEach(delay => {
                chords.forEach(c => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();

                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(c.f, audioCtx.currentTime + delay + c.t);

                    gain.gain.setValueAtTime(0.0001, audioCtx.currentTime + delay + c.t);
                    gain.gain.linearRampToValueAtTime(c.vol, audioCtx.currentTime + delay + c.t + 0.03);
                    gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + delay + c.t + c.dur);

                    osc.connect(gain);
                    gain.connect(audioCtx.destination);

                    osc.start(audioCtx.currentTime + delay + c.t);
                    osc.stop(audioCtx.currentTime + delay + c.t + c.dur + 0.05);
                });
            });
        } catch (e) {
            console.warn('[Notifications] Audio error:', e);
        }
    }

    // Show Desktop / OS Notification (shows even if minimized)
    function showDesktopNotification(order) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        try {
            const title = '🔔 New Order ' + (order.order_number || ('#' + order.id));
            const branch = order.branch_name ? ('Branch: ' + order.branch_name + '\n') : '';
            const amount = order.total_amount ? ('Amount: SAR ' + parseFloat(order.total_amount).toFixed(2) + '\n') : '';
            const customer = order.customer_name ? ('Customer: ' + order.customer_name) : '';

            const n = new Notification(title, {
                body: branch + amount + customer,
                icon: '/assets/images/logo.png',
                tag: 'new-order-' + order.id,
                requireInteraction: true // Keep on screen on Windows until dismissed/clicked
            });

            n.onclick = function() {
                window.focus();
                const isManager = window.location.pathname.includes('/manager/');
                const targetUrl = (isManager ? '/manager/' : '/admin/') + 'order_details.php?id=' + order.id;
                window.location.href = targetUrl;
                n.close();
            };
        } catch (e) {
            console.warn('[Notifications] Desktop notification failed:', e);
        }
    }

    // Update the topbar bell icon UI state
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
            badge.style.background = '#10b981'; // Green active
            bell.setAttribute('title', 'Order alerts active (Sound & Desktop notifications enabled)');
        } else {
            badge.style.background = '#f59e0b'; // Amber: needs permission
            bell.setAttribute('title', 'Click to enable order sound & desktop alerts');
        }
    }

    // Handle Topbar Bell Click
    function setupBellButton() {
        const bell = document.getElementById('btn-notifications');
        if (!bell) return;

        bell.style.cursor = 'pointer';
        bell.addEventListener('click', function(e) {
            e.preventDefault();
            initAudioContext();
            unlockAudio();

            if ('Notification' in window) {
                if (Notification.permission !== 'granted') {
                    Notification.requestPermission().then(perm => {
                        updateBellUI();
                        if (perm === 'granted') {
                            playOrderSound();
                            showToast('Order sound & desktop notifications enabled!');
                        }
                    });
                    return;
                }
            }

            // Test chime if already granted
            playOrderSound();
            showToast('Audio test chime played. Order alerts are active.');
        });
    }

    // Simple toast notification
    function showToast(msg) {
        let toast = document.getElementById('order-notify-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'order-notify-toast';
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:12px 20px;border-radius:8px;font-size:0.9rem;box-shadow:0 10px 25px rgba(0,0,0,0.2);z-index:999999;transition:all 0.3s;display:flex;align-items:center;gap:10px;';
            document.body.appendChild(toast);
        }
        toast.innerHTML = '<i class="fa-solid fa-bell" style="color:#10b981;"></i> <span>' + msg + '</span>';
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
        }, 4000);
    }

    // Background poller
    function pollNewOrders() {
        // If not initialized yet, since_id = 0 to get current max ID without alerting old orders
        const since = (lastOrderId === null) ? 0 : lastOrderId;

        fetch('/api/check_new_orders.php?since_id=' + since, {
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data) return;

            // First run: just initialize the highest order ID
            if (data.initialized) {
                lastOrderId = data.latest_id;
                try {
                    const storedMax = parseInt(localStorage.getItem('sheera_last_seen_order_id') || '0', 10);
                    if (storedMax > lastOrderId) lastOrderId = storedMax;
                    localStorage.setItem('sheera_last_seen_order_id', lastOrderId);
                } catch(e) {}
                return;
            }

            // If there are new orders!
            if (data.has_new && Array.isArray(data.orders) && data.orders.length > 0) {
                const storedAlerted = parseInt(localStorage.getItem('sheera_last_alerted_order_id') || '0', 10);

                data.orders.forEach(order => {
                    const oid = parseInt(order.id, 10);
                    // Avoid duplicate alerts across multiple open tabs
                    if (oid > storedAlerted) {
                        try {
                            localStorage.setItem('sheera_last_alerted_order_id', oid);
                        } catch(e) {}

                        playOrderSound();
                        showDesktopNotification(order);
                        showToast('New Order Received: ' + (order.order_number || ('#' + order.id)));

                        // If on orders.php list page, reload so the new order appears
                        if (window.location.pathname.endsWith('/orders.php')) {
                            setTimeout(() => { window.location.reload(); }, 2000);
                        }
                    }
                });

                lastOrderId = data.latest_id;
                try {
                    localStorage.setItem('sheera_last_seen_order_id', lastOrderId);
                } catch(e) {}
            } else if (data.latest_id) {
                lastOrderId = data.latest_id;
            }
        })
        .catch(err => {
            // Quiet network failure
        });
    }

    // On Document Ready
    function init() {
        setupBellButton();
        updateBellUI();

        // Automatically prompt for notification permission if not yet decided
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(() => {
                updateBellUI();
            });
        }

        // Start polling immediately, then every 12 seconds (even when tab is minimized)
        pollNewOrders();
        setInterval(pollNewOrders, 12000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
