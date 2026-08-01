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
    // Simple script to toggle sidebar on mobile
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if(menuToggle && sidebar) {
            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
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
</body>
</html>
