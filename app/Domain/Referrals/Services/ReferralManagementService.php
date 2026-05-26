<?php

namespace App\Domain\Referrals\Services;

use App\Domain\Wallets\Enums\{PointTransactionEnums, PointTransactionPhaseEnums};
use App\Domain\Wallets\Services\{TransactionWalletReconcileService, CreateOrGetMerigoWalletService};
use App\Models\Workflows\Referrals\{ReferralRule, ReferralRecord};
use Illuminate\Support\Facades\{Cache, DB};
use App\Helpers\UidGenerator;
use App\Models\Users\User;

class ReferralManagementService
{
   public $ruleBook = null;
   public function handle($USER, $referCode): bool
   {
      logger('2-landed on ReferralManagementService');
      $referrer = User::with('userRelatingBackTo_ReferInvitor')->where('own_referral_code', $referCode)->first();

      $existingRecord = ReferralRecord::invitedId($USER->id)->invitorId($referrer->id)->first();

      $this->ruleBook = ReferralRule::first();

      if ($existingRecord) {
         logger('3.1-if on ReferralManagementService');
         return false;
      } else {
         logger('3.2-else on ReferralManagementService');
         $referral = ReferralRecord::create([
            'invitor_id' => $referrer->id,
            'invited_id' => $USER->id,
            'refer_code' => $referCode,
            'credit_amount' => $this->ruleBook?->credit_amount ?? 0,
            'status' => 0,
         ]);
         $this->walletTransactions($USER, $referrer, $referral);
         logger('10-SUCCESS from ReferralManagementService');
         return true;
      }
   }

   private function walletTransactions($USER, $referrer, $referral)
   {
      $merigoWalletService = new CreateOrGetMerigoWalletService();
      // $merigoWalletService = $merigoWallet->handle($referral, $USER, false);

      $reconcile = new TransactionWalletReconcileService();
      logger('4-before cache-lock on walletTransactions');

      $result = Cache::lock("checkin:{$USER->id}:{$referral->referCode}", 10)->block(5, function () use ($referral, $USER, $referrer, $merigoWalletService, $reconcile) {

         logger('5-before db:transaction on walletTransactions');
         return DB::transaction(function () use ($referral, $USER, $referrer, $merigoWalletService, $reconcile) {

            // $cdTime = new CDTimeChecker();
            // $cdTime->handle($targetBar, $USER);
            $users = [$USER, $referrer];

            foreach ($users as $index => $targetUser) {
               logger('6.1-inside the loop', [$targetUser->id]);
               $wallet = $merigoWalletService->handle($referral, $targetUser);

               $credit_amount = $this->ruleBook?->credit_amount ?? 0;
               $message = "Points earned by exchanging refer code '{$referral->refer_code}' between {$USER->name} and {$referrer->name}";

               if ($targetUser->id === $referrer->id) {
                  $todayEarnings = ReferralRecord::invitorId($referrer->id)
                     ->where('id', '!=', $referral->id)
                     ->whereDate('created_at', now()->toDateString())
                     ->sum('credit_amount');

                  $creditLimit = $this->ruleBook?->credit_limit ?? 0;
                  $remainingLimit = max(0, $creditLimit - $todayEarnings);

                  logger('====todayEarnings, creditLimit, remainingLimit', [$todayEarnings, $creditLimit, $remainingLimit]);

                  if ($remainingLimit <= 0) {
                     logger('6.2-referrer earnings limit crossed', [$creditLimit]);

                     $credit_amount = 0;
                     $message = "Daily referral earning limit reached.";
                  } else {
                     $credit_amount = min($credit_amount, $remainingLimit);
                  }
               }

               if ($credit_amount <= 0) {
                  continue;
               }

               $transaction = $wallet->walletTransactions()->create([
                  'transaction_uid' => UidGenerator::uniqueULID("TRX-REFR", 10, 26, 4),
                  'user_id' => $targetUser->id,
                  'transaction_type' => PointTransactionEnums::REFERRAL?->value,
                  'amount' => $credit_amount,
                  'phase' => PointTransactionPhaseEnums::COMPLETED->value,
                  'note' => $message ?? "Points earned by exchanging refer code '{$referral->refer_code}' between {$USER->name} and {$referrer->name}",
                  'status' => 1,
               ]);

               $wallet = $reconcile->handle($transaction, $wallet);

               // return [
               //    'transaction' => $transaction,
               //    'wallet' => $wallet,
               // ];
            }

         });
      });
      return $result;
   }
}