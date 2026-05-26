<x-app-layout>

    @php
        $breadCrumbs = [
            ['label' => 'Create Bar'],
            ['label' => 'Bar List', 'url' => 'backend_bar_index'],
        ];
    @endphp

    <x-indicators.breadcrumb :crumbs="$breadCrumbs" />

    <!-- Page Header -->
    <x-indicators.page_header_widget pageName="Bar: Create" />

    <!-- Alert -->
    <x-indicators.alert-component message="Form Submission" />

    <div class="px-6 py-2">

        <section class="displayArea mx-auto my-5 rounded-lg bg-white p-5 shadow-md">

            <form method="POST" action="{{ route('backend_bar_store')}} " enctype="multipart/form-data">
                @csrf

                <!-- Hidden flag -->
                <input type="hidden" name="flag" value="create">

                <!-- ================= Manager ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">
                        Bar <span class="text-red-500">*</span>
                    </label>

                    <select name="bar_admin_id" class="dropdownSelectInputs" required>
                        <option value="">Select Bar Admin</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- ================= Bar Name ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">
                        Bar Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" class="mediumInput" placeholder="Enter bar name" required>
                </div>

                <!-- ================= PTS ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Point to earn</label>
                    <input type="text" name="earning_points" class="mediumInput" placeholder="Positive integers only (min: 0)">
                </div>

                <!-- ================= Contact ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Contact</label>
                    <input type="text" name="contact" class="mediumInput" placeholder="Phone / Mobile">
                </div>

                <!-- ================= Address ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">Address</label>
                    <input type="text" name="address" class="mediumInput" placeholder="Full address">
                </div>

                <!-- ================= City ================= -->
                <div class="mb-5">
                    <label class="inputLabelMedium">City</label>
                    <input type="text" name="city" class="mediumInput" placeholder="City name">
                </div>

                <!-- ================= Location ================= -->
                <div class="grid grid-cols-2 gap-4">

                    <div>
                        <label class="inputLabelMedium">Latitude</label>
                        <input type="text" name="latitude" class="mediumInput" placeholder="e.g. 23.8103">
                    </div>

                    <div>
                        <label class="inputLabelMedium">Longitude</label>
                        <input type="text" name="longitude" class="mediumInput" placeholder="e.g. 90.4125">
                    </div>
                    {{-- <div>
                        <label class="inputLabelMedium">Choose place</label>
                        <input type="text" id="search" placeholder="Search location..." class="mediumInput form-control">
                        <ul id="suggestions"></ul>
                    </div> --}}

                </div>

                <!-- ================= Image ================= -->
                <div class="my-5">
                    <label class="inputLabelMedium">
                        Bar Image
                        <span class="text-sm text-gray-400">(Max 10MB)</span>
                    </label>

                    <input type="file" name="image" class="dropify" data-height="150" />

                    <input type="hidden" name="remove_image" id="remove_image" value="0">
                </div>

                <!-- ================= Submit ================= -->
                <button type="submit" class="saveButton mt-4">
                    Create Bar
                </button>

            </form>

        </section>

    </div>

    <!-- Dropify Script -->
    <script>
        $(document).ready(function () {
            const dr = $('.dropify').dropify();

            dr.on('dropify.afterClear', function () {
                $('#remove_image').val(1);
            });

            dr.on('change', function () {
                $('#remove_image').val(0);
            });
        });
    </script>

    <script>
        const apiKey = "{{ config('services.maptiler.secret') }}";
        const input = document.getElementById('search');
        const suggestionsBox = document.getElementById('suggestions');

        input.addEventListener('input', async function () {
            const query = this.value;

            if (query.length < 3) return;

            const res = await fetch(`https://api.maptiler.com/geocoding/${query}.json?key=${apiKey}&autocomplete=true&country=us`);
            const data = await res.json();

            suggestionsBox.innerHTML = '';

            data.features.forEach(place => {
                const li = document.createElement('li');
                li.textContent = place.place_name;

                li.addEventListener('click', () => {
                    input.value = place.place_name;

                    const lat = place.center[1];
                    const lng = place.center[0];

                    console.log('Selected:', { lat, lng });

                    // ----- send to Laravel
                    fetch('/admin/user/get-locationsave-location', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ lat, lng, name: place.place_name })
                    });

                    suggestionsBox.innerHTML = '';
                });

                suggestionsBox.appendChild(li);
            });
        });
    </script>

</x-app-layout>