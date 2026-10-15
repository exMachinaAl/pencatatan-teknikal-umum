@vite(['resources/css/app.css', 'resources/js/app.js'])

<script>
    // Menu selalu dimulai tertutup, termasuk setelah refresh pada layar kecil.
    // Storage dapat dinonaktifkan oleh browser; kegagalannya tidak boleh memblokir UI.
    try {
        localStorage.setItem('isOpen', 'false')
    } catch (error) {
        // Alpine store di bawah tetap menutup menu bila storage tidak tersedia.
    }

    document.addEventListener('alpine:initialized', () => {
        window.Alpine?.store('sidebar')?.close()
    }, { once: true })
</script>
