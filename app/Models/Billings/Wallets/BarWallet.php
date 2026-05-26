<?php

namespace App\Models\Billings\Wallets;

use App\Models\Users\User;
use App\Models\Workflows\Bar\Bar;
use Illuminate\Database\Eloquent\Attributes\{Table, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Table('wallet_bar_points')]
#[Fillable(['bar_wallet_uid', 'user_id', 'bar_id', 'balance', 'total_earnings', 'total_spent', 'status'])]

class BarWallet extends Model
{
	protected function casts(): array
	{
		return [
			'user_id' => 'integer',
			'bar_id' => 'integer',
			'balance' => 'integer',
			'total_earnings' => 'integer',
			'total_spent' => 'integer',
			'status' => 'boolean',
		];
	}

	public function walletRelatingBackTo_User()
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	public function walletRelatingBackTo_Bar()
	{
		return $this->belongsTo(Bar::class, 'bar_id');
	}

	public function walletTransactions()
	{
		return $this->morphMany(PointsTransaction::class,'wallet_morphed');
	}
	// ----------------- QUERY SCOPING -----------------
	public function scopeUserId($query, int $user_id)
	{
		return $query->where('user_id', $user_id);
	}

	public function scopeBarId($query, int $bar_id)
	{
		return $query->where('bar_id', $bar_id);
	}

	public function scopeStatus($query, $status)
	{
		return $query->where('status', $status);
	}
}
