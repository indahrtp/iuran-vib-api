<?php

namespace App\Models\Concerns;

trait HasAuditColumns
{
    protected static function bootHasAuditColumns(): void
    {
        static::creating(function ($model) {
            if ($id = auth()->id()) {
                $model->created_by = $id;
                $model->updated_by = $id;
            }
        });

        static::updating(function ($model) {
            if ($id = auth()->id()) {
                $model->updated_by = $id;
            }
        });
    }
}