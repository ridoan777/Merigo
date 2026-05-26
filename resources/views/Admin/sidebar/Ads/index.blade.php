<x-app-layout>

    @php
        $breadCrumbs = [
            ['label' => 'Create Ads', 'url' => 'backend_ads_create', 'icon' => 'plus'],
            ['label' => 'Ad List'],
        ];
    @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />

    <!-- Start Main Widgets -->
    <x-indicators.page_header_widget pageName="Ad List" />
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
        <a href="{{ route('backend_ads_create') }}" class="darkButton">+ Create</a>
        <div class="">
            <form action="{{ route('backend_ads_bulk_delete') }}" method="POST">
                @csrf
                <x-ui_items.datatable.datatable-btn-group exportRoute="backend_projects_spreadsheet_export"
                    importRoute="backend_projects_excel_import" spreadsheetButton="false" importButton="false"
                    pdfButton="false" deleteButton="true" />
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
                            <th class="min-w-36">Title</th>
                            <th class="min-w-40">Details</th>
                            <th>Image</th>
                            <th class="min-w-24">Schedule</th>
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
    <!-- -------------------------------- APP AREA Starts -------------------------------- -->

    <!-- --------------------------------- -->
    <script>
        $(function () {
            window.dataTableDisplay = $('#dataTableDisplay').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ordering: true,
                scrollX: true,
                ajax: {
                    url: '{{ route("backend_ads_datatable") }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                },
                columns: [
                    { data: 'checkbox', className: 'no-row-click dt-body-center', orderable: false, searchable: false },
                    { data: 'SN', className: 'px-3 py-2 text-center', orderable: true, searchable: false },
                    { data: 'id', className: 'px-3 py-2 text-center', orderable: true, searchable: false },
                    { data: 'ad_uid', className: 'px-3 py-2 text-center', orderable: true, searchable: true },
                    { data: 'title_block', className: 'px-3 py-2 text-left', orderable: true, searchable: true },
                    { data: 'description_block', className: 'px-3 py-2 text-left', orderable: true, searchable: true },
                    { data: 'image_block', className: 'px-3 py-2 text-center', orderable: false, searchable: false },
                    { data: 'schedule_block', className: 'px-3 py-2 text-center', orderable: true, searchable: true },
                    { data: 'status_label', className: 'px-3 py-2 text-center', orderable: true, searchable: true },
                    { data: 'created_at_formatted', className: 'px-3 py-2 text-center', orderable: true, searchable: true },
                    { data: 'actions', className: 'px-3 py-2 text-center', orderable: false, searchable: false }
                ],
                pageLength: 25,
                createdRow: function (row, data) {
                    dtApplyRowEvents(row, data);
                }
            });
        });
    </script>
    <!-- --------------------------------- -->

</x-app-layout>