<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PhilippineMobile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'contact_number',
        'login_number',
        'date_joined',
        'created_at',
        'user_type',
        'is_active',
    ];

    /**
     * Whether the canonical login-number column exists in the current schema.
     * Guarded so the model keeps working against a database that has not run
     * the login-number migration yet.
     */
    public static function hasLoginNumberColumn(): bool
    {
        static $exists = null;

        return $exists ??= Schema::hasColumn('users', 'login_number');
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (!self::hasLoginNumberColumn()) {
                return;
            }

            // Keep the canonical login number in step with the stored contact
            // number so the database UNIQUE constraint can reject a duplicate
            // mobile number even when two registrations race. Rows untouched by
            // this save are left alone, so legacy rows that never resolve to a
            // valid mobile number keep their NULL canonical value.
            if (!$user->isDirty('contact_number') && !$user->isDirty('login_number')) {
                return;
            }

            $user->login_number = PhilippineMobile::normalize($user->contact_number);
        });
    }

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'user_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the password for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the name attribute.
     */
    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Set the name attribute.
     */
    public function setNameAttribute($value)
    {
        $parts = explode(' ', $value, 2);
        $this->first_name = $parts[0] ?? '';
        $this->last_name = $parts[1] ?? '';
    }

    /**
     * Get the buyer associated with this user.
     */
    public function buyer()
    {
        return $this->hasOne(Buyer::class, 'user_id', 'user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'date_joined' => 'date',
        ];
    }

    public static function accountCreatedAtColumn(): string
    {
        if (Schema::hasColumn('users', 'date_joined')) {
            return 'date_joined';
        }

        if (Schema::hasColumn('users', 'created_at')) {
            return 'created_at';
        }

        throw new \LogicException('The users table has no supported account creation date column.');
    }
}
