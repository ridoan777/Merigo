<?php

namespace App\Models\Workflows\Ads;

use Illuminate\Database\Eloquent\Attributes\{Table,Fillable,Appends};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Table('ads')]
#[Fillable(['ad_uid', 'title', 'description', 'ad_link', 'image', 'schedule_day', 'status'])]
#[Appends(['image_url'])]

class Ad extends Model
{
	protected function casts(): array
	{
		return [
			'status' => 'integer',
		];
	}

	// ----------------- QUERY SCOPING -----------------
	public function scopeScheduleDay($query, string $schedule_day)
	{
		return $query->where('schedule_day', $schedule_day);
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
