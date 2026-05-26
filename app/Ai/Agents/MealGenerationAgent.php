<?php

namespace App\Ai\Agents;

use App\Ai\Tools\MealPreferenceTool;
use App\Helpers\Errors\LoggerAccess;
use App\Models\Workflows\Meals\MealPreference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\{Agent,Conversational,HasStructuredOutput,HasTools,Tool};
use Laravel\Ai\Attributes\{MaxSteps, MaxTokens, Model, Provider, Temperature, Timeout};
use Laravel\Ai\Attributes\{UseCheapestModel, UseSmartestModel};
use Laravel\Ai\Messages\Message;
use App\Models\Users\User;
use Laravel\Ai\Promptable;

use Stringable;

#[Provider('openai')]
#[Model('gpt-4.1')]
// #[Model('gpt-4.1-mini')]
#[MaxSteps(5)]
#[MaxTokens(32000)]
#[Temperature(0.4)]
#[Timeout(240)]

// #[UseCheapestModel]
// #[UseSmartestModel]

class MealGenerationAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public ?int $duration;

    public function __construct(
        public User $user,
        public ?MealPreference $TARGET_PREFERENCE,
        public ?int $TARGET_DURATION = null,
    ) {
        $this->duration = $this?->TARGET_DURATION ? $this?->TARGET_DURATION : ($this->TARGET_PREFERENCE?->duration ?? 3);

        LoggerAccess::showLog(['local','staging'], 'info', "Regular Agent constructor is hit. Meal duration=", $this->duration);
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        LoggerAccess::showLog(['local','staging'], 'info', "Agent instructions is hit", $this->user->id);
        return $this->buildPrompt();
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        LoggerAccess::showLog(['local','staging'], 'info', "Agent tools is hit", $this->user->id);
        return [
            // new MealPreferenceTool(),
        ];
    }

    /**
     * Get the agent's structured output schema definition.
     */

    public function schema(JsonSchema $schema): array
    {
        $days = [];

        for ($i = 1; $i <= $this->duration; $i++) {
            $days["day-{$i}"] = $schema->object(fn($schema) => [
                'day_number' => $schema->integer()->description("must match the day-{$i}")->required(),

                'meals' => $schema->array()->items(
                    $schema->object(fn($schema) => [
                        'period' => $schema->string()->description('serially breakfast, lunch, dinner')->required(),
                        'title' => $schema->string()->required(),
                        'image_prompt' => $schema->string()->required(),
                        'serving_details' => $schema->string()->required(),

                        'ingredients' => $schema->array()->items($schema->string())->required(),

                        'macro_nutrients' => $schema->object(fn($schema) => [
                            'carb' => $schema->integer()->required(),
                            'fat' => $schema->integer()->required(),
                            'protein' => $schema->integer()->required(),
                            'energy' => $schema->integer()->required(),
                        ])->required(),

                        'grocery_list' => $schema->array()->items(
                            $schema->object(fn($schema) => [
                                'item' => $schema->string()->required(),
                                'qty_type' => $schema->string()->required(),
                                'amount' => $schema->integer()->required(),
                            ])
                        )->required(),
                    ])
                )->required(),
            ])->required();
        }

        return [
            'planning' => $schema->object(fn($schema) => $days)->required(),
        ];
    }
    // --------------------------------------------------

    protected function buildPrompt(): string
    {
        $nutrition = json_encode($this->TARGET_PREFERENCE?->nutrition ?? []);
        $medical = json_encode($this->TARGET_PREFERENCE?->medical ?? []);
        $goal = $this->TARGET_PREFERENCE?->goal ?? 'stay_healthy';

        // ------------------ STREAMLINING USER PREFERENCE ------------------
        $preference = json_encode([
            'duration' => $this->TARGET_PREFERENCE?->duration,
            'goal' => $this->TARGET_PREFERENCE?->goal,
            'gender' => $this->TARGET_PREFERENCE?->gender,
            'age' => $this->TARGET_PREFERENCE?->age,
            'weight_kg' => $this->TARGET_PREFERENCE?->weight_kg,
            'height_cm' => $this->TARGET_PREFERENCE?->height_cm,
            'workout' => $this->TARGET_PREFERENCE?->workout,
            'prefer_exercise' => (array)$this->TARGET_PREFERENCE?->prefer_exercise,
            'sleep' => $this->TARGET_PREFERENCE?->sleep,
            'nutrition' => (array)$this->TARGET_PREFERENCE?->nutrition,
            'medical' => (array)$this->TARGET_PREFERENCE?->medical,
        ], JSON_UNESCAPED_UNICODE);
        // ------------------ STREAMLINING USER PREFERENCE ------------------


        // ------------------ FINAL PROMPT ------------------
        return "
            You are a professional gym trainer and nutrition expert.

            Your task:
            Generate a personalized meal plan from day-1 to day-{$this->duration} based on the user's stored preferences.

            STRICT OUTPUT RULES (MANDATORY):

            - Use the following user preference JSON as the ONLY source of truth:\n\n{$preference}\n\n
            - The following JSON contains COMPLETE and VERIFIED user preferences.
            - You MUST use it as the ONLY source of truth.
            - Do NOT assume, override, or ignore any value.
            - Return ONLY a valid JSON object
            - DO NOT include explanations
            - DO NOT include markdown
            - DO NOT include text before or after JSON
            - DO NOT escape JSON (no \\n, no \\\")
            - Use double quotes only
            - \"day_number\" must match its corresponding day key (e.g., day-4 must have day_number = 4)
            - You MUST explicitly generate ALL days (the planning object ) from day-1 to day-{$this->duration} for {$this->duration} days with breakfast, lunch, dinner
            - Do NOT generate any other days
            - DO NOT return partial results
            
            - If fewer or more days are returned, the output is INVALID
            - RESPECT the {$nutrition} options from the {$preference} and GENERATE meals BASED ON that.
            - Use ONLY the nutrition preferences provided in the JSON. Do NOT introduce other nutrition patterns.
            - AVOID suggesting the food that affects the medical condition defined at {$medical}
            - The food must be based on NORTH-AMERICA

            ====================================
            REQUIRED JSON STRUCTURE (EXACT)
            ====================================

            {
                \"planning\": {
                   \"day-1\": {
                        \"day_number\": 1,
                        \"meals\": [
                            {
                                \"period\": \"breakfast\",
                                \"title\": \"string\",
                                \"image_prompt\": \"string\",
                                \"serving_details\": \"string\",
                                \"ingredients\": [\"string\", \"string\", \"string\"],
                                \"macro_nutrients\": {
                                    \"carb\": integer,
                                    \"fat\": integer,
                                    \"protein\": integer,
                                    \"energy\": integer
                                },
                                \"grocery_list\": [
                                    {
                                        \"item\": \"string\",
                                        \"qty_type\": \"gm | ml | piece\",
                                        \"amount\": integer
                                    },
                                ]
                            },
                            {
                               \"period\": \"lunch\",
                                \"title\": \"string\",
                                \"image_prompt\": \"string\",
                                \"serving_details\": \"string\",
                                \"ingredients\": [\"string\", \"string\", \"string\"],
                                \"macro_nutrients\": {
                                    \"carb\": integer,
                                    \"fat\": integer,
                                    \"protein\": integer,
                                    \"energy\": integer
                                },
                                \"grocery_list\": [
                                    {
                                        \"item\": \"string\",
                                        \"qty_type\": \"gm | ml | piece\",
                                        \"amount\": integer
                                    },
                                ]
                            },
                            {
                               \"period\": \"dinner\",
                                \"title\": \"string\",
                                \"image_prompt\": \"string\",
                                \"serving_details\": \"string\",
                                \"ingredients\": [\"string\", \"string\", \"string\"],
                                \"macro_nutrients\": {
                                    \"carb\": integer,
                                    \"fat\": integer,
                                    \"protein\": integer,
                                    \"energy\": integer
                                },
                                \"grocery_list\": [
                                    {
                                        \"item\": \"string\",
                                        \"qty_type\": \"gm | ml | piece\",
                                        \"amount\": integer
                                    }
                                ]
                            }
                        ]
                    },
                   \"day-n\": {
                        \"day_number\": n,
                        \"meals\": [
                            {
                                \"period\": \"breakfast | lunch | dinner\",
                                \"title\": \"string\",
                                \"image_prompt\": \"string\",
                                \"serving_details\": \"string\",
                                \"ingredients\": [\"string\", \"string\", \"string\"],
                                \"macro_nutrients\": {
                                    \"carb\": integer,
                                    \"fat\": integer,
                                    \"protein\": integer,
                                    \"energy\": integer
                                },
                                \"grocery_list\": [
                                    {
                                        \"item\": \"string\",
                                        \"qty_type\": \"gm | ml | piece\",
                                        \"amount\": integer
                                    },
                                    {
                                        \"item\": \"string\",
                                        \"qty_type\": \"gm | ml | piece\",
                                        \"amount\": integer
                                    },
                                ]
                            }
                        ]
                    },
                },
            }

            ====================================
            OUTPUT STRUCTURE RULES
            ====================================

            - Repeat the same structure for ALL days from day-1 to day-{$this->duration}
            - Do NOT include placeholders like \"...\"
            - Each day MUST contain exactly 3 meals: breakfast, lunch, dinner

            ====================================
            FOOD NAMING RULES
            ====================================

            - MUST be lowercase snake_case
            - MAY include quantity prefix:
                - 2_pc_egg
                - 1_bowl_white_rice
            - MAY include cooking method:
                - grilled_chicken
                - steamed_broccoli
            - MAY include preparation style:
                - pan_fried_salmon_with_seasoning
            - Grocery list has item: food/cooking item name, qty_type is which unit will be used, amount is based on the qty_type. If qty_type = ltr, then it means 1.5 ltr
            - NORTH-AMERICAN origins


            ====================================
            IMPORTANT
            ====================================

            - Amount MUST be integer
            - The PRIMARY GOAL of this meal plan is to {$goal}
            - The planning must INCLUDE breakfast, lunch, dinner for each day
            - Missing any day makes the output INVALID
            - DO NOT rename keys
            - INVALID if structure deviates
            - Every ingredient MUST exist in grocery_list. Do not exclude spices, salt, peppers, etc.
            - DO NOT put any units or numbers in ingredients. Number, amount and units are for \"grocery_list\" only.
            - Respect units according to how they are used usually. If the food says 2_pcs_of_scramble_eggs_with_peppers, use piece as units in \"grocery_list\" instead of gm or ltr.
            - Grocery list MUST include only essential items (no duplicates, no unnecessary repetition)
            - Each meal MUST include \"serving_details\" explaining how the dish is prepared and served
            - \"serving_details\" must clearly describe:
                - cooking method (grilled, boiled, baked, mixed, raw, etc.)
                - preparation style (mixed, layered, sautéed, smashed, toppings, etc.)
                - doneness if applicable (medium, well-done, rare, etc.)
                - how ingredients are combined into the final dish
                - total character limit will be between 30 to 200. Not more, not less.
            - The explanation must be beginner-friendly and practical (how a user can actually prepare it)
            - The title alone is NOT enough; serving_details MUST clarify preparation clearly
            - Some EXAMPLES of \"serving_details\" are given below: - If the suggested food is 'grilled_chicken_with_asparagus',
                - \"serving_details\": \"Grill chicken breast on medium heat for 10-12 minutes per side until fully cooked. Lightly season with salt and pepper. Serve with asparagus sautéed in olive oil for 5 minutes.\"
                    - Add 'checken_breast', 'asparagus', 'salt', 'pepper', 'olive_oil' in \"ingredients\" and \"grocery_list\" with their respective amount and units.
                - If the suggested food is 'cottage_cheese_with_blueberries', \"serving_details\": \"Serve fresh cottage cheese in a bowl topped with raw blueberries. Mix lightly before eating. No cooking required.\"
            ";
    }
}
