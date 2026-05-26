<?php

namespace App\Models\Billings\Wallets;

use App\Domain\Wallets\Enums\PointTransactionEnums;
use Illuminate\Database\Eloquent\Attributes\{Table, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Table('wallet_point_transactions')]
#[Fillable(['transaction_uid', 'wallet_morphed_id', 'wallet_morphed_type', 'user_id', 'transaction_type', 'amount', 'phase', 'note', 'status'])]

class PointsTransaction extends Model
{
	protected function casts(): array
	{
		return [
			'wallet_morphed_id' => 'integer',
			'transaction_type' => PointTransactionEnums::class,
			'amount' => 'integer',
			'status' => 'boolean',
		];
	}

	// ----------------- POLYMORPHIC RELATION -----------------
	public function walletMorphed()
	{
		return $this->morphTo(__FUNCTION__, 'wallet_morphed_type', 'wallet_morphed_id');

	}
	// ----------------- POLYMORPHIC RELATION -----------------

	// ----------------- QUERY SCOPING -----------------
	public function scopeTransactionType($query, string $transactionType)
	{
		return $query->where('transaction_type', $transactionType);
	}

	public function scopeuserId($query, int $user_id)
	{
		return $query->where('user_id', $user_id);
	}

	public function scopePhase($query, string $phase)
	{
		return $query->where('phase', $phase);
	}

	public function scopeStatus($query, bool $status)
	{
		return $query->where('status', $status);
	}
	// ----------------- QUERY SCOPING -----------------
}