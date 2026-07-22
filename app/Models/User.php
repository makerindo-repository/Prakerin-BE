<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\LogsActivity;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, LogsActivity;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guard_name = 'sanctum';

    protected $fillable = [
        'id',
        'username',
        'email',
        'password',
        'role',
        'photo_profile',
        'email_verified_at',
        'last_login_at',
        'is_pro',
        'is_verified',
        'email_notifications_enabled',
        'whatsapp_notifications_enabled',
        'whatsapp_number',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function student()
    {
        return $this->hasOne(Student::class);
    }
    public function school()
    {
        return $this->hasOne(School::class);
    }
    public function company()
    {
        return $this->hasOne(Company::class);
    }

    /**
     * Map the legacy `role` column (+ school type where relevant) to the
     * corresponding Spatie role name (guard 'sanctum').
     *
     * Mirrors the mapping used by database/seeders/RolePermissionSeeder.php,
     * so any place that creates a User must call this + assignRole() to make
     * sure the user actually receives whatever permissions super_admin has
     * configured for that role.
     */
    public static function resolveSpatieRoleName(string $legacyRole, ?string $schoolType = null): ?string
    {
        return match ($legacyRole) {
            'super_admin' => 'super_admin',
            'company'     => 'company_owner',
            'school'      => $schoolType === 'school' ? 'school_admin' : 'university_admin',
            'student'     => $schoolType === 'school' ? 'siswa' : 'mahasiswa',
            default       => null,
        };
    }

    /**
     * Assign the correct Spatie role to this user based on their legacy
     * `role` column (+ school type where relevant). Safe to call multiple
     * times (syncRoles replaces any previous role of this guard).
     */
    public function syncSpatieRole(?string $schoolType = null): void
    {
        $roleName = static::resolveSpatieRoleName($this->role, $schoolType);

        if ($roleName) {
            $this->syncRoles([$roleName]);
        }
    }

    public function feedbacksGiven()
    {
        return $this->hasMany(Feedback::class, 'from_user_id');
    }

    public function feedbacksReceived()
    {
        return $this->hasMany(Feedback::class, 'to_user_id');
    }

    public function mentorAssignments()
    {
        return $this->hasMany(MentorAssignment::class, 'student_id');
    }


    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at'               => 'datetime',
            'last_login_at'                   => 'datetime',
            'password'                        => 'hashed',
            'email_notifications_enabled'     => 'boolean',
            'whatsapp_notifications_enabled'  => 'boolean',
        ];
    }

    public function inboxItems()
    {
        return $this->hasMany(InboxItem::class);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }


    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function toRate() {
        return $this->belongsToMany(User::class, 'user_user',  'user_id', 'related_user_id')->withTimestamps()->withPivot('is_done');
    }
    public function rated() {
        return $this->belongsToMany(User::class, 'user_user', 'related_user_id', 'user_id')->withTimestamps()->withPivot('is_done');
    }
}