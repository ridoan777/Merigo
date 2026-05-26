<?php

namespace App\Models\Workflows\Referrals;

use Illuminate\Database\Eloquent\Attributes\{Table, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Table('referral_rules')]
#[Fillable(['credit_amount', 'credit_limit', 'exchange_limit', 'status'])]

class ReferralRule extends Model
{
	protected function casts(): array
	{
		return [
			'credit_amount' => 'integer',
			'credit_limit' => 'integer',
			'exchange_limit' => 'integer',
			'status' => 'integer',
		];
	}

	// ----------------- QUERY SCOPING -----------------
	public function scopeCreditAmount($query, $amount)
	{
		return $query->where('credit_amount', $amount);
	}

	public function scopeCreditLimit($query, $limit)
	{
		return $query->where('credit_limit', $limit);
	}

	public function scopeExchangeLimit($query, $limit)
	{
		return $query->where('exchange_limit', $limit);
	}

	public function scopeStatus($query, $status)
	{
		return $query->where('status', $status);
	}
	// ----------------- QUERY SCOPING -----------------
}
