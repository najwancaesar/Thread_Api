    <!-- Footer -->
    <footer class="footer mt-auto py-3" style="background-color: var(--primary-color); color: white;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <span>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</span>
                </div>
                <div class="col-md-6 text-end">
                    <span>
                        <i class="fas fa-server"></i> Database: <?php echo DB_NAME; ?> |
                        <i class="fas fa-microchip"></i> Machine: <?php echo MACHINE_ID; ?> |
                        <i class="fas fa-clock"></i> Last Update: <span id="lastUpdateTime"><?php echo date('H:i:s'); ?></span>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="<?php echo appUrl('assets/js/main.js'); ?>"></script>

    <script>
        // Auto refresh last update time
        function updateTime() {
            const now = new Date();
            const timeString = now.getHours().toString().padStart(2, '0') + ':' +
                              now.getMinutes().toString().padStart(2, '0') + ':' +
                              now.getSeconds().toString().padStart(2, '0');
            $('#lastUpdateTime').text(timeString);
        }

        setInterval(updateTime, 1000);
        $(document).ready(updateTime);
    </script>
    </body>

    </html>