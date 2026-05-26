<?php

namespace App\Http\Requests\Billings;

use Illuminate\Foundation\Http\FormRequest;

class PointTransactionsRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			'id' => 'nullable|integer|exists:wallet_point_transactions,id',

			'wallet_morphed_id' => 'required|integer',
			'wallet_morphed_type' => 'required|string',
			'user_id' => 'required|integer|exists:users,id',
			'transaction_type' => 'required|string|in:earning,spending',
			'amount' => 'required|integer|min:1',
			'phase' => 'nullable|string|max:255',
			'note' => 'nullable|string|max:1000',
			'status' => 'nullable|boolean|in:0,1',
		];
	}
}
