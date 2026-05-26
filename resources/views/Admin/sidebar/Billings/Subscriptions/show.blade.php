<x-app-layout>

    @php
        $breadCrumbs = [
            ['label' => 'Tiers', 'url' => 'backend_subscription_tiers_index', 'icon' => 'dollarSack'],
            ['label' => ($tier?->name ?? 'single tier')],
        ];
    @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />
    <!-- ---------------- APP AREA Starts ---------------- -->

    <!-- Start Main Widgets -->
    <x-indicators.page_header_widget pageName="Single Tier: {{ $tier?->name ?? null }}" />
    <!-- End Main Widgets -->


    <!-- Alert Component -->
    <x-indicators.alert-component message="Modification" />
    <!-- Alert Component -->

    @php
        $ACCORDION_1_VISIBILITY = ''; // hidden or ''
    @endphp

    <!---------------------------------------------------------------------------------------------- accordion -->
    <div class="hs-accordion-group">

        <x-ui_items.accordion.accordion-destroy />

        <div id="hs-destroy-heading-one"
            class="hs-accordion-to-destroy hs-accordion hs-accordion-active:border-gray-200 PrelineAccordionItemParent">

            <x-ui_items.accordion.accordion_toggle_button toggleLabel="Modify this project" />

            <div id="hs-destroy-collapse-one"
                class="hs-accordion-content {{ $ACCORDION_1_VISIBILITY }} w-full overflow-hidden transition-[height] duration-300"
                role="region" aria-labelledby="hs-destroy-heading-one">
                <div class="p-3">
                    <!-- --------------------- -->
                    <section
                        class="displayArea mx-auto my-5 rounded-lg border-none bg-white p-4 shadow-md shadow-gray-400 dark:bg-neutral-700 dark:shadow-none">
                        <div class="my-5">
                            <form method="POST" action="{{ route('backend_subscription_tier_store') }}"
                                class="rounded-lg p-2 dark:bg-neutral-700" enctype="multipart/form-data">
                                @csrf

                                <!--- IDENTIFIER HIDDEN INPUTS -->
                                <input type="hidden" name="flag" class="smallInput" value="update">
                                <input type="hidden" name="id" class="smallInput" value="{{ $tier?->id ?? null }}">
                                <!--- IDENTIFIER HIDDEN INPUTS -->
                                <div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="mb-5 mt-2">
                                            <label for="service_id" class="inputLabelMedium">Project/Service
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <select id="plan" name="service_id" class="dropdownSelectInputs">
                                                <option value="" selected>Choose a Project/Service</option>
                                                @foreach ($projects as $index => $value)
                                                    <option value="{{ $value->id }}" {{ $tier?->service_id === $value->id ? 'selected' : '' }}>{{ $index + 1 }}.
                                                        {{ $value->title }} |
                                                        Manager:{{ $value->projectRelatingBackTo_User->name ?? 'No Manager Assigned' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <!-- -->
                                        <div class="mb-5 mt-2">
                                            <label for="platform" class="inputLabelMedium">Subscription Platform
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <select id="plan" name="platform" class="dropdownSelectInputs">
                                                <option value="mobile" {{ $tier?->platform === "mobile" ? 'selected' : '' }}>Mobile (Apple/Google Stores)</option>
                                                <option value="web" {{ $tier?->platform === "web" ? 'selected' : '' }}>Web (Stripe, etc.)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="mb-5 mt-2">
                                            <label for="name" class="inputLabelMedium">Package/Tier Name
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="name" class="mediumInput" value="{{ $tier?->name ?? null }}">
                                        </div>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="slogan" class="inputLabelMedium">Slogan</label>
                                            <input type="text" name="slogan" class="mediumInput" value="{{ $tier?->slogan ?? null }}">
                                        </div>
                                    </div>
                                    <!-- -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="mb-5 mt-2">
                                            <label for="starting_price" class="inputLabelMedium">Starting Price
                                                <span class="text-red-500">*</span>
                                                <span class="text-sm text-gray-400 font-light"> (First or
                                                    before-discount price)</span>
                                            </label>
                                            <input type="number" name="starting_price" class="mediumInput"
                                                value="{{ $tier?->starting_price ?? 0.00 }}" step="0.01">
                                        </div>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="final_price" class="inputLabelMedium">Final Price
                                                <span class="text-red-500">*</span>
                                                <span class="text-sm text-gray-400 font-light"> (Payable/Selling
                                                    price)</span>
                                            </label>
                                            <input type="number" name="final_price" class="mediumInput"
                                                value="{{ $tier?->final_price ?? 0.00 }}" step="0.01">
                                        </div>
                                    </div>
                                    <!-- -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="mb-5 mt-2">
                                            <label for="price_label" class="inputLabelMedium">Price Label
                                                <span class="text-sm text-gray-400 font-light"> (Like, Best Deal! Great
                                                    Offer!)</span>
                                            </label>
                                            <input type="text" name="price_label" class="mediumInput"
                                                 value="{{ $tier?->price_label ?? null }}">
                                        </div>
                                        <div class="mb-5 mt-2">
                                            <label for="duration" class="inputLabelMedium">Subscription duration
                                                <span class="text-red-500">*</span>
                                                <span class="text-sm text-gray-400 font-light"> (Integer in days. Like
                                                    monthly is 30,
                                                    6-month plan = 180)</span>
                                            </label>
                                            <input type="text" name="duration" class="mediumInput"
                                                 value="{{ $tier?->duration ?? null }}">
                                        </div>
                                    </div>

                                    <!-- -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="mb-5 mt-2">
                                            <label for="benefits" class="inputLabelMedium">Benefits
                                                <span class="text-sm text-gray-400 font-light"> (Max 2000 characters. Use Ctrl+Shift+V for safe pasting)</span>
                                            </label>
                                            <textarea class="summernote" name="benefits" id="benefits">
															{!! $tier?->benefits ?? null !!}
														  </textarea>
                                        </div>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="description" class="inputLabelMedium">Description
                                                <span class="text-sm text-gray-400 font-light"> (Max 2000 characters. Use Ctrl+Shift+V for safe pasting)</span>
                                            </label>
                                            <textarea class="summernote" name="description" id="description">
																{!! $tier?->description ?? null !!}
														  </textarea>
                                        </div>
                                    </div>
                                    <!--  -->
                                    <div class="grid grid-cols-2 gap-4 p-4 shadow-lg rounded-lg">
                                        <h3 class="col-span-2">Mobile Subscription <span class="text-base text-red-500">* (This section is only required for mobile subscriptions)</span></h3>
                                        <span class="col-span-2 text-sm text-gray-400 font-light">(This is just for
                                            display purpose on
                                            mobile/frontend devices. Doesn't change how app stores manage their
                                            subscription.)</span>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="rc_entitlement_id" class="inputLabelMedium">RevenueCat
                                                Entitlement ID
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="rc_entitlement_id" class="mediumInput" value="{{ $tier?->rc_entitlement_id ?? null }}">
                                        </div>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="apple_product_id" class="inputLabelMedium">Apple Product ID
                                            </label>
                                            <input type="text" name="apple_product_id" class="mediumInput" value="{{ $tier?->apple_product_id ?? null }}">
                                        </div>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="google_subscription_id" class="inputLabelMedium">Google
                                                Subscription ID
                                            </label>
                                            <input type="text" name="google_subscription_id" class="mediumInput" value="{{ $tier?->google_subscription_id ?? null }}">
                                        </div>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="google_base_plan_id" class="inputLabelMedium">Google Base Plan
                                                ID
                                            </label>
                                            <input type="text" name="google_base_plan_id" class="mediumInput" value="{{ $tier?->google_base_plan_id ?? null }}">
                                        </div>
                                    </div>
                                    <!-- -->
                                    <div class="grid grid-cols-2 gap-4 p-4 shadow-lg rounded-lg">
                                        <h3 class="col-span-2">Web Subscription <span class="text-base text-red-500">* (This section is only required for web based Stripe subscriptions)</span></h3>
                                        <span class="col-span-2 text-sm text-gray-400 font-light">(This is just for display purpose on websites. Doesn't change how Stripe manage their subscription. ❗ Wrong ID can lead to wrong payment process.)</span>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="stripe_product_id" class="inputLabelMedium">Stripe Product ID
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="stripe_product_id" class="mediumInput"
                                                 value="{{ $tier?->stripe_product_id ?? null }}">
                                        </div>
                                        <!--  -->
                                        <div class="mb-5 mt-2">
                                            <label for="stripe_price_id" class="inputLabelMedium">Stripe Price ID
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="stripe_price_id" class="mediumInput" value="{{ $tier?->stripe_price_id ?? null }}">
                                        </div>
                                    </div>
                                    <!-- -->

                                    <div class="my-5">
                                        <label for="image" class="inputLabelMedium">Tier Image
                                            <span class="text-sm text-gray-400 font-light"> (Max 1GB)</span>
                                        </label>

													 <input type="file" id="imageDropify" name="image" class="dropify" data-height="150"
    													@if ($tier?->image) data-default-file="{{ Storage::url($tier->image) }}" @endif />

                                        <input type="hidden" name="remove_image" id="remove_image" value="0">
                                    </div>
                                    <!-- -->
                                    <div class="mb-5 mt-2">
                                        <label for="status" class="inputLabelMedium">Active Status</label>
                                        <select name="status" class="dropdownSelectInputs">
                                            <option value="1" {{ (int) $tier?->status === 1 ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ (int) $tier?->status === 0 ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>

                                </div>
										  <div class="flex_x_between_y_center">
											  <button type="submit" class="showButton my-4">Update</button>
											</div>
										</form>
										<form action="{{ route('backend_subscription_tier_delete', $tier?->id) }}" method="POST"
											class="float-right">
											@csrf
											@method('DELETE')
											<button type="submit" class="deleteButton p-2"
												onclick="return confirm('Are you sure you want to delete this resource?');">
												{!! \App\Helpers\IconPack::trash(['class' => 'iconPackItem w-4 h-4 text-gray-100']) !!}
											</button>
										</form>
                        </div>
                    </section>
                    <!-- --------------------------------- -->
                </div>
            </div>
        </div>
        <!--  -->

    </div>

    <!---------------------------------------------------------------------------------------------- accordion -->

    <!-- ---------------- APP AREA Starts ---------------- -->

</x-app-layout>
