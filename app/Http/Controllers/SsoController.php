<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    protected function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function generateVisSso(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        // Only allow Admin GA and GA Manager
        $allowedRoles = ['admin_ga', 'admin_ga_manager', 'super_admin'];
        if (!in_array($user->role, $allowedRoles, true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $secret = env('VIS_SSO_SECRET', null);
        if (!$secret) {
            Log::error('VIS_SSO_SECRET is not set');
            return response()->json(['success' => false, 'error' => 'SSO not configured'], 500);
        }

        $claims = [
            'iss'   => env('APP_URL', 'http://localhost'),
            'aud'   => 'vis-admin',
            'sub'   => (string)$user->id,
            'name'  => (string)$user->name,
            'email' => (string)($user->email ?? ''),
            'role'  => (string)$user->role,
            'iat'   => time(),
            'exp'   => time() + 300, // valid 5 minutes to tolerate redirects
            'nonce' => (string) Str::uuid(),
        ];

        $payload = $this->base64url_encode(json_encode($claims));
        $sig     = $this->base64url_encode(hash_hmac('sha256', $payload, $secret, true));
        $token   = $payload . '.' . $sig;

        // Prefer explicit frontend URL for SPA; fallback to SSO target base
        $rawTarget = env('VIS_FRONTEND_URL', env('VIS_SSO_TARGET', 'http://127.0.0.1:3000'));

        // If this request originates from localhost, force VIS to localhost too
        $requestHost = $request->getHost();
        if (in_array($requestHost, ['localhost', '127.0.0.1'], true)) {
            $rawTarget = 'http://127.0.0.1:8085';
        }

        // Parse components and normalize port (map 8015 -> 8085)
        $scheme = parse_url($rawTarget, PHP_URL_SCHEME) ?: 'http';
        $hostPart = parse_url($rawTarget, PHP_URL_HOST) ?: '127.0.0.1';
        $portPart = parse_url($rawTarget, PHP_URL_PORT);
        // Force SPA port 3000 when pointing to backend ports
        if ($portPart === 8015 || $portPart === 8085 || !$portPart) {
            $portPart = 3000;
        }

        $base = $scheme . '://' . $hostPart . ($portPart ? (':' . $portPart) : '');
        // Open SPA verify page; it will call backend /api/v1/admin/sso/verify and handle UX
        $finalVerifyPath = '/admin/sso/verify';
        $redirectUrl = $base . $finalVerifyPath . '?token=' . $token;

        return response()->json(['success' => true, 'url' => $redirectUrl]);
    }
}

