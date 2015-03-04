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

    /** @var \samson\fs\FileService Pointer to file system module */
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

        // Call parent initialization
        parent::init($params);
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
    	 // TODO: FIX IT!!!!!!!!
        if(!$this->fs->exists($file)) {
            $file = $upload_dir.'/'.$filename;
        }
        
        // Check if file exists
        if ($this->fs->exists($file)) {
            // Get file extension
            $fileExtension = $this->fs->extension($file);

            // Read file data and create image handle
            $img = imagecreatefromstring($this->fs->read($file, $filename));

            // Получим текущие размеры картинки
            $sWidth = imagesx( $img );
            $sHeight = imagesy( $img );

            // Получим соотношение сторон картинки
            $originRatio = $sHeight / $sWidth;

            // Iterate all configured scaling sizes
            foreach ($this->thumnails_sizes as $folder=>$size) {
                //trace($folder);
                $folder_path = $upload_dir.'/'.$folder;

                $this->fs->mkDir($folder_path);

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

                if ($fileExtension == "png") {
                    imagealphablending($new_img, false);
                    $colorTransparent = imagecolorallocatealpha($new_img, 0, 0, 0, 127);
                    imagefill($new_img, 0, 0, $colorTransparent);
                    imagesavealpha($new_img, true);
                } elseif($fileExtension == "gif") {
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
                imagecopyresampled($new_img, $img, 0, 0, 0, 0, $new_width, $new_height, $sWidth, $sHeight);

	            if (isset($size['crop']) && is_array($size['crop'])) {
		            $crop = & $size['crop'];
		            $cropWidth = $size['width'];
		            $cropHeight = $size['height'];
		            $x = 0;
		            $y = 0;
		            if ($crop['x'] == 'center') {
			            $x = floor(($new_width - $size['width'])/2);
		            } elseif ($crop['x'] == 'right') {
			            $x = floor($new_width - $size['width']);
		            }
		            if ($crop['y'] == 'middle') {
			            $y = floor(($new_height - $size['height'])/2);
		            } elseif ($crop['y'] == 'bottom') {
			            $y = floor($new_height - $size['height']);
		            }
		            if ($size['width'] >= $new_width) {
			            $cropWidth = $new_width;
			            $x = 0;
		            }
		            if ($size['height'] >= $new_height) {
			            $cropHeight = $new_height;
			            $y = 0;
		            }

		            $to_crop_array = array('x' =>$x , 'y' => $y, 'width' => $cropWidth, 'height'=> $cropHeight);
		            $thumb_im = imagecrop($new_img, $to_crop_array);
		            $new_img = $thumb_im;
	            }

                // Получим полный путь к превьюхе
                $new_path = $folder_path.'/'.$filename;

                ob_start();
                // Create image handle
                switch (strtolower($fileExtension)) {
                    case 'jpg':
                    case 'jpeg': imagejpeg($new_img, null, (isset($size['quality'])?$size['quality']:100)); break;
                    case 'png': imagepng($new_img, null); break;
                    case 'gif': imagegif($new_img); break;
                    default: return e('Не поддерживаемый формат изображения[##]!', E_SAMSON_CORE_ERROR, $filename);
                }

                $final_image = ob_get_contents();

                ob_end_clean();
                imagedestroy($new_img);

                // Copy scaled resource
                $this->fs->write($final_image, $filename, $folder_path);
            }

            return true;
        }

        return false;
    }

}
