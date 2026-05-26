<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class MercValidateRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		// dd(request()->all());
		return [
			'id' => 'nullable|integer|exists:merchandises,id',

			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
			'points_cost' => 'required|integer|min:0',
			
			'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:10480',
			'remove_image' => 'nullable|boolean',

			'status' => 'nullable|boolean|in:0,1',
		];
	}
}
