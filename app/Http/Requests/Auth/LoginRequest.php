<?php

namespace App\Http\Requests\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
        ];
        if (config('auth.security_login')) {
            $rules['password'] = ['required', 'string'];
        }
        return $rules;
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $userId = (int) $this->input('user_id');
        $shopId = (int) $this->input('shop_id');

        $user = User::find($userId);
        if (! $user) {
            $this->recordLoginFailure($userId, $shopId);
            throw ValidationException::withMessages([
                'user_id' => trans('auth.failed'),
            ]);
        }

        // 選択ユーザーが選択店舗に所属しているか確認
        if (! $user->shops()->where('shops.id', $shopId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => trans('auth.failed'),
            ]);
        }

        if (config('auth.security_login')) {
            $password = $this->input('password');
            if (! Hash::check($password, $user->password)) {
                RateLimiter::hit($this->throttleKey());
                $this->recordLoginFailure($userId, $shopId);
                throw ValidationException::withMessages([
                    'password' => trans('auth.failed'),
                ]);
            }
        }

        RateLimiter::clear($this->throttleKey());
        Auth::login($user, $this->boolean('remember'));
    }

    /**
     * ログイン失敗を記録する
     */
    private function recordLoginFailure(int $userId, int $shopId): void
    {
        try {
            ActivityLog::create([
                'user_id' => null,
                'shop_id' => null,
                'action_type' => 'login_failed',
                'resource_type' => null,
                'resource_id' => null,
                'route_name' => 'login',
                'url' => $this->fullUrl(),
                'method' => $this->method(),
                'description' => 'ログイン失敗: user_id=' . $userId,
                'old_values' => null,
                'new_values' => [
                    'user_id' => $userId,
                    'shop_id' => $shopId,
                ],
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log login failure in LoginRequest', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $this->ip(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'password' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('user_id')).'|'.$this->ip());
    }
}
