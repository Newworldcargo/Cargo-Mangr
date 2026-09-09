<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Core\Traits\SpatieLogsActivity;
use App\Helpers\HelperTraits\FileHelper;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Users\Events\UserDeletedEvent;
use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, SpatieLogsActivity, FileHelper, HasRoles;
    use InteractsWithMedia;


    /**
	 * directory user images.
	 * @var string
	 */
    const DIRECTORY_IMAGE = 'users_avatar';

    // Users Types
    const STAFF  = 0;
    const ADMIN  = 1;


    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'national_id',
        'responsible_mobile',
        'secondary_mobile',
        'country_code',
        'provider',
        'provider_id',
        'verified',
        'otp'
       ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'verified' => 'boolean',
    ];

    /**
	 * Revert avatar url.
	 * @return string
	 */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('preview')->fit(Manipulations::FIT_CROP, 300, 300)->nonQueued();
    }

    public function getAvatarImageAttribute()
    {
        return $this->avatar ? Storage::url($this->avatar) : asset('assets/lte/media/avatars/blank.png');
        // $model->avatar ? Storage::url($model->avatar) : asset('assets/img/blank.png')
    }

    /**
	 * Revert user role.
	 * @return string
	 */
    public function getUserRoleAttribute()
    {
        $user_roles =  config('cms.user_roles');


        $modules_file = json_decode(File::get(base_path('modules_statuses.json')), true);
        foreach($modules_file as $module) {
            if(is_array(config('module_'.strtolower($module).'.user_roles'))){
                $user_roles = array_merge($user_roles, config('module_'.strtolower($module).'.user_roles'));
            }
        }
        $user_roles = array_merge($user_roles, config('module_cargo.user_roles'));
        return $user_roles[$this->role];
    }


    public function createTwoFactorAuthSecret()
    {
        $google2fa = new Google2FA();
        return $google2fa->generateSecretKey();
    }

    public function twoFactorQrCodeSvg()
    {
        if (!$this->two_factor_secret) {
            return null;
        }

        $google2fa = new Google2FA();
        $secret = decrypt($this->two_factor_secret);

        $url = $google2fa->getQRCodeUrl(
            config('app.name'),
            $this->email,
            $secret
        );

        return QrCode::format('svg')->size(200)->generate($url);
    }


    /**
	 * Observer locale.
	 */
    protected static function booted()
    {
        // when deleted user
        static::deleted(function ($user) {
            // remove avatar when deleted user
            if ($user->avatar && $user->avatar != null) {
                $user->deleteFile($user->avatar, self::DIRECTORY_IMAGE);
            }
            event(new UserDeletedEvent($user));
        });
    }

    public function getUsersOnly($query)
    {
        return $query->whereIn('role', [0,1]);
    }

    public function getAdminsOnly($query)
    {
        return $query->where('role', 1);
    }

    public function getStaffOnly($query)
    {
        return $query->where('role', 0);
    }

    public function getOthersOnly($query)
    {
        return $query->whereNotIn('role', [0,1]);
    }

}
