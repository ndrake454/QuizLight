</main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. All rights reserved.</p>
                </div>
                <div class="flex space-x-4">
                    <a href="/about.php" class="hover:text-blue-300 transition duration-150">About</a>
                    <a href="/privacy.php" class="hover:text-blue-300 transition duration-150">Privacy</a>
                    <a href="mailto:admin@quizlight.org" class="hover:text-blue-300 transition duration-150">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Service Worker Registration for PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(registration => {
                        console.log('Service Worker registered');
                    })
                    .catch(error => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }
    </script>

    <!-- Custom JS -->
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>