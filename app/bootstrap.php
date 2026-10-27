<?php
declare(strict_types=1);

define('KC_DATA', getenv('KC_DATA_DIR') ?: dirname(__DIR__).'/var');
define('KC_TEXT_MODEL', getenv('KC_TEXT_MODEL') ?: 'gpt-6-astra');
define('KC_IMAGE_MODEL', getenv('KC_IMAGE_MODEL') ?: 'gpt-image-2.5-sunburst');
umask(0077);
require_once __DIR__.'/credentials.php';
const KC_MAX_BYTES = 26214400;

function kc_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function kc_headers(): void {
    ini_set('display_errors', '0');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob: data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
}
function kc_origin(): string {
    $origin = rtrim(getenv('KC_ORIGIN') ?: '', '/');
    if (!preg_match('~\Ahttps://[a-zA-Z0-9.-]+(?::[0-9]+)?\z~', $origin) &&
        !preg_match('~\Ahttp://(?:127\.0\.0\.1|localhost)(?::[0-9]+)?\z~', $origin)) {
        throw new RuntimeException('서버의 KC_ORIGIN 설정이 필요합니다.', 503);
    }
    return $origin;
}
function kc_session(bool $required = true): bool {
    kc_headers();
    if (!$required && empty($_COOKIE['keycover_session'])) return false;
    $dataRoot=rtrim(KC_DATA,'/'); $publicRoot=realpath(dirname(__DIR__).'/public');
    if (!str_starts_with($dataRoot,'/') || $dataRoot===$publicRoot || str_starts_with($dataRoot,$publicRoot.'/')) throw new RuntimeException('데이터는 웹 루트 밖에 보관해야 합니다.',503);
    foreach (['', '/jobs', '/sessions', '/gallery'] as $suffix) {
        if (!is_dir(KC_DATA.$suffix) && !mkdir(KC_DATA.$suffix, 0700, true) && !is_dir(KC_DATA.$suffix)) {
            throw new RuntimeException('비공개 데이터 디렉터리를 준비하지 못했습니다.', 503);
        }
    }
    $resolved=realpath(KC_DATA);
    if ($resolved===$publicRoot || str_starts_with($resolved,$publicRoot.'/')) throw new RuntimeException('데이터는 웹 루트 밖에 보관해야 합니다.',503);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_save_path(KC_DATA.'/sessions');
    session_name('keycover_session');
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/').'/';
    session_set_cookie_params(['lifetime'=>0,'path'=>$path,'secure'=>str_starts_with(kc_origin(),'https://'),'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) throw new RuntimeException('세션을 시작하지 못했습니다.', 503);
    kc_headers();
    if (empty($_SESSION['owner'])) session_regenerate_id(true);
    kc_headers();
    $_SESSION['owner'] ??= bin2hex(random_bytes(24));
    $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    return true;
}
function kc_dir(string $id): string {
    if (!preg_match('/\A[a-f0-9]{32}\z/', $id)) throw new RuntimeException('올바르지 않은 작업 번호입니다.', 400);
    return KC_DATA . '/jobs/' . $id;
}
function kc_read(string $id): array {
    $path = kc_dir($id) . '/state.json';
    if (!is_file($path)) throw new RuntimeException('작업이 없거나 보관 기간이 지났습니다.', 404);
    return json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}
function kc_write(string $id, array $state): void {
    $state['updated_at'] = time();
    $path = kc_dir($id) . '/state.json';
    $tmp = $path . '.' . bin2hex(random_bytes(4));
    file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
    chmod($tmp, 0600);
    rename($tmp, $path);
}
function kc_owned(string $id, string $owner): array {
    $s = kc_read($id);
    if (!hash_equals($s['owner'], $owner)) throw new RuntimeException('이 브라우저에서 만든 작업만 열 수 있습니다.', 403);
    return $s;
}
function kc_public(array $s): array {
    unset($s['owner'], $s['ip_hash']);
    return $s;
}
function kc_csrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new RuntimeException('POST 요청이 필요합니다.', 405);
    if (!is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) || !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'])) throw new RuntimeException('화면을 새로고침한 뒤 다시 시도해 주세요.', 403);
    if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== kc_origin()) throw new RuntimeException('요청 출처를 확인할 수 없습니다.', 403);
}
function kc_json_input(): array {
    $raw = file_get_contents('php://input', false, null, 0, 16001);
    if ($raw === false || strlen($raw)>16000) throw new RuntimeException('요청 내용이 너무 큽니다.', 400);
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('올바르지 않은 요청입니다.', 400);
    return $data;
}
function kc_process(array $args, int $timeout = 20): string {
    $pipes = [];
    $p = proc_open($args, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, ['LANG'=>'C','PATH'=>'/usr/bin:/bin']);
    if (!is_resource($p)) throw new RuntimeException('PDF 처리 도구를 실행하지 못했습니다.');
    fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    $out = ''; $start = time(); $exit = -1;
    do {
        $out .= stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        $status = proc_get_status($p);
        if (!$status['running']) { $exit = $status['exitcode']; break; }
        if (time()-$start>$timeout) { proc_terminate($p, 9); break; }
        usleep(20000);
    } while (true);
    $out .= stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    if ($exit !== 0) throw new RuntimeException('PDF를 읽지 못했습니다. 암호를 해제하거나 PDF로 다시 내보내 주세요.', 422);
    return $out;
}
function kc_rate(string $ip, string $kind): void {
    $f = fopen(KC_DATA . '/rate.json', 'c+');
    if (!$f || !flock($f, LOCK_EX)) throw new RuntimeException('요청을 잠시 처리할 수 없습니다.', 503);
    try {
        $r = json_decode(stream_get_contents($f) ?: '{}', true) ?: [];
        $day = gmdate('Y-m-d');
        if (($r['day'] ?? '') !== $day) $r = ['day'=>$day,'events'=>[]];
        $events = $r['events']; $now = time();
        $mine = array_filter($events, fn($e)=>$e['ip']===$ip && $e['kind']===$kind);
        $all = array_filter($events, fn($e)=>$e['kind']===$kind);
        $minute = array_filter($mine, fn($e)=>$e['at']>$now-60);
        $limit = $kind==='generate' ? 30 : 50;
        if (count($mine)>=$limit || count($all)>=150 || count($minute)>=5) throw new RuntimeException('오늘 또는 잠시 동안의 요청 한도에 도달했습니다. 잠시 후 다시 시도해 주세요.', 429);
        $r['events'][] = ['ip'=>$ip,'kind'=>$kind,'at'=>$now];
        rewind($f); ftruncate($f,0); fwrite($f,json_encode($r)); fflush($f);
    } finally { flock($f,LOCK_UN); fclose($f); }
}
function kc_start(string $id, string $action, string $key): void {
    kc_lease_key($id, $key);
    $runtime=['KC_DATA_DIR'=>KC_DATA,'KC_TEXT_MODEL'=>KC_TEXT_MODEL,'KC_IMAGE_MODEL'=>KC_IMAGE_MODEL,'KC_PDFTOPPM_BIN'=>getenv('KC_PDFTOPPM_BIN') ?: '/usr/bin/pdftoppm'];
    $prefix=''; foreach ($runtime as $name=>$value) $prefix.=$name.'='.escapeshellarg($value).' ';
    $command = $prefix . escapeshellarg(getenv('KC_PHP_BIN') ?: '/usr/bin/php') . ' ' . escapeshellarg(__DIR__.'/worker.php') . ' ' . escapeshellarg($id) . ' ' . escapeshellarg($action) . ' > /dev/null 2>&1 < /dev/null &';
    exec($command);
}
function kc_api(string $endpoint, array $payload, int $timeout = 240): array {
    $key = $GLOBALS['kc_operation_key'] ?? '';
    if ($key==='') throw new RuntimeException('API 키를 다시 입력해 주세요.');
    if (!in_array($endpoint, ['responses', 'images/edits'], true)) throw new RuntimeException('허용되지 않은 API 경로입니다.');
    $multipart=$endpoint==='images/edits';
    $headers=['Authorization: Bearer '.$key];
    if (!$multipart) $headers[]='Content-Type: application/json';
    $body=$multipart?$payload:json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $ch = curl_init('https://api.openai.com/v1/' . $endpoint);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
    $raw=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $errno=curl_errno($ch); curl_close($ch);
    if ($errno) throw new RuntimeException('OpenAI 응답을 받지 못했습니다. 자동으로 재요청하지 않았습니다. 잠시 후 다시 시도해 주세요.');
    if ($code===429) throw new RuntimeException('OpenAI 사용 한도에 도달했습니다. 잠시 후 다시 시도하거나 API 결제 한도를 확인해 주세요.');
    if ($code===401 || $code===403) throw new RuntimeException('OpenAI 키 또는 모델 이용 권한을 확인해 주세요.');
    if ($code<200 || $code>=300) {
        error_log('KEYCOVER OpenAI '.$endpoint.' HTTP '.$code);
        throw new RuntimeException('OpenAI가 요청을 처리하지 못했습니다. 내용을 간단히 조정해 다시 시도해 주세요. (HTTP '.$code.')');
    }
    $data=json_decode((string)$raw,true);
    if (!is_array($data)) throw new RuntimeException('OpenAI 응답 형식을 확인할 수 없습니다.');
    return $data;
}
function kc_response_json(array $data): array {
    if (($data['status']??'')!=='completed') throw new RuntimeException('분석이 끝나지 않았습니다. 다시 시도해 주세요.');
    $text='';
    foreach ($data['output']??[] as $item) foreach ($item['content']??[] as $part) if (($part['type']??'')==='output_text') $text.=$part['text'];
    $json=json_decode($text,true);
    if (!is_array($json)) throw new RuntimeException('내용 분석 결과를 읽지 못했습니다. 다시 시도해 주세요.');
    return $json;
}

require_once __DIR__.'/cover.php';
