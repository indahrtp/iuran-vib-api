<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitOccupancy extends Model
{
    use HasAuditColumns;

    protected $table = 'unit_occupancies';

    protected $fillable = ['unit_id', 'resident_id', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
}