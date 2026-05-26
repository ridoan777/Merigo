@props([
   'id' => 'popup-modal',
   'title' => '',
   'message' => '',
   'confirmText' => '',
   'modalWidth' => 'max-w-5xl',
   'cancelText' => 'Cancel',
   'confirmColor' => 'bg-red-600 hover:bg-red-800 focus:ring-red-300 dark:focus:ring-red-800',
])

<div id="{{ $id }}" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-90 justify-center items-center w-full md:inset-0 max-h-full bg-[#00000086] mt-0!">
   <div class="relative w-full {{ $modalWidth }} max-h-[85vh] overflow-y-auto">
      <div class="relative bg-gray-100 rounded-lg shadow dark:bg-gray-700">
         <button type="button" data-modal-hide="{{ $id }}" class="absolute top-3 inset-e-2.5 z-10 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white">
         {!! \App\Helpers\IconPack::cross(['class' => 'iconPackItem w-5 h-5 text-gray-500']) !!}
         <span class="sr-only">Close modal</span>
      </button>

         <div class="p-5">

            <h2 class="mb-2 text-xl text-center font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
            <h3 class="mb-5 text-lg font-normal text-gray-500 dark:text-gray-400">{{ $message }}</h3>

            <section id="targetModalBlockToFill">
               {{ $slot }}
            </section>
				
         </div>
      </div>
   </div>
</div>

{{-- 
   public function show(MyStat $myStat)
	{
		$myStat->load(['myStatRelatingBackTo_User:id,user_uid,name,email,avatar']);
		$user = $myStat->myStatRelatingBackTo_User;

		return view('Admin.sidebar.My_stats.show', compact('myStat', 'user'));
	}
   
   public function myStatDatatable(Request $request)
	{
      $row->view_url = route('backend_my_stats_show', [
         'myStat' => $row->id,
         'slug' => $row->slug
      ]);
      ... ... ....
      $row->DT_RowAttr = DatatableHelper::rowClickBehaviour($row->view_url, 'modal', 'datatableViewModalTrigger', 'datatableViewModalContent');
      ... ... ...
      $row->DT_RowAttr = DatatableHelper::rowClickBehaviour($row->view_url, 'page');
      
      return $row;
   }

   <!----------- SHOW-PAGE MODAL ----------->
	<button type="button" id="datatableViewModalTrigger" data-modal-target="datatable-click-view-modal"
		data-modal-toggle="datatable-click-view-modal" class="hidden"></button>

	<x-ui_items.modals.show-modal id="datatable-click-view-modal" title="My-Stat Details" modalWidth="max-w-6xl">
		<section id="datatableViewModalContent"></section>
	</x-ui_items.modals.show-modal>
	<!----------- SHOW-PAGE MODAL ----------->

--}}