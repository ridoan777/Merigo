<x-app-layout>

    @php
        $breadCrumbs = [
            ['label' => 'Event List', 'url' => 'backend_event_index', 'icon' => 'ribbon'],
            ['label' => 'Edit Event'],
        ];
     @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />

    <!-- Page Header -->
    <x-indicators.page_header_widget pageName="Event: {{ $event->name }}" />

    <!-- Alert -->
    <x-indicators.alert-component message="Form Submission" />

    @php
        $creator = $event?->eventRelatingBackTo_Creator;
    @endphp
    <!--------------------------------------- APP AREA --------------------------------------->
    <div class="px-6 py-2">

        <section class="displayArea mx-auto my-5 rounded-lg bg-white dark:bg-neutral-700 p-5">
            <!-- ================= Basics ================= -->
            <div class="grid grid-cols-2 gap-4">
                <div class="mb-5">
                    <label class="inputLabelMedium">DB ID</label>
                    <input type="text" class="normalInput" value="{{ $event->id }}" readonly disabled>
                </div>
                <div class="mb-5">
                    <label class="inputLabelMedium">Event UID</label>
                    <input type="text" class="normalInput" value="{{ $event->event_uid }}" readonly disabled>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-12">
                <h3 class="col-span-3">Creator's Information</h3>
                <div class="col-span-2">
                    <div class="mb-5 flex_x_between_y_center gap-4">
                        <label class="inputLabelMedium min-w-32">Creator Name: </label>
                        <input type="text" class="normalInput" value="{{ $creator?->name ?? "N/A" }}"
                            readonly disabled>
                    </div>
                    <div class="mb-5 flex_x_between_y_center gap-4">
                        <label class="inputLabelMedium">Role: </label>
                        <input type="text" class="normalInput" value="{{ ucfirst($creator?->user_role) }}"
                            readonly disabled>
                    </div>
                </div>
                <div class="col-span-1">
                    <a href="{{ route('backend_single_user', $creator->id) }}">
                        <img src="{{ $creator?->avatar ? Storage::url($creator?->avatar) : asset('site_assets/dummies/dummy_man.webp') }}" alt="Avatar" class="w-24 h-24 rounded-lg hover:scale-105">
                    </a>
                </div>
            </div>
        </section>

        <section class="displayArea mx-auto my-5 rounded-lg bg-white dark:bg-neutral-700 p-5">

            <form method="POST" action="{{ route('backend_event_store') }}" enctype="multipart/form-data">
                @csrf

                <!-- Hidden IDs -->
                <input type="hidden" name="flag" value="update">
                <input type="hidden" name="id" value="{{ $event->id }}">

                <!-- ================= Bar Selection ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium flex">
                        Bar <span class="text-red-500">*</span>
                        <a href="{{ route('backend_bar_show', $event?->bar_id) }}" class="ml-4">
                            {!! \App\Helpers\IconPack::telegramArrow(['class' => 'iconPackItem w-6 h-6 p-1 bg-violet-500 text-gray-200 rounded-full']) !!}
                        </a>
                    </label>

                    <select name="bar_id" class="dropdownSelectInputs" required>
                        <option value="">Select Bar</option>
                        @foreach($bars as $bar)
                            <option value="{{ $bar->id }}" {{ $event->bar_id == $bar->id ? 'selected' : '' }}>
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
                    <input type="text" name="name" class="normalInput" value="{{ $event->name }}"
                        placeholder="Enter event name" required>
                </div>

                <!-- ================= Description ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Description</label>
                    <textarea class="summernote" name="description"
                        id="description">{!! $event->description !!}</textarea>
                </div>

                <!-- ================= Schedule & Day ================= -->
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="inputLabelMedium">Event Day</label>
                        <select name="event_day" class="dropdownSelectInputs">
                            <option value="">Select Day</option>
                            @foreach(\App\Domain\Bars\Enums\WeekdayEnums::cases() as $day)
                                <option value="{{ $day->value }}" {{ $event->event_day == $day->value ? 'selected' : '' }}>
                                    {{ $day?->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="inputLabelMedium">Expiry Date <span class="grayLabel">(Optional)</span></label>
                        <input type="datetime-local" name="expiry" class="normalInput"
                            value="{{ $event->expiry ? \Carbon\Carbon::parse($event->expiry)->format('Y-m-d\TH:i') : '' }}">
                    </div>
                </div>

                <!-- ================= Points ================= -->
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="inputLabelMedium">Points Giveaway <span class="text-red-500">*</span></label>
                        <input type="number" name="points_giveaway" class="normalInput"
                            value="{{ $event->points_giveaway }}" placeholder="0" min="0" required>
                    </div>
                    <div>
                        <label class="inputLabelMedium">Status</label>
                        <select name="status" class="dropdownSelectInputs">
                            <option value="1" {{ $event->status == 1 ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ $event->status == 0 ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- ================= Gallery Image ================= -->
                <div id="galleryContainer" class="my-5 grid grid-cols-4 gap-4">
                    @foreach($galleries as $index => $item)
                        <div class="gallery-item mb-4 relative" id="gallery-item-{{ $index }}">
                            <div class="flex justify-between items-center px-8">
                                <label class="inputLabelMedium">Event Gallery</label>
                                <button type="button" class="removeExistingImageBtn text-red-500 hover:text-red-700"
                                    data-index="{{ $index }}">
                                    {!! \App\Helpers\IconPack::trash(['class' => 'w-5 h-5']) !!}
                                </button>
                            </div>
                            <input type="file" name="image[{{ $index }}][file]" class="dropify" data-height="120"
                                data-default-file="{{ $item->filepath_url }}" />
                            <input type="hidden" name="image[{{ $index }}][id]" value="{{ $item->id }}">
                            <input type="hidden" name="image[{{ $index }}][remove_image]" value="0"
                                class="remove-image-input" id="remove-image-{{ $index }}" />
                        </div>
                    @endforeach

                    @if($galleries->isEmpty())
                        <div class="gallery-item mb-4">
                            <label class="inputLabelMedium">Event Gallery Image</label>
                            <input type="file" name="image[0][file]" class="dropify" data-height="120" />
                            <input type="hidden" name="image[0][remove_image]" value="0" class="remove-image-input" />
                        </div>
                    @endif
                </div>
                <div class="flex justify-end">
                    <button type="button" id="addImageBtn" class="px-4 py-2 text-xs bg-violet-600 text-white rounded">+
                        Add More Image</button>
                </div>

                <button type="submit" class="saveButton mt-4">Update Event</button>

            </form>

        </section>

    </div>
    <!--------------------------------------- APP AREA --------------------------------------->

</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const container = document.getElementById('galleryContainer');
        const addBtn = document.getElementById('addImageBtn');

        let imageIndex = {{ count($galleries) > 0 ? count($galleries) : 1
    }};

    // Handle removal of existing images
    document.querySelectorAll('.removeExistingImageBtn').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = this.getAttribute('data-index');
            const item = document.getElementById(`gallery-item-${index}`);
            item.querySelector('.remove-image-input').value = 1;
            item.style.display = 'none';
        });
    });

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