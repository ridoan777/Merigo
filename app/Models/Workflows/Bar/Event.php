<?php

namespace App\Models\Workflows\Bar;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Attributes\{Table,Fillable,Appends};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Table('bar_events')]
#[Fillable(['event_uid', 'bar_id', 'creator_id', 'event_day', 'name', 'description', 'points_giveaway', 'image', 'expiry', 'status'])]
#[Appends(['image_url'])]

class Event extends Model
{
	protected function casts(): array
	{
		return [
			'bar_id' => 'integer',
			'creator_id' => 'integer',
			'points_giveaway' => 'integer',
			'expiry' => 'date',
			'status' => 'integer',
		];
	}

	public function eventRelatingBackTo_Bar()
	{
		return $this->belongsTo(Bar::class, 'bar_id');
	}

	public function eventRelatingBackTo_Creator()
	{
		return $this->belongsTo(User::class, 'creator_id');
	}

	public function eventRelationWith_Deals()
	{
		return $this->hasMany(Deal::class, 'event_id');
	}

	public function eventRelationWith_Gallery()
	{
		return $this->hasMany(EventGallery::class, 'event_id');
	}

	// ----------------- QUERY SCOPING -----------------
	public function scopeBarId($query, int $bar_id)
	{
		return $query->where('bar_id', $bar_id);
	}

	public function scopeEventDay($query, string $event_day)
	{
		return $query->where('event_day', $event_day);
	}

	public function scopeStatus($query, $status)
	{
		return $query->where('status', $status);
	}
	// ----------------- QUERY SCOPING -----------------

	// ----------------- URL FRIENDLY IMAGE -----------------
	public function getImageUrlAttribute()
	{
		if ($this->image) {
			return Storage::url($this->image);
		}
		return null;
	}
	// ----------------- URL FRIENDLY IMAGE -----------------
}
