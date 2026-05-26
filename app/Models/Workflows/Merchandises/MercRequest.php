<?php

namespace App\Models\Workflows\Merchandises;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Attributes\{Table, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Table('merc_requests')]
#[Fillable(['merc_id', 'user_id', 'phase', 'points_debited', 'receiver_phone', 'receiver_address', 'note', 'status'])]

class MercRequest extends Model
{
	protected function casts(): array
	{
		return [
			'merc_id' => 'integer',
			'user_id' => 'integer',
			'points_debited' => 'integer',
			'status' => 'integer',
		];
	}

	// ----------------- RELATIONS -----------------
	public function mercReqRelatingBackTo_Merchandise()
	{
		return $this->belongsTo(Merchandise::class, 'merc_id');
	}

	public function merReqRelatingBackTo_User()
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	// ----------------- QUERY SCOPING -----------------
	public function scopeMercId($query, int $merc_id)
	{
		return $query->where('merc_id', $merc_id);
	}

	public function scopeUserId($query, int $user_id)
	{
		return $query->where('user_id', $user_id);
	}

	public function scopePhase($query, $phase)
	{
		return $query->where('phase', $phase);
	}

	public function scopeStatus($query, $status)
	{
		return $query->where('status', $status);
	}
	// ----------------- QUERY SCOPING -----------------
}
