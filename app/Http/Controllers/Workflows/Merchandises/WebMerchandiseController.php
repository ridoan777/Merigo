<?php

namespace App\Http\Controllers\Workflows\Merchandises;

use App\Domain\Merchandises\Actions\MerchandiseSaveAction;
use App\Helpers\FileHelpers\FileManagement;
use App\Helpers\Ui\DatatableHelper;
use App\Helpers\UidGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\MercValidateRequest;
use App\Models\Workflows\Merchandises\Merchandise;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebMerchandiseController extends Controller
{
    public function index()
    {
        return view('Admin.sidebar.Merchandises.index');
    }

	public function show(Merchandise $merchandise)
	{
		return view('Admin.sidebar.Merchandises.show', compact('merchandise'));
	}

    public function store(MercValidateRequest $request, MerchandiseSaveAction $action)
    {
        try {
            $validated = $request->validated();
            // dd($validated);
            $USER = $request->user();

            $MERC_UID = null;
            $existingMerchandise = null;
            $isNewItem = false;

            // ------------ CHECKING EXISTANCE + UID ------------
            if (isset($validated['id']) && $validated['id']) {
                $existingMerchandise = Merchandise::findOrFail((int)$validated['id']);
                $MERC_UID = $existingMerchandise->merc_uid;

            } else {
                $MERC_UID = UidGenerator::uniqueULID($validated['name'], 12, 16, 4);
                $isNewItem = true;
            }
            // ------------ CHECKING EXISTANCE + UID ------------

            $good = $action->execute($validated, $MERC_UID, $existingMerchandise, $USER, $isNewItem);

            return redirect()->back()->with('success', "A merchandise good '{$good->name}' is saved successfully.");
        } catch (Throwable $e) {
            return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
        }
    }

    public function toggle(Merchandise $merchandise)
    {
        try {
            $merchandise->update([
                'status' => !$merchandise->status,
            ]);

            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function delete(Merchandise $merchandise)
    {
        try {
            $name = $merchandise->name ?? $merchandise->merc_uid;
            $DISK_FOLDER = config('filesystems.default');

            if ($merchandise->image) {
                $imageFolder = dirname($merchandise->image);
                FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
            }

            $merchandise->delete();

            return redirect()->back()->with('success', "A merchandise good '{$name}' has been deleted successfully.");
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:merchandises,id',
        ]);

        $ids = $request->ids;
        $merchandises = Merchandise::whereIn('id', $ids)->get();

        if ($merchandises->isEmpty()) {
            return back()->with('error', 'No valid merchandise items selected.');
        }

        $DISK_FOLDER = config('filesystems.default');
        $deleted = [];

        DB::beginTransaction();

        try {
            foreach ($merchandises as $merchandise) {
                /** @var Merchandise $merchandise */
                if ($merchandise->image) {
                    $imageFolder = dirname($merchandise->image);
                    FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
                }

                $merchandise->delete();
                $deleted[] = $merchandise->name ?? $merchandise->merc_uid;
            }

            DB::commit();

            $MESSAGE = "Selected merchandise items [" . count($deleted) . "] have been deleted successfully.";

            return redirect()->back()->with('success', $MESSAGE);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Merchandise bulk delete failed', ['ids' => $ids, 'error' => $e->getMessage()]);

            return back()->with('error', 'Bulk delete failed. No changes were made.');
        }
    }

    public function merchandiseDatatable(Request $request)
    {
        try {

            $columns = [null, null, 'id', 'merc_uid', 'name', 'image', 'points_cost', 'description', 'creator', 'status', 'created_at', null];

            $draw = (int)$request->input('draw');
            $start = (int)$request->input('start', 0);
            $length = (int)$request->input('length', 25);

            $orderColIndex = (int)$request->input('order.0.column', 0);
            $orderCol = $columns[$orderColIndex] ?? 'created_at';

            $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            $query = Merchandise::with('merchandiseRelatingBackTo_Creator:id,user_uid,name,email,avatar')->select(['id', 'merc_uid', 'name', 'description', 'points_cost', 'image', 'creator', 'status', 'created_at']);

            if ($search = $request->input('search.value')) {
                $query->where(function ($q) use ($search) {
                    $q->where('merc_uid', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($orderCol)
                $query->orderBy($orderCol, $orderDir);

            $recordsTotal = Merchandise::count();
            $recordsFiltered = $query->count();

            $data = $query->offset($start)->limit($length)->get();

            $sn = $start;
            $zones = \App\Helpers\Ui\ConvertDynamicTimezone::handle();

            $data->transform(function ($row) use (&$sn, $zones) {

                $row->DT_RowId = 'row_' . $row->id;
                $row->view_url = route('backend_merchandise_show', $row->id);

                $row->checkbox = '<input type="checkbox" name="ids[]" value="' . $row->id . '" class="row-check w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">';

                $row->SN = ++$sn;

                $row->image_block = $row->image ? '<img src="' . e($row->image_url) . '" class="w-12 h-12 object-cover rounded border mx-auto">' : '<span class="text-xs text-gray-400">No Image</span>';

                $row->point_block = '<span class="font-bold text-indigo-600">+' . number_format($row->points_cost) . ' pts</span>';

                // $row->creator_block = $row->creator;
                $row->creator_block = DatatableHelper::userBlock($row->merchandiseRelatingBackTo_Creator);

                $row->status_label = DatatableHelper::phaseColor($row->status);

                $row->created_at_formatted = $row->created_at ? $row->created_at->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')' : 'N/A';

                $row->actions = DatatableHelper::datatableAction($row, true, 'backend_merchandise_toggle', 'backend_merchandise_delete');

                return $row;
            });

            return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);

        } catch (Throwable $e) {

            return DatatableHelper::handleDatatableError($request, $e, true, "Merchandise Datatable Error");
        }
    }
}
