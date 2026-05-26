<x-app-layout>

	@php
		$breadCrumbs = [
			['label' => 'Create Event'],
			['label' => 'Event List', 'url' => 'backend_event_index', 'icon' => 'ribbon'],
		];
	 @endphp

	<x-indicators.breadcrumb :crumbs="$breadCrumbs" />

	<!-- Page Header -->
	<x-indicators.page_header_widget pageName="Event: Create" />

	<!-- Alert -->
	<x-indicators.alert-component message="Form Submission" />

	<div class="px-6 py-2">

		<section class="displayArea mx-auto my-5 rounded-lg bg-white p-5 shadow-md">

			<form method="POST" action="{{ route('backend_event_store') }}" enctype="multipart/form-data">
				@csrf

				<!-- Hidden flag -->
				<input type="hidden" name="flag" value="create">

				<!-- ================= Bar Selection ================= -->
				<div class="mb-5">
					<label class="inputLabelMedium">
						Bar <span class="text-red-500">*</span>
					</label>

					<select name="bar_id" class="dropdownSelectInputs" required>
						<option value="">Select Bar</option>
						@foreach($bars as $bar)
							<option value="{{ $bar->id }}">
								[{{ $bar?->id }}] | {{ $bar?->name }} | {{ $bar?->address }} {{ $bar?->city }}
							</option>
						@endforeach
					</select>
				</div>

				<!-- ================= Event Name ================= -->
				<div class="mb-5">
					<label class="inputLabelMedium">
						Event Name <span class="text-red-500">*</span>
					</label>
					<input type="text" name="name" class="normalInput" placeholder="Enter event name" required>
				</div>

				<!-- ================= Description ================= -->
				<div class="mb-5">
					<label class="inputLabelMedium">Description</label>
					<textarea class="summernote" name="description" id="description"></textarea>
				</div>

				<!-- ================= Schedule & Day ================= -->
				<div class="grid grid-cols-2 gap-4 mb-5">
					<div>
						<label class="inputLabelMedium">Event Day</label>
						<select name="event_day" class="dropdownSelectInputs">
							<option value="">Select Day</option>
							@foreach(\App\Domain\Bars\Enums\WeekdayEnums::cases() as $day)
								<option value="{{ $day->value }}">{{ $day?->label() }}</option>
							@endforeach
						</select>
					</div>
					<div>
						<label class="inputLabelMedium">Expiry Date <span class="grayLabel">(Optional)</span></label>
						<input type="datetime-local" name="expiry" class="normalInput" required>
					</div>
				</div>

				<!-- ================= Points ================= -->
				<div class="grid grid-cols-2 gap-4 mb-5">
					<div>
						<label class="inputLabelMedium">Points Giveaway <span class="text-red-500">*</span></label>
						<input type="number" name="points_giveaway" class="normalInput" placeholder="0" min="0" required>
					</div>
					<div>
						<label class="inputLabelMedium">Status</label>
						<select name="status" class="dropdownSelectInputs">
							<option value="1">Active</option>
							<option value="0">Inactive</option>
						</select>
					</div>
				</div>

				<!-- ================= Gallery Image ================= -->
				<div id="galleryContainer" class="my-5 grid grid-cols-4 gap-4">
					<div class="gallery-item mb-4">
						<label class="inputLabelMedium">Event Gallery Image</label>
						<input type="file" name="image[0][file]" class="dropify" data-height="120" />
						<input type="hidden" name="image[0][remove_image]" value="0" class="remove-image-input" />
					</div>
				</div>
				<div class="flex justify-end">
					<button type="button" id="addImageBtn" class="px-4 py-2 text-xs bg-violet-600 text-white rounded">+ Add
						More Image</button>
				</div>


				<button type="submit" class="saveButton mt-4">Create Event</button>

			</form>

		</section>

	</div>

</x-app-layout>

<script>
	document.addEventListener('DOMContentLoaded', function () {

		const container = document.getElementById('galleryContainer');
		const addBtn = document.getElementById('addImageBtn');

		let imageIndex = 1;

		addBtn.addEventListener('click', function () {

			const newItem = document.createElement('div');

			newItem.className = 'gallery-item mb-4 relative';

			newItem.innerHTML = `
				<div class="flex justify-between items-center px-8">
					<label class="inputLabelMedium">Add More Image</label>
					<button type="button" class="removeImageBtn text-red-500 hover:text-red-700">
						{!! \App\Helpers\IconPack::trash(['class' => 'w-5 h-5']) !!}
					</button>
				</div>

				<input type="file" name="image[${imageIndex}][file]" class="dropify" data-height="120" />

				<input type="hidden" name="image[${imageIndex}][remove_image]" value="0" class="remove-image-input" />
			`;

			container.appendChild(newItem);

			$(newItem).find('.dropify').dropify();

			newItem.querySelector('.removeImageBtn').addEventListener('click', function () {
				newItem.remove();
			});

			imageIndex++;
		});

	});
</script>