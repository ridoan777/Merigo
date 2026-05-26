<?php

namespace App\Http\Requests\Workflow;

use App\Domain\Bars\Enums\WeekdayEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AdValidateRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			'id' => 'nullable|integer|exists:ads,id',

			'schedule_day' => ['nullable', new Enum(WeekdayEnums::class)],

			'title' => 'required|string|max:255',
			'description' => 'nullable|string',
			'ad_link' => 'nullable|url|max:255',
			'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:10480',
			'remove_image' => 'nullable|boolean',

			'status' => 'nullable|boolean|in:0,1',
		];
	}
}
