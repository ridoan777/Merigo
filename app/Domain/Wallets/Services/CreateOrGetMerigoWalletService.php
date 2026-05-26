<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Wallets\Actions\MerigoWalletSaveAction;
use App\Helpers\Errors\ExceptionHandling;
use App\Helpers\UidGenerator;
use App\Models\Billings\Wallets\MerigoWallet;

class CreateOrGetMerigoWalletService
{
   public function handle($referral, $USER, ?bool $createIfNotExists = false)
   {
      $MERIGO_WALLET_ID = null;

      // ------------ CHECKING EXISTANCE + UID ------------
      $existingWallet = MerigoWallet::userId($USER->id)->first();
      if ($existingWallet) {
         logger('7-existingWallet true', [$existingWallet]);
         return $existingWallet;
      } else {
         if($createIfNotExists){
            ExceptionHandling::bailout(404, "No wallet found! You cannot exchange this good without merigo points! Try to referring some friends first using your own refer code!");
         }
            
         $MERIGO_WALLET_ID = UidGenerator::uniqueULID("WAL-MERIGO", 12, 18, 4);
      }
      // ------------ CHECKING EXISTANCE + UID ------------
      $referral = [];
      $referral['balance'] = 0;
      $referral['total_earnings'] = 0;
      $referral['total_spent'] = 0;
      $referral['status'] = 1;

      $action = new MerigoWalletSaveAction();
      $wallet = $action->execute($referral, $MERIGO_WALLET_ID, $USER);

      return $wallet;
   }
}