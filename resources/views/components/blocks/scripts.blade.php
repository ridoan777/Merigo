<!----------- DARK MODE TRIGGER ---------->
<script>
   (function () {
      const savedTheme = localStorage.getItem('hs_theme');
      const html = document.documentElement;

      if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
         html.classList.add('dark');
         html.classList.remove('light');
      } else {
         html.classList.add('light');
         html.classList.remove('dark');
      }

      html.classList.add('no-transitions');
   })();
</script>
<!----------- DARK MODE TRIGGER ---------->

<!----------- PRELINE INITIALIZER ---------->
<script>
   document.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => {
         if (window.HSStaticMethods && typeof HSStaticMethods.autoInit === 'function') {
            // HSStaticMethods.autoInit(['overlay'])  // not working in Laravel 13
            HSStaticMethods.autoInit()
         } else {
            console.warn('Preline overlay initialization failed: HSStaticMethods not found.')
         }
      }, 200)
   })
</script>
<!----------- PRELINE INITIALIZER ---------->

<!----------- DATATABLE ---------->
<script>
   // --------------------- ROW EVENTS (HOVER + CLICK) ---------------------
   window.dtApplyRowEvents = function (row, data) {
      $(row)
         .css('cursor', 'pointer')
         .attr('title', 'Click to view details')
         .on('click', function (e) {
            const $clickedCell = $(e.target).closest('td');
            if ($clickedCell.hasClass('no-row-click')) {
               return;
            }

            if ($(e.target).is('button, a, input, select, textarea')) {
               return;
            }

            const $interactiveParent = $(e.target).closest('button, a');
            if ($interactiveParent.length && $(row).find($interactiveParent).length) {
               return;
            }

            if (data.view_url) {
               window.location.href = data.view_url;
            }
         })
         .on('mouseenter', function () {
            $(this).css({ 'background-color': '#364153', 'color': '#fff' });
         })
         .on('mouseleave', function () {
            $(this).css({ 'background-color': '', 'color': '' });
         });
   };

   // --------------------- ROW EVENTS (NO-CLICKS) ---------------------
   $(document).on('click', '.row-check', function (e) {
      e.stopPropagation();
   });

   // --------------------- TOGGLE FORM (COMMON) ---------------------
   $(document).on('click', '.toggleForm button', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const form = $(this).closest('form');
      $.ajax({
         url: form.attr('action'),
         type: 'POST',
         data: form.serialize(),
         success: function (res) {
            if (window.dataTableDisplay) {
               window.dataTableDisplay.ajax.reload(null, false);
            }
         },
         error: function () {
            alert('Error toggling status.');
         }
      });
   });

   // --------------------- DELETE FORM (COMMON) ---------------------
   $(document).on('click', '.deleteForm button', function (e) {
      e.preventDefault();
      e.stopPropagation();
      // if (confirm('Are you sure you want to delete this?')) {
      //    $(this).closest('form').off('submit').submit();
      // }
      const message = $(this).data('confirm') || 'Are you sure you want to delete this?';

      if (confirm(message)) {
         $(this).closest('form').off('submit').submit();
      }
   });

   // --------------------- SELECT ALL CHECKBOX ---------------------
   $(document).on('change', '#select_all', function () {
      $('.row-check').prop('checked', $(this).is(':checked'));
   });
</script>
<!----------- DATATABLE ---------->

<!----------- DARK MODE FLICKERING/WHITE FLASH FIX ---------->
<script>
   document.addEventListener('DOMContentLoaded', function () {
      const html = document.documentElement;
      const toggle = document.getElementById('darkSwtich');

      if (!toggle) return;

      toggle.checked = html.classList.contains('dark');

      setTimeout(() => {
         html.classList.remove('no-transitions');
      }, 100);

      // Toggle handler
      toggle.addEventListener('change', (e) => {
         if (e.target.checked) {
            html.classList.add('dark');
            localStorage.setItem('hs_theme', 'dark');
         } else {
            html.classList.remove('dark');
            localStorage.setItem('hs_theme', 'light');
         }
      });
   });
</script>
<!----------- DARK MODE FLICKERING/WHITE FLASH FIX ---------->

