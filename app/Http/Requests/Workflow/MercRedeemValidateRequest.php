<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class MercRedeemValidateRequest extends FormRequest
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
			'id' => 'nullable|integer|exists:merc_requests,id',
			'merc_id' => 'required|integer|exists:merchandises,id',

			'receiver_phone' => 'required|string|max:20',
			'receiver_address' => 'required|string',
			'note' => 'nullable|string',

			'status' => 'nullable|integer|in:0,1',
		];
	}
}
