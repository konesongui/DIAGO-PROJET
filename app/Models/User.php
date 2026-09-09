<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar_path',
        'address',
        'phone', 'emergency_contact', 'gender', 'birth_date', 'marital_status',
        'father_name', 'mother_name', 'qualification', 'experience', 'remark',
        'permanent_address', 'account_title', 'bank_name', 'bank_branch',
        'bank_account_number', 'ifsc_code', 'facebook_url', 'twitter_url',
        'linkedin_url', 'instagram_url', 'matricule', 'job_title', 'services',
        'manager', 'cnps', 'base_salary', 'contract_type', 'workstation',
        'location', 'registered_at',
        'password',
        'role_id',
        'is_active',
        'entreprise_id',
        'succursale_id',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'registered_at' => 'date',
        'base_salary' => 'decimal:2',
        'is_active' => 'boolean',
        'permissions' => 'array',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function succursale()
    {
        return $this->belongsTo(Succursale::class);
    }

    public function isEntrepriseAdmin(): bool
    {
        return $this->hasRole('admin') && $this->succursale_id === null;
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && strtolower($this->role->name) === strtolower($roleName);
    }

    public function hasPermission(string $module, string $ability = 'view'): bool
    {
        if ($this->hasRole('admin') || $this->hasRole('super_admin')) {
            return true;
        }

        return (bool) data_get($this->permissions, $module . '.' . $ability, false);
    }
}
