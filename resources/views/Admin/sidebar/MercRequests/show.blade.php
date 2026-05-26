<x-app-layout>

    @php
        $breadCrumbs = [
            ['label' => 'All goods', 'url' => 'backend_merchandise_index', 'icon' => 'cubic'],
            ['label' => 'Good Requests', 'url' => 'backend_merc_requests_index', 'icon' => 'dollar'],
            ['label' => 'Request Details'],
        ];
    @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />

    <!-- Start Main Widgets -->
    <x-indicators.page_header_widget :pageName="'Request: ' . $mercRequest->merc_request_uid" />
    <!-- End Main Widgets -->


    <!-- Alert Component -->
    <x-indicators.alert-component message="Form Submission" />
    <!-- Alert Component -->

    <!-- -------------------------------- APP AREA Starts -------------------------------- -->
    <div class="px-6 py-2">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left Column: Request & User Info -->
            <div class="col-span-2 space-y-6">
                <section class="rounded-lg bg-white dark:bg-neutral-700 p-6 shadow-md">
                    <h3 class="text-lg font-semibold mb-4 border-b pb-2">Redemption Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="">
                                <p class="text-gray-500 text-xs uppercase">Phase</p>
                                <p class="font-medium text-blue-500">{{ ucfirst($mercRequest->phase) }}</p>
                            </div>
                            <div class="mt-4">
                                <p class="text-gray-500 text-xs uppercase">Req. Date</p>
                                <p class="font-medium">{{ $mercRequest->created_at->format('M d, Y h:i A') }}</p>
                            </div>

                        </div>
                        <div>
                            <p class="text-gray-500 text-xs uppercase">Requested By</p>
                            @php
                                $user = $mercRequest->merReqRelatingBackTo_User;
                                $userAvatar = \App\Helpers\Ui\GetUserAvatar::alignAvatar($user, true);
                            @endphp
                            <div class="flex items-center gap-3 mt-1">
                                <a href="{{ route('backend_single_user', $user->id) }}">
                                    <img src="{{ $userAvatar }}" alt="User" class="w-32 h-32 rounded-lg border">
                                </a>
                                <div>
                                    <p class="font-bold text-sm leading-tight">{{ $user?->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $user?->email }}</p>
                                </div>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-[11px]">
                                <div><span class="text-gray-400">UID:</span> {{ $user?->user_uid }}</div>
                                <div><span class="text-gray-400">Role:</span> <span
                                        class="capitalize">{{ $user?->user_role }}</span></div>
                                @if($user?->city)
                                    <div class="col-span-2"><span class="text-gray-400">City:</span> {{ $user->city }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-gray-500 text-xs uppercase mb-2">Shipping / Receiver Phone</p>
                        <div
                            class="bg-gray-50 dark:bg-neutral-800 p-4 rounded-md border border-gray-200 dark:border-neutral-600">
                            {!! $mercRequest->receiver_phone !!}
                        </div>
                        <p class="text-gray-500 text-xs uppercase my-2">Shipping / Receiver Address</p>
                        <div
                            class="bg-gray-50 dark:bg-neutral-800 p-4 rounded-md border border-gray-200 dark:border-neutral-600">
                            {!! $mercRequest->receiver_address !!}
                        </div>
                    </div>

                    @if($mercRequest->note)
                        <div class="mt-6">
                            <p class="text-gray-500 text-xs uppercase mb-2">User's Note</p>
                            <p class="text-sm italic text-gray-600 dark:text-gray-300">{{ $mercRequest->note }}</p>
                        </div>
                    @endif

                </section>

                <!-- Update Status Form -->
                <section class="rounded-lg bg-white dark:bg-neutral-700 p-6 shadow-md">
                    <h3 class="text-lg font-semibold mb-4 border-b pb-2">Update Status/Phase</h3>
                    <form method="POST" action="{{ route('backend_merc_requests_store', $mercRequest->id) }}">
                        @csrf
                        <div class="grid grid-cols-1 gap-4">
                            <input type="hidden" name="id" value="{{ $mercRequest->id }}">
                            <div>
                                <select name="phase" class="normalInput">
                                    <option value="pending" {{ $mercRequest->phase == 'pending' ? 'selected' : '' }}>
                                        Pending</option>
                                    <option value="incoming" {{ $mercRequest->phase == 'incoming' ? 'selected' : '' }}>Incoming</option>
                                    <option value="declined" {{ $mercRequest->phase == 'declined' ? 'selected' : '' }}>
                                        Declined</option>
                                    <option value="delivered" {{ $mercRequest->phase == 'delivered' ? 'selected' : '' }}>
                                        Delivered</option>
                                    <option value="cancelled" {{ $mercRequest->phase == 'cancelled' ? 'selected' : '' }}>
                                        Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="saveButton mt-4">Update Request</button>
                    </form>
                </section>
            </div>

            <!-- Right Column: Product Info -->
            <div class="col-span-1 space-y-6">
                <section class="rounded-lg bg-white dark:bg-neutral-700 p-6 shadow-md">
                    <h3 class="text-lg font-semibold mb-4 border-b pb-2">Product Information</h3>
                    @php
                        $merchandise = $mercRequest?->mercReqRelatingBackTo_Merchandise;
                    @endphp
                    <div class="text-center mb-4">
                        <a href="{{ route('backend_merchandise_show', $merchandise->id) }}">
                            @if($merchandise?->image)
                                <img src="{{ Storage::url($merchandise->image) }}" alt="Product"
                                    class="w-full h-48 object-cover rounded-lg shadow-sm">
                            @else
                                <div class="w-full h-48 bg-gray-200 flex items-center justify-center rounded-lg">
                                    <span class="text-gray-400">No Image</span>
                                </div>
                            @endif
                        </a>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-gray-500 text-xs uppercase">Product Name</p>
                            <p class="font-bold text-blue-600">{{ $merchandise?->name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs uppercase">Points Cost</p>
                            <p class="font-bold text-orange-500">{{ number_format($merchandise->points_cost) }} Points
                            </p>
                        </div>
                    </div>
                </section>
            </div>

        </div>
    </div>
    <!-- -------------------------------- APP AREA Starts -------------------------------- -->
</x-app-layout>