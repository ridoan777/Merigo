<?php

namespace App\Models\Billings\Wallets;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Attributes\{Table, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Table('wallet_merigo_points')]
#[Fillable(['merigo_wallet_uid', 'user_id', 'total_referrals', 'balance', 'total_earnings', 'total_spent', 'status'])]

class MerigoWallet extends Model
{
	protected function casts(): array
	{
		return [
			'user_id' => 'integer',
			'total_referrals' => 'integer',
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

	public function walletTransactions()
	{
		return $this->morphMany(PointsTransaction::class,'wallet_morphed');
	}
	// ----------------- QUERY SCOPING -----------------
	public function scopeUserId($query, int $user_id)
	{
		return $query->where('user_id', $user_id);
	}

	public function scopeStatus($query, $status)
	{
		return $query->where('status', $status);
	}
}
