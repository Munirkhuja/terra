<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enums\EventEnum;
use App\Enums\SourceEnum;
use App\Enums\StatusEnum;
use App\Models\ImageUpload;
use App\MoonShine\Fields\ImageEditor;
use App\MoonShine\Fields\Map;
use App\Services\KafkaProducer;
use Illuminate\Support\Facades\Auth;
use MaycolMunoz\MoonLeaflet\Fields\Leaflet;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use VI\MoonShineSpatieMediaLibrary\Fields\MediaLibrary;

/**
 * @extends ModelResource<ImageUpload>
 */
#[Order(1)]
class ImageUploadResource extends ModelResource
{
    protected string $model = ImageUpload::class;

    public function getTitle(): string
    {
        return __('site.menu.image_uploads');
    }

    /**#[Icon('users')]
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('site.column.name'), 'title')->sortable(),
            Enum::make(__('site.column.status'), 'status')->sortable()
                ->attach(StatusEnum::class),
            MediaLibrary::make(__('site.column.image'), ImageUpload::IMAGE_COLLECTION),
            Date::make(__('site.column.created_at'), 'created_at')->sortable(),
            Date::make(__('site.column.updated_at'), 'updated_at')->sortable(),
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make(__('site.column.title'), 'title')
                    ->required(),

                Textarea::make(__('site.column.description'), 'description')
                    ->nullable(),

                Textarea::make(__('site.column.metadata'), 'metadata')
                    ->nullable(),
                ImageEditor::make(__('site.column.image_select'), ImageUpload::IMAGE_COLLECTION)
                    ->multiple(false)
                    ->removable(),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make(__('site.column.title'), 'title'),
            Enum::make(__('site.column.status'), 'status')
                ->attach(StatusEnum::class),
            Enum::make(__('site.column.status'), 'event')
                ->attach(EventEnum::class),
            Enum::make(__('site.column.status'), 'source')
                ->attach(SourceEnum::class),
            Textarea::make(__('site.column.description'), 'description'),
            Textarea::make(
                __('site.column.error_message'),
                'error_message',
                static function ($item) {
                    return '<pre style="white-space: pre-wrap; word-break: break-word;">' .
                        json_encode($item->error_message ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
                        '</pre>';
                }
            )->unescape(),
            Textarea::make(
                __('site.column.metadata'),
                'metadata',
                static function ($item) {
                    return '<pre style="white-space: pre-wrap; word-break: break-word;">' .
                        json_encode($item->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
                        '</pre>';
                }
            )->unescape(),
            Textarea::make(
                __('site.column.result'),
                'result',
                function ($item) {
                    return '<pre style="white-space: pre-wrap; word-break: break-word;">' .
                        json_encode($item->result ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
                        '</pre>';
                }
            )->unescape(),
            MediaLibrary::make(__('site.column.image'), ImageUpload::IMAGE_COLLECTION),
            Leaflet::make(__('site.column.location'))
//                ->columns('result->geolocation->lat', 'result->geolocation->lon')
                ->columns('latitude', 'longitude')
                ->zoom(14),

            Text::make('Latitude', 'latitude'),
            Text::make('Longitude', 'longitude'),
        ];
    }

    /**
     * @param ImageUpload $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            ImageUpload::IMAGE_COLLECTION => ['required'],
        ];
    }

    protected function beforeCreating(mixed $item): mixed
    {
        $item->user_id = Auth::id();
        $item->source = SourceEnum::WEB;
        $item->event = EventEnum::GET_COORDINATE;
        $item->status = StatusEnum::PROCESSING;
        return $item;
    }

    protected function afterCreated(mixed $item): mixed
    {
        $kafkaProducer = new KafkaProducer();
        $kafkaProducer->send(
            $item->id,
            $item->getFirstMedia()->getUrl(),
            $item->metadata,
        );
        return $item;
    }

}
