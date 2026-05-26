<?php

namespace App\Http\Requests\Billings;

use Illuminate\Foundation\Http\FormRequest;

class BarWalletRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			'id' => 'nullable|integer|exists:wallet_bar_points,id',

			'bar_id' => 'required|integer|exists:bars,id',

			'balance' => 'nullable|integer|min:0',
			'total_earnings' => 'nullable|integer|min:0',
			'total_spent' => 'nullable|integer|min:0',

			'status' => 'nullable|boolean|in:0,1',
		];
	}
}
