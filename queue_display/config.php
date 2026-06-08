<?php
// Queue Display — config (no session, no auth)
function loadLocalSettings()
{
    $file = getSettingsLocalFilePath();
    if (!is_file($file)) return array();
    $settings = require $file;
    return is_array($settings) ? $settings : array();
}

function localSetting(array $settings, $key, $default)
{
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

$__localSettings = loadLocalSettings();

define('APP_TITLE',        'Queue Display');
define('APP_TIMEZONE',     'Asia/Bangkok');
define('QUEUE_REFRESH_MS', (int)localSetting($__localSettings, 'queue_refresh_ms', 5000));
define('READY_LIMIT',      (int)localSetting($__localSettings, 'ready_limit',      30));
define('PREPARING_LIMIT',  (int)localSetting($__localSettings, 'preparing_limit',  30));

define('KDS_ENV_DB_HOST', 'KDS_DB_HOST');
define('KDS_ENV_DB_PORT', 'KDS_DB_PORT');
define('KDS_ENV_DB_NAME', 'KDS_DB_NAME');
define('KDS_ENV_DB_USER', 'KDS_DB_USER');
define('KDS_ENV_DB_PASS', 'KDS_DB_PASS');

if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(APP_TIMEZONE);
}

function getLocalSettings()
{
    static $settings = null;
    if ($settings === null) $settings = loadLocalSettings();
    return $settings;
}

function getSettingsLocalFilePath()
{
    $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? (string)$_SERVER['SCRIPT_FILENAME'] : '';
    if ($scriptFile !== '') {
        $scriptDir = dirname(realpath($scriptFile) ?: $scriptFile);
        $localPath = $scriptDir . DIRECTORY_SEPARATOR . 'settings.local.php';
        if (is_file($localPath)) return $localPath;
    }
    return __DIR__ . DIRECTORY_SEPARATOR . 'settings.local.php';
}

function getDbConfig()
{
    static $dbConfig = null;
    if ($dbConfig !== null) return $dbConfig;

    $envConfig = array(
        'host' => getenv(KDS_ENV_DB_HOST) ?: '',
        'port' => getenv(KDS_ENV_DB_PORT) ?: '',
        'name' => getenv(KDS_ENV_DB_NAME) ?: '',
        'user' => getenv(KDS_ENV_DB_USER) ?: '',
        'pass' => getenv(KDS_ENV_DB_PASS) ?: '',
    );
    if ($envConfig['host'] !== '' && $envConfig['name'] !== '' && $envConfig['user'] !== '') {
        $dbConfig = normalizeDbConfig($envConfig);
        return $dbConfig;
    }

    $local = getLocalSettings();
    $dbConfig = normalizeDbConfig(array(
        'host' => localSetting($local, 'db_host', ''),
        'port' => localSetting($local, 'db_port', 3307),
        'name' => localSetting($local, 'db_name', ''),
        'user' => localSetting($local, 'db_user', ''),
        'pass' => localSetting($local, 'db_pass', ''),
    ));
    return $dbConfig;
}

function normalizeDbConfig($config)
{
    $host = trim((string)($config['host'] ?? ''));
    $port = (int)($config['port']  ?? 3307);
    $name = trim((string)($config['name'] ?? ''));
    $user = trim((string)($config['user'] ?? ''));
    $pass = (string)($config['pass'] ?? '');
    if ($host === '' || $name === '' || $user === '') {
        throw new Exception('DB config incomplete: host/name/user required');
    }
    if ($port <= 0) $port = 3307;
    return compact('host', 'port', 'name', 'user', 'pass');
}

function getDbConnection()
{
    $db   = getDbConfig();
    $conn = new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], (int)$db['port']);
    if ($conn->connect_error) throw new Exception('DB connection failed: ' . $conn->connect_error);
    if (!$conn->set_charset('utf8')) throw new Exception('Cannot set charset utf8');
    $conn->query("SET time_zone = '+07:00'");
    return $conn;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function jsonResponse($payload)
{
    while (ob_get_level()) ob_end_clean();
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    $body = json_encode($payload, $flags);
    echo $body !== false ? $body : '{"success":false,"error":"json encode error"}';
    exit;
}
