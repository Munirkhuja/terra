<?php
namespace App\Services;

use Junges\Kafka\Facades\Kafka;

class KafkaProducer
{
    public function send(string $imageId, string $image_url, array $metadata = []): void
    {
        Kafka::asyncPublish()
            ->onTopic(config('kafka.input_topic'))
            ->withBodyKey('image_id', $imageId)
            ->withBodyKey('image_url', $image_url)
            ->withBodyKey('metadata', $metadata)
            ->send();
    }
}
