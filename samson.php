<?php
namespace samson\scale;

use samson\core\CompressableExternalModule;
use samson\core\iModuleViewable;

/**
 * Интерфейс для подключения модуля в ядро фреймворка SamsonPHP
 *
 * @package SamsonPHP
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com>
 * @author Nikita Kotenko <nick.w2r@gmail.com>
 * @version 0.1
 */
class Scale extends CompressableExternalModule
{
	/** Идентификатор модуля */
	protected $id = 'scale';

	public $thumnails_sizes = array('mini'=>array('width'=>208, 'height'=>190, 'fit'=>true, 'quality'=>100));
	
	public function resize($file, $filename, $upload_dir = NULL)
	{
        // Путь к папке с загрузками
        if(!isset($upload_dir))$upload_dir = 'upload/';
        //trace($upload_dir);
        //trace($file);
			
		if(file_exists($file))			
		{
			$file_type = pathinfo( $file, PATHINFO_EXTENSION );
            $lowFileType = strtolower($file_type);

			// Получим текущую фотографию
			if (( $lowFileType == 'jpg' ) || ( $lowFileType == 'jpeg' ))
			$img = imagecreatefromjpeg( $file );
			elseif ( $lowFileType == 'png' )
			$img = imagecreatefrompng( $file );
			elseif ( $lowFileType == 'gif' )
			$img = imagecreatefromgif( $file );
			else { trace( 'Не поддерживаемый формат изображения!');return false; }

            if (!$img) {
                trace( 'Ошибка создания изображения!');return false;
            }
				
			// Получим текущие размеры картинки
			$sWidth = imagesx( $img );
			$sHeight = imagesy( $img );		

			// Получим соотношение сторон картинки
			$originRatio = $sHeight / $sWidth;	
			
			
			foreach ($this->thumnails_sizes as $folder=>$size)
			{
                //trace($folder);
				$folder_path = $upload_dir.$folder;
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
                    if ( $correlation)
                    {
                        $width = $tWidth;
                        $height = $width * $originRatio;
                    }
                    else
                    {
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
	
				//Сохраним временную картинку в файл
				if (( $lowFileType == 'jpg' ) || ( $lowFileType == 'jpeg' ))
				{
					imagejpeg( $new_img, $new_path, (isset($size['quality'])?$size['quality']:100) );
				}
				elseif ($lowFileType == 'png')
				{
					imagepng( $new_img, $new_path );
				}
				elseif ($lowFileType == 'gif')
				{
					imagegif( $new_img, $new_path );
				}
				else return false;				
			}
			return true;
		}
		return false;
	}
			
}