<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use App\MoonShine\Fields\Traits\HasConfig;
use App\MoonShine\Fields\Traits\HasLeaflet;
use MoonShine\UI\Fields\Field;

class Leaflet extends Field
{
    use HasConfig, HasLeaflet;


    protected string $view = 'admin.fields.leaflet';

    protected bool $isGroup = true;

    protected array $propertyAttributes = [
        'type',
    ];

    protected function reformatFilledValue(mixed $data): mixed
    {
        return parent::reformatFilledValue($data);
    }


    protected function viewData(): array
    {
        return [
            'initLatitude' => $this->getInitialLatitude(),
            'initLongitude' => $this->getInitialLongitude(),
            'zoom' => $this->getZoom(),
            'minZoom' => $this->getMinZoom(),
            'maxZoom' => $this->getMaxZoom(),
            'draggable' => $this->isMapDraggable(),
            'latAttributes' => $this->getLatitudeAttributes(),
            'lonAttributes' => $this->getLongitudeAttributes(),
        ];
    }
}
