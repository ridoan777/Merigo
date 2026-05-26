<?php

namespace App\Models\Workflows\Referrals;

use Illuminate\Database\Eloquent\Attributes\{Table, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Table('referral_records')]
#[Fillable(['invitor_id', 'invited_id', 'refer_code', 'credit_amount', 'status'])]

class ReferralRecord extends Model
{
	protected function casts(): array
	{
		return [
			'invitor_id' => 'integer',
			'invited_id' => 'integer',
			'credit_amount' => 'integer',
			'status' => 'integer',
		];
	}

	// ----------------- QUERY SCOPING -----------------
	public function scopeInvitorId($query, $id)
	{
		return $query->where('invitor_id', $id);
	}

	public function scopeInvitedId($query, $id)
	{
		return $query->where('invited_id', $id);
	}

	public function scopeReferCode($query, $code)
	{
		return $query->where('refer_code', $code);
	}

	public function scopeCreditAmount($query, $amount)
	{
		return $query->where('credit_amount', $amount);
	}

	public function scopeStatus($query, $status)
	{
		return $query->where('status', $status);
	}
	// ----------------- QUERY SCOPING -----------------
}
