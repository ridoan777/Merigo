<x-app-layout>

    @php
        $breadCrumbs = [
            ['label' => 'All goods', 'url' => 'backend_merchandise_index', 'icon' => 'cubic'],
            ['label' => 'Single Good'],
            ['label' => 'Good Requests', 'url' => 'backend_merc_requests_index', 'icon' => 'dollar'],
        ];
    @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />

    <!-- Start Main Widgets -->
    <x-indicators.page_header_widget :pageName="$merchandise->name" />
    <!-- End Main Widgets -->


    <!-- Alert Component -->
    <x-indicators.alert-component message="Form Submission" />
    <!-- Alert Component -->

    <!-- -------------------------------- APP AREA Starts -------------------------------- -->
    <div class="px-6 py-2">

        <section class="displayArea mx-auto my-5 rounded-lg bg-white dark:bg-neutral-700 p-5 shadow-md dark:shadow">

            <form method="POST" action="{{ route('backend_merchandise_store') }}" enctype="multipart/form-data">
                @csrf

                <!-- ------------------ HIDDEN ------------------ -->
                <input type="hidden" name="id" value="{{ $merchandise->id }}">

                <!-- ------------------ Name ------------------ -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Product/good Name<span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ $merchandise->name }}" class="normalInput" placeholder="255 characters only" required>
                </div>

                <!-- ------------------ Description ------------------ -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Description<span class="grayLabel"> (1000 characters only)</span>
                    </label>
                    <textarea class="summernote" name="description" id="description">{!! $merchandise->description !!}</textarea>
                </div>

                <!-- ------------------ Points ------------------ -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Points need to redeem this<span class="text-red-500">*</span>
                        <span class="grayLabel"> (Positive Integers only)</span>
                    </label>
                    <input type="number" step="1" name="points_cost" value="{{ $merchandise->points_cost }}" class="normalInput" placeholder="0.00">
                </div>

                <!-- ------------------ Image ------------------ -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Image<span class="text-red-500">*</span>
                        <span class="grayLabel"> (Maximum 5MB)</span>
                    </label>

                    <input type="file" id="imageDropify" name="image" class="dropify" data-height="150" @if ($merchandise?->image) data-default-file="{{ Storage::url($merchandise->image) }}" @endif />
                    <input type="hidden" name="remove_image" id="remove_image" value="0">
                </div>

                <button type="submit" class="saveButton mt-4">Update</button>

            </form>

        </section>

    </div>
    <!-- -------------------------------- APP AREA Starts -------------------------------- -->
</x-app-layout>