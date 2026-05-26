<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Wallets\Enums\PointTransactionEnums;
use App\Helpers\Errors\ExceptionHandling;
use App\Models\Billings\Wallets\{BarWallet, PointsTransaction};

class CDTimeChecker
{
   public function handle($targetBar, $USER)
   {
      $lastTransaction = PointsTransaction::where('wallet_morphed_type', BarWallet::class)
         ->userId($USER->id)
         ->transactionType(PointTransactionEnums::CHECKIN->value)
         // ->whereHas('walletMorphed', fn($q) => $q->where('bar_id', $targetBar->id))
         ->whereHasMorph('walletMorphed',[BarWallet::class],fn($q) => $q->where('bar_id', $targetBar->id))
         ->latest()->first();

      if (!$lastTransaction) {
         return true;
      }

      $hoursPassed = $lastTransaction->created_at->diffInHours(now());

      $hoursLeft = ($hoursPassed && $targetBar->cd_time) ? round($targetBar->cd_time - $hoursPassed, 2) : $targetBar->cd_time;

      if ($hoursPassed >= ($targetBar->cd_time ?? 0)) {
         return true;
      } else {
         ExceptionHandling::bailout(409, "You cannot redeem any points from this bar within {$targetBar->cd_time} hours. Come back in {$hoursLeft} hours!");
      }
   }
}