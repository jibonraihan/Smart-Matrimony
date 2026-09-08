
<?php
require_once '../includes/image_helper.php';

function resizeAndSaveImage($tmpFile,$userId){

    $info = getimagesize($tmpFile);

    if($info===false){

        return false;

    }

    // Graceful fallback when PHP GD is not enabled.
    // Keep the validated original image instead of crashing during Step 5 save.
    $gdReady = function_exists('imagecreatefromjpeg')
        && function_exists('imagecreatefrompng')
        && function_exists('imagecreatefromwebp')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled')
        && function_exists('imagewebp');

    if(!$gdReady){

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if(!isset($extensionMap[$info['mime']])){
            return false;
        }

        $fileName = 'USR_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extensionMap[$info['mime']];
        $uploadDir = __DIR__ . '/../uploads/profile/';

        if(!is_dir($uploadDir) && !mkdir($uploadDir,0777,true)){
            return false;
        }

        if(!copy($tmpFile, $uploadDir . $fileName)){
            return false;
        }

        return $fileName;
    }

    switch($info['mime']){

        case 'image/jpeg':
            $source=imagecreatefromjpeg($tmpFile);
            $ext='jpg';
            break;

        case 'image/png':
            $source=imagecreatefrompng($tmpFile);
            $ext='png';
            break;

        case 'image/webp':
            $source=imagecreatefromwebp($tmpFile);
            $ext='webp';
            break;

        default:
            return false;

    }

    $width=imagesx($source);
    $height=imagesy($source);

    $size=800;

    $canvas=imagecreatetruecolor($size,$size);
        $srcRatio=$width/$height;

    if($srcRatio>1){

        $newHeight=$size;

        $newWidth=$width*($size/$height);

    }else{

        $newWidth=$size;

        $newHeight=$height*($size/$width);

    }

    imagecopyresampled(

        $canvas,

        $source,

        0-($newWidth-$size)/2,

        0-($newHeight-$size)/2,

        0,

        0,

        $newWidth,

        $newHeight,

        $width,

        $height

    );
        $fileName =

    "USR_"

    .

    $userId

    .

    "_"

    .

    bin2hex(random_bytes(8))

    .

    ".webp";

    $savePath = __DIR__ . "/../uploads/profile/" . $fileName;

    if(!is_dir(__DIR__ . "/../uploads/profile")){

        mkdir(__DIR__ . "/../uploads/profile",0777,true);

    }

    imagewebp($canvas,$savePath,85);

    imagedestroy($source);

    imagedestroy($canvas);

    return $fileName;

}