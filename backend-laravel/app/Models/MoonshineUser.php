<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use MoonShine\Laravel\Database\Factories\MoonshineUserFactory;
use MoonShine\Laravel\Models\MoonshineUserRole;

/**
 * @property string $email
 * @property string $name
 * @property string $avatar
 * @property int $moonshine_user_role_id
 * @property string $password
 */
class MoonshineUser extends Authenticatable
{
    use Notifiable;
    use HasApiTokens;

    protected $fillable = [
        'email',
        'moonshine_user_role_id',
        'password',
        'name',
        'avatar',
    ];

    protected static function newFactory(): Factory
    {
        return MoonshineUserFactory::new();
    }

    public function isSuperUser(): bool
    {
        return $this->moonshine_user_role_id === MoonshineUserRole::DEFAULT_ROLE_ID;
    }

    public function moonshineUserRole(): BelongsTo
    {
        return $this->belongsTo(MoonshineUserRole::class);
    }
}
