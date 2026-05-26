<?php

namespace App\Models\Workflows\Bar;

use App\Models\Billings\Wallets\BarWallet;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Attributes\{Table,Fillable,Appends};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Table('bars')]
#[Fillable(['bar_uid', 'bar_admin_id', 'name', 'earning_points', 'cd_time', 'contact', 'address', 'city', 'image', 'latitude', 'longitude', 'status'])]
#[Appends(['image_url'])]

class Bar extends Model
{
	protected function casts(): array
	{
		return [
			'bar_admin_id' => 'integer',
			'earning_points' => 'integer',
			'cd_time' => 'integer',	// how many hours before a user can earn again
			'status' => 'integer',
		];
	}

	public function barRelatingBackTo_User()
	{
		return $this->belongsTo(User::class, 'bar_admin_id');
	}

	public function barRelationWith_Event()
	{
		return $this->hasMany(Event::class, 'bar_id');
	}

	public function barRelationWith_Deal()
	{
		return $this->hasMany(Deal::class, 'bar_id');
	}

	public function barRelationWith_Wallets()
	{
		return $this->hasMany(BarWallet::class, 'bar_id');
	}

	// ----------------- QUERY SCOPING -----------------
	public function scopeUserId($query, int $bar_admin_id)
	{
		return $query->where('bar_admin_id', $bar_admin_id);
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
