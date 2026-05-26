<?php

namespace App\Ai\Tools;

use App\Models\Workflows\Meals\MealPreference;
use App\Models\Workflows\Projects\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class MealPreferenceTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        Log::info('Tool description is hit', []);
        return 'Collects and structures user fitness, nutrition, and health preferences for meal generation. Use this tool before generating any meal plan.';
    }

    /**
     * Execute the tool.
     */
    // public function handle(Request $request): Stringable|string
    public function handle(Request $request): Stringable|string
    {
        $USER = auth()->user();
        
        Log::info('Tool handle is hit', ['request' => $request, 'user' => $USER->id]);

        $TARGET_PREFERENCE = MealPreference::userId($USER->id)->latest()->first();

        if (!$TARGET_PREFERENCE) {
            return json_encode(['error' => 'No meal preference found for this user.'], JSON_UNESCAPED_SLASHES);
        }

        $data = [
            'preference_id' => $TARGET_PREFERENCE->id,

            'duration' => (int)($TARGET_PREFERENCE->duration ?? 3),
            'goal' => (string)($TARGET_PREFERENCE->goal ?? 'active_fit'),
            'gender' => (string)($TARGET_PREFERENCE->gender ?? $USER->gender ?? 'male'),
            'age' => (int)($TARGET_PREFERENCE->age ?? 18),

            'body' => [
                'weight_kg' => (float)($TARGET_PREFERENCE->weight_kg ?? 80),
                'height_cm' => (float)($TARGET_PREFERENCE->height_cm ?? 175),
            ],

            'activity' => [
                'workout_days' => (int)($TARGET_PREFERENCE->workout ?? 0),
                'preferred_exercise' => array_values($TARGET_PREFERENCE->prefer_exercise ?? ['none']),
                'sleep_range' => (string)($TARGET_PREFERENCE->sleep ?? '6-7'),
            ],

            'nutrition_preferences' => array_values($TARGET_PREFERENCE->nutrition ?? ['moderate_protein', 'moderate_carb', 'moderate_fiber', 'moderate_hydration']),

            'medical_conditions' => array_values($TARGET_PREFERENCE->medical ?? ['none']),
        ];

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // return $data;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        Log::info('Tool schema is hit', []);
        // return [
        //     'type' => 'object',
        //     'properties' => [
        //         'duration' => $schema->integer()->description('Duration in days'),
        //         'goal' => $schema->string()->description('Fitness goal'),
        //         'gender' => $schema->string(),
        //         'age' => $schema->integer(),

        //         'weight_kg' => $schema->number(),
        //         'height_cm' => $schema->number(),

        //         'workout' => $schema->integer()->description('Workout days per week'),

        //         'prefer_exercise' => $schema->array()->description('Exercise types')->items($schema->string()),
        //         'sleep' => $schema->string()->description('Sleep duration'),

        //         'nutrition' => $schema->array()->description('Nutrition preferences')->items($schema->string()),
        //         'medical' => $schema->array()->description('Medical conditions')->items($schema->string()),
        //     ],
        //     'required'   [],
        // ];
        return [];
    }
}
