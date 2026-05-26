<?php

namespace App\Http\Requests\Workflow;

use App\Domain\Bars\Enums\PointsToShowEnums;
use App\Models\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class DealValidateRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			'id' => 'nullable|integer|exists:bar_deals,id',

			'bar_id' => 'required|integer|exists:bars,id',
			'event_id' => 'nullable|integer|exists:bar_events,id',

			'name' => 'required|string|max:255',

			'point_cost' => 'required|integer|min:0',

			'to_show' => ['nullable', new Enum(PointsToShowEnums::class)],

			'expiry' => 'nullable|date',

			'status' => 'nullable|boolean|in:0,1',
		];
	}
}
