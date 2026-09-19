            </main>
        </div>
    </div>

    <button class="scroll-top-btn" id="scrollTopBtn" aria-label="Scroll to top"><i class="bi bi-arrow-up"></i></button>

    <script nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        (function () {
            var toggle = document.getElementById('portalNavToggle');
            var sidebar = document.getElementById('portalSidebar');
            if (toggle && sidebar) {
                toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
            }
            var scrollBtn = document.getElementById('scrollTopBtn');
            window.addEventListener('scroll', function () {
                scrollBtn.classList.toggle('visible', window.scrollY > 300);
            });
            scrollBtn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        })();
    </script>
</body>

</html>
