<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Carbon;
use GuzzleHttp\Client;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $code = '';

    public function mount(): void
    {
        $user = Auth::user();

        if ($user && $user->phone_number_verified_at) {
            $this->redirect(route('dashboard', absolute: false), navigate: true);
        }
    }

    public function verify(): void
    {
        $user = Auth::user();

        if (!$user || !$user->otp_request_id) {
            $this->addError('code', 'Missing OTP session. Please try again.');
            return;
        }

        $validated = $this->validate([
            'code' => ['required', 'digits:6'],
        ]);

        try {
            $client = new Client();
            $response = $client->request('POST', 'https://api.movider.co/v1/verify/acknowledge', [
                'form_params' => [
                    'api_key' => config('services.movider.key'),
                    'api_secret' => config('services.movider.secret'),
                    'request_id' => $user->otp_request_id,
                    'code' => $this->code,
                ],
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            // if (isset($data['status']) && $data['status'] === '0') {
            if (!isset($data['error'])) {
                $user->phone_number_verified_at = now();
                $user->otp_request_id = null;
                $user->save();

                Session::forget('otp_last_sent');
                Session::flash('status', 'Phone successfully verified!');
                $this->redirect(route('dashboard', absolute: false), navigate: true);
                return;
            }

            $this->addError('code', 'Invalid or expired OTP code.');
        } catch (\Exception $e) {
            Log::error('Failed to verify OTP via Movider', [
                'message' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            $this->addError('code', 'OTP verification failed. Try again later.');
        }
    }

    public function resend(): void
    {
        $user = Auth::user();

        if (!$user || !$user->phone_number) {
            return;
        }

        $lastSent = session('otp_last_sent');
        $now = now()->timestamp;

        if ($lastSent && $now - (int) $lastSent < 300) {
            $this->addError('code', 'Please wait before requesting another code.');
            return;
        }

        try {
            $client = new Client();
            $response = $client->request('POST', 'https://api.movider.co/v1/verify', [
                'form_params' => [
                    'api_key' => config('services.movider.key'),
                    'api_secret' => config('services.movider.secret'),
                    'to' => $user->phone_number,
                    'code_length' => '6',
                    'from' => 'Chatkoto',
                    'language' => 'en-us',
                    'pin_expire' => '300',
                ],
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['request_id'])) {
                $user->otp_request_id = $data['request_id'];
                $user->save();

                Session::put('otp_last_sent', $now);
                $this->dispatch('otp-resend-timer-start');
                Session::flash('status', 'OTP code resent.');
            } else {
                $this->addError('code', 'Failed to resend OTP.');
            }
        } catch (\Exception $e) {
            Log::error('Failed to resend OTP', ['message' => $e->getMessage()]);
            $this->addError('code', 'Resend failed. Try again later.');
        }
    }
};
?>

@php
    $lastSent = session('otp_last_sent');
    $remaining = $lastSent ? max(0, 300 - (now()->timestamp - (int) $lastSent)) : 0;
@endphp

<div x-data="{
    remaining: {{ $remaining }},
    get canResend() { return this.remaining <= 0 },
    startCountdown() {
        const timer = setInterval(() => {
            if (this.remaining > 0) {
                this.remaining--;
            } else {
                clearInterval(timer);
            }
        }, 1000);
    }
}" x-init="startCountdown();
$wire.on('otp-resend-timer-start', () => {
    remaining = 300;
    startCountdown();
})">
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Enter the 6-digit code sent to your phone to verify your number.') }}
    </div>

    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-4">
        <x-text-input wire:model.defer="code" type="text" maxlength="6" placeholder="Enter OTP" class="w-full" />
        @error('code')
            <div class="text-sm text-red-600 mt-2">{{ $message }}</div>
        @enderror
    </div>

    <div class="mt-4 flex items-center justify-between">
        <x-primary-button wire:click="verify">
            {{ __('Verify') }}
        </x-primary-button>

        <button type="button" wire:click="resend" x-bind:disabled="!canResend"
            class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 disabled:opacity-50">
            <span x-show="canResend">{{ __('Resend OTP') }}</span>
            <span x-show="!canResend">
                {{ __('Resend in') }}
                <span x-text="Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0')"></span>
            </span>
        </button>
    </div>
</div>
