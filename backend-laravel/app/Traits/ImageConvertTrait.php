<?php

namespace App\Traits;

use Imagick;
use ImagickException;

trait ImageConvertTrait
{
    /**
     * @throws ImagickException
     */
    public function convertHeicFileTo($input, $output, $format): void
    {
        $file_format = $format;
        if ($format === 'jpg') {
            $file_format = "jpeg";
        }
        $imagick = new Imagick();
        $imagick->readImage($input);
        $imagick->setImageFormat($file_format);
        $imagick->setImageCompressionQuality(10);
        $imagick->writeImage($output);
        $imagick->clear();
        $imagick->destroy();
    }
}
