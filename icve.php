<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'code' => 405,
        'msg' => '请求方法不允许，请使用POST'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = '';
    if (isset($_FILES['file']['error'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = '文件太大';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = '没有选择文件';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = '文件上传不完整';
                break;
            default:
                $errorMsg = '上传失败';
        }
    } else {
        $errorMsg = '请上传文件，字段名称为 file';
    }
    
    echo json_encode([
        'code' => 400,
        'msg' => $errorMsg
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$uploadedFile = $_FILES['file'];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://www.icve.com.cn/project/Api/upload',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        'code' => 500,
        'msg' => '获取上传凭证失败：' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($httpCode !== 200) {
    echo json_encode([
        'code' => $httpCode,
        'msg' => '获取上传凭证失败，HTTP状态码：' . $httpCode
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$uploadData = json_decode($response, true);

if (!$uploadData || $uploadData['code'] != 1) {
    echo json_encode([
        'code' => 500,
        'msg' => $uploadData['msg'] ?? '获取上传凭证失败'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$fileExtension = '';
if (isset($uploadedFile['name']) && !empty($uploadedFile['name'])) {
    $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    if ($fileExtension) {
        $fileExtension = '.' . $fileExtension;
    }
}

$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
$randomStr = '';
for ($i = 0; $i < 32; $i++) {
    $randomStr .= $characters[random_int(0, strlen($characters) - 1)];
}

$fileName = $randomStr . $fileExtension;
$key = $uploadData['signature']['dir'] . '/music.hjfggzs.top/qqmusic/' . $fileName;

$mimeType = $uploadedFile['type'];
if (empty($mimeType) || $mimeType === 'application/octet-stream') {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
    finfo_close($finfo);
}
if (empty($mimeType)) {
    $mimeType = 'application/octet-stream';
}

$postFields = [
    'key' => $key,
    'policy' => $uploadData['signature']['policy'],
    'OSSAccessKeyId' => $uploadData['signature']['accessid'],
    'signature' => $uploadData['signature']['signature'],
    'callback' => $uploadData['signature']['callback'],
    'file' => new CURLFile($uploadedFile['tmp_name'], $mimeType, $uploadedFile['name'])
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $uploadData['signature']['host'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Linux; Android 13; MEIZU 18X Build/TKQ1.221114.001) AppleWebKit/537.36'
    ]
]);
$uploadResponse = curl_exec($ch);
$uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$uploadError = curl_error($ch);
curl_close($ch);

if ($uploadError) {
    echo json_encode([
        'code' => 500,
        'msg' => '文件上传失败：' . $uploadError
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($uploadHttpCode >= 200 && $uploadHttpCode < 300) {
    $fileUrl = 'https://file.icve.com.cn/' . $key;
    echo json_encode([
        'code' => 200,
        'msg' => '上传成功',
        'url' => $fileUrl
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'code' => $uploadHttpCode,
        'msg' => '上传失败，HTTP状态码：' . $uploadHttpCode,
        'response' => $uploadResponse
    ], JSON_UNESCAPED_UNICODE);
}