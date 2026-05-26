<x-app-layout>

    @php
    $breadCrumbs = [
    ['label' => 'Create Bar', 'url' => 'backend_bar_create', 'icon' => 'layers'],
    ['label' => 'Bar List'],
    ];
    @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />

    <!-- Start Main Widgets -->
    <x-indicators.page_header_widget pageName="Bars: List" />
    <!-- End Main Widgets -->


    <!-- Alert Component -->
    <x-indicators.alert-component message="Form Submission" />
    <!-- Alert Component -->

    @php
    $ORDER_IN_PAGE = 1;
    $ACCORDION_1_VISIBILITY = ''; // hidden or ''
    @endphp

    <!-- -------------------------------- APP AREA Starts -------------------------------- -->
    <div class="px-6 py-2">

        <div class="">
            <form action="{{ route('backend_bars_bulk_delete') }}" method="POST">
                @csrf
                <x-ui_items.datatable.datatable-btn-group exportRoute="backend_projects_spreadsheet_export" importRoute="backend_projects_excel_import" spreadsheetButton="false" importButton="false" pdfButton="false" deleteButton="true" />
                <!------->
                <table id="dataTableDisplay"
                    class="dataTableDisplay min-w-full text-sm border border-gray-300 bg-white rounded-xl shadow-lg">
                    <thead class="text-xs text-gray-400 uppercase bg-gray-100 rounded-t-xl">
                        <tr>
                            <th class="no-row-click dt-body-center">
                                <input id="select_all" type="checkbox"
                                    class="w-4 h-4 rounded border-gray-400 cursor-pointer checked:bg-blue-600 checked:border-blue-600">
                            </th>
                            <th>SN</th>
                            <th>DB ID</th>
                            <th class="w-20">UID</th>
                            <th class="min-w-40">Owner/Admin</th>
                            <th class="min-w-26">Bar Name</th>
                            <th>Earning Pts</th>
                            <th>Deals</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Image</th>
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
    <!-- --------------------------------- -->
    {{-- <x-ui_items.modals.save-file-modal id="hs-confirm-modal" title="Import File"
        message="Upload your Excel/CSV file to process." formAction="#" formMethod="POST" confirmText="Upload"
        cancelText="Cancel" instructionImg="site_assets/report_upload_guide/excel_projects_import.png" /> --}}

    <!-- -------------------------------- APP AREA Starts -------------------------------- -->

    {{-- SCRIPT FOR ALL USERS DATA-TABLE:SERVICE CATEGORY --}}
    <script>
        $(function() {
            window.dataTableDisplay = $('#dataTableDisplay').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ordering: true,
                scrollX: true,
                ajax: {
                    url: '{{ route('backend_bar_datatable') }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                },
                columns: [
                    { data: 'checkbox', className: 'no-row-click dt-body-center', orderable: false, searchable: false },
                    { data: 'SN', className: 'px-3 py-2 text-center' },
                    { data: 'bar_id', className: 'px-3 py-2 text-center' },
                    { data: 'bar_uid', className: 'px-3 py-2 text-center' },
                    { data: 'manager', className: 'px-3 py-2 text-center' },
                    { data: 'name', className: 'px-3 py-2 text-center' },
                    { data: 'earning_point', className: 'px-3 py-2 text-center' },
                    { data: 'deals', className: 'px-3 py-2 text-center' },
                    { data: 'contact', className: 'px-3 py-2 text-left' },
                    { data: 'address_formatted', className: 'px-3 py-2 text-center', orderable: false, searchable: false },
                    { data: 'image_preview', className: 'px-3 py-2 text-center', orderable: false, searchable: false }, // ----- center align
                    { data: 'status_label', className: 'px-3 py-2 text-center', orderable: false, searchable: false },
                    { data: 'created_at_formatted', className: 'px-3 py-2 text-center' },
                    { data: 'actions', className: 'px-3 py-2 text-center', orderable: false, searchable: false }
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

</x-app-layout>