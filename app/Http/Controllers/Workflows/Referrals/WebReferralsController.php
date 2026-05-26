<?php

namespace App\Http\Controllers\Workflows\Referrals;

use App\Http\Controllers\Controller;
use App\Models\Workflows\Referrals\ReferralRule;
use Illuminate\Http\Request;

class WebReferralsController extends Controller
{
    public function index()
    {
        $rule = ReferralRule::first();
        return view('Admin.sidebar.Referrals.index', compact('rule'));
    }

    public function storeRule(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'nullable|integer|exists:referral_rules,id',
                'credit_amount' => 'required|integer|min:0',
                'credit_limit' => 'nullable|integer|min:0',
                'exchange_limit' => 'nullable|integer|min:0',
            ]);

            $rule = ReferralRule::updateOrCreate(
                [
                    'id' => $request->id ?? 1
                ],
                $validated
            );

            return redirect()->back()->with('success', "Referral rules updated successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
        }
    }
}
