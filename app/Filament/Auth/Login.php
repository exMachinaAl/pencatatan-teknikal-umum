<?php

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $view = 'filament.auth.login';

    public function getHeading(): string | Htmlable
    {
        return 'Masuk ke ruang kerja';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Gunakan akun sekolah untuk melanjutkan.';
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Alamat email')
            ->placeholder('nama@sekolah.sch.id')
            ->autocomplete('username')
            ->extraInputAttributes(['autocapitalize' => 'none', 'spellcheck' => 'false'])
            ->validationMessages([
                'required' => 'Masukkan alamat email Anda.',
                'email' => 'Masukkan alamat email yang valid.',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Kata sandi')
            ->extraInputAttributes([])
            ->validationMessages(['required' => 'Masukkan kata sandi Anda.']);
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->label('Ingat saya di perangkat ini');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Masuk ke ruang kerja');
    }

    protected function throwFailureValidationException(): never
    {
        // Pesan sama untuk akun tidak aktif dan kredensial salah: jangan bocorkan keberadaan akun.
        throw ValidationException::withMessages([
            'data.email' => 'Tidak dapat masuk. Periksa email dan kata sandi, atau hubungi administrator.',
        ]);
    }
}
