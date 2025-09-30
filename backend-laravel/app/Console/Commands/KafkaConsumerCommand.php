<?php

namespace App\Console\Commands;

use App\Enums\StatusEnum;
use App\Events\ImageProcessed;
use App\Models\ImageUpload;
use App\Traits\JsonArrayTrait;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

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

        Kafka::consume([config('kafka.topic_responses')])
            ->withHandler(function ($message) {
                $body = $message->getBody();
                try {
                    $upload = ImageUpload::query()->find($body['image_id']);

                    if ($upload) {
                        $upload->update([
                            'status' => StatusEnum::SUCCESS->value,
                            'result' => $this->toJsonWithTrait($body),
                            'updated_at' => now(),
                        ]);
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
            ->start();
    }
}
