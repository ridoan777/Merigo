<?php

namespace App\Ai\Agents;

use App\Ai\Tools\MealPreferenceTool;
use App\Helpers\Errors\LoggerAccess;
use App\Models\Workflows\Meals\MealPreference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\{Agent, Conversational, HasStructuredOutput, HasTools, Tool};
use Laravel\Ai\Attributes\{MaxSteps, MaxTokens, Model, Provider, Temperature, Timeout};
use Laravel\Ai\Attributes\{UseCheapestModel, UseSmartestModel};
use Laravel\Ai\Messages\Message;
use App\Models\Users\User;
use App\Models\Workflows\Meals\MealPlan;
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

class MealSwapAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public ?int $duration;
    public ?int $day;
    public ?string $period;

    public function __construct(
        public User $user,
        public ?MealPreference $TARGET_PREFERENCE,
        public ?MealPlan $TARGET_MEAL,
    ) {
        $this->day = $this->TARGET_MEAL?->day ?? 1;
        $this->period = $this->TARGET_MEAL?->period ?? "breakfast";
        LoggerAccess::showLog(['local', 'staging'], 'info', "MealSwapAgent is hit", []);
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        LoggerAccess::showLog(['local', 'staging'], 'info', "Agent instructions is hit", $this->user->id);
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
        return [];
    }

    /**
     * Get the agent's structured output schema definition.
     */

    public function schema(JsonSchema $schema): array
    {
        $days = [];

        $days["day-{$this->day}"] = $schema->object(fn($schema) => [
            'day_number' => $schema->integer()->description("must match the day-{$this->day}")->required(),

            'meals' => $schema->array()->items(
                $schema->object(fn($schema) => [
                    'period' => $schema->string()->description($this->period)->required(),
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
            'prefer_exercise' => (array) $this->TARGET_PREFERENCE?->prefer_exercise,
            'sleep' => $this->TARGET_PREFERENCE?->sleep,
            'nutrition' => (array) $this->TARGET_PREFERENCE?->nutrition,
            'medical' => (array) $this->TARGET_PREFERENCE?->medical,
        ], JSON_UNESCAPED_UNICODE);
        // ------------------ STREAMLINING USER PREFERENCE ------------------


        // ------------------ FINAL PROMPT ------------------
        return "
            You are a professional gym trainer and nutrition expert.

            Your task:
            Generate a new personalized single meal swapping this meal {$this->TARGET_MEAL} for day {$this->day} {$this->period} based on the user's stored preferences.

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
            - You MUST explicitly generate this SINGLE MEAL maitaining the structure of each column as they are for day-{$this->day} & period {$this->period}.
            - Do NOT generate any other days.
            - Do NOT generate any other period.
            - DO NOT return partial results
            
            - RESPECT the {$nutrition} options from the {$preference} and GENERATE meals BASED ON that.
            - Use ONLY the nutrition preferences provided in the JSON. Do NOT introduce other nutrition patterns.
            - AVOID suggesting the food that affects the medical condition defined at {$medical}
            - The food must be based on NORTH-AMERICA

            ====================================
            REQUIRED JSON STRUCTURE (EXACT)
            ====================================

            {
                \"day-{$this->day}\": {
                    \"day_number\": {$this->day},
                    \"meals\": [
                        {
                            \"period\": \"{$this->period}\",
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
                    ]
                },
            }

            ====================================
            OUTPUT STRUCTURE RULES
            ====================================

            - FOLLOW the same structure
            - Do NOT include placeholders like \"...\"

            ====================================
            FOOD NAMING RULES
            ====================================

            - MUST be lowercase snake_case
            - MAY include quantity prefix:
                - 2_pc_
                - 1_bowl_
            - MAY include cooking method:
                - grilled_
                - steamed_
                - boiled_
                - cooked_
                - seasoned_
            - MAY include preparation style:
                - pan_fried_salmon_with_seasoning
            - Grocery list has item: food/cooking item name, qty_type is which unit will be used, amount is based on the qty_type. If qty_type = ltr, then it means 1.5 ltr
            - NORTH-AMERICAN origins
            - Make a different food other than {$this->TARGET_MEAL->title}


            ====================================
            IMPORTANT
            ====================================

            - Amount MUST be integer
            - Make food VARSATILE
            - The PRIMARY GOAL of this meal plan is to {$goal}
            - DO NOT rename keys
            - INVALID if structure deviates
            - Every ingredient MUST exist in grocery_list. Do not exclude spices, salt, peppers, etc.
            - DO NOT put any units or numbers in ingredients. Number, amount and units are for \"grocery_list\" only.
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
