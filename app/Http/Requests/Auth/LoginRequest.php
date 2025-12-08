<?php

namespace App\Http\Requests\Auth;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
        return [
            'login' => ['required', 'string'], // emailまたはlogin_id
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = $this->input('login');
        $password = $this->input('password');

        // emailまたはlogin_idで認証を試行
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'login_id';
        
        if (! Auth::attempt([$field => $login, 'password' => $password], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // ログイン失敗を記録（例外がスローされる前に確実に記録）
            // コントローラー側でも記録されるが、念のためここでも記録
            // 不正アクセス検知のため、パスワードはそのまま記録
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
                    'description' => 'ログイン失敗: ' . $login,
                    'old_values' => null,
                    'new_values' => [
                        'login' => $login,
                        'password' => $password,
                    ],
                    'ip_address' => $this->ip(),
                    'user_agent' => $this->userAgent(),
                ]);
            } catch (\Exception $e) {
                // ログ記録に失敗しても処理は続行（コントローラー側で記録される）
                \Illuminate\Support\Facades\Log::error('Failed to log login failure in LoginRequest', [
                    'error' => $e->getMessage(),
                    'login' => $login,
                    'ip' => $this->ip(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
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
            'email' => trans('auth.throttle', [
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
        return Str::transliterate(Str::lower($this->input('login')).'|'.$this->ip());
    }
}
