<?php

namespace App\Http\Controllers\Billings\Customers;

use App\Http\Controllers\Controller;
use App\Helpers\{ApiJsonReturnHelper, Errors\ExceptionHandling, IconPack};
use App\Helpers\PaymentHelpers\{CentsConversion, SubscriptionAuthCheck};
use App\Models\Billings\Subscriptions\{SubscribedUserWeb, SubscriptionTier};
use Illuminate\Support\Facades\{Auth, DB, Log, Storage};
use Illuminate\{Http\Request, Validation\Rule};
use App\Models\Workflows\Projects\Project;
use App\Helpers\Settings\SettingsGatekeeping;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use Throwable;

class WebCustomersController extends Controller
{
   // -------------------------- INDEXES --------------------------
   public function index()
   {
      return view('Admin.sidebar.Billings.Customers.index');
   }

   public function subscribers()
   {
      return view('Admin.sidebar.Billings.Customers.Subscribers.subscribers');
   }

   public function buyers()
   {
      return view('Admin.sidebar.Billings.Customers.buyers');
   }
   // -------------------------- INDEXES --------------------------

   // -------------------------- SUBSCRIBERS --------------------------

   public function showSubscriber(SubscribedUserWeb $subscriber)
   {
      $subscriber->load('subscriberWebRelatingBackTo_User', 'subscriberWebRelatingBackTo_Tier', 'subscriberWebRelationWith_Project');
      return view('Admin.sidebar.Billings.Customers.Subscribers.show', compact('subscriber'));
   }
   
   public function toggleSubscriber(SubscribedUserWeb $subscriber)
   {
      try {
         $subscriber->update([
            'status' => !$subscriber->status,
         ]);

         return response()->json(['success' => true]);
      } catch (Exception $e) {
         return response()->json(['success' => false, 'error' => $e->getMessage()]);
      }
   }

   public function deleteSubscriber(SubscribedUserWeb $subscriber)
   {
      $username = null;
      $type = null;
      try {
         $username = $subscriber?->subscriberWebRelatingBackTo_User?->name ?? null;
         $type = "$" . ($subscriber?->amount ?? "N/A") . "/" . ($subscriber?->duration ??  "N/A") . " day(s)";
         // dd($username, $type);
         $subscriber->delete();
         return redirect()->back()->with('success', "Subscription record of '{$username}' for '{$type}' is deleted successfully.");
      } catch (Exception $e) {
         return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
      }
   }

   public function bulkDeleteSubscriber(Request $request)
   {
      $request->validate([
         'ids' => 'required|array',
         'ids.*' => 'integer|exists:subscribed_user_webs,id',
      ]);

      $ids = $request->ids;

      $subscribers = SubscribedUserWeb::whereIn('id', $ids)->get();

      if ($subscribers->isEmpty()) {
         return back()->with('error', 'No valid subscribers selected.');
      }

      $MESSAGE = "Selected subscriptions are deleted.";
      DB::beginTransaction();
      // dd($ids, $subscribers);
      try {
         foreach ($subscribers as $subscriber) {
            $subscriber->delete();
         }

         DB::commit();

         return redirect()->back()->with('success', $MESSAGE);
      } catch (Throwable $e) {
         DB::rollBack();
         // Log::error('Subscriber bulk delete failed', ['ids' => $ids, 'error' => $e->getMessage()]);
         return back()->with('error', 'Bulk delete failed. No changes were made.');
      }
   }

   // -------------------------- SUBSCRIBERS --------------------------


   // -------------------------- DATATABLES --------------------------

