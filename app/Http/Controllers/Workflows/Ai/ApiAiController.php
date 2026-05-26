<?php

namespace App\Http\Controllers\Workflows\Ai;

use App\Helpers\Errors\ExceptionHandling;
use App\Http\Controllers\Controller;
// use Http;
use App\Models\System\Settings\OptionSiteSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;


class ApiAiController extends Controller
{
   public function send(Request $request)
   {
      $OPEN_AI_DB = OptionSiteSetup::where('type', 'open_ai')->pluck('value', 'name')->toArray();
      try {
         $start = microtime(true);
         $response = Http::withToken(config('services.openai.chatgpt'))
            ->timeout((int) ($OPEN_AI_DB['timeout_seconds'] ?? 120))
            ->post(
               $OPEN_AI_DB['api_endpoint'],
               [
                  'model' => $OPEN_AI_DB['ai_model'] ?? 'gpt-4.1-mini',
                  'max_output_tokens' => (int) ($OPEN_AI_DB['max_output_tokens'] ?? 9999),
                  'text' => [
                     'format' => [
                        'type' => 'json_object'
                     ]
                  ],
                  'input' => [
                     [
                        'role' => "system",
                        'content' => $OPEN_AI_DB['role_system_content'] ?? 'You are a property and transaction management virtual assistance.'
                     ],
                     [
                        'role' => 'user',
                        'content' => "
                           Return ONLY valid JSON.

                           Format:
                           {
                              \"story\": \"string\"
                           }

                           Make a 50 word story about your role in my system.
                        ",
                     ],
                  ],
               ]
            )->json();
         $end = microtime(true);
         if (isset($response['error'])) {
            return response()->json([
               'status' => false,
               'error_type' => 'ai_rate_limit',
               'message' => $response['error']['message'],
               'retry_after' => 'later',
               'code' => 409
            ], 409);
         }

         $timeSpent = round($end - $start, 3);

         $text = $response['output'][0]['content'][0]['text'] ?? null;

         $planning = json_decode($text, true);

         if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON returned by OpenAI');
         }

         $tokenUsage = $response['usage'] ?? null;

         return [
            'status' => true,
            'data' => [
               // 'response' => $response,
               'planning' => $planning,
               'tokenUsage' => $tokenUsage,
               'timeSpentSeconds' => $timeSpent,
               'ai_model' => $OPEN_AI_DB['ai_model'] ?? null,
               'key' => config('services.openai.chatgpt')
            ],
            'code' => 200
         ];
      } catch (Throwable $e) {
         return ExceptionHandling::handle('communication with AI server(s)', $e);
      }
   }
   // -----------------------------------------------

   public function send1(Request $request)
   {
      try {
         $response = Http::withHeaders(
            [
               "Content-Type" => "application/json",
               "Authorization" => "Bearer " . config('services.openai.chatgpt')
            ]
         )->post("https://api.openai.com/v1/responses", [
                  "model" => "gpt-4.1-mini",
                  "input" => $request->post('content'),
               ])->json();

         // return $response['choices'][0]['message']['content'];
         // return $response['output_texts'];
         return response()->json([
            'status' => true,
            'data' => $response["output"][0]["content"][0]["text"] ?? null,
            'code' => 200
         ]);
      } catch (Throwable $e) {

         return response()->json([
            'status' => false,
            'data' => $response ?? null,
            "error" => $e->getMessage(),
            "details" => $e->getTrace()[0] ?? null,
            "code" => 500
         ], 500);
      }
   }
   // -----------------------------------------------
}
