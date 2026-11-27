<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * アプリで対応できる範囲のセキュリティヘッダー（P1・P2）。
 *
 * - HTTPS のときだけ HSTS を付ける（TrustProxies により、手前の Apache が付ける X-Forwarded-Proto も見る）
 * - X-Powered-By（PHP のバージョン）を消す
 *
 * サーバー（Apache）の設定でも同じことができる。手順は PR の説明を参照。
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (config('security_headers.remove_powered_by', true)) {
            $response->headers->remove('X-Powered-By');
            if (!headers_sent() && function_exists('header_remove')) {
                header_remove('X-Powered-By');
            }
        }

        if (config('security_headers.hsts.enabled', true)
            && $request->isSecure()
            && !$response->headers->has('Strict-Transport-Security')) {
            $value = 'max-age='.(int) config('security_headers.hsts.max_age', 31536000);
            if (config('security_headers.hsts.include_subdomains', false)) {
                $value .= '; includeSubDomains';
            }
            $response->headers->set('Strict-Transport-Security', $value);
        }

        return $response;
    }
}
