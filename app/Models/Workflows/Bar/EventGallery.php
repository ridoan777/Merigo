<?php

namespace App\Models\Workflows\Bar;

use Illuminate\Database\Eloquent\Attributes\{Table,Fillable,Appends};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Table('bar_event_galleries')]
#[Fillable(['event_id', 'filename', 'filepath', 'metadata', 'status'])]
#[Appends(['filepath_url'])]

class EventGallery extends Model
{
	protected function casts(): array
	{
		return [
			'event_id' => 'integer',
			'metadata' => 'json',
			'status' => 'integer',
		];
	}

	public function galleryRelatingBackTo_Event()
	{
		return $this->belongsTo(Event::class, 'event_id');
	}

	// ----------------- QUERY SCOPING -----------------
	public function scopeEventId($query, int $event_id)
	{
		return $query->where('event_id', $event_id);
	}

	public function scopeStatus($query, $status)
	{
		return $query->where('status', $status);
	}
	// ----------------- QUERY SCOPING -----------------

	// ----------------- URL FRIENDLY FILEPATH -----------------
	public function getFilepathUrlAttribute()
	{
		if ($this->filepath) {
			return Storage::url($this->filepath);
		}
		return null;
	}
	// ----------------- URL FRIENDLY FILEPATH -----------------
}
