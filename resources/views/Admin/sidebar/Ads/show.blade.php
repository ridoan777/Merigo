<x-app-layout>

    @php
        $breadCrumbs = [
            ['label' => 'Ads List', 'url' => 'backend_ads_index', 'icon' => 'microphone'],
            ['label' => 'Edit Ad'],
        ];
    @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />

    <x-indicators.page_header_widget pageName="Edit Ad" />

    <x-indicators.alert-component message="Form Submission" />

    <div class="px-6 py-2">

        <section class="displayArea mx-auto my-5 rounded-lg bg-white p-5 shadow-md">

            <form method="POST" action="{{ route('backend_ads_store', $singleAd->id) }}" enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="flag" value="update">
                <input type="hidden" name="id" value="{{ $singleAd->id }}">

                <!-- ================= UID ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Ad UID</label>

                    <input type="text" class="normalInput bg-gray-100 cursor-not-allowed"
                        value="{{ $singleAd->ad_uid }}" readonly>
                </div>

                <!-- ================= Title ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">
                        Ad Title
                        <span class="text-red-500">*</span>
                    </label>

                    <input type="text" name="title" class="normalInput" value="{{ old('title', $singleAd->title) }}"
                        placeholder="Enter ad title" required>
                </div>

                <!-- ================= Description ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Description</label>

                    <textarea class="summernote" name="description"
                        id="description">{!! old('description', $singleAd->description) !!}</textarea>
                </div>

                <!-- ================= Ad Link ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">
                        Ad Link
                        <span class="grayLabel">(Optional)</span>
                    </label>

                    <input type="url" name="ad_link" class="normalInput"
                        value="{{ old('ad_link', $singleAd->ad_link) }}" placeholder="https://example.com">
                </div>

                <!-- ================= Schedule Day & Status ================= -->
                <div class="grid grid-cols-2 gap-4 mb-5">

                    <div>
                        <label class="inputLabelMedium">
                            Schedule Day
                            <span class="text-red-500">*</span>
                        </label>

                        <select name="schedule_day" class="dropdownSelectInputs">

                            <option value="">Select Day</option>

                            @foreach(\App\Domain\Bars\Enums\WeekdayEnums::cases() as $day)

                                <option value="{{ $day->value }}" {{ old('schedule_day', $singleAd->schedule_day) == $day->value ? 'selected' : '' }}>

                                    {{ $day->label() }}

                                </option>

                            @endforeach

                        </select>
                    </div>

                    <div>
                        <label class="inputLabelMedium">Status</label>

                        <select name="status" class="dropdownSelectInputs">

                            <option value="1" {{ old('status', $singleAd->status) == 1 ? 'selected' : '' }}>
                                Active
                            </option>

                            <option value="0" {{ old('status', $singleAd->status) == 0 ? 'selected' : '' }}>
                                Inactive
                            </option>

                        </select>
                    </div>

                </div>

                <!-- ================= Image Upload ================= -->
                <div class="mb-5">

                    <label class="inputLabelMedium">
                        Replace Image
                        <span class="grayLabel">(Optional)</span>
                    </label>

                    <input type="file" id="imageDropify" name="image" class="dropify" data-height="150" @if ($singleAd?->image) data-default-file="{{ Storage::url($singleAd->image) }}" @endif />

                    <input type="hidden" name="remove_image" id="remove_image" value="0">
                </div>

                <!-- ================= Created At ================= -->
                <div class="mb-5">

                    <label class="inputLabelMedium">Created At</label>

                    <input type="text" class="normalInput bg-gray-100 cursor-not-allowed"
                        value="{{ $singleAd->created_at?->format('d M Y, h:i A') }}" readonly>
                        
                </div>

                <!-- ================= Submit ================= -->
                    <button type="submit" class="saveButton">
                        Update Ad
                    </button>

                </div>

            </form>

        </section>

    </div>

</x-app-layout>