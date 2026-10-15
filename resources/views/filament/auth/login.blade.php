<x-filament-panels::page.simple class="tu-login-page">
    {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.before') }}

    <x-filament-panels::form wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <p class="tu-login-note">Kesulitan masuk? Hubungi administrator sekolah.</p>

    {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.after') }}
</x-filament-panels::page.simple>
