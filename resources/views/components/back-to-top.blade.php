<button id="back-to-top"
        class="fixed bottom-10 right-10 hidden p-3 rounded-full bg-blue-600 text-white shadow-lg hover:bg-blue-700 focus:outline-none transition-all duration-300 z-50"
        title="Back to Top"
        aria-label="Back to Top">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
    </svg>
</button>

<script>
    (function () {
        const btn = document.getElementById('back-to-top');
        if (!btn) return;

        const showAt = 300; // px

        function onScroll() {
            if (window.scrollY > showAt) {
                btn.classList.remove('hidden');
                btn.classList.add('opacity-100');
            } else {
                btn.classList.add('hidden');
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // initial state
        onScroll();
    })();
</script>
