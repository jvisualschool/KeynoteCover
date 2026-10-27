<?php
declare(strict_types=1);

const KC_KEY_TTL = 28800;
function kc_validate_key(mixed $value): string {
    if (!is_string($value) || !preg_match('/\Ask-[A-Za-z0-9_-]{20,240}\z/', $value)) {
        throw new RuntimeException('OpenAI API 키 형식을 확인해 주세요.', 400);
    }
    return $value;
}
function kc_vault_key(): string {
    $path = KC_DATA . '/credential.key';
    if (!is_file($path)) {
        $handle = @fopen($path, 'x');
        if ($handle) {
            chmod($path, 0600);
            if (fwrite($handle, random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                fclose($handle); throw new RuntimeException('키 보관소를 준비하지 못했습니다.', 503);
            }
            fclose($handle);
        }
    }
    $key = @file_get_contents($path);
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('키 보관소 설정을 확인해 주세요.', 503);
    }
    return $key;
}
function kc_seal_key(string $key, int $expires): string {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox(json_encode(['key'=>$key,'expires'=>$expires], JSON_THROW_ON_ERROR), $nonce, kc_vault_key()));
}
function kc_open_key(string $sealed): string {
    $raw = base64_decode($sealed, true);
    if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        throw new RuntimeException('API 키를 다시 입력해 주세요.', 409);
    }
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), kc_vault_key());
    if ($plain === false) throw new RuntimeException('API 키를 다시 입력해 주세요.', 409);
    $record = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
    if (($record['expires'] ?? 0) <= time()) throw new RuntimeException('API 키 사용 시간이 만료됐습니다. 다시 입력해 주세요.', 409);
    return kc_validate_key($record['key'] ?? null);
}
function kc_key_ready(): bool {
    if (empty($_SESSION['openai_key'])) return false;
    try { kc_open_key($_SESSION['openai_key']); return true; }
    catch (Throwable) { unset($_SESSION['openai_key'], $_SESSION['key_expires']); return false; }
}
function kc_user_key(): string {
    if (!kc_key_ready()) throw new RuntimeException('먼저 자신의 OpenAI API 키를 입력해 주세요.', 409);
    return kc_open_key($_SESSION['openai_key']);
}
function kc_key_status(): array {
    $ready = kc_key_ready();
    return ['key_ready'=>$ready, 'key_expires'=>$ready ? $_SESSION['key_expires'] : null];
}
function kc_lease_key(string $id, string $key): void {
    $path = kc_dir($id) . '/credential.enc';
    $tmp = $path . '.' . bin2hex(random_bytes(8));
    $sealed = kc_seal_key($key, time() + 1200);
    if (file_put_contents($tmp, $sealed, LOCK_EX) === false) throw new RuntimeException('작업을 준비하지 못했습니다.', 503);
    chmod($tmp, 0600); rename($tmp, $path);
}
function kc_take_key(string $id): string {
    $path = kc_dir($id) . '/credential.enc';
    $sealed = @file_get_contents($path);
    if (is_file($path)) unlink($path);
    if (!is_string($sealed)) throw new RuntimeException('API 키를 다시 입력해 작업을 시작해 주세요.', 409);
    return kc_open_key($sealed);
}
