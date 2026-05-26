<div class="px-6 flex justify-start">
	<button type="button" data-modal-target="hs-form-modal" data-modal-toggle="hs-form-modal"
		class="darkButton">{!! \App\Helpers\IconPack::plus(['class' => 'iconPackItem w-3 h-3 ']) !!} Create
	</button>
</div>
<!--  -->
<x-ui_items.modals.show-modal id="hs-form-modal" cancelText="Cancel">
	<x-indicators.page_header_widget pageName="Create Goods" />

	<div class="px-6 py-2">

		<section class="displayArea mx-auto my-5 rounded-lg bg-white dark:bg-neutral-700 p-5 shadow-md dark:shadow">

			<form method="POST" action="{{ route('backend_merchandise_store') }}" enctype="multipart/form-data">
				@csrf

				<!-- ------------------ Name ------------------ -->
				<div class="mb-5">
					<label class="inputLabelMedium">Product/good Name<span class="text-red-500">*</span>
					</label>
					<input type="text" name="name"  class="normalInput" placeholder="255 characters only" required>
				</div>

				<!-- ------------------ Description ------------------ -->
				<div class="mb-5">
					<label class="inputLabelMedium">Description<span class="grayLabel"> (1000 characters only)</span>
					</label>
					<textarea class="summernote" name="description" id="description"></textarea>
				</div>

				<!-- ------------------ Points ------------------ -->
				<div class="mb-5">
					<label class="inputLabelMedium">Points need to redeem this<span class="text-red-500">*</span>
						<span class="grayLabel"> (Positive Integers only)</span>
					</label>
					<input type="number" step="1" name="points_cost" class="normalInput" placeholder="0.00">
				</div>

				<!-- ------------------ Image ------------------ -->
				<div class="mb-5">
					<label class="inputLabelMedium">Image<span class="text-red-500">*</span>
						<span class="grayLabel"> (Maximum 5MB)</span>
					</label>
					<input type="file" id="imageDropify" name="image" class="dropify" data-height="150" />
					<input type="hidden" name="remove_image" id="remove_image" value="0">
				</div>

				<button type="submit" class="saveButton mt-4">Save</button>

			</form>

		</section>

	</div>
</x-ui_items.modals.show-modal>