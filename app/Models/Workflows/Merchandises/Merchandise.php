<?php

namespace App\Models\Workflows\Merchandises;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Attributes\{Table, Fillable, Appends};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Table('merchandises')]
#[Fillable(['merc_uid', 'name', 'description', 'points_cost', 'image', 'creator', 'status'])]
#[Appends(['image_url'])]

class Merchandise extends Model
{
	protected function casts(): array
	{
		return [
			'points_cost' => 'integer',
			'creator' => 'integer',
			'status' => 'integer',
		];
	}

	
	// ----------------- RELATIONS -----------------
	public function merchandiseRelatingBackTo_Creator()
	{
		return $this->belongsTo(User::class, 'creator');
	}

	// ----------------- QUERY SCOPING -----------------

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
