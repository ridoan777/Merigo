<?php

namespace App\Helpers\Ui;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ConvertDynamicTimezone
{
   public static function handle()
   {
      $tz = config('settings.display_timezone');
      $timezoneOffset = Carbon::now($tz)->format('P');
      return [
         'zone' => $tz ?? null,
         'offset' => $timezoneOffset ?? null,
      ];
   }
}

/*
USE CASE-1:
   $zones = \App\Helpers\Profiles\ConvertDynamicTimezone::handle()
   $zones['zone']
USER CASE-2:
   $row->created_at_formatted = $row->created_at ? Carbon::createFromFormat('Y-m-d H:i:s', $row->getRawOriginal('created_at'), 'UTC')->timezone($zones['zone'])->format('d M Y, H:i') . ' (UTC' . $zones['offset'] . ')' : 'N/A';
*/