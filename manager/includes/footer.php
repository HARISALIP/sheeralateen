<?php
/**
 * admin/includes/footer.php
 * Closes the layout wrappers and adds necessary JavaScript.
 */
?>
    </main> <!-- End Content -->

    <footer class="footer">
        &copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?> &mdash; Super Admin Portal. All rights reserved.
    </footer>
    </div> <!-- End Main Wrapper -->
</div> <!-- End Admin Layout -->

<script>
    // Responsive sidebar toggle (Desktop collapse / Mobile drawer)
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        // Sync class from html to body if set early
        if (document.documentElement.classList.contains('sidebar-collapsed')) {
            document.body.classList.add('sidebar-collapsed');
        }

        if(menuToggle && sidebar) {
            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('open');
                } else {
                    const isMedium = window.innerWidth > 768 && window.innerWidth <= 1180;
                    if (isMedium) {
                        document.body.classList.toggle('sidebar-expanded');
                    } else {
                        document.body.classList.toggle('sidebar-collapsed');
                        const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                        localStorage.setItem('sidebar_collapsed', isCollapsed ? '1' : '0');
                    }
                }
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        }
    });
</script>
<script src="/assets/js/order-notifications.js?v=<?= time() ?>"></script>
</body>
</html>
