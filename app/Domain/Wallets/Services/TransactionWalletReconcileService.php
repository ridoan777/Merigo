<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Wallets\Enums\PointTransactionEnums;

class TransactionWalletReconcileService
{
   public function handle($transaction, $wallet)
   {
      // $type = PointTransactionEnums::from($transaction->transaction_type);
      $type = $transaction->transaction_type;

      if ($type->isEarning()) {
         $wallet->increment('balance', $transaction->amount);
         $wallet->increment('total_earnings', $transaction->amount);

         if($type == PointTransactionEnums::REFERRAL){
            $wallet->increment('total_referrals', 1);
         }
      }

      if ($type->isSpending()) {
         $wallet->decrement('balance', $transaction->amount);
         $wallet->increment('total_spent', $transaction->amount);
      }

      return $wallet->fresh();
   }
}