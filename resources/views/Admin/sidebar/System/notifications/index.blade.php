<x-app-layout>

	@php
		$breadCrumbs = [
			['label' => 'Notifications', 'url' => 'backend_system_in_app_notify_index', 'icon' => 'bell'],
			['label' => 'In-App-Notifications'],
		];
	 @endphp

	<x-indicators.breadcrumb :crumbs="$breadCrumbs" />
	<!-- ---------------- APP AREA Starts ---------------- -->

	<!-- Start Main Widgets -->
	<x-indicators.page_header_widget pageName="List: All In-App Notifications" />
	<!-- End Main Widgets -->


	<!-- Alert Component -->
	<x-indicators.alert-component message="Form Submission" />
	<!-- Alert Component -->

	@php
		$ORDER_IN_PAGE = 1;
		$ACCORDION_1_VISIBILITY = ''; // hidden or ''
	 @endphp

	<!---------------------------------------------------------------------------------------------- accordion -->
	<div class="hs-accordion-group">

		<x-ui_items.accordion.accordion-destroy />

		<div id="hs-destroy-heading-two"
			class="hs-accordion-to-destroy hs-accordion hs-accordion-active:border-gray-200 PrelineAccordionItemParent">

			<x-ui_items.accordion.accordion_toggle_button toggleLabel="In-App" />

			<div id="hs-destroy-collapse-two"
				class="hs-accordion-content {{ $ACCORDION_1_VISIBILITY }} w-full overflow-hidden transition-[height] duration-300"
				role="region" aria-labelledby="hs-destroy-heading-two">
				<div class="p-3">
					<!-- --------------------------------- -->
					<section
						class="displayArea mx-auto my-5 rounded-lg border-none bg-white p-4 shadow-md shadow-gray-400 dark:bg-neutral-700 dark:shadow-none">
						<div class="my-5">
							<div class="overflow-x-auto">
								<form action="{{ route('backend_system_in_app_notify_bulk_delete') }}" method="POST">
									@csrf
									<!------->
									<x-ui_items.datatable.datatable-btn-group exportRoute="backend_projects_spreadsheet_export" importRoute="backend_projects_excel_import" spreadsheetButton="false" importButton="false" pdfButton="false" deleteButton="true" />
									<!------->
									<table id="dataTableDisplay" class="dataTableDisplay min-w-full text-sm border border-gray-300 bg-white rounded-xl shadow-lg">
										<thead class="text-xs text-gray-700 uppercase bg-gray-100 rounded-t-xl">
											<tr>
												<th class="no-row-click dt-body-center">
													<input id="select_all" type="checkbox"
														class="w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">
												</th>
												<th>SN</th>
												<th>DB ID</th>
												<th>Trigger</th>
												<th>Type</th>
												<th>User</th>
												<th>Focus</th>
												<th>Message</th>
												<th>Action</th>
												<th>Status</th>
												<th>Created At</th>
												<th>Action</th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
								</form>
							</div>
						</div>
					</section>
					<!-- --------------------------------- -->
				</div>
			</div>
		</div>
		<!--  -->

	</div>
	<!-- --------------------------------- -->
	<x-ui_items.modals.save-file-modal id="hs-confirm-modal" title="Import File"
		message="Upload your Excel/CSV file to process." formAction="#" formMethod="POST" confirmText="Upload"
		cancelText="Cancel" instructionImg="site_assets/report_upload_guide/excel_projects_import.png" />

	<!-- -------------------------------- APP AREA Starts -------------------------------- -->


	{{-- SCRIPT FOR ALL USERS DATA-TABLE:SERVICE CATEGORY --}}
	<script>
		$(function () {
			window.dataTableDisplay = $('#dataTableDisplay').DataTable({
				processing: true,
				serverSide: true,
				responsive: true,
				scrollX: true,
				ajax: {
					url: '{{ route('backend_system_in_app_notify_datatable') }}',
					type: 'POST',
					headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
				},
				columns: [
					{ data: 'checkbox', orderable: false, searchable: false },
					{ data: 'SN' },
					{ data: 'id' },
					{ data: 'trigger_place' },
					{ data: 'type', orderable: false },
					{ data: 'user', orderable: false, searchable: false },
					{ data: 'focus', orderable: false, searchable: false },
					{ data: 'message' },
					{ data: 'action', orderable: false, searchable: false },
					{ data: 'status_label', orderable: false, searchable: false },
					{ data: 'created_at_formatted' },
					{ data: 'actions', orderable: false, searchable: false }
				],
				pageLength: 25,
				createdRow: function (row, data) {
					dtApplyRowEvents(row, data);
				}
			});

		});
	</script>
	{{-- SCRIPT FOR ALL USERS DATA-TABLE --}}


	<style>
		table thead th {
			text-align: center !important;
		}

		.dataTableDisplay tbody tr:nth-child(even) {
			background-color: #e5e7eb;
			/* bg-gray-200 */
		}

		.dataTableDisplay tbody tr:nth-child(odd) {
			background-color: #f3f4f6;
			/* bg-gray-100 */
		}
	</style>



	<!-- ---------------- APP AREA Starts ---------------- -->

</x-app-layout>