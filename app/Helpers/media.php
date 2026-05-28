<?php

if (! function_exists('encrypt_secret')) {
    function encrypt_secret(string $secret): string
    {
        return encrypt($secret);
    }
}

if (! function_exists('decrypt_secret')) {
    function decrypt_secret(string $encrypted): string
    {
        return decrypt($encrypted);
    }
}

if (! function_exists('generate_media_token')) {
    function generate_media_token(\App\Models\Item $item): string
    {
        $client = \App\Models\MediaClient::where('app_id', 'mms')->where('is_active', true)->first();
        if (! $client) {
            return '';
        }
        $secret = decrypt_secret($client->encrypted_secret);
        $payload = json_encode([
            'app' => 'mms',
            'code' => $item->code,
            'exp' => time() + 300, // 5 min, le temps de la prévisualisation
        ]);
        $payloadBase64 = base64_encode($payload);
        $signature = hash_hmac('sha256', $payloadBase64, $secret);

        return $payloadBase64.'.'.$signature;
    }
}

if (! function_exists('is_publicly_accessible')) {
    function is_publicly_accessible(\Illuminate\Database\Eloquent\Model $entity): bool
    {
        if ($entity instanceof \App\Models\Item && $entity->is_sub) {
            return $entity->public_access === 'full';
        }

        if ($entity instanceof \App\Models\Item && ! $entity->is_sub) {
            $collection = $entity->itemable;
            if (! $collection instanceof \App\Models\Collection) {
                return $entity->public_access === 'full';
            }

            return $collection->public_access === 'full' && $entity->public_access === 'full';
        }

        return $entity->public_access === 'full';
    }
}

if (! function_exists('verify_media_token')) {
    function verify_media_token(string $token, string $resourceCode): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        [$payloadBase64, $hmacBase64] = $parts;

        $payloadJson = base64_decode($payloadBase64);
        $payload = json_decode($payloadJson, true);

        if (! $payload || ! isset($payload['app'], $payload['code'], $payload['exp'])) {
            return false;
        }
        if ($payload['code'] !== $resourceCode) {
            return false;
        }
        if ($payload['exp'] < time()) {
            return false;
        }

        $client = \App\Models\MediaClient::where('app_id', $payload['app'])->where('is_active', true)->first();
        if (! $client) {
            return false;
        }

        $secret = decrypt_secret($client->encrypted_secret);
        $expectedHmac = hash_hmac('sha256', $payloadBase64, $secret);

        return hash_equals($expectedHmac, $hmacBase64);
    }
}
