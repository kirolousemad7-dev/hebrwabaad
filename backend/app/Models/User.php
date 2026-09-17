<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'department_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_sent_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function memberProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasOne<UserNotificationPreference, $this>
     */
    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function isEmployee(): bool
    {
        return $this->role instanceof UserRole && $this->role->isEmployee();
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeEmployees(Builder $query): void
    {
        $query->whereIn('role', UserRole::employeeValues());
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function lastSeenAt(): ?Carbon
    {
        $value = $this->tokens_max_last_used_at ?? null;

        return $value ? Carbon::parse($value) : null;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    /**
     * @return HasMany<PrintingRequest, $this>
     */
    public function printingRequests(): HasMany
    {
        return $this->hasMany(PrintingRequest::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'account_manager_id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function customerProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'customer_id');
    }

    /**
     * @return HasMany<Consultation, $this>
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function managedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'account_manager_id');
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function customerConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_to');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function customerPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    /**
     * @return HasMany<WorkSubmission, $this>
     */
    public function workSubmissions(): HasMany
    {
        return $this->hasMany(WorkSubmission::class);
    }

    /**
     * @return HasOne<Supplier, $this>
     */
    public function supplierProfile(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    public function canReviewContent(): bool
    {
        return $this->role instanceof UserRole && $this->role->canReviewContent();
    }

    public function canSubmitEmployeeWork(): bool
    {
        return $this->role instanceof UserRole && $this->role->canSubmitEmployeeWork();
    }

    public function isSupplier(): bool
    {
        return $this->role === UserRole::Supplier;
    }
}
