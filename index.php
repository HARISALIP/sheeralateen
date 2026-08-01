<?php
/**
 * index.php
 * ---------------------------------------------------------
 * Public landing page for the Sheera Lateen Branch Management
 * System. Purely informational — no session or DB access
 * required, so it stays fast even before login.
 */

$appName    = 'Branch Management System';
$brandName  = 'Sheera Lateen';
$year       = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($brandName) ?> | <?= htmlspecialchars($appName) ?></title>
    <meta name="description" content="A centralized platform for managing branches, orders, and business operations.">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <div class="brand">
            <!--
                Placeholder logo (initials badge). Replace with the real
                Sheera Lateen logo by swapping this span for:
                <img src="assets/images/logo.png" alt="Sheera Lateen logo" class="brand-logo-img">
            -->
            <span class="brand-logo" aria-hidden="true">SL</span>
            <span class="brand-name"><?= htmlspecialchars($brandName) ?></span>
        </div>
    </header>

    <main id="main">
        <section class="hero">
            <h1><?= htmlspecialchars($appName) ?></h1>
            <p class="hero-description">
                A centralized platform for managing branches, orders, and business operations.
            </p>
            <a href="login.php" class="btn-cta">Login to Dashboard</a>
        </section>

        <section class="features" aria-label="Key features">
            <div class="feature-grid">

                <article class="feature-card">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 21h18"></path>
                            <path d="M5 21V9l7-5 7 5v12"></path>
                            <path d="M9 21v-6h6v6"></path>
                        </svg>
                    </span>
                    <h3>Branch Management</h3>
                    <p>Create, monitor, and manage every branch from a single, centralized view.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="6" y="3" width="12" height="18" rx="2"></rect>
                            <path d="M9 3v3h6V3"></path>
                            <path d="M9 11h6"></path>
                            <path d="M9 15h6"></path>
                        </svg>
                    </span>
                    <h3>Order Management</h3>
                    <p>Track orders from assignment to delivery, with a full status history.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4v5h5"></path>
                            <path d="M20 20v-5h-5"></path>
                            <path d="M4.5 15a8 8 0 0 0 14.9 2.5"></path>
                            <path d="M19.5 9A8 8 0 0 0 4.6 6.5"></path>
                        </svg>
                    </span>
                    <h3>Shopify Integration</h3>
                    <p>Built to connect seamlessly with your Shopify store as your business grows.</p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 20V10"></path>
                            <path d="M11 20V4"></path>
                            <path d="M18 20v-7"></path>
                            <path d="M3 20h18"></path>
                        </svg>
                    </span>
                    <h3>Reports &amp; Analytics</h3>
                    <p>Get clear insight into branch performance and order trends.</p>
                </article>

            </div>
        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= htmlspecialchars($year) ?> <?= htmlspecialchars($brandName) ?></p>
        <p><?= htmlspecialchars($appName) ?></p>
    </footer>
</body>
</html>
