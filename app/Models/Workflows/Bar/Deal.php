<?php

namespace App\Models\Workflows\Bar;

use App\Domain\Bars\Enums\PointsToShowEnums;
use Illuminate\Database\Eloquent\Attributes\{Table, Fillable, Appends};
use Illuminate\Database\Eloquent\Model;

#[Table('bar_deals')]
#[Fillable(['deal_uid', 'bar_id', 'event_id', 'name', 'point_cost', 'to_show', 'expiry', 'status'])]

class Deal extends Model
{
	protected function casts(): array
	{
		return [
			'bar_id' => 'integer',
			'event_id' => 'integer',
			'point_cost' => 'integer',
			'to_show' => PointsToShowEnums::class,
			'expiry' => 'datetime',
			'status' => 'integer',
		];
	}

	public function dealRelatingBackTo_Bar()
	{
		return $this->belongsTo(Bar::class, 'bar_id');
	}

	public function dealRelatingBackTo_Event()
	{
		return $this->belongsTo(Event::class, 'event_id');
	}


	// ----------------- QUERY SCOPING -----------------
	public function scopeBarId($query, int $bar_id)
	{
		return $query->where('bar_id', $bar_id);
	}

	public function scopeEventId($query, int $event_id)
	{
		return $query->where('event_id', $event_id);
	}

	public function scopeExpiry($query, $expiry)
	{
		return $query->where('expiry', $expiry);
	}

	public function scopeStatus($query, $status)
	{
		return $query->where('status', $status);
	}
	// ----------------- QUERY SCOPING -----------------
}
