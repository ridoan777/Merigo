<?php

namespace App\Helpers\Ui;

use App\Helpers\IconPack;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DatatableHelper
{
   // -----------------------------------------------
   public static function datatableAction($row, $toggle = true, $toggleRoute = null, $deleteRoute = null)
   {
      $row->status_label = $row->status
         ? '<span class="text-green-600 font-medium">Active</span>'
         : '<span class="text-red-600 font-medium">Inactive</span>';

      $toggleIcon = $row->status
         ? IconPack::toggleOn(['class' => 'iconPackItem w-8 h-8 text-green-500'])
         : IconPack::toggleOff(['class' => 'iconPackItem w-8 h-8 text-red-500']);

      $trashIcon = IconPack::trash(['class' => 'iconPackItem w-3 h-3 text-gray-100']);

      $toggleBtn = '';
      $deleteBtn = '';

      if ($toggle && $toggleRoute) {
         $toggleBtn = '
            <form action="' . route($toggleRoute, $row->id) . '" method="POST" class="toggleForm inline-flex items-center p-2">
                ' . csrf_field() . '
                <button type="submit">' . $toggleIcon . '</button>
            </form>';
      }

      if ($deleteRoute) {
         $deleteBtn = '
            <form action="' . route($deleteRoute, $row->id) . '" method="POST" class="deleteForm inline-flex items-center p-2">
                ' . csrf_field() . method_field('DELETE') . '
                <button type="submit" class="bg-red-500 p-2 rounded">' . $trashIcon . '</button>
            </form>';
      }

      return '
        <div class="flex justify-center items-center space-x-2">
            ' . $toggleBtn . '
            ' . $deleteBtn . '
        </div>';
   }

   // -----------------------------------------------

   public static function handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data)
   {
      return response()->json([
         'draw' => $draw,
         'recordsTotal' => $recordsTotal,
         'recordsFiltered' => $recordsFiltered,
         'data' => $data,
      ]);
   }
   // -----------------------------------------------

   public static function handleDatatableError($request, $error, $log = true, $logMssg = null)
   {
      if ($log) {
         Log::error($logMssg . ': ' . $error->getMessage());
      }
      return response()->json([
         'draw' => intval($request->input('draw')),
         'recordsTotal' => 0,
         'recordsFiltered' => 0,
         'data' => [],
         'error' => config('app.debug') ? $error->getMessage() : 'An error occurred.'
      ], 500);
   }
   // -----------------------------------------------

   public static function handleSearch($query, $search, $columns)
   {
      $query->where(function ($q) use ($search, $columns) {
         foreach ($columns as $index => $col) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $q->{$method}($col, 'like', "%{$search}%");
         }
         // relational search
         $q->orWhereHas('projectRelatingBackTo_User', function ($uq) use ($search) {
            $uq->where('name', 'like', "%{$search}%");
         });
      });

      return $query;
   }
   // -----------------------------------------------

   public static function userBlock($user)
   {
      $avatar = $user?->avatar ?? null;
      $avatarHtml = $avatar
         ? '<img src="' . Storage::url($avatar) . '" class="w-10 h-10 rounded-full object-cover mx-auto" />'
         : '<img src="' . asset('site_assets/dummies/dummy_man.webp') . '" class="w-10 h-10 rounded-lg object-cover mx-auto opacity-70" />';

      $targetUserId = $user?->id ?? '—';
      $targetUserName = ($user?->name ?? '—') . " " . ($user?->last_name ?? null);
      $targetUserUid = $user?->user_uid ?? '—';
      $targetUserEmail = $user?->email ?? '—';

      return '
         <div class="flex items-center space-x-3 flex-nowrap">
            <div class="shrink-0">' . $avatarHtml . '</div>
            <div class="flex flex-col text-left">
               <span class="text-xs text-gray-400">DB ID: ' . $targetUserId . '</span>
               <span class="text-sm font-medium">' . $targetUserName . '</span>
               <span class="text-xs font-light text-gray-500 dark:text-gray-300"> UID: ' . $targetUserUid . '</span>
               <span class="text-xs text-gray-400 dark:text-gray-300">' . $targetUserEmail . '</span>
            </div>
         </div>
      ';
   }
   // -----------------------------------------------

   public static function subscriberBlock($content)
   {
      $tier = $content?->subscribedAppUserRelatingBackTo_Tier;

      return '
         <div class="flex items-center space-x-3 flex-nowrap">
            <div class="flex flex-col text-left">
               <span class="text-xs text-gray-400">DB ID: ' . ($content?->id ?? '—') . '</span>
               <span class="text-xs font-light text-gray-500 dark:text-gray-300"> UID: ' . ($content?->subscription_uid ?? "-") . '</span>
               <span class="text-sm font-medium">Tier:' . ($tier ? $tier->name : "N/A") . '</span>
               <span class="text-sx">Store:' . ($content?->store ?? "N/A") . '</span>
               <span class="text-sm">Amount:' . ($content?->amount ? ($content?->amount . " " . $content?->currency) : "N/A") . '</span>
            </div>
         </div>
      ';
   }
   // -----------------------------------------------

   public static function detailBlock($content, $hasUid = true, $content_uid, $hasImage = true, $image)
   {
      if (is_array($content)) {
         $content = (object)$content;
      }
      $avatarHtml = $image
         ? '<img src="' . Storage::url($image) . '" class="w-10 h-10 rounded-lg   object-cover mx-auto" />'
         : '<img src="' . asset('site_assets/dummies/dummy_no_file.jpg') . '" class="w-10 h-10 rounded-lg object-cover mx-auto opacity-70" />';

      $targetUserId = $content?->id ?? '—';
      $targetUserName = ($content?->title ?? $content?->name ?? '—');
      $targetUserUid = $content_uid ?? '—';

      $hideUid = $hasUid ? null : 'hidden';
      $hideImage = $hasImage ? null : 'hidden';

      return '
         <div class="flex items-center space-x-3 flex-nowrap">
            <div class="shrink-0 ' . $hideImage . '">' . $avatarHtml . '</div>
            <div class="flex flex-col text-left">
               <span class="text-xs text-gray-400">DB ID: ' . $targetUserId . '</span>
               <span class="text-sm font-medium">' . $targetUserName . '</span>
               <span class="text-xs font-light text-gray-500 dark:text-gray-300 ' . $hideUid . '"> UID: ' . $targetUserUid . '</span>
            </div>
         </div>
      ';
   }
   // -----------------------------------------------

   public static function galleryBlock($content, ?int $maxImage = 3)
   {
      if ($content->count()) {
         $images = '';

         foreach ($content->take($maxImage) as $gallery) {
            $images .= '
               <img src="' . $gallery->filepath_url . '" class="w-12 h-12 rounded-lg object-cover border border-gray-200 shadow-sm" />
            ';
         }
         return '
                  <div class="flex items-center justify-center gap-2">
                     ' . $images . '
                  </div>
               ';
      } else {
         return '<span class="text-gray-400 italic">No images</span>';
      }
   }
   // -----------------------------------------------

   public static function roleColor($role)
   {
      $map = [
         'super_admin' => 'bg-blue-300 opacity-75',
         'admin' => 'bg-emerald-300 opacity-75',
         'student' => 'bg-fuchsia-300 opacity-75',
         'bar_admin' => 'bg-violet-300 opacity-75',
         'guest' => 'bg-orange-300 opacity-75',
      ];

      $key = $role ? strtolower($role) : null;
      $color = $key && isset($map[$key]) ? $map[$key] : 'bg-gray-400';
      $text = $key ? ucfirst($key) : 'Unverified';

      return '<span class="px-2 py-1 text-xs font-medium rounded ' . $color . ' text-gray-800">' . $text . '</span>';
   }
   // -----------------------------------------------

   public static function phaseColor($phase, $size = null)
   {
      $phaseColor = [
         'male' => 'bg-indigo-300 opacity-75',
         'female' => 'bg-rose-300 opacity-75',
         'prefer_not' => 'bg-green-300 opacity-75',
         // -------------------------
         'pending' => 'bg-orange-200 opacity-75',
         'processing' => 'bg-violet-200 opacity-75',
         'declined' => 'bg-rose-200 opacity-75',
         'incoming' => 'bg-blue-200 opacity-75',
         'completed' => 'bg-green-200 opacity-75',
         // -------------------------
         'on-track' => 'bg-cyan-400 opacity-75',
         'cancel_scheduled' => 'bg-orange-400 opacity-75',
         'cancelled' => 'bg-fuchsia-400 opacity-75',
         'payment_failed' => 'bg-red-400 opacity-75',
      ];

      $textSize = $size ?? 'text-xs';

      $matchPhase = $phase ? strtolower((string)$phase) : 'N/A';
      $color = $phaseColor[$matchPhase] ?? 'bg-gray-400';

      return '<span class="px-2 py-1 ' . $textSize . ' font-medium rounded ' . $color . '  text-gray-800">' . ucfirst($matchPhase) . '</span>';
   }
   // -----------------------------------------------

   public static function notifyColor($phase, $size = null)
   {
      $phaseColor = [
         'login' => 'bg-indigo-300 opacity-75',
         'order' => 'bg-indigo-300 opacity-75',
         'system' => 'bg-emerald-300 opacity-75',
         'message' => 'bg-violet-300 opacity-75',
         'payment' => 'bg-blue-300 opacity-75',
         'update' => 'bg-green-300 opacity-75',
         // -------------------------
         'info' => 'bg-cyan-400 opacity-75',
         'alert' => 'bg-yellow-400 opacity-75',
         'warning' => 'bg-orange-400 opacity-75',
         'critical' => 'bg-red-400 opacity-75',
      ];

      $textSize = $size ?? 'text-xs';

      $matchPhase = strtolower($phase);
      $color = $phaseColor[$matchPhase] ?? 'bg-gray-400';

      return '<span class="px-2 py-1 ' . $textSize . ' font-medium rounded ' . $color . '  text-gray-800">' . ucfirst($matchPhase) . '</span>';
   }
}
// -----------------------------------------------

/*
USE CASE-1: success + error
   try {
      return DatatableHelper::handleDatatableSuccess($draw, $recordsTotal, $recordsFiltered, $data);
   } catch (Throwable $e) {
      return DatatableHelper::handleDatatableError($request, $e, true, "Workmen jobs datatable error");
   }

USE CASE-2: action
   $row->actions = DatatableHelper::datatableAction($row, true, 'backend_issue_toggle', 'backend_issue_delete');

   // here, true = there'll be a toggle button

USE CASE-3: user
   $row->merchant_info = DatatableHelper::userBlock($row->productRelatingBackTo_Merchant);

USE CASE-4: detail
   $row->detail = DatatableHelper::detailBlock($content, $hasUid = true, $content_uid, $hasImage = true, $image);

USE CASE-5: phase
   $row->role = DatatableHelper::phaseColor($role);
*/