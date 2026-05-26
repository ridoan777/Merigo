<x-app-layout>

	@php
		$breadCrumbs = [
			['label' => 'Tiers', 'url' => 'backend_subscription_tiers_index', 'icon' => 'dollarSack'],
		];
	 @endphp

	<x-indicators.breadcrumb :crumbs="$breadCrumbs" />

	<!-- Start Main Widgets -->
	<x-indicators.page_header_widget pageName="Subscriptions: Tiers/Packages" />
	<!-- End Main Widgets -->

	<!-- Alert Component -->
	<x-indicators.alert-component message="Form Submission" />
	<!-- Alert Component -->

	@php
		$ORDER_IN_PAGE = 1;
		$ACCORDION_1_VISIBILITY = 'hidden'; // hidden or ''
		$ACCORDION_2_VISIBILITY = '';
		$ACCORDION_3_VISIBILITY = '';
	@endphp

	<!-- -------------------------------- APP AREA Starts -------------------------------- -->
	<div class="hs-accordion-group">

		<x-ui_items.accordion.accordion-destroy />

		<div id="hs-destroy-heading-one"
			class="hs-accordion-to-destroy hs-accordion hs-accordion-active:border-gray-200 PrelineAccordionItemParent">

			<x-ui_items.accordion.accordion_toggle_button toggleLabel="Create New Tiers" />

			<!-- --------------------------------- -->
			<div id="hs-destroy-collapse-one"
				class="hs-accordion-content {{ $ACCORDION_1_VISIBILITY }} w-full overflow-hidden transition-[height] duration-300"
				role="region" aria-labelledby="hs-destroy-heading-one">
				<div class="p-3">
					<form method="POST" action="{{ route('backend_subscription_tier_store') }}"
						class="rounded-lg p-2 dark:bg-neutral-700" enctype="multipart/form-data">
						@csrf

						<!--- IDENTIFIER HIDDEN INPUTS -->
						<input type="hidden" name="flag" class="smallInput" value="create">
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
											<option value="{{ $value->id }}">{{ $index + 1 }}.
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
										<option value="mobile">Mobile (Apple/Google Stores)</option>
										<option value="web">Web (Stripe, etc.)</option>
									</select>
								</div>
							</div>
							<!-- -->
							<div class="grid grid-cols-2 gap-4">
								<div class="mb-5 mt-2">
									<label for="name" class="inputLabelMedium">Package/Tier Name
										<span class="text-red-500">*</span>
									</label>
									<input type="text" name="name" class="mediumInput" placeholder="max 255-characters">
								</div>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="slogan" class="inputLabelMedium">Slogan</label>
									<input type="text" name="slogan" class="mediumInput" placeholder="max 255-characters">
								</div>
							</div>
							<!-- -->
							<div class="grid grid-cols-2 gap-4">
								<div class="mb-5 mt-2">
									<label for="starting_price" class="inputLabelMedium">Starting Price
										<span class="text-red-500">*</span>
										<span class="text-sm text-gray-400 font-light"> (First or before-discount price)</span>
									</label>
									<input type="number" name="starting_price" class="mediumInput" placeholder="numbers only"
										step="0.01">
								</div>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="final_price" class="inputLabelMedium">Final Price
										<span class="text-red-500">*</span>
										<span class="text-sm text-gray-400 font-light"> (Payable/Selling price)</span>
									</label>
									<input type="number" name="final_price" class="mediumInput" placeholder="numbers only"
										step="0.01">
								</div>
							</div>
							<!-- -->
							<div class="grid grid-cols-2 gap-4">
								<div class="mb-5 mt-2">
									<label for="price_label" class="inputLabelMedium">Price Label
										<span class="text-sm text-gray-400 font-light"> (Like, Best Deal! Great Offer!)</span>
									</label>
									<input type="text" name="price_label" class="mediumInput" placeholder="max 255-characters">
								</div>
								<div class="mb-5 mt-2">
									<label for="duration" class="inputLabelMedium">Subscription duration
										<span class="text-red-500">*</span>
										<span class="text-sm text-gray-400 font-light"> (Integer in days. Like monthly is 30,
											6-month plan = 180)</span>
									</label>
									<input type="text" name="duration" class="mediumInput" placeholder="max 255-characters">
								</div>
							</div>

							<!-- -->
							<div class="grid grid-cols-2 gap-4">
								<div class="mb-5 mt-2">
									<label for="benefits" class="inputLabelMedium">Benefits
										<span class="text-sm text-gray-400 font-light"> (Max 2000 characters. Use
											Ctrl+Shift+V for safe pasting)</span>
									</label>
									<textarea class="summernote" name="benefits" id="benefits"></textarea>
								</div>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="description" class="inputLabelMedium">Description
										<span class="text-sm text-gray-400 font-light"> (Max 2000 characters. Use
											Ctrl+Shift+V for safe pasting)</span>
									</label>
									<textarea class="summernote" name="description" id="description"></textarea>
								</div>
							</div>
							<!--  -->
							<div class="grid grid-cols-2 gap-4 p-4 shadow-lg rounded-lg">
								<h3 class="col-span-2">Mobile Subscription <span class="text-base text-red-500">* (This section
										is only required for mobile subscriptions)</span></h3>
								<span class="col-span-2 text-sm text-gray-400 font-light">(This is just for display purpose on
									mobile/frontend devices. Doesn't change how app stores manage their subscription.)</span>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="rc_entitlement_id" class="inputLabelMedium">RevenueCat Entitlement ID
										<span class="text-red-500">*</span>
									</label>
									<input type="text" name="rc_entitlement_id" class="mediumInput">
								</div>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="apple_product_id" class="inputLabelMedium">Apple Product ID
									</label>
									<input type="text" name="apple_product_id" class="mediumInput">
								</div>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="google_subscription_id" class="inputLabelMedium">Google Subscription ID
									</label>
									<input type="text" name="google_subscription_id" class="mediumInput">
								</div>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="google_base_plan_id" class="inputLabelMedium">Google Base Plan ID
									</label>
									<input type="text" name="google_base_plan_id" class="mediumInput">
								</div>
							</div>
							<!-- -->
							<div class="grid grid-cols-2 gap-4 p-4 shadow-lg rounded-lg">
								<h3 class="col-span-2">Web Subscription <span class="text-base text-red-500">* (This section is
										only required for web based Stripe subscriptions)</span></h3>
								<span class="col-span-2 text-sm text-gray-400 font-light">(This is just for display purpose on
									websites. Doesn't change how Stripe manage their subscription. ❗ Wrong ID can lead to wrong
									payment process.)</span>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="stripe_product_id" class="inputLabelMedium">Stripe Product ID
										<span class="text-red-500">*</span>
									</label>
									<input type="text" name="stripe_product_id" class="mediumInput" placeholder="integers only">
								</div>
								<!--  -->
								<div class="mb-5 mt-2">
									<label for="stripe_price_id" class="inputLabelMedium">Stripe Price ID
										<span class="text-red-500">*</span>
									</label>
									<input type="text" name="stripe_price_id" class="mediumInput">
								</div>
							</div>
							<!-- -->

							<div class="my-5">
								<label for="image" class="inputLabelMedium">Tier Image
									<span class="text-sm text-gray-400 font-light"> (Max 1GB)</span>
								</label>

								<input type="file" id="imageDropify" name="image" class="dropify" data-height="150" />

								<input type="hidden" name="remove_image" id="remove_image" value="0">
							</div>
							<!-- -->
							<div class="mb-5 mt-2">
								<label for="status" class="inputLabelMedium">Active Status</label>
								<select name="status" class="dropdownSelectInputs">
									<option value="1">Active</option>
									<option value="0">Inactive</option>
								</select>
							</div>

						</div>

						<button type="submit" class="saveButton my-4">Create Module</button>
					</form>
				</div>
			</div>
			<!-- --------------------------------- -->
		</div>
		<!--------->
		<div id="hs-destroy-heading-two"
			class="hs-accordion-to-destroy hs-accordion hs-accordion-active:border-gray-200 PrelineAccordionItemParent">

			<x-ui_items.accordion.accordion_toggle_button toggleLabel="All Tiers::Web" />

			<!-- --------------------------------- -->
			<div id="hs-destroy-collapse-two"
				class="hs-accordion-content {{ $ACCORDION_2_VISIBILITY }} w-full overflow-hidden transition-[height] duration-300"
				role="region" aria-labelledby="hs-destroy-heading-two">
				<div class="p-3 grid grid-cols-3 gap-8">

					@foreach ($webTiers as $index => $tier)
						<div class="p-4 rounded-2xl border hover:bg-emerald-50 hover:shadow-lg cursor-pointer">
							<div class="flex_x_between_y_center">
								<h3 class="text-gray-400">[{{ $tier?->id }}] {{ $tier?->name ?? "N/A" }}</h3>
								<b class="capitalize">{{ $tier?->platform ?? "N/A" }}</b>
							</div>
							<div class="flex justify-between">
								<h5 class="appTextBlue">Slogan: {{ $tier?->slogan ?? 'N/A' }}</h5>
								{{-- --}}
								<div>
									<a href="{{ route('backend_subscription_tier_show', $tier->id) }}" class="showButton p-1">
										{!! \App\Helpers\IconPack::edit(['class' => 'iconPackItem w-3 h-3 text-gray-100']) !!}
									</a>
									<form action="{{ route('backend_subscription_tier_delete', $tier->id) }}" method="POST"
										class="inline-block">
										@csrf
										@method('DELETE')
										<button type="submit" class="deleteButton p-1"
											onclick="return confirm('Are you sure you want to delete this resource?');">
											{!! \App\Helpers\IconPack::trash(['class' => 'iconPackItem w-3 h-3 text-gray-100']) !!}
										</button>
									</form>
								</div>
							</div>
							<div class="flex items-center gap-8">
								<h3>{{ $tier?->final_price ?? 'N/A' }}$</h3>
								<h4 class="line-through">{{ $tier?->starting_price ?? 'N/A' }}$</h4>
								<p class="text-gray-500">{{ $tier?->duration ?? 'N/A' }} day(s)
								</p>
							</div>
							<h6>Label: {{ $tier?->price_label ?? 'N/A' }}</h6>
							<ul class="text-sm">{!! $tier?->benefits ?? 'N/A' !!}</ul>
						</div>
					@endforeach

				</div>
			</div>
			<!-- --------------------------------- -->

		</div>
		<!--------->
		<div id="hs-destroy-heading-three"
			class="hs-accordion-to-destroy hs-accordion hs-accordion-active:border-gray-200 PrelineAccordionItemParent">

			<x-ui_items.accordion.accordion_toggle_button toggleLabel="All Tiers::Mobile" />

			<!-- --------------------------------- -->
			<div id="hs-destroy-collapse-three"
				class="hs-accordion-content {{ $ACCORDION_3_VISIBILITY }} w-full overflow-hidden transition-[height] duration-300"
				role="region" aria-labelledby="hs-destroy-heading-three">
				<div class="p-3 grid grid-cols-3 gap-8">

					@foreach ($mobileTiers as $index => $tier)
						<div class="p-4 rounded-2xl border shadow-lg">
							<div class="flex_x_between_y_center">
								<h3 class="text-gray-400">[{{ $tier?->id }}] {{ $tier?->name ?? "N/A" }}</h3>
								<b class="capitalize">{{ $tier?->platform ?? "N/A" }}</b>
							</div>
							<div class="flex justify-between">
								<h5 class="appTextBlue">Slogan: {{ $tier?->slogan ?? 'N/A' }}</h5>
								{{-- --}}
								<div>
									<a href="{{ route('backend_subscription_tier_show', $tier->id) }}" class="showButton p-1">
										{!! \App\Helpers\IconPack::edit(['class' => 'iconPackItem w-3 h-3 text-gray-100']) !!}
									</a>
									<form action="{{ route('backend_subscription_tier_delete', $tier->id) }}" method="POST"
										class="inline-block">
										@csrf
										@method('DELETE')
										<button type="submit" class="deleteButton p-1"
											onclick="return confirm('Are you sure you want to delete this resource?');">
											{!! \App\Helpers\IconPack::trash(['class' => 'iconPackItem w-3 h-3 text-gray-100']) !!}
										</button>
									</form>
								</div>
							</div>
							<div class="flex items-center gap-8">
								<h3>{{ $tier?->final_price ?? 'N/A' }}$</h3>
								<h4 class="line-through">{{ $tier?->starting_price ?? 'N/A' }}$</h4>
								<p class="text-gray-500">{{ $tier?->duration ?? 'N/A' }} day(s)
								</p>
							</div>
							<h6>Label: {{ $tier?->price_label ?? 'N/A' }}</h6>
							<ul class="text-sm">{!! $tier?->benefits ?? 'N/A' !!}</ul>
						</div>
					@endforeach

				</div>
			</div>
			<!-- --------------------------------- -->

		</div>
		<!-- -------------------------------- APP AREA Starts -------------------------------- -->


</x-app-layout>