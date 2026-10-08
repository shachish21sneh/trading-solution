<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrokerAuthController
{
    /**
     * Redirect to Zerodha Kite Login
     */
    public function zerodhaLogin()
    {
        $apiKey = env('KITE_API_KEY');
        if (! $apiKey) {
            return response()->json([
                'error' => 'KITE_API_KEY is not set in .env. Please add your Zerodha Kite API Key first.',
            ], 400);
        }

        $url = "https://kite.zerodha.com/connect/login?v=3&api_key={$apiKey}";

        return redirect()->away($url);
    }

    /**
     * Zerodha Kite OAuth Callback: exchanges request_token for access_token
     */
    public function zerodhaCallback(Request $request)
    {
        $requestToken = $request->query('request_token');
        $status = $request->query('status');

        if ($status !== 'success' || ! $requestToken) {
            return response()->json([
                'error' => 'Zerodha authorization failed or was cancelled.',
                'details' => $request->all(),
            ], 400);
        }

        $apiKey = env('KITE_API_KEY');
        $apiSecret = env('KITE_API_SECRET');

        if (! $apiKey || ! $apiSecret) {
            return response()->json([
                'error' => 'KITE_API_KEY or KITE_API_SECRET missing in .env',
            ], 400);
        }

        // SHA-256 checksum: SHA256(api_key + request_token + api_secret)
        $checksum = hash('sha256', $apiKey.$requestToken.$apiSecret);

        try {
            $response = Http::withoutVerifying()->asForm()->post('https://api.kite.trade/session/token', [
                'api_key' => $apiKey,
                'request_token' => $requestToken,
                'checksum' => $checksum,
            ]);

            if ($response->successful()) {
                $data = $response->json('data');
                $accessToken = $data['access_token'];

                // Save to Cache & update runtime config
                Cache::forever('kite:access_token', $accessToken);
                Cache::forever('market_data_provider', 'zerodha');
                $this->updateEnvFile('KITE_ACCESS_TOKEN', $accessToken);
                $this->updateEnvFile('MARKET_DATA_PROVIDER', 'zerodha');

                return redirect('/?broker_connected=zerodha&token_generated=true');
            }

            return response()->json([
                'error' => 'Failed to exchange request_token with Zerodha Kite',
                'response' => $response->json(),
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Zerodha OAuth Error: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Redirect to Upstox Login
     */
    public function upstoxLogin()
    {
        $apiKey = env('UPSTOX_API_KEY');
        if (! $apiKey) {
            return response()->json([
                'error' => 'UPSTOX_API_KEY is not set in .env. Please add your Upstox API Key first.',
            ], 400);
        }

        $redirectUri = urlencode($this->getUpstoxRedirectUri());
        $url = "https://api.upstox.com/v2/login/authorization/dialog?response_type=code&client_id={$apiKey}&redirect_uri={$redirectUri}";

        return redirect()->away($url);
    }

    /**
     * Upstox OAuth Callback: exchanges auth code for access_token
     */
    public function upstoxCallback(Request $request)
    {
        $code = $request->query('code');
        if (! $code) {
            return response()->json(['error' => 'No authorization code returned from Upstox.'], 400);
        }

        $apiKey = env('UPSTOX_API_KEY');
        $apiSecret = env('UPSTOX_API_SECRET');
        $redirectUri = $this->getUpstoxRedirectUri();

        try {
            $response = Http::withoutVerifying()->asForm()->post('https://api.upstox.com/v2/login/authorization/token', [
                'code' => $code,
                'client_id' => $apiKey,
                'client_secret' => $apiSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'];

                // Save to Cache & update runtime config
                Cache::forever('upstox:access_token', $accessToken);
                Cache::forever('market_data_provider', 'upstox');
                $this->updateEnvFile('UPSTOX_ACCESS_TOKEN', $accessToken);
                $this->updateEnvFile('MARKET_DATA_PROVIDER', 'upstox');

                return redirect('/?broker_connected=upstox&token_generated=true');
            }

            $errorMsg = $response->json('errors.0.message') ?? $response->json('message') ?? 'Upstox authorization code expired or invalid.';

            return $this->renderOAuthError('Upstox Authorization Failed', $errorMsg, route('broker.upstox.login'), 'Re-Connect Upstox Now');
        } catch (\Throwable $e) {
            Log::error('Upstox OAuth Error: '.$e->getMessage());

            return $this->renderOAuthError('Upstox Connection Error', $e->getMessage(), route('broker.upstox.login'), 'Try Connecting Again');
        }
    }

    /**
     * Get current broker connection status and configurations
     */
    public function status()
    {
        $currentProvider = Cache::get('market_data_provider', env('MARKET_DATA_PROVIDER', 'simulation'));

        return response()->json([
            'current_provider' => $currentProvider,
            'upstox' => [
                'has_api_key' => ! empty(env('UPSTOX_API_KEY')),
                'has_api_secret' => ! empty(env('UPSTOX_API_SECRET')),
                'has_token' => ! empty(Cache::get('upstox:access_token', env('UPSTOX_ACCESS_TOKEN'))),
                'redirect_url' => url('/broker/upstox/callback'),
            ],
            'zerodha' => [
                'has_api_key' => ! empty(env('KITE_API_KEY')),
                'has_api_secret' => ! empty(env('KITE_API_SECRET')),
                'has_token' => ! empty(Cache::get('kite:access_token', env('KITE_ACCESS_TOKEN'))),
                'redirect_url' => url('/broker/zerodha/callback'),
            ],
            'angelone' => [
                'has_api_key' => ! empty(env('ANGELONE_API_KEY', env('SMARTAPI_API_KEY'))),
                'has_client_code' => ! empty(env('ANGELONE_CLIENT_CODE', env('SMARTAPI_CLIENT_CODE'))),
                'has_password' => ! empty(env('ANGELONE_PASSWORD', env('SMARTAPI_PASSWORD'))),
                'has_totp_secret' => ! empty(env('ANGELONE_TOTP_SECRET', env('SMARTAPI_TOTP'))),
                'has_token' => ! empty(Cache::get('angelone:jwt_token', env('ANGELONE_JWT_TOKEN', env('SMARTAPI_JWT_TOKEN')))),
                'api_key' => env('ANGELONE_API_KEY', env('SMARTAPI_API_KEY', '')),
                'client_code' => env('ANGELONE_CLIENT_CODE', env('SMARTAPI_CLIENT_CODE', '')),
                'public_ip' => env('ANGELONE_CLIENT_IP', '122.168.79.123'),
            ],
        ]);
    }

    /**
     * Save broker API keys directly from UI modal
     */
    public function saveCredentials(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:upstox,zerodha,angelone,simulation',
            'api_key' => 'nullable|string|max:100',
            'api_secret' => 'nullable|string|max:100',
            'client_code' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:100',
            'totp_secret' => 'nullable|string|max:100',
        ]);

        $provider = $request->input('provider');

        if ($provider === 'simulation') {
            Cache::forever('market_data_provider', 'simulation');
            $this->updateEnvFile('MARKET_DATA_PROVIDER', 'simulation');

            return response()->json(['success' => true, 'message' => 'Switched to Simulated Data Stream.']);
        }

        if ($provider === 'upstox') {
            if ($request->filled('api_key')) {
                $this->updateEnvFile('UPSTOX_API_KEY', $request->input('api_key'));
            }
            if ($request->filled('api_secret')) {
                $this->updateEnvFile('UPSTOX_API_SECRET', $request->input('api_secret'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Upstox credentials saved! Now click Connect to login and authorize.',
                'auth_url' => route('broker.upstox.login'),
            ]);
        }

        if ($provider === 'zerodha') {
            if ($request->filled('api_key')) {
                $this->updateEnvFile('KITE_API_KEY', $request->input('api_key'));
            }
            if ($request->filled('api_secret')) {
                $this->updateEnvFile('KITE_API_SECRET', $request->input('api_secret'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Zerodha Kite credentials saved! Now click Connect to authorize with Kite.',
                'auth_url' => route('broker.zerodha.login'),
            ]);
        }

        if ($provider === 'angelone') {
            if ($request->filled('api_key')) {
                $this->updateEnvFile('ANGELONE_API_KEY', $request->input('api_key'));
            }
            if ($request->filled('client_code')) {
                $this->updateEnvFile('ANGELONE_CLIENT_CODE', $request->input('client_code'));
            }
            if ($request->filled('password')) {
                $this->updateEnvFile('ANGELONE_PASSWORD', $request->input('password'));
            }
            if ($request->filled('totp_secret')) {
                $this->updateEnvFile('ANGELONE_TOTP_SECRET', $request->input('totp_secret'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Angel One credentials saved! Now click 1-Click Connect to authenticate and generate session.',
                'auth_url' => route('broker.angelone.login'),
            ]);
        }

        return response()->json(['error' => 'Invalid provider specified.'], 400);
    }

    /**
     * Angel One SmartAPI 1-Click Login & Session Generator
     */
    public function angeloneAuthorize(Request $request)
    {
        $apiKey = $request->input('api_key') ?: env('ANGELONE_API_KEY', env('SMARTAPI_API_KEY'));
        $clientCode = $request->input('client_code') ?: env('ANGELONE_CLIENT_CODE', env('SMARTAPI_CLIENT_CODE'));
        $password = $request->input('password') ?: env('ANGELONE_PASSWORD', env('SMARTAPI_PASSWORD'));
        $totp = $request->input('totp');
        $totpSecret = $request->input('totp_secret') ?: env('ANGELONE_TOTP_SECRET', env('SMARTAPI_TOTP'));

        if (! $apiKey || ! $clientCode || ! $password) {
            return response()->json([
                'success' => false,
                'error' => 'API Key, Client Code, and Password/MPIN are required for Angel One.',
            ], 422);
        }

        // If no direct 6-digit TOTP is provided, generate from TOTP secret
        if (empty($totp) && ! empty($totpSecret)) {
            $totp = $this->generateTotp($totpSecret);
        }

        if (empty($totp)) {
            return response()->json([
                'success' => false,
                'error' => 'Please provide either the current 6-digit TOTP from your authenticator app or your TOTP Secret Key.',
            ], 422);
        }

        $publicIp = env('ANGELONE_CLIENT_IP', '122.168.79.123');

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-UserType' => 'USER',
                'X-SourceID' => 'WEB',
                'X-ClientLocalIP' => '127.0.0.1',
                'X-ClientPublicIP' => $publicIp,
                'X-MACAddress' => '00:00:00:00:00:00',
                'X-PrivateKey' => $apiKey,
            ])->post('https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword', [
                'clientcode' => $clientCode,
                'password' => $password,
                'totp' => (string) $totp,
            ]);

            if ($response->successful() && $response->json('status') === true) {
                $jwtToken = $response->json('data.jwtToken');
                $refreshToken = $response->json('data.refreshToken');
                $feedToken = $response->json('data.feedToken');

                Cache::forever('angelone:jwt_token', $jwtToken);
                Cache::forever('angelone:feed_token', $feedToken);
                Cache::forever('market_data_provider', 'angelone');

                $this->updateEnvFile('ANGELONE_API_KEY', $apiKey);
                $this->updateEnvFile('ANGELONE_CLIENT_CODE', $clientCode);
                $this->updateEnvFile('ANGELONE_PASSWORD', $password);
                if (! empty($totpSecret)) {
                    $this->updateEnvFile('ANGELONE_TOTP_SECRET', $totpSecret);
                }
                $this->updateEnvFile('ANGELONE_JWT_TOKEN', $jwtToken);
                if ($feedToken) {
                    $this->updateEnvFile('ANGELONE_FEED_TOKEN', $feedToken);
                }
                $this->updateEnvFile('MARKET_DATA_PROVIDER', 'angelone');

                return response()->json([
                    'success' => true,
                    'message' => 'Angel One SmartAPI connected successfully! Live feed session active.',
                    'jwt_token' => substr((string) $jwtToken, 0, 10).'...',
                    'feed_token' => $feedToken ? (substr((string) $feedToken, 0, 10).'...') : null,
                ]);
            }

            $errMsg = $response->json('message') ?? $response->json('errorcode') ?? 'Angel One authentication failed. Check credentials and TOTP.';

            return response()->json([
                'success' => false,
                'error' => $errMsg,
                'details' => $response->json(),
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Angel One Auth Exception: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Browser GET fallback for Angel One Login
     */
    public function angeloneLogin(Request $request)
    {
        $res = $this->angeloneAuthorize($request);
        $data = $res->getData(true);

        if (! empty($data['success'])) {
            return redirect('/?broker_connected=angelone&token_generated=true');
        }

        return $this->renderOAuthError(
            'Angel One Authentication',
            $data['error'] ?? 'Authentication failed. Please verify your Client ID, MPIN, and TOTP.',
            '/',
            'Back to Settings'
        );
    }

    /**
     * Standard RFC 6238 TOTP computation from Base32 secret key
     */
    public function generateTotp(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^2-7A-Z]/', '', $secret));
        if (empty($secret)) {
            return '';
        }

        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32Lookup = array_flip(str_split($base32Chars));

        $binaryString = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            if (! isset($base32Lookup[$char])) {
                continue;
            }
            $buffer = ($buffer << 5) | $base32Lookup[$char];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binaryString .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        $timeSlice = floor(time() / 30);
        $timeBytes = pack('N*', 0).pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $timeBytes, $binaryString, true);
        $offset = ord($hash[19]) & 0x0F;

        $otp = (
            ((ord($hash[$offset + 0]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Helper to write a key to .env file
     */
    protected function updateEnvFile(string $key, string $value): void
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        if (str_contains($content, "{$key}=")) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }

        file_put_contents($path, $content);
    }

    /**
     * Render beautiful OAuth error page with retry button
     */
    protected function renderOAuthError(string $title, string $message, string $retryUrl, string $buttonText = 'Try Again')
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { margin: 0; background: #0B0F19; color: #F3F4F6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: #111827; border: 1px solid #1F2937; border-radius: 16px; padding: 36px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .icon { font-size: 44px; margin-bottom: 16px; }
        h2 { font-size: 20px; margin: 0 0 10px 0; color: #FCA5A5; font-weight: 700; }
        p { font-size: 13px; color: #9CA3AF; line-height: 1.6; margin: 0 0 24px 0; word-break: break-word; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #9333EA; hover: background: #7E22CE; color: #FFFFFF; font-weight: 600; font-size: 14px; padding: 12px 24px; border-radius: 10px; text-decoration: none; transition: 0.2s; box-shadow: 0 10px 15px -3px rgba(147, 51, 234, 0.3); }
        .btn:hover { background: #7E22CE; transform: translateY(-1px); }
        .back-link { display: block; margin-top: 16px; font-size: 12px; color: #6B7280; text-decoration: none; }
        .back-link:hover { color: #9CA3AF; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠️</div>
        <h2>{$title}</h2>
        <p>{$message}</p>
        <a href="{$retryUrl}" class="btn">🔑 {$buttonText}</a>
        <a href="/" class="back-link">← Return to Dashboard</a>
    </div>
</body>
</html>
HTML;

        return response($html, 400)->header('Content-Type', 'text/html');
    }

    protected function getUpstoxRedirectUri(): string
    {
        return env('UPSTOX_REDIRECT_URI', 'http://localhost:8000/broker/upstox/callback');
    }

    protected function getZerodhaRedirectUri(): string
    {
        return env('KITE_REDIRECT_URI', 'http://localhost:8000/broker/zerodha/callback');
    }
}
