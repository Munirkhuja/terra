<?php

namespace App\Console\Commands;

use App\Enums\StatusEnum;
use App\Events\ImageProcessed;
use App\Models\ImageUpload;
use App\MoonShine\Resources\ImageUploadResource;
use App\Traits\JsonArrayTrait;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use MoonShine\Laravel\Notifications\MoonShineNotification;
use MoonShine\Laravel\Notifications\NotificationButton;
use MoonShine\Support\Enums\Color;

class KafkaConsumerCommand extends Command
{
    use JsonArrayTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:consume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from Kafka topic with ML results';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Listening for messages from Kafka...");

        Kafka::consumer([config('kafka.output_topic')])
            ->withHandler(function ($message) {
                $body = $message->getBody();
                try {
                    $upload = ImageUpload::query()->find($body['image_id']);

                    if ($upload) {
                        $upload->update([
                            'status' => StatusEnum::SUCCESS->value,
                            'result' => $this->toJsonWithTrait($body),
                            'latitude' => $body['geolocation']['lat'],
                            'longitude' => $body['geolocation']['lat'],
                            'updated_at' => now(),
                        ]);
                        $resource = app(ImageUploadResource::class);
                        $url = $resource->getDetailPageUrl($upload->id);
                        MoonShineNotification::send(
                            message: __('site.message.prepare_result', ['id' => $upload->id]),
                            // Optional button
                            button: new NotificationButton(
                                __('site.button.goto_view_result'),
                                $url,
                                attributes: ['target' => '_blank']
                            ),
                            // Optional administrator IDs (default for all)
                            ids: [$upload->user_id],
                            // Optional icon color
                            color: Color::GREEN,
                            // Optional icon
                            icon: 'information-circle'
                        );
                        broadcast(new ImageProcessed($upload));
                    } else {
                        logger()->warning('ImageUpload not found for ID: ' . ($body['image_id'] ?? 'null'));
                    }
                } catch (\Throwable $e) {
                    logger()->error('Kafka handler error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
                $this->info("Received: " . json_encode($body));
            })
            ->build()
            ->consume();
    }
}
