<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
        'role',
        'referral_code',
        'evomi_username',
        'evomi_subuser_id',
        'evomi_keys',
        'stripe_customer_id',
        'default_payment_method',
        'auto_topup_settings',
        'has_claimed_trial',
        'trial_claim_ip',
        'signup_ip',
        'custom_referral_rate',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
        'google_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'evomi_username',
        'evomi_subuser_id',
        'evomi_keys',
        // FIX U4: Hide payment and tracking fields from API serialization.
        // These are sensitive and never needed in the frontend user list/detail.
        'stripe_customer_id',
        'default_payment_method',
        'signup_ip',
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
            'password' => 'hashed',
            'balance' => 'float',
            'has_claimed_trial' => 'boolean',
            'evomi_keys' => 'array',
            'auto_topup_settings' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:json',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_confirmed_at);
    }

    /* Relationships */
    public function orders() { return $this->hasMany(Order::class); }
    public function wallet_transactions() { return $this->hasMany(WalletTransaction::class); }
    public function support_tickets() { return $this->hasMany(SupportTicket::class); }
    public function referrals() { return $this->hasMany(Referral::class, 'referrer_id'); }
    public function invoices() { return $this->hasMany(Invoice::class); }
}
