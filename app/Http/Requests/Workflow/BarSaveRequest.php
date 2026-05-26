<?php

namespace App\Http\Requests\Workflow;

use App\Models\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarSaveRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			'id' => 'nullable|integer|exists:bars,id',

			'bar_admin_id' => [
				'integer',
				'exists:users,id',
				Rule::requiredIf(fn() => auth()->user()?->hasRoleKey('admin', 'super_admin')),
			],

			'name' => 'required|string|max:255',
			'earning_points' => 'required|integer|min:0',
			'cd_time' => 'nullable|integer|min:0',
			'contact' => 'nullable|string|max:50',
			'city' => 'nullable|string|max:100',
			'address' => 'nullable|string',
			'latitude' => 'nullable|numeric',
			'longitude' => 'nullable|numeric',
			'status' => 'nullable|boolean|in:0,1',

			'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:2048',
			'remove_image' => 'nullable|boolean',
		];
	}
}
