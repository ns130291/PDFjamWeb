<?php

$prefix = 'Upload-';

// delete PDF files older than 1 day
$expireTime = strtotime("-1 day");
$fileSystemIterator = new FilesystemIterator(sys_get_temp_dir());
foreach ($fileSystemIterator as $file) {
    if (strpos($file->getFilename(), $prefix) === 0 && $file->getCTime() < $expireTime) {
        unlink($file->getPathname());
    }
}

$fileUpload = tempnam(sys_get_temp_dir(), $prefix);
header('Content-Type: application/json; charset=utf-8');
if (move_uploaded_file($_FILES['file']['tmp_name'], $fileUpload)) {
    echo '{"file": "' . explode("-", basename($fileUpload))[1] . '"}';
} else {
    http_response_code(500);
    echo '{"error": "UPLOAD_FAILED"}';
}

?>