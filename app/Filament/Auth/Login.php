<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $view = 'filament.auth.login';

    public function getHeading(): string|Htmlable
    {
        return 'Masuk ke ruang kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gunakan akun sekolah untuk melanjutkan.';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Username atau NIP')
            ->placeholder('Contoh: admin atau 198001012005011001')
            ->required()
            ->autofocus()
            ->autocomplete('username')
            ->helperText('Email akun lama juga tetap dapat digunakan.')
            ->extraInputAttributes(['tabindex' => 1, 'autocapitalize' => 'none', 'spellcheck' => 'false'])
            ->validationMessages([
                'required' => 'Masukkan username, NIP, atau email Anda.',
            ]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $user = User::findForLogin((string) ($data['login'] ?? ''));

        return [
            'id' => $user?->getKey() ?? 0,
            'password' => $data['password'],
        ];
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
            'data.login' => 'Tidak dapat masuk. Periksa username/NIP dan kata sandi, atau hubungi administrator.',
        ]);
    }
}
