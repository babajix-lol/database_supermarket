<?php
/**
 * Footer Layout Component
 * Sistem Manajemen Supermarket
 */
?>
            </main>

            <!-- Bottom Footer Bar -->
            <footer class="no-print border-t border-slate-200/70 bg-white/50 px-8 py-4 text-center text-xs text-slate-400 mt-auto">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p>&copy; <?= date('Y') ?> <strong class="text-slate-600 font-semibold">Sistem Manajemen Supermarket</strong>. Desain Modern "Donezo" Style.</p>
                    <p class="text-[11px] text-slate-400">PHP Native & PDO &bull; Tailwind CSS &bull; MySQL</p>
                </div>
            </footer>
        </div>
    </div>

    <!-- Inisialisasi Icon Lucide -->
    <script>
        lucide.createIcons();

        // Auto dismiss flash alert after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById('flash-alert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300);
            }
        }, 5000);
    </script>
</body>
</html>
