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
{{-- TU-UI-V1.2-ADDITIVE-CSS --}}
<link rel="stylesheet" href="{{ asset('css/tu-ui-v12-safe.css') }}?v=20261019-12" />
{{-- TU-UI-V1.3-TABLE-FIX --}}
<link rel="stylesheet" href="{{ asset('css/tu-ui-v13-table-fix.css') }}?v=20261019-13" />
{{-- TU-UI-V2.0-OPERATIONAL --}}
<link rel="stylesheet" href="{{ asset('css/tu-ui-v20-operasional.css') }}?v=20261019-21" />
