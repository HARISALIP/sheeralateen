/**
 * assets/js/order-notifications.js
 * -------------------------------------------------------------
 * Real-time new order listener with sound and desktop notifications.
 * Works even when the browser tab is minimized or in the background.
 */
(function() {
    'use strict';

    let lastOrderId = null;
    let audioElement = null;
    let audioCtx = null;
    let isUnlocked = false;

    // Create and preload the HTML5 audio element
    function setupAudioElement() {
        if (!audioElement) {
            audioElement = document.getElementById('sheera-order-audio');
            if (!audioElement) {
                audioElement = document.createElement('audio');
                audioElement.id = 'sheera-order-audio';
                audioElement.preload = 'auto';
                audioElement.src = '/assets/sounds/order_alert.wav';
                document.body.appendChild(audioElement);
            }
        }
    }

    // Web Audio API Synth as secondary fallback
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

    // Unlock both Audio Element & AudioContext on user interaction
    function unlockAudio() {
        setupAudioElement();
        if (audioElement && !isUnlocked) {
            // Muted play & immediate pause satisfies browser autoplay policy
            audioElement.muted = true;
            const playPromise = audioElement.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    audioElement.pause();
                    audioElement.currentTime = 0;
                    audioElement.muted = false;
                    isUnlocked = true;
                }).catch(() => {});
            }
        }
        initAudioContext();
    }

    // Register user gesture listeners to unlock audio
    ['click', 'touchstart', 'keydown'].forEach(evt => {
        window.addEventListener(evt, unlockAudio, { once: false, passive: true });
    });

    // Play synthesized bell chime via Web Audio API
    function playSynthChime() {
        try {
            initAudioContext();
            if (!audioCtx) return;
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            const chords = [
                { f: 659.25, t: 0.00, dur: 0.35, vol: 0.40 }, // E5
                { f: 987.77, t: 0.12, dur: 0.45, vol: 0.45 }, // B5
                { f: 1318.51, t: 0.28, dur: 0.65, vol: 0.50 } // E6
            ];

            [0, 0.90].forEach(delay => {
                chords.forEach(c => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();

                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(c.f, audioCtx.currentTime + delay + c.t);

                    gain.gain.setValueAtTime(0.0001, audioCtx.currentTime + delay + c.t);
                    gain.gain.linearRampToValueAtTime(c.vol, audioCtx.currentTime + delay + c.t + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + delay + c.t + c.dur);

                    osc.connect(gain);
                    gain.connect(audioCtx.destination);

                    osc.start(audioCtx.currentTime + delay + c.t);
                    osc.stop(audioCtx.currentTime + delay + c.t + c.dur + 0.05);
                });
            });
        } catch(e) {}
    }

    // Main sound player: HTML5 Audio first, then synth fallback
    function playOrderSound() {
        setupAudioElement();
        let played = false;

        if (audioElement) {
            audioElement.currentTime = 0;
            audioElement.muted = false;
            const p = audioElement.play();
            if (p !== undefined) {
                p.then(() => {
                    played = true;
                }).catch(() => {
                    // Fallback to Web Audio synth if HTML5 audio was restricted
                    playSynthChime();
                });
            } else {
                played = true;
            }
        }

        // Also trigger synth after small delay for rich double chime
        setTimeout(playSynthChime, 100);
    }

    // Show Desktop / OS Notification (shows even if minimized)
    function showDesktopNotification(title, body, orderId) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        try {
            const options = {
                body: body,
                icon: '/assets/images/logo.png',
                tag: orderId ? ('new-order-' + orderId) : 'test-alert'
            };

            const n = new Notification(title, options);
            n.onclick = function() {
                window.focus();
                if (orderId) {
                    const isManager = window.location.pathname.includes('/manager/');
                    const targetUrl = (isManager ? '/manager/' : '/admin/') + 'order_details.php?id=' + orderId;
                    window.location.href = targetUrl;
                }
                n.close();
            };
        } catch (e) {
            console.warn('[Notifications] Desktop notification error:', e);
        }
    }

    // Update Topbar Bell Icon State
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
            bell.setAttribute('title', 'Order alerts active! Click to test sound & notification.');
        } else {
            badge.style.background = '#f59e0b'; // Amber: needs permission
            bell.setAttribute('title', 'Click to allow notifications & sound alerts');
        }
    }

    // Handle Topbar Bell Click
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
                        showDesktopNotification('🔔 Notifications Enabled!', 'You will now receive sound and desktop alerts for new orders.', null);
                        showToast('Notifications & sound alerts enabled!');
                    }
                });
                return;
            }

            // Test chime & test notification if already granted
            playOrderSound();
            showDesktopNotification('🔔 Notification Test Successful!', 'Sound and desktop alerts are active.', null);
            showToast('Test chime played! Desktop notification sent.');
        });
    }

    // Toast message
    function showToast(msg) {
        let toast = document.getElementById('order-notify-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'order-notify-toast';
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:12px 20px;border-radius:8px;font-size:0.9rem;box-shadow:0 10px 25px rgba(0,0,0,0.25);z-index:999999;transition:all 0.3s;display:flex;align-items:center;gap:10px;';
            document.body.appendChild(toast);
        }
        toast.innerHTML = '<i class="fa-solid fa-bell" style="color:#10b981;"></i> <span>' + msg + '</span>';
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
        }, 4500);
    }

    // Process new order alert
    function processOrderAlert(order) {
        const oid = parseInt(order.id, 10);
        const storedAlerted = parseInt(localStorage.getItem('sheera_last_alerted_order_id') || '0', 10);

        if (oid > storedAlerted) {
            try {
                localStorage.setItem('sheera_last_alerted_order_id', oid);
            } catch(e) {}

            playOrderSound();

            const title = '🔔 New Order ' + (order.order_number || ('#' + order.id));
            const body = (order.branch_name ? ('Branch: ' + order.branch_name + '\n') : '') +
                         (order.total_amount ? ('Amount: SAR ' + parseFloat(order.total_amount).toFixed(2) + '\n') : '') +
                         (order.customer_name ? ('Customer: ' + order.customer_name) : '');

            showDesktopNotification(title, body, order.id);
            showToast('New Order Received: ' + (order.order_number || ('#' + order.id)));

            // If on orders.php list, wait 6 seconds (let chime finish) then refresh list
            if (window.location.pathname.endsWith('/orders.php')) {
                setTimeout(() => { window.location.reload(); }, 6000);
            }
        }
    }

    // Polling function
    function pollNewOrders() {
        const since = (lastOrderId === null) ? 0 : lastOrderId;

        fetch('/api/check_new_orders.php?since_id=' + since, {
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data) return;

            // Handle any new orders returned (either on handshake or regular poll)
            if (data.has_new && Array.isArray(data.orders) && data.orders.length > 0) {
                data.orders.forEach(order => {
                    processOrderAlert(order);
                });
            }

            if (data.latest_id) {
                lastOrderId = data.latest_id;
                try {
                    localStorage.setItem('sheera_last_seen_order_id', lastOrderId);
                } catch(e) {}
            }
        })
        .catch(() => {});
    }

    // Init
    function init() {
        setupAudioElement();
        setupBellButton();
        updateBellUI();

        // Request permission on load if not yet determined
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(() => {
                updateBellUI();
            });
        }

        // Initialize polling immediately, then every 10 seconds
        pollNewOrders();
        setInterval(pollNewOrders, 10000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