<!----------- SUMMERNOTE ----------->
<script>
   $('.summernote').summernote({
      placeholder: 'Type here...',
      tabsize: 2,
      height: null,
      minHeight: 120,
      maxHeight: 300,
      focus: true,
      toolbar: [
         ['style', ['style']],
         ['font', ['bold', 'underline', 'clear']],
         ['color', ['color']],
         ['para', ['ul', 'ol', 'paragraph']],
         ['table', ['table']],
         ['insert', ['link', 'picture', 'video']],
         ['view', ['fullscreen', 'codeview', 'help']]
      ]
   });
</script>
<!----------- SUMMERNOTE ----------->


<!----------- DROPIFY INITIALIZER ----------->
<script src="{{ asset('site_assets/js/dropify.min.js') }}"></script>

<script>
   $(document).ready(function () {
      $('.dropify').dropify();
   });

   // $('.dropify').on('dropify.afterClear', function (event, element) {
   //    document.getElementById('remove_image').value = '1';
   // });

   $('#avatarDropify').on('dropify.afterClear', function (event, element) {
      document.getElementById('remove_avatar').value = '1';
   });

   $('#imageDropify').on('dropify.afterClear', function (event, element) {
      document.getElementById('remove_image').value = '1';
   });

   // For video removal
   $('#videoDropify').on('dropify.afterClear', function (event, element) {
      document.getElementById('remove_video').value = '1';
   });
</script>

<script>
   $.ajaxSetup({
      headers: {
         'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
   });
</script>
<!----------- DROPIFY INITIALIZER ----------->

<!----------- FILEPOND CHUNK UPLOAD ----------->
<script>
   const errorDiv = document.getElementById('filepond-error');
   const submitBtn = document.getElementById('submitButton');

   FilePond.registerPlugin(FilePondPluginFileValidateSize, FilePondPluginFileValidateType);

   document.addEventListener('DOMContentLoaded', () => {
      let uploadingCount = 0;

      const updateSubmitBtn = () => {
         if (submitBtn) submitBtn.disabled = uploadingCount > 0;
      };

      document.querySelectorAll('.filepond').forEach(inputElement => {
         const isMultiple = inputElement.hasAttribute('multiple');

         const pond = FilePond.create(inputElement, {
            instantUpload: true,
            allowProcess: true,
            chunkUploads: true,
            chunkSize: 5000000,
            chunkRetryDelays: [2000, 5000, 10000],
            allowMultiple: isMultiple,
            server: {
               url: "{{ route('filepond_process') }}",
               headers: {
                  'X-CSRF-TOKEN': '{{ csrf_token() }}'
               },
            },
            acceptedFileTypes: ['video/mp4', 'video/avi', 'video/mov', 'video/mpeg', 'video/quicktime'],
            allowFileTypeValidation: true,
            maxFileSize: '1024MB',
            allowFileSizeValidation: true,
            labelMaxFileSizeExceeded: 'File is too large (max 1024MB or 1GB)',
            onerror: (error) => {
               errorDiv.textContent = (error && (error.body || error.main)) || 'An error occurred during upload.';
            },
         });

         // Using pond.on() instead of inline callbacks. These fire ONLY on user actions
         pond.on('addfile', (error, file) => {
            if (error) return; // validation error, not a real upload
            uploadingCount++;
            updateSubmitBtn();
         });

         pond.on('processfile', (error, file) => {
            uploadingCount = Math.max(0, uploadingCount - 1);
            updateSubmitBtn();
            if (error) {
               errorDiv.textContent = (error && (error.body || error.main)) || 'An error occurred during upload.';
            } else {
               errorDiv.textContent = '';
            }
         });

         pond.on('processfilerevert', (file) => {
            // file was removed after upload completed, no counter change needed
         });

         pond.on('removefile', (error, file) => {
            // Only decrement if the file was still uploading (not yet completed)
            if (file.status !== 5) { // 5 = PROCESSING_COMPLETE
               uploadingCount = Math.max(0, uploadingCount - 1);
               updateSubmitBtn();
            }
         });
      });
   });
</script>
<!----------- FILEPOND CHUNK UPLOAD ----------->

<!----------- FLOWBITE JS (No Flowbite CSS/Style bcz it will break the dashboard made with Preline) ----------->
<script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
<!----------- FLOWBITE JS ----------->