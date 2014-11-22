<?php
namespace samson\scale;

use samson\core\CompressableExternalModule;
use samson\core\iModuleViewable;

/**
 * Scale module controller
 *
 * @package SamsonPHP
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com>
 * @author Nikita Kotenko <nick.w2r@gmail.com>
 */
class ScaleController extends CompressableExternalModule
{
    /** @var string Identifier */
    protected $id = 'scale';

    /** @var \samson\fs\FileSystemController Pointer to file system module */
    protected $fs;

    /** @var array Generic sizes collection */
    public $thumnails_sizes = array(
        'mini' => array(
            'width'=>208,
            'height'=>190,
            'fit'=>true,
            'quality'=>100)
    );

    /**
     * Module initialization
     * @param array $params Collection of parameters
     * @return bool|void
     */
    public function init(array $params = array())
    {
        // Store pointer to file system module
        $this->fs = & m('fs');
    }

    /**
     * Perform resource scaling
     * @param string $file
     * @param string $filename
     * @param string $upload_dir
     * @return bool True is scalling completed without errors
     */
    public function resize($file, $filename, $upload_dir = 'upload')
    {
        // Check if file exists
        if ($this->fs->exists($file)) {
            // Read file data
            $file = $this->fs->read($file, $filename);
            // Get file extension
            $file_type = pathinfo($file, PATHINFO_EXTENSION );

            // Create image handle
            $img = null;
            switch (strtolower($file_type)) {
                case 'jpg':
                case 'jpeg': $img = imagecreatefromjpeg($file); break;
                case 'png': $img = imagecreatefrompng( $file ); break;
                case 'gif': $img = imagecreatefromgif( $file ); break;
                default: return e('Не поддерживаемый формат изображения[##]!', E_SAMSON_CORE_ERROR, $filename);
            }

            // Получим текущие размеры картинки
            $sWidth = imagesx( $img );
            $sHeight = imagesy( $img );

            // Получим соотношение сторон картинки
            $originRatio = $sHeight / $sWidth;

            // Iterate all configured scaling sizes
            foreach ($this->thumnails_sizes as $folder=>$size) {
                //trace($folder);
                $folder_path = $upload_dir.'/'.$folder;
                if(!file_exists($folder_path))  mkdir( $folder_path, 0775, true );

                $tHeight = $size['height'];
                $tWidth = $size['width'];
                // Получим соотношение сторон в коробке
                $tRatio = $tHeight / $tWidth;
                if (($tHeight >= $sHeight)&&($tWidth >= $sWidth)) {
                    $width = $sWidth;
                    $height = $sHeight;
                } else {
                    if ($size['fit']) $correlation = ($originRatio < $tRatio);
                    else $correlation = ($originRatio > $tRatio);
                    // Сравним соотношение сторон картинки и "целевой" коробки для определения
                    // по какой стороне будем уменьшать картинку
                    if ( $correlation) {
                        $width = $tWidth;
                        $height = $width * $originRatio;
                    } else {
                        $height = $tHeight;
                        $width = $height / $originRatio;
                    }
                }

                // Зададим расмер превьюшки
                $new_width = floor( $width );
                $new_height = floor( $height );

                // Создадим временный файл
                $new_img = imagecreateTRUEcolor( $new_width, $new_height );

                if($file_type=="png") {
                    imagealphablending($new_img, false);
                    $colorTransparent = imagecolorallocatealpha($new_img, 0, 0, 0, 127);
                    imagefill($new_img, 0, 0, $colorTransparent);
                    imagesavealpha($new_img, true);
                } elseif($file_type=="gif") {
                    $trnprt_indx = imagecolortransparent($img);
                    if ($trnprt_indx >= 0) {
                        //its transparent
                        $trnprt_color = imagecolorsforindex($img, $trnprt_indx);
                        $trnprt_indx = imagecolorallocate($new_img, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                        imagefill($new_img, 0, 0, $trnprt_indx);
                        imagecolortransparent($new_img, $trnprt_indx);
                    }
                }

                // Скопируем, изменив размер
                imagecopyresampled ( $new_img, $img, 0, 0, 0, 0, $new_width, $new_height, $sWidth, $sHeight );

                // Получим полный путь к превьюхе
                $new_path = $folder_path.'/'.$filename;

                // Create image handle
                switch (strtolower($file_type)) {
                    case 'jpg':
                    case 'jpeg': imagejpeg($new_img, $new_path, (isset($size['quality'])?$size['quality']:100)); break;
                    case 'png': imagepng($new_img, $new_path); break;
                    case 'gif': imagegif($new_img, $new_path); break;
                    default: return e('Не поддерживаемый формат изображения[##]!', E_SAMSON_CORE_ERROR, $filename);
                }

                // Copy scaled resource
                $this->fs->copy($new_path, $filename, $folder_path);
            }

            return true;
        }

        return false;
    }

}