<?php

namespace App\Http\Controllers\Workflows\Merchandises;

use App\Helpers\Ui\DatatableHelper;
use App\Http\Controllers\Controller;
use App\Models\Workflows\Merchandises\MercRequest;
use App\Models\Workflows\Referrals\ReferralRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WebMercRequestController extends Controller
{
    public function index()
    {
        return view('Admin.sidebar.MercRequests.index');
    }

    public function show(MercRequest $mercRequest)
    {
        return view('Admin.sidebar.MercRequests.show', compact('mercRequest'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:merc_requests,id',
                'phase' => 'required|string'
            ]);
            // dd($validated);

            // ------------ CHECKING EXISTANCE + UID ------------
            $existingMerchandise = MercRequest::findOrFail((int)$validated['id']);
            // ------------ CHECKING EXISTANCE + UID ------------

            $existingMerchandise->update([
                'phase' => $validated['phase']
            ]);

            return redirect()->back()->with('success', "A merchandise request is updated to the phase '{$existingMerchandise->phase}' successfully.");
        } catch (Throwable $e) {
            return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
        }
    }

    public function toggle(MercRequest $mercRequest)
    {
        try {
            $mercRequest->update([
                'status' => !$mercRequest->status,
            ]);

            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function delete(MercRequest $mercRequest)
    {
        try {
            $name = $mercRequest?->mercReqRelatingBackTo_Merchandise?->name;
            $mercRequest->delete();

            return redirect()->back()->with('success', "Merchandise request for '{$name}' has been deleted successfully.");
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:merc_requests,id',
        ]);

        $ids = $request->ids;
        $requests = MercRequest::whereIn('id', $ids)->get();

        if ($requests->isEmpty()) {
            return back()->with('error', 'No valid requests selected.');
        }

        DB::beginTransaction();
        try {
            $count = $requests->count();
            foreach ($requests as $mercRequest) {
                /** @var MercRequest $mercRequest */
                $mercRequest->delete();
            }

            DB::commit();
            return redirect()->back()->with('success', "Selected merchandise requests [{$count}] have been deleted successfully.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('MercRequest bulk delete failed', ['ids' => $ids, 'error' => $e->getMessage()]);

            return back()->with('error', 'Bulk delete failed. No changes were made.');
        }
    }

    public function mercReqDatatable(Request $request)
    {
        try {

            $columns = [null, null, 'id', 'merc_id', 'name', 'phase', 'user_id', 'receiver_phone', 'points_debited', 'note', 'status', 'created_at', null];

            $draw = (int)$request->input('draw');
            $start = (int)$request->input('start', 0);
            $length = (int)$request->input('length', 25);

            $orderColIndex = (int)$request->input('order.0.column', 0);
            $orderCol = $columns[$orderColIndex] ?? 'created_at';

            $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            $query = MercRequest::with([
                'mercReqRelatingBackTo_Merchandise:id,merc_uid,name,image,points_cost',
                'merReqRelatingBackTo_User:id,user_uid,name,email,avatar'
            ])->select(['id', 'merc_id', 'user_id', 'phase', 'points_debited', 'receiver_phone', 'receiver_address', 'note', 'status', 'created_at']);

            if ($search = $request->input('search.value')) {
                $query->where(function ($q) use ($search) {
                    $q->where('receiver_phone', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhere('phase', 'like', "%{$search}%")
                        ->orWhereHas('mercReqRelatingBackTo_Merchandise', function ($mq) use ($search) {
                            $mq->where('name', 'like', "%{$search}%")
                                ->orWhere('merc_uid', 'like', "%{$search}%");
                        })
                        ->orWhereHas('merReqRelatingBackTo_User', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($orderCol) {
                $query->orderBy($orderCol, $orderDir);
            }

            $recordsTotal = MercRequest::count();
            $recordsFiltered = $query->count();

            $data = $query->offset($start)->limit($length)->get();

            $sn = $start;
            $zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

            $data->transform(function ($row) use (&$sn, $zones) {

                $row->DT_RowId = 'row_' . $row->id;
                $row->view_url = route('backend_merc_requests_show', $row->id);

                $row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

                $row->SN = ++$sn;

                $mercRequestProduct = $row->mercReqRelatingBackTo_Merchandise;

                $row->product = DatatableHelper::detailBlock($mercRequestProduct, true, $mercRequestProduct->merc_uid, true, $mercRequestProduct?->image);

                $row->phase = DatatableHelper::phaseColor($row->phase);

                $row->user = DatatableHelper::userBlock($row->merReqRelatingBackTo_User);

                $row->receiver = '<div class="text-xs text-left">
                    <div>' . e($row->receiver_phone) . '</div>
                    <div class="text-gray-400">' . e(Str::limit($row->receiver_address, 200)) . '</div>
                    </div>';

                $row->transactions = '<span class="font-bold text-fuchsia-600 dark:text-fuchsia-400">-' . number_format($row->points_debited) . ' pts</span>';

                $row->note = '<span class="text-xs text-gray-600">' . e(Str::limit($row->note, 200)) . '</span>';

                $row->status_label = DatatableHelper::phaseColor($row->status);

                $row->created_at_formatted = $row->created_at
                    ? $row->created_at->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')'
                    : 'N/A';

                $row->actions = DatatableHelper::datatableAction($row, true, 'backend_merc_requests_toggle', 'backend_merc_requests_delete');

                return $row;
            });

            return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);

        } catch (Throwable $e) {
            return DatatableHelper::handleDatatableError($request, $e, true, "Merc Request Datatable Error");
        }
    }
}
