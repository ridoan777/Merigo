
<!-- Page Header -->
<x-indicators.page_header_widget pageName="Deal: ({{ $deal?->name ?? 'N/A' }})" />

<div class="px-6 py-2">

    <section class="displayArea mx-auto my-5 rounded-lg bg-white dark:bg-neutral-700 p-5 shadow-md">

        <form method="POST" action="{{ route('backend_deal_store') }}" enctype="multipart/form-data">
            @csrf

            <!-- Hidden flag and ID -->
            <input type="hidden" name="flag" value="update">
            <input type="hidden" name="id" value="{{ $deal?->id }}">

            <!-- (Read-only reference) -->
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div class="mb-5">
                    <label class="inputLabelMedium">ID</label>
                    <input type="text" value="{{ $deal?->id }}" class="normalInput " readonly disabled>
                </div>
                <div class="mb-5">
                    <label class="inputLabelMedium">DB UID</label>
                    <input type="text" value="{{ $deal?->deal_uid }}" class="normalInput " readonly disabled>
                </div>
            </div>

            <!-- Bar and Event selection -->
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="inputLabelMedium">
                        Bar <span class="text-red-500">*</span>
                    </label>
                    <select name="bar_id" class="dropdownSelectInputs" required>
                        <option value="">Select Bar</option>
                        @foreach($bars as $bar)
                            <option value="{{ $bar->id }}" {{ (int)$deal?->bar_id === (int)$bar->id ? 'selected' : '' }}>
                                [{{ $bar->id }}] {{ $bar->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="inputLabelMedium">
                        Event <span class="grayLabel">(Optional)</span>
                    </label>
                    <select name="event_id" class="dropdownSelectInputs">
                        <option value="">Select Event</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" {{ (int)$deal?->event_id === (int)$event->id ? 'selected' : '' }}>
                                [{{ $event->id }}] {{ $event->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Deal Name -->
            <div class="mb-5">
                <label class="inputLabelMedium">
                    Deal Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" class="normalInput" value="{{ $deal?->name }}" placeholder="Enter deal name" required>
            </div>

            <!-- Costs and Visibility -->
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="inputLabelMedium">Point Cost <span class="text-red-500">*</span></label>
                    <input type="number" name="point_cost" class="normalInput" value="{{ $deal?->point_cost }}" placeholder="0" min="0" required>
                </div>
                <div>
                    <label class="inputLabelMedium">Show Visibility</label>
                    <select name="to_show" class="dropdownSelectInputs">
                        @foreach(\App\Domain\Bars\Enums\PointsToShowEnums::cases() as $visibility)
                            <option value="{{ $visibility->value }}" {{ $deal?->to_show === $visibility ? 'selected' : '' }}>
                                {{ $visibility->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Expiry and Status -->
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="inputLabelMedium">Expiry Date</label>
                    <input type="datetime-local" name="expiry" class="normalInput" value="{{ $deal?->expiry?->format('Y-m-d\TH:i') }}">
                </div>
                <div>
                    <label class="inputLabelMedium">Status</label>
                    <select name="status" class="dropdownSelectInputs">
                        <option value="1" {{ $deal?->status == 1 ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ $deal?->status == 0 ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button type="submit" class="showButton">Update Deal</button>
            </div>

        </form>
    </section>
</div>
