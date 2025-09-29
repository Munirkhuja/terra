<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use VI\MoonShineSpatieMediaLibrary\Fields\MediaLibrary;

class ImageEditor extends MediaLibrary
{
    protected string $view = 'admin.fields.image-editor';



    public function removeExcludedFilesPatched(null|array|string $newValue = null): void
    {
        $values = $this->toValue(withDefault: false);
        $current = $this->getValue();

        $valuesCollection = collect(is_array($values) ? $values : [$values]);
        $currentCollection = collect(is_array($current) ? $current : [$current]);
        $valuesCollection->diff($currentCollection)->each(
            function (?string $file) use ($newValue): void {
                $old = array_filter(\is_array($newValue) ? $newValue : [$newValue]);

                if ($file !== null && ! \in_array($file, $old, true)) {
                    $this->deleteFile($file);
                }
            },
        );
    }

}