   public function subscribersDatatable(Request $request)
   {
      try {
         $columns = ['id', 'user_id', 'tier_id', 'service_id', 'payment_status', 'sub_status', 'amount', 'duration', 'next_renewal_at', 'payer', 'last4', 'brand', 'expiry', 'status', 'pdf_1st', 'pdf_latest', 'created_at'];

         $draw = intval($request->input('draw'));
         $start = intval($request->input('start', 0));
         $length = intval($request->input('length', 25));
         $orderColIndex = intval($request->input('order.0.column', 0));
         $orderCol = $columns[$orderColIndex] ?? 'id';
         $orderDir = in_array(strtolower($request->input('order.0.dir')), ['asc', 'desc']) ? $request->input('order.0.dir') : 'asc';

         $query = SubscribedUserWeb::with([
            'subscriberWebRelatingBackTo_User',
            'subscriberWebRelatingBackTo_Tier',
            'subscriberWebRelationWith_Project'
         ])->select(['id', 'user_id', 'tier_id', 'service_id', 'payment_status', 'sub_status', 'amount', 'duration', 'next_renewal_at', 'payer', 'last4', 'brand', 'expiry', 'status', 'pdf_1st', 'pdf_latest', 'created_at']);

         if ($search = $request->input('search.value')) {
            $query->where(function ($q) use ($search) {
               $q->where('payment_status', 'like', "%{$search}%")
                  ->orWhere('sub_status', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhereHas(
                     'subscriberWebRelatingBackTo_User',
                     fn($u) =>
                     $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                  )
                  ->orWhereHas(
                     'subscriberWebRelatingBackTo_Tier',
                     fn($t) =>
                     $t->where('name', 'like', "%{$search}%")
                  )
                  ->orWhereHas(
                     'subscriberWebRelationWith_Project',
                     fn($p) =>
                     $p->where('title', 'like', "%{$search}%")
                  );
            });
         }

         $recordsTotal = SubscribedUserWeb::count();
         $recordsFiltered = $query->count();

         $data = $query->orderBy($orderCol, $orderDir)->offset($start)->limit($length)->get();

         $sn = $start;
         $zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

         $data->transform(function ($row) use (&$sn, $zones) {
            $row->DT_RowId = 'row_' . $row->id;
            $row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

            $row->SN = ++$sn;

            $user = $row->subscriberWebRelatingBackTo_User;
            $avatar = $user?->avatar;
            $userAvatar = $avatar
               ? '<img src="' . Storage::url($avatar) . '" class="w-10 h-10 rounded-full object-cover" />'
               : '<img src="' . asset('site_assets/dummies/dummy_man.webp') . '" class="w-10 h-10 rounded-full opacity-70" />';

            $row->user = '
               <div class="flex items-center space-x-3">
                  <div>' . $userAvatar . '</div>
                  <div class="flex flex-col">
                     <span class="text-xs text-gray-400">DB ID: ' . ($user->id ?? '—') . '</span>
                     <span class="text-sm font-medium">' . ($user->name ?? '—') . '</span>
                     <span class="text-xs text-gray-400">' . ($user->email ?? '—') . '</span>
                  </div>
               </div>
            ';

            $tier = $row->subscriberWebRelatingBackTo_Tier;
            $tierImg = $tier?->image
               ? '<img src="' . Storage::url($tier->image) . '" class="w-10 h-10 rounded object-cover" />'
               : '<span class="text-gray-400 italic">No image</span>';

            // ------------------- TIER -------------------
            $platformColors = [
               'web' => 'bg-sky-300 bg-opacity-25',
               'mobile' => 'bg-fuchsia-300 bg-opacity-25',
            ];
            $platformClass = $platformColors[$tier->platform] ?? 'bg-gray-300 bg-opacity-25';
            $row->tier = '
               <div class="flex items-center space-x-3">
                  <div>' . $tierImg . '</div>
                  <div class="flex flex-col">
                     <span class="text-sm font-medium">' . ($tier->name ?? '—') . '</span>
                     <span class="text-xs text-center rounded-lg inline-block ' . $platformClass . ' text-gray-700">' . ($tier->platform ?? '—') . '</span>
                  </div>
               </div>
            ';

            $project = $row->subscriberWebRelationWith_Project;
            $projImg = $project?->image
               ? '<img src="' . Storage::url($project->image) . '" class="w-10 h-10 rounded object-cover mx-auto" />'
               : '<span class="text-gray-400 italic">No image</span>';

            $row->service = '
               <div class="flex items-center gap-4">
                  ' . $projImg . '
                  <div>
                     <span class="text-xs text-gray-400">DB ID: ' . ($project->id ?? '—') . '</span>
                     <span class="text-sm">' . ($project->title ?? '—') . '</span>
                  </div>
               </div>
            ';

            // ------------------- PAYMENT STATUS -------------------
            $paymentStatusColors = [
               'pending' => 'bg-sky-300 bg-opacity-25',
               'paid' => 'bg-fuchsia-300 bg-opacity-25',
            ];
            $paymentStatusClass = $paymentStatusColors[$row->payment_status] ?? 'bg-gray-300 bg-opacity-25';
            $row->payment_status = '<span class="px-2 py-1 rounded-lg inline-block ' . $paymentStatusClass . '">' . ucfirst($row->payment_status) . '</span>';

            // ------------------- SUBSCRIPTION STATUS -------------------
            $subStatusColors = [
               'requested' => 'bg-sky-300 bg-opacity-25',
               'running' => 'bg-orange-300 bg-opacity-25',
               'cancelled' => 'bg-rose-300 bg-opacity-25',
            ];
            $subStatusClass = $subStatusColors[$row->sub_status] ?? 'bg-gray-300 bg-opacity-25';
            $row->sub_status = '<span class="px-2 py-1 rounded-lg inline-block ' . $subStatusClass . '">' . ucfirst($row->sub_status) . '</span>';

            // ------------- TOTAL AMOUNT -------------
            $amount = $row->getRawOriginal('amount');
            $row->amount_display  = $amount !== null ? '<p>$' . number_format((float) $amount, 2) . '</p>' : 'N/A';

            // ---------- CARD ----------
            $row->card = '
               <div class="flex flex-col text-center">
                  <span class="text-sm font-medium">' . ($row->payer ?? '—') . '</span>
                  <span class="text-xs text-gray-500">' . ($row->brand ? strtoupper($row->brand) : '—') . ' •••• ' . ($row->last4 ?? '—') . '</span>
                  <span class="text-xs text-gray-500">' . ($row->expiry ?? '—') . '</span>
               </div>
            ';

            $row->duration_data = $row->duration ? $row->duration . ' (days)' : "N/A";

            $row->next_renewal_at = $row->next_renewal_at ? Carbon::createFromFormat('Y-m-d H:i:s',   $row->getRawOriginal('next_renewal_at'), 'UTC')->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
               : '—';

            $row->status_label = $row->status
               ? '<span class="text-green-600 font-medium">Active</span>'
               : '<span class="text-red-600 font-medium">Inactive</span>';

            $row->created_at_formatted = '<span class="text-xs">' .
               Carbon::createFromFormat('Y-m-d H:i:s', $row->getRawOriginal('created_at'), 'UTC')->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
               . '</span>';

            // --------------- ACTION ---------------
            $row->view_url = route('backend_billings_subscriber_show', $row->id);

            $toggleIcon = '';
            $trashIcon = '';

            try {
               if (class_exists('\App\Helpers\IconPack')) {
                  if ($row->status) {
                     $toggleIcon = IconPack::toggleOn(['class' => 'iconPackItem w-8 h-8 text-green-500']);
                  } else {
                     $toggleIcon = IconPack::toggleOff(['class' => 'iconPackItem w-8 h-8 text-red-500']);
                  }

                  $trashIcon = IconPack::trash(['class' => 'iconPackItem w-3 h-3 text-gray-100']);
                  $pdfIcon = IconPack::pdf(['class' => 'iconPackItem w-8 h-8 text-red-500']);
               } else {
                  $toggleIcon = $row->status ? '<span>✅</span>' : '<span>⛔</span>';
                  $trashIcon = '<span>🗑</span>';
               }
            } catch (Throwable $e) {
               $toggleIcon = $row->status ? '<span>✅</span>' : '<span>⛔</span>';
               $trashIcon = '<span>🗑</span>';
            }

            $row->actions = '
                <div class="flex justify-center items-center space-x-2">
                  <a href="' . $row->pdf_1st . '">' . $pdfIcon . '</a>

                    <form action="' . route('backend_billings_subscriber_toggle', $row->id) . '"
                        method="POST"
                        class="toggleForm inline-flex items-center justify-center p-2 rounded transition"
                        style="display:inline;">
                        ' . csrf_field() . '
                        <button type="submit" class="text-white p-2 rounded" title="Toggle Status">
                            ' . $toggleIcon . '
                        </button>
                    </form>

                    <form action="' . route('backend_billings_subscriber_delete', $row->id) . '"
                        method="POST"
                        class="deleteForm inline-flex items-center justify-center p-2 rounded transition"
                        style="display:inline;">
                        ' . csrf_field() . method_field('DELETE') . '
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded" title="Delete">
                            ' . $trashIcon . '
                        </button>
                    </form>
                </div>
            ';
            return $row;
         });

         return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
         ]);
      } catch (Throwable $e) {
         Log::error('Subscribers Datatable Error: ' . $e->getMessage());
         return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => config('app.debug') ? $e->getMessage() : 'An error occurred.'
         ], 500);
      }
   }

   // -------------------------- DATATABLES --------------------------
}
