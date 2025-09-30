<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enums\EventEnum;
use App\Enums\SourceEnum;
use App\Enums\StatusEnum;
use App\Models\ImageUpload;
use App\MoonShine\Fields\ImageEditor;
use App\Services\KafkaProducer;
use Illuminate\Support\Facades\Auth;
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

    protected function afterCreated(mixed $item,KafkaProducer $kafkaProducer): mixed
    {
        $kafkaProducer->send(
            $item->id,
            $item->getFirstMedia()->getUrl(),
            $item->metadata,
        );
        return $item;
    }

    protected function onLoad(): void
    {
        parent::onLoad();
    }

}
