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

$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
$randomStr = '';
for ($i = 0; $i < 32; $i++) {
    $randomStr .= $characters[random_int(0, strlen($characters) - 1)];
}

$fileExtension = '';
if (isset($uploadedFile['name']) && !empty($uploadedFile['name'])) {
    $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    if ($fileExtension) {
        $fileExtension = '.' . $fileExtension;
    }
}

$fileName = $randomStr . $fileExtension;

$data = [
    'action' => 'cos.getUptokenFromCustomer',
    'key' => 'im/4d2c3f00-7d4c-11e5-af15-41bf63ae4ea0/music.hjfggzs.top/Sakura/' . $fileName
];

$getUrl = 'https://webchat.7moor.com/chat?data=' . urlencode(json_encode($data));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $getUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        'code' => 500,
        'msg' => '获取上传凭证失败：' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$tokenData = json_decode($response, true);

if (!$tokenData || !isset($tokenData['success']) || $tokenData['success'] !== true) {
    echo json_encode([
        'code' => 500,
        'msg' => $tokenData['message'] ?? '获取上传凭证失败'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$putUrl = $tokenData['putUrl'];
$signUrl = $tokenData['signUrl'];

$signUrl = str_replace('cdn-visitor-eo.7moor-fs2.com', 'cik06-cos.7moor-fs2.com', $signUrl);
$signUrl = preg_replace('/\?.*$/', '', $signUrl);

$mimeType = $uploadedFile['type'];
if (empty($mimeType) || $mimeType === 'application/octet-stream') {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
    finfo_close($finfo);
}
if (empty($mimeType)) {
    $mimeType = 'application/octet-stream';
}

$fileContent = file_get_contents($uploadedFile['tmp_name']);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $putUrl,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_POSTFIELDS => $fileContent,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'Content-Type: ' . $mimeType,
        'Content-Length: ' . strlen($fileContent)
    ]
]);
$putResponse = curl_exec($ch);
$putHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$putError = curl_error($ch);
curl_close($ch);

if ($putError) {
    echo json_encode([
        'code' => 500,
        'msg' => '文件上传失败：' . $putError
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($putHttpCode >= 200 && $putHttpCode < 300) {
    echo json_encode([
        'code' => 200,
        'msg' => '上传成功',
        'url' => $signUrl
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'code' => $putHttpCode,
        'msg' => '上传失败，HTTP状态码：' . $putHttpCode
    ], JSON_UNESCAPED_UNICODE);
}