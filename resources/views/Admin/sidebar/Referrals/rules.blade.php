<div class="px-6 flex justify-start">
	<button type="button" data-modal-target="hs-form-modal" data-modal-toggle="hs-form-modal"
		class="darkButton">{!! \App\Helpers\IconPack::gear(['class' => 'iconPackItem w-3 h-3 ']) !!} Set Rules
	</button>
</div>
<!--  -->
<x-ui_items.modals.show-modal id="hs-form-modal" cancelText="Cancel">
	<x-indicators.page_header_widget pageName="Referral Rules" />

	<div class="px-6 py-2">

		<section class="displayArea mx-auto my-5 rounded-lg bg-white dark:bg-neutral-700 p-5 shadow-md dark:shadow">

			<form method="POST" action="{{ route('backend_referral_store') }}" enctype="multipart/form-data">
				@csrf

				<!-- Hidden flag -->
				<input type="hidden" name="id" value="{{ $rule?->id ?? null }}">

				<!-- ------------------ Credit Amount ------------------ -->
				<div class="mb-5">
					<label class="inputLabelMedium">Credit Amount<span class="text-red-500">*</span>
						<span class="grayLabel"> (Merigo Points users will earn on each referral)</span>
					</label>
					<input type="number" name="credit_amount" value="{{ $rule?->credit_amount }}" class="normalInput" placeholder="Integers only" required>
				</div>

				<!-- ------------------ Minimum Purchase ------------------ -->
				<div class="mb-5">
					<label class="inputLabelMedium">Daily Credit Limit<span class="text-red-500">*</span>
						<span class="grayLabel"> (How much a user can earn Merigo Points by referring in a day)</span>
					</label>
					<input type="number" step="0.01" name="credit_limit" value="{{ $rule?->credit_limit }}" class="normalInput" placeholder="0.00">
				</div>

				<!-- ------------------ Exchange Limit ------------------ -->
				<div class="mb-5">
					<label class="inputLabelMedium">Exchange Limit<span class="text-red-500">*</span>
						<span class="grayLabel"> (Maximum Merigo Points a user can exchange in a day in the merchandise store)</span>
					</label>
					<input type="number" name="exchange_limit" value="{{ $rule?->exchange_limit }}" class="normalInput" placeholder="0">
				</div>

				<button type="submit" class="saveButton mt-4">Update Rules</button>

			</form>

		</section>

	</div>
</x-ui_items.modals.show-modal>