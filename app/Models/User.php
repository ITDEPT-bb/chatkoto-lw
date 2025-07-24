<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Namu\WireChat\Traits\Chatable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasSlug;

    use Chatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'middlename',
        'surname',
        'email',
        'phone_number',
        'phone_number_verified_at',
        'otp_request_id',
        'birthday',
        'gender',
        'avatar',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_number_verified_at' => 'datetime',
            'birthday' => 'date',
            'password' => 'hashed',
            'gender' => Gender::class,
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name', 'surname'])
            ->saveSlugsTo('username')
            ->slugsShouldBeNoLongerThan(15);
    }

    public function canCreateChats(): bool
    {
        return $this->hasVerifiedEmail();
    }

    public function canCreateGroups(): bool
    {
        return $this->hasVerifiedEmail() === true;
    }
}
