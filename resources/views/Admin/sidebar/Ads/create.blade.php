<x-app-layout>

	@php
		$breadCrumbs = [
			['label' => 'Create Ads'],
			['label' => 'Ads List', 'url' => 'backend_ads_index', 'icon' => 'microphone'],
		];
	 @endphp

	<x-indicators.breadcrumb :crumbs="$breadCrumbs" />

	<!-- Page Header -->
	<x-indicators.page_header_widget pageName="Create Ads" />

	<!-- Alert -->
	<x-indicators.alert-component message="Form Submission" />

	<div class="px-6 py-2">

		<section class="displayArea mx-auto my-5 rounded-lg bg-white p-5 shadow-md">

			<form method="POST" action="{{ route('backend_ads_store') }}" enctype="multipart/form-data">
				@csrf

				<!-- Hidden flag -->
				<input type="hidden" name="flag" value="create">

				<!-- ================= Title ================= -->
				<div class="mb-5">
					<label class="inputLabelMedium">Ad Title <span class="text-red-500">*</span></label>
					<input type="text" name="title" class="normalInput" placeholder="Enter ad title" required>
				</div>

				<!-- ================= Description ================= -->
				<div class="mb-5">
					<label class="inputLabelMedium">Description</label>
					<textarea class="summernote" name="description" id="description"></textarea>
				</div>

				<!-- ================= Ad Link ================= -->
				<div class="mb-5">
					<label class="inputLabelMedium">
						Ad Link
						<span class="grayLabel">(Optional)</span>
					</label>

					<input type="url" name="ad_link" class="normalInput" placeholder="https://example.com">
				</div>

				<!-- ================= Schedule Day & Status ================= -->
				<div class="grid grid-cols-2 gap-4 mb-5">

					<div>
						<label class="inputLabelMedium">Schedule Day <span class="text-red-500">*</span></label>

						<select name="schedule_day" class="dropdownSelectInputs">
							<option value="">Select Day</option>

							@foreach(\App\Domain\Bars\Enums\WeekdayEnums::cases() as $day)
								<option value="{{ $day->value }}">
									{{ $day->label() }}
								</option>
							@endforeach
						</select>
					</div>

					<div>
						<label class="inputLabelMedium">Status</label>

						<select name="status" class="dropdownSelectInputs">
							<option value="1">Active</option>
							<option value="0">Inactive</option>
						</select>
					</div>

				</div>

				<!-- ================= Image ================= -->
				<div class="mb-5">
					<label class="inputLabelMedium">
						Ad Image
						<span class="text-red-500">*</span>
						<span class="grayLabel">(Max 5MB)</span>
					</label>

					<input type="file" name="image" class="dropify" data-height="180">
					<input type="hidden" name="remove_image" value="0">
				</div>

				<button type="submit" class="saveButton mt-4">Create Ad</button>
			</form>

		</section>

	</div>

</x-app-layout>