<?php

namespace App\Http\Requests\Workflow;

use App\Domain\Bars\Enums\WeekdayEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class EventValidateRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			'id' => 'nullable|integer|exists:bar_events,id',

			'bar_id' => 'required|integer|exists:bars,id',
			
			'event_day' => ['nullable', new Enum(WeekdayEnums::class)],

			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
			'points_giveaway' => 'required|integer|min:0',
			
			'image' => 'nullable|array|max:10',
			'image.*.id' => 'nullable|integer|exists:bar_event_galleries,id',
			'image.*.file' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:10480',
			'image.*.remove_image' => 'required|boolean',

			'expiry' => 'nullable|date',

			'status' => 'nullable|boolean|in:0,1',
		];
	}
}
