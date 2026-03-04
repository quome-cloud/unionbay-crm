<?php

namespace Webkul\ActionStream\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Webkul\ActionStream\Contracts\NextAction as NextActionContract;
use Webkul\User\Models\UserProxy;

class NextAction extends Model implements NextActionContract
{
    protected $table = 'next_actions';

    protected $fillable = [
        'actionable_type',
        'actionable_id',
        'user_id',
        'action_type',
        'description',
        'due_date',
        'due_time',
        'priority',
        'status',
        'completed_at',
        'snoozed_until',
    ];

    protected $casts = [
        'due_date'      => 'date',
        'completed_at'  => 'datetime',
        'snoozed_until' => 'datetime',
    ];

    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    public function isOverdue(): bool
    {
        if ($this->status !== 'pending' || ! $this->due_date) {
            return false;
        }

        return $this->due_date->isPast();
    }

    public function isDueToday(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->isToday();
    }

    public function isDueThisWeek(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->isFuture() && $this->due_date->diffInDays(now()) <= 7;
    }

    public function getPriorityColorAttribute(): string
    {
        if ($this->isOverdue()) {
            return 'red';
        }

        if ($this->isDueToday()) {
            return 'orange';
        }

        if ($this->isDueThisWeek()) {
            return 'yellow';
        }

        if ($this->due_date) {
            return 'green';
        }

        return 'gray';
    }
}
