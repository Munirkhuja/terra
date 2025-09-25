<?php

use App\Enums\EventEnum;
use App\Enums\SourceEnum;
use App\Enums\StatusEnum;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('image_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('source')->default(SourceEnum::ETC->value);
            $table->string('event')->default(EventEnum::GET_COORDINATE->value);
            $table->string('status')->default(StatusEnum::PROCESSING->value);
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_uploads');
    }
};
