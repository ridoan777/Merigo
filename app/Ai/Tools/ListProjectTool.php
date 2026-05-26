<?php

namespace App\Ai\Tools;

use App\Models\Workflows\Projects\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListProjectTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List all the projects available in this system.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $manager = trim((string) ($request['project'] ?? ''));

        $projects = Project::with(['projectRelatingBackTo_User:id,name,avatar'])
            ->select(['id', 'project_uid', 'project_manager_id', 'title', 'phase', 'progress', 'location', 'start_date', 'target_date', 'description', 'image', 'video', 'status', 'created_at'])
            ->when($manager !== '', function ($query) use ($manager) {
                $query->whereHas('projectRelatingBackTo_User', function ($query) use ($manager) {
                    $query->where('name', 'like', '%' . $manager . '%');
                });
            })
            ->latest('id')
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'project_uid' => $project->project_uid,
                    'title' => $project->title,
                    'phase' => $project->phase,
                    'image' => $project->image,
                    'project_image_url' => $project->project_image_url,
                    'status' => $project->status,
                    'manager_details' => [
                        'id' => $project->projectRelatingBackTo_User?->id,
                        'name' => $project->projectRelatingBackTo_User?->name,
                        'avatar_url' => $project->projectRelatingBackTo_User?->avatar_url,
                    ],
                ];
            })
            ->values();

        // return $projects;
        return $projects->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->required()->description('Project manager name. Use empty string to list all projects.'),
        ];
    }
}
