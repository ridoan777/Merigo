<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Wallets\Actions\BarWalletSaveAction;
use App\Helpers\Errors\ExceptionHandling;
use App\Helpers\UidGenerator;
use App\Models\Billings\Wallets\BarWallet;

class CreateOrGetBarWalletService
{
   public function handle($validated, $barId, $USER, ?bool $strict = false)
   {
      $BAR_WALLET_UID = null;

      // ------------ CHECKING EXISTANCE + UID ------------
      $existingWallet = BarWallet::userId($USER->id)->barId($barId)->first();
      if ($existingWallet) {
         return $existingWallet;
      } else {
         if($strict){
            ExceptionHandling::bailout(404, "No wallet found! You cannot purchase this deal without bar points! Try to Check-in first!");
         }
            
         $BAR_WALLET_UID = UidGenerator::uniqueULID("WAL-BAR", 12, 18, 4);
      }
      // ------------ CHECKING EXISTANCE + UID ------------
      $validated = [];
      $validated['balance'] = 0;
      $validated['total_earnings'] = 0;
      $validated['total_spent'] = 0;
      $validated['status'] = 1;

      $action = new BarWalletSaveAction();
      $wallet = $action->execute($validated, $barId, $BAR_WALLET_UID, $USER);

      return $wallet;
   }
}