<x-app-layout>

    @php
        $breadCrumbs = [
            ['label' => 'Create Bar', 'url' => 'backend_bar_create'],
            ['label' => 'Bar List', 'url' => 'backend_bar_index'],
            ['label' => 'Single Bar'],
        ];
    @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />
    <!-- ---------------- APP AREA Starts ---------------- -->

    <!-- Start Main Widgets -->
    <x-indicators.page_header_widget pageName="Bar: {{ $bar?->name ?? null }}" />
    <!-- End Main Widgets -->

    <!-- Alert Component -->
    <x-indicators.alert-component message="Modification" />
    <!-- Alert Component -->

    @php
        $ACCORDION_1_VISIBILITY = ''; // hidden or ''
        $ACCORDION_2_VISIBILITY = 'hidden';
        $ACCORDION_3_VISIBILITY = 'hidden';
    @endphp


    <div class="hs-accordion-group">

        <x-ui_items.accordion.accordion-destroy />

        <div id="hs-destroy-heading-one"
            class="hs-accordion-to-destroy hs-accordion hs-accordion-active:border-gray-200 PrelineAccordionItemParent">

            <x-ui_items.accordion.accordion_toggle_button toggleLabel="Modify this bar" />

            <div id="hs-destroy-collapse-one"
                class="hs-accordion-content {{ $ACCORDION_1_VISIBILITY }} w-full overflow-hidden transition-[height] duration-300"
                role="region" aria-labelledby="hs-destroy-heading-one">
                <div class="p-3">
                    <!-- --------------------------------- -->
                    <section class="displayArea mx-auto my-5 rounded-lg bg-white p-4 shadow-md">

                        <form method="POST" action="{{ route('backend_bar_store') }}" enctype="multipart/form-data">

                            @csrf

                            <!-- Hidden -->
                            <input type="hidden" name="flag" value="update">
                            <input type="hidden" name="id" value="{{ $bar?->id }}">


                            <!-- Bar UID -->
                            <div class="mb-5">
                                <label class="inputLabelMedium">
                                    Bar UID <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" class="normalInput"
                                    value="{{ $bar?->bar_uid ?? null }}" readonly disabled>
                            </div>


                            <!-- Bar Admin -->
                            <div class="mb-5">
                                <label class="inputLabelMedium">Bar Admin</label>

                                <select name="bar_admin_id" class="dropdownSelectInputs">
                                    <option value="">Select User</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}"
                                            {{ ((int) $bar?->bar_admin_id) === ((int) $user->id) ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Bar Name -->
                            <div class="mb-5">
                                <label class="inputLabelMedium">Bar Name</label>
                                <input type="text" name="name" class="mediumInput" value="{{ $bar?->name }}">
                            </div>

                            <!-- Points -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="mb-5">
                                    <label class="inputLabelMedium">Points to earn</label>
                                    <input type="text" name="earning_points" class="mediumInput"
                                        value="{{ $bar?->earning_points ?? 0 }}">
                                </div>
                                <div class="mb-5">
                                    <label class="inputLabelMedium">CD Time <span class="grayLabel">(Integer for
                                            hours)</span></label>
                                    <input type="text" name="cd_time" class="mediumInput"
                                        value="{{ $bar?->cd_time ?? 0 }}">
                                </div>
                            </div>

                            <!-- Contact -->
                            <div class="mb-5">
                                <label class="inputLabelMedium">Contact</label>
                                <input type="text" name="contact" class="mediumInput" value="{{ $bar?->contact }}">
                            </div>

                            <!-- Address -->
                            <div class="mb-5">
                                <label class="inputLabelMedium">Address</label>
                                <input type="text" name="address" class="mediumInput" value="{{ $bar?->address }}">
                            </div>

                            <!-- City -->
                            <div class="mb-5">
                                <label class="inputLabelMedium">City</label>
                                <input type="text" name="city" class="mediumInput" value="{{ $bar?->city }}">
                            </div>

                            <!-- Location -->
                            <div class="grid grid-cols-2 gap-4">
                                <input type="text" name="latitude" class="mediumInput" value="{{ $bar?->latitude }}"
                                    placeholder="Latitude">

                                <input type="text" name="longitude" class="mediumInput"
                                    value="{{ $bar?->longitude }}" placeholder="Longitude">
                            </div>

                            <!-- Image -->
                            <div class="my-5">
                                <input type="file" id="imageDropify" name="image" class="dropify" data-height="150"
                                    @if ($bar?->image) data-default-file="{{ Storage::url($bar->image) }}" @endif />

                                <input type="hidden" name="remove_image" id="remove_image" value="0">
                            </div>

                            <!-- Status -->
                            <div class="mb-5">
                                <label class="inputLabelMedium">Status</label>

                                <select name="status" class="dropdownSelectInputs">
                                    <option value="1" {{ $bar?->status == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ $bar?->status == 0 ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>

                            <button type="submit" class="showButton">Update Bar</button>

                        </form>

                    </section>
                    <!-- --------------------------------- -->
                </div>
            </div>
        </div>
        <!--  -->

        <div id="hs-destroy-heading-two"
            class="hs-accordion-to-destroy hs-accordion hs-accordion-active:border-gray-200 PrelineAccordionItemParent">

            <x-ui_items.accordion.accordion_toggle_button toggleLabel="Events under this bar" />

            <div id="hs-destroy-collapse-two"
                class="hs-accordion-content {{ $ACCORDION_2_VISIBILITY }} w-full overflow-hidden transition-[height] duration-300"
                role="region" aria-labelledby="hs-destroy-heading-two">
                <div class="p-3 grid grid-cols-3 gap-4">
                    <!-- --------------------------------- -->
                    @foreach ($events as $item)
                        <a href="{{ route('backend_event_show', $item->id) }}">
                            <div
                                class="p-4 border rounded-lg bg-linear-to-br from-rose-100 to-blue-200 dark:from-rose-900/40 dark:via-slate-950 dark:to-blue-900/40 hover:scale-101">
                                <h4 class="flex justify-between">
                                    <span class="dark:text-gray-200">[{{ $item?->id }}] Event Name:
                                        {{ $item?->name ?? 'N/A' }}</span>
                                    <p class="px-2 py-1 text-sm inline dark:text-gray-800 rounded-lg bg-green-400">+
                                        {{ $item?->points_giveaway ?? 0 }} pts</p>
                                </h4>
                                <h5 class="my-2 dark:text-gray-200">Schedule Day:
                                    {{ $item?->event_day ? ucfirst($item?->event_day) : 'N/A' }}</h5>
                                <h5 class="my-2 dark:text-gray-200">Total Event Deals:
                                    {{ $item?->eventRelationWith_Deals ? $item?->eventRelationWith_Deals?->count() : 'N/A' }}
                                </h5>
                                <b class="my-2 text-gray-600 dark:text-gray-200">Expiry:
                                    {{ $item?->expiry ? $item?->expiry?->format('t-M-Y') : 'N/A' }}</b>
                                <p class="my-2 dark:text-white">
                                    Status:
                                    <span
                                        class="px-2 py-1 {{ $item?->status ? 'bg-green-200 text-green-600' : 'bg-red-200 text-red-600' }} rounded-lg">
                                        {{ $item?->status ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                            </div>
                        </a>
                    @endforeach
                    <!-- --------------------------------- -->
                </div>
            </div>
        </div>
        <!--  -->

        <div id="hs-destroy-heading-three"
            class="hs-accordion-to-destroy hs-accordion hs-accordion-active:border-gray-200 PrelineAccordionItemParent">

            <x-ui_items.accordion.accordion_toggle_button toggleLabel="Deals under this bar" />

            <div id="hs-destroy-collapse-three"
                class="hs-accordion-content {{ $ACCORDION_3_VISIBILITY }} w-full overflow-hidden transition-[height] duration-300"
                role="region" aria-labelledby="hs-destroy-heading-three">
                <div class="p-3 grid grid-cols-3 gap-4">
                    <!-- --------------------------------- -->
                    @foreach ($deals as $item)
                        <div class="p-4 border rounded-lg cursor-pointer bg-linear-to-br from-indigo-300 to-blue-300 dark:from-rose-900/40 dark:via-slate-950 dark:to-blue-900/40 hover:scale-101"
                            data-ajax-modal="true" data-modal-trigger="click-view-modal-trigger"
                            data-modal-content="viewModalContent"
                            data-view-url="{{ route('backend_deal_show', ['deal' => $item->id]) }}">
                            <h4 class="dark:text-gray-200 flex justify-between">
                                <span>Deal Name: {{ $item?->name ?? 'N/A' }}</span>
                                <p class="px-2 py-1 text-sm inline border rounded-lg bg-amber-500 text-gray-800">-
                                    {{ $item?->point_cost ?? 0 }} pts</p>
                            </h4>
                            <p class="text-gray-600 text-sm">[{{ $item?->id }}] | UID: {{ $item?->deal_uid }} | Bar: {{ $item?->bar_id ?? 'N/A' }}</p>
                            <h5 class="my-2 dark:text-gray-200">Event: {{ $item?->event_id ?? 'N/A' }}</h5>
                            <h6 class="my-2 dark:text-gray-200">To show:
                                {{ $item?->to_show ? ucfirst($item?->to_show?->value) : 'N/A' }}</h6>
                            <p class="my-2 text-sm dark:text-gray-200">Deal Expiry:
                                {{ $item?->expiry ? $item?->expiry->format('t-M-Y h:i A') : 'N/A' }}</p>
                            <div class="flex_x_between_y_center">
                                <p class="my-2 text-white">
                                    Status:
                                    <span
                                        class="px-2 py-1 {{ $item?->status ? 'bg-green-200 text-green-600' : 'bg-red-200 text-red-600' }} rounded-lg">
                                        {{ $item?->status ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                                
                                <button type="button" data-modal-target="hs-delete-modal" data-modal-toggle="hs-delete-modal"
                                data-delete-url="{{ route('backend_deal_delete', $item?->id) }}"
                                class="deleteButton">
                                    {!! \App\Helpers\IconPack::trash(['class' => 'iconPackItem w-3 h-3 text-gray-100']) !!}
                                </button>
    	
                            </div>
                        </div>
                    @endforeach
                    <!----------- SHOW-PAGE MODAL ----------->
                    <button id="click-view-modal-trigger" type="button" class="hidden" data-modal-target="click-view-modal" data-modal-toggle="click-view-modal"></button>

                    <x-ui_items.modals.show-modal id="click-view-modal" modalWidth="max-w-6xl">
                        <section id="viewModalContent"></section>
                    </x-ui_items.modals.show-modal>
                    <!----------- SHOW-PAGE MODAL ----------->
                    <!----------- DELETE MODAL ----------->
                    <x-ui_items.modals.delete-modal id="hs-delete-modal" title="Confirm Deletion" confirmText="Yes, delete it" cancelText="Cancel" message="Are you sure you want to delete? This action cannot be undone" />
                    <!----------- DELETE MODAL ----------->
                </div>
            </div>
        </div>
        <!--  -->

    </div>

</x-app-layout>
